<?php
// user/index.php -- Search Bus Routes & Journey Booking (Phase 2.5, 3.2, 3.3, 3.5, 4.3)
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$cust_id = (int)($_SESSION['uid'] ?? 0);

// Phase 4.3: Query passenger's next upcoming journey
$next_trip = db_one($link,
    "SELECT b.*, r.price, r.time AS sched_time
     FROM booking b
     LEFT JOIN route r ON (b.city1 = r.city1 AND b.city2 = r.city2 AND b.bus = r.busno)
     WHERE b.id = ? AND (b.status NOT IN ('Cancelled', 'Expired') OR b.status IS NULL)
       AND (b.date > CURDATE() OR (b.date = CURDATE() AND b.time >= CURTIME()))
     ORDER BY b.date ASC, b.time ASC
     LIMIT 1",
    'i', [$cust_id]
);

$from_cities = db_all($link, 'SELECT DISTINCT city1 FROM route ORDER BY city1 ASC');
$to_cities = db_all($link, 'SELECT DISTINCT city2 FROM route ORDER BY city2 ASC');

// Phase 3.2: Connected route mapping for client-side dynamic filtering
$route_pairs = db_all($link, 'SELECT DISTINCT city1, city2 FROM route ORDER BY city1 ASC, city2 ASC');
$routes_map = [];
foreach ($route_pairs as $rp) {
    $routes_map[$rp['city1']][] = $rp['city2'];
}

// U-13: Switch to GET parameters for bookmarkable, shareable, idempotent search
$search_from = trim((string)($_GET['from'] ?? ''));
$search_to = trim((string)($_GET['to'] ?? ''));
$search_date = trim((string)($_GET['date'] ?? ''));
$sort = trim((string)($_GET['sort'] ?? 'time_asc'));

$matched_routes = [];
$booked_counts = [];
$searched = false;
$alert = null;
$alert_type = 'info';

// Run hold expiration sweep once per request (U-11)
release_expired_holds($link);

if (isset($_GET['from']) || isset($_GET['to']) || isset($_GET['date'])) {
    $searched = true;

    if ($search_from === '' || $search_to === '') {
        $alert = 'Please select both departure and destination cities.';
        $alert_type = 'warning';
    } elseif ($search_from === $search_to) {
        $alert = 'Departure city and destination city cannot be the same.';
        $alert_type = 'danger';
    } else {
        $date_chk = validate_travel_datetime($search_date);
        if (!$date_chk['ok']) {
            $alert = $date_chk['error'];
            $alert_type = 'danger';
        } else {
            // U-11: Optimized batch query joining capacity in a single database round-trip
            $matched_routes = db_all(
                $link,
                'SELECT r.*, COALESCE(b.capacity, 36) AS bus_capacity
                 FROM route r
                 LEFT JOIN buses b ON r.busno = b.bus_number
                 WHERE r.city1 = ? AND r.city2 = ?
                 ORDER BY r.time ASC',
                'ss',
                [$search_from, $search_to]
            );

            // Fetch taken seat counts in one grouped query
            if (!empty($matched_routes)) {
                $buses = array_values(array_unique(array_column($matched_routes, 'busno')));
                $placeholders = implode(',', array_fill(0, count($buses), '?'));
                $types = 's' . str_repeat('s', count($buses));
                $params = array_merge([$search_date], $buses);

                $counts_rows = db_all(
                    $link,
                    "SELECT bus, `time`, COUNT(*) AS taken_count
                     FROM booking
                     WHERE `date` = ? AND status IN ('Confirmed', 'Pending') AND bus IN ({$placeholders})
                     GROUP BY bus, `time`",
                    $types,
                    $params
                );

                foreach ($counts_rows as $cr) {
                    $key = $cr['bus'] . '::' . substr((string)$cr['time'], 0, 5);
                    $booked_counts[$key] = (int)$cr['taken_count'];
                }

                // Phase 3.2: Sorting controls (departure time, price, available seats)
                if ($sort === 'price_asc') {
                    usort($matched_routes, fn($a, $b) => (float)$a['price'] <=> (float)$b['price']);
                } elseif ($sort === 'seats_desc') {
                    usort($matched_routes, function($a, $b) use ($booked_counts) {
                        $capA = (int)($a['bus_capacity'] ?? 36);
                        $capB = (int)($b['bus_capacity'] ?? 36);
                        $tA = $booked_counts[$a['busno'] . '::' . substr((string)$a['time'], 0, 5)] ?? 0;
                        $tB = $booked_counts[$b['busno'] . '::' . substr((string)$b['time'], 0, 5)] ?? 0;
                        $openA = max(0, $capA - $tA);
                        $openB = max(0, $capB - $tB);
                        return $openB <=> $openA;
                    });
                }
            }
        }
    }
}

