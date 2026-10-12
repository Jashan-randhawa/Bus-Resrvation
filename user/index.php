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

                // Phase 3.2 / Issue 12: Sorting controls with departed trips sorted below upcoming
                $now_ts = time();
                usort($matched_routes, function($a, $b) use ($sort, $booked_counts, $search_date, $now_ts) {
                    $tsA = strtotime($search_date . ' ' . (string)$a['time']);
                    $tsB = strtotime($search_date . ' ' . (string)$b['time']);
                    $depA = ($tsA <= $now_ts);
                    $depB = ($tsB <= $now_ts);

                    // Departed trips are pushed below upcoming trips
                    if ($depA !== $depB) {
                        return $depA ? 1 : -1;
                    }

                    if ($sort === 'price_asc') {
                        return (float)$a['price'] <=> (float)$b['price'];
                    } elseif ($sort === 'seats_desc') {
                        $capA = (int)($a['bus_capacity'] ?? 36);
                        $capB = (int)($b['bus_capacity'] ?? 36);
                        $tA = $booked_counts[$a['busno'] . '::' . substr((string)$a['time'], 0, 5)] ?? 0;
                        $tB = $booked_counts[$b['busno'] . '::' . substr((string)$b['time'], 0, 5)] ?? 0;
                        $openA = max(0, $capA - $tA);
                        $openB = max(0, $capB - $tB);
                        return $openB <=> $openA;
                    } else {
                        // Default time_asc
                        return $tsA <=> $tsB;
                    }
                });
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
    <div class="card upcoming-journey-card mb-4">
        <div class="card-body p-3 p-md-4 d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="flex-grow-1 pr-md-4">
                <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                    <span class="journey-badge-upcoming">
                        <span class="journey-badge-dot"></span>
                        Upcoming Journey
                    </span>
                    <div class="journey-pnr-pill">
                        <span class="pnr-label">PNR:</span>
                        <span class="pnr-code"><?= e($next_trip['pnr']) ?></span>
                    </div>
                </div>

                <div class="journey-corridor mb-2">
                    <h4 class="journey-city font-weight-bold text-dark mb-0 d-flex align-items-center flex-wrap">
                        <span><?= e(ucwords(strtolower((string)$next_trip['city1']))) ?></span>
                        <span class="journey-arrow mx-2">&rarr;</span>
                        <span><?= e(ucwords(strtolower((string)$next_trip['city2']))) ?></span>
                    </h4>
                </div>

                <div class="journey-meta-grid">
                    <div class="journey-meta-item">
                        <span class="mr-1">📅</span>
                        <span><?= e(fmt_date($next_trip['date'])) ?> at <strong><?= e(fmt_time($next_trip['time'])) ?></strong></span>
                    </div>
                    <div class="journey-meta-item">
                        <span class="mr-1">🚌</span>
                        <span>Bus <strong>#<?= e($next_trip['bus']) ?></strong></span>
                    </div>
                    <div class="journey-meta-item">
                        <span class="badge badge-primary px-2 py-1 font-weight-bold">
                            Seat #<?= e($next_trip['seat']) ?>
                        </span>
                    </div>
                </div>
            </div>

            <div class="mt-3 mt-md-0 d-flex flex-column align-items-stretch align-items-md-end justify-content-center">
                <a href="<?= BASE_URL ?>/user/ticket.php?pnr=<?= urlencode($next_trip['pnr']) ?>" class="btn btn-primary journey-action-btn font-weight-bold shadow-sm btn-block-mobile" style="min-height: 44px; display: inline-flex; align-items: center; justify-content: center;">
                    <span>View Boarding Pass</span>
                    <span class="ml-2 font-weight-bold">&rarr;</span>
                </a>
            </div>
        </div>
    </div>
