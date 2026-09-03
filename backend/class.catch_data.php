<?php

class catch_data
{

    function def() 
    {
		$conn = new connect();
		$acl = $conn->check_acl();
        if (isset($_REQUEST['searcher']))
        {
            $searcher = $_REQUEST['searcher'];
        }
        else
        {
            $searcher = null;
        }
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>Catch Mackerel Data</h2>			
				<?php
					if (($acl == '2') or ($acl > '5'))
					{
					?>
						<input type='button' value='Add' onclick='window.open("index.php?option=catch_data&task=edit&id=0","_self")'>
					<?php
					}
				?>
                <input type='hidden' name='option' value='catch_data'>
                <input type='hidden' name='task' value='def'>


                </form>
                <table id='datatable' class='table table-bordered table-striped'>
                    <thead>
                        <tr>
                            <th class='text-center'>Id</th>
                            <th class='text-center'>Station</th>
                            <th class='text-center'>Year</th>
							<th class='text-center'>Month</th>
                            <th class='text-center'>Equipment_id</th>
                            <th class='text-center'>Amount</th>
                            <th class='text-center'>Unit</th>
                            <th class='text-center'>Status</th>

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
                        $sql = 'select `catch_mackereldata`.`id` as `id`, `catch_mackereldata`.`year` as `year`,
                        `catch_mackereldata`.`month` as `month`,
                        `catch_mackereldata`.`amount` as `amount`, catch_mackereldata.unit as unit,
                        `station`.`station_name` as `sta`, 
                        catch_mackereldata.equipment_id as eq,`catch_mackereldata`.`status`
                        as `status` from `catch_mackereldata`, `station`
                         where `catch_mackereldata`.`station_id` = station.id ';
                        $conn = new connect();
                        $res = $conn->query($sql);
                        while ($cdr = $res->fetch())
                        {
                            echo "<tr>";
                            echo "<td>";
                            echo $cdr['id'];
                            echo "</td>";
                            echo "<td>";
                            echo $cdr['sta'];
                            echo "</td>";
                            echo "<td>";
                            echo $cdr['year'];
                            echo "</td>";
							echo "<td>";
                            echo $cdr['month'];
                            echo "</td>";
                            echo "<td>";
                            echo $cdr['eq'];
                            echo "</td>";
            				echo "<td>";
                            echo number_format($cdr['amount'],2);
                            echo "</td>";
                            echo "<td>";
                            echo $cdr['unit'];
                            echo "</td>";                           
                            echo "<td>";
                            if ($cdr['status'] == 1) 
                            {
                                echo "Active";
                                $ds = "In-Active";
                                $dss = "0";
                            }
                            else
                            {
                                echo "In-Active";
                                $ds = "Active";
                                $dss = "1";
                            }
                            echo "</td>";
                            if (($acl == '2') or ($acl > '5')) {
                            echo "<td>";
                            echo "<input type='button' value='Edit' onclick='window.open(\"index.php?option=catch_data&task=edit&id=".$cdr['id']."\",\"_self\")' />";
                            echo "<input type='button' value='".$ds."'onclick='if(confirm(\"Are you sure?\")) window.location=\"index.php?option=catch_data&task=del&id=".$cdr['id']."&stat=".$dss."\"' />";
						    echo "<input type='button' value='Detail' onclick='window.open(\"index.php?option=catch_data&task=det&id=".$cdr['id']."\",\"_self\")' />";
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
            $station_id = "";
            $year = "";
            $month = "";
            $amount = "";
            $unit = "" ;
            $equipment_id = "";
        }
        else 
        {
            $sql = "select * from `catch_mackereldata` where `id` = '".$id."'";
            $conn = new connect();
            $res = $conn->query($sql);
            while ($cdr = $res->fetch()) 
            {
                $station_id = $cdr['station_id'];
                $year = $cdr['year'];
                $month = $cdr['month'];
                $amount = $cdr['amount'];
                $unit = $cdr['unit'];
                $equipment_id = $cdr['equipment_id'];
            }
        }
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>Catch Mackerel Data</h2>
                <form action='index.php' method='get'>
				<table class='table'>
					<thead>
						<tr>
							<th colspan='2' class='text-center'>Edit Data</th>
						</tr>
					</thead>
					<tbody>
                        <tr>
							<td>Year</td>
							<td>
								<input name='year' value='<?php echo $year;?>'>
							</td>
						</tr>
                        <tr>
							<td>Month</td>
							<td>
								<input name='month' value='<?php echo $month;?>'>
							</td>
						</tr>
                        <tr>
                            <td>equipment</td>
                            <td>
                                <select name='equipment_id'>
                                    <option value="">เลือกอุปกรณ์การจับ</option>
                                    <?php
                                    $conn = new connect();
                                    $sql_station = "select id, name from equipment where status = 1";
                                    $res_station = $conn->query($sql_station);
                                    while ($st = $res_station->fetch()) {
                                        $selected = ($st['id'] == $equipment_id) ? "selected" : "";
                                        echo "<option value='".$st['id']."' $selected>".$st['name']."</option>";
                                        }
                                        ?>
                                        
                                        </select>
                                    </td>
                                </tr>                        
                                <tr>
                                    <td>Amount</td>
							<td>
								<input name='amount' value='<?php echo $amount;?>'>
							</td>
						</tr>
                        <tr>
							<td>Unit</td>
							<td>
								<input name='unit' value='<?php echo $unit;?>'>
							</td>
						</tr>
                        <tr>
                            <td>Station</td>
                            <td>
                                <select name='station_id' required>
                                    <option value="">เลือกสถานี</option>
                                    <?php
                                    $conn = new connect();
                                    $sql_station = "select id, station_name from station where status = 1";
                                    $res_station = $conn->query($sql_station);
                                    while ($st = $res_station->fetch()) {
                                        $selected = ($st['id'] == $station_id) ? "selected" : "";
                                        echo "<option value='".$st['id']."' $selected>".$st['station_name']."</option>";
                                        }
                                        ?>
                                        
                                        </select>
                                    </td>
                                </tr>
    					<tr>
							<td colspan='2' class='text-center'>
								<input type='hidden' name="option" value='catch_data'>
								<input type='hidden' name="task" value='save'>
								<input type='hidden' name="id" value='<?php echo $id;?>'>
								<input type='submit' value='Save'>
								<input type='button' value='Back' onclick='window.open("index.php?option=catch_data&task=def","_self")'>
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
		$sql = "update `catch_mackereldata` set `status` = '".$_REQUEST['stat']."' where `id` = '".$id."'";
		$conn = new connect();
		$conn->query($sql);
		header('location:index.php?option=catch_data&task=def');
    }

    function save() 
    {
        $id = $_REQUEST['id'];
		$station_id = $_REQUEST['station_id'];
        $year = $_REQUEST['year'];
		$month = $_REQUEST['month'];
        $amount = $_REQUEST['amount'];
        $unit = $_REQUEST['unit'];
        $equipment_id = $_REQUEST['equipment_id'];

		if ($id == 0) 
		{
			$sql = "insert into `catch_mackereldata` set `station_id` = '".$station_id."',
             `year` = '".$year."', `month` = '".$month."' ,
             `amount` = '".$amount."',
             `unit` = '".$unit."',
             equipment_id = '".$equipment_id."'";
		}
		else 
		{
			$sql = "update `catch_mackereldata` set `station_id` = '".$station_id."',
             `year` = '".$year."', `month` = '".$month."' ,
             `amount` = '".$amount."',
             `unit` = '".$unit."',
             equipment_id = '".$equipment_id."'
             where `id` = '".$id."'";
		}
		$conn = new connect();
		$conn->query($sql);
		header('location:index.php?option=catch_data&task=def');
    }

    function det() 
    {
        $id = $_REQUEST['id'];
		$sql = "select * from `catch_mackereldata` where `id` = '".$id."'";
		$conn = new connect();
		$res = $conn->query($sql);
		while ($cdr = $res->fetch()) 
		{
			$station_id = $cdr['station_id'];
			$year = $cdr['year'];
			$month = $cdr['month'];
            $amount = $cdr['amount'];
            $unit = $cdr['unit'];
            $equipment_id = $cdr['equipment_id'];

		}
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>Catch Makerel Data</h2>
                <table class='table'>
					<thead>
						<tr>
							<th colspan='2' class='text-center'>Detail Data</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>Station</td>
							<td>
								<?php echo $station_id;?>
							</td>
						</tr>
                        <tr>
							<td>Year</td>
							<td>
								<?php echo $year;?>
							</td>
						</tr>
						<tr>
							<td>Month</td>
							<td>
								<?php echo $month;?>
							</td>
						</tr>
           				<tr>
							<td>Equipment_id</td>
							<td>
								<?php echo $equipment_id;?>
							</td>
						</tr>
                        <tr>
							<td>Amount</td>
							<td>
								<?php echo $amount;?>
							</td>
						</tr>
                        <tr>
							<td>Unit</td>
							<td>
								<?php echo $unit;?>
							</td>
						</tr>
						<tr>
							<td colspan='2' class='text-center'>
								<input type='button' value='Back' onclick='window.open("index.php?option=catch_data&task=def","_self")'>
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