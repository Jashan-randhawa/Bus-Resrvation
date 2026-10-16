<?php
/**
 * Path-Aware BusBook Brand Preloader Partial
 * Renders the centered brand logo with glowing halo and animated progress track.
 */
$current_script_preloader = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
$is_admin_route_preloader = (strpos($current_script_preloader, '/admin/') !== false);
$preloader_prefix = $is_admin_route_preloader ? '../' : './';
?>
<!-- BusBook Brand Preloader Stylesheet -->
<link rel="stylesheet" href="<?php echo $preloader_prefix; ?>assets/css/preloader.css?v=2.4.0">

<!-- BusBook Brand Preloader Overlay -->
<div id="busbook-preloader" role="status" aria-live="polite" aria-label="Loading application">
  <div class="preloader-content">
    <div class="preloader-logo-wrap">
      <div class="preloader-halo" aria-hidden="true"></div>
      <img src="<?php echo $preloader_prefix; ?>assets/images/bus-emblem.png" alt="BusBook Logo" class="preloader-logo" width="80" height="80">
    </div>
    <div class="preloader-title">BusBook</div>
    <div class="preloader-subtitle">Bus Reservation System</div>
    <div class="preloader-track" aria-hidden="true">
      <div class="preloader-bar"></div>
    </div>
    <div class="preloader-status">Loading application...</div>
  </div>
</div>

<!-- BusBook Brand Preloader Controller -->
<script src="<?php echo $preloader_prefix; ?>assets/js/preloader.js?v=2.4.0"></script>
