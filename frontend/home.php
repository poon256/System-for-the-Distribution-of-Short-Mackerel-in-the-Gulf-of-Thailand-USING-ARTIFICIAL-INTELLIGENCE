<?php
session_start();
?>

<!DOCTYPE html>
<html lang="th">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Mackerel Engine</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<link rel="stylesheet" href="../css/menu.css">
<link rel="stylesheet" href="../css/theme.css">
<link rel="stylesheet" href="../css/footer.css">

</head>

<body>

<?php include 'menu.php'; ?>

<div class="hero-section">

    <div class="container">

        <div class="hero-box">

            <h1 class="mb-3">

                ระบบคาดการณ์การกระจายตัวของปลาทูในทะเลอ่าวไทย
                <br>
                โดยใช้ปัญญาประดิษฐ์

            </h1>

            <p class="lead mb-5">

                AI-based Mackerel Distribution Forecasting System

            </p>

            <!-- Dashboard -->

            <div class="row g-4 mb-4">

                <div class="col-md-3">

                    <div class="card dashboard-card">

                        <div class="card-body">

                            <h5>Dataset</h5>

                            <h2>6</h2>

                            <p>Years</p>

                        </div>

                    </div>

                </div>

                <div class="col-md-3">

                    <div class="card dashboard-card">

                        <div class="card-body">

                            <h5>Province</h5>

                            <h2>5</h2>

                            <p>Areas</p>

                        </div>

                    </div>

                </div>

                <div class="col-md-3">

                    <div class="card dashboard-card">

                        <div class="card-body">

                            <h5>Models</h5>

                            <h2>3</h2>

                            <p>AI Models</p>

                        </div>

                    </div>

                </div>

                <div class="col-md-3">

                    <div class="card dashboard-card">

                        <div class="card-body">

                            <h5>Training</h5>

                            <h2>2562-2566</h2>

                            <p>Dataset Period</p>

                        </div>

                    </div>

                </div>

            </div>

            <!-- Spawning -->

            <div class="spawning-card">

                <h5>

                    ⚠ ฤดูวางไข่ของปลาทู

                </h5>

                <p>

                    เดือนกุมภาพันธ์ - พฤษภาคม
                    เป็นช่วงฤดูวางไข่ของปลาทูในอ่าวไทยตอนบน

                </p>

            </div>

            <!-- Button -->

            <div class="mt-4">

                <a href="prediction.php"
                   class="btn btn-primary btn-lg px-4">

                    Start Prediction

                </a>

            </div>

        </div>

    </div>

</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>