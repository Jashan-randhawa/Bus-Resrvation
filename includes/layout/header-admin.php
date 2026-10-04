<?php
require_once __DIR__ . '/../config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'Admin') ?> - Bus Reservation</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/admin.css">
</head>
<body>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <!-- Brand Header (N-05) -->
        <div class="admin-brand">
            <a href="<?= BASE_URL ?>/admin/index.php" class="brand-link">
                <img src="<?= BASE_URL ?>/assets/images/bus.svg" alt="Logo" class="brand-logo">
                <span class="brand-title">Bus Service</span>
            </a>
            <span class="badge badge-primary brand-badge">Admin</span>
        </div>

        <!-- Admin Profile Info -->
        <div class="admin-profile">
            <img src="<?= BASE_URL ?>/assets/images/userav-min.png" alt="Admin Avatar" class="profile-avatar">
            <div class="profile-info">
                <div class="profile-name"><?= e($_SESSION['name'] ?? 'Admin') ?></div>
                <div class="profile-role">System Administration</div>
            </div>
        </div>

        <!-- Grouped Navigation (N-01 to N-04, Appendix A) -->
        <nav class="admin-nav">
            <?php
            $nav_menu = [
                'Overview' => [
                    ['Dashboard', 'index.php', null],
                ],
                'Operations' => [
                    ['Bookings', 'bookings.php', 'edit-booking.php'],
                    ['Seat Availability', 'seats.php', null],
                ],
                'Fleet & Schedule' => [
                    ['Buses', 'buses.php', 'edit-bus.php'],
                    ['Routes', 'routes.php', 'edit-route.php'],
                ],
                'People' => [
                    ['Customers', 'customers.php', 'edit-customer.php'],
                    ['Administrators', 'add-admin.php', null],
                    ['Customer Queries', 'queries.php', null],
                ],
                'System' => [
                    ['Diagnostics', 'diagnostics.php', null],
                ],
            ];
            $current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
            ?>
            <?php foreach ($nav_menu as $group => $items): ?>
                <div class="nav-group-header"><?= e($group) ?></div>
                <ul class="nav-group-list">
                    <?php foreach ($items as [$label, $file, $child]): ?>
                        <?php
                        $is_active = ($current_script === $file || ($child !== null && $current_script === $child));
                        $link_url = BASE_URL . '/admin/' . $file;
                        ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $is_active ? 'active' : '' ?>" href="<?= $link_url ?>" <?= $is_active ? 'aria-current="page"' : '' ?>>
                                <?= e($label) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endforeach; ?>
        </nav>

        <!-- Sidebar Footer: View site & Logout (N-05) -->
        <div class="admin-sidebar-footer">
            <a href="<?= BASE_URL ?>/homepage.php" class="btn btn-sm btn-outline-light btn-block mb-2" target="_blank">
                View Public Site &rarr;
            </a>
            <form action="<?= BASE_URL ?>/admin/index.php" method="post" class="m-0">
                <?= csrf_field() ?>
                <button type="submit" name="logout" value="1" class="btn btn-sm btn-danger btn-block">
                    Log out
                </button>
            </form>
        </div>
    </aside>

    <main class="admin-main">
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