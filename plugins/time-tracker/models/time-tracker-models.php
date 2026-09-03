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

        $pdb->createTable('categories', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            slug VARCHAR(64) NOT NULL UNIQUE,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            is_enabled TINYINT(1) NOT NULL DEFAULT 1,
            is_custom TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_is_enabled (is_enabled)
        ");

        $pdb->createTable('items', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            category VARCHAR(64) NOT NULL DEFAULT 'project',
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            estimated_hours DECIMAL(8,2) NULL,
            lead_user_id INT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            is_archived TINYINT(1) NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY idx_category (category),
            KEY idx_is_active (is_active),
            KEY idx_is_archived (is_archived)
        ");

        // Seed default categories
        self::seedDefaultCategories();

        $pdb->createTable('tasks', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            item_id INT NOT NULL,
            task_name VARCHAR(255) NOT NULL,
            ticket_ref VARCHAR(64) NULL,
            is_billable TINYINT(1) NOT NULL DEFAULT 1,
            is_overtime TINYINT(1) NOT NULL DEFAULT 0,
            hours DECIMAL(6,2) NOT NULL DEFAULT 0.00,
            entry_datetime DATETIME NOT NULL,
            status ENUM('in_progress', 'completed') NOT NULL DEFAULT 'completed',
            last_checkin_at DATETIME NULL,
            checkin_token VARCHAR(64) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_datetime (user_id, entry_datetime),
            KEY idx_user_id (user_id),
            KEY idx_item_id (item_id),
            KEY idx_entry_datetime (entry_datetime),
            KEY idx_status (status),
            KEY idx_checkin_token (checkin_token),
            KEY idx_ticket_ref (ticket_ref)
        ");

        $pdb->createTable('teams', "
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            description TEXT NULL,
            supervisor_user_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ");

        $pdb->createTable('team_members', "
            team_id INT NOT NULL,
            user_id INT NOT NULL,
            PRIMARY KEY (team_id, user_id),
            KEY idx_user_id (user_id)
        ");

        $pdb->createTable('settings', "
            setting_key VARCHAR(64) PRIMARY KEY,
            setting_value TEXT NULL
        ");
    }

    /* ================= ACTIVE TASK TIMER & CHECKIN METHODS ================= */

    public static function getActiveTaskForUser($userId) {
        $pdb = self::getPdb();
        $tbTasks = $pdb->getTableName('tasks');
        $tbItems = $pdb->getTableName('items');

        $sql = "SELECT t.*, i.name as item_name, i.category as item_category
                FROM {$tbTasks} t
                LEFT JOIN {$tbItems} i ON t.item_id = i.id
                WHERE t.user_id = ? AND t.status = 'in_progress'
                ORDER BY t.id DESC LIMIT 1";

        $stmt = $pdb->query($sql, [$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function startTaskTimer($userId, $itemId, $taskName, $ticketRef = '', $isBillable = 1, $isOvertime = 0) {
        $pdb = self::getPdb();
        $tbTasks = $pdb->getTableName('tasks');

        // Check if user already has an active in_progress task
        $activeTask = self::getActiveTaskForUser($userId);
        if ($activeTask) {
            throw new Exception("You already have an active running task ('" . htmlspecialchars($activeTask['task_name']) . "'). Please mark it as finished before starting a new one.");
        }

        $now = date('Y-m-d H:i:s');
        if (self::isDatetimeDuplicate($userId, $now)) {
            throw new Exception("Validation Error: Another task entry already exists at the current datetime ($now).");
        }

        // Generate secure checkin_token for SSO expiry resilience
        $token = bin2hex(random_bytes(32));

        $pdb->query(
            "INSERT INTO {$tbTasks} (user_id, item_id, task_name, ticket_ref, is_billable, is_overtime, hours, entry_datetime, status, last_checkin_at, checkin_token) VALUES (?, ?, ?, ?, ?, ?, 0.00, ?, 'in_progress', ?, ?)",
            [$userId, $itemId, trim($taskName), trim($ticketRef), $isBillable ? 1 : 0, $isOvertime ? 1 : 0, $now, $now, $token]
        );

        return get_db_connection()->lastInsertId();
    }

    public static function checkinTaskResponse($taskId, $userId = null, $action = 'still_working', $checkinToken = null) {
        $pdb = self::getPdb();
        $tbTasks = $pdb->getTableName('tasks');

        $task = self::getTaskById($taskId);
        if (!$task) {
            throw new Exception("Task not found.");
        }

        // Verify session user OR valid checkin token (resilient to expired SSO session)
        $authorized = false;
        if ($userId && (int)$task['user_id'] === (int)$userId) {
            $authorized = true;
        } elseif (!empty($checkinToken) && !empty($task['checkin_token']) && hash_equals($task['checkin_token'], $checkinToken)) {
            $authorized = true;
        }

        if (!$authorized) {
            throw new Exception("Access Denied: Invalid session or check-in token.");
        }

        $now = date('Y-m-d H:i:s');

        if ($action === 'finished') {
            $startTs = strtotime($task['entry_datetime']);
            $nowTs = time();
            $elapsedHours = max(0.1, round(($nowTs - $startTs) / 3600, 2));

            $pdb->query(
                "UPDATE {$tbTasks} SET status = 'completed', hours = ?, last_checkin_at = ? WHERE id = ?",
                [$elapsedHours, $now, $taskId]
            );
            return 'finished';
        } else { // 'still_working'
            $startTs = strtotime($task['entry_datetime']);
            $nowTs = time();
            $runningHours = max(0.1, round(($nowTs - $startTs) / 3600, 2));

            $pdb->query(
                "UPDATE {$tbTasks} SET hours = ?, last_checkin_at = ? WHERE id = ?",
                [$runningHours, $now, $taskId]
            );
            return 'still_working';
        }
    }

    public static function getTasksNeedingCheckin() {
        $pdb = self::getPdb();
        $tbTasks = $pdb->getTableName('tasks');

        // Query in_progress tasks where last_checkin_at is older than 15 minutes (900 seconds)
        $fifteenMinsAgo = date('Y-m-d H:i:s', time() - 900);
        $sql = "SELECT t.* FROM {$tbTasks} t WHERE t.status = 'in_progress' AND (t.last_checkin_at IS NULL OR t.last_checkin_at <= ?)";
        $stmt = $pdb->query($sql, [$fifteenMinsAgo]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /* ================= SETTINGS & FINANCIAL YEAR METHODS ================= */

    public static function getSetting($key, $default = null) {
        $pdb = self::getPdb();
        $tbSettings = $pdb->getTableName('settings');
        $stmt = $pdb->query("SELECT setting_value FROM {$tbSettings} WHERE setting_key = ?", [$key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($row && $row['setting_value'] !== null) ? $row['setting_value'] : $default;
    }

    public static function saveSetting($key, $value) {
        $pdb = self::getPdb();
        $tbSettings = $pdb->getTableName('settings');
        $pdb->query("INSERT INTO {$tbSettings} (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?", [$key, $value, $value]);
        return true;
    }

    public static function getFinancialYearStartConfig() {
        $month = (int)self::getSetting('fy_start_month', 9);
        $day = (int)self::getSetting('fy_start_day', 1);
        return ['month' => $month, 'day' => $day];
    }

    public static function getFinancialYearDateRange($fyYear) {
        $config = self::getFinancialYearStartConfig();
        $m = $config['month'];
        $d = $config['day'];

        if ($m === 1 && $d === 1) {
            $startDate = sprintf('%04d-01-01', $fyYear);
            $endDate = sprintf('%04d-12-31', $fyYear);
        } else {
            $startDate = sprintf('%04d-%02d-%02d', $fyYear - 1, $m, $d);
            $startNextTs = strtotime(sprintf('%04d-%02d-%02d', $fyYear, $m, $d));
            $endTs = strtotime('-1 day', $startNextTs);
            $endDate = date('Y-m-d', $endTs);
        }

        return [
            'fy_year' => (int)$fyYear,
            'start_date' => $startDate,
            'end_date' => $endDate
        ];
    }

    public static function getCurrentFinancialYear($dateStr = null) {
        if (!$dateStr) $dateStr = date('Y-m-d');
        $ts = strtotime($dateStr);
        $calYear = (int)date('Y', $ts);

        $config = self::getFinancialYearStartConfig();
        $m = $config['month'];
        $d = $config['day'];

        if ($m === 1 && $d === 1) {
            return $calYear;
        }

        $fyStartThisYear = strtotime(sprintf('%04d-%02d-%02d', $calYear, $m, $d));
        if ($ts >= $fyStartThisYear) {
            return $calYear + 1;
        } else {
            return $calYear;
        }
    }

    public static function getAvailableFinancialYears() {
        $currFy = self::getCurrentFinancialYear();
        $years = [];
        for ($i = $currFy - 3; $i <= $currFy + 1; $i++) {
            $years[] = $i;
        }
        return $years;
    }

    public static function getAllUsers() {
        try {
            $db = get_db_connection();
            $stmt = $db->query("SELECT id, display_name, email FROM users ORDER BY display_name ASC");
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($users)) return $users;
        } catch (Exception $e) {}

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

    /* ================= TEAMS METHODS ================= */

    public static function getTeams() {
        $pdb = self::getPdb();
        $tbTeams = $pdb->getTableName('teams');
        $stmt = $pdb->query("SELECT * FROM {$tbTeams} ORDER BY name ASC");
        $teams = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($teams as &$t) {
            $t['supervisor_name'] = self::getUserName($t['supervisor_user_id']);
            $t['members'] = self::getTeamMembers($t['id']);
            $t['member_count'] = count($t['members']);
        }
        return $teams;
    }

    public static function getTeamById($id) {
        $pdb = self::getPdb();
        $tbTeams = $pdb->getTableName('teams');
        $stmt = $pdb->query("SELECT * FROM {$tbTeams} WHERE id = ?", [$id]);
        $team = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($team) {
            $team['supervisor_name'] = self::getUserName($team['supervisor_user_id']);
            $team['members'] = self::getTeamMembers($team['id']);
        }
        return $team;
    }

    public static function getTeamMembers($teamId) {
        $pdb = self::getPdb();
        $tbMembers = $pdb->getTableName('team_members');
        $stmt = $pdb->query("SELECT user_id FROM {$tbMembers} WHERE team_id = ?", [$teamId]);
        $rows = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $result = [];
        foreach ($rows as $uid) {
            $result[] = [
                'user_id' => $uid,
                'user_name' => self::getUserName($uid)
            ];
        }
        return $result;
    }

    public static function getTeamUserIds($teamId) {
        $pdb = self::getPdb();
        $tbMembers = $pdb->getTableName('team_members');
        $stmt = $pdb->query("SELECT user_id FROM {$tbMembers} WHERE team_id = ?", [$teamId]);
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function saveTeam($id, $name, $description = '', $supervisor_user_id = null, $member_user_ids = []) {
        $pdb = self::getPdb();
        $tbTeams = $pdb->getTableName('teams');
        $tbMembers = $pdb->getTableName('team_members');

        if (empty(trim($name))) {
            throw new Exception("Team name cannot be empty.");
        }

        $supId = ($supervisor_user_id !== null && $supervisor_user_id !== '' && (int)$supervisor_user_id > 0) ? (int)$supervisor_user_id : null;

        if ($id > 0) {
            $pdb->query("UPDATE {$tbTeams} SET name = ?, description = ?, supervisor_user_id = ? WHERE id = ?", [trim($name), trim($description), $supId, $id]);
            $teamId = $id;
        } else {
            $pdb->query("INSERT INTO {$tbTeams} (name, description, supervisor_user_id) VALUES (?, ?, ?)", [trim($name), trim($description), $supId]);
            $teamId = get_db_connection()->lastInsertId();
        }

        $pdb->query("DELETE FROM {$tbMembers} WHERE team_id = ?", [$teamId]);
        if (!empty($member_user_ids)) {
            $uniqueUids = array_unique(array_map('intval', $member_user_ids));
            foreach ($uniqueUids as $uid) {
                if ($uid > 0) {
                    $pdb->query("INSERT INTO {$tbMembers} (team_id, user_id) VALUES (?, ?)", [$teamId, $uid]);
                }
            }
        }

        return $teamId;
    }

    public static function deleteTeam($id) {
        $pdb = self::getPdb();
        $tbTeams = $pdb->getTableName('teams');
        $tbMembers = $pdb->getTableName('team_members');

        $pdb->query("DELETE FROM {$tbMembers} WHERE team_id = ?", [$id]);
        $pdb->query("DELETE FROM {$tbTeams} WHERE id = ?", [$id]);
        return true;
    }

    /* ================= DYNAMIC CATEGORY METHODS ================= */

    public static function seedDefaultCategories() {
        $defaults = [
            ['slug' => 'project', 'name' => 'Projects', 'description' => 'IT Projects and Capital Deliverables', 'is_custom' => 0],
            ['slug' => 'support', 'name' => 'Support Activities', 'description' => 'Tier 1-3 Tickets, Troubleshooting, and Helpdesk', 'is_custom' => 0],
            ['slug' => 'maintenance', 'name' => 'Maintenance Activities', 'description' => 'System Patching, Upgrades, and Backups', 'is_custom' => 0]
        ];

        foreach ($defaults as $d) {
            try {
                self::saveCategory(0, $d['slug'], $d['name'], $d['description'], 1, $d['is_custom']);
            } catch (Exception $e) {}
        }
    }

    public static function getCategories($enabledOnly = false) {
        $pdb = self::getPdb();
        $tbCats = $pdb->getTableName('categories');

        $whereSql = $enabledOnly ? "WHERE is_enabled = 1" : "";
        $stmt = $pdb->query("SELECT * FROM {$tbCats} {$whereSql} ORDER BY is_custom ASC, name ASC");
        $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($categories)) {
            self::seedDefaultCategories();
            $stmt = $pdb->query("SELECT * FROM {$tbCats} {$whereSql} ORDER BY is_custom ASC, name ASC");
            $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }

        return $categories;
    }

    public static function getCategoryBySlug($slug) {
        $pdb = self::getPdb();
        $tbCats = $pdb->getTableName('categories');
        $stmt = $pdb->query("SELECT * FROM {$tbCats} WHERE slug = ?", [$slug]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function saveCategory($id, $slug, $name, $description = '', $isEnabled = 1, $isCustom = 1) {
        $pdb = self::getPdb();
        $tbCats = $pdb->getTableName('categories');

        $cleanSlug = preg_replace('/[^a-zA-Z0-9_]/', '_', strtolower(trim($slug)));
        if (empty($cleanSlug)) {
            throw new Exception("Category slug identifier cannot be empty.");
        }
        if (empty(trim($name))) {
            throw new Exception("Category display name cannot be empty.");
        }

        $enabledVal = $isEnabled ? 1 : 0;
        $customVal = $isCustom ? 1 : 0;

        if ($id > 0) {
            $pdb->query("UPDATE {$tbCats} SET name = ?, description = ?, is_enabled = ? WHERE id = ?", [trim($name), trim($description), $enabledVal, $id]);
            return $id;
        } else {
            // Check for slug uniqueness
            $existing = self::getCategoryBySlug($cleanSlug);
            if ($existing) {
                // If existing, update it
                $pdb->query("UPDATE {$tbCats} SET name = ?, description = ?, is_enabled = ? WHERE slug = ?", [trim($name), trim($description), $enabledVal, $cleanSlug]);
                return $existing['id'];
            }

            $pdb->query("INSERT INTO {$tbCats} (slug, name, description, is_enabled, is_custom) VALUES (?, ?, ?, ?, ?)", [$cleanSlug, trim($name), trim($description), $enabledVal, $customVal]);
            return get_db_connection()->lastInsertId();
        }
    }

    public static function toggleCategoryEnabled($id, $isEnabled) {
        $pdb = self::getPdb();
        $tbCats = $pdb->getTableName('categories');
        $pdb->query("UPDATE {$tbCats} SET is_enabled = ? WHERE id = ?", [$isEnabled ? 1 : 0, $id]);
        return true;
    }

    public static function deleteCategory($id) {
        $pdb = self::getPdb();
        $tbCats = $pdb->getTableName('categories');
        $stmt = $pdb->query("SELECT * FROM {$tbCats} WHERE id = ?", [$id]);
        $cat = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$cat) return false;
        if ($cat['is_custom'] == 0) {
            throw new Exception("System default categories cannot be deleted.");
        }

        $pdb->query("DELETE FROM {$tbCats} WHERE id = ?", [$id]);
        return true;
    }

    /* ================= ITEMS & PROJECTS METHODS ================= */

    public static function getEnabledCategoryTypes() {
        $cats = self::getCategories(true);
        return array_column($cats, 'slug');
    }

    public static function getItems($category = null, $activeOnly = false, $includeArchived = false) {
        $pdb = self::getPdb();
        $tbItems = $pdb->getTableName('items');

        $where = [];
        $params = [];

        if ($category && in_array($category, ['project', 'support', 'maintenance'])) {
            $where[] = "category = ?";
            $params[] = $category;
        }

        if ($activeOnly) {
            $where[] = "is_active = 1";
            $where[] = "is_archived = 0";
            $enabledCats = self::getEnabledCategoryTypes();
            if (empty($enabledCats)) {
                return [];
            }
            $inClause = implode(',', array_fill(0, count($enabledCats), '?'));
            $where[] = "category IN ({$inClause})";
            foreach ($enabledCats as $ec) {
                $params[] = $ec;
            }
        } elseif (!$includeArchived) {
            $where[] = "is_archived = 0";
        }

        $whereSql = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";

        $stmt = $pdb->query("SELECT * FROM {$tbItems} {$whereSql} ORDER BY category ASC, name ASC", $params);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $tbTasks = $pdb->getTableName('tasks');
        foreach ($items as &$item) {
            $sumStmt = $pdb->query("SELECT SUM(hours) as total_hours FROM {$tbTasks} WHERE item_id = ?", [$item['id']]);
            $sumRow = $sumStmt->fetch(PDO::FETCH_ASSOC);
            $item['actual_hours'] = $sumRow && $sumRow['total_hours'] ? (float)$sumRow['total_hours'] : 0.0;
            $item['lead_user_name'] = self::getUserName($item['lead_user_id']);
        }

        return $items;
    }

    public static function toggleItemActive($id, $isActive) {
        $pdb = self::getPdb();
        $tbItems = $pdb->getTableName('items');
        $pdb->query("UPDATE {$tbItems} SET is_active = ? WHERE id = ?", [$isActive ? 1 : 0, $id]);
        return true;
    }

    public static function archiveItem($id, $isArchived) {
        $pdb = self::getPdb();
        $tbItems = $pdb->getTableName('items');
        $pdb->query("UPDATE {$tbItems} SET is_archived = ? WHERE id = ?", [$isArchived ? 1 : 0, $id]);
        return true;
    }

    public static function getProjectsLedByUser($userId) {
        $pdb = self::getPdb();
        $tbItems = $pdb->getTableName('items');
        $tbTasks = $pdb->getTableName('tasks');

        $stmt = $pdb->query("SELECT * FROM {$tbItems} WHERE lead_user_id = ? ORDER BY name ASC", [$userId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    public static function saveItem($id, $category, $name, $description = '', $estimated_hours = null, $lead_user_id = null, $is_active = 1) {
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
        $activeVal = $is_active ? 1 : 0;

        if ($id > 0) {
            $pdb->query(
                "UPDATE {$tbItems} SET category = ?, name = ?, description = ?, estimated_hours = ?, lead_user_id = ?, is_active = ? WHERE id = ?",
                [$category, trim($name), trim($description), $estHours, $leadUser, $activeVal, $id]
            );
            return $id;
        } else {
            $pdb->query(
                "INSERT INTO {$tbItems} (category, name, description, estimated_hours, lead_user_id, is_active) VALUES (?, ?, ?, ?, ?, ?)",
                [$category, trim($name), trim($description), $estHours, $leadUser, $activeVal]
            );
            return get_db_connection()->lastInsertId();
        }
    }

    public static function deleteItem($id) {
        $pdb = self::getPdb();
        $tbItems = $pdb->getTableName('items');
        $tbTasks = $pdb->getTableName('tasks');

        $pdb->query("DELETE FROM {$tbTasks} WHERE item_id = ?", [$id]);
        $pdb->query("DELETE FROM {$tbItems} WHERE id = ?", [$id]);
        return true;
    }

    /* ================= TASKS METHODS ================= */

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

    public static function saveTask($taskId, $userId, $itemId, $taskName, $hours, $entryDatetime, $status = 'completed', $ticketRef = '', $isBillable = 1, $isOvertime = 0) {
        $pdb = self::getPdb();
        $tbTasks = $pdb->getTableName('tasks');

        if (empty(trim($taskName))) {
            throw new Exception("Task description/name cannot be empty.");
        }

        $numHours = (float)$hours;
        if ($numHours <= 0 && $status === 'completed') {
            throw new Exception("Number of hours spent must be greater than 0.");
        }

        if (!$itemId || (int)$itemId <= 0) {
            throw new Exception("Please select a valid Project, Support, or Maintenance item.");
        }

        $item = self::getItemById($itemId);
        if (!$item) {
            throw new Exception("Selected Project/Category item does not exist.");
        }

        $formattedDt = date('Y-m-d H:i:s', strtotime($entryDatetime));
        if (!$formattedDt || $formattedDt === '1970-01-01 00:00:00') {
            throw new Exception("Invalid date and time provided.");
        }

        if (self::isDatetimeDuplicate($userId, $formattedDt, $taskId)) {
            throw new Exception("Validation Error: User already has another task logged for exact datetime (" . date('Y-m-d H:i', strtotime($formattedDt)) . ").");
        }

        if ($taskId > 0) {
            $existingTask = self::getTaskById($taskId);
            if (!$existingTask) {
                throw new Exception("Task not found.");
            }

            $pdb->query(
                "UPDATE {$tbTasks} SET user_id = ?, item_id = ?, task_name = ?, ticket_ref = ?, is_billable = ?, is_overtime = ?, hours = ?, entry_datetime = ?, status = ? WHERE id = ?",
                [$userId, $itemId, trim($taskName), trim($ticketRef), $isBillable ? 1 : 0, $isOvertime ? 1 : 0, $numHours, $formattedDt, $status, $taskId]
            );
            return $taskId;
        } else {
            $pdb->query(
                "INSERT INTO {$tbTasks} (user_id, item_id, task_name, ticket_ref, is_billable, is_overtime, hours, entry_datetime, status, last_checkin_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [$userId, $itemId, trim($taskName), trim($ticketRef), $isBillable ? 1 : 0, $isOvertime ? 1 : 0, $numHours, $formattedDt, $status, date('Y-m-d H:i:s')]
            );
            return get_db_connection()->lastInsertId();
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

    public static function getTasks($userId = null, $startDate = null, $endDate = null, $itemId = null, $category = null, $teamId = null) {
        $pdb = self::getPdb();
        $tbTasks = $pdb->getTableName('tasks');
        $tbItems = $pdb->getTableName('items');

        $where = [];
        $params = [];

        if ($userId) {
            $where[] = "t.user_id = ?";
            $params[] = $userId;
        }
        if ($teamId) {
            $teamUserIds = self::getTeamUserIds($teamId);
            if (empty($teamUserIds)) {
                return [];
            }
            $inClause = implode(',', array_fill(0, count($teamUserIds), '?'));
            $where[] = "t.user_id IN ({$inClause})";
            foreach ($teamUserIds as $tuid) {
                $params[] = $tuid;
            }
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
