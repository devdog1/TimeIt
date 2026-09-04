<?php
// Finance View (Read-Only)
$users = TimeTrackerModel::getAllUsers();
$items = TimeTrackerModel::getItems();
$teams = TimeTrackerModel::getTeams();
$enabledCategories = TimeTrackerModel::getCategories(true);

$filterUserId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;
$filterCategory = isset($_GET['category']) && $_GET['category'] !== '' ? $_GET['category'] : null;
$filterItemId = isset($_GET['item_id']) && $_GET['item_id'] !== '' ? (int)$_GET['item_id'] : null;
$filterTeamId = isset($_GET['team_id']) && $_GET['team_id'] !== '' ? (int)$_GET['team_id'] : null;

$availableFys = TimeTrackerModel::getAvailableFinancialYears();
$selectedFy = isset($_GET['fy_year']) && $_GET['fy_year'] !== '' ? (int)$_GET['fy_year'] : null;

if ($selectedFy) {
    $fyRange = TimeTrackerModel::getFinancialYearDateRange($selectedFy);
    $startDate = $_GET['start_date'] ?? $fyRange['start_date'];
    $endDate = $_GET['end_date'] ?? $fyRange['end_date'];
} else {
    $startDate = $_GET['start_date'] ?? date('Y-m-01');
    $endDate = $_GET['end_date'] ?? date('Y-m-t');
}

$allTasks = TimeTrackerModel::getTasks($filterUserId, $startDate, $endDate, $filterItemId, $filterCategory, $filterTeamId);
$totalHoursLogged = array_sum(array_column($allTasks, 'hours'));

// Aggregate category hours
$hoursByCategory = ['project' => 0.0, 'support' => 0.0, 'maintenance' => 0.0];
foreach ($allTasks as $t) {
    if (isset($hoursByCategory[$t['item_category']])) {
        $hoursByCategory[$t['item_category']] += (float)$t['hours'];
    }
}

$exportCsvUrl = "index.php?route=time_tracker_finance&export_csv=1&start_date={$startDate}&end_date={$endDate}&user_id={$filterUserId}&category={$filterCategory}&item_id={$filterItemId}&team_id={$filterTeamId}";
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-file-invoice-dollar text-success me-2"></i> Finance View (Read-Only)</h3>
            <p class="text-muted small mb-0">Financial audit view of all project hours, support time, maintenance activity, and team allocations.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= htmlspecialchars($exportCsvUrl) ?>" class="btn btn-success btn-sm">
                <i class="fa-solid fa-file-csv me-1"></i> Export Report to CSV
            </a>
        </div>
    </div>

    <div class="alert alert-info py-2 small d-flex align-items-center mb-4">
        <i class="fa-solid fa-lock me-2 fs-5"></i>
        <span><strong>Read-Only Access:</strong> Finance mode allows viewing complete time allocation metrics across projects and teams. Task modification capabilities are restricted to Supervisors.</span>
    </div>

    <!-- Summary Metrics -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-success">
                <div class="card-body">
                    <small class="text-muted text-uppercase fw-bold">Total Billable/Logged Hours</small>
                    <h3 class="fw-bold text-success mb-0 mt-1"><?= number_format($totalHoursLogged, 2) ?> hrs</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-primary">
                <div class="card-body">
                    <small class="text-muted text-uppercase fw-bold">Project Hours</small>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($hoursByCategory['project'], 2) ?> hrs</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-info">
                <div class="card-body">
                    <small class="text-muted text-uppercase fw-bold">Support Activity Hours</small>
                    <h3 class="fw-bold text-info mb-0 mt-1"><?= number_format($hoursByCategory['support'], 2) ?> hrs</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-warning">
                <div class="card-body">
                    <small class="text-muted text-uppercase fw-bold">Maintenance Activity Hours</small>
                    <h3 class="fw-bold text-warning mb-0 mt-1"><?= number_format($hoursByCategory['maintenance'], 2) ?> hrs</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body bg-light py-3">
            <form action="index.php" method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="route" value="time_tracker_finance">

                <div class="col-md-2">
                    <label class="form-label small fw-bold">Financial Year</label>
                    <select name="fy_year" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Custom Date Range</option>
                        <?php foreach ($availableFys as $fy): ?>
                            <option value="<?= $fy ?>" <?= $selectedFy == $fy ? 'selected' : '' ?>>FY <?= $fy ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">Team</label>
                    <select name="team_id" class="form-select form-select-sm">
                        <option value="">All Teams</option>
                        <?php foreach ($teams as $t): ?>
                            <option value="<?= $t['id'] ?>" <?= $filterTeamId == $t['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">User</label>
                    <select name="user_id" class="form-select form-select-sm">
                        <option value="">All Users</option>
                        <?php foreach ($users as $u): ?>
                            <option value="<?= $u['id'] ?>" <?= $filterUserId == $u['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($u['display_name'] ?? $u['email']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <?php foreach ($enabledCategories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat['slug']) ?>" <?= $filterCategory === $cat['slug'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($cat['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label small fw-bold">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
                </div>

                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-dark flex-grow-1"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                    <a href="index.php?route=time_tracker_finance" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Detailed Audit Table -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-receipt me-2"></i> Finance Task Allocation Audit Log</h5>
            <span class="badge bg-secondary"><?= count($allTasks) ?> Records</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Task ID</th>
                            <th>User Name</th>
                            <th>Date & Time</th>
                            <th>Category</th>
                            <th>Applied Item / Project</th>
                            <th>Task Description</th>
                            <th class="text-end">Logged Hours</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($allTasks)): ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No time tracking data records found for this period.</td></tr>
                        <?php else: ?>
                            <?php foreach ($allTasks as $task):
                                $bClass = 'bg-secondary';
                                if ($task['item_category'] === 'project') $bClass = 'bg-primary';
                                elseif ($task['item_category'] === 'support') $bClass = 'bg-info text-dark';
                                elseif ($task['item_category'] === 'maintenance') $bClass = 'bg-warning text-dark';
                            ?>
                                <tr>
                                    <td><code>#<?= $task['id'] ?></code></td>
                                    <td class="fw-bold"><i class="fa-solid fa-user me-1 text-secondary"></i> <?= htmlspecialchars($task['user_name']) ?></td>
                                    <td>
                                        <div class="fw-bold small"><?= date('M d, Y', strtotime($task['entry_datetime'])) ?></div>
                                        <small class="text-muted"><?= date('h:i A', strtotime($task['entry_datetime'])) ?></small>
                                    </td>
                                    <td><span class="badge <?= $bClass ?>"><?= ucfirst($task['item_category'] ?? 'N/A') ?></span></td>
                                    <td class="fw-bold"><?= htmlspecialchars($task['item_name'] ?? 'Unassigned') ?></td>
                                    <td><?= htmlspecialchars($task['task_name']) ?></td>
                                    <td class="fw-bold text-success text-end"><?= number_format($task['hours'], 2) ?> hrs</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
