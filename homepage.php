<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth/session-bootstrap.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/db_con.php';
ob_start();
$msg = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['user']) || isset($_POST['admin']))) {
  csrf_verify();
  $role = isset($_POST['admin']) ? 'admin' : 'user';
  $email = strtolower(trim((string)($_POST['email'] ?? '')));
  $pwd = (string)($_POST['pwd'] ?? '');
  $ip = client_ip();

  if ($email === '' || $pwd === '') {
    $msg = '<div class="alert alert-warning text-center mx-auto my-3" style="max-width: 600px;">Please fill in all the required fields.</div>';
  } else {
    $acctKey = 'login:acct:' . $email;
    $ipKey   = 'login:ip:' . $ip;

    // Rate limiting (O3: 5 failures per 15 min per account, 20 per 15 min per IP)
    if (throttle_blocked($link, $acctKey, 5, 900) || throttle_blocked($link, $ipKey, 20, 900)) {
      $msg = '<div class="alert alert-danger text-center mx-auto my-3" style="max-width: 600px;">Too many failed attempts. Please try again later.</div>';
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
          header('Location: ' . BASE_URL . '/' . $role . '/index.php?d=2');
          exit();
        }
      }

      // Record failed login attempt
      throttle_hit($link, $acctKey);
      throttle_hit($link, $ipKey);
      $msg = '<div class="alert alert-danger text-center mx-auto my-3" style="max-width: 600px;">Invalid email or password combination.</div>';
    }
  }
}

// Registration handler (H-06)
if (isset($_POST['userbtn'])) {
  csrf_verify();
  $name = trim(trim((string)($_POST['fname'] ?? '')) . ' ' . trim((string)($_POST['lname'] ?? '')));
  $email = strtolower(trim((string)($_POST['user_email'] ?? '')));
  $pwd = (string)($_POST['user_pwd'] ?? '');
  $phone = preg_replace('/\D/', '', (string)($_POST['user_no'] ?? ''));
  $addr = trim((string)($_POST['address'] ?? ''));
  $errors = [];

  if ($name === '') { $errors[] = 'Full name is required.'; }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Enter a valid email address.'; }
  if (strlen($pwd) < 8 || !preg_match('/[A-Za-z]/', $pwd) || !preg_match('/\d/', $pwd)) {
    $errors[] = 'Password must be at least 8 characters and include both letters and numbers.';
  }
  if (strlen($phone) < 10 || strlen($phone) > 15) { $errors[] = 'Enter a valid phone number.'; }
  if (!$errors && db_one($link, 'SELECT id FROM costumer WHERE email = ?', 's', [$email])) {
    $errors[] = 'That email address is already registered.';
  }

  if ($errors) {
    $msg = '<div class="alert alert-warning text-center mx-auto my-3" style="max-width: 600px;">' . e(implode(' ', $errors)) . '</div>';
  } else {
    db_exec($link,
      'INSERT INTO costumer (name, email, pwd, phone, address) VALUES (?,?,?,?,?)',
      'sssss', [$name, $email, password_hash($pwd, PASSWORD_DEFAULT), $phone, $addr]);
    $msg = '<div class="alert alert-success text-center mx-auto my-3" style="max-width: 600px;">Account created successfully! You can now log in.</div>';
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
    $msg = '<div class="alert alert-success text-center mx-auto my-3" style="max-width: 600px;">Thank you! Your inquiry has been submitted successfully.</div>';
  } else {
    $msg = '<div class="alert alert-warning text-center mx-auto my-3" style="max-width: 600px;">Please provide your name, a valid email, and your query details.</div>';
  }
}
?>
<?php require_once __DIR__ . '/includes/layout/header-public.php'; ?>

<?php if (!empty($msg)): ?>
  <div class="container" style="margin-top: 5rem;">
    <?= $msg ?>
  </div>
<?php endif; ?>

