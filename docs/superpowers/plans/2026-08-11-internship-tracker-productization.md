# InternTrack Productization Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Productize InternTrack into one coherent, polished, demo-ready internship-tracking website — single database, new analytics/calendar/notification features, repaired and hardened flows, unified UI.

**Architecture:** Consolidate three MySQL databases into one (`internship_tracker1`) via a one-time migration script; refactor auth to a single role-based login; add a `notify()` helper backed by a `notifications` table (PHPMailer already present); add Chart.js-driven analytics, a calendar/timeline page, shared layout partials, and a hardened/consistent UI. Existing procedural-PHP + `config.php` helper pattern is preserved.

**Tech Stack:** PHP 8, MySQL, vanilla JS, Chart.js 4 (CDN), PHPMailer 6 (composer), CSS3 (dark theme).

## Global Constraints

- **Single database:** All code must use only `Database::getConnection()` against `internship_tracker1`. No references to `ADMIN_DB_NAME`, `COMPANY_DB_NAME`, `getAdminConnection()`, `getCompanyConnection()` may remain (grep must return zero hits outside `sql/` docs).
- **Roles:** `users.role` = `student | company | admin` only. Every role-gated page uses one of `requireAuth()` / `requireAdmin()` / `requireCompanyAuth()`.
- **Auth redirects:** student → `dashboard.php`, company → `php/company_dashboard.php`, admin → `php/admin_dashboard.php`.
- **Output escaping:** every dynamic value in HTML output passes through `e()`.
- **CSRF:** every POST form submits a `csrf_token` field validated by `verifyCSRF()`.
- **No dead files:** `php/tempCodeRunnerFile.php`, `reset_pw.php`, `student-dashboard.html` are deleted.
- **Error handling:** DB errors are `error_log`ged; users never see raw PDO messages. New features wrapped in try/catch.
- **Demo-safe emails:** SMTP only attempted when `SMTP_USERNAME` is non-empty; failures degrade to in-app notification.
- **PHP version:** 8.0+; use `?:`/`??`, typed signatures already in use.

---

## Phase 0 — Foundation: unified database

### Task 0.1: Write the unified schema and migration script

**Files:**
- Create: `sql/unified_schema.sql`
- Create: `sql/migrate_unify.php`
- Test: `sql/migrate_unify.php` (run via CLI)

**Interfaces:**
- Consumes: existing live DBs `internship_tracker1`, `internship_tracker_admin`, `internship_tracker_company`.
- Produces: unified tables in `internship_tracker1`: `users` (role incl. `company`), `companies` (superset columns), `internships`, **`company_internships`**, **`applications`**, `progress_logs`, `documents`, `activity_log`, `settings`, `password_resets`, `login_rate_limits`, **`notifications`**.
- Later tasks read: `users.company_id`, `company_internships`, `applications`, `notifications`.

- [ ] **Step 1: Write `sql/unified_schema.sql`**

Full DDL for `internship_tracker1` matching the spec (Section 1). Order matters for FKs: `companies` before `users` before `internships`/`company_internships`/`applications` before `notifications`. Copy the existing table definitions for `progress_logs`, `documents`, `activity_log`, `settings`, `password_resets`, `login_rate_limits` verbatim from `sql/database.sql`. The `users` table becomes:

```sql
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
```

New tables (match spec exactly):

```sql
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
```

- [ ] **Step 2: Write `sql/migrate_unify.php`**

A CLI script that opens all three legacy DBs, creates the unified schema, and copies data. Run only after a manual `mysqldump` backup (printed as the first line of output). Core copy logic (each copy uses `INSERT ... ON DUPLICATE KEY UPDATE` with explicit column lists):

```php
<?php
// Run from CLI: php sql/migrate_unify.php
// WARNING: back up first: mysqldump internship_tracker1 > backup_main.sql (and *_admin, *_company)
$main = new PDO('mysql:host=localhost;dbname=internship_tracker1;charset=utf8mb4', $user='root', $pass='', [...]);
$admin = new PDO('mysql:host=localhost;dbname=internship_tracker_admin;charset=utf8mb4', $user='root', $pass='', [...]);
$company = new PDO('mysql:host=localhost;dbname=internship_tracker_company;charset=utf8mb4', $user='root', $pass='', [...]);

// 1. Apply unified_schema.sql (create tables if not exists).
// 2. companies: insert admin/company DB companies into main, keyed by name (upsert).
//    Build map oldCompanyName => newCompanyId.
// 3. users:
//    - admin_users (admin DB)  -> role 'admin', company_id resolved by name if set.
//    - admin_users (company DB)-> role 'company', company_id via map.
//    - main.users already hold students + any role='admin' rows; update role where 'admin'.
//    Upsert by email with ON DUPLICATE KEY UPDATE.
//    Build map email => newUserId.
// 4. company_internships: from company DB internships (company_id remapped).
//    Build map oldInternshipId => newId.
// 5. applications: from company DB applications; student_id remapped by email where
//    a matching user exists, else NULL; company_internship_id remapped.
// 6. Log every migrated row count; die with a summary at the end.
```

For each copy step, print row counts, e.g. `echo "users migrated: {$n}\n";`.

- [ ] **Step 3: Run the migration and verify**

Run: `php sql/migrate_unify.php`
Expected: prints migrated counts for companies, users, company_internships, applications. Then in phpMyAdmin verify:
- `SELECT role, COUNT(*) FROM users GROUP BY role;` shows `admin`, `company`, `student` buckets with the expected counts.
- `SELECT COUNT(*) FROM company_internships;` matches the old company-DB internships count.
- `SELECT COUNT(*) FROM applications;` matches old applications count.

- [ ] **Step 4: Backup the old schemas to git**

Copy the three legacy `sql/*_database.sql` files into `sql/legacy/` and commit so nothing is lost.

```bash
mkdir -p sql/legacy && git mv sql/admin_database.sql sql/legacy/ && git mv sql/company_database.sql sql/legacy/
git add sql/unified_schema.sql sql/migrate_unify.php sql/database.sql
git commit -m "feat(db): unified schema and migration script"
```

- [ ] **Step 5: Commit**

```bash
git add sql/unified_schema.sql sql/migrate_unify.php sql/legacy/
git commit -m "feat(db): unified schema and migration script"
```

