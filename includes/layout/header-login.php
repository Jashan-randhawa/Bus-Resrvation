<?php require_once __DIR__ . '/../config.php'; ?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, viewport-fit=cover">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/bootstrap.min.css?v=4.6.2">
    
    <!-- Design System & Auth Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/design-system.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/public.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/theme-emerald.css?v=<?= file_exists(dirname(__DIR__, 2) . '/assets/css/theme-emerald.css') ? filemtime(dirname(__DIR__, 2) . '/assets/css/theme-emerald.css') : '2.4.1' ?>">
    
    <!-- Global Theme Toggle (Head load to avoid FOUC) -->
    <script src="<?= BASE_URL ?>/assets/js/theme-toggle.js"></script>

    <!-- Favicon -->
    <link rel="shortcut icon" href="<?= BASE_URL ?>/favicon.ico">
    <link rel="icon" type="image/x-icon" href="<?= BASE_URL ?>/favicon.ico">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= BASE_URL ?>/assets/images/bus-emblem.png">
    <link rel="icon" type="image/svg+xml" href="<?= BASE_URL ?>/assets/images/bus.svg">

    <!-- PWA Meta Tags & Service Worker -->
    <?php require_once dirname(__DIR__, 2) . '/assets/partials/_pwa-head.php'; ?>
    <title><?= e($title ?? 'Sign In') ?> - BusBook</title>
</head>

<body>
    <!-- BusBook Brand Preloader Overlay -->
    <?php require_once dirname(__DIR__, 2) . '/assets/partials/_preloader.php'; ?>