<!-- Hero Section -->
<section id="image">
  <div class="hero-overlay"></div>
  <div class="hero-content" data-aos="fade-up" data-aos-duration="800">
    <div class="badge badge-primary px-3 py-2 mb-3 font-weight-bold" style="letter-spacing: 0.05em; font-size: 0.8rem;">
      FAST • SECURE • RELIABLE TRANSIT
    </div>
    <h1 class="hero-title">Effortless Bus Ticket Reservations</h1>
    <p class="hero-subtitle">
      Plan journeys across top routes with instant seat allocation, live availability tracking, and transparent ticketing in just seconds.
    </p>
    <div class="hero-actions">
      <a href="#pnr" class="btn btn-primary btn-lg shadow">
        Check Ticket by PNR &darr;
      </a>
      <button class="btn btn-outline-light btn-lg" data-toggle="modal" data-target="#userlogin">
        Book Bus Tickets &rarr;
      </button>
    </div>
  </div>
</section>

<!-- PNR Lookup Section -->
<section id="pnr">
  <div class="container">
    <div class="text-center mb-5" data-aos="fade-up">
      <span class="badge badge-info p-2 px-3 mb-2 font-weight-bold">INSTANT VERIFICATION</span>
      <h2 class="font-weight-bold">Check Reservation Status</h2>
      <p class="text-muted mx-auto" style="max-width: 540px;">
        Enter your 10-character booking PNR and the last 4 digits of your contact phone number to view ticket details.
      </p>
    </div>

    <div class="pnr-card-search" data-aos="fade-up" data-aos-delay="100">
      <form method="get" action="<?= e(BASE_URL) ?>/homepage.php#pnr" class="row">
        <div class="col-md-5 mb-3 mb-md-0">
          <label class="form-label font-weight-bold small text-muted">PNR Number</label>
          <input class="form-control" name="pnr" maxlength="10" placeholder="e.g. 9B3A57EF10" value="<?= e($_GET['pnr'] ?? '') ?>" required style="text-transform: uppercase;">
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
          <label class="form-label font-weight-bold small text-muted">Last 4 Digits of Phone</label>
          <input class="form-control" name="phone4" maxlength="4" pattern="\d{4}" placeholder="e.g. 5521" value="<?= e($_GET['phone4'] ?? '') ?>" required>
        </div>
        <div class="col-md-3 d-flex align-items-end">
          <button class="btn btn-primary btn-block py-2" type="submit">
            Search Ticket
          </button>
        </div>
      </form>

      <?php
      if (isset($_GET['pnr'], $_GET['phone4'])) {
        $pnrInput = strtoupper(trim((string)$_GET['pnr']));
        $phone4 = preg_replace('/\D/', '', (string)$_GET['phone4']);
        $b = null;
        $ip = client_ip();
        $ipKey = 'pnr:ip:' . $ip;
        $targetKey = 'pnr:target:' . $pnrInput;

        if (!throttle_blocked($link, $ipKey, 10, 600) && !throttle_blocked($link, $targetKey, 5, 900)) {
          throttle_hit($link, $ipKey);
          throttle_hit($link, $targetKey);

          if (preg_match('/^[A-F0-9]{10}$/', $pnrInput) && strlen($phone4) === 4) {
            release_expired_holds($link);
            $b = db_one($link,
              'SELECT pnr, bus, name, contact, city1, city2, `date`, `time`, seat, price, sno, status
               FROM booking
               WHERE pnr = ? AND RIGHT(contact, 4) = ?
               LIMIT 1',
              'ss', [$pnrInput, $phone4]);
          }
        }

        if ($b): ?>
          <div class="card mt-4 border-0 shadow-sm overflow-hidden">
            <div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
              <div>
                <span class="text-muted small text-uppercase">PNR CODE</span>
                <h5 class="mb-0 text-white font-weight-bold"><?= e($b['pnr']) ?></h5>
              </div>
              <?php
              $b_status = (string)($b['status'] ?? 'Confirmed');
              $badge_color = 'success';
              if ($b_status === 'Pending') $badge_color = 'warning text-dark';
              elseif ($b_status === 'Cancelled' || $b_status === 'Expired') $badge_color = 'danger';
              ?>
              <span class="badge badge-<?= $badge_color ?> p-2 px-3 font-weight-bold"><?= e($b_status) ?></span>
            </div>
            <div class="card-body">
              <div class="row">
                <div class="col-sm-6 mb-3">
                  <div class="text-muted small">PASSENGER</div>
                  <div class="font-weight-bold text-dark"><?= e($b['name']) ?></div>
                </div>
                <div class="col-sm-6 mb-3">
                  <div class="text-muted small">CONTACT</div>
                  <div class="font-weight-bold text-dark">***-***-<?= e(substr($b['contact'], -4)) ?></div>
                </div>
                <div class="col-sm-6 mb-3">
                  <div class="text-muted small">BUS DETAILS</div>
                  <div class="font-weight-bold text-dark">Bus #<?= e($b['bus']) ?></div>
                </div>
                <div class="col-sm-6 mb-3">
                  <div class="text-muted small">SEAT ASSIGNED</div>
                  <div><span class="badge badge-primary p-2">Seat #<?= e((string)$b['seat']) ?></span></div>
                </div>
                <div class="col-sm-6 mb-3">
                  <div class="text-muted small">TRIP ROUTE</div>
                  <div class="font-weight-bold text-dark"><?= e($b['city1']) ?> &rarr; <?= e($b['city2']) ?></div>
                </div>
                <div class="col-sm-6 mb-3">
                  <div class="text-muted small">SCHEDULE</div>
                  <div class="font-weight-bold text-dark"><?= e($b['date']) ?> at <?= e($b['time']) ?></div>
                </div>
                <div class="col-12 pt-2 border-top d-flex justify-content-between align-items-center">
                  <span class="font-weight-bold text-muted">Total Fare:</span>
                  <span class="font-weight-bold text-success h4 mb-0"><?= CURRENCY ?><?= e(number_format((float)$b['price'], 2)) ?></span>
                </div>
              </div>
            </div>
          </div>
        <?php else: ?>
          <div class="alert alert-warning mt-4 text-center mb-0">
            No booking record was found matching that PNR and phone combination, or search limit reached.
          </div>
        <?php endif;
      }
      ?>
    </div>
  </div>
