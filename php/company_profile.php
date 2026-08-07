<?php
session_start();
require_once __DIR__ . '/config.php';
$user = requireCompanyAuth();
$csrf = generateCSRF();

$db = Database::getCompanyConnection();
ensureCompanySchema($db);
$companyId = (int)$user['company_id'];

$message = '';
$messageType = 'success';

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request token. Please try again.';
        $messageType = 'error';
    } elseif ($_POST['action'] === 'update_profile') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            $message = 'Company name is required.';
            $messageType = 'error';
        } else {
            $stmt = $db->prepare("
                UPDATE companies SET name=?, industry=?, website=?, location=?, phone=?, email=?, description=?
                WHERE id=?
            ");
            $stmt->execute([
                $name,
                trim($_POST['industry'] ?? ''),
                trim($_POST['website'] ?? ''),
                trim($_POST['location'] ?? ''),
                trim($_POST['phone'] ?? ''),
                trim($_POST['email'] ?? ''),
                trim($_POST['description'] ?? ''),
                $companyId,
            ]);
            $message = 'Company profile updated successfully!';
        }
    }
}

$coStmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
$coStmt->execute([$companyId]);
$company = $coStmt->fetch();

if (!$company) {
    http_response_code(404);
    die('<h3>Company not found.</h3>');
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Company Profile</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/responsive.css">
  <style>
    :root {
      --bg-deep: #050505; --bg-charcoal: #0A0A0A; --bg-card: #161616; --border-subtle: #222222;
      --green-neon: #22C55E; --green-emerald: #16A34A; --text-primary: #FFFFFF; --text-secondary: #A1A1AA;
      --text-muted: #71717A; --radius-md: 12px; --radius-lg: 16px; --transition: 200ms cubic-bezier(.4,0,.2,1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.55; }
    .dashboard-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; text-decoration: none; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 0 20px rgba(34,197,94,0.3); }
    .logo-text { font-size: 1.35rem; font-weight: 800; color: var(--text-primary); } .logo-text span { color: var(--green-neon); }
    .nav-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3); }
    .nav-item .icon { font-size: 1.1rem; width: 22px; text-align: center; }
    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; color: var(--bg-deep); flex-shrink: 0; }
    .user-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.75rem; color: var(--text-muted); }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; text-align: left; margin-top: 0.75rem; text-decoration: none; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }

    .main-content { padding: 1.5rem 2rem; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .page-title { font-size: 1.8rem; font-weight: 700; } .page-title span { color: var(--green-neon); }
    .page-sub { color: var(--text-muted); font-size: 0.9rem; margin-top: 0.3rem; }

    .panel { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; }
    .panel-title { font-size: 1.05rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem; } .panel-title i { color: var(--green-neon); }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-group.full { grid-column: 1 / -1; }
    .form-label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem; }
    .form-control { width: 100%; background: var(--bg-charcoal); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.7rem 0.9rem; color: var(--text-primary); font-size: 0.9rem; outline: none; transition: border-color 200ms, box-shadow 200ms; }
    .form-control:focus { border-color: var(--green-neon); box-shadow: 0 0 0 3px rgba(34,197,94,0.12); }
    textarea.form-control { resize: vertical; min-height: 90px; }
    .btn-primary { background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); color: var(--bg-deep); font-weight: 700; padding: 0.7rem 1.6rem; border: none; border-radius: var(--radius-md); cursor: pointer; font-size: 0.9rem; transition: all var(--transition); }
    .btn-primary:hover { box-shadow: 0 0 25px rgba(34,197,94,0.45); }

    .alert { padding: 0.9rem 1.2rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem; }
    .alert.success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: var(--green-neon); }
    .alert.error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #F87171; }

    .password-wrapper { position: relative; }
    .password-wrapper .form-control { padding-right: 3rem; }
    .password-toggle { position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%); background: none; border: none; color: var(--text-muted); font-size: 1rem; cursor: pointer; padding: 0.5rem; }
    .password-toggle:hover { color: var(--green-neon); }

    .info-row { display: grid; grid-template-columns: 140px 1fr; gap: 0.75rem; padding: 0.55rem 0; border-bottom: 1px solid rgba(34,34,34,0.6); font-size: 0.9rem; }
    .info-row:last-child { border-bottom: none; }
    .info-label { color: var(--text-muted); font-weight: 600; }
    .info-value { color: var(--text-primary); }

    @media (max-width: 768px) {
      .dashboard-layout { grid-template-columns: 1fr; }
      .sidebar { position: static; height: auto; }
      .main-content { padding: 1rem; }
      .form-grid { grid-template-columns: 1fr; }
      .page-header { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
    }
  </style>
</head>
<body>
  <div id="toast-container" class="toast-container"></div>

  <div class="dashboard-layout">
    <aside class="sidebar">
      <a class="sidebar-logo" href="company_dashboard.php">
        <div class="logo-icon"><i class="fas fa-building"></i></div>
        <div class="logo-text">Intern<span>Track</span></div>
      </a>
      <div class="nav-menu">
        <div class="nav-label">Menu</div>
        <a class="nav-item" href="company_dashboard.php"><span class="icon"><i class="fas fa-chart-pie"></i></span> Dashboard</a>
        <a class="nav-item" href="company_internships.php"><span class="icon"><i class="fas fa-briefcase"></i></span> Internships</a>
        <a class="nav-item" href="company_applications.php"><span class="icon"><i class="fas fa-file-signature"></i></span> Applications</a>
        <a class="nav-item active" href="company_profile.php"><span class="icon"><i class="fas fa-user-cog"></i></span> Company Profile</a>
      </div>
      <div class="sidebar-footer">
        <div class="user-chip">
          <div class="user-avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'C', 0, 1))) ?></div>
          <div>
            <div class="user-name"><?= e($user['full_name'] ?? 'Company User') ?></div>
            <div class="user-role">Company Admin</div>
          </div>
        </div>
        <a class="logout-btn" href="#" onclick="handleLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </aside>

    <main class="main-content">
      <div class="page-header">
        <div>
          <h1 class="page-title">Company <span>Profile</span></h1>
          <p class="page-sub">Update your company details and account security.</p>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert <?= e($messageType) ?>"><?= e($message) ?></div>
      <?php endif; ?>

      <div class="panel">
        <div class="panel-title"><i class="fas fa-info-circle"></i> Company Details</div>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="update_profile">

          <div class="form-grid">
            <div class="form-group full">
              <label class="form-label">Company Name *</label>
              <input type="text" name="name" class="form-control" required value="<?= e($company['name']) ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Industry</label>
              <input type="text" name="industry" class="form-control" value="<?= e($company['industry'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Website</label>
              <input type="url" name="website" class="form-control" value="<?= e($company['website'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Location</label>
              <input type="text" name="location" class="form-control" value="<?= e($company['location'] ?? '') ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Phone</label>
              <input type="text" name="phone" class="form-control" value="<?= e($company['phone'] ?? '') ?>">
            </div>
            <div class="form-group full">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" value="<?= e($company['email'] ?? '') ?>">
            </div>
            <div class="form-group full">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" placeholder="Tell students about your company"><?= e($company['description'] ?? '') ?></textarea>
            </div>
          </div>

          <div style="margin-top:1.2rem;">
            <button type="submit" class="btn-primary">Save Profile</button>
          </div>
        </form>
      </div>

      <div class="panel">
        <div class="panel-title"><i class="fas fa-lock"></i> Change Password</div>
        <form onsubmit="handleCompanyChangePassword(event)">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <div class="form-grid">
            <div class="form-group full">
              <label class="form-label">Current Password</label>
              <div class="password-wrapper">
                <input type="password" name="current_password" class="form-control" required autocomplete="current-password">
                <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Toggle password"><i class="fas fa-eye"></i></button>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">New Password</label>
              <div class="password-wrapper">
                <input type="password" name="new_password" class="form-control" required minlength="8" autocomplete="new-password">
                <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Toggle password"><i class="fas fa-eye"></i></button>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Confirm New Password</label>
              <div class="password-wrapper">
                <input type="password" name="confirm_password" class="form-control" required minlength="8" autocomplete="new-password">
                <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Toggle password"><i class="fas fa-eye"></i></button>
              </div>
            </div>
          </div>
          <div style="margin-top:1.2rem;">
            <button type="submit" id="pw-btn" class="btn-primary">Update Password</button>
          </div>
        </form>
      </div>
    </main>
  </div>

  <script src="../js/app.js"></script>
  <script src="../js/interactive.js"></script>
  <script>
  function handleLogout(e) {
    e.preventDefault();
    fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) })
      .finally(() => { window.location.href = '../landing.php'; });
  }

  function togglePassword(btn) {
    const wrapper = btn.parentElement;
    const input = wrapper.querySelector('input');
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') { input.type = 'text'; if (icon) icon.className = 'fas fa-eye-slash'; }
    else { input.type = 'password'; if (icon) icon.className = 'fas fa-eye'; }
  }

  function toast(msg, type) {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const el = document.createElement('div');
    el.className = 'toast' + (type === 'error' ? ' error' : '');
    el.textContent = msg;
    container.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = '.3s'; setTimeout(() => el.remove(), 300); }, 4000);
  }

  async function handleCompanyChangePassword(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('pw-btn');
    btn.textContent = 'Updating…';
    btn.disabled = true;

    const fd = new FormData(form);
    const newPw = fd.get('new_password');
    if (newPw !== fd.get('confirm_password')) {
      toast('Passwords do not match.', 'error');
      btn.textContent = 'Update Password';
      btn.disabled = false;
      return;
    }
    if (newPw.length < 8 || !/[A-Z]/.test(newPw) || !/[0-9]/.test(newPw)) {
      toast('Password must be 8+ chars with an uppercase letter and a number.', 'error');
      btn.textContent = 'Update Password';
      btn.disabled = false;
      return;
    }

    fd.set('action', 'company_change_password');
    fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

    try {
      const res = await fetch('auth.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        toast('Password changed successfully!', 'success');
        form.reset();
      } else {
        toast(data.message || 'Failed to change password.', 'error');
      }
    } catch (err) {
      toast('Network error. Please try again.', 'error');
    } finally {
      btn.textContent = 'Update Password';
      btn.disabled = false;
    }
  }
  </script>
</body>
</html>