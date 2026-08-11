# 🎓 InternTrack — Internship Tracking System

A full-stack web application to manage, track, and analyze internship applications.

---

## ⚙️ Tech Stack

| Layer      | Technology       |
|------------|------------------|
| Frontend   | HTML5, CSS3, Vanilla JS |
| Backend    | PHP 8.x          |
| Database   | MySQL (SQL)      |
| Utility    | Java (Report Generator) |
| Auth       | bcrypt, CSRF, Session hardening |

---

## 🚀 Quick Setup

### Prerequisites
- **XAMPP / WAMP / LAMP** (PHP 8.0+ & MySQL 5.7+)
- **Java 17+** (for report utility only)

---

### Step 1 — Database Setup

1. Start MySQL via XAMPP/WAMP Control Panel.
2. Open **phpMyAdmin** → `http://localhost/phpmyadmin`
3. Import the unified schema:
   ```
   sql/unified_schema.sql
   ```
   This creates the `internship_tracker1` database, all tables, and starter rows.
4. (Recommended) Load demo data:
   ```bash
   php sql/seed_demo.php
   ```
   The seeder is idempotent — safe to re-run. It adds demo students, a company
   account, internships with weekly progress logs, job postings, applications,
   and notifications, so the dashboards, charts, and calendar look alive.

---

### Step 2 — Configure Database

The app reads its configuration from environment variables, so credentials never
need to be edited into the code or committed to git. If a variable is not set,
a safe local default is used.

Set these in your environment (or web-server vhost) before running:

| Variable           | Default                | Purpose                          |
|--------------------|------------------------|----------------------------------|
| `DB_HOST`          | `localhost`            | MySQL host                       |
| `DB_NAME`          | `internship_tracker1`  | Main database                    |
| `DB_USER`          | `root`                 | MySQL user                       |
| `DB_PASS`          | *(empty)*              | MySQL password                   |
| `SMTP_USERNAME`    | *(empty)*              | SMTP login (e.g. Gmail address)  |
| `SMTP_PASSWORD`    | *(empty)*              | SMTP app password                |
| `SMTP_FROM_EMAIL`  | `no-reply@localhost`   | From address for emails          |
| `APP_DEBUG`        | *(off)*                | Set to `1` to show errors        |

**XAMPP example** — set in the Apache vhost or before `php` runs:

```apache
SetEnv DB_USER "root"
SetEnv DB_PASS ""
SetEnv SMTP_USERNAME "you@gmail.com"
SetEnv SMTP_PASSWORD "your-app-password"
```

**Command line example:**

```bash
# Linux / macOS
export DB_USER=root
export DB_PASS='yourpassword'
php -S localhost:8000

# Windows (PowerShell)
$env:DB_USER='root'
$env:DB_PASS='yourpassword'
php -S localhost:8000
```

---

### Step 3 — Deploy Files

Copy the entire `internship-tracker/` folder into your web server root:

