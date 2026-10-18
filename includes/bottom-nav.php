<?php
/**
 * includes/bottom-nav.php - Native-style fixed bottom navigation bar
 * Designed for Progressive Web App (PWA) standalone mode and compact mobile viewports.
 */
require_once __DIR__ . '/config.php';

$current_script = basename($_SERVER['SCRIPT_NAME'] ?? '');
$is_logged_in = !empty($_SESSION['uid']);

// Determine active bottom navigation item
$active_tab = 'home';
if ($current_script === 'my-bookings.php' || $current_script === 'ticket.php') {
    $active_tab = 'tickets';
} elseif ($current_script === 'profile.php') {
    $active_tab = 'profile';
} elseif ($current_script === 'booking.php') {
    $active_tab = 'search';
}
?>
<nav class="pwa-bottom-nav" id="pwaBottomNav" aria-label="Mobile Navigation Bar">
  <div class="bottom-nav-container">
    <!-- Tab 1: Home -->
    <a href="<?= BASE_URL ?>/homepage.php#home" class="bottom-nav-item <?= $active_tab === 'home' ? 'active' : '' ?>" aria-label="Home">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"></path>
        <polyline points="9 22 9 12 15 12 15 22"></polyline>
      </svg>
      <span>Home</span>
    </a>

    <!-- Tab 2: Search Buses -->
    <a href="<?= BASE_URL ?>/homepage.php#search" class="bottom-nav-item <?= $active_tab === 'search' ? 'active' : '' ?>" aria-label="Search Buses">
      <svg viewBox="0 0 24 24" aria-hidden="true">
        <circle cx="11" cy="11" r="8"></circle>
        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
      </svg>
      <span>Search</span>
    </a>

    <!-- Tab 3: My Tickets / Bookings -->
    <?php if ($is_logged_in): ?>
      <a href="<?= BASE_URL ?>/user/my-bookings.php" class="bottom-nav-item <?= $active_tab === 'tickets' ? 'active' : '' ?>" aria-label="My Bookings">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V9z"></path>
          <line x1="9" y1="12" x2="9.01" y2="12"></line>
          <line x1="13" y1="12" x2="15" y2="12"></line>
        </svg>
        <span>My Tickets</span>
      </a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/homepage.php#pnr" class="bottom-nav-item <?= $active_tab === 'tickets' ? 'active' : '' ?>" aria-label="Track PNR Status">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M3 9a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v2a2 2 0 0 0 0 4v2a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-2a2 2 0 0 0 0-4V9z"></path>
          <line x1="9" y1="12" x2="9.01" y2="12"></line>
          <line x1="13" y1="12" x2="15" y2="12"></line>
        </svg>
        <span>Track PNR</span>
      </a>
    <?php endif; ?>

    <!-- Tab 4: Profile / Sign In -->
    <?php if ($is_logged_in): ?>
      <a href="<?= BASE_URL ?>/user/profile.php" class="bottom-nav-item <?= $active_tab === 'profile' ? 'active' : '' ?>" aria-label="My Account">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
          <circle cx="12" cy="7" r="4"></circle>
        </svg>
        <span>Profile</span>
      </a>
    <?php else: ?>
      <a href="<?= BASE_URL ?>/login.php" class="bottom-nav-item <?= $active_tab === 'profile' ? 'active' : '' ?>" data-toggle="modal" data-target="#userLoginModal" aria-label="Sign In">
        <svg viewBox="0 0 24 24" aria-hidden="true">
          <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
          <polyline points="10 17 15 12 10 7"></polyline>
          <line x1="15" y1="12" x2="3" y2="12"></line>
        </svg>
        <span>Sign In</span>
      </a>
    <?php endif; ?>
  </div>
</nav>
