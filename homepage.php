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

  if ($email === '' || $pwd === '') {
    $msg = '<p class="alert alert-warning text-center">Please fill all the fields</p>';
  } else {
    $sql = $role === 'admin'
      ? 'SELECT id, name, phone, Password AS pwd FROM admin WHERE Email_id = ? LIMIT 1'
      : 'SELECT id, name, phone, pwd FROM costumer WHERE email = ? LIMIT 1';
    $row = db_one($link, $sql, 's', [$email]);

    if ($row) {
      $matched = password_verify($pwd, $row['pwd']);
      if (!$matched && $pwd === $row['pwd']) {
        // Seamless migration: rehash on first login if still plain text
        $matched = true;
        $newHash = password_hash($pwd, PASSWORD_DEFAULT);
        $updateSql = $role === 'admin'
          ? 'UPDATE admin SET Password = ? WHERE id = ?'
          : 'UPDATE costumer SET pwd = ? WHERE id = ?';
        db_exec($link, $updateSql, 'si', [$newHash, (int)$row['id']]);
      }

      if ($matched) {
        login_user($role, $row);
        header('Location: ' . BASE_URL . '/' . $role . '/index.php?d=2');
        exit();
      }
    }
    $msg = '<p class="alert alert-warning text-center">Invalid Email or Password</p>';
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

  if ($name === '') { $errors[] = 'Name is required.'; }
  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $errors[] = 'Enter a valid email.'; }
  if (strlen($pwd) < 8 || !preg_match('/[A-Za-z]/', $pwd) || !preg_match('/\d/', $pwd)) {
    $errors[] = 'Password needs 8+ characters with a letter and a number.';
  }
  if (strlen($phone) < 10 || strlen($phone) > 15) { $errors[] = 'Enter a valid phone number.'; }
  if (!$errors && db_one($link, 'SELECT id FROM costumer WHERE email = ?', 's', [$email])) {
    $errors[] = 'That email is already registered.';
  }

  if ($errors) {
    $msg = '<p class="alert alert-warning text-center">' . e(implode(' ', $errors)) . '</p>';
  } else {
    db_exec($link,
      'INSERT INTO costumer (name, email, pwd, phone, address) VALUES (?,?,?,?,?)',
      'sssss', [$name, $email, password_hash($pwd, PASSWORD_DEFAULT), $phone, $addr]);
    $msg = '<p class="alert alert-success text-center">Account created successfully. You can log in now.</p>';
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
    $msg = '<p class="alert alert-success text-center">Thanks, we received your message.</p>';
  } else {
    $msg = '<p class="alert alert-warning text-center">Please fill name, a valid email and message.</p>';
  }
}
?>
<?php require_once __DIR__ . '/includes/layout/header-public.php'; ?>
<?php if (!empty($msg)) { echo $msg; } ?>
<section id="image">
  <div class="overlay">
    <div class="description">
      <h1 class="text-center">Welcome to Simple Bus Ticket Booking System</h1>

      <p class=" text-center ">Welcome to Simple Bus Ticket Booking System. Login now to manage bus tickets and
        much more. OR, simply scroll down to check the Ticket status using Passenger Name Record (PNR
        number)</p>
    </div>
    <center class="mt-3">
      <button class="btn btn-danger " data-toggle="modal" data-target="#loginModal">Administrator Login</button>
    </center>
    <div class="modal fade" id="userlogin">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <button type="button" class="close text-right" data-dismiss="modal">
            <span>&times;</span>
          </button>
          <ul class="nav nav-tabs">
            <li class="btn nav-item col-6 active">
              <a href="#login" aria-controls="login" class="btn btn-info btn-block" data-toggle="tab">Account
                Login</a>
            </li>
            <li class="btn nav-item col-6">
              <a href="#Register" aria-controls="Register" class="btn btn-info btn-block" data-toggle="tab">Create
                Account</a>
            </li>
          </ul>
          <div class="tab-content">
            <div role="tabpanel" class="tab-pane active" id="login">
              <div class="modal-header">
                <h5>
                  If you already have an online account, please enter your email
                  address and password below
                </h5>
              </div>
              <div class="modal-body">
                <form action="" method="post">
                  <?= csrf_field() ?>
                  <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="Email" />
                  </div>
                  <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="pwd" class="form-control" placeholder="password" />
                  </div>
                  <div class="form-group">
                    <input type="submit" class="btn btn-success btn-block" name="user" value="User Login" />
                  </div>

                </form>
              </div>
              <div class="modal-footer">
                <a href class="btn-link">forget password?</a>
              </div>
            </div>
            <div role="tabpanel" class="tab-pane" id="Register">
              <div class="modal-header">
                <h5>New Registeration</h5>
              </div>
              <div class="modal-body">
                <form action="" method="post">
                  <?= csrf_field() ?>
                  <div class="row">
                    <div class="form-group col-6">
                      <label for="fname">First Name:</label>
                      <input type="text" class="form-control" name="fname" id="fname" placeholder="First Name" required />
                    </div>
                    <div class="form-group col-6">
                      <label for="lname">Last Name:</label>
                      <input type="text" class="form-control" name="lname" id="lname" placeholder="Last Name" required />
                    </div>
                  </div>
                  <div class="form-group col--lg-12">
                    <label for="user_email">Email:</label>
                    <input type="email" class="form-control" placeholder="Email" name="user_email" id="user_email" required />
                  </div>
                  <div class="form-group col--lg-12">
                    <label for="user_pwd">Password:</label>
                    <input type="password" class="form-control" name="user_pwd" id="user_pwd" placeholder="Password" minlength="8" autocomplete="new-password" required />
                  </div>
                  <h6>
                    Password must be a minimum of 8 characters and contain at
                    least 1 number and 1 letter
                  </h6>
                  <div class="form-group col--lg-12">
                    <label for="user_no">Phone:</label>
                    <input type="tel" class="form-control" name="user_no" id="user_no" placeholder="Mobile Number" required />
                  </div>
                  <div class="form-group col--lg-12">
                    <label for="address">Address:</label>
                    <textarea name="address" id="address" rows="2" placeholder="Your Address"
                      style=" width: 100% ; "></textarea>
                  </div>
                  <div class="form-group col--lg-12 ">
                    <input type="submit" class="btn btn-success btn-block" name="userbtn" value="Register Account" />
                  </div>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="modal fade" id="loginModal">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <button type="button" class="close text-right" data-dismiss="modal">
            <span>&times;</span>
          </button>
          <div class="tab-content">
            <div role="tabpanel" class="tab-pane active" id="login">
              <div class="modal-header">
                <h5>
                  Only Registered Admin Can Login
                </h5>
              </div>
              <div class="modal-body">
                <form action="" method="post">
                  <?= csrf_field() ?>
                  <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="Email" />
                  </div>
                  <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" name="pwd" class="form-control" placeholder="password" />
                  </div>
                  <div class="form-group">
                    <input type="submit" class="btn btn-success btn-block" name="admin" value="Admin Login" />
                  </div>
                </form>
              </div>
              <div class="modal-footer">
                <a href class="btn-link">forget password?</a>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
    <center class=" mt-3 ">
      <a href="#pnr" data-value="pnr"><button class="btn btn-primary">Scroll Down <i class="fa fa-arrow-down"></i></button></a>
    </center>
  </div>
