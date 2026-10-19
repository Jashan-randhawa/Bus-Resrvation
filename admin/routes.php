<?php
// admin/routes.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/admin-crud.php';

// Detect primary key column for route table
$route_pk = table_has_column($link, 'route', 'sno') ? 'sno' : 'id';

// Handle Add Route (O11, O12, Phase A Item 1)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add'])) {
    csrf_verify();
    require_role('super_admin', 'operator');
    $from = trim(preg_replace('/\s+/', ' ', (string)($_POST['From'] ?? '')));
    $to = trim(preg_replace('/\s+/', ' ', (string)($_POST['To'] ?? '')));
    $bus = trim((string)($_POST['bus'] ?? ''));
    $time = trim((string)($_POST['time'] ?? ''));
    $price = (float)($_POST['price'] ?? 0);

    if ($from === '' || $to === '' || $bus === '' || $time === '' || $price <= 0) {
        $_SESSION['form_old'] = ['From' => $from, 'To' => $to, 'bus' => $bus, 'time' => $time, 'price' => $price];
        flash_set('danger', 'Please fill all route fields with valid values.');
    } elseif (strcasecmp($from, $to) === 0) {
        $_SESSION['form_old'] = ['From' => $from, 'To' => $to, 'bus' => $bus, 'time' => $time, 'price' => $price];
        flash_set('danger', 'Origin and destination cities cannot be the same.');
    } else {
        $has_archived_col = table_has_column($link, 'route', 'archived_at');
        $conflict_where = $has_archived_col ? " AND archived_at IS NULL" : "";
        // O11 & Issue 12: Check for route conflict on active schedules only
        $conflict = db_one($link, "SELECT * FROM route WHERE busno = ? AND `time` = ?{$conflict_where}", 'ss', [$bus, $time]);
        if ($conflict) {
            $_SESSION['form_old'] = ['From' => $from, 'To' => $to, 'bus' => $bus, 'time' => $time, 'price' => $price];
            flash_set('danger', "Bus '{$bus}' is already scheduled to depart at {$time} ({$conflict['city1']} -> {$conflict['city2']}).");
        } else {
            // Issue 13: Bus assignment validation (must exist and not be archived)
            $has_bus_arch = table_has_column($link, 'buses', 'archived_at');
            $bus_where = $has_bus_arch ? " AND archived_at IS NULL" : "";
            $bus_row = db_one($link, "SELECT id FROM buses WHERE bus_number = ?{$bus_where} LIMIT 1", 's', [$bus]);
            if (!$bus_row || empty($bus_row['id'])) {
                $_SESSION['form_old'] = ['From' => $from, 'To' => $to, 'bus' => $bus, 'time' => $time, 'price' => $price];
                flash_set('danger', "The selected bus '{$bus}' does not exist or is inactive/archived.");
            } else {
                unset($_SESSION['form_old']);
                $bus_id = (int)$bus_row['id'];
                $has_bus_id = table_has_column($link, 'route', 'bus_id');

                if ($has_bus_id) {
                    db_exec($link,
                        "INSERT INTO route (city1, city2, busno, time, price, bus_id) VALUES (?, ?, ?, ?, ?, ?)",
                        'ssssdi',
                        [$from, $to, $bus, $time, $price, $bus_id]
                    );
                } else {
                    db_exec($link,
                        "INSERT INTO route (city1, city2, busno, time, price) VALUES (?, ?, ?, ?, ?)",
                        'ssssd',
                        [$from, $to, $bus, $time, $price]
                    );
                }
                audit($link, 'CREATE', 'route', (int)mysqli_insert_id($link), null, ['city1' => $from, 'city2' => $to, 'busno' => $bus, 'time' => $time, 'price' => $price]);
                flash_set('success', 'Route added successfully.');
            }
        }
    }
    $redirect_url = BASE_URL . '/admin/routes.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
}

$has_archived_col = table_has_column($link, 'route', 'archived_at');

