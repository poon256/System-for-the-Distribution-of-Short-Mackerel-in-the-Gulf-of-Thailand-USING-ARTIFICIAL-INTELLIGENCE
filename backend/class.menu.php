<nav class="navbar navbar-expand-lg bg-body-tertiary">
    <div class="container-fluid">
        <a class="navbar-brand" href="../frontend/home.php">Project</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
        </button>
		<div class="collapse navbar-collapse" id="navbarSupportedContent">
		<ul class="navbar-nav me-auto mb-2 mb-lg-0">

			<?php
			require_once('../config/class.connect.php');
			if (isset($_SESSION['uid']))
			{
				$sql = "select * from `app`,`acl`,`uig` 
				where `app`.`status` = '1' and `acl`.`status` = '1' and `uig`.`status` = '1' and `acl`.`appid` = `app`.`id` and `acl`.`ugid` = `uig`.`ugid` and `uig`.`uid` = '".$_SESSION['uid']."' 
				order by `app`.`appgroup`";
				$conn = new connect();
				$res = $conn->query($sql);
				while ($cdr = $res->fetch())
				{
					echo '<li class="nav-item">';
					echo '<a class="nav-link" href="index.php?option='.$cdr['dir'].'&task=def">';
					echo $cdr['name'];
					echo '</a>';
					echo '</li>';
					echo'</li>';
				}		
			?>
				<li class="nav-item">
					<a class="nav-link" aria-current="page" href="../frontend/home.php">Homepage</a>
				</li>
				<li class="nav-item">
				<a class="nav-link" aria-current="page" href="index.php?option=logs&task=logout">Log out</a>
				</li>
			<?php
			}
			else 
			{
			?>
				<li class="nav-item">
				<a class="nav-link" aria-current="page" href="index.php?option=logs&task=login_form">Log in</a>
				</li>
			<?php
			}
		?>
        </ul>
        </div>
    </div>
</nav>