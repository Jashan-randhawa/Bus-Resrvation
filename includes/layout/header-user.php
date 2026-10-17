<?php require_once __DIR__ . '/../config.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/images/bus-emblem.png">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/bus.svg">
    <title><?= e($title ?? 'Customer Portal') ?> - BusBook</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css?v=4.6.2">
    
    <!-- Design System, Admin Core & User Styles (F1, F12) -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/design-system.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/design-system.css') ?: 1 ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/admin.css') ?: 1 ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin-mobile-responsive.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/admin-mobile-responsive.css') ?: 1 ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/user.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/user.css') ?: 1 ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/seat-map.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/seat-map.css') ?: 1 ?>">

    <!-- Global Theme Toggle (Head load to avoid FOUC) -->
    <script src="<?= BASE_URL ?>/assets/js/theme-toggle.js"></script>

    <!-- PWA Meta Tags & Service Worker -->
    <?php require_once dirname(__DIR__, 2) . '/assets/partials/_pwa-head.php'; ?>
</head>

<body>
    <!-- BusBook Brand Preloader Overlay -->
    <?php require_once dirname(__DIR__, 2) . '/assets/partials/_preloader.php'; ?>

    <!-- Inline SVG Sprite for User Portal Navigation Icons (F1) -->
    <svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
        <symbol id="nav-ticket" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V9z"></path>
            <line x1="9" y1="12" x2="9.01" y2="12"></line>
            <line x1="13" y1="12" x2="15" y2="12"></line>
        </symbol>
        <symbol id="nav-clipboard" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
            <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
            <path d="M9 12h6"></path>
            <path d="M9 16h6"></path>
        </symbol>
        <symbol id="nav-user-cog" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
            <circle cx="12" cy="7" r="4"></circle>
            <circle cx="19" cy="11" r="2"></circle>
        </symbol>
        <symbol id="nav-menu" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="3" y1="12" x2="21" y2="12"></line>
            <line x1="3" y1="6" x2="21" y2="6"></line>
            <line x1="3" y1="18" x2="21" y2="18"></line>
        </symbol>
        <symbol id="nav-close" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
        </symbol>
        <symbol id="nav-external-link" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
            <polyline points="15 3 21 3 21 9"></polyline>
            <line x1="10" y1="14" x2="21" y2="3"></line>
        </symbol>
    </svg>

    <a class="skip-link sr-only sr-only-focusable" href="#main">Skip to main content</a>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <div class="admin-shell">
        <aside class="admin-sidebar" id="adminSidebar">
            <!-- Brand Header -->
            <div class="admin-brand">
                <a href="<?= BASE_URL ?>/user/index.php" class="brand-link">
                    <img src="<?= BASE_URL ?>/assets/images/bus-emblem.png" alt="BusBook Logo" class="brand-logo">
                    <span class="brand-title">BusBook</span>
                </a>
                <div class="d-flex align-items-center">
                    <span class="badge badge-info p-1 px-2 font-weight-bold mr-1" style="font-size:0.65rem;">User</span>
                    <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none sidebar-close-btn ml-1" id="sidebarCloseBtn" aria-label="Close navigation menu" title="Close menu">
                        <svg width="16" height="16" aria-hidden="true"><use href="#nav-close"></use></svg>
                    </button>
                </div>
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
            <nav class="admin-nav" aria-label="Customer portal navigation">
                <?php
                $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
                ?>
                <div class="nav-group-header">My Trip Center</div>
                <ul class="nav-group-list">
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/user/index.php" class="nav-link d-flex align-items-center <?= $current_script === 'index.php' || $current_script === 'booking.php' ? 'active' : '' ?>" <?= ($current_script === 'index.php' || $current_script === 'booking.php') ? 'aria-current="page"' : '' ?>>
                            <span class="nav-icon-tile mr-2.5" aria-hidden="true">
                                <svg class="nav-icon" width="20" height="20"><use href="#nav-ticket"></use></svg>
                            </span>
                            <span class="nav-label">Search & Book</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/user/my-bookings.php" class="nav-link d-flex align-items-center <?= $current_script === 'my-bookings.php' ? 'active' : '' ?>" <?= $current_script === 'my-bookings.php' ? 'aria-current="page"' : '' ?>>
                            <span class="nav-icon-tile mr-2.5" aria-hidden="true">
                                <svg class="nav-icon" width="20" height="20"><use href="#nav-clipboard"></use></svg>
                            </span>
                            <span class="nav-label">My Reservations</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="<?= BASE_URL ?>/user/profile.php" class="nav-link d-flex align-items-center <?= $current_script === 'profile.php' ? 'active' : '' ?>" <?= $current_script === 'profile.php' ? 'aria-current="page"' : '' ?>>
                            <span class="nav-icon-tile mr-2.5" aria-hidden="true">
                                <svg class="nav-icon" width="20" height="20"><use href="#nav-user-cog"></use></svg>
                            </span>
                            <span class="nav-label">My Profile</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <!-- User Footer -->
            <div class="admin-sidebar-footer">
                <a href="<?= BASE_URL ?>/homepage.php" class="btn btn-sm btn-outline-light btn-block mb-2 d-flex align-items-center justify-content-center">
                    <span>Public Homepage</span>
                    <svg class="ml-1.5" width="14" height="14" aria-hidden="true"><use href="#nav-external-link"></use></svg>
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
            <!-- Top bar with toggle & theme switch (F1, F9) -->
            <div class="admin-topbar">
                <button type="button" class="admin-menu-btn" id="sidebarToggle" aria-controls="adminSidebar" aria-expanded="false" aria-label="Open navigation menu">
                    <svg width="22" height="22" aria-hidden="true"><use href="#nav-menu"></use></svg>
                </button>
                <div class="topbar-title-block">
                    <div class="topbar-eyebrow-wrap">
                        <span class="topbar-eyebrow group-pill-overview">
                            <span class="topbar-eyebrow-dot" aria-hidden="true"></span>
                            <span class="topbar-eyebrow-text">Customer Portal</span>
                        </span>
                        <span class="topbar-breadcrumb-sep d-none d-lg-inline" aria-hidden="true">/</span>
                        <span class="topbar-desktop-title font-weight-bold d-none d-lg-inline"><?= e($title ?? 'Customer Area') ?></span>
                    </div>
                    <h1 class="page-title-mobile font-weight-bold d-lg-none">
                        <?= e($title ?? 'Customer Area') ?>
                    </h1>
                </div>
                
                <div class="ml-auto d-flex align-items-center">
                    <button type="button" class="theme-toggle-btn" aria-label="Toggle dark mode" title="Toggle dark mode">
                        <span class="theme-toggle-icon"></span>
                    </button>
                </div>
            </div>

            <main id="main" tabindex="-1" class="admin-content-wrap">
            <?php if (function_exists('flash_get')): ?>
                <?php foreach (flash_get() as $m): ?>
                    <div class="alert alert-<?= e($m['type'] ?? 'info') ?> alert-dismissible fade show" role="alert">
                        <?= e($m['msg'] ?? '') ?>
                        <button type="button" class="close" data-dismiss="alert">&times;</button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <!-- Idle Session Warning Modal (U-14, F14) -->
            <div class="modal fade" id="idleSessionModal" tabindex="-1" role="dialog" aria-labelledby="idleSessionModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false" data-heartbeat-url="<?= BASE_URL ?>/user/api-heartbeat.php" data-login-url="<?= BASE_URL ?>/homepage.php">
                <div class="modal-dialog modal-dialog-centered" role="document">
                    <div class="modal-content border-0 shadow">
                        <div class="modal-header bg-warning text-dark">
                            <h5 class="modal-title font-weight-bold" id="idleSessionModalLabel">⚠️ Session Timeout Warning</h5>
                        </div>
                        <div class="modal-body p-4 text-center">
                            <div style="font-size: 48px;" class="mb-2">⏱️</div>
                            <h5 class="font-weight-bold text-dark">Are you still there?</h5>
                            <p class="text-muted mb-0">Your session will expire in <strong id="idle-countdown">120</strong> seconds due to inactivity. Click below to stay signed in and keep your work.</p>
                        </div>
                        <div class="modal-footer justify-content-center bg-light">
                            <button type="button" class="btn btn-primary px-4 py-2 font-weight-bold shadow-sm" id="stay-signed-in-btn">
                                Stay Signed In
                            </button>
                        </div>
                    </div>
                </div>
            </div>