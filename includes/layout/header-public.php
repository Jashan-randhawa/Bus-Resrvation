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
  <meta property="og:title" content="BusBook - Safe &amp; Simple Ticket Booking">
  <meta property="og:description" content="Simple and reliable bus ticket booking system. Real-time seats and instant PNR lookup.">
  <meta property="og:image" content="<?= e(BASE_URL) ?>/assets/images/hero-800.webp">
  <meta property="og:url" content="<?= e(BASE_URL) ?>/homepage.php">
  <meta property="og:type" content="website">
  <meta name="twitter:card" content="summary_large_image">

  <!-- Favicon -->
  <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico">
  <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
  <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/images/bus-emblem.png">
  <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/bus.svg">

  <!-- Google Fonts: Inter (400, 500, 600, 700) -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css?v=4.6.2">
  
  <title>BusBook - Safe &amp; Simple Ticket Booking</title>
  
  <!-- Design System & Custom CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/design-system.css?v=<?= file_exists(dirname(__DIR__, 2) . '/assets/css/design-system.css') ? filemtime(dirname(__DIR__, 2) . '/assets/css/design-system.css') : '2.4.1' ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/home.css?v=<?= file_exists(dirname(__DIR__, 2) . '/assets/css/home.css') ? filemtime(dirname(__DIR__, 2) . '/assets/css/home.css') : '2.4.1' ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/public.css?v=<?= file_exists(dirname(__DIR__, 2) . '/assets/css/public.css') ? filemtime(dirname(__DIR__, 2) . '/assets/css/public.css') : '2.4.1' ?>">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/footer.css?v=<?= file_exists(dirname(__DIR__, 2) . '/assets/css/footer.css') ? filemtime(dirname(__DIR__, 2) . '/assets/css/footer.css') : '2.4.1' ?>">
  
  <!-- Global Theme Toggle (Head load to avoid FOUC) -->
  <script src="<?= BASE_URL ?>/assets/js/theme-toggle.js"></script>

  <!-- PWA Meta Tags & Service Worker -->
  <?php require_once dirname(__DIR__, 2) . '/assets/partials/_pwa-head.php'; ?>
</head>

<body class="home-page">
  <!-- Brand Preloader Overlay -->
  <?php require_once dirname(__DIR__, 2) . '/assets/partials/_preloader.php'; ?>

  <!-- Skip to Main Content Link for Keyboard / Assistive Tech -->
  <a href="#main" class="skip-link sr-only sr-only-focusable">Skip to main content</a>

  <nav class="navbar navbar-expand-lg navbar-public fixed-top" id="publicNav" aria-label="Main Navigation">
    <div class="container">
      <a href="<?= BASE_URL ?>/homepage.php#home" class="navbar-brand-custom">
        <img src="<?= BASE_URL ?>/assets/images/bus-emblem.png" alt="BusBook Logo" width="36" height="36" class="mr-2">
        <span>BusBook</span>
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
            $user_full_name = trim((string)($_SESSION['name'] ?? 'Passenger'));
            $name_parts = explode(' ', $user_full_name);
            $display_first_name = $name_parts[0] !== '' ? $name_parts[0] : 'Passenger';
            $user_initial = strtoupper(mb_substr($display_first_name, 0, 1, 'UTF-8'));
            $user_email = trim((string)($_SESSION['email'] ?? ''));
            ?>
            <li class="nav-item ml-lg-2 mt-2 mt-lg-0 d-none d-xl-inline-block">
              <a href="<?= BASE_URL ?>/user/my-bookings.php" class="btn btn-outline-primary btn-nav-action text-nowrap">
                <svg class="mr-1" width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V9z"></path><line x1="9" y1="12" x2="9.01" y2="12"></line><line x1="13" y1="12" x2="15" y2="12"></line></svg>
                My Bookings
              </a>
            </li>
            <li class="nav-item ml-lg-2 mt-2 mt-lg-0">
              <a href="<?= BASE_URL ?>/user/index.php" class="btn btn-primary btn-nav-action text-nowrap shadow-sm">
                Dashboard &rarr;
              </a>
            </li>
            <li class="nav-item dropdown nav-user-dropdown ml-lg-2 mt-2 mt-lg-0">
              <button class="btn nav-user-pill dropdown-toggle d-flex align-items-center text-nowrap" type="button" id="userAccountDropdown" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-label="Open user menu for <?= e($display_first_name) ?>">
                <span class="nav-user-avatar" aria-hidden="true"><?= e($user_initial) ?></span>
                <span class="nav-user-name font-weight-semibold"><?= e($display_first_name) ?></span>
              </button>
              <div class="dropdown-menu dropdown-menu-right nav-user-menu shadow-lg border-0 py-2" aria-labelledby="userAccountDropdown">
                <div class="nav-user-header px-3 py-2 border-bottom">
                  <div class="d-flex align-items-center">
                    <span class="nav-user-avatar nav-user-avatar-lg mr-2" aria-hidden="true"><?= e($user_initial) ?></span>
                    <div class="overflow-hidden">
                      <div class="font-weight-bold text-dark text-truncate nav-user-fullname"><?= e($user_full_name) ?></div>
                      <small class="text-muted d-block text-truncate nav-user-meta"><?= !empty($user_email) ? e($user_email) : 'Passenger Account' ?></small>
                    </div>
                  </div>
                </div>
                <div class="nav-user-links py-1">
                  <a class="dropdown-item d-flex align-items-center py-2 text-nowrap" href="<?= BASE_URL ?>/user/index.php">
                    <svg class="nav-menu-icon mr-2 text-primary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7"></rect><rect x="14" y="3" width="7" height="7"></rect><rect x="14" y="14" width="7" height="7"></rect><rect x="3" y="14" width="7" height="7"></rect></svg>
                    <span>Dashboard</span>
                  </a>
                  <a class="dropdown-item d-flex align-items-center py-2 text-nowrap" href="<?= BASE_URL ?>/user/my-bookings.php">
                    <svg class="nav-menu-icon mr-2 text-primary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V9z"></path><line x1="9" y1="12" x2="9.01" y2="12"></line><line x1="13" y1="12" x2="15" y2="12"></line></svg>
                    <span>My Bookings</span>
                  </a>
                  <a class="dropdown-item d-flex align-items-center py-2 text-nowrap" href="<?= BASE_URL ?>/user/profile.php">
                    <svg class="nav-menu-icon mr-2 text-primary" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path><circle cx="12" cy="7" r="4"></circle></svg>
                    <span>Account Settings</span>
                  </a>
                </div>
                <div class="dropdown-divider my-1"></div>
                <form method="post" action="<?= BASE_URL ?>/homepage.php" class="m-0">
                  <?= csrf_field() ?>
                  <button type="submit" name="logout" value="logout" class="dropdown-item text-danger d-flex align-items-center py-2 text-nowrap font-weight-medium" title="Sign out of passenger account">
                    <svg class="nav-menu-icon mr-2 text-danger" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
                    <span>Sign Out</span>
                  </button>
                </form>
              </div>
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