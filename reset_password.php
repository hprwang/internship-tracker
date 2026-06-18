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
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Reset Password</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg-deep: #050505;
      --bg-charcoal: #0A0A0A;
      --bg-panel: #111111;
      --bg-card: #161616;
      --bg-elevated: #1A1A1A;
      --border-subtle: #222222;
      --border-light: #2A2A2A;
      --green-neon: #22C55E;
      --green-emerald: #16A34A;
      --green-glow: #4ADE80;
      --green-muted: #86EFAC;
      --text-primary: #FFFFFF;
      --text-secondary: #A1A1AA;
      --text-muted: #71717A;
      --shadow-soft: 0 4px 24px rgba(0,0,0,0.4);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
      --radius-xl: 24px;
      --transition: 200ms cubic-bezier(.4,0,.2,1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.55; display: flex; align-items: center; justify-content: center; }

    /* Background Effects */
    .bg-effects { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
    .bg-effects::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(ellipse 80% 60% at 10% 0%, rgba(34,197,94,0.08) 0%, transparent 50%), radial-gradient(ellipse 60% 50% at 90% 100%, rgba(34,197,94,0.06) 0%, transparent 50%); }
    .bg-effects::after { content: ''; position: absolute; top: 15%; left: 10%; width: 400px; height: 400px; background: var(--green-neon); opacity: 0.04; filter: blur(120px); border-radius: 50%; }

    /* Auth Card */
    .auth-card {
      width: 100%;
      max-width:420px;
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: 20px;
      padding: 2.5rem;
      position: relative;
      z-index: 1;
      animation: slideUp .35s ease;
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(20px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .auth-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--green-neon), var(--green-glow));
      border-radius: 20px 20px 0 0;
    }

    /* Logo */
    .auth-logo {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      margin-bottom: 2rem;
    }

    .logo-icon {
      width: 44px;
      height: 44px;
      background: linear-gradient(135deg, var(--green-neon), var(--green-neon));
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      box-shadow: 0 0 20px rgba(34,197,94,0.3);
    }

    .logo-text {
      font-size: 1.4rem;
      font-weight: 800;
      background: linear-gradient(135deg, var(--text-primary), #4ADE80);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    /* Tab */
    .auth-tabs {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 2rem;
    }

    .auth-tab {
      padding: 0.6rem 1.25rem;
      background: rgba(34,197,94,0.1);
      border: 1px solid rgba(34,197,94,0.3);
      border-radius: 8px;
      font-size: 0.9rem;
      font-weight: 600;
      color: var(--green-neon);
    }

    /* Form */
    .form-group {
      margin-bottom: 1.25rem;
    }

    .form-label {
      display: block;
      font-size: 0.85rem;
      font-weight: 600;
      color: var(--text-secondary);
      margin-bottom: 0.5rem;
    }

    .form-control {
      width: 100%;
      padding: 0.875rem 1rem;
      background: var(--bg-panel);
      border: 1px solid var(--border-subtle);
      border-radius: 10px;
      color: var(--text-primary);
      font-size: 0.95rem;
      transition: all 0.2s;
    }

    .form-control:hover {
      border-color: var(--border-light);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--green-neon);
      box-shadow: 0 0 0 3px rgba(34,197,94,0.15);
    }

    .form-control::placeholder {
      color: var(--text-muted);
    }

    /* Button */
    .btn-primary {
      width: 100%;
      padding: 0.9rem 1.5rem;
      background: linear-gradient(135deg, var(--green-neon), var(--green-neon));
      color: var(--bg-deep);
      border: none;
      border-radius: 10px;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-primary:hover {
      box-shadow: 0 0 25px rgba(34,197,94,0.5);
      transform: translateY(-2px);
    }

    .btn-primary:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none;
      box-shadow: none;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 2rem 1rem;
    }

    .empty-icon {
      width: 60px;
      height: 60px;
      margin: 0 auto 1rem;
      background: rgba(239,68,68,0.1);
      border: 2px solid rgba(239,68,68,0.3);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      color: #F87171;
    }

    .empty-state div {
      color: var(--text-secondary);
      font-size: 0.95rem;
    }

    .empty-state div + div {
      margin-top: 0.5rem;
      color: var(--text-muted);
      font-size: 0.85rem;
    }

    /* Email display */
    .email-display {
      text-align: center;
      margin-bottom: 1.5rem;
      padding: 1rem;
      background: var(--bg-panel);
      border-radius: 10px;
      border: 1px solid var(--border-subtle);
    }

    .email-display .label {
      font-size: 0.8rem;
      color: var(--text-muted);
      margin-bottom: 0.25rem;
    }

    .email-display .email {
      font-size: 1rem;
      font-weight: 600;
      color: var(--green-neon);
    }

    /* Back link */
    .back-link {
      display: block;
      text-align: center;
      margin-top: 1.5rem;
      color: var(--text-muted);
      font-size: 0.9rem;
    }

    .back-link a {
      color: var(--green-neon);
      text-decoration: none;
      font-weight: 500;
    }

    .back-link a:hover {
      text-decoration: underline;
    }
  </style>
</head>
<body>
  <div class="bg-effects"></div>
  <div id="toast-container" class="toast-container"></div>

  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-icon">🎓</div>
      <span class="logo-text">InternTrack</span>
    </div>

    <div class="auth-tabs">
      <span class="auth-tab">Reset Password</span>
    </div>

    <div id="reset-form">
      <?php if (!$validReset): ?>
        <div class="empty-state">
          <div class="empty-icon">!</div>
          <div>Invalid or expired reset link.</div>
          <div>Please request a new password reset.</div>
        </div>
      <?php else: ?>
        <div class="email-display">
          <div class="label">Resetting password for</div>
          <div class="email"><?= e($email) ?></div>
        </div>

        <form onsubmit="handleResetPassword(event)" data-reset-token="<?= e($token) ?>" data-reset-email="<?= e($email) ?>">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

          <div class="form-group">
            <label class="form-label">New Password</label>
            <input type="password" name="new_password" class="form-control" placeholder="Min. 8 chars, 1 uppercase, 1 number" required>
          </div>

          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
          </div>

          <button type="submit" id="reset-btn" class="btn-primary">Update Password</button>
        </form>
      <?php endif; ?>
    </div>

    <div class="back-link">
      <a href="index.php">← Back to Sign In</a>
    </div>
  </div>

  <script src="js/app.js"></script>
  <script>
    async function handleResetPassword(e) {
      e.preventDefault();
      const form = e.target;
      const btn = document.getElementById('reset-btn');

      const newPassword = form.new_password.value;
      const confirmPassword = form.confirm_password.value;

      if (newPassword.length < 8) {
        showToast('Password must be at least 8 characters', 'error');
        return;
      }

      if (!/[A-Z]/.test(newPassword)) {
        showToast('Password must contain at least one uppercase letter', 'error');
        return;
      }

      if (!/[0-9]/.test(newPassword)) {
        showToast('Password must contain at least one number', 'error');
        return;
      }

      if (newPassword !== confirmPassword) {
        showToast('Passwords do not match', 'error');
        return;
      }

      btn.disabled = true;
      btn.textContent = 'Updating...';

      try {
        const res = await fetch('php/auth.php?action=forgot_reset', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            csrf_token: form.csrf_token.value,
            token: form.dataset.resetToken,
            email: form.dataset.resetEmail,
            new_password: newPassword
          }).toString()
        });

        const data = await res.json();

        if (data.success) {
          showToast('Password updated! Redirecting...', 'success');
          setTimeout(() => window.location.href = 'index.php', 1500);
        } else {
          showToast(data.message || 'Failed to update password', 'error');
          btn.disabled = false;
          btn.textContent = 'Update Password';
        }
      } catch (err) {
        showToast('Network error. Please try again.', 'error');
        btn.disabled = false;
        btn.textContent = 'Update Password';
      }
    }
  </script>
</body>
</html>