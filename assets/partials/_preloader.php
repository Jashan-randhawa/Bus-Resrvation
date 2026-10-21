<?php
/**
 * Path-Aware BusBook Brand Preloader Partial
 * Renders the centered brand logo with glowing halo and animated progress track.
 * Dynamically resolves root prefix whether served from root, 1-level portal (/admin/, /user/),
 * or 2-level nested subdirectories (e.g. /admin/edit/).
 */
$current_script_preloader = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
$is_nested_admin_preloader = (strpos($current_script_preloader, '/admin/edit/') !== false || strpos($current_script_preloader, '/user/edit/') !== false || (bool)preg_match('#/(admin|user)/[^/]+/#i', $current_script_preloader));
$is_admin_route_preloader = (strpos($current_script_preloader, '/admin/') !== false || strpos($current_script_preloader, '/user/') !== false);
$preloader_prefix = $is_nested_admin_preloader ? '../../' : ($is_admin_route_preloader ? '../' : './');
?>
<!-- BusBook Brand Preloader Stylesheet -->
<link rel="stylesheet" href="<?php echo $preloader_prefix; ?>assets/css/preloader.css?v=2.4.0">

<!-- BusBook Brand Preloader Overlay -->
<div id="busbook-preloader" role="status" aria-live="polite" aria-label="Loading application">
  <div class="preloader-content">
    <div class="preloader-logo-wrap">
      <div class="preloader-halo" aria-hidden="true"></div>
      <img src="<?php echo $preloader_prefix; ?>assets/images/bus-emblem.png" alt="BusBook Logo" class="preloader-logo" width="80" height="80" onerror="this.onerror=null;this.src='<?php echo (defined('BASE_URL') ? BASE_URL : '') . '/assets/images/bus-emblem.png'; ?>';">
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
