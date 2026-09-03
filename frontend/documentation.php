<?php
session_start();
?>
<!DOCTYPE html>
<html lang="th">

<head>

<meta charset="UTF-8">

<title>Documentation</title>

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
                Documentation
            </h2>

            <h4>
                วิธีใช้งาน
            </h4>

            <ol>

                <li>
                    เลือกเดือน ที่ต้องการ
                </li>

                <li>
                    เลือกปี ที่ต้องการ
                </li>

                <li>
                    เลือกพื้นที่จังหวัด ที่ต้องการ
                </li>

                <li>
                    กด Predict
                </li>

            </ol>

            <hr>
            
            <h4>
                Regression
            </h4>

            <p>

                ระบบจะแสดงระดับ
                ปริมาณปลาทูในเชิงตัวเลขและกราฟเชิงเส้น

            </p>

            <hr>

            <h4>
                Classification
            </h4>

            <p>

                ระบบจะแสดงระดับ

                LOW,
                MEDIUM,
                HIGH

            </p>

            <hr>

            <h4>
                Clustering
            </h4>

            <p>

                ระบบจะแสดง Heatmap
                การกระจายตัวของปลาทู

            </p>

            <hr>

            <h4>
                Prediction
            </h4>

            <p>

                รวมผลลัพธ์จากทุกโมเดล
                ไว้ในหน้าเดียว

            </p>

        </div>

    </div>

</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>