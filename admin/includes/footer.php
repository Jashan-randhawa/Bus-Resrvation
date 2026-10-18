<?php
/**
 * admin/includes/footer.php - Minimalist Admin Utility Bar
 * Displays release version, server operational status, database engine, and dynamic copyright.
 */
?>
<footer class="admin-utility-footer py-3 px-4 mt-auto border-top" aria-label="Admin Utility Bar">
  <div class="container-fluid d-flex flex-column flex-md-row align-items-center justify-content-between small text-muted">
    <div class="d-flex align-items-center mb-2 mb-md-0 flex-wrap">
      <span class="badge badge-primary mr-2 font-weight-bold px-2 py-1">BusBook v2.4.0</span>
      <span class="status-indicator d-inline-flex align-items-center mr-3 text-success font-weight-bold">
        <span class="status-dot mr-1 text-success">●</span> System Operational
      </span>
      <span class="mr-3 d-none d-sm-inline">
        <span class="text-secondary font-weight-bold">Engine:</span> TiDB Serverless / MySQL 8.0 (TLS)
      </span>
      <span class="d-none d-md-inline">
        <span class="text-secondary font-weight-bold">Runtime:</span> PHP <?= PHP_MAJOR_VERSION . '.' . PHP_MINOR_VERSION ?>
      </span>
    </div>
    <div class="text-md-right">
      <span>&copy; <?= date('Y') ?> BusBook Operator Center &bull; Enterprise Fleet Control</span>
    </div>
  </div>
</footer>
