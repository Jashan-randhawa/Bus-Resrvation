<?php
// admin/dashboard.php
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';

$total_bookings = (int)(db_one($link, 'SELECT COUNT(*) AS c FROM booking')['c'] ?? 0);
$total_buses = (int)(db_one($link, 'SELECT COUNT(*) AS c FROM buses')['c'] ?? 0);
$total_routes = (int)(db_one($link, 'SELECT COUNT(*) AS c FROM route')['c'] ?? 0);
$total_customers = (int)(db_one($link, 'SELECT COUNT(*) AS c FROM costumer')['c'] ?? 0);
$total_admins = (int)(db_one($link, 'SELECT COUNT(*) AS c FROM admin')['c'] ?? 0);
$earnings_row = db_one($link, 'SELECT COALESCE(SUM(price), 0) AS cost FROM booking');
$total_earnings = number_format((float)($earnings_row['cost'] ?? 0), 2);
?>
<div class="col-lg-10 col-md-12 col-sm-12" style=" float: right ; ">
    <div class="row">
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
                <h1 class="card-title text-center btn-info " style=" padding: 2px; " >Bookings</h1>
                <h6>Total Number Of Bookings</h6>
                <h1><?= e($total_bookings) ?></h1>
            </div>
            <div class="footer" >
                <a href="<?= BASE_URL ?>/admin/bookings.php" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
                <h1 class="card-title text-center btn-success " style=" padding: 2px; " >Buses</h1>
                <h6>Total Number Of Buses</h6>
                <h1><?= e($total_buses) ?></h1>
            </div>
            <div class="footer" >
                <a href="<?= BASE_URL ?>/admin/buses.php" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
                <h1 class="card-title text-center btn-danger " style=" padding: 2px; " >Routes</h1>
                <h6>Total Number Of Routes</h6>
                <h1><?= e($total_routes) ?></h1>
            </div>
            <div class="footer" >
                <a href="<?= BASE_URL ?>/admin/routes.php" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
                <h1 class="card-title text-center btn-warning " style=" padding: 2px; " >Seats</h1>
                <h6>Total Number Of Seats</h6>
                <h1><?= e($total_buses * 36) ?></h1>
            </div>
            <div class="footer" >
                <a href="<?= BASE_URL ?>/admin/seats.php" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
                <h1 class="card-title text-center btn-primary " style=" padding: 2px; " >Costumer</h1>
                <h6>Total Number Of Costumer</h6>
                <h1><?= e($total_customers) ?></h1>
            </div>
            <div class="footer" >
                <a href="<?= BASE_URL ?>/admin/customers.php" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
                <h1 class="card-title text-center btn-secondary " style=" padding: 2px; " >Admin</h1>
                <h6>Total Number Of Admin</h6>
                <h1><?= e($total_admins) ?></h1>
            </div>
            <div class="footer" >
                <a href="<?= BASE_URL ?>/admin/add-admin.php" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
                <h1 class="card-title text-center btn-dark " style=" padding: 2px; " >Earnings</h1>
                <h6>Total Number Of Earnings</h6>
                <h1>$<?= e($total_earnings) ?></h1>
            </div>
            <div class="footer" >
                <a href="<?= BASE_URL ?>/admin/bookings.php" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
    </div>
</div>