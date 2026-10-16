# Changelog

All notable changes to this project are documented in this file.

## [2.4.0] - 2026-10-16

### Progressive Web App (PWA) & App Store Readiness
- **Complete Progressive Web App (PWA) Implementation**:
  - Implemented Web App Manifest (`manifest.json`) supporting standalone display mode, dual quick-actions (`Book Tickets`, `Admin Dashboard`), and high-res icon assets.
  - Developed root Service Worker (`sw.js`) implementing `sbtbs-bus-cache-v1` with non-GET mutation protection and offline fallback routing (`offline.html`).
  - Created Path-Aware Layout Head partial (`assets/partials/_pwa-head.php`) dynamically resolving root (`./`) vs admin (`../`) asset scopes, enabling mobile seat/table horizontal scroll touch ergonomics, print style protection, and DOM-injected floating install banner.
- **Web Push Notifications & Notification Actions**:
  - Service worker handles incoming remote `push` events with action buttons (*View Ticket*, *Dismiss*), vibration patterns, and app badges.
  - Implemented `notificationclick` handler focusing existing windows or navigating to target booking records.
- **Background Sync & Periodic Background Sync**:
  - Registered Background Sync (`sync`) handler for deferred replay of customer booking inquiries upon network reconnect.
  - Registered Periodic Background Sync (`periodicsync`) fetching latest route schedules every 12 hours.
- **Modern Desktop & Operating System Integrations**:
  - **Windows 11 Widgets Board**: Adaptive Cards integration with preview templates and JSON data endpoints (`widgets/bus-widget.json`, `widgets/bus-widget-data.json`).
  - **Microsoft Edge Side Panel**: Configured `edge_side_panel` with 400px preferred width.
  - **Window Controls Overlay**: Configured `window-controls-overlay` display override and titlebar drag regions for native app look and feel.
  - **Tabbed Display Mode**: Multi-document interface with `tabbed` display override and `tab_strip` configuration.
  - **Protocol Handlers**: OS registration for `web+bus` and `web+ticket` URI schemes with instant search redirection in `pnr.php`.
  - **Web Share Target API**: Inbound share listener in `pnr.php` receiving shared PNR strings from WhatsApp and messaging apps.
  - **File Handlers**: OS file associations for `.ticket`, `.json`, and `.csv` bookings and manifest exports.
- **PWABuilder & App Store Packaging Readiness**:
  - Generated square `192x192` and `512x512` PNG raster icons with explicit `purpose: "any"` and `purpose: "maskable"` entries.
  - Generated desktop (`1280x720`, wide) and mobile (`720x1280`, narrow) UI preview screenshots.
  - Added localization and text direction attributes (`lang: "en-US"`, `dir: "ltr"`).
  - Configured international age rating certification (`iarc_rating_id`) and native app store preference (`prefer_related_applications: false`).
  - Added `note_taking` quick integration endpoint (`./index.php?action=new_note`).
  - Extended domain scope (`scope_extensions`) covering Google OAuth and service subdomains, paired with `.well-known/web-app-origin-association`.
- **Automated Verification Suites**:
  - Added comprehensive PWABuilder manifest test suite (`tests/test_pwabuilder_manifest.php`) verifying all 30 Required, Recommended, and Optional criteria (30/30 passing).

### Core Platform Enhancements

- **Google Sign-In Performance & Session Data Synchronization (`includes/auth/session-bootstrap.php`, `includes/google-auth.php`, `google-callback.php`, `includes/password-reset.php`, `homepage.php`)**:
  - **Zero Callback Latency**: Configured `CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4` on Google OAuth token exchange (`oauth2.googleapis.com`) to eliminate 3–5 second dual-stack IPv6 DNS resolution stalls.
  - **Non-Blocking Session Close**: Added explicit `session_write_close()` before issuing 302 redirect headers in `google-callback.php` and within `pr_after_response()`, immediately releasing the PHP session lock so the user dashboard (`user/index.php`) loads instantaneously without lock contention.
  - **Full Account Data Synchronization**: Updated `login_user()` to populate `$_SESSION['email']`, `$_SESSION['google_id']`, and `$_SESSION['pwd_set']` in addition to `uid`, `name`, and `phone`. Synchronized Google profile display names to the database for accounts with blank or placeholder names.
  - **Customer Password Query Parity**: Updated `homepage.php` costumer authentication query to select `email, google_id, pwd_set` ensuring data parity between password and Google logins.
  - **Test Suite**: Added test Suite 11 to `tests/test_google_auth.php` covering session data completeness, IPv4 resolution enforcement, and session lock release (120/120 passing).

- **Google Sign-In Account Data Access & Sensitive Action Re-Authentication (`google-reauth.php`, `user/profile.php`, `includes/google-auth.php`, migration `013`)**:
  - **Sensitive Action Parity for Google-Only Accounts**: Closes the credential gap where Google accounts had no usable password for sensitive actions (change email, change password, delete account).
  - **Google Re-Authentication Flow (`google_begin_reauth()`, `google_reauth_ok()`)**: Requires a fresh Google confirmation within 300 seconds (`max_age=300`, `login_hint=$email`, `prompt=login`, `auth_time` claim verification) before allowing email changes or account deactivation.
  - **Explicit Credential State (`costumer.pwd_set`, Migration `013`)**: Added `pwd_set` flag (`TINYINT(1)`, default 1). Initialized to 0 for Google-created or linked accounts, and updated to 1 whenever a customer sets or resets their password.
  - **Profile Security UI (`user/profile.php`)**: Displays "Signed in with Google" and "Google-Only Credentials" badges, hides raw password fields for Google-only users in favor of a "Confirm with Google" button, and enables Google users to optionally set a permanent account password.
  - **Testing**: Added test Suite 10 to `tests/test_google_auth.php` covering `google_reauth_ok()`, `customer_proof_ok()`, session timeouts, clock skew, and static file wiring (111/111 passing).

