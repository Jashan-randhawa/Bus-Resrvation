# 🧑‍💼 Customer Portal & Booking Flow

The **Customer Portal** (`user/`) allows passengers to search routes, view real-time seat availability counts, reserve bus seats via an interactive dynamic seat picker, and manage their reservations.

---

## 1. Customer Authentication Flow

```mermaid
sequenceDiagram
    actor User as Passenger
    participant Home as homepage.php
    participant DB as TiDB / MySQL
    participant Session as PHP Session
    participant Portal as user/index.php

    User->>Home: Click "Account Login" or "Create Account"
    
    alt Register
        User->>Home: Submit name, email, password (min 8 chars), phone, address
        Home->>DB: INSERT INTO costumer (pwd = password_hash(...))
        DB-->>Home: Account created
    else Login
        User->>Home: Submit email & password
        Home->>DB: Check throttle_blocked('login:user:...')
        Home->>DB: SELECT * FROM costumer WHERE email=?
        DB-->>Home: Customer record found
        Home->>Home: Verify password_verify(pwd, hash)
        Home->>Session: Store $_SESSION['uid'], $_SESSION['name'], $_SESSION['phone']
        Home->>Portal: Redirect to /user/index.php
    else Continue with Google (Customer Only)
        User->>Home: Click "Continue with Google" (google-login.php)
        Home->>Home: Generate cryptographically secure state, nonce & S256 PKCE
        Home->>User: Redirect to Google OAuth 2.0 Consent Screen
        User->>Home: Google redirects back to google-callback.php?code=...&state=...
        Home->>Home: Validate session state & consume (single use)
        Home->>Home: Exchange code for ID token via TLS
        Home->>Home: Validate ID token (issuer, audience, exp, nonce, email_verified)
        Home->>DB: Match by google_id or email (Migration 012)
        Home->>Session: login_user('user', customer)
        Home->>Portal: Redirect to /user/index.php
    end
```

> [!NOTE]
> **Google Sign-In is strictly customer-facing.** The Administrator Portal (`admin/`) does not accept Google OAuth and strictly requires username, strong password, and optional Multi-Factor Authentication (MFA/TOTP).

---

## 2. Dashboard, Upcoming Journey Hero Card & Custom Search (`user/index.php`)

The Customer Dashboard combines an airline-grade boarding pass preview with an accessible search engine:
1. **Upcoming Journey Hero Card:** Automatically highlights the passenger's next confirmed departure with a live pulsing status badge, origin/destination route visualization, departure countdown, assigned seat & PNR pills, and 1-tap **Boarding Pass** and **Share** actions.
2. **Accessible In-DOM Custom City Dropdowns (`.custom-dropdown-widget`):**
   - Replaces inconsistent OS-native combobox popups with a theme-aware in-DOM dropdown menu (`role="listbox"`) supporting full keyboard navigation (`ArrowDown`, `ArrowUp`, `Enter`, `Escape`).
   - Formats city names in clean Title Case and dynamically filters available destination cities based on the selected origin city (`routePairs`).
   - Includes a circular **Swap Cities (`⇄`)** button that validates reverse route connectivity before swapping.
3. **Real-Time Seat Availability Calculation:**
   $$\text{Available Seats} = \text{Bus Capacity} - \text{Booked Seats}(\text{bus}, \text{date}, \text{time})$$
   Active seats include `Confirmed` and `Pending` reservations; soft-cancelled and expired hold seats are automatically liberated.

---

## 3. Dynamic Interactive Bus Seat Map Engine (`user/booking.php`)

The seat picker dynamically renders the exact capacity and layout (`2+2`, `2+1`, `1+2`, `1+1`) of the assigned vehicle (`get_bus_capacity()`, from 10 to 60 seats):

- **Fluid Mobile Seat Grid (`assets/css/seat-map.css`):** Uses CSS `clamp(38px, 11vw, 48px)` seat sizing so a 4-seat + aisle row fits cleanly down to `320px` mobile screens without horizontal scrolling.
- **Visual Seat States:**
  - Available seats render as **Blue** clickable buttons.
  - Already booked or held seats render as **Red** disabled buttons (`aria-disabled="true"`).
  - Selected seat turns **Green** (`aria-pressed="true"`) and updates the live summary.
- **Sticky Mobile Booking Bar (`.booking-sticky-footer`):** On mobile viewports (`<768px`), a fixed bottom confirmation bar displays the selected seat number, total fare, and a 1-tap **Confirm Booking** button (`min-height: 44px`).

---

## 4. Ticket Reservation & Concurrency Defense

1. **Server-Side Price & Departure Time Resolution (F1, F2):** Fare and departure times are read strictly from the database, preventing client-side price tampering.
2. **Date Boundaries (F5):** Travel dates cannot be in the past or more than 90 days in advance. Buses scheduled earlier than the current server time for today cannot be booked.
3. **Atomic Concurrency Defense (F4):** Booking insertions claim a lock in the `seat_lock` table (`PRIMARY KEY (bus_id, travel_date, seat_no)`) and rely on `uq_booking_active_seat`. Any race condition is caught immediately as duplicate key `1062`, rolling back cleanly and instructing the customer to pick another seat.
4. **Cryptographic PNR Generation:** Uses `random_bytes(5)` to generate a 10-character hex token (e.g. `B4C90A81DE`).
5. **Seat Hold & Payment Lifecycle (O13):**
   - Bookings can enter `Pending` state with a 10-minute hold window (`hold_expires_at`).
   - If not confirmed within 10 minutes, `release_expired_holds()` automatically transitions the state to `Expired`, liberating the seat for other passengers.

