CREATE DATABASE sophen_residence;
USE sophen_residence;

-- Users table (Admin and Security)
CREATE TABLE users (
    user_id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    role_id INT NOT NULL,
    email VARCHAR(100) UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT,
    last_login TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_role (role_id)
);

-- Roles table
CREATE TABLE roles (
    role_id INT PRIMARY KEY AUTO_INCREMENT,
    role_name VARCHAR(50) NOT NULL,
    role_description TEXT,
    is_admin BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Room types
CREATE TABLE room_types (
    room_type_id INT PRIMARY KEY AUTO_INCREMENT,
    type_name VARCHAR(50) NOT NULL,
    description TEXT,
    max_occupancy INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Rooms
CREATE TABLE rooms (
    room_id INT PRIMARY KEY AUTO_INCREMENT,
    room_number VARCHAR(10) UNIQUE NOT NULL,
    room_type_id INT,
    is_occupied BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_type_id) REFERENCES room_types(room_type_id),
    INDEX idx_room_number (room_number)
);

-- Residents
CREATE TABLE residents (
    resident_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    student_number VARCHAR(20) UNIQUE,
    phone VARCHAR(15),
    email VARCHAR(100),
    room_id INT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(room_id),
    INDEX idx_student_number (student_number),
    INDEX idx_room (room_id)
);

-- Visitors
CREATE TABLE visitors (
    visitor_id INT PRIMARY KEY AUTO_INCREMENT,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    id_number VARCHAR(20) UNIQUE NOT NULL,
    phone VARCHAR(15),
    email VARCHAR(100),
    is_blocked BOOLEAN DEFAULT FALSE,
    visit_count INT DEFAULT 0,
    last_visit_date TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_id_number (id_number),
    INDEX idx_email (email)
);

-- Block reasons
CREATE TABLE block_reasons (
    reason_id INT PRIMARY KEY AUTO_INCREMENT,
    reason_code VARCHAR(10) UNIQUE NOT NULL,
    reason_description VARCHAR(200) NOT NULL,
    severity_level INT DEFAULT 1,
    default_block_days INT DEFAULT 7,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Visitor blocks
CREATE TABLE visitor_blocks (
    block_id INT PRIMARY KEY AUTO_INCREMENT,
    visitor_id INT NOT NULL,
    blocked_by_admin_id INT NOT NULL,
    reason_id INT,
    block_start_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    block_period_days INT NOT NULL,
    block_status ENUM('active', 'expired', 'unblocked') DEFAULT 'active',
    unblocked_by_admin_id INT NULL,
    unblock_date TIMESTAMP NULL,
    unblock_reason TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visitor_id) REFERENCES visitors(visitor_id),
    FOREIGN KEY (blocked_by_admin_id) REFERENCES users(user_id),
    FOREIGN KEY (reason_id) REFERENCES block_reasons(reason_id),
    FOREIGN KEY (unblocked_by_admin_id) REFERENCES users(user_id),
    INDEX idx_visitor (visitor_id),
    INDEX idx_status (block_status)
);

-- Visits
CREATE TABLE visits (
    visit_id INT PRIMARY KEY AUTO_INCREMENT,
    visitor_id INT NOT NULL,
    room_id INT,
    host_name VARCHAR(100) NOT NULL,
    purpose ENUM('student', 'maintenance', 'other') NOT NULL,
    expected_checkin TIMESTAMP NULL,
    actual_checkin TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expected_checkout TIMESTAMP NULL,
    actual_checkout TIMESTAMP NULL,
    visit_status ENUM('pending', 'approved', 'declined', 'checked_in', 'checked_out', 'overstayed') DEFAULT 'pending',
    checked_in_by INT,
    checked_out_by INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visitor_id) REFERENCES visitors(visitor_id),
    FOREIGN KEY (room_id) REFERENCES rooms(room_id),
    FOREIGN KEY (checked_in_by) REFERENCES users(user_id),
    FOREIGN KEY (checked_out_by) REFERENCES users(user_id),
    INDEX idx_visitor (visitor_id),
    INDEX idx_status (visit_status),
    INDEX idx_dates (actual_checkin, actual_checkout)
);

