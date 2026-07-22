<?php
session_start();
require_once __DIR__ . '/config.php';
$user = requireAuth();
if (!in_array($user['role'] ?? '', ['admin', 'super_admin'])) {
    http_response_code(403);
    die('<h3>Access Denied</h3><p>Admin access required.</p>');
}
if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$csrf = generateCSRF();
$db = Database::getConnection();

// Get all settings
$settings = [];
$stmt = $db->query("SELECT key_name, value_text FROM settings");
while ($row = $stmt->fetch()) {
    $settings[$row['key_name']] = $row['value_text'];
}

// Defaults
$defaults = [
    'site_name' => 'InternTrack', 'site_email' => '', 'site_phone' => '',
    'allow_registration' => '1', 'require_approval' => '1',
    'default_internship_duration' => '3', 'max_internships_per_student' => '5',
    'email_notifications' => '1', 'email_new_application' => '1', 'email_status_change' => '1',
    'maintenance_mode' => '0', 'maintenance_message' => '',
    'theme' => 'dark', 'items_per_page' => '10',
    'session_timeout' => '60', 'max_login_attempts' => '5',
];
$settings = array_merge($defaults, $settings);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Settings</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
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
      --text-primary: #FFFFFF;
      --text-secondary: #A1A1AA;
      --text-muted: #71717A;
      --shadow-soft: 0 4px 24px rgba(0,0,0,0.4);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
      --transition: 200ms cubic-bezier(.4,0,.2,1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.5; }

    .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }

    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.25rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.25rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.25rem; }
    .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 0 20px rgba(34,197,94,0.25); }
    .logo-text { font-size: 1.25rem; font-weight: 800; color: var(--text-primary); }
    .logo-text span { color: var(--green-neon); }

    .nav-section { margin-bottom: 1.5rem; }
    .nav-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3); }
    .nav-item .icon { font-size: 1rem; width: 20px; text-align: center; }

    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 34px; height: 34px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; color: var(--bg-deep); }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.7rem; color: var(--text-muted); }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.85rem; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; margin-top: 0.5rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }

    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; overflow-y: auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-subtle); }
    .header-actions { display: flex; gap: 0.5rem; align-items: center; }
    .page-title { font-size: 1.6rem; font-weight: 700; }
    .page-title span { color: var(--green-neon); }
    .page-subtitle { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: var(--radius-md); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; }
    .btn-primary { background: var(--green-neon); color: var(--bg-deep); }
    .btn-primary:hover { background: var(--green-glow); box-shadow: 0 0 20px rgba(34,197,94,0.4); }
    .btn-secondary { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-subtle); }
    .btn-secondary:hover { border-color: var(--green-neon); color: var(--green-neon); }

    /* Tabs */
    .tabs { display: flex; gap: 0.25rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; padding-bottom: 0; }
    .tab-btn { padding: 0.75rem 1.25rem; background: transparent; border: none; color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; cursor: pointer; border-bottom: 2px solid transparent; margin-bottom: -1px; transition: all var(--transition); }
    .tab-btn:hover { color: var(--text-primary); }
    .tab-btn.active { color: var(--green-neon); border-bottom-color: var(--green-neon); }
    .tab-content { display: none; }
    .tab-content.active { display: block; }

    .settings-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; }
    .card-title { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: var(--text-primary); }
    .card-desc { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 1rem; }
    .form-group { margin-bottom: 1.25rem; }
    .form-label { display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.5rem; font-weight: 500; }
    .form-control { display: block; width: 100%; padding: 0.625rem 0.875rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.9rem; transition: all var(--transition); }
    .form-control:focus { outline: none; border-color: var(--green-neon); box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }
    .form-control::placeholder { color: var(--text-muted); }
    select.form-control { cursor: pointer; }
    textarea.form-control { min-height: 100px; resize: vertical; }
    .form-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; }

    .toggle-group { display: flex; align-items: center; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-subtle); }
    .toggle-group:last-child { border-bottom: none; }
    .toggle-info { }
    .toggle-label { font-size: 0.9rem; font-weight: 500; color: var(--text-primary); }
    .toggle-desc { font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem; }
    .toggle { width: 48px; height: 26px; background: var(--bg-elevated); border-radius: 13px; position: relative; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); flex-shrink: 0; }
    .toggle.active { background: var(--green-neon); border-color: var(--green-neon); }
    .toggle::after { content: ''; position: absolute; width: 20px; height: 20px; background: var(--text-primary); border-radius: 50%; top: 2px; left: 2px; transition: all var(--transition); }
    .toggle.active::after { left: 24px; }

    .danger-zone { border-color: rgba(239,68,68,0.3); background: rgba(239,68,68,0.05); }
    .danger-zone .card-title { color: #F87171; }

    .toast-container { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); animation: slideIn 0.3s ease; font-size: 0.85rem; }
    .toast.success { border-color: var(--green-neon); }
    .toast.error { border-color: #F87171; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    @media (max-width: 900px) {
      .admin-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .main-content { padding: 1rem; }
      .form-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div id="toast-container" class="toast-container"></div>

<div class="admin-layout">
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon"><i class="fas fa-clipboard-list"></i></div>
      <div class="logo-text">Intern<span>Track</span></div>
    </div>

    <div class="nav-section">
      <div class="nav-label">Dashboard</div>
      <nav class="nav-menu">
<a href="admin_dashboard.php" class="nav-item"><span class="icon"><i class="fas fa-chart-pie"></i></span> Overview</a>
        <a href="admin_students.php" class="nav-item"><span class="icon"><i class="fas fa-users"></i></span> Students</a>
        <a href="admin_companies.php" class="nav-item"><span class="icon"><i class="fas fa-building"></i></span> Companies</a>
        <a href="admin_internships.php" class="nav-item"><span class="icon"><i class="fas fa-briefcase"></i></span> Internships</a>
        <a href="admin_reports.php" class="nav-item"><span class="icon"><i class="fas fa-chart-bar"></i></span> Reports</a>
      </nav>
    </div>

    <div class="nav-section">
      <div class="nav-label">System</div>
      <nav class="nav-menu">
        <a href="admin_settings.php" class="nav-item active"><span class="icon"><i class="fas fa-cog"></i></span> Settings</a>
      </nav>
    </div>

    <div class="sidebar-footer">
      <div class="user-chip">
        <div class="user-avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= e($user['full_name']) ?></div>
          <div class="user-role">Administrator</div>
        </div>
      </div>
      <button class="logout-btn" onclick="handleLogout()"><span class="icon"><i class="fas fa-sign-out-alt"></i></span> Logout</button>
    </div>
  </aside>

  <main class="main-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">System <span>Settings</span></h1>
        <p class="page-subtitle">Configure system preferences, security, and notifications</p>
      </div>
      <div class="header-actions">
        <button type="submit" form="settings-form" class="btn btn-primary">Save Changes</button>
      </div>
    </div>

    <div class="tabs">
      <button class="tab-btn active" data-tab="general">General</button>
      <button class="tab-btn" data-tab="registration">Registration</button>
      <button class="tab-btn" data-tab="internships">Internships</button>
      <button class="tab-btn" data-tab="notifications">Notifications</button>
      <button class="tab-btn" data-tab="security">Security</button>
      <button class="tab-btn" data-tab="maintenance">Maintenance</button>
    </div>

    <form id="settings-form">
      <!-- General Tab -->
      <div class="tab-content active" data-tab="general">
        <div class="settings-card">
          <h3 class="card-title">Site Information</h3>
          <p class="card-desc">Basic information about your internship tracking system</p>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Site Name</label>
              <input type="text" name="site_name" class="form-control" value="<?= e($settings['site_name']) ?>" placeholder="InternTrack">
            </div>
            <div class="form-group">
              <label class="form-label">Contact Email</label>
              <input type="email" name="site_email" class="form-control" value="<?= e($settings['site_email']) ?>" placeholder="admin@example.com">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Contact Phone</label>
            <input type="tel" name="site_phone" class="form-control" value="<?= e($settings['site_phone']) ?>" placeholder="+1 (555) 000-0000">
          </div>
        </div>

        </div>

      <!-- Registration Tab -->
      <div class="tab-content" data-tab="registration">
        <div class="settings-card">
          <h3 class="card-title">User Registration</h3>
          <p class="card-desc">Control how students register and access the system</p>

          <div class="toggle-group">
            <div class="toggle-info">
              <div class="toggle-label">Allow New Registrations</div>
              <div class="toggle-desc">Let new students create accounts</div>
            </div>
            <div class="toggle <?= $settings['allow_registration']?'active':'' ?>" data-toggle="allow_registration"></div>
            <input type="hidden" name="allow_registration" value="<?= $settings['allow_registration'] ?>">
          </div>

          <div class="toggle-group">
            <div class="toggle-info">
              <div class="toggle-label">Require Admin Approval</div>
              <div class="toggle-desc">New accounts need admin approval before activation</div>
            </div>
            <div class="toggle <?= $settings['require_approval']?'active':'' ?>" data-toggle="require_approval"></div>
            <input type="hidden" name="require_approval" value="<?= $settings['require_approval'] ?>">
          </div>
        </div>
      </div>

      <!-- Internships Tab -->
      <div class="tab-content" data-tab="internships">
        <div class="settings-card">
          <h3 class="card-title">Internship Defaults</h3>
          <p class="card-desc">Default settings for new internships</p>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Default Duration (months)</label>
              <input type="number" name="default_internship_duration" class="form-control" value="<?= e($settings['default_internship_duration']) ?>" min="1" max="24">
            </div>
            <div class="form-group">
              <label class="form-label">Max Internships Per Student</label>
              <input type="number" name="max_internships_per_student" class="form-control" value="<?= e($settings['max_internships_per_student']) ?>" min="1" max="20">
            </div>
          </div>
        </div>
      </div>

      <!-- Notifications Tab -->
      <div class="tab-content" data-tab="notifications">
        <div class="settings-card">
          <h3 class="card-title">Email Notifications</h3>
          <p class="card-desc">Configure when to send email notifications</p>

          <div class="toggle-group">
            <div class="toggle-info">
              <div class="toggle-label">Enable Email Notifications</div>
              <div class="toggle-desc">Send email notifications to admins</div>
            </div>
            <div class="toggle <?= $settings['email_notifications']?'active':'' ?>" data-toggle="email_notifications"></div>
            <input type="hidden" name="email_notifications" value="<?= $settings['email_notifications'] ?>">
          </div>

          <div class="toggle-group">
            <div class="toggle-info">
              <div class="toggle-label">New Application Alerts</div>
              <div class="toggle-desc">Notify when a student applies for an internship</div>
            </div>
            <div class="toggle <?= $settings['email_new_application']?'active':'' ?>" data-toggle="email_new_application"></div>
            <input type="hidden" name="email_new_application" value="<?= $settings['email_new_application'] ?>">
          </div>

          <div class="toggle-group">
            <div class="toggle-info">
              <div class="toggle-label">Status Change Alerts</div>
              <div class="toggle-desc">Notify when an internship status changes</div>
            </div>
            <div class="toggle <?= $settings['email_status_change']?'active':'' ?>" data-toggle="email_status_change"></div>
            <input type="hidden" name="email_status_change" value="<?= $settings['email_status_change'] ?>">
          </div>
        </div>
      </div>

      <!-- Security Tab -->
      <div class="tab-content" data-tab="security">
        <div class="settings-card">
          <h3 class="card-title">Session & Login</h3>
          <p class="card-desc">Configure session timeout and login security</p>
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Session Timeout (minutes)</label>
              <input type="number" name="session_timeout" class="form-control" value="<?= e($settings['session_timeout']) ?>" min="5" max="480">
            </div>
            <div class="form-group">
              <label class="form-label">Max Login Attempts</label>
              <input type="number" name="max_login_attempts" class="form-control" value="<?= e($settings['max_login_attempts']) ?>" min="3" max="10">
            </div>
          </div>
        </div>
      </div>

      <!-- Maintenance Tab -->
      <div class="tab-content" data-tab="maintenance">
        <div class="settings-card danger-zone">
          <h3 class="card-title">Maintenance Mode</h3>
          <p class="card-desc">Temporarily disable the site for maintenance</p>

          <div class="toggle-group">
            <div class="toggle-info">
              <div class="toggle-label">Enable Maintenance Mode</div>
              <div class="toggle-desc">Show visitors a maintenance message</div>
            </div>
            <div class="toggle <?= $settings['maintenance_mode']?'active':'' ?>" data-toggle="maintenance_mode"></div>
            <input type="hidden" name="maintenance_mode" value="<?= $settings['maintenance_mode'] ?>">
          </div>

          <div class="form-group">
            <label class="form-label">Maintenance Message</label>
            <textarea name="maintenance_message" class="form-control" placeholder="We'll be back soon!"><?= e($settings['maintenance_message']) ?></textarea>
          </div>
        </div>
      </div>
    </form>
  </main>
</div>

<script src="../js/app.js"></script>
<script>
Object.assign(App, { csrfToken: '<?= $csrf ?>' });

function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = '<span>' + msg + '</span>';
  c.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

// Tab switching
document.querySelectorAll('.tab-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
    this.classList.add('active');
    document.querySelector('[data-tab="' + this.dataset.tab + '"].tab-content').classList.add('active');
  });
});

// Toggle handlers
document.querySelectorAll('.toggle').forEach(toggle => {
  toggle.addEventListener('click', function() {
    this.classList.toggle('active');
    const input = this.nextElementSibling.nextElementSibling;
    if (input && input.tagName === 'INPUT') {
      input.value = this.classList.contains('active') ? '1' : '0';
    }
  });
});

// Initialize toggles from hidden inputs
document.querySelectorAll('.toggle').forEach(toggle => {
  const input = toggle.nextElementSibling.nextElementSibling;
  if (input && input.tagName === 'INPUT' && input.value === '1') {
    toggle.classList.add('active');
  }
});

// Form submit
document.getElementById('settings-form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'save_settings');
  fd.append('csrf_token', App.csrfToken);

  try {
    const res = await fetch('admin.php', { method: 'POST', body: fd });
    const data = await res.json();
    toast(data.message, data.success ? 'success' : 'error');
  } catch(err) {
    toast('Error: ' + err.message, 'error');
  }
});

async function handleLogout() {
  await fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
  window.location.href = 'admin_login.php';
}
</script>
</body>
</html>