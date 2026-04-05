<?php include_once("./adminsession.php") ?>
<?php include_once("adminhead.php") ?>
<div class="col-lg-10 col-md-12 col-sm-12" style=" float: right ; ">
  <h1 class="text-info">Bus Status</h1>
  <br>
  <button class="btn btn-danger " data-toggle="modal" data-target="#loginModal">Add Route Details</button>
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
                Add Bus Route Details
              </h5>
            </div>
            <div class="modal-body">
              <form action="" method="post">
                <div class="form-group">
                  <label for="From"> From :</label>
                  <input type="text" name="From" class="form-control" placeholder="From city" />
                </div>
                <div class="form-group">
                  <label for="To"> To :</label>
                  <input type="text" name="To" class="form-control" placeholder="To city" />
                </div>
                <div class="form-group">
                  <label for="bus no">Bus no :</label>
                  <select name="bus" id="bus" style=" width: 189px; " required>
                    <option value="">Select Bus Number</option>
                    <?php
                    include_once("./db_con.php");
                    $qry = "SELECT bus_number FROM buses";
                    $resultset = mysqli_query($link, $qry);
                    $row = mysqli_fetch_assoc($resultset);
                    while ($row = mysqli_fetch_assoc($resultset)) {
                      ?>
                      <option value="<?php echo $row['bus_number']; ?>"><?php echo $row['bus_number']; ?></option>
                      <?php
                    }
                    ?>
                  </select>
                </div>
                <div class="form-group">
                  <label for="Departure Time :">Departure Time :</label>
                  <input type="time" name="time" class="form-control" placeholder="Departure Time" />
                </div>
                <div class="form-group">
                  <label for="Ticket price">Ticket Price :</label>
                  <input type=" number " name="price" class="form-control" placeholder="Ticket Price" />
                </div>
                <div class="form-group">
                  <input type="submit" class="btn btn-success" name="add" value="Submit" />
                </div>
                <?php
                if (isset($_REQUEST["add"])) {
                  include_once("./db_con.php");
                  $qry = " insert into route values(null,'$_REQUEST[From]','$_REQUEST[To]','$_REQUEST[bus]','$_REQUEST[time]','$_REQUEST[price]')";
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
  <section class="mt-4">
    <form action="" method="post">
      <input type="submit" name="route" class=" btn btn-block btn-info " value="Show Route Details">
    </form>
    <?php
    if (isset($_REQUEST["route"])) {
      //var_dump($row);
      $mytable = <<<Tab
        <div class="table-responsive" style="margin-top:10px;">
        <table class="table table-bordered table-striped" >
        <tr>
        <th>#</th>
        <th>From</th>
        <th>To</th>
        <th>Bus Number</th>
        <th>Time</th>
        <th>Price</th>
        <th>Edit</th>
        <th>Delete</th>
        </tr>
        Tab;

      include_once("./db_con.php");
      $qry = "select * from route";
      $resultset = mysqli_query($link, $qry);
      while ($row = mysqli_fetch_assoc($resultset)) {
        $mytable = $mytable . "
        <tr><td>$row[sno]</td>
        <td>$row[city1]</td>
        <td>$row[city2]</td>
        <td>$row[busno]</td>
        <td>$row[time]</td>
        <td>$row[price]</td>
        <td><a href='./editpage/routeedit.php?id=$row[sno]' class='btn btn-warning'>Edit</a></td>
        <td><a href='./route.php?id=$row[sno]' class='btn btn-danger'>Delete</a></td></tr>";
      }
      $mytable = $mytable . "</table></div>";
      echo $mytable;

    }

    ?>
    <?php
    if (isset($_GET['id'])) {
      include_once("./db_con.php");
      $qry = "delete from route where sno= $_GET[id]";
      $resultset = mysqli_query($link, $qry);
      echo "<script>window.location='route.php'</script>";
    }
    ?>
  </section>
</div>
<?php include_once("adminfooter.php") ?>