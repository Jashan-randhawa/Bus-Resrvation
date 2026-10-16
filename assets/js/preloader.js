/**
 * BusBook Preloader & Loading State Controller
 * Manages smooth fadeout upon page readiness, fallback timeout, and programmatic API.
 */
(function() {
  const PRELOADER_ID = 'busbook-preloader';
  const MIN_DISPLAY_MS = 250; // brief smooth transition
  const MAX_SAFETY_MS = 2500; // fail-safe timeout
  const startTime = Date.now();

  function dismiss() {
    const el = document.getElementById(PRELOADER_ID);
    if (!el || el.classList.contains('preloader-hidden')) return;

    const elapsed = Date.now() - startTime;
    const remaining = Math.max(0, MIN_DISPLAY_MS - elapsed);

    setTimeout(() => {
      el.classList.add('preloader-hidden');
      setTimeout(() => {
        el.style.display = 'none';
      }, 420);
    }, remaining);
  }

  // Dismiss when window load event fires
  if (document.readyState === 'complete') {
    dismiss();
  } else {
    window.addEventListener('load', dismiss);
  }

  // Safety timer to prevent perpetual block on slow 3rd-party assets
  setTimeout(dismiss, MAX_SAFETY_MS);

  // Global programmatic API
  window.BusBookLoader = {
    show: function(message) {
      let el = document.getElementById(PRELOADER_ID);
      if (!el) return;
      if (message) {
        const msgEl = el.querySelector('.preloader-status');
        if (msgEl) msgEl.textContent = message;
      }
      el.style.display = 'flex';
      // Force reflow for CSS transition
      void el.offsetHeight;
      el.classList.remove('preloader-hidden');
    },
    hide: function() {
      dismiss();
    }
  };
})();
