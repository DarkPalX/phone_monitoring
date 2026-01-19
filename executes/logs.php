<?php
	include("config.php");

	$employee_id = $_GET['employee_id'] ?? null;
	$log_date_from = $_GET['log_date_from'] ?? date('Y-m-d');
	$log_date_to = $_GET['log_date_to'] ?? $_GET['log_date_from'];
	// $log_date_to = $_GET['log_date_to'] ?? date('Y-m-d');

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
		$log_date_to
	];

	$stmt = sqlsrv_query($conn, $sql, $params);
	
	if ($stmt === false) {
		die(print_r(sqlsrv_errors(), true));
	}

	
	$totalIn = 0;
	$totalOut = 0;

	while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {

		if ($row['duration']) {
			if ($row['status'] === 'IN') {
				$totalIn += (int) $row['duration'];
			} elseif ($row['status'] === 'OUT') {
				$totalOut += (int) $row['duration'];
			}
		}

		echo "
			<tr>
				<td>
					{$row['status']}<br>
					" . $row['logged_at']->format('h:i A') . "<br>
					<small style='font-size:12px;'>". $row['logged_at']->format('m-d-Y') ."</small>
				</td>
				<td>
					" . ($row['duration'] && $row['status'] == 'OUT' ? gmdate('H:i:s', $row['duration']) : '-') . "
				</td>
			</tr>
		";

		// echo "
		// 	<tr>
		// 		<td>
		// 			{$row['status']}<br>
		// 			" . $row['logged_at']->format('h:i A') . "
		// 		</td>
		// 		<td>
		// 			" . ($row['duration'] ? gmdate('H:i:s', $row['duration']) : '-') . "
		// 		</td>
		// 	</tr>
		// ";
	}

	echo "
		<tr class='totals-row d-none'
			data-in='" . gmdate('H:i:s', $totalIn) . "'
			data-out='" . gmdate('H:i:s', $totalOut) . "'>
		</tr>
	";

?>

