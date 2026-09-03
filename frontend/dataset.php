<?php
session_start();

require_once __DIR__ . '/../config/class.connect.php';

$db = new connect();

$rows = [];
$errorMessage = '';

$recordsPerPage = 50;

// =====================================
// รับค่าค้นหา
// =====================================
$search = isset($_GET['search'])
    ? trim($_GET['search'])
    : '';

// =====================================
// รับหมายเลขหน้า
// =====================================
$currentPage = isset($_GET['page'])
    ? (int) $_GET['page']
    : 1;

if ($currentPage < 1) {
    $currentPage = 1;
}

$offset = ($currentPage - 1) * $recordsPerPage;

// =====================================
// ค่าเริ่มต้น
// =====================================
$totalRecords = 0;
$totalProvinces = 0;
$minimumYear = null;
$maximumYear = null;
$totalPages = 1;

// =====================================
// ชื่อเดือนสำหรับแสดงผล
// =====================================
$monthNames = [
    1  => 'มกราคม',
    2  => 'กุมภาพันธ์',
    3  => 'มีนาคม',
    4  => 'เมษายน',
    5  => 'พฤษภาคม',
    6  => 'มิถุนายน',
    7  => 'กรกฎาคม',
    8  => 'สิงหาคม',
    9  => 'กันยายน',
    10 => 'ตุลาคม',
    11 => 'พฤศจิกายน',
    12 => 'ธันวาคม'
];

// =====================================
// ชื่อเดือนสำหรับค้นหา
// =====================================
$monthSearchMap = [
    // ภาษาไทย
    'มกราคม' => 1,
    'กุมภาพันธ์' => 2,
    'มีนาคม' => 3,
    'เมษายน' => 4,
    'พฤษภาคม' => 5,
    'มิถุนายน' => 6,
    'กรกฎาคม' => 7,
    'สิงหาคม' => 8,
    'กันยายน' => 9,
    'ตุลาคม' => 10,
    'พฤศจิกายน' => 11,
    'ธันวาคม' => 12,

    // ภาษาอังกฤษ
    'january' => 1,
    'february' => 2,
    'march' => 3,
    'april' => 4,
    'may' => 5,
    'june' => 6,
    'july' => 7,
    'august' => 8,
    'september' => 9,
    'october' => 10,
    'november' => 11,
    'december' => 12,

    // ภาษาอังกฤษแบบย่อ
    'jan' => 1,
    'feb' => 2,
    'mar' => 3,
    'apr' => 4,
    'jun' => 6,
    'jul' => 7,
    'aug' => 8,
    'sep' => 9,
    'sept' => 9,
    'oct' => 10,
    'nov' => 11,
    'dec' => 12
];

