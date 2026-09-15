<?php
$teams = TimeTrackerModel::getTeams();
$users = TimeTrackerModel::getAllUsers();
$items = TimeTrackerModel::getItems(null, true);

$editTeam = null;
if (isset($_GET['edit_team'])) {
    $editTeam = TimeTrackerModel::getTeamById((int)$_GET['edit_team']);
}

$selectedTeamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : (!empty($teams) ? $teams[0]['id'] : 0);
$recurringTasks = $selectedTeamId > 0 ? TimeTrackerModel::getRecurringTasksForTeam($selectedTeamId) : [];
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-users-gear text-primary me-2"></i> Teams & Team Supervisors</h3>
            <p class="text-muted small mb-0">Organize users into teams, assign team supervisors, and configure advanced recurring critical team tasks with allocated time.</p>
        </div>
        <div>
            <a href="index.php?route=time_tracker_supervisor" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Supervisor Console
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

    <div class="row g-4 mb-4">
        <!-- Add / Edit Team Form -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid <?= $editTeam ? 'fa-pen-to-square' : 'fa-plus' ?> me-2"></i>
                        <?= $editTeam ? 'Edit Team Configuration' : 'Create New Team' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form action="index.php?route=time_tracker_teams" method="POST">
                        <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                        <input type="hidden" name="action" value="save_team">
                        <?php if ($editTeam): ?>
                            <input type="hidden" name="team_id" value="<?= $editTeam['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Team Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Infrastructure Support, Cloud Devs" value="<?= htmlspecialchars($editTeam['name'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Team Description</label>
                            <textarea name="description" class="form-control" rows="2" placeholder="Optional details..."><?= htmlspecialchars($editTeam['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Team Supervisor</label>
                            <select name="supervisor_user_id" class="form-select">
                                <option value="">-- Select Team Supervisor --</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($editTeam && $editTeam['supervisor_user_id'] == $u['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['display_name'] ?? $u['email']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Assign Team Members</label>
                            <div class="border rounded p-2 bg-light" style="max-height: 180px; overflow-y: auto;">
                                <?php
                                $existingMemberIds = $editTeam ? array_column($editTeam['members'], 'user_id') : [];
                                foreach ($users as $u):
                                    $checked = in_array($u['id'], $existingMemberIds) ? 'checked' : '';
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="member_user_ids[]" value="<?= $u['id'] ?>" id="user_chk_<?= $u['id'] ?>" <?= $checked ?>>
                                        <label class="form-check-label small" for="user_chk_<?= $u['id'] ?>">
                                            <?= htmlspecialchars($u['display_name'] ?? $u['email']) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <?php if ($editTeam): ?>
                                <a href="index.php?route=time_tracker_teams" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark me-1"></i> Cancel</a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary btn-sm ms-auto px-4">
                                <i class="fa-solid fa-floppy-disk me-1"></i> <?= $editTeam ? 'Update Team' : 'Create Team' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Teams Directory -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light py-3">
                    <h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-list me-2"></i> Active IT Support & Project Teams</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Team Name</th>
                                    <th>Team Supervisor</th>
                                    <th>Members</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($teams)): ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted">No teams created yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($teams as $t): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-dark d-block"><?= htmlspecialchars($t['name']) ?></strong>
                                                <?php if (!empty($t['description'])): ?>
                                                    <small class="text-muted d-block"><?= htmlspecialchars($t['description']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary">
                                                    <i class="fa-solid fa-user-shield me-1"></i> <?= htmlspecialchars($t['supervisor_name']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php if (empty($t['members'])): ?>
                                                    <span class="text-muted small">No members assigned</span>
                                                <?php else: ?>
                                                    <?php foreach ($t['members'] as $m): ?>
                                                        <span class="badge bg-light text-dark border me-1 mb-1"><?= htmlspecialchars($m['user_name']) ?></span>
                                                    <?php endforeach; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <a href="index.php?route=time_tracker_teams&team_id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-danger me-1" title="Manage Critical Tasks">
                                                    <i class="fa-solid fa-rotate-left"></i> Recurring
                                                </a>
                                                <a href="index.php?route=time_tracker_teams&edit_team=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <form action="index.php?route=time_tracker_teams" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this team?');">
                                                    <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                                                    <input type="hidden" name="action" value="delete_team">
                                                    <input type="hidden" name="team_id" value="<?= $t['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recurring Critical Tasks Configuration Section -->
    <?php if (!empty($teams)): ?>
    <div class="card shadow-sm border-0">
        <div class="card-header bg-danger text-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0"><i class="fa-solid fa-rotate me-2"></i> Recurring Critical Team Tasks Manager</h5>
            <div class="d-flex align-items-center gap-2">
                <span class="small">Team:</span>
                <select onchange="location.href='index.php?route=time_tracker_teams&team_id=' + this.value;" class="form-select form-select-sm bg-white text-dark fw-bold" style="width: auto;">
                    <?php foreach ($teams as $t): ?>
                        <option value="<?= $t['id'] ?>" <?= $selectedTeamId == $t['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($t['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="card-body">
            <div class="row g-4">
                <!-- Add Recurring Task Form -->
                <div class="col-lg-5 border-end">
                    <h6 class="fw-bold mb-3 text-danger"><i class="fa-solid fa-plus me-1"></i> Add Recurring Critical Task</h6>
                    <form action="index.php?route=time_tracker_teams&team_id=<?= $selectedTeamId ?>" method="POST">
                        <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                        <input type="hidden" name="action" value="save_recurring_task">
                        <input type="hidden" name="team_id" value="<?= $selectedTeamId ?>">

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Critical Task Name</label>
                            <input type="text" name="task_name" class="form-control form-control-sm" placeholder="e.g. Daily Backup Verification, Weekly Firewall Audit" required>
                        </div>

                        <div class="mb-2">
                            <label class="form-label small fw-bold">Project / Category Item</label>
                            <select name="item_id" class="form-select form-select-sm" required>
                                <option value="">Select Project / Category...</option>
                                <?php foreach ($items as $i): ?>
                                    <option value="<?= $i['id'] ?>">[<?= ucfirst($i['category']) ?>] <?= htmlspecialchars($i['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="row g-2 mb-2">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Recurrence Schedule</label>
                                <select id="rt_frequency_select" name="frequency" class="form-select form-select-sm" onchange="toggleScheduleOptions(this.value);" required>
                                    <option value="daily">Daily Check</option>
                                    <option value="weekly">Weekly (Selected Day)</option>
                                    <option value="set_days">Set Days of Week</option>
                                    <option value="monthly">Monthly</option>
                                    <option value="quarterly">Quarterly</option>
                                    <option value="yearly">Yearly (Specific Date)</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold">Allocated Time Given (Hrs)</label>
                                <input type="number" step="0.25" min="0.1" name="allocated_hours" class="form-control form-control-sm" placeholder="e.g. 1.5">
                                <div class="form-text small">Time allocated for completion.</div>
                            </div>
                        </div>

                        <!-- Schedule Sub-Options Dynamic Container -->
                        <div id="schedule_sub_options" class="mb-2 p-2 bg-light rounded border">
                            <!-- Weekly Day Selector -->
                            <div id="opt_weekly" class="sched-opt">
                                <label class="form-label small fw-bold mb-1">Day of the Week</label>
                                <select name="schedule_config_weekly" class="form-select form-select-sm">
                                    <option value="1">Monday</option>
                                    <option value="2">Tuesday</option>
                                    <option value="3">Wednesday</option>
                                    <option value="4">Thursday</option>
                                    <option value="5">Friday</option>
                                    <option value="6">Saturday</option>
                                    <option value="7">Sunday</option>
                                </select>
                            </div>

                            <!-- Set Days Selector -->
                            <div id="opt_set_days" class="sched-opt d-none">
                                <label class="form-label small fw-bold mb-1">Set Days of Week</label>
                                <input type="text" name="set_days" class="form-control form-control-sm" placeholder="1,3,5 or Mon,Wed,Fri">
                                <div class="form-text small">e.g. 1,3,5 for Mon, Wed, Fri.</div>
                            </div>

                            <!-- Monthly Sub-Options -->
                            <div id="opt_monthly" class="sched-opt d-none">
                                <label class="form-label small fw-bold mb-1">Monthly Schedule Mode</label>
                                <select name="schedule_config_monthly" class="form-select form-select-sm">
                                    <option value="day_1">1st of the Month</option>
                                    <option value="day_15">15th of the Month</option>
                                    <option value="day_28">28th of the Month</option>
                                    <option value="nth_1_1">1st Monday of Month</option>
                                    <option value="nth_2_1">2nd Monday of Month</option>
                                    <option value="nth_3_2">3rd Tuesday of Month</option>
                                    <option value="nth_3_4">3rd Thursday of Month</option>
                                    <option value="nth_4_5">4th Friday of Month</option>
                                </select>
                            </div>

                            <!-- Yearly Date Selector -->
                            <div id="opt_yearly" class="sched-opt d-none">
                                <label class="form-label small fw-bold mb-1">Yearly Specific Date (MM-DD)</label>
                                <input type="text" name="schedule_config_yearly" class="form-control form-control-sm" placeholder="e.g. 09-01 (Sept 1st)">
                            </div>
                        </div>

                        <input type="hidden" id="final_schedule_config" name="schedule_config" value="">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Instructions / Description</label>
                            <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Details or checklist for team members..."></textarea>
                        </div>

                        <button type="submit" onclick="prepareScheduleConfig();" class="btn btn-danger btn-sm w-100 fw-bold">
                            <i class="fa-solid fa-clock-rotate-left me-1"></i> Save & Schedule Recurring Task
                        </button>
                    </form>
                </div>

                <!-- Active Recurring Tasks List -->
                <div class="col-lg-7">
                    <h6 class="fw-bold mb-3 text-secondary"><i class="fa-solid fa-list-check me-1"></i> Active Scheduled Critical Tasks</h6>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0 small">
                            <thead class="table-light">
                                <tr>
                                    <th>Task Name</th>
                                    <th>Category Item</th>
                                    <th>Frequency & Config</th>
                                    <th>Allocated Time</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recurringTasks)): ?>
                                    <tr><td colspan="5" class="text-center py-4 text-muted">No recurring critical tasks scheduled for this team.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recurringTasks as $rt): ?>
                                        <tr>
                                            <td>
                                                <strong class="text-dark d-block"><?= htmlspecialchars($rt['task_name']) ?></strong>
                                                <?php if (!empty($rt['description'])): ?>
                                                    <span class="text-muted d-block small"><?= htmlspecialchars($rt['description']) ?></span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-secondary"><?= htmlspecialchars($rt['item_name']) ?></span></td>
                                            <td>
                                                <span class="badge bg-danger text-capitalize"><?= htmlspecialchars($rt['frequency']) ?></span>
                                                <?php if (!empty($rt['schedule_config'])): ?>
                                                    <code class="d-block small mt-1"><?= htmlspecialchars($rt['schedule_config']) ?></code>
                                                <?php endif; ?>
                                            </td>
                                            <td class="fw-bold text-success">
                                                <?= !empty($rt['allocated_hours']) ? number_format($rt['allocated_hours'], 2) . ' hrs' : '&mdash;' ?>
                                            </td>
                                            <td class="text-end">
                                                <form action="index.php?route=time_tracker_teams&team_id=<?= $selectedTeamId ?>" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this recurring task schedule?');">
                                                    <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                                                    <input type="hidden" name="action" value="delete_recurring_task">
                                                    <input type="hidden" name="recurring_task_id" value="<?= $rt['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger py-0 px-2">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    function toggleScheduleOptions(freq) {
        document.querySelectorAll('.sched-opt').forEach(function(el) { el.classList.add('d-none'); });
        if (freq === 'weekly') {
            document.getElementById('opt_weekly').classList.remove('d-none');
        } else if (freq === 'set_days') {
            document.getElementById('opt_set_days').classList.remove('d-none');
        } else if (freq === 'monthly') {
            document.getElementById('opt_monthly').classList.remove('d-none');
        } else if (freq === 'yearly') {
            document.getElementById('opt_yearly').classList.remove('d-none');
        }
    }

    function prepareScheduleConfig() {
        var freq = document.getElementById('rt_frequency_select').value;
        var cfg = '';
        if (freq === 'weekly') {
            cfg = document.querySelector('[name="schedule_config_weekly"]').value;
        } else if (freq === 'monthly') {
            cfg = document.querySelector('[name="schedule_config_monthly"]').value;
        } else if (freq === 'yearly') {
            cfg = document.querySelector('[name="schedule_config_yearly"]').value;
        }
        document.getElementById('final_schedule_config').value = cfg;
    }

    document.addEventListener("DOMContentLoaded", function() {
        toggleScheduleOptions(document.getElementById('rt_frequency_select').value);
    });
    </script>
    <?php endif; ?>
</div>
