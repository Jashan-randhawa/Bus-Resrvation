<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth/session-bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/db_con.php';
ob_start();
$msg = "";
$msg_type = "info";
$open_modal = ""; // Tracks which modal/tab to re-open on validation error or success (E1)
$preserved_email = "";

// Admin login modal & session-status GET handling (Issue 1)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['login'] ?? '') === 'admin')) {
  $open_modal = 'admin';
  $reason = $_GET['error'] ?? '';
  if ($reason === 'deactivated') {
    $msg = 'This admin account has been deactivated. Please contact a super administrator.';
    $msg_type = 'warning';
  } elseif ($reason === 'expired') {
    $msg = 'Your admin session has expired. Please sign in again.';
    $msg_type = 'info';
  }
}

// Read-only queries for the central search & booking card (A1, P10, P13)
$route_archived_sql = table_has_column($link, 'route', 'archived_at') ? ' WHERE archived_at IS NULL' : '';
$route_pairs = db_all($link, "SELECT DISTINCT city1, city2 FROM route{$route_archived_sql} ORDER BY city1 ASC, city2 ASC");
$from_cities = array_values(array_unique(array_column($route_pairs, 'city1')));
$to_cities = array_values(array_unique(array_column($route_pairs, 'city2')));
$user_role = $_SESSION['role'] ?? null;

$popular_routes = db_all($link, "
    SELECT city1, city2, MIN(price) AS min_price, COUNT(DISTINCT busno) AS bus_count
    FROM route
    {$route_archived_sql}
    GROUP BY city1, city2
    ORDER BY bus_count DESC, min_price ASC
    LIMIT 6
");
$total_routes_count = (int)(db_one($link, "SELECT COUNT(*) AS c FROM route{$route_archived_sql}")['c'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['user']) || isset($_POST['admin']))) {
  csrf_verify();
  $role = isset($_POST['admin']) ? 'admin' : 'user';
  $email = strtolower(trim((string)($_POST['email'] ?? '')));
  $pwd = (string)($_POST['pwd'] ?? '');
  $preserved_email = $email;
  $ip = client_ip();

  if ($email === '' || $pwd === '') {
    $msg = 'Please fill in all the required fields.';
    $msg_type = 'warning';
    $open_modal = ($role === 'admin') ? 'admin' : 'user';
  } else {
    // Scoped rate limiting keys (Issue 5: separate portal counters, pair throttling, spray brake)
    $acctKey = 'login:' . $role . ':' . $email;
    $pairKey = 'login:pair:' . $ip . ':' . $email;
    $ipKey   = 'login:ip:' . $ip;

    if (throttle_blocked($link, $pairKey, 5, 900)
     || throttle_blocked($link, $acctKey, 20, 900)
     || throttle_blocked($link, $ipKey, 20, 900)) {
      http_response_code(429);
      if ($role === 'admin') {
        try {
          audit($link, 'LOGIN_FAILED', 'admin', null, null, ['email' => $email, 'reason' => 'throttled']);
        } catch (Throwable $e) {}
      }
      $msg = 'Too many failed attempts. Please try again later.';
      $msg_type = 'danger';
      $open_modal = ($role === 'admin') ? 'admin' : 'user';
    } else {
      $sql = $role === 'admin'
        ? 'SELECT id, name, phone, Password AS pwd, role, is_active, last_login_at, password_changed_at, totp_secret, totp_enabled FROM admin WHERE Email_id = ? LIMIT 1'
        : 'SELECT id, name, phone, pwd FROM costumer WHERE email = ? LIMIT 1';
      $row = db_one($link, $sql, 's', [$email]);

      // Timing equalization against user enumeration (Issue 28)
      static $dummy_hash = null;
      if ($dummy_hash === null) {
        $dummy_hash = password_hash('busres-timing-equalizer-dummy-password', PASSWORD_DEFAULT);
      }
      if (!$row) {
        password_verify($pwd, $dummy_hash);
      }

      $matched = false;
      if ($row) {
        // Deactivated admin account check (Issue 20)
        if ($role === 'admin' && isset($row['is_active']) && (int)$row['is_active'] === 0) {
          try {
            audit($link, 'LOGIN_FAILED', 'admin', (int)$row['id'], null, ['email' => $email, 'reason' => 'account_deactivated']);
          } catch (Throwable $e) {}
          $msg = 'This account has been deactivated.';
          $msg_type = 'warning';
          $open_modal = 'admin';
        } else {
          $matched = password_verify($pwd, $row['pwd']);

          // O2: Only allow legacy migration if stored value is NOT a password hash
          if (!$matched && password_get_info($row['pwd'])['algo'] === null && hash_equals($row['pwd'], $pwd)) {
            $matched = true;
            $newHash = password_hash($pwd, PASSWORD_DEFAULT);
            $updateSql = $role === 'admin'
              ? 'UPDATE admin SET Password = ?, password_changed_at = NOW() WHERE id = ?'
              : 'UPDATE costumer SET pwd = ? WHERE id = ?';
            db_exec($link, $updateSql, 'si', [$newHash, (int)$row['id']]);
          }

          if ($matched) {
            // Rehash if password hash cost needs upgrade
            if (password_needs_rehash($row['pwd'], PASSWORD_DEFAULT)) {
              $newHash = password_hash($pwd, PASSWORD_DEFAULT);
              $updateSql = $role === 'admin'
                ? 'UPDATE admin SET Password = ?, password_changed_at = NOW() WHERE id = ?'
                : 'UPDATE costumer SET pwd = ? WHERE id = ?';
              db_exec($link, $updateSql, 'si', [$newHash, (int)$row['id']]);
            }

            throttle_clear($link, $pairKey);
            throttle_clear($link, $acctKey);

            // Two-Factor Authentication check (Issue 2)
            if ($role === 'admin' && !empty($row['totp_enabled'])) {
              $_SESSION['mfa_admin_id'] = (int)$row['id'];
              $_SESSION['mfa_started'] = time();
              header('Location: ' . BASE_URL . '/admin/mfa.php');
              exit();
            }

            $login_ok = login_user($role, $row);
            if (!$login_ok) {
              try {
                audit($link, 'LOGIN_FAILED', 'admin', (int)$row['id'], null, ['email' => $email, 'reason' => 'invalid_role']);
              } catch (Throwable $e) {}
              $msg = 'Account configuration error. Please contact a super administrator.';
              $msg_type = 'danger';
              $open_modal = 'admin';
            } else {
              if ($role === 'admin') {
                db_exec($link, 'UPDATE `admin` SET last_login_at = NOW() WHERE id = ?', 'i', [(int)$row['id']]);
                try {
                  audit($link, 'LOGIN', 'admin', (int)$row['id'], null, ['email' => $email, 'status' => 'success']);
                } catch (Throwable $e) {}
              }

              // Phase 1.1: Redirect to validated next return path if present
              $next = safe_next_url($_POST['next'] ?? $_GET['next'] ?? null);
              $dest = $next !== null ? $next : (BASE_URL . '/' . $role . '/index.php');
              header('Location: ' . $dest);
              exit();
            }
          }
        }
      }

      if (!$matched && empty($msg)) {
        // Record failed login attempt
        throttle_hit($link, $pairKey);
        throttle_hit($link, $acctKey);
        throttle_hit($link, $ipKey);

        if ($role === 'admin') {
          try {
            audit($link, 'LOGIN_FAILED', 'admin', null, null, ['email' => $email, 'reason' => 'invalid_credentials']);
          } catch (Throwable $e) {}
        }

        $msg = 'Invalid email or password combination.';
        $msg_type = 'danger';
        $open_modal = ($role === 'admin') ? 'admin' : 'user';
      }
    }
  }
}

