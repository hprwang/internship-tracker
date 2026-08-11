<?php
/**
 * migrate_unify.php  —  InternTrack one-time unification migration (CLI)
 *
 * Merges the three legacy databases into a single `internship_tracker1`:
 *   - internship_tracker_admin  (admin accounts)          -> users (role=admin)
 *   - internship_tracker_company (company accounts,       -> users (role=company)
 *                                companies, internships,    companies (canonical)
 *                                applications)               company_internships
 *                                                           applications
 *   - internship_tracker1 main  (students, admins)        -> kept in place, extended
 *
 * Safety:
 *   - NEVER drops the legacy databases (they stay as a backup).
 *   - Idempotent — safe to run more than once.
 *   - Uses information_schema to introspect live schemas, so it adapts to
 *     column drift instead of assuming a fixed shape.
 *
 * Usage:
 *   php sql/migrate_unify.php
 */

declare(strict_types=1);

$host = 'localhost';
$user = 'root';
$pass = '';
$targetDb = 'internship_tracker1';
$adminDb  = 'internship_tracker_admin';
$companyDb = 'internship_tracker_company';

$pdo = new PDO("mysql:host={$host};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

// --------------------------------------------------------------------------
// Helpers
// --------------------------------------------------------------------------

function useDb(PDO $pdo, string $db): void { $pdo->exec("USE `{$db}`"); }

/** Current default database for the connection, or null if none selected. */
function defaultDb(PDO $pdo): ?string {
    $v = $pdo->query("SELECT DATABASE()")->fetchColumn();
    return ($v === false || $v === null || $v === '') ? null : (string)$v;
}

/** Run $fn while the default DB is information_schema, then restore the prior one. */
function withInfoSchema(PDO $pdo, callable $fn) {
    $prev = defaultDb($pdo);
    useDb($pdo, 'information_schema');
    $result = $fn();
    if ($prev !== null && $prev !== 'information_schema') useDb($pdo, $prev);
    return $result;
}

function tableExists(PDO $pdo, string $db, string $table): bool {
    return (bool)withInfoSchema($pdo, function () use ($pdo, $db, $table) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t");
        $st->execute([':db' => $db, ':t' => $table]);
        return (int)$st->fetchColumn() > 0;
    });
}

/** @return array<string,array> column name => information_schema row */
function columnsOf(PDO $pdo, string $db, string $table): array {
    if (!tableExists($pdo, $db, $table)) return [];
    return withInfoSchema($pdo, function () use ($pdo, $db, $table) {
        $st = $pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t");
        $st->execute([':db' => $db, ':t' => $table]);
        $cols = [];
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $cols[$row['COLUMN_NAME']] = $row;
        }
        return $cols;
    });
}

function indexExists(PDO $pdo, string $db, string $table, string $index): bool {
    return (bool)withInfoSchema($pdo, function () use ($pdo, $db, $table, $index) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND INDEX_NAME = :i");
        $st->execute([':db' => $db, ':t' => $table, ':i' => $index]);
        return (int)$st->fetchColumn() > 0;
    });
}

function tableCount(PDO $pdo, string $db, string $table): int {
    if (!tableExists($pdo, $db, $table)) return 0;
    useDb($pdo, $db);
    return (int)$pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
}

/** Add a column to a table only if it does not already exist. */
function ensureCol(PDO $pdo, string $db, string $table, string $name, string $def, array $cols): array {
    if (isset($cols[$name])) return $cols;
    useDb($pdo, $db);
    $pdo->exec("ALTER TABLE `{$table}` ADD COLUMN `{$name}` {$def}");
    echo "  + added column {$table}.{$name}\n";
    $cols[$name] = ['COLUMN_NAME' => $name];
    return $cols;
}

/** Extend an ENUM column to include extra values (idempotent). */
function extendEnum(PDO $pdo, string $db, string $table, string $name, array $values): void {
    $cols = columnsOf($pdo, $db, $table);
    if (!isset($cols[$name])) return;
    $current = $cols[$name]['COLUMN_TYPE'];           // e.g. enum('admin','student')
    foreach ($values as $v) {
        if (strpos($current, "'" . addslashes($v) . "'") !== false) continue;
        $in = array_map(fn($x) => "'" . addslashes($x) . "'", $values);
        $newEnum = "enum(" . implode(',', $in) . ")";
        useDb($pdo, $db);
        $pdo->exec("ALTER TABLE `{$table}` MODIFY `{$name}` {$newEnum}");
        echo "  + extended enum {$table}.{$name} with " . $v . "\n";
        return; // re-check next time the script runs
    }
}

