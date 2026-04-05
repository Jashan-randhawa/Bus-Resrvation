<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
        integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/css/bootstrap.min.css"
        integrity="sha384-TX8t27EcRE3e/ihU7zmQxVncDAy5uIKz4rEkgIXeMed4M0jlfIDPvg6uqKI2xXr2" crossorigin="anonymous">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <title>Home</title>
    <link rel="stylesheet" href="./admin.css">
</head>

<body>
    <?php include_once("./usersession.php"); ?>
    <div class="container-fluid">
        <nav class="navbar navbar-expand-lg navbar-light ">
            <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#mycollapsediv"
                aria-controls="mycollapsediv" aria-expanded="false" aria-label="toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="mycollapsediv">
                <ul class="navbar-nav mr-auto mt-2 ml-5 col-lg-3 mr-auto flex-column vertical-nav bar">
                    <li>
                        <div class="row" style=" padding-left: 30px; ">
                            <img src="../images/bus.svg" alt="" style=" width: 35px; height: 25px; margin-top: 0px; ">
                            <a href="../Homepage.php" style=" text-decoration: none; color: black; ">
                                <h4>Bus Service</h4>
                            </a>
                        </div>
                    </li>
                    <li>
                        <img src="../images/userav-min.png" alt="" class=" img-fluid ">
                        <h4 class=" text-center">User</h4>
                        <h6 class=" text-center">
                            <?php echo $_SESSION['name']; ?>
                        </h6>
                    </li>
                    <li>
                        <a href="./admin.php" class=" col-lg-3 " style=" text-decoration: none ; color: black; ">New
                            Booking</a>
                    </li>
                    <li>
                        <a href="./userbooking.php" class=" col-lg-3 "
                            style=" text-decoration: none ; color: black; ">Your Booking</a>
                    </li>
                    <li>
                        <form action="" method="post">
                            <input type="submit" value="logout" name="logout" class=" btn btn-link col-lg-6 "
                                style=" text-decoration: none; color: black; ">
                        </form>
                    </li>
            </div>
        </nav>
        <section class=" col-lg-10 col-md-10 col-sm-12 mt-5 " style=" float: right; ">
            <h1 class=" text-center mb-5 ">Welcome to Our Bus Service Choose Your Destination to Travel</h1>
            <div class="card col-lg-10 col-md-10 col-sm-12" style=" margin: auto ; ">
                <div class=" card-body ">
                    <form action="" method="post">
                        <div class="form-group">
                            <label for="city">Your City :</label>
                            <br>
                            <select name="from" class="col-lg-6 col-md-6 col-sm-12" id="from" required>
                                <option value="">Select Your City</option>
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
                            <label for="#">Destination :</label>
                            <br>
                            <select name="to" class="col-lg-6 col-md-6 col-sm-12" id="to" required>
                                <option value="">Select Your Destination</option>
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
                        <div class=" form-group ">
                            <button type="submit" name="subbtn" class=" btn btn-info btn-block ">Check Avialable
                                Buses</button>
                        </div>
                        <?php
                        if (isset($_POST['subbtn'])) {
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
        <th>Booking</th>
        </tr>
        Tab;

                            include_once("./db_con.php");
                            $qry = "select * from route  where city1='$_POST[from]' and city2='$_POST[to]'";
                            $resultset = mysqli_query($link, $qry);
                            while ($row = mysqli_fetch_assoc($resultset)) {
                                $mytable = $mytable . "
        <tr><td>$row[sno]</td>
        <td>$row[city1]</td>
        <td>$row[city2]</td>
        <td>$row[busno]</td>
        <td>$row[time]</td>
        <td>$row[price]</td>
        <td><a href='./booking.php?city1=$row[city1]&city2=$row[city2]&bus=$row[busno]&time=$row[time]&price=$row[price]&date=$_POST[date]' class='btn btn-warning'>Book Seat</a></td></tr>";
                            }
                            $mytable = $mytable . "</table></div>";
                            echo $mytable;

                        }


                        ?>
                    </form>
                </div>
            </div>
        </section>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.5.1/dist/jquery.slim.min.js"
        integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-Fy6S3B9q64WdZWQUiU+q4/2Lc9npb8tCaSX9FK7E8HnRr0Jz8D6OP9dO5Vg3Q9ct"
        crossorigin="anonymous"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"
        integrity="sha384-DfXdz2htPH0lsSSs5nCTpuj/zy4C+OGpamoFVy38MVBnE+IbbVYUew+OrCXaRkfj"
        crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.5.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ho+j7jyWK8fNQe+A12Hb8AhRq26LrZ/JpcUGGOn+Y7RsweNrtN/tE3MoK7ZeZDyx"
        crossorigin="anonymous"></script>
    <script>
        AOS.init();
    </script>
</body>

</html>