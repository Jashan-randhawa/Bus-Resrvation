<?php include_once("./adminhead.php"); ?>
<div class="col-lg-10 col-md-12 col-sm-12" style=" float: right ; ">
    <h1 class=" text-info ">Add New Admin</h1>
    <div class="row">
        <div class=" card col-lg-6 col-md-6 col-sm-6 mt-5 " style=" background-color: aquamarine; ">
            <div class=" card-body ">
                <form action="" method="post">
                    <div class=" form-group ">
                        <label for=" Name ">Name : </label>
                        <input type="text" class=" form-control " name="unm">
                    </div>
                    <div class=" form-group ">
                        <label for="email">Email Id : </label>
                        <input type="email" class=" form-control " name="email">
                    </div>
                    <div class=" form-group ">
                        <label for="Password">Password : </label>
                        <input type=" password " class=" form-control " name="pwd">
                    </div>
                    <div class=" form-group ">
                        <label for="Phone">Phone : </label>
                        <input type="tel" class=" form-control " name="phone">
                    </div>
                    <div class=" form-group ">
                        <input type="submit" class=" btn btn-info btn-block " name="subbtn">
                    </div>
                    <?php
                    if(isset($_POST["subbtn"])){
                        include_once("./db_con.php");
                        $qry = "insert into admin(name,Email_id,Password,phone) values('$_POST[unm]','$_POST[email]','$_POST[pwd]','$_POST[phone]')";
                        $resultset = mysqli_query($link,$qry);
                    }
                    ?>
                </form>
            </div>
        </div>
        <div class=" col-lg-6 col-md-6 col-sm-6 mt-5 " >
        <form action="" method="post">
      <input type="submit" name="admin" class=" btn btn-block btn-info mt-4 " value=" Show Admin Details ">
    </form>
    <?php
    if (isset($_POST['admin'])) {
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
        </tr>
        Tab;

      include_once("./db_con.php");
      $qry = "select *from admin";
      $resultset = mysqli_query($link, $qry);
      while ($row = mysqli_fetch_assoc($resultset)) {
        $mytable = $mytable . "<tr>
        <td>$row[sno]</td>
        <td>$row[name]</td>
        <td>$row[Email_id]</td>
        <td>$row[Password]</td>
        <td>$row[phone]</td></tr>";
      }
      $mytable = $mytable . "</table></div>";
      echo $mytable;

    }

    ?>
        </div>
    </div>
</div>
<?php include_once("./adminfooter.php"); ?>