<?php endif; ?>

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 font-weight-bold">Search Journey</h5>
    </div>
    <div class="card-body p-3 p-md-4">
        <!-- U-13: GET search form for bookmarkable results -->
        <form action="" method="get" id="search-form">
            <div class="form-row align-items-center">
                <div class="col-12 col-md-4 form-group mb-2 mb-md-3">
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

                <!-- Desktop Swap Button -->
                <div class="col-md-1 form-group text-center d-none d-md-block pt-3 mb-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="swap-cities-btn" title="Swap Origin and Destination" style="margin-top: 8px;">
                        ⇄
                    </button>
                </div>

                <!-- Mobile Circular Swap Button (F6) -->
                <div class="col-12 text-center d-md-none my-1">
                    <button type="button" class="btn swap-cities-btn-circle" id="swap-cities-btn-mobile" title="Swap Origin and Destination" aria-label="Swap Origin and Destination">
                        ⇄
                    </button>
                </div>

                <div class="col-12 col-md-3 form-group mb-2 mb-md-3">
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

                <div class="col-12 col-md-4 form-group mb-3">
                    <label for="date" class="font-weight-bold small text-muted">Travel Date</label>
                    <input type="date" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+90 days')) ?>" name="date" id="date" value="<?= e($search_date ?: date('Y-m-d')) ?>" class="form-control" required />
                </div>
            </div>
            <div class="d-flex justify-content-end align-items-center mt-1">
                <button type="submit" class="btn btn-primary px-4 py-2 font-weight-bold shadow-sm" id="search-submit-btn">
                    🔍 Find Available Buses
                </button>
            </div>
            <div id="swap-hint" class="small text-danger mt-2 font-weight-medium" style="display:none;" role="alert"></div>
        </form>
    </div>
</div>

<?php if ($searched && empty($alert)): ?>
    <div class="data-table-wrapper mb-4" id="search-results">
        <div class="table-header d-flex flex-column flex-md-row justify-content-between align-items-md-center">
            <div class="mb-2 mb-md-0">
                <h5 class="mb-0 font-weight-bold">Available Journeys</h5>
                <small class="text-muted">Travel Date: <?= e(fmt_date($search_date)) ?> &bull; <?= count($matched_routes) ?> bus(es) found</small>
            </div>

            <!-- Phase 3.2: Sorting controls & Return Trip shortcut -->
            <?php if (!empty($matched_routes)): ?>
                <div class="d-flex flex-column flex-sm-row align-items-stretch align-items-sm-center">
                    <a href="<?= BASE_URL ?>/user/index.php?from=<?= urlencode($search_to) ?>&to=<?= urlencode($search_from) ?>&date=<?= urlencode($search_date) ?>" class="btn btn-outline-secondary btn-sm mr-sm-3 mb-2 mb-sm-0 font-weight-bold shadow-sm text-center" title="Search return buses">
                        ⇄ Return: <?= e($search_to) ?> &rarr; <?= e($search_from) ?>
                    </a>
                    <div class="d-flex align-items-center justify-content-between justify-content-sm-start">
                        <label for="sort-select" class="small text-muted font-weight-bold mb-0 mr-2">Sort:</label>
                        <select id="sort-select" class="form-control form-control-sm flex-grow-1 flex-sm-grow-0" style="min-width: 170px;">
                            <option value="time_asc" <?= $sort === 'time_asc' ? 'selected' : '' ?>>Departure (Earliest)</option>
                            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Fare (Lowest)</option>
                            <option value="seats_desc" <?= $sort === 'seats_desc' ? 'selected' : '' ?>>Available Seats (Most)</option>
                        </select>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($matched_routes) && $search_date === date('Y-m-d')): ?>
            <div class="px-3 pt-3">
                <div class="alert alert-info py-2 px-3 mb-0 small" role="status">
                    ℹ️ <strong>Same-day schedule:</strong> Upcoming available buses are shown first; trips that have already departed today are sorted below.
                </div>
            </div>
        <?php endif; ?>

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
                    <div class="card border rounded-lg shadow-sm mb-3 search-result-card <?= $is_cutoff ? 'bg-light text-muted' : 'bg-white' ?>">
                        <div class="card-body p-3">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <div class="font-weight-bold text-dark h5 mb-0">
                                        <?= e(fmt_time($row['time'] ?? '')) ?>
                                        <?php if ($is_departed): ?>
                                            <span class="badge badge-warning text-dark ml-1 font-weight-bold">Departed</span>
                                        <?php elseif ($is_cutoff): ?>
                                            <span class="badge badge-secondary ml-1 font-weight-bold">Closed</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="font-weight-bold text-dark mt-1">
                                        <?= e($row['city1']) ?> &rarr; <?= e($row['city2']) ?>
                                    </div>
                                    <span class="badge badge-light border text-dark font-weight-bold mt-1">🚌 Bus #<?= e($row['busno'] ?? '') ?></span>
                                </div>
                                <div class="text-right">
                                    <div class="font-weight-bold text-primary h4 mb-0">
                                        <?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?>
                                    </div>
                                    <small class="text-muted">per seat</small>
                                </div>
                            </div>
                            <div class="d-flex justify-content-between align-items-center pt-2.5 mt-2 border-top">
                                <div>
                                    <?php if ($is_cutoff): ?>
                                        <span class="badge badge-secondary px-2 py-1 font-weight-bold">Booking Closed</span>
                                    <?php elseif ($available_seats > 0): ?>
                                        <span class="badge badge-success px-2 py-1 font-weight-bold"><?= $available_seats ?> Seats Open</span>
                                    <?php else: ?>
                                        <span class="badge badge-danger px-2 py-1 font-weight-bold">Sold Out</span>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <?php if ($is_cutoff || $available_seats <= 0): ?>
                                        <button class="btn btn-secondary btn-sm px-3 font-weight-bold" disabled>Unavailable</button>
                                    <?php else: ?>
                                        <a href="<?= BASE_URL ?>/user/booking.php?<?= e($book_params) ?>" class="btn btn-primary btn-sm px-3 py-2 font-weight-bold shadow-sm d-inline-block">
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

