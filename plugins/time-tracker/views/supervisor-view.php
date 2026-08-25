<?php
// Supervisor View with permissions checks, multi-view filters, summary cards, edit tasks modal/inline support
$users = TimeTrackerModel::getAllUsers();
$items = TimeTrackerModel::getItems();
$teams = TimeTrackerModel::getTeams();

$activeTab = $_GET['tab'] ?? 'users'; // 'users', 'projects', 'categories', 'all_tasks'

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

// Aggregate hours by category
$hoursByCategory = [
    'project' => 0.0,
    'support' => 0.0,
    'maintenance' => 0.0
];
foreach ($allTasks as $t) {
    if (isset($hoursByCategory[$t['item_category']])) {
        $hoursByCategory[$t['item_category']] += (float)$t['hours'];
    }
}

// Aggregate hours by user
$userStats = [];
foreach ($allTasks as $t) {
    $uid = $t['user_id'];
    if (!isset($userStats[$uid])) {
        $userStats[$uid] = [
            'user_id' => $uid,
            'name' => $t['user_name'],
            'total_hours' => 0.0,
            'tasks_count' => 0,
            'project_hours' => 0.0,
            'support_hours' => 0.0,
            'maintenance_hours' => 0.0
        ];
    }
    $userStats[$uid]['total_hours'] += (float)$t['hours'];
    $userStats[$uid]['tasks_count']++;
    if (isset($userStats[$uid][$t['item_category'] . '_hours'])) {
        $userStats[$uid][$t['item_category'] . '_hours'] += (float)$t['hours'];
    }
}

