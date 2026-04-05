<?php include_once("./header1.php"); ?>
<section id="image">
    <div class=" card-img-overlay ">
        <div class=" card col-lg-6 col-md-6 col-sm-6 col-xs-12 mt-5" style=" margin-top: 6em;  margin: auto ; ">
            <div class="card-body">
                <h5 class="card-title text-center text-success ">Edit Details</h5>
                <form action="" method="post">
                    <?php
                    if (isset($_GET['id'])) {
                        include_once("./db_con.php");
                        $id = $_GET['id'];
                        $qry = "select * from buses where sno='$id'";
                        $resultset = mysqli_query($link, $qry);
                        $row = mysqli_fetch_assoc($resultset);
                    }
                    ?>
                    <div class=" form-group ">
                        <label for="busno">Bus Number</label>
                        <input type="text" name="edit" value="<?php echo $row['bus_number']; ?>" class="form-control">
                    </div>
                    <div class=" form-group ">
                        <input type="submit" name="subbtn" class="btn btn-success btn-block">
                    </div>
                    <?php
                    if (isset($_POST['subbtn'])) {
                        include_once("./db_con.php");
                        $qry = "Update buses set bus_number='$_POST[edit]' where sno='$id'";
                        $resultset = mysqli_query($link, $qry);
                        header("location:../buses.php");
                    }
                    ?>
                </form>
            </div>
        </div>
    </div>
</section>
<?php include_once("./footer.php"); ?>