<?php
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <!-- Immediate Dark Mode Pre-Script to Prevent FOUC -->
    <script>
    (function(){try{var s=localStorage.getItem('theme')||localStorage.getItem('busres_theme_mode');if(!s&&window.matchMedia&&window.matchMedia('(prefers-color-scheme: dark)').matches)s='dark';if(s==='dark'){document.documentElement.setAttribute('data-theme','dark');document.documentElement.classList.add('dark-mode');if(document.body){document.body.setAttribute('data-theme','dark');document.body.classList.add('dark-mode');}else{document.addEventListener('DOMContentLoaded',function(){document.body.setAttribute('data-theme','dark');document.body.classList.add('dark-mode');});}}}catch(e){}})();
    </script>
    <meta name="robots" content="noindex, nofollow">
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/images/bus-emblem.png">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/bus.svg">
    <title><?= e($title ?? 'Admin') ?> - BusBook</title>
    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css?v=4.6.2">
    <!-- AOS animation library removed for admin performance (A17):
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet"
        integrity="sha384-/rJKQnzOkEo+daG0jMjU1IwwY9unxt1NBw3Ef2fmOJ3PW/TfAg2KXVoWwMZQZtw9" crossorigin="anonymous">
    -->
    
    <!-- Design System & Admin Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/design-system.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/design-system.css') ?: 1 ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/admin.css') ?: '2026-10-10' ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin-mobile-responsive.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/admin-mobile-responsive.css') ?: '2026-10-10' ?>">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/theme.css?v=<?= @filemtime(__DIR__ . '/../../assets/css/theme.css') ?: 1 ?>">
    <?php if (!empty($page_css)): ?>
        <?php foreach ((array)$page_css as $css_file): ?>
            <?php 
            $clean_css = ltrim($css_file, '/');
            $css_disk = __DIR__ . '/../../' . $clean_css;
            $css_ver = file_exists($css_disk) ? filemtime($css_disk) : '1';
            ?>
            <link rel="stylesheet" href="<?= BASE_URL ?>/<?= $clean_css ?>?v=<?= $css_ver ?>">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- Global Theme Toggle (Head load to avoid FOUC) -->
    <script src="<?= BASE_URL ?>/assets/js/theme-toggle.js"></script>

    <!-- PWA Meta Tags & Service Worker -->
    <?php require_once dirname(__DIR__, 2) . '/assets/partials/_pwa-head.php'; ?>
</head>
<body>
<!-- BusBook Brand Preloader Overlay -->
<?php require_once dirname(__DIR__, 2) . '/assets/partials/_preloader.php'; ?>

<!-- Inline SVG Sprite for Admin Navigation Icons (Section 3.3) -->
<svg xmlns="http://www.w3.org/2000/svg" style="display: none;">
    <symbol id="nav-dashboard" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="3" width="7" height="9" rx="1"></rect>
        <rect x="14" y="3" width="7" height="5" rx="1"></rect>
        <rect x="14" y="12" width="7" height="9" rx="1"></rect>
        <rect x="3" y="16" width="7" height="5" rx="1"></rect>
    </symbol>
    <symbol id="nav-ticket" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2z"></path>
        <line x1="9" y1="9" x2="9" y2="15"></line>
        <line x1="15" y1="9" x2="15" y2="15"></line>
    </symbol>
    <symbol id="nav-seat" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M7 13h10a4 4 0 0 0 4-4V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a4 4 0 0 0 4 4z"></path>
        <path d="M4 13v6a2 2 0 0 0 2 2h1"></path>
        <path d="M20 13v6a2 2 0 0 1-2 2h-1"></path>
        <line x1="7" y1="17" x2="17" y2="17"></line>
    </symbol>
    <symbol id="nav-clipboard" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"></path>
        <rect x="8" y="2" width="8" height="4" rx="1" ry="1"></rect>
        <path d="M9 12h6"></path>
        <path d="M9 16h6"></path>
    </symbol>
    <symbol id="nav-bus" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <rect x="3" y="4" width="18" height="14" rx="3"></rect>
        <path d="M3 10h18"></path>
        <circle cx="7" cy="15" r="1.5"></circle>
        <circle cx="17" cy="15" r="1.5"></circle>
        <path d="M5 18v2"></path>
        <path d="M19 18v2"></path>
    </symbol>
    <symbol id="nav-route" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <circle cx="6" cy="19" r="3"></circle>
        <circle cx="18" cy="5" r="3"></circle>
        <path d="M12 19h4.5a3.5 3.5 0 0 0 0-7h-9a3.5 3.5 0 0 1 0-7H12"></path>
    </symbol>
    <symbol id="nav-user-cog" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
        <circle cx="12" cy="7" r="4"></circle>
        <circle cx="19" cy="11" r="2"></circle>
    </symbol>
    <symbol id="nav-users" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path>
        <circle cx="9" cy="7" r="4"></circle>
        <path d="M23 21v-2a4 4 0 0 0-3-3.87"></path>
        <path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
    </symbol>
    <symbol id="nav-shield" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
    </symbol>
    <symbol id="nav-message" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
    </symbol>
    <symbol id="nav-activity" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
    </symbol>
    <symbol id="nav-list" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <line x1="8" y1="6" x2="21" y2="6"></line>
        <line x1="8" y1="12" x2="21" y2="12"></line>
        <line x1="8" y1="18" x2="21" y2="18"></line>
        <line x1="3" y1="6" x2="3.01" y2="6"></line>
        <line x1="3" y1="12" x2="3.01" y2="12"></line>
        <line x1="3" y1="18" x2="3.01" y2="18"></line>
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
    <symbol id="nav-bell" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
    </symbol>
    <symbol id="nav-external-link" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path>
        <polyline points="15 3 21 3 21 9"></polyline>
        <line x1="10" y1="14" x2="21" y2="3"></line>
    </symbol>