try {
    // =====================================
    // เชื่อมต่อฐานข้อมูล
    // =====================================
    $conn = $db->conn();

    $conn->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    // =====================================
    // เงื่อนไขค้นหา
    // =====================================
    $whereSql = '';
    $parameters = [];

    if ($search !== '') {
        $normalizedSearch = mb_strtolower(
            trim($search),
            'UTF-8'
        );

        $searchedMonth =
            $monthSearchMap[$normalizedSearch] ?? null;

        if ($searchedMonth !== null) {
            // กรณีค้นหาด้วยชื่อเดือน
            $whereSql = "
                WHERE cmd.month = :searched_month
            ";

            $parameters[':searched_month'] =
                $searchedMonth;
        } else {
            // กรณีค้นหาคอลัมน์ทั่วไป
            $whereSql = "
                WHERE
                    CAST(cmd.id AS CHAR) LIKE :search
                    OR s.station_name LIKE :search
                    OR CAST(cmd.year AS CHAR) LIKE :search
                    OR CAST(cmd.month AS CHAR) LIKE :search
                    OR CAST(cmd.amount AS CHAR) LIKE :search
                    OR cmd.unit LIKE :search
                    OR e.name LIKE :search
                    OR CAST(cmd.status AS CHAR) LIKE :search
            ";

            $parameters[':search'] =
                '%' . $search . '%';
        }
    }

    // =====================================
    // นับจำนวนข้อมูลตามผลค้นหา
    // =====================================
    $countSql = "
        SELECT COUNT(*) AS total_records
        FROM catch_mackereldata AS cmd

        LEFT JOIN station AS s
            ON s.id = cmd.station_id

        LEFT JOIN equipment AS e
            ON e.id = cmd.equipment_id

        $whereSql
    ";

    $countStatement = $conn->prepare($countSql);

    foreach ($parameters as $key => $value) {
        $parameterType =
            $key === ':searched_month'
                ? PDO::PARAM_INT
                : PDO::PARAM_STR;

        $countStatement->bindValue(
            $key,
            $value,
            $parameterType
        );
    }

    $countStatement->execute();

    $countResult = $countStatement->fetch(
        PDO::FETCH_ASSOC
    );

    $totalRecords = (int) (
        $countResult['total_records'] ?? 0
    );

    // =====================================
    // คำนวณจำนวนหน้า
    // =====================================
    $totalPages = max(
        1,
        (int) ceil(
            $totalRecords / $recordsPerPage
        )
    );

    if ($currentPage > $totalPages) {
        $currentPage = $totalPages;
        $offset =
            ($currentPage - 1) * $recordsPerPage;
    }

    // =====================================
    // นับจำนวนจังหวัดทั้งหมด
    // =====================================
    $provinceSql = "
        SELECT
            COUNT(DISTINCT cmd.station_id)
            AS total_provinces
        FROM catch_mackereldata AS cmd

        INNER JOIN station AS s
            ON s.id = cmd.station_id
    ";

    $provinceStatement =
        $conn->prepare($provinceSql);

    $provinceStatement->execute();

    $provinceResult =
        $provinceStatement->fetch(
            PDO::FETCH_ASSOC
        );

    $totalProvinces = (int) (
        $provinceResult['total_provinces'] ?? 0
    );

    // =====================================
    // หาปีต่ำสุดและสูงสุด
    // ปีในฐานข้อมูลเป็น พ.ศ.
    // =====================================
    $yearSql = "
        SELECT
            MIN(year) AS minimum_year,
            MAX(year) AS maximum_year
        FROM catch_mackereldata
    ";

    $yearStatement = $conn->prepare($yearSql);
    $yearStatement->execute();

    $yearResult = $yearStatement->fetch(
        PDO::FETCH_ASSOC
    );

    if (
        $yearResult &&
        $yearResult['minimum_year'] !== null &&
        $yearResult['maximum_year'] !== null
    ) {
        $minimumYear =
            (int) $yearResult['minimum_year'];

        $maximumYear =
            (int) $yearResult['maximum_year'];
    }

    // =====================================
    // ดึงข้อมูลจากฐานข้อมูล
    // เรียงตาม ID เหมือนใน Database
    // =====================================
    $dataSql = "
        SELECT
            cmd.id,
            s.station_name AS province,
            cmd.year,
            cmd.month,
            cmd.amount,
            cmd.unit,
            e.name AS equipment_name,
            cmd.status

        FROM catch_mackereldata AS cmd

        LEFT JOIN station AS s
            ON s.id = cmd.station_id

        LEFT JOIN equipment AS e
            ON e.id = cmd.equipment_id

        $whereSql

        ORDER BY cmd.id ASC

        LIMIT :records_per_page
        OFFSET :record_offset
    ";

    $dataStatement = $conn->prepare($dataSql);

    foreach ($parameters as $key => $value) {
        $parameterType =
            $key === ':searched_month'
                ? PDO::PARAM_INT
                : PDO::PARAM_STR;

        $dataStatement->bindValue(
            $key,
            $value,
            $parameterType
        );
    }

    $dataStatement->bindValue(
        ':records_per_page',
        $recordsPerPage,
        PDO::PARAM_INT
    );

    $dataStatement->bindValue(
        ':record_offset',
        $offset,
        PDO::PARAM_INT
    );

    $dataStatement->execute();

    $rows = $dataStatement->fetchAll(
        PDO::FETCH_ASSOC
    );

} catch (PDOException $exception) {
    $errorMessage =
        'Database error: ' .
        $exception->getMessage();

} catch (Throwable $exception) {
    $errorMessage =
        'System error: ' .
        $exception->getMessage();
}

// =====================================
// สร้าง URL สำหรับ Pagination
// =====================================
function buildPageUrl(
    int $page,
    string $search
): string {
    return '?' . http_build_query([
        'search' => $search,
        'page' => $page
    ]);
}
?>

<!DOCTYPE html>
<html lang="th">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Mackerel Dataset</title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="../css/menu.css"
    >

    <link
        rel="stylesheet"
        href="../css/data.css"
    >

</head>

<body>

<?php include 'menu.php'; ?>

