<?php
session_start();
?>
<!DOCTYPE html>
<html lang="th">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>About</title>

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
                About Project
            </h2>

            <p>
                ระบบคาดการณ์การกระจายตัวของปลาทูในทะเลอ่าวไทยตอนบน
                โดยใช้ปัญญาประดิษฐ์
            </p>

            <hr>

            <h4>วัตถุประสงค์</h4>

            <ul>

                <li>
                    เพื่อพัฒนาระบบคาดการณ์การกระจายตัวของปลาทูในทะเลอ่าวไทยโดยใช้เทคนิคการ
                    เรียนรู้ของเครื่อง (Machine Learning)
                </li>

                <li>
                    เพื่อวิเคราะห์ปัจจัยด้านฤดูกาลและสภาพแวดล้อมทางทะเลที่มีผลต่อปลาทู
                </li>

                <li>
                    เพื่อพัฒนาเว็บแอปพลิเคชันสำหรับแสดงผลการวิเคราะห์และคาดการณ์ในรูปแบบแผนที่
                    และกราฟที่เข้าใจได้ง่าย
                </li>

                <li>
                    เพื่อสนับสนุนการตัดสินใจด้านการบริหารจัดการทรัพยากรทางทะเล
                </li>

            </ul>

            <h4 class="mt-4">
                ข้อมูลที่ใช้
            </h4>

            <ul>

                <li>
                    Sea Surface Temperature (SST)
                </li>

                <li>
                    Chlorophyll-a (Chl-a)
                </li>

                <li>
                    Fishery Catch Dataset
                </li>

            </ul>

            <h4 class="mt-4">
                พื้นที่ศึกษา
            </h4>

            <p>

                กรุงเทพมหานคร,
                สมุทรปราการ,
                สมุทรสาคร,
                สมุทรสงคราม,
                เพชรบุรี,
                ชลบุรี,
                ฉะเชิงเทรา

            </p>

        </div>

    </div>

</div>

<?php include 'footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>