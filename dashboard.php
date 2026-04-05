<div class="col-lg-10 col-md-12 col-sm-12" style=" float: right ; ">
    <div class="row">
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
                <?php
                include_once("./db_con.php");
                $qry="SELECT * from booking";
                $resultset=mysqli_query($link,$qry);
                $row=mysqli_num_rows($resultset);
                ?>
                <h1 class="card-title text-center btn-info " style=" padding: 2px; " >Bookings</h1>
                <h6>Total Number Of Bookings</h6>
                <h1><?php echo $row; ?></h1>
            </div>
            <div class="footer" >
                <a href="#" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
            <?php
                include_once("./db_con.php");
                $qry="SELECT * from buses";
                $resultset=mysqli_query($link,$qry);
                $row=mysqli_num_rows($resultset);
                ?>
                <h1 class="card-title text-center btn-success " style=" padding: 2px; " >Buses</h1>
                <h6>Total Number Of Buses</h6>
                <h1><?php echo $row; ?></h1>
            </div>
            <div class="footer" >
                <a href="#" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
                <h1 class="card-title text-center btn-danger " style=" padding: 2px; " >Routes</h1>
                <?php
                include_once("./db_con.php");
                $qry="SELECT * from route";
                $resultset=mysqli_query($link,$qry);
                $row=mysqli_num_rows($resultset);
                ?>
                <h6>Total Number Of Routes</h6>
                <h1><?php echo $row; ?></h1>
            </div>
            <div class="footer" >
                <a href="#" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
                <h1 class="card-title text-center btn-warning " style=" padding: 2px; " >Seats</h1>
                <?php
                include_once("./db_con.php");
                $qry="SELECT * from buses";
                $resultset=mysqli_query($link,$qry);
                $row=mysqli_num_rows($resultset);
                ?>
                <h6>Total Number Of Seats</h6>
                <h1><?php echo $row*36; ?></h1>
            </div>
            <div class="footer" >
                <a href="#" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
                <h1 class="card-title text-center btn-primary " style=" padding: 2px; " >Costumer</h1>
                <?php
                include_once("./db_con.php");
                $qry="SELECT * from costumer";
                $resultset=mysqli_query($link,$qry);
                $row=mysqli_num_rows($resultset);
                ?>
                <h6>Total Number Of Costumer</h6>
                <h1><?php echo $row; ?></h1>
            </div>
            <div class="footer" >
                <a href="#" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
                <h1 class="card-title text-center btn-secondary " style=" padding: 2px; " >Admin</h1>
                <?php
                include_once("./db_con.php");
                $qry="SELECT * from admin";
                $resultset=mysqli_query($link,$qry);
                $row=mysqli_num_rows($resultset);
                ?>
                <h6>Total Number Of Admin</h6>
                <h1><?php echo $row; ?></h1>
            </div>
            <div class="footer" >
                <a href="#" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
        <div class=" card col-lg-3 m-4">
            <div class="card-body">
                <h1 class="card-title text-center btn-dark " style=" padding: 2px; " >Earnings</h1>
                <?php
                include_once("./db_con.php");
                $qry="SELECT SUM(price) as cost from booking";
                $resultset=mysqli_query($link,$qry);
                $row = mysqli_fetch_assoc($resultset);
                ?>
                <h6>Total Number Of Earnings</h6>
                <h1><?php echo $row['cost']; ?></h1>
            </div>
            <div class="footer" >
                <a href="#" style=" font-size: larger; float: right; " > View More </a>
            </div>
        </div>
    </div>
</div>