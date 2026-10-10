<?php
// admin/index.php -- Admin Dashboard
require_once __DIR__ . '/../includes/auth/admin-session.php';
require_once __DIR__ . '/../includes/db_con.php';
require_once __DIR__ . '/../includes/helpers.php';

$title = 'Dashboard';
$page_css = ['assets/css/dashboard.css'];
require_once __DIR__ . '/../includes/layout/header-admin.php';

include_once __DIR__ . '/dashboard.php';
require_once __DIR__ . '/../includes/layout/footer-admin.php';