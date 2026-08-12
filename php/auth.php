<?php
/**
 * Authentication Handler
 */
session_start();
require_once 'config.php';

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';

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
    case 'get_csrf':
        header('Content-Type: application/json');
        echo json_encode(['token' => generateCSRF()]);
        exit;
    default:
        jsonResponse(false, 'Invalid action.');
}

function handleLogin(): void {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($csrf)) jsonResponse(false, 'Invalid request token.');
    if ($username === '') jsonResponse(false, 'Username is required.');
    if (strlen($password) < 6) jsonResponse(false, 'Password too short.');

    // Brute-force protection: block after repeated failures per username.
    $rateKey = 'login:' . strtolower($username);
    if (isRateLimited($rateKey)) {
        jsonResponse(false, 'Too many failed login attempts. Please try again in 5 minutes.');
    }

    $db = Database::getConnection();
    $isEmail = filter_var($username, FILTER_VALIDATE_EMAIL);
    $col = $isEmail ? 'email' : 'username';
    $stmt = $db->prepare("SELECT id, username, email, password_hash, role, full_name, company_id, is_active
                          FROM users WHERE $col = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        checkRateLimit($rateKey);
        jsonResponse(false, 'Invalid username or password.');
    }
    if ((int)$user['is_active'] !== 1) jsonResponse(false, 'Account is disabled. Contact administrator.');

    // Company accounts are no longer supported (company portal removed).
    if ($user['role'] === 'company') {
        jsonResponse(false, 'Company accounts are no longer supported. Contact the administrator.');
    }

    // Regenerate session ID on login (prevent fixation)
    session_regenerate_id(true);

    $sessionUser = [
        'id' => (int)$user['id'],
        'username' => $user['username'],
        'email' => $user['email'],
        'role' => $user['role'],
        'full_name' => $user['full_name'],
        'company_id' => !empty($user['company_id']) ? (int)$user['company_id'] : null,
    ];
    $_SESSION['user'] = $sessionUser;

    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    logActivity((int)$user['id'], 'login');

    // Check for custom redirect or determine based on role
    $customRedirect = $_POST['redirect_to'] ?? '';
    if ($customRedirect !== '' && isSafeLocalRedirect($customRedirect)) {
        $redirect = $customRedirect;
    } else {
        $redirect = $user['role'] === 'admin' ? 'php/admin_dashboard.php' : 'dashboard.php';
    }
    jsonResponse(true, 'Login successful.', ['user' => $sessionUser, 'redirect' => $redirect]);
}

function handleLogout(): void {
    if (!empty($_SESSION['user'])) {
        logActivity($_SESSION['user']['id'], 'logout');
    }
    $_SESSION = [];
    session_destroy();

    // If GET request, redirect to the landing page; otherwise return JSON
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        header('Location: ' . appBasePathUrl('landing.php'));
        exit;
    }
    jsonResponse(true, 'Logged out successfully.');
}

function handleRegister(): void {
    $fullName = trim($_POST['full_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $csrf     = trim($_POST['csrf_token'] ?? '');

    // Public registration always creates a student account. Admin accounts are
    // only created by the migration/seed script, never via public registration.
    $role = 'student';

    if (!verifyCSRF($csrf)) jsonResponse(false, 'Invalid request token.');

    // Validation
    $confirmPassword = trim($_POST['confirm_password'] ?? '');
    if (strlen($fullName) < 2) jsonResponse(false, 'Full name must be at least 2 characters.');
    if (strlen($username) < 3 || strlen($username) > 100) jsonResponse(false, 'Username must be 3-100 characters.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email address.');
    if (strlen($password) < 8) jsonResponse(false, 'Password must be at least 8 characters.');
    if (!preg_match('/[A-Z]/', $password)) jsonResponse(false, 'Password must contain an uppercase letter.');
    if (!preg_match('/[0-9]/', $password)) jsonResponse(false, 'Password must contain a number.');
    if ($password !== $confirmPassword) {
        jsonResponse(false, 'Passwords do not match.');
    }

    $db = Database::getConnection();
    $newId = null;
    try {
        // Uniqueness check in the unified users table
        $check = $db->prepare("SELECT id, email, username FROM users WHERE email = ? OR username = ?");
        $check->execute([$email, $username]);
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
        $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, full_name, company_id)
                              VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$username, $email, $hash, $role, $fullName, null]);
        $newId = (int)$db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Registration error: " . $e->getMessage());
        jsonResponse(false, 'Registration failed. Please try again.');
    }

    if ($newId !== null) {
        logActivity($newId, 'register');
        notify($newId, 'Welcome to ' . APP_NAME, 'Your account was created successfully.', 'success');
    }

    $message = 'Account created successfully! You can now log in.';
    jsonResponse(true, $message);
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

    // Rate limit per email Ã¢â‚¬â€ 3 requests per 60 seconds
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
            $resetUrl = $scheme . '://' . $host
                      . appBasePathUrl('/reset_password.php?token=' . urlencode($tokenPlain) . '&email=' . urlencode($email));



            $appName    = defined('APP_NAME') ? APP_NAME : 'InternTrack';
            $firstName  = explode(' ', $user['full_name'])[0];
            $subject    = "Reset your {$appName} password";

            // Plain-text body
            $bodyText = "Hi {$firstName},\n\n"
                      . "We received a request to reset the password for your {$appName} account.\n\n"
                      . "Click the link below to choose a new password:\n"
                      . "{$resetUrl}\n\n"
                      . "This link will expire in 1 hour.\n\n"
                      . "If you did not request a password reset, you can safely ignore this email Ã¢â‚¬â€ "
                      . "your password will remain unchanged.\n\n"
                      . "Ã¢â‚¬â€ The {$appName} Team";

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

