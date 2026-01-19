<?php
	include("executes/config.php");

	$log_date_from = $_GET['log_date_from'] ?? date('Y-m-d');
	$log_date_to = $_GET['log_date_to'] ?? date('Y-m-d');

	// FOR CARDS DISPLAYING EMPLOYEES
	$sql = "
		SELECT 
			e.id,
			e.name,
			(
				SELECT TOP 1 l.status
				FROM logs l
				WHERE l.employee_id = e.id
				AND l.logged_at >= CAST(? AS DATE)
				AND l.logged_at <  DATEADD(DAY, 1, CAST(? AS DATE))
				ORDER BY l.logged_at DESC
			) AS current_status
		FROM employees e
		WHERE e.deleted_at IS NULL
		ORDER BY e.id DESC
	";

	$params = [$log_date_from, $log_date_from];
	$stmt   = sqlsrv_query($conn, $sql, $params);

	if ($stmt === false) {
		die(print_r(sqlsrv_errors(), true));
	}


	// FOR DURATION SUMMARY TABLE
	$summarySql = "
		WITH DateRange AS (
			SELECT CAST(? AS DATE) AS log_date
			UNION ALL
			SELECT DATEADD(DAY, 1, log_date)
			FROM DateRange
			WHERE log_date < CAST(? AS DATE)
		)
		SELECT
			d.log_date,
			e.name,
			COALESCE(SUM(l.duration), 0) AS total_seconds
		FROM DateRange d
		CROSS JOIN employees e
		LEFT JOIN logs l
			ON l.employee_id = e.id
			AND CAST(l.logged_at AS DATE) = d.log_date
		WHERE e.deleted_at IS NULL
		GROUP BY d.log_date, e.name
		ORDER BY d.log_date DESC, e.name
		OPTION (MAXRECURSION 1000);
	";

	// $summarySql = "
	// 	SELECT 
	// 		CAST(l.logged_at AS DATE) AS log_date,
	// 		e.name,
	// 		SUM(ISNULL(l.duration, 0)) AS total_seconds
	// 	FROM logs l
	// 	JOIN employees e ON e.id = l.employee_id
	// 	WHERE l.logged_at >= CAST(? AS DATE)
	// 	AND l.logged_at < DATEADD(DAY, 1, CAST(? AS DATE))
	// 	GROUP BY CAST(l.logged_at AS DATE), e.name
	// 	ORDER BY log_date DESC, e.name
	// ";

	$summaryParams = [$log_date_from, $log_date_to];
	$summaryStmt   = sqlsrv_query($conn, $summarySql, $summaryParams);

	if ($summaryStmt === false) {
		die(print_r(sqlsrv_errors(), true));
	}

	function formatDuration($seconds)
	{
		$seconds = (int) $seconds;

		if ($seconds <= 0) {
			return 0;
		}

		// Convert everything to minutes
		return floor($seconds / 60);
	}


	// function formatDuration($seconds)
	// {
	// 	$seconds = (int) $seconds;

	// 	if ($seconds <= 0) {
	// 		return '0';
	// 	}

	// 	// Less than 1 minute → seconds
	// 	if ($seconds < 60) {
	// 		return '0';
	// 		// return $seconds . 's';
	// 	}

	// 	// Less than 1 hour → minutes
	// 	if ($seconds < 3600) {
	// 		return floor($seconds / 60);
	// 	}

	// 	// 1 hour or more → HH:MM:SS
	// 	return gmdate('H:i:s', $seconds);
	// }


	// function formatDuration($seconds)
	// {
	// 	if (!$seconds) return '00:00:00';
	// 	return gmdate('H:i:s', (int) $seconds);
	// }

?>

