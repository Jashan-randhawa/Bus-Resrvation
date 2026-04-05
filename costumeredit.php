<?php include_once("./header1.php"); ?>
<section id="image" >
    <div class=" card-img-overlay " >
        <div class=" card col-lg-6 col-md-6 col-sm-6 col-xs-12 col-lg-offset-3 col-md-offset-3 col-sm-offset-3 col-xs-offset-3  "
        style=" margin-top: 6em; margin: auto ; ">
        <div class=" card-body " >
        <h4 class=" card-title text-center text-success "> Edit Details </h4>
        <form action="" method="post">
        <?php
                    if (isset($_GET['id'])) {
                        include_once("./db_con.php");
                        $id = $_GET['id'];
                        $qry = "select * from costumer where sno='$id'";
                        $resultset = mysqli_query($link, $qry);
                        $row = mysqli_fetch_assoc($resultset);
                    }
                    ?>
                <div class="form-group">
                  <label for="Name">Name :</label>
                  <input type="text" name="unm" class="form-control" value="<?php echo $row['name']; ?>" placeholder="Enter your name" />
                </div>
                <div class="form-group">
                  <label for="email">Email Id :</label>
                  <input type="email" name="email" class="form-control" value="<?php echo $row['email']; ?>" placeholder="Enter your email" />
                </div>
                <div class="form-group">
                  <label for="Phone">Phone :</label>
                  <input type="tel" name="phone" class="form-control" value="<?php echo $row['phone']; ?>" placeholder="Enter your number" />
                </div>
                <div class="form-group">
                  <label for="Address">Address :</label>
                  <input type="text" name="address" class="form-control" value="<?php echo $row['address']; ?>" placeholder="Enter your address" />
                </div>
                <div class="form-group">
                  <input type="submit" class="btn btn-success btn-block " name="edit" value="edit" />
                </div>
                <?php
                    if (isset($_POST['edit'])) {
                        include_once("./db_con.php");
                        $qry = "Update costumer set name='$_POST[unm]',
                        email='$_POST[email]',
                        phone='$_POST[phone]',
                        address='$_POST[address]'";
                        $resultset = mysqli_query($link, $qry);
                        header("location:../costumer.php");
                    }
                    ?>
              </form>
        </div>
    </div>
    </div>
</section>
<?php include_once("./footer.php"); ?>