- **"Continue with Google" sign-in for customers (`google-login.php`, `google-callback.php`, `includes/google-auth.php`, `includes/layout/google-button.php`, migration `012`)**:
  - **Secure OAuth 2.0 / OpenID Connect flow**: authorization-code exchange runs server-side over TLS with per-attempt `state`, `nonce` and PKCE (S256); the ID token's issuer, audience, expiry, nonce and `email_verified` are all checked. The client secret never reaches the browser.
  - **Strictly customer-only (not admin)**: Google authentication is scoped solely to customers (`costumer` table and `login_user('user', ...)`); the administrator portal remains password-protected and MFA-enforced.
  - **Account matching & linking rules**: matched by Google account id first, then by e-mail; unknown e-mails create a customer; a different Google account on the same e-mail is refused; archived customers are refused.
  - **Pre-hijack protection**: connecting Google to an existing password account removes that old password (the bus app does not verify e-mails at registration) and sends a notice e-mail; the customer can use *Forgot password* to set a new one.
  - **Schema**: `costumer.google_id` (unique, nullable) is added by the migration runner (`012_customer_google_signin`) and by `database/init.sql` for new installs. Google-created customers have no usable password and an empty phone number, which they enter on the booking form.
  - **UI**: a "Continue with Google" button on the homepage customer sign-in and register panes and on `login.php`, shown only when `GOOGLE_CLIENT_ID` and `GOOGLE_CLIENT_SECRET` are set.
  - **Setup**: create an OAuth client (Web application) in Google Cloud Console, add `<APP_URL>/google-callback.php` as an authorized redirect URI, set the two variables, then run `php database/db_migrate.php`. Tests: `php tests/test_google_auth.php` (88/88 passing).

- **Forgot password now works by e-mailed reset link (`forgot-password.php`, `reset-password.php`, `includes/password-reset.php`, migration `011`)**:
  - **Hashed, single-use tokens**: only the SHA-256 hash is stored in `password_resets.token`; links expire after `PASSWORD_RESET_TTL_MIN` minutes (default 10) and are deleted once used.
  - **No account enumeration**: identical response for known and unknown addresses; look-up and email delivery run after the response is sent.
  - **Abuse limits**: 3 reset e-mails per address per 15 min, 60 s debounce, per-IP lockout after 5 bad tokens; stored token is removed if the e-mail cannot be delivered.
  - **Brevo HTTPS REST API (Port 443) Transport**: delivers transactional emails over HTTPS port 443 bypassing cloud SMTP port 587 blocks, with fallback to standard SMTP.
  - **Setup**: set `BREVO_API_KEY` (or `MAIL_ENABLED=true` with SMTP variables) and `APP_URL`; run migration 011. Tests: `php tests/test_password_reset.php` (39/39 passing).

- **CI Pipeline Heuristic & Request Sanitization Fix (`homepage.php`, `.github/workflows/ci.yml`)**:
  - **Eliminated CI False Positives**: Resolved false positive in GitHub Actions CI step "Block raw request data inside SQL" caused by direct `empty($_GET['...'])` references inside `<option>` tags on lines containing `select`.
  - **Sanitized Local Variables (`homepage.php`)**: Read query parameters once into local sanitized `$search_from` and `$search_to` variables before HTML rendering and used `$search_from === ''` / `$search_to === ''` in `<option>` selected attributes.
  - **Hardened Workflow Grep Check (`.github/workflows/ci.yml`)**: Updated the workflow check to exclude vendor and node_modules directories, gracefully handle grep exit codes with `|| true`, and provide explicit actionable failure output if genuine raw request data is embedded inside SQL strings.

- **Global White & Dark Theme Navigation Sidebar Elevation (`assets/css/design-system.css`, `assets/css/admin.css`, `includes/layout/header-admin.php`, `tests/test_admin_layout.php`)**:
  - **Light Mode Pure White Surface (`--sidebar-bg: #ffffff`)**: Overhauled the dark navy hardcoded drawer to a crisp white background in Light Theme with slate text (`#475569`), subtle dividers (`#e2e8f0`), light profile card block (`#f8fafc`), clean interactive hover states (`#f1f5f9`), and primary active pills (`rgba(37, 99, 235, 0.1)`).
  - **Dark Mode Seamless Midnight Surface (`--sidebar-bg: #0f172a`)**: Configured midnight dark styling with crisp white headers (`#ffffff`), glowing accent pills, and high-contrast accessible typography when dark mode is toggled.
  - **Accessible Footer Actions & Touch Targets**: Styled the Public Homepage shortcut button with clean border and contrast in light mode and glowing outline in dark mode.
  - **Dynamic Cache-Busting Across All Shell Headers**: Added `?v=filemtime` cache-busting to `design-system.css`, `admin.css`, and `user.css` in both admin and user layout shells.
  - **Automated Verification**: Expanded `test_admin_layout.php` and `test_user_layout.php` with theme token verification suites (all suites passing 100%).

