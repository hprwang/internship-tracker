<?php
/**
 * Authentication Handler
 */
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'login':
        handleLogin();
        break;
    case 'logout':
        handleLogout();
        break;
    case 'register':
        handleRegister();
        break;
    case 'forgot_request':
        handleForgotRequest();
        break;
    case 'forgot_reset':
        handleForgotReset();
        break;
    case 'change_password':
        handleChangePassword();
        break;
    case 'list_company_internships':
        handleListCompanyInternships();
        break;
    case 'update_internship_status':
        handleUpdateInternshipStatus();
        break;
    default:
        jsonResponse(false, 'Invalid action.');
}

function handleLogin(): void {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST['csrf_token'] ?? '';
    $roleHint = $_POST['role_hint'] ?? 'student'; // 'student' or 'admin'

    // Debug log - comment out in production
    error_log("handleLogin called: username=$username, role_hint=$roleHint, csrf=" . substr($csrf, 0, 10) . "...");
    error_log("POST data: " . print_r($_POST, true));

    if (!verifyCSRF($csrf)) jsonResponse(false, 'Invalid request token.');
    if ($username === '') jsonResponse(false, 'Username is required.');
    if (strlen($password) < 6) jsonResponse(false, 'Password too short.');

    // Rate limiting per IP — 5 attempts per 60 seconds (1 minute)
    $rateKey = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!checkRateLimit($rateKey, 5, 60)) {
        jsonResponse(false, 'Too many login attempts. Please wait 1 minute.');
    }

    // Use admin database for admin login, main database for student login
    $roleHintLower = strtolower($roleHint);
    error_log("handleLogin: role_hint = $roleHint (lowercase: $roleHintLower)");
    if ($roleHintLower === 'admin') {
        $db = Database::getAdminConnection();
        // Query admin_users table - handle case where table doesn't exist yet
        try {
            // First check if table exists
            $result = $db->query("SHOW TABLES LIKE 'admin_users'");
            $tables = $result->fetchAll();
            if (count($tables) === 0) {
                error_log("admin_users table does not exist for admin login");
                jsonResponse(false, 'Admin account not found. Please register first.');
            }

            $isEmail = filter_var($username, FILTER_VALIDATE_EMAIL);
            if ($isEmail) {
                $stmt = $db->prepare("SELECT id, username, email, password_hash, role, full_name, is_active FROM admin_users WHERE email = ? LIMIT 1");
                $stmt->execute([$username]);
            } else {
                $stmt = $db->prepare("SELECT id, username, email, password_hash, role, full_name, is_active FROM admin_users WHERE username = ? LIMIT 1");
                $stmt->execute([$username]);
            }
            $user = $stmt->fetch();
            error_log("Admin login query executed, user found: " . ($user ? 'yes' : 'no'));
        } catch (PDOException $e) {
            error_log("Admin login error - admin_users table may not exist: " . $e->getMessage());
            jsonResponse(false, 'Admin account not found. Please register first.');
        }
    } else {
        $db = Database::getConnection();
        // Query users table
        $isEmail = filter_var($username, FILTER_VALIDATE_EMAIL);
        if ($isEmail) {
            $stmt = $db->prepare("SELECT id, username, email, password_hash, role, full_name, is_active FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$username]);
        } else {
            $stmt = $db->prepare("SELECT id, username, email, password_hash, role, full_name, is_active FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
        }
        $user = $stmt->fetch();
    }

    // Debug log
    if (!$user) {
        error_log("User not found: $username (isEmail=" . ($isEmail ? 'true' : 'false') . ")");
        jsonResponse(false, 'Invalid username or password.');
    }
    if (!password_verify($password, $user['password_hash'])) {
        error_log("Password verification failed for: $username");
        jsonResponse(false, 'Invalid username or password.');
    }
    if (!$user['is_active']) {
        jsonResponse(false, 'Account is deactivated. Contact administrator.');
    }

    // Role-based access control: Only allow admins on admin login page, only allow students on student login page
    $userRole = strtolower(trim($user['role']));
    $roleHintLower = strtolower($roleHint);

    // Debug logging
    error_log("Login debug: roleHint='$roleHint' (lower: $roleHintLower), userRole='$userRole'");

    // Role check - now using separate databases so this should work properly
    if ($roleHintLower === 'admin' && $userRole !== 'admin') {
        jsonResponse(false, 'Access denied. This page is for administrators only.');
    }
    if ($roleHintLower === 'student' && $userRole === 'admin') {
        jsonResponse(false, 'Admin accounts cannot log in here. Use the admin login page.');
    }

    // Regenerate session ID on login (prevent fixation)
    session_regenerate_id(true);

    $sessionUser = [
        'id'        => $user['id'],
        'username'  => $user['username'],
        'email'     => $user['email'],
        'role'      => $user['role'],
        'full_name' => $user['full_name'],
    ];
    // Add company_id for admins
    if ($user['role'] === 'admin' && !empty($user['company_id'])) {
        $sessionUser['company_id'] = $user['company_id'];
    }
    $_SESSION['user'] = $sessionUser;

    // Update last_login
    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    logActivity($user['id'], 'login');

    // Fix redirect based on current location
    $currentPath = $_SERVER['REQUEST_URI'] ?? '';
    $isInPhpFolder = strpos($currentPath, '/php/') !== false;

    if ($user['role'] === 'admin') {
        // Admin goes to admin dashboard - use relative path based on current location
        $redirect = $isInPhpFolder ? 'admin_dashboard.php' : 'php/admin_dashboard.php';
    } else {
        // Student goes to student dashboard
        $redirect = 'dashboard.php';
    }
    error_log("Login successful, redirecting to: $redirect");
    jsonResponse(true, 'Login successful.', ['user' => $sessionUser, 'redirect' => $redirect]);
}

function handleLogout(): void {
    if (!empty($_SESSION['user'])) {
        logActivity($_SESSION['user']['id'], 'logout');
    }
    $_SESSION = [];
    session_destroy();
    jsonResponse(true, 'Logged out successfully.');
}

function handleRegister(): void {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST['csrf_token'] ?? '';
    $roleHint = $_POST['role_hint'] ?? 'student'; // 'student' or 'admin' - determines what role can be registered
    $role     = $_POST['role'] ?? null;
    $companyId = $_POST['company_id'] ?? null;

    // Role-based access control: Determine what role can be registered based on the page
    if ($roleHint === 'admin') {
        // Admin registration page - only allow admin registration
        if ($role !== 'admin') {
            $role = 'admin'; // Force admin role for admin registration page
        }
    } else {
        // Student registration page - only allow student registration
        $role = 'student';
    }

    // For admins, company_id is required
    if ($role === 'admin' && empty($companyId)) {
        jsonResponse(false, 'Please select a company.');
    }

    if (!verifyCSRF($csrf)) jsonResponse(false, 'Invalid request token.');

    // Validation
    $confirmPassword = $_POST['confirm_password'] ?? '';
    if (strlen($fullName) < 2) jsonResponse(false, 'Full name must be at least 2 characters.');
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) jsonResponse(false, 'Username must be 3-30 alphanumeric characters only.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email address.');
    if (strlen($password) < 8) jsonResponse(false, 'Password must be at least 8 characters.');
    if (!preg_match('/[A-Z]/', $password)) jsonResponse(false, 'Password must contain an uppercase letter.');
    if (!preg_match('/[0-9]/', $password)) jsonResponse(false, 'Password must contain a number.');
    if ($password !== $confirmPassword) jsonResponse(false, 'Passwords do not match.');

    // Use admin database for admin registration, main database for student registration
    $roleHintLower = strtolower($roleHint);
    try {
        if ($roleHintLower === 'admin') {
            $db = Database::getAdminConnection();

            // Check if admin_users table exists using info query
            $tableExists = false;
            try {
                $result = $db->query("SHOW TABLES LIKE 'admin_users'");
                $tables = $result->fetchAll();
                $tableExists = count($tables) > 0;
                error_log("admin_users table exists in admin DB: " . ($tableExists ? 'yes' : 'no'));
            } catch (PDOException $e) {
                error_log("Error checking for admin_users table: " . $e->getMessage());
                $tableExists = false;
            }

            if (!$tableExists) {
                // Table doesn't exist, create it in main DB
                error_log("admin_users table not found, creating in main DB");
                $db = Database::getConnection();
                $db->exec("CREATE TABLE IF NOT EXISTS admin_users (
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
                ) ENGINE=InnoDB");
            }
            // Check uniqueness in admin_users table
            $check = $db->prepare("SELECT id, email, username FROM admin_users WHERE email = ? OR username = ?");
            $check->execute([$email, $username]);
        } else {
            $db = Database::getConnection();
            // Check uniqueness in users table
            $check = $db->prepare("SELECT id, email, username FROM users WHERE email = ? OR username = ?");
            $check->execute([$email, $username]);
        }
        $existing = $check->fetch();
        if ($existing) {
            if ($existing['email'] === $email && $existing['username'] === $username) {
                jsonResponse(false, 'Email and username already exist.');
            } elseif ($existing['email'] === $email) {
                jsonResponse(false, 'Email already exists. Please use a different email.');
            } else {
                jsonResponse(false, 'Username already exists. Please choose a different username.');
            }
        }

        $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

        // Convert company_id to int or null
        $companyIdInt = !empty($companyId) ? (int)$companyId : null;

        // Insert into appropriate table
        if ($roleHintLower === 'admin') {
            $stmt = $db->prepare("INSERT INTO admin_users (username, email, password_hash, role, full_name, company_id, permissions) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hash, $role, $fullName, $companyIdInt, '{"users": "read", "companies": "read", "internships": "read"}']);
        } else {
            $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, full_name) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$username, $email, $hash, $role, $fullName]);
        }
        $newId = $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        jsonResponse(false, 'Registration failed: ' . $e->getMessage());
    }

    logActivity((int)$newId, 'register');

    // For admin registration, redirect to login page; for student, show message
    $redirect = $role === 'admin' ? 'admin_login.php' : '';
    $message = $role === 'admin'
        ? 'Account created successfully! Redirecting to login...'
        : 'Account created successfully! You can now log in.';
    jsonResponse(true, $message, ['redirect' => $redirect]);
}

