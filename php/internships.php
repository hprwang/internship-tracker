<?php
/**
 * Internships API Handler
 */
session_start();
require_once 'config.php';

header('Content-Type: application/json');
$user = requireAuth();
$db   = Database::getConnection();

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

switch ($action) {
    case 'list':        getInternships($user, $db);       break;
    case 'get':         getInternship($user, $db);        break;
    case 'create':      createInternship($user, $db);     break;
    case 'update':      updateInternship($user, $db);     break;
    case 'delete':      deleteInternship($user, $db);     break;
    case 'stats':       getStats($user, $db);             break;
    case 'log_add':     addProgressLog($user, $db);       break;
    case 'log_list':    getProgressLogs($user, $db);      break;
    case 'companies':   getCompanies($db);                break;
    case 'add_company': addCompany($user, $db);           break;
    default:            jsonResponse(false, 'Unknown action.');
}

// ── GET LIST ──────────────────────────────────────────────────────────────────
function getInternships(array $user, PDO $db): void {
    $whereUser = $user['role'] === 'admin' ? '' : 'WHERE i.student_id = :uid';
    $params = $user['role'] === 'admin' ? [] : [':uid' => $user['id']];

    $status = $_GET['status'] ?? '';
    if ($status) {
        $whereUser = $whereUser ? $whereUser . ' AND i.status = :status' : 'WHERE i.status = :status';
        $params[':status'] = $status;
    }

    $stmt = $db->prepare("
        SELECT i.*, c.name AS company_name, c.industry, c.location AS company_location,
               u.full_name AS student_name, u.email AS student_email
        FROM internships i
        JOIN companies c ON i.company_id = c.id
        JOIN users u ON i.student_id = u.id
        $whereUser
        ORDER BY i.created_at DESC
    ");
    $stmt->execute($params);
    jsonResponse(true, '', ['internships' => $stmt->fetchAll()]);
}

// ── GET SINGLE ────────────────────────────────────────────────────────────────
function getInternship(array $user, PDO $db): void {
    $id = (int)($_GET['id'] ?? 0);
    $stmt = $db->prepare("
        SELECT i.*, c.name AS company_name, c.industry, c.website, c.location AS company_location,
               c.contact_person, c.contact_email,
               u.full_name AS student_name, u.email AS student_email
        FROM internships i
        JOIN companies c ON i.company_id = c.id
        JOIN users u ON i.student_id = u.id
        WHERE i.id = ?
        " . ($user['role'] !== 'admin' ? ' AND i.student_id = ?' : '')
    );
    $params = [$id];
    if ($user['role'] !== 'admin') $params[] = $user['id'];
    $stmt->execute($params);
    $data = $stmt->fetch();
    if (!$data) jsonResponse(false, 'Internship not found.');
    jsonResponse(true, '', ['internship' => $data]);
}

// ── CREATE ────────────────────────────────────────────────────────────────────
function createInternship(array $user, PDO $db): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid token.');

    $required = ['company_id','title','start_date','end_date','status','work_mode'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) jsonResponse(false, "Field '$field' is required.");
    }

    $studentId = $user['role'] === 'admin' && !empty($_POST['student_id'])
        ? (int)$_POST['student_id'] : $user['id'];

    $stmt = $db->prepare("
        INSERT INTO internships
            (student_id, company_id, title, description, start_date, end_date,
             status, stipend, work_mode, supervisor_name, supervisor_email, notes)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $studentId,
        (int)$_POST['company_id'],
        trim($_POST['title']),
        trim($_POST['description'] ?? ''),
        $_POST['start_date'],
        $_POST['end_date'],
        $_POST['status'],
        (float)($_POST['stipend'] ?? 0),
        $_POST['work_mode'],
        trim($_POST['supervisor_name'] ?? ''),
        trim($_POST['supervisor_email'] ?? ''),
        trim($_POST['notes'] ?? ''),
    ]);
    $newId = (int)$db->lastInsertId();
    logActivity($user['id'], 'create_internship', 'internship', $newId);
    jsonResponse(true, 'Internship added successfully!', ['id' => $newId]);
}

// ── UPDATE ────────────────────────────────────────────────────────────────────
function updateInternship(array $user, PDO $db): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid token.');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) jsonResponse(false, 'Invalid ID.');

    // Ownership check
    if ($user['role'] !== 'admin') {
        $check = $db->prepare("SELECT id FROM internships WHERE id = ? AND student_id = ?");
        $check->execute([$id, $user['id']]);
        if (!$check->fetch()) jsonResponse(false, 'Access denied.');
    }

    $stmt = $db->prepare("
        UPDATE internships SET
            company_id=?, title=?, description=?, start_date=?, end_date=?,
            status=?, stipend=?, work_mode=?, supervisor_name=?, supervisor_email=?, notes=?
        WHERE id=?
    ");
    $stmt->execute([
        (int)$_POST['company_id'],
        trim($_POST['title']),
        trim($_POST['description'] ?? ''),
        $_POST['start_date'],
        $_POST['end_date'],
        $_POST['status'],
        (float)($_POST['stipend'] ?? 0),
        $_POST['work_mode'],
        trim($_POST['supervisor_name'] ?? ''),
        trim($_POST['supervisor_email'] ?? ''),
        trim($_POST['notes'] ?? ''),
        $id,
    ]);
    logActivity($user['id'], 'update_internship', 'internship', $id);
    jsonResponse(true, 'Internship updated successfully!');
}

