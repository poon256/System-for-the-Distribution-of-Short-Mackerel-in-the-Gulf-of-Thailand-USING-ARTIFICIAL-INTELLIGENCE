<?php

ob_start();
session_start();

if (isset($_REQUEST['option']))
{
	$option = $_REQUEST['option'];
}
elseif (isset($_SESSION['uid']))
{
	$option = "home";
}
else
{
	$option = "logs";
}
if (isset($_REQUEST['task']))
{
	$task = $_REQUEST['task'];
}
elseif (isset($_SESSION['uid']))
{
	$task = "def";
}
else
{
	$task = "login_form";
}


?>
<!DOCTYPE html>
<html>
    <head>
        <title>
            Prototype
        </title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
		<link rel="stylesheet" href="https://code.jquery.com/ui/1.14.0/themes/base/jquery-ui.css">
		<link rel="stylesheet" href="https://cdn.datatables.net/2.1.6/css/dataTables.bootstrap5.min.css">
	</head>
    <body>
	<?php
		require_once('class.menu.php');
		require_once('../config/class.connect.php');
		if ((isset($_SESSION['uid']) == null) && ($option <> 'logs'))
		{
			$option = 'logs';
			$task = 'login_form';
		}
		require_once('class.'.$option.'.php');
		$clas = new $option();
		$clas->$task();
	?>
    </body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script><script src="https://code.jquery.com/jquery-3.7.1.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://code.jquery.com/ui/1.14.0/jquery-ui.js"></script>
<script src="https://cdn.datatables.net/2.1.6/js/dataTables.min.js"></script>
<script src="https://cdn.datatables.net/2.1.6/js/dataTables.bootstrap5.min.js"></script>
<script src="project.js"></script>
</html>