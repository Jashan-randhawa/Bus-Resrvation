<?php
/**
 * Phase 3: Path-Aware PWA Head Partial for Bus-Resrvation
 * Automatically resolves relative paths whether included from:
 * - Root pages (/index.php, /pnr.php) -> "./"
 * - Admin pages (/admin/*.php) -> "../"
 */
$current_script = isset($_SERVER['SCRIPT_NAME']) ? str_replace('\\', '/', $_SERVER['SCRIPT_NAME']) : '';
$is_admin_route = (strpos($current_script, '/admin/') !== false);
$root_prefix = $is_admin_route ? '../' : './';
?>
<!-- PWA Manifest & Mobile App Meta Tags -->
<link rel="manifest" href="<?php echo $root_prefix; ?>manifest.json">
<meta name="theme-color" content="#212529">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="BusBooking">
<link rel="icon" type="image/svg+xml" href="<?php echo $root_prefix; ?>assets/img/app-icon.svg">
<link rel="icon" type="image/png" sizes="192x192" href="<?php echo $root_prefix; ?>assets/img/app-icon-192.png">
<link rel="icon" type="image/png" sizes="512x512" href="<?php echo $root_prefix; ?>assets/img/app-icon-512.png">
<link rel="apple-touch-icon" href="<?php echo $root_prefix; ?>assets/img/app-icon-512.png">

<!-- Mobile App Ergonomics, Seat Grid Scroll & Ticket Print Fixes -->
<style>
#pwa-install-banner {
  display: none;
  position: fixed;
  bottom: 20px;
  right: 20px;
  z-index: 99999;
  background-color: #212529;
  color: #ffffff;
  border: 2px solid #ffc107;
  padding: 10px 18px;
  border-radius: 50px;
  font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
  font-weight: 600;
  font-size: 14px;
  letter-spacing: 0.3px;
  cursor: pointer;
  transition: transform 0.2s ease, background-color 0.2s ease;
}
#pwa-install-banner:hover {
  background-color: #343a40;
  transform: translateY(-2px);
}
#pwa-install-banner:active {
  transform: translateY(0);
}

/* Ensure 38-seat diagrams and admin tables scroll smoothly on narrow mobile screens */
@media (max-width: 768px) {
  .table-container,
  #seat-results,
  .table-responsive,
  #booking .table,
  #route .table,
  #bus .table,
  #customer .table {
    overflow-x: auto;
    -webkit-overflow-scrolling: touch;
  }
}

/* Keep printed tickets clean on pnr.php */
@media print {
  #pwa-install-banner {
    display: none !important;
  }
}

/* Window Controls Overlay (Desktop Custom Titlebar) */
@media (display-mode: window-controls-overlay) {
  .titlebar-drag-region {
    app-region: drag;
    -webkit-app-region: drag;
  }
  .titlebar-no-drag {
    app-region: no-drag;
    -webkit-app-region: no-drag;
  }
}
</style>

<!-- Service Worker Registration & Install Prompt Controller -->
<script>
(function () {
  const rootPrefix = <?php echo json_encode($root_prefix); ?>;

  // 1. Register Service Worker at Root Scope
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', function () {
      navigator.serviceWorker.register(rootPrefix + 'sw.js', { scope: rootPrefix })
        .then(async function (reg) {
          // Register Periodic Background Sync if supported
          if ('periodicSync' in reg) {
            try {
              const status = await navigator.permissions.query({ name: 'periodic-background-sync' });
              if (status.state === 'granted') {
                await reg.periodicSync.register('update-bus-routes', {
                  minInterval: 12 * 60 * 60 * 1000
                });
              }
            } catch (e) {}
          }
        })
        .catch(function (err) {
          console.warn('[PWA] Service Worker registration failed:', err);
        });
    });
  }

  // 2. Dynamically Inject Floating Install Button Once DOM is Ready
  let deferredInstallPrompt = null;

  function ensureInstallButton() {
    let btn = document.getElementById('pwa-install-banner');
    if (!btn && document.body) {
      btn = document.createElement('button');
      btn.id = 'pwa-install-banner';
      btn.type = 'button';
      btn.setAttribute('aria-label', 'Install Bus Reservation App');
      btn.innerHTML = '📲 Install Bus App';
      document.body.appendChild(btn);

      btn.addEventListener('click', async function () {
        if (!deferredInstallPrompt) return;
        deferredInstallPrompt.prompt();
        const choiceResult = await deferredInstallPrompt.userChoice;
        if (choiceResult.outcome === 'accepted') {
          btn.style.display = 'none';
        }
        deferredInstallPrompt = null;
      });
    }
    return btn;
  }

  // 3. Listen for Browser Installability Event
  window.addEventListener('beforeinstallprompt', function (e) {
    e.preventDefault();
    deferredInstallPrompt = e;

    if (window.matchMedia('(display-mode: standalone)').matches) {
      return;
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', function () {
        const btn = ensureInstallButton();
        if (btn) btn.style.display = 'block';
      });
    } else {
      const btn = ensureInstallButton();
      if (btn) btn.style.display = 'block';
    }
  });

  // 4. Hide Button Immediately After Successful Installation
  window.addEventListener('appinstalled', function () {
    deferredInstallPrompt = null;
    const btn = document.getElementById('pwa-install-banner');
    if (btn) btn.style.display = 'none';
  });
})();
</script>
