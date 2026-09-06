<?php
session_start();
?>

<!DOCTYPE html>
<html lang="th">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Clustering Model</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="../css/menu.css">
<link rel="stylesheet" href="../css/k_mean.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<style>
    #map { height: 500px; width: 100%; }
    .legend {
        padding: 6px 10px;
        font: 14px/16px Arial, Helvetica, sans-serif;
        background: white;
        background: rgba(255,255,255,0.9);
        box-shadow: 0 0 15px rgba(0,0,0,0.2);
        border-radius: 5px;
        line-height: 24px;
    }
    .legend i {
        width: 14px;
        height: 14px;
        float: left;
        margin-right: 8px;
        opacity: 0.8;
        border-radius: 50%;
        margin-top: 5px;
    }
    .navbar, header, #menu-container { 
    position: relative !important;
    z-index: 9999 !important; 
}

</style>
</head>

<body>

<?php include 'menu.php'; ?>

<div class="container page-container mt-4">

    <div class="page-header mb-4">
        <h2>K-Means Clustering</h2>
        <p class="text-muted">Spatial Distribution Analysis</p>
    </div>

    <!-- Input -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header">Clustering Parameters</div>
        <div class="card-body">
            <form method="post">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Month</label>
                        <select class="form-select" name="month" required>
                            <option value="">เลือกเดือน</option>
                            <?php
                            $months = [
                                1 => "มกราคม", 2 => "กุมภาพันธ์", 3 => "มีนาคม", 4 => "เมษายน", 
                                5 => "พฤษภาคม", 6 => "มิถุนายน", 7 => "กรกฎาคม", 8 => "สิงหาคม", 
                                9 => "กันยายน", 10 => "ตุลาคม", 11 => "พฤศจิกายน", 12 => "ธันวาคม"
                            ];
                            foreach($months as $num => $name){
                                $selected = (isset($_POST['month']) && $_POST['month'] == $num) ? "selected" : "";
                                echo "<option value='$num' $selected>$name</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Year</label>
                        <select class="form-select" name="year" required>
                            <option value="">เลือกปี</option>
                            <?php
                            $conn = new mysqli("127.0.0.1","root","","projecta");
                            $result = $conn->query("select distinct year from dataset_ml order by year asc");
                            while($row = $result->fetch_assoc()){
                                $selected = (isset($_POST['year']) && $_POST['year'] == $row['year']) ? "selected" : "";
                                echo "<option value='".$row['year']."' $selected>".$row['year']."</option>";
                            }
                            $conn->close();
                            ?>
                        </select>
                    </div>
                </div>
                <button type="submit" name="run" class="btn btn-info text-white">Generate Heatmap</button>
            </form>
        </div>
    </div>

    <!-- Heatmap -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-header bg-info text-white">Heatmap Visualization</div>
        <div class="card-body">
            <div id="map"></div>
        </div>
    </div>

    <?php
    $conn=new mysqli("127.0.0.1",
     "root", "", "projecta");

    $month = isset($_POST["month"]) ? intval($_POST["month"]) : 0;
    $year  = isset($_POST["year"]) ? intval($_POST["year"]) : 0;

    $data=[];

    if($month>0 && $year>0){
        $sql="
        select
        s.station_name,
        s.latitude,
        s.longitude,
        d.station_id,
        d.year,
        d.month,
        sum(d.amount) as amount,
        round(AVG(d.cluster)) as cluster
        from dataset_ml d
        join station s on d.station_id=s.id
        where d.year=$year
        and d.month=$month
        group by
        s.station_name,
        s.latitude,
        s.longitude,
        d.station_id,
        d.year,
        d.month;
        ";
        
        $result=$conn->query($sql);
        while($row=$result->fetch_assoc()){
            if (floatval($row['latitude']) != 0 && floatval($row['amount']) > 0) {
                $data[]=$row;
            }
        }
    } else {
        $sql="select s.station_name, s.latitude, s.longitude, d.station_id, d.year, d.month, d.amount, d.cluster
        from dataset_ml d join station s on d.station_id=s.id where 1=0";
        $result=$conn->query($sql);
        while($row=$result->fetch_assoc()){
            $data[]=$row;
        }
    }
    $conn->close();
    ?>

    <!-- Cluster Summary -->
    <div class="card shadow-sm border-0 mb-5">
        <div class="card-header">Cluster Summary</div>
        <div class="card-body">
            <table class="table table-bordered table-striped align-middle">
                <thead>
                    <tr>
                        <th>Province</th>
                        <th>Cluster</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(!empty($data)): ?>
                        <?php foreach($data as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['station_name']); ?></td>
                                <td>
                                    <?php 
                                    if($item['cluster'] == 0) echo '<span class="badge bg-danger">Cluster 0 (วิกฤต/ปลาน้อย)</span>';
                                    elseif($item['cluster'] == 1) echo '<span class="badge bg-warning text-dark">Cluster 1 (ปานกลาง)</span>';
                                    else echo '<span class="badge bg-primary">Cluster 2 (ปลาชุกชุม)</span>';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="2" class="text-center text-muted">กรุณาเลือกเดือนและปีเพื่อแสดงผลข้อมูล</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

<script>
var map=L.map('map').setView([13.25,100.15],8);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { 
    maxZoom:18 
}).addTo(map); 
 
var data = <?php echo json_encode($data, JSON_PRETTY_PRINT); ?>; 
console.log(data); 
 
data.forEach(function(r){ 
    if(parseFloat(r.latitude) === 0 || parseFloat(r.longitude) === 0) return; 
 
    let color; 
    if(r.cluster == 0){ 
        color = "red";    // วิกฤต,ปลาน้อย
    } 
    else{ 
        color = "blue";    // ปลาชุกชุม
    } 
 
    let radius=5; 
    let amt = parseFloat(r.amount); 
    if(amt<10){ radius=6; } 
    else if(amt<50){ radius=10; } 
    else if(amt<100){ radius=14; } 
    else{ radius=18; } 

    L.circleMarker([parseFloat(r.latitude), parseFloat(r.longitude)], { 
        radius: radius, 
        color: color, 
        fillColor: color, 
        fillOpacity: 0.8 
    }) 
    .addTo(map) 
    .bindPopup(
        "<b>"+r.station_name+"</b><br>"+
        "ปี : "+r.year+"<br>"+
        "เดือน : "+r.month+"<hr>"+
        "<b>จำนวนปลารวม :</b> "+parseFloat(r.amount).toFixed(2)+" ตัน<br>"+
        "<b>Cluster :</b> "+r.cluster
    );
}); 

var legend = L.control({position: 'bottomright'});
legend.onAdd = function (map) {
    var div = L.DomUtil.create('div', 'legend'),
        labels = ['วิกฤต/ปลาน้อย', 'ปลาชุกชุม'],
        colors = ['red','blue'];

    div.innerHTML = '<b>ระดับสภาวะกลุ่ม</b><br>';
    for (var i = 0; i < colors.length; i++) {
        div.innerHTML +=
            '<i style="background:' + colors[i] + '"></i> ' + labels[i] + '<br>';
    }
    return div;
};
legend.addTo(map);
</script> 
 
</body> 
</html>
