<?php
session_start();

// Serve graph images from ../model/output through this PHP file.
if (isset($_GET['graph'])) {
    $allowedGraphs = [
        'fish' => 'regression_fish.png',
        'sst' => 'regression_sst.png',
        'chlor' => 'regression_chlor.png',
        'rainfall' => 'regression_rainfall.png',
        'wind' => 'regression_wind.png'
    ];

    $graphKey = (string)$_GET['graph'];
    if (!isset($allowedGraphs[$graphKey])) {
        http_response_code(404);
        exit('Graph not found');
    }

    $outputDirectory = realpath(
        __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR .
        'model' . DIRECTORY_SEPARATOR . 'output'
    );

    $graphPath = realpath(
        __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR .
        'model' . DIRECTORY_SEPARATOR . 'output' . DIRECTORY_SEPARATOR .
        $allowedGraphs[$graphKey]
    );

    if (
        $outputDirectory === false ||
        $graphPath === false ||
        strpos($graphPath, $outputDirectory) !== 0 ||
        !is_file($graphPath)
    ) {
        http_response_code(404);
        exit('Graph file not found');
    }

    header('Content-Type: image/png');
    header('Content-Length: ' . filesize($graphPath));
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    readfile($graphPath);
    exit();
}

function h($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function extract_json($text): ?array
{
    $text = trim((string)$text);
    $decoded = json_decode($text, true);
    if (is_array($decoded)) {
        return $decoded;
    }

    $lines = preg_split('/\R/', $text);
    if (is_array($lines)) {
        for ($index = count($lines) - 1; $index >= 0; $index--) {
            $line = trim($lines[$index]);
            if ($line === '') {
                continue;
            }
            $decoded = json_decode($line, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }

    return null;
}

function metric_value($value, int $decimals = 4, string $suffix = ''): string
{
    if ($value === null || $value === '' || !is_numeric($value)) {
        return '-';
    }

    return number_format((float)$value, $decimals) . $suffix;
}

$result = null;
$error = null;
$rawOutput = null;

$selectedMonth = $_POST['month'] ?? '10';
$selectedYear = $_POST['year'] ?? '2567';
$selectedProvince = $_POST['province'] ?? 'สมุทรปราการ';

$months = [
    1 => 'มกราคม',
    2 => 'กุมภาพันธ์',
    3 => 'มีนาคม',
    4 => 'เมษายน',
    5 => 'พฤษภาคม',
    6 => 'มิถุนายน',
    7 => 'กรกฎาคม',
    8 => 'สิงหาคม',
    9 => 'กันยายน',
    10 => 'ตุลาคม',
    11 => 'พฤศจิกายน',
    12 => 'ธันวาคม'
];

$provinceList = [
    'สมุทรปราการ',
    'สมุทรสาคร',
    'สมุทรสงคราม',
    'เพชรบุรี',
    'ชลบุรี'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $province = trim($_POST['province'] ?? '');
    $year = (int)($_POST['year'] ?? 0);
    $month = (int)($_POST['month'] ?? 0);

    $selectedProvince = $province;
    $selectedYear = (string)$year;
    $selectedMonth = (string)$month;

    if ($province === '' || $year < 2562 || $year > 2570 || $month < 1 || $month > 12) {
        $error = 'กรุณาเลือกจังหวัด ปี (2562-2570) และเดือนให้ถูกต้อง';
    } else {
        $python = 'C:\Users\Admin\AppData\Local\Python\bin\python.exe';
        $script = realpath(
            __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR .
            'model' . DIRECTORY_SEPARATOR . 'Regression_Linear.py'
        );

        if ($script === false || !is_file($script)) {
            $error = 'ไม่พบไฟล์ Regression_Linear.py ในโฟลเดอร์ model';
        } elseif (!function_exists('shell_exec')) {
            $error = 'PHP ปิดการใช้งาน shell_exec()';
        } else {
            $provinceBase64 = base64_encode($province);
            $command =
                escapeshellcmd($python) . ' ' .
                escapeshellarg($script) . ' ' .
                escapeshellarg($provinceBase64) . ' ' .
                escapeshellarg((string)$year) . ' ' .
                escapeshellarg((string)$month) . ' ' .
                escapeshellarg('--b64') .
                ' 2>&1';

            $rawOutput = shell_exec($command);

            if ($rawOutput === null || trim($rawOutput) === '') {
                $error = 'ไม่สามารถเรียก Python ได้ กรุณาตรวจสอบ Python, shell_exec() และ package ที่จำเป็น';
            } else {
                $decoded = extract_json($rawOutput);

                if (!is_array($decoded)) {
                    $error = 'Python ไม่ได้ส่งข้อมูลกลับมาเป็น JSON';
                } elseif (($decoded['status'] ?? '') !== 'success') {
                    $error = $decoded['message'] ?? 'เกิดข้อผิดพลาดจากโมเดล';
                } else {
                    $result = $decoded;
                }
            }
        }
    }
}

$model = $result['model'] ?? [];
$validationMetrics = $model['validation_metrics'] ?? [];
$diagnostics = $model['diagnostics'] ?? [];
$sourceCounts = $result['source_counts'] ?? [];
?>

<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Linear Regression</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <link rel="stylesheet" href="../css/menu.css">
    <link rel="stylesheet" href="../css/data.css">

    <style>
        .metric-card {
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 12px;
            height: 100%;
            padding: 16px;
            background: #fff;
        }
        .metric-value {
            font-size: 1.35rem;
            font-weight: 700;
            margin-bottom: 0;
        }
        .equation-box {
            overflow-wrap: anywhere;
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 10px;
            padding: 14px;
            font-family: Consolas, Monaco, monospace;
        }
    </style>
</head>

<body>
<?php include 'menu.php'; ?>

<div class="container page-container">

    <div class="page-header mb-4">
        <h2>Linear Regression</h2>
        <p class="text-muted mb-0">
            Predict Mackerel Distribution Density
        </p>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header">ตัวกำหนดการคาดการณ์</div>
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <div class="col-md-4 mb-3">
                        <label class="form-label">เดือน</label>
                        <select class="form-select" name="month" required>
                            <?php foreach ($months as $number => $monthName): ?>
                                <option
                                    value="<?= h($number) ?>"
                                    <?= (int)$selectedMonth === (int)$number ? 'selected' : '' ?>
                                >
                                    <?= h($monthName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">ปี (พ.ศ. 2562-2570)</label>
                        <input
                            type="number"
                            class="form-control"
                            name="year"
                            value="<?= h($selectedYear) ?>"
                            min="2562"
                            max="2570"
                            required
                        >
                    </div>

                    <div class="col-md-4 mb-3">
                        <label class="form-label">จังหวัด</label>
                        <select class="form-select" name="province" required>
                            <?php foreach ($provinceList as $provinceName): ?>
                                <option
                                    value="<?= h($provinceName) ?>"
                                    <?= $selectedProvince === $provinceName ? 'selected' : '' ?>
                                >
                                    <?= h($provinceName) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button class="btn btn-primary" type="submit">Predict</button>
            </form>
        </div>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger">
            <strong>Error:</strong> <?= h($error) ?>

            <?php if ($rawOutput): ?>
                <hr>
                <pre class="mb-0 small text-wrap"><?= h($rawOutput) ?></pre>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-primary text-white">
            ผลลัพธ์การคาดการณ์รายเดือน
        </div>

        <div class="card-body">
            <div class="row text-center">
                <div class="col-md-4 mb-3">
                    <h6 class="text-muted">Predicted Mackerel Catch</h6>
                    <h1 class="result-value text-primary">
                        <?= $result ? number_format((float)$result['ton'], 2) : '-' ?>
                    </h1>
                    <p class="text-muted">Ton</p>
                </div>

                <div class="col-md-4 mb-3">
                    <h6 class="text-muted">SST</h6>
                    <h1 class="result-value text-success">
                        <?= $result ? number_format((float)$result['sst'], 2) : '-' ?>
                    </h1>
                    <p class="text-muted">°C</p>
                </div>

                <div class="col-md-4 mb-3">
                    <h6 class="text-muted">Chlorophyll-a</h6>
                    <h1 class="result-value text-warning">
                        <?= $result ? number_format((float)$result['chlor_a'], 2) : '-' ?>
                    </h1>
                    <p class="text-muted">mg/m³</p>
                </div>
            </div>

            <?php if ($result): ?>
                <div class="text-center text-muted">
                    จังหวัด <?= h($result['province']) ?>,
                    ปี <?= h($result['year']) ?>,
                    เดือน <?= h($months[(int)$result['month']] ?? $result['month']) ?>
                </div>

                <?php if (($result['actual_ton'] ?? null) !== null): ?>
                    <div class="text-center mt-2">
                        Actual:
                        <strong><?= number_format((float)$result['actual_ton'], 2) ?> ton</strong>
                        · Difference:
                        <strong><?= number_format(abs((float)$result['ton'] - (float)$result['actual_ton']), 2) ?> ton</strong>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header">กราฟภาพรวมผลลัพธ์คาดการณ์ในแต่ละเดือน</div>
        <div class="card-body text-center">
            <?php if ($result): ?>
                <h4>Mackerel Catch</h4>
                <img
                    src="regression.php?graph=fish&amp;v=<?= time() ?>"
                    class="img-fluid mb-4"
                    alt="Mackerel catch graph"
                >

                <h4>Sea Surface Temperature</h4>
                <img
                    src="regression.php?graph=sst&amp;v=<?= time() ?>"
                    class="img-fluid mb-4"
                    alt="SST graph"
                >

                <h4>Chlorophyll-a</h4>
                <img
                    src="regression.php?graph=chlor&amp;v=<?= time() ?>"
                    class="img-fluid"
                    alt="Chlorophyll-a graph"
                >

                <h4>RainFall</h4>
                <img
                    src="regression.php?graph=rainfall&amp;v=<?= time() ?>"
                    class="img-fluid"
                    alt="RainFall graph"
                >

                <h4>Wind</h4>
                <img
                    src="regression.php?graph=wind&amp;v=<?= time() ?>"
                    class="img-fluid"
                    alt="Wind graph"
                >
            <?php else: ?>
                <div class="graph-placeholder">
                    เลือกเดือน ปี จังหวัด แล้วกด Predict เพื่อแสดงกราฟ
                </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-dark text-white">
            Linear Regression and Error Metrics
        </div>

        <div class="card-body">
            <?php if (!$result): ?>
                <div class="text-muted text-center py-4">
                    กด Predict เพื่อแสดงค่าประเมินโมเดล
                </div>
            <?php else: ?>
                <div class="row g-3 mb-4">
                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="text-muted small">MAE</div>
                            <p class="metric-value">
                                <?= metric_value($validationMetrics['mae'] ?? null, 2, ' ton') ?>
                            </p>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="text-muted small">RMSE</div>
                            <p class="metric-value">
                                <?= metric_value($validationMetrics['rmse'] ?? null, 2, ' ton') ?>
                            </p>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="text-muted small">R²</div>
                            <p class="metric-value">
                                <?= metric_value($validationMetrics['r2'] ?? null, 4) ?>
                            </p>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <div class="metric-card">
                            <div class="text-muted small">Validation Rows</div>
                            <p class="metric-value">
                                <?= h($validationMetrics['rows'] ?? '-') ?>
                            </p>
                        </div>
                    </div>
                </div>

                <div class="alert alert-light border mb-4">
                    <div>
                        <strong>Annual adjustment:</strong>
                        <?= h($model['annual_calibration_label'] ?? '-') ?>
                        <?php if ((float)($model['annual_calibration_weight'] ?? 0) > 0): ?>
                            (<?= number_format((float)$model['annual_calibration_weight'] * 100, 0) ?>%)
                        <?php endif; ?>
                    </div>
                    <div>
                        <strong>Prediction guardrail:</strong>
                        <?= h($model['guardrail_label'] ?? '-') ?>
                    </div>
                    <div class="small text-muted mt-1">
                        ระบบเลือกการปรับเฉพาะแบบที่ลดความคลาดเคลื่อนจาก Walk-forward validation
                        และไม่ทำให้ค่าคลาดเคลื่อนสูงผิดปกติเพิ่มเกินกรอบที่กำหนด
                    </div>
                </div>

                <h6>Regression Equation</h6>
                <div class="equation-box">
                    <?= h($diagnostics['equation'] ?? '-') ?>
                </div>

                <div class="small text-muted mt-3">
                    ข้อมูล SST และ Chlorophyll-a ที่เป็นค่าว่างหรือ 0 จะเติมจากเดือนเดียวกันของปีอื่นในจังหวัดเดียวกัน
                    · SST ที่เติม <?= h($sourceCounts['sst_imputed_months'] ?? 0) ?> เดือน
                    · Chlorophyll-a ที่เติม <?= h($sourceCounts['chlorophyll_a_imputed_months'] ?? 0) ?> เดือน
                </div>

                <div class="small text-muted mt-3">
                    ข้อมูล Rain และ Wind ที่เป็นค่าว่างหรือ 0 จะเติมจากเดือนเดียวกันของปีอื่นในจังหวัดเดียวกัน
                    · Rain ที่เติม <?= h($sourceCounts['rain_imputed_months'] ?? 0) ?> เดือน
                    · Wind ที่เติม <?= h($sourceCounts['wind_a_imputed_months'] ?? 0) ?> เดือน
                </div>
                
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include 'footer.php'; ?>

<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
></script>
</body>
</html>
