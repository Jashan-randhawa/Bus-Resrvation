<?php include_once("header1.php"); ?>
<section id="image">
    <div class="overlay">
        <div class="description">
            <form action="Homepage.php" method="post">
                <div class="card col-lg-6 col-md-6 col-sm-12 col-xs-12" style=" margin: auto ; ">
                    <div class="card-header">
                        <h1 class="text-center">Login</h1>
                        <p class="text-center">If you are registered user.Please enter your given email id and password.</p>
                    </div>
                    <div class=" card-body">
                        <div class="mb-4">
                            <label for="Email" class="form-label"> Email Id : </label>
                            <input type="email" name="email" placeholder="Enter your Email Id" class="form-control"
                                required>
                        </div>
                        <div class="mb-4">
                            <label for="Password" class="form-label"> Password : </label>
                            <input type="password" name="password" placeholder="Enter your password"
                                class="form-control" required>
                        </div>
                        <div class="mb-4">
                            <input type="submit" value="Login" name="loginbtn" class="btn btn-primary btn-block">
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
    </div>
    <?php include_once("footer.php"); ?>