</section>

<!-- About Section -->
<section id="about">
  <div class="container text-center" data-aos="fade-up">
    <span class="badge badge-primary p-2 px-3 mb-2 font-weight-bold">WHY CHOOSE US</span>
    <h2 class="font-weight-bold">Modern & Secure Travel Experience</h2>
    <p class="text-muted mx-auto" style="max-width: 680px;">
      Our platform powers automated ticket issuing, dynamic seating distribution, and unified customer reservations with real-time auditability.
    </p>

    <div class="features-grid">
      <div class="feature-box">
        <div class="feature-icon">🛡️</div>
        <h5 class="font-weight-bold">Guaranteed Seats</h5>
        <p class="text-muted small mb-0">Live seat allocation prevents double-bookings with transactional database consistency.</p>
      </div>
      <div class="feature-box">
        <div class="feature-icon">⚡</div>
        <h5 class="font-weight-bold">Instant PNR</h5>
        <p class="text-muted small mb-0">Receive a cryptographically distinct 10-character token immediately for ticket tracking.</p>
      </div>
      <div class="feature-box">
        <div class="feature-icon">🚌</div>
        <h5 class="font-weight-bold">Curated Fleet</h5>
        <p class="text-muted small mb-0">Regularly serviced buses operating on well-timed, strictly scheduled intercity routes.</p>
      </div>
    </div>
  </div>
</section>

<!-- Contact Section -->
<section id="contact">
  <div class="container" data-aos="fade-up">
    <div class="contact-card">
      <div class="text-center mb-4">
        <span class="badge badge-info p-2 px-3 mb-2 font-weight-bold">GET IN TOUCH</span>
        <h2 class="font-weight-bold">Contact Support</h2>
        <p class="text-muted">Have inquiries regarding routes, fleet, or your booking? Let our team know.</p>
      </div>
      <form action="" method="post">
        <?= csrf_field() ?>
        <div class="form-row">
          <div class="col-md-6 form-group">
            <label class="form-label font-weight-bold small text-muted">Your Full Name</label>
            <input type="text" class="form-control" name="name" placeholder="John Doe" required />
          </div>
          <div class="col-md-6 form-group">
            <label class="form-label font-weight-bold small text-muted">Email Address</label>
            <input type="email" class="form-control" name="email" placeholder="john@example.com" required />
          </div>
        </div>
        <div class="form-group">
          <label class="form-label font-weight-bold small text-muted">Subject</label>
          <input type="text" class="form-control" name="subject" placeholder="Question regarding ticket #..." />
        </div>
        <div class="form-group">
          <label class="form-label font-weight-bold small text-muted">Message / Inquiries</label>
          <textarea rows="4" class="form-control" name="query" placeholder="Type your message here..." required></textarea>
        </div>
        <button type="submit" name="subbtn" class="btn btn-primary btn-block btn-lg shadow-sm">
          Send Message
        </button>
      </form>
    </div>
  </div>
