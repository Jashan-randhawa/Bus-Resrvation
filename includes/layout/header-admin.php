<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <title>Home</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>

<body>
    <div class="container-fluid">
        <nav class="navbar navbar-expand-lg navbar-light ">
            
            <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#mycollapsediv"
                aria-controls="mycollapsediv" aria-expanded="false" aria-label="toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mycollapsediv">
                <ul class="navbar-nav mr-auto mt-2 ml-5 col-lg-3 mr-auto flex-column vertical-nav bar">
                    <li>
                        <div class="row" style=" padding-left: 30px; ">
                            <img src="<?= BASE_URL ?>/assets/images/bus.svg" alt="" style=" width: 35px; height: 25px; margin-top: 0px; ">
                            <a href="<?= BASE_URL ?>/homepage.php" style=" text-decoration: none ; color: black; " ><h4>Bus Service</h4></a>
                        </div>
                    </li>
                    <li>
                        <img src="<?= BASE_URL ?>/assets/images/userav-min.png" alt="" class=" img-fluid ">
                        <h4 class=" text-center"><?= e($_SESSION['name'] ?? 'Admin') ?></h4>
                        <h6 class=" text-center">system administration</h6>
                    </li>
                     <li class="nav-item active">
                        <a href="<?= BASE_URL ?>/admin/index.php" data-value="Dashboard" class="nav-link">Dashboard</a>
                    </li>
                    <li class=" nav-item active">
                        <a href="<?= BASE_URL ?>/admin/buses.php" data-value="about" class="nav-link">Buses</a>
                    </li>
                    <a href="<?= BASE_URL ?>/admin/routes.php" data-value="contact" class="nav-link">Routes</a>
                    </li>
                    <li class=" nav-item active">
                        <a href="<?= BASE_URL ?>/admin/customers.php" class="nav-link">Costumer</a>
                    </li>
                    <li class=" nav-item active">
                        <a href="<?= BASE_URL ?>/admin/bookings.php" class="nav-link">Booking</a>
                    </li>
                    <li class=" nav-item active">
                        <a href="<?= BASE_URL ?>/admin/seats.php" class="nav-link">Seats</a>
                    </li>
                    <li class=" nav-item active">
                        <a href="<?= BASE_URL ?>/admin/add-admin.php" class="nav-link">New Admin</a>
                    </li>
                    <li class=" nav-item active">
                        <a href="<?= BASE_URL ?>/admin/queries.php" class="nav-link">Query</a>
                    </li>
                    <li class=" nav-item active">
                        <a href="<?= BASE_URL ?>/admin/diagnostics.php" class="nav-link text-info font-weight-bold">Diagnostics</a>
                    </li>
                    <li>
                        <form action="" method="post">
                        <?= csrf_field() ?>
                        <input type="submit" value="logout" name="logout" class=" btn btn-link col-lg-6 " style=" text-decoration: none; color: black; " >
                        </form>
                    </li>
                </ul>
            </div>
        </nav>