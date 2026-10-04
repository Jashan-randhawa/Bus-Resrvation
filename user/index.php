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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <title>User - Search Routes</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>

<body>
    <div class="container-fluid">
        <nav class="navbar navbar-expand-lg navbar-light">
            <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#mycollapsediv"
                aria-controls="mycollapsediv" aria-expanded="false" aria-label="toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mycollapsediv">
                <ul class="navbar-nav mr-auto mt-2 ml-5 col-lg-3 mr-auto flex-column vertical-nav bar">
                    <li>
                        <div class="row" style="padding-left: 30px;">
                            <img src="<?= BASE_URL ?>/assets/images/bus.svg" alt="" style="width: 35px; height: 25px; margin-top: 0px;">
                            <a href="<?= BASE_URL ?>/homepage.php" style="text-decoration: none; color: black;">
                                <h4>Bus Service</h4>
                            </a>
                        </div>
                    </li>
                    <li>
                        <img src="<?= BASE_URL ?>/assets/images/userav-min.png" alt="" class="img-fluid">
                        <h4 class="text-center">User</h4>
                        <h6 class="text-center"><?= e($_SESSION['name'] ?? 'User') ?></h6>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/user/index.php" class="col-lg-3 font-weight-bold" style="text-decoration: none; color: black;">New Booking</a>
                    </li>
                    <li>
                        <a href="<?= BASE_URL ?>/user/my-bookings.php" class="col-lg-3" style="text-decoration: none; color: black;">Your Booking</a>
                    </li>
                    <li>
                        <form action="" method="post">
                            <?= csrf_field() ?>
                            <input type="submit" value="logout" name="logout" class="btn btn-link col-lg-6" style="text-decoration: none; color: black;">
                        </form>
                    </li>
                </ul>
            </div>
        </nav>
        <section class="col-lg-10 col-md-10 col-sm-12 mt-4" style="float: right;">
            <h1 class="text-center mb-4 text-info">Find and Book Bus Routes</h1>
            <div class="card col-lg-10 col-md-10 col-sm-12 mx-auto">
                <div class="card-body">
                    <form action="" method="post">
                        <?= csrf_field() ?>
                        <div class="form-group">
                            <label for="from">Departure City :</label>
                            <select name="from" class="form-control col-lg-6 col-md-8 col-sm-12" id="from" required>
                                <option value="">Select Your City</option>
                                <?php foreach ($from_cities as $row): ?>
                                    <option value="<?= e($row['city1']) ?>" <?= $search_from === $row['city1'] ? 'selected' : '' ?>>
                                        <?= e($row['city1']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="to">Destination City :</label>
                            <select name="to" class="form-control col-lg-6 col-md-8 col-sm-12" id="to" required>
                                <option value="">Select Your Destination</option>
                                <?php foreach ($to_cities as $row): ?>
                                    <option value="<?= e($row['city2']) ?>" <?= $search_to === $row['city2'] ? 'selected' : '' ?>>
                                        <?= e($row['city2']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="date">Travel Date :</label>
                            <input type="date" min="<?= date('Y-m-d') ?>" name="date" id="date" value="<?= e($search_date ?: date('Y-m-d')) ?>" class="form-control col-lg-6 col-md-8 col-sm-12" required />
                        </div>
                        <div class="form-group">
                            <button type="submit" name="subbtn" class="btn btn-info col-lg-6 col-md-8 col-sm-12">Check Available Buses</button>
                        </div>
                    </form>

                    <?php if ($searched): ?>
                        <hr class="my-4">
                        <h4 class="text-secondary mb-3">Available Buses</h4>
                        <div class="table-responsive">
                            <table class="table table-bordered table-striped">
                                <thead class="thead-dark">
                                    <tr>
                                        <th>#</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Bus Number</th>
                                        <th>Departure Time</th>
                                        <th>Seats Available</th>
                                        <th>Price</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($matched_routes)): ?>
                                        <tr><td colspan="8" class="text-center text-muted">No buses available for this route. Please try different cities.</td></tr>
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
                                                <td><?= e($rid) ?></td>
                                                <td><?= e($row['city1'] ?? '') ?></td>
                                                <td><?= e($row['city2'] ?? '') ?></td>
                                                <td><?= e($row['busno'] ?? '') ?></td>
                                                <td><?= e($row['time'] ?? '') ?></td>
                                                <td>
                                                    <span class="badge badge-<?= $available_seats > 0 ? 'success' : 'danger' ?> p-2">
                                                        <?= $available_seats ?> / <?= $bus_cap ?>
                                                    </span>
                                                </td>
                                                <td>$<?= e(number_format((float)($row['price'] ?? 0), 2)) ?></td>
                                                <td>
                                                    <?php if ($available_seats > 0): ?>
                                                        <a href="<?= BASE_URL ?>/user/booking.php?<?= e($book_params) ?>" class="btn btn-warning btn-sm">Book Seat</a>
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
                    <?php endif; ?>
                </div>
            </div>
        </section>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"
        integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct"
        crossorigin="anonymous"></script>
</body>
</html>