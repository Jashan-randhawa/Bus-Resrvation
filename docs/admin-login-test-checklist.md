# Admin Login Verification & Test Checklist

Reference: *Admin Login Improvement Plan - Bus Reservation System (PHP, MySQL)*, Section 7.

This checklist documents the testing procedure and expected results for validating the administrator sign-in flow, modal behavior, session-expiry redirects, keyboard navigation, and accessibility.

---

## 1. Functional Test Matrix

| Case | Steps to Execute | Expected Result | Verified |
| :--- | :--------------- | :-------------- | :------: |
| **Expired Session** | Sign in as admin, wait past 30 minutes (or trigger timeout in `admin-session.php`), open `admin/index.php` | Redirected to `homepage.php?login=admin&error=expired`; modal opens automatically; message: *Your admin session has expired. Please sign in again.* (Info alert) | [ ] |
| **Deactivated Account** | Deactivate an admin in database/admin settings, then load any admin page while signed in | Signed out immediately; redirected to `homepage.php?login=admin&error=deactivated`; modal opens; message: *This admin account has been deactivated. Please contact a super administrator.* (Warning alert) | [ ] |
| **Direct Link** | Open `homepage.php?login=admin` in a new browser tab | Page loads with admin modal open and focus automatically set in the email field | [ ] |
| **Unknown Parameter** | Open `homepage.php?login=admin&error=<script>` in browser | Whitelist filter ignores unknown error code; modal opens with no message; no script runs (XSS protected) | [ ] |
| **Wrong Password** | Submit an incorrect password into the admin sign-in form | Modal reopens with generic error alert; email is preserved; password field is cleared | [ ] |
| **Throttling** | Attempt 5 consecutive failed logins for one account | Rate-limit message displayed (*Too many failed attempts*); account enumeration prevented | [ ] |

---

## 2. Accessibility, Usability & Responsive Tests

| Case | Steps to Execute | Expected Result | Verified |
| :--- | :--------------- | :-------------- | :------: |
| **Keyboard Navigation** | Tab through the page, open modal, tab through form fields and close control | Logical tab sequence; visible focus indicators; pressing `Escape` closes the modal | [ ] |
| **Screen Reader** | Navigate using NVDA / JAWS / VoiceOver | Modal dialog announced by accessible title; alerts announced via `role="alert"` / `aria-live` | [ ] |
| **Dark Theme** | Toggle theme switch, then open the admin modal | Slate header (`#0f172a`) retained; dark-mode surface applied to modal body; text contrast satisfies WCAG AA (≥ 4.5:1) | [ ] |
| **Mobile Layout** | Test viewport at 375 px and 768 px widths | No horizontal scrollbar; modal card, header badge, inputs, and submit button fit comfortably | [ ] |
| **Caps Lock Detection** | Turn Caps Lock ON and type inside the password field | Warning badge appears immediately; turns off when Caps Lock is deactivated | [ ] |
| **Password Visibility** | Click the Show/Hide password toggle button | Switches input between masked and plaintext; toggles `aria-label` and `aria-pressed`; resets to masked on close or submit | [ ] |

---

## 3. Regression & Security Checks

- [ ] **Automated Test Suite**: Run `php tests/test_admin_login.php` and `php tests/run_tests.php` — all tests pass.
- [ ] **CI Lint**: Run `find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l` — no syntax errors.
- [ ] **CSP Compliance**: Check browser developer console on `homepage.php` — no Content Security Policy violations.
- [ ] **Audit Logging**: Confirm successful and failed admin sign-ins continue to record `LOGIN` and `LOGIN_FAILED` entries in `audit_log`.
