<?php
$userId = $_SESSION['user_id'] ?? 0;
$items = TimeTrackerModel::getItems();
$activeTask = TimeTrackerModel::getActiveTaskForUser($userId);

// Filter parameters
$catFilter = $_GET['category'] ?? '';
$startDate = $_GET['start_date'] ?? date('Y-m-01');
$endDate = $_GET['end_date'] ?? date('Y-m-t');

$enableBillableOvertime = TimeTrackerModel::getSetting('enable_billable_overtime', '1');

$tasks = TimeTrackerModel::getTasks($userId, $startDate, $endDate, null, $catFilter);
$totalHours = array_sum(array_column($tasks, 'hours'));

// Check if editing a task
$editTask = null;
if (isset($_GET['edit_task'])) {
    $editTask = TimeTrackerModel::getTaskById((int)$_GET['edit_task']);
    if ($editTask && $editTask['user_id'] != $userId && !has_permission('time_tracker_supervisor_access') && !has_role('administrator')) {
        $editTask = null;
    }
}
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-clock text-primary me-2"></i> Time Tracker</h3>
            <p class="text-muted small mb-0">Track hours spent on IT projects, support tickets, and maintenance activities.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=time_tracker_calendar" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-calendar-days me-1"></i> Calendar View
            </a>
            <a href="index.php?route=time_tracker_items" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-folder-tree me-1"></i> Manage Projects & Categories
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

    <!-- Running Active Task Timer Widget (if any) -->
    <?php if ($activeTask):
        $startTs = strtotime($activeTask['entry_datetime']);
        $elapsedSecs = max(0, time() - $startTs);
    ?>
        <div class="card shadow-sm mb-4 border-2 border-primary bg-primary-subtle">
            <div class="card-body py-3 d-flex flex-wrap justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <div class="spinner-grow text-primary" role="status">
                        <span class="visually-hidden">Running...</span>
                    </div>
                    <div>
                        <span class="badge bg-primary text-uppercase mb-1">Active Timer Running</span>
                        <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($activeTask['task_name']) ?></h5>
                        <small class="text-muted">Item: <strong><?= htmlspecialchars($activeTask['item_name']) ?></strong> &bull; Started at <?= date('h:i A', $startTs) ?></small>
                    </div>
                </div>

                <div class="d-flex align-items-center gap-3 mt-2 mt-md-0">
                    <div class="text-end me-2">
                        <small class="text-uppercase fw-bold text-muted d-block">Elapsed Time</small>
                        <span id="activeTimerClock" class="fw-bold fs-4 text-primary">00:00:00</span>
                    </div>

                    <form action="index.php?route=time_tracker" method="POST" class="d-inline">
                        <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                        <input type="hidden" name="action" value="checkin_response">
                        <input type="hidden" name="task_id" value="<?= $activeTask['id'] ?>">
                        <input type="hidden" name="checkin_action" value="still_working">
                        <button type="submit" class="btn btn-outline-primary btn-sm fw-bold">
                            <i class="fa-solid fa-rotate me-1"></i> Still Working
                        </button>
                    </form>

                    <form action="index.php?route=time_tracker" method="POST" class="d-inline">
                        <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                        <input type="hidden" name="action" value="checkin_response">
                        <input type="hidden" name="task_id" value="<?= $activeTask['id'] ?>">
                        <input type="hidden" name="checkin_action" value="finished">
                        <button type="submit" class="btn btn-success btn-sm fw-bold px-3">
                            <i class="fa-solid fa-check me-1"></i> Finish Task
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <script>
        (function() {
            var startSecs = <?= $elapsedSecs ?>;
            function updateClock() {
                startSecs++;
                var hrs = Math.floor(startSecs / 3600);
                var mins = Math.floor((startSecs % 3600) / 60);
                var secs = startSecs % 60;
                var str = (hrs < 10 ? '0' + hrs : hrs) + ':' + (mins < 10 ? '0' + mins : mins) + ':' + (secs < 10 ? '0' + secs : secs);
                var el = document.getElementById('activeTimerClock');
                if (el) el.innerText = str;
            }
            setInterval(updateClock, 1000);
            updateClock();
        })();
        </script>
    <?php endif; ?>

    <!-- Start Live Timer or Log Completed Task Card -->
    <div class="card shadow-sm mb-4 border-0">
        <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fw-bold">
                <i class="fa-solid <?= $editTask ? 'fa-pen-to-square' : 'fa-plus-circle' ?> me-2"></i>
                <?= $editTask ? 'Edit Task Entry' : 'Log Time or Start Live Timer' ?>
            </h5>
            <?php if (!$editTask && !$activeTask): ?>
                <span class="badge bg-light text-primary">Live 15-Min Check-in Supported</span>
            <?php endif; ?>
        </div>
        <div class="card-body">
            <form action="index.php?route=time_tracker" method="POST" class="row g-3">
                <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                <input type="hidden" name="action" value="save_task">
                <?php if ($editTask): ?>
                    <input type="hidden" name="task_id" value="<?= $editTask['id'] ?>">
                <?php endif; ?>

                <div class="col-md-4">
                    <label class="form-label fw-bold small">Task Name / Description</label>
                    <input type="text" name="task_name" class="form-control" placeholder="Describe the task performed..." value="<?= htmlspecialchars($editTask['task_name'] ?? '') ?>" required>
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold small">Ticket Ref # (Optional)</label>
                    <input type="text" name="ticket_ref" class="form-control" placeholder="e.g. INC-1024" value="<?= htmlspecialchars($editTask['ticket_ref'] ?? '') ?>">
                </div>

                <div class="col-md-3">
                    <label class="form-label fw-bold small">Applied Category / Project</label>
                    <select name="item_id" class="form-select" required>
                        <option value="">-- Select Project / Activity --</option>
                        <?php
                        $categories = ['project' => 'Projects', 'support' => 'Support Activities', 'maintenance' => 'Maintenance Activities'];
                        foreach ($categories as $catKey => $catLabel):
                            $catItems = array_filter($items, function($i) use ($catKey) { return $i['category'] === $catKey; });
                            if (empty($catItems)) continue;
                        ?>
                            <optgroup label="<?= $catLabel ?>">
                                <?php foreach ($catItems as $item): ?>
                                    <option value="<?= $item['id'] ?>" <?= ($editTask && $editTask['item_id'] == $item['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($item['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </optgroup>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="col-md-1">
                    <label class="form-label fw-bold small">Hours</label>
                    <input type="number" step="0.25" min="0" name="hours" class="form-control" placeholder="0.00" value="<?= htmlspecialchars($editTask['hours'] ?? '') ?>">
                </div>

                <div class="col-md-2">
                    <label class="form-label fw-bold small">Date & Time</label>
                    <input type="datetime-local" name="entry_datetime" class="form-control" value="<?= htmlspecialchars(isset($editTask['entry_datetime']) ? date('Y-m-d\TH:i', strtotime($editTask['entry_datetime'])) : date('Y-m-d\TH:i')) ?>" required>
                </div>

                <?php if ($enableBillableOvertime === '1'): ?>
                <div class="col-md-12 d-flex align-items-center gap-4">
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_billable" value="1" id="chk_billable" <?= (!isset($editTask['is_billable']) || $editTask['is_billable'] == 1) ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-bold" for="chk_billable">Billable Hours</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" name="is_overtime" value="1" id="chk_overtime" <?= (isset($editTask['is_overtime']) && $editTask['is_overtime'] == 1) ? 'checked' : '' ?>>
                        <label class="form-check-label small fw-bold text-warning" for="chk_overtime">Overtime Hours</label>
                    </div>
                </div>
                <?php endif; ?>

                <div class="col-12 text-end d-flex justify-content-end gap-2">
                    <?php if ($editTask): ?>
                        <a href="index.php?route=time_tracker" class="btn btn-secondary me-2"><i class="fa-solid fa-xmark me-1"></i> Cancel</a>
                    <?php endif; ?>

                    <?php if (!$editTask && !$activeTask): ?>
                        <button type="submit" onclick="this.form.action.value='start_timer_task';" class="btn btn-success px-3">
                            <i class="fa-solid fa-play me-1"></i> Start Live Task Timer
                        </button>
                    <?php endif; ?>

                    <button type="submit" class="btn btn-primary px-4">
                        <i class="fa-solid fa-floppy-disk me-1"></i> <?= $editTask ? 'Update Task' : 'Save Time Entry' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Filter & Task List Section -->
    <div class="card shadow-sm border-0">
        <div class="card-header bg-light py-3 d-flex flex-wrap justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-list me-2"></i> Logged Time Entries</h5>
            <div class="badge bg-primary fs-6">
                Total Hours: <?= number_format($totalHours, 2) ?> hrs
            </div>
        </div>
        <div class="card-body">
            <!-- Filter Bar -->
            <form action="index.php" method="GET" class="row g-2 mb-4 align-items-end">
                <input type="hidden" name="route" value="time_tracker">
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Category Filter</label>
                    <select name="category" class="form-select form-select-sm">
                        <option value="">All Categories</option>
                        <option value="project" <?= $catFilter === 'project' ? 'selected' : '' ?>>Projects</option>
                        <option value="support" <?= $catFilter === 'support' ? 'selected' : '' ?>>Support Activities</option>
                        <option value="maintenance" <?= $catFilter === 'maintenance' ? 'selected' : '' ?>>Maintenance Activities</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">Start Date</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($startDate) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small fw-bold">End Date</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($endDate) ?>">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button type="submit" class="btn btn-sm btn-dark flex-grow-1"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                    <a href="index.php?route=time_tracker" class="btn btn-sm btn-outline-secondary"><i class="fa-solid fa-rotate-left"></i> Reset</a>
                </div>
            </form>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Date & Time</th>
                            <th>Category</th>
                            <th>Applied Item / Project</th>
                            <th>Task Description</th>
                            <th>Status</th>
                            <th>Hours Spent</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tasks)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">
                                    <i class="fa-solid fa-folder-open fs-3 d-block mb-2"></i>
                                    No time entries found for the selected period.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tasks as $task):
                                $badgeClass = 'bg-secondary';
                                if ($task['item_category'] === 'project') $badgeClass = 'bg-primary';
                                elseif ($task['item_category'] === 'support') $badgeClass = 'bg-info text-dark';
                                elseif ($task['item_category'] === 'maintenance') $badgeClass = 'bg-warning text-dark';
                            ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?= date('M d, Y', strtotime($task['entry_datetime'])) ?></div>
                                        <small class="text-muted"><?= date('h:i A', strtotime($task['entry_datetime'])) ?></small>
                                    </td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?>"><?= ucfirst($task['item_category'] ?? 'N/A') ?></span>
                                    </td>
                                    <td class="fw-bold">
                                        <?= htmlspecialchars($task['item_name'] ?? 'Unassigned') ?>
                                    </td>
                                    <td><?= htmlspecialchars($task['task_name']) ?></td>
                                    <td>
                                        <?php if (($task['status'] ?? 'completed') === 'in_progress'): ?>
                                            <span class="badge bg-warning text-dark"><i class="fa-solid fa-spinner fa-spin me-1"></i> In Progress</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success border border-success"><i class="fa-solid fa-check me-1"></i> Finished</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="fw-bold text-success">
                                        <i class="fa-regular fa-clock me-1"></i> <?= number_format($task['hours'], 2) ?> hrs
                                    </td>
                                    <td class="text-end">
                                        <a href="index.php?route=time_tracker&edit_task=<?= $task['id'] ?>" class="btn btn-sm btn-outline-primary me-1" title="Edit Task">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </a>
                                        <form action="index.php?route=time_tracker" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this task entry?');">
                                            <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                                            <input type="hidden" name="action" value="delete_task">
                                            <input type="hidden" name="task_id" value="<?= $task['id'] ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Task">
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
</div>