### Task 0.2: Point config.php at one database

**Files:**
- Modify: `php/config.php:13-19`, `php/config.php:69-158`

**Interfaces:**
- Consumes: `sql/migrate_unify.php` output (unified DB).
- Produces: `Database::getConnection()` only; `ADMIN_DB_NAME`/`COMPANY_DB_NAME` removed; `getAdminConnection()`/`getCompanyConnection()` removed; `ensureCompanySchema()` removed.

- [ ] **Step 1: Remove the extra DB constants**

In `php/config.php`, delete lines 15-16 (`ADMIN_DB_NAME`, `COMPANY_DB_NAME`).

- [ ] **Step 2: Trim the Database class**

Replace the `Database` class (lines 69-158) with a version that keeps only `getConnection()`. Delete `getAdminConnection()`, `getCompanyConnection()`, the static properties `$adminInstance`/`$companyInstance`, and the `ensureCompanySchema()` function (line 159). Keep the existing `getConnection()` implementation unchanged.

- [ ] **Step 3: Syntax check**

Run: `php -l php/config.php`
Expected: `No syntax errors detected`.

- [ ] **Step 4: Grep for leftover references**

Run: `grep -rn "getAdminConnection\|getCompanyConnection\|ADMIN_DB_NAME\|COMPANY_DB_NAME\|ensureCompanySchema" php/`
Expected: only `auth.php` and page files remain — those are fixed in Tasks 0.3 and 0.4.

- [ ] **Step 5: Commit**

```bash
git add php/config.php
git commit -m "refactor(db): single database connection in config.php"
```

### Task 0.3: Rewrite auth to a single role-based path

**Files:**
- Modify: `php/auth.php` (handleLogin lines 48-281, handleRegister lines 297-488, handleCompanyChangePassword lines 727-758, handleListCompanyInternships 760-789, handleUpdateInternshipStatus 790-end)

**Interfaces:**
- Consumes: `Database::getConnection()` (Task 0.2); `users.role` values `student|company|admin`.
- Produces: `handleLogin()` returns JSON `{success, message, redirect}` where `redirect` = `dashboard.php` (student), `php/company_dashboard.php` (company), `php/admin_dashboard.php` (admin). `$_SESSION['user']` always contains `id, username, email, role, full_name, company_id`.

- [ ] **Step 1: Simplify handleLogin**

Replace the entire `handleLogin()` body with a single-path version. The `role_hint` param is ignored (keep accepting it for backward compat with old forms). Logic:

```php
function handleLogin(): void {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST['csrf_token'] ?? '';
    if (!verifyCSRF($csrf)) jsonResponse(false, 'Invalid request token.');
    if ($username === '') jsonResponse(false, 'Username is required.');
    if (strlen($password) < 6) jsonResponse(false, 'Password too short.');
    if (isRateLimited('login:' . strtolower($username))) jsonResponse(false, 'Too many attempts. Try again later.');

    $db = Database::getConnection();
    $isEmail = filter_var($username, FILTER_VALIDATE_EMAIL);
    $col = $isEmail ? 'email' : 'username';
    $stmt = $db->prepare("SELECT id, username, email, password_hash, role, full_name, company_id, is_active
                          FROM users WHERE $col = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        checkRateLimit('login:' . strtolower($username));
        jsonResponse(false, 'Invalid username or password.');
    }
    if ((int)$user['is_active'] !== 1) jsonResponse(false, 'Account is disabled. Contact administrator.');

    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int)$user['id'], 'username' => $user['username'], 'email' => $user['email'],
        'role' => $user['role'], 'full_name' => $user['full_name'],
        'company_id' => $user['company_id'] ? (int)$user['company_id'] : null,
    ];
    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    logActivity((int)$user['id'], 'login');

    $redirect = $user['role'] === 'admin' ? 'php/admin_dashboard.php'
              : ($user['role'] === 'company' ? 'php/company_dashboard.php' : 'dashboard.php');
    jsonResponse(true, 'Login successful', ['redirect' => $redirect]);
}
```

- [ ] **Step 2: Test login via HTTP**

Start XAMPP Apache+MySQL. Run:
```bash
curl -s -c /tmp/cj -b /tmp/cj -X POST http://localhost/internship-tracker/php/auth.php \
  -d "action=login&username=admin@interntracker.com&password=Admin@123&csrf_token=$(curl -s http://localhost/internship-tracker/php/auth.php?action=get_csrf | php -r 'echo json_decode(stream_get_contents(STDIN))->token;')"
```
Expected: JSON with `success:true` and `redirect:"php/admin_dashboard.php"`. Repeat for `student001@interntracker.com` → `dashboard.php`, and a migrated company account → `php/company_dashboard.php`.

- [ ] **Step 3: Verify register + change_password still work**

Exercise `handleRegister` (student) and `handleChangePassword` via curl against the running server; expect `success:true`. Confirm no code path calls `getCompanyConnection()` — grep returns nothing.

- [ ] **Step 4: Commit**

```bash
git add php/auth.php
git commit -m "refactor(auth): single role-based login path"
```

### Task 0.4: Update cross-DB call sites

**Files:**
- Modify: `php/admin.php` (lines 86-146), `php/admin_dashboard.php:21`, `php/admin_companies.php:18`, `php/admin_internships.php:18`, `php/company_applications.php:7`, `php/company_profile.php:7`, `php/company_internships.php:7`, `php/company_dashboard.php:7`, `php/api/export_reports.php:5`, `php/internships.php:446,490,536`

**Interfaces:**
- Consumes: `Database::getConnection()` (Task 0.2); unified tables.
- Produces: every page uses `$db = Database::getConnection();` and queries the unified schema.

- [ ] **Step 1: Replace connection calls**

In each file listed, replace `Database::getCompanyConnection()` / `Database::getAdminConnection()` with `Database::getConnection()`. In `php/admin.php`, replace `$cDb = Database::getCompanyConnection();` with `$cDb = Database::getConnection();`.

- [ ] **Step 2: Fix queries that referenced company-DB internals**

