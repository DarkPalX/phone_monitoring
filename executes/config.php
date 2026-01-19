<?php
	$serverName = ".\RAEVIN"; 
	$connectionInfo = array( "Database"=>"phone_monitoring_db", "UID"=>"sa", "PWD"=>"P@ssw0rd");
	$conn = sqlsrv_connect( $serverName, $connectionInfo);

	date_default_timezone_set('Asia/Manila');
?>
