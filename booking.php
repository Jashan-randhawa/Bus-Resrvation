<?php include_once("./adminsession.php") ?>
<?php include_once("adminhead.php") ?>

<div class="col-lg-10 col-md-12 col-sm-12" style=" float: right ; ">
  <h1 class="text-info">Booking Status</h1>
  <br>
  <button class="btn btn-danger " data-toggle="modal" data-target="#loginModal">Add Bus Details</button>
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
                Add Booking
              </h5>
            </div>
            <div class="modal-body">
              <form action="" method="post">
                <div class=" form-group ">
                  <label for="#">Id</label>
                  <input type="text" name="id" class=" form-control " value=" <?php echo rand(1, 10000000); ?> ">
                </div>
                <div class="form-group">
                  <label for="bus" class="mr-5">Bus Number :</label>
                  <select name="bus" id="bus" required>
                    <option value="">Select Bus Number</option>
                    <?php
                    include_once("./db_con.php");
                    $qry = "SELECT bus_number FROM buses";
                    $resultset = mysqli_query($link, $qry);
                    while ($row = mysqli_fetch_assoc($resultset)) {
                      ?>
                      <option value="<?php echo $row['bus_number']; ?>"><?php echo $row['bus_number']; ?></option>
                      <?php
                    }
                    ?>
                  </select>
                </div>
                <div class="form-group">
                  <label for="Costumer Name">Costumer Name :</label>
                  <input type="text" name="unm" class="form-control" placeholder="Enter Costumer Name" />
                </div>
                <div class="form-group">
                  <label for="Contact Number">Contact Number:</label>
                  <input type="tel" name="num" class="form-control" placeholder="Enter Contact Number" />
                </div>
                <div class="form-group">
                  <label for="#">From :</label>
                  <select name="from" id="from" required>
                    <option value="">Select Bus Number</option>
                    <?php
                    include_once("./db_con.php");
                    $qry = "SELECT city1 FROM route";
                    $resultset = mysqli_query($link, $qry);
                    while ($row = mysqli_fetch_assoc($resultset)) {
                      ?>
                      <option value="<?php echo $row['city1']; ?>"><?php echo $row['city1']; ?></option>
                      <?php
                    }
                    ?>
                  </select>
                </div>
                <div class="form-group">
                  <label for="#">To :</label>
                  <select name="to" id="to" required>
                    <option value="">Select Bus Number</option>
                    <?php
                    include_once("./db_con.php");
                    $qry = "select city2 from route";
                    $resultset = mysqli_query($link, $qry);
                    while ($row = mysqli_fetch_assoc($resultset)) {
                      ?>
                      <option value="<?php echo $row['city2']; ?>"><?php echo $row['city2']; ?></option>
                      <?php
                    }
                    ?>
                  </select>
                </div>
                <div class="form-group">
                  <label for="date">Date</label>
                  <input type="date" min="<?php
                  $currentDate = date('Y-m-d');
                  echo $currentDate; ?>" name="date" id="date" class="form-control" placeholder="Enter Date" />
                </div>
                <div class="form-group">
                  <label for="time">Time</label>
                  <input type="time" name="time" class="form-control" placeholder="Enter Date" />
                </div>
                <script>
                  function fun(val) {
                    document.getElementById("seat_no").value = val;
                  }
                </script>
                <div class="form-group">
                  <label for="Select Seat">Select Seat :</label>
                  <div class="mb-3">
                    <div class="container" id="seat">
                      <div class="row mb-3 ">
                        <div class="col-lg-4 col-md-6 col-sm-4 ">
                          <button type="button" class="btn btn-info seat" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(1)">1</button>
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(2)">2</button>
                        </div>
                        <div class="col-lg-6 col-md-4 col-sm-4 ">
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(3)">3</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(4)">4</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(5)">5</button>
                        </div>
                      </div>
                      <div class="row mb-3 ">
                        <div class="col-lg-4 col-md-6 col-sm-4 ">
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(6)">6</button>
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(7)">7</button>
                        </div>
                        <div class="col-lg-6 col-md-4 col-sm-4 ">
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(8)">8</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(9)">9</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(10)">10</button>
                        </div>
                      </div>
                      <div class="row mb-3 ">
                        <div class="col-lg-4 col-md-6 col-sm-4 ">
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(11)">11</button>
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(12)">12</button>
                        </div>
                        <div class="col-lg-6 col-md-4 col-sm-4 ">
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(13)">13</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(14)">14</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(15)">15</button>
                        </div>
                      </div>
                      <div class="row mb-3 ">
                        <div class="col-lg-4 col-md-6 col-sm-4 ">
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(16)">16</button>
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(17)">17</button>
                        </div>
                        <div class="col-lg-6 col-md-4 col-sm-4 ">
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(18)">18</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(19)">19</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(20)">20</button>
                        </div>
                      </div>
                      <div class="row mb-3 ">
                        <div class="col-lg-4 col-md-6 col-sm-4 ">
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(21)">21</button>
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(22)">22</button>
                        </div>
                        <div class="col-lg-6 col-md-4 col-sm-4 ">
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(23)">23</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(24)">24</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(25)">25</button>
                        </div>
                      </div>
                      <div class="row mb-3 ">
                        <div class="col-lg-4 col-md-6 col-sm-4 ">
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(26)">26</button>
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(27)">27</button>
                        </div>
                        <div class="col-lg-6 col-md-4 col-sm-4 ">
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(28)">28</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(29)">29</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1"
                            onclick="fun(30)">30</button>
                        </div>
                      </div>
                      <div class="row  mb-3">
                        <div class="col-lg-10 col-md-6 col-sm-4 " style=" width: 300px; ">
                          <button class="btn btn-info btn-sm  " id="1" style=" width: 50px; height: 40px; "
                            onclick="fun(31)">31</button>
                          <button class="btn btn-info btn-sm" id="1" style=" width: 50px; height: 40px; "
                            onclick="fun(32)">32</button>
                          <button class="btn btn-info btn-sm" id="1" style=" width: 50px; height: 40px; "
                            onclick="fun(33)">33</button>
                          <button class="btn btn-info btn-sm" id="1" style=" width: 50px; height: 40px; "
                            onclick="fun(34)">34</button>
                          <button class="btn btn-info btn-sm  " id="1" style=" width: 50px; height: 40px; "
                            onclick="fun(35)">35</button>
                          <button class="btn btn-info btn-sm " id="1" style=" width: 50px; height: 40px; "
                            onclick="fun(36)">36</button>
                        </div>
                      </div>
                      <div class="row">
                        <div class=" form-group "></div>
                        <label for="Seat Number">Seat Number:</label>
                        <input type="number" name="seat" class="form-control" id="seat_no"
                          placeholder="Enter Seat Number" onclick="fun()">
                      </div>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label for="Amount">Total Amount:</label>
                  <input type="number" name="amount" class="form-control" id="amount" placeholder="Enter Amount"
                    required>
                </div>
                <div class="form-group">
                  <input type="submit" class="btn btn-success btn-block" name="check" value="Submit" />
                </div>
                <?php
                if (isset($_POST["check"])) {
                  include_once("./db_con.php");
                  $qry = "select seat from booking where bus = '$_POST[bus]'";
                  $resultset = mysqli_query($link, $qry);
                  if (mysqli_num_rows($resultset) == 0) {
                    include_once("./db_con.php");
                    $qry = "insert into booking values('$_POST[id]','$_POST[bus]','$_POST[unm]','$_POST[num]','$_POST[from]','$_POST[to]','$_POST[seat]','$_POST[amount]')";
                    $resultset = mysqli_query($link, $qry);
                  } else {
                    while ($row = mysqli_fetch_assoc($resultset)) {
                      if (!($row['seat'] == $_POST['seat'])) {
                        include_once("./db_con.php");
                        $qry = "insert into booking values('$_POST[id]','$_POST[bus]','$_POST[unm]','$_POST[num]','$_POST[from]','$_POST[to]','$_POST[seat]','$_POST[amount]')";
                        $resultset = mysqli_query($link, $qry);
                      } else {
                        include_once("./db_con.php");
                        $qry = "select * from booking";
                        $resultset = mysqli_query($link, $qry);
                        while ($row = mysqli_fetch_assoc($resultset)) {
                          if ($row['bus'] == $_POST['bus'] && $row['seat'] == $_POST['seat']) {
                            echo "<script>alert('Seat Already Booked')</script>";
                            echo "<script>window.location='booking.php'</script>";
                          }
                        }
                      }
                    }
                  }
                } /*

    if ($row['bus'] == $_POST['bus'] && $row['seat'] == $_POST['seat']) {
      echo "<script>alert('Seat Already Booked')</script>";
      echo "<script>window.location='booking.php'</script>";
    } 
    else if (!$row['seat'] == $_POST['seat']) {
      include_once("./db_con.php");
      $qry = "insert into booking values('$_POST[id]','$_POST[bus]','$_POST[unm]','$_POST[num]','$_POST[from]','$_POST[to]','$_POST[seat]','$_POST[amount]')";
      $resultset = mysqli_query($link, $qry);

      echo "<script>window.location='booking.php'</script>";
if(isset($_POST['add'])){
  include_once("./db_con.php");
      $qry = "insert into booking values('$_POST[id]','$_POST[bus]','$_POST[unm]','$_POST[num]','$_POST[from]','$_POST[to]','$_POST[seat]','$_POST[amount]')";
      $resultset = mysqli_query($link, $qry);
}*/
                ?>
              </form>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <section>
    <form action="" method="post">
      <input type="submit" name="booking" class=" btn btn-block btn-info mt-4 " value=" Show Booking Details ">
    </form>
    <?php
    if (isset($_POST['booking'])) {
      //var_dump($row);
      $mytable = <<<Tab
        <div class="table-responsive" style="margin-top:10px;">
        <table class="table table-bordered table-striped" >
        <tr>
        <th>PNR</th>
        <th>Bus Number</th>
        <th>Costumer Name</th>
        <th>Contact</th>
        <th>From</th>
        <th>To</th>
        <th>Date</th>
        <th>Time</th>
        <th>Seat Number</th>
        <th>Price</th>
        <th>Edit</th>
        <th>Delete</th>
        </tr>
        Tab;

      include_once("./db_con.php");
      $qry = "select *from booking";
      $resultset = mysqli_query($link, $qry);
      while ($row = mysqli_fetch_assoc($resultset)) {
        $mytable = $mytable . "
        <tr>
        
        <td>$row[sno]</td>
        <td>$row[bus]</td>
        <td>$row[name]</td>
        <td>$row[contact]</td>
        <td>$row[city1]</td>
        <td>$row[city2]</td>
        <td>$row[date]</td>
        <td>$row[time]</td>
        <td>$row[seat]</td>
        <td>$row[price]</td>
        <td><a href='./bookingedit.php?id=$row[seat]' class='btn btn-warning'>Edit</a></td>
        <td><a href='./booking.php?id=$row[sno]' class='btn btn-danger'>Delete</a></td></tr>";
      }
      $mytable = $mytable . "</table></div>";
      echo $mytable;

    }

    ?>
    <?php
    if (isset($_GET['id'])) {
      include_once("./db_con.php");
      $qry = "delete from booking where sno= $_GET[id]";
      $resultset = mysqli_query($link, $qry);
      echo "<script>window.location='booking.php'</script>";
    }
    ?>
  </section>
</div>
<?php include_once("adminfooter.php") ?>