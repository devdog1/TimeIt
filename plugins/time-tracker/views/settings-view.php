<?php
$fyConfig = TimeTrackerModel::getFinancialYearStartConfig();
$currFy = TimeTrackerModel::getCurrentFinancialYear();
$currRange = TimeTrackerModel::getFinancialYearDateRange($currFy);

$emailEnabled = TimeTrackerModel::getSetting('email_reports_enabled', '0');
$emailFreq = TimeTrackerModel::getSetting('email_reports_frequency', 'weekly');
$emailRecipients = TimeTrackerModel::getSetting('email_reports_recipients', '');
$emailType = TimeTrackerModel::getSetting('email_reports_type', 'finance');

$enableTimesheets = TimeTrackerModel::getSetting('enable_timesheets', '1');
$enableBillableOvertime = TimeTrackerModel::getSetting('enable_billable_overtime', '1');

$months = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December'
];

$previewReport = isset($_GET['preview_weekly_report']) && $_GET['preview_weekly_report'] === '1';
?>

<div class="container-fluid py-3">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-sliders text-primary me-2"></i> Time Tracker Settings</h3>
            <p class="text-muted small mb-0">Configure Financial Year start rules, presentation naming, and framework scheduled email reports.</p>
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

    <!-- Preview Example Weekly Team Activities Report Modal/Box -->
    <?php if ($previewReport): ?>
        <div class="card border-primary shadow-sm mb-4">
            <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fa-solid fa-eye me-2"></i> Preview: Example Previous Week's Team Activities Emailed Report</span>
                <a href="index.php?route=time_tracker_settings" class="btn btn-sm btn-light py-0">Close Preview</a>
            </div>
            <div class="card-body bg-light p-4">
                <?= time_tracker_generate_weekly_team_report_html() ?>
            </div>
        </div>
    <?php endif; ?>

    <form action="index.php?route=time_tracker_settings" method="POST">
        <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
        <input type="hidden" name="action" value="save_settings">

        <div class="row g-4">
            <!-- Financial Year Configuration Card -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-primary text-white py-3">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-calendar-check me-2"></i> Financial Year Configuration</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-bold small">Financial Year Start Month & Day</label>
                            <div class="row g-2">
                                <div class="col-md-7">
                                    <select name="fy_start_month" class="form-select">
                                        <?php foreach ($months as $num => $mname): ?>
                                            <option value="<?= $num ?>" <?= $fyConfig['month'] == $num ? 'selected' : '' ?>><?= $mname ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="col-md-5">
                                    <input type="number" min="1" max="31" name="fy_start_day" class="form-control" value="<?= htmlspecialchars($fyConfig['day']) ?>" required>
                                </div>
                            </div>
                            <div class="form-text small mt-2">
                                Example: Setting to <strong>September 1st</strong> causes the Financial Year to run from Sept 1 to Aug 31.
                            </div>
                        </div>

                        <div class="alert alert-light border border-info py-3 mb-0">
                            <h6 class="fw-bold text-info mb-1"><i class="fa-solid fa-circle-info me-1"></i> Financial Year Presentation Rule</h6>
                            <p class="small text-muted mb-2">
                                The presentation name of the Financial Year is defined as the year in which <strong>January 1st</strong> falls.
                            </p>
                            <div class="small fw-bold text-dark">
                                Active Current FY: <span class="badge bg-primary fs-6 ms-1">FY <?= $currFy ?></span>
                                <div class="text-muted fw-normal mt-1">
                                    Date Range: <?= date('M d, Y', strtotime($currRange['start_date'])) ?> &mdash; <?= date('M d, Y', strtotime($currRange['end_date'])) ?>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <h6 class="fw-bold mb-3"><i class="fa-solid fa-toggle-on text-primary me-2"></i> Plugin Feature Modules</h6>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="enable_timesheets" value="1" id="chk_enable_timesheets" <?= $enableTimesheets === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold small" for="chk_enable_timesheets">
                                Enable Printable Employee Timesheets & Approval Signatures View
                            </label>
                        </div>

                        <div class="form-check form-switch mb-2">
                            <input class="form-check-input" type="checkbox" name="enable_billable_overtime" value="1" id="chk_enable_billable_overtime" <?= $enableBillableOvertime === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold small" for="chk_enable_billable_overtime">
                                Enable Billable Hours & Overtime Switches
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Scheduled Email Reports Card -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-header bg-dark text-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0"><i class="fa-solid fa-envelope-open-text me-2"></i> Scheduled Email Reports</h5>
                        <span class="badge bg-secondary">Task Scheduler API</span>
                    </div>
                    <div class="card-body">
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" name="email_reports_enabled" value="1" id="chk_email_enabled" <?= $emailEnabled === '1' ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold small" for="chk_email_enabled">
                                Enable Framework Background Task Scheduled Email Reports
                            </label>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Report Delivery Frequency</label>
                            <select name="email_reports_frequency" class="form-select">
                                <option value="daily" <?= $emailFreq === 'daily' ? 'selected' : '' ?>>Daily Summary</option>
                                <option value="weekly" <?= $emailFreq === 'weekly' ? 'selected' : '' ?>>Weekly Summary (Previous Week's Team Activities)</option>
                                <option value="monthly" <?= $emailFreq === 'monthly' ? 'selected' : '' ?>>Monthly Summary (1st of Month)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Report Type</label>
                            <select name="email_reports_type" class="form-select">
                                <option value="weekly_team" <?= $emailType === 'weekly_team' ? 'selected' : '' ?>>Previous Week's Team Activities Report</option>
                                <option value="finance" <?= $emailType === 'finance' ? 'selected' : '' ?>>Finance Summary (Category & Project Breakdown)</option>
                                <option value="supervisor" <?= $emailType === 'supervisor' ? 'selected' : '' ?>>Supervisor Audit (Detailed Task & Team Logs)</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-bold small">Recipient Email Addresses</label>
                            <input type="text" name="email_reports_recipients" class="form-control" placeholder="finance@company.com, manager@company.com" value="<?= htmlspecialchars($emailRecipients) ?>">
                            <div class="form-text small">Separate multiple email addresses with commas.</div>
                        </div>

                        <div class="d-flex gap-2 mt-4 pt-2 border-top">
                            <a href="index.php?route=time_tracker_settings&preview_weekly_report=1" class="btn btn-outline-primary btn-sm flex-grow-1">
                                <i class="fa-solid fa-eye me-1"></i> Preview Weekly Report HTML
                            </a>
                            <button type="submit" form="trigger_test_form" class="btn btn-outline-dark btn-sm flex-grow-1">
                                <i class="fa-solid fa-paper-plane me-1"></i> Trigger Task Runner Now
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 text-end">
                <button type="submit" class="btn btn-primary px-4 btn-lg">
                    <i class="fa-solid fa-floppy-disk me-1"></i> Save All Settings
                </button>
            </div>
        </div>
    </form>

    <form id="trigger_test_form" action="index.php?route=time_tracker_settings" method="POST" class="d-none">
        <?php if (function_exists('csrf_field')) { echo csrf_field(); } ?>
        <input type="hidden" name="action" value="trigger_test_email">
    </form>
</div>
