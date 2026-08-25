<?php
/**
 * Plugin Name: IT Time Tracker
 * Description: Clockify-style time tracking system for IT support, maintenance activities, and projects with financial year reports, teams, scheduled email reports, and Chrome Service Worker 15-minute active task check-ins.
 * Version: 1.5.0
 * Author: DevDog
 * Permissions: user_access, supervisor_access, finance_access
 * Roles: user:user_access; supervisor:user_access,supervisor_access; finance:user_access,finance_access
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../');
}

require_once __DIR__ . '/models/time-tracker-models.php';

// Early checkin token processor (Resilient to SSO Session Expiry & Closed Web Pages)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'checkin_response') {
    $taskId = (int)($_POST['task_id'] ?? 0);
    $checkinAction = $_POST['checkin_action'] ?? 'still_working';
    $checkinToken = $_POST['checkin_token'] ?? null;
    $userId = $_SESSION['user_id'] ?? null;

    if ($taskId > 0 && (!empty($checkinToken) || $userId)) {
        try {
            $res = TimeTrackerModel::checkinTaskResponse($taskId, $userId, $checkinAction, $checkinToken);
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => true, 'status' => $res]);
                exit;
            }
            $_SESSION['tt_success'] = ($res === 'finished') ? "Task marked as finished! Total elapsed time saved." : "Task status updated: Still working on it!";
        } catch (Exception $e) {
            if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
            $_SESSION['tt_error'] = $e->getMessage();
        }
    }
}

// Plugin activation hook: install tables
add_action('plugin_activate_time-tracker', function() {
    TimeTrackerModel::installTables();
});

// Register Scheduler API background tasks
add_action('init_scheduler', function($scheduler) {
    if (method_exists($scheduler, 'registerTask')) {
        // Daily emailed reports check
        $scheduler->registerTask(
            'send_scheduled_reports',
            'time_tracker_send_scheduled_reports',
            86400,
            'time-tracker'
        );

        // 15-minute active task check-in background runner (900 seconds)
        $scheduler->registerTask(
            'check_active_tasks',
            'time_tracker_check_active_tasks',
            900,
            'time-tracker'
        );
    }
});

// 15-minute active task background check-in runner
function time_tracker_check_active_tasks() {
    $needingCheckin = TimeTrackerModel::getTasksNeedingCheckin();
    $count = count($needingCheckin);

    $logMsg = sprintf("[TimeTracker 15-Min Checkin] Found %d active tasks needing check-in prompt.\n", $count);
    echo $logMsg;

    if (function_exists('log_action')) {
        log_action('TIME_TRACKER_ACTIVE_TASK_CHECKIN', ['count' => $count]);
    }
}

// Helper: Generate Previous Week's Team Activities HTML Report
function time_tracker_generate_weekly_team_report_html($teamId = null) {
    $prevMon = date('Y-m-d', strtotime('monday last week'));
    $prevSun = date('Y-m-d', strtotime('sunday last week'));

    $tasks = TimeTrackerModel::getTasks(null, $prevMon, $prevSun, null, null, $teamId);
    $totalHours = array_sum(array_column($tasks, 'hours'));

    $grouped = [];
    foreach ($tasks as $t) {
        $uname = $t['user_name'];
        if (!isset($grouped[$uname])) {
            $grouped[$uname] = [
                'user_name' => $uname,
                'total_hours' => 0.0,
                'tasks' => []
            ];
        }
        $grouped[$uname]['total_hours'] += (float)$t['hours'];
        $grouped[$uname]['tasks'][] = $t;
    }

    ob_start();
    ?>
    <div style="font-family: Arial, sans-serif; color: #333; max-width: 800px; margin: 0 auto; border: 1px solid #ddd; border-radius: 8px; overflow: hidden;">
        <div style="background-color: #0d6efd; color: #ffffff; padding: 20px;">
            <h2 style="margin: 0; font-size: 22px;">📊 Weekly Team Activities Report</h2>
            <p style="margin: 5px 0 0 0; font-size: 14px; opacity: 0.9;">
                Period: <strong><?= date('M d, Y', strtotime($prevMon)) ?> &mdash; <?= date('M d, Y', strtotime($prevSun)) ?></strong> (Previous Week)
            </p>
        </div>

        <div style="padding: 20px; background-color: #f8f9fa; border-bottom: 1px solid #ddd;">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 16px; font-weight: bold;">Total Team Hours Logged:</span>
                <span style="font-size: 20px; font-weight: bold; color: #198754; background: #e8f5e9; padding: 4px 12px; border-radius: 20px;">
                    <?= number_format($totalHours, 2) ?> hrs
                </span>
            </div>
        </div>

        <div style="padding: 20px;">
            <?php if (empty($grouped)): ?>
                <p style="color: #6c757d; font-style: italic;">No team tasks were logged for the previous week.</p>
            <?php else: ?>
                <?php foreach ($grouped as $userRow): ?>
                    <div style="margin-bottom: 25px; border: 1px solid #e0e0e0; border-radius: 6px; padding: 15px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #0d6efd; padding-bottom: 8px; margin-bottom: 12px;">
                            <h3 style="margin: 0; font-size: 16px; color: #0d6efd;">
                                👤 <?= htmlspecialchars($userRow['user_name']) ?>
                            </h3>
                            <span style="font-weight: bold; font-size: 14px; color: #198754;">
                                <?= number_format($userRow['total_hours'], 2) ?> hrs
                            </span>
                        </div>

                        <table style="width: 100%; border-collapse: collapse; font-size: 13px;">
                            <thead>
                                <tr style="background-color: #f1f3f5; text-align: left;">
                                    <th style="padding: 6px 8px; border: 1px solid #dee2e6;">Date & Time</th>
                                    <th style="padding: 6px 8px; border: 1px solid #dee2e6;">Category</th>
                                    <th style="padding: 6px 8px; border: 1px solid #dee2e6;">Applied Item / Project</th>
                                    <th style="padding: 6px 8px; border: 1px solid #dee2e6;">Task Name</th>
                                    <th style="padding: 6px 8px; border: 1px solid #dee2e6; text-align: right;">Hours</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($userRow['tasks'] as $tsk): ?>
                                    <tr>
                                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;"><?= date('M d, H:i', strtotime($tsk['entry_datetime'])) ?></td>
                                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;">
                                            <span style="font-weight: bold; text-transform: uppercase; font-size: 10px; color: #495057;">
                                                <?= htmlspecialchars($tsk['item_category'] ?? 'N/A') ?>
                                            </span>
                                        </td>
                                        <td style="padding: 6px 8px; border: 1px solid #dee2e6; font-weight: bold;"><?= htmlspecialchars($tsk['item_name'] ?? 'Unassigned') ?></td>
                                        <td style="padding: 6px 8px; border: 1px solid #dee2e6;"><?= htmlspecialchars($tsk['task_name']) ?></td>
                                        <td style="padding: 6px 8px; border: 1px solid #dee2e6; text-align: right; font-weight: bold; color: #198754;">
                                            <?= number_format($tsk['hours'], 2) ?> h
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <div style="background-color: #f8f9fa; padding: 12px 20px; text-align: center; font-size: 12px; color: #6c757d; border-top: 1px solid #ddd;">
            Generated automatically by IT Time Tracker Plugin &bull; Portal Framework
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Task callback for background scheduled reports
function time_tracker_send_scheduled_reports() {
    $enabled = TimeTrackerModel::getSetting('email_reports_enabled', '0');
    if ($enabled !== '1') {
        echo "[TimeTracker] Scheduled emailed reports disabled.\n";
        return;
    }

    $recipientsRaw = TimeTrackerModel::getSetting('email_reports_recipients', '');
    if (empty(trim($recipientsRaw))) {
        echo "[TimeTracker] No email report recipients configured.\n";
        return;
    }

    $recipients = array_map('trim', explode(',', $recipientsRaw));
    $reportHtml = time_tracker_generate_weekly_team_report_html();

    $logMsg = sprintf(
        "[TimeTracker Scheduled Report] Generated Previous Week's Team Activities HTML report for %d recipients (%s). Report size: %d bytes.\n",
        count($recipients),
        implode(', ', $recipients),
        strlen($reportHtml)
    );

    echo $logMsg;
    if (function_exists('log_action')) {
        log_action('TIME_TRACKER_SCHEDULED_REPORT', ['details' => $logMsg]);
    }
}

// Inject Chrome Web Notification & Service Worker JS for Closed-Page Support
add_action('theme_footer', function() {
    $userId = $_SESSION['user_id'] ?? 0;
    if (!$userId) return;

    $activeTask = TimeTrackerModel::getActiveTaskForUser($userId);
    if (!$activeTask) return;

    $activeTaskId = $activeTask['id'];
    $checkinToken = $activeTask['checkin_token'] ?? '';
    $lastCheckinTs = strtotime($activeTask['last_checkin_at'] ?? $activeTask['entry_datetime']);
    $elapsedCheckinSecs = max(0, time() - $lastCheckinTs);
    $jsonTask = json_encode($activeTask);
    $csrfToken = $_SESSION['csrf_token'] ?? '';
    ?>
    <div id="activeTaskCheckinModal" class="modal fade" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-primary border-3">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-clock-rotate-left me-2"></i> Active Task Check-in</h5>
                </div>
                <div class="modal-body text-center py-4">
                    <i class="fa-solid fa-bell-ring fs-1 text-primary mb-3"></i>
                    <h5 class="fw-bold mb-2">Are you still working on this task?</h5>
                    <p class="text-dark bg-light p-2 rounded border fw-bold fs-6 mb-3">
                        "<?= htmlspecialchars($activeTask['task_name']) ?>"
                    </p>
                    <p class="text-muted small mb-0">Periodic 15-minute check-in to keep your time entries accurate.</p>
                </div>
                <div class="modal-footer justify-content-center gap-2">
                    <button type="button" onclick="sendCheckinResponse('still_working');" class="btn btn-primary px-4 fw-bold">
                        <i class="fa-solid fa-play me-1"></i> Still Working On It
                    </button>
                    <button type="button" onclick="sendCheckinResponse('finished');" class="btn btn-success px-4 fw-bold">
                        <i class="fa-solid fa-check me-1"></i> Mark as Finished
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
    function sendCheckinResponse(action) {
        var formData = new FormData();
        formData.append('action', 'checkin_response');
        formData.append('task_id', '<?= $activeTaskId ?>');
        formData.append('checkin_token', '<?= addslashes($checkinToken) ?>');
        formData.append('checkin_action', action);
        formData.append('csrf_token', '<?= $csrfToken ?>');

        fetch('index.php?route=time_tracker', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
            window.location.reload();
        })
        .catch(function(err) {
            window.location.reload();
        });
    }

    document.addEventListener("DOMContentLoaded", function() {
        var activeTaskObj = <?= $jsonTask ?>;
        var elapsedSecs = <?= $elapsedCheckinSecs ?>;
        var checkinInterval = 900; // 15 minutes = 900 seconds
        var remainingMs = Math.max(1000, (checkinInterval - elapsedSecs) * 1000);

        // Register Service Worker to handle closed-page background notifications
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('plugins/time-tracker/assets/sw.js')
            .then(function(reg) {
                if ('Notification' in window && Notification.permission !== 'granted' && Notification.permission !== 'denied') {
                    Notification.requestPermission();
                }

                // Delegate notification trigger to Service Worker
                setTimeout(function() {
                    if (reg.active) {
                        reg.active.postMessage({
                            type: 'TRIGGER_ACTIVE_TASK_CHECKIN',
                            task: activeTaskObj
                        });
                    }
                }, remainingMs);
            })
            .catch(function(err) {
                console.log('SW registration error:', err);
            });
        }

        if (elapsedSecs >= checkinInterval) {
            var modalEl = document.getElementById('activeTaskCheckinModal');
            if (modalEl && typeof bootstrap !== 'undefined') {
                var modal = new bootstrap.Modal(modalEl);
                modal.show();
            }
        }
    });
    </script>
    <?php
});

// Register navigation links
add_filter('theme_nav_links', function($links) {
    if (!has_permission('time_tracker_user_access') && !has_permission('time_tracker_supervisor_access') && !has_permission('time_tracker_finance_access') && !has_role('administrator')) {
        return $links;
    }

    $children = [
        ['label' => 'My Tasks', 'icon' => 'fa-solid fa-list-check', 'route' => 'time_tracker'],
        ['label' => 'Calendar View', 'icon' => 'fa-solid fa-calendar-days', 'route' => 'time_tracker_calendar'],
        ['label' => 'Projects & Categories', 'icon' => 'fa-solid fa-folder-tree', 'route' => 'time_tracker_items']
    ];

    if (has_permission('time_tracker_supervisor_access') || has_role('administrator')) {
        $children[] = ['label' => 'Teams Management', 'icon' => 'fa-solid fa-users-gear', 'route' => 'time_tracker_teams'];
        $children[] = ['label' => 'Supervisor View', 'icon' => 'fa-solid fa-user-shield', 'route' => 'time_tracker_supervisor'];
        $children[] = ['label' => 'Plugin Settings', 'icon' => 'fa-solid fa-sliders', 'route' => 'time_tracker_settings'];
    }

    if (has_permission('time_tracker_finance_access') || has_role('administrator')) {
        $children[] = ['label' => 'Finance View (Read-Only)', 'icon' => 'fa-solid fa-file-invoice-dollar', 'route' => 'time_tracker_finance'];
    }

    $links[] = [
        'label' => 'Time Tracker',
        'icon'  => 'fa-solid fa-clock',
        'route' => 'time_tracker',
        'children' => $children
    ];

    return $links;
});

// Dashboard widgets on home screen
add_action('index_dashboard_widgets', function($userContext) {
    if (!has_permission('time_tracker_user_access') && !has_permission('time_tracker_supervisor_access') && !has_permission('time_tracker_finance_access') && !has_role('administrator')) {
        return;
    }

    $userId = $_SESSION['user_id'] ?? 0;
    $items = TimeTrackerModel::getItems();
    $recentTasks = TimeTrackerModel::getTasks($userId, date('Y-m-d'), date('Y-m-d'));
    $todayHours = array_sum(array_column($recentTasks, 'hours'));

    $leadProjects = TimeTrackerModel::getProjectsLedByUser($userId);
    ?>

    <!-- Quick Time Tracker Log Widget -->
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-start border-4 border-primary h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-primary">
                    <i class="fa-solid fa-bolt me-1"></i> Quick Time Tracker Log
                </h6>
                <span class="badge bg-primary rounded-pill"><?= number_format($todayHours, 2) ?> hrs logged today</span>
            </div>
            <div class="card-body">
                <form action="index.php?route=time_tracker" method="POST" class="row g-2">
                    <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                    <input type="hidden" name="action" value="quick_add_task">
                    <div class="col-md-12">
                        <input type="text" name="task_name" class="form-control form-control-sm" placeholder="What are you working on?" required>
                    </div>
                    <div class="col-md-6">
                        <select name="item_id" class="form-select form-select-sm" required>
                            <option value="">Select Category / Project...</option>
                            <?php foreach ($items as $item): ?>
                                <option value="<?= $item['id'] ?>">[<?= ucfirst($item['category']) ?>] <?= htmlspecialchars($item['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="number" step="0.25" min="0.1" name="hours" class="form-control form-control-sm" placeholder="Hours" required>
                    </div>
                    <div class="col-md-3">
                        <input type="datetime-local" name="entry_datetime" class="form-control form-control-sm" value="<?= date('Y-m-d\TH:i') ?>" required>
                    </div>
                    <div class="col-md-12 text-end mt-2">
                        <button type="submit" class="btn btn-sm btn-primary w-100">
                            <i class="fa-solid fa-plus me-1"></i> Log Task
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Project Lead Status Widget -->
    <?php if (!empty($leadProjects)): ?>
    <div class="col-md-6 mb-4">
        <div class="card shadow-sm border-start border-4 border-success h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-success">
                    <i class="fa-solid fa-user-tie me-1"></i> Projects You Lead (<?= count($leadProjects) ?>)
                </h6>
                <a href="index.php?route=time_tracker_items" class="btn btn-sm btn-outline-success py-0">Manage</a>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush small">
                    <?php foreach ($leadProjects as $lp):
                        $est = $lp['estimated_hours'];
                        $act = $lp['actual_hours'];
                        $pct = ($est && $est > 0) ? min(100, round(($act / $est) * 100)) : 0;
                    ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="text-dark d-block"><?= htmlspecialchars($lp['name']) ?></strong>
                                <small class="text-muted">Category: <?= ucfirst($lp['category']) ?></small>
                            </div>
                            <div class="text-end">
                                <span class="fw-bold text-success d-block"><?= number_format($act, 2) ?> hrs logged</span>
                                <?php if ($est && $est > 0): ?>
                                    <small class="text-muted"><?= $pct ?>% of <?= number_format($est, 2) ?>h est.</small>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php
});

// Helper for POST processing flash messages
function time_tracker_handle_posts() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;

    if (function_exists('csrf_verify')) {
        csrf_verify();
    } elseif (function_exists('validate_csrf')) {
        validate_csrf();
    }

    $action = $_POST['action'] ?? '';
    $userId = $_SESSION['user_id'] ?? 0;
    $isSupervisor = has_permission('time_tracker_supervisor_access') || has_role('administrator');

    try {
        if ($action === 'start_timer_task') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $taskName = $_POST['task_name'] ?? '';
            TimeTrackerModel::startTaskTimer($userId, $itemId, $taskName);
            $_SESSION['tt_success'] = "Active task timer started! We will check in with you every 15 minutes.";
        }
        elseif ($action === 'save_task' || $action === 'quick_add_task') {
            $taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
            $itemId = (int)($_POST['item_id'] ?? 0);
            $taskName = $_POST['task_name'] ?? '';
            $hours = (float)($_POST['hours'] ?? 0);
            $entryDatetime = $_POST['entry_datetime'] ?? '';

            if ($taskId > 0 && !$isSupervisor) {
                $existingTask = TimeTrackerModel::getTaskById($taskId);
                if (!$existingTask || $existingTask['user_id'] != $userId) {
                    throw new Exception("Access Denied: You cannot modify another user's task entry.");
                }
            }

            $targetUserId = $userId;
            if ($isSupervisor && isset($_POST['user_id']) && (int)$_POST['user_id'] > 0) {
                $targetUserId = (int)$_POST['user_id'];
            } elseif ($taskId > 0) {
                $existingTask = TimeTrackerModel::getTaskById($taskId);
                if ($existingTask) {
                    $targetUserId = $existingTask['user_id'];
                }
            }

            TimeTrackerModel::saveTask($taskId, $targetUserId, $itemId, $taskName, $hours, $entryDatetime, 'completed');
            $_SESSION['tt_success'] = ($taskId > 0) ? "Task updated successfully." : "Task logged successfully!";
        }
        elseif ($action === 'delete_task') {
            $taskId = (int)($_POST['task_id'] ?? 0);
            TimeTrackerModel::deleteTask($taskId, $userId, $isSupervisor);
            $_SESSION['tt_success'] = "Task deleted successfully.";
        }
        elseif ($action === 'save_item') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            $category = $_POST['category'] ?? 'project';
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $estimatedHours = $_POST['estimated_hours'] !== '' ? $_POST['estimated_hours'] : null;
            $leadUserId = $_POST['lead_user_id'] !== '' ? $_POST['lead_user_id'] : null;

            TimeTrackerModel::saveItem($itemId, $category, $name, $description, $estimatedHours, $leadUserId);
            $_SESSION['tt_success'] = ($itemId > 0) ? "Item updated successfully." : "New project/category item created!";
        }
        elseif ($action === 'delete_item') {
            $itemId = (int)($_POST['item_id'] ?? 0);
            TimeTrackerModel::deleteItem($itemId);
            $_SESSION['tt_success'] = "Item and linked tasks deleted successfully.";
        }
        elseif ($action === 'save_team') {
            if (!$isSupervisor) throw new Exception("Access Denied: Supervisor privileges required to manage teams.");
            $teamId = (int)($_POST['team_id'] ?? 0);
            $name = $_POST['name'] ?? '';
            $description = $_POST['description'] ?? '';
            $supervisorUserId = $_POST['supervisor_user_id'] !== '' ? (int)$_POST['supervisor_user_id'] : null;
            $memberUserIds = isset($_POST['member_user_ids']) && is_array($_POST['member_user_ids']) ? $_POST['member_user_ids'] : [];

            TimeTrackerModel::saveTeam($teamId, $name, $description, $supervisorUserId, $memberUserIds);
            $_SESSION['tt_success'] = ($teamId > 0) ? "Team updated successfully!" : "New team created successfully!";
        }
        elseif ($action === 'delete_team') {
            if (!$isSupervisor) throw new Exception("Access Denied: Supervisor privileges required.");
            $teamId = (int)($_POST['team_id'] ?? 0);
            TimeTrackerModel::deleteTeam($teamId);
            $_SESSION['tt_success'] = "Team deleted successfully.";
        }
        elseif ($action === 'save_settings') {
            if (!$isSupervisor) throw new Exception("Access Denied: Supervisor privileges required.");

            $fyMonth = (int)($_POST['fy_start_month'] ?? 9);
            $fyDay = (int)($_POST['fy_start_day'] ?? 1);
            $emailEnabled = isset($_POST['email_reports_enabled']) ? '1' : '0';
            $emailFreq = $_POST['email_reports_frequency'] ?? 'weekly';
            $emailRecipients = $_POST['email_reports_recipients'] ?? '';
            $emailType = $_POST['email_reports_type'] ?? 'finance';

            TimeTrackerModel::saveSetting('fy_start_month', $fyMonth);
            TimeTrackerModel::saveSetting('fy_start_day', $fyDay);
            TimeTrackerModel::saveSetting('email_reports_enabled', $emailEnabled);
            TimeTrackerModel::saveSetting('email_reports_frequency', $emailFreq);
            TimeTrackerModel::saveSetting('email_reports_recipients', $emailRecipients);
            TimeTrackerModel::saveSetting('email_reports_type', $emailType);

            $_SESSION['tt_success'] = "Plugin settings and email report configurations saved successfully!";
        }
        elseif ($action === 'trigger_test_email') {
            if (!$isSupervisor) throw new Exception("Access Denied: Supervisor privileges required.");
            time_tracker_send_scheduled_reports();
            $_SESSION['tt_success'] = "Example weekly team activities report triggered successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['tt_error'] = $e->getMessage();
    }

    $redirectRoute = $_GET['route'] ?? 'time_tracker';
    redirect("index.php?route=" . urlencode($redirectRoute));
    exit;
}

// CSV Export Helper
function time_tracker_export_csv() {
    if (!isset($_GET['export_csv']) || $_GET['export_csv'] !== '1') return;

    if (!has_permission('time_tracker_finance_access') && !has_permission('time_tracker_supervisor_access') && !has_role('administrator')) {
        die('Access Denied');
    }

    $filterUserId = isset($_GET['user_id']) && $_GET['user_id'] !== '' ? (int)$_GET['user_id'] : null;
    $filterCategory = isset($_GET['category']) && $_GET['category'] !== '' ? $_GET['category'] : null;
    $filterItemId = isset($_GET['item_id']) && $_GET['item_id'] !== '' ? (int)$_GET['item_id'] : null;
    $filterTeamId = isset($_GET['team_id']) && $_GET['team_id'] !== '' ? (int)$_GET['team_id'] : null;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;

    $tasks = TimeTrackerModel::getTasks($filterUserId, $startDate, $endDate, $filterItemId, $filterCategory, $filterTeamId);

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=time_tracker_report_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Task ID', 'User Name', 'Date & Time', 'Category', 'Item / Project Name', 'Task Description', 'Hours Spent', 'Status']);

    foreach ($tasks as $t) {
        fputcsv($output, [
            $t['id'],
            $t['user_name'],
            $t['entry_datetime'],
            ucfirst($t['item_category'] ?? ''),
            $t['item_name'] ?? 'Unassigned',
            $t['task_name'],
            number_format($t['hours'], 2),
            ucfirst($t['status'] ?? 'completed')
        ]);
    }
    fclose($output);
    exit;
}

// Register routes
add_action('register_routes', function() {
    time_tracker_export_csv();

    register_route('time_tracker', function() {
        if (!has_permission('time_tracker_user_access') && !has_permission('time_tracker_supervisor_access') && !has_permission('time_tracker_finance_access') && !has_role('administrator')) {
            die('Access Denied: You do not have permission to access Time Tracker.');
        }
        time_tracker_handle_posts();
        require_once __DIR__ . '/views/dashboard-view.php';
    });

    register_route('time_tracker_calendar', function() {
        if (!has_permission('time_tracker_user_access') && !has_permission('time_tracker_supervisor_access') && !has_permission('time_tracker_finance_access') && !has_role('administrator')) {
            die('Access Denied: You do not have permission to access Time Tracker.');
        }
        time_tracker_handle_posts();
        require_once __DIR__ . '/views/calendar-view.php';
    });

    register_route('time_tracker_items', function() {
        if (!has_permission('time_tracker_user_access') && !has_permission('time_tracker_supervisor_access') && !has_permission('time_tracker_finance_access') && !has_role('administrator')) {
            die('Access Denied: You do not have permission to access Time Tracker.');
        }
        time_tracker_handle_posts();
        require_once __DIR__ . '/views/items-view.php';
    });

    register_route('time_tracker_teams', function() {
        if (!has_permission('time_tracker_supervisor_access') && !has_role('administrator')) {
            die('Access Denied: Supervisor privileges required.');
        }
        time_tracker_handle_posts();
        require_once __DIR__ . '/views/teams-view.php';
    });

    register_route('time_tracker_settings', function() {
        if (!has_permission('time_tracker_supervisor_access') && !has_role('administrator')) {
            die('Access Denied: Supervisor privileges required.');
        }
        time_tracker_handle_posts();
        require_once __DIR__ . '/views/settings-view.php';
    });

    register_route('time_tracker_supervisor', function() {
        if (!has_permission('time_tracker_supervisor_access') && !has_role('administrator')) {
            die('Access Denied: Supervisor permissions required.');
        }
        time_tracker_handle_posts();
        require_once __DIR__ . '/views/supervisor-view.php';
    });

    register_route('time_tracker_finance', function() {
        if (!has_permission('time_tracker_finance_access') && !has_role('administrator')) {
            die('Access Denied: Finance read-only permissions required.');
        }
        require_once __DIR__ . '/views/finance-view.php';
    });
});
