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

// Read-only queries for the central search & booking card (A1)
$from_cities = db_all($link, 'SELECT DISTINCT city1 FROM route ORDER BY city1 ASC');
$to_cities = db_all($link, 'SELECT DISTINCT city2 FROM route ORDER BY city2 ASC');
$user_role = $_SESSION['role'] ?? null;

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
    $acctKey = 'login:acct:' . $email;
    $ipKey   = 'login:ip:' . $ip;

    // Rate limiting (O3: 5 failures per 15 min per account, 20 per 15 min per IP)
    if (throttle_blocked($link, $acctKey, 5, 900) || throttle_blocked($link, $ipKey, 20, 900)) {
      $msg = 'Too many failed attempts. Please try again later.';
      $msg_type = 'danger';
      $open_modal = ($role === 'admin') ? 'admin' : 'user';
    } else {
      $sql = $role === 'admin'
        ? 'SELECT id, name, phone, Password AS pwd FROM admin WHERE Email_id = ? LIMIT 1'
        : 'SELECT id, name, phone, pwd FROM costumer WHERE email = ? LIMIT 1';
      $row = db_one($link, $sql, 's', [$email]);

      if ($row) {
        $matched = password_verify($pwd, $row['pwd']);

        // O2: Only allow legacy migration if stored value is NOT a password hash
        if (!$matched && password_get_info($row['pwd'])['algo'] === null && hash_equals($row['pwd'], $pwd)) {
          $matched = true;
          $newHash = password_hash($pwd, PASSWORD_DEFAULT);
          $updateSql = $role === 'admin'
            ? 'UPDATE admin SET Password = ? WHERE id = ?'
            : 'UPDATE costumer SET pwd = ? WHERE id = ?';
          db_exec($link, $updateSql, 'si', [$newHash, (int)$row['id']]);
        }

        if ($matched) {
          // Rehash if password hash cost needs upgrade
          if (password_needs_rehash($row['pwd'], PASSWORD_DEFAULT)) {
            $newHash = password_hash($pwd, PASSWORD_DEFAULT);
            $updateSql = $role === 'admin'
              ? 'UPDATE admin SET Password = ? WHERE id = ?'
              : 'UPDATE costumer SET pwd = ? WHERE id = ?';
            db_exec($link, $updateSql, 'si', [$newHash, (int)$row['id']]);
          }

          throttle_clear($link, $acctKey);
          login_user($role, $row);

          // Phase 1.1: Redirect to validated next return path if present
          $next = safe_next_url($_POST['next'] ?? $_GET['next'] ?? null);
          $dest = $next !== null ? $next : (BASE_URL . '/' . $role . '/index.php');
          header('Location: ' . $dest);
          exit();
        }
      }

      // Record failed login attempt
      throttle_hit($link, $acctKey);
      throttle_hit($link, $ipKey);
      $msg = 'Invalid email or password combination.';
      $msg_type = 'danger';
      $open_modal = ($role === 'admin') ? 'admin' : 'user';
    }
  }
}

// Registration handler (H-06, Phase 1.4)
if (isset($_POST['userbtn'])) {
  csrf_verify();
  $raw_name = trim(trim((string)($_POST['fname'] ?? '')) . ' ' . trim((string)($_POST['lname'] ?? '')));
  $email = strtolower(trim((string)($_POST['user_email'] ?? '')));
  $pwd = (string)($_POST['user_pwd'] ?? '');
  $raw_phone = (string)($_POST['user_no'] ?? '');
  $raw_addr = (string)($_POST['address'] ?? '');
  $preserved_email = $email;

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
    $msg = 'Account created successfully! You can now sign in.';
    $msg_type = 'success';
    $open_modal = 'register_success'; // Switches to sign in tab inside modal
  }
}

// Contact form handler (C-02, H-07)
if (isset($_POST['subbtn'])) {
  csrf_verify();
  $n = trim((string)($_POST['name'] ?? ''));
  $em = trim((string)($_POST['email'] ?? ''));
  $s = trim((string)($_POST['subject'] ?? ''));
  $q = trim((string)($_POST['query'] ?? ''));

  if ($n !== '' && filter_var($em, FILTER_VALIDATE_EMAIL) && $q !== '') {
    db_exec($link,
      'INSERT INTO query (user_name, user_email, user_subject, user_qry) VALUES (?,?,?,?)',
      'ssss', [$n, $em, $s, $q]);
    $msg = 'Thank you! Your inquiry has been submitted successfully.';
    $msg_type = 'success';
  } else {
    $msg = 'Please provide your name, a valid email, and your query details.';
    $msg_type = 'warning';
  }
}
?>
<?php require_once __DIR__ . '/includes/layout/header-public.php'; ?>

