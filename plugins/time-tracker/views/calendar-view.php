<?php
$userId = $_SESSION['user_id'] ?? 0;

$selectedMonth = isset($_GET['month']) ? (int)$_GET['month'] : (int)date('m');
$selectedYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$viewMode = $_GET['view'] ?? 'grid'; // 'grid' or 'agenda'

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

<?php
// Prepare FullCalendar event objects
$fcEvents = [];
foreach ($tasks as $t) {
    $startIso = date('Y-m-d\TH:i:s', strtotime($t['entry_datetime']));
    $endTs = strtotime($t['entry_datetime']) + (int)round(((float)$t['hours']) * 3600);
    $endIso = date('Y-m-d\TH:i:s', $endTs);

    $color = '#6c757d';
    if ($t['item_category'] === 'project') $color = '#0d6efd';
    elseif ($t['item_category'] === 'support') $color = '#0dcaf0';
    elseif ($t['item_category'] === 'maintenance') $color = '#ffc107';

    $fcEvents[] = [
        'id' => $t['id'],
        'title' => '[' . number_format($t['hours'], 1) . 'h] ' . $t['task_name'],
        'start' => $startIso,
        'end' => $endIso,
        'backgroundColor' => $color,
        'borderColor' => $color,
        'textColor' => ($t['item_category'] === 'maintenance' || $t['item_category'] === 'support') ? '#000000' : '#ffffff',
        'extendedProps' => [
            'task_name' => $t['task_name'],
            'item_name' => $t['item_name'] ?? 'Unassigned',
            'category' => ucfirst($t['item_category'] ?? ''),
            'hours' => number_format($t['hours'], 2),
            'ticket_ref' => $t['ticket_ref'] ?? '',
            'status' => $t['status'] ?? 'completed',
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
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-calendar-days text-primary me-2"></i> User Calendar (FullCalendar)</h3>
            <p class="text-muted small mb-0">Interactive FullCalendar view of logged hours and task activities.</p>
        </div>
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-success fs-6"><i class="fa-regular fa-clock me-1"></i> Total Month: <?= number_format($totalMonthHours, 2) ?> hrs</span>
            <a href="index.php?route=time_tracker" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Color Legend Bar -->
    <div class="card shadow-sm border-0 mb-3 bg-light">
        <div class="card-body py-2 d-flex flex-wrap align-items-center justify-content-between gap-2 small">
            <div class="d-flex align-items-center gap-3">
                <span class="fw-bold text-secondary"><i class="fa-solid fa-palette me-1"></i> Category Legend:</span>
                <span><span class="badge" style="background-color: #0d6efd;">&nbsp;&nbsp;</span> Projects</span>
                <span><span class="badge text-dark" style="background-color: #0dcaf0;">&nbsp;&nbsp;</span> Support Activities</span>
                <span><span class="badge text-dark" style="background-color: #ffc107;">&nbsp;&nbsp;</span> Maintenance</span>
                <span><span class="badge" style="background-color: #6c757d;">&nbsp;&nbsp;</span> Other Categories</span>
            </div>
            <span class="text-muted"><i class="fa-solid fa-hand-pointer me-1"></i> Click any task entry for details or editing.</span>
        </div>
    </div>

    <!-- FullCalendar Card Container -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <div id="fullCalendarContainer" style="min-height: 700px;"></div>
        </div>
    </div>
</div>

<!-- Task Detail Modal -->
<div class="modal fade" id="taskDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-circle-info me-2"></i> Task Entry Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h5 id="modalTaskTitle" class="fw-bold text-dark mb-3"></h5>
                <dl class="row mb-0 small">
                    <dt class="col-sm-4">Category:</dt>
                    <dd class="col-sm-8" id="modalTaskCategory"></dd>

                    <dt class="col-sm-4">Project / Item:</dt>
                    <dd class="col-sm-8 fw-bold" id="modalTaskItem"></dd>

                    <dt class="col-sm-4">Ticket Ref #:</dt>
                    <dd class="col-sm-8" id="modalTaskTicket"></dd>

                    <dt class="col-sm-4">Start Time:</dt>
                    <dd class="col-sm-8" id="modalTaskStart"></dd>

                    <dt class="col-sm-4">End Time:</dt>
                    <dd class="col-sm-8" id="modalTaskEnd"></dd>

                    <dt class="col-sm-4">Hours Spent:</dt>
                    <dd class="col-sm-8 fw-bold text-success" id="modalTaskHours"></dd>
                </dl>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                <a href="#" id="modalEditLink" class="btn btn-primary btn-sm fw-bold"><i class="fa-solid fa-pen-to-square me-1"></i> Edit Task</a>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    var calendarEl = document.getElementById("fullCalendarContainer");
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
                window.location.href = "index.php?route=time_tracker_calendar&month=" + viewMonth + "&year=" + viewYear;
            }
        },
        buttonText: {
            today: "Today",
            month: "Grid Month",
            week: "Week",
            day: "Day",
            list: "Agenda List"
        },
        events: <?= json_encode($fcEvents) ?>,
        eventClick: function(info) {
            var props = info.event.extendedProps;
            document.getElementById("modalTaskTitle").innerText = props.task_name;
            document.getElementById("modalTaskItem").innerText = props.item_name;
            document.getElementById("modalTaskCategory").innerText = props.category;
            document.getElementById("modalTaskHours").innerText = props.hours + " hrs";
            document.getElementById("modalTaskStart").innerText = props.entry_datetime;
            document.getElementById("modalTaskEnd").innerText = props.end_datetime;
            document.getElementById("modalTaskTicket").innerText = props.ticket_ref ? props.ticket_ref : "None";
            document.getElementById("modalEditLink").href = "index.php?route=time_tracker&edit_task=" + info.event.id;

            var modal = new bootstrap.Modal(document.getElementById("taskDetailModal"));
            modal.show();
        }
    });
    calendar.render();
});
</script>
