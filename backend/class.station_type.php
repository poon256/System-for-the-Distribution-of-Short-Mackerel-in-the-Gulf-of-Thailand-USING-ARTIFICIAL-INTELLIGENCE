<?php

class station_type
{

    function def() 
    {
		$conn = new connect();
		$acl = $conn->check_acl();
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>Station Type</h2>	
				<?php
					if (($acl == '2') or ($acl > '5'))
					{
					?>
						<input type='button' value='Add' onclick='window.open("index.php?option=station_type&task=edit&id=0","_self")'>
					<?php
					}
				?>
                <input type='hidden' name='option' value='station_type'>
                <input type='hidden' name='task' value='def'>

                <table id='datatable' class='table table-bordered table-striped'>
                    <thead>
                        <tr>
                            <th class='text-center'>Id</th>
                            <th class='text-center'>Name</th>
							<th class='text-center'>detail</th>
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
                        $sql = 'select * from `station_type`';
                        $conn = new connect();
                        $res = $conn->query($sql);
                        while ($cdr = $res->fetch())
                        {
                            echo "<tr>";
                            echo "<td>";
                            echo $cdr['id'];
                            echo "</td>";
                            echo "<td>";
                            echo $cdr['name'];
                            echo "</td>";
							echo "<td>";
                            echo $cdr['detail'];
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
                            echo "<input type='button' value='Edit' onclick='window.open(\"index.php?option=station_type&task=edit&id=".$cdr['id']."\",\"_self\")' />";
                            echo "<input type='button' value='".$ds."'onclick='if(confirm(\"Are you sure?\")) window.location=\"index.php?option=station_type&task=del&id=".$cdr['id']."&stat=".$dss."\"' />";
						    echo "<input type='button' value='Detail' onclick='window.open(\"index.php?option=station_type&task=det&id=".$cdr['id']."\",\"_self\")' />";
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
            $name = "";
            $detail = "";
        }
        else 
        {
            $sql = "select * from `station_type` where `id` = '".$id."'";
            $conn = new connect();
            $res = $conn->query($sql);
            while ($cdr = $res->fetch()) 
            {
                $name = $cdr['name'];
                $detail = $cdr['detail'];

            }
        }
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>Station type</h2>
                <form action='index.php' method='get'>
				<table class='table'>
					<thead>
						<tr>
							<th colspan='2' class='text-center'>Edit Data</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>Name</td>
							<td>
								<input name='name' value='<?php echo $name;?>'>
							</td>
						</tr>
                        <tr>
							<td>detail</td>
							<td>
								<input name='detail' value='<?php echo $detail;?>'>
							</td>
						</tr>
						<tr>
							<td colspan='2' class='text-center'>
								<input type='hidden' name="option" value='station_type'>
								<input type='hidden' name="task" value='save'>
								<input type='hidden' name="id" value='<?php echo $id;?>'>
								<input type='submit' value='Save'>
								<input type='button' value='Back' onclick='window.open("index.php?option=station_type&task=def","_self")'>
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
		$sql = "update `station_type` set `status` = '".$_REQUEST['stat']."' where `id` = '".$id."'";
		$conn = new connect();
		$conn->query($sql);
		header('location:index.php?option=station_type&task=def');
    }

    function save() 
    {
        $id = $_REQUEST['id'];
		$name = $_REQUEST['name'];
        $detail = $_REQUEST['detail'];
		if ($id == 0) 
		{
			$sql = "insert into `station_type` set `name` = '".$name."', `detail` = '".$detail."'";
		}
		else 
		{
			$sql = "update `station_type` set `name` = '".$name."', `detail` = '".$detail."'  where `id` = '".$id."'";
		}
		$conn = new connect();
		$conn->query($sql);
		header('location:index.php?option=station_type&task=def');
    }

    function det() 
    {
        $id = $_REQUEST['id'];
		$sql = "select * from `station_type` where `id` = '".$id."'";
		$conn = new connect();
		$res = $conn->query($sql);
		while ($cdr = $res->fetch()) 
		{
			$name = $cdr['name'];
            $detail = $cdr['detail'];

		}
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>User Management</h2>
                <table class='table'>
					<thead>
						<tr>
							<th colspan='2' class='text-center'>user Data</th>
						</tr>
					</thead>
					<tbody>
						<tr>
							<td>Name</td>
							<td>
								<?php echo $name;?>
							</td>
						</tr>
                        <tr>
							<td>detail</td>
							<td>
								<?php echo $detail;?>
							</td>
						</tr>
						<tr>
							<td colspan='2' class='text-center'>
								<input type='button' value='Back' onclick='window.open("index.php?option=station_type&task=def","_self")'>
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