- **XAMPP**: `C:\xampp\htdocs\internship-tracker\`
- **WAMP**: `C:\wamp64\www\internship-tracker\`
- **Linux/Mac LAMP**: `/var/www/html/internship-tracker/`

Create the uploads directory and set write permissions:
```bash
mkdir -p uploads
chmod 755 uploads    # Linux/Mac only
```

---

### Step 4 — Run the App

Open your browser:
```
http://localhost/internship-tracker/
```

**Demo accounts** (see `docs/DEMO.md` for the full table and walkthrough):

| Role    | Email                                 | Password     |
|---------|---------------------------------------|--------------|
| Student | `demo_student1@interntracker.com`     | `Student@123`|
| Company | `demo_company@interntracker.com`      | `Company@123`|
| Admin   | `admin@interntracker.com`             | `Admin@123`  |

---

## 📁 Project Structure

```
internship-tracker/
├── index.php                 ← Landing / login / register (one path, all roles)
├── dashboard.php             ← Student dashboard (analytics + KPIs)
├── browse_internships.php    ← Browse company postings & apply
├── companies.php             ← Browse companies
├── progress.php              ← Weekly progress logging
├── calendar.php              ← Calendar & timeline view
├── profile.php               ← Student profile
├── change_password.php       ← Password change
├── reset_password.php        ← Password reset (token link)
├── css/
│   └── style.css             ← Global dark-theme styles
├── js/
│   ├── app.js                ← Core client logic
│   ├── interactive.js        ← UI interactions
│   ├── analytics.js          ← Chart.js dashboards (student/company/admin)
│   ├── calendar.js           ← Calendar & timeline
│   └── notifications.js      ← Notification bell
├── php/
│   ├── config.php            ← DB, helpers, security (single connection)
│   ├── auth.php              ← Login / register / logout / password API
│   ├── internships.php       ← Internships CRUD API
│   ├── analytics.php         ← Analytics data API
│   ├── notifications.php     ← Notifications API
│   ├── admin.php             ← Admin API
│   ├── admin_dashboard.php, admin_students.php, admin_companies.php,
│   │   admin_internships.php, admin_reports.php, admin_settings.php
│   ├── company_dashboard.php, company_internships.php,
│   │   company_applications.php, company_profile.php
│   ├── partials/
│   │   ├── header.php        ← Shared header + notification helpers
│   │   ├── admin_header.php  ← Admin sidebar
│   │   └── company_header.php← Company sidebar
│   └── api/export_reports.php
├── sql/
│   ├── unified_schema.sql    ← Single unified schema (internship_tracker1)
│   ├── migrate_unify.php     ← Migrates an old multi-DB install
│   └── seed_demo.php         ← Idempotent demo-data seeder
├── tests/
│   ├── notify_test.php, analytics_test.php, analytics_admin_test.php,
│   ├── analytics_company_test.php, calendar_test.php, upload_test.php
├── java/
│   └── InternshipReportGenerator.java  ← CLI report tool
├── uploads/                  ← Document uploads (auto-created)
├── docs/
│   └── DEMO.md               ← Demo guide, accounts & walkthrough
└── README.md
```

---

## 🔑 Features

### For Students
- ✅ Register & login securely
- ✅ Add/edit/delete internship applications
- ✅ Track status: Applied → Interview → Accepted → Ongoing → Completed
- ✅ Log weekly progress (tasks, skills, challenges, hours, rating)
- ✅ Browse companies & job postings and apply
- ✅ Upload documents (offer letter, resume, transcripts)
- ✅ Calendar & timeline view of internship dates
- ✅ Analytics dashboard (applications and progress over time)
- ✅ In-app notification bell

### For Companies
- ✅ Company account & profile management
- ✅ Post internship opportunities (active / closed / pending)
- ✅ Review applications and update their status
- ✅ Dashboard with KPIs and application analytics

### For Admins
- ✅ See all students' internships
- ✅ Manage students, companies, and internship postings
- ✅ Reports & analytics across the platform
- ✅ Settings / system configuration
- ✅ Full audit log in database

### Notifications
- ✅ In-app notifications with unread badge for all roles
- ✅ Optional email delivery when SMTP is configured

### Security
- ✅ bcrypt password hashing (cost=12)
- ✅ CSRF token protection on all forms
- ✅ Rate limiting on login (5 attempts / 5 min)
- ✅ Session fixation prevention
- ✅ PDO prepared statements (no SQL injection)
- ✅ XSS prevention (htmlspecialchars)
- ✅ HTTP-only session cookies
- ✅ DB errors logged, never shown to users

---

## ☕ Java Report Generator

Generate CSV and text reports from the command line:

```bash
cd java/

# Compile
javac InternshipReportGenerator.java

# Run (with MySQL Connector/J on classpath)
java -cp ".;mysql-connector-j-8.x.jar" InternshipReportGenerator ./reports
```

**Download MySQL Connector/J:** https://dev.mysql.com/downloads/connector/j/

Reports generated in `./reports/`:
- `internships_YYYYMMDD.csv` — full application list
- `status_summary_YYYYMMDD.txt` — counts by status
- `student_summary_YYYYMMDD.csv` — per-student stats
- `progress_report_YYYYMMDD.txt` — hours & ratings

---

## 🔧 Customization

- **Change accent color**: Edit `--accent` in `css/style.css`
- **Add new statuses**: Update ENUM in `sql/unified_schema.sql` + add badge class
- **Add file uploads**: Extend `php/internships.php` using `UPLOAD_DIR` constant

---

## 📄 License

MIT — free to use and modify.