/**
 * Forgot password: generate reset token + send email
 */
function handleForgotRequest(): void {
    $email = trim($_POST['email'] ?? '');
    $csrf  = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($csrf)) jsonResponse(false, 'Invalid request token.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email address.');

    $db = Database::getConnection();

    $stmt = $db->prepare("SELECT id, full_name FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Always return the same message to avoid user-enumeration
    $genericMsg = 'If your email is registered, a reset link has been sent. Please check your inbox (and spam folder).';

    // Rate limit per email — 3 requests per 60 seconds
    $rateKey = 'forgot_' . md5(strtolower($email));
    if (!checkRateLimit($rateKey, 3, 60)) {
        jsonResponse(true, $genericMsg);  // Still return success to avoid revealing rate limit
    }

    // Initialize so static analyzers don't report undefined variable
    $resetUrl = '';

    if ($user) {
        try {
            $tokenPlain = bin2hex(random_bytes(32));
            $tokenHash  = password_hash($tokenPlain, PASSWORD_BCRYPT, ['cost' => 10]);
            $expiresAt  = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

            $ins = $db->prepare("INSERT INTO password_resets (user_id, email, token_hash, expires_at) VALUES (?,?,?,?)");
            $ins->execute([(int)$user['id'], $email, $tokenHash, $expiresAt]);

            // Build reset URL dynamically so it works regardless of whether the app is served
            // from http://localhost/ or http://localhost/internship-tracker/
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

            // SCRIPT_NAME for this file is typically /internship-tracker/php/auth.php
            // We want the app root: /internship-tracker/
            $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/php/auth.php')), '/');
            $basePath = $scriptDir === '' ? '' : $scriptDir; // e.g. /internship-tracker/php
            // remove trailing /php segment if present
            if (substr($basePath, -4) === '/php') {
                $basePath = substr($basePath, 0, -4); // /internship-tracker
            }
            $basePath = rtrim($basePath, '/'); // e.g. /internship-tracker

            $resetPath = '/reset_password.php?token=' . urlencode($tokenPlain) . '&email=' . urlencode($email);
            $resetUrl = $scheme . '://' . $host . $basePath . $resetPath;



            $appName    = defined('APP_NAME') ? APP_NAME : 'InternTrack';
            $firstName  = explode(' ', $user['full_name'])[0];
            $subject    = "Reset your {$appName} password";

            // Plain-text body
            $bodyText = "Hi {$firstName},\n\n"
                      . "We received a request to reset the password for your {$appName} account.\n\n"
                      . "Click the link below to choose a new password:\n"
                      . "{$resetUrl}\n\n"
                      . "This link will expire in 1 hour.\n\n"
                      . "If you did not request a password reset, you can safely ignore this email — "
                      . "your password will remain unchanged.\n\n"
                      . "— The {$appName} Team";

            // HTML body
            $bodyHtml = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif'>
  <table width='100%' cellpadding='0' cellspacing='0'>
    <tr><td align='center' style='padding:40px 0'>
      <table width='560' cellpadding='0' cellspacing='0'
             style='background:#ffffff;border-radius:8px;overflow:hidden;
                    box-shadow:0 2px 8px rgba(0,0,0,.08)'>
        <!-- Header -->
        <tr><td style='background:#4f46e5;padding:28px 32px'>
          <h1 style='margin:0;color:#fff;font-size:22px'>{$appName}</h1>
        </td></tr>
        <!-- Body -->
        <tr><td style='padding:32px'>
          <p style='margin:0 0 16px;font-size:16px;color:#111'>Hi <strong>" . htmlspecialchars($firstName, ENT_QUOTES) . "</strong>,</p>
          <p style='margin:0 0 16px;font-size:15px;color:#444'>
            We received a request to reset the password for your <strong>{$appName}</strong> account.
          </p>
          <p style='margin:0 0 24px;font-size:15px;color:#444'>
            Click the button below to choose a new password. This link will expire in <strong>1 hour</strong>.
          </p>
          <!-- CTA button -->
          <table cellpadding='0' cellspacing='0'>
            <tr><td style='background:#4f46e5;border-radius:6px'>
              <a href='" . htmlspecialchars($resetUrl, ENT_QUOTES) . "'
                 style='display:inline-block;padding:14px 32px;color:#fff;font-size:15px;
                        font-weight:bold;text-decoration:none'>
                Reset My Password
              </a>
            </td></tr>
          </table>
          <p style='margin:24px 0 0;font-size:13px;color:#888'>
            Or copy and paste this URL into your browser:<br>
            <a href='" . htmlspecialchars($resetUrl, ENT_QUOTES) . "'
               style='color:#4f46e5;word-break:break-all'>" . htmlspecialchars($resetUrl, ENT_QUOTES) . "</a>
          </p>
          <hr style='margin:28px 0;border:none;border-top:1px solid #eee'>
          <p style='margin:0;font-size:13px;color:#aaa'>
            If you didn&rsquo;t request a password reset, you can safely ignore this email.
          </p>
        </td></tr>
        <!-- Footer -->
        <tr><td style='background:#f9fafb;padding:16px 32px;border-top:1px solid #eee'>
          <p style='margin:0;font-size:12px;color:#bbb;text-align:center'>
            &copy; " . date('Y') . " {$appName}. All rights reserved.
          </p>
        </td></tr>
      </table>
    </td></tr>
  </table>
</body>
</html>";

            $mailOk = sendMail($email, $user['full_name'], $subject, $bodyText, $bodyHtml);
            if ($mailOk) {
                error_log("Password reset email sent successfully to {$email}");
                logActivity((int)$user['id'], 'forgot_password_request');
            } else {
                error_log("Password reset email failed to send to {$email}");
            }
        } catch (Exception $e) {
            error_log("Forgot password error: " . $e->getMessage());
            // Still respond with generic message to avoid leaking information
        }
    }

    jsonResponse(true, $genericMsg);
}