// Registration handler (H-06, Phase 1.4, P9)
$old_register = [
  'fname' => '',
  'lname' => '',
  'user_email' => '',
  'user_no' => '',
  'address' => '',
];

if (isset($_POST['userbtn'])) {
  csrf_verify();
  $raw_fname = trim((string)($_POST['fname'] ?? ''));
  $raw_lname = trim((string)($_POST['lname'] ?? ''));
  $raw_name = trim($raw_fname . ' ' . $raw_lname);
  $email = strtolower(trim((string)($_POST['user_email'] ?? '')));
  $pwd = (string)($_POST['user_pwd'] ?? '');
  $raw_phone = (string)($_POST['user_no'] ?? '');
  $raw_addr = (string)($_POST['address'] ?? '');
  $preserved_email = $email;

  $old_register = [
    'fname' => $raw_fname,
    'lname' => $raw_lname,
    'user_email' => $email,
    'user_no' => $raw_phone,
    'address' => $raw_addr,
  ];

  $person_val = validate_person_fields($raw_name, $raw_phone, $raw_addr);
  $errors = $person_val['errors'];

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'Enter a valid email address.';
  }
  if (strlen($pwd) < 8 || !preg_match('/[A-Za-z]/', $pwd) || !preg_match('/\d/', $pwd)) {
    $errors[] = 'Password must be at least 8 characters and include both letters and numbers.';
  }
  if (!$errors && db_one($link, 'SELECT id FROM costumer WHERE email = ?', 's', [$email])) {
    $errors[] = 'That email address is already registered.';
  }

  if ($errors) {
    $msg = implode(' ', $errors);
    $msg_type = 'warning';
    $open_modal = 'register';
  } else {
    db_exec($link,
      'INSERT INTO costumer (name, email, pwd, phone, address) VALUES (?,?,?,?,?)',
      'sssss', [$person_val['name'], $email, password_hash($pwd, PASSWORD_DEFAULT), $person_val['phone'], $person_val['address']]);
    
    $new_uid = (int)mysqli_insert_id($link);

    // Phase 4.2: Automatically sign user in upon registration
    $_SESSION['uid'] = $new_uid;
    $_SESSION['name'] = $person_val['name'];
    $_SESSION['email'] = $email;
    $_SESSION['phone'] = $person_val['phone'];
    $_SESSION['role'] = 'user';
    $_SESSION['last_seen'] = time();
    session_regenerate_id(true);

    // Send welcome confirmation email
    $welcome_sub = 'Welcome to Bus Reservation!';
    $welcome_body = '<div style="font-family:sans-serif;max-width:600px;margin:auto;padding:20px;border:1px solid #e2e8f0;border-radius:8px;">'
        . '<h2 style="color:#2563eb;margin-top:0;">Welcome, ' . htmlspecialchars($person_val['name']) . '!</h2>'
        . '<p>Thank you for creating your account with the Bus Reservation System. You are now logged in and ready to book bus tickets.</p>'
        . '</div>';
    send_app_mail($email, $welcome_sub, $welcome_body);

    $safe_next = safe_next_url($_POST['next'] ?? $_GET['next'] ?? null);
    $target = $safe_next ?: (BASE_URL . '/user/index.php');
    flash_set('success', 'Account created successfully! Welcome, ' . $person_val['name'] . '.');
    header('Location: ' . $target);
    exit;
  }
}

// Contact form handler (C-02, H-07, P8)
$contact_old = [
  'name' => $_SESSION['name'] ?? '',
  'email' => $_SESSION['email'] ?? '',
  'subject' => '',
  'query' => ''
];
$contact_alert = null;
$contact_alert_type = 'info';

if (isset($_POST['subbtn'])) {
  csrf_verify();

  // Honeypot spam check
  if (!empty($_POST['website'])) {
    header('Location: ' . BASE_URL . '/homepage.php#contact');
    exit;
  }

  $n = trim((string)($_POST['name'] ?? ''));
  $em = trim((string)($_POST['email'] ?? ''));
  $s = trim((string)($_POST['subject'] ?? ''));
  $q = trim((string)($_POST['query'] ?? ''));

  $contact_old = ['name' => $n, 'email' => $em, 'subject' => $s, 'query' => $q];
  $ip = client_ip();
  $contactKey = 'contact:ip:' . $ip;

  if (throttle_blocked($link, $contactKey, 5, 3600)) {
    $contact_alert = 'Too many inquiries sent recently from your IP. Please try again later.';
    $contact_alert_type = 'danger';
  } elseif ($n === '' || !filter_var($em, FILTER_VALIDATE_EMAIL) || $q === '') {
    $contact_alert = 'Please provide your full name, a valid email address, and inquiry details.';
    $contact_alert_type = 'warning';
  } else {
    $n = mb_substr($n, 0, 100);
    $em = mb_substr($em, 0, 100);
    $s = mb_substr($s, 0, 200);
    $q = mb_substr($q, 0, 3000);

    db_exec($link,
      'INSERT INTO query (user_name, user_email, user_subject, user_qry) VALUES (?,?,?,?)',
      'ssss', [$n, $em, $s, $q]);
    throttle_hit($link, $contactKey);

    flash_set('success', 'Thank you! Your inquiry has been submitted successfully. Our support team will reply by email.');
    header('Location: ' . BASE_URL . '/homepage.php#contact');
    exit;
  }
}
?>
<?php require_once __DIR__ . '/includes/layout/header-public.php'; ?>

