<?php include_once("./usersession.php"); ?>
<?php include_once("./adminhead.php"); ?>

<section class="col-lg-10 col-md-10 col-sm-12 " style=" float: right; ">
  <form action="" method="post">
    <?php $number = $_SESSION['phone']; ?>
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
        <th>Seat Number</th>
        <th>Price</th>
        <th>Cancel Booking</th>
        </tr>
        Tab;
    include_once("./db_con.php");
    $qry = "select * from booking where contact='$number'";
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
        <td>$row[seat]</td>
        <td>$row[price]</td>
        <td><a href='./userbooking.php?id=$row[sno]' class='btn btn-danger'>Cancel</a></td></tr>";
    }
    $mytable = $mytable . "</table></div>";
    echo $mytable;

  }

  ?>
  <?php
  if (isset($_GET['id'])) {
    echo "<script>confirm('Are you sure you want to cancel booking')</script>";
    include_once("./db_con.php");
    $qry = "delete from booking where sno= $_GET[id]";
    $resultset = mysqli_query($link, $qry);
    echo "<script>window.location.href='userbooking.php'</script>";
  }
  ?>
</section>
<?php include_once("./adminfooter.php"); ?>