// ── DELETE ────────────────────────────────────────────────────────────────────
function deleteInternship(array $user, PDO $db): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid token.');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) jsonResponse(false, 'Invalid ID.');

    if ($user['role'] !== 'admin') {
        $check = $db->prepare("SELECT id FROM internships WHERE id = ? AND student_id = ?");
        $check->execute([$id, $user['id']]);
        if (!$check->fetch()) jsonResponse(false, 'Access denied.');
    }

    $db->prepare("DELETE FROM internships WHERE id = ?")->execute([$id]);
    logActivity($user['id'], 'delete_internship', 'internship', $id);
    jsonResponse(true, 'Internship deleted.');
}

// ── STATS ─────────────────────────────────────────────────────────────────────
function getStats(array $user, PDO $db): void {
    $filter = $user['role'] === 'admin' ? '' : 'WHERE student_id = ' . $user['id'];

    $total = $db->query("SELECT COUNT(*) FROM internships $filter")->fetchColumn();
    $byStatus = $db->query("SELECT status, COUNT(*) as cnt FROM internships $filter GROUP BY status")->fetchAll();
    $recent = $db->query("
        SELECT i.title, c.name AS company, i.status, i.start_date
        FROM internships i JOIN companies c ON i.company_id=c.id
        $filter ORDER BY i.created_at DESC LIMIT 5
    ")->fetchAll();

    jsonResponse(true, '', ['total' => $total, 'by_status' => $byStatus, 'recent' => $recent]);
}

// ── PROGRESS LOGS ─────────────────────────────────────────────────────────────
function addProgressLog(array $user, PDO $db): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid token.');
    $internshipId = (int)($_POST['internship_id'] ?? 0);

    // Verify ownership
    $check = $db->prepare("SELECT id FROM internships WHERE id = ? AND student_id = ?");
    $check->execute([$internshipId, $user['id']]);
    if (!$check->fetch()) jsonResponse(false, 'Access denied.');

    // Get next week number
    $weekStmt = $db->prepare("SELECT COALESCE(MAX(week_number),0)+1 FROM progress_logs WHERE internship_id=?");
    $weekStmt->execute([$internshipId]);
    $weekNum = (int)$weekStmt->fetchColumn();

    $stmt = $db->prepare("
        INSERT INTO progress_logs (internship_id, week_number, log_date, tasks_completed, skills_learned, challenges, hours_worked, rating)
        VALUES (?,?,?,?,?,?,?,?)
    ");
    $stmt->execute([
        $internshipId, $weekNum, $_POST['log_date'] ?? date('Y-m-d'),
        trim($_POST['tasks_completed'] ?? ''),
        trim($_POST['skills_learned'] ?? ''),
        trim($_POST['challenges'] ?? ''),
        (float)($_POST['hours_worked'] ?? 0),
        (int)($_POST['rating'] ?? 3),
    ]);
    jsonResponse(true, 'Progress log saved!', ['week' => $weekNum]);
}

function getProgressLogs(array $user, PDO $db): void {
    $id = (int)($_GET['internship_id'] ?? 0);
    if ($id <= 0) jsonResponse(false, 'Invalid internship id.');

    // Students can only view logs for their own internships; admins can view all.
    if ($user['role'] === 'admin') {
        $stmt = $db->prepare("
            SELECT * FROM progress_logs
            WHERE internship_id = ?
            ORDER BY week_number ASC
        ");
        $stmt->execute([$id]);
    } else {
        $stmt = $db->prepare("
            SELECT pl.*
            FROM progress_logs pl
            JOIN internships i ON pl.internship_id = i.id
            WHERE pl.internship_id = ?
              AND i.student_id = ?
            ORDER BY pl.week_number ASC
        ");
        $stmt->execute([$id, $user['id']]);
    }

    jsonResponse(true, '', ['logs' => $stmt->fetchAll()]);
}

// ── COMPANIES ─────────────────────────────────────────────────────────────────
function getCompanies(PDO $db): void {
    $rows = $db->query("SELECT id, name, industry, location FROM companies ORDER BY name")->fetchAll();
    jsonResponse(true, '', ['companies' => $rows]);
}

function addCompany(array $user, PDO $db): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid token.');
    if (empty($_POST['name'])) jsonResponse(false, 'Company name required.');

    $stmt = $db->prepare("INSERT INTO companies (name, industry, website, location, contact_person, contact_email, contact_phone) VALUES (?,?,?,?,?,?,?)");
    $stmt->execute([
        trim($_POST['name']),
        trim($_POST['industry'] ?? ''),
        trim($_POST['website'] ?? ''),
        trim($_POST['location'] ?? ''),
        trim($_POST['contact_person'] ?? ''),
        trim($_POST['contact_email'] ?? ''),
        trim($_POST['contact_phone'] ?? ''),
    ]);
    jsonResponse(true, 'Company added!', ['id' => $db->lastInsertId()]);
}
