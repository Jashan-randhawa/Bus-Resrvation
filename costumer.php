<?php include_once("./adminsession.php") ?>
<?php include_once("adminhead.php") ?>
<div class="col-lg-10 col-md-12 col-sm-12" style=" float: right ; ">
  <h1 class="text-info">Costumer Status</h1>
  <br>
  <button class="btn btn-danger " data-toggle="modal" data-target="#loginModal">Add Costumer Details</button>
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
                Costumer Details
              </h5>
            </div>
            <div class="modal-body">
              <form action="" method="post">
                <div class="form-group">
                  <label for="Name">Name :</label>
                  <input type="text" name="unm" class="form-control" placeholder="Enter your name" />
                </div>
                <div class="form-group">
                  <label for="email">Email Id :</label>
                  <input type="email" name="email" class="form-control" placeholder="Enter your email" />
                </div>
                <div class="form-group">
                  <label for="password">Password</label>
                  <input type="password" name="pwd" class="form-control" placeholder="password" />
                </div>
                <div class="form-group">
                  <label for="Phone">Phone :</label>
                  <input type="tel" name="phone" class="form-control" placeholder="Enter your number" />
                </div>
                <div class="form-group">
                  <label for="Address">Address :</label>
                  <textarea name="address" class="form-control" placeholder="Enter your address" cols="2"></textarea>
                </div>
                <div class="form-group">
                  <input type="submit" class="btn btn-success" name="add" value="Submit" />
                </div>
                <?php

                if (isset($_POST['add'])) {
                  include_once("./db_con.php");
                  $qry = "insert into costumer values('','$_POST[unm]','$_POST[email]','$_POST[pwd]','$_POST[phone]','$_POST[address]')";
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
  <section>
    <form action="" method="post">
      <input type="submit" name="costumer" class=" btn btn-block btn-info mt-4 " value=" Show Costumer Details ">
    </form>
    <?php
    if (isset($_POST['costumer'])) {
      //var_dump($row);
      $mytable = <<<Tab
        <div class="table-responsive" style="margin-top:10px;">
        <table class="table table-bordered table-striped" >
        <tr>
        <th>#</th>
        <th>Name</th>
        <th>Email</th>
        <th>Password</th>
        <th>Phone</th>
        <th>Address</th>
        <th>Edit</th>
        <th>Delete</th>
        </tr>
        Tab;

      include_once("./db_con.php");
      $qry = "select *from costumer";
      $resultset = mysqli_query($link, $qry);
      while ($row = mysqli_fetch_assoc($resultset)) {
        $mytable = $mytable . "
        <tr><td>$row[sno]</td>
        <td>$row[name]</td>
        <td>$row[email]</td>
        <td>$row[pwd]</td>
        <td>$row[phone]</td>
        <td>$row[address]</td>
        <td><a href='./editpage/costumeredit.php?id=$row[sno]' class='btn btn-warning'>Edit</a></td>
        <td><a href='./costumer.php?id=$row[sno]' class='btn btn-danger'>Delete</a></td></tr>";
      }
      $mytable = $mytable . "</table></div>";
      echo $mytable;

    }

    ?>
    <?php
    if (isset($_GET['id'])) {
      include_once("./db_con.php");
      $qry = "delete from costumer where sno = $_GET[id]";
      $resultset = mysqli_query($link, $qry);
    }
    ?>
  </section>
</div>
<?php include_once("adminfooter.php") ?>