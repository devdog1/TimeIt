<?php
// Timesheet Approval & Print View
$users = TimeTrackerModel::getAllUsers();
$teams = TimeTrackerModel::getTeams();

$selectedUserId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : ($_SESSION['user_id'] ?? 1);
$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('monday this week'));
$endDate = $_GET['end_date'] ?? date('Y-m-d', strtotime('sunday this week'));

$tasks = TimeTrackerModel::getTasks($selectedUserId, $startDate, $endDate);
$selectedUserName = TimeTrackerModel::getUserName($selectedUserId);

$totalHours = array_sum(array_column($tasks, 'hours'));
$billableHours = 0.0;
$overtimeHours = 0.0;

foreach ($tasks as $t) {
    if (!empty($t['is_billable'])) $billableHours += (float)$t['hours'];
    if (!empty($t['is_overtime'])) $overtimeHours += (float)$t['hours'];
}
?>

<div class="container-fluid py-3">
    <!-- Header (Hidden when printing) -->
    <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-file-signature text-primary me-2"></i> Employee Timesheet & Approval</h3>
            <p class="text-muted small mb-0">Official weekly/monthly timesheet document with signature lines for supervisor approval.</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print();" class="btn btn-primary btn-sm">
                <i class="fa-solid fa-print me-1"></i> Print / Save PDF
            </button>
            <a href="index.php?route=time_tracker" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Timesheet Filter (Hidden when printing) -->
    <div class="card shadow-sm border-0 mb-4 d-print-none">
        <div class="card-body bg-light py-3">
            <form action="index.php" method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="route" value="time_tracker_timesheet">

                <div class="col-md-4">
                    <label class="form-label small fw-bold">Select Employee</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $selectedUserId == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['display_name'] ?? $u['email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold">Period Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label small fw-bold">Period End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-dark flex-grow-1"><i class="fa-solid fa-filter me-1"></i> Generate</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Official Printable Timesheet Sheet -->
    <div class="card shadow border-0 p-4 bg-white" id="timesheetPrintArea">
        <div class="border-bottom pb-3 mb-4 d-flex justify-content-between align-items-center">
            <div>
                <h2 class="fw-bold text-dark mb-0">IT SUPPORT TIMESHEET</h2>
                <span class="text-muted small">Official Time & Activity Record</span>
            </div>
            <div class="text-end">
                <h5 class="fw-bold text-primary mb-1"><?= htmlspecialchars($selectedUserName) ?></h5>
                <span class="badge bg-secondary">Period: <?= date('M d, Y', strtotime($startDate)) ?> &mdash; <?= date('M d, Y', strtotime($endDate)) ?></span>
            </div>
        </div>

        <!-- Metric Summary Cards -->
        <div class="row g-3 mb-4">
            <div class="col-4">
                <div class="p-3 border rounded text-center bg-light">
                    <small class="text-muted text-uppercase fw-bold d-block">Total Hours Logged</small>
                    <span class="fs-4 fw-bold text-dark"><?= number_format($totalHours, 2) ?> hrs</span>
                </div>
            </div>
            <div class="col-4">
                <div class="p-3 border rounded text-center bg-light">
                    <small class="text-muted text-uppercase fw-bold d-block">Billable Hours</small>
                    <span class="fs-4 fw-bold text-success"><?= number_format($billableHours, 2) ?> hrs</span>
                </div>
            </div>
            <div class="col-4">
                <div class="p-3 border rounded text-center bg-light">
                    <small class="text-muted text-uppercase fw-bold d-block">Overtime Hours</small>
                    <span class="fs-4 fw-bold text-warning"><?= number_format($overtimeHours, 2) ?> hrs</span>
                </div>
            </div>
        </div>

        <!-- Task Table -->
        <table class="table table-bordered align-middle mb-4">
            <thead class="table-dark">
                <tr>
                    <th>Date & Time</th>
                    <th>Ticket Ref #</th>
                    <th>Category</th>
                    <th>Item / Project</th>
                    <th>Task Description</th>
                    <th class="text-center">Billable</th>
                    <th class="text-end">Hours</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($tasks)): ?>
                    <tr><td colspan="7" class="text-center py-4 text-muted">No time entries recorded for this employee during the selected period.</td></tr>
                <?php else: ?>
                    <?php foreach ($tasks as $t): ?>
                        <tr>
                            <td>
                                <div><?= date('M d, Y', strtotime($t['entry_datetime'])) ?></div>
                                <small class="text-muted"><?= date('h:i A', strtotime($t['entry_datetime'])) ?></small>
                            </td>
                            <td><code><?= !empty($t['ticket_ref']) ? htmlspecialchars($t['ticket_ref']) : '&mdash;' ?></code></td>
                            <td><span class="badge bg-secondary"><?= ucfirst($t['item_category'] ?? '') ?></span></td>
                            <td class="fw-bold"><?= htmlspecialchars($t['item_name'] ?? 'Unassigned') ?></td>
                            <td><?= htmlspecialchars($t['task_name']) ?></td>
                            <td class="text-center">
                                <?= !empty($t['is_billable']) ? '<span class="text-success fw-bold">Yes</span>' : '<span class="text-muted">No</span>' ?>
                                <?= !empty($t['is_overtime']) ? ' <span class="badge bg-warning text-dark">OT</span>' : '' ?>
                            </td>
                            <td class="fw-bold text-end"><?= number_format($t['hours'], 2) ?> h</td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="table-light fw-bold fs-6">
                    <td colspan="6" class="text-end">Total Hours Submitted:</td>
                    <td class="text-end text-success"><?= number_format($totalHours, 2) ?> hrs</td>
                </tr>
            </tfoot>
        </table>

        <!-- Signatures Section -->
        <div class="row pt-4 mt-4 border-top">
            <div class="col-6">
                <div class="border-bottom pb-5 mb-2" style="border-style: dashed !important;"></div>
                <div class="fw-bold">Employee Signature: <?= htmlspecialchars($selectedUserName) ?></div>
                <small class="text-muted">Date: ____ / ____ / ________</small>
            </div>
            <div class="col-6">
                <div class="border-bottom pb-5 mb-2" style="border-style: dashed !important;"></div>
                <div class="fw-bold">Supervisor Approval Signature</div>
                <small class="text-muted">Date: ____ / ____ / ________</small>
            </div>
        </div>
    </div>
</div>
