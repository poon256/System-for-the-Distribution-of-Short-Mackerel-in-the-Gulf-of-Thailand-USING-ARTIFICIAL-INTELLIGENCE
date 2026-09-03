<?php
session_start();
?>
<!DOCTYPE html>
<html lang="th">

<head>

<meta charset="UTF-8">

<title>Profile</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<link rel="stylesheet" href="../css/menu.css">
<link rel="stylesheet" href="../css/info.css">

</head>

<body>

<?php include 'menu.php'; ?>

<div class="info-container">

<div class="container">

<div class="info-card">

<h2 class="page-title">
Team Members
</h2>

<div class="row text-center">

<div class="col-md-4">

<img src="img/profile1.jpg"
     class="profile-img">

<h4 class="mt-3">
นายปัญญากร เขียวชู
</h4>

<p>
Class: Paladin
</p>

</div>

<div class="col-md-4">

<img src="img/profile2.jpg"
     class="profile-img">

<h4 class="mt-3">
นายธีรภัทร พอกแก้ว
</h4>

<p>
Class: Dancer
</p>

</div>

<div class="col-md-4">

<img src="img/profile3.jpg"
     class="profile-img">

<h4 class="mt-3">
นายสัตยา พอกแก้ว
</h4>

<p>
Class: White Mage
</p>

</div>

</div>

</div>

</div>

</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>