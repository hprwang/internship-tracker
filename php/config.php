<?php
/**
 * Database Configuration - Internship Tracker
 * Secure PDO connection with prepared statements
 */

// Load Composer autoloader (PHPMailer + any future packages)
$composerAutoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($composerAutoload)) {
    require_once $composerAutoload;
}

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'internship_tracker1');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME', 'InternTrack');
define('APP_VERSION', '1.0.0');
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_TYPES', ['application/pdf', 'image/jpeg', 'image/png', 'application/msword',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document']);

// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬ SMTP / Email Settings Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬
// Set USE_SMTP = true to send via SMTP (recommended for production).
// Set USE_SMTP = false to fall back to PHP's built-in mail() (XAMPP local only).
define('USE_SMTP',       true);  // SMTP enabled for Gmail
define('SMTP_HOST',      'smtp.gmail.com');     // e.g. smtp.gmail.com | smtp.office365.com
define('SMTP_PORT',      587);                  // 587 = STARTTLS  |  465 = SSL
define('SMTP_SECURE',    'tls');                // 'tls' (port 587) or 'ssl' (port 465)
define('SMTP_USERNAME',  getenv('SMTP_USERNAME') ?: '');      // Ã¢â€ Â set via env, e.g. your Gmail address
define('SMTP_PASSWORD',  getenv('SMTP_PASSWORD') ?: '');      // Ã¢â€ Â set via env (Gmail App Password, not your login pw)
define('SMTP_FROM_EMAIL',getenv('SMTP_FROM_EMAIL') ?: (getenv('SMTP_USERNAME') ?: 'no-reply@localhost'));
define('SMTP_FROM_NAME', 'InternTrack');
// Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬Ã¢â€â‚¬

/*
 * Session security
 * ini_set() must run only before the session is started; otherwise XAMPP/PHP will warn.
 */
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_strict_mode', 1);
    ini_set('session.cookie_samesite', 'Strict');
    // Only send the session cookie over HTTPS when the site is served over HTTPS
    if (!empty(['HTTPS']) && ['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
}

// Error reporting: log everything, but never display errors to users unless APP_DEBUG=1
error_reporting(E_ALL);
ini_set('display_errors', getenv('APP_DEBUG') === '1' ? '1' : '0');
ini_set('log_errors', '1');

// Create uploads directory for emails and files if it doesn't exist
if (!is_dir(UPLOAD_DIR)) {
    @mkdir(UPLOAD_DIR, 0755, true);
}
$emailsDir = UPLOAD_DIR . 'emails/';
if (!is_dir($emailsDir)) {
    @mkdir($emailsDir, 0755, true);
}

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, $options);
            } catch (PDOException $e) {
                // Never expose raw DB errors to user
                error_log("DB Connection Error: " . $e->getMessage());
                http_response_code(500);
                die(json_encode(['success' => false, 'message' => 'Database connection failed. Please try again later.']));
            }
        }
        return self::$instance;
    }

    // Prevent cloning and unserialization
    private function __clone() {}
    public function __wakeup() { throw new \Exception("Cannot unserialize singleton"); }
}

/**
 * Security helper: sanitize output
 */
