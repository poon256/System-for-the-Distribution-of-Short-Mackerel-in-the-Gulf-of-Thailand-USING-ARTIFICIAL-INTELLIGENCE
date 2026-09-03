<?php
session_start();
?>
<!DOCTYPE html>
<html lang="th">

<head>

<meta charset="UTF-8">

<title>SST Data</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
rel="stylesheet">

<link rel="stylesheet" href="../css/menu.css">
<link rel="stylesheet" href="../css/data.css">

</head>

<body>

<?php include 'menu.php'; ?>

<div class="container page-container">

<h2 class="page-title">
Sea Surface Temperature (SST)
</h2>

<div class="row mb-4">

<div class="col-md-4">

<div class="card summary-card shadow">

<div class="card-body text-center">

<h5>Average SST</h5>

<h2>29.5°C</h2>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card summary-card shadow">

<div class="card-body text-center">

<h5>Maximum SST</h5>

<h2>31.2°C</h2>

</div>

</div>

</div>

<div class="col-md-4">

<div class="card summary-card shadow">

<div class="card-body text-center">

<h5>Minimum SST</h5>

<h2>27.8°C</h2>

</div>

</div>

</div>

</div>

<div class="graph-placeholder shadow mb-4">

Future SST Graph

</div>

<div class="card data-card shadow">

<div class="card-body">

<table class="table table-striped">

<thead>

<tr>

<th>Year</th>
<th>Month</th>
<th>Province</th>
<th>SST</th>

</tr>

</thead>

<tbody>

<tr>

<td>2566</td>
<td>January</td>
<td>สมุทรปราการ</td>
<td>29.6</td>

</tr>

<tr>

<td>2566</td>
<td>January</td>
<td>สมุทรสาคร</td>
<td>29.3</td>

</tr>

</tbody>

</table>

</div>

</div>

</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>