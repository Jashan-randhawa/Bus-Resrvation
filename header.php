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
  <link rel="stylesheet" href="./project.css">
</head>

<body>
  <div class="container-fluid">
    <nav class="navbar navbar-expand-lg navbar-light fixed-top">
      <a href="" class="navbar-brand">Home</a>
      <button type="button" class="navbar-toggler" data-toggle="collapse" data-target="#mycollapsediv"
        aria-controls="mycollapsediv" aria-expanded="false" aria-label="toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="mycollapsediv">
        <ul class="navbar-nav mr-auto mt-2">
          <li class="nav-item active">
            <a href="#about" data-value="about" class="nav-link">About Me</a>
          </li>
          <li class="nav-item dropdown">
            <a href="#" class="nav-link dropdown-toggle" data-toggle="dropdown">Our Services</a>
            <div class="dropdown-menu" id="">
              <a href="https://ifacet.ilearn.edusquares.com/dashboard" class="dropdown-item">Web Development</a>
              <a href="#" class="dropdown-item">App Development</a>
              <a href="#" class="dropdown-item">Testing</a>
              <a href="#" class="dropdown-item">AWS</a>
              <a href="#" class="dropdown-item">Data Science</a>
              <a href="#" class="dropdown-item">Artificial Intelligence</a>
            </div>
          </li>
          <li class="nav-item active">
            <a href="#team" data-value="team" class="nav-link">Our Team</a>
          </li>
          <li class="nav-item active">
            <a href="#blog" data-value="blog" class="nav-link">Blogs</a>
          </li>
          <li class="nav-item active">
            <a href="#contact" data-value="contact" class="nav-link">Contact Us</a>
          </li>
          <li class="nav-item active">
            <button type="button" class="btn" id="btn" data-toggle="modal" data-target="#mymodal">
              Login/Register
            </button>
          </li>
          <form class="form-inline my-2 my-lg-0 " method="post" style="position:fixed; left: 940px; ">
            <input class="form-control mr-sm-2" type="search" name="ctrl_srh" id="ctrl_nm" placeholder="Search By Name"
              aria-label="Search">
            <button class="btn btn-success my-2 my-sm-0" name="searchbtn" type="submit">Search</button>
          </form>
        </ul>
    </nav>