</section>

<!-- User Login / Register Modal -->
<div class="modal fade" id="userlogin" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header d-flex justify-content-between align-items-center">
        <h5 class="modal-title font-weight-bold">Passenger Access</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="p-3 bg-light border-bottom">
        <ul class="nav nav-tabs border-0" id="authTab" role="tablist">
          <li class="nav-item flex-fill text-center">
            <a class="nav-link active font-weight-bold" id="login-tab" data-toggle="tab" href="#user-login-pane" role="tab">Sign In</a>
          </li>
          <li class="nav-item flex-fill text-center">
            <a class="nav-link font-weight-bold" id="register-tab" data-toggle="tab" href="#register-pane" role="tab">Register</a>
          </li>
        </ul>
      </div>
      <div class="tab-content" id="authTabContent">
        <!-- Login Pane -->
        <div class="tab-pane fade show active p-4" id="user-login-pane" role="tabpanel">
          <form action="" method="post">
            <?= csrf_field() ?>
            <div class="form-group">
              <label for="user-email-input" class="font-weight-bold small text-muted">Email Address</label>
              <input type="email" id="user-email-input" name="email" class="form-control" placeholder="passenger@example.com" required />
            </div>
            <div class="form-group">
              <label for="user-pwd-input" class="font-weight-bold small text-muted">Password</label>
              <input type="password" id="user-pwd-input" name="pwd" class="form-control" placeholder="••••••••" required />
            </div>
            <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold" name="user">
              Sign In to Account
            </button>
          </form>
        </div>
        <!-- Register Pane -->
        <div class="tab-pane fade p-4" id="register-pane" role="tabpanel">
          <form action="" method="post">
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
              <input type="email" class="form-control" name="user_email" id="user_email" placeholder="email@example.com" required />
            </div>
            <div class="form-group">
              <label for="user_pwd" class="font-weight-bold small text-muted">Password (8+ chars, letter & number)</label>
              <input type="password" class="form-control" name="user_pwd" id="user_pwd" placeholder="••••••••" minlength="8" autocomplete="new-password" required />
            </div>
            <div class="form-group">
              <label for="user_no" class="font-weight-bold small text-muted">Phone Number</label>
              <input type="tel" class="form-control" name="user_no" id="user_no" placeholder="10-15 digits" required />
            </div>
            <div class="form-group">
              <label for="address" class="font-weight-bold small text-muted">Address</label>
              <textarea name="address" id="address" rows="2" class="form-control" placeholder="Your street address"></textarea>
            </div>
            <button type="submit" class="btn btn-success btn-block py-2 font-weight-bold" name="userbtn">
              Create New Account
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Admin Login Modal -->
<div class="modal fade" id="loginModal" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header bg-dark text-white">
        <h5 class="modal-title font-weight-bold">System Administration</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body p-4">
        <p class="text-muted small mb-4">Authorized personnel only. Sessions are monitored and rate-limited.</p>
        <form action="" method="post">
          <?= csrf_field() ?>
          <div class="form-group">
            <label for="admin-email-input" class="font-weight-bold small text-muted">Admin Email</label>
            <input type="email" id="admin-email-input" name="email" class="form-control" placeholder="admin@domain.com" required />
          </div>
          <div class="form-group">
            <label for="admin-pwd-input" class="font-weight-bold small text-muted">Master Password</label>
            <input type="password" id="admin-pwd-input" name="pwd" class="form-control" placeholder="••••••••" required />
          </div>
          <button type="submit" class="btn btn-primary btn-block py-2 font-weight-bold" name="admin">
            Enter Admin Portal
          </button>
        </form>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/layout/footer.php'; ?>