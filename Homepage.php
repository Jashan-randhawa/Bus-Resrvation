<?php include_once("header2.php"); ?>
<?php
if (isset($_POST["user"])) {
  echo "hello...";
  var_dump(empty($_POST["email"]));
  if (!empty($_POST['email']) and !empty($_POST['pwd'])) {
    include_once("./db_con.php");
    $qry = "select * from costumer where email = '$_POST[email]' and pwd = '$_POST[pwd]'";
    $resultset = mysqli_query($link, $qry);
    echo mysqli_num_rows($resultset);
    $n = mysqli_num_rows($resultset);
    if ($n == 1) {
      session_start();
      $row = mysqli_fetch_assoc($resultset);
      $_SESSION["name"] = $row["name"];
      $_SESSION["pwd"] = $row["pwd"];
      $_SESSION["phone"] = $row["phone"];
      $name = $_SESSION["name"];
      header("location:./userinterface/admin.php?d=2");
    } else {
      echo "<p class='alert alert-warning'>Invalid Email or Password</p>";
    }


  } else {
    echo "<p class='alert alert-warning'>Please fill all the fields</p>";
  }
}
?>
<?php
if (isset($_POST["admin"])) {
  echo "hello...";
  var_dump(empty($_POST["email"]));
  if (!empty($_POST['email']) and !empty($_POST['pwd'])) {
    include_once("./db_con.php");
    $qry = "select * from admin where Email_id = '$_POST[email]' and Password = '$_POST[pwd]'";
    $resultset = mysqli_query($link, $qry);
    echo mysqli_num_rows($resultset);
    $n = mysqli_num_rows($resultset);
    if ($n == 1) {
      session_start();
      $row = mysqli_fetch_assoc($resultset);
      $_SESSION["name"] = $row["name"];
      $_SESSION["pwd"] = $row["Password"];
      $_SESSION["phone"] = $row["phone"];
      $name = $_SESSION["name"];
      header("location:./admin.php?d=2");
    } else {
      echo "<p class='alert alert-warning'>Invalid Email or Password</p>";
    }


  } else {
    echo "<p class='alert alert-warning'>Please fill all the fields</p>";
  }
}
?>
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
                  <div class="form-group col--lg-12">
                    <label for="Email">Email:</label>
                    <input type="text" class="form-control" placeholder="Email" name="user_email" />
                  </div>
                  <div class="form-group col--lg-12">
                    <label for="Password">Password:</label>
                    <input type="text" class="form-control" name="user_pwd" placeholder="Password" />
                  </div>
                  <h6>
                    Password must be a minimum of 8 characters and contain at
                    least 1 number and 1 letter
                  </h6>
                  <div class="form-group col--lg-12">
                    <label for="Phone">Phone:</label>
                    <input type="tel" class="form-control" name="user_no" placeholder="Mobile Number" />
                  </div>
                  <div class="form-group col--lg-12">
                    <label for="Address">Address:</label>
                    <textarea name="address" id="address" cols="2" placeholder="Your Address"
                      style=" width: 100% ; "></textarea>
                  </div>
                  <div class="form-group col--lg-12 ">
                    <input type="submit" class="btn btn-success btn-block" name="userbtn" />
                  </div>
                  <?php
                  if (isset($_POST['userbtn'])) {
                    include_once("./db_con.php");
                    $qry = "insert into costumer values('','$_POST[fname] $_POST[lname]','$_POST[user_email]','$_POST[user_pwd]','$_POST[user_no]','$_POST[address]')";
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
  <div class="pb-5">
    <form action="" method="post">
      <center>
        <label for="PNR">
          <h1 class="mt-5 mb-5">Enter Your PNR Number To Get Booking Details</h1>
        </label>
        <table>
          <tr>
            <td style=" padding-top: 12px; ">
              <label for="pnr">
                <h4>Enter PNR Number : </h4>
              </label>
            </td>
            <td>
              <input type="number" name="pnrnum">
            </td>
          </tr>
        </table>
        <input type="submit" class=" btn btn-block btn-info col-lg-4 " name="pnr" value=" search ">
      </center>
      <?php
      if (isset($_POST['pnr'])) {
        $pnr = $_POST['pnrnum']; ?>
        <script>
          window.location.href = "./Homepage.php?pnr=<?php echo $pnr; ?>";
        </script>
        <?php
      }
      ?>
    </form>
    <center>
      <button class="btn btn-danger col-lg-4 " id="go" data-toggle="modal"
        data-target="#pnrmodal" hidden>Administrator Login</button>
    </center>
    <?php
    if (isset($_GET['pnr'])) {
      include_once("./db_con.php");
      $qry = "select * from booking where sno ='$_GET[pnr]'";
      $resultset = mysqli_query($link, $qry);
      $row = mysqli_fetch_assoc($resultset);
      if (isset($row)) {
        error_reporting(E_ERROR | E_PARSE);
        if ($_GET['pnr'] == $row['sno']) {
          ?>
          <script type="text/javascript">
            function show_modal() {
              document.getElementById("go").click();
            }
          </script>
          <?php
        } 
      } else {
        echo "<script>alert('invalid pnr number');</script>";
      }
    }
    ?>
    <div class="modal fade" id="pnrmodal">
      <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
          <button type="button" class="close text-right" data-dismiss="modal">
            <span>&times;</span>
          </button>
          <div class=" tab-content ">
            <?php
            if(isset($_GET['pnr'])){
              include_once("./db_con.php");
              $qry="select * from booking where sno='$_GET[pnr]'";
              $resultset=mysqli_query($link,$qry);
              $row=mysqli_fetch_assoc($resultset);
              echo "<h1 class='text-center'> PNR Number = ".$row['sno'],"</h1>";
              echo "<h1 class='text-center'> Bus Number = ".$row['bus'],"</h1>";
              echo "<h1 class='text-center'> Name = ".$row['name'],"</h1>";
              echo "<h1 class='text-center'> Contact Number = ".$row['contact'],"</h1>";
              echo "<h1 class='text-center'> Bus point = ".$row['city1'],"</h1>";
              echo "<h1 class='text-center'> Destination = ".$row['city2'],"</h1>";
              echo "<h1 class='text-center'> Seat Number = ".$row['seat'],"</h1>";
              echo "<h1 class='text-center'> Total Amount = ".$row['price'],"</h1>";
            }
            ?>
          </div>
        </div>
      </div>
    </div>
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
      <form action="./project.php" method="post">
        <div class="input-group mt-4 mb-4">
          <div class="input-group-append">
            <span class="input-group-text" id="basic-addon1">Name</span>
          </div>
          <input type="text" class="form-control" placeholder="Username" name="name" aria-label="Username"
            aria-describedby="basic-addon1" />
          <div class="input-group-append">
            <span class="input-group-text" id="basic-addon2">Email</span>
          </div>
          <input type="text" class="form-control" name="email" placeholder="Email Address" aria-label="Email Address"
            aria-describedby="basic-addon2" />
        </div>
        <div class="input-group mt-4 mb-4">
          <div class="input-group-append">
            <span class="input-group-text" id="basic-addon3">Subject</span>
          </div>
          <input type="text" class="form-control" name="subject" placeholder="Subject" aria-label="Username"
            aria-describedby="basic-addon3" />
        </div>
        <div class="input-group mt-4 mb-4">
          <textarea rows="number" class="form-control" placeholder="How We Can Help You" name="query"
            aria-label="Username" aria-describedby="basic-addon3"></textarea>
        </div>
        <div class="input-group-append">
          <input type="submit" name="subbtn" class="btn btn-primary btn-lg btn-block">
        </div>
    </div>
  </div>
  <?php
  if (isset($_POST['subbtn'])) {
    include_once("./db_con.php");
    $qry = "insert into query (user_name,user_email,user_subject,user_qry) values ('$_POST[name]','$_POST[email]','$_POST[subject]','$_POST[query]')";
    $resultset = mysqli_query($link, $qry);
  }
  ?>
</section>
<?php include 'footer.php'; ?>