In `php/internships.php` lines 446-560 (`browseCompanyInternships`, `applyToCompanyInternship`, `getMyApplications`), rewrite the SQL to use the unified tables:
- Company postings now come from `company_internships ci JOIN companies c ON ci.company_id = c.id` (old code joined company-DB `internships`).
- `applyToCompanyInternship` inserts into `applications (company_internship_id, student_id, cover_letter, resume)` where `student_id = $user['id']`.
- `getMyApplications` selects `applications a JOIN company_internships ci ON a.company_internship_id = ci.id JOIN companies c ON ci.company_id = c.id WHERE a.student_id = ?`.

- [ ] **Step 3: Syntax check every touched file**

Run: `for f in php/admin.php php/admin_dashboard.php php/admin_companies.php php/admin_internships.php php/company_applications.php php/company_profile.php php/company_internships.php php/company_dashboard.php php/api/export_reports.php php/internships.php; do php -l "$f" || exit 1; done`
Expected: all pass.

- [ ] **Step 4: Grep for leftover cross-DB refs**

Run: `grep -rn "getAdminConnection\|getCompanyConnection" php/`
Expected: zero matches.

- [ ] **Step 5: Smoke-test role pages over HTTP**

Load `http://localhost/internship-tracker/php/admin_dashboard.php`, `php/company_dashboard.php`, `php/company_internships.php`, `php/company_applications.php`, `php/company_profile.php`, `php/admin_companies.php`, `php/admin_internships.php`, `php/api/export_reports.php` as their respective roles. Each should render 200 with real (non-empty) data where data exists.

- [ ] **Step 6: Commit**

```bash
git add php/admin.php php/admin_dashboard.php php/admin_companies.php php/admin_internships.php php/company_applications.php php/company_profile.php php/company_internships.php php/company_dashboard.php php/api/export_reports.php php/internships.php
git commit -m "refactor(db): use single connection across admin/company/API pages"
```

---

## Phase 1 — New features

### Task 1.1: notify() helper + in-app notifications center

**Files:**
- Modify: `php/config.php` (append `notify()`, `getUnreadNotifications()`, `markNotificationRead()` after `sendMail`)
- Create: `php/notifications.php` (action API)
- Test: `tests/notify_test.php`

**Interfaces:**
- Consumes: `notifications` table (Task 0.1); `sendMail()` (config.php); `Database::getConnection()`.
- Produces:
  - `notify(int $userId, string $title, string $message, string $type = 'info', bool $email = false): void`
  - `getUnreadNotifications(int $userId, int $limit = 20): array` → `[{id, title, message, type, is_read, created_at}]`
  - `markNotificationRead(int $notificationId, int $userId): void`
  - API `php/notifications.php?action=list|mark_read|unread_count`

- [ ] **Step 1: Write the failing test**

Create `tests/notify_test.php`:

```php
<?php
require_once __DIR__ . '/../php/config.php';
$db = Database::getConnection();
$db->beginTransaction();
try {
    $db->exec("INSERT INTO users (username,email,password_hash,role,full_name) VALUES
        ('tester_n_'.uniqid(), 'tn_'.uniqid().'@test.local', 'x', 'student', 'T')");
    $uid = (int)$db->lastInsertId();
    notify($uid, 'Hi', 'Hello');
    $unread = getUnreadNotifications($uid);
    assert(count($unread) === 1 && $unread[0]['title'] === 'Hi');
    markNotificationRead((int)$unread[0]['id'], $uid);
    assert(count(getUnreadNotifications($uid)) === 0);
    echo "PASS\n";
    $db->rollBack();
} catch (Throwable $e) {
    $db->rollBack();
    echo "FAIL: " . $e->getMessage() . "\n";
    exit(1);
}
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/notify_test.php`
Expected: `FAIL: Call to undefined function notify()`

- [ ] **Step 3: Implement the helpers**

Append to `php/config.php`:

```php
function notify(int $userId, string $title, string $message, string $type = 'info', bool $email = false): void {
    try {
        $db = Database::getConnection();
        $channel = $email ? 'both' : 'in_app';
        $stmt = $db->prepare("INSERT INTO notifications (user_id, title, message, type, channel) VALUES (?,?,?,?,?)");
        $stmt->execute([$userId, $title, $message, $type, $channel]);
    } catch (Throwable $e) {
        error_log('notify(): ' . $e->getMessage());
        return;
    }
    if ($email && defined('SMTP_USERNAME') && SMTP_USERNAME !== '') {
        try {
            $stmt = $db->prepare("SELECT email, full_name FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $u = $stmt->fetch();
            if ($u) sendMail($u['email'], $u['full_name'] ?? '', $title, $message);
        } catch (Throwable $e) {
            error_log('notify(): email failed: ' . $e->getMessage());
        }
    }
}

function getUnreadNotifications(int $userId, int $limit = 20): array {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT id, title, message, type, is_read, created_at
                          FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function markNotificationRead(int $notificationId, int $userId): void {
    Database::getConnection()->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
        ->execute([$notificationId, $userId]);
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/notify_test.php`
Expected: `PASS`

- [ ] **Step 5: Build the notifications API**

Create `php/notifications.php`:

```php
<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
$user = requireAuth();
$action = $_GET['action'] ?? $_POST['action'] ?? '';
switch ($action) {
    case 'list':
        jsonResponse(true, '', ['notifications' => getUnreadNotifications((int)$user['id'])]);
    case 'mark_read':
        markNotificationRead((int)($_GET['id'] ?? 0), (int)$user['id']);
        jsonResponse(true, '');
    case 'unread_count':
        $n = Database::getConnection()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $n->execute([(int)$user['id']]);
        jsonResponse(true, '', ['count' => (int)$n->fetchColumn()]);
    default:
        jsonResponse(false, 'Invalid action.');
}
```

- [ ] **Step 6: Syntax check + HTTP test**

Run: `php -l php/notifications.php && php -l php/config.php`
Then with a logged-in session cookie, GET `http://localhost/internship-tracker/php/notifications.php?action=unread_count` → expect JSON with `count`.

- [ ] **Step 7: Commit**

```bash
git add php/config.php php/notifications.php tests/notify_test.php
git commit -m "feat(notify): notifications helper, API, and tests"
```

### Task 1.2: Notification bell in shared header + triggers

**Files:**
- Create: `php/partials/header.php` (notification bell + nav slot)
- Modify: `dashboard.php`, `progress.php`, `profile.php`, `companies.php`, `browse_internships.php`, `calendar.php` (add `require_once 'php/partials/header.php'`)
- Modify: `php/internships.php` (status-change trigger), `php/auth.php` (registration trigger)

