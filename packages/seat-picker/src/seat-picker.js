/**
 * @jashan-randhawa/bus-seat-picker
 * Accessible bus seat selection widget with live availability polling.
 */
(function (root, factory) {
  if (typeof define === 'function' && define.amd) {
    define([], factory);
  } else if (typeof module === 'object' && module.exports) {
    module.exports = factory();
  } else {
    root.BusSeatPicker = factory();
  }
}(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  function resolveElement(target) {
    if (!target) return null;
    if (typeof target === 'string') {
      return document.querySelector(target);
    }
    return target.nodeType ? target : null;
  }

  function isSafeUrl(url) {
    if (!url || typeof url !== 'string') return false;
    var trimmed = url.trim();
    if (trimmed.startsWith('/') || trimmed.startsWith('./') || trimmed.startsWith('../')) {
      return true;
    }
    return /^https?:\/\//i.test(trimmed);
  }

  function BusSeatPicker(opts) {
    var options = opts || {};
    this.options = options;

    this.grid = resolveElement(options.grid || '#seat-grid');
    if (!this.grid) {
      this.destroyed = true;
      return;
    }

    this.submitBtn = resolveElement(options.submitButton || '#submit-booking-btn');
    this.summary = resolveElement(options.summary || '#summary-seat-display');
    this.live = resolveElement(options.liveRegion || '#seat-live');
    this.errorBox = resolveElement(options.errorBox || '#booking-validation-error');

    this.availabilityUrl = options.availabilityUrl || 'api-seats.php';
    this.pollInterval = options.pollInterval || 30000;
    this.maxBackoff = options.maxBackoff || 120000;
    this.requestTimeout = options.requestTimeout || 8000;
    this.currentDelay = this.pollInterval;

    this.classes = Object.assign({
      badge: 'badge badge-success px-2 py-1 mr-1',
      hidden: 'd-none',
      alertWarning: 'alert alert-warning mt-3',
      alertLink: 'alert-link font-weight-bold'
    }, options.classes || {});

    this.text = Object.assign({
      sessionExpired: 'Your session has expired.',
      signInPrompt: 'Sign in again',
      signInSuffix: ' to complete your reservation.',
      seatTaken: 'Seat #{seat} was just taken. Please choose another.',
      seatTakenLive: 'Seat #{seat} was just reserved by another passenger. Choose another seat.',
      selectedPrefix: 'Seat #'
    }, options.text || {});

    this.onSelect = typeof options.onSelect === 'function' ? options.onSelect : null;
    this.onSessionExpired = typeof options.onSessionExpired === 'function' ? options.onSessionExpired : null;

    this.sessionExpired = false;
    this.destroyed = false;
    this.timer = null;
    this.activeController = null;

    this._bindEvents();
    this.schedule();
  }

  BusSeatPicker.prototype.say = function (msg) {
    if (!this.live) return;
    this.live.textContent = '';
    var self = this;
    setTimeout(function () {
      if (!self.destroyed && self.live) {
        self.live.textContent = msg;
      }
    }, 50);
  };

  BusSeatPicker.prototype.showSelected = function (radio) {
    if (!radio) return;
    var seatVal = radio.value;
    var seatType = radio.dataset.seatType || 'Standard';

    if (this.summary) {
      this.summary.textContent = '';
      var b = document.createElement('span');
      b.className = this.classes.badge;
      b.textContent = this.text.selectedPrefix + seatVal;

      var t = document.createElement('small');
      t.className = 'text-muted';
      t.textContent = ' (' + seatType + ')';

      this.summary.append(b, t);
    }

    if (this.submitBtn) {
      this.submitBtn.disabled = false;
    }

    if (this.onSelect) {
      this.onSelect(seatVal, seatType, radio);
    }
  };

  BusSeatPicker.prototype.sync = function (booked) {
    if (!Array.isArray(booked)) return;
    var set = new Set(booked.map(String));
    var radios = this.grid.querySelectorAll('input[name="seat"]');
    var self = this;

    radios.forEach(function (r) {
      var nowBooked = set.has(r.value);
      if (nowBooked && !r.disabled && r.checked) {
        r.checked = false;
        if (self.submitBtn) self.submitBtn.disabled = true;
        if (self.summary) {
          self.summary.textContent = self.text.seatTaken.replace('{seat}', r.value);
        }
        self.say(self.text.seatTakenLive.replace('{seat}', r.value));
      }
      if (r.disabled !== nowBooked) {
        r.disabled = nowBooked;
        var lbl = r.getAttribute('aria-label') || '';
        r.setAttribute(
          'aria-label',
          lbl.replace(/(available|booked)$/, nowBooked ? 'booked' : 'available')
        );
      }
    });
  };

  BusSeatPicker.prototype.handleSessionExpiry = function (loginUrl) {
    this.sessionExpired = true;
    var safeUrl = isSafeUrl(loginUrl) ? loginUrl : 'homepage.php';

    if (this.errorBox) {
      this.errorBox.textContent = '';
      this.errorBox.className = this.classes.alertWarning;

      var prefixSpan = document.createElement('span');
      prefixSpan.textContent = this.text.sessionExpired + ' ';

      var link = document.createElement('a');
      link.href = safeUrl;
      link.className = this.classes.alertLink;
      link.textContent = this.text.signInPrompt;

      var suffixSpan = document.createElement('span');
      suffixSpan.textContent = this.text.signInSuffix;

      this.errorBox.append(prefixSpan, link, suffixSpan);
      this.errorBox.classList.remove(this.classes.hidden);
    }

    if (this.submitBtn) {
      this.submitBtn.disabled = true;
    }

    this.say(this.text.sessionExpired);

    if (this.onSessionExpired) {
      this.onSessionExpired(safeUrl);
    }
  };

  BusSeatPicker.prototype.poll = function () {
    if (this.destroyed || document.hidden || this.sessionExpired) {
      return this.schedule();
    }

    if (this.activeController) {
      this.activeController.abort();
    }

    var ctl = new AbortController();
    this.activeController = ctl;

    var killTimeout = setTimeout(function () {
      ctl.abort();
    }, this.requestTimeout);

    var q = new URLSearchParams({
      bus: this.grid.dataset.bus || '',
      date: this.grid.dataset.date || '',
      time: this.grid.dataset.time || ''
    });

    var self = this;
    var separator = this.availabilityUrl.indexOf('?') === -1 ? '?' : '&';
    var url = this.availabilityUrl + separator + q.toString();

    fetch(url, { credentials: 'same-origin', signal: ctl.signal })
      .then(function (r) {
        if (r.status === 401) {
          self.sessionExpired = true;
          return r.json().catch(function () {
            return { ok: false, error: 'session_expired' };
          });
        }
        if (!r.ok) throw new Error('HTTP ' + r.status);
        return r.json();
      })
      .then(function (d) {
        if (self.destroyed) return;
        if (d && d.error === 'session_expired') {
          self.handleSessionExpiry(d.login_url);
          return;
        }
        if (d && d.ok && Array.isArray(d.booked_seats)) {
          self.sync(d.booked_seats);
          self.currentDelay = self.pollInterval;
        }
      })
      .catch(function (err) {
        if (self.destroyed || (err && err.name === 'AbortError')) return;
        self.currentDelay = Math.min(self.currentDelay * 2, self.maxBackoff);
      })
      .finally(function () {
        clearTimeout(killTimeout);
        self.activeController = null;
        if (!self.sessionExpired && !self.destroyed) {
          self.schedule();
        }
      });
  };

  BusSeatPicker.prototype.schedule = function () {
    if (this.destroyed || this.sessionExpired) return;
    clearTimeout(this.timer);
    var self = this;
    this.timer = setTimeout(function () {
      self.poll();
    }, this.currentDelay);
  };

  BusSeatPicker.prototype._bindEvents = function () {
    var self = this;

    this._onChange = function (e) {
      if (e.target && e.target.checked && e.target.name === 'seat') {
        self.showSelected(e.target);
      }
    };
    this.grid.addEventListener('change', this._onChange);

    this._onVisibility = function () {
      if (!document.hidden && !self.sessionExpired && !self.destroyed) {
        clearTimeout(self.timer);
        self.poll();
      }
    };
    document.addEventListener('visibilitychange', this._onVisibility);
  };

  BusSeatPicker.prototype.destroy = function () {
    this.destroyed = true;
    clearTimeout(this.timer);
    if (this.activeController) {
      this.activeController.abort();
    }
    if (this.grid && this._onChange) {
      this.grid.removeEventListener('change', this._onChange);
    }
    if (this._onVisibility) {
      document.removeEventListener('visibilitychange', this._onVisibility);
    }
  };

  return {
    init: function (options) {
      return new BusSeatPicker(options);
    }
  };
}));
