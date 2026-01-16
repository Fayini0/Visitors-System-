-- Migration to add password reset fields to users table
-- Run this SQL in your database to add forgot password functionality

ALTER TABLE users
ADD COLUMN reset_token VARCHAR(255) NULL,
ADD COLUMN reset_expires TIMESTAMP NULL;

-- Add index for faster token lookups
CREATE INDEX idx_reset_token ON users(reset_token);
CREATE INDEX idx_reset_expires ON users(reset_expires);