**Interfaces:**
- Consumes: `php/notifications.php` API; `notify()`.
- Produces: a reusable page header with brand, role-aware nav, notification bell (badge + dropdown), logout.

- [ ] **Step 1: Write the header partial**

Create `php/partials/header.php`. It reads `$user = requireAuth()`-style session data (passed in from the page), renders the brand, nav links per role, and the bell:

```php
<?php
if (!isset($user)) { $user = requireAuth(); }
$nav = [
  'student'  => [['dashboard.php','Dashboard'],['browse_internships.php','Browse'],['companies.php','Companies'],['calendar.php','Calendar'],['progress.php','Progress'],['profile.php','Profile']],
  'company'  => [['company_dashboard.php','Dashboard'],['company_internships.php','Postings'],['company_applications.php','Applications'],['company_profile.php','Profile']],
  'admin'    => [['php/admin_dashboard.php','Dashboard'],['php/admin_students.php','Students'],['php/admin_companies.php','Companies'],['php/admin_internships.php','Internships'],['php/admin_reports.php','Reports'],['php/admin_settings.php','Settings']],
];
$role = $user['role'] ?? 'student';
?>
<header class="topbar">
  <a class="brand" href="<?= appBasePathUrl('dashboard.php') ?>">🎓 <?= e(APP_NAME) ?></a>
  <nav class="main-nav"><?php foreach ($nav[$role] ?? [] as [$href,$label]): ?>
    <a href="<?= e(appBasePathUrl($href)) ?>"><?= e($label) ?></a>
  <?php endforeach; ?></nav>
  <div class="notif-bell" id="notifBell">
    <i class="fa-solid fa-bell"></i><span class="notif-badge" id="notifBadge" hidden>0</span>
    <div class="notif-dropdown" id="notifDropdown" hidden></div>
  </div>
  <a class="logout" href="<?= e(appBasePathUrl('logout.php')) ?>">Logout</a>
</header>
```

- [ ] **Step 2: Add bell JS**

Create `js/notifications.js` (loaded on every authed page): on load fetch `unread_count`; poll every 60s; click toggles dropdown listing `notifications.php?action=list`; clicking an item calls `mark_read`. Append:

```js
async function refreshNotifCount() {
  const r = await fetch('/internship-tracker/php/notifications.php?action=unread_count');
  const d = await r.json();
  const badge = document.getElementById('notifBadge');
  if (d.success) { badge.textContent = d.count; badge.hidden = d.count === 0; }
}
document.addEventListener('DOMContentLoaded', () => { refreshNotifCount(); setInterval(refreshNotifCount, 60000); });
```

- [ ] **Step 3: Wire the header into student pages**

At the top of each listed page (after `$user = requireAuth();`), add `require_once __DIR__ . '/php/partials/header.php';` and add `<script src="js/notifications.js"></script>` before `</body>`. Adjust relative paths if the page lives at web root vs `/php/`.

- [ ] **Step 4: Add the status-change trigger**

In `php/internships.php`, inside `updateInternship`, after the UPDATE succeeds, notify the student (owner) and, when a company changes a posting's status, the applying students:

```php
// after successful status change:
notify((int)$internship['student_id'], 'Internship status updated',
       "Your internship \"{$internship['title']}\" is now {$newStatus}.", 'info');
```

- [ ] **Step 5: Add the registration trigger**

In `php/auth.php` `handleRegister`, after the new student is created, `notify((int)$db->lastInsertId(), 'Welcome to ' . APP_NAME, 'Your account was created successfully.', 'success')`. Look up and notify the first admin user on new company registration.

- [ ] **Step 6: Verify in browser**

Log in as a student → bell shows 0 → change an internship status as admin/company → refresh student page → bell badge increments, dropdown lists the notification.

- [ ] **Step 7: Commit**

```bash
git add php/partials/header.php js/notifications.js php/internships.php php/auth.php
git commit -m "feat(notify): notification bell and status-change triggers"
```

### Task 1.3: Analytics — student dashboard charts

**Files:**
- Modify: `dashboard.php`
- Create: `php/analytics.php` (action API), `js/analytics.js`
- Test: `tests/analytics_test.php`

**Interfaces:**
- Consumes: unified `internships`, `progress_logs`, `companies`, `users`.
- Produces: API `php/analytics.php?scope=student&chart=status|timeline|stipend|hours` returning JSON; `js/analytics.js` renders charts into `#analyticsCharts`.

- [ ] **Step 1: Write the failing test**

