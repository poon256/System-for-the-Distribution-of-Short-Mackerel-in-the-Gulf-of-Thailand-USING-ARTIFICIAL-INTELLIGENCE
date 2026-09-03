<?php

class logs
{
	
	function def()
	{
	?>
	<div class='container'>
		<div class='row'>
			<div class='col-12'>
			<h2>Logs</h2>
			<table id='datatable' class='table table-bordered table-striped'>
				<thead>
					<tr>
						<th class='text-center'>No</th>
						<th class='text-center'>Users</th>
						<th class='text-center'>Action</th>
						<th class='text-center'>Date</th>
					</tr>
				</thead>
				<tbody>
				<?php
					$a = 1;
					$sql = "select * from `logs` left join`users` on  `users`.`id` = `logs`.`uid`";
					$conn = new connect();
					$res = $conn->query($sql);
					while ($cdr = $res->fetch())
					{
						echo "<tr>";
						echo "<td>";
						echo $a;
						echo "</td>";
						echo "<td>";
						echo $cdr['user'];
						echo "</td>";
						echo "<td>";
						echo $cdr['action'];
						echo "</td>";
						echo "<td>";
						echo date("d/m/Y H:i:s",$cdr['dating']);
						echo "</td>";
						echo "</tr>";
						$a++;
					}
				?>
				</tbody>
			</table>
			</div>
		</div>
	</div>
	<?php
	}
	
	function login_form()
	{
	?>
	<div class='container'>
		<div class='row'>
			<div class='col-12'>
			<form action='index.php' method='post'>
			<table class='table'>
				<tr>
					<td colspan='2' class='text-center'>Login</td>
				</tr>
				<tr>
					<td>Username</td>
					<td>
						<input name='user'>
					</td>
				</tr>
				<tr>
					<td>Password</td>
					<td>
						<input type='password' name='pass'>
					</td>
				</tr>
				<tr>
					<td colspan='2' class='text-center'>
						<input type='submit' value='Login'>
						<input type='hidden' name='option' value='logs'>
						<input type='hidden' name='task' value='login'>
					</td>
				</tr>
				<tr>
					<td colspan='2' class='text-center text-danger'>
					<?php
					if ((isset($_REQUEST['cc'])) && ($_REQUEST['cc'] == 0))
					{
						echo "Username or Password is wrong";
					}
					?>
					</td>
				</tr>
			</table>
			</form>
			</div>
		</div>
	</div>
	<?php
	}
	
	function login()
	{
    $conn = new connect();
    $user = $_REQUEST['user'] ?? '';
    $pass = $_REQUEST['pass'] ?? '';
    $secure_pass = $conn->salter($pass);
    $sql = "select * from `users` where `user` = '$user' and `pass` = '$secure_pass' and `status` = '1'";
    
    $res = $conn->query($sql);
    
    if ($cdr = $res->fetch()) 
    {
        $cc = 1;
        $_SESSION['uid'] = $cdr['id'];
        $_SESSION['uname'] = $cdr['name'];
        $this->save_logs("login", $cdr['id']);
    }
    else 
    {
        $cc = 0;
        $this->save_logs("cannot login", "0");
    }
    
    header('location:index.php?cc=' . $cc);
    exit();
	}
	
	function logout()
	{	
		$this->save_logs("logout",$_SESSION['uid']);
		session_destroy();
		header('location:index.php');
	}
	
	function save_logs($action,$uid)
	{
		$sql = "insert into `logs` set `action` = '".$action."', `uid` = '".$uid."', `dating` = '".time()."'";
		$conn = new connect();
		$res = $conn->query($sql);
	}
	
}

?>