/**
 * Forgot password: apply new password using token
 */
function handleForgotReset(): void {
    $token   = $_POST['token'] ?? '';
    $email   = trim($_POST['email'] ?? '');
    $newPw   = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $csrf    = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($csrf)) jsonResponse(false, 'Invalid request token.');
    if ($token === '' || strlen($token) < 16) jsonResponse(false, 'Invalid token.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email address.');
    if (strlen($newPw) < 8) jsonResponse(false, 'Password must be at least 8 characters.');
    if ($newPw !== $confirm) jsonResponse(false, 'Passwords do not match.');
    if (!preg_match('/[A-Z]/', $newPw)) jsonResponse(false, 'Password must contain an uppercase letter.');
    if (!preg_match('/[0-9]/', $newPw)) jsonResponse(false, 'Password must contain a number.');

    $db = Database::getConnection();

    $stmt = $db->prepare("
        SELECT pr.id, pr.user_id, pr.token_hash, pr.expires_at, pr.used_at
        FROM password_resets pr
        WHERE pr.email = ?
          AND pr.used_at IS NULL
          AND pr.expires_at > NOW()
        ORDER BY pr.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$email]);
    $rows = $stmt->fetchAll();

    $matched = null;
    foreach ($rows as $r) {
        if (password_verify($token, $r['token_hash'])) {
            $matched = $r;
            break;
        }
    }

    if (!$matched) jsonResponse(false, 'Invalid or expired reset link. Please request a new one.');

    $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, (int)$matched['user_id']]);
        $db->prepare("UPDATE password_resets SET used_at = NOW() WHERE id = ?")->execute([(int)$matched['id']]);
        $db->commit();
        logActivity((int)$matched['user_id'], 'reset_password');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, 'Failed to reset password. Please try again.');
    }

    jsonResponse(true, 'Password updated successfully. You can now log in.');
}