function info(string $msg): void { echo "[migrate] {$msg}\n"; }

// --------------------------------------------------------------------------
// Step 0 — make sure the target database + core tables exist
// --------------------------------------------------------------------------

withInfoSchema($pdo, function () use ($pdo, $targetDb) {
    $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = :db");
    $st->execute([':db' => $targetDb]);
    if ((int)$st->fetchColumn() === 0) {
        $pdo->exec("CREATE DATABASE `{$targetDb}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
});
useDb($pdo, $targetDb);
info("target database: {$targetDb}");

$pdo->exec("CREATE TABLE IF NOT EXISTS companies (
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
  INDEX idx_status (status)
) ENGINE=InnoDB");

// --------------------------------------------------------------------------
// Step 1 — canonical companies: ensure columns, then merge company DB in
// --------------------------------------------------------------------------

$cc = columnsOf($pdo, $targetDb, 'companies');
foreach ([
    'industry' => 'VARCHAR(100)',
    'description' => 'TEXT',
    'website' => 'VARCHAR(255)',
    'location' => 'VARCHAR(200)',
    'contact_person' => 'VARCHAR(150)',
    'contact_email' => 'VARCHAR(150)',
    'contact_phone' => 'VARCHAR(30)',
    'email' => 'VARCHAR(150)',
    'phone' => 'VARCHAR(50)',
    'logo_url' => 'VARCHAR(255)',
    'status' => "VARCHAR(20) DEFAULT 'active'",
] as $name => $def) {
    $cc = ensureCol($pdo, $targetDb, 'companies', $name, $def, $cc);
}

// dedupe by name before enforcing the unique key (keep lowest id)
$pdo->exec("DELETE c FROM companies c
  JOIN companies k ON k.name = c.name AND k.id < c.id");

// enforce unique name (safe now)
try {
    $pdo->exec("ALTER TABLE companies ADD UNIQUE KEY uk_company_name (name)");
    echo "  + added unique key on companies.name\n";
} catch (PDOException $e) {
    // already present or duplicate edge case — continue
}

// merge legacy company DB companies (keep existing main records, fill blanks)
if (tableExists($pdo, $companyDb, 'companies')) {
    $src = columnsOf($pdo, $companyDb, 'companies');
    $copyable = array_intersect(['name','industry','description','website','location',
        'contact_person','contact_email','contact_phone','email','phone','logo_url','status'],
        array_keys($src));
    if (in_array('name', $copyable, true)) {
        $fields = implode(', ', $copyable);
        $updates = implode(', ', array_map(fn($f) => "{$f} = VALUES({$f})", array_filter($copyable, fn($f) => $f !== 'name')));
        $pdo->exec("INSERT INTO companies ({$fields})
            SELECT {$fields} FROM `{$companyDb}`.companies
            ON DUPLICATE KEY UPDATE {$updates}");
        info("merged companies from {$companyDb}: " . count($copyable) . " fields copied");
    }
}

// --------------------------------------------------------------------------
// Step 2 — users table shape (add company_id, extend role enum)
// --------------------------------------------------------------------------

$uc = columnsOf($pdo, $targetDb, 'users');
foreach ([
    'company_id' => 'INT DEFAULT NULL',
    'last_login' => 'TIMESTAMP NULL',
    'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP',
] as $name => $def) {
    $uc = ensureCol($pdo, $targetDb, 'users', $name, $def, $uc);
}
if (isset($uc['company_id']) && !indexExists($pdo, $targetDb, 'users', 'idx_company')) {
    $pdo->exec("ALTER TABLE users ADD KEY idx_company (company_id)");
}
if (isset($uc['email']) && !indexExists($pdo, $targetDb, 'users', 'uq_email')) {
    // dedupe duplicate emails (keep lowest id) before enforcing uniqueness
    $pdo->exec("DELETE u FROM users u
      JOIN users k ON k.email = u.email AND k.id < u.id");
    try {
        $pdo->exec("ALTER TABLE users ADD UNIQUE KEY uq_email (email)");
        echo "  + added unique key on users.email\n";
    } catch (PDOException $e) {
        echo "  ! could not add unique key on users.email: {$e->getMessage()}\n";
    }
}
extendEnum($pdo, $targetDb, 'users', 'role', ['student', 'company', 'admin']);

// demote legacy supervisor rows to student (no such role in unified model)
useDb($pdo, $targetDb);
$pdo->exec("UPDATE users SET role = 'student' WHERE role NOT IN ('student','company','admin')");

// --------------------------------------------------------------------------
// Step 3 — merge admin accounts from all three sources into users
// --------------------------------------------------------------------------

function lookupEmail(PDO $pdo, string $db, string $email): ?int {
    useDb($pdo, $db);
    $st = $pdo->prepare("SELECT id FROM users WHERE email = :e LIMIT 1");
    $st->execute([':e' => $email]);
    $id = $st->fetchColumn();
    return $id === false ? null : (int)$id;
}

/** Insert/update a user row. Later sources win for the same email. */
function upsertUser(PDO $pdo, string $targetDb, array $u): void {
    useDb($pdo, $targetDb);
    $email = (string)$u['email'];
    $role  = in_array($u['role'] ?? 'student', ['student', 'company', 'admin'], true)
        ? $u['role'] : 'student';
    $username = (string)($u['username'] ?? '');

    $existing = lookupEmail($pdo, $targetDb, $email);
    if ($existing !== null) {
        // keep existing; only company accounts may update role/company_id
        if ($role === 'company') {
            $st = $pdo->prepare("UPDATE users SET role='company',
                company_id = :cid, is_active = :act, full_name = COALESCE(NULLIF(:fn,''), full_name)
                WHERE id = :id");
            $st->execute([
                ':cid' => $u['company_id'] ?? null,
                ':act' => $u['is_active'] ?? 1,
                ':fn'  => $u['full_name'] ?? '',
                ':id'  => $existing,
            ]);
        }
        return;
    }

    // username collision with a different email -> suffix it
    $base = $username !== '' ? $username : ('user' . substr(md5($email), 0, 6));
    $name = $base; $i = 1;
    while (true) {
        $st = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :un");
        $st->execute([':un' => $name]);
        if ((int)$st->fetchColumn() === 0) break;
        $name = $base . '_' . (++$i);
    }

    $hash = (string)($u['password_hash'] ?? '');
    if ($hash === '' || !preg_match('/^\$2[ayb]\$/', $hash)) {
        $hash = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT); // unrecoverable placeholder
    }

    $st = $pdo->prepare("INSERT INTO users (username, email, password_hash, role, full_name, company_id, is_active)
        VALUES (:un, :em, :ph, :role, :fn, :cid, :act)");
    $st->execute([
        ':un'   => $name,
        ':em'   => $email,
        ':ph'   => $hash,
        ':role' => $role,
        ':fn'   => (string)($u['full_name'] ?? $name),
        ':cid'  => $u['company_id'] ?? null,
        ':act'  => $u['is_active'] ?? 1,
    ]);
}

/** Build map: old numeric company id (in a legacy DB) => target companies.id, by name. */
function companyMapByName(PDO $pdo, string $targetDb, string $legacyDb): array {
    $map = [];
    if (!tableExists($pdo, $legacyDb, 'companies')) return $map;
    $legacyCols = columnsOf($pdo, $legacyDb, 'companies');
    if (!isset($legacyCols['id']) || !isset($legacyCols['name'])) return $map;
    $legacyRows = $pdo->query("SELECT id, name FROM `{$legacyDb}`.companies")->fetchAll(PDO::FETCH_ASSOC);
    $byName = [];
    useDb($pdo, $targetDb);
    foreach ($pdo->query("SELECT id, name FROM companies")->fetchAll(PDO::FETCH_ASSOC) as $t) {
        $byName[$t['name']] = (int)$t['id'];
    }
    foreach ($legacyRows as $r) {
        if (isset($byName[$r['name']])) $map[(int)$r['id']] = $byName[$r['name']];
    }
    return $map;
}

// 3a. main DB admin_users (auto-created legacy table) — company accounts first
if (tableExists($pdo, $targetDb, 'admin_users')) {
    $mainCompanyMap = []; // numeric ids in main DB companies map 1:1 to themselves
    foreach ($pdo->query("SELECT id FROM companies")->fetchAll(PDO::FETCH_COLUMN) as $cid) {
        $mainCompanyMap[(int)$cid] = (int)$cid;
    }
    foreach ($pdo->query("SELECT id, username, email, password_hash, role, full_name, company_id FROM admin_users")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cid = $r['company_id'] ? (int)$r['company_id'] : null;
        if ($cid !== null && isset($mainCompanyMap[$cid])) {
            $role = 'company';
        } else {
            $role = ($r['role'] === 'admin' || $r['role'] === 'super_admin') ? 'admin' : 'company';
            $cid = null;
        }
        upsertUser($pdo, $targetDb, [
            'username' => $r['username'], 'email' => $r['email'],
            'password_hash' => $r['password_hash'] ?? '', 'full_name' => $r['full_name'],
            'role' => $role, 'company_id' => $cid, 'is_active' => 1,
        ]);
    }
    info("merged admin_users from main DB into users");
}

// 3b. admin DB admin_users — admins (only added for emails not already present)
if (tableExists($pdo, $adminDb, 'admin_users')) {
    foreach ($pdo->query("SELECT username, email, password_hash, role, full_name FROM `{$adminDb}`.admin_users")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        upsertUser($pdo, $targetDb, [
            'username' => $r['username'], 'email' => $r['email'],
            'password_hash' => $r['password_hash'] ?? '', 'full_name' => $r['full_name'],
            'role' => 'admin', 'company_id' => null, 'is_active' => 1,
        ]);
    }
    info("merged admin_users from {$adminDb} into users");
}

// 3c. company DB admin_users — company accounts (wins on company_id for dup emails)
if (tableExists($pdo, $companyDb, 'admin_users')) {
    $map = companyMapByName($pdo, $targetDb, $companyDb);
    foreach ($pdo->query("SELECT username, email, password_hash, role, full_name, company_id FROM `{$companyDb}`.admin_users")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cid = $r['company_id'] ? ($map[(int)$r['company_id']] ?? null) : null;
        upsertUser($pdo, $targetDb, [
            'username' => $r['username'], 'email' => $r['email'],
            'password_hash' => $r['password_hash'] ?? '', 'full_name' => $r['full_name'],
            'role' => 'company', 'company_id' => $cid, 'is_active' => 1,
        ]);
    }
    info("merged admin_users from {$companyDb} into users");
}

// --------------------------------------------------------------------------
// Step 4 — company_internships (job postings)
// --------------------------------------------------------------------------

$pdo->exec("CREATE TABLE IF NOT EXISTS company_internships (
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
  INDEX idx_company (company_id),
  INDEX idx_status (status)
) ENGINE=InnoDB");

$postingMap = []; // old company-DB internships.id => new company_internships.id
if (tableExists($pdo, $companyDb, 'internships')) {
    useDb($pdo, $targetDb);

    // Remove duplicates left by a previous partial run (keep lowest id per company+title).
    $pdo->exec("DELETE p FROM company_internships p
      JOIN company_internships k ON k.company_id = p.company_id AND k.title = p.title AND k.id < p.id");

    // Existing postings keyed by (company_id, title) => id, for idempotency.
    $existingPostings = [];
    foreach ($pdo->query("SELECT id, company_id, title FROM company_internships")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existingPostings[$row['company_id'] . '|' . $row['title']] = (int)$row['id'];
    }

    $map = companyMapByName($pdo, $targetDb, $companyDb);
    $srcCols = columnsOf($pdo, $companyDb, 'internships');
    $statusEnum = ['active' => 'active', 'closed' => 'closed', 'pending' => 'pending',
                   'open' => 'active', 'Open' => 'active', 'Closed' => 'closed',
                   'Active' => 'active', 'Draft' => 'pending', '' => 'active'];
    $inserted = 0;
    foreach ($pdo->query("SELECT * FROM `{$companyDb}`.internships")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $cid = isset($r['company_id']) ? ($map[(int)$r['company_id']] ?? null) : null;
        if ($cid === null) continue; // posting without a known company
        $title = (string)($r['title'] ?? 'Untitled posting');
        $key = $cid . '|' . $title;
        if (isset($existingPostings[$key])) {
            $postingMap[(int)$r['id']] = $existingPostings[$key]; // already present — reuse
            continue;
        }
        $status = $statusEnum[$r['status'] ?? ''] ?? 'active';
        $st = $pdo->prepare("INSERT INTO company_internships (company_id, title, description, requirements, location, duration, stipend, status)
            VALUES (:cid, :title, :desc, :req, :loc, :dur, :stip, :status)");
        $st->execute([
            ':cid'    => $cid,
            ':title'  => $title,
            ':desc'   => isset($srcCols['description']) ? (string)($r['description'] ?? '') : '',
            ':req'    => isset($srcCols['requirements']) ? (string)($r['requirements'] ?? '') : '',
            ':loc'    => isset($srcCols['location']) ? (string)($r['location'] ?? '') : '',
            ':dur'    => isset($srcCols['duration']) ? (string)($r['duration'] ?? '') : '',
            ':stip'   => isset($srcCols['stipend']) ? (float)($r['stipend'] ?? 0) : 0,
            ':status' => $status,
        ]);
        $newId = (int)$pdo->lastInsertId();
        $existingPostings[$key] = $newId;
        $postingMap[(int)$r['id']] = $newId;
        $inserted++;
    }
    info("merged job postings into company_internships ({$inserted} inserted, " . count($postingMap) . " mapped)");
}

// --------------------------------------------------------------------------
// Step 5 — internships (student-tracked records) shape check
// --------------------------------------------------------------------------

$ic = columnsOf($pdo, $targetDb, 'internships');
foreach ([
    'work_mode' => "ENUM('remote','onsite','hybrid') DEFAULT 'onsite'",
    'supervisor_name' => 'VARCHAR(150)',
    'supervisor_email' => 'VARCHAR(150)',
    'offer_letter_path' => 'VARCHAR(255)',
    'resume_path' => 'VARCHAR(255)',
    'cover_letter_path' => 'VARCHAR(255)',
    'transcripts_path' => 'VARCHAR(255)',
    'notes' => 'TEXT',
] as $name => $def) {
    $ic = ensureCol($pdo, $targetDb, 'internships', $name, $def, $ic);
}

// --------------------------------------------------------------------------
// Step 6 — applications (remap student_id by email, posting by map)
// --------------------------------------------------------------------------

// If a legacy main-DB applications table exists with a different layout
// (pre-unification schema), preserve it under applications_legacy rather than
// silently dropping data; the unified applications table is created fresh.
$appCols = columnsOf($pdo, $targetDb, 'applications');
if ($appCols && !isset($appCols['company_internship_id'])
    && !tableExists($pdo, $targetDb, 'applications_legacy')) {
    useDb($pdo, $targetDb);
    $pdo->exec("RENAME TABLE applications TO applications_legacy");
    echo "  + preserved legacy main applications table as applications_legacy\n";
    $appCols = [];
}

$pdo->exec("CREATE TABLE IF NOT EXISTS applications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  company_internship_id INT NOT NULL,
  student_id INT DEFAULT NULL,
  cover_letter TEXT,
  resume TEXT,
  status ENUM('pending','under_review','accepted','rejected') DEFAULT 'pending',
  notes TEXT,
  applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_internship (company_internship_id),
  INDEX idx_student (student_id),
  INDEX idx_status (status)
) ENGINE=InnoDB");

$appStatusMap = ['pending' => 'pending', 'under_review' => 'under_review', 'under review' => 'under_review',
                 'accepted' => 'accepted', 'rejected' => 'rejected', 'applied' => 'pending',
                 '' => 'pending'];
if (tableExists($pdo, $companyDb, 'applications')) {
    useDb($pdo, $targetDb);
    // Drop migration artifacts that point at postings removed by the dedupe above.
    $pdo->exec("DELETE a FROM applications a
        LEFT JOIN company_internships c ON c.id = a.company_internship_id
        WHERE c.id IS NULL");
    // Existing applications keyed by (posting, student) for idempotency.
    $existingApps = [];
    foreach ($pdo->query("SELECT company_internship_id, student_id FROM applications")->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existingApps[$row['company_internship_id'] . '|' . ($row['student_id'] ?? 'anon')] = true;
    }
    $srcCols = columnsOf($pdo, $companyDb, 'applications');
    $migrated = 0;
    foreach ($pdo->query("SELECT * FROM `{$companyDb}`.applications")->fetchAll(PDO::FETCH_ASSOC) as $r) {
        $newPosting = isset($r['internship_id']) ? ($postingMap[(int)$r['internship_id']] ?? null) : null;
        if ($newPosting === null) continue;
        $studentEmail = isset($srcCols['student_email']) ? (string)($r['student_email'] ?? '') : '';
        $sid = null;
        if ($studentEmail !== '') {
            $sid = lookupEmail($pdo, $targetDb, $studentEmail);
        }
        $appKey = $newPosting . '|' . ($sid ?? 'anon');
        if (isset($existingApps[$appKey])) continue; // already migrated
        $existingApps[$appKey] = true;
        $status = $appStatusMap[strtolower(trim((string)($r['status'] ?? '')))] ?? 'pending';
        $notes = '';
        if (isset($srcCols['student_name']) && $r['student_name'] !== null && $sid === null) {
            $notes .= '(legacy applicant: ' . $r['student_name'] . ')';
        }
        if (isset($srcCols['notes']) && $r['notes'] !== null) {
            $notes .= ' ' . $r['notes'];
        }
        $st = $pdo->prepare("INSERT INTO applications (company_internship_id, student_id, cover_letter, resume, status, notes)
            VALUES (:pi, :sid, :cl, :rs, :status, :notes)");
        $st->execute([
            ':pi'     => $newPosting,
            ':sid'    => $sid,
            ':cl'     => isset($srcCols['cover_letter']) ? (string)($r['cover_letter'] ?? '') : '',
            ':rs'     => isset($srcCols['resume']) ? (string)($r['resume'] ?? '') : '',
            ':status' => $status,
            ':notes'  => trim($notes),
        ]);
        $migrated++;
    }
    info("merged {$migrated} applications into applications table");
}

// --------------------------------------------------------------------------
// Step 7 — ensure all remaining unified tables exist
// --------------------------------------------------------------------------

$pdo->exec("CREATE TABLE IF NOT EXISTS progress_logs (
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
  INDEX idx_internship (internship_id)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS documents (
  id INT AUTO_INCREMENT PRIMARY KEY,
  internship_id INT NOT NULL,
  doc_type ENUM('offer_letter','nda','report','certificate','other') DEFAULT 'other',
  filename VARCHAR(255) NOT NULL,
  original_name VARCHAR(255) NOT NULL,
  file_size INT,
  uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_internship (internship_id)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS activity_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  action VARCHAR(100) NOT NULL,
  entity_type VARCHAR(50),
  entity_id INT,
  ip_address VARCHAR(45),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_created (created_at)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS login_rate_limits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  rate_key VARCHAR(100) NOT NULL,
  blocked_until INT UNSIGNED NOT NULL DEFAULT 0,
  attempts TEXT NOT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE INDEX uq_rate_key (rate_key)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS settings (
  id INT AUTO_INCREMENT PRIMARY KEY,
  key_name VARCHAR(100) NOT NULL UNIQUE,
  value_text TEXT,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_key (key_name)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS password_resets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  email VARCHAR(150) NOT NULL,
  token_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at DATETIME NOT NULL,
  used_at TIMESTAMP NULL,
  INDEX idx_email (email),
  INDEX idx_expires (expires_at),
  INDEX idx_user (user_id)
) ENGINE=InnoDB");

$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  title VARCHAR(200) NOT NULL,
  message TEXT,
  type ENUM('info','warning','error','success') DEFAULT 'info',
  channel ENUM('in_app','email','both') DEFAULT 'in_app',
  is_read TINYINT(1) DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user (user_id),
  INDEX idx_read (is_read)
) ENGINE=InnoDB");

// --------------------------------------------------------------------------
// Step 8 — report
// --------------------------------------------------------------------------

info("--- final counts in {$targetDb} ---");
foreach (['users','companies','internships','company_internships','applications',
          'progress_logs','documents','activity_log','settings','password_resets',
          'notifications','login_rate_limits'] as $t) {
    printf("  %-20s %d rows\n", $t, tableCount($pdo, $targetDb, $t));
}
printf("  %-20s %d rows (left untouched, backup)\n", $companyDb . '.companies', tableCount($pdo, $companyDb, 'companies'));
info("DONE. Legacy databases were NOT dropped.");
