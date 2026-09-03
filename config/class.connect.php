<?php

class connect
{

    function conn() //ฟังก์ชันที่เชื่อมต่อกับ Database ทั้งหมด
    {
        $host = 'localhost';
        $dbname = 'projecta';
        $user = 'root';
        $pass = '';
        $conn = new PDO ("mysql:host=$host;dbname=$dbname","$user","$pass");
        $conn->exec("set names utf8");
        return $conn;
    }


    function query($sql)  //ฟังก์ชันที่เกี่ยวกับการ execute
    {
        $conn = $this->conn(); //เรียกใช้ฟังก์ชันใน class
        $res = $conn->prepare($sql);
        $res->execute();
        return $res;
    }

    function counts($res)  //ฟังก์ชันที่เกี่ยวกับการ execute
    {
        $counts = $res->rowCount();
        return $counts;
    }

    function save_logs($action,$uid)
	{
		$sql = "insert into `logs` set `action` = '".$action."', `uid` = '".$uid."', `dating` = '".time()."'";
		$this->query($sql);
	}
    
    function salter($txt)
    {
    $key = 'kerel';
    return hash('sha256', $key . $txt . $key); 
    }

	function query_lastid($sql)
	{
        $conn = $this->conn(); //เรียกใช้ฟังก์ชันใน class
        $res = $conn->prepare($sql);
        $res->execute();
        return $conn->lastInsertId();
	}

	function check_acl()
	{
		if (isset($_REQUEST['option']))
		{
			$option = $_REQUEST['option'];
		}
		else
		{
			$option = "logs";
		}
		$sql = "select max(`acl`.`accl`) as `mca` from `app`, `acl`, `uig` where `app`.`dir` = '".$option."' and `acl`.`status` = '1' and `uig`.`status` = '1' and `acl`.`appid` = `app`.`id` and `acl`.`ugid` = `uig`.`ugid` and `uig`.`uid` = '".$_SESSION['uid']."'";
		$res = $this->query($sql);
		while ($cdr = $res->fetch())
		{
			$acl = $cdr['mca'];
		}
		return $acl;
	}


}

?>