# @jashan-randhawa/bus-seat-picker

Accessible bus seat selection widget with live availability polling, exponential backoff, screen-reader announcements, and session-expiry handling.

## Installation

Configure your `.npmrc` to authenticate with GitHub Packages:

```ini
@jashan-randhawa:registry=https://npm.pkg.github.com
//npm.pkg.github.com/:_authToken=${GITHUB_TOKEN}
```

Then install the package:

```bash
npm install @jashan-randhawa/bus-seat-picker
```

Or load directly in the browser:

```html
<link rel="stylesheet" href="node_modules/@jashan-randhawa/bus-seat-picker/dist/seat-picker.css">
<script src="node_modules/@jashan-randhawa/bus-seat-picker/dist/seat-picker.js"></script>
```

## HTML Contract

The widget expects a grid container with dataset attributes and radio inputs:

```html
<div id="seat-grid" data-bus="1" data-date="2026-10-15" data-time="09:00:00">
  <input type="radio" name="seat" value="1" data-seat-type="Window" aria-label="Seat 1 window available" />
  <input type="radio" name="seat" value="2" data-seat-type="Aisle" aria-label="Seat 2 aisle available" />
</div>

<div id="summary-seat-display"></div>
<button type="submit" id="submit-booking-btn" disabled>Confirm & Reserve</button>
<div id="seat-live" class="sr-only" aria-live="polite"></div>
<div id="booking-validation-error" class="d-none"></div>
```

## JavaScript API

```javascript
const picker = BusSeatPicker.init({
  grid: '#seat-grid',
  submitButton: '#submit-booking-btn',
  summary: '#summary-seat-display',
  liveRegion: '#seat-live',
  errorBox: '#booking-validation-error',
  availabilityUrl: 'api-seats.php',
  pollInterval: 30000,
  maxBackoff: 120000,
  requestTimeout: 8000,
  classes: {
    badge: 'badge badge-success px-2 py-1 mr-1',
    hidden: 'd-none',
    alertWarning: 'alert alert-warning mt-3',
    alertLink: 'alert-link font-weight-bold'
  },
  text: {
    sessionExpired: 'Your session has expired.'
  },
  onSelect(seat, seatType, element) {
    console.log('Selected seat:', seat, seatType);
  },
  onSessionExpired(loginUrl) {
    console.warn('Session expired, redirected to:', loginUrl);
  }
});

// To clean up and disconnect:
picker.destroy();
```

## License

MIT © Jashan Randhawa