-- Security alerts
CREATE TABLE security_alerts (
    alert_id INT PRIMARY KEY AUTO_INCREMENT,
    visit_id INT,
    visitor_id INT,
    room_id INT,
    alert_type ENUM('overstay', 'blocked_visitor', 'emergency', 'suspicious') NOT NULL,
    severity ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    message TEXT NOT NULL,
    alert_status ENUM('new', 'acknowledged', 'resolved', 'dismissed') DEFAULT 'new',
    acknowledged_by INT NULL,
    resolved_by INT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visit_id) REFERENCES visits(visit_id),
    FOREIGN KEY (visitor_id) REFERENCES visitors(visitor_id),
    FOREIGN KEY (room_id) REFERENCES rooms(room_id),
    FOREIGN KEY (acknowledged_by) REFERENCES users(user_id),
    FOREIGN KEY (resolved_by) REFERENCES users(user_id),
    INDEX idx_status (alert_status),
    INDEX idx_type (alert_type),
    INDEX idx_created (created_at)
);

-- System settings
CREATE TABLE system_settings (
    setting_id INT PRIMARY KEY AUTO_INCREMENT,
    default_checkout_time TIME DEFAULT '23:00:00',
    checkout_alert_minutes INT DEFAULT 30,
    max_visit_duration_hours DECIMAL(4,2) DEFAULT 8.00,
    smtp_host VARCHAR(255),
    smtp_username VARCHAR(255),
    smtp_password VARCHAR(255),
    smtp_port INT DEFAULT 587,
    email_from VARCHAR(255),
    email_from_name VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Daily reports
CREATE TABLE daily_reports (
    report_id INT PRIMARY KEY AUTO_INCREMENT,
    report_date DATE NOT NULL,
    generated_by INT NOT NULL,
    shift_type ENUM('day', 'night') DEFAULT 'day',
    total_checkins INT DEFAULT 0,
    total_checkouts INT DEFAULT 0,
    total_incidents INT DEFAULT 0,
    officer_name VARCHAR(100),
    notes TEXT,
    report_status ENUM('draft', 'submitted', 'reviewed') DEFAULT 'draft',
    generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    exported_at TIMESTAMP NULL,
    FOREIGN KEY (generated_by) REFERENCES users(user_id),
    INDEX idx_date (report_date),
    INDEX idx_generated_by (generated_by)
);

-- Activity logs
CREATE TABLE activity_logs (
    log_id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(user_id),
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
);

-- Insert default data
INSERT INTO roles (role_name, role_description, is_admin) VALUES
('Administrator', 'Full system access', TRUE),
('Security', 'Security operations access', FALSE);

INSERT INTO room_types (type_name, description, max_occupancy) VALUES
('Single', 'Single occupancy room', 1),
('Double', 'Double occupancy room', 2);

INSERT INTO system_settings () VALUES ();

INSERT INTO block_reasons (reason_code, reason_description, severity_level, default_block_days) VALUES
('SEC01', 'Security violation', 5, 30),
('DIST01', 'Disturbing other residents', 3, 14),
('TRES01', 'Trespassing', 4, 21),
('OTHER', 'Other reasons', 2, 7);

-- Default admin user (password should be hashed in real implementation)
INSERT INTO users (username, password_hash, first_name, last_name, role_id, email) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System', 'Administrator', 1, 'admin@sophen.com');

-- Report Types table
CREATE TABLE report_types (
    report_type_id INT PRIMARY KEY AUTO_INCREMENT,
    report_name VARCHAR(100) NOT NULL,
    report_description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_report_name (report_name)
);

-- Generated Reports table
CREATE TABLE generated_reports (
    report_id INT PRIMARY KEY AUTO_INCREMENT,
    report_type_id INT NOT NULL,
    generated_by INT NOT NULL,
    generation_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    file_name VARCHAR(255) NOT NULL,
    report_status ENUM('generating', 'generated', 'failed') DEFAULT 'generating',
    file_path VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (report_type_id) REFERENCES report_types(report_type_id),
    FOREIGN KEY (generated_by) REFERENCES users(user_id),
    INDEX idx_type (report_type_id),
    INDEX idx_status (report_status),
    INDEX idx_date (generation_date)
);

-- Insert default report types
INSERT INTO report_types (report_name, report_description) VALUES
('daily_visitor_log', 'Detailed log of all visitor check-ins and check-outs for a specific day or date range'),
('weekly_visitor_report', 'Summary of visitor activity aggregated by day over a weekly period'),
('monthly_visitor_log', 'Monthly statistics including total visits, unique visitors, and average duration'),
('security_incident_report', 'Comprehensive report of all security alerts, incidents, and responses'),
('blocked_visitor_report', 'Report of all blocked visitors, including reasons and block periods'),
('visitor_frequency_report', 'Analysis of visitor patterns, frequency, and duration statistics'),
('resident_report', 'Report of resident information and room assignments'),
('room_occupancy_report', 'Report of room occupancy status and utilization');
