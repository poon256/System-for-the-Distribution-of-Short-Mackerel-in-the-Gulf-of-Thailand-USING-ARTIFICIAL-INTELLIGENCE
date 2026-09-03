<?php

class station
{

    function def() 
    {
		$conn = new connect();
		$acl = $conn->check_acl();
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>Station Management</h2>
				<?php
					if (($acl == '2') or ($acl > '5'))
					{
					?>
						<input type='button' value='Add' onclick='window.open("index.php?option=station&task=edit&id=0","_self")'>
					<?php
					}
				?>
                <input type='hidden' name='option' value='station'>
                <input type='hidden' name='task' value='def'>
                <table id='datatable' class='table table-bordered table-striped'>
                    <thead>
                        <tr>
                            <th class='text-center'>Id</th>
                            <th class='text-center'>satation_name</th>
							<th class='text-center'>longitude</th>
                            <th class='text-center'>latitude</th>
                            <th class='text-center'>station type</th>

                <?php
					if (($acl == '2') or ($acl > '5'))
					{
					?>
                            <th class='text-center'>Action</th>
					<?php
					}
				?>
                    </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = 'select  station.id as id ,
						station.station_name as station_name , 
						station.longitude as longitude ,
						station.latitude as latitude , 
						station_type.name as type
						from `station`,station_type
						where station.type = station_type.id';
                        $conn = new connect();
                        $res = $conn->query($sql);
                        while ($cdr = $res->fetch())
                        {
                            echo "<tr>";
                            echo "<td>";
                            echo $cdr['id'];
                            echo "</td>";
                            echo "<td>";
                            echo $cdr['station_name'];
                            echo "</td>";
							echo "<td>";
                            echo $cdr['longitude'];
                            echo "</td>";
                            echo "<td>";
                            echo $cdr['latitude'];
                            echo "</td>";
							echo "<td>";
                            echo $cdr['type'];
                            echo "</td>";
                            if (($acl == '2') or ($acl > '5')) {
                            echo "<td>";
                            echo "<input type='button' value='Edit' onclick='window.open(\"index.php?option=station&task=edit&id=".$cdr['id']."\",\"_self\")' />";
						    echo "<input type='button' value='Detail' onclick='window.open(\"index.php?option=station&task=det&id=".$cdr['id']."\",\"_self\")' />";
						    echo "</td>";
                            }
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <?php
        
    }  
  function edit() 
    {
        $id = $_REQUEST['id'];
        if ($id == 0) 
        {
            $station_name = "";
            $longitude = "";
            $latitude = "";
            $type = "";
        }
        else 
        {
            $sql = "select * from `station` where `id` = '".$id."'";
            $conn = new connect();
            $res = $conn->query($sql);
            while ($cdr = $res->fetch()) 
            {
                $station_name = $cdr['station_name'];
                $longitude = $cdr['longitude'];
                $latitude = $cdr['latitude'];
                $type = $cdr['type'];
            }
        }
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>Station Data</h2>
                <form action='index.php' method='get'>
				<table class='table'>
					<thead>
						<tr>
							<th colspan='2' class='text-center'>Edit Data</th>
						</tr>
					</thead>
					<tbody>
                        <tr>
							<td>Station Name</td>
							<td>
								<input name='station_name' value='<?php echo $station_name;?>'>
							</td>
						</tr>
                        <tr>
							<td>Longitude</td>
							<td>
								<input name='longitude' value='<?php echo $longitude;?>'>
							</td>
						</tr>
                        <tr>
							<td>Latitude</td>
							<td>
								<input name='latitude' value='<?php echo $latitude;?>'>
							</td>
						</tr>
						<tr>
							<tr>
                            <td>Station Type</td>
                            <td>
                                <select name='type' required>
                                    <option value="">เลือกสถานี</option>
                                    <?php
                                    $conn = new connect();
                                    $sql_station = "select id, name from station_type where status = 1";
                                    $res_station = $conn->query($sql_station);
                                    while ($st = $res_station->fetch()) {
                                        $selected = ($st['id'] == $type) ? "selected" : "";
                                        echo "<option value='".$st['id']."' $selected>".$st['name']."</option>";
                                        }
                                        ?>
                                        
                                        </select>
                                    </td>
                                </tr>
						<tr>
							<td colspan='2' class='text-center'>
								<input type='hidden' name="option" value='station'>
								<input type='hidden' name="task" value='save'>
								<input type='hidden' name="id" value='<?php echo $id;?>'>
								<input type='submit' value='Save'>
								<input type='button' value='Back' onclick='window.open("index.php?option=station&task=def","_self")'>
							</td>
						</tr>
					</tbody>
				</table>
				</form>
                </div>
            </div>
        </div>
        <?php
    }

    function del() 
    {
        $id = $_REQUEST['id'];
		$sql = "update `station` set `status` = '".$_REQUEST['stat']."' where `id` = '".$id."'";
		$conn = new connect();
		$conn->query($sql);
		header('location:index.php?option=station&task=def');
    }

    function save() 
    {
        $id = $_REQUEST['id'];
		$station_name = $_REQUEST['station_name'];
        $latitude = $_REQUEST['latitude'];
		$longitude = $_REQUEST['longitude'];
        $type = $_REQUEST['type'];

		if ($id == 0) 
		{
			$sql = "insert into `station` set `station_name` = '".$station_name."', `latitude` = '".$latitude."', `longitude` = '".$longitude."' ,`type` = '".$type."'";
		}
		else 
		{
			$sql = "update `station` set `station_name` = '".$station_name."', `latitude` = '".$latitude."', `longitude` = '".$longitude."' ,`type` = '".$type."' where `id` = '".$id."'";
		}
		$conn = new connect();
		$conn->query($sql);
		header('location:index.php?option=station&task=def');
    }

    function det() 
    {
        $id = $_REQUEST['id'];
		$sql = "select * from `station` where `id` = '".$id."'";
		$conn = new connect();
		$res = $conn->query($sql);
		while ($cdr = $res->fetch()) 
		{
			$station_name = $cdr['station_name'];
			$latitude = $cdr['latitude'];
			$longitude = $cdr['longitude'];
            $type = $cdr['type'];

		}
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>Api Management</h2>
                <table class='table'>
					<thead>
						<tr>
							<th colspan='2' class='text-center'>Api Data</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>station name</td>
							<td>
								<?php echo $station_name;?>
							</td>
						</tr>
                        <tr>
							<td>latitude</td>
							<td>
								<?php echo $latitude;?>
							</td>
						</tr>
						<tr>
							<td>longitude</td>
							<td>
								<?php echo $longitude;?>
							</td>
						</tr>
        				<tr>
							<td>type</td>
							<td>
								<?php echo $type;?>
							</td>
						</tr>
						<tr>
							<td colspan='2' class='text-center'>
								<input type='button' value='Back' onclick='window.open("index.php?option=station&task=def","_self")'>
							</td>
						</tr>
					</tbody>
				</table>
                </div>
            </div>
        </div>
        <?php
    }
}
?>