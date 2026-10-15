<?php
/**
 * assets/partials/_pwa-head.php
 *
 * PWA meta tags, Web App Manifest linkage, and Service Worker registration.
 * Included within the <head> section across all user-facing and admin pages.
 */
$_pwa_base = defined('BASE_URL') ? rtrim(BASE_URL, '/') : '';
?>
<!-- PWA Web App Manifest & App Icons -->
<link rel="manifest" href="<?= $_pwa_base ?>/manifest.json">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="Bus Reservation">
<meta name="application-name" content="Bus Reservation">
<meta name="msapplication-TileColor" content="#2563eb">
<link rel="apple-touch-icon" href="<?= $_pwa_base ?>/assets/img/app-icon.svg">
<link rel="mask-icon" href="<?= $_pwa_base ?>/assets/img/app-icon.svg" color="#2563eb">

<!-- PWA Service Worker Registration -->
<script>
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      var swUrl = '<?= $_pwa_base ?>/sw.js';
      navigator.serviceWorker.register(swUrl)
        .then(function (reg) {
          // ServiceWorker registered successfully
        })
        .catch(function (err) {
          console.warn('[PWA] ServiceWorker registration failed:', err);
        });
    });
  }
</script>
