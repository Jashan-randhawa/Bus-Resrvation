<?php include_once("./adminsession.php");  ?>
<?php include_once("./adminhead.php"); ?>
<div class="col-lg-10 col-md-12 col-sm-12" style=" float: right ; ">
<section class="mt-4">
    <form action="" method="post">
      <input type="submit" name="route" class=" btn btn-block btn-info " value="Show All Comments">
    </form>
    <?php
    if (isset($_REQUEST["route"])) {
      //var_dump($row);
      $mytable = <<<Tab
        <div class="table-responsive" style="margin-top:10px;">
        <table class="table table-bordered table-striped" >
        <tr>
        <th>#</th>
        <th>Name</th>
        <th>Email Id</th>
        <th>Subject</th>
        <th>Comment</th>
        </tr>
        Tab;

      include_once("./db_con.php");
      $qry = "select * from query";
      $resultset = mysqli_query($link, $qry);
      while ($row = mysqli_fetch_assoc($resultset)) {
        $mytable = $mytable . "
        <tr><td>$row[sno]</td>
        <td>$row[name]</td>
        <td>$row[email]</td>
        <td>$row[subject]</td>
        <td>$row[query]</td></tr>";
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
<?php include_once("./adminfooter.php"); ?>