<main id="main" tabindex="-1">
  <?php if (!empty($msg) && empty($open_modal)): ?>
    <div class="container pt-4">
      <div class="alert alert-<?= e($msg_type) ?> alert-dismissible fade show text-center" role="alert">
        <?= e($msg) ?>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
    </div>
  <?php endif; ?>

  <!-- 1. Split Hero Section (B1, B2, A2) -->
  <section id="home" class="hero-split-section" aria-labelledby="heroHeading">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-lg-6 hero-animate-in">
          <div class="hero-pill">
            <span>🛡️</span>
            <span>DIRECT INTERCITY BOOKINGS</span>
          </div>
          <h1 id="heroHeading" class="hero-heading">
            Simple, Reliable Bus Ticket Booking
          </h1>
          <p class="hero-subheading">
            Reserve your seat on scheduled routes with transparent pricing, instant PNR issuance, and live seat maps.
          </p>
          <div class="hero-actions">
            <a href="#search" class="btn btn-primary btn-lg shadow-sm">
              Search &amp; Book Tickets &rarr;
            </a>
            <a href="#pnr" class="btn btn-outline-secondary btn-lg">
              Check PNR Status &darr;
            </a>
          </div>
        </div>
        <div class="col-lg-6 hero-animate-in">
          <div class="hero-image-wrapper">
            <img src="<?= BASE_URL ?>/assets/images/hero-800.webp"
                 srcset="<?= BASE_URL ?>/assets/images/hero-480.webp 480w, <?= BASE_URL ?>/assets/images/hero-800.webp 800w, <?= BASE_URL ?>/assets/images/hero-1200.webp 1200w"
                 sizes="(max-width: 991px) 100vw, 50vw"
                 alt="Modern Bus Travel Illustration" 
                 width="1200" 
                 height="540"
                 fetchpriority="high">
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- 2. Central Search & Booking Card (A1) -->
  <section id="search" class="home-section bg-light" aria-labelledby="searchHeading">
    <div class="container">
      <div class="booking-search-card">
        <div class="card-title-bar d-flex flex-column flex-sm-row justify-content-between align-items-sm-center">
          <div>
            <h2 id="searchHeading" class="h4 font-weight-bold text-dark mb-1">Plan Your Journey</h2>
            <p class="text-muted small mb-0">
              Select departure, destination, and travel date to find scheduled coaches.
              <span class="d-block d-md-inline text-muted mt-1 mt-md-0 font-italic">Need more seats? Make a separate booking for each passenger.</span>
            </p>
          </div>
          <div class="mt-2 mt-sm-0">
            <span class="badge badge-primary px-3 py-2 font-weight-bold">
              1 Seat per Booking
            </span>
          </div>
        </div>

        <?php if (empty($route_pairs)): ?>
          <div class="alert alert-info mb-0 text-center py-3">
            <strong>No scheduled routes are available at this time.</strong>
            <p class="mb-0 small text-muted">Our scheduling team is updating timetables. Please check back shortly or contact customer support.</p>
          </div>
        <?php elseif ($user_role === 'user'): ?>
          <!-- Logged-in passenger directly posts to user/index.php -->
          <form action="<?= BASE_URL ?>/user/index.php" method="post">
            <?= csrf_field() ?>
            <div class="booking-form-grid">
              <div class="form-group mb-0">
                <label for="home_from" class="booking-field-label">
                  <span>📍</span> Departure City
                </label>
                <select name="from" id="home_from" class="form-control" required>
                  <option value="">Select Origin City</option>
                  <?php foreach ($from_cities as $c): ?>
                    <option value="<?= e($c) ?>"><?= e($c) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group mb-0">
                <div class="d-flex justify-content-between align-items-center">
                  <label for="home_to" class="booking-field-label mb-0">
                    <span>🏁</span> Destination City
                  </label>
                  <button type="button" class="btn btn-link btn-sm p-0 text-primary text-decoration-none" id="home_swap_btn" title="Swap Departure and Destination" style="font-size: 0.8rem; line-height: 1;">⇄ Swap</button>
                </div>
                <select name="to" id="home_to" class="form-control mt-1" required>
                  <option value="">Select Destination City</option>
                  <?php foreach ($to_cities as $c): ?>
                    <option value="<?= e($c) ?>"><?= e($c) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group mb-0">
                <label for="home_date" class="booking-field-label">
                  <span>📅</span> Travel Date
                </label>
                <input type="date" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+90 days')) ?>" name="date" id="home_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
              </div>

              <div class="form-group mb-0">
                <button type="submit" name="subbtn" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm" style="min-height: 44px;">
                  Find Available Buses
                </button>
              </div>
            </div>
          </form>
        <?php else: ?>
          <!-- Guest search form: saves to sessionStorage and opens sign-in modal -->
          <form id="guestSearchForm" onsubmit="handleGuestSearch(event)">
            <div class="booking-form-grid">
              <div class="form-group mb-0">
                <label for="guest_from" class="booking-field-label">
                  <span>📍</span> Departure City
                </label>
                <select id="guest_from" class="form-control" required>
                  <option value="">Select Origin City</option>
                  <?php foreach ($from_cities as $c): ?>
                    <option value="<?= e($c) ?>"><?= e($c) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group mb-0">
                <div class="d-flex justify-content-between align-items-center">
                  <label for="guest_to" class="booking-field-label mb-0">
                    <span>🏁</span> Destination City
                  </label>
                  <button type="button" class="btn btn-link btn-sm p-0 text-primary text-decoration-none" id="guest_swap_btn" title="Swap Departure and Destination" style="font-size: 0.8rem; line-height: 1;">⇄ Swap</button>
                </div>
                <select id="guest_to" class="form-control mt-1" required>
                  <option value="">Select Destination City</option>
                  <?php foreach ($to_cities as $c): ?>
                    <option value="<?= e($c) ?>"><?= e($c) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group mb-0">
                <label for="guest_date" class="booking-field-label">
                  <span>📅</span> Travel Date
                </label>
                <input type="date" min="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d', strtotime('+90 days')) ?>" id="guest_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
              </div>

              <div class="form-group mb-0">
                <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold shadow-sm" style="min-height: 44px;">
                  Find Available Buses
                </button>
              </div>
            </div>
          </form>
          <script>
            function handleGuestSearch(e) {
              e.preventDefault();
              var fromEl = document.getElementById('guest_from');
              var toEl = document.getElementById('guest_to');
              var dateEl = document.getElementById('guest_date');
              var fromVal = fromEl ? fromEl.value : '';
              var toVal = toEl ? toEl.value : '';
              var dateVal = dateEl ? dateEl.value : '';

              if (!fromVal || !toVal || !dateVal) {
                return;
              }

              var q = new URLSearchParams({
                from: fromVal,
                to: toVal,
                date: dateVal
              });
              var nextUrl = '<?= BASE_URL ?>/user/index.php?' + q.toString();

              document.querySelectorAll('#userlogin input[name="next"]').forEach(function(input) {
                input.value = nextUrl;
              });

              var noticeBox = document.getElementById('guestSearchNotice');
              var noticeText = document.getElementById('guestSearchNoticeText');
              if (noticeBox && noticeText) {
                noticeText.textContent = 'Sign in or register to view available buses from ' + fromVal + ' to ' + toVal + ' on ' + dateVal + '.';
                noticeBox.classList.remove('d-none');
              }

              if (window.jQuery) {
                $('#userlogin').modal('show');
              }
            }
          </script>
        <?php endif; ?>

        <script>
          window.ROUTES = <?= json_encode($route_pairs, JSON_HEX_TAG) ?>;
          function prefillSearch(fromCity, toCity) {
            var isUser = <?= json_encode($user_role === 'user') ?>;
            var fromId = isUser ? 'home_from' : 'guest_from';
            var toId = isUser ? 'home_to' : 'guest_to';
            var fromEl = document.getElementById(fromId);
            var toEl = document.getElementById(toId);
            if (fromEl) {
              fromEl.value = fromCity;
              fromEl.dispatchEvent(new Event('change'));
            }
            if (toEl) {
              setTimeout(function() {
                toEl.value = toCity;
              }, 60);
            }
          }
          (function() {
            function setupRouteSelectors(fromId, toId, swapBtnId) {
              var fromSelect = document.getElementById(fromId);
              var toSelect = document.getElementById(toId);
              var swapBtn = document.getElementById(swapBtnId);
              if (!fromSelect || !toSelect) return;

              function filterDests() {
                var fromVal = fromSelect.value;
                var curTo = toSelect.value;
                var allowed = [];
                if (window.ROUTES && Array.isArray(window.ROUTES)) {
                  for (var i = 0; i < window.ROUTES.length; i++) {
                    if (window.ROUTES[i].city1 === fromVal) {
                      allowed.push(window.ROUTES[i].city2);
                    }
                  }
                }

                for (var j = 1; j < toSelect.options.length; j++) {
                  var opt = toSelect.options[j];
                  if (!fromVal) {
                    opt.disabled = false;
                  } else {
                    opt.disabled = (opt.value === fromVal || allowed.indexOf(opt.value) === -1);
                  }
                }

                if (fromVal && curTo && (curTo === fromVal || allowed.indexOf(curTo) === -1)) {
                  toSelect.value = '';
                }
              }

              fromSelect.addEventListener('change', filterDests);
              if (fromSelect.value) {
                filterDests();
              }

              if (swapBtn) {
                swapBtn.addEventListener('click', function(e) {
                  e.preventDefault();
                  var origFrom = fromSelect.value;
                  var origTo = toSelect.value;
                  if (!origFrom && !origTo) return;

                  if (origFrom && origTo) {
                    var hasReverse = window.ROUTES && window.ROUTES.some(function(r) {
                      return r.city1 === origTo && r.city2 === origFrom;
                    });
                    if (!hasReverse) {
                      alert('No direct return route exists from ' + origTo + ' to ' + origFrom + '.');
                      return;
                    }
                  }

                  fromSelect.value = origTo;
                  filterDests();
                  toSelect.value = origFrom;
                });
              }
            }

            document.addEventListener('DOMContentLoaded', function() {
              setupRouteSelectors('home_from', 'home_to', 'home_swap_btn');
              setupRouteSelectors('guest_from', 'guest_to', 'guest_swap_btn');

              // Initialize client local date and max limit (P11)
              try {
                var d = new Date();
                d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
                var today = d.toISOString().slice(0, 10);
                var dMax = new Date();
                dMax.setDate(dMax.getDate() + 90);
                dMax.setMinutes(dMax.getMinutes() - dMax.getTimezoneOffset());
                var maxDay = dMax.toISOString().slice(0, 10);

                ['home_date', 'guest_date'].forEach(function(id) {
                  var el = document.getElementById(id);
                  if (!el) return;
                  el.min = today;
                  el.max = maxDay;
                  if (!el.value || el.value < today) {
                    el.value = today;
                  }
                });
              } catch (e) {}
            });
          })();
        </script>
      </div>
    </div>
  </section>

  <!-- Popular Routes Showcase & Trust Signals (P13) -->
  <?php if (!empty($popular_routes)): ?>
  <section id="routes" class="home-section" aria-labelledby="routesHeading">
    <div class="container">
      <div class="text-center mb-5">
        <span class="badge badge-primary p-2 px-3 mb-2 font-weight-bold">FARES &amp; TIMETABLES</span>
        <h2 id="routesHeading" class="font-weight-bold">Popular Intercity Routes</h2>
        <p class="text-muted mx-auto" style="max-width: 600px;">
          Explore scheduled coach journeys with transparent, upfront pricing. Click any route to plan your trip.
        </p>
      </div>

      <div class="row">
        <?php foreach ($popular_routes as $pr): ?>
          <div class="col-md-6 col-lg-4 mb-4">
            <div class="card h-100 border-0 shadow-sm popular-route-card" style="border-radius: 12px; transition: transform 0.2s, box-shadow 0.2s;">
              <div class="card-body p-4 d-flex flex-column justify-content-between">
                <div>
                  <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="badge badge-light border text-muted px-2 py-1 small">
                      <span aria-hidden="true">🚌</span> <?= e((string)($pr['bus_count'] ?? 1)) ?> Daily Departure<?= ((int)($pr['bus_count'] ?? 1) > 1) ? 's' : '' ?>
                    </span>
                    <span class="text-success font-weight-bold">
                      from <?= CURRENCY ?><?= e(number_format((float)$pr['min_price'], 2)) ?>
                    </span>
                  </div>
                  <h5 class="card-title font-weight-bold text-dark mb-1">
                    <?= e($pr['city1']) ?> <span class="text-primary mx-1">&rarr;</span> <?= e($pr['city2']) ?>
                  </h5>
                  <p class="card-text text-muted small">Daily scheduled coaches with reserved seating.</p>
                </div>
                <div class="mt-3 pt-3 border-top">
                  <a href="#search" class="btn btn-outline-primary btn-sm btn-block font-weight-bold" onclick="prefillSearch('<?= e($pr['city1']) ?>', '<?= e($pr['city2']) ?>');">
                    Book This Route &rarr;
                  </a>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Trust signals / Facts row -->
      <div class="card border-0 shadow-sm mt-4 bg-light" style="border-radius: 12px;">
        <div class="card-body p-4">
          <div class="row text-center">
            <div class="col-6 col-md-3 mb-3 mb-md-0">
              <div class="h3 font-weight-bold text-primary mb-1"><?= $total_routes_count ?>+</div>
              <div class="text-muted small">Active Schedules</div>
            </div>
            <div class="col-6 col-md-3 mb-3 mb-md-0">
              <div class="h3 font-weight-bold text-primary mb-1">10 Min</div>
              <div class="text-muted small">Guaranteed Seat Hold</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="h3 font-weight-bold text-primary mb-1">Instant</div>
              <div class="text-muted small">PNR &amp; Boarding Pass</div>
            </div>
            <div class="col-6 col-md-3">
              <div class="h3 font-weight-bold text-primary mb-1">24/7</div>
              <div class="text-muted small">Online Inquiry Support</div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- 3. PNR Lookup Section (D1-D8) -->
  <section id="pnr" class="home-section" aria-labelledby="pnrHeading">
    <div class="container">
      <div class="text-center mb-5">
        <span class="badge badge-info p-2 px-3 mb-2 font-weight-bold">TICKET VERIFICATION</span>
        <h2 id="pnrHeading" class="font-weight-bold">Check Reservation Status</h2>
        <p class="text-muted mx-auto" style="max-width: 540px;">
          Enter your 10-character booking PNR and the last 4 digits of your contact phone number to view ticket details.
        </p>
      </div>

      <div class="pnr-search-box mx-auto" style="max-width: 700px;">
        <?php
        $searched_pnr = strtoupper(trim((string)($_POST['pnr'] ?? $_GET['pnr'] ?? '')));
        $searched_phone4 = preg_replace('/\D/', '', (string)($_POST['phone4'] ?? $_GET['phone4'] ?? ''));
        ?>
        <form method="post" action="<?= e(BASE_URL) ?>/homepage.php#pnr" id="pnrSearchForm" class="row">
          <?= csrf_field() ?>
          <div class="col-md-5 mb-3 mb-md-0">
            <label for="pnr_input" class="d-block font-weight-bold small text-muted">PNR Number</label>
            <input class="form-control" 
                   id="pnr_input"
                   name="pnr" 
                   maxlength="10" 
                   pattern="[A-Fa-f0-9]{10}"
                   autocomplete="off"
                   autocapitalize="characters"
                   placeholder="e.g. 9B3A57EF10" 
                   value="<?= e($searched_pnr) ?>" 
                   required 
                   style="text-transform: uppercase;">
          </div>
          <div class="col-md-4 mb-3 mb-md-0">
            <label for="phone4_input" class="d-block font-weight-bold small text-muted">Last 4 Digits of Phone</label>
            <input class="form-control" 
                   id="phone4_input"
                   name="phone4" 
                   maxlength="4" 
                   pattern="\d{4}" 
                   inputmode="numeric"
                   autocomplete="off"
                   placeholder="e.g. 5521" 
                   value="<?= e($searched_phone4) ?>" 
                   required>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary btn-block py-2" id="pnrSubmitBtn" type="submit" style="min-height: 40px;">
              Search Ticket
            </button>
          </div>
        </form>
        <script>
          (function() {
            var f = document.getElementById('pnrSearchForm');
            var btn = document.getElementById('pnrSubmitBtn');
            if (f && btn) {
              f.addEventListener('submit', function () {
                setTimeout(function () {
                  btn.disabled = true;
                  btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> Searching...';
                }, 0);
              });
              window.addEventListener('pageshow', function () {
                btn.disabled = false;
                btn.textContent = 'Search Ticket';
              });
            }
          })();
        </script>

        <?php
        $is_pnr_post = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pnr'], $_POST['phone4']));
        $is_pnr_get = (isset($_GET['pnr'], $_GET['phone4']));
        if ($is_pnr_post || $is_pnr_get) {
          if ($is_pnr_post) {
            csrf_verify();
          }
          // Send no-store & noindex headers for privacy on PNR lookup results (D3 / P4)
          if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate');
            header('X-Robots-Tag: noindex, nofollow');
          }

          $pnrInput = $searched_pnr;
          $phone4 = $searched_phone4;
          $b = null;
          $pnr_state = 'not_found';
          $ip = client_ip();
          $ipKey = 'pnr:ip:' . $ip;
          $targetKey = 'pnr:target:' . $pnrInput;

          // 1. Validate format
          if (!preg_match('/^[A-F0-9]{10}$/', $pnrInput) || strlen($phone4) !== 4) {
            $pnr_state = 'invalid';
          } elseif (throttle_blocked($link, $ipKey, 10, 600) || throttle_blocked($link, $targetKey, 5, 900)) {
            // 2. Throttled state (D1)
            $pnr_state = 'throttled';
          } else {
            // Count search attempt against rate limits
            throttle_hit($link, $ipKey);
            throttle_hit($link, $targetKey);

            release_expired_holds($link);
            $b = db_one($link,
              'SELECT pnr, bus, name, contact, city1, city2, `date`, `time`, seat, price, sno, status
               FROM booking
               WHERE pnr = ? AND RIGHT(contact, 4) = ?
               LIMIT 1',
              'ss', [$pnrInput, $phone4]);

            if ($b) {
              $pnr_state = 'found';
            } else {
              $pnr_state = 'not_found';
            }
          }

          if ($pnr_state === 'found' && $b): 
            // Format dates and times cleanly (D4)
            $rawDate = (string)($b['date'] ?? '');
            $rawTime = (string)($b['time'] ?? '');
            $formattedDate = $rawDate;
            $formattedTime = $rawTime;
            if ($dObj = DateTime::createFromFormat('Y-m-d', $rawDate)) {
              $formattedDate = $dObj->format('D, d M Y');
            }
            if ($tObj = DateTime::createFromFormat('H:i:s', $rawTime)) {
              $formattedTime = $tObj->format('h:i A');
            } elseif ($tObj = DateTime::createFromFormat('H:i', $rawTime)) {
              $formattedTime = $tObj->format('h:i A');
            }
            ?>
            <div class="ticket-receipt-card" role="status" tabindex="-1" id="pnrResultArea">
              <div class="ticket-header">
                <div>
                  <div class="pnr-label">Booking Reference (PNR)</div>
                  <div class="pnr-code"><?= e($b['pnr']) ?></div>
                </div>
                <?php
                $b_status = (string)($b['status'] ?? 'Confirmed');
                $status_class = 'badge-pnr-confirmed';
                if ($b_status === 'Pending') $status_class = 'badge-pnr-pending';
                elseif ($b_status === 'Cancelled' || $b_status === 'Expired') $status_class = 'badge-pnr-cancelled';
                ?>
                <span class="badge badge-pnr-status <?= $status_class ?>">
                  <?= e($b_status) ?>
                </span>
              </div>
              <div class="ticket-body">
                <div class="row">
                  <div class="col-sm-6 ticket-row-item">
                    <div class="item-label">Passenger Name</div>
                    <div class="item-value"><?= e($b['name']) ?></div>
                  </div>
                  <div class="col-sm-6 ticket-row-item">
                    <div class="item-label">Contact Phone</div>
                    <div class="item-value">***-***-<?= e(substr($b['contact'], -4)) ?></div>
                  </div>
                  <div class="col-sm-6 ticket-row-item">
                    <div class="item-label">Route Journey</div>
                    <div class="item-value"><?= e($b['city1']) ?> &rarr; <?= e($b['city2']) ?></div>
                  </div>
                  <div class="col-sm-6 ticket-row-item">
                    <div class="item-label">Departure Schedule</div>
                    <div class="item-value"><?= e($formattedDate) ?> at <?= e($formattedTime) ?></div>
                  </div>
                  <div class="col-sm-6 ticket-row-item">
                    <div class="item-label">Bus Vehicle</div>
                    <div class="item-value">Bus #<?= e($b['bus']) ?></div>
                  </div>
                  <div class="col-sm-6 ticket-row-item">
                    <div class="item-label">Seat Assigned</div>
                    <div class="item-value text-primary font-weight-bold">Seat #<?= e((string)$b['seat']) ?></div>
                  </div>
                  <div class="col-12 pt-3 border-top d-flex justify-content-between align-items-center">
                    <span class="text-muted font-weight-bold">Total Fare:</span>
                    <span class="font-weight-bold text-success h4 mb-0"><?= CURRENCY ?><?= e(number_format((float)$b['price'], 2)) ?></span>
                  </div>
                </div>
              </div>
            </div>
            <script>
              document.addEventListener('DOMContentLoaded', function() {
                var resArea = document.getElementById('pnrResultArea');
                if (resArea) resArea.focus();
              });
            </script>
          <?php elseif ($pnr_state === 'throttled'): ?>
            <div class="alert alert-danger mt-4 text-center mb-0" role="alert" tabindex="-1" id="pnrResultArea">
              <strong>Lookup limit reached:</strong> Too many verification attempts have been made. Please wait 15 minutes before checking this ticket again.
            </div>
            <script>
              document.addEventListener('DOMContentLoaded', function() {
                var resArea = document.getElementById('pnrResultArea');
                if (resArea) resArea.focus();
              });
            </script>
          <?php elseif ($pnr_state === 'invalid'): ?>
            <div class="alert alert-warning mt-4 text-center mb-0" role="alert" tabindex="-1" id="pnrResultArea">
              <strong>Invalid format:</strong> Please verify that your PNR is exactly 10 alphanumeric characters and phone digits are 4 numbers.
            </div>
            <script>
              document.addEventListener('DOMContentLoaded', function() {
                var resArea = document.getElementById('pnrResultArea');
                if (resArea) resArea.focus();
              });
            </script>
          <?php else: ?>
            <div class="alert alert-warning mt-4 text-center mb-0" role="alert" tabindex="-1" id="pnrResultArea">
              <strong>No matching reservation:</strong> No booking record was found matching that PNR token and phone number.
            </div>
            <script>
              document.addEventListener('DOMContentLoaded', function() {
                var resArea = document.getElementById('pnrResultArea');
                if (resArea) resArea.focus();
              });
            </script>
          <?php endif;
        }
        ?>
      </div>
    </div>
  </section>

  <!-- 4. Features & Facts Section (C1, B4, P14) -->
  <section id="about" class="home-section bg-light" aria-labelledby="aboutHeading">
    <div class="container text-center">
      <span class="badge badge-primary p-2 px-3 mb-2 font-weight-bold">WHY CHOOSE US</span>
      <h2 id="aboutHeading" class="font-weight-bold">Designed for Reliable Travel</h2>
      <p class="text-muted mx-auto" style="max-width: 680px;">
        Direct seat reservations, instant boarding passes, and customer-first support designed for peace of mind.
      </p>

      <div class="features-container text-left">
        <div class="feature-card">
          <div class="feature-svg-icon" aria-hidden="true">
            <!-- Shield SVG icon -->
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
          </div>
          <h3>Guaranteed Seat Selection</h3>
          <p>Your seat is held for 10 minutes while you pay. Real-time availability ensures you never get double-booked.</p>
        </div>

        <div class="feature-card">
          <div class="feature-svg-icon" aria-hidden="true">
            <!-- Ticket SVG icon -->
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line></svg>
          </div>
          <h3>Instant Ticket Access</h3>
          <p>Check your ticket any time with your PNR and phone digits. Download or print your boarding pass whenever you need.</p>
        </div>

        <div class="feature-card">
          <div class="feature-svg-icon" aria-hidden="true">
            <!-- Lock SVG icon -->
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
          </div>
          <h3>Secure &amp; Protected</h3>
          <p>Your details are stored securely. Encrypted account protection and privacy safeguards keep your bookings safe.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- 5. Contact Section (C3) -->
  <section id="contact" class="home-section" aria-labelledby="contactHeading">
    <div class="container">
      <div class="contact-form-card">
        <div class="text-center mb-4">
          <span class="badge badge-info p-2 px-3 mb-2 font-weight-bold">SUPPORT INQUIRIES</span>
          <h2 id="contactHeading" class="font-weight-bold">Send Us a Message</h2>
          <p class="text-muted">Have inquiries regarding routes, schedules, or ticketing? Send a message and our team will reply by email.</p>
        </div>

        <?php
        $flashes = flash_get();
        foreach ($flashes as $f): ?>
          <div class="alert alert-<?= e($f['type']) ?> alert-dismissible fade show mb-4" role="alert">
            <?= e($f['msg']) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endforeach; ?>

        <?php if (!empty($contact_alert)): ?>
          <div class="alert alert-<?= e($contact_alert_type) ?> alert-dismissible fade show mb-4" role="alert">
            <?= e($contact_alert) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>

        <form action="<?= e(BASE_URL) ?>/homepage.php#contact" method="post">
          <?= csrf_field() ?>
          <div style="display:none;" aria-hidden="true">
            <input type="text" name="website" tabindex="-1" autocomplete="off">
          </div>
          <div class="form-row">
            <div class="col-md-6 form-group">
              <label for="contact_name" class="font-weight-bold small text-muted">Your Full Name</label>
              <input type="text" id="contact_name" class="form-control" name="name" maxlength="100" value="<?= e($contact_old['name']) ?>" placeholder="John Doe" required />
            </div>
            <div class="col-md-6 form-group">
              <label for="contact_email" class="font-weight-bold small text-muted">Email Address</label>
              <input type="email" id="contact_email" class="form-control" name="email" maxlength="100" value="<?= e($contact_old['email']) ?>" placeholder="john@example.com" required />
            </div>
          </div>
          <div class="form-group">
            <label for="contact_subject" class="font-weight-bold small text-muted">Subject</label>
            <input type="text" id="contact_subject" class="form-control" name="subject" maxlength="200" value="<?= e($contact_old['subject']) ?>" placeholder="Inquiry regarding ticket #..." />
          </div>
          <div class="form-group">
            <label for="contact_query" class="font-weight-bold small text-muted">Message Content</label>
            <textarea id="contact_query" rows="4" class="form-control" name="query" maxlength="3000" placeholder="Type your inquiry here..." required><?= e($contact_old['query']) ?></textarea>
          </div>
          <button type="submit" name="subbtn" class="btn btn-primary btn-block btn-lg shadow-sm" style="min-height: 48px;">
            Send Inquiry
          </button>
        </form>
      </div>
    </div>
  </section>
