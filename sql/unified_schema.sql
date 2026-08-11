-- ============================================
-- InternTrack - Unified Database Schema
-- Target for BOTH fresh installs and the result
-- of sql/migrate_unify.php on an existing install.
--
-- One database: internship_tracker1
-- Roles: student | company | admin
-- ============================================

CREATE DATABASE IF NOT EXISTS internship_tracker1
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE internship_tracker1;

-- Users: one table for all people (students, companies, admins)
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(150) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('student','company','admin') DEFAULT 'student',
  full_name VARCHAR(150) NOT NULL,
  company_id INT DEFAULT NULL,
  is_active TINYINT(1) DEFAULT 1,
  last_login TIMESTAMP NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE SET NULL,
  INDEX idx_email (email),
  INDEX idx_role (role),
  INDEX idx_company (company_id)
) ENGINE=InnoDB;

-- Companies: canonical list (student companies + company accounts)
CREATE TABLE IF NOT EXISTS companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(200) NOT NULL,
  industry VARCHAR(100),
  description TEXT,
  website VARCHAR(255),
  location VARCHAR(200),
  contact_person VARCHAR(150),
  contact_email VARCHAR(150),
  contact_phone VARCHAR(30),
  email VARCHAR(150),
  phone VARCHAR(50),
  logo_url VARCHAR(255),
  status VARCHAR(20) DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_company_name (name),
  INDEX idx_name (name),
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- Internships: student-tracked internship records
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
  resume_path VARCHAR(255),
  cover_letter_path VARCHAR(255),
  transcripts_path VARCHAR(255),
  notes TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE RESTRICT,
  INDEX idx_student (student_id),
  INDEX idx_status (status),
  INDEX idx_dates (start_date, end_date)
) ENGINE=InnoDB;

-- Company internships: job postings published by company accounts
CREATE TABLE IF NOT EXISTS company_internships (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  requirements TEXT,
  location VARCHAR(150),
  duration VARCHAR(100),
  stipend DECIMAL(10,2) DEFAULT 0.00,
  status ENUM('active','closed','pending') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (company_id) REFERENCES companies(id) ON DELETE CASCADE,
  INDEX idx_company (company_id),
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- Applications: students applying to company internship postings
CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_internship_id INT NOT NULL,
  student_id INT DEFAULT NULL,
  cover_letter TEXT,
  resume TEXT,
  status ENUM('pending','under_review','accepted','rejected') DEFAULT 'pending',
  notes TEXT,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (company_internship_id) REFERENCES company_internships(id) ON DELETE CASCADE,
  FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_internship (company_internship_id),
  INDEX idx_student (student_id),
  INDEX idx_status (status)
) ENGINE=InnoDB;

-- Progress / weekly logs
CREATE TABLE IF NOT EXISTS progress_logs (
  id INT AUTO_INCREMENT PRIMARY KEY,
  internship_id INT NOT NULL,
  week_number INT NOT NULL,
  log_date DATE NOT NULL,
  tasks_completed TEXT,
  skills_learned TEXT,
  challenges TEXT,
  hours_worked DECIMAL(5,2) DEFAULT 0,
  rating TINYINT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (internship_id) REFERENCES internships(id) ON DELETE CASCADE,
  INDEX idx_internship (internship_id)
) ENGINE=InnoDB;

-- Documents attached to internships
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

-- Login rate limiting
CREATE TABLE IF NOT EXISTS login_rate_limits (
  id            INT AUTO_INCREMENT PRIMARY KEY,
  rate_key      VARCHAR(100) NOT NULL,
  blocked_until INT UNSIGNED NOT NULL DEFAULT 0,
  attempts      TEXT NOT NULL,
  updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE INDEX uq_rate_key (rate_key)
) ENGINE=InnoDB;

-- System settings
CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(100) NOT NULL UNIQUE,
  value_text TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_key (key_name)
) ENGINE=InnoDB;

-- Password reset tokens
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

-- In-app notifications (+ email demo fallback)
CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  message TEXT,
  type ENUM('info','warning','error','success') DEFAULT 'info',
  channel ENUM('in_app','email','both') DEFAULT 'in_app',
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  INDEX idx_user (user_id),
  INDEX idx_read (is_read)
) ENGINE=InnoDB;

-- ============================================
-- Seed data (fresh installs only)
-- ============================================

-- Default admin (password: Admin@123)
INSERT INTO users (username, email, password_hash, role, full_name) VALUES (
  'admin',
  'admin@interntracker.com',
  '$2y$12$UvFRYQ5cu0Xm3q2NKnwwP.8XNMX0vydmQqTHtn22kU8YopsxsqW5W',
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
('MediCare Systems', 'Healthcare', 'https://medicare.np', 'Bhaktapur, Nepal', 'Dr. Suman Rai', 'suman@medicare.np')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Sample students (password: Student@123)
INSERT INTO users (username, email, password_hash, role, full_name) VALUES
('student001', 'student001@interntracker.com', '$2y$12$UVu733aOXVWw2DhPb0S/leDE2gsmMETyIVEuQ7hSruvsTf3h9NcfC', 'student', 'Ram Sharma'),
('student002', 'student002@interntracker.com', '$2y$12$UVu733aOXVWw2DhPb0S/leDE2gsmMETyIVEuQ7hSruvsTf3h9NcfC', 'student', 'Sita Devi'),
('student003', 'student003@interntracker.com', '$2y$12$UVu733aOXVWw2DhPb0S/leDE2gsmMETyIVEuQ7hSruvsTf3h9NcfC', 'student', 'Hari Khatri')
ON DUPLICATE KEY UPDATE
  email = VALUES(email),
  password_hash = VALUES(password_hash),
  role = VALUES(role),
  full_name = VALUES(full_name);
