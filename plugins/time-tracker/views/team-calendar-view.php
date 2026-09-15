<?php
$teams = TimeTrackerModel::getTeams();
$selectedTeamId = isset($_GET['team_id']) && (int)$_GET['team_id'] > 0 ? (int)$_GET['team_id'] : (!empty($teams) ? $teams[0]['id'] : 0);

$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$teamCalendarData = $selectedTeamId > 0 ? TimeTrackerModel::getTeamCalendarTasksAndInstances($selectedTeamId, $selectedMonth, $selectedYear) : ['tasks' => [], 'instances' => []];

$completedTasks = $teamCalendarData['tasks'];
$instances = $teamCalendarData['instances'];

// Group completed tasks by day
$tasksByDay = [];
foreach ($completedTasks as $task) {
    $day = (int)date('d', strtotime($task['entry_datetime']));
    if (!isset($tasksByDay[$day])) $tasksByDay[$day] = [];
    $tasksByDay[$day][] = $task;
}

// Group recurring instances by day
$instancesByDay = [];
foreach ($instances as $inst) {
    $day = (int)date('d', strtotime($inst['due_date']));
    if (!isset($instancesByDay[$day])) $instancesByDay[$day] = [];
    $instancesByDay[$day][] = $inst;
}

$firstDayTimestamp = mktime(0, 0, 0, $selectedMonth, 1, $selectedYear);
$daysInMonth = date('t', $firstDayTimestamp);
$monthName = date('F', $firstDayTimestamp);
$startDayOfWeek = date('w', $firstDayTimestamp); // 0 (Sun) to 6 (Sat)

// Prev & Next month math
$prevMonth = $selectedMonth - 1;
$prevYear = $selectedYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }

$nextMonth = $selectedMonth + 1;
$nextYear = $selectedYear;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$totalTeamMonthHours = array_sum(array_column($completedTasks, 'hours'));
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-users-viewfinder text-primary me-2"></i> Team Calendar View</h3>
            <p class="text-muted small mb-0">Overview of team pending, in-progress ("working on"), and completed critical tasks.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=time_tracker_teams" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-users-gear me-1"></i> Manage Teams
            </a>
            <a href="index.php?route=time_tracker" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
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

    <!-- Team Selector & Month Navigation Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-2 d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div class="d-flex align-items-center gap-2">
                <span class="fw-bold text-dark small"><i class="fa-solid fa-users me-1"></i> Select Team:</span>
                <select onchange="location.href='index.php?route=time_tracker_team_calendar&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>&team_id=' + this.value;" class="form-select form-select-sm bg-light text-dark fw-bold" style="width: auto;">
                    <?php foreach ($teams as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $selectedTeamId == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="d-flex align-items-center gap-2">
                <a href="index.php?route=time_tracker_team_calendar&team_id=<?= $selectedTeamId ?>&month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-chevron-left"></i>
                </a>
                <h5 class="fw-bold mb-0 text-dark px-2"><?= $monthName ?> <?= $selectedYear ?></h5>
                <a href="index.php?route=time_tracker_team_calendar&team_id=<?= $selectedTeamId ?>&month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-outline-primary btn-sm">
                    <i class="fa-solid fa-chevron-right"></i>
                </a>
            </div>

            <div>
                <span class="badge bg-success fs-6"><i class="fa-regular fa-clock me-1"></i> Team Month Hours: <?= number_format($totalTeamMonthHours, 2) ?> hrs</span>
            </div>
        </div>
    </div>

    <!-- Calendar Grid -->
    <div class="card shadow-sm border-0">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-bordered mb-0 calendar-table" style="table-layout: fixed;">
                    <thead class="table-dark text-center">
                        <tr>
                            <th style="width: 14.28%;">Sun</th>
                            <th style="width: 14.28%;">Mon</th>
                            <th style="width: 14.28%;">Tue</th>
                            <th style="width: 14.28%;">Wed</th>
                            <th style="width: 14.28%;">Thu</th>
                            <th style="width: 14.28%;">Fri</th>
                            <th style="width: 14.28%;">Sat</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $dayCounter = 1;
                        $cellCounter = 0;
                        while ($dayCounter <= $daysInMonth) {
                            echo "<tr>";
                            for ($i = 0; $i < 7; $i++) {
                                if ($cellCounter < $startDayOfWeek || $dayCounter > $daysInMonth) {
                                    echo "<td class='bg-light text-muted p-2' style='height: 140px; min-height: 140px;'></td>";
                                } else {
                                    $dayTasks = $tasksByDay[$dayCounter] ?? [];
                                    $dayInstances = $instancesByDay[$dayCounter] ?? [];
                                    $dayHours = array_sum(array_column($dayTasks, 'hours'));
                                    $isToday = ($dayCounter == date('j') && $selectedMonth == date('n') && $selectedYear == date('Y'));
                                    $bgClass = $isToday ? 'bg-primary-subtle border border-primary' : '';

                                    echo "<td class='p-2 align-top {$bgClass}' style='height: 140px; min-height: 140px; overflow-y: auto;'>";
                                    echo "<div class='d-flex justify-content-between align-items-center mb-1'>";
                                    echo "<span class='fw-bold fs-6 " . ($isToday ? 'text-primary' : '') . "'>{$dayCounter}</span>";
                                    if ($dayHours > 0) {
                                        echo "<span class='badge bg-success small'>" . number_format($dayHours, 2) . " hrs</span>";
                                    }
                                    echo "</div>";

                                    // Render Pending & Working-On Recurring Instances
                                    foreach ($dayInstances as $inst) {
                                        $dueStatus = $inst['due_status'];
                                        if ($inst['status'] === 'pending') {
                                            if ($inst['assigned_user_name']) {
                                                // Working On State
                                                echo "<div class='p-1 rounded bg-warning-subtle border border-warning mb-1 small'>";
                                                echo "<div class='fw-bold text-dark text-truncate'><i class='fa-solid fa-spinner fa-spin text-warning me-1'></i> " . htmlspecialchars($inst['task_name']) . "</div>";
                                                echo "<small class='text-muted d-block'>Assigned: <strong>" . htmlspecialchars($inst['assigned_user_name']) . "</strong></small>";
                                                echo "</div>";
                                            } else {
                                                // Unclaimed Pending State
                                                echo "<div class='p-1 rounded bg-danger-subtle border border-danger mb-1 small'>";
                                                echo "<div class='fw-bold text-danger text-truncate'><i class='fa-solid fa-triangle-exclamation me-1'></i> " . htmlspecialchars($inst['task_name']) . "</div>";
                                                echo "<span class='badge {$dueStatus['badge_class']} my-1 d-block text-truncate'>" . htmlspecialchars($dueStatus['label']) . "</span>";

                                                echo "<form action='index.php?route=time_tracker_team_calendar&team_id={$selectedTeamId}&month={$selectedMonth}&year={$selectedYear}' method='POST' class='mt-1'>";
                                                if (function_exists('csrf_field')) { echo csrf_field(); }
                                                echo "<input type='hidden' name='action' value='claim_recurring_instance'>";
                                                echo "<input type='hidden' name='instance_id' value='{$inst['id']}'>";
                                                echo "<button type='submit' class='btn btn-sm btn-danger py-0 px-1 w-100 fw-bold fs-7'><i class='fa-solid fa-hand-pointer me-1'></i> Take Ownership</button>";
                                                echo "</form>";
                                                echo "</div>";
                                            }
                                        }
                                    }

                                    // Render Completed Tasks
                                    foreach ($dayTasks as $t) {
                                        echo "<div class='mb-1 p-1 rounded bg-light border text-truncate small' title='" . htmlspecialchars($t['user_name'] . ": " . $t['task_name']) . "'>";
                                        echo "<span class='badge bg-success me-1'>" . number_format($t['hours'], 2) . "h</span>";
                                        echo "<strong>" . htmlspecialchars($t['user_name']) . ":</strong> " . htmlspecialchars($t['task_name']);
                                        echo "</div>";
                                    }

                                    echo "</td>";
                                    $dayCounter++;
                                }
                                $cellCounter++;
                            }
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