</main>

<!-- User Login / Register Modal -->
<div class="modal fade" id="userlogin" tabindex="-1" role="dialog" aria-labelledby="userLoginModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header d-flex justify-content-between align-items-center">
        <h5 id="userLoginModalTitle" class="modal-title font-weight-bold">Passenger Access</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="p-3 bg-light border-bottom">
        <ul class="nav nav-tabs border-0" id="authTab" role="tablist">
          <li class="nav-item flex-fill text-center">
            <a class="nav-link font-weight-bold <?= ($open_modal === 'register') ? '' : 'active' ?>" id="login-tab" data-toggle="tab" href="#user-login-pane" role="tab" aria-controls="user-login-pane" aria-selected="<?= ($open_modal === 'register') ? 'false' : 'true' ?>">Sign In</a>
          </li>
          <li class="nav-item flex-fill text-center">
            <a class="nav-link font-weight-bold <?= ($open_modal === 'register') ? 'active' : '' ?>" id="register-tab" data-toggle="tab" href="#register-pane" role="tab" aria-controls="register-pane" aria-selected="<?= ($open_modal === 'register') ? 'true' : 'false' ?>">Register</a>
          </li>
        </ul>
      <div id="guestSearchNotice" class="alert alert-info alert-dismissible fade show m-3 mb-0 d-none" role="alert">
        <span class="mr-1">🚌</span> <span id="guestSearchNoticeText"></span>
        <button type="button" class="close" data-dismiss="alert" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="tab-content" id="authTabContent">
        <!-- Login Pane -->
        <div class="tab-pane fade <?= ($open_modal === 'register') ? '' : 'show active' ?> p-4" id="user-login-pane" role="tabpanel" aria-labelledby="login-tab">
          <?php if (($open_modal === 'user' || $open_modal === 'register_success') && !empty($msg)): ?>
            <div class="alert alert-<?= e($msg_type) ?> alert-dismissible fade show mb-3" role="alert">
              <?= e($msg) ?>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
          <?php endif; ?>
          <form action="<?= e(BASE_URL) ?>/homepage.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e(safe_next_url($_GET['next'] ?? $_POST['next'] ?? null) ?? '') ?>">
            <div class="form-group">
              <label for="user-email-input" class="font-weight-bold small text-muted">Email Address</label>
              <input type="email" id="user-email-input" name="email" class="form-control" value="<?= e($open_modal === 'user' ? $preserved_email : '') ?>" placeholder="passenger@example.com" autocomplete="username" required />
            </div>
            <div class="form-group mb-1">
              <label for="user-pwd-input" class="font-weight-bold small text-muted">Password</label>
              <div class="input-group">
                <input type="password" id="user-pwd-input" name="pwd" class="form-control" placeholder="••••••••" autocomplete="current-password" required />
                <div class="input-group-append">
                  <button class="btn btn-outline-secondary" type="button" id="user-login-pwd-toggle" aria-label="Show password" aria-pressed="false">
                    <span id="user-login-pwd-toggle-text">Show</span>
                  </button>
                </div>
              </div>
              <div id="user-login-caps-warning" class="text-warning small mt-1 d-none" role="status" aria-live="polite">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="mr-1" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                Caps Lock is ON
              </div>
            </div>
            <div class="text-right mb-3">
              <a href="<?= BASE_URL ?>/forgot-password.php" class="small text-muted">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold" name="user" style="min-height: 44px;">
              Sign In to Account
            </button>
          </form>
        </div>
        <!-- Register Pane -->
        <div class="tab-pane fade <?= ($open_modal === 'register') ? 'show active' : '' ?> p-4" id="register-pane" role="tabpanel" aria-labelledby="register-tab">
          <?php if ($open_modal === 'register' && !empty($msg)): ?>
            <div class="alert alert-<?= e($msg_type) ?> alert-dismissible fade show mb-3" role="alert">
              <?= e($msg) ?>
              <button type="button" class="close" data-dismiss="alert" aria-label="Close">
                <span aria-hidden="true">&times;</span>
              </button>
            </div>
          <?php endif; ?>
          <form action="<?= e(BASE_URL) ?>/homepage.php" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= e(safe_next_url($_GET['next'] ?? $_POST['next'] ?? null) ?? '') ?>">
            <div class="form-row">
              <div class="form-group col-6">
                <label for="fname" class="font-weight-bold small text-muted">First Name</label>
                <input type="text" class="form-control" name="fname" id="fname" value="<?= e($old_register['fname']) ?>" placeholder="First" required />
              </div>
              <div class="form-group col-6">
                <label for="lname" class="font-weight-bold small text-muted">Last Name</label>
                <input type="text" class="form-control" name="lname" id="lname" value="<?= e($old_register['lname']) ?>" placeholder="Last" required />
              </div>
            </div>
            <div class="form-group">
              <label for="user_email" class="font-weight-bold small text-muted">Email Address</label>
              <input type="email" class="form-control" name="user_email" id="user_email" value="<?= e($old_register['user_email'] ?: ($open_modal === 'register' ? $preserved_email : '')) ?>" placeholder="email@example.com" autocomplete="email" required />
            </div>
            <div class="form-group">
              <label for="user_pwd" class="font-weight-bold small text-muted">Password</label>
              <div class="input-group">
                <input type="password" class="form-control" name="user_pwd" id="user_pwd" placeholder="••••••••" minlength="8" autocomplete="new-password" required />
                <div class="input-group-append">
                  <button class="btn btn-outline-secondary" type="button" id="user-reg-pwd-toggle" aria-label="Show password" aria-pressed="false">
                    <span id="user-reg-pwd-toggle-text">Show</span>
                  </button>
                </div>
              </div>
              <div id="user-reg-caps-warning" class="text-warning small mt-1 d-none" role="status" aria-live="polite">
                <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="mr-1" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                Caps Lock is ON
              </div>
              <small class="form-text text-muted">Must be 8+ characters with at least 1 letter and 1 number.</small>
            </div>
            <div class="form-group">
              <label for="user_no" class="font-weight-bold small text-muted">Phone Number</label>
              <input type="tel" class="form-control" name="user_no" id="user_no" inputmode="tel" pattern="[0-9]{10,15}" value="<?= e($old_register['user_no']) ?>" placeholder="10-15 digits" required />
            </div>
            <div class="form-group">
              <label for="address" class="font-weight-bold small text-muted">Address <span class="text-muted font-weight-normal">(optional)</span></label>
              <textarea name="address" id="address" rows="2" class="form-control" placeholder="Your street address (optional)"><?= e($old_register['address']) ?></textarea>
            </div>
            <button type="submit" class="btn btn-success btn-block py-2 font-weight-bold" name="userbtn" style="min-height: 44px;">
              Create New Account
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Admin Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" role="dialog" aria-labelledby="adminLoginModalTitle" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header admin-auth-header">
        <div class="d-flex align-items-center">
          <img src="<?= BASE_URL ?>/assets/images/bus.svg" alt="Bus Service Logo" width="28" height="28" class="mr-2">
          <h5 id="adminLoginModalTitle" class="admin-modal-title">Administration Portal</h5>
        </div>
        <div class="d-flex align-items-center">
          <span class="admin-badge mr-2">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
            Restricted access
          </span>
          <button type="button" class="close text-white p-0 m-0" data-dismiss="modal" aria-label="Close" style="opacity: 0.85;">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
      </div>
      <div class="modal-body p-4">
        <p class="admin-auth-subtitle mb-3">Authorized administrative personnel sign-in.</p>
        <?php if ($open_modal === 'admin' && !empty($msg)): ?>
          <div class="alert alert-<?= e($msg_type) ?> alert-dismissible fade show mb-3" role="alert">
            <?= e($msg) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>
        <form action="<?= e(BASE_URL) ?>/homepage.php" method="post">
          <?= csrf_field() ?>
          <div class="form-group">
            <label for="admin-email-input" class="admin-form-label">Admin Email</label>
            <input type="email" id="admin-email-input" name="email" class="form-control" value="<?= e($open_modal === 'admin' ? $preserved_email : '') ?>" placeholder="admin@domain.com" autocomplete="username" required />
          </div>
          <div class="form-group">
            <label for="admin-pwd-input" class="admin-form-label">Password</label>
            <div class="input-group">
              <input type="password" id="admin-pwd-input" name="pwd" class="form-control" placeholder="••••••••" autocomplete="current-password" required />
              <div class="input-group-append">
                <button class="btn btn-outline-secondary" type="button" id="admin-pwd-toggle" aria-label="Show password" aria-pressed="false">
                  <span id="admin-pwd-toggle-text">Show</span>
                </button>
              </div>
            </div>
            <div id="admin-caps-warning" class="text-warning small mt-1 d-none" role="status" aria-live="polite">
              <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" class="mr-1" aria-hidden="true"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
              Caps Lock is ON
            </div>
          </div>
          <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold" name="admin" style="min-height: 44px;">
            Admin Sign In
          </button>
          <div class="text-center mt-3 pt-2 border-top">
            <small class="text-muted">Need a password reset? Contact a super administrator.</small>
          </div>
        </form>
      </div>
    </div>
  </div>
