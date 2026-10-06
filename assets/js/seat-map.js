// assets/js/seat-map.js -- Accessible Seat Selection & Live Refresh (U-15)
(function () {
  var grid = document.getElementById('seat-grid');
  if (!grid) return;
  var btn = document.getElementById('submit-booking-btn');
  var summary = document.getElementById('summary-seat-display');
  var live = document.getElementById('seat-live');
  var radios = grid.querySelectorAll('input[name="seat"]');

  function say(msg) {
    if (!live) return;
    live.textContent = '';
    setTimeout(function () {
      live.textContent = msg;
    }, 50);
  }

  function showSelected(r) {
    if (!summary) return;
    summary.textContent = '';
    var b = document.createElement('span');
    b.className = 'badge badge-success px-2 py-1 mr-1';
    b.textContent = 'Seat #' + r.value;
    var t = document.createElement('small');
    t.className = 'text-muted';
    var seatType = r.dataset.seatType || 'Standard';
    t.textContent = '(' + seatType + ')';
    summary.append(b, t);
    if (btn) btn.disabled = false;
  }

  grid.addEventListener('change', function (e) {
    if (e.target && e.target.checked) {
      showSelected(e.target);
    }
  });

  // Availability sync: both directions, no class juggling (CSS keys off :disabled)
  function sync(booked) {
    var set = new Set(booked.map(String));
    radios.forEach(function (r) {
      var nowBooked = set.has(r.value);
      if (nowBooked && !r.disabled && r.checked) {
        r.checked = false;
        if (btn) btn.disabled = true;
        if (summary) {
          summary.textContent = 'Seat #' + r.value + ' was just taken. Please choose another.';
        }
        say('Seat #' + r.value + ' was just reserved by another passenger. Choose another seat.');
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
  }

  var delay = 30000, timer;

  var sessionExpired = false;

  function poll() {
    if (document.hidden || sessionExpired) return schedule();
    var ctl = new AbortController();
    var kill = setTimeout(function () {
      ctl.abort();
    }, 8000);
    var q = new URLSearchParams({
      bus: grid.dataset.bus || '',
      date: grid.dataset.date || '',
      time: grid.dataset.time || ''
    });

    fetch('api-seats.php?' + q, { credentials: 'same-origin', signal: ctl.signal })
      .then(function (r) {
        if (r.status === 401) {
          sessionExpired = true;
          return r.json().catch(function () { return { ok: false, error: 'session_expired' }; });
        }
        if (!r.ok) throw 0;
        return r.json();
      })
      .then(function (d) {
        if (d && d.error === 'session_expired') {
          sessionExpired = true;
          var errBox = document.getElementById('booking-validation-error');
          if (errBox) {
            var loginLink = d.login_url || 'homepage.php';
            errBox.className = 'alert alert-warning mt-3';
            errBox.innerHTML = 'Your session has expired. <a href="' + loginLink + '" class="alert-link font-weight-bold">Sign in again</a> to complete your reservation.';
            errBox.classList.remove('d-none');
          }
          if (btn) btn.disabled = true;
          say('Your session has expired. Please sign in again.');
          return;
        }
        if (d && d.ok && Array.isArray(d.booked_seats)) {
          sync(d.booked_seats);
          delay = 30000;
        }
      })
      .catch(function () {
        delay = Math.min(delay * 2, 120000); // exponential backoff on error
      })
      .finally(function () {
        clearTimeout(kill);
        if (!sessionExpired) {
          schedule();
        }
      });
  }

  function schedule() {
    if (sessionExpired) return;
    clearTimeout(timer);
    timer = setTimeout(poll, delay);
  }

  document.addEventListener('visibilitychange', function () {
    if (!document.hidden && !sessionExpired) {
      clearTimeout(timer);
      poll();
    }
  });

  schedule();

  // U-05, Phase 1.7, Phase 5.2: Double click lock, bfcache reset, and accessible inline error
  var bookingForm = document.getElementById('booking-form');
  var errBox = document.getElementById('booking-validation-error');

  grid.addEventListener('change', function () {
    if (errBox) {
      errBox.classList.add('d-none');
      errBox.textContent = '';
    }
  });

  if (bookingForm && btn) {
    bookingForm.addEventListener('submit', function (e) {
      var selectedRadio = document.querySelector('input[name="seat"]:checked');
      if (!selectedRadio) {
        e.preventDefault();
        if (errBox) {
          errBox.className = 'alert alert-danger mt-3';
          errBox.textContent = 'Please select an available seat from the seat map before confirming.';
          errBox.classList.remove('d-none');
          errBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }
        say('Please select an available seat from the seat map before confirming.');
        return false;
      }
      if (!btn.disabled) {
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>Booking...';
        var checkHidden = document.createElement('input');
        checkHidden.type = 'hidden';
        checkHidden.name = 'check';
        checkHidden.value = '1';
        bookingForm.appendChild(checkHidden);
      }
    });
  }

  // Phase 1.7: Restore button state on browser Back/Forward (bfcache) navigation
  window.addEventListener('pageshow', function () {
    if (btn) {
      btn.innerHTML = 'Confirm &amp; Reserve Ticket';
      var selectedRadio = document.querySelector('input[name="seat"]:checked');
      btn.disabled = !selectedRadio;
    }
    if (errBox) {
      errBox.classList.add('d-none');
      errBox.textContent = '';
    }
  });
})();
