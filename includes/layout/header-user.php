<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Customer Portal') ?> - Bus Reservation</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    
    <!-- Design System & Shared Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/design-system.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>

<body>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="admin-shell">
        <aside class="admin-sidebar" id="adminSidebar">
            <!-- Brand Header -->
            <div class="admin-brand">
                <a href="<?= BASE_URL ?>/homepage.php" class="brand-link">
                    <img src="<?= BASE_URL ?>/assets/images/bus.svg" alt="Bus Logo" class="brand-logo">
                    <span class="brand-title">Bus Service</span>
                </a>
                <span class="badge badge-info p-1 px-2 font-weight-bold" style="font-size:0.65rem;">User</span>
            </div>

            <!-- Profile Info -->
            <div class="admin-profile">
                <img src="<?= BASE_URL ?>/assets/images/userav-min.png" alt="User Avatar" class="profile-avatar">
                <div class="profile-info">
                    <div class="profile-name"><?= e($_SESSION['name'] ?? 'Passenger') ?></div>
                    <div class="profile-role">Registered Account</div>
                </div>
            </div>

            <!-- User Navigation -->
            <nav class="admin-nav">
                <?php
                $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
                ?>
                <div class="nav-group-header">My Trip Center</div>
                <ul class="nav-group-list">
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/user/index.php" class="nav-link <?= $current_script === 'index.php' || $current_script === 'booking.php' ? 'active' : '' ?>">
                            <span class="mr-2">🔍</span> Search & Book
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/user/my-bookings.php" class="nav-link <?= $current_script === 'my-bookings.php' ? 'active' : '' ?>">
                            <span class="mr-2">🎟️</span> My Reservations
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- User Footer -->
            <div class="admin-sidebar-footer">
                <a href="<?= BASE_URL ?>/homepage.php" class="btn btn-sm btn-outline-light btn-block mb-2">
                    &larr; Public Homepage
                </a>
                <form action="<?= BASE_URL ?>/user/index.php" method="post" class="m-0">
                    <?= csrf_field() ?>
                    <button type="submit" value="logout" name="logout" class="btn btn-sm btn-danger btn-block">
                        Log Out
                    </button>
                </form>
            </div>
        </aside>

        <div class="admin-main">
            <!-- Mobile Topbar -->
            <div class="admin-topbar">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="sidebarToggle">
                    &#9776; Menu
                </button>
                <span class="font-weight-bold text-dark"><?= e($title ?? 'Customer Area') ?></span>
                <div></div>
            </div>

            <div class="admin-content-wrap">