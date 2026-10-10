<?php
require_once __DIR__ . '/../config.php';
$user_role = $_SESSION['role'] ?? null;
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="Simple and reliable bus ticket booking system. Check real-time seat availability, lookup PNR status, and reserve seats across intercity routes.">
  <meta name="theme-color" content="#1e293b">

  <!-- Open Graph & Canonical (P19) -->
  <link rel="canonical" href="<?= e(BASE_URL) ?>/homepage.php">
  <meta property="og:title" content="Bus Service - Safe &amp; Simple Ticket Booking">
  <meta property="og:description" content="Simple and reliable bus ticket booking system. Real-time seats and instant PNR lookup.">
  <meta property="og:image" content="<?= e(BASE_URL) ?>/assets/images/hero-800.webp">
  <meta property="og:url" content="<?= e(BASE_URL) ?>/homepage.php">
  <meta property="og:type" content="website">
  <meta name="twitter:card" content="summary_large_image">

  <!-- Favicon -->
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/bus.svg">

  <!-- Google Fonts: Inter (400, 500, 600, 700) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
  
  <title>Bus Service - Safe &amp; Simple Ticket Booking</title>
  
  <!-- Design System & Custom CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/design-system.css?v=2026-10-10">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/home.css?v=2026-10-10">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/public.css?v=2026-10-10">
  
  <!-- Global Theme Toggle (Head load to avoid FOUC) -->
  <script src="<?= BASE_URL ?>/assets/js/theme-toggle.js"></script>
</head>

<body class="home-page">
  <!-- Skip to Main Content Link for Keyboard / Assistive Tech -->
  <a href="#main" class="skip-link sr-only sr-only-focusable">Skip to main content</a>

  <nav class="navbar navbar-expand-lg navbar-public fixed-top" id="publicNav" aria-label="Main Navigation">
    <div class="container">
      <a href="<?= BASE_URL ?>/homepage.php#home" class="navbar-brand-custom">
        <img src="<?= BASE_URL ?>/assets/images/bus.svg" alt="Bus Service Logo" width="32" height="32">
        <span>Bus Service</span>
      </a>
      
      <!-- Accessible Mobile Menu Toggler (>= 44px touch target) -->
      <button type="button" class="navbar-toggler custom-nav-toggler" data-toggle="collapse" data-target="#mycollapsediv"
        aria-controls="mycollapsediv" aria-expanded="false" aria-label="Toggle navigation">
        <span class="toggler-bar"></span>
        <span class="toggler-bar"></span>
        <span class="toggler-bar"></span>
      </button>

      <div class="collapse navbar-collapse" id="mycollapsediv">
        <ul class="navbar-nav ml-auto align-items-lg-center">
          <li class="nav-item">
            <a href="#home" class="nav-link nav-link-custom">Home</a>
          </li>
          <li class="nav-item">
            <a href="#search" class="nav-link nav-link-custom">Search &amp; Book</a>
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

          <!-- Presentation-only Session-Aware Nav Elements (P16) -->
          <?php if ($user_role === 'user'): ?>
            <?php
            $display_first_name = '';
            if (!empty($_SESSION['name'])) {
              $name_parts = explode(' ', trim($_SESSION['name']));
              $display_first_name = $name_parts[0];
            }
            ?>
            <?php if (!empty($display_first_name)): ?>
              <li class="nav-item ml-lg-3 mt-2 mt-lg-0 d-flex align-items-center">
                <span class="text-muted small font-weight-bold">
                  Hi, <?= e($display_first_name) ?>
                </span>
              </li>
            <?php endif; ?>
            <li class="nav-item ml-lg-2 mt-2 mt-lg-0">
              <a href="<?= BASE_URL ?>/user/my-bookings.php" class="btn btn-outline-primary btn-nav-action">
                My Bookings
              </a>
            </li>
            <li class="nav-item ml-lg-2 mt-2 mt-lg-0">
              <a href="<?= BASE_URL ?>/user/index.php" class="btn btn-primary btn-nav-action">
                Dashboard &rarr;
              </a>
            </li>
            <li class="nav-item ml-lg-2 mt-2 mt-lg-0">
              <form method="post" action="<?= BASE_URL ?>/homepage.php" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" name="logout" value="logout" class="btn btn-outline-danger btn-nav-action" title="Sign out of passenger account">
                  Sign Out
                </button>
              </form>
            </li>
          <?php elseif ($user_role === 'admin'): ?>
            <li class="nav-item ml-lg-3 mt-2 mt-lg-0">
              <a href="<?= BASE_URL ?>/admin/index.php" class="btn btn-dark btn-nav-action">
                Admin Panel &rarr;
              </a>
            </li>
            <li class="nav-item ml-lg-2 mt-2 mt-lg-0">
              <form method="post" action="<?= BASE_URL ?>/homepage.php" class="d-inline">
                <?= csrf_field() ?>
                <button type="submit" name="logout" value="logout" class="btn btn-outline-danger btn-nav-action" title="Sign out of administrative session">
                  Sign Out
                </button>
              </form>
            </li>
          <?php else: ?>
            <li class="nav-item ml-lg-2 mt-2 mt-lg-0">
              <button type="button" class="btn btn-outline-secondary btn-nav-action" data-toggle="modal" data-target="#loginModal">
                Admin Portal
              </button>
            </li>
            <li class="nav-item ml-lg-2 mt-2 mt-lg-0">
              <button type="button" class="btn btn-primary btn-nav-action" data-toggle="modal" data-target="#userlogin">
                Passenger Sign In
              </button>
            </li>
          <?php endif; ?>

          <!-- Global Dark / Light Mode Button -->
          <li class="nav-item ml-lg-3 mt-2 mt-lg-0">
            <button type="button" class="theme-toggle-btn" aria-label="Toggle dark mode" title="Toggle dark mode">
              <span class="theme-toggle-icon"></span>
              <span class="theme-toggle-text d-lg-none ml-1">Theme</span>
            </button>
          </li>
        </ul>
      </div>
    </div>
  </nav>