<?php
	include("config.php");

	$employee_id = $_POST['employee_id'];
	$status      = $_POST['status'];
	$now         = date('Y-m-d H:i:s');

	/* START TRANSACTION */
	if (!sqlsrv_begin_transaction($conn)) {
		die(print_r(sqlsrv_errors(), true));
	}

	// Get last log for this employee (any status)
	$lastStmt = sqlsrv_query($conn, "
		SELECT TOP 1 id, status, logged_at
		FROM logs
		WHERE employee_id = ?
		ORDER BY logged_at DESC
	", [$employee_id]);

	if ($lastStmt === false) {
		sqlsrv_rollback($conn);
		die(print_r(sqlsrv_errors(), true));
	}

	$lastRow = sqlsrv_fetch_array($lastStmt, SQLSRV_FETCH_ASSOC);

	// Update duration of the last log if it exists
	if ($lastRow) {
		$lastTime = $lastRow['logged_at'] instanceof DateTime
			? $lastRow['logged_at']->getTimestamp()
			: strtotime($lastRow['logged_at']);

		$currentTime = strtotime($now);
		$duration = $currentTime - $lastTime;

		$updateStmt = sqlsrv_query($conn, "
			UPDATE logs SET duration = ? WHERE id = ?
		", [$duration, $lastRow['id']]);

		if ($updateStmt === false) {
			sqlsrv_rollback($conn);
			die(print_r(sqlsrv_errors(), true));
		}
	}

	// Insert new log with NULL duration
	$insertStmt = sqlsrv_query($conn, "
		INSERT INTO logs (employee_id, status, logged_at, duration)
		VALUES (?, ?, ?, NULL)
	", [$employee_id, $status, $now]);

	if ($insertStmt === false) {
		sqlsrv_rollback($conn);
		die(print_r(sqlsrv_errors(), true));
	}

	sqlsrv_commit($conn);
?>
