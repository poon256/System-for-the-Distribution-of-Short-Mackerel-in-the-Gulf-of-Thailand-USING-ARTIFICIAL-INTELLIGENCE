<?php

class usergroup
{

    function def() 
    {
        $conn = new connect();
		$acl = $conn->check_acl();
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
				<h2>User Group</h2>
				<?php
					if (($acl == '2') or ($acl > '5'))
					{
					?>
						<input type='button' value='Add' onclick='window.open("index.php?option=usergroup&task=edit&id=0","_self")'>
					<?php
					}
					?>
                <table id='datatable' class='table table-bordered table-striped'>
                    <thead>
                        <tr>
                            <th class='text-center'>Id</th>
                            <th class='text-center'>Name</th>
                            <th class='text-center'>Status</th>
				<?php
					if (($acl == '2') or ($acl > '5'))
					{
					?>
                            <th class='text-center'>Action</th>
					<?php
					}
					if ($acl > '3')
					{
					?>
                            <th class='text-center'>Approve</th>
					<?php
					}
				?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $sql = 'select * from `usergroup`';
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
							if (($acl == '2') or ($acl > '5'))
							{
                            echo "<td>";
                            echo "<input type='button' value='Edit' onclick='window.open(\"index.php?option=usergroup&task=edit&id=".$cdr['id']."\",\"_self\")' />";
						    echo "<input type='button' value='".$ds."' onclick='confirm_del(\"index.php?option=usergroup&task=del&id=".$cdr['id']."&stat=".$dss."\")' />";
						    echo "<input type='button' value='Detail' onclick='window.open(\"index.php?option=usergroup&task=det&id=".$cdr['id']."\",\"_self\")' />";
						    echo "</td>";
							}
							if ($acl > '3')
							{
                            echo "<td>";
						    echo "<input type='button' value='UIG' onclick='window.open(\"index.php?option=usergroup&task=uig&id=".$cdr['id']."\",\"_self\")' />";
						    echo "<input type='button' value='ACL' onclick='window.open(\"index.php?option=usergroup&task=acl&id=".$cdr['id']."\",\"_self\")' />";
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
        }
        else 
        {
            $sql = "select * from `usergroup` where `id` = '".$id."'";
            $conn = new connect();
            $res = $conn->query($sql);
            while ($cdr = $res->fetch()) 
            {
                $name = $cdr['name'];
            }
        }
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
				<h2>User Group</h2>
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
							<td colspan='2' class='text-center'>
								<input type='hidden' name="option" value='usergroup'>
								<input type='hidden' name="task" value='save'>
								<input type='hidden' name="id" value='<?php echo $id;?>'>
								<input type='submit' value='Save'>
								<input type='button' value='Back' onclick='window.open("index.php?option=usergroup&task=def","_self")'>
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
		$sql = "update `usergroup` set `status` = '".$_REQUEST['stat']."' where `id` = '".$id."'";
		$conn = new connect();
		$conn->query($sql);
		header('location:index.php?option=usergroup&task=def');
    }

    function save() 
    {
        $id = $_REQUEST['id'];
		$name = $_REQUEST['name'];
		if ($id == 0) 
		{
			$sql = "insert into `usergroup` set `name` = '".$name."'";
		}
		else 
		{
			$sql = "update `usergroup` set `name` = '".$name."' where `id` = '".$id."'";
		}
		$conn = new connect();
		$conn->query($sql);
		header('location:index.php?option=usergroup&task=def');
    }

    function det() 
    {
        $id = $_REQUEST['id'];
		$sql = "select * from `usergroup` where `id` = '".$id."'";
		$conn = new connect();
		$res = $conn->query($sql);
		while ($cdr = $res->fetch()) 
		{
			$name = $cdr['name'];
		}
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
				<h2>User Group</h2>
                <table class='table'>
					<thead>
						<tr>
							<th colspan='2' class='text-center'>usergroup Data</th>
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
							<td colspan='2' class='text-center'>
								<input type='button' value='Back' onclick='window.open("index.php?option=usergroup&task=def","_self")'>
							</td>
						</tr>
					</tbody>
				</table>
                </div>
            </div>
        </div>
        <?php
    }
    function uig() 
    {
        $id = $_REQUEST['id'];
		$sql = "select * from `usergroup` where `id` = '".$id."'";
		$conn = new connect();
		$res = $conn->query($sql);
		while ($cdr = $res->fetch()) 
		{
			$name = $cdr['name'];
		}
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <table class='table'>
					<thead>
						<tr>
							<th colspan='2' class='text-center'>User in Group</th>
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
							<td colspan='2' class='text-center'>
								<input type='button' value='Back' onclick='window.open("index.php?option=usergroup&task=def","_self")'>
							</td>
						</tr>
					</tbody>
				</table>
				<form action='index.php?option=usergroup&task=saveuig' method='post'>
				<table class='table table-bordered table-striped'>
					<thead>
						<tr>
							<th class='text-center'>
								Check
							</th>
							<th class='text-center'>
								Username
							</th>
							<th class='text-center'>
								Name
							</th>
						</tr>
					</thead>
					<tbody>
					<?php
                        $sql = 'select *, (select count(`id`) from `uig` where `ugid` = "'.$id.'" and `uid` = `users`.`id` and `uig`.`status` > "0") as `cc` from `users` where `status` > "0"';
                        $conn = new connect();
                        $res = $conn->query($sql);
                        while ($cdr = $res->fetch())
                        {
                            echo "<tr>";
                            echo "<td class='text-center'>";
							if ($cdr['cc'] == 1)
							{
								echo "<input type='checkbox' name='uig[]' value='".$cdr['id']."' checked />";
							}
							else
							{
								echo "<input type='checkbox' name='uig[]' value='".$cdr['id']."' />";
							}
                            echo "</td>";
                            echo "<td class='text-center'>";
                            echo $cdr['user'];
                            echo "</td>";
                            echo "<td class='text-center'>";
                            echo $cdr['name'];
                            echo "</td>";
                            echo "</tr>";
                        }
                     ?>
					</tbody>
					<tfoot>
						<tr>
							<td class='text-center' colspan='3'>
								<input type='submit' value='Save' />
								<input type='hidden' name='gid' value='<?php echo $id;?>' />
							</td>
						</tr>
					</tfoot>
				</table>
				</form>
                </div>
            </div>
        </div>
        <?php
    }

