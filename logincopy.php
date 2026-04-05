<div class="modal fade" id="userlogin">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <button type="button" class="close text-right" data-dismiss="modal">
        <span>&times;</span>
      </button>
      <ul class="nav nav-tabs">
        <li class="btn nav-item col-6 active">
          <a href="#login" aria-controls="login" class="btn btn-info btn-block" data-toggle="tab">Account Login</a>
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
                <input type="submit" class="btn btn-success btn-block" name="admin" value="Admin Login" />
              </div>
              <?php
              if (isset($_REQUEST["user"])) {
            if (!empty($_REQUEST['email']) and !empty($_REQUEST['pwd'])) {
                include_once("./db_con.php");
                $qry = "select name,pwd from costumer where email = '$_REQUEST[email]' and pwd = '$_REQUEST[pwd]'";
                $resultset = mysqli_query($link, $qry);
                echo mysqli_num_rows($resultset);
                $n = mysqli_num_rows($resultset);
                if ($n == 1) {
                    session_start();
                    $row = mysqli_fetch_assoc($resultset);
                    $_SESSION["name"] = $row["name"];
                    $_SESSION["pwd"] = $row["pwd"];
                    $name = $_SESSION["name"];
                    header("location:./userinterface/admin.php?d=0");
                } else {
                    echo "<p class='alert alert-warning'>Invalid Email or Password</p>";
                }


            } else {
                echo "<p class='alert alert-warning'>Please fill all the fields</p>";
            }
        }
        ?>
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
              <div class="row">
                <div class="form-group col-6">
                  <label for="First-name">First Name:</label>
                  <input type="text" class="form-control" name="fname" placeholder="First Name" />
                </div>
                <div class="form-group col-6">
                  <label for="Last-Name">Last Name:</label>
                  <input type="text" class="form-control" name="lname" placeholder="Last Name" />
                </div>
              </div>
              <div class="form-group">
                <input type="checkbox" id="" />Already a Customer?
              </div>
              <div class="row">
                <div class="form-group col-6">
                  <label for="Email">Email:</label>
                  <input type="text" class="form-control" placeholder="Email" name="user_email" />
                </div>
                <div class="form-group col-6">
                  <label for="Confirm-Email">Confirm Email:</label>
                  <input type="text" class="form-control" placeholder="Email" />
                </div>
              </div>
              <div class="row">
                <div class="form-group col-6">
                  <label for="Password">Password:</label>
                  <input type="text" class="form-control" name="user_pwd" placeholder="Password" />
                </div>
                <div class="form-group col-6">
                  <label for="Confirm-Password">Confirm Password:</label>
                  <input type="text" class="form-control" placeholder="Password" />
                </div>
              </div>
              <h6>
                Password must be a minimum of 8 characters and contain at
                least 1 number and 1 letter
              </h6>
              <div class="form-group">
                <input type="checkbox" />I would like to receive special
                wine offers, shipment tracking info and order status updates
              </div>
              <div class="form-group col-12">
                <label for="Phone">Phone:</label>
                <input type="tel" class="form-control" name="user_no" placeholder="Mobile Number" />
              </div>
              <div class="form-group">
                <input type="submit" class="btn btn-success btn-block" name="userbtn" />
              </div>
              <?php
              if (isset($_POST['userbtn'])) {
                include_once("./db_con.php");
                $qry = "insert into user_info(user_name,user_email,user_pwd,user_no) values('$_POST[fname] $_POST[lname]','$_POST[user_email]','$_POST[user_pwd]','$_POST[user_no]')";
                $resultset = mysqli_query($link, $qry);
              }
              ?>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>