var allDestOptions = [];
var initToSelect = document.getElementById('to');
if (initToSelect) {
    for (var i = 1; i < initToSelect.options.length; i++) {
        allDestOptions.push({
            value: initToSelect.options[i].value,
            text: initToSelect.options[i].textContent
        });
    }
}

function filterDestinations(targetValue) {
    var fromSelect = document.getElementById('from');
    var toSelect = document.getElementById('to');
    if (!fromSelect || !toSelect) return;

    var fromVal = fromSelect.value;
    var currentTo = targetValue !== undefined ? targetValue : toSelect.value;
    var allowedDests = (fromVal && routesMap[fromVal]) ? routesMap[fromVal] : null;

    // Reset toSelect options keeping only the placeholder
    toSelect.innerHTML = '';
    var placeholderOpt = document.createElement('option');
    placeholderOpt.value = '';
    placeholderOpt.textContent = 'Select Destination';
    toSelect.appendChild(placeholderOpt);

    var isSelected = false;

    if (!fromVal) {
        // No departure city selected yet: show all destination cities
        allDestOptions.forEach(function(item) {
            var opt = document.createElement('option');
            opt.value = item.value;
            opt.textContent = item.text;
            if (item.value === currentTo) {
                opt.selected = true;
                isSelected = true;
            }
            toSelect.appendChild(opt);
        });
    } else if (allowedDests && allowedDests.length > 0) {
        // Show ONLY the destinations available from this departure city
        var rendered = {};
        allDestOptions.forEach(function(item) {
            if (allowedDests.indexOf(item.value) !== -1) {
                var opt = document.createElement('option');
                opt.value = item.value;
                opt.textContent = item.text;
                if (item.value === currentTo) {
                    opt.selected = true;
                    isSelected = true;
                }
                toSelect.appendChild(opt);
                rendered[item.value] = true;
            }
        });
        // In case any destination in allowedDests was not in allDestOptions
        allowedDests.forEach(function(dest) {
            if (!rendered[dest]) {
                var opt = document.createElement('option');
                opt.value = dest;
                opt.textContent = dest;
                if (dest === currentTo) {
                    opt.selected = true;
                    isSelected = true;
                }
                toSelect.appendChild(opt);
            }
        });
    } else {
        var noneOpt = document.createElement('option');
        noneOpt.value = '';
        noneOpt.disabled = true;
        noneOpt.textContent = 'No routes available';
        toSelect.appendChild(noneOpt);
    }

    if (!isSelected) {
        toSelect.value = '';
    }
}

document.getElementById('from')?.addEventListener('change', function() {
    filterDestinations();
});
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

    if (!hasOpt(fromSelect, t)) {
        showSwapHint('Cannot swap: Reverse origin ' + t + ' is not available.');
        return;
    }

    if (typeof routesMap !== 'undefined' && routesMap[t] && routesMap[t].indexOf(f) === -1) {
        showSwapHint('Cannot swap: No scheduled routes currently exist for ' + t + ' → ' + f + '.');
        return;
    }

    var hint = document.getElementById('swap-hint');
    if (hint) hint.style.display = 'none';

    fromSelect.value = t;
    filterDestinations(f);
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

// Issue 12: Loading spinner on search submission & Anchor scroll
var searchForm = document.getElementById('search-form');
var searchBtn = document.getElementById('search-submit-btn');
if (searchForm && searchBtn) {
    searchForm.addEventListener('submit', function() {
        searchBtn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>Searching...';
        searchBtn.style.pointerEvents = 'none';
    });
}
window.addEventListener('pageshow', function() {
    if (searchBtn) {
        searchBtn.style.pointerEvents = '';
        searchBtn.innerHTML = '🔍 Find Available Buses';
    }
});

<?php if ($searched): ?>
document.addEventListener('DOMContentLoaded', function() {
    var resEl = document.getElementById('search-results');
    if (resEl) {
        resEl.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/../includes/layout/footer-user.php'; ?>