function handleChangePassword(): void {
    $user = requireAuth();
    $csrf = $_POST['csrf_token'] ?? '';
    $currentPw = $_POST['current_password'] ?? '';
    $newPw = $_POST['new_password'] ?? '';

    if (!verifyCSRF($csrf)) jsonResponse(false, 'Invalid request token.');
    if (empty($currentPw)) jsonResponse(false, 'Current password is required.');
    if (strlen($newPw) < 8) jsonResponse(false, 'Password must be at least 8 characters.');
    if (!preg_match('/[A-Z]/', $newPw)) jsonResponse(false, 'Password must contain an uppercase letter.');
    if (!preg_match('/[0-9]/', $newPw)) jsonResponse(false, 'Password must contain a number.');

    $db = Database::getConnection();

    $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = ?");
    $stmt->execute([(int)$user['id']]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($currentPw, $row['password_hash'])) {
        jsonResponse(false, 'Current password is incorrect.');
    }

    $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);

    $db->beginTransaction();
    try {
        $db->prepare("UPDATE users SET password_hash = ? WHERE id = ?")->execute([$hash, (int)$user['id']]);
        $db->commit();
        logActivity((int)$user['id'], 'change_password');
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, 'Failed to change password. Please try again.');
    }

    jsonResponse(true, 'Password changed successfully.');
}

