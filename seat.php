<?php include_once("./adminsession.php") ?>
<?php include_once("./adminhead.php") ?>
<div class=" col-lg-10 col-md-10 col-sm-6 col-xs-12 " style=" float: right;  ">
    <div class="card col-lg-4 col-md-6 col-sm-12 col-xs-12 ">
        <div class=" card-body ">
            <form action="" method="post">
                    <div class=" form-group col-lg-12" style="margin: auto ; ">
                        <select name="bus" id="bus" style=" width: 250px; " required>
                            <option value="">Select Bus Number</option>
                            <?php
                            include_once("./db_con.php");
                            $qry = "SELECT bus_number FROM buses";
                            $resultset = mysqli_query($link, $qry);
                            while ($row = mysqli_fetch_assoc($resultset)) {
                                ?>
                                <option value="<?php echo $row['bus_number']; ?>"><?php echo $row['bus_number']; ?></option>
                                <?php
                            }
                            ?>
                        </select>
                    </div>
                    <div class=" form-group col-lg-12 mt-3" style="margin: auto ; ">
                    <input type="date" id="date" name="date" style=" width: 250px; " required>
                    </div>
                    <div class=" form-group col-lg-12 mt-3 " style="margin: auto ; ">
                        <input type="submit" name="submit" value="Submit" class="btn btn-block btn-primary col-lg-12">
                    </div>
            </form>
        </div>
    </div>
    <section>
        <?php
        if (isset($_POST['submit'])) {
            include_once("./db_con.php");
            $qry = "select seat from booking where date='$_POST[date]' and  bus='$_POST[bus]'";
            $resultset = mysqli_query($link, $qry);
            $row = mysqli_num_rows($resultset);
            while ($val = mysqli_fetch_assoc($resultset)) {
                ?>
                <script>
                    function fun<?php echo $val['seat']; ?>() {
                        document.getElementById("<?php echo $val['seat']; ?>").style.background = "red";
                        document.getElementById("<?php echo $val['seat']; ?>").click();
                    }
                </script>
                <?php
            } ?>
            <script> function call(){
            <?php include_once("./db_con.php");
            $qry = "select seat from booking where date='$_POST[date]' and   bus='$_POST[bus]'";
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
        <div class=" container col-lg-6 mt-5">
            <div class="row mb-3 ">
                <div class="col-lg-4 col-md-6 col-sm-4 ">
                    <button type="button" class="btn btn-info seat" style=" width: 50px; height: 40px; "
                        id="1">1</button>
                    <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="2">2</button>
                </div>
                <div class="col-lg-6 col-md-4 col-sm-4 ">
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="3">3</button>
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="4">4</button>
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="5">5</button>
                </div>
            </div>
            <div class="row mb-3 ">
                <div class="col-lg-4 col-md-6 col-sm-4 ">
                    <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="6"
                        onclick="fun6()">6</button>
                    <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="7"
                        onclick="fun7()">7</button>
                </div>
                <div class="col-lg-6 col-md-4 col-sm-4 ">
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="8">8</button>
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="9">9</button>
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="10">10</button>
                </div>
            </div>
            <div class="row mb-3 ">
                <div class="col-lg-4 col-md-6 col-sm-4 ">
                    <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="11">11</button>
                    <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="12">12</button>
                </div>
                <div class="col-lg-6 col-md-4 col-sm-4 ">
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="13">13</button>
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="14">14</button>
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="15">15</button>
                </div>
            </div>
            <div class="row mb-3 ">
                <div class="col-lg-4 col-md-6 col-sm-4 ">
                    <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="16">16</button>
                    <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="17">17</button>
                </div>
                <div class="col-lg-6 col-md-4 col-sm-4 ">
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="18">18</button>
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="19">19</button>
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="20">20</button>
                </div>
            </div>
            <div class="row mb-3 ">
                <div class="col-lg-4 col-md-6 col-sm-4 ">
                    <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="21">21</button>
                    <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="22">22</button>
                </div>
                <div class="col-lg-6 col-md-4 col-sm-4 ">
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="23">23</button>
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="24">24</button>
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="25">25</button>
                </div>
            </div>
            <div class="row mb-3 ">
                <div class="col-lg-4 col-md-6 col-sm-4 ">
                    <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="26">26</button>
                    <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; " id="27">27</button>
                </div>
                <div class="col-lg-6 col-md-4 col-sm-4 ">
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="28">28</button>
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="29">29</button>
                    <button class="btn btn-info" style=" width: 50px; height: 40px; " id="30">30</button>
                </div>
            </div>
            <div class="row  mb-3">
                <div class="col-lg-10 col-md-6 col-sm-4 " style=" width: 300px; ">
                    <button class="btn btn-info btn-sm " id="31" style=" width: 50px; height: 40px; ">31</button>
                    <button class="btn btn-info btn-sm ml-1 " id="32" style=" width: 50px; height: 40px; ">32</button>
                    <button class="btn btn-info btn-sm ml-1 " id="33" style=" width: 50px; height: 40px; ">33</button>
                    <button class="btn btn-info btn-sm ml-1 " id="34" style=" width: 50px; height: 40px; ">34</button>
                    <button class="btn btn-info btn-sm " id="35" style=" width: 50px; height: 40px; ">35</button>
                    <button class="btn btn-info btn-sm " id="36" style=" width: 50px; height: 40px; ">36</button>
                </div>
            </div>
        </div>
    </section>
  
</div>
<?php include_once("./adminfooter.php") ?>