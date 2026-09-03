<?php
session_start();
?>

<!DOCTYPE html>
<html lang="th">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Prediction</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
      rel="stylesheet">

<link rel="stylesheet" href="../css/menu.css">
<link rel="stylesheet" href="../css/data.css">
<link rel="stylesheet" href="../css/footer.css">

</head>

<body>

<?php include 'menu.php'; ?>

<div class="container page-container">

    <div class="page-header mb-4">

        <h2>

            Prediction Dashboard

        </h2>

        <p class="text-muted">

            Integrated AI Prediction System

        </p>

    </div>

    <!-- Input -->

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-header">

            Prediction Parameters

        </div>

        <div class="card-body">

            <div class="row">

                <div class="col-md-4 mb-3">

                    <label class="form-label">

                        Month

                    </label>

                    <select class="form-select">

                        <option>มกราคม</option>
                        <option>กุมภาพันธ์</option>
                        <option>มีนาคม</option>
                        <option>เมษายน</option>
                        <option>พฤษภาคม</option>
                        <option>มิถุนายน</option>
                        <option>กรกฎาคม</option>
                        <option>สิงหาคม</option>
                        <option>กันยายน</option>
                        <option>ตุลาคม</option>
                        <option>พฤศจิกายน</option>
                        <option>ธันวาคม</option>

                    </select>

                </div>

                <div class="col-md-4 mb-3">

                    <label class="form-label">

                        Year

                    </label>

                    <input type="number"
                           class="form-control"
                           value="2567">

                </div>

                <div class="col-md-4 mb-3">

                    <label class="form-label">

                        Province

                    </label>

                    <select class="form-select">

                        <option>กรุงเทพมหานคร</option>
                        <option>สมุทรปราการ</option>
                        <option>สมุทรสาคร</option>
                        <option>สมุทรสงคราม</option>
                        <option>เพชรบุรี</option>
                        <option>ชลบุรี</option>
                        <option>ฉะเชิงเทรา</option>

                    </select>

                </div>

            </div>

            <button class="btn btn-primary">

                Predict

            </button>

        </div>

    </div>

    <!-- Spawning -->

    <div class="spawning-alert">

        ⚠ เดือนที่เลือกอยู่ในช่วงฤดูวางไข่ของปลาทู

    </div>

    <!-- Result -->

    <div class="row mb-4">

        <div class="col-md-4 mb-3">

            <div class="card shadow-sm result-card">

                <div class="card-body">

                    <h5>

                        Regression

                    </h5>

                    <h2 class="text-primary">

                        74.2

                    </h2>

                    <small>

                        Ton

                    </small>

                </div>

            </div>

        </div>

        <div class="col-md-4 mb-3">

            <div class="card shadow-sm result-card">

                <div class="card-body">

                    <h5>

                        Classification

                    </h5>

                    <span class="badge bg-danger fs-5 px-4 py-2">

                        HIGH

                    </span>

                    <small class="mt-2">

                        Density Level

                    </small>

                </div>

            </div>

        </div>

        <div class="col-md-4 mb-3">

            <div class="card shadow-sm result-card">

                <div class="card-body">

                    <h5>

                        Cluster

                    </h5>

                    <h2>

                        1

                    </h2>

                    <small>

                        Cluster Group

                    </small>

                </div>

            </div>

        </div>

    </div>

    <!-- Heatmap -->

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-header">

            Heatmap

        </div>

        <div class="card-body">

            <div class="graph-placeholder">

                Heatmap Visualization

            </div>

        </div>

    </div>

    <!-- Summary -->

    <div class="card shadow-sm border-0">

        <div class="card-header">

            AI Summary

        </div>

        <div class="card-body">

            พื้นที่ที่เลือกมีแนวโน้มพบปลาทูในระดับ

            <strong class="text-danger">

                HIGH

            </strong>

            และอยู่ในกลุ่มพื้นที่ที่มีความหนาแน่นสูง

        </div>

    </div>

</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>