	function saveuig()
	{
		$gid = $_REQUEST['gid'];
		$conn = new connect();
		$sql = "update `uig` set `status` = '0' where `ugid` = '".$gid."'";
		$conn->query($sql);
		foreach ($_REQUEST['uig'] as $uid)
		{
			$sql = "insert into `uig` set `uid` = '".$uid."', `ugid` = '".$gid."'";
			$conn->query($sql);
		}
		header('location:index.php?option=usergroup&task=uig&id='.$gid);
	}

	function acl()
	{
        $id = $_REQUEST['id'];
		$sql = "select * from `usergroup` where `id` = '".$id."'";
		$conn = new connect();
		$res = $conn->query($sql);
		while ($cdr = $res->fetch()) 
		{
			$name = $cdr['name'];
		}
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <table class='table'>
					<thead>
						<tr>
							<th colspan='2' class='text-center'>User Group ACL</th>
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
							<td colspan='2' class='text-center'>
								<input type='button' value='Back' onclick='window.open("index.php?option=usergroup&task=def","_self")'>
							</td>
						</tr>
					</tbody>
				</table>
				<form action='index.php?option=usergroup&task=saveacl' method='post'>
				<table class='table table-bordered table-striped'>
					<thead>
						<tr>
							<th class='text-center'>
								Check
							</th>
							<th class='text-center'>
								Name
							</th>
							<th class='text-center'>
								Application
							</th>
						</tr>
					</thead>
					<tbody>
					<?php
						$aa = 0;
                        $sql = 'select *, (select `accl` from `acl` where `status` = "1" and `app`.`id` = `appid` and `ugid` = "'.$id.'") as `cc` from `app` where `status` > "0"';
                        $conn = new connect();
                        $res = $conn->query($sql);
                        while ($cdr = $res->fetch())
                        {
                            echo "<tr>";
                            echo "<td class='text-center'>";
							echo "<select name='cc+".$aa."'>";
							if ($cdr['cc'] == '0')
							{
								echo "<option value='0' selected>No Access</option>";
							}
							else
							{
								echo "<option value='0'>No Access</option>";
							}
							if ($cdr['cc'] == '1')
							{
								echo "<option value='1' selected>Read</option>";
							}
							else
							{
								echo "<option value='1'>Read</option>";
							}
							if ($cdr['cc'] == '2')
							{
								echo "<option value='2' selected>Write</option>";
							}
							else
							{
								echo "<option value='2'>Write</option>";
							}
							if ($cdr['cc'] == '3')
							{
								echo "<option value='3' selected>Read + Write</option>";
							}
							else
							{
								echo "<option value='3'>Read + Write</option>";
							}
							if ($cdr['cc'] == '6')
							{
								echo "<option value='6' selected>Full Access</option>";
							}
							else
							{
								echo "<option value='6'>Full Access</option>";
							}
							echo "</select>";
							echo "<input type='hidden' name='ca+".$aa."' value='".$cdr['id']."' />";
                            echo "</td>";
                            echo "<td class='text-center'>";
                            echo $cdr['name'];
                            echo "</td>";
                            echo "<td class='text-center'>";
                            echo $cdr['detail'];
                            echo "</td>";
                            echo "</tr>";
							$aa++;
                        }
                     ?>
					</tbody>
					<tfoot>
						<tr>
							<td class='text-center' colspan='3'>
								<input type='submit' value='Save' />
								<input type='hidden' name='gid' value='<?php echo $id;?>' />
								<input type='hidden' name='limit' value='<?php echo $aa;?>' />
							</td>
						</tr>
					</tfoot>
				</table>
				</form>
                </div>
            </div>
        </div>
        <?php
	}
 
    function saveacl()
    {
        $conn = new connect();
        $gid = $_REQUEST['gid'];
        $limit = $_REQUEST['limit'];
		$sql = "update `acl` set status = '0' where `ugid` = '".$gid."'";
		$conn->query($sql);
        $a = 0;
        while ($a < $limit) 
        {
            if ($_REQUEST['cc+'.$a] > 0) 
            {
                $sql = "insert into `acl` set `ugid` = '".$gid."', `appid` = '".$_REQUEST['ca+'.$a]."', `accl` = '".$_REQUEST['cc+'.$a]."'";
				//echo $sql."<br />";
                $conn->query($sql);
            }
            $a++;
        }
        header("location:index.php?option=usergroup&task=acl&id=".$gid);
    }
 

}

?>