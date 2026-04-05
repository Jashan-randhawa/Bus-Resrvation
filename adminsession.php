<?php
session_start();
if (!isset($_SESSION["name"]) and !isset($_SESSION["pwd"])) {
    header("location:./Homepage.php");
}

if(isset($_POST['logout']))
{
    session_unset();
    session_destroy();
    header("location:./Homepage.php");
}
?>