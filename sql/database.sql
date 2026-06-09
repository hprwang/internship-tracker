-- ============================================
-- Internship Tracking System - Database Schema
-- ============================================

CREATE DATABASE IF NOT EXISTS internship_tracker1
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE internship_tracker1;

-- Users table (admins, students)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin', 'student', 'supervisor') DEFAULT 'student',
  full_name VARCHAR(150) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  is_active TINYINT(1) DEFAULT 1,
  last_login TIMESTAMP NULL,
  INDEX idx_email (email),
  INDEX idx_role (role)
) ENGINE=InnoDB;

-- Companies table
CREATE TABLE IF NOT EXISTS companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  industry VARCHAR(100),
  website VARCHAR(255),
  location VARCHAR(200),
  contact_person VARCHAR(150),
  contact_email VARCHAR(150),
  contact_phone VARCHAR(30),
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_name (name)
) ENGINE=InnoDB;

-- Internships table
CREATE TABLE IF NOT EXISTS internships (
  id INT AUTO_INCREMENT PRIMARY KEY,
  student_id INT NOT NULL,
  company_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  start_date DATE NOT NULL,
  end_date DATE NOT NULL,
  status ENUM('applied','interview','accepted','ongoing','completed','rejected','withdrawn') DEFAULT 'applied',
  stipend DECIMAL(10,2) DEFAULT 0.00,
  work_mode ENUM('remote','onsite','hybrid') DEFAULT 'onsite',
  supervisor_name VARCHAR(150),
  supervisor_email VARCHAR(150),
  offer_letter_path VARCHAR(255),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  INDEX idx_student (student_id),
  INDEX idx_status (status),
  INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB;

-- Progress / Weekly logs
CREATE TABLE IF NOT EXISTS progress_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  internship_id INT NOT NULL,
  week_number INT NOT NULL,
  log_date DATE NOT NULL,
  tasks_completed TEXT,
  skills_learned TEXT,
  challenges TEXT,
  hours_worked DECIMAL(5,2) DEFAULT 0,
  rating TINYINT CHECK (rating BETWEEN 1 AND 5),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (internship_id) REFERENCES internships(id) ON DELETE CASCADE,
  INDEX idx_internship (internship_id)
) ENGINE=InnoDB;

-- Documents table
CREATE TABLE IF NOT EXISTS documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  internship_id INT NOT NULL,
  doc_type ENUM('offer_letter','nda','report','certificate','other') DEFAULT 'other',
  filename VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  file_size INT,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (internship_id) REFERENCES internships(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Activity log for audit trail
CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50),
  entity_id INT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB;


-- Login rate limiting (replaces temp-file approach)
CREATE TABLE IF NOT EXISTS login_rate_limits (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  rate_key      VARCHAR(100) NOT NULL,
  blocked_until INT UNSIGNED NOT NULL DEFAULT 0,
  attempts      TEXT NOT NULL,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE INDEX uq_rate_key (rate_key)
) ENGINE=InnoDB;

-- Password reset tokens (used for "Forgot Password?")
CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  email VARCHAR(150) NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  used_at TIMESTAMP NULL,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_email (email),
  INDEX idx_expires (expires_at),
  INDEX idx_user (user_id)
) ENGINE=InnoDB;

-- ============================================
-- Seed Data
-- ============================================

-- Default admin (password: Admin@1234)
INSERT INTO users (username, email, password_hash, role, full_name) VALUES (
  'admin',
  'admin@interntracker.com',
  '$2y$12$KIXNa3Z1Oq6vAeRzV7uoq.dC5lA8F4sI8.BbISOOVOW.EUGqLxr2m',
  'admin',
  'System Administrator'
)
ON DUPLICATE KEY UPDATE
  email = VALUES(email),
  password_hash = VALUES(password_hash),
  role = VALUES(role),
  full_name = VALUES(full_name);

-- Sample companies
INSERT INTO companies (name, industry, website, location, contact_person, contact_email) VALUES
('TechNova Solutions', 'Information Technology', 'https://technova.io', 'Kathmandu, Nepal', 'Priya Sharma', 'priya@technova.io'),
('FinEdge Corp', 'Finance & Banking', 'https://finedge.com', 'Pokhara, Nepal', 'Rajan Thapa', 'rajan@finedge.com'),
('GreenBuild Inc', 'Civil Engineering', 'https://greenbuild.np', 'Lalitpur, Nepal', 'Anita Gurung', 'anita@greenbuild.np'),
('MediCare Systems', 'Healthcare', 'https://medicare.np', 'Bhaktapur, Nepal', 'Dr. Suman Rai', 'suman@medicare.np');