function e(string $str): string {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

/**
 * Log user activity
 */
function logActivity(int $userId, string $action, string $entityType = '', int $entityId = 0): void {
    try {
        $db = Database::getConnection();
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $stmt = $db->prepare("INSERT INTO activity_log (user_id, action, entity_type, entity_id, ip_address) VALUES (?,?,?,?,?)");
        $stmt->execute([$userId, $action, $entityType, $entityId, $ip]);
    } catch (Exception $e) {
        error_log("Activity log error: " . $e->getMessage());
    }
}

/**
 * Rate limiting Ã¢â‚¬â€ stored in the login_rate_limits DB table (no temp files, no JSON).
 * The table is created automatically on first use.
 */
function checkRateLimit(string $key, int $maxAttempts = 5, int $windowSeconds = 60): bool {
    try {
        $db  = Database::getConnection();
        $now = time();

        // Ensure the rate-limit table exists
        $db->exec("CREATE TABLE IF NOT EXISTS login_rate_limits (
            id            INT AUTO_INCREMENT PRIMARY KEY,
            rate_key      VARCHAR(100) NOT NULL,
            blocked_until INT UNSIGNED NOT NULL DEFAULT 0,
            attempts      TEXT NOT NULL,
            updated_at    TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE INDEX uq_rate_key (rate_key)
        ) ENGINE=InnoDB");

        // Fetch existing row
        $sel = $db->prepare("SELECT blocked_until, attempts FROM login_rate_limits WHERE rate_key = ? LIMIT 1");
        $sel->execute([$key]);
        $row = $sel->fetch();

        $blockedUntil = (int)($row['blocked_until'] ?? 0);

        // Still blocked?
        if ($blockedUntil > $now) return false;

        // Rebuild attempt timestamps (pipe-separated plain text Ã¢â‚¬â€ no JSON, no files)
        $raw    = ($row && $row['attempts'] !== '') ? $row['attempts'] : '';
        $recent = $raw !== '' ? array_filter(explode('|', $raw), fn($t) => (int)$t > $now - $windowSeconds) : [];
        $recent[] = $now;
        $recent   = array_values($recent);

        $newBlocked  = count($recent) > $maxAttempts ? $now + $windowSeconds : 0;
        $attemptsStr = implode('|', $recent);

        $db->prepare("INSERT INTO login_rate_limits (rate_key, blocked_until, attempts)
                      VALUES (?, ?, ?)
                      ON DUPLICATE KEY UPDATE blocked_until = VALUES(blocked_until), attempts = VALUES(attempts)")
           ->execute([$key, $newBlocked, $attemptsStr]);

        return $newBlocked === 0;

    } catch (Exception $e) {
        error_log("Rate limit DB error: " . $e->getMessage());
        return true; // fail open Ã¢â‚¬â€ a DB hiccup should not lock everyone out
    }
}

/**
 * Send email via native PHP SMTP (no PHPMailer required)
 */
function sendMailViaSMTP(string $to, string $toName, string $subject, string $bodyHtml, string $bodyText): bool {
    try {
        $host = SMTP_HOST;
        $port = SMTP_PORT;
        $username = SMTP_USERNAME;
        $password = SMTP_PASSWORD;
        $from = SMTP_FROM_EMAIL;
        $fromName = SMTP_FROM_NAME;

        // Connect to SMTP server
        $socket = @fsockopen($host, $port, $errno, $errstr, 30);
        if (!$socket) {
            error_log("SMTP Connection failed: $errstr ($errno)");
            return false;
        }

        $response = fgets($socket, 1024);
        if (strpos($response, '220') === false) {
            fclose($socket);
            return false;
        }

        // Send EHLO
        fwrite($socket, "EHLO " . gethostname() . "\r\n");
        $response = fgets($socket, 1024);

        // Start TLS
        fwrite($socket, "STARTTLS\r\n");
        $response = fgets($socket, 1024);
        
        if (!stream_context_set_default(['ssl' => ['allow_self_signed' => true, 'verify_peer' => false, 'verify_peer_name' => false]])) {
            error_log("Failed to set SSL context");
        }
        
        if (!@stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            error_log("TLS negotiation failed");
            fclose($socket);
            return false;
        }

        // Send EHLO again after TLS
        fwrite($socket, "EHLO " . gethostname() . "\r\n");
        $response = fgets($socket, 1024);

        // Authenticate
        fwrite($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 1024);
        
        fwrite($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 1024);
        
        fwrite($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 1024);
        
        if (strpos($response, '235') === false && strpos($response, '250') === false) {
            error_log("SMTP Authentication failed: $response");
            fclose($socket);
            return false;
        }

        // Send email
        fwrite($socket, "MAIL FROM: <" . $from . ">\r\n");
        $response = fgets($socket, 1024);

        fwrite($socket, "RCPT TO: <" . $to . ">\r\n");
        $response = fgets($socket, 1024);

        fwrite($socket, "DATA\r\n");
        $response = fgets($socket, 1024);

        $headers = "From: " . $fromName . " <" . $from . ">\r\n";
        $headers .= "To: " . $toName . " <" . $to . ">\r\n";
        $headers .= "Subject: " . $subject . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "Content-Transfer-Encoding: 8bit\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "\r\n";

        $message = $headers . $bodyHtml;
        fwrite($socket, $message . "\r\n.\r\n");
        $response = fgets($socket, 1024);

        if (strpos($response, '250') === false) {
            error_log("Email send failed: $response");
            fclose($socket);
            return false;
        }

        fwrite($socket, "QUIT\r\n");
        fclose($socket);

        error_log("Email sent via native SMTP to {$to}");
        return true;

    } catch (Exception $e) {
        error_log("SMTP error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email Ã¢â‚¬â€ tries SMTP via PHPMailer when USE_SMTP=true, falls back to mail().
 * For local development, also saves a copy to disk for inspection.
 */
function sendMail(string $toEmail, string $toName, string $subject, string $bodyText, string $bodyHtml = ''): bool {
    $emailsSent = false;
    
    if (defined('USE_SMTP') && USE_SMTP) {
        // Try native SMTP first
        $emailsSent = sendMailViaSMTP($toEmail, $toName, $subject, $bodyHtml ?: $bodyText, $bodyText);
        
        if ($emailsSent) {
            return true;
        }

        // Fallback to PHPMailer if available
        $mailerClass = class_exists('PHPMailer\PHPMailer\PHPMailer')
            ? 'PHPMailer\PHPMailer\PHPMailer'
            : (class_exists('PHPMailer') ? 'PHPMailer' : null);

        if ($mailerClass) {
            try {
                $mail = new $mailerClass(true);
                $mail->isSMTP();
                $mail->Host       = SMTP_HOST;
                $mail->SMTPAuth   = true;
                $mail->Username   = SMTP_USERNAME;
                $mail->Password   = SMTP_PASSWORD;
                $mail->SMTPSecure = SMTP_SECURE;
                $mail->Port       = SMTP_PORT;

                $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
                $mail->addAddress($toEmail, $toName);
                $mail->Subject = $subject;

                if ($bodyHtml !== '') {
                    $mail->isHTML(true);
                    $mail->Body    = $bodyHtml;
                    $mail->AltBody = $bodyText;
                } else {
                    $mail->isHTML(false);
                    $mail->Body = $bodyText;
                }

                $mail->send();
                error_log("Email sent via SMTP to {$toEmail}");
                return true;
            } catch (Exception $ex) {
                error_log("PHPMailer error: " . $ex->getMessage());
            }
        } else {
            error_log("PHPMailer not found, attempting native SMTP");
        }
    }

    // Fallback: PHP built-in mail()
    if (!$emailsSent) {
        $fromEmail = defined('SMTP_FROM_EMAIL') ? SMTP_FROM_EMAIL : 'no-reply@localhost';
        $headers = "From: {$fromEmail}\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        
        // If HTML content provided, use HTML headers, otherwise plain text
        if ($bodyHtml !== '') {
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "Content-Transfer-Encoding: 8bit\r\n";
            $ok = @mail($toEmail, $subject, $bodyHtml, $headers);  // Suppress warning
            if ($ok) {
                error_log("HTML Email sent via mail() to {$toEmail}");
                $emailsSent = true;
            }
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $ok = @mail($toEmail, $subject, $bodyText, $headers);  // Suppress warning
            if ($ok) {
                error_log("Plain text email sent via mail() to {$toEmail}");
                $emailsSent = true;
            }
        }
        
        if (!$ok) {
            error_log("mail() failed to={$toEmail} subject={$subject}");
        }
    }
    
    // For LOCAL DEVELOPMENT: Always save a copy to disk for inspection
    $emailsDir = defined('UPLOAD_DIR') ? UPLOAD_DIR . 'emails/' : null;
    if ($emailsDir && @is_dir($emailsDir)) {
        try {
            $timestamp = date('Y-m-d_H-i-s');
            $safeEmail = preg_replace('/[^a-zA-Z0-9._-]/', '_', $toEmail);
            $filename = $safeEmail . '_' . $timestamp . '_' . uniqid() . '.html';
            $filepath = $emailsDir . $filename;
            
            $fullEmail = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; background: #f5f5f5; padding: 20px; }
        .container { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .meta { background: #f0f0f0; padding: 12px; border-radius: 4px; margin-bottom: 20px; font-size: 12px; }
        .meta p { margin: 4px 0; }
        .email-body { border-top: 2px solid #e0e0e0; padding-top: 20px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <h2>Ã°Å¸â€œÂ§ Email Copy (Local Development)</h2>
        <div class='meta'>
            <p><strong>To:</strong> {$toEmail} ({$toName})</p>
            <p><strong>Subject:</strong> {$subject}</p>
            <p><strong>Sent:</strong> {$timestamp}</p>
            <p><strong>Status:</strong> Ã¢Å“â€œ Saved</p>
        </div>
        <div class='email-body'>
            " . (!empty($bodyHtml) ? $bodyHtml : '<pre>' . htmlspecialchars($bodyText) . '</pre>') . "
        </div>
    </div>
</body>
</html>";
            
            file_put_contents($filepath, $fullEmail);
            error_log("Email saved to: {$filepath}");
        } catch (Exception $e) {
            error_log("Failed to save email to disk: " . $e->getMessage());
        }
    }
    
    return $emailsSent;
}

/**
 * Validate and store an uploaded file under uploads/<subdir>/.
 * Returns a web-relative path like 'uploads/<subdir>/<name>' or null on failure (logged).
 * Verifies actual content MIME via finfo (never trusts the client-supplied type),
 * enforces MAX_FILE_SIZE, rejects executable extensions, and stores under a random name.
 */
function handleUpload(array $file, string $subdir): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || empty($file['name'])) {
        error_log('Upload rejected: no file');
        return null;
    }
    if (isset($file['size']) && $file['size'] > MAX_FILE_SIZE) {
        error_log('Upload rejected: too large');
        return null;
    }
    $origName = basename($file['name']);
    $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
    if (in_array($ext, ['php', 'phtml', 'phar', 'pl', 'py', 'cgi', 'asp', 'aspx', 'jsp', 'sh', 'exe', 'bat', 'cmd'], true)) {
        error_log('Upload rejected: bad extension');
        return null;
    }
    try {
        $detected = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    } catch (Throwable $e) {
        error_log('Upload rejected: finfo unavailable');
        return null;
    }
    if (!in_array($detected, ALLOWED_TYPES, true)) {
        error_log("Upload rejected: bad MIME ($detected)");
        return null;
    }
    $dir = UPLOAD_DIR . trim($subdir, '/') . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $name = bin2hex(random_bytes(8)) . '.' . $ext;
    $target = $dir . $name;
    // move_uploaded_file is required for real HTTP uploads; fall back to rename for CLI tests.
    $moved = is_uploaded_file($file['tmp_name'])
        ? move_uploaded_file($file['tmp_name'], $target)
        : rename($file['tmp_name'], $target);
    if (!$moved) {
        error_log('Upload move failed');
        return null;
    }
    return 'uploads/' . trim($subdir, '/') . '/' . $name;
}

/**
 * In-app notification helpers
 */
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
                          FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT ?");
    $stmt->bindValue(1, $userId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function markNotificationRead(int $notificationId, int $userId): void {
    Database::getConnection()->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")
        ->execute([$notificationId, $userId]);
}

/**
 * Analytics aggregation helpers
 */
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

/**
 * CSRF token
 */
function generateCSRF(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRF(string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $sessionToken = $_SESSION['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '' || $token === '') {
        return false;
    }
    return hash_equals($sessionToken, $token);
}

/**
 * Check whether a rate-limit key is currently blocked (without recording an attempt).
 * Used before credential verification so successful logins are never counted.
 */
function isRateLimited(string $key, int $maxAttempts = 5, int $windowSeconds = 60): bool {
    try {
        $db = Database::getConnection();
        $sel = $db->prepare("SELECT blocked_until FROM login_rate_limits WHERE rate_key = ? LIMIT 1");
        $sel->execute([$key]);
        $row = $sel->fetch();
        return (int)($row['blocked_until'] ?? 0) > time();
    } catch (Exception $e) {
        error_log("Rate limit check error: " . $e->getMessage());
        return false; // fail open Ã¢â‚¬â€ a DB hiccup should not lock everyone out
    }
}

/**
 * Allow only safe local redirect targets (relative .php paths on this app).
 * Prevents open-redirect attacks via ?redirect_to=https://evil.example
 */
function isSafeLocalRedirect(string $url): bool {
    if ($url === '' || $url[0] === '/' || strpos($url, '://') !== false || strpos($url, '\\') !== false) {
        return false;
    }
    return preg_match('#^[a-zA-Z0-9_\-./]+\.php$#', $url) === 1;
}

/**
 * JSON response helper
 */
function jsonResponse(bool $success, string $message, array $data = []): void {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}

/**
 * Auth check
 */
function requireAuth(): array {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['user'])) {
        header('Location: ' . appBasePathUrl('index.php'));
        exit;
    }
    return $_SESSION['user'];
}

/**
 * Absolute URL to a page at the application root, regardless of whether the
 * current page lives in the web root or the /php/ subfolder. Avoids broken
 * relative redirects (e.g. /php/index.php).
 */
function appBasePath(): string {
    $scriptDir = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php')), '/');
    // If this file is inside a /php subfolder, strip it to reach the app root
    if (substr($scriptDir, -4) === '/php') {
        $scriptDir = substr($scriptDir, 0, -4);
    }
    return rtrim($scriptDir, '/');
}

function appBasePathUrl(string $page): string {
    $base = appBasePath();
    return $base === '' ? $page : rtrim($base, '/') . '/' . ltrim($page, '/');
}

function requireAdmin(): array {
    $user = requireAuth();
    if (!in_array($user['role'], ['admin', 'super_admin'])) {
        http_response_code(403);
        die(json_encode(['success' => false, 'message' => 'Access denied.']));
    }
    return $user;
}

