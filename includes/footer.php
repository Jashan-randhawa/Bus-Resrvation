<?php
/**
 * includes/footer.php - Modernized 4-Column Web Footer & PWA Navigation
 * Separates heavy desktop web utility (.website-only-footer) from standalone mobile app navigation (.pwa-bottom-nav).
 */
require_once __DIR__ . '/config.php';
$is_logged_in = !empty($_SESSION['uid']);
?>
<footer class="website-only-footer public-footer" aria-label="Site Footer">
  <div class="container py-5">
    <div class="footer-grid">
      <!-- Column 1: Brand & PWA Action -->
      <div class="footer-brand-wrap">
        <a href="<?= BASE_URL ?>/homepage.php#home" class="footer-brand" aria-label="BusBook Homepage">
          <img src="<?= BASE_URL ?>/assets/images/bus-emblem.png" alt="BusBook Logo" width="32" height="32" class="footer-brand-logo">
          <span class="footer-brand-title">BusBook</span>
        </a>
        <p class="footer-tagline">
          Reliable intercity bus bookings with real-time seat selection, atomic concurrency guarantees, and instant verified PNR tracking.
        </p>
        
        <!-- PWA Install Action Button (Revealed dynamically via Service Worker beforeinstallprompt) -->
        <button type="button" class="btn btn-pwa-install pwa-install-btn" id="installPwaBtn" style="display: none;" onclick="if(window.installBusApp){window.installBusApp();}" aria-label="Install BusBook App">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 16l4-5h-3V4h-2v7H8l4 5zm-8 4h16v-2H4v2z"/>
          </svg>
          <span>Install App</span>
        </button>
      </div>

      <!-- Column 2: Quick Navigation -->
      <div class="footer-col">
        <h4 class="footer-title">Quick Links</h4>
        <ul class="footer-links">
          <li>
            <a href="<?= BASE_URL ?>/homepage.php#home" class="footer-link">Home</a>
          </li>
          <li>
            <a href="<?= BASE_URL ?>/homepage.php#search" class="footer-link">Search Buses</a>
          </li>
          <li>
            <a href="<?= BASE_URL ?>/homepage.php#pnr" class="footer-link">Track Ticket / PNR</a>
          </li>
          <li>
            <a href="<?= BASE_URL ?>/homepage.php#contact" class="footer-link">Support &amp; Feedback</a>
          </li>
          <?php if ($is_logged_in): ?>
            <li>
              <a href="<?= BASE_URL ?>/user/my-bookings.php" class="footer-link">
                My Bookings <span class="footer-link-badge">Active</span>
              </a>
            </li>
            <li>
              <a href="<?= BASE_URL ?>/user/index.php" class="footer-link">Passenger Dashboard</a>
            </li>
          <?php else: ?>
            <li>
              <a href="<?= BASE_URL ?>/login.php" class="footer-link" data-toggle="modal" data-target="#userLoginModal">Passenger Login</a>
            </li>
          <?php endif; ?>
          <li>
            <a href="<?= BASE_URL ?>/homepage.php?login=admin" class="footer-link text-muted" data-toggle="modal" data-target="#loginModal">Admin Portal</a>
          </li>
        </ul>
      </div>

      <!-- Column 3: Top Bus Routes -->
      <div class="footer-col">
        <h4 class="footer-title">Top Routes</h4>
        <ul class="footer-links">
          <li>
            <a href="<?= BASE_URL ?>/homepage.php#search" class="footer-link">Delhi &rarr; Chandigarh</a>
          </li>
          <li>
            <a href="<?= BASE_URL ?>/homepage.php#search" class="footer-link">Mumbai &rarr; Pune</a>
          </li>
          <li>
            <a href="<?= BASE_URL ?>/homepage.php#search" class="footer-link">Bengaluru &rarr; Chennai</a>
          </li>
          <li>
            <a href="<?= BASE_URL ?>/homepage.php#search" class="footer-link">Jaipur &rarr; Delhi</a>
          </li>
          <li>
            <a href="<?= BASE_URL ?>/homepage.php#search" class="footer-link">Hyderabad &rarr; Vijayawada</a>
          </li>
        </ul>
      </div>

      <!-- Column 4: Support & Trust Badges -->
      <div class="footer-col">
        <h4 class="footer-title">Help &amp; Trust</h4>
        <div class="footer-support-items">
          <div class="footer-support-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6 19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72 12.84 12.84 0 0 0 .7 2.81 2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45 12.84 12.84 0 0 0 2.81.7A2 2 0 0 1 22 16.92z"></path>
            </svg>
            <div>
              <span class="d-block small text-muted">24/7 Helpline:</span>
              <a href="tel:+9118002872665" class="font-weight-bold">+91 1800-287-2665</a>
            </div>
          </div>
          
          <div class="footer-support-row">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"></path>
              <polyline points="22,6 12,13 2,6"></polyline>
            </svg>
            <div>
              <span class="d-block small text-muted">Support Email:</span>
              <a href="mailto:support@busbook.transit">support@busbook.transit</a>
            </div>
          </div>

          <!-- Trust & Security Badges -->
          <div class="footer-trust-strip">
            <span class="trust-badge-pill" title="End-to-End Encrypted Transport">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              256-Bit TLS
            </span>
            <span class="trust-badge-pill" title="Atomic Concurrency Safe Reservation">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              Atomic Locks
            </span>
            <span class="trust-badge-pill" title="QR-Verified Digital Boarding Pass">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <polyline points="20 6 9 17 4 12"></polyline>
              </svg>
              Verified PNR
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Bottom Sub-Footer: Copyright & Legal Policies -->
    <div class="footer-sub">
      <div>
        <span>&copy; <?= date('Y') ?> BusBook System. All rights reserved.</span>
      </div>
      <ul class="footer-sub-links">
        <li><a href="<?= BASE_URL ?>/homepage.php#contact">Privacy Policy</a></li>
        <li><a href="<?= BASE_URL ?>/homepage.php#contact">Terms of Service</a></li>
        <li><a href="<?= BASE_URL ?>/homepage.php#contact">Cancellation Policy</a></li>
        <li><a href="<?= BASE_URL ?>/homepage.php?login=admin" data-toggle="modal" data-target="#loginModal">Operator Login</a></li>
      </ul>
    </div>
  </div>
</footer>

<!-- PWA Native Mobile Bottom Navigation Bar (Active in standalone mode / mobile) -->
<?php require_once __DIR__ . '/bottom-nav.php'; ?>

<!-- Scripts & Core Asset Inclusions -->
<?php require_once __DIR__ . '/layout/footer.php'; ?>
