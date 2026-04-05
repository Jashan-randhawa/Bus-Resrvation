<?php include_once("./adminsession.php") ?>
<?php include_once("./header1.php"); ?>
<section id="image">
    <div class=" card-img-overlay ">
        <div class=" card col-lg-6 col-md-6 col-sm-6 col-xs-12 col-lg-offset-3 col-md-offset-3 col-sm-offset-3 col-xs-offset-3  "
            style=" margin-top: 6em; margin: auto ; ">
            <div class="card-body">
                <h4 class=" card-title text-center text-success "> Edit Details </h4>
                <form action="" method="post">
                    <?php
                    if (isset($_GET['id'])) {
                        include_once("./db_con.php");
                        $id = $_GET['id'];
                        $qry = "select * from route where sno='$id'";
                        $resultset = mysqli_query($link, $qry);
                        $row = mysqli_fetch_assoc($resultset);
                    }
                    ?>
                    <div class="form-group">
                        <label for="From"> From :</label>
                        <input type="text" name="From" class="form-control" value="<?php echo $row['city1']; ?>"
                            placeholder="From city" />
                    </div>
                    <div class="form-group">
                        <label for="To"> To :</label>
                        <input type="text" name="To" value="<?php echo $row['city2']; ?>" class="form-control"
                            placeholder="To city" />
                    </div>
                    <div class="form-group">
                        <label for="bus no">Bus no :</label>
                        <input type="text" name="bus" value="<?php echo $row['busno']; ?>" class="form-control"
                            placeholder="Bus Number" />
                    </div>
                    <div class="form-group">
                        <label for="Departure Time :">Departure Time :</label>
                        <input type="time" name="time" value="<?php echo $row['time']; ?>" class="form-control"
                            placeholder="Departure Time" />
                    </div>
                    <div class="form-group">
                        <label for="Ticket price">Ticket Price :</label>
                        <input type=" number " name="price" value="<?php echo $row['price']; ?>" class="form-control"
                            placeholder="Ticket Price" />
                    </div>
                    <div class="form-group">
                        <input type="submit" class="btn btn-success btn-block " value="Edit" name="edit" />
                    </div>
                    <?php
                    if (isset($_POST['edit'])) {
                        include_once("./db_con.php");
                        $qry = "Update route set busno='$_POST[bus]',
                        city1='$_POST[From]',
                        city2='$_POST[To]',
                        time='$_POST[time]',
                        price='$_POST[price]' where sno='$id'";
                        $resultset = mysqli_query($link, $qry);
                        header("location:../route.php");
                    }
                    ?>
                </form>
            </div>
        </div>
    </div>
</section>
<?php include_once("./footer.php"); ?>