---

## 5. Personal Bookings, Status Filter Tabs & Soft Cancellation (`user/my-bookings.php`)

Passengers can inspect their travel history strictly bound to their authenticated session ID:
- **Responsive 3-Column Filter Tabs:** Switch between `All`, `Upcoming`, and `Past / Cancelled` reservations with live badge counts.
- Displays PNR token, bus number, route corridor, departure timestamp, seat badge, status badge, and fare.
- **Cancellation Policy (O4, F7):**
  - Only un-departed future trips within the allowed cutoff window (`APP_CANCEL_CUTOFF_MIN`) can be cancelled.
  - Cancellation marks `status = 'Cancelled'` and deletes the corresponding `seat_lock` row inside a transaction, keeping the audit record while instantly releasing the seat for new reservations.

---

## 6. Digital Boarding Pass, Native Share & HMAC-SHA256 Seal (`user/ticket.php`, `user/verify.php`)

Tickets carry a cryptographically signed verification seal, QR code, and native sharing capabilities:
1. **HMAC-SHA256 Signature:** Computed over `PNR|Date|Time|Seat` using `TICKET_HMAC_SECRET`.
2. **Dedicated Validity States:**
   - **`VALID PASS`:** Future departure, confirmed booking status.
   - **`COMPLETED`:** Departed past journey (receipt remains viewable/printable for expense accounting).
   - **`PENDING PAYMENT`:** Seat hold active, awaiting payment confirmation.
   - **`VOID`:** Cancelled or expired reservations (watermarked overlay displayed).
3. **Native Web Share API Integration:** Clicking **Share Boarding Pass** invokes `navigator.share` on mobile devices (with automatic clipboard fallback on desktop) to share trip details and the verification link.
4. **Public QR Code & Verification Endpoint (`user/verify.php`):**
   - Conductors and travelers can scan the QR code to verify the ticket against the live database using constant-time `hash_equals($expected_sig, $sig)`.

---

## 7. Customer Account Security & Profile Management (`user/profile.php`)

Passengers can manage their personal details and security settings from a dedicated profile workspace:
- **Contact Details:** Update full name, phone number, and residential address.
- **Email Change Verification:** Updating the account email requires current password verification and uniqueness checks.
- **Password Rotation:** Enforces minimum 8-character password complexity and verifies the current password before hashing with bcrypt (`PASSWORD_DEFAULT`).
- **Self-Service Account Deactivation:** Passengers can archive their account (`archived_at = NOW()`) after password confirmation, provided they have no active upcoming reservations.

---

## 8. Public PNR Verification (`homepage.php#pnr`)

Travelers can verify ticket status from the homepage without logging in:
1. Requires the **10-character PNR** and the **last 4 digits of the passenger phone number**.
2. **Rate Limited (O1):** Public lookups are protected against brute-force enumeration:
   - Max 10 attempts per 10 minutes per IP.
   - Max 5 attempts per 15 minutes per target PNR token.
3. Displays live status badge (`Confirmed`, `Pending`, `Expired`, `Cancelled`), passenger initials, route, seat number, and departure time.

---

## 9. Self-Service Email Password Reset Flow (`forgot-password.php`, `reset-password.php`, `includes/password-reset.php`)

Passengers who forget their password can request a single-use reset link by email:
1. **Zero Enumeration & Background SMTP Dispatch:** Submitting an email address on `forgot-password.php` immediately renders the identical *"Check Your Inbox"* view whether or not the account exists, flushing the HTTP response via `pr_after_response()` before running database lookup and SMTP delivery (`pr_issue_and_send()`).
2. **SHA-256 Hashed Tokens (`password_resets`):** A 256-bit random hex token (`pr_new_token()`) is emailed in the link (`?code=<token>`), while only its SHA-256 hash (`pr_hash_token()`) is stored in `password_resets.token`. Plain tokens are never stored in the database or rendered on any web page.
3. **Single-Use & Short TTL:** Links expire after `PASSWORD_RESET_TTL_MIN` (default `10` minutes, clamped `5..60`) and are deleted immediately upon use or if email delivery fails.
4. **Rate Limiting & Debounce:** Enforces a 60-second resend cooldown (`PR_DEBOUNCE_SECS`), per-email request limit (`3 / 15m`), per-IP request limit (`12 / 15m`), and per-IP bad-token lockout (`5 / 15m`).
5. **Password Reuse Prevention & Security Notice:** `reset-password.php` sends `Cache-Control: no-store` and `Referrer-Policy: no-referrer`, blocks reusing the current password, updates the bcrypt hash, logs `PASSWORD_RESET_REQUESTED` / `PASSWORD_RESET_SUCCESS` to `audit_log`, and dispatches a *"Your password was changed"* confirmation email.
