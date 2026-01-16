<?php
/**
 * Database Initialization Script for Sophen Residence System
 * Run this script once to set up the database structure and sample data
 * 
 * Usage: php database/init.php
 * Or access via browser: http://localhost/sophen-residence-system/database/init.php
 */

// Include database configuration
require_once '../config/database.php';

// Database connection settings
$host = "localhost";
$username = "root";  // Change as needed
$password = "";      // Change as needed
$database = "sophen_residence";

try {
    // Connect to MySQL server (without selecting database first)
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "<h1>Sophen Residence System - Database Setup</h1>";
    echo "<div style='font-family: Arial, sans-serif; max-width: 800px; margin: 0 auto; padding: 20px;'>";
    
    // Create database if it doesn't exist
    echo "<h2>Step 1: Creating Database</h2>";
    $pdo->exec("CREATE DATABASE IF NOT EXISTS $database CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "<p style='color: green;'>✓ Database '$database' created successfully</p>";
    
    // Select the database
    $pdo->exec("USE $database");
    
    // Disable foreign key checks for initial setup to handle self-references and order
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Drop existing tables if they exist (for clean installation)
    echo "<h2>Step 2: Preparing Tables</h2>";
    $dropTables = [
        'activity_logs', 'daily_reports', 'security_alerts', 'visitor_blocks', 'visits', 'block_reasons',
        'residents', 'visitors', 'rooms', 'room_types', 'users', 'roles',
        'system_settings'
    ];
    
    foreach ($dropTables as $table) {
        $pdo->exec("DROP TABLE IF EXISTS $table");
        echo "<p>• Dropped table '$table' if existed</p>";
    }
    
    // Create tables
    echo "<h2>Step 3: Creating Tables</h2>";
    
    // Roles table
    $pdo->exec("
        CREATE TABLE roles (
            role_id INT PRIMARY KEY AUTO_INCREMENT,
            role_name VARCHAR(50) NOT NULL,
            role_description TEXT,
            is_admin BOOLEAN DEFAULT FALSE,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<p style='color: green;'>✓ Created 'roles' table</p>";
    
    // Users table (without self-referencing FK initially)
    $pdo->exec("
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
            created_by INT NULL,
            last_login TIMESTAMP NULL,
            FOREIGN KEY (role_id) REFERENCES roles(role_id),
            INDEX idx_username (username),
            INDEX idx_role (role_id)
        )
    ");
    echo "<p style='color: green;'>✓ Created 'users' table</p>";
    
    // Room types table
    $pdo->exec("
        CREATE TABLE room_types (
            room_type_id INT PRIMARY KEY AUTO_INCREMENT,
            type_name VARCHAR(50) NOT NULL,
            description TEXT,
            max_occupancy INT DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<p style='color: green;'>✓ Created 'room_types' table</p>";
    
    // Rooms table
    $pdo->exec("
        CREATE TABLE rooms (
            room_id INT PRIMARY KEY AUTO_INCREMENT,
            room_number VARCHAR(10) UNIQUE NOT NULL,
            room_type_id INT,
            is_occupied BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (room_type_id) REFERENCES room_types(room_type_id),
            INDEX idx_room_number (room_number)
        )
    ");
    echo "<p style='color: green;'>✓ Created 'rooms' table</p>";
    
    // Residents table
    $pdo->exec("
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
        )
    ");
    echo "<p style='color: green;'>✓ Created 'residents' table</p>";
    
    // Visitors table
    $pdo->exec("
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
            INDEX idx_email (email),
            INDEX idx_name (first_name, last_name)
        )
    ");
    echo "<p style='color: green;'>✓ Created 'visitors' table</p>";
    
    // Block reasons table
    $pdo->exec("
        CREATE TABLE block_reasons (
            reason_id INT PRIMARY KEY AUTO_INCREMENT,
            reason_code VARCHAR(10) UNIQUE NOT NULL,
            reason_description VARCHAR(200) NOT NULL,
            severity_level INT DEFAULT 1,
            default_block_days INT DEFAULT 7,
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
    echo "<p style='color: green;'>✓ Created 'block_reasons' table</p>";
    
    // Visitor blocks table
    $pdo->exec("
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
        )
    ");
    echo "<p style='color: green;'>✓ Created 'visitor_blocks' table</p>";
    
    // Visits table
    $pdo->exec("
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
            visit_status ENUM('pending', 'approved', 'checked_in', 'checked_out', 'overstayed', 'cancelled') DEFAULT 'pending',
            checked_in_by INT,
            checked_out_by INT,
            verification_code VARCHAR(10) NULL,
            verification_sent BOOLEAN DEFAULT FALSE,
            host_verified BOOLEAN DEFAULT FALSE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (visitor_id) REFERENCES visitors(visitor_id),
            FOREIGN KEY (room_id) REFERENCES rooms(room_id),
            FOREIGN KEY (checked_in_by) REFERENCES users(user_id),
            FOREIGN KEY (checked_out_by) REFERENCES users(user_id),
            INDEX idx_visitor (visitor_id),
            INDEX idx_status (visit_status),
            INDEX idx_dates (actual_checkin, actual_checkout)
        )
    ");
    echo "<p style='color: green;'>✓ Created 'visits' table</p>";
    
    // Security alerts table
    $pdo->exec("
        CREATE TABLE security_alerts (
            alert_id INT PRIMARY KEY AUTO_INCREMENT,
            visit_id INT,
            visitor_id INT,
            room_id INT,
            alert_type ENUM('overstay', 'blocked_visitor', 'emergency', 'suspicious', 'checkout_reminder') NOT NULL,
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
        )
    ");
    echo "<p style='color: green;'>✓ Created 'security_alerts' table</p>";
    
    // System settings table
    $pdo->exec("
        CREATE TABLE system_settings (
            setting_id INT PRIMARY KEY AUTO_INCREMENT,
            default_checkout_time TIME DEFAULT '23:00:00',
            checkout_alert_minutes INT DEFAULT 30,
            max_visit_duration_hours DECIMAL(4,2) DEFAULT 8.00,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
    echo "<p style='color: green;'>✓ Created 'system_settings' table</p>";
    
    // Daily reports table
    $pdo->exec("
        CREATE TABLE daily_reports (
            report_id INT PRIMARY KEY AUTO_INCREMENT,
            report_date DATE NOT NULL,
            generated_by INT NOT NULL,
            shift_type ENUM('day', 'night', 'full') DEFAULT 'day',
            total_checkins INT DEFAULT 0,
            total_checkouts INT DEFAULT 0,
            total_incidents INT DEFAULT 0,
            officer_name VARCHAR(100),
            shift_notes TEXT,
            incidents_summary TEXT,
            equipment_status TEXT,
            handover_notes TEXT,
            report_status ENUM('draft', 'submitted', 'reviewed') DEFAULT 'draft',
            generated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            exported_at TIMESTAMP NULL,
            FOREIGN KEY (generated_by) REFERENCES users(user_id),
            INDEX idx_date (report_date),
            INDEX idx_generated_by (generated_by)
        )
    ");
    echo "<p style='color: green;'>✓ Created 'daily_reports' table</p>";
    
    // Activity logs table
    $pdo->exec("
        CREATE TABLE activity_logs (
            log_id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(user_id),
            INDEX idx_user (user_id),
            INDEX idx_action (action),
            INDEX idx_created (created_at)
        )
    ");
    echo "<p style='color: green;'>✓ Created 'activity_logs' table</p>";
    
    // Insert default data
    echo "<h2>Step 4: Inserting Default Data</h2>";
    
    // Insert roles
    $pdo->exec("
        INSERT INTO roles (role_name, role_description, is_admin) VALUES
        ('Administrator', 'Full system access and management', TRUE),
        ('Security', 'Security operations and visitor management', FALSE)
    ");
    echo "<p style='color: green;'>✓ Inserted default roles</p>";
    
    // Insert room types
    $pdo->exec("
        INSERT INTO room_types (type_name, description, max_occupancy) VALUES
        ('Single', 'Single occupancy room', 1),
        ('Double', 'Double occupancy room', 2),
        ('Suite', 'Suite with multiple rooms', 4)
    ");
    echo "<p style='color: green;'>✓ Inserted room types</p>";
    
    // Insert system settings
    $pdo->exec("
        INSERT INTO system_settings (default_checkout_time, checkout_alert_minutes, max_visit_duration_hours) VALUES
        ('23:00:00', 30, 8.00)
    ");
    echo "<p style='color: green;'>✓ Inserted system settings</p>";
    
    // Insert block reasons
    $pdo->exec("
        INSERT INTO block_reasons (reason_code, reason_description, severity_level, default_block_days) VALUES
        ('SEC01', 'Security violation - unauthorized access', 5, 30),
        ('DIST01', 'Disturbing other residents', 3, 14),
        ('TRES01', 'Trespassing in restricted areas', 4, 21),
        ('VIOL01', 'Violence or threatening behavior', 5, 60),
        ('DRUG01', 'Drug-related violations', 5, 90),
        ('THEFT01', 'Theft or suspicious behavior', 4, 30),
        ('OTHER', 'Other violations', 2, 7)
    ");
    echo "<p style='color: green;'>✓ Inserted block reasons</p>";
    
    // Insert default admin user (password: admin123)
    $adminPasswordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO users (username, password_hash, first_name, last_name, role_id, email) VALUES
        ('admin', '$adminPasswordHash', 'System', 'Administrator', 1, 'admin@sophen.com')
    ");
    echo "<p style='color: green;'>✓ Created default admin user (username: admin, password: admin123)</p>";
    
    // Insert default security user (password: security123)
    $securityPasswordHash = password_hash('security123', PASSWORD_DEFAULT);
    $pdo->exec("
        INSERT INTO users (username, password_hash, first_name, last_name, role_id, email, created_by) VALUES
        ('security', '$securityPasswordHash', 'Security', 'Officer', 2, 'security@sophen.com', 1)
    ");
    echo "<p style='color: green;'>✓ Created default security user (username: security, password: security123)</p>";
    
    // Add self-referencing FK to users table after data insertion
    $pdo->exec("
        ALTER TABLE users 
        ADD CONSTRAINT fk_users_created_by 
        FOREIGN KEY (created_by) REFERENCES users(user_id)
    ");
    echo "<p style='color: green;'>✓ Added self-referencing foreign key to users table</p>";
    
    // Insert sample rooms
    $roomsData = [
        ['A101', 1], ['A102', 1], ['A103', 2], ['A104', 2], ['A105', 1],
        ['B201', 1], ['B202', 2], ['B203', 1], ['B204', 2], ['B205', 3],
        ['B302', 1], ['C301', 1], ['C302', 2], ['C303', 1], ['C304', 1]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO rooms (room_number, room_type_id) VALUES (?, ?)");
    foreach ($roomsData as $room) {
        $stmt->execute($room);
    }
    echo "<p style='color: green;'>✓ Inserted " . count($roomsData) . " sample rooms</p>";
    
    // Insert sample residents
    $residentsData = [
        ['John', 'Smith', 'STU001', '+27 123 456 789', 'john.smith@student.ac.za', 1],
        ['Sarah', 'Johnson', 'STU002', '+27 987 654 321', 'sarah.johnson@student.ac.za', 3],
        ['Michael', 'Brown', 'STU003', '+27 555 123 456', 'mike.brown@student.ac.za', 6],
        ['Emma', 'Davis', 'STU004', '+27 444 789 123', 'emma.davis@student.ac.za', 8],
        ['David', 'Wilson', 'STU005', '+27 333 456 789', 'david.wilson@student.ac.za', 10],
        ['Mike', 'Johnson', 'STU006', '+27 222 111 333', 'mike.johnson@student.ac.za', 11]
    ];
    
    $stmt = $pdo->prepare("INSERT INTO residents (first_name, last_name, student_number, phone, email, room_id) VALUES (?, ?, ?, ?, ?, ?)");
    foreach ($residentsData as $resident) {
        $stmt->execute($resident);
    }
    echo "<p style='color: green;'>✓ Inserted " . count($residentsData) . " sample residents</p>";
    
    // Insert sample visitors
    $visitorsData = [
        ['Alice', 'Johnson', '9001015009088', '+27 111 222 333', 'alice.johnson@email.com', 3, '2024-12-15 14:30:00'],
        ['Bob', 'Williams', '202212345', '+27 222 333 444', 'bob.williams@email.com', 1, '2024-12-10 16:45:00'],
        ['Carol', 'Martinez', '8905123456789', '+27 333 444 555', 'carol.martinez@email.com', 5, '2024-12-18 10:20:00']
    ];
    
    $stmt = $pdo->prepare("INSERT INTO visitors (first_name, last_name, id_number, phone, email, visit_count, last_visit_date) VALUES (?, ?, ?, ?, ?, ?, ?)");
    foreach ($visitorsData as $visitor) {
        $stmt->execute($visitor);
    }
    echo "<p style='color: green;'>✓ Inserted " . count($visitorsData) . " sample visitors</p>";
    
    // Create useful views
    echo "<h2>Step 5: Creating Views</h2>";
    
    // Drop existing views if they exist
    $dropViews = ['visitor_summary', 'active_visits'];
    foreach ($dropViews as $view) {
        $pdo->exec("DROP VIEW IF EXISTS $view");
        echo "<p>• Dropped view '$view' if existed</p>";
    }
    
    // Visitor summary view
    $pdo->exec("
        CREATE VIEW visitor_summary AS
        SELECT 
            v.visitor_id,
            CONCAT(v.first_name, ' ', v.last_name) as full_name,
            v.id_number,
            v.phone,
            v.email,
            v.is_blocked,
            v.visit_count,
            v.last_visit_date,
            CASE 
                WHEN vb.block_id IS NOT NULL AND vb.block_status = 'active' THEN TRUE 
                ELSE FALSE 
            END as currently_blocked
        FROM visitors v
        LEFT JOIN visitor_blocks vb ON v.visitor_id = vb.visitor_id AND vb.block_status = 'active'
    ");
    echo "<p style='color: green;'>✓ Created visitor_summary view</p>";
    
    // Active visits view
    $pdo->exec("
        CREATE VIEW active_visits AS
        SELECT 
            vi.visit_id,
            vi.visitor_id,
            CONCAT(v.first_name, ' ', v.last_name) as visitor_name,
            v.id_number,
            r.room_number,
            vi.host_name,
            vi.purpose,
            vi.actual_checkin,
            vi.expected_checkout,
            vi.visit_status,
            TIMESTAMPDIFF(HOUR, vi.actual_checkin, NOW()) as hours_spent
        FROM visits vi
        JOIN visitors v ON vi.visitor_id = v.visitor_id
        LEFT JOIN rooms r ON vi.room_id = r.room_id
        WHERE vi.visit_status IN ('approved', 'checked_in')
        ORDER BY vi.actual_checkin DESC
    ");
    echo "<p style='color: green;'>✓ Created active_visits view</p>";
    
    // Create stored procedures
    echo "<h2>Step 6: Creating Stored Procedures</h2>";
    
    // Drop existing procedures if they exist
    $dropProcedures = ['CreateVisit', 'CheckoutVisitor'];
    foreach ($dropProcedures as $proc) {
        $pdo->exec("DROP PROCEDURE IF EXISTS $proc");
        echo "<p>• Dropped procedure '$proc' if existed</p>";
    }
    
    $pdo->exec("
        CREATE PROCEDURE CreateVisit(
            IN p_visitor_id INT,
            IN p_room_number VARCHAR(10),
            IN p_host_name VARCHAR(100),
            IN p_purpose ENUM('student', 'maintenance', 'other')
        )
        BEGIN
            DECLARE v_room_id INT;
            DECLARE v_checkout_time TIMESTAMP;
            
            SELECT room_id INTO v_room_id FROM rooms WHERE room_number = p_room_number;
            
            SELECT IF(TIME(NOW()) <= s.default_checkout_time,
                       TIMESTAMP(CURDATE(), s.default_checkout_time),
                       TIMESTAMP(DATE_ADD(CURDATE(), INTERVAL 1 DAY), s.default_checkout_time))
            INTO v_checkout_time FROM system_settings s LIMIT 1;
            
            INSERT INTO visits (visitor_id, room_id, host_name, purpose, expected_checkout, visit_status)
            VALUES (p_visitor_id, v_room_id, p_host_name, p_purpose, v_checkout_time, 'pending');
            
            SELECT LAST_INSERT_ID() as visit_id;
        END
    ");
    echo "<p style='color: green;'>✓ Created CreateVisit stored procedure</p>";
    
    $pdo->exec("
        CREATE PROCEDURE CheckoutVisitor(
            IN p_visitor_id INT,
            IN p_checked_out_by INT
        )
        BEGIN
            UPDATE visits 
            SET actual_checkout = NOW(), 
                visit_status = 'checked_out',
                checked_out_by = p_checked_out_by
            WHERE visitor_id = p_visitor_id 
            AND visit_status IN ('checked_in', 'approved')
            ORDER BY actual_checkin DESC 
            LIMIT 1;
            
            SELECT ROW_COUNT() as affected_rows;
        END
    ");
    echo "<p style='color: green;'>✓ Created CheckoutVisitor stored procedure</p>";
    
    // Re-enable foreign key checks
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "<h2>✅ Database Setup Complete!</h2>";
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h3>Default Login Credentials:</h3>";
    echo "<p><strong>Administrator:</strong><br>";
    echo "Username: <code>admin</code><br>";
    echo "Password: <code>admin123</code></p>";
    echo "<p><strong>Security:</strong><br>";
    echo "Username: <code>security</code><br>";
    echo "Password: <code>security123</code></p>";
    echo "<p><strong>⚠️ Important:</strong> Change these default passwords immediately after first login!</p>";
    echo "</div>";
    
    echo "<h3>What's Next?</h3>";
    echo "<ul>";
    echo "<li>✅ Database structure created with all tables, views, and procedures</li>";
    echo "<li>✅ Sample data inserted (rooms, residents, visitors)</li>";
    echo "<li>✅ Default admin and security users created</li>";
    echo "<li>🔄 You can now test the visitor flow and admin interfaces</li>";
    echo "<li>🔧 Customize system settings through the admin panel</li>";
    echo "<li>📊 Start using the system with real data</li>";
    echo "</ul>";
    
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; border-radius: 5px; margin: 20px 0;'>";
    echo "<h4>🔧 Configuration Notes:</h4>";
    echo "<ul>";
    echo "<li>Default checkout time: 23:00 (11:00 PM)</li>";
    echo "<li>Checkout reminder: 30 minutes before</li>";
    echo "<li>Maximum visit duration: 8 hours</li>";
    echo "<li>15 sample rooms created (A101-C304)</li>";
    echo "<li>6 sample residents added</li>";
    echo "<li>3 sample visitors for testing</li>";
    echo "</ul>";
    echo "</div>";
    
    echo "<h3>🧪 Test the System:</h3>";
    echo "<p>You can now:</p>";
    echo "<ul>";
    echo "<li><a href='../index.php'>Visit the landing page</a></li>";
    echo "<li><a href='../visitor/step1.php'>Test the visitor flow</a></li>";
    echo "<li><a href='../admin/login.php'>Access admin panel</a></li>";
    echo "<li><a href='../security/login.php'>Access security dashboard</a></li>";
    echo "</ul>";
    
    echo "</div>";
    
} catch (PDOException $e) {
    echo "<div style='color: red; background: #f8d7da; border: 1px solid #f5c6cb; padding: 15px; border-radius: 5px; margin: 20px; font-family: Arial, sans-serif;'>";
    echo "<h2>❌ Database Setup Failed</h2>";
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
    echo "<h3>Common Solutions:</h3>";
    echo "<ul>";
    echo "<li>Make sure MySQL server is running</li>";
    echo "<li>Check database connection settings in this file</li>";
    echo "<li>Ensure the database user has CREATE and INSERT privileges</li>";
    echo "<li>Verify MySQL credentials are correct</li>";
    echo "</ul>";
    echo "</div>";
}
?>