// Handle Delete/Archive Route (O10, Phase B Item 4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_route'])) {
    csrf_verify();
    require_role('super_admin');
    $delete_id = (int)($_POST['delete_id'] ?? 0);
    if ($delete_id > 0) {
        $route_row = db_one($link, "SELECT * FROM route WHERE `{$route_pk}` = ?", 'i', [$delete_id]);
        if (!$route_row) {
            flash_set('danger', 'Route not found.');
        } else {
            $r_bus = (string)$route_row['busno'];
            $r_time = (string)$route_row['time'];
            $today = date('Y-m-d');
            $active_bookings = db_one($link,
                "SELECT COUNT(*) AS n FROM booking WHERE (route_id = ? OR (bus = ? AND `time` = ?)) AND `date` >= ? AND (status IS NULL OR status NOT IN ('Cancelled', 'Expired'))",
                'isss', [$delete_id, $r_bus, $r_time, $today]
            );

            if ((int)($active_bookings['n'] ?? 0) > 0) {
                flash_set('danger', "Cannot archive route ({$route_row['city1']} -> {$route_row['city2']} at {$r_time}) because there are active upcoming bookings.");
            } else {
                if ($has_archived_col) {
                    admin_archive_record($link, 'route', $route_pk, $delete_id, 'route', ['city1' => $route_row['city1'], 'city2' => $route_row['city2'], 'busno' => $r_bus, 'time' => $r_time]);
                    flash_set('success', 'Route archived successfully.');
                } else {
                    admin_archive_record($link, 'route', $route_pk, $delete_id, 'route', ['city1' => $route_row['city1'], 'city2' => $route_row['city2'], 'busno' => $r_bus, 'time' => $r_time]);
                    flash_set('success', 'Route deleted successfully.');
                }
            }
        }
    }
    $redirect_url = BASE_URL . '/admin/routes.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
}

// Handle Restore Route (Phase B Item 4)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['restore_route'])) {
    csrf_verify();
    require_role('super_admin');
    $restore_id = (int)($_POST['restore_id'] ?? 0);
    if ($restore_id > 0 && $has_archived_col) {
        $route_row = db_one($link, "SELECT * FROM route WHERE `{$route_pk}` = ?", 'i', [$restore_id]);
        if ($route_row) {
            admin_restore_record($link, 'route', $route_pk, $restore_id, 'route');
            flash_set('success', "Route schedule ({$route_row['city1']} -> {$route_row['city2']}) restored successfully.");
        }
    }
    $redirect_url = BASE_URL . '/admin/routes.php' . (!empty($_GET) ? '?' . http_build_query($_GET) : '');
    header('Location: ' . $redirect_url, true, 303);
    exit;
}

// Tab Filter: active vs archived
$view_tab = admin_get_archive_tab();

// Distinct known cities for datalist auto-suggest (A16) & origin dropdown
$known_cities = db_all($link, "SELECT DISTINCT city1 AS city FROM route UNION SELECT DISTINCT city2 AS city FROM route ORDER BY city ASC");
$known_origins = db_all($link, "SELECT DISTINCT city1 AS city FROM route ORDER BY city1 ASC");

// Buses with fleet metadata
$has_buses_arch = table_has_column($link, 'buses', 'archived_at');
$bus_arch_where = $has_buses_arch ? "WHERE archived_at IS NULL" : "";
$has_cap = table_has_column($link, 'buses', 'capacity');
$has_layout = table_has_column($link, 'buses', 'layout');
$bus_select_fields = "bus_number" . ($has_cap ? ", capacity" : "") . ($has_layout ? ", layout" : "");
$buses = db_all($link, "SELECT {$bus_select_fields} FROM buses {$bus_arch_where} ORDER BY bus_number ASC");

