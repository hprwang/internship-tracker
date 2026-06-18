<?php
session_start();
require_once 'php/config.php';
$user = requireAuth();
$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Change Password</title>
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
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.55; overflow-x: hidden; }

    /* Background Effects */
    .bg-effects { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
    .bg-effects::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(ellipse 80% 60% at 10% 0%, rgba(34,197,94,0.08) 0%, transparent 50%), radial-gradient(ellipse 60% 50% at 90% 100%, rgba(34,197,94,0.06) 0%, transparent 50%); }
    .bg-effects::after { content: ''; position: absolute; top: 15%; left: 10%; width: 400px; height: 400px; background: var(--green-neon); opacity: 0.04; filter: blur(120px); border-radius: 50%; }

    .layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; position: relative; z-index: 1; }

    /* Sidebar */
    .sidebar {
      background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem;
      display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto;
    }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; cursor: pointer; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--green-neon), var(--green-neon)); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 0 20px rgba(34,197,94,0.3); }
    .logo-text { font-size: 1.35rem; font-weight: 800; background: linear-gradient(135deg, var(--text-primary), #4ADE80); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }

    .nav-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: 12px; color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s; border: none; background: transparent; width: 100%; text-align: left; }
    .nav-item .icon { font-size: 1.1rem; width: 22px; text-align: center; }
    .nav-item:hover { background: var(--bg-panel); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3), 0 0 20px rgba(34,197,94,0.1); }

    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-subtle); cursor: pointer; }
    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green-neon), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; color: var(--bg-deep); }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.75rem; color: var(--text-muted); text-transform: capitalize; }

    /* Main Content */
    .main-content { background: var(--bg-deep); padding: 2rem; overflow-y: auto; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100vh; }

    .page-header { display: flex; align-items: center; margin-bottom: 1.5rem; width: 100%; max-width: 500px; }
    .back-btn { display: flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 8px; color: var(--text-secondary); font-size: 0.9rem; cursor: pointer; transition: all 0.2s; }
    .back-btn:hover { border-color: var(--green-neon); color: var(--green-neon); }
    .page-title { display: none; }

    /* Card */
    .card {
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: 16px;
      overflow: hidden;
      position: relative;
      max-width: 500px;
    }

    .card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--green-neon), var(--green-glow));
    }

    .card-body { padding: 2rem; }

    .info-row { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-subtle); }
    .info-row:last-child { border-bottom: none; }
    .info-label { color: var(--text-muted); font-size: 0.85rem; }
    .info-value { font-weight: 500; font-size: 0.9rem; }

    /* Form */
    .form-group { margin-bottom: 1.25rem; }
    .form-label { display: block; font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.5rem; }
    .form-control { width: 100%; padding: 0.875rem 1rem; background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: 10px; color: var(--text-primary); font-size: 0.95rem; transition: all 0.2s; }
    .form-control:hover { border-color: var(--border-light); }
    .form-control:focus { outline: none; border-color: var(--green-neon); box-shadow: 0 0 0 3px rgba(34,197,94,0.15); }
    .form-control::placeholder { color: var(--text-muted); }

    /* Button */
    .btn-primary {
      padding: 0.875rem 2rem;
      background: linear-gradient(135deg, var(--green-neon), var(--green-neon));
      color: var(--bg-deep);
      border: none;
      border-radius: 10px;
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.2s;
    }

    .btn-primary:hover { box-shadow: 0 0 25px rgba(34,197,94,0.5); transform: translateY(-2px); }
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }

    .btn-secondary {
      padding: 0.875rem 2rem;
      background: transparent;
      color: var(--text-secondary);
      border: 1px solid var(--border-subtle);
      border-radius: 10px;
      font-size: 0.95rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.2s;
    }
    .btn-secondary:hover { border-color: var(--green-neon); color: var(--green-neon); }

    .button-row { display: flex; gap: 1rem; margin-top: 1.5rem; }

    /* Toast */
    .toast-container {
      position: fixed;
      top: 1.5rem;
      right: 1.5rem;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .toast {
      padding: 1rem 1.5rem;
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: 10px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.4);
      display: flex;
      align-items: center;
      gap: 0.75rem;
      animation: slideIn .3s ease;
      font-size: 0.9rem;
    }

    .toast.success { border-color: var(--green-neon); }
    .toast.success::before { content: '✓'; color: var(--green-neon); font-weight: 700; }
    .toast.error { border-color: #EF4444; }
    .toast.error::before { content: '!'; color: #EF4444; font-weight: 700; }

    @keyframes slideIn {
      from { opacity: 0; transform: translateX(20px); }
      to { opacity: 1; transform: translateX(0); }
    }
  </style>
</head>
<body>
  <div class="bg-effects"></div>
  <div id="toast-container" class="toast-container"></div>

  <div class="layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-logo" onclick="window.location.href='dashboard.php'">
        <div class="logo-icon">🎓</div>
        <span class="logo-text">InternTrack</span>
      </div>

      <div class="nav-label">Menu</div>
      <nav class="nav-menu">
        <button class="nav-item" onclick="window.location.href='dashboard.php'">
          <span class="icon">📊</span> Dashboard
        </button>
        <button class="nav-item" onclick="window.location.href='internships.php'">
          <span class="icon">💼</span> Internships
        </button>
        <button class="nav-item" onclick="window.location.href='progress.php'">
          <span class="icon">📈</span> Progress Logs
        </button>
        <button class="nav-item" onclick="window.location.href='companies.php'">
          <span class="icon">🏢</span> Companies
        </button>
      </nav>

      <div class="sidebar-footer">
        <div class="nav-label">Account</div>
        <div class="user-chip" onclick="window.location.href='profile.php'">
          <div class="user-avatar"><?= strtoupper(substr($user['full_name'], 0, 1)) ?></div>
          <div class="user-info">
            <div class="user-name"><?= e($user['full_name']) ?></div>
            <div class="user-role"><?= e($user['role']) ?></div>
          </div>
        </div>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <div class="page-header">
        <button class="back-btn" onclick="window.location.href='profile.php'">
          ← Back
        </button>
      </div>

      <div class="card">
        <div class="card-body">
          <h2 style="font-size: 1.4rem; font-weight: 700; margin-bottom: 1.5rem; text-align: center;">Change <span style="color: var(--green-neon);">Password</span></h2>
          <form id="change-password-form">
            <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

            <div class="form-group">
              <label class="form-label">Current Password</label>
              <input type="password" name="current_password" class="form-control" placeholder="Enter current password" required>
            </div>

            <div class="form-group">
              <label class="form-label">New Password</label>
              <input type="password" name="new_password" class="form-control" placeholder="Min. 8 chars, 1 uppercase, 1 number" required>
            </div>

            <div class="form-group">
              <label class="form-label">Confirm New Password</label>
              <input type="password" name="confirm_password" class="form-control" placeholder="Confirm new password" required>
            </div>

            <div class="button-row">
              <button type="submit" id="submit-btn" class="btn-primary">Update Password</button>
              <button type="button" class="btn-secondary" onclick="window.location.href='profile.php'">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>

  <script src="js/app.js"></script>
  <script>
    function showToast(message, type = 'success') {
      const container = document.getElementById('toast-container');
      const toast = document.createElement('div');
      toast.className = `toast ${type}`;
      toast.textContent = message;
      container.appendChild(toast);
      setTimeout(() => toast.remove(), 4000);
    }

    document.getElementById('change-password-form').addEventListener('submit', async function(e) {
      e.preventDefault();
      const form = e.target;
      const btn = document.getElementById('submit-btn');

      const currentPassword = form.current_password.value;
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
        const res = await fetch('php/auth.php?action=change_password', {
          method: 'POST',
          headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
          body: new URLSearchParams({
            csrf_token: form.csrf_token.value,
            current_password: currentPassword,
            new_password: newPassword
          }).toString()
        });

        const data = await res.json();

        if (data.success) {
          showToast('Password updated successfully!', 'success');
          setTimeout(() => window.location.href = 'profile.php', 1500);
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
    });
  </script>
</body>
</html>