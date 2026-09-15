<?php
// Unit test script verifying Uncompleted Recurring Tasks Non-Recreation and Due By / Past Due Status
define('APP_ROOT', __DIR__ . '/');

session_start();
$_SESSION['user_id'] = 1;

function add_action($tag, $cb) {}
function add_filter($tag, $cb) {}
function has_permission($p) { return true; }
function has_role($r) { return true; }

class MockPdoConnection {
    public function lastInsertId() { return MockPDO::getLastId(); }
    public function prepare($sql) { return new MockStmt([]); }
}

class MockPDO {
    private static $recurringTasks = [
        ['id' => 1, 'team_id' => 1, 'item_id' => 10, 'task_name' => 'Daily Backup Check', 'frequency' => 'daily', 'set_days' => '', 'is_active' => 1]
    ];
    private static $recurringInstances = [
        ['id' => 1, 'recurring_task_id' => 1, 'team_id' => 1, 'due_date' => '2026-08-20', 'status' => 'pending'] // 5 days past due
    ];
    private static $lastId = 1;

    public static function getLastId() { return self::$lastId; }

    public function createTable($name, $sql) {}
    public function getTableName($table) { return "plug_time_tracker_" . $table; }

    public function query($sql, $params = []) {
        $sql = trim($sql);

        // SELECT active recurring tasks
        if (strpos($sql, 'SELECT * FROM plug_time_tracker_recurring_tasks WHERE is_active = 1') === 0) {
            return new MockStmt(self::$recurringTasks);
        }

        // SELECT pending recurring instances check
        if (strpos($sql, 'SELECT id FROM plug_time_tracker_recurring_instances WHERE recurring_task_id = ? AND status = \'pending\'') === 0) {
            $rtId = $params[0];
            foreach (self::$recurringInstances as $ri) {
                if ($ri['recurring_task_id'] == $rtId && $ri['status'] === 'pending') {
                    return new MockStmt([['id' => $ri['id']]]);
                }
            }
            return new MockStmt([]);
        }

        // INSERT instance
        if (strpos($sql, 'INSERT INTO plug_time_tracker_recurring_instances') === 0) {
            self::$lastId++;
            $inst = ['id' => self::$lastId, 'recurring_task_id' => $params[0], 'team_id' => $params[1], 'due_date' => $params[2], 'status' => 'pending'];
            self::$recurringInstances[] = $inst;
            return new MockStmt([]);
        }

        return new MockStmt([]);
    }
}

class MockStmt {
    private $data;
    public function __construct($data) { $this->data = $data; }
    public function fetchAll($mode = null) { return $this->data; }
    public function fetch($mode = null) { return isset($this->data[0]) ? $this->data[0] : false; }
    public function execute($params = []) {}
}

class PluginDatabase {
    public function __construct($slug) {}
    public function createTable($name, $sql) {}
    public function getTableName($table) { return "plug_time_tracker_" . $table; }
    public function query($sql, $params = []) {
        global $mockPdo;
        return $mockPdo->query($sql, $params);
    }
}

$mockPdo = new MockPDO();
$mockConn = new MockPdoConnection();

function get_db_connection() { global $mockConn; return $mockConn; }

require_once __DIR__ . '/plugins/time-tracker/models/time-tracker-models.php';

echo "=== TESTING UNCOMPLETED RECURRING TASK NON-RECREATION & DUE BY TIMER ===\n";

// 1. Run generatePendingRecurringInstances when pending instance already exists
$prevCount = MockPDO::getLastId();
TimeTrackerModel::generatePendingRecurringInstances();
$afterCount = MockPDO::getLastId();
assert($prevCount === $afterCount);
echo "Test 1: System does NOT recreate recurring task when pending unfinished instance exists PASS\n";

// 2. Test getRecurringInstanceDueStatus for past due, due today, and future due dates
$pastDueStatus = TimeTrackerModel::getRecurringInstanceDueStatus(date('Y-m-d', strtotime('-3 days')));
assert($pastDueStatus['is_past_due'] === true);
assert(strpos($pastDueStatus['label'], 'PAST DUE by 3 days') !== false);
echo "Test 2: Past Due badge calculation PASS (" . $pastDueStatus['label'] . ")\n";

$dueTodayStatus = TimeTrackerModel::getRecurringInstanceDueStatus(date('Y-m-d'));
assert($dueTodayStatus['is_past_due'] === false);
assert($dueTodayStatus['label'] === 'DUE TODAY');
echo "Test 3: Due Today badge calculation PASS (" . $dueTodayStatus['label'] . ")\n";

$futureStatus = TimeTrackerModel::getRecurringInstanceDueStatus(date('Y-m-d', strtotime('+2 days')));
assert($futureStatus['is_past_due'] === false);
assert(strpos($futureStatus['label'], 'Due in 2 days') !== false);
echo "Test 4: Future Due badge calculation PASS (" . $futureStatus['label'] . ")\n";

echo "=== ALL RECURRING DUE TIMER TESTS PASSED SUCCESSFULLY ===\n";
