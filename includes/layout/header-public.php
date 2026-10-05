<?php require_once __DIR__ . '/../config.php'; ?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  
  <title>Bus Service - Safe & Simple Ticket Booking</title>
  
  <!-- Design System & Custom CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/design-system.css">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/home.css">
</head>

<body onload="<?php if(isset($_GET['pnr'])){echo "show_modal();";}?>">
  <nav class="navbar navbar-expand-lg navbar-public fixed-top">
    <div class="container">
      <a href="<?= BASE_URL ?>/homepage.php" class="navbar-brand-custom">
        <img src="<?= BASE_URL ?>/assets/images/bus.svg" alt="Bus Service Logo">
        <span>Bus Service</span>
      </a>
      <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#mycollapsediv"
        aria-controls="mycollapsediv" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon">&#9776;</span>
      </button>
      <div class="collapse navbar-collapse" id="mycollapsediv">
        <ul class="navbar-nav ml-auto align-items-lg-center">
          <li class="nav-item">
            <a href="#image" class="nav-link nav-link-custom">Home</a>
          </li>
          <li class="nav-item">
            <a href="#pnr" class="nav-link nav-link-custom">Check PNR</a>
          </li>
          <li class="nav-item">
            <a href="#about" class="nav-link nav-link-custom">About</a>
          </li>
          <li class="nav-item">
            <a href="#contact" class="nav-link nav-link-custom">Contact</a>
          </li>
          <li class="nav-item ml-lg-2 mt-2 mt-lg-0">
            <button type="button" class="btn btn-outline-primary btn-sm px-3" data-toggle="modal" data-target="#userlogin">
              Passenger Login
            </button>
          </li>
          <li class="nav-item ml-lg-2 mt-2 mt-lg-0">
            <button type="button" class="btn btn-dark btn-sm px-3" data-toggle="modal" data-target="#loginModal">
              Admin Portal
            </button>
          </li>
        </ul>
      </div>
    </div>
  </nav>