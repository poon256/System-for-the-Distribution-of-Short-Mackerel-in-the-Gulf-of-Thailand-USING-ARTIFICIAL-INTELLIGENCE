<?php

class app
{
    function def() 
    {
        $conn = new connect();
		$acl = $conn->check_acl();
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>Application Management</h2>
				<?php
					if (($acl == '2') or ($acl > '5'))
					{
					?>
                        <input type='button' value='Add' onclick='window.open("index.php?option=app&task=edit&id=0","_self")'>
					<?php
					}
				?>
                <table id='datatable' class='table table-bordered table-striped'>
                    <thead>
                        <tr>
                            <th class='text-center'>Id</th>
                            <th class='text-center'>Name</th>
                            <th class='text-center'>Directory</th>

                    <?php
					if (($acl == '2') or ($acl > '5')) {
					?>
                    <th class='text-center'>Action</th>
					<?php
					}
                    ?>
                    </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = 'select * from `app`';
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
                            echo $cdr['dir'];
                            echo "</td>";
                            if (($acl == '2') or ($acl > '5'))
							{
                            echo "<td>";
                            echo "<input type='button' value='Edit' onclick='window.open(\"index.php?option=app&task=edit&id=".$cdr['id']."\",\"_self\")' />";
						    echo "<input type='button' value='Del' onclick='confirm_del(\"index.php?option=app&task=del&id=".$cdr['id']."&stat=0\")' />";
						    echo "<input type='button' value='Detail' onclick='window.open(\"index.php?option=app&task=det&id=".$cdr['id']."\",\"_self\")' />";
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
        $conn = new connect();
        $id = $_REQUEST['id'];
        if ($id == 0) 
        {
            $head = "Add";
            $name = "";
            $dir = "";
            $detail = "";
        }
        else 
        {
            $head = "Edit";
            $sql = "select * from app where id = '".$id."'";
            $res = $conn->query($sql);
            while ($cdr = $res->fetch()) 
            {
                $name = $cdr['name'];
                $dir = $cdr['dir'];
                $detail = $cdr['detail'];
            }
        }
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>Application Management</h2>
                <form action="index.php" method="get">
                <table class='table table-bordered table-striped'>
                    <tr>
                        <td colspan='2' class='text-center'><?php echo $head;?> Application List</td>
                    </tr>
                    <tr>
                        <td>Name</td>
                        <td>
                            <input name='name' value="<?php echo $name;?>">
                        </td>
                    </tr>
                    <tr>
                        <td>Dir</td>
                        <td>
                            <input name='dir' value="<?php echo $dir;?>">
                        </td>
                    </tr>
                    <tr>
                        <td>Detail</td>
                        <td>
                            <input name='detail' value="<?php echo $detail;?>">
                        </td>
                    </tr>
                    <tr>
                        <td colspan='2' class='text-center'>
                            <input type="submit" value="Save">
                            <input type="button" value="Back" onclick="window.open('index.php?option=app&task=def','_self')">
                            <input type="hidden" name="option" value="app">
                            <input type="hidden" name="task" value="save">
                            <input type="hidden" name="id" value="<?php echo $id;?>">
                        </td>
                    </tr>
                </table>
                </form>
                </div>
            </div>
        </div>
                </div>
        <?php

    }
 
    function save()
    {
        $conn = new connect();
        $id = $_REQUEST['id'];
        $name = $_REQUEST['name'];
        $dir = $_REQUEST['dir'];
        $detail = $_REQUEST['detail'];
        if ($id > 0) 
        {
            $sql = "update app set name = '".$name."', dir = '".$dir."', detail = '".$detail."' where id = '".$id."'";
            $conn->query($sql);
        }
        else 
        {
            $sql = "insert into app set name = '".$name."', dir = '".$dir."', detail = '".$detail."'";
            $conn->query($sql);
			$sql = "select * from app where name = '".$name."' and dir = '".$dir."' and detail = '".$detail."'";
			$res = $conn->query($sql);
			while ($cdr = $res->fetch()) 
			{
				$id = $cdr['id'];
			}
        }
        header("location:index.php?option=app&task=def&id=".$id);
    }

    function del() 
    {
        $id = $_REQUEST['id'];
		$sql = "update `app` set `status` = '".$_REQUEST['stat']."' where `id` = '".$id."'";
		$conn = new connect();
		$conn->query($sql);
		header('location:index.php?option=app&task=def');
    }

    function det() 
    {
        $id = $_REQUEST['id'];
        
		$sql = "select * from `app` where `id` = '".$id."'";
		$conn = new connect();
		$res = $conn->query($sql);
		while ($cdr = $res->fetch()) 
		{
            $id = $cdr['id'];
			$name = $cdr['name'];
            $dir = $cdr['dir'];

		}
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>Application Management</h2>
                <table class='table table-bordered table-striped'>
						<tr>
							<td colspan='2' class='text-center'>app Data</td>
						</tr>
						<tr>
							<td>Id</td>
							<td>
								<?php echo $id;?>
							</td>
						</tr>
                        <tr>
							<td>Name</td>
							<td>
								<?php echo $name;?>
							</td>
						</tr>
                        <tr>
							<td>Directory</td>
							<td>
								<?php echo $dir;?>
							</td>
						</tr>
						<tr>
							<td colspan='2' class='text-center'>
								<input type='button' value='Back' onclick='window.open("index.php?option=app&task=def","_self")'>
							</td>
						</tr>
				</table>
                </div>
            </div>
        </div>
        <?php
    }


}

?>