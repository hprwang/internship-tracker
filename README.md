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
3. Create a new database called `internship_tracker` (or run the SQL below).
4. Import the schema:
   ```
   sql/database.sql
   ```
   This creates all tables and inserts sample data.

---

### Step 2 — Configure Database

Edit `php/config.php` and update credentials if needed:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'internship_tracker');
define('DB_USER', 'root');   // Your MySQL user
define('DB_PASS', '');       // Your MySQL password
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

**Default Admin Login:**
- Email: `admin@interntracker.com`
- Password: `Admin@1234`

---

## 📁 Project Structure

```
internship-tracker/
├── index.php               ← Login / Register page
├── dashboard.php           ← Main SPA dashboard
├── css/
│   └── style.css           ← All styles (dark theme)
├── js/
│   └── app.js              ← All client-side logic
├── php/
│   ├── config.php          ← DB config, helpers, security
│   ├── auth.php            ← Login/register/logout API
│   └── internships.php     ← Internships CRUD API
├── sql/
│   └── database.sql        ← Full DB schema + seed data
├── java/
│   └── InternshipReportGenerator.java  ← CLI report tool
├── uploads/                ← Document uploads (auto-created)
└── README.md
```

---

## 🔑 Features

### For Students
- ✅ Register & login securely
- ✅ Add/edit/delete internship applications
- ✅ Track status: Applied → Interview → Accepted → Ongoing → Completed
- ✅ Log weekly progress (tasks, skills, challenges, hours, rating)
- ✅ Browse companies

### For Admins
- ✅ See all students' internships
- ✅ Delete any record
- ✅ Full audit log in database

### Security
- ✅ bcrypt password hashing (cost=12)
- ✅ CSRF token protection on all forms
- ✅ Rate limiting on login (5 attempts / 5 min)
- ✅ Session fixation prevention
- ✅ PDO prepared statements (no SQL injection)
- ✅ XSS prevention (htmlspecialchars)
- ✅ HTTP-only session cookies

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
- **Add new statuses**: Update ENUM in `sql/database.sql` + add badge class
- **Add file uploads**: Extend `php/internships.php` using `UPLOAD_DIR` constant

---

## 📄 License

MIT — free to use and modify.
