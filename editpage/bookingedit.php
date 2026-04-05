<?php include_once("./header1.php"); ?>
<section id="image" style=" height: 1120px;" >
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
                        $qry = "select * from booking where seat='$id'";
                        $resultset = mysqli_query($link, $qry);
                        $row = mysqli_fetch_assoc($resultset);
                    }
                    ?>
                <div class="form-group">
                  <label for="Costumer Name">Costumer Name :</label>
                  <input type="text" name="unm" class="form-control" value="<?php echo $row['name'] ?>" placeholder="Enter Costumer Name" />
                </div>
                <div class="form-group">
                  <label for="Contact Number">Contact Number:</label>
                  <input type="tel" name="num" class="form-control" value="<?php echo $row['contact'] ?>" placeholder="Enter Contact Number" />
                </div>
                <div class="form-group">
                  <label for="From">From :</label>
                  <input type="text" name="from" value="<?php echo $row['city1'] ?>" class="form-control" placeholder="Enter Via City" />
                </div>
                <div class="form-group">
                  <label for="To">To :</label>
                  <input type="text" name="to" value="<?php echo $row['city2'] ?>" class="form-control" placeholder="Costumer destination" />
                </div>
                <div class="form-group">
                  <label for="Select Seat">Select Seat :</label>
                  <div class="mb-3">
                    <div class="container" id="seat">
                      <div class="row mb-3 ">
                        <div class="col-lg-4 col-md-6 col-sm-4 ">
                          <button type="button" class="btn btn-info seat" style=" width: 50px; height: 40px; "
                          id="1" onclick="fun(1)" >1</button>
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; "
                          id="1" onclick="fun(2)" >2</button>
                        </div>
                        <div class="col-lg-6 col-md-4 col-sm-4 ">
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(3)">3</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(4)">4</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(5)">5</button>
                        </div>
                      </div>
                      <div class="row mb-3 ">
                        <div class="col-lg-4 col-md-6 col-sm-4 ">
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; "
                          id="1" onclick="fun(6)">6</button>
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; "
                          id="1" onclick="fun(7)">7</button>
                        </div>
                        <div class="col-lg-6 col-md-4 col-sm-4 ">
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(8)">8</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(9)">9</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(10)">10</button>
                        </div>
                      </div>
                      <div class="row mb-3 ">
                        <div class="col-lg-4 col-md-6 col-sm-4 ">
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; "
                          id="1" onclick="fun(11)">11</button>
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; "
                          id="1" onclick="fun(12)">12</button>
                        </div>
                        <div class="col-lg-6 col-md-4 col-sm-4 ">
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(13)">13</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(14)">14</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(15)">15</button>
                        </div>
                      </div>
                      <div class="row mb-3 ">
                        <div class="col-lg-4 col-md-6 col-sm-4 ">
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; "
                          id="1" onclick="fun(16)">16</button>
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; "
                          id="1" onclick="fun(17)">17</button>
                        </div>
                        <div class="col-lg-6 col-md-4 col-sm-4 ">
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(18)">18</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(19)">19</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(20)">20</button>
                        </div>
                      </div>
                      <div class="row mb-3 ">
                        <div class="col-lg-4 col-md-6 col-sm-4 ">
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; "
                          id="1" onclick="fun(21)">21</button>
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; "
                          id="1" onclick="fun(22")>22</button>
                        </div>
                        <div class="col-lg-6 col-md-4 col-sm-4 ">
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(23)">23</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(24)">24</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(25)">25</button>
                        </div>
                      </div>
                      <div class="row mb-3 ">
                        <div class="col-lg-4 col-md-6 col-sm-4 ">
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; "
                          id="1" onclick="fun(26)">26</button>
                          <button type="button" class="btn btn-info" style=" width: 50px; height: 40px; "
                          id="1" onclick="fun(27)">27</button>
                        </div>
                        <div class="col-lg-6 col-md-4 col-sm-4 ">
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(28)">29</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(29)">28</button>
                          <button class="btn btn-info" style=" width: 50px; height: 40px; " id="1" onclick="fun(30)">30</button>
                        </div>
                      </div>
                      <div class="row  mb-3">
                        <div class="col-lg-10 col-md-6 col-sm-4 " style=" width: 300px; ">
                          <button class="btn btn-info btn-sm ml-1 " id="1" onclick="fun(31)">31</button>
                          <button class="btn btn-info btn-sm ml-1 " id="1" onclick="fun(32)">32</button>
                          <button class="btn btn-info btn-sm ml-1 " id="1" onclick="fun(33)">33</button>
                          <button class="btn btn-info btn-sm ml-1 " id="1" onclick="fun(34)">34</button>
                          <button class="btn btn-info btn-sm ml-1 " id="1" onclick="fun(35)">35</button>
                          <button class="btn btn-info btn-sm ml-1 " id="1" onclick="fun(36)">36</button>
                          <button class="btn btn-info btn-sm ml-1 " id="1" onclick="fun(37)">37</button>
                          <button class="btn btn-info btn-sm " id="1" onclick="fun(38)">38</button>
                        </div>
                      </div>
                      <div class="row">
                        <div class=" form-group "></div>
                        <label for="Seat Number">Seat Number:</label>
                        <input type="number" name="seat_no" class="form-control" id="seat_no"
                          placeholder="Enter Seat Number" value="<?php echo $row['seat'] ?>" onclick="fun()">
                      </div>
                    </div>
                  </div>
                </div>
                <div class="form-group">
                  <label for="Amount">Total Amount:</label>
                  <input type="number" name="amount" value="<?php echo $row['price'] ?>" class="form-control" id="amount" placeholder="Enter Amount"
                    required>
                </div>
                <div class="form-group">
                  <input type="submit" class="btn btn-success btn-block " name="edit" value="Submit" />
                </div>
                <?php
                    if (isset($_POST['edit'])) {
                        include_once("./db_con.php");
                        $qry = "Update booking set name='$_POST[unm]',
                        city1='$_POST[from]',
                        city2='$_POST[to]',
                        contact='$_POST[num]',
                        price='$_POST[amount]' where seat='$id'";
                        $resultset = mysqli_query($link, $qry);
                      echo "<script>alert('Booking Updated Successfully')</script>";
                      echo "<script>window.location.href='booking.php'</script>"; 
                    }
                    ?>
                </form>
            </div>
        </div>
    </div>
</section>
<?php include_once("./footer.php"); ?>