<?php
$items = TimeTrackerModel::getItems();
$users = TimeTrackerModel::getAllUsers();

$editItem = null;
if (isset($_GET['edit_item'])) {
    $editItem = TimeTrackerModel::getItemById((int)$_GET['edit_item']);
}
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-folder-tree text-primary me-2"></i> Projects & Categories</h3>
            <p class="text-muted small mb-0">Manage Projects, Support Activities, and Maintenance Activities for time tracking.</p>
        </div>
        <div>
            <a href="index.php?route=time_tracker" class="btn btn-outline-secondary btn-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
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
        <!-- Add / Edit Item Form -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-dark text-white py-3">
                    <h5 class="fw-bold mb-0">
                        <i class="fa-solid <?= $editItem ? 'fa-pen-to-square' : 'fa-plus' ?> me-2"></i>
                        <?= $editItem ? 'Edit Category Item' : 'Create Category Item' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form action="index.php?route=time_tracker_items" method="POST">
                        <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                        <input type="hidden" name="action" value="save_item">
                        <?php if ($editItem): ?>
                            <input type="hidden" name="item_id" value="<?= $editItem['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Category Type</label>
                            <select name="category" class="form-select" required>
                                <option value="project" <?= ($editItem && $editItem['category'] === 'project') ? 'selected' : '' ?>>Project</option>
                                <option value="support" <?= ($editItem && $editItem['category'] === 'support') ? 'selected' : '' ?>>Support Activity</option>
                                <option value="maintenance" <?= ($editItem && $editItem['category'] === 'maintenance') ? 'selected' : '' ?>>Maintenance Activity</option>
                            </select>
                            <div class="form-text small">Select category under which tasks will be grouped.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Item / Project Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Migration to Cloud, Tier 2 Ticket Support" value="<?= htmlspecialchars($editItem['name'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Description</label>
                            <textarea name="description" class="form-control" rows="3" placeholder="Optional details about this project/activity..."><?= htmlspecialchars($editItem['description'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Estimated Hours (Optional)</label>
                            <input type="number" step="0.5" min="0" name="estimated_hours" class="form-control" placeholder="e.g. 100" value="<?= htmlspecialchars($editItem['estimated_hours'] ?? '') ?>">
                            <div class="form-text small">Used for project progress tracking in Supervisor View.</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Project Lead User (Optional)</label>
                            <select name="lead_user_id" class="form-select">
                                <option value="">-- No Project Lead Assigned --</option>
                                <?php foreach ($users as $u): ?>
                                    <option value="<?= $u['id'] ?>" <?= ($editItem && $editItem['lead_user_id'] == $u['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($u['display_name'] ?? $u['email']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="is_active" value="1" id="chk_item_active" <?= (!isset($editItem['is_active']) || $editItem['is_active'] == 1) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold small" for="chk_item_active">Active (Visible in Task Logging)</label>
                        </div>

                        <div class="d-flex justify-content-between">
                            <?php if ($editItem): ?>
                                <a href="index.php?route=time_tracker_items" class="btn btn-secondary btn-sm"><i class="fa-solid fa-xmark me-1"></i> Cancel</a>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary btn-sm ms-auto px-4">
                                <i class="fa-solid fa-floppy-disk me-1"></i> <?= $editItem ? 'Update Item' : 'Create Item' ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Items Table -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-light py-3">
                    <h5 class="fw-bold mb-0 text-secondary"><i class="fa-solid fa-list-check me-2"></i> Configured Categories & Items</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Status</th>
                                    <th>Category</th>
                                    <th>Name & Description</th>
                                    <th>Lead User</th>
                                    <th>Est. Hours</th>
                                    <th>Actual Logged</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-4 text-muted">No category items created yet.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($items as $item):
                                        $bClass = 'bg-secondary';
                                        if ($item['category'] === 'project') $bClass = 'bg-primary';
                                        elseif ($item['category'] === 'support') $bClass = 'bg-info text-dark';
                                        elseif ($item['category'] === 'maintenance') $bClass = 'bg-warning text-dark';

                                        $isActive = ($item['is_active'] ?? 1) == 1;
                                    ?>
                                        <tr class="<?= !$isActive ? 'table-light text-muted' : '' ?>">
                                            <td>
                                                <?php if ($isActive): ?>
                                                    <span class="badge bg-success"><i class="fa-solid fa-circle-check me-1"></i> Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary"><i class="fa-solid fa-ban me-1"></i> Disabled</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge <?= $bClass ?>"><?= ucfirst($item['category']) ?></span></td>
                                            <td>
                                                <div class="fw-bold text-dark"><?= htmlspecialchars($item['name']) ?></div>
                                                <?php if (!empty($item['description'])): ?>
                                                    <small class="text-muted d-block"><?= htmlspecialchars($item['description']) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td class="small">
                                                <i class="fa-solid fa-user-tie text-muted me-1"></i> <?= htmlspecialchars($item['lead_user_name']) ?>
                                            </td>
                                            <td>
                                                <?= $item['estimated_hours'] !== null ? number_format($item['estimated_hours'], 2) . ' hrs' : '<span class="text-muted small">N/A</span>' ?>
                                            </td>
                                            <td class="fw-bold text-success">
                                                <?= number_format($item['actual_hours'], 2) ?> hrs
                                            </td>
                                            <td class="text-end">
                                                <form action="index.php?route=time_tracker_items" method="POST" class="d-inline">
                                                    <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                                                    <input type="hidden" name="action" value="toggle_item_status">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                                    <input type="hidden" name="target_status" value="<?= $isActive ? '0' : '1' ?>">
                                                    <button type="submit" class="btn btn-sm <?= $isActive ? 'btn-outline-warning' : 'btn-outline-success' ?> me-1" title="<?= $isActive ? 'Disable Item' : 'Enable Item' ?>">
                                                        <i class="fa-solid <?= $isActive ? 'fa-ban' : 'fa-circle-check' ?>"></i>
                                                    </button>
                                                </form>

                                                <a href="index.php?route=time_tracker_items&edit_item=<?= $item['id'] ?>" class="btn btn-sm btn-outline-primary me-1">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>

                                                <form action="index.php?route=time_tracker_items" method="POST" class="d-inline" onsubmit="return confirm('Deleting this item will also remove all associated time entries! Are you sure?');">
                                                    <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
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