- **Search Journey City Dropdowns & Custom Select Modernization (`user/index.php`, `homepage.php`, `assets/css/user.css`, `includes/layout/header-user.php`, `tests/test_user_layout.php`)**:
  - **In-DOM Custom Dropdown Widget (`user/index.php`, `assets/css/user.css`)**: Implemented an accessible, custom styled dropdown component (`.custom-dropdown-widget`, `.custom-dropdown-btn`, `.custom-dropdown-menu`) replacing the unstylable Windows Chromium native combobox popup. Features floating elevation shadow, rounded corners (12px), city pin icons (📍), active checkmarks (✓), smooth hover states, outside click to dismiss, and full dark theme support (`html[data-theme="dark"]`).
  - **Underlying Select Synchronization**: Automatically synchronizes value selection, dynamic destination filtering (`filterDestinations()`), and bidirectional city swapping (`swapCities()`) with underlying form elements, preserving native validation and GET search parameters.
  - **Automatic Asset Cache-Busting (`includes/layout/header-user.php`)**: Appended dynamic `?v=filemtime` query tokens to all CSS links to prevent stale browser disk caching and ensure UI updates render immediately upon browser reload.
  - **Title Case City Formatting**: Replaced unformatted raw lowercase database option values (`delhi`, `jaipur`, `sirsa`, etc.) with clean Title Case display strings (`Delhi`, `Jaipur`, `Sirsa`) in both server-side PHP (`ucwords(strtolower(...))`) and client-side JavaScript (`formatCity(...)`).
  - **Symmetric Grid Alignment**: Equalized Departure City (`col-md-4`) and Destination City (`col-md-4`) columns, preventing dropdown squeeze and popup overflow on tablet and desktop viewports.
  - **Automated Regression Suite**: Expanded [`tests/test_user_layout.php`](file:///C:/Users/jasha/.gemini/antigravity/scratch/Bus-Resrvation/tests/test_user_layout.php) with validation checks (43/43 passing).


  - **High-Definition Vector Bus Icon (`assets/images/bus.svg`)**: Replaced flat monochrome black silhouette with a custom multi-stop gradient transit coach vector featuring windshield glare reflections, destination LED matrix sign, glowing LED headlights, bumper detailing, and grounding drop shadow. Scalable from 16px favicon up to 128px high-res displays.
  - **Cohesive In-Page Header Elevation (`assets/css/design-system.css`)**: Overhauled `.page-header` across all admin and customer pages with 1.7rem bold typography, linear gradient accent underline bar (`.page-header::after`), and comfortable subtitle line-height.
  - **Sticky Topbar Header Polish (`assets/css/admin.css`)**: Upgraded `.admin-topbar` with 12px backdrop blur glassmorphism, soft ambient shadow, 32px brand logo with hover tilt animation, and responsive eyebrow indicator with live pulsing dot.
  - **Public Navigation Header (`assets/css/home.css`)**: Polished `.navbar-public` and `.navbar-brand-custom` with refined logo drop-shadows, 14px backdrop blur, and modern dark-mode contrast tokens.

- **Upcoming Journey Hero Boarding Pass UI Elevation (`user/index.php`, `assets/css/user.css`)**:
  - **Modern Boarding Pass Aesthetic**: Upgraded the drab plain-text upcoming trip box to an airline-grade hero boarding pass card with smooth gradient accent borders, rounded corners (16px), and soft ambient elevation.
  - **Pulsing Active Status Chip**: Replaced flat uppercase badge with a SaaS-grade live indicator pill (`.journey-badge-upcoming`) featuring a pulsating emerald status dot (`.journey-badge-dot`).
  - **Elevated PNR Code Pill**: Separated booking reference into a crisp mono pill (`.journey-pnr-pill`) positioned opposite the status pill.
  - **Title-Cased Route Corridor (`.journey-corridor`)**: Formatted raw city names with clean title-casing (e.g. `Delhi → Sirsa`) and crisp directional arrow typography.
  - **Structured Metadata Chips (`.journey-meta-grid`)**: Replaced jumbled text and bullet points with structured chips displaying departure date/time, fleet bus number, and assigned seat pill badge.
  - **Elevated Boarding Pass Action Button (`.journey-action-btn`)**: Styled action button with smooth hover lift, shadow, and full touch target responsiveness.
  - Added full dark mode support (`html[data-theme="dark"] .upcoming-journey-card`).

- **Transit Route Schedule Management & UI Modernization (`admin/routes.php`, `admin/edit/edit-route.php`, `assets/css/admin.css`)**:
  - **Executive Operational KPI Metrics**: Added 4 top stat cards (`.stat-card`) displaying Active Schedules (`stat-routes`), Fleet Allocated in Service (`stat-fleet`), Connected Cities Served (`stat-bookings`), and Average Route Tariff (`stat-revenue`) with dynamic range hints.
  - **Smart Search & Filter Toolbar (`.filter-bar`)**: Introduced responsive multi-criteria filtering: instant keyword search across origin/destination/bus (`q`), origin city dropdown, fleet bus dropdown, and 6-option sorting (departure time, price, corridor A-Z, ID). All filters safely preserved across pagination and archive tab toggling.
  - **Elevated Route Corridor Presentation**: Replaced plain table cells with visual Route Corridors (`.route-corridor`) featuring bold city typography, primary directional indicators (`&rarr;`), and fleet capacity metadata (`capacity` and `layout`).
  - **Direct Conductor Manifest Shortcuts**: Integrated a 1-click shortcut button (`📋 Manifest`) on every route schedule row directly jumping to `admin/manifest.php?bus=...&time=...` for instant passenger verification.
  - **Touch-Friendly Mobile Stacked Cards (`.route-card-mobile`)**: Replaced cramped tables on `<768px` devices with elevated card items featuring `.route-meta-strip` chips, prominent corridor titles, and 44px minimum height touch action buttons with 0 horizontal overflow.
  - **Interactive City Swap Controls (`#swapModalCitiesBtn`, `#swapEditCitiesBtn`)**: Added rotating circular swap buttons between Origin and Destination fields in both route creation and editing views, allowing instant reverse route configuration with client-side conflict prevention.
  - **Fleet Bus Selector Enhancements**: Enriched `<select name="bus">` options to display seating capacity and seat layout (e.g., `Bus #101 (36 seats, 2+2)`) for immediate operator visibility.
  - Added test suite [`tests/test_route_ui.php`](file:///C:/Users/jasha/.gemini/antigravity/scratch/Bus-Resrvation/tests/test_route_ui.php) with 19 assertion checks (19/19 passing).

- **User Section Mobile Modernization (Phase 6 - Profile & Security Settings Responsiveness, F9, F10)**:
  - **Responsive Form Container Padding (`user/profile.php`)**: Reduced card body padding on mobile devices (`p-3 p-md-4`) to provide comfortable input field width and breathing room on 320px–375px screens.
  - **Accessible Password Toggle Controls (`user/profile.php`)**: Added accessible `aria-label="Toggle password visibility"` and scaled toggle buttons to 44x44px touch targets with dynamic `Show password`/`Hide password` label updates.
  - **Full-Width Mobile Form Actions (`user/profile.php`)**: Applied `.btn-block-mobile` and 44px minimum height to all 4 profile form submission buttons (profile update, password change, email update, account deactivation) for effortless thumb reach on phones.
  - **Mobile Keyboard Hinting**: Specified `inputmode="tel"` on contact phone input to summon the numerical keypad on touch devices.
  - Expanded `tests/test_user_layout.php` with Suite 9 assertions (40/40 passing).

- **User Section Mobile Modernization (Phase 5 - Ticket & Verify View Responsiveness, F8)**:
  - **Fluid Boarding Pass Container (`user/ticket.php`)**: Reduced card container padding on phones (`p-3 p-md-5`) and added responsive scaling to the `.ticket-void-overlay` watermark on narrow screens (<576px) to eliminate text overflow.
  - **Responsive Digital Pass & QR Code (`user/ticket.php`)**: Replaced fixed 160px min-width with fluid centered layout (`max-width: 220px`) and raised helper/meta text sizes to 12px for WCAG AAA readability.
  - **Native Web Share API Integration (`user/ticket.php`)**: Added `#shareTicketBtn` utilizing modern `navigator.share` on supported mobile devices to effortlessly share ticket PNR and itinerary details.
  - **Safe-Area Insets & Public Verification (`user/verify.php`)**: Added `viewport-fit=cover` to public verification page, set 12px label typography, and upgraded return button to 44px min-height touch target.
  - Expanded `tests/test_user_layout.php` with Suite 8 assertions (35/35 passing).

- **User Section Mobile Modernization (Phase 4 - My Reservations Responsiveness, F7)**:
  - **Category Tabs Layout (`user/my-bookings.php`, `assets/css/user.css`, F7)**: Wrapped booking filter tabs in `.booking-tabs-wrap` and `.booking-tabs-nav` with equal 3-column grid (`grid-template-columns: repeat(3, 1fr)`) under 576px. Removed decorative emojis from tab labels to eliminate wrapping and prevent horizontal overflow on 320px screens.
  - **Elevated Mobile Reservation Cards (`user/my-bookings.php`)**: Redesigned mobile cards with prominent PNR badge, route arrow header, status tag, clear fare display, and full 44px min-height touch action buttons (View Ticket & Cancel Booking).
  - **Compact Mobile Pagination (`user/my-bookings.php`)**: Introduced compact mobile pagination control (`Prev`, `Page X of Y`, `Next`) with 44px touch targets on `<768px`, while reserving full numbered pagination for desktop screens.
  - **Mobile-Responsive Cancel Modal (`user/my-bookings.php`)**: Formatted cancellation modal action buttons with stacked vertical layout (`flex-column-reverse flex-sm-row`) and full width (`.btn-block-mobile`) on phones.
  - Expanded `tests/test_user_layout.php` with Suite 7 assertions (29/29 passing).

- **User Section Mobile Modernization (Phase 3 - Booking & Seat Map Responsiveness)**:
  - **Fluid Seat Size Clamping (`assets/css/seat-map.css`, F2)**: Computed seat dimensions using fluid CSS clamp `--seat-w: clamp(38px, calc((100vw - 110px) / 5), 44px)` and `--aisle-w: clamp(14px, 4vw, 20px)`, eliminating 320px viewport horizontal overflow across all 2+2, 1+2, 2+1, and 1+1 layouts.
  - **Deduplication of Seat Media Rules (`assets/css/design-system.css`, F4)**: Removed dead, conflicting 480px seat rules (42x54px) from `design-system.css`, ensuring `seat-map.css` serves as the authoritative seat styling.
  - **Mobile Sticky Seat Summary & Confirm Bar (`user/booking.php`, `assets/css/user.css`, F5)**: Styled `.booking-sticky-footer` with sticky bottom positioning and safe-area padding (`env(safe-area-inset-bottom)`), keeping the selected seat number, live fare, and confirm button visible under the thumb without scrolling back up.
  - **Mobile Form & Keyboard Optimization**: Added `inputmode="tel"` to phone contact field in `user/booking.php` and decreased card padding (`p-3 p-md-4`) to maximize available width on narrow screens.
  - **Two-Column Legend Wrapping**: Updated `.bus-legend` to wrap cleanly with 12px text size on mobile screens.
  - Expanded `tests/test_user_layout.php` with 5 new assertion checks (24/24 passing).

- **User Section Mobile Modernization (Phase 2 - Search Page & Result Cards)**:
  - **Single-Thumb Search Controls (`user/index.php`)**: Stacked form fields vertically on mobile with 16px font-size and min 48px touch targets, eliminating iOS Safari viewport auto-zooming (F10).
  - **Floating Circular Swap Control (F6)**: Replaced bulky separate row with a sleek circular button (`.swap-cities-btn-circle`) centered between departure and destination fields (44x44px touch target).
  - **Full-Width Action Buttons**: Styled search submission and boarding pass buttons to span full container width on phones for easy thumb reach.
  - **Sticky Topbar Scroll Anchor**: Added `scroll-margin-top: calc(var(--topbar-h, 56px) + 16px)` on `#search-results` preventing topbar from obscuring search results.
  - **Elevated Mobile Result Cards**: Refined mobile card visual hierarchy showcasing Departure Time (`h5`) and Tariff (`h4 text-primary`) as prominent lead elements, with full-width booking action row.
  - **Responsive Sort & Return Trip Controls**: Wrapped sorting and return trip actions cleanly on small screens without horizontal clipping.
  - Expanded `tests/test_user_layout.php` with 6 new assertion checks (19/19 passing).

- **User Section Mobile Modernization (Phases 0 & 1 - Shell, Header, Drawer)**:
  - **Phase 0 (Baseline & Safety Net)**: Created automated test suite `tests/test_user_layout.php` with 13 static assertion checks covering HTML comment balance, viewport tags, controller scripts, stylesheets, touch targets, and ARIA attributes.
  - **Phase 1 (Shell, Header & Drawer)**:
    - Added `viewport-fit=cover` to `includes/layout/header-user.php` for notched devices in portrait and landscape.
    - Linked `assets/css/admin.css` directly in `<head>` and removed blocking `@import url('admin.css')` from `assets/css/user.css`.
    - Linked `assets/css/admin-mobile-responsive.css` in the user header, providing mobile drawer styling, safe-area padding, and touch target scaling.
    - Added `#sidebarCloseBtn` inside the user navigation drawer with SVG close icon (`#nav-close`).
    - Integrated authoritative `assets/js/admin-mobile.js` controller into `includes/layout/footer-user.php` with focus trap, `inert` state management, Escape handling, and swipe-left gesture support. Removed duplicate inline sidebar script.
    - Modernized user topbar with 44px `.admin-menu-btn`, two-line title block (`.topbar-title-block`, `.topbar-eyebrow`, `.page-title-mobile`), and 44px `.theme-toggle-btn`.
    - Extracted inline session timeout detection into `assets/js/user-idle.js`, cleanly decoupling behavioral logic from PHP markup.

- **Topbar Section Header & Eyebrow Pill UI Elevation Across All Sections**:
  - Upgraded the topbar title block (`.topbar-title-block`) across all 5 administration sections (Overview, Operations, Fleet & Schedule, People, and System).
  - Replaced plain raw text with a SaaS-grade micro-badge pill (`.topbar-eyebrow`) featuring a luminous status dot (`.topbar-eyebrow-dot`), tight letter-tracking (`0.08em`), pill rounding (`9999px`), and soft border/tint backgrounds.
  - Implemented 5 distinct semantic color themes for section groups:
    - **Overview**: Indigo (`group-pill-overview`)
    - **Operations**: Emerald (`group-pill-operations`)
    - **Fleet & Schedule**: Cyan / Sky (`group-pill-fleet-schedule`)
    - **People**: Violet (`group-pill-people`)
    - **System**: Amber (`group-pill-system`)
  - Elevated title typography (`.page-title-mobile`) with tight tracking (`-0.022em`), 700 weight, responsive max-width calculation (`calc(100vw - 165px)`), and proper dark-mode theme token adaptation (`var(--c-text)`).
  - Added seamless desktop breadcrumb display in the topbar (`.topbar-breadcrumb-sep` and `.topbar-desktop-title`) connecting section badge with page title.
  - Expanded `tests/test_admin_layout.php` with 2 new assertion checks (33/33 passing).

- **Admin Mobile Navigation Bar & Topbar Modernization (Option A Architecture)**:
  - Overhauled the administrative shell navigation into a modern, accessible, touch-first mobile drawer and topbar experience adhering to the Option A specification.
  - **Inline SVG Sprite System**: Introduced 16 unified stroke-style SVG navigation icons (`currentColor`, 20px) in `includes/layout/header-admin.php` replacing inconsistent operating-system emojis. Icons seamlessly inherit theme and active states across desktop and mobile.
  - **Modern Sticky Topbar (Section 3.1)**:
    - Sticky 56px header with safe-area insets (`env(safe-area-inset-top)`), surface backdrop blur (8px), and subtle bottom border.
    - 44x44px borderless hamburger menu button with visible focus ring.
    - Two-line title block featuring a nav group eyebrow (e.g. *Operations*, *Fleet & Schedule*, *People*) and CSS-ellipsis page title, removing the legacy hardcoded 15-character PHP truncation.
    - Added an unread queries notification bell button (`topbar-bell-btn`) linked to `queries.php` with a counter badge (showing `9+` when exceeding 9 unread messages).
    - 44x44px theme toggle touch target.
  - **Refined Off-Canvas Drawer (Section 3.2)**:
    - Authoritative single width rule `min(86vw, 320px)` eliminating conflicting 250px and 280px media queries.
    - Flex-column structure: fixed brand header (restoring the "Bus Service" title on mobile viewports), dedicated 44x44px close button (`#sidebarCloseBtn`), user profile card with distinct viewer role pill, scrollable navigation area (`overflow-y: auto`, `overscroll-behavior: contain`), and fixed footer with safe-area bottom padding.
    - 48px item row touch targets (`--nav-item-h: 48px`), 12px border radius, 32x32px icon tile, 3px left active accent bar, and WCAG AA compliant `#94a3b8` group headers.
  - **Authoritative Controller (`assets/js/admin-mobile.js`, Section 3.4)**:
    - Single initialization guard (`window.__adminMobileNavInitialized`).
    - Robust `inert` state machine applying `inert` to the drawer when closed and to `.admin-main` when the drawer is open.
    - Focus trapping with keyboard loop fallback and automatic focus return to `#sidebarToggle` upon closing.
    - Coordinated scroll locking with Bootstrap modals (`show.bs.modal`) to prevent backdrop conflicts.
    - Touch swipe-to-close gesture detection (horizontal delta > 50px with vertical drift < 100px).
  - **Motion & Accessibility Tokens (Section 4 & 5)**:
    - Standardized tokens `--nav-item-h: 48px`, `--nav-radius: 12px`, `--nav-active-bg: rgba(37,99,235,0.16)`, `--nav-label: #94a3b8`, and `--topbar-h: 56px`.
    - Gated hover animations inside `@media (hover: hover)` to eliminate stuck hover states on touch screens.
    - Honored `prefers-reduced-motion` across drawer translate and overlay fade animations.
  - **Automated Verification**: Extended `tests/test_admin_layout.php` to 31 static assertion checks across 7 suites covering HTML balance, script deduplication, sprite symbols, ARIA modal attributes, tokens, and responsive constraints.
    - Fixed unclosed HTML comment on obsolete AOS script block in `includes/layout/footer-admin.php` that inadvertently swallowed `admin-mobile.js` and sidebar toggle scripts.
    - Unified drawer open/close controller inside `assets/js/admin-mobile.js`, eliminating duplicate inline scripts. Added dedicated `#sidebarCloseBtn`, inert attribute management for closed drawer (< 992px), swipe-to-dismiss gesture detection, and focus restoration to `#sidebarToggle`.
    - De-duplicated `.table-stack` CSS across `admin-mobile-responsive.css` and `admin.css`. Removed hardcoded light values in favor of design system dark mode tokens (`var(--c-surface)`, `var(--c-border)`, `var(--c-text-muted)`).
    - Removed dead Bootstrap 5 `.form-select` selector, exempted `.form-control-sm` from the 44px touch height rule, and scoped broad `.btn-group` rules.
    - Aligned action cell in `admin/queries.php` to standard `data-label="Actions"`.
  - **Phase 2 (Shell)**:
    - Configured `viewport-fit=cover` across `header-admin.php`, `admin/mfa.php`, and `header-login.php`.
    - Removed hardcoded PHP 15-character truncation on `$title` in `header-admin.php` to let CSS ellipsis manage screen width up to `55vw`.
    - Integrated safe-area insets (`env(safe-area-inset-*)`) for notched mobile displays in `.admin-topbar` and `.admin-sidebar`.
  - **Phase 3 (Pages)**:
    - Added single-column fallback for `.kpi-grid` under 380px in `assets/css/dashboard.css` to prevent number clipping on narrow mobile screens.
    - Converted `admin/bookings.php` search and filter card into an expandable mobile accordion with a dedicated toggle button.
    - Added docked sticky styling with safe-area padding for the bulk operations action bar (`#bulkBookingsForm`) on mobile viewports.
    - Implemented full-screen modal responsive styling for `#singleReplyModal` in `assets/css/admin.css`.
    - Added word wrapping (`overflow-wrap: break-word`, `word-break: break-word`) to `.table-stack td` to eliminate horizontal overflow on long emails, notes, and route names.
    - Added `inputmode="numeric"` to 2FA security code input in `admin/mfa.php` for automatic mobile numeric keypad triggering.
  - **Phase 5 (Testing & CI)**:
    - Added automated static layout test suite `tests/test_admin_layout.php` (17/17 passing) validating comment balance, script loading, `inert` state handling, focus return, safe-area insets, token consistency, and CI pipeline integration.

- **Redesign Performance Overview — Privacy-First Analytics Dashboard**:
  - Redesigned the Performance Overview section into a modern, responsive, information-dense SaaS analytics dashboard adhering strictly to privacy-first principles.
  - Implemented 4 responsive KPI cards: **Total Bookings** (with comparison delta vs previous period), **Confirmed Bookings** (with contextual percentage of total), **Pending Bookings** (with contextual percentage), and **Cancellation Rate** (with status-coded delta and warning threshold indicators).
  - Enhanced **Booking & Revenue Trends** combo chart with dual Y-axes (Bookings on left, Revenue on right), compact header legend, custom interval aggregation toggle (`Daily` vs `Weekly`), dashed gridlines, and informative hover tooltips without passenger details.
  - Created a responsive **Booking Status** donut visualization with prominent center total and confirmation percentage, accessible counts and shares legend, and zero-division handling for empty periods.
  - Replaced dense text lists with a compact **Key Insights** section providing 3 mathematically verified insights (volume trend, confirmation stability, cancellation range) alongside a visible privacy badge (`Aggregate analytics only · Personal booking details hidden`).
  - Enforced strict privacy safeguards across all layers: server-side aggregated datasets only, no passenger names, phone numbers, email addresses, booking IDs, PNRs, or transaction references in frontend datasets, charts, tooltips, or error handlers.
  - Maintained full compatibility with dark mode (`html[data-theme="dark"]`), keyboard accessibility, WCAG AA contrast, and `prefers-reduced-motion`.
  - All test suites passing (24/24 in `test_perf_overview.php`, 25/25 in `test_dashboard_audit.php`, 26/26 in `test_departure_carousel.php`, 68/68 in `test_admin_login.php`).

- **Attention-Needed Action Center Interface Upgrade**:
  - Redesigned the Attention-Needed operational section into a clean, compact, professional action-center interface.
  - Introduced an operational priority system with strict visual hierarchy: **Critical** (subtle red indicator, e.g. overbooked trips, seat-lock concurrency warnings), **High Priority** (restrained amber indicator, e.g. departures boarding within 60 mins, unanswered inquiries), and **Attention** (informational blue indicator, e.g. low-occupancy departures).
  - Implemented automatic sorting by severity rank ensuring critical interventions are always surfaced at the top.
  - Streamlined row layout with compact meta-line, severity badge, clear issue title, concise one-line context, and aligned action links (`Inspect Manifest →`, `View Manifest →`, `Open Inbox →`, `Review Seats →`, `Run Diagnostics →`).
  - Added a calm, restrained empty state ("All clear: No outstanding issues require attention") when zero operational alerts exist.
  - Polished responsive behavior for desktop, tablet, and stacked mobile screens with comprehensive dark theme tokens.


- **Admin Dashboard Performance Overview Business Analytics Workspace**:
  - Redesigned the Performance Overview section into a self-contained business analytics workspace with dynamic date context, comparison range indication, and synchronized 7d/30d/90d duration selectors.
  - Implemented 4 authoritative primary KPI tiles with semantic delta indicators and context footnotes: **Reservations**, **Confirmed Revenue** (strictly excluding pending, cancelled, and expired holds), **Cancellation Rate** (with restrained threshold color coding and percentage point change), and **Average Confirmed Booking Value** (`Confirmed Revenue / Eligible Confirmed Bookings`).
  - Corrected Chart.js dataset semantics in `assets/js/perf-chart.js`: replaced crude `total - cancelled` estimation with true status aggregation (`Confirmed`, `Pending`, `Cancelled`, `Expired`), rendering stacked daily volume bars alongside the confirmed revenue line.
  - Upgraded the Booking Outcomes donut panel to display a full status distribution breakdown with exact counts, percentages, and centered cancellation rate.
  - Added a structured **Key Insights** panel showcasing Busiest Day, Highest Revenue Day, Average Daily Volume, Booking Lead Time, and Cancellation Trend.
  - Enhanced the collapsible **Daily Breakdown Table** with sticky table headers and full status columns (Total Bookings, Confirmed, Pending, Cancelled, Expired, Confirmed Revenue, Cancel Rate).
  - Synchronized the streaming, formula-safe **Daily Breakdown CSV Export** with the extended status breakdown and audit logging.
  - Verified 100% test pass rate across all suites including `tests/test_perf_overview.php`.
- **Today's Departures Section Simplification & Optimization**:
  - Replaced the horizontal auto-scrolling carousel with a clean, vertically scrollable compact operational list (`.dep-card-wrap`, `.dep-table-head`, `.dep-list-scroll`, `.dep-row`).
  - Streamlined departure rows to show only essential operational data: departure time, origin &rarr; destination, bus vehicle badge, status chip (`Boarding soon` / `Upcoming`), seat occupancy count with slim progress bar, seats remaining (with `OVERBOOKED` detection), and direct manifest action.
  - Prioritized active departures in chronological order (nearest departure first) and excluded completed trips (`Departed`) from the primary upcoming list.
  - Added clean empty states distinguishing between "all departures completed today" and "no departures configured for today" with shortcuts to route schedules and full manifest.
  - Removed carousel overhead: eliminated auto-rotation timer, 5-second progress bars, prev/next buttons, pause toggles, slide counters, and navigation dots.
  - Safely removed obsolete single-use JavaScript files (`assets/js/dep-strip.js`, `assets/js/dep-carousel.js`) and carousel-only CSS declarations.
  - Implemented responsive desktop grid, tablet column compaction (<992px), and mobile stacked card view (<576px) with dark theme overrides and `prefers-reduced-motion` compliance.
  - Updated departure test suite `tests/test_departure_carousel.php` (26/26 passing).
- **Admin Executive Dashboard Deep Audit & Upgrade**:
  - Established authoritative definitions for all executive and operational KPIs, segregating window performance from all-time directory counts.
  - Implemented dual date reporting modes: **By Journey Date** (`mode=journey`, default for transport operations) and **By Booking Date** (`mode=created`, for revenue/sales tracking).
  - Eliminated duplicate `$top_routes` SQL execution and introduced dual ranking for Top Transit Corridors: **By Reservation Volume** and **By Confirmed Route Revenue**.
  - Added operational **Attention-Needed Panel** surfacing actionable customer support inquiries, overbooked departures, trips boarding within 60 minutes, low-occupancy departures (<30%), and real-time seat-lock concurrency checks.
  - Added **Average Confirmed Booking Value** calculation and **Booking Lead Time** analytics (`DATEDIFF(journey_date, booking_creation_date)`).
  - Integrated streaming, formula-safe **Daily Breakdown CSV Export** (`?export=daily_csv`) with administrative audit logging.
  - Role-gated System Health and diagnostic endpoints to `super_admin` only to prevent unauthorized privilege exposure.
  - Added dedicated test suite `tests/test_dashboard_audit.php` (25/25 passing).
- **Admin Dashboard Redesign (Plan v2)**:
  - Unified all 8 KPI cards into an executive grid (`.stat-card`) with real context sublines (Today's bookings, Active routes, Active vehicles, Total fleet capacity, Non-archived customers, Open inquiries, Active administrators, Confirmed revenue).
  - Streamlined KPI card design by removing redundant header badges and replacing legacy emoji with an inline SVG icon sprite (`#icon-wallet`, `#icon-ticket`, `#icon-users`, `#icon-mail`, `#icon-bus`, `#icon-route`, `#icon-seat`, `#icon-shield`, `#icon-clock`, `#icon-refresh`).
  - Added collapsible KPI Definitions & Methodology footnote under the KPI grid (`#kpiDefinitions`).
  - Relocated Today's Scheduled Departures directly beneath the KPI grid as a 5-second auto-advancing card strip (`.dep-strip`, `dep-strip.js`) featuring real-time status chips (Departed, Boarding soon, Upcoming), animated progress bar, slide counter, seat availability, occupancy progress meters, and dotted route line.
  - Positioned 30-Day Performance Overview full width with KPI indicator tiles, Chart.js interactive combo chart & outcome donut chart, highlights line, and collapsible daily breakdown table.
  - Redesigned Top Transit Corridors as a full-width ranked card list with proportional volume progress bars, route header, bus badges, and route revenue.
  - Extracted modular dashboard styles into `assets/css/dashboard.css` with dark mode support (`html[data-theme="dark"]`) and WCAG AA contrast compliance.
- **Departure Schedule Carousel (A12)**:
  - Multi-card departure carousel with auto-sliding, pause guards, touch swipe, and accessibility support.


## [2.3.0] - 2026-10-08

### Added
- **Admin Section Security Remediation Plan**: Fully implemented and verified 32 findings across 4 phases:
  - Phase 1 (Access & Auth): Session hard lifetime cap (8h), password rotation check, fail-closed role check, RFC 6238 TOTP 2FA engine with AES-256-GCM encryption, scoped rate limiting, timing equalization, append-only triggers on `audit_log`, and archival script.
  - Phase 2 (Money & Reporting): RFC 5321 SMTP client with TLS, guarded inquiry email updates, manifest revenue confirmed-only filter with hold segregation, PII masking and export auditing, server-side tariff lookup, locked journey edit parameters, and 30-day dashboard reporting.
  - Phase 3 (Data Integrity): Active route schedule overlap checks, route edit lockdown on active bookings, bus capacity bounds (10–60), container startup migration gating (`MIGRATE_ON_START=1`), safe migration deduping, customer foreign key linkage (`customer_id`), and migration script reconciliation.
  - Phase 4 (Hygiene & Hardening): Diagnostics count reconciliation, double-escaping fixes, SQL `LIKE` wildcard escaping (`escape_like()`), enforced Content-Security-Policy with SRI hashes, read-only GET cleanliness, CLI hold expiration script, and strict manifest date format validation.
- **GitHub Packages Release Automation**: Corrected GHCR metadata tagging with semver, edge, and conditional latest; release bundle archive workflow.

## [2.2.0] - 2026-10-04

### Added
- **Atomic Booking Concurrency & Seat Locking (Issues 2, 4, 5)**: Added transactional `seat_lock` acquisition in `create_booking()`, rejecting uncatalogued fleet vehicles, eliminating `INSERT IGNORE`, and handling MySQL 1062 unique constraint violations with resubmission recovery.
- **Stale Lock Eviction & Lifecycle Management (Issue 2)**: Added `purge_stale_seat_locks()` and upgraded `release_expired_holds()` to purge orphan locks associated with cancelled, expired, or deleted bookings.
- **Atomic Cancellation Helper (Issue 3)**: Introduced `cancel_booking()` ensuring atomic status transition to `Cancelled` and deletion from `seat_lock` in a single transaction across `admin/bookings.php` and `user/my-bookings.php`.
- **Booking Concurrency Diagnostics (Issue 1)**: Added `booking_concurrency_status()` evaluating modern `seat_lock` integrity and active-seat partial constraints without checking deprecated `uq_booking_seat`.
- **Concurrent Test Harness & Suite 14 (Issue 11)**: Created synchronized worker `tests/concurrency_worker.php` and added Suite 14 (Tests A to I) covering same-departure races, day-level lock constraints, hold release, atomic rollback, and 8-worker parallel competition.
- **Password Visibility Toggle (Issue 5)**: Added accessible Show/Hide toggle button in the admin login password group with `aria-label` and `aria-pressed` states, auto-resetting on submit and modal close.
- **Caps Lock Detection & Live Alerts (Issue 6)**: Added real-time Caps Lock state listener with `aria-live="polite"` accessible announcement to minimize mistyped password lockout risks.
- **Admin Password Recovery Guidance (Issue 6)**: Added clear UI recovery guidance directing administrators to super administrators for password reset procedures.

### Changed
- **Migration Conflict Guards (Issues 6, 7, 8)**: Guarded migration 001 against recreating retired `uq_booking_seat`, added pre-migration conflict detection to migration 005 prior to `uq_booking_active_seat` creation, and added conflict checks prior to migration 007 `seat_lock` backfill.
- **Seat Occupancy Visualizer (Issue 10)**: Updated `admin/seats.php` all-departures lock query to join `seat_lock` with `booking` filtering for active statuses (`Confirmed`, `Pending`).
- **Admin Modal Visual Redesign & Palette (Issues 3, 4, 7)**: Redesigned the admin modal header with dedicated slate-900 surface (`#0f172a`), inline brand bus logo, and amber "Restricted access" badge.
- **Accessibility & WCAG AA Contrast Compliance (Issue 7)**: Upgraded helper and label contrast to `#64748b` (4.76:1 ratio against white) and implemented full dark theme support (`html[data-theme="dark"]`).
- **Admin Entry Point Links (Issue 2)**: Converted the header "Admin Portal" button and footer link from JavaScript-only placeholders to accessible, bookmarkable links pointing to `homepage.php?login=admin`, preserving modal trigger attributes for progressive enhancement.

### Fixed
- **Diagnostics False Alarms (Issue 1)**: Replaced retired index check on `uq_booking_seat` with comprehensive table constraint diagnostics in `admin/diagnostics.php`.
- **Session Bootstrap CLI Warning**: Added null coalescing check to `$_SERVER['REQUEST_METHOD']` in `includes/auth/session-bootstrap.php` for seamless CLI test execution.
- **Admin Sign-in Redirects (Issue 1, Issue 8)**: Added GET query parameter handling on `homepage.php` to automatically open the admin login modal when unauthenticated or redirected with `?login=admin`.
- **Session Expiry & Deactivation Notices**: Added whitelist handling for `&error=expired` and `&error=deactivated` so signed-out or deactivated admins receive clear, actionable feedback.
- **Session Guard Expiry Flagging**: Updated `admin-session.php` to distinguish expired active sessions from unauthenticated visits when redirecting.
- **Test Coverage**: Added automated regression checks in `tests/run_tests.php`, standalone test harness in `tests/test_admin_login.php`, and manual test verification checklist in `docs/admin-login-test-checklist.md`.

## [2.1.0] - 2026-10-04

### Security & Hardening
- **SQL Injection Remediation (C-01, C-02)**: Converted all database queries project-wide to parameterized prepared statements (`db_one`, `db_all`, `db_exec`).
- **Authentication & Password Security (C-03, C-04)**: Implemented bcrypt password hashing (`password_hash`/`password_verify`), widened database password columns to 255 chars, and removed hardcoded seed credentials. Added secure CLI admin provisioning script.
- **CSRF Defense (H-03)**: Added CSRF tokens and server-side verification to all forms and actions; converted state-changing GET deletions to POST requests.
- **Role Isolation & Session Security (H-01, H-02)**: Implemented strict role-based session bootstrap with session regeneration, 30-minute idle expiration, and secure cookie flags (`HttpOnly`, `SameSite=Lax`, HTTPS detection).
- **Double-Booking & Race Condition Prevention (H-04, H-08)**: Enforced transactional seat bookings with row-level locks and unique constraints; server-side authoritative fare resolution; randomized 10-char hex PNR tokens.
- **PNR Privacy & Rate Limiting (C-05, H-05)**: Replaced sequential PNR guessing with two-factor rate-limited lookup requiring the last 4 digits of passenger phone.
- **XSS & Output Sanitization (C-06, H-07)**: Sanitized all dynamic outputs with `e()` HTML escaping across admin, user, and public templates.
- **Container Hardening (M-05, M-06)**: Upgraded Dockerfile to PHP 8.4-apache, configured Apache HTTP security headers (CSP, HSTS, X-Frame-Options, etc.), and added healthcheck.
- **CI & Automation (M-08)**: Added GitHub Actions CI pipeline for PHP 8.4 linting, composer validation, and SQL anti-pattern checks; enabled Dependabot.
