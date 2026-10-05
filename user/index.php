<?php
// user/index.php
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$from_cities = db_all($link, 'SELECT DISTINCT city1 FROM route ORDER BY city1 ASC');
$to_cities = db_all($link, 'SELECT DISTINCT city2 FROM route ORDER BY city2 ASC');

// U-13: Switch to GET parameters for bookmarkable, shareable, idempotent search
$search_from = trim((string)($_GET['from'] ?? ''));
$search_to = trim((string)($_GET['to'] ?? ''));
$search_date = trim((string)($_GET['date'] ?? ''));

$matched_routes = [];
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
        // U-13: Reject same origin and destination
        $alert = 'Departure city and destination city cannot be the same.';
        $alert_type = 'danger';
    } else {
        // U-13: Validate travel date
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

            // U-11: Fetch taken seat counts in one grouped query rather than 3N queries
            $booked_counts = [];
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
            }
        }
    }
}

$title = 'Search Bus Routes';
require_once __DIR__ . '/../includes/layout/header-user.php';
?>
<div class="page-header">
    <div>
        <h1 class="page-title">Find & Book Bus Routes</h1>
        <p class="page-subtitle">Select your departure city, destination, and travel date to see available seats.</p>
    </div>
</div>

<?php if ($alert): ?>
    <div class="alert alert-<?= e($alert_type) ?> alert-dismissible fade show" role="alert">
        <?= e($alert) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 font-weight-bold">Search Journey</h5>
    </div>
    <div class="card-body p-4">
        <!-- U-13: GET search form for bookmarkable results without CSRF resubmit warnings -->
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
        </form>
    </div>
</div>

<?php if ($searched && empty($alert)): ?>
    <div class="data-table-wrapper">
        <div class="table-header">
            <div>
                <h5 class="mb-0">Available Journeys</h5>
                <small class="text-muted">Travel Date: <?= e(fmt_date($search_date)) ?></small>
            </div>
            <span class="record-count"><?= count($matched_routes) ?> found</span>
        </div>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="thead-light">
                    <tr>
                        <th style="width: 70px;">#</th>
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
                    <?php if (empty($matched_routes)): ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state py-5">
                                    <div class="empty-icon">🚌</div>
                                    <div class="empty-title">No direct buses on <?= e(fmt_date($search_date)) ?></div>
                                    <div class="empty-text mb-3">There are currently no scheduled buses running between <?= e($search_from) ?> and <?= e($search_to) ?> on this date.</div>
                                    
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
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($matched_routes as $row): ?>
                            <?php
                            $rid = (int)($row['sno'] ?? 0);
                            $bus_cap = (int)($row['bus_capacity'] ?? 36);
                            $key = $row['busno'] . '::' . substr((string)$row['time'], 0, 5);
                            $taken = $booked_counts[$key] ?? 0;
                            $available_seats = max(0, $bus_cap - $taken);

                            $now_time = date('H:i:s');
                            $is_today = ($search_date === date('Y-m-d'));
                            $departed_today = ($is_today && substr((string)$row['time'], 0, 8) < $now_time);

                            $book_params = http_build_query([
                                'route_id' => $rid,
                                'date'     => $search_date
                            ]);
                            ?>
                            <tr class="<?= $departed_today ? 'text-muted' : '' ?>">
                                <td><span class="text-muted small">#<?= e($rid) ?></span></td>
                                <td class="font-weight-medium text-dark"><?= e($row['city1'] ?? '') ?></td>
                                <td class="font-weight-medium text-dark"><?= e($row['city2'] ?? '') ?></td>
                                <td><span class="badge badge-light border text-dark font-weight-bold">🚌 <?= e($row['busno'] ?? '') ?></span></td>
                                <td>
                                    <!-- U-18: Friendly formatted departure time -->
                                    <span class="font-weight-bold"><?= e(fmt_time($row['time'] ?? '')) ?></span>
                                    <?php if ($departed_today): ?>
                                        <br><span class="badge badge-warning text-dark">Already Departed</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($departed_today): ?>
                                        <span class="badge badge-secondary px-2 py-1">Departed</span>
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
                                    <?php if ($departed_today): ?>
                                        <button class="btn btn-secondary btn-sm" disabled>Departed</button>
                                    <?php elseif ($available_seats > 0): ?>
                                        <a href="<?= BASE_URL ?>/user/booking.php?<?= e($book_params) ?>" class="btn btn-primary btn-sm px-3 shadow-sm">
                                            Select Seat &rarr;
                                        </a>
                                    <?php else: ?>
                                        <button class="btn btn-secondary btn-sm" disabled>Sold Out</button>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<script>
// U-13: Swap Cities handler
function swapCities() {
    var fromSelect = document.getElementById('from');
    var toSelect = document.getElementById('to');
    if (fromSelect && toSelect) {
        var temp = fromSelect.value;
        fromSelect.value = toSelect.value;
        toSelect.value = temp;
    }
}
document.getElementById('swap-cities-btn')?.addEventListener('click', swapCities);
document.getElementById('swap-cities-btn-mobile')?.addEventListener('click', swapCities);
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-user.php'; ?>