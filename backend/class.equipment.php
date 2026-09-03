<?php

class equipment
{

    function def() 
    {
		$conn = new connect();
		$acl = $conn->check_acl();
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>Equipment</h2>	
				<?php
					if (($acl == '2') or ($acl > '5'))
					{
					?>
						<input type='button' value='Add' onclick='window.open("index.php?option=equipment&task=edit&id=0","_self")'>
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
							<th class='text-center'>impact_level</th>
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
                        $sql = 'select * from `equipment`';
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
                            echo $cdr['impact_level'];
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
                            echo "<input type='button' value='Edit' onclick='window.open(\"index.php?option=equipment&task=edit&id=".$cdr['id']."\",\"_self\")' />";
                            echo "<input type='button' value='".$ds."'onclick='if(confirm(\"Are you sure?\")) window.location=\"index.php?option=equipment&task=del&id=".$cdr['id']."&stat=".$dss."\"' />";
						    echo "<input type='button' value='Detail' onclick='window.open(\"index.php?option=equipment&task=det&id=".$cdr['id']."\",\"_self\")' />";
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
            $impact_level = "";
        }
        else 
        {
            $sql = "select * from `equipment` where `id` = '".$id."'";
            $conn = new connect();
            $res = $conn->query($sql);
            while ($cdr = $res->fetch()) 
            {
                $name = $cdr['name'];
                $impact_level = $cdr['impact_level'];

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
							<td>impact_level</td>
							<td>
								<input name='impact_level' value='<?php echo $impact_level;?>'>
							</td>
						</tr>
						<tr>
							<td colspan='2' class='text-center'>
								<input type='hidden' name="option" value='equipment'>
								<input type='hidden' name="task" value='save'>
								<input type='hidden' name="id" value='<?php echo $id;?>'>
								<input type='submit' value='Save'>
								<input type='button' value='Back' onclick='window.open("index.php?option=equipment&task=def","_self")'>
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
		$sql = "update `equipment` set `status` = '".$_REQUEST['stat']."' where `id` = '".$id."'";
		$conn = new connect();
		$conn->query($sql);
		header('location:index.php?option=equipment&task=def');
    }

    function save() 
    {
        $id = $_REQUEST['id'];
		$name = $_REQUEST['name'];
        $impact_level = $_REQUEST['impact_level'];
		if ($id == 0) 
		{
			$sql = "insert into `equipment` set `name` = '".$name."', `impact_level` = '".$impact_level."'";
		}
		else 
		{
			$sql = "update `equipment` set `name` = '".$name."', `impact_level` = '".$impact_level."'  where `id` = '".$id."'";
		}
		$conn = new connect();
		$conn->query($sql);
		header('location:index.php?option=equipment&task=def');
    }

    function det() 
    {
        $id = $_REQUEST['id'];
		$sql = "select * from `equipment` where `id` = '".$id."'";
		$conn = new connect();
		$res = $conn->query($sql);
		while ($cdr = $res->fetch()) 
		{
			$name = $cdr['name'];
            $impact_level = $cdr['impact_level'];

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
							<td>impact_level</td>
							<td>
								<?php echo $impact_level;?>
							</td>
						</tr>
						<tr>
							<td colspan='2' class='text-center'>
								<input type='button' value='Back' onclick='window.open("index.php?option=equipment&task=def","_self")'>
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