<div class="container page-container">

    <!-- =====================================
         Header
    ====================================== -->

    <div class="mb-4">

        <h2 class="page-title mb-1">
            ข้อมูลจำนวนปลาทู
        </h2>

    </div>

    <!-- =====================================
         Summary Cards
    ====================================== -->

    <div class="row mb-4">

        <!-- จำนวนข้อมูล -->

        <div class="col-md-4 mb-3">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body text-center">

                    <h6 class="text-muted">
                        ข้อมูลผลลัพธ์ทั้งหมด
                    </h6>

                    <h2 class="text-primary mb-0">

                        <?= number_format(
                            $totalRecords
                        ) ?>

                    </h2>

                </div>

            </div>

        </div>

        <!-- จำนวนจังหวัด -->

        <div class="col-md-4 mb-3">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body text-center">

                    <h6 class="text-muted">
                        จังหวัดทั้งหมด
                    </h6>

                    <h2 class="text-primary mb-0">

                        <?= number_format(
                            $totalProvinces
                        ) ?>

                    </h2>

                </div>

            </div>

        </div>

        <!-- ช่วงปี -->

        <div class="col-md-4 mb-3">

            <div class="card shadow-sm border-0 h-100">

                <div class="card-body text-center">

                    <h6 class="text-muted">
                        ช่วงปี
                    </h6>

                    <h2 class="text-primary mb-0">

                        <?php if (
                            $minimumYear !== null &&
                            $maximumYear !== null
                        ): ?>

                            <?= htmlspecialchars(
                                $minimumYear .
                                ' - ' .
                                $maximumYear,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        <?php else: ?>

                            -

                        <?php endif; ?>

                    </h2>

                </div>

            </div>

        </div>

    </div>

    <!-- =====================================
         Error
    ====================================== -->

    <?php if ($errorMessage !== ''): ?>

        <div class="alert alert-danger">

            <?= htmlspecialchars(
                $errorMessage,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

        </div>

    <?php endif; ?>

    <!-- =====================================
         Search Form
    ====================================== -->

    <div class="card shadow-sm border-0 mb-4">

        <div class="card-body">

            <form
                method="get"
                action="dataset.php"
                class="row g-2"
            >

                <div class="col-md-9">

                    <input
                        type="text"
                        name="search"
                        class="form-control"
                        placeholder="Search province, year, month, amount or equipment..."
                        value="<?= htmlspecialchars(
                            $search,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>

                <div class="col-md-3 d-flex gap-2">

                    <button
                        type="submit"
                        class="btn btn-primary flex-fill"
                    >
                        Search
                    </button>

                    <a
                        href="dataset.php"
                        class="btn btn-secondary"
                    >
                        Clear
                    </a>

                </div>

            </form>

        </div>

    </div>

    <!-- =====================================
         Dataset Table
    ====================================== -->

    <div class="card shadow-sm border-0">

        <div class="card-body">

            <div
                class="d-flex justify-content-between
                       align-items-center flex-wrap mb-3"
            >

                <h5 class="mb-0">
                    Catch Mackerel Data
                </h5>

                <span class="text-muted">

                    Page
                    <?= number_format($currentPage) ?>

                    of

                    <?= number_format($totalPages) ?>

                </span>

            </div>

            <div class="table-responsive">

                <table
                    class="table table-striped table-hover
                           table-bordered align-middle"
                >

                    <thead class="table-light">

                        <tr>

                            <th>ID</th>

                            <th>Province</th>

                            <th>Year</th>

                            <th>Month</th>

                            <th class="text-end">
                                Amount
                            </th>

                            <th>Unit</th>

                            <th>Equipment</th>

                            <th>Status</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (!empty($rows)): ?>

                        <?php foreach ($rows as $row): ?>

                            <?php
                            $monthNumber =
                                (int) $row['month'];

                            $monthName =
                                $monthNames[$monthNumber]
                                ?? (string) $monthNumber;
                            ?>

                            <tr>

                                <!-- ID -->

                                <td>
                                    <?= (int) $row['id'] ?>
                                </td>

                                <!-- Province -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['province'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>

                                <!-- Year -->

                                <td>
                                    <?= (int) $row['year'] ?>
                                </td>

                                <!-- Month -->

                                <td>

                                    <?= htmlspecialchars(
                                        $monthName,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>

                                <!-- Amount -->

                                <td class="text-end">

                                    <?= number_format(
                                        (float) $row['amount'],
                                        2
                                    ) ?>

                                </td>

                                <!-- Unit -->

                                <td>

                                    <?= htmlspecialchars(
                                        $row['unit'] ?? '-',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>

                                <!-- Equipment -->

                                <td>

                                    <?= htmlspecialchars(
                                        trim(
                                            $row['equipment_name']
                                            ?? '-'
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </td>

                                <!-- Status -->

                                <td>

                                    <?php if (
                                        (int) $row['status'] === 1
                                    ): ?>

                                        <span class="badge bg-success">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="badge bg-secondary">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>

                            <td
                                colspan="8"
                                class="text-center text-muted py-4"
                            >
                                No data found.
                            </td>

                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <!-- =====================================
                 Pagination
            ====================================== -->

            <?php if ($totalPages > 1): ?>

                <?php
                $startPage = max(
                    1,
                    $currentPage - 2
                );

                $endPage = min(
                    $totalPages,
                    $currentPage + 2
                );
                ?>

                <nav
                    class="mt-4"
                    aria-label="Dataset pagination"
                >

                    <ul
                        class="pagination
                               justify-content-center
                               flex-wrap"
                    >

                        <!-- Previous -->

                        <li
                            class="page-item
                            <?= $currentPage <= 1
                                ? 'disabled'
                                : '' ?>"
                        >

                            <a
                                class="page-link"
                                href="<?= $currentPage > 1
                                    ? htmlspecialchars(
                                        buildPageUrl(
                                            $currentPage - 1,
                                            $search
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                    : '#' ?>"
                            >
                                Previous
                            </a>

                        </li>

                        <!-- First page -->

                        <?php if ($startPage > 1): ?>

                            <li class="page-item">

                                <a
                                    class="page-link"
                                    href="<?= htmlspecialchars(
                                        buildPageUrl(
                                            1,
                                            $search
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >
                                    1
                                </a>

                            </li>

                            <?php if ($startPage > 2): ?>

                                <li class="page-item disabled">

                                    <span class="page-link">
                                        ...
                                    </span>

                                </li>

                            <?php endif; ?>

                        <?php endif; ?>

                        <!-- หมายเลขหน้า -->

                        <?php for (
                            $pageNumber = $startPage;
                            $pageNumber <= $endPage;
                            $pageNumber++
                        ): ?>

                            <li
                                class="page-item
                                <?= $pageNumber === $currentPage
                                    ? 'active'
                                    : '' ?>"
                            >

                                <a
                                    class="page-link"
                                    href="<?= htmlspecialchars(
                                        buildPageUrl(
                                            $pageNumber,
                                            $search
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                                    <?= $pageNumber ?>

                                </a>

                            </li>

                        <?php endfor; ?>

                        <!-- Last page -->

                        <?php if ($endPage < $totalPages): ?>

                            <?php if (
                                $endPage <
                                $totalPages - 1
                            ): ?>

                                <li class="page-item disabled">

                                    <span class="page-link">
                                        ...
                                    </span>

                                </li>

                            <?php endif; ?>

                            <li class="page-item">

                                <a
                                    class="page-link"
                                    href="<?= htmlspecialchars(
                                        buildPageUrl(
                                            $totalPages,
                                            $search
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                >

                                    <?= $totalPages ?>

                                </a>

                            </li>

                        <?php endif; ?>

                        <!-- Next -->

                        <li
                            class="page-item
                            <?= $currentPage >= $totalPages
                                ? 'disabled'
                                : '' ?>"
                        >

                            <a
                                class="page-link"
                                href="<?= $currentPage < $totalPages
                                    ? htmlspecialchars(
                                        buildPageUrl(
                                            $currentPage + 1,
                                            $search
                                        ),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                    : '#' ?>"
                            >
                                Next
                            </a>

                        </li>

                    </ul>

                </nav>

            <?php endif; ?>

            <!-- =====================================
                 จำนวนรายการที่กำลังแสดง
            ====================================== -->

            <?php if ($totalRecords > 0): ?>

                <?php
                $firstRecord = $offset + 1;

                $lastRecord = min(
                    $offset + $recordsPerPage,
                    $totalRecords
                );
                ?>

                <p class="text-center text-muted mb-0">

                    Showing

                    <?= number_format($firstRecord) ?>

                    to

                    <?= number_format($lastRecord) ?>

                    of

                    <?= number_format($totalRecords) ?>

                    records

                </p>

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