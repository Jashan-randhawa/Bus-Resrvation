<!doctype html>
<html lang="en">

<head>
  <!-- Required meta tags -->
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

  <!-- Bootstrap CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css"
    integrity="sha384-xOolHFLEh07PJGoPkLv1IbcEPTNtaed2xpHsD9ESMhqIYd0nLMwNLD69Npy4HI+N" crossorigin="anonymous">
  <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
  <title>Home</title>
  <link rel="stylesheet" href="./project1.css">
  
</head>

<body onload="<?php if(isset($_GET['pnr'])){echo "show_modal()";}?>">
  <div class="container-fluid">
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
      <div>
        <a href="#" class="nav-item nav-logo">Bus Service</a>
        <!-- <a href="#" class="nav-item">Gallery</a> -->
      </div>
      <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#mycollapsediv"
        aria-controls="mycollapsediv" aria-expanded="false" aria-label="toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mycollapsediv">
        <ul class="navbar-nav mr-auto  ml-5 ">
          <li class="nav-item active">
            <a href="#image" data-value="Home" class="nav-link">Home</a>
          </li>
          <li class=" nav-item active">
            <a href="#about" data-value="about" class="nav-link">About Me</a>
          </li>
          <a href=" #contact" data-value="contact" class="nav-link">Contact Us</a>
          </li>
          <li class=" nav-item active">
          <a href="#" data-value="login" data-toggle="modal" data-target="#userlogin" class="nav-link">Login</a>
          </li>
        </ul>
      </div>
  </div>
  </nav>