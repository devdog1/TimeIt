<?php
$userId = $_SESSION['user_id'] ?? 0;

$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

$tasks = TimeTrackerModel::getTasksForCalendar($userId, $selectedMonth, $selectedYear);

// Group tasks by day
$tasksByDay = [];
foreach ($tasks as $task) {
    $day = (int)date('d', strtotime($task['entry_datetime']));
    if (!isset($tasksByDay[$day])) {
        $tasksByDay[$day] = [];
    }
    $tasksByDay[$day][] = $task;
}

$firstDayTimestamp = mktime(0, 0, 0, $selectedMonth, 1, $selectedYear);
$daysInMonth = date('t', $firstDayTimestamp);
$monthName = date('F', $firstDayTimestamp);
$startDayOfWeek = date('w', $firstDayTimestamp); // 0 (Sun) to 6 (Sat)

// Total month hours
$totalMonthHours = array_sum(array_column($tasks, 'hours'));

// Prev & Next month math
$prevMonth = $selectedMonth - 1;
$prevYear = $selectedYear;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $selectedMonth + 1;
$nextYear = $selectedYear;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-calendar-days text-primary me-2"></i> User Calendar View</h3>
            <p class="text-muted small mb-0">Monthly view of logged hours and task activities.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="index.php?route=time_tracker" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Month Navigation Bar -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body py-2 d-flex justify-content-between align-items-center">
            <a href="index.php?route=time_tracker_calendar&month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="btn btn-outline-primary btn-sm">
                <i class="fa-solid fa-chevron-left me-1"></i> Previous Month
            </a>
            <h4 class="fw-bold mb-0 text-dark"><?= $monthName ?> <?= $selectedYear ?></h4>
            <div class="d-flex align-items-center gap-3">
                <span class="badge bg-success fs-6"><i class="fa-regular fa-clock me-1"></i> Total: <?= number_format($totalMonthHours, 2) ?> hrs</span>
                <a href="index.php?route=time_tracker_calendar&month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="btn btn-outline-primary btn-sm">
                    Next Month <i class="fa-solid fa-chevron-right ms-1"></i>
                </a>
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
                                    echo "<td class='bg-light text-muted p-2' style='height: 120px; min-height: 120px;'></td>";
                                } else {
                                    $dayTasks = $tasksByDay[$dayCounter] ?? [];
                                    $dayHours = array_sum(array_column($dayTasks, 'hours'));
                                    $isToday = ($dayCounter == date('j') && $selectedMonth == date('n') && $selectedYear == date('Y'));
                                    $bgClass = $isToday ? 'bg-primary-subtle border border-primary' : '';

                                    echo "<td class='p-2 align-top {$bgClass}' style='height: 120px; min-height: 120px; overflow-y: auto;'>";
                                    echo "<div class='d-flex justify-content-between align-items-center mb-1'>";
                                    echo "<span class='fw-bold fs-6 " . ($isToday ? 'text-primary' : '') . "'>{$dayCounter}</span>";
                                    if ($dayHours > 0) {
                                        echo "<span class='badge bg-success small'>" . number_format($dayHours, 2) . " hrs</span>";
                                    }
                                    echo "</div>";

                                    echo "<div class='task-list small'>";
                                    foreach ($dayTasks as $t) {
                                        $bClass = 'bg-secondary';
                                        if ($t['item_category'] === 'project') $bClass = 'bg-primary';
                                        elseif ($t['item_category'] === 'support') $bClass = 'bg-info text-dark';
                                        elseif ($t['item_category'] === 'maintenance') $bClass = 'bg-warning text-dark';

                                        echo "<div class='mb-1 p-1 rounded bg-light border text-truncate' title='" . htmlspecialchars($t['item_name'] . ": " . $t['task_name']) . "'>";
                                        echo "<span class='badge {$bClass} me-1'>" . number_format($t['hours'], 2) . "h</span>";
                                        echo "<strong class='text-dark'>" . htmlspecialchars($t['task_name']) . "</strong>";
                                        echo "</div>";
                                    }
                                    echo "</div>";
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
