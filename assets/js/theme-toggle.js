/**
 * @jashan-randhawa/busres-ui
 * Permanent Light Mode Enforcer & Legacy Theme Storage Purger
 */
(function (root, factory) {
  if (typeof define === 'function' && define.amd) {
    define([], factory);
  } else if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    var themeModule = factory();
    root.BusThemeToggle = themeModule;
    root.toggleGlobalTheme = themeModule.toggle;
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  var LEGACY_STORAGE_KEYS = ['busres_theme_mode', 'busbook_theme', 'theme'];

  function purgeLegacyDarkTheme() {
    try {
      LEGACY_STORAGE_KEYS.forEach(function (k) {
        localStorage.removeItem(k);
      });
    } catch (e) {}

    if (typeof document !== 'undefined') {
      var root = document.documentElement;
      root.setAttribute('data-theme', 'light');
      root.classList.remove('dark-mode');
      root.classList.remove('dark');

      try {
        var metas = document.querySelectorAll('meta[name="theme-color"]');
        metas.forEach(function (meta) {
          meta.setAttribute('content', '#ffffff');
        });
      } catch (e) {}

      // Clean up any lingering toggle buttons in DOM
      var buttons = document.querySelectorAll('.theme-toggle-btn');
      buttons.forEach(function (btn) {
        btn.remove();
      });
    }
  }

  function getSavedTheme() {
    return 'light';
  }

  function applyTheme() {
    purgeLegacyDarkTheme();
    return 'light';
  }

  function toggle() {
    purgeLegacyDarkTheme();
    return 'light';
  }

  function init() {
    purgeLegacyDarkTheme();
    return {
      getTheme: getSavedTheme,
      setTheme: function () { purgeLegacyDarkTheme(); },
      toggle: toggle
    };
  }

  // Auto-enforce immediately upon load
  purgeLegacyDarkTheme();

  if (typeof document !== 'undefined') {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', purgeLegacyDarkTheme);
    } else {
      purgeLegacyDarkTheme();
    }
  }

  return {
    init: init,
    toggle: toggle,
    applyTheme: applyTheme,
    getSavedTheme: getSavedTheme
  };
}));