</div>

<!-- Modal Context Re-open Script (E1, E5) -->
<?php if (!empty($open_modal)): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
  var modalTarget = <?= json_encode($open_modal) ?>;
  if (modalTarget === 'user' || modalTarget === 'register' || modalTarget === 'register_success') {
    $('#userlogin').modal('show');
  } else if (modalTarget === 'admin') {
    $('#loginModal').modal('show');
  }
});
</script>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
  // Auto-focus first input field when modals are opened (E5)
  if (window.jQuery) {
    $('#userlogin').on('shown.bs.modal', function () {
      var activePane = document.querySelector('#authTabContent .tab-pane.active');
      if (activePane) {
        var firstInput = activePane.querySelector('input:not([type=hidden])');
        if (firstInput) firstInput.focus();
      }
    });
    $('#loginModal').on('shown.bs.modal', function () {
      var emailField = document.getElementById('admin-email-input');
      if (emailField) emailField.focus();
    });
  }

  // Password Visibility Toggle & Caps Lock Detection (Issues 5 & 6, P9)
  (function() {
    function setupPasswordEnhancements(pwdInputId, pwdToggleId, pwdToggleTextId, capsWarningId, formEl, modalId) {
      var pwdInput = document.getElementById(pwdInputId);
      var pwdToggle = document.getElementById(pwdToggleId);
      var pwdToggleText = document.getElementById(pwdToggleTextId);
      var capsWarning = document.getElementById(capsWarningId);

      function resetPasswordVisibility() {
        if (pwdInput && pwdInput.type !== 'password') {
          pwdInput.type = 'password';
          if (pwdToggle) {
            pwdToggle.setAttribute('aria-label', 'Show password');
            pwdToggle.setAttribute('aria-pressed', 'false');
          }
          if (pwdToggleText) {
            pwdToggleText.textContent = 'Show';
          }
        }
      }

      if (pwdToggle && pwdInput) {
        pwdToggle.addEventListener('click', function() {
          var isPassword = pwdInput.type === 'password';
          pwdInput.type = isPassword ? 'text' : 'password';
          pwdToggle.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
          pwdToggle.setAttribute('aria-pressed', isPassword ? 'true' : 'false');
          if (pwdToggleText) {
            pwdToggleText.textContent = isPassword ? 'Hide' : 'Show';
          }
          pwdInput.focus();
        });
      }

      if (pwdInput && capsWarning) {
        function checkCapsLock(e) {
          if (e.getModifierState && e.getModifierState('CapsLock')) {
            capsWarning.classList.remove('d-none');
          } else {
            capsWarning.classList.add('d-none');
          }
        }
        pwdInput.addEventListener('keydown', checkCapsLock);
        pwdInput.addEventListener('keyup', checkCapsLock);
        pwdInput.addEventListener('blur', function() {
          capsWarning.classList.add('d-none');
        });
      }

      // Reset visibility when form submits or modal closes
      if (formEl) {
        formEl.addEventListener('submit', resetPasswordVisibility);
      }
      if (modalId && window.jQuery) {
        $(modalId).on('hidden.bs.modal', function() {
          resetPasswordVisibility();
          if (capsWarning) {
            capsWarning.classList.add('d-none');
          }
        });
      }
    }

    var adminModal = document.getElementById('loginModal');
    var adminForm = adminModal ? adminModal.querySelector('form') : null;
    setupPasswordEnhancements('admin-pwd-input', 'admin-pwd-toggle', 'admin-pwd-toggle-text', 'admin-caps-warning', adminForm, '#loginModal');

    var userLoginForm = document.querySelector('#user-login-pane form');
    setupPasswordEnhancements('user-pwd-input', 'user-login-pwd-toggle', 'user-login-pwd-toggle-text', 'user-login-caps-warning', userLoginForm, '#userlogin');

    var userRegForm = document.querySelector('#register-pane form');
    setupPasswordEnhancements('user_pwd', 'user-reg-pwd-toggle', 'user-reg-pwd-toggle-text', 'user-reg-caps-warning', userRegForm, '#userlogin');
  })();
});
</script>

<?php require_once __DIR__ . '/includes/layout/footer-public.php'; ?>