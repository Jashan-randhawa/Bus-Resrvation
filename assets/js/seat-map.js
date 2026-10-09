// assets/js/seat-map.js -- App Initializer for Accessible Seat Selection (U-15)
(function () {
  var grid = document.getElementById('seat-grid');
  if (!grid) return;
  var btn = document.getElementById('submit-booking-btn');
  var bookingForm = document.getElementById('booking-form');
  var errBox = document.getElementById('booking-validation-error');
  var submitTimeout = null;

  function resetButton() {
    if (submitTimeout) {
      clearTimeout(submitTimeout);
      submitTimeout = null;
    }
    if (btn) {
      btn.innerHTML = 'Confirm &amp; Reserve Ticket';
      var selectedRadio = document.querySelector('input[name="seat"]:checked');
      btn.disabled = !selectedRadio;
    }
  }

  // Initialize the vendored/packaged BusSeatPicker widget
  var picker = null;
  if (typeof BusSeatPicker !== 'undefined') {
    picker = BusSeatPicker.init({
      grid: '#seat-grid',
      submitButton: '#submit-booking-btn',
      summary: '#summary-seat-display',
      liveRegion: '#seat-live',
      errorBox: '#booking-validation-error',
      availabilityUrl: 'api-seats.php',
      pollInterval: 15000,
      maxBackoff: 60000,
      requestTimeout: 8000,
      classes: {
        badge: 'badge badge-success px-2 py-1 mr-1',
        hidden: 'd-none',
        alertWarning: 'alert alert-warning mt-3',
        alertLink: 'alert-link font-weight-bold'
      },
      text: {
        sessionExpired: 'Your session has expired.',
        signInPrompt: 'Sign in again',
        signInSuffix: ' to complete your reservation.'
      }
    });
  }

  // Clear validation message when selection changes
  grid.addEventListener('change', function () {
    if (errBox) {
      errBox.classList.add('d-none');
      errBox.textContent = '';
    }
  });

  // U-05, Phase 1.7, Phase 5.2, Issue 7: Double click lock, 20s timeout, and accessible inline error
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
        if (picker && typeof picker.say === 'function') {
          picker.say('Please select an available seat from the seat map before confirming.');
        }
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

        // 20-second timeout resetting "Booking..." button on network failure or stalled submit
        submitTimeout = setTimeout(function () {
          resetButton();
          if (errBox) {
            errBox.className = 'alert alert-warning mt-3';
            errBox.textContent = 'Reservation request timed out. Please check your connection and submit again.';
            errBox.classList.remove('d-none');
          }
        }, 20000);
      }
    });
  }

  // Phase 1.7: Restore button state on browser Back/Forward (bfcache) navigation
  window.addEventListener('pageshow', function () {
    resetButton();
    if (errBox) {
      errBox.classList.add('d-none');
      errBox.textContent = '';
    }
  });
})();
