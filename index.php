<?php
	include("executes/config.php");

	$log_date_from = $_GET['log_date_from'] ?? date('Y-m-d');

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
			.emp-btn { height: 80px; font-size: 1.25rem; font-weight: 600; }
			.log-container { max-height: 220px; overflow-y: auto; }
			.table th { position: sticky; top: 0; background: #0dcaf0; z-index: 2; }
		</style>
	</head>
	<body>

		<h1 class="text-center mb-5 text-uppercase">
			Phone Monitoring App
			<br><small style="font-size:21px;">Logs on <?= (new DateTime($_GET['log_date_from'] ?? date('Y-m-d')))->format('F d, Y') ?></small>
		</h1>

		<div class="card shadow-sm mb-2">
    		<div class="card-body">
   
				<div class="d-flex justify-content-between align-items-center flex-wrap gap-3">
					<div class="col-12">
						<div class="d-flex justify-content-between align-items-center flex-wrap gap-2">


							<div class="d-flex align-items-center gap-2">
								<label for="logDateFrom" class="fw-semibold mb-0">Logs Date:</label>

								<input type="date" id="logDateFrom" class="form-control" style="width: 170px" value="<?= $_GET['log_date_from'] ?? date('Y-m-d') ?>">

								<a href="index.php" class="btn btn-outline-secondary btn-sm">Today</a>
								<a href="reports.php" class="btn btn-outline-secondary btn-sm">Go to Reports</a>
							</div>

							<div class="form-group">
								<button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEmployeeModal">+ Add Employee</button>
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
							if ($status === 'IN') $btnClass = 'btn-success';
							elseif ($status === 'OUT') $btnClass = 'btn-danger';
						?>
							<div class="col-xl-2 col-lg-4 col-md-6">
								<div class="card emp-card h-100">
									<div class="card-header text-end p-1">
										<button class="btn btn-transparent btn-sm edit-emp-btn" style="font-size:10px;" data-id="<?= $emp['id'] ?>" data-name="<?= htmlspecialchars($emp['name']) ?>" data-bs-toggle="modal" data-bs-target="#editEmployeeModal">
											✎ EDIT NAME
										</button>

										<button class="btn btn-transparent text-danger btn-sm delete-emp-btn" style="font-size:10px;" data-bs-toggle="modal" data-bs-target="#deleteEmployeeModal" onclick="document.getElementById('deleteEmpButton').href='executes/employee.php?action=delete&id=<?= $emp['id'] ?>'">
											X DELETE
										</button>
									</div>
									<div class="card-body text-center">

										<button class="btn <?= $btnClass ?> btn-lg emp-btn w-100 mb-3"
												data-id="<?= $emp['id'] ?>"
												data-status="<?= $status ?? 'OUT' ?>" <?= (($_GET['log_date_from'] ?? date('Y-m-d')) !== date('Y-m-d')) ? 'disabled' : '' ?>>
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


		<!-- MODALS -->
		 
		<!-- Create Employee Modal -->
		<div class="modal fade" id="createEmployeeModal" tabindex="-1">
			<div class="modal-dialog">
				<form action="executes/employee.php" method="POST" class="modal-content">
					<input type="hidden" name="action" value="create">
					<div class="modal-header">
						<h5>Add Employee</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<input type="text" name="name" class="form-control mb-2" placeholder="Employee Name" required>
					</div>
					<div class="modal-footer">
						<button class="btn btn-primary">Create</button>
						<button class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Edit Employee Modal -->
		<div class="modal fade" id="editEmployeeModal" tabindex="-1">
			<div class="modal-dialog">
				<form action="executes/employee.php" method="POST" class="modal-content">
					<input type="hidden" name="action" value="update">
					<input type="hidden" name="id" id="editEmployeeId">
					<div class="modal-header">
						<h5>Edit Employee</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						<input type="text" name="name" id="editEmployeeName" class="form-control" required>
					</div>
					<div class="modal-footer">
						<button class="btn btn-primary">Save</button>
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					</div>
				</form>
			</div>
		</div>

		<!-- Delete Employee Modal -->
		<div class="modal fade" id="deleteEmployeeModal" tabindex="-1">
			<div class="modal-dialog">
				<form action="executes/employee.php" method="POST" class="modal-content">
					<input type="hidden" name="action" value="update">
					<input type="hidden" name="id" id="editEmployeeId">
					<div class="modal-header">
						<h5>Warning</h5>
						<button type="button" class="btn-close" data-bs-dismiss="modal"></button>
					</div>
					<div class="modal-body">
						Are you sure you want to delete this employee?
					</div>
					<div class="modal-footer">
						<a id="deleteEmpButton" href="#" class="btn btn-danger">Confirm</a>
						<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
					</div>
				</form>
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
						$('#logs-' + empId).html(html);
					});
				}

				// Load all logs on page load
				$('.emp-btn').each(function () {
					loadLogs($(this).data('id'));
				});

				$('#logDateFrom, #logDateTo').on('change', function () {
					const from = $('#logDateFrom').val();
					const to   = $('#logDateTo').val();

					window.location.href = `index.php?log_date_from=${from}&log_date_to=${to}`;
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
