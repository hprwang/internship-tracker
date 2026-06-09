<?php
session_start();
require_once 'php/config.php';

// Reset password page (token + email)
$token = $_GET['token'] ?? '';
$email = $_GET['email'] ?? '';

$csrf = generateCSRF();

$validReset = false;
if (!empty($token) && !empty($email)) {
    try {
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

        foreach ($rows as $r) {
            if (password_verify($token, $r['token_hash'])) {
                $validReset = true;
                break;
            }
        }
    } catch (Exception $e) {
        $validReset = false;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Reset Password</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div id="toast-container" class="toast-container"></div>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-icon">🎓</div>
      <span>intern intern track</span>
    </div>

    <div class="auth-tabs">
      <span class="auth-tab active" style="cursor:default">Reset Password</span>
    </div>

    <div id="reset-form">
      <?php if (!$validReset): ?>
        <div class="empty-state" style="margin-top:1rem">
          <div class="empty-icon">!</div>
          <div>Invalid or expired reset link.</div>
          <div style="margin-top:.5rem;font-size:.85rem;color:var(--muted)">
            Please request a new one.
          </div>
        </div>
      <?php else: ?>
        <p style="text-align:center;margin-top:.5rem;font-size:.86rem;color:var(--muted)">
          Resetting password for: <strong style="color:var(--text)"><?= e($email) ?></strong>
        </p>

        <form onsubmit="handleResetPassword(event)" data-reset-token="<?= e($token) ?>" data-reset-email="<?= e($email) ?>">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <div class="form-group" style="margin-top:1rem">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Min. 8 chars, 1 uppercase, 1 number" required>
          </div>

          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
          </div>

          <button type="submit" id="reset-btn" class="btn btn-primary btn-full" style="margin-top:.8rem">Update Password</button>
        </form>
      <?php endif; ?>
    </div>

    <div style="margin-top:1.2rem;text-align:center">
      <a href="index.php" style="color:var(--accent);text-decoration:none">Back to Sign In</a>
    </div>
  </div>
</div>

<script src="js/app.js"></script>
</body>
</html>
