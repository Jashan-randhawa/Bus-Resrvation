/* assets/js/theme-toggle.js -- Global Dark/Light Theme Switcher */
(function() {
  var STORAGE_KEY = 'busres_theme_mode';

  function getSavedTheme() {
    try {
      var saved = localStorage.getItem(STORAGE_KEY);
      if (saved === 'dark' || saved === 'light') {
        return saved;
      }
    } catch (e) {}

    // Fall back to system preference
    if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return 'dark';
    }
    return 'light';
  }

  function applyTheme(theme) {
    var root = document.documentElement;
    if (theme === 'dark') {
      root.setAttribute('data-theme', 'dark');
      root.classList.add('dark-mode');
    } else {
      root.removeAttribute('data-theme');
      root.classList.remove('dark-mode');
    }

    // Update all toggle buttons on the page
    var buttons = document.querySelectorAll('.theme-toggle-btn');
    buttons.forEach(function(btn) {
      var iconSpan = btn.querySelector('.theme-toggle-icon');
      var textSpan = btn.querySelector('.theme-toggle-text');
      var isDark = (theme === 'dark');

      btn.setAttribute('aria-label', isDark ? 'Switch to light mode' : 'Switch to dark mode');
      btn.setAttribute('title', isDark ? 'Switch to light mode' : 'Switch to dark mode');

      if (iconSpan) {
        iconSpan.innerHTML = isDark
          ? '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>'
          : '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>';
      }

      if (textSpan) {
        textSpan.textContent = isDark ? 'Light' : 'Dark';
      }
    });
  }

  // Expose global toggler
  window.toggleGlobalTheme = function() {
    var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    var next = (current === 'dark') ? 'light' : 'dark';
    try {
      localStorage.setItem(STORAGE_KEY, next);
    } catch (e) {}
    applyTheme(next);
  };

  // Immediate initialization before DOM parse to avoid FOUC (Flash of Unstyled Content)
  var initialTheme = getSavedTheme();
  applyTheme(initialTheme);

  // Sync state once DOM is loaded
  document.addEventListener('DOMContentLoaded', function() {
    applyTheme(getSavedTheme());

    // Bind click events on any .theme-toggle-btn element
    document.querySelectorAll('.theme-toggle-btn').forEach(function(btn) {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        window.toggleGlobalTheme();
      });
    });
  });
})();
