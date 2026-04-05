<?php include_once("./usersession.php"); ?>
<?php include_once("./adminhead.php"); ?>


<div class=" col-lg-10 col-md-10 col-sm-12 col-xs-12" style="
  float: right;">
    <section class=" col-lg-8 col-md-8 col-sm-12 col-xs-12" " style=" margin: auto ;">
        <div class=" card">
            <div class="card-body">
                <h5>
                    Add Booking
                </h5>
                <form action="" method="post">
                    <div class=" form-group ">
                        <label for="PNR">PNR</label>
                        <input type="text" name="id" class=" form-control " value=" <?php echo rand(1, 10000000); ?> ">
                    </div>
                    <div class="form-group">
                        <label for="bus" class="mr-5">Bus Number :</label>
                        <input type="text" name="bus" class="form-control" value="<?php echo $_GET['bus']; ?>" readonly
                            placeholder="Enter Bus Number" />
                    </div>
                    <div class="form-group">
                        <label for="Your Name">Your Name :</label>
                        <input type="text" name="unm" value="<?php echo $_SESSION['name']; ?>" class="form-control"
                            placeholder="Enter Costumer Name" required />
                    </div>
                    <div class="form-group">
                        <label for="Contact Number">Contact Number:</label>
                        <input type="tel" name="num" value="<?php echo $_SESSION['phone'] ?>" class="form-control"
                            placeholder="Enter Contact Number" required />
                    </div>
                    <div class="form-group">
                        <label for="#">From :</label>
                        <input type="text" name="from" class="form-control" value="<?php echo $_GET['city1']; ?>"
                            readonly placeholder="Enter Bus Number" />
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="#">To :</label>
                        <input type="text" name="to" class="form-control" value="<?php echo $_GET['city2']; ?>" readonly
                            placeholder="Enter Bus Number" />
                    </div>
                    <div class="form-group">
                        <label for="date">Date</label>
                        <input type="date" min="<?php
                        $currentDate = date('Y-m-d');
                        echo $currentDate; ?>" name="date" id="date" value="<?php echo $_GET['date']; ?>" class="form-control" placeholder="Enter Date" />
                    </div>
                    <div class="form-group">
                        <label for="time">Time</label>
                        <input type="time" name="time" class="form-control" value="<?php echo $_GET['time'];  ?>" placeholder="Enter Date" />
                    </div>
                    <script>
                        function fun(val) {
                            document.getElementById("seat_no").value = val;
                        }
                    </script>
                    <?php
                    if (isset($_GET['bus'])) {
                        include_once("./db_con.php");
                        $qry = "select seat from booking where date='$_GET[date]' and bus='$_GET[bus]'";
                        $resultset = mysqli_query($link, $qry);
                        $row = mysqli_num_rows($resultset);
                        while ($val = mysqli_fetch_assoc($resultset)) {
                            ?>
                            <script>
                                function fun<?php echo $val['seat']; ?>() {
                                    document.getElementById("<?php echo $val['seat']; ?>").style.background = "red";
                                    document.getElementById("<?php echo $val['seat']; ?>").disabled = true;
                                }
                            </script>
                            <?php
                        } ?>
                        <script> function call() {
                                <?php include_once("./db_con.php");
                                $qry = "select seat from booking where date='$_GET[date]' and bus='$_GET[bus]'";
                                $resultset = mysqli_query($link, $qry);
                                $row = mysqli_num_rows($resultset);
                                while ($val = mysqli_fetch_assoc($resultset)) {
                                    ?>
                                    fun<?php echo $val['seat']; ?>();

                                    <?php
                                }
                                echo "}</script>";
                    }

                    ?>
                <div class="form-group" >
                <label for="Select Seat">Select Seat :</label>
                <div class="mb-3">
                    <div class="container" id="seat">
                        <div class="row mb-3 ">
                            <div class="col-lg-4 col-md-6 col-sm-4 ">
                                <button type="button" class="btn btn-info seat" style=" width: 50px; height: 40px; "
                                    id="1" onclick="fun(1)">1</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="2"
                                    onclick="fun(2)">2</button>
                            </div>
                            <div class="col-lg-6 col-md-4 col-sm-4 ">
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="3"
                                    onclick="fun(3)">3</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="4"
                                    onclick="fun(4)">4</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="5"
                                    onclick="fun(5)">5</button>
                            </div>
                        </div>
                        <div class="row mb-3 ">
                            <div class="col-lg-4 col-md-6 col-sm-4 ">
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="6"
                                    onclick="fun(6)">6</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="7"
                                    onclick="fun(7)">7</button>
                            </div>
                            <div class="col-lg-6 col-md-4 col-sm-4 ">
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="8"
                                    onclick="fun(8)">8</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="9"
                                    onclick="fun(9)">9</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="10"
                                    onclick="fun(10)">10</button>
                            </div>
                        </div>
                        <div class="row mb-3 ">
                            <div class="col-lg-4 col-md-6 col-sm-4 ">
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="11"
                                    onclick="fun(11)">11</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="12"
                                    onclick="fun(12)">12</button>
                            </div>
                            <div class="col-lg-6 col-md-4 col-sm-4 ">
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="13"
                                    onclick="fun(13)">13</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="14"
                                    onclick="fun(14)">14</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="15"
                                    onclick="fun(15)">15</button>
                            </div>
                        </div>
                        <div class="row mb-3 ">
                            <div class="col-lg-4 col-md-6 col-sm-4 ">
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="16"
                                    onclick="fun(16)">16</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="17"
                                    onclick="fun(17)">17</button>
                            </div>
                            <div class="col-lg-6 col-md-4 col-sm-4 ">
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="18"
                                    onclick="fun(18)">18</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="19"
                                    onclick="fun(19)">19</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="20"
                                    onclick="fun(20)">20</button>
                            </div>
                        </div>
                        <div class="row mb-3 ">
                            <div class="col-lg-4 col-md-6 col-sm-4 ">
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="21"
                                    onclick="fun(21)">21</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="22"
                                    onclick="fun(22)">22</button>
                            </div>
                            <div class="col-lg-6 col-md-4 col-sm-4 ">
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="23"
                                    onclick="fun(23)">23</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="24"
                                    onclick="fun(24)">24</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="25"
                                    onclick="fun(25)">25</button>
                            </div>
                        </div>
                        <div class="row mb-3 ">
                            <div class="col-lg-4 col-md-6 col-sm-4 ">
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="26"
                                    onclick="fun(26)">26</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="27"
                                    onclick="fun(27)">27</button>
                            </div>
                            <div class="col-lg-6 col-md-4 col-sm-4 ">
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="28"
                                    onclick="fun(28)">28</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="29"
                                    onclick="fun(29)">29</button>
                                <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="30"
                                    onclick="fun(30)">30</button>
                            </div>
                        </div>
                        <div class="row  mb-3">
                            <div class="col-lg-10 col-md-6 col-sm-4 " style=" width: 300px; ">
                                <button type="button" class="btn btn-info btn-sm  " id="31" style=" width: 50px; height: 40px; "
                                    onclick="fun(31)">31</button>
                                <button type="button" class="btn btn-info btn-sm ml-2" id="32" style=" width: 50px; height: 40px; "
                                    onclick="fun(32)">32</button>
                                <button type="button" class="btn btn-info btn-sm ml-2" id="33" style=" width: 50px; height: 40px; "
                                    onclick="fun(33)">33</button>
                                <button type="button" class="btn btn-info btn-sm ml-2" id="34" style=" width: 50px; height: 40px; "
                                    onclick="fun(34)">34</button>
                                <button type="button" class="btn btn-info btn-sm ml-2  " id="35" style=" width: 50px; height: 40px; "
                                    onclick="fun(35)">35</button>
                                <button type="button" class="btn btn-info btn-sm ml-3 " id="36" style=" width: 50px; height: 40px; "
                                    onclick="fun(36)">36</button>
                            </div>
                        </div>
                        <div class="row">
                            <div class=" form-group "></div>
                            <label for="Seat Number">Seat Number:</label>
                            <input type="number" name="seat" class="form-control" id="seat_no"
                                placeholder="Enter Seat Number" required >
                        </div>
                    </div>
                </div>
            </div >
            <div class="form-group">
                <label for="Amount">Total Amount:</label>
                <input type="number" name="amount" class="form-control" id="amount"
                    value="<?php echo $_GET['price']; ?>" placeholder="Enter Amount" required>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-success btn-block" name="check" value="Submit" />
            </div>
            <?php
            if (isset($_POST["check"])) {
                include_once("./db_con.php");
                $qry = "insert into booking (bus, name, contact, city1, city2, seat, price, date, time, status) values('$_POST[bus]','$_POST[unm]','$_POST[num]','$_POST[from]','$_POST[to]','$_POST[seat]','$_POST[amount]','$_POST[date]','$_POST[time]','Confirmed')";
                $resultset = mysqli_query($link, $qry);
                if($resultset){
                    echo "<script>alert('Booking Successful!');</script>";
                    echo "<script>window.location.replace('admin.php');</script>";
                } else {
                    echo "<script>alert('Booking failed: " . mysqli_error($link) . "');</script>";
                }
            } ?>
        </form >
    </div >
    </div >
</section >
</div >
<?php include_once("./adminfooter.php") ?>