/**
 * List internships for a company (company admin view)
 */
function handleListCompanyInternships(): void {
    $user = requireAuth();
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($csrf)) jsonResponse(false, 'Invalid request token.');
    if ($user['role'] !== 'admin') jsonResponse(false, 'Access denied.');

    $companyId = $_POST['company_id'] ?? null;
    if (!$companyId) jsonResponse(false, 'Company ID required.');

    $db = Database::getConnection();

    // Get students from this company
    $stmt = $db->prepare("
        SELECT ui.id, ui.role, ui.start_date, ui.end_date, ui.status, ui.description,
               u.id as student_id, u.full_name as student_name, u.email as student_email
        FROM user_internships ui
        JOIN users u ON ui.user_id = u.id
        WHERE u.company_id = ?
        ORDER BY ui.created_at DESC
    ");
    $stmt->execute([$companyId]);
    $internships = $stmt->fetchAll();

    jsonResponse(true, '', ['internships' => $internships]);
}

/**
 * Update internship status (company admin approval/rejection)
 */
function handleUpdateInternshipStatus(): void {
    $user = requireAuth();
    $csrf = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($csrf)) jsonResponse(false, 'Invalid request token.');
    if ($user['role'] !== 'admin') jsonResponse(false, 'Access denied.');

    $internshipId = (int)($_POST['internship_id'] ?? 0);
    $status = $_POST['status'] ?? '';

    if (!$internshipId) jsonResponse(false, 'Invalid internship ID.');
    if (!in_array($status, ['active', 'rejected', 'pending'])) jsonResponse(false, 'Invalid status.');

    $db = Database::getConnection();

    // Verify the internship belongs to a student in this admin's company
    $stmt = $db->prepare("
        SELECT ui.id FROM user_internships ui
        JOIN users u ON ui.user_id = u.id
        WHERE ui.id = ? AND u.company_id = ?
    ");
    $stmt->execute([$internshipId, $user['company_id'] ?? 0]);
    if (!$stmt->fetch()) {
        jsonResponse(false, 'Internship not found or access denied.');
    }

    $stmt = $db->prepare("UPDATE user_internships SET status = ? WHERE id = ?");
    $stmt->execute([$status, $internshipId]);

    jsonResponse(true, 'Internship ' . $status . '.');
}
