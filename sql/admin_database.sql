-- ============================================
-- Admin Database - Separate Database for Admin Operations
-- ============================================

CREATE DATABASE IF NOT EXISTS internship_tracker_admin
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE internship_tracker_admin;

-- Admin users table (separate from main users table for security)
CREATE TABLE IF NOT EXISTS admin_users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  full_name VARCHAR(150) NOT NULL,
  company_id INT DEFAULT NULL,
  role ENUM('super_admin', 'admin', 'moderator') DEFAULT 'admin',
  permissions JSON,
  is_active TINYINT(1) DEFAULT 1,
  last_login TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_email (email),
  INDEX idx_role (role),
  INDEX idx_company (company_id)
) ENGINE=InnoDB;

-- Admin sessions for tracking admin logins
CREATE TABLE IF NOT EXISTS admin_sessions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  session_token VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45),
  user_agent VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  is_active TINYINT(1) DEFAULT 1,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
  INDEX idx_admin (admin_id),
  INDEX idx_token (session_token),
  INDEX idx_expires (expires_at)
) ENGINE=InnoDB;

-- Admin activity log for audit trail
CREATE TABLE IF NOT EXISTS admin_activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50),
  entity_id INT,
  details JSON,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
  INDEX idx_admin (admin_id),
  INDEX idx_created (created_at),
  INDEX idx_action (action)
) ENGINE=InnoDB;

-- Admin settings/configuration table
CREATE TABLE IF NOT EXISTS admin_settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT,
  setting_type ENUM('string', 'number', 'boolean', 'json') DEFAULT 'string',
  description VARCHAR(255),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_key (setting_key)
) ENGINE=InnoDB;

-- Admin notifications/messages
CREATE TABLE IF NOT EXISTS admin_notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  admin_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  message TEXT,
  type ENUM('info', 'warning', 'error', 'success') DEFAULT 'info',
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (admin_id) REFERENCES admin_users(id) ON DELETE CASCADE,
  INDEX idx_admin (admin_id),
  INDEX idx_read (is_read)
) ENGINE=InnoDB;

-- ============================================
-- Seed Data
-- ============================================

-- Default admin user (password: Admin@123)
-- Hash generated with password_hash('Admin@123', PASSWORD_BCRYPT, ['cost' => 12])
INSERT INTO admin_users (username, email, password_hash, role, full_name, permissions) VALUES (
  'admin',
  'admin@interntracker.com',
  '$2y$12$OvxoxXkqe0Gkbz2Yid8iNOo5h6.zoyM1sEaXXmGFbGwEAnNSf7lIi',
  'super_admin',
  'System Administrator',
  '{"users": "all", "companies": "all", "internships": "all", "reports": "all", "settings": "all"}'
)
ON DUPLICATE KEY UPDATE
  email = VALUES(email),
  password_hash = VALUES(password_hash),
  role = VALUES(role),
  full_name = VALUES(full_name);

-- Additional admin users (password: Admin@123)
INSERT INTO admin_users (username, email, password_hash, role, full_name, permissions) VALUES (
  'superadmin',
  'superadmin@interntracker.com',
  '$2y$12$OvxoxXkqe0Gkbz2Yid8iNOo5h6.zoyM1sEaXXmGFbGwEAnNSf7lIi',
  'super_admin',
  'Super Admin',
  '{"users": "all", "companies": "all", "internships": "all", "reports": "all", "settings": "all"}'
),
(
  'manager',
  'manager@interntracker.com',
  '$2y$12$OvxoxXkqe0Gkbz2Yid8iNOo5h6.zoyM1sEaXXmGFbGwEAnNSf7lIi',
  'admin',
  'Manager User',
  '{"users": "read", "companies": "all", "internships": "all", "reports": "read", "settings": "none"}'
)
ON DUPLICATE KEY UPDATE
  email = VALUES(email),
  password_hash = VALUES(password_hash),
  role = VALUES(role),
  full_name = VALUES(full_name);

-- Default settings
INSERT INTO admin_settings (setting_key, setting_value, setting_type, description) VALUES
('site_name', 'InternTrack', 'string', 'Site name'),
('site_logo', '', 'string', 'Site logo URL'),
('allow_registration', 'true', 'boolean', 'Allow new user registration'),
('require_email_verification', 'false', 'boolean', 'Require email verification'),
('default_user_role', 'student', 'string', 'Default role for new users'),
('max_login_attempts', '5', 'number', 'Maximum login attempts before lockout'),
('session_timeout', '3600', 'number', 'Session timeout in seconds'),
('pagination_limit', '20', 'number', 'Items per page in pagination')
ON DUPLICATE KEY UPDATE
  setting_value = VALUES(setting_value);