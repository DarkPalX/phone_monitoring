<?php
	include("executes/config.php");

	$log_date_from = $_GET['log_date_from'] ?? date('Y-m-d');
	$log_date_to = $_GET['log_date_to'] ?? date('Y-m-d');

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
		ORDER BY e.id DESC
	";

	$params = [$log_date_from, $log_date_from];
	$stmt   = sqlsrv_query($conn, $sql, $params);

	if ($stmt === false) {
		die(print_r(sqlsrv_errors(), true));
	}
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
					<div class="row g-4">

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

					</div>
				</div>

			</div>
		</div>


		<!-- jQuery + Bootstrap JS + Popper -->
		<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.min.js"></script>

		<script>
			$(document).ready(function() {
				$(document).on('click', '.emp-btn', function (e) {
					e.preventDefault();
					e.stopPropagation();

					let btn = $(this);

					if (btn.data('busy')) return;
					btn.data('busy', true);

					let empId  = btn.data('id');
					let status = btn.data('status');
					let newStatus = (status === 'OUT') ? 'IN' : 'OUT';

					btn.data('status', newStatus);
					btn.removeClass('btn-secondary btn-success btn-danger')
					.addClass(newStatus === 'IN' ? 'btn-success' : 'btn-danger');

					$.post('executes/logs_controller.php', { employee_id: empId, status: newStatus })
						.done(function () {
							loadLogs(empId);
						})
						.always(function () {
							btn.data('busy', false);
						});
				});

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
										<td>Total Duration IN:</td>
										<td><strong class="text-success">${totals.data('in')}</strong></td>
									</tr>
									<tr>
										<td>Total Duration OUT:</td>
										<td><strong class="text-danger">${totals.data('out')}</strong></td>
									</tr>
								</table>
							`);
						}
					});
				}

				// Load all logs on page load
				$('.emp-btn').each(function () {
					loadLogs($(this).data('id'));
				});

				$('#logDateFrom, #logDateTo').on('change', function () {
					const from = $('#logDateFrom').val();
					const to   = $('#logDateTo').val();

					window.location.href = `reports.php?log_date_from=${from}&log_date_to=${to}`;
				});

				// Populate edit modal
				$(document).on('click', '.edit-emp-btn', function () {
					$('#editEmployeeId').val($(this).data('id'));
					$('#editEmployeeName').val($(this).data('name'));
				});
			});
		</script>

	</body>
</html>