</section>
<section id="pnr">
  <div class="pb-5 text-center">
    <h1 class="mt-5 mb-4">Check Your Booking Details</h1>
    <form method="get" action="<?= e(BASE_URL) ?>/homepage.php#pnr" class="form-inline justify-content-center">
      <input class="form-control m-1" name="pnr" maxlength="10" placeholder="PNR (10 characters or ID)" value="<?= e($_GET['pnr'] ?? '') ?>" required>
      <input class="form-control m-1" name="phone4" maxlength="4" pattern="\d{4}" placeholder="Last 4 digits of phone" value="<?= e($_GET['phone4'] ?? '') ?>" required>
      <button class="btn btn-info m-1" type="submit">Search Booking</button>
    </form>
    <?php
    if (isset($_GET['pnr'], $_GET['phone4'])) {
      $pnrInput = strtoupper(trim((string)$_GET['pnr']));
      $phone4 = preg_replace('/\D/', '', (string)$_GET['phone4']);
      $b = null;
      $key = 'pnr:' . client_ip();

      if (!throttle_blocked($link, $key, 10, 600)) {
        throttle_hit($link, $key);
        if ((preg_match('/^[A-F0-9]{10}$/', $pnrInput) || ctype_digit($pnrInput)) && strlen($phone4) === 4) {
          $b = db_one($link,
            'SELECT pnr, bus, name, contact, city1, city2, `date`, `time`, seat, price, sno
             FROM booking
             WHERE (pnr = ? OR sno = ?) AND RIGHT(contact, 4) = ?
             LIMIT 1',
            'sss', [$pnrInput, $pnrInput, $phone4]);
        }
      }

      if ($b): ?>
        <div class="card mx-auto mt-4 text-left shadow-sm" style="max-width:540px;">
          <div class="card-body">
            <h4 class="card-title text-success">PNR: <?= e($b['pnr'] ?: $b['sno']) ?></h4>
            <hr>
            <p class="mb-1"><strong>Passenger:</strong> <?= e($b['name']) ?> (Phone: ***-***-<?= e(substr($b['contact'], -4)) ?>)</p>
            <p class="mb-1"><strong>Bus Number:</strong> <?= e($b['bus']) ?></p>
            <p class="mb-1"><strong>Route:</strong> <?= e($b['city1']) ?> &rarr; <?= e($b['city2']) ?></p>
            <p class="mb-1"><strong>Departure:</strong> <?= e($b['date']) ?> at <?= e($b['time']) ?></p>
            <p class="mb-1"><strong>Seat Allocated:</strong> <span class="badge badge-info">Seat <?= e((string)$b['seat']) ?></span></p>
            <p class="mb-0"><strong>Total Amount:</strong> $<?= e((string)$b['price']) ?></p>
          </div>
        </div>
      <?php else: ?>
        <p class="alert alert-warning mt-4 mx-auto" style="max-width:540px;">
          No booking found matching those details, or too many lookup attempts. Please verify your PNR and last 4 digits of your phone.
        </p>
      <?php endif;
    }
    ?>
  </div>
