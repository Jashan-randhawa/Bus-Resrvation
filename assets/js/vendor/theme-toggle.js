/**
 * @jashan-randhawa/busres-ui
 * Global Light/Dark Theme Switcher with configurable storage key and accessible icon toggling.
 */
(function (root, factory) {
  if (typeof define === 'function' && define.amd) {
    define([], factory);
  } else if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    var themeModule = factory();
    root.BusThemeToggle = themeModule;
    // For backwards compatibility with existing templates calling toggleGlobalTheme()
    root.toggleGlobalTheme = themeModule.toggle;
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var DEFAULT_STORAGE_KEY = 'busres_theme_mode';
  var config = {
    storageKey: DEFAULT_STORAGE_KEY
  };

  function getSavedTheme(storageKey) {
    var key = storageKey || config.storageKey;
    try {
      var saved = localStorage.getItem(key);
      if (saved === 'dark' || saved === 'light') {
        return saved;
      }
    } catch (e) {}

    if (typeof window !== 'undefined' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
      return 'dark';
    }
    return 'light';
  }

  function applyTheme(theme, storageKey) {
    if (typeof document === 'undefined') return;
    var root = document.documentElement;
    var isDark = (theme === 'dark');

    if (isDark) {
      root.setAttribute('data-theme', 'dark');
      root.classList.add('dark-mode');
    } else {
      root.removeAttribute('data-theme');
      root.classList.remove('dark-mode');
    }

    var buttons = document.querySelectorAll('.theme-toggle-btn');
    buttons.forEach(function (btn) {
      var iconSpan = btn.querySelector('.theme-toggle-icon');
      var textSpan = btn.querySelector('.theme-toggle-text');

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

  function toggle(storageKey) {
    var key = storageKey || config.storageKey;
    var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
    var next = (current === 'dark') ? 'light' : 'dark';
    try {
      localStorage.setItem(key, next);
    } catch (e) {}
    applyTheme(next, key);
    return next;
  }

  function init(options) {
    if (options && options.storageKey) {
      config.storageKey = options.storageKey;
    }
    var theme = getSavedTheme(config.storageKey);
    applyTheme(theme, config.storageKey);
    return {
      getTheme: function () { return getSavedTheme(config.storageKey); },
      setTheme: function (t) {
        try { localStorage.setItem(config.storageKey, t); } catch (e) {}
        applyTheme(t, config.storageKey);
      },
      toggle: function () { return toggle(config.storageKey); }
    };
  }

  // Auto-init immediately in browser environment to prevent FOUC
  if (typeof document !== 'undefined') {
    applyTheme(getSavedTheme(config.storageKey), config.storageKey);

    document.addEventListener('DOMContentLoaded', function () {
      applyTheme(getSavedTheme(config.storageKey), config.storageKey);

      document.querySelectorAll('.theme-toggle-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          toggle(config.storageKey);
        });
      });
    });
  }

  return {
    init: init,
    toggle: toggle,
    applyTheme: applyTheme,
    getSavedTheme: getSavedTheme
  };
}));
