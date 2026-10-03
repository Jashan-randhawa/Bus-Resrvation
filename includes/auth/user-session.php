<?php
require_once __DIR__ . '/../config.php';
session_start();
if (!isset($_SESSION["name"]) and !isset($_SESSION["pwd"])) {
    header("location: " . BASE_URL . "/homepage.php");
    exit();
}

if(isset($_POST['logout']))
{
    session_unset();
    session_destroy();
    header("location: " . BASE_URL . "/homepage.php");
    exit();
}
?>