// Supervisor Edit Task state
$editSupervisorTask = null;
if (isset($_GET['supervisor_edit_task'])) {
    $editSupervisorTask = TimeTrackerModel::getTaskById((int)$_GET['supervisor_edit_task']);
}
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-user-shield text-danger me-2"></i> Supervisor Management Console</h3>
            <p class="text-muted small mb-0">Overview of team logged hours, project progress, category breakdowns, and task auditing.</p>
        </div>
        <div>
            <a href="index.php?route=time_tracker" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Flash messages -->
    <?php if (isset($_SESSION['tt_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> <?= htmlspecialchars($_SESSION['tt_success']) ?>
            <?php unset($_SESSION['tt_success']); ?>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['tt_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i> <?= htmlspecialchars($_SESSION['tt_error']) ?>
            <?php unset($_SESSION['tt_error']); ?>
        </div>
    <?php endif; ?>

    <!-- Supervisor Edit Task Card (if requested) -->
    <?php if ($editSupervisorTask): ?>
        <div class="card border-warning shadow-sm mb-4">
            <div class="card-header bg-warning text-dark fw-bold d-flex justify-content-between align-items-center">
                <span><i class="fa-solid fa-user-gear me-2"></i> Supervisor Editing User Task Entry (#<?= $editSupervisorTask['id'] ?>)</span>
                <a href="index.php?route=time_tracker_supervisor&tab=<?= $activeTab ?>" class="btn-close"></a>
            </div>
            <div class="card-body">
                <form action="index.php?route=time_tracker_supervisor&tab=<?= $activeTab ?>" method="POST" class="row g-3">
                    <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                    <input type="hidden" name="action" value="save_task">
                    <input type="hidden" name="task_id" value="<?= $editSupervisorTask['id'] ?>">

                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Assigned User</label>
                        <select name="user_id" class="form-select" required>
                            <?php foreach ($users as $u): ?>
                                <option value="<?= $u['id'] ?>" <?= ($editSupervisorTask['user_id'] == $u['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($u['display_name'] ?? $u['email']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fw-bold small">Task Description</label>
                        <input type="text" name="task_name" class="form-control" value="<?= htmlspecialchars($editSupervisorTask['task_name']) ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label fw-bold small">Applied Item / Category</label>
                        <select name="item_id" class="form-select" required>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= $item['id'] ?>" <?= ($editSupervisorTask['item_id'] == $item['id']) ? 'selected' : '' ?>>
                                    [<?= ucfirst($item['category']) ?>] <?= htmlspecialchars($item['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-1">
                        <label class="form-label fw-bold small">Hours</label>
                        <input type="number" step="0.25" min="0.1" name="hours" class="form-control" value="<?= htmlspecialchars($editSupervisorTask['hours']) ?>" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fw-bold small">Date & Time</label>
                        <input type="datetime-local" name="entry_datetime" class="form-control" value="<?= date('Y-m-d\TH:i', strtotime($editSupervisorTask['entry_datetime'])) ?>" required>
                    </div>

                    <div class="col-12 text-end">
                        <a href="index.php?route=time_tracker_supervisor&tab=<?= $activeTab ?>" class="btn btn-secondary btn-sm me-2"><i class="fa-solid fa-xmark me-1"></i> Cancel</a>
                        <button type="submit" class="btn btn-warning btn-sm fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Save Supervisor Override</button>
                    </div>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Summary Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-primary">
                <div class="card-body">
                    <small class="text-muted text-uppercase fw-bold">Total Hours Logged</small>
                    <h3 class="fw-bold text-primary mb-0 mt-1"><?= number_format($totalHoursLogged, 2) ?> hrs</h3>
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
                    <small class="text-muted text-uppercase fw-bold">Support Hours</small>
                    <h3 class="fw-bold text-info mb-0 mt-1"><?= number_format($hoursByCategory['support'], 2) ?> hrs</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 border-start border-4 border-warning">
                <div class="card-body">
                    <small class="text-muted text-uppercase fw-bold">Maintenance Hours</small>
                    <h3 class="fw-bold text-warning mb-0 mt-1"><?= number_format($hoursByCategory['maintenance'], 2) ?> hrs</h3>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter Form -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body bg-light py-3">
            <form action="index.php" method="GET" class="row g-2 align-items-end">
                <input type="hidden" name="route" value="time_tracker_supervisor">
                <input type="hidden" name="tab" value="<?= htmlspecialchars($activeTab) ?>">

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

                <div class="col-md-3">
                    <label class="form-label small fw-bold">Category</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <option value="project" <?= $filterCategory === 'project' ? 'selected' : '' ?>>Projects</option>
                        <option value="support" <?= $filterCategory === 'support' ? 'selected' : '' ?>>Support Activities</option>
                        <option value="maintenance" <?= $filterCategory === 'maintenance' ? 'selected' : '' ?>>Maintenance Activities</option>
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
                    <button type="submit" class="btn btn-sm btn-dark flex-grow-1"><i class="fa-solid fa-filter me-1"></i> Apply Filter</button>
                    <a href="index.php?route=time_tracker_supervisor&tab=<?= $activeTab ?>" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Supervisor Tabs -->
    <ul class="nav nav-tabs mb-4">
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'users' ? 'active fw-bold' : '' ?>" href="index.php?route=time_tracker_supervisor&tab=users&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>&user_id=<?= $filterUserId ?>">
                <i class="fa-solid fa-users me-1"></i> User Summary View
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'projects' ? 'active fw-bold' : '' ?>" href="index.php?route=time_tracker_supervisor&tab=projects&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                <i class="fa-solid fa-diagram-project me-1"></i> Project Progress View
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'categories' ? 'active fw-bold' : '' ?>" href="index.php?route=time_tracker_supervisor&tab=categories&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                <i class="fa-solid fa-chart-pie me-1"></i> Category Breakdown View
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= $activeTab === 'all_tasks' ? 'active fw-bold' : '' ?>" href="index.php?route=time_tracker_supervisor&tab=all_tasks&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>">
                <i class="fa-solid fa-list-check me-1"></i> Detailed Tasks Audit View
            </a>
        </li>
    </ul>

    <!-- Tab 1: User Summary View -->
    <?php if ($activeTab === 'users'): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User Name</th>
                                <th>Total Logged Hours</th>
                                <th>Project Hours</th>
                                <th>Support Hours</th>
                                <th>Maintenance Hours</th>
                                <th>Total Tasks Logged</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($userStats)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No user time tracking data recorded for this period.</td></tr>
                            <?php else: ?>
                                <?php foreach ($userStats as $us): ?>
                                    <tr>
                                        <td class="fw-bold"><i class="fa-solid fa-user me-2 text-primary"></i> <?= htmlspecialchars($us['name']) ?></td>
                                        <td class="fw-bold text-success"><?= number_format($us['total_hours'], 2) ?> hrs</td>
                                        <td><?= number_format($us['project_hours'], 2) ?> hrs</td>
                                        <td><?= number_format($us['support_hours'], 2) ?> hrs</td>
                                        <td><?= number_format($us['maintenance_hours'], 2) ?> hrs</td>
                                        <td><span class="badge bg-secondary"><?= $us['tasks_count'] ?> tasks</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 2: Project Progress View -->
    <?php if ($activeTab === 'projects'): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Project / Item Name</th>
                                <th>Category</th>
                                <th>Project Lead</th>
                                <th>Estimated Hours</th>
                                <th>Actual Hours Logged</th>
                                <th style="width: 25%;">Progress vs Estimate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($items)): ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No projects found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($items as $item):
                                    $est = $item['estimated_hours'];
                                    $act = $item['actual_hours'];
                                    $pct = ($est && $est > 0) ? min(100, round(($act / $est) * 100)) : 0;
                                    $barClass = 'bg-primary';
                                    if ($est && $act > $est) $barClass = 'bg-danger';
                                    elseif ($pct >= 80) $barClass = 'bg-warning';
                                ?>
                                    <tr>
                                        <td class="fw-bold text-dark"><?= htmlspecialchars($item['name']) ?></td>
                                        <td><span class="badge bg-secondary"><?= ucfirst($item['category']) ?></span></td>
                                        <td><i class="fa-solid fa-user-tie text-muted me-1"></i> <?= htmlspecialchars($item['lead_user_name']) ?></td>
                                        <td><?= $est !== null ? number_format($est, 2) . ' hrs' : '<span class="text-muted small">N/A</span>' ?></td>
                                        <td class="fw-bold text-success"><?= number_format($act, 2) ?> hrs</td>
                                        <td>
                                            <?php if ($est && $est > 0): ?>
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="progress flex-grow-1" style="height: 12px;">
                                                        <div class="progress-bar <?= $barClass ?>" role="progressbar" style="width: <?= $pct ?>%;"></div>
                                                    </div>
                                                    <small class="fw-bold"><?= $pct ?>%</small>
                                                </div>
                                            <?php else: ?>
                                                <small class="text-muted">No estimate defined</small>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Tab 3: Category Breakdown View -->
    <?php if ($activeTab === 'categories'): ?>
        <div class="row g-4">
            <?php
            $cats = [
                'project' => ['title' => 'Projects Overview', 'color' => 'primary'],
                'support' => ['title' => 'Support Activities Overview', 'color' => 'info'],
                'maintenance' => ['title' => 'Maintenance Activities Overview', 'color' => 'warning']
            ];
            foreach ($cats as $ckey => $cdata):
                $cItems = array_filter($items, function($i) use ($ckey) { return $i['category'] === $ckey; });
                $cTotal = array_sum(array_column($cItems, 'actual_hours'));
            ?>
                <div class="col-md-4">
                    <div class="card shadow-sm border-0 border-top border-4 border-<?= $cdata['color'] ?>">
                        <div class="card-header bg-light py-3 d-flex justify-content-between align-items-center">
                            <h6 class="fw-bold mb-0 text-dark"><?= $cdata['title'] ?></h6>
                            <span class="badge bg-<?= $cdata['color'] ?> fs-6"><?= number_format($cTotal, 2) ?> hrs</span>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                <?php if (empty($cItems)): ?>
                                    <li class="list-group-item text-muted small py-3 text-center">No items logged in this category.</li>
                                <?php else: ?>
                                    <?php foreach ($cItems as $ci): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-center">
                                            <div>
                                                <div class="fw-bold small"><?= htmlspecialchars($ci['name']) ?></div>
                                                <small class="text-muted">Lead: <?= htmlspecialchars($ci['lead_user_name']) ?></small>
                                            </div>
                                            <span class="fw-bold text-success"><?= number_format($ci['actual_hours'], 2) ?> hrs</span>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Tab 4: Detailed Tasks Audit View (with Supervisor Editing) -->
    <?php if ($activeTab === 'all_tasks'): ?>
        <div class="card shadow-sm border-0">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>User</th>
                                <th>Date & Time</th>
                                <th>Category</th>
                                <th>Project / Item</th>
                                <th>Task Description</th>
                                <th>Hours</th>
                                <th class="text-end">Supervisor Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allTasks)): ?>
                                <tr><td colspan="7" class="text-center py-4 text-muted">No task entries matching current filters.</td></tr>
                            <?php else: ?>
                                <?php foreach ($allTasks as $task):
                                    $bClass = 'bg-secondary';
                                    if ($task['item_category'] === 'project') $bClass = 'bg-primary';
                                    elseif ($task['item_category'] === 'support') $bClass = 'bg-info text-dark';
                                    elseif ($task['item_category'] === 'maintenance') $bClass = 'bg-warning text-dark';
                                ?>
                                    <tr>
                                        <td class="fw-bold"><i class="fa-solid fa-user text-secondary me-1"></i> <?= htmlspecialchars($task['user_name']) ?></td>
                                        <td>
                                            <div class="fw-bold small"><?= date('M d, Y', strtotime($task['entry_datetime'])) ?></div>
                                            <small class="text-muted"><?= date('h:i A', strtotime($task['entry_datetime'])) ?></small>
                                        </td>
                                        <td><span class="badge <?= $bClass ?>"><?= ucfirst($task['item_category'] ?? 'N/A') ?></span></td>
                                        <td class="fw-bold"><?= htmlspecialchars($task['item_name'] ?? 'Unassigned') ?></td>
                                        <td><?= htmlspecialchars($task['task_name']) ?></td>
                                        <td class="fw-bold text-success"><?= number_format($task['hours'], 2) ?> hrs</td>
                                        <td class="text-end">
                                            <a href="index.php?route=time_tracker_supervisor&tab=all_tasks&supervisor_edit_task=<?= $task['id'] ?>&start_date=<?= $startDate ?>&end_date=<?= $endDate ?>" class="btn btn-sm btn-outline-warning me-1" title="Supervisor Edit">
                                                <i class="fa-solid fa-pen-to-square me-1"></i> Edit
                                            </a>
                                            <form action="index.php?route=time_tracker_supervisor&tab=all_tasks" method="POST" class="d-inline" onsubmit="return confirm('Supervisor override: Are you sure you want to delete this task?');">
                                                <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                                                <input type="hidden" name="action" value="delete_task">
                                                <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Supervisor Delete">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>
