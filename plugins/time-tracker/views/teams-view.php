<?php
$teams = TimeTrackerModel::getTeams();
$users = TimeTrackerModel::getAllUsers();

$editTeam = null;
if (isset($_GET['edit_team'])) {
    $editTeam = TimeTrackerModel::getTeamById((int)$_GET['edit_team']);
}
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-users-gear text-primary me-2"></i> Teams & Team Supervisors</h3>
            <p class="text-muted small mb-0">Organize users into teams, assign dedicated team supervisors, and track team activities.</p>
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

    <div class="row g-4">
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
</div>
