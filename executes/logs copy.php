<?php
	include("config.php");

	$employee_id = $_GET['employee_id'] ?? null;
	$log_date_from = $_GET['log_date_from'] ?? date('Y-m-d');
	$log_date_to = $_GET['log_date_to'] ?? date('Y-m-d');

	if (!$employee_id) {
		exit;
	}

	$sql = "
		SELECT status, logged_at, duration
		FROM logs
		WHERE employee_id = ?
		AND logged_at >= CAST(? AS DATE)
		AND logged_at <  DATEADD(DAY, 1, CAST(? AS DATE))
		ORDER BY logged_at DESC
	";

	$params = [
		$employee_id,
		$log_date_from,
		$log_date_from
	];

	$stmt = sqlsrv_query($conn, $sql, $params);
	
	if ($stmt === false) {
		die(print_r(sqlsrv_errors(), true));
	}

	while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
		echo "
			<tr>
				<td>
					{$row['status']}<br>
					" . $row['logged_at']->format('h:i  A') . "
				</td>
				<td>
					" . ($row['duration'] ? gmdate('H:i:s', $row['duration']) : '-') . "
				</td>
			</tr>
		";
	}

	// $lastStatus = null;

	// while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

	// 	// skip duplicates
	// 	if ($row['status'] === $lastStatus) {
	// 		continue;
	// 	}

	// 	$lastStatus = $row['status'];

	// 	$duration = '-';
	// 	if ($row['duration'] !== null) {
	// 		$duration = gmdate('H:i:s', $row['duration']);
	// 	}

	// 	echo "
	// 		<tr>
	// 			<td>
	// 				{$row['status']}<br>
	// 				" . $row['logged_at']->format('h:i A') . "
	// 			</td>
	// 			<td>{$duration}</td>
	// 		</tr>
	// 	";
	// }

?>

