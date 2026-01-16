-- Migration: Add activity_logs table
-- Run this after the main schema setup to add the missing activity_logs table

USE sophen_residence;

-- Activity logs table
CREATE TABLE IF NOT EXISTS activity_logs (
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

-- Insert a sample log entry to test
INSERT INTO activity_logs (user_id, action, details) VALUES (1, 'system_init', 'Activity logs table created');