Create `tests/analytics_test.php` that seeds two internships for a temp student, calls a new `studentAnalyticsData(int $userId): array` function, and asserts the four chart arrays have expected shapes (status keys present, timeline length > 0, stipend series length 2).

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/analytics_test.php` → Expected: `FAIL: Call to undefined function studentAnalyticsData()`.

- [ ] **Step 3: Implement the aggregation function**

Append to `php/config.php`:

```php
function studentAnalyticsData(int $userId): array {
    $db = Database::getConnection();
    $status = $db->prepare("SELECT status, COUNT(*) c FROM internships WHERE student_id = ? GROUP BY status");
    $status->execute([$userId]);
    $timeline = $db->prepare("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COUNT(*) c FROM internships WHERE student_id = ? GROUP BY ym ORDER BY ym");
    $timeline->execute([$userId]);
    $stipend = $db->prepare("SELECT c.name, i.stipend FROM internships i JOIN companies c ON i.company_id = c.id WHERE i.student_id = ? AND i.stipend > 0 ORDER BY i.stipend DESC");
    $stipend->execute([$userId]);
    $hours = $db->prepare("SELECT p.log_date, p.hours_worked, p.rating FROM progress_logs p
                           JOIN internships i ON p.internship_id = i.id WHERE i.student_id = ? ORDER BY p.log_date");
    $hours->execute([$userId]);
    return [
        'status'   => $status->fetchAll(),
        'timeline' => $timeline->fetchAll(),
        'stipend'  => $stipend->fetchAll(),
        'hours'    => $hours->fetchAll(),
    ];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/analytics_test.php` → Expected: `PASS`.

- [ ] **Step 5: Create the analytics API**

Create `php/analytics.php`:

```php
<?php
session_start();
require_once __DIR__ . '/config.php';
header('Content-Type: application/json');
$user = requireAuth();
$scope = $_GET['scope'] ?? '';
switch ($scope) {
    case 'student':
        jsonResponse(true, '', studentAnalyticsData((int)$user['id']));
    case 'admin':
        if ($user['role'] !== 'admin') { http_response_code(403); jsonResponse(false, 'Denied.'); }
        jsonResponse(true, '', adminAnalyticsData());
    case 'company':
        if ($user['role'] !== 'company') { http_response_code(403); jsonResponse(false, 'Denied.'); }
        jsonResponse(true, '', companyAnalyticsData((int)($user['company_id'] ?? 0)));
    default:
        jsonResponse(false, 'Invalid scope.');
}
```

(`adminAnalyticsData()` and `companyAnalyticsData()` are implemented in Tasks 1.4 and 1.5.)

- [ ] **Step 6: Add charts to dashboard.php**

Add a `<div id="analyticsCharts"></div>` section and include Chart.js CDN (`https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js`) plus `js/analytics.js`. In `js/analytics.js`:

```js
async function loadAnalytics(scope) {
  const r = await fetch('/internship-tracker/php/analytics.php?scope=' + scope);
  const d = await r.json();
  if (!d.success) return;
  const el = document.getElementById('analyticsCharts'); if (!el) return;
  el.innerHTML = '<canvas id="statusChart"></canvas><canvas id="timelineChart"></canvas>';
  new Chart(document.getElementById('statusChart'), { type: 'doughnut', data: {
    labels: d.data.status.map(s => s.status),
    datasets: [{ data: d.data.status.map(s => s.c), backgroundColor: ['#22C55E','#3B82F6','#F59E0B','#10B981','#8B5CF6','#EF4444','#6B7280'] }] } });
  new Chart(document.getElementById('timelineChart'), { type: 'line', data: {
    labels: d.data.timeline.map(t => t.ym),
    datasets: [{ label: 'Applications', data: d.data.timeline.map(t => t.c), borderColor: '#22C55E', tension: 0.3 }] } });
}
```

- [ ] **Step 7: Verify in browser**

Log in as a student with ≥2 internships and progress logs → dashboard shows a donut + timeline chart populated from real data.

- [ ] **Step 8: Commit**

```bash
git add dashboard.php php/analytics.php js/analytics.js tests/analytics_test.php
git commit -m "feat(analytics): student dashboard charts"
```

### Task 1.4: Analytics — admin dashboard charts

**Files:**
- Modify: `php/admin_dashboard.php`, `php/config.php` (add `adminAnalyticsData()`)
- Test: `tests/analytics_admin_test.php`

**Interfaces:**
- Consumes: unified `users`, `companies`, `internships`, `applications`, `company_internships`.
- Produces: `adminAnalyticsData(): array` → `{kpis, registrations, statusDist, topCompanies}`.

- [ ] **Step 1: Write the failing test**

Create `tests/analytics_admin_test.php` seeding one company, one student, one internship; asserts `adminAnalyticsData()` returns `kpis.students >= 1`, `topCompanies` non-empty, `statusDist` contains the seeded status.

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/analytics_admin_test.php` → `FAIL: Call to undefined function adminAnalyticsData()`.

- [ ] **Step 3: Implement adminAnalyticsData()**

```php
function adminAnalyticsData(): array {
    $db = Database::getConnection();
    $kpis = [
        'students'     => (int)$db->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
        'companies'    => (int)$db->query("SELECT COUNT(*) FROM companies")->fetchColumn(),
        'internships'  => (int)$db->query("SELECT COUNT(*) FROM internships")->fetchColumn(),
        'applications' => (int)$db->query("SELECT COUNT(*) FROM applications")->fetchColumn(),
    ];
    $regs = $db->query("SELECT DATE_FORMAT(created_at, '%Y-%m') ym, COUNT(*) c FROM users WHERE role='student' GROUP BY ym ORDER BY ym")->fetchAll();
    $status = $db->query("SELECT status, COUNT(*) c FROM internships GROUP BY status")->fetchAll();
    $top = $db->query("SELECT c.name, COUNT(i.id) n FROM companies c JOIN internships i ON i.company_id = c.id GROUP BY c.id ORDER BY n DESC LIMIT 5")->fetchAll();
    return ['kpis' => $kpis, 'registrations' => $regs, 'statusDist' => $status, 'topCompanies' => $top];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/analytics_admin_test.php` → Expected: `PASS`.

- [ ] **Step 5: Render KPI cards + charts in admin_dashboard.php**

Add 4 KPI cards (`kpis`), a registrations bar chart, a status donut, and a top-companies bar chart using `js/analytics.js` extended with an `adminAnalytics()` loader (same Chart.js pattern as Task 1.3, `type: 'bar'` for registrations and topCompanies, `type: 'doughnut'` for statusDist).

- [ ] **Step 6: Verify in browser**

Log in as admin → dashboard shows KPI cards and three charts with platform-wide numbers.

- [ ] **Step 7: Commit**

```bash
git add php/admin_dashboard.php php/config.php tests/analytics_admin_test.php
git commit -m "feat(analytics): admin dashboard KPIs and charts"
```

### Task 1.5: Analytics — company dashboard charts

**Files:**
- Modify: `php/company_dashboard.php`, `php/config.php` (add `companyAnalyticsData()`)
- Test: `tests/analytics_company_test.php`

**Interfaces:**
- Consumes: `company_internships`, `applications` for the logged-in company.
- Produces: `companyAnalyticsData(int $companyId): array` → `{perPosting, statusDist, timeline}`.

- [ ] **Step 1: Write the failing test**

Create `tests/analytics_company_test.php` seeding one company posting + two applications (one pending, one accepted); asserts `perPosting` has 1 row with `n=2`, `statusDist` shows both statuses, `timeline` non-empty.

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/analytics_company_test.php` → `FAIL: Call to undefined function companyAnalyticsData()`.

- [ ] **Step 3: Implement companyAnalyticsData()**

```php
function companyAnalyticsData(int $companyId): array {
    $db = Database::getConnection();
    $per = $db->prepare("SELECT ci.title, COUNT(a.id) n FROM company_internships ci
                         LEFT JOIN applications a ON a.company_internship_id = ci.id
                         WHERE ci.company_id = ? GROUP BY ci.id ORDER BY n DESC");
    $per->execute([$companyId]);
    $status = $db->prepare("SELECT a.status, COUNT(*) c FROM applications a
                            JOIN company_internships ci ON a.company_internship_id = ci.id
                            WHERE ci.company_id = ? GROUP BY a.status");
    $status->execute([$companyId]);
    $tl = $db->prepare("SELECT DATE_FORMAT(a.applied_at, '%Y-%m') ym, COUNT(*) c FROM applications a
                        JOIN company_internships ci ON a.company_internship_id = ci.id
                        WHERE ci.company_id = ? GROUP BY ym ORDER BY ym");
    $tl->execute([$companyId]);
    return ['perPosting' => $per->fetchAll(), 'statusDist' => $status->fetchAll(), 'timeline' => $tl->fetchAll()];
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/analytics_company_test.php` → Expected: `PASS`.

- [ ] **Step 5: Render charts in company_dashboard.php**

Add `perPosting` (bar), `statusDist` (doughnut), `timeline` (line) using the same `js/analytics.js` Chart.js pattern.

- [ ] **Step 6: Verify in browser**

Log in as a company with postings + applications → dashboard shows the three charts.

- [ ] **Step 7: Commit**

```bash
git add php/company_dashboard.php php/config.php tests/analytics_company_test.php
git commit -m "feat(analytics): company dashboard charts"
```

### Task 1.6: Calendar & timeline page

**Files:**
- Create: `calendar.php`, `js/calendar.js`, `css/calendar.css`
- Modify: `php/config.php` (add `calendarEvents(int $userId, ?int $adminFilter = null): array`)

**Interfaces:**
- Consumes: `internships`, `progress_logs`, `applications`, `company_internships` dates.
- Produces: `calendarEvents()` → events `[{date:'YYYY-MM-DD', title, type: 'applied|interview|internship_start|internship_end|progress', color}]`; `calendar.php` renders month grid + timeline.

- [ ] **Step 1: Write the failing test**

Create `tests/calendar_test.php` seeding one internship with known start/end + one progress log; asserts `calendarEvents()` returns events covering start, end, and the progress date with correct types.

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/calendar_test.php` → `FAIL: Call to undefined function calendarEvents()`.

- [ ] **Step 3: Implement calendarEvents()**

```php
function calendarEvents(int $userId, ?int $adminFilter = null): array {
    $db = Database::getConnection();
    $events = [];
    $iStmt = $db->prepare("SELECT i.*, c.name company FROM internships i JOIN companies c ON i.company_id = c.id
                           WHERE i.student_id = ?");
    $iStmt->execute([$userId]);
    foreach ($iStmt->fetchAll() as $i) {
        $events[] = ['date' => substr($i['created_at'], 0, 10), 'title' => "Applied: {$i['title']} @ {$i['company']}", 'type' => 'applied'];
        $events[] = ['date' => $i['start_date'], 'title' => "{$i['title']} @ {$i['company']}", 'type' => 'internship_start'];
        $events[] = ['date' => $i['end_date'],   'title' => "{$i['title']} ends",           'type' => 'internship_end'];
        if ($i['status'] === 'interview') $events[] = ['date' => $i['start_date'], 'title' => "Interview: {$i['title']}", 'type' => 'interview'];
    }
    $pStmt = $db->prepare("SELECT p.log_date, p.hours_worked, i.title FROM progress_logs p JOIN internships i ON p.internship_id = i.id WHERE i.student_id = ?");
    $pStmt->execute([$userId]);
    foreach ($pStmt->fetchAll() as $p) {
        $events[] = ['date' => $p['log_date'], 'title' => "Progress log: {$p['title']} ({$p['hours_worked']}h)", 'type' => 'progress'];
    }
    return $events;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/calendar_test.php` → Expected: `PASS`.

- [ ] **Step 5: Build calendar.php**

Render a month grid (JS-generated from embedded JSON events). `calendar.php` outputs:

```php
$events = calendarEvents((int)$user['id']);
// (admin: accept ?student_id= and reuse calendarEvents($id, $filter))
$eventsJson = json_encode($events);
// <div id="calGrid"></div><div id="calTimeline"></div>
// <script>window.CAL_EVENTS = <?= $eventsJson ?>;</script>
```

`js/calendar.js`: build a 7-column month grid; color dots by `type` (applied=blue, interview=amber, internship_start=green, internship_end=red, progress=purple); clicking a day lists that day's events below; a timeline list groups events by month with badges. `css/calendar.css`: grid cells, dot colors, today highlight, responsive.

- [ ] **Step 6: Verify in browser**

Student with internships + progress logs → calendar shows dots on correct dates; click a day → event list; timeline groups correctly. Admin can pass `?student_id=` to filter.

- [ ] **Step 7: Commit**

```bash
git add calendar.php js/calendar.js css/calendar.css php/config.php tests/calendar_test.php
git commit -m "feat(calendar): calendar and timeline page"
```

---

## Phase 2 — Hardening & bug fixes

### Task 2.1: File upload validation + cleanup

**Files:**
- Modify: `php/config.php` (add `handleUpload(array $file, string $subdir): ?string`), `php/internships.php` (create/update), `php/profile.php`, `php/company_profile.php`

**Interfaces:**
- Consumes: `UPLOAD_DIR`, `MAX_FILE_SIZE`, `ALLOWED_TYPES` (config.php).
- Produces: `handleUpload(array $file, string $subdir): ?string` returns safe relative path or null (logged).

- [ ] **Step 1: Write the failing test**

Create `tests/upload_test.php` that calls `handleUpload()` with a synthetic file array (`['name'=>'x.pdf','tmp_name'=>'<write temp file>','error'=>UPLOAD_ERR_OK]`), asserts the returned path is under `uploads/`, that a `.php` disguised file is rejected, and that an oversized file is rejected.

- [ ] **Step 2: Run to verify it fails**

Run: `php tests/upload_test.php` → `FAIL: Call to undefined function handleUpload()`.

- [ ] **Step 3: Implement handleUpload()**

```php
function handleUpload(array $file, string $subdir): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if ($file['size'] > MAX_FILE_SIZE) { error_log("Upload rejected: too large"); return null; }
    if (!in_array($file['type'], ALLOWED_TYPES, true)) { error_log("Upload rejected: bad type"); return null; }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (in_array($ext, ['php','phtml','phar','sh'], true)) { error_log("Upload rejected: bad extension"); return null; }
    $dir = UPLOAD_DIR . trim($subdir, '/') . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) { error_log("Upload move failed"); return null; }
    return 'uploads/' . trim($subdir, '/') . '/' . $name;
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/upload_test.php` → Expected: `PASS`.

- [ ] **Step 5: Wire into create/update/delete**

In `php/internships.php` `createInternship`/`updateInternship`, replace any raw `$_FILES` handling with `handleUpload()` for `offer_letter`, `resume`, `cover_letter`, `transcripts`. In `deleteInternship`, `@unlink` the stored file paths before deleting the record. Same in `profile.php` / `company_profile.php` for avatar/logo uploads.

- [ ] **Step 6: Verify + commit**

Smoke: upload a PDF as a student → file appears under `uploads/`, DB path is relative. Delete the internship → file removed from disk.

```bash
git add php/config.php php/internships.php php/profile.php php/company_profile.php tests/upload_test.php
git commit -m "fix(uploads): safe file validation and cleanup"
```

### Task 2.2: CSRF audit on all POST forms

**Files:**
- Modify: pages containing `<form method="post">` — search `grep -rln '<form' *.php php/*.php`

**Interfaces:**
- Consumes: `generateCSRF()` / `verifyCSRF()` (config.php).
- Produces: every POST form includes a hidden `csrf_token`.

- [ ] **Step 1: Find all forms**

Run: `grep -rn '<form' --include=*.php .`
Expected: a list of files. For each, confirm a `<input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">` exists inside the form and `$csrf` is set (from `generateCSRF()`).

- [ ] **Step 2: Fix missing tokens**

For each form missing a token: add the hidden input; ensure the page computed `$csrf = generateCSRF();` before output. For JS-driven forms that POST via `fetch`, verify the JS includes `csrf_token` (pages already expose a `<meta name="csrf-token">` — read it and append to the body).

- [ ] **Step 3: Verify + commit**

Log in and submit every POST form; confirm none return "Invalid request token."

```bash
git add -A
git commit -m "fix(csrf): ensure every POST form carries a token"
```

### Task 2.3: XSS audit — e() on all output

**Files:**
- Modify: all `.php` pages — `grep -rn 'echo \$' --include=*.php php/ *.php`

**Interfaces:**
- Consumes: `e()` (config.php).
- Produces: no raw `echo $var` of user/DB data; every echoed dynamic value wrapped in `e()`.

- [ ] **Step 1: Find raw echoes**

Run: `grep -rn 'echo \$' --include=*.php . | grep -v 'e(' | grep -v 'jsonResponse'`
Audit each hit: if it's user/DB-derived data in HTML context, wrap with `e(...)`. Skip values already HTML-escaped or numeric-only.

- [ ] **Step 2: Fix**

Wrap offending echoes in `e()`.

- [ ] **Step 3: Verify + commit**

```bash
git add -A
git commit -m "fix(xss): escape all dynamic output"
```

### Task 2.4: Password-reset flow verification

**Files:**
- Modify: `reset_password.php`, `php/auth.php` (`handleForgotRequest`, `handleForgotReset`)

- [ ] **Step 1: Review the flow end-to-end**

Read `handleForgotRequest` (auth.php:489) and `handleForgotReset` (auth.php:632) plus `reset_password.php`. Confirm: token stored hashed; expiry checked; token single-use (`used_at` set). Confirm the reset link uses `appBasePathUrl()`.

- [ ] **Step 2: Test over HTTP**

With a student account, request a reset → capture the emailed link (reads `uploads/emails/*.html` on XAMPP) → visit link → set a new password → log in with it → old password rejected.

- [ ] **Step 3: Fix what breaks + commit**

Fix any broken URL, unset token, or expiry bug found.

```bash
git add -A
git commit -m "fix(auth): verified password reset end-to-end"
```

### Task 2.5: Date validation + status transitions

**Files:**
- Modify: `php/internships.php` (`createInternship`, `updateInternship`), `php/company_internships.php`

- [ ] **Step 1: Add validation rules**

In `createInternship`/`updateInternship`, reject `start_date > end_date`:

```php
if (!empty($_POST['start_date']) && !empty($_POST['end_date']) && $_POST['end_date'] < $_POST['start_date']) {
    jsonResponse(false, 'End date cannot be before start date.');
}
```

In `company_internships.php` status updates, only allow transitions within a whitelist map (e.g. active↔closed↔pending), rejecting unknown values.

- [ ] **Step 2: Test**

Submit an internship with end before start → expect the JSON error. Change a posting to an invalid status → expect rejection.

- [ ] **Step 3: Commit**

```bash
git add php/internships.php php/company_internships.php
git commit -m "fix(validation): date and status transition checks"
```

### Task 2.6: Remove dead files

**Files:**
- Delete: `php/tempCodeRunnerFile.php`, `reset_pw.php`, `student-dashboard.html`

- [ ] **Step 1: Confirm no references**

Run: `grep -rn "tempCodeRunnerFile\|reset_pw.php\|student-dashboard" --include=*.php --include=*.html .`
Expected: no references outside this plan.

- [ ] **Step 2: Delete + commit**

```bash
git rm php/tempCodeRunnerFile.php reset_pw.php student-dashboard.html
git commit -m "chore: remove dead files"
```

---

## Phase 3 — UI/UX polish

### Task 3.1: Shared layout partials for admin/company pages

**Files:**
- Create: `php/partials/admin_header.php`, `php/partials/company_header.php` (or reuse `header.php` from Task 1.2)
- Modify: `php/admin_dashboard.php`, `php/admin_students.php`, `php/admin_companies.php`, `php/admin_internships.php`, `php/admin_reports.php`, `php/admin_settings.php`, `php/company_dashboard.php`, `php/company_internships.php`, `php/company_applications.php`, `php/company_profile.php`

**Interfaces:**
- Consumes: `php/partials/header.php` pattern (Task 1.2).
- Produces: consistent topbar/nav per role; pages delegate header/footer rendering to partials.

- [ ] **Step 1: Extract headers**

For each admin page, replace the repeated `<header>`/nav markup with `require_once __DIR__ . '/partials/admin_header.php';` (and company pages with the company partial). Move any inline duplicate nav styles into `css/style.css`.

- [ ] **Step 2: Verify all pages render**

Load each page per role; nav links match role; no duplicate headers.

- [ ] **Step 3: Commit**

```bash
git add php/partials php/admin_dashboard.php php/admin_students.php php/admin_companies.php php/admin_internships.php php/admin_reports.php php/admin_settings.php php/company_dashboard.php php/company_internships.php php/company_applications.php php/company_profile.php
git commit -m "refactor(ui): shared layout partials for admin and company pages"
```

### Task 3.2: Consolidate per-page styles into the theme

**Files:**
- Modify: `css/style.css`, `css/responsive.css`, all pages with inline `<style>` blocks

- [ ] **Step 1: Inventory inline styles**

Run: `grep -rn '<style>' --include=*.php .`
Expected: list of pages with `<style>` blocks.

- [ ] **Step 2: Consolidate**

For each page: move unique styles into `css/style.css` (component classes) and `css/responsive.css` (breakpoints), then remove the page's `<style>` block. Standardize class names already in use (`.card`, `.btn`, `.badge`, `.stat-card`); create missing shared classes for any ad-hoc styling so pages stop redefining the same thing.

- [ ] **Step 3: Verify visual parity**

Screenshot each page before/after (or eyeball) — no layout regression at desktop and mobile widths.

- [ ] **Step 4: Commit**

```bash
git add css/style.css css/responsive.css
git commit -m "refactor(ui): consolidate inline styles into theme"
```

### Task 3.3: Consistent components — toasts, empty states, loading

**Files:**
- Modify: `css/style.css`, `js/app.js` (add `showToast()`), pages as needed

- [ ] **Step 1: Add shared toast + empty-state styles**

Add `.toast` (fixed bottom-right, colored by type) and `.empty-state` (centered icon + text) to `css/style.css`. Add `showToast(message, type)` to `js/app.js` that auto-dismisses after 4s.

- [ ] **Step 2: Wire toasts into key actions**

Replace ad-hoc `alert()`/`innerHTML` feedback in dashboard/companies/progress with `showToast()`.

- [ ] **Step 3: Add empty states to lists**

Where a list renders nothing (no internships, no companies, no notifications), show a friendly `.empty-state` block.

- [ ] **Step 4: Verify + commit**

```bash
git add css/style.css js/app.js
git commit -m "feat(ui): shared toasts and empty states"
```

---

## Phase 4 — Testing & demo

### Task 4.1: Seed-data script

**Files:**
- Create: `sql/seed_demo.php`

**Interfaces:**
- Consumes: unified schema.
- Produces: deterministic demo records across all three roles (≥4 students, ≥6 companies, ≥10 student internships with progress logs, ≥6 company postings with applications, sample notifications).

- [ ] **Step 1: Write the seeder**

`sql/seed_demo.php` (CLI) inserts demo rows using `ON DUPLICATE KEY UPDATE`. Use fixed usernames so it is idempotent: `demo_student1..4@interntracker.com` (password `Student@123`), `demo_company@interntracker.com` (password `Company@123`, linked to a company), plus one admin. Distribute internship dates across the last 6 months and next 2 months so analytics + calendar look alive. Insert progress logs weekly for ongoing internships and applications across statuses.

- [ ] **Step 2: Run + verify counts**

Run: `php sql/seed_demo.php`
Verify in phpMyAdmin: counts per table increased as expected; charts/calendar populated when logged in as demo accounts.

- [ ] **Step 3: Commit**

```bash
git add sql/seed_demo.php
git commit -m "feat(demo): seed script with realistic data"
```

### Task 4.2: Smoke-test checklist run

**Files:**
- Create: `docs/DEMO.md` (begin; finish in Task 4.3)

- [ ] **Step 1: Run the checklist**

Execute and record results in `docs/DEMO.md`:
- [ ] Fresh import of `sql/unified_schema.sql` + `sql/seed_demo.php` on a clean DB
- [ ] Login: student / company / admin → correct dashboards
- [ ] Student: add/edit/delete internship; log progress; browse & apply; upload a PDF; notifications bell works
- [ ] Company: create posting; see applications; change status; dashboard charts render
- [ ] Admin: KPIs + charts render; manage students/companies/internships; reports page; settings
- [ ] Calendar: dots on dates; day click; timeline
- [ ] Password reset end-to-end
- [ ] Responsive at 375px and 1280px

- [ ] **Step 2: Fix any failures found**

Address each failure with a targeted commit (repeat the relevant task's steps).

- [ ] **Step 3: Commit the checklist**

```bash
git add docs/DEMO.md
git commit -m "docs(demo): smoke-test checklist"
```

### Task 4.3: Finalize DEMO.md

**Files:**
- Modify: `docs/DEMO.md`

- [ ] **Step 1: Write the walkthrough**

Add: demo credentials table (all roles + passwords), a 10-minute "what to show" script (start at landing → login as each role → click through the highlights), and a note that SMTP email is optional (in-app notifications work without it).

- [ ] **Step 2: Update README.md**

Update the README feature list (analytics, calendar, notifications) and project structure to match the new files (`php/partials/`, `php/notifications.php`, `calendar.php`, `js/analytics.js`, `js/calendar.js`, `js/notifications.js`, `sql/unified_schema.sql`, `sql/seed_demo.php`).

- [ ] **Step 3: Commit**

```bash
git add docs/DEMO.md README.md
git commit -m "docs: demo walkthrough and updated README"
```

---

## Final Verification (run at the end)

- [ ] `grep -rn "getAdminConnection\|getCompanyConnection\|ADMIN_DB_NAME\|COMPANY_DB_NAME" php/` → zero matches
- [ ] `for f in $(git diff --name-only main); do case "$f" in *.php) php -l "$f" || exit 1;; esac; done` → all clean
- [ ] `php tests/notify_test.php && php tests/analytics_test.php && php tests/analytics_admin_test.php && php tests/analytics_company_test.php && php tests/calendar_test.php && php tests/upload_test.php` → all PASS
- [ ] Browser smoke test per Task 4.2 passes for all three roles
