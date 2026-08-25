<?php
/**
 * Plugin Name: IT Time Tracker
 * Description: Clockify-style time tracking system for IT support, maintenance activities, and projects.
 * Version: 1.0.0
 * Author: DevDog
 * Permissions: user_access, supervisor_access
 * Roles: user:user_access; supervisor:user_access,supervisor_access
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
    if (!has_permission('time_tracker_user_access') && !has_permission('time_tracker_supervisor_access') && !has_role('administrator')) {
        return $links;
    }

    $timeTrackerNav = [
        'label' => 'Time Tracker',
        'icon'  => 'fa-solid fa-clock',
        'route' => 'time_tracker',
        'children' => [
            ['label' => 'My Tasks', 'icon' => 'fa-solid fa-list-check', 'route' => 'time_tracker'],
            ['label' => 'Calendar View', 'icon' => 'fa-solid fa-calendar-days', 'route' => 'time_tracker_calendar'],
            ['label' => 'Projects & Categories', 'icon' => 'fa-solid fa-folder-tree', 'route' => 'time_tracker_items']
        ]
    ];

    if (has_permission('time_tracker_supervisor_access') || has_role('administrator')) {
        $timeTrackerNav['children'][] = [
            'label' => 'Supervisor View',
            'icon'  => 'fa-solid fa-user-shield',
            'route' => 'time_tracker_supervisor'
        ];
    }

    $links[] = $timeTrackerNav;
    return $links;
});

// Dashboard quick-add widget on home screen
add_action('index_dashboard_widgets', function($userContext) {
    if (!has_permission('time_tracker_user_access') && !has_permission('time_tracker_supervisor_access') && !has_role('administrator')) {
        return;
    }

    $items = TimeTrackerModel::getItems();
    $userId = $_SESSION['user_id'] ?? 0;
    $recentTasks = TimeTrackerModel::getTasks($userId, date('Y-m-d'), date('Y-m-d'));
    $todayHours = array_sum(array_column($recentTasks, 'hours'));
    ?>
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
    } catch (Exception $e) {
        $_SESSION['tt_error'] = $e->getMessage();
    }

    // Redirect to preserve GET route and display flash message
    $redirectRoute = $_GET['route'] ?? 'time_tracker';
    redirect("index.php?route=" . urlencode($redirectRoute));
    exit;
}

// Register routes
add_action('register_routes', function() {
    register_route('time_tracker', function() {
        if (!has_permission('time_tracker_user_access') && !has_permission('time_tracker_supervisor_access') && !has_role('administrator')) {
            die('Access Denied: You do not have permission to access Time Tracker.');
        }
        time_tracker_handle_posts();
        require_once __DIR__ . '/views/dashboard-view.php';
    });

    register_route('time_tracker_calendar', function() {
        if (!has_permission('time_tracker_user_access') && !has_permission('time_tracker_supervisor_access') && !has_role('administrator')) {
            die('Access Denied: You do not have permission to access Time Tracker.');
        }
        time_tracker_handle_posts();
        require_once __DIR__ . '/views/calendar-view.php';
    });

    register_route('time_tracker_items', function() {
        if (!has_permission('time_tracker_user_access') && !has_permission('time_tracker_supervisor_access') && !has_role('administrator')) {
            die('Access Denied: You do not have permission to access Time Tracker.');
        }
        time_tracker_handle_posts();
        require_once __DIR__ . '/views/items-view.php';
    });

    register_route('time_tracker_supervisor', function() {
        if (!has_permission('time_tracker_supervisor_access') && !has_role('administrator')) {
            die('Access Denied: Supervisor permissions required.');
        }
        time_tracker_handle_posts();
        require_once __DIR__ . '/views/supervisor-view.php';
    });
});
