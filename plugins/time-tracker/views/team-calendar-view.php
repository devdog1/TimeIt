<?php
$teams = TimeTrackerModel::getTeams();
$selectedTeamId = isset($_GET['team_id']) && (int)$_GET['team_id'] > 0 ? (int)$_GET['team_id'] : (!empty($teams) ? $teams[0]['id'] : 0);

$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$viewMode = $_GET['view'] ?? 'grid'; // 'grid' or 'agenda'

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

<?php
// Prepare FullCalendar team event objects
$fcTeamEvents = [];

foreach ($instances as $inst) {
    if ($inst['status'] !== 'pending') continue;
    $dueStatus = $inst['due_status'];
    $isClaimed = !empty($inst['assigned_user_name']);

    $color = $isClaimed ? '#ffc107' : '#dc3545';
    $title = ($isClaimed ? '[WORKING ON: ' . $inst['assigned_user_name'] . '] ' : '[CRITICAL] ') . $inst['task_name'];

    $fcTeamEvents[] = [
        'id' => 'inst_' . $inst['id'],
        'title' => $title,
        'start' => $inst['due_date'],
        'backgroundColor' => $color,
        'borderColor' => $color,
        'textColor' => $isClaimed ? '#000000' : '#ffffff',
        'extendedProps' => [
            'type' => 'instance',
            'instance_id' => $inst['id'],
            'task_name' => $inst['task_name'],
            'item_name' => $inst['item_name'] ?? 'Unassigned',
            'due_date' => date('M d, Y', strtotime($inst['due_date'])),
            'due_label' => $dueStatus['label'],
            'badge_class' => $dueStatus['badge_class'],
            'is_claimed' => $isClaimed,
            'assigned_user_name' => $inst['assigned_user_name'] ?? ''
        ]
    ];
}

foreach ($completedTasks as $t) {
    $startIso = date('Y-m-d\TH:i:s', strtotime($t['entry_datetime']));
    $endTs = strtotime($t['entry_datetime']) + (int)round(((float)$t['hours']) * 3600);
    $endIso = date('Y-m-d\TH:i:s', $endTs);

    $color = '#198754';

    $fcTeamEvents[] = [
        'id' => 'task_' . $t['id'],
        'title' => '[' . $t['user_name'] . '] ' . $t['task_name'] . ' (' . number_format($t['hours'], 1) . 'h)',
        'start' => $startIso,
        'end' => $endIso,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'textColor' => '#ffffff',
        'extendedProps' => [
            'type' => 'task',
            'task_name' => $t['task_name'],
            'user_name' => $t['user_name'],
            'item_name' => $t['item_name'] ?? 'Unassigned',
            'category' => ucfirst($t['item_category'] ?? ''),
            'hours' => number_format($t['hours'], 2),
            'entry_datetime' => date('M d, Y h:i A', strtotime($t['entry_datetime'])),
            'end_datetime' => date('M d, Y h:i A', $endTs)
        ]
    ];
}
?>

<!-- FullCalendar Library -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.10/index.global.min.js"></script>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-users-viewfinder text-primary me-2"></i> Team Calendar (FullCalendar)</h3>
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

    <!-- Team Selector Bar -->
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

            <div>
                <span class="badge bg-success fs-6"><i class="fa-regular fa-clock me-1"></i> Team Month Hours: <?= number_format($totalTeamMonthHours, 2) ?> hrs</span>
            </div>
        </div>
    </div>

    <!-- Color Legend Bar -->
    <div class="card shadow-sm border-0 mb-3 bg-light">
        <div class="card-body py-2 d-flex flex-wrap align-items-center justify-content-between gap-2 small">
            <div class="d-flex align-items-center gap-3">
                <span class="fw-bold text-secondary"><i class="fa-solid fa-palette me-1"></i> Team Legend:</span>
                <span><span class="badge" style="background-color: #dc3545;">&nbsp;&nbsp;</span> Critical Unclaimed Task</span>
                <span><span class="badge text-dark" style="background-color: #ffc107;">&nbsp;&nbsp;</span> Working-On / Claimed</span>
                <span><span class="badge" style="background-color: #198754;">&nbsp;&nbsp;</span> Completed Team Task</span>
            </div>
            <span class="text-muted"><i class="fa-solid fa-hand-pointer me-1"></i> Click any critical task to take ownership or view details.</span>
        </div>
    </div>

    <!-- FullCalendar Container Card -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <div id="fullTeamCalendarContainer" style="min-height: 700px;"></div>
        </div>
    </div>
</div>

