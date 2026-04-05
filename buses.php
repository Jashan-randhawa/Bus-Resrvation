<?php include_once("./adminsession.php") ?>
<?php include_once("adminhead.php") ?>
<div class="col-lg-10 col-md-12 col-sm-12" style=" float: right ; ">
  <h1 class="text-info">Bus Status</h1>
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
                Add Bus Number
              </h5>
            </div>
            <div class="modal-body">
              <form action="" method="post">
                <div class="form-group">
                  <label for="Bus Number">Bus Number :</label>
                  <input type="text" name="busno" class="form-control" placeholder="Bus Number" />
                </div>
                <div class="form-group">
                  <input type="submit" class="btn btn-success" name="add" value="Submit" />
                </div>
                <?php
                if (isset($_POST['add'])) {
                  include_once("./db_con.php");
                  $qry = "insert into buses(bus_number) values('$_POST[busno]')";
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
  <section  >
    <form action="" method="post">
      <input type="submit" name="open" class=" btn btn-block btn-info mt-4 " value=" Show Bus Details ">
    </form>
    <?php
    if (isset($_POST['open'])) {
      //var_dump($row);
      $mytable = <<<Tab
        <div class="table-responsive" style="margin-top:10px;">
        <table class="table table-bordered table-striped" >
        <tr>
        <th>#</th>
        <th>Bus Number</th>
        <th>Edit</th>
        <th>Delete</th>
        Tab;

      include_once("./db_con.php");
      $qry = "select * from buses";
      $resultset = mysqli_query($link, $qry);
      while ($row = mysqli_fetch_assoc($resultset)) {
        $mytable = $mytable . "
        <tr><td>$row[sno]</td>
        <td>$row[bus_number]</td>
        <td><a href='./editpage/busesedit.php?id=$row[sno]' class='btn btn-warning'>Edit</a></td>
        <td><a href='./buses.php?id=$row[sno]' class='btn btn-danger'>Delete</a></td>";
      }
      $mytable = $mytable . "</table></div>";
      echo $mytable;

    }

    ?>
    <?php
    if(isset($_GET['id'])){
      include_once("./db_con.php");
      $qry = "delete from buses where sno = $_GET[id]";
      $resultset=mysqli_query($link,$qry);
    }
    ?>
  </section>
</div>
<?php include_once("adminfooter.php") ?>