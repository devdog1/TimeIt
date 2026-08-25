<?php
/**
 * Plugin Name: IT Time Tracker
 * Description: Clockify-style time tracking system for IT support, maintenance activities, and projects with team and finance views.
 * Version: 1.1.0
 * Author: DevDog
 * Permissions: user_access, supervisor_access, finance_access
 * Roles: user:user_access; supervisor:user_access,supervisor_access; finance:user_access,finance_access
 */

if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../');
}

require_once __DIR__ . '/models/time-tracker-models.php';

// Plugin activation hook: install tables
add_action('plugin_activate_time-tracker', function() {
    TimeTrackerModel::installTables();
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

    // Check if user is lead on any projects
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
        if ($action === 'save_task' || $action === 'quick_add_task') {
            $taskId = isset($_POST['task_id']) ? (int)$_POST['task_id'] : 0;
            $itemId = (int)($_POST['item_id'] ?? 0);
            $taskName = $_POST['task_name'] ?? '';
            $hours = (float)($_POST['hours'] ?? 0);
            $entryDatetime = $_POST['entry_datetime'] ?? '';

            // Security check: non-supervisors can only edit their own tasks
            if ($taskId > 0 && !$isSupervisor) {
                $existingTask = TimeTrackerModel::getTaskById($taskId);
                if (!$existingTask || $existingTask['user_id'] != $userId) {
                    throw new Exception("Access Denied: You cannot modify another user's task entry.");
                }
            }

            // If supervisor editing someone else's task
            $targetUserId = $userId;
            if ($isSupervisor && isset($_POST['user_id']) && (int)$_POST['user_id'] > 0) {
                $targetUserId = (int)$_POST['user_id'];
            } elseif ($taskId > 0) {
                $existingTask = TimeTrackerModel::getTaskById($taskId);
                if ($existingTask) {
                    $targetUserId = $existingTask['user_id'];
                }
            }

            TimeTrackerModel::saveTask($taskId, $targetUserId, $itemId, $taskName, $hours, $entryDatetime);
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
    fputcsv($output, ['Task ID', 'User Name', 'Date & Time', 'Category', 'Item / Project Name', 'Task Description', 'Hours Spent']);

    foreach ($tasks as $t) {
        fputcsv($output, [
            $t['id'],
            $t['user_name'],
            $t['entry_datetime'],
            ucfirst($t['item_category'] ?? ''),
            $t['item_name'] ?? 'Unassigned',
            $t['task_name'],
            number_format($t['hours'], 2)
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