<main id="main">
  <?php if (!empty($msg)): ?>
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
            <img src="<?= BASE_URL ?>/assets/images/sbtbsimg.jpg" 
                 alt="Modern Bus Travel Illustration" 
                 width="1920" 
                 height="864"
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
        <div class="card-title-bar">
          <div>
            <h2 id="searchHeading" class="h4 font-weight-bold text-dark mb-1">Plan Your Journey</h2>
            <p class="text-muted small mb-0">Select departure, destination, and travel date to find scheduled coaches.</p>
          </div>
          <span class="badge badge-primary px-3 py-2 font-weight-bold d-none d-md-inline-block">
            1 Seat per Booking
          </span>
        </div>

        <?php if ($user_role === 'user'): ?>
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
                    <option value="<?= e($c['city1']) ?>"><?= e($c['city1']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group mb-0">
                <label for="home_to" class="booking-field-label">
                  <span>🏁</span> Destination City
                </label>
                <select name="to" id="home_to" class="form-control" required>
                  <option value="">Select Destination City</option>
                  <?php foreach ($to_cities as $c): ?>
                    <option value="<?= e($c['city2']) ?>"><?= e($c['city2']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group mb-0">
                <label for="home_date" class="booking-field-label">
                  <span>📅</span> Travel Date
                </label>
                <input type="date" min="<?= date('Y-m-d') ?>" name="date" id="home_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
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
                    <option value="<?= e($c['city1']) ?>"><?= e($c['city1']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group mb-0">
                <label for="guest_to" class="booking-field-label">
                  <span>🏁</span> Destination City
                </label>
                <select id="guest_to" class="form-control" required>
                  <option value="">Select Destination City</option>
                  <?php foreach ($to_cities as $c): ?>
                    <option value="<?= e($c['city2']) ?>"><?= e($c['city2']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="form-group mb-0">
                <label for="guest_date" class="booking-field-label">
                  <span>📅</span> Travel Date
                </label>
                <input type="date" min="<?= date('Y-m-d') ?>" id="guest_date" value="<?= date('Y-m-d') ?>" class="form-control" required>
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
              var fromVal = document.getElementById('guest_from').value;
              var toVal = document.getElementById('guest_to').value;
              var dateVal = document.getElementById('guest_date').value;
              sessionStorage.setItem('guest_search_from', fromVal);
              sessionStorage.setItem('guest_search_to', toVal);
              sessionStorage.setItem('guest_search_date', dateVal);
              $('#userlogin').modal('show');
            }
          </script>
        <?php endif; ?>
      </div>
    </div>
  </section>

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
        <form method="get" action="<?= e(BASE_URL) ?>/homepage.php#pnr" id="pnrSearchForm" onsubmit="handlePnrSubmit()" class="row">
          <div class="col-md-5 mb-3 mb-md-0">
            <label for="pnr_input" class="form-label font-weight-bold small text-muted">PNR Number</label>
            <input class="form-control" 
                   id="pnr_input"
                   name="pnr" 
                   maxlength="10" 
                   pattern="[A-Fa-f0-9]{10}"
                   autocomplete="off"
                   autocapitalize="characters"
                   placeholder="e.g. 9B3A57EF10" 
                   value="<?= e($_GET['pnr'] ?? '') ?>" 
                   required 
                   style="text-transform: uppercase;">
          </div>
          <div class="col-md-4 mb-3 mb-md-0">
            <label for="phone4_input" class="form-label font-weight-bold small text-muted">Last 4 Digits of Phone</label>
            <input class="form-control" 
                   id="phone4_input"
                   name="phone4" 
                   maxlength="4" 
                   pattern="\d{4}" 
                   inputmode="numeric"
                   autocomplete="off"
                   placeholder="e.g. 5521" 
                   value="<?= e($_GET['phone4'] ?? '') ?>" 
                   required>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <button class="btn btn-primary btn-block py-2" id="pnrSubmitBtn" type="submit" style="min-height: 40px;">
              Search Ticket
            </button>
          </div>
        </form>
        <script>
          function handlePnrSubmit() {
            var btn = document.getElementById('pnrSubmitBtn');
            if (btn) {
              btn.disabled = true;
              btn.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span> Searching...';
            }
            document.getElementById('pnrSearchForm').submit();
          }
        </script>

        <?php
        if (isset($_GET['pnr'], $_GET['phone4'])) {
          // Send no-store & noindex headers for privacy on PNR lookup results (D3)
          if (!headers_sent()) {
            header('Cache-Control: no-store, no-cache, must-revalidate');
          }
          echo '<meta name="robots" content="noindex">';

          $pnrInput = strtoupper(trim((string)$_GET['pnr']));
          $phone4 = preg_replace('/\D/', '', (string)$_GET['phone4']);
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

  <!-- 4. Features & Facts Section (C1, B4) -->
  <section id="about" class="home-section bg-light" aria-labelledby="aboutHeading">
    <div class="container text-center">
      <span class="badge badge-primary p-2 px-3 mb-2 font-weight-bold">CORE CAPABILITIES</span>
      <h2 id="aboutHeading" class="font-weight-bold">Designed for Reliable Travel</h2>
      <p class="text-muted mx-auto" style="max-width: 680px;">
        Direct seat reservation backed by transactional constraints, automated ticket tokens, and clear route schedules.
      </p>

      <div class="features-container text-left">
        <div class="feature-card">
          <div class="feature-svg-icon" aria-hidden="true">
            <!-- Shield SVG icon -->
            <svg viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
          </div>
          <h3>Seat Management</h3>
          <p>Unique seat constraints prevent double-booking, with 10-minute holds released automatically if booking times expire.</p>
        </div>

        <div class="feature-card">
          <div class="feature-svg-icon" aria-hidden="true">
            <!-- Ticket SVG icon -->
            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="16" rx="2"></rect><line x1="3" y1="10" x2="21" y2="10"></line></svg>
          </div>
          <h3>Random 10-Char PNR</h3>
          <p>Every confirmed booking generates a unique 10-character token used for swift public lookup along with phone verification.</p>
        </div>

        <div class="feature-card">
          <div class="feature-svg-icon" aria-hidden="true">
            <!-- Lock SVG icon -->
            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
          </div>
          <h3>Account Security</h3>
          <p>Protected with CSRF tokens, securely hashed credentials, and brute-force rate limiting on authentication and PNR searches.</p>
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
        <form action="<?= e(BASE_URL) ?>/homepage.php" method="post">
          <?= csrf_field() ?>
          <div class="form-row">
            <div class="col-md-6 form-group">
              <label for="contact_name" class="font-weight-bold small text-muted">Your Full Name</label>
              <input type="text" id="contact_name" class="form-control" name="name" placeholder="John Doe" required />
            </div>
            <div class="col-md-6 form-group">
              <label for="contact_email" class="font-weight-bold small text-muted">Email Address</label>
              <input type="email" id="contact_email" class="form-control" name="email" placeholder="john@example.com" required />
            </div>
          </div>
          <div class="form-group">
            <label for="contact_subject" class="font-weight-bold small text-muted">Subject</label>
            <input type="text" id="contact_subject" class="form-control" name="subject" placeholder="Inquiry regarding ticket #..." />
          </div>
          <div class="form-group">
            <label for="contact_query" class="font-weight-bold small text-muted">Message Content</label>
            <textarea id="contact_query" rows="4" class="form-control" name="query" placeholder="Type your inquiry here..." required></textarea>
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
              <input type="password" id="user-pwd-input" name="pwd" class="form-control" placeholder="••••••••" autocomplete="current-password" required />
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
            <div class="form-row">
              <div class="form-group col-6">
                <label for="fname" class="font-weight-bold small text-muted">First Name</label>
                <input type="text" class="form-control" name="fname" id="fname" placeholder="First" required />
              </div>
              <div class="form-group col-6">
                <label for="lname" class="font-weight-bold small text-muted">Last Name</label>
                <input type="text" class="form-control" name="lname" id="lname" placeholder="Last" required />
              </div>
            </div>
            <div class="form-group">
              <label for="user_email" class="font-weight-bold small text-muted">Email Address</label>
              <input type="email" class="form-control" name="user_email" id="user_email" value="<?= e($open_modal === 'register' ? $preserved_email : '') ?>" placeholder="email@example.com" autocomplete="email" required />
            </div>
            <div class="form-group">
              <label for="user_pwd" class="font-weight-bold small text-muted">Password</label>
              <input type="password" class="form-control" name="user_pwd" id="user_pwd" placeholder="••••••••" minlength="8" autocomplete="new-password" required />
              <small class="form-text text-muted">Must be 8+ characters with at least 1 letter and 1 number.</small>
            </div>
            <div class="form-group">
              <label for="user_no" class="font-weight-bold small text-muted">Phone Number</label>
              <input type="tel" class="form-control" name="user_no" id="user_no" placeholder="10-15 digits" required />
            </div>
            <div class="form-group">
              <label for="address" class="font-weight-bold small text-muted">Address</label>
              <textarea name="address" id="address" rows="2" class="form-control" placeholder="Your street address"></textarea>
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
      <div class="modal-header bg-dark text-white">
        <h5 id="adminLoginModalTitle" class="modal-title font-weight-bold">System Administration</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-4">
        <p class="text-muted small mb-3">Authorized administrative personnel sign-in.</p>
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
            <label for="admin-email-input" class="font-weight-bold small text-muted">Admin Email</label>
            <input type="email" id="admin-email-input" name="email" class="form-control" value="<?= e($open_modal === 'admin' ? $preserved_email : '') ?>" placeholder="admin@domain.com" autocomplete="username" required />
          </div>
          <div class="form-group">
            <label for="admin-pwd-input" class="font-weight-bold small text-muted">Password</label>
            <input type="password" id="admin-pwd-input" name="pwd" class="form-control" placeholder="••••••••" autocomplete="current-password" required />
          </div>
          <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold" name="admin" style="min-height: 44px;">
            Admin Sign In
          </button>
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
// Auto-focus first input field when modals are opened (E5)
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
</script>

<?php require_once __DIR__ . '/includes/layout/footer-public.php'; ?>