</section>
<section id="about">
  <div>
    <h1 class=" text-center mt-5 ">About Us</h1>
    <h4 class=" text-center mb-3 ">Wanna know were it all started?</h4>
    <p>
      Lorem ipsum dolor sit amet consecteturadipisicing elit. Perferendis soluta voluptas eaque, numquam veritatis
      aperiam expedita deleniti, nesciunt cum alias velit. Cupiditate commodi
      Lorem ipsum dolor, sit amet consectetur adipisicing elit. Accusamus cum nisi ea optio unde aliquam quia
      reprehenderit atque eum tenetur!
      Lorem ipsum dolor sit amet consectetur adipisicing elit. Sed placeat debitis corporis voluptates modi quibusdam
      quidem voluptatibus illum, maiores sequi.
    </p>
  </div>
</section>
<section id="contact">
  <div class="contact p-5" id="contact">
    <div class="container btn page">
      <div class="row"></div>
      <h1 class="text-center col-lg-12 col-md-12 col-sm-12">Contact Us</h1>
      <p>
        Pityful a rethoric question ran over her cheek,then she comtinued her
        way,On her way she met a copy.
      </p>
      <form action="" method="post">
        <?= csrf_field() ?>
        <div class="input-group mt-4 mb-4">
          <div class="input-group-append">
            <span class="input-group-text" id="basic-addon1">Name</span>
          </div>
          <input type="text" class="form-control" placeholder="Username" name="name" aria-label="Username"
            aria-describedby="basic-addon1" required />
          <div class="input-group-append">
            <span class="input-group-text" id="basic-addon2">Email</span>
          </div>
          <input type="email" class="form-control" name="email" placeholder="Email Address" aria-label="Email Address"
            aria-describedby="basic-addon2" required />
        </div>
        <div class="input-group mt-4 mb-4">
          <div class="input-group-append">
            <span class="input-group-text" id="basic-addon3">Subject</span>
          </div>
          <input type="text" class="form-control" name="subject" placeholder="Subject" aria-label="Username"
            aria-describedby="basic-addon3" />
        </div>
        <div class="input-group mt-4 mb-4">
          <textarea rows="4" class="form-control" placeholder="How We Can Help You" name="query"
            aria-label="Username" aria-describedby="basic-addon3" required></textarea>
        </div>
        <div class="input-group-append">
          <input type="submit" name="subbtn" class="btn btn-primary btn-lg btn-block" value="Send Message">
        </div>
      </form>
    </div>
  </div>
</section>
<?php require_once __DIR__ . '/includes/layout/footer.php'; ?>