</svg>

<a class="skip-link sr-only sr-only-focusable" href="#adminMain">Skip to content</a>
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<div class="admin-shell">
    <aside class="admin-sidebar" id="adminSidebar">
        <!-- Brand Header (Bus Service text visible on all viewports) -->
        <div class="admin-brand">
            <a href="<?= BASE_URL ?>/admin/index.php" class="brand-link">
                <img src="<?= BASE_URL ?>/assets/images/bus-emblem.png" alt="BusBook Logo" class="brand-logo">
                <span class="brand-title">BusBook</span>
            </a>
            <div class="d-flex align-items-center">
                <span class="brand-badge mr-1">Admin</span>
                <button type="button" class="btn btn-sm btn-outline-secondary d-lg-none sidebar-close-btn ml-1" id="sidebarCloseBtn" aria-label="Close navigation menu" title="Close menu">
                    <svg width="16" height="16" aria-hidden="true"><use href="#nav-close"></use></svg>
                </button>
            </div>
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
                <div class="profile-role">
                    <span class="badge <?= $admin_role === 'viewer' ? 'badge-secondary' : 'badge-primary' ?> font-weight-bold">
                        <?= e($admin_role_title) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Grouped Navigation -->
        <nav class="admin-nav" aria-label="Admin navigation">
            <?php
            $is_super = function_exists('is_super_admin') && is_super_admin();
            $people_items = [
                ['Profile & Security', 'profile.php', null, 'nav-user-cog'],
                ['Customers', 'customers.php', 'edit-customer.php', 'nav-users'],
            ];
            if ($is_super) {
                $people_items[] = ['Administrators', 'add-admin.php', null, 'nav-shield'];
            }
            $people_items[] = ['Customer Queries', 'queries.php', null, 'nav-message'];

            $nav_menu = [
                'Overview' => [
                    ['Dashboard', 'index.php', null, 'nav-dashboard'],
                ],
                'Operations' => [
                    ['Bookings', 'bookings.php', 'edit-booking.php', 'nav-ticket'],
                    ['Seat Availability', 'seats.php', null, 'nav-seat'],
                    ['Passenger Manifest', 'manifest.php', null, 'nav-clipboard'],
                ],
                'Fleet & Schedule' => [
                    ['Buses', 'buses.php', 'edit-bus.php', 'nav-bus'],
                    ['Routes', 'routes.php', 'edit-route.php', 'nav-route'],
                ],
                'People' => $people_items,
            ];

            if ($is_super) {
                $nav_menu['System'] = [
                    ['Diagnostics', 'diagnostics.php', null, 'nav-activity'],
                    ['Audit Log', 'audit-log.php', null, 'nav-list'],
                ];
            }
            $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');

            // Hoist unread queries count for both topbar bell and drawer badge
            $has_q_status = isset($link) && function_exists('table_has_column') && table_has_column($link, 'query', 'status');
            $unread_count = 0;
            if ($has_q_status) {
                $now = time();
                $cached_time = (int)($_SESSION['unread_queries_checked_at'] ?? 0);
                if ($current_script === 'queries.php' || ($now - $cached_time) > 60 || !isset($_SESSION['unread_queries_count'])) {
                    $unread_res = db_one($link, "SELECT COUNT(*) AS c FROM `query` WHERE status = 'new'");
                    $unread_count = (int)($unread_res['c'] ?? 0);
                    $_SESSION['unread_queries_count'] = $unread_count;
                    $_SESSION['unread_queries_checked_at'] = $now;
                } else {
                    $unread_count = (int)$_SESSION['unread_queries_count'];
                }
            }

            // Determine current active group for eyebrow header
            $current_group = 'Overview';
            foreach ($nav_menu as $grp => $items) {
                foreach ($items as [$lbl, $file, $child, $icon]) {
                    if ($current_script === $file || ($child !== null && $current_script === $child)) {
                        $current_group = $grp;
                        break 2;
                    }
                }
            }
            $group_slug = strtolower(trim(preg_replace('/[^a-zA-Z0-9]+/', '-', $current_group), '-'));
            ?>
            <?php foreach ($nav_menu as $group => $items): ?>
                <div class="nav-group-header"><?= e($group) ?></div>
                <ul class="nav-group-list">
                    <?php foreach ($items as [$label, $file, $child, $icon]): ?>
                        <?php
                        $is_active = ($current_script === $file || ($child !== null && $current_script === $child));
                        $link_url = BASE_URL . '/admin/' . $file;
                        $badge_html = '';
                        if ($file === 'queries.php' && $unread_count > 0) {
                            $badge_html = ' <span class="badge badge-danger badge-pill ml-auto font-weight-bold">' . $unread_count . '</span>';
                        }
                        ?>
                        <li class="nav-item">
                            <a class="nav-link d-flex align-items-center <?= $is_active ? 'active' : '' ?>" href="<?= $link_url ?>" <?= $is_active ? 'aria-current="page"' : '' ?>>
                                <span class="nav-icon-tile mr-2.5" aria-hidden="true">
                                    <svg class="nav-icon" width="20" height="20"><use href="#<?= e($icon) ?>"></use></svg>
                                </span>
                                <span class="nav-label"><?= e($label) ?></span>
                                <?= $badge_html ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>

        <!-- Sidebar Footer -->
        <div class="admin-sidebar-footer">
            <a href="<?= BASE_URL ?>/homepage.php" class="btn btn-sm btn-outline-light btn-block mb-2 d-flex align-items-center justify-content-center" target="_blank">
                <span>Public Site</span>
                <svg class="ml-1.5" width="14" height="14" aria-hidden="true"><use href="#nav-external-link"></use></svg>
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
        <!-- Top bar for mobile toggle & theme switch (Section 3.1) -->
        <div class="admin-topbar">
            <button type="button" class="admin-menu-btn" id="sidebarToggle" aria-controls="adminSidebar" aria-expanded="false" aria-label="Open navigation menu">
                <svg width="22" height="22" aria-hidden="true"><use href="#nav-menu"></use></svg>
            </button>
            <div class="topbar-title-block">
                <div class="topbar-eyebrow-wrap">
                    <span class="topbar-eyebrow group-pill-<?= e($group_slug) ?>">
                        <span class="topbar-eyebrow-dot" aria-hidden="true"></span>
                        <span class="topbar-eyebrow-text"><?= e($current_group) ?></span>
                    </span>
                    <span class="topbar-breadcrumb-sep d-none d-lg-inline" aria-hidden="true">/</span>
                    <span class="topbar-desktop-title font-weight-bold d-none d-lg-inline"><?= e($title ?? 'Admin') ?></span>
                </div>
                <h1 class="page-title-mobile font-weight-bold d-lg-none">
                    <?= e($title ?? 'Admin') ?>
                </h1>
            </div>
            
            <div class="ml-auto d-flex align-items-center gap-2">
                <a href="<?= BASE_URL ?>/admin/queries.php" class="topbar-bell-btn <?= $unread_count > 0 ? '' : 'd-none' ?>" aria-label="Customer queries, <?= $unread_count ?> unread" title="Customer queries">
                    <svg width="20" height="20" aria-hidden="true"><use href="#nav-bell"></use></svg>
                    <?php if ($unread_count > 0): ?>
                        <span class="topbar-bell-badge font-weight-bold"><?= $unread_count > 9 ? '9+' : $unread_count ?></span>
                    <?php endif; ?>
                </a>
                <button type="button" class="theme-toggle-btn" aria-label="Toggle dark mode" title="Toggle dark mode">
                    <span class="theme-toggle-icon"></span>
                </button>
            </div>
        </div>

        <main id="adminMain" tabindex="-1" class="admin-content-wrap">
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