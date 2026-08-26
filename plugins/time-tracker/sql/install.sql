CREATE TABLE IF NOT EXISTS plug_time_tracker_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    category ENUM('project', 'support', 'maintenance') NOT NULL DEFAULT 'project',
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plug_time_tracker_tasks (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plug_time_tracker_teams (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    supervisor_user_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plug_time_tracker_team_members (
    team_id INT NOT NULL,
    user_id INT NOT NULL,
    PRIMARY KEY (team_id, user_id),
    KEY idx_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS plug_time_tracker_settings (
    setting_key VARCHAR(64) PRIMARY KEY,
    setting_value TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
