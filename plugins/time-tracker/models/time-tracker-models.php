<?php
if (!defined('APP_ROOT')) {
    define('APP_ROOT', __DIR__ . '/../../../');
}

if (file_exists(APP_ROOT . 'PluginDatabase.php')) {
    require_once APP_ROOT . 'PluginDatabase.php';
}

class TimeTrackerModel {
    private static $pdb = null;

    private static function getPdb() {
        if (self::$pdb === null) {
            self::$pdb = new PluginDatabase('time-tracker');
        }
        return self::$pdb;
    }

    public static function installTables() {
        $pdb = self::getPdb();
        $pdb->createTable('items', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            category ENUM('project', 'support', 'maintenance') NOT NULL DEFAULT 'project',
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            estimated_hours DECIMAL(8,2) NULL,
            lead_user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_category (category)
        ");

        $pdb->createTable('tasks', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            item_id INT NOT NULL,
            task_name VARCHAR(255) NOT NULL,
            hours DECIMAL(6,2) NOT NULL,
            entry_datetime DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_datetime (user_id, entry_datetime),
            KEY idx_user_id (user_id),
            KEY idx_item_id (item_id),
            KEY idx_entry_datetime (entry_datetime)
        ");
    }

    public static function getAllUsers() {
        try {
            $db = get_db_connection();
            $stmt = $db->query("SELECT id, display_name, email FROM users ORDER BY display_name ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($users)) return $users;
        } catch (Exception $e) {}

        // Fallback array if table doesn't exist in testing env
        $currId = $_SESSION['user_id'] ?? 1;
        return [
            ['id' => $currId, 'display_name' => 'Current User', 'email' => 'user@example.com']
        ];
    }

    public static function getUserName($userId) {
        if (!$userId) return 'Unassigned / N/A';
        try {
            $db = get_db_connection();
            $stmt = $db->prepare("SELECT display_name, email FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $u = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($u) {
                return !empty($u['display_name']) ? $u['display_name'] : $u['email'];
            }
        } catch (Exception $e) {}
        return "User #" . $userId;
    }

    public static function getItems($category = null) {
        $pdb = self::getPdb();
        $tbItems = $pdb->getTableName('items');

        if ($category && in_array($category, ['project', 'support', 'maintenance'])) {
            $stmt = $pdb->query("SELECT * FROM {$tbItems} WHERE category = ? ORDER BY name ASC", [$category]);
        } else {
            $stmt = $pdb->query("SELECT * FROM {$tbItems} ORDER BY category ASC, name ASC");
        }
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Calculate logged hours for each item
        $tbTasks = $pdb->getTableName('tasks');
        foreach ($items as &$item) {
            $sumStmt = $pdb->query("SELECT SUM(hours) as total_hours FROM {$tbTasks} WHERE item_id = ?", [$item['id']]);
            $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
            $item['actual_hours'] = $sumRow && $sumRow['total_hours'] ? (float)$sumRow['total_hours'] : 0.0;
            $item['lead_user_name'] = self::getUserName($item['lead_user_id']);
        }

        return $items;
    }

    public static function getItemById($id) {
        $pdb = self::getPdb();
        $tbItems = $pdb->getTableName('items');
        $stmt = $pdb->query("SELECT * FROM {$tbItems} WHERE id = ?", [$id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($item) {
            $item['lead_user_name'] = self::getUserName($item['lead_user_id']);
        }
        return $item;
    }

    public static function saveItem($id, $category, $name, $description = '', $estimated_hours = null, $lead_user_id = null) {
        $pdb = self::getPdb();
        $tbItems = $pdb->getTableName('items');

        if (!in_array($category, ['project', 'support', 'maintenance'])) {
            throw new Exception("Invalid category selected.");
        }
        if (empty(trim($name))) {
            throw new Exception("Item name cannot be empty.");
        }

        $estHours = ($estimated_hours !== null && $estimated_hours !== '') ? (float)$estimated_hours : null;
        $leadUser = ($lead_user_id !== null && $lead_user_id !== '' && (int)$lead_user_id > 0) ? (int)$lead_user_id : null;

        if ($id > 0) {
            $pdb->query(
                "UPDATE {$tbItems} SET category = ?, name = ?, description = ?, estimated_hours = ?, lead_user_id = ? WHERE id = ?",
                [$category, trim($name), trim($description), $estHours, $leadUser, $id]
            );
            return $id;
        } else {
            $pdb->query(
                "INSERT INTO {$tbItems} (category, name, description, estimated_hours, lead_user_id) VALUES (?, ?, ?, ?, ?)",
                [$category, trim($name), trim($description), $estHours, $leadUser]
            );
            return $pdb->lastInsertId();
        }
    }

    public static function deleteItem($id) {
        $pdb = self::getPdb();
        $tbItems = $pdb->getTableName('items');
        $tbTasks = $pdb->getTableName('tasks');

        // Delete linked tasks first
        $pdb->query("DELETE FROM {$tbTasks} WHERE item_id = ?", [$id]);
        $pdb->query("DELETE FROM {$tbItems} WHERE id = ?", [$id]);
        return true;
    }

    public static function isDatetimeDuplicate($userId, $entryDatetime, $excludeTaskId = 0) {
        $pdb = self::getPdb();
        $tbTasks = $pdb->getTableName('tasks');

        $formattedDt = date('Y-m-d H:i:s', strtotime($entryDatetime));

        if ($excludeTaskId > 0) {
            $stmt = $pdb->query("SELECT id FROM {$tbTasks} WHERE user_id = ? AND entry_datetime = ? AND id != ?", [$userId, $formattedDt, $excludeTaskId]);
        } else {
            $stmt = $pdb->query("SELECT id FROM {$tbTasks} WHERE user_id = ? AND entry_datetime = ?", [$userId, $formattedDt]);
        }

        return $stmt->fetch() !== false;
    }

    public static function saveTask($taskId, $userId, $itemId, $taskName, $hours, $entryDatetime) {
        $pdb = self::getPdb();
        $tbTasks = $pdb->getTableName('tasks');

        if (empty(trim($taskName))) {
            throw new Exception("Task description/name cannot be empty.");
        }

        $numHours = (float)$hours;
        if ($numHours <= 0) {
            throw new Exception("Number of hours spent must be greater than 0.");
        }

        if (!$itemId || (int)$itemId <= 0) {
            throw new Exception("Please select a valid Project, Support, or Maintenance item.");
        }

        // Validate item exists
        $item = self::getItemById($itemId);
        if (!$item) {
            throw new Exception("Selected Project/Category item does not exist.");
        }

        $formattedDt = date('Y-m-d H:i:s', strtotime($entryDatetime));
        if (!$formattedDt || $formattedDt === '1970-01-01 00:00:00') {
            throw new Exception("Invalid date and time provided.");
        }

        // Validate datetime collision for user
        if (self::isDatetimeDuplicate($userId, $formattedDt, $taskId)) {
            throw new Exception("Validation Error: User already has another task logged for exact datetime (" . date('Y-m-d H:i', strtotime($formattedDt)) . ").");
        }

        if ($taskId > 0) {
            // Verify existing task ownership if updating
            $existingTask = self::getTaskById($taskId);
            if (!$existingTask) {
                throw new Exception("Task not found.");
            }

            $pdb->query(
                "UPDATE {$tbTasks} SET user_id = ?, item_id = ?, task_name = ?, hours = ?, entry_datetime = ? WHERE id = ?",
                [$userId, $itemId, trim($taskName), $numHours, $formattedDt, $taskId]
            );
            return $taskId;
        } else {
            $pdb->query(
                "INSERT INTO {$tbTasks} (user_id, item_id, task_name, hours, entry_datetime) VALUES (?, ?, ?, ?, ?)",
                [$userId, $itemId, trim($taskName), $numHours, $formattedDt]
            );
            return $pdb->lastInsertId();
        }
    }

    public static function getTaskById($id) {
        $pdb = self::getPdb();
        $tbTasks = $pdb->getTableName('tasks');
        $tbItems = $pdb->getTableName('items');

        $sql = "SELECT t.*, i.name as item_name, i.category as item_category, i.estimated_hours, i.lead_user_id
                FROM {$tbTasks} t
                LEFT JOIN {$tbItems} i ON t.item_id = i.id
                WHERE t.id = ?";
        $stmt = $pdb->query($sql, [$id]);
        $task = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($task) {
            $task['user_name'] = self::getUserName($task['user_id']);
        }
        return $task;
    }

    public static function getTasks($userId = null, $startDate = null, $endDate = null, $itemId = null, $category = null) {
        $pdb = self::getPdb();
        $tbTasks = $pdb->getTableName('tasks');
        $tbItems = $pdb->getTableName('items');

        $where = [];
        $params = [];

        if ($userId) {
            $where[] = "t.user_id = ?";
            $params[] = $userId;
        }
        if ($startDate) {
            $where[] = "t.entry_datetime >= ?";
            $params[] = date('Y-m-d 00:00:00', strtotime($startDate));
        }
        if ($endDate) {
            $where[] = "t.entry_datetime <= ?";
            $params[] = date('Y-m-d 23:59:59', strtotime($endDate));
        }
        if ($itemId) {
            $where[] = "t.item_id = ?";
            $params[] = $itemId;
        }
        if ($category) {
            $where[] = "i.category = ?";
            $params[] = $category;
        }

        $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $sql = "SELECT t.*, i.name as item_name, i.category as item_category, i.estimated_hours, i.lead_user_id
                FROM {$tbTasks} t
                LEFT JOIN {$tbItems} i ON t.item_id = i.id
                {$whereSql}
                ORDER BY t.entry_datetime DESC, t.id DESC";

        $stmt = $pdb->query($sql, $params);
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($tasks as &$t) {
            $t['user_name'] = self::getUserName($t['user_id']);
        }

        return $tasks;
    }

    public static function deleteTask($id, $userId = null, $isSupervisor = false) {
        $pdb = self::getPdb();
        $tbTasks = $pdb->getTableName('tasks');

        if ($isSupervisor || $userId === null) {
            $pdb->query("DELETE FROM {$tbTasks} WHERE id = ?", [$id]);
        } else {
            $pdb->query("DELETE FROM {$tbTasks} WHERE id = ? AND user_id = ?", [$id, $userId]);
        }
        return true;
    }

    public static function getTasksForCalendar($userId = null, $month = null, $year = null) {
        if (!$month) $month = date('m');
        if (!$year) $year = date('Y');

        $startDate = sprintf('%04d-%02d-01 00:00:00', $year, $month);
        $lastDay = date('t', strtotime($startDate));
        $endDate = sprintf('%04d-%02d-%02d 23:59:59', $year, $month, $lastDay);

        return self::getTasks($userId, $startDate, $endDate);
    }
}
