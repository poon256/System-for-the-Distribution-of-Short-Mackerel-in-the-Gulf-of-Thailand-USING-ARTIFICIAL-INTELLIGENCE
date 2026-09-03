<?php

class users
{

    function def() 
    {
		$conn = new connect();
		$acl = $conn->check_acl();
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>User Management</h2>
				<?php
					if (($acl == '2') or ($acl > '5'))
					{
					?>
						<input type='button' value='Add' onclick='window.open("index.php?option=users&task=edit&id=0","_self")'>
					<?php
					}
				?>
                <input type='hidden' name='option' value='users'>
                <input type='hidden' name='task' value='def'>


                </form>
                <table id='datatable' class='table table-bordered table-striped'>
                    <thead>
                        <tr>
                            <th class='text-center'>Id</th>
                            <th class='text-center'>user</th>
                            <th class='text-center'>Name</th>
							<th class='text-center'>Surname</th>
                            <th class='text-center'>Email</th>
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
                        $sql = 'select * from `users`';
                        $conn = new connect();
                        $res = $conn->query($sql);
                        while ($cdr = $res->fetch())
                        {
                            echo "<tr>";
                            echo "<td>";
                            echo $cdr['id'];
                            echo "</td>";
                            echo "<td>";
                            echo $cdr['user'];
                            echo "</td>";
                            echo "<td>";
                            echo $cdr['name'];
                            echo "</td>";
							echo "<td>";
                            echo $cdr['surname'];
                            echo "</td>";
            				echo "<td>";
                            echo $cdr['mail'];
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
                            echo "<input type='button' value='Edit' onclick='window.open(\"index.php?option=users&task=edit&id=".$cdr['id']."\",\"_self\")' />";
                            echo "<input type='button' value='".$ds."'onclick='if(confirm(\"Are you sure?\")) window.location=\"index.php?option=users&task=del&id=".$cdr['id']."&stat=".$dss."\"' />";
						    echo "<input type='button' value='Detail' onclick='window.open(\"index.php?option=users&task=det&id=".$cdr['id']."\",\"_self\")' />";
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
            $surname = "";
            $user = "";
            $pass = "";
            $mail = "";

        }
        else 
        {
            $sql = "select * from `users` where `id` = '".$id."'";
            $conn = new connect();
            $res = $conn->query($sql);
            while ($cdr = $res->fetch()) 
            {
                $name = $cdr['name'];
                $surname = $cdr['surname'];
                $user = $cdr['user'];
                $pass = $cdr['pass'];
                $mail = $cdr['mail'];

            }
        }
        ?>
        <div class='container'>
            <div class='row'>
                <div class='col-12'>
                <h2>User Management</h2>
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
							<td>Surname</td>
							<td>
								<input name='surname' value='<?php echo $surname;?>'>
							</td>
						</tr>
						<tr>
							<td>user</td>
							<td>
								<input name='user' value='<?php echo $user;?>'>
							</td>
						</tr>
						<tr>
							<td>Password</td>
							<td>
								<input type='password' name='pass' value='<?php echo $pass;?>'>
							</td>
						</tr>
    					<tr>
							<td>Email</td>
							<td>
								<input type='mail' name='mail' value='<?php echo $mail;?>'>
							</td>
						</tr>
						<tr>
							<td colspan='2' class='text-center'>
								<input type='hidden' name="option" value='users'>
								<input type='hidden' name="task" value='save'>
								<input type='hidden' name="id" value='<?php echo $id;?>'>
								<input type='submit' value='Save'>
								<input type='button' value='Back' onclick='window.open("index.php?option=users&task=def","_self")'>
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
		$sql = "update `users` set `status` = '".$_REQUEST['stat']."' where `id` = '".$id."'";
		$conn = new connect();
		$conn->query($sql);
		header('location:index.php?option=users&task=def');
    }
    
    function save() 
    {
        $conn = new connect(); // สร้าง object ก่อน
        $id = $_REQUEST['id'];
        $name = $_REQUEST['name'];
        $surname = $_REQUEST['surname'];
        $user = $_REQUEST['user'];
        $mail = $_REQUEST['mail'];
        
        // แก้ตรงนี้: นำรหัสผ่านไปผ่านฟังก์ชัน salter ก่อนบันทึก
        $pass = $conn->salter($_REQUEST['pass']);

        if ($id == 0) 
        {
            // เพิ่มการบันทึก mail และ status ด้วย
            $sql = "insert into `users` set `name` = '".$name."', `user` = '".$user."', `surname` = '".$surname."' ,`pass` = '".$pass."', `mail` = '".$mail."', `status` = '1'";
        }
        else 
        {
            $sql = "update `users` set `name` = '".$name."', `user` = '".$user."', `surname` = '".$surname."' ,`pass` = '".$pass."', `mail` = '".$mail."' where `id` = '".$id."'";
        }
        
        $conn->query($sql);
        header('location:index.php?option=users&task=def');
        exit();
    }

    function det() 
    {
        $id = $_REQUEST['id'];
		$sql = "select * from `users` where `id` = '".$id."'";
		$conn = new connect();
		$res = $conn->query($sql);
		while ($cdr = $res->fetch()) 
		{
			$name = $cdr['name'];
            $surname = $cdr['surname'];
			$user = $cdr['user'];
			$pass = $cdr['pass'];
            $mail = $cdr['mail'];

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
							<td>surname</td>
							<td>
								<?php echo $surname;?>
							</td>
						</tr>
						<tr>
							<td>user</td>
							<td>
								<?php echo $user;?>
							</td>
						</tr>
        				<tr>
							<td>Email</td>
							<td>
								<?php echo $mail;?>
							</td>
						</tr>
						<tr>
							<td colspan='2' class='text-center'>
								<input type='button' value='Back' onclick='window.open("index.php?option=users&task=def","_self")'>
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