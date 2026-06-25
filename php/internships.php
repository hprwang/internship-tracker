<?php
/**
 * Internships API Handler
 */
session_start();
require_once __DIR__ . '/config.php';

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
    case 'log_delete': deleteProgressLog($user, $db);  break;
    case 'log_list':    getProgressLogs($user, $db);      break;
    case 'companies':   getCompanies($db);                break;
    case 'add_company': addCompany($user, $db);           break;
    case 'delete_company': deleteCompany($user, $db);      break;
    case 'get_company': getCompany($user, $db);            break;
    case 'update_company': updateCompany($user, $db);        break;
    case 'test':       jsonResponse(true, 'PHP works! User: ' . $user['email']); break;
    case 'whoami':    jsonResponse(true, '', ['user' => ['id' => $user['id'], 'email' => $user['email'], 'role' => $user['role']]]); break;

    default:            jsonResponse(false, 'Unknown action: ' . $action);
}

// Ensure PHP doesn't fall through without ending after JSON
// (jsonResponse already exits, but keep this file resilient if execution changes).


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
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, 'Invalid token.');
    }

    $required = ['company_id','title','start_date','end_date','status','work_mode'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            jsonResponse(false, "Field '$field' is required.");
        }
    }

    $studentId = $user['role'] === 'admin' && !empty($_POST['student_id'])
        ? (int)$_POST['student_id'] : $user['id'];

    // Handle file uploads
    $resumePath = '';
    $coverLetterPath = '';
    $transcriptsPath = '';

    $uploadDir = dirname(__DIR__) . '/uploads/internships/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Upload resume - check if file was actually uploaded
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK && !empty($_FILES['resume']['name'])) {
        $resumePath = handleFileUpload($_FILES['resume'], $uploadDir, $studentId, 'resume');
        if (!$resumePath) jsonResponse(false, 'Failed to upload resume.');
    }

    // Upload cover letter
    if (isset($_FILES['cover_letter']) && $_FILES['cover_letter']['error'] === UPLOAD_ERR_OK && !empty($_FILES['cover_letter']['name'])) {
        $coverLetterPath = handleFileUpload($_FILES['cover_letter'], $uploadDir, $studentId, 'cover_letter');
        if (!$coverLetterPath) jsonResponse(false, 'Failed to upload cover letter.');
    }

    // Upload transcripts
    if (isset($_FILES['transcripts']) && $_FILES['transcripts']['error'] === UPLOAD_ERR_OK && !empty($_FILES['transcripts']['name'])) {
        $transcriptsPath = handleFileUpload($_FILES['transcripts'], $uploadDir, $studentId, 'transcripts');
        if (!$transcriptsPath) jsonResponse(false, 'Failed to upload transcripts.');
    }

    try {
        $stmt = $db->prepare("
            INSERT INTO internships
                (student_id, company_id, title, description, start_date, end_date,
                 status, stipend, work_mode,
                 resume_path, cover_letter_path, transcripts_path, notes)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
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
            $resumePath,
            $coverLetterPath,
            $transcriptsPath,
            trim($_POST['notes'] ?? ''),
        ]);
        $newId = (int)$db->lastInsertId();
        logActivity($user['id'], 'create_internship', 'internship', $newId);
        jsonResponse(true, 'Internship added successfully!', ['id' => $newId]);
    } catch (Exception $e) {
        error_log("Database error: " . $e->getMessage());
        jsonResponse(false, 'Failed to save internship: ' . $e->getMessage());
    }
}

// ── File Upload Helper ─────────────────────────────────────────────────
function handleFileUpload(array $file, string $uploadDir, int $userId, string $type): string {
    if ($file['error'] !== UPLOAD_ERR_OK) return '';

    $allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
    $maxSize = 10 * 1024 * 1024; // 10MB

    if (!in_array($file['type'], $allowedTypes) && $file['type'] !== '') return '';
    if ($file['size'] > $maxSize) return '';

    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = $userId . '_' . $type . '_' . time() . '.' . $ext;
    $targetPath = $uploadDir . $filename;

    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/internships/' . $filename;
    }
    return '';
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
            status=?, stipend=?, work_mode=?, notes=?
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
    $internshipId = (int)($_POST['internship_id'] ?? 0);
    error_log("log_add: user_id={$user['id']}, internship_id=$internshipId");

    if ($internshipId <= 0) jsonResponse(false, 'Please select an internship.');

    // Verify ownership
    $check = $db->prepare("SELECT id FROM internships WHERE id = ? AND student_id = ?");
    $check->execute([$internshipId, $user['id']]);
    $exists = $check->fetch();

    // If not found by student_id, check if user is admin (can add to any)
    if (!$exists && $user['role'] === 'admin') {
        $check = $db->prepare("SELECT id FROM internships WHERE id = ?");
        $check->execute([$internshipId]);
        $exists = $check->fetch();
    }

    if (!$exists) jsonResponse(false, 'Access denied: internship not found or not owned.');

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

function deleteProgressLog(array $user, PDO $db): void {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) jsonResponse(false, 'Log ID required.');

    // Verify ownership
    $check = $db->prepare("SELECT pl.id FROM progress_logs pl JOIN internships i ON pl.internship_id = i.id WHERE pl.id = ? AND i.student_id = ?");
    $check->execute([$id, $user['id']]);
    if (!$check->fetch()) jsonResponse(false, 'Access denied or not found.');

    $stmt = $db->prepare("DELETE FROM progress_logs WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    jsonResponse(true, 'Progress log deleted.');
}

// ── COMPANIES ─────────────────────────────────────────────────────────────────
function getCompanies(PDO $db): void {
    $stmt = $db->query("SELECT MIN(id) as id, name, MIN(industry) as industry, MIN(location) as location, MIN(website) as website, MIN(contact_person) as contact_person, MIN(contact_email) as contact_email, MIN(contact_phone) as contact_phone FROM companies GROUP BY name ORDER BY name");
    $rows = $stmt->fetchAll();
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

function deleteCompany(array $user, PDO $db): void {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) jsonResponse(false, 'Company ID required.');

    $stmt = $db->prepare("DELETE FROM companies WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    jsonResponse(true, 'Company deleted.');
}

function updateCompany(array $user, PDO $db): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid token.');
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) jsonResponse(false, 'Company ID required.');
    if (empty($_POST['name'])) jsonResponse(false, 'Company name required.');

    $stmt = $db->prepare("UPDATE companies SET name = ?, industry = ?, website = ?, location = ?, contact_person = ?, contact_email = ?, contact_phone = ? WHERE id = ? LIMIT 1");
    $stmt->execute([
        trim($_POST['name']),
        trim($_POST['industry'] ?? ''),
        trim($_POST['website'] ?? ''),
        trim($_POST['location'] ?? ''),
        trim($_POST['contact_person'] ?? ''),
        trim($_POST['contact_email'] ?? ''),
        trim($_POST['contact_phone'] ?? ''),
        $id,
    ]);
    jsonResponse(true, 'Company updated!', ['id' => $id]);
}

function getCompany(array $user, PDO $db): void {
    $id = (int)($_POST['id'] ?? 0);
    if (!$id) jsonResponse(false, 'Company ID required.');

    $stmt = $db->prepare("SELECT * FROM companies WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $company = $stmt->fetch();
    if (!$company) jsonResponse(false, 'Company not found.');

    jsonResponse(true, '', ['company' => $company]);
}
