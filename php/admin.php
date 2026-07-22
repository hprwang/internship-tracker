<?php
/**
 * Admin API Handler
 */
session_start();
require_once 'config.php';

header('Content-Type: application/json');
$action = $_GET['action'] ?? $_POST['action'] ?? '';

$user = $_SESSION['user'] ?? null;
if (!$user || ($user['role'] ?? '') !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Admin access required.']);
    exit;
}
$db = Database::getConnection();

switch ($action) {
    // Students
    case 'list_students':
        $stmt = $db->query("
            SELECT u.id, u.username, u.email, u.full_name, u.is_active, u.created_at,
                   (SELECT COUNT(*) FROM internships WHERE student_id = u.id) as internship_count
            FROM users u WHERE u.role = 'student' ORDER BY u.created_at DESC
        ");
        echo json_encode(['success' => true, 'students' => $stmt->fetchAll()]);
        break;

    case 'add_student':
        addUser('student');
        break;

    case 'edit_student':
        updateUser();
        break;

    case 'delete_student':
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("DELETE FROM users WHERE id = ? AND role = 'student'")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Student deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        }
        break;

    case 'toggle_student_status':
        $id = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0);
        if ($id) {
            $db->prepare("UPDATE users SET is_active = ? WHERE id = ? AND role = 'student'")->execute([$status, $id]);
            echo json_encode(['success' => true, 'message' => $status ? 'Student activated.' : 'Student deactivated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        }
        break;

    // Companies
    case 'list_companies':
        $stmt = $db->query("SELECT * FROM companies ORDER BY name");
        echo json_encode(['success' => true, 'companies' => $stmt->fetchAll()]);
        break;

    case 'add_company':
        $name = trim($_POST['name'] ?? '');
        if (!$name) {
            echo json_encode(['success' => false, 'message' => 'Company name required.']);
            break;
        }
        // Check for duplicate
        $check = $db->prepare("SELECT id FROM companies WHERE name = ?");
        $check->execute([$name]);
        if ($check->fetch()) {
            echo json_encode(['success' => false, 'message' => 'Company already exists.']);
            break;
        }
        $stmt = $db->prepare("INSERT INTO companies (name, industry, website, location, contact_person, contact_email) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $name,
            trim($_POST['industry'] ?? ''),
            trim($_POST['website'] ?? ''),
            trim($_POST['location'] ?? ''),
            trim($_POST['contact_person'] ?? ''),
            trim($_POST['contact_email'] ?? '')
        ]);
        logActivity($user['id'], 'add_company', 'companies', $db->lastInsertId());
        echo json_encode(['success' => true, 'message' => 'Company added.']);
        break;

    case 'edit_company':
    case 'update_company':
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE companies SET name=?, industry=?, website=?, location=?, contact_person=?, contact_email=?, status=? WHERE id=?");
        $stmt->execute([
            trim($_POST['name'] ?? ''),
            trim($_POST['industry'] ?? ''),
            trim($_POST['website'] ?? ''),
            trim($_POST['location'] ?? ''),
            trim($_POST['contact_person'] ?? ''),
            trim($_POST['contact_email'] ?? ''),
            trim($_POST['status'] ?? 'active'),
            $id
        ]);
        logActivity($user['id'], 'edit_company', 'companies', $id);
        echo json_encode(['success' => true, 'message' => 'Company updated.']);
        break;

    case 'delete_company':
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("DELETE FROM companies WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Company deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        }
        break;

    // Internships
    case 'list_internships':
        $stmt = $db->query("
            SELECT i.*,
                   u.full_name as student_name, u.email as student_email,
                   c.name as company_name
            FROM internships i
            LEFT JOIN users u ON i.student_id = u.id
            LEFT JOIN companies c ON i.company_id = c.id
            ORDER BY i.created_at DESC
        ");
        echo json_encode(['success' => true, 'internships' => $stmt->fetchAll()]);
        break;

    case 'add_internship':
        $studentId = (int)($_POST['student_id'] ?? 0);
        $companyId = (int)($_POST['company_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        if (!$studentId || !$companyId || !$title) {
            echo json_encode(['success' => false, 'message' => 'Student, company, and title required.']);
            break;
        }
        $stmt = $db->prepare("INSERT INTO internships (student_id, company_id, title, description, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, ?, 'applied')");
        $stmt->execute([
            $studentId,
            $companyId,
            $title,
            trim($_POST['description'] ?? ''),
            $_POST['start_date'] ?? date('Y-m-d'),
            $_POST['end_date'] ?? date('Y-m-d', strtotime('+3 months'))
        ]);
        logActivity($user['id'], 'add_internship', 'internships', $db->lastInsertId());
        echo json_encode(['success' => true, 'message' => 'Internship created.']);
        break;

    case 'edit_internship':
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $db->prepare("UPDATE internships SET student_id=?, company_id=?, title=?, description=?, start_date=?, end_date=?, status=? WHERE id=?");
        $stmt->execute([
            (int)$_POST['student_id'],
            (int)$_POST['company_id'],
            trim($_POST['title'] ?? ''),
            trim($_POST['description'] ?? ''),
            $_POST['start_date'],
            $_POST['end_date'],
            $_POST['status'] ?? 'applied',
            $id
        ]);
        logActivity($user['id'], 'edit_internship', 'internships', $id);
        echo json_encode(['success' => true, 'message' => 'Internship updated.']);
        break;

    case 'delete_internship':
        $id = (int)($_POST['id'] ?? 0);
        if ($id) {
            $db->prepare("DELETE FROM internships WHERE id = ?")->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Internship deleted.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
        }
        break;

    case 'update_internship_status':
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $valid = ['applied', 'interview', 'accepted', 'ongoing', 'completed', 'rejected', 'withdrawn'];
        if ($id && in_array($status, $valid)) {
            $db->prepare("UPDATE internships SET status = ? WHERE id = ?")->execute([$status, $id]);
            logActivity($user['id'], 'update_status', 'internships', $id);
            echo json_encode(['success' => true, 'message' => 'Status updated.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        }
        break;

    case 'update_application_status':
        $id = (int)($_POST['id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $valid = ['applied', 'accepted', 'rejected'];
        if ($id && in_array($status, $valid)) {
            $stmt = $db->prepare("SELECT internship_id FROM applications WHERE id = ?");
            $stmt->execute([$id]);
            $app = $stmt->fetch();
            if ($app && $app['internship_id']) {
                $db->prepare("UPDATE internships SET status = ? WHERE id = ?")->execute([$status === 'accepted' ? 'accepted' : $status, $app['internship_id']]);
                $db->prepare("UPDATE applications SET status = ? WHERE id = ?")->execute([$status, $id]);
                logActivity($user['id'], 'update_status', 'applications', $id);
                echo json_encode(['success' => true, 'message' => 'Application ' . $status . '.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Application not found.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid status.']);
        }
        break;

    // Admin Users
    case 'list_admins':
        $stmt = $db->query("SELECT id, username, email, full_name, is_active, last_login FROM users WHERE role = 'admin' ORDER BY created_at DESC");
        echo json_encode(['success' => true, 'admins' => $stmt->fetchAll()]);
        break;

    case 'add_admin':
        addUser('admin');
        break;

    // Activity
    case 'list_activity':
        $stmt = $db->query("
            SELECT al.*, u.full_name as user_name
            FROM activity_log al
            LEFT JOIN users u ON al.user_id = u.id
            ORDER BY al.created_at DESC LIMIT 100
        ");
        echo json_encode(['success' => true, 'activities' => $stmt->fetchAll()]);
        break;

    // Stats
    case 'get_stats':
        $stats = [
            'students' => ($db->query("SELECT COUNT(*) as c FROM users WHERE role = 'student'")->fetch()['c'] ?? 0),
            'companies' => ($db->query("SELECT COUNT(*) as c FROM companies")->fetch()['c'] ?? 0),
            'internships' => ($db->query("SELECT COUNT(*) as c FROM internships")->fetch()['c'] ?? 0),
            'active' => ($db->query("SELECT COUNT(*) as c FROM internships WHERE status = 'ongoing'")->fetch()['c'] ?? 0),
            'completed' => ($db->query("SELECT COUNT(*) as c FROM internships WHERE status = 'completed'")->fetch()['c'] ?? 0),
            'pending' => ($db->query("SELECT COUNT(*) as c FROM internships WHERE status = 'applied'")->fetch()['c'] ?? 0),
        ];
        echo json_encode(['success' => true, 'stats' => $stats]);
        break;

    // Settings
    case 'save_settings':
        $allowed = [
            'site_name', 'site_email', 'site_phone', 'allow_registration', 'require_approval',
            'default_internship_duration', 'max_internships_per_student',
            'email_notifications', 'email_new_application', 'email_status_change',
            'maintenance_mode', 'maintenance_message', 'theme', 'items_per_page',
            'session_timeout', 'max_login_attempts'
        ];
        $saved = 0;
        foreach ($allowed as $key) {
            if (isset($_POST[$key])) {
                $value = $_POST[$key];
                $stmt = $db->prepare("INSERT INTO settings (key_name, value_text) VALUES (?, ?) ON DUPLICATE KEY UPDATE value_text = VALUES(value_text)");
                $stmt->execute([$key, $value]);
                $saved++;
            }
        }
        logActivity($user['id'], 'update_settings', 'settings', 0);
        echo json_encode(['success' => true, 'message' => "Saved $saved settings."]);
        break;

    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action.']);
}

function addUser(string $role): void {
    global $db, $user;
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($csrf)) {
        echo json_encode(['success' => false, 'message' => 'Invalid token.']);
        return;
    }

    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (strlen($fullName) < 2 || strlen($username) < 3 || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 8) {
        echo json_encode(['success' => false, 'message' => 'Invalid input data.']);
        return;
    }

    // Check duplicates
    $check = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $check->execute([$email, $username]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Email or username already exists.']);
        return;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, full_name) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$username, $email, $hash, $role, $fullName]);
    $newId = $db->lastInsertId();

    logActivity($user['id'], 'add_user', 'users', $newId);
    echo json_encode(['success' => true, 'message' => ucfirst($role) . ' added.']);
}

function updateUser(): void {
    global $db, $user;
    $id = (int)($_POST['id'] ?? 0);
    $fullName = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');

    if (!$id || strlen($fullName) < 2) {
        echo json_encode(['success' => false, 'message' => 'Invalid data.']);
        return;
    }

    $stmt = $db->prepare("UPDATE users SET full_name = ?, email = ? WHERE id = ?");
    $stmt->execute([$fullName, $email, $id]);
    logActivity($user['id'], 'update_user', 'users', $id);
    echo json_encode(['success' => true, 'message' => 'User updated.']);
}