<!-- Instance / Task Action Modal -->
<div class="modal fade" id="teamEventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-dark text-white">
                <h5 class="modal-title fw-bold" id="teamModalHeaderTitle">Task Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-start">
                <h5 id="teamModalTitle" class="fw-bold text-dark mb-3"></h5>
                <div id="teamModalTaskBody"></div>
            </div>
            <div class="modal-footer" id="teamModalFooter">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var calendarEl = document.getElementById("fullTeamCalendarContainer");
    if (!calendarEl) return;

    var calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: "dayGridMonth",
        initialDate: "<?= sprintf('%04d-%02d-01', $selectedYear, $selectedMonth) ?>",
        headerToolbar: {
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,timeGridWeek,timeGridDay,listMonth"
        },
        datesSet: function(dateInfo) {
            var currDate = dateInfo.view.currentStart;
            var viewMonth = currDate.getMonth() + 1;
            var viewYear = currDate.getFullYear();
            var selectedMonth = <?= $selectedMonth ?>;
            var selectedYear = <?= $selectedYear ?>;

            if (viewMonth !== selectedMonth || viewYear !== selectedYear) {
                window.location.href = "index.php?route=time_tracker_team_calendar&team_id=<?= $selectedTeamId ?>&month=" + viewMonth + "&year=" + viewYear;
            }
        },
        buttonText: {
            today: "Today",
            month: "Grid Month",
            week: "Week",
            day: "Day",
            list: "Agenda List"
        },
        events: <?= json_encode($fcTeamEvents) ?>,
        eventClick: function(info) {
            var props = info.event.extendedProps;
            document.getElementById("teamModalTitle").innerText = props.task_name;

            var bodyHtml = '';
            var footerHtml = '<button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>';

            if (props.type === 'instance') {
                document.getElementById("teamModalHeaderTitle").innerText = 'Critical Recurring Task';
                bodyHtml += '<p class="small text-muted">Item: <strong>' + props.item_name + '</strong> &bull; Due: ' + props.due_date + '</p>';
                bodyHtml += '<p><span class="badge ' + props.badge_class + '">' + props.due_label + '</span></p>';

                if (props.is_claimed) {
                    bodyHtml += '<div class="alert alert-warning py-2 small fw-bold"><i class="fa-solid fa-spinner fa-spin me-1"></i> Working On: ' + props.assigned_user_name + '</div>';
                } else {
                    footerHtml += '<form action="index.php?route=time_tracker_team_calendar&team_id=<?= $selectedTeamId ?>&month=<?= $selectedMonth ?>&year=<?= $selectedYear ?>" method="POST" class="d-inline">';
                    footerHtml += '<?= function_exists('csrf_field') ? csrf_field() : '' ?>';
                    footerHtml += '<input type="hidden" name="action" value="claim_recurring_instance">';
                    footerHtml += '<input type="hidden" name="instance_id" value="' + props.instance_id + '">';
                    footerHtml += '<button type="submit" class="btn btn-danger btn-sm fw-bold"><i class="fa-solid fa-hand-pointer me-1"></i> Take Ownership & Start Timer</button>';
                    footerHtml += '</form>';
                }
            } else {
                document.getElementById("teamModalHeaderTitle").innerText = 'Completed Team Task';
                bodyHtml += '<dl class="row mb-0 small">';
                bodyHtml += '<dt class="col-sm-4">Completed By:</dt><dd class="col-sm-8 fw-bold text-primary">' + props.user_name + '</dd>';
                bodyHtml += '<dt class="col-sm-4">Category:</dt><dd class="col-sm-8">' + props.category + '</dd>';
                bodyHtml += '<dt class="col-sm-4">Project / Item:</dt><dd class="col-sm-8 fw-bold">' + props.item_name + '</dd>';
                bodyHtml += '<dt class="col-sm-4">Start Time:</dt><dd class="col-sm-8">' + props.entry_datetime + '</dd>';
                bodyHtml += '<dt class="col-sm-4">End Time:</dt><dd class="col-sm-8">' + props.end_datetime + '</dd>';
                bodyHtml += '<dt class="col-sm-4">Hours Spent:</dt><dd class="col-sm-8 fw-bold text-success">' + props.hours + ' hrs</dd>';
                bodyHtml += '</dl>';
            }

            document.getElementById("teamModalTaskBody").innerHTML = bodyHtml;
            document.getElementById("teamModalFooter").innerHTML = footerHtml;

            var modal = new bootstrap.Modal(document.getElementById("teamEventModal"));
            modal.show();
        }
    });
    calendar.render();
});
</script>