<!DOCTYPE html>
<html lang="en">
	<head>
		<meta charset="UTF-8">
		<title>Phone Monitoring</title>

		<!-- Bootstrap CSS -->
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet">

		<style>
			body { background: #f4f6f9; padding: 40px; }
			.emp-card { border-radius: 14px; box-shadow: 0 4px 12px rgba(0,0,0,.08); }
			.emp-btn { height: 45px; font-size: 1.25rem; font-weight: 600; }
			.log-container { max-height: 450px; overflow-y: auto; }
			.table th { position: sticky; top: 0; background: #0dcaf0; z-index: 2; }
		</style>
	</head>
	<body>

		<h1 class="text-center mb-5 text-uppercase">
			Phone Monitoring Log Reports
			<br><small style="font-size:21px;">Logs on <?= (new DateTime($_GET['log_date_from'] ?? date('Y-m-d')))->format('F d, Y') ?> <?= ($_GET['log_date_from'] ?? date('Y-m-d')) == ($_GET['log_date_to'] ?? date('Y-m-d')) ? '' : 'to ' . (new DateTime($_GET['log_date_to'] ?? date('Y-m-d')))->format('F d, Y') ?></small>
		</h1>

		<div class="card shadow-sm mb-2">
    		<div class="card-body">
   
				<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
					<div class="col-12">
						<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">


							<div class="d-flex align-items-center gap-2">
								<label for="logDateFrom" class="fw-semibold mb-0">Date from</label>
								<input type="date" id="logDateFrom" class="form-control" style="width: 170px" value="<?= $_GET['log_date_from'] ?? date('Y-m-d') ?>">
								<label for="logDateTo" class="fw-semibold mb-0">to</label>
								<input type="date" id="logDateTo" class="form-control" style="width: 170px" value="<?= $_GET['log_date_to'] ?? date('Y-m-d') ?>">

								<a href="reports.php" class="btn btn-outline-secondary btn-sm">Today</a>
							</div>

							<div class="form-group">
								<a href="index.php" class="btn btn-outline-primary btn-sm">Back</a>
							</div>

						</div>
					</div>
				</div>
			
			</div>
		</div>

		<div class="card shadow-sm mb-4">
    		<div class="card-body">
				<div class="container-fluid">

				<div class="card shadow-sm mb-4">
					<div class="card-body">
						<h5 class="mb-3 fw-bold">Out Duration Summary</h5>

						<table class="table table-bordered table-striped">
							<thead class="table-info">
								<tr>
									<th>Date</th>
									<th>Name</th>
									<th>Duration (mins)</th>
								</tr>
							</thead>
							<tbody>
								<?php
								$curr_date = null;

								while ($row = sqlsrv_fetch_array($summaryStmt, SQLSRV_FETCH_ASSOC)):

									$rowDate = $row['log_date']->format('Y-m-d');

									// New date header
									if ($curr_date !== $rowDate):
										$curr_date = $rowDate;
								?>
										<tr class="table-secondary">
											<td colspan="3" class="fw-bold text-center">
												<?= (new DateTime($rowDate))->format('F d, Y') ?>
											</td>
										</tr>
								<?php
									endif;
								?>
									<tr>
										<td><?= $row['log_date']->format('m/d/Y') ?></td>
										<td><?= htmlspecialchars($row['name']) ?></td>
										<td><?= formatDuration($row['total_seconds']) ?></td>
									</tr>
								<?php endwhile; ?>
							</tbody>

						</table>
					</div>
				</div>

					
					<!-- <div class="row g-4">

						<?php while ($emp = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)):
							$status = $emp['current_status'];
							$btnClass = 'btn-secondary';
						?>
							<div class="col-xl-2 col-lg-4 col-md-6">
								<div class="card emp-card h-100">
									<div class="card-header text-start p-1" style="font-size:10px;">
										<table>
											<tr>
												<td>Total Duration IN:</td>
												<td><strong id="total-in-<?= $emp['id'] ?>">00:00:00</strong></td>
											</tr>
											<tr>
												<td>Total Duration OUT:</td>
												<td><strong id="total-out-<?= $emp['id'] ?>">00:00:00</strong></td>
											</tr>
										</table>
									</div>
									<div class="card-body text-center">

										<button class="btn btn-secondary btn-lg emp-btn w-100 mb-3"
												data-id="<?= $emp['id'] ?>" disabled>
											<?= htmlspecialchars($emp['name']) ?>
										</button>

										<div class="log-container">
											<table class="table table-sm table-striped table-bordered mb-0">
												<thead>
													<tr>
														<th>Log</th>
														<th>Duration</th>
													</tr>
												</thead>
												<tbody id="logs-<?= $emp['id'] ?>">
													<tr>
														<td colspan="2" class="text-muted text-center">Loading...</td>
													</tr>
												</tbody>
											</table>
										</div>

									</div>
								</div>
							</div>
						<?php endwhile; ?>

					</div> -->

				</div>

			</div>
		</div>


		<!-- jQuery + Bootstrap JS + Popper -->
		<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>

		<script>
			$(document).ready(function() {
				// Load logs
				function loadLogs(empId) {
					$.get('executes/logs.php', {employee_id: empId, log_date_from: $('#logDateFrom').val(), log_date_to: $('#logDateTo').val() }, function (html) {

						const container = $('#logs-' + empId);
						container.html(html);

						const totals = container.find('.totals-row');

						if (totals.length) {
							container.closest('.emp-card').find('.card-header').html(`
								<table>
									<tr>
										<td>Total Duration OUT:</td>
										<td><strong class="text-danger">${totals.data('out')}</strong></td>
									</tr>
								</table>
							`);
							// container.closest('.emp-card').find('.card-header').html(`
							// 	<table>
							// 		<tr>
							// 			<td>Total Duration IN:</td>
							// 			<td><strong class="text-success">${totals.data('in')}</strong></td>
							// 		</tr>
							// 		<tr>
							// 			<td>Total Duration OUT:</td>
							// 			<td><strong class="text-danger">${totals.data('out')}</strong></td>
							// 		</tr>
							// 	</table>
							// `);
						}
					});
				}

				$('#logDateFrom, #logDateTo').on('change', function () {
					const from = $('#logDateFrom').val();
					const to   = $('#logDateTo').val();

					window.location.href = `reports.php?log_date_from=${from}&log_date_to=${to}`;
				});

			});
		</script>

	</body>
</html>
