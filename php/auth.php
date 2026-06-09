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
    default:
        jsonResponse(false, 'Invalid action.');
}

function handleLogin(): void {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $csrf     = $_POST['csrf_token'] ?? '';

    if (!verifyCSRF($csrf)) jsonResponse(false, 'Invalid request token.');
    if ($username === '') jsonResponse(false, 'Username is required.');
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) jsonResponse(false, 'Invalid username format.');
    if (strlen($password) < 6) jsonResponse(false, 'Password too short.');

    // Rate limiting per IP — 5 attempts per 60 seconds (1 minute)
    $rateKey = 'login_' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!checkRateLimit($rateKey, 5, 60)) {
        jsonResponse(false, 'Too many login attempts. Please wait 1 minute.');
    }

    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT id, username, email, password_hash, role, full_name, is_active FROM users WHERE username = ? LIMIT 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password_hash'])) {
        jsonResponse(false, 'Invalid username or password.');
    }
    if (!$user['is_active']) {
        jsonResponse(false, 'Account is deactivated. Contact administrator.');
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
    $_SESSION['user'] = $sessionUser;

    // Update last_login
    $db->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
    logActivity($user['id'], 'login');

    jsonResponse(true, 'Login successful.', ['user' => $sessionUser, 'redirect' => 'dashboard.php']);
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

    if (!verifyCSRF($csrf)) jsonResponse(false, 'Invalid request token.');

    // Validation
    if (strlen($fullName) < 2) jsonResponse(false, 'Full name must be at least 2 characters.');
    if (!preg_match('/^[a-zA-Z0-9_]{3,30}$/', $username)) jsonResponse(false, 'Username: 3-30 alphanumeric chars only.');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse(false, 'Invalid email address.');
    if (strlen($password) < 8) jsonResponse(false, 'Password must be at least 8 characters.');
    if (!preg_match('/[A-Z]/', $password)) jsonResponse(false, 'Password must contain an uppercase letter.');
    if (!preg_match('/[0-9]/', $password)) jsonResponse(false, 'Password must contain a number.');

    $db = Database::getConnection();

    // Check uniqueness
    $check = $db->prepare("SELECT id FROM users WHERE email = ? OR username = ?");
    $check->execute([$email, $username]);
    if ($check->fetch()) jsonResponse(false, 'Email or username already in use.');

    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    $stmt = $db->prepare("INSERT INTO users (username, email, password_hash, role, full_name) VALUES (?, ?, ?, 'student', ?)");
    $stmt->execute([$username, $email, $hash, $fullName]);
    $newId = $db->lastInsertId();

    logActivity((int)$newId, 'register');
    jsonResponse(true, 'Account created! You can now log in.');
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

    if ($user) {
        try {
            $tokenPlain = bin2hex(random_bytes(32));
            $tokenHash  = password_hash($tokenPlain, PASSWORD_BCRYPT, ['cost' => 10]);
            $expiresAt  = (new DateTime('+1 hour'))->format('Y-m-d H:i:s');

            $ins = $db->prepare("INSERT INTO password_resets (user_id, email, token_hash, expires_at) VALUES (?,?,?,?)");
            $ins->execute([(int)$user['id'], $email, $tokenHash, $expiresAt]);

            // Build reset URL (use a safe relative URL to avoid invalid/expired link due to wrong app root)
            $resetUrl = 'reset_password.php?token=' . urlencode($tokenPlain)
                        . '&email=' . urlencode($email);

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