$title = 'Search Bus Routes';
require_once __DIR__ . '/../includes/layout/header-user.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Find &amp; Book Bus Routes</h1>
        <p class="page-subtitle">Select your departure city, destination, and travel date to see available seats.</p>
    </div>
</div>

<?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<!-- Phase 4.3: Your Next Trip Banner -->
<?php if ($next_trip): ?>
    <div class="card border-0 shadow-sm mb-4 bg-light border-left border-primary" style="border-left-width: 5px !important;">
        <div class="card-body p-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div>
                <span class="badge badge-success text-uppercase font-weight-bold px-2 py-1 mb-2">Upcoming Journey</span>
                <h4 class="font-weight-bold text-dark mb-1">
                    <?= e($next_trip['city1']) ?> &rarr; <?= e($next_trip['city2']) ?>
                </h4>
                <p class="text-muted mb-0 small">
                    📅 <?= e(fmt_date($next_trip['date'])) ?> at <?= e(fmt_time($next_trip['time'])) ?> &bull;
                    🚌 Bus #<?= e($next_trip['bus']) ?> &bull;
                    🪑 Seat #<?= e($next_trip['seat']) ?> &bull;
                    PNR: <strong class="text-dark font-monospace"><?= e($next_trip['pnr']) ?></strong>
                </p>
            </div>
            <div class="mt-3 mt-md-0">
                <a href="<?= BASE_URL ?>/user/ticket.php?pnr=<?= urlencode($next_trip['pnr']) ?>" class="btn btn-primary px-4 py-2 font-weight-bold shadow-sm">
                    View Boarding Pass &rarr;
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 font-weight-bold">Search Journey</h5>
    </div>
    <div class="card-body p-4">
        <!-- U-13: GET search form for bookmarkable results -->
        <form action="" method="get" id="search-form">
            <div class="form-row align-items-center">
                <div class="col-md-4 form-group">
                    <label for="from" class="font-weight-bold small text-muted">Departure City</label>
                    <select name="from" class="form-control" id="from" required>
                        <option value="">Select Origin</option>
                        <?php foreach ($from_cities as $row): ?>
                            <option value="<?= e($row['city1']) ?>" <?= $search_from === $row['city1'] ? 'selected' : '' ?>>
                                <?= e($row['city1']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-1 form-group text-center d-none d-md-block pt-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="swap-cities-btn" title="Swap Origin and Destination" style="margin-top: 8px;">
                        ⇄
                    </button>
                </div>

                <div class="col-md-3 form-group">
                    <label for="to" class="font-weight-bold small text-muted">Destination City</label>
                    <select name="to" class="form-control" id="to" required>
                        <option value="">Select Destination</option>
                        <?php foreach ($to_cities as $row): ?>
                            <option value="<?= e($row['city2']) ?>" <?= $search_to === $row['city2'] ? 'selected' : '' ?>>
                                <?= e($row['city2']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-4 form-group">
                    <label for="date" class="font-weight-bold small text-muted">Travel Date</label>
                    <input type="date" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+90 days')) ?>" name="date" id="date" value="<?= e($search_date ?: date('Y-m-d')) ?>" class="form-control" required />
                </div>
            </div>
            <div class="d-flex justify-content-between align-items-center mt-2">
                <button type="button" class="btn btn-outline-secondary btn-sm d-md-none" id="swap-cities-btn-mobile">
                    ⇄ Swap Cities
                </button>
                <button type="submit" class="btn btn-primary px-4 py-2 font-weight-bold shadow-sm ml-auto">
                    🔍 Find Available Buses
                </button>
            </div>
            <div id="swap-hint" class="small text-danger mt-2 font-weight-medium" style="display:none;" role="alert"></div>
        </form>
    </div>
</div>

<?php if ($searched && empty($alert)): ?>
    <div class="data-table-wrapper mb-4">
        <div class="table-header d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
            <div class="mb-2 mb-sm-0">
                <h5 class="mb-0 font-weight-bold">Available Journeys</h5>
                <small class="text-muted">Travel Date: <?= e(fmt_date($search_date)) ?> &bull; <?= count($matched_routes) ?> bus(es) found</small>
            </div>

            <!-- Phase 3.2: Sorting controls & Return Trip shortcut -->
            <?php if (!empty($matched_routes)): ?>
                <div class="d-flex align-items-center flex-wrap">
                    <a href="<?= BASE_URL ?>/user/index.php?from=<?= urlencode($search_to) ?>&to=<?= urlencode($search_from) ?>&date=<?= urlencode($search_date) ?>" class="btn btn-outline-secondary btn-sm mr-sm-3 mb-2 mb-sm-0 font-weight-bold shadow-sm" title="Search return buses">
                        ⇄ Return: <?= e($search_to) ?> &rarr; <?= e($search_from) ?>
                    </a>
                    <div class="d-flex align-items-center">
                        <label for="sort-select" class="small text-muted font-weight-bold mb-0 mr-2">Sort:</label>
                        <select id="sort-select" class="form-control form-control-sm" style="width: auto;">
                            <option value="time_asc" <?= $sort === 'time_asc' ? 'selected' : '' ?>>Departure (Earliest)</option>
                            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Fare (Lowest)</option>
                            <option value="seats_desc" <?= $sort === 'seats_desc' ? 'selected' : '' ?>>Available Seats (Most)</option>
                        </select>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (empty($matched_routes)): ?>
            <div class="empty-state py-5 text-center">
                <div class="empty-icon" style="font-size: 3rem;">🚌</div>
                <div class="empty-title h5 font-weight-bold mt-2">No direct buses on <?= e(fmt_date($search_date)) ?></div>
                <div class="empty-text mb-3 text-muted">There are currently no scheduled buses running between <?= e($search_from) ?> and <?= e($search_to) ?> on this date.</div>
                
                <!-- U-13: Suggest nearby departure dates -->
                <div class="d-flex justify-content-center flex-wrap gap-2">
                    <?php for ($d = 1; $d <= 3; $d++): ?>
                        <?php
                        $next_date = date('Y-m-d', strtotime($search_date . " +{$d} days"));
                        $next_url = '?from=' . urlencode($search_from) . '&to=' . urlencode($search_to) . '&date=' . urlencode($next_date);
                        ?>
                        <a href="<?= e($next_url) ?>" class="btn btn-outline-primary btn-sm mx-1 mb-2">
                            Check <?= e(fmt_date($next_date, 'D, j M')) ?> &rarr;
                        </a>
                    <?php endfor; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Desktop Table View (Hidden on mobile <768px) -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-hover mb-0">
                    <thead class="thead-light">
                        <tr>
                            <!-- Phase 2.5: Removed internal database route ID column -->
                            <th>Origin</th>
                            <th>Destination</th>
                            <th>Bus</th>
                            <th>Departure Time</th>
                            <th>Seats Available</th>
                            <th>Tariff</th>
                            <th class="text-right">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($matched_routes as $row): ?>
                            <?php
                            $rid = (int)($row['sno'] ?? 0);
                            $bus_cap = (int)($row['bus_capacity'] ?? 36);
                            $key = $row['busno'] . '::' . substr((string)$row['time'], 0, 5);
                            $taken = $booked_counts[$key] ?? 0;
                            $available_seats = max(0, $bus_cap - $taken);

                            $travel_ts = strtotime($search_date . ' ' . (string)$row['time']);
                            $cutoff_min = defined('APP_BOOKING_CUTOFF_MIN') ? (int)APP_BOOKING_CUTOFF_MIN : 30;
                            $cutoff_secs = $cutoff_min * 60;
                            $is_departed = ($travel_ts <= time());
                            $is_cutoff = ($travel_ts < (time() + $cutoff_secs));

                            $book_params = http_build_query([
                                'route_id' => $rid,
                                'date'     => $search_date
                            ]);
                            ?>
                            <tr class="<?= $is_cutoff ? 'text-muted' : '' ?>">
                                <td class="font-weight-medium text-dark"><?= e($row['city1'] ?? '') ?></td>
                                <td class="font-weight-medium text-dark"><?= e($row['city2'] ?? '') ?></td>
                                <td><span class="badge badge-light border text-dark font-weight-bold">🚌 <?= e($row['busno'] ?? '') ?></span></td>
                                <td>
                                    <span class="font-weight-bold"><?= e(fmt_time($row['time'] ?? '')) ?></span>
                                    <?php if ($is_departed): ?>
                                        <br><span class="badge badge-warning text-dark">Already Departed</span>
                                    <?php elseif ($is_cutoff): ?>
                                        <br><span class="badge badge-secondary">Booking Closed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($is_departed): ?>
                                        <span class="badge badge-secondary px-2 py-1">Departed</span>
                                    <?php elseif ($is_cutoff): ?>
                                        <span class="badge badge-secondary px-2 py-1">Closed</span>
                                    <?php elseif ($available_seats > 0): ?>
                                        <span class="badge badge-success px-2 py-1">
                                            <?= $available_seats ?> of <?= $bus_cap ?> Open
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-danger px-2 py-1">Full</span>
                                    <?php endif; ?>
                                </td>
                                <td class="font-weight-bold text-dark h6 mb-0"><?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?></td>
                                <td class="text-right">
                                    <?php if ($is_departed): ?>
                                        <button class="btn btn-secondary btn-sm" disabled>Departed</button>
                                    <?php elseif ($is_cutoff): ?>
                                        <button class="btn btn-secondary btn-sm" disabled title="Booking closes <?= $cutoff_min ?>m before departure">Closed</button>
                                    <?php elseif ($available_seats > 0): ?>
                                        <a href="<?= BASE_URL ?>/user/booking.php?<?= e($book_params) ?>" class="btn btn-primary btn-sm px-3 shadow-sm font-weight-bold">
                                            Select Seat &rarr;
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled>Sold Out</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Phase 3.3: Mobile Responsive Cards (Visible on screens <768px) -->
            <div class="d-block d-md-none p-3">
                <?php foreach ($matched_routes as $row): ?>
                    <?php
                    $rid = (int)($row['sno'] ?? 0);
                    $bus_cap = (int)($row['bus_capacity'] ?? 36);
                    $key = $row['busno'] . '::' . substr((string)$row['time'], 0, 5);
                    $taken = $booked_counts[$key] ?? 0;
                    $available_seats = max(0, $bus_cap - $taken);

                    $travel_ts = strtotime($search_date . ' ' . (string)$row['time']);
                    $cutoff_min = defined('APP_BOOKING_CUTOFF_MIN') ? (int)APP_BOOKING_CUTOFF_MIN : 30;
                    $cutoff_secs = $cutoff_min * 60;
                    $is_departed = ($travel_ts <= time());
                    $is_cutoff = ($travel_ts < (time() + $cutoff_secs));

                    $book_params = http_build_query([
                        'route_id' => $rid,
                        'date'     => $search_date
                    ]);
                    ?>
                    <div class="card border rounded shadow-sm mb-3 <?= $is_cutoff ? 'bg-light text-muted' : 'bg-white' ?>">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                                <span class="badge badge-light border text-dark font-weight-bold">🚌 Bus #<?= e($row['busno'] ?? '') ?></span>
                                <div class="font-weight-bold text-success h5 mb-0">
                                    <?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?>
                                </div>
                            </div>
                            <div class="mb-2">
                                <div class="font-weight-bold text-dark h6 mb-1">
                                    <?= e($row['city1']) ?> &rarr; <?= e($row['city2']) ?>
                                </div>
                                <div class="small text-muted">
                                    Departure: <strong class="text-dark"><?= e(fmt_time($row['time'] ?? '')) ?></strong>
                                    <?php if ($is_departed): ?>
                                        <span class="badge badge-warning text-dark ml-1">Departed</span>
                                    <?php elseif ($is_cutoff): ?>
                                        <span class="badge badge-secondary ml-1">Closed</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2 border-top">
                                <div>
                                    <?php if ($is_cutoff): ?>
                                        <span class="badge badge-secondary px-2 py-1">Booking Closed</span>
                                    <?php elseif ($available_seats > 0): ?>
                                        <span class="badge badge-success px-2 py-1"><?= $available_seats ?> Seats Open</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger px-2 py-1">Sold Out</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($is_cutoff || $available_seats <= 0): ?>
                                        <button class="btn btn-secondary btn-sm" disabled>Unavailable</button>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/user/booking.php?<?= e($book_params) ?>" class="btn btn-primary btn-sm px-3 font-weight-bold">
                                            Select Seat &rarr;
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
// Phase 3.2: Connected Routes mapping and dynamic destination filtering
var routesMap = <?= json_encode($routes_map) ?>;

function filterDestinations() {
    var fromVal = document.getElementById('from').value;
    var toSelect = document.getElementById('to');
    var currentTo = toSelect.value;
    var allowedDests = routesMap[fromVal] || null;

    for (var i = 1; i < toSelect.options.length; i++) {
        var opt = toSelect.options[i];
        if (!allowedDests) {
            opt.disabled = false;
        } else {
            opt.disabled = (allowedDests.indexOf(opt.value) === -1);
        }
    }

    if (allowedDests && allowedDests.indexOf(currentTo) === -1 && currentTo !== '') {
        toSelect.value = '';
    }
}

document.getElementById('from')?.addEventListener('change', filterDestinations);
if (document.getElementById('from')?.value) {
    filterDestinations();
}

function hasOpt(selectEl, val) {
    if (!selectEl || !val) return false;
    for (var i = 0; i < selectEl.options.length; i++) {
        if (selectEl.options[i].value === val) return true;
    }
    return false;
}

function showSwapHint(msg) {
    var hint = document.getElementById('swap-hint');
    if (hint) {
        hint.textContent = msg;
        hint.style.display = 'block';
        setTimeout(function() {
            hint.style.display = 'none';
        }, 4000);
    }
}

// U-13 / Issue 6: Swap Cities handler
function swapCities() {
    var fromSelect = document.getElementById('from');
    var toSelect = document.getElementById('to');
    if (!fromSelect || !toSelect) return;

    var f = fromSelect.value;
    var t = toSelect.value;

    if (!f || !t) {
        showSwapHint('Please select both Origin and Destination before swapping.');
        return;
    }

    if (!hasOpt(fromSelect, t) || !hasOpt(toSelect, f)) {
        showSwapHint('Cannot swap: Reverse direction is not available in the city selection options.');
        return;
    }

    if (typeof routesMap !== 'undefined' && routesMap[t] && routesMap[t].indexOf(f) === -1) {
        showSwapHint('Cannot swap: No scheduled routes currently exist for ' + t + ' → ' + f + '.');
        return;
    }

    var hint = document.getElementById('swap-hint');
    if (hint) hint.style.display = 'none';

    fromSelect.value = t;
    toSelect.value = f;
    filterDestinations();
}
document.getElementById('swap-cities-btn')?.addEventListener('click', swapCities);
document.getElementById('swap-cities-btn-mobile')?.addEventListener('click', swapCities);

// Phase 3.2: Sort changer
function updateSort(val) {
    var url = new URL(window.location.href);
    url.searchParams.set('sort', val);
    window.location.href = url.toString();
}
document.getElementById('sort-select')?.addEventListener('change', function() {
    updateSort(this.value);
});
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-user.php'; ?>