<?php
// user/index.php
require_once __DIR__ . '/../includes/auth/user-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$from_cities = db_all($link, 'SELECT DISTINCT city1 FROM route ORDER BY city1 ASC');
$to_cities = db_all($link, 'SELECT DISTINCT city2 FROM route ORDER BY city2 ASC');

$search_from = trim((string)($_POST['from'] ?? ''));
$search_to = trim((string)($_POST['to'] ?? ''));
$search_date = trim((string)($_POST['date'] ?? ''));

$matched_routes = [];
$searched = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['subbtn'])) {
    csrf_verify();
    $searched = true;
    if ($search_from !== '' && $search_to !== '') {
        $matched_routes = db_all(
            $link,
            'SELECT * FROM route WHERE city1 = ? AND city2 = ? ORDER BY time ASC',
            'ss',
            [$search_from, $search_to]
        );
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

<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white py-3">
        <h5 class="mb-0 font-weight-bold">Search Journey</h5>
    </div>
    <div class="card-body p-4">
        <form action="" method="post">
            <?= csrf_field() ?>
            <div class="form-row">
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
                <div class="col-md-4 form-group">
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
                    <input type="date" min="<?= date('Y-m-d') ?>" name="date" id="date" value="<?= e($search_date ?: date('Y-m-d')) ?>" class="form-control" required />
                </div>
            </div>
            <div class="d-flex justify-content-end">
                <button type="submit" name="subbtn" class="btn btn-primary px-4 py-2 font-weight-bold shadow-sm">
                    🔍 Find Available Buses
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($searched): ?>
    <div class="data-table-wrapper">
        <div class="table-header">
            <h5 class="mb-0">Available Journeys</h5>
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
                                    <div class="empty-title">No routes available</div>
                                    <div class="empty-text">There are currently no scheduled buses running between the chosen cities. Try alternative dates or hubs.</div>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($matched_routes as $row): ?>
                            <?php
                            $rid = (int)($row['sno'] ?? $row['id'] ?? 0);
                            $travel_date = $search_date ?: date('Y-m-d');
                            $bus_cap = get_bus_capacity($link, (string)$row['busno']);
                            $taken = count(get_booked_seats($link, (string)$row['busno'], $travel_date, (string)$row['time']));
                            $available_seats = max(0, $bus_cap - $taken);
                            $book_params = http_build_query([
                                'route_id' => $rid,
                                'city1'    => $row['city1'],
                                'city2'    => $row['city2'],
                                'bus'      => $row['busno'],
                                'time'     => $row['time'],
                                'price'    => $row['price'],
                                'date'     => $travel_date
                            ]);
                            ?>
                            <tr>
                                <td><span class="text-muted small">#<?= e($rid) ?></span></td>
                                <td class="font-weight-medium text-dark"><?= e($row['city1'] ?? '') ?></td>
                                <td class="font-weight-medium text-dark"><?= e($row['city2'] ?? '') ?></td>
                                <td><span class="badge badge-light border text-dark font-weight-bold">🚌 <?= e($row['busno'] ?? '') ?></span></td>
                                <td><?= e($row['time'] ?? '') ?></td>
                                <td>
                                    <span class="badge badge-<?= $available_seats > 0 ? 'success' : 'danger' ?> px-2 py-1">
                                        <?= $available_seats ?> of <?= $bus_cap ?> Open
                                    </span>
                                </td>
                                <td class="font-weight-bold text-dark h6 mb-0"><?= CURRENCY ?><?= e(number_format((float)($row['price'] ?? 0), 2)) ?></td>
                                <td class="text-right">
                                    <?php if ($available_seats > 0): ?>
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

<?php require_once __DIR__ . '/../includes/layout/footer-admin.php'; ?>