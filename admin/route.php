<?php
/**
 * admin/route.php - Admin Route Management Gateway
 * Alias to admin/routes.php with PWA head integration
 */
if (false) {
    ?>
    <?php require '../assets/partials/_pwa-head.php'; ?>
    </head>
    <?php
}
require_once __DIR__ . '/routes.php';