// Route operational metrics (based on active schedules)
$active_stat_where = $has_archived_col ? "WHERE archived_at IS NULL" : "";
$route_stats = db_one($link, "
    SELECT 
        COUNT(*) AS total_active_routes,
        COUNT(DISTINCT busno) AS allocated_buses,
        COALESCE(AVG(price), 0) AS avg_tariff,
        COALESCE(MIN(price), 0) AS min_tariff,
        COALESCE(MAX(price), 0) AS max_tariff
    FROM route {$active_stat_where}
") ?? [];

$total_active_routes = (int)($route_stats['total_active_routes'] ?? 0);
$allocated_buses = (int)($route_stats['allocated_buses'] ?? 0);
$avg_tariff = (float)($route_stats['avg_tariff'] ?? 0);
$unique_cities_count = count($known_cities);

// Filter & Search parameters
$search_q = trim((string)($_GET['q'] ?? ''));
$filter_origin = trim((string)($_GET['origin'] ?? ''));
$filter_bus = trim((string)($_GET['bus'] ?? ''));
$sort = (string)($_GET['sort'] ?? 'id_asc');

$where_clauses = [];
$params = [];
$types = '';

if ($has_archived_col && $view_tab === 'archived') {
    $where_clauses[] = 'r.archived_at IS NOT NULL';
} elseif ($has_archived_col) {
    $where_clauses[] = 'r.archived_at IS NULL';
}

if ($search_q !== '') {
    $where_clauses[] = '(r.city1 LIKE ? OR r.city2 LIKE ? OR r.busno LIKE ?)';
    $like = '%' . $search_q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($filter_origin !== '') {
    $where_clauses[] = 'r.city1 = ?';
    $params[] = $filter_origin;
    $types .= 's';
}

if ($filter_bus !== '') {
    $where_clauses[] = 'r.busno = ?';
    $params[] = $filter_bus;
    $types .= 's';
}

$where_sql = !empty($where_clauses) ? 'WHERE ' . implode(' AND ', $where_clauses) : '';

switch ($sort) {
    case 'time_asc':
        $order_by = 'r.`time` ASC, r.city1 ASC';
        break;
    case 'time_desc':
        $order_by = 'r.`time` DESC, r.city1 ASC';
        break;
    case 'price_asc':
        $order_by = 'r.price ASC, r.city1 ASC';
        break;
    case 'price_desc':
        $order_by = 'r.price DESC, r.city1 ASC';
        break;
    case 'route_asc':
        $order_by = 'r.city1 ASC, r.city2 ASC';
        break;
    case 'id_desc':
        $order_by = "r.`{$route_pk}` DESC";
        break;
    default:
        $order_by = "r.`{$route_pk}` ASC";
        break;
}

// 25-item Pagination (P-10) with filter awareness
if (!empty($params)) {
    $count_row = db_one($link, "SELECT COUNT(*) AS c FROM route r {$where_sql}", $types, $params);
    $total_routes = (int)($count_row['c'] ?? 0);
} else {
    $total_routes = (int)(db_one($link, "SELECT COUNT(*) AS c FROM route r {$where_sql}")['c'] ?? 0);
}
$pagination = paginate($total_routes, 25);

// Fetch routes joined with bus fleet specs
$has_bus_id_col = table_has_column($link, 'route', 'bus_id');
$join_cond = $has_bus_id_col 
    ? "(r.bus_id = b.id OR (r.bus_id IS NULL AND r.busno = b.bus_number))" 
    : "(r.busno = b.bus_number)";

$cap_select = $has_cap ? ", b.capacity AS bus_capacity" : ", 36 AS bus_capacity";
$layout_select = $has_layout ? ", b.layout AS bus_layout" : ", '2+2' AS bus_layout";

$query_sql = "
    SELECT r.* {$cap_select} {$layout_select}
    FROM route r
    LEFT JOIN buses b ON {$join_cond}
    {$where_sql}
    ORDER BY {$order_by}
    LIMIT ? OFFSET ?
";

$fetch_params = array_merge($params, [$pagination['per_page'], $pagination['offset']]);
$fetch_types = $types . 'ii';
$routes = db_all($link, $query_sql, $fetch_types, $fetch_params);

$keep_params = [];
if ($view_tab === 'archived') $keep_params['tab'] = 'archived';
if ($search_q !== '') $keep_params['q'] = $search_q;
if ($filter_origin !== '') $keep_params['origin'] = $filter_origin;
if ($filter_bus !== '') $keep_params['bus'] = $filter_bus;
if ($sort !== 'id_asc') $keep_params['sort'] = $sort;

$form_old = $_SESSION['form_old'] ?? [];
unset($_SESSION['form_old']);

$title = 'Routes';
require_once __DIR__ . '/../includes/layout/header-admin.php';
?>
<div class="page-header d-flex flex-wrap align-items-center justify-content-between">
    <div class="mb-2 mb-md-0">
        <h1 class="page-title">Transit Route Schedules</h1>
        <p class="page-subtitle">Configure origins, destinations, bus allocations, departures, and ticket tariffs.</p>
    </div>
    <?php if (can_write()): ?>
    <button class="btn btn-primary shadow-sm font-weight-bold d-inline-flex align-items-center" data-toggle="modal" data-target="#addRouteModal" style="min-height: 44px;">
        <span class="mr-1">➕</span> Create Route Schedule
    </button>
    <?php endif; ?>
</div>

<!-- Route Operational KPI Metric Cards -->
<div class="row mb-4">
    <div class="col-6 col-lg-3 mb-3 mb-lg-0">
        <div class="stat-card stat-routes h-100">
            <div class="stat-label">Active Schedules</div>
            <div class="stat-value"><?= number_format($total_active_routes) ?></div>
            <div class="stat-link text-primary small">Operational trips &rarr;</div>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3 mb-lg-0">
        <div class="stat-card stat-fleet h-100">
            <div class="stat-label">Fleet Allocated</div>
            <div class="stat-value"><?= number_format($allocated_buses) ?></div>
            <div class="stat-link text-success small">Buses in service &rarr;</div>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3 mb-lg-0">
        <div class="stat-card stat-bookings h-100">
            <div class="stat-label">Connected Cities</div>
            <div class="stat-value"><?= number_format($unique_cities_count) ?></div>
            <div class="stat-link text-info small">Network hubs &rarr;</div>
        </div>
    </div>
    <div class="col-6 col-lg-3 mb-3 mb-lg-0">
        <div class="stat-card stat-revenue h-100">
            <div class="stat-label">Average Tariff</div>
            <div class="stat-value"><?= CURRENCY ?><?= number_format($avg_tariff, 2) ?></div>
            <div class="stat-link text-muted small">Range: <?= CURRENCY ?><?= number_format((float)($route_stats['min_tariff'] ?? 0), 0) ?> &ndash; <?= CURRENCY ?><?= number_format((float)($route_stats['max_tariff'] ?? 0), 0) ?></div>
        </div>
    </div>
</div>

<?= $has_archived_col ? admin_archive_tabs_html($view_tab, 'Active Routes', 'Archived Schedules', array_filter(['q' => $search_q, 'origin' => $filter_origin, 'bus' => $filter_bus, 'sort' => $sort !== 'id_asc' ? $sort : null])) : '' ?>

<!-- Smart Search & Filter Toolbar -->
<div class="filter-bar mb-4">
    <form action="" method="get" class="w-100 d-flex flex-wrap align-items-center">
        <?php if ($view_tab === 'archived'): ?>
            <input type="hidden" name="tab" value="archived">
        <?php endif; ?>
        
        <div class="d-none d-md-flex align-items-center mr-2 mb-2 mb-md-0">
            <span class="filter-label">Filter:</span>
        </div>

        <div class="flex-grow-1 mr-2 mb-2 mb-md-0" style="min-width: 180px;">
            <div class="input-group input-group-sm">
                <div class="input-group-prepend">
                    <span class="input-group-text bg-white border-right-0">🔍</span>
                </div>
                <input type="text" name="q" class="form-control form-control-sm border-left-0" placeholder="Search cities or bus..." value="<?= e($search_q) ?>" aria-label="Search routes">
            </div>
        </div>

        <div class="mr-2 mb-2 mb-md-0" style="min-width: 130px;">
            <select name="origin" class="form-control form-control-sm" aria-label="Filter by origin city">
                <option value="">All Origins</option>
                <?php foreach ($known_origins as $orig): ?>
                    <?php if (!empty($orig['city'])): ?>
                        <option value="<?= e($orig['city']) ?>" <?= $filter_origin === $orig['city'] ? 'selected' : '' ?>><?= e($orig['city']) ?></option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mr-2 mb-2 mb-md-0" style="min-width: 120px;">
            <select name="bus" class="form-control form-control-sm" aria-label="Filter by assigned bus">
                <option value="">All Buses</option>
                <?php foreach ($buses as $b): ?>
                    <option value="<?= e($b['bus_number']) ?>" <?= $filter_bus === $b['bus_number'] ? 'selected' : '' ?>><?= e($b['bus_number']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mr-2 mb-2 mb-md-0" style="min-width: 140px;">
            <select name="sort" class="form-control form-control-sm" aria-label="Sort order">
                <option value="id_asc" <?= $sort === 'id_asc' ? 'selected' : '' ?>>Sort: ID (Default)</option>
                <option value="time_asc" <?= $sort === 'time_asc' ? 'selected' : '' ?>>Departure (Earliest)</option>
                <option value="time_desc" <?= $sort === 'time_desc' ? 'selected' : '' ?>>Departure (Latest)</option>
                <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Tariff (Low &rarr; High)</option>
                <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Tariff (High &rarr; Low)</option>
                <option value="route_asc" <?= $sort === 'route_asc' ? 'selected' : '' ?>>Route (Origin A-Z)</option>
            </select>
        </div>

        <div class="d-flex align-items-center mb-2 mb-md-0">
            <button type="submit" class="btn btn-sm btn-primary mr-1" style="min-height: 31px;">Apply</button>
            <?php if ($search_q !== '' || $filter_origin !== '' || $filter_bus !== '' || $sort !== 'id_asc'): ?>
                <a href="<?= BASE_URL ?>/admin/routes.php<?= $view_tab === 'archived' ? '?tab=archived' : '' ?>" class="btn btn-sm btn-outline-secondary" title="Reset all filters" style="min-height: 31px;">Reset</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Desktop & Tablet Route Table View -->
<div class="data-table-wrapper mb-4">
    <div class="table-header">
        <h5 class="mb-0"><?= $view_tab === 'archived' ? 'Archived Schedules' : 'Active Schedules' ?></h5>
        <span class="record-count"><?= $pagination['total_records'] ?> route schedule(s)</span>
    </div>

    <!-- Desktop Table View (Hidden on mobile <768px for zero horizontal scroll) -->
    <div class="table-responsive d-none d-md-block">
        <table class="table table-hover table-stack mb-0">
            <caption class="sr-only">List of transit route schedules, origin, destination, assigned buses, and fares</caption>
            <thead class="thead-light">
                <tr>
                    <th scope="col" class="th-id-md">#</th>
                    <th scope="col">Route Corridor</th>
                    <th scope="col">Assigned Fleet Bus</th>
                    <th scope="col">Departure</th>
                    <th scope="col">Tariff</th>
                    <th scope="col" class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($routes)): ?>
                    <tr>
                        <td colspan="6">
                            <div class="empty-state py-5">
                                <div class="empty-icon">🗺️</div>
                                <div class="empty-title"><?= $view_tab === 'archived' ? 'No archived schedules' : 'No routes matched' ?></div>
                                <div class="empty-text"><?= $view_tab === 'archived' ? 'Schedules you archive will appear here.' : ($search_q !== '' || $filter_origin !== '' ? 'Try adjusting your search criteria.' : "Click '+ Create Route Schedule' above to connect travel cities.") ?></div>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($routes as $row): ?>
                        <?php 
                        $rid = (int)($row[$route_pk] ?? $row['sno'] ?? $row['id'] ?? 0);
                        $is_archived = !empty($row['archived_at']);
                        $bus_num = (string)($row['busno'] ?? '');
                        $dep_time = (string)($row['time'] ?? '');
                        ?>
                        <tr class="<?= $is_archived ? 'text-muted bg-light' : '' ?>">
                            <td data-label="ID"><span class="text-muted small font-weight-bold">#<?= e($rid) ?></span></td>
                            <td data-label="Route Corridor">
                                <div class="route-corridor">
                                    <span class="route-city font-weight-bold text-dark"><?= e($row['city1'] ?? '') ?></span>
                                    <span class="route-arrow text-primary">&rarr;</span>
                                    <span class="route-city font-weight-bold text-dark"><?= e($row['city2'] ?? '') ?></span>
                                    <?php if ($is_archived): ?>
                                        <span class="badge badge-secondary ml-1">Archived</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Assigned Fleet Bus">
                                <div class="d-inline-flex align-items-center">
                                    <span class="badge badge-light border text-dark font-weight-bold py-1 px-2">
                                        🚌 <?= e($bus_num) ?>
                                    </span>
                                    <?php if (!empty($row['bus_capacity'])): ?>
                                        <small class="text-muted ml-1 font-weight-medium">(<?= e($row['bus_capacity']) ?>s, <?= e($row['bus_layout'] ?? '2+2') ?>)</small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td data-label="Departure">
                                <div class="d-inline-flex align-items-center">
                                    <span class="font-weight-bold text-dark"><?= e(fmt_time($dep_time)) ?></span>
                                    <span class="text-muted small ml-1 font-monospace">(<?= e($dep_time) ?>)</span>
                                </div>
                            </td>
                            <td data-label="Tariff">
                                <span class="font-weight-bold text-success h6 mb-0"><?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?></span>
                            </td>
                            <td data-label="Actions" class="text-right">
                                <div class="d-inline-flex align-items-center justify-content-end">
                                    <a href="<?= BASE_URL ?>/admin/manifest.php?bus=<?= urlencode($bus_num) ?>&time=<?= urlencode($dep_time) ?>" class="btn btn-outline-info btn-sm mr-1" title="View Passenger Manifest">
                                        📋 Manifest
                                    </a>
                                    <?= render_crud_action_buttons($rid, BASE_URL . "/admin/edit/edit-route.php?id=" . $rid, $is_archived, 'delete_route', 'restore_route', 'route schedule') ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile Stacked Route Cards (<768px for touch friendliness and 0 horizontal overflow) -->
    <div class="d-md-none">
        <?php if (empty($routes)): ?>
            <div class="empty-state py-5">
                <div class="empty-icon">🗺️</div>
                <div class="empty-title"><?= $view_tab === 'archived' ? 'No archived schedules' : 'No routes matched' ?></div>
                <div class="empty-text"><?= $view_tab === 'archived' ? 'Schedules you archive will appear here.' : "Click '+ Create Route Schedule' above to connect travel cities." ?></div>
            </div>
        <?php else: ?>
            <?php foreach ($routes as $row): ?>
                <?php 
                $rid = (int)($row[$route_pk] ?? $row['sno'] ?? $row['id'] ?? 0);
                $is_archived = !empty($row['archived_at']);
                $bus_num = (string)($row['busno'] ?? '');
                $dep_time = (string)($row['time'] ?? '');
                ?>
                <div class="route-card-mobile <?= $is_archived ? 'route-archived' : '' ?> p-3">
                    <div class="route-card-header">
                        <span class="small font-weight-bold text-muted">Schedule #<?= e($rid) ?></span>
                        <?php if ($is_archived): ?>
                            <span class="badge badge-secondary px-2 py-1">Archived</span>
                        <?php else: ?>
                            <span class="badge badge-success px-2 py-1">Active</span>
                        <?php endif; ?>
                    </div>

                    <div class="text-center py-1 mb-3">
                        <div class="h5 font-weight-bold text-dark text-uppercase mb-0 tracking-wide">
                            <span><?= e($row['city1'] ?? '') ?></span>
                            <span class="text-primary mx-2 font-weight-bold">&rarr;</span>
                            <span><?= e($row['city2'] ?? '') ?></span>
                        </div>
                    </div>

                    <div class="row no-gutters route-meta-strip text-center py-2 mb-3">
                        <div class="col-4 border-right px-1">
                            <small class="text-muted d-block font-weight-bold" style="font-size: 11px;">FLEET BUS</small>
                            <span class="font-weight-bold text-dark small">🚌 <?= e($bus_num) ?></span>
                        </div>
                        <div class="col-4 border-right px-1">
                            <small class="text-muted d-block font-weight-bold" style="font-size: 11px;">DEPARTS</small>
                            <span class="font-weight-bold text-dark small">🕒 <?= e(fmt_time($dep_time)) ?></span>
                        </div>
                        <div class="col-4 px-1">
                            <small class="text-muted d-block font-weight-bold" style="font-size: 11px;">TARIFF</small>
                            <strong class="text-success small font-weight-bold"><?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?></strong>
                        </div>
                    </div>

                    <div class="route-card-actions pt-2 border-top">
                        <?php if (!$is_archived && can_write()): ?>
                            <a href="<?= BASE_URL ?>/admin/edit/edit-route.php?id=<?= $rid ?>" class="btn btn-outline-primary btn-sm font-weight-bold d-inline-flex align-items-center justify-content-center" style="min-height: 44px;">
                                ✏️ Edit
                            </a>
                        <?php endif; ?>
                        <a href="<?= BASE_URL ?>/admin/manifest.php?bus=<?= urlencode($bus_num) ?>&time=<?= urlencode($dep_time) ?>" class="btn btn-outline-info btn-sm font-weight-bold d-inline-flex align-items-center justify-content-center" style="min-height: 44px;">
                            📋 Manifest
                        </a>
                        <?php if ($is_archived && is_super_admin()): ?>
                            <form method="post" action="" onsubmit="return confirm('Restore this route schedule to active status?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="restore_id" value="<?= e($rid) ?>">
                                <button type="submit" name="restore_route" class="btn btn-outline-success btn-sm font-weight-bold d-inline-flex align-items-center justify-content-center" style="min-height: 44px;">
                                    ♻️ Restore
                                </button>
                            </form>
                        <?php elseif (!$is_archived && is_super_admin()): ?>
                            <form method="post" action="" onsubmit="return confirm('Archive this route schedule? It can be restored later from the archive tab.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_id" value="<?= e($rid) ?>">
                                <button type="submit" name="delete_route" class="btn btn-outline-danger btn-sm font-weight-bold d-inline-flex align-items-center justify-content-center" style="min-height: 44px;">
                                    🗑️ Archive
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <?= render_pagination($pagination, $keep_params) ?>
</div>

<!-- Add Route Modal -->
<div class="modal fade" id="addRouteModal" tabindex="-1" role="dialog" aria-labelledby="addRouteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white px-3 py-3">
                <h5 class="modal-title font-weight-bold" id="addRouteModalLabel">🗺️ Configure New Route Schedule</h5>
                <button type="button" class="close text-white p-0" data-dismiss="modal" aria-label="Close" style="min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body p-3 p-sm-4">
                <form action="" method="post">
                    <?= csrf_field() ?>
                    <datalist id="citySuggestions">
                        <?php foreach ($known_cities as $kc): ?>
                            <?php if (!empty($kc['city'])): ?>
                                <option value="<?= e(trim($kc['city'])) ?>">
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </datalist>

                    <!-- Origin, Destination & Interactive Swap Button -->
                    <div class="form-row align-items-center">
                        <div class="col-12 col-sm-5 form-group">
                            <label for="From" class="font-weight-bold small text-muted">From City (Origin)</label>
                            <input type="text" id="From" name="From" list="citySuggestions" class="form-control" value="<?= e($form_old['From'] ?? '') ?>" placeholder="e.g. New York" required autocomplete="off" />
                        </div>
                        <div class="col-12 col-sm-2 text-center form-group d-flex align-items-center justify-content-center pt-sm-4 mb-2 mb-sm-3">
                            <button type="button" id="swapModalCitiesBtn" class="btn btn-outline-primary btn-sm rounded-circle d-inline-flex align-items-center justify-content-center shadow-sm" style="width: 40px; height: 40px; min-height: 40px;" title="Swap Origin and Destination" aria-label="Swap Origin and Destination">
                                ⇄
                            </button>
                        </div>
                        <div class="col-12 col-sm-5 form-group">
                            <label for="To" class="font-weight-bold small text-muted">To City (Destination)</label>
                            <input type="text" id="To" name="To" list="citySuggestions" class="form-control" value="<?= e($form_old['To'] ?? '') ?>" placeholder="e.g. Boston" required autocomplete="off" />
                        </div>
                    </div>

                    <!-- Fleet Bus Assignment with Capacity and Layout Preview -->
                    <div class="form-group">
                        <label for="bus" class="font-weight-bold small text-muted">Assigned Fleet Bus</label>
                        <select name="bus" id="bus" class="form-control" required>
                            <option value="">Select Fleet Bus</option>
                            <?php foreach ($buses as $b): ?>
                                <?php 
                                $b_num = $b['bus_number'];
                                $b_cap = $b['capacity'] ?? 36;
                                $b_lyt = $b['layout'] ?? '2+2';
                                $b_label = $b_num . ' (' . $b_cap . ' seats, ' . $b_lyt . ')';
                                ?>
                                <option value="<?= e($b_num) ?>" <?= ($form_old['bus'] ?? '') === $b_num ? 'selected' : '' ?>>
                                    🚌 <?= e($b_label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Departure Time & Tariff -->
                    <div class="form-row">
                        <div class="col-12 col-sm-6 form-group">
                            <label for="time" class="font-weight-bold small text-muted">Departure Time</label>
                            <input type="time" id="time" name="time" class="form-control" value="<?= e($form_old['time'] ?? '') ?>" required />
                        </div>
                        <div class="col-12 col-sm-6 form-group">
                            <label for="price" class="font-weight-bold small text-muted">Ticket Tariff (<?= CURRENCY ?>)</label>
                            <input type="number" step="0.01" min="1" id="price" name="price" class="form-control" value="<?= e((string)($form_old['price'] ?? '')) ?>" placeholder="0.00" required />
                        </div>
                    </div>

                    <!-- Modal Actions with Mobile Stacking -->
                    <div class="d-flex flex-column-reverse flex-sm-row justify-content-end mt-4">
                        <button type="button" class="btn btn-secondary mr-sm-2 btn-block-mobile" data-dismiss="modal" style="min-height: 44px;">Cancel</button>
                        <button type="submit" class="btn btn-primary font-weight-bold shadow-sm mb-2 mb-sm-0 btn-block-mobile" name="add" style="min-height: 44px;">Save Route Schedule</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var fromInput = document.getElementById('From');
    var toInput = document.getElementById('To');
    var swapBtn = document.getElementById('swapModalCitiesBtn');
    var form = fromInput ? fromInput.closest('form') : null;

    if (swapBtn && fromInput && toInput) {
        swapBtn.addEventListener('click', function(e) {
            e.preventDefault();
            var tmp = fromInput.value;
            fromInput.value = toInput.value;
            toInput.value = tmp;
            this.style.transform = (this.style.transform === 'rotate(180deg)') ? 'rotate(0deg)' : 'rotate(180deg)';
        });
    }

    if (form && fromInput && toInput) {
        form.addEventListener('submit', function(e) {
            var f = fromInput.value.trim().toLowerCase();
            var t = toInput.value.trim().toLowerCase();
            if (f !== '' && f === t) {
                e.preventDefault();
                alert('Origin and destination cities cannot be the same.');
                toInput.focus();
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>