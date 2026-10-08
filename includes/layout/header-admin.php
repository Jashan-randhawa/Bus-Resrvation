<?php
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Admin') ?> - Bus Reservation</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"
        integrity="sha384-/rJKQnzOkEo+daG0jMjU1IwwY9unxt1NBw3Ef2fmOJ3PW/TfAg2KXVoWwMZQZtw9" crossorigin="anonymous">
    
    <!-- Design System & Admin Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/design-system.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">

    <!-- Global Theme Toggle (Head load to avoid FOUC) -->
    <script src="<?= BASE_URL ?>/assets/js/theme-toggle.js"></script>
</head>
<body>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="admin-shell">
    <aside class="admin-sidebar" id="adminSidebar">
        <!-- Brand Header -->
        <div class="admin-brand">
            <a href="<?= BASE_URL ?>/admin/index.php" class="brand-link">
                <img src="<?= BASE_URL ?>/assets/images/bus.svg" alt="Logo" class="brand-logo">
                <span class="brand-title">Bus Service</span>
            </a>
            <span class="brand-badge">Admin</span>
        </div>

        <!-- Admin Profile Info -->
        <div class="admin-profile">
            <img src="<?= BASE_URL ?>/assets/images/userav-min.png" alt="Admin Avatar" class="profile-avatar">
            <div class="profile-info">
                <div class="profile-name"><?= e($_SESSION['name'] ?? 'Admin') ?></div>
                <?php
                $admin_role = (string)($_SESSION['role'] ?? 'operator');
                $admin_role_title = match($admin_role) {
                    'super_admin' => 'Super Administrator',
                    'operator' => 'Fleet Operator',
                    'viewer' => 'Read-Only Viewer',
                    default => 'Administrator',
                };
                ?>
                <div class="profile-role"><?= e($admin_role_title) ?></div>
            </div>
        </div>

        <!-- Grouped Navigation -->
        <nav class="admin-nav">
            <?php
            $is_super = function_exists('is_super_admin') && is_super_admin();
            $people_items = [
                ['Profile & Security', 'profile.php', null, '⚙️'],
                ['Customers', 'customers.php', 'edit-customer.php', '👥'],
            ];
            if ($is_super) {
                $people_items[] = ['Administrators', 'add-admin.php', null, '🛡️'];
            }
            $people_items[] = ['Customer Queries', 'queries.php', null, '💬'];

            $nav_menu = [
                'Overview' => [
                    ['Dashboard', 'index.php', null, '📊'],
                ],
                'Operations' => [
                    ['Bookings', 'bookings.php', 'edit-booking.php', '🎟️'],
                    ['Seat Availability', 'seats.php', null, '🪑'],
                    ['Passenger Manifest', 'manifest.php', null, '📋'],
                ],
                'Fleet & Schedule' => [
                    ['Buses', 'buses.php', 'edit-bus.php', '🚌'],
                    ['Routes', 'routes.php', 'edit-route.php', '🗺️'],
                ],
                'People' => $people_items,
            ];

            if ($is_super) {
                $nav_menu['System'] = [
                    ['Diagnostics', 'diagnostics.php', null, '⚡'],
                    ['Audit Log', 'audit-log.php', null, '🛡️'],
                ];
            }
            $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
            ?>
            <?php foreach ($nav_menu as $group => $items): ?>
                <div class="nav-group-header"><?= e($group) ?></div>
                <ul class="nav-group-list">
                    <?php foreach ($items as [$label, $file, $child, $icon]): ?>
                        <?php
                        $is_active = ($current_script === $file || ($child !== null && $current_script === $child));
                        $link_url = BASE_URL . '/admin/' . $file;
                        $badge_html = '';
                        if ($file === 'queries.php') {
                            $has_q_status = isset($link) && function_exists('table_has_column') && table_has_column($link, 'query', 'status');
                            $unread_count = 0;
                            if ($has_q_status) {
                                $unread_res = db_one($link, "SELECT COUNT(*) AS c FROM `query` WHERE status = 'new'");
                                $unread_count = (int)($unread_res['c'] ?? 0);
                            }
                            if ($unread_count > 0) {
                                $badge_html = ' <span class="badge badge-danger badge-pill ml-auto font-weight-bold">' . $unread_count . '</span>';
                            }
                        }
                        ?>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center <?= $is_active ? 'active' : '' ?>" href="<?= $link_url ?>" <?= $is_active ? 'aria-current="page"' : '' ?>>
                                <span class="mr-2" style="font-size: 1rem;"><?= $icon ?></span>
                                <span><?= e($label) ?></span>
                                <?= $badge_html ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>

        <!-- Sidebar Footer -->
        <div class="admin-sidebar-footer">
            <a href="<?= BASE_URL ?>/homepage.php" class="btn btn-sm btn-outline-light btn-block mb-2" target="_blank">
                Public Site &rarr;
            </a>
            <form action="<?= BASE_URL ?>/admin/index.php" method="post" class="m-0">
                <?= csrf_field() ?>
                <button type="submit" name="logout" value="1" class="btn btn-sm btn-danger btn-block">
                    Log Out
                </button>
            </form>
        </div>
    </aside>

    <div class="admin-main">
        <!-- Top bar for mobile toggle & theme switch -->
        <div class="admin-topbar">
            <button type="button" class="btn btn-sm btn-outline-secondary" id="sidebarToggle">
                &#9776; Menu
            </button>
            <span class="font-weight-bold text-dark ml-2"><?= e($title ?? 'Admin Area') ?></span>
            
            <div class="ml-auto d-flex align-items-center">
                <button type="button" class="theme-toggle-btn ml-2" aria-label="Toggle dark mode" title="Toggle dark mode">
                    <span class="theme-toggle-icon"></span>
                    <span class="theme-toggle-text d-none d-sm-inline ml-1">Theme</span>
                </button>
            </div>
        </div>

        <div class="admin-content-wrap">
            <?php if (function_exists('flash_get')): ?>
                <?php foreach (flash_get() as $m): ?>
                    <div class="alert alert-<?= e($m['type'] === 'error' ? 'danger' : $m['type']) ?> alert-dismissible fade show mb-4" role="alert">
                        <?= e($m['msg']) ?>
                        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>