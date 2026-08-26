# IT Time Tracker Plugin for Portal Framework (`zzz5`)

An enterprise-grade, Clockify-style time tracking system built specifically as a modular plugin for the **Portal Framework (`zzz5`)**. Tailored for IT support groups, IT operations teams, and project offices to track time spent across **Projects**, **Support Activities**, and **Maintenance Activities**.

---

## 🌟 Key Features

### ⏱️ Time Tracking & Live Task Timer
* **Clockify-Style Logging:** Log hours spent on any task with custom descriptions, ticket reference IDs (e.g. `INC-10243`), billable/overtime switches, and category assignments.
* **Live Task Timer:** Start a live running timer for active tasks.
* **15-Minute Chrome Background Check-ins:** Periodic 15-minute check-in prompts asking users if they are still working or finished.
* **Service Worker & SSO Expiry Resilience (`sw.js`):** Built-in Chrome Service Worker background notification handlers that deliver check-in prompts even if the web tab is closed or the user's SSO session has expired.
* **Duplicate Datetime Validation:** Automatic validation preventing users from logging overlapping or duplicate task entries for the exact same datetime.

### 📁 Categories, Projects & Archiving
* **Category Groups:** Categorize time entries under **Projects**, **Support Activities**, and **Maintenance Activities**.
* **Project Leads & Hour Estimates:** Assign project lead users and define estimated completion hours for projects.
* **Project Archiving:** Archive completed or inactive projects to hide them from task logging dropdowns while preserving historical task records and financial audit metrics.
* **Category Toggles:** Enable or disable specific category types in plugin settings.

### 👥 Team Management & Supervisors
* **IT Teams:** Create support teams (e.g. *Tier 2 Desktop Support*, *Cloud Infrastructure*).
* **Team Supervisors:** Assign dedicated team supervisors and assign members to teams.
* **Team Activity Filtering:** Filter tasks, summaries, and audit logs by team.

### 📊 Supervisor Console & Multi-Tab Auditing
* **User Summary View:** Review logged hours, project time, support time, and maintenance time grouped by team members.
* **Project Progress View:** Visual progress bars displaying actual logged hours against estimated project hours.
* **Category Breakdown View:** Category-level distribution of logged time.
* **Task Audit & Editing:** Full supervisor override capabilities to edit task details, reassign task ownership, adjust hours, or delete entries.

### 💰 Read-Only Finance View & CSV Exports
* **Finance Audit Log:** Dedicated read-only view (`time_tracker_finance`) displaying task allocations, billable/overtime totals, and ticket reference numbers.
* **One-Click CSV Export:** Export filtered time tracking audit reports directly to CSV.

### 📅 User Calendar & Printable Timesheets
* **User Calendar View:** Monthly grid view showcasing daily hours and task entries.
* **Printable Employee Timesheet:** Official weekly/monthly employee timesheet with signature lines for employee and supervisor approval.

### 🗓️ Configurable Financial Year
* **Custom Start Date:** Configure the start of the financial year (e.g. **September 1st**).
* **Jan 1st Presentation Rule:** Financial year presentation year matches the calendar year in which January 1st falls (e.g. Sept 1, 2025 – Aug 31, 2026 is named **FY 2026**).
* **Quick FY Filters:** One-click Financial Year selector in Supervisor and Finance reports.

### ⚙️ Framework Scheduler & Emailed Reports
* **Parallel Task Scheduler API:** Integrates with the `zzz5` core Scheduler API (`init_scheduler`).
* **Automated Weekly Team Activities Report:** Sends HTML email summaries of the previous week's team activities (Monday to Sunday) to configured recipients.

---

## 📂 Directory Structure

```
plugins/time-tracker/
├── plugin.php                   <-- Primary entry point & hook orchestrator
├── README.md                    <-- Full plugin documentation
├── assets/
│   └── sw.js                    <-- Chrome Service Worker for background notifications
├── models/
│   └── time-tracker-models.php  <-- Data models & SQL queries
├── sql/
│   ├── install.sql              <-- Automated DB installation schema
│   └── uninstall.sql            <-- Database cleanup schema
└── views/
    ├── dashboard-view.php       <-- User dashboard, quick logger & live timer
    ├── calendar-view.php        <-- Monthly grid calendar view
    ├── items-view.php           <-- Projects & categories management & archiving
    ├── teams-view.php           <-- Team configuration & team supervisor assignment
    ├── supervisor-view.php      <-- Supervisor management console & task editor
    ├── finance-view.php         <-- Read-only finance audit view & CSV exporter
    ├── timesheet-view.php       <-- Printable employee timesheet & signature approval
    └── settings-view.php        <-- Financial Year, emailed reports & feature settings
```

---

## 🔐 Permissions & RBAC Roles

The plugin registers dynamic permissions and roles automatically upon activation in `PluginDatabase`:

| Permission Name | Description |
| :--- | :--- |
| `time_tracker_user_access` | Access task logging, calendar views, and items list. |
| `time_tracker_supervisor_access` | Access supervisor console, team management, task overrides, and settings. |
| `time_tracker_finance_access` | Access read-only finance view and CSV report exporter. |

### Provisioned Default Roles
* **`time_tracker_user`**: Granted `time_tracker_user_access`.
* **`time_tracker_supervisor`**: Granted `time_tracker_user_access` and `time_tracker_supervisor_access`.
* **`time_tracker_finance`**: Granted `time_tracker_user_access` and `time_tracker_finance_access`.

---

## 🗄️ Database Tables (`plug_time_tracker_*`)

| Table Name | Description |
| :--- | :--- |
| `plug_time_tracker_items` | Category items & projects (estimated hours, lead user, active/archive state). |
| `plug_time_tracker_tasks` | Logged time entries (ticket ref, billable, overtime, status, checkin token). |
| `plug_time_tracker_teams` | IT teams & designated team supervisors. |
| `plug_time_tracker_team_members` | Mapping table linking users to IT teams. |
| `plug_time_tracker_settings` | Plugin configuration (FY start date, email report settings, feature toggles). |

---

## 🛠️ Background Scheduled Tasks

The plugin registers two background tasks via `add_action('init_scheduler', ...)`:

1. **`send_scheduled_reports`** (Interval: 86400s / Daily): Evaluates scheduled email report settings and sends previous week HTML team activity reports to configured recipients.
2. **`check_active_tasks`** (Interval: 900s / 15 mins): Scans for active running task timers and queues 15-minute check-in notifications.

---

## 🚀 Installation & Setup

1. Place the plugin directory inside the portal root at `/plugins/time-tracker/`.
2. Navigate to **Admin -> Modules & Plugins** (`admin-plugins.php`).
3. Click **Check Compatibility** on **IT Time Tracker**.
4. Click **Activate**. The framework will execute `install.sql` and provision dynamic permissions/roles automatically.
