-- Company database
CREATE DATABASE IF NOT EXISTS internship_tracker_company;
USE internship_tracker_company;

-- Admin users table for companies
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
);

-- Companies table
CREATE TABLE IF NOT EXISTS companies (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL,
  industry VARCHAR(100),
  description TEXT,
  location VARCHAR(150),
  website VARCHAR(255),
  email VARCHAR(150),
  phone VARCHAR(50),
  logo_url VARCHAR(255),
  status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_name (name),
  INDEX idx_status (status)
);

-- Internships table (company internships)
CREATE TABLE IF NOT EXISTS internships (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  description TEXT,
  requirements TEXT,
  location VARCHAR(150),
  duration VARCHAR(100),
  stipend DECIMAL(10,2),
  status ENUM('active', 'closed', 'pending') DEFAULT 'active',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_company (company_id),
  INDEX idx_status (status)
);

-- Applications table
CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  internship_id INT NOT NULL,
  student_id INT DEFAULT NULL,
  student_name VARCHAR(150),
  student_email VARCHAR(150),
  student_phone VARCHAR(50),
  student_resume TEXT,
  cover_letter TEXT,
  status ENUM('pending', 'accepted', 'rejected', 'under_review') DEFAULT 'pending',
  notes TEXT,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_internship (internship_id),
  INDEX idx_student (student_id),
  INDEX idx_status (status)
);