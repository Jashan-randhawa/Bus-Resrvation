<?php require_once __DIR__ . '/../config.php'; ?>
<footer class="public-footer border-top bg-white" aria-label="Site Footer">
  <div class="container py-5">
    <div class="row align-items-center">
      <div class="col-md-6 mb-3 mb-md-0">
        <div class="d-flex align-items-center mb-2">
          <img src="<?= BASE_URL ?>/assets/images/bus.svg" alt="Bus Service Logo" width="28" height="28" class="mr-2">
          <span class="font-weight-bold text-dark h5 mb-0">Bus Service</span>
        </div>
        <p class="text-muted small mb-0">
          Reliable intercity bus bookings with real-time seat selection and verified PNR status tracking.
        </p>
      </div>
      <div class="col-md-6 text-md-right">
        <ul class="list-inline mb-2">
          <li class="list-inline-item mr-3">
            <a href="#home" class="text-muted small">Home</a>
          </li>
          <li class="list-inline-item mr-3">
            <a href="#search" class="text-muted small">Search Routes</a>
          </li>
          <li class="list-inline-item mr-3">
            <a href="#pnr" class="text-muted small">Check PNR</a>
          </li>
          <li class="list-inline-item mr-3">
            <a href="#contact" class="text-muted small">Support</a>
          </li>
          <li class="list-inline-item">
            <a href="<?= e(BASE_URL) ?>/homepage.php?login=admin" class="text-muted small" data-toggle="modal" data-target="#loginModal">Admin Portal</a>
          </li>
        </ul>
        <p class="text-muted small mb-0">
          &copy; <?= date('Y') ?> Bus Service System. All rights reserved.
        </p>
      </div>
    </div>
  </div>
</footer>
<?php require_once __DIR__ . '/footer.php'; ?>
