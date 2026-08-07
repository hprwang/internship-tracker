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
    case 'browse_list': browseCompanyInternships($user); break;
    case 'browse_apply': applyToCompanyInternship($user); break;
    case 'my_applications': getMyApplications($user); break;
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

    // Fallback for admin logins from the student page (admins may live in admin_users)
    if (!$data && in_array($user['role'] ?? '', ['admin', 'super_admin'], true)) {
        $stmt = $db->prepare("
            SELECT i.*, c.name AS company_name, c.industry, c.website, c.location AS company_location,
                   c.contact_person, c.contact_email,
                   u.full_name AS student_name, u.email AS student_email
            FROM internships i
            JOIN companies c ON i.company_id = c.id
            JOIN users u ON i.student_id = u.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $data = $stmt->fetch();
    }
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
        jsonResponse(false, 'Failed to save internship. Please check your input and try again.');
    }
}

// ── File Upload Helper ─────────────────────────────────────────────────
function handleFileUpload(array $file, string $uploadDir, int $userId, string $type): string {
    if ($file['error'] !== UPLOAD_ERR_OK || empty($file['name'])) return '';

    // Whitelist by extension AND by detected MIME type.
    // Never trust the client-supplied $_FILES['type'] value.
    $allowedExts  = ['pdf', 'doc', 'docx'];
    $allowedMimes = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ];

    $origName = basename($file['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

    // Only plain document extensions are allowed
    if (!in_array($ext, $allowedExts, true)) return '';
    // Reject disguised executable names such as resume.pdf.php
    if (preg_match('/\.(php|phtml|phar|pl|py|cgi|asp|aspx|jsp|sh|exe|bat|cmd)$/i', $origName)) return '';

    if ($file['size'] <= 0 || $file['size'] > MAX_FILE_SIZE) return '';

    // Verify the actual file content, not the client-reported type
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->file($file['tmp_name']);
    if (!in_array($detectedMime, $allowedMimes, true)) return '';

    // Store under a random, non-guessable name with a whitelisted extension
    $filename = $userId . '_' . $type . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
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
    $isAdmin = in_array($user['role'] ?? '', ['admin', 'super_admin'], true);
    $params  = $isAdmin ? [] : [':uid' => $user['id']];
    $filter  = $isAdmin ? '' : 'WHERE student_id = :uid';

    $totalStmt = $db->prepare("SELECT COUNT(*) FROM internships $filter");
    $totalStmt->execute($params);
    $total = $totalStmt->fetchColumn();

    $statusStmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM internships $filter GROUP BY status");
    $statusStmt->execute($params);
    $byStatus = $statusStmt->fetchAll();

    $recentStmt = $db->prepare("
        SELECT i.title, c.name AS company, i.status, i.start_date
        FROM internships i JOIN companies c ON i.company_id=c.id
        $filter ORDER BY i.created_at DESC LIMIT 5
    ");
    $recentStmt->execute($params);
    $recent = $recentStmt->fetchAll();

    jsonResponse(true, '', ['total' => $total, 'by_status' => $byStatus, 'recent' => $recent]);
}

// ── PROGRESS LOGS ─────────────────────────────────────────────────────────────
function addProgressLog(array $user, PDO $db): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid token.');
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
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid token.');
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
    $stmt = $db->query("SELECT * FROM companies ORDER BY name");
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
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid token.');
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

// ── BROWSE COMPANY INTERNSHIPS (student side) ────────────────────────────────
function browseCompanyInternships(array $user): void {
    $companyDb = Database::getCompanyConnection();
    ensureCompanySchema($companyDb);

    // Internships the current student has already applied to
    $applied = [];
    try {
        $aStmt = $companyDb->prepare("SELECT internship_id FROM applications WHERE student_id = ?");
        $aStmt->execute([(int)$user['id']]);
        while ($row = $aStmt->fetch()) {
            $applied[(int)$row['internship_id']] = true;
        }
    } catch (Exception $e) {
        error_log("browseCompanyInternships applied query failed: " . $e->getMessage());
    }

    $stmt = $companyDb->prepare("
        SELECT i.*, c.name AS company_name, c.industry AS company_industry,
               c.location AS company_location, c.website AS company_website
        FROM internships i
        JOIN companies c ON i.company_id = c.id
        WHERE i.status = 'active'
        ORDER BY i.created_at DESC
    ");
    $stmt->execute();
    $internships = $stmt->fetchAll();

    foreach ($internships as &$int) {
        $int['applied'] = isset($applied[(int)$int['id']]);
        $int['stipend'] = (float)($int['stipend'] ?? 0);
    }
    unset($int);

    jsonResponse(true, '', ['internships' => $internships]);
}

function applyToCompanyInternship(array $user): void {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) jsonResponse(false, 'Invalid token.');

    $internshipId = (int)($_POST['internship_id'] ?? 0);
    if (!$internshipId) jsonResponse(false, 'Internship ID required.');

    $coverLetter = trim($_POST['cover_letter'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    $companyDb = Database::getCompanyConnection();
    ensureCompanySchema($companyDb);

    // Internship must exist and be active
    $stmt = $companyDb->prepare("SELECT id FROM internships WHERE id = ? AND status = 'active'");
    $stmt->execute([$internshipId]);
    if (!$stmt->fetch()) jsonResponse(false, 'Internship not found or no longer accepting applications.');

    // Prevent duplicate applications
    $dup = $companyDb->prepare("SELECT id FROM applications WHERE internship_id = ? AND student_id = ?");
    $dup->execute([$internshipId, (int)$user['id']]);
    if ($dup->fetch()) jsonResponse(false, 'You have already applied to this internship.');

    // Optional resume upload (PDF/DOC/DOCX)
    $resumePath = '';
    if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK && !empty($_FILES['resume']['name'])) {
        $uploadDir = dirname(__DIR__) . '/uploads/internships/';
        if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
        $resumePath = handleFileUpload($_FILES['resume'], $uploadDir, (int)$user['id'], 'application_resume');
        if (!$resumePath) jsonResponse(false, 'Resume upload failed. Use PDF, DOC, or DOCX (max 5MB).');
    }

    try {
        $ins = $companyDb->prepare("
            INSERT INTO applications (internship_id, student_id, student_name, student_email,
                                      student_phone, student_resume, cover_letter, status)
            VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')
        ");
        $ins->execute([
            $internshipId,
            (int)$user['id'],
            trim($user['full_name'] ?? ''),
            trim($user['email'] ?? ''),
            $phone,
            $resumePath,
            $coverLetter,
        ]);
        logActivity((int)$user['id'], 'company_apply', 'internship', $internshipId);
        jsonResponse(true, 'Application submitted! The company will review it shortly.');
    } catch (Exception $e) {
        error_log("applyToCompanyInternship failed: " . $e->getMessage());
        jsonResponse(false, 'Failed to submit application. Please try again.');
    }
}

function getMyApplications(array $user): void {
    $companyDb = Database::getCompanyConnection();
    ensureCompanySchema($companyDb);

    $stmt = $companyDb->prepare("
        SELECT a.*, i.title AS internship_title, i.location AS internship_location,
               i.stipend, c.name AS company_name
        FROM applications a
        JOIN internships i ON a.internship_id = i.id
        JOIN companies c ON i.company_id = c.id
        WHERE a.student_id = ?
        ORDER BY a.applied_at DESC
    ");
    $stmt->execute([(int)$user['id']]);
    $apps = $stmt->fetchAll();

    foreach ($apps as &$app) {
        $app['stipend'] = (float)($app['stipend'] ?? 0);
    }
    unset($app);

    jsonResponse(true, '', ['applications' => $apps]);
}