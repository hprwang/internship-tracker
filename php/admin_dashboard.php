<?php
session_start();
require_once __DIR__ . '/config.php';
$user = requireAuth();
if (($user['role'] ?? '') !== 'admin') {
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

// Get full stats
$totalStudents = $db->query("SELECT COUNT(*) as c FROM users WHERE role = 'student'")->fetch()['c'] ?? 0;
$totalCompanies = $db->query("SELECT COUNT(*) as c FROM companies")->fetch()['c'] ?? 0;
$totalInternships = $db->query("SELECT COUNT(*) as c FROM internships")->fetch()['c'] ?? 0;
$activeInternships = $db->query("SELECT COUNT(*) as c FROM internships WHERE status = 'ongoing'")->fetch()['c'] ?? 0;
$completedInternships = $db->query("SELECT COUNT(*) as c FROM internships WHERE status = 'completed'")->fetch()['c'] ?? 0;
$pendingApps = $db->query("SELECT COUNT(*) as c FROM internships WHERE status = 'applied'")->fetch()['c'] ?? 0;

// Recent activity
$activities = $db->query("SELECT al.*, u.full_name FROM activity_log al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 10")->fetchAll();

// Get recent students
$recentStudents = $db->query("SELECT u.id, u.full_name, u.email, u.created_at, (SELECT COUNT(*) FROM internships WHERE student_id = u.id) as internship_count FROM users u WHERE u.role = 'student' ORDER BY u.created_at DESC LIMIT 5")->fetchAll();

// Get recent companies
$recentCompanies = $db->query("SELECT * FROM companies ORDER BY created_at DESC LIMIT 5")->fetchAll();

// Get recent internships
$recentInternships = $db->query("
    SELECT i.*, u.full_name as student_name, c.name as company_name
    FROM internships i
    LEFT JOIN users u ON i.student_id = u.id
    LEFT JOIN companies c ON i.company_id = c.id
    ORDER BY i.created_at DESC LIMIT 5
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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

    /* Sidebar */
    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.25rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.25rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.25rem; }
    .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 0 20px rgba(34,197,94,0.25); }
    .logo-text { font-size: 1.25rem; font-weight: 800; background: linear-gradient(135deg, var(--text-primary), var(--green-glow)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

    .nav-section { margin-bottom: 1.5rem; }
    .nav-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
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

    /* Main Content */
    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; overflow-y: auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-subtle); }
    .page-title { font-size: 1.6rem; font-weight: 700; }
    .page-title span { color: var(--green-neon); }
    .page-subtitle { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; }
    .header-actions { display: flex; gap: 0.5rem; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: var(--radius-md); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; }
    .btn-primary { background: var(--green-neon); color: var(--bg-deep); }
    .btn-primary:hover { background: var(--green-glow); box-shadow: 0 0 20px rgba(34,197,94,0.4); }
    .btn-secondary { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-subtle); }
    .btn-secondary:hover { border-color: var(--green-neon); color: var(--green-neon); }
    .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; }

    /* Stats Grid */
    .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.25rem; transition: all var(--transition); }
    .stat-card:hover { border-color: var(--green-neon); transform: translateY(-2px); }
    .stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem; }
    .stat-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .stat-icon { width: 32px; height: 32px; background: rgba(34,197,94,0.1); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; }
    .stat-value { font-size: 1.75rem; font-weight: 700; }
    .stat-value.active { color: var(--green-neon); }

    /* Dashboard Grid */
    .dashboard-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.25rem; }
    .dash-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
    .dash-card-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); }
    .dash-card-title { font-size: 0.95rem; font-weight: 600; }
    .dash-card-link { font-size: 0.8rem; color: var(--green-neon); text-decoration: none; }
    .dash-card-link:hover { text-decoration: underline; }
    .dash-card-body { padding: 0; }

    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; background: var(--bg-elevated); border-bottom: 1px solid var(--border-subtle); }
    .data-table td { padding: 0.875rem 1rem; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle); }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: var(--bg-elevated); }

    .empty-message { padding: 1.5rem; text-align: center; color: var(--text-muted); font-size: 0.85rem; }

    .status-badge { display: inline-flex; padding: 0.2rem 0.5rem; border-radius: 999px; font-size: 0.7rem; font-weight: 600; text-transform: capitalize; }
    .status-badge.active { background: rgba(34,197,94,0.15); color: var(--green-neon); }
    .status-badge.pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
    .status-badge.completed { background: rgba(96,165,250,0.15); color: #60A5FA; }
    .status-badge.rejected { background: rgba(239,68,68,0.15); color: #F87171; }

    /* Modal */
    .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center; }
    .modal.show { display: flex; }
    .modal-content { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; width: 100%; max-width: 480px; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
    .modal-title { font-size: 1.1rem; font-weight: 700; }
    .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; padding: 0.25rem; }
    .form-group { margin-bottom: 1rem; }
    .form-label { display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.375rem; }
    .form-control { display: block; width: 100%; padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.85rem; }
    .form-control:focus { outline: none; border-color: var(--green-neon); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }

    /* Toast */
    .toast-container { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); animation: slideIn 0.3s ease; max-width: 320px; font-size: 0.85rem; }
    .toast.success { border-color: var(--green-neon); }
    .toast.error { border-color: #F87171; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    /* Responsive */
    @media (max-width: 1200px) { .stats-grid { grid-template-columns: repeat(3, 1fr); } }
    @media (max-width: 900px) {
      .admin-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .main-content { padding: 1rem; }
      .stats-grid { grid-template-columns: 1fr 1fr; }
      .dashboard-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div id="toast-container" class="toast-container"></div>

<div id="modal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title" id="modal-title">Add New</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <form id="modal-form">
      <div id="modal-fields"></div>
      <div class="form-row">
        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<div class="admin-layout">
  <!-- Sidebar -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">⚡</div>
      <span class="logo-text">InternTrack</span>
    </div>

    <div class="nav-section">
      <div class="nav-label">Dashboard</div>
      <nav class="nav-menu">
        <a href="admin_dashboard.php" class="nav-item active"><span class="icon">◉</span> Overview</a>
        <a href="admin_students.php" class="nav-item"><span class="icon">👥</span> Students</a>
        <a href="admin_companies.php" class="nav-item"><span class="icon">🏢</span> Companies</a>
        <a href="admin_internships.php" class="nav-item"><span class="icon">💼</span> Internships</a>
        <a href="admin_applications.php" class="nav-item"><span class="icon">📝</span> Applications</a>
        <a href="admin_reports.php" class="nav-item"><span class="icon">📈</span> Reports</a>
      </nav>
    </div>

    <div class="nav-section">
      <div class="nav-label">System</div>
      <nav class="nav-menu">
        <a href="#" class="nav-item"><span class="icon">⚙</span> Settings</a>
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
      <button class="logout-btn" onclick="handleLogout()"><span class="icon">⏻</span> Logout</button>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="main-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Admin <span>Dashboard</span></h1>
        <p class="page-subtitle">Overview of all students, companies, and internships</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" onclick="location.reload()">↻ Refresh</button>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-header">
          <span class="stat-label">Total Students</span>
          <div class="stat-icon">👥</div>
        </div>
        <div class="stat-value"><?= $totalStudents ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <span class="stat-label">Companies</span>
          <div class="stat-icon">🏢</div>
        </div>
        <div class="stat-value"><?= $totalCompanies ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <span class="stat-label">Active</span>
          <div class="stat-icon">⚡</div>
        </div>
        <div class="stat-value active"><?= $activeInternships ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <span class="stat-label">Completed</span>
          <div class="stat-icon">✓</div>
        </div>
        <div class="stat-value" style="color:#60A5FA"><?= $completedInternships ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <span class="stat-label">Pending</span>
          <div class="stat-icon">⏳</div>
        </div>
        <div class="stat-value" style="color:#F59E0B"><?= $pendingApps ?></div>
      </div>
    </div>

    <!-- Dashboard Grid -->
    <div class="dashboard-grid">
      <!-- Recent Students -->
      <div class="dash-card">
        <div class="dash-card-header">
          <h3 class="dash-card-title">Recent Students</h3>
          <a href="admin_students.php" class="dash-card-link">View All →</a>
        </div>
        <div class="dash-card-body">
          <table class="data-table">
            <thead><tr><th>Name</th><th>Email</th><th>Internships</th></tr></thead>
            <tbody>
              <?php if($recentStudents): foreach($recentStudents as $s): ?>
              <tr><td><?= e($s['full_name']) ?></td><td><?= e($s['email']) ?></td><td><?= $s['internship_count'] ?></td></tr>
              <?php endforeach; else: ?>
              <tr><td colspan="3" class="empty-message">No students yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent Companies -->
      <div class="dash-card">
        <div class="dash-card-header">
          <h3 class="dash-card-title">Recent Companies</h3>
          <a href="admin_companies.php" class="dash-card-link">View All →</a>
        </div>
        <div class="dash-card-body">
          <table class="data-table">
            <thead><tr><th>Name</th><th>Industry</th><th>Location</th></tr></thead>
            <tbody>
              <?php if($recentCompanies): foreach($recentCompanies as $c): ?>
              <tr><td><?= e($c['name']) ?></td><td><?= e($c['industry'] ?? '-') ?></td><td><?= e($c['location'] ?? '-') ?></td></tr>
              <?php endforeach; else: ?>
              <tr><td colspan="3" class="empty-message">No companies yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent Internships -->
      <div class="dash-card">
        <div class="dash-card-header">
          <h3 class="dash-card-title">Recent Internships</h3>
          <a href="admin_internships.php" class="dash-card-link">View All →</a>
        </div>
        <div class="dash-card-body">
          <table class="data-table">
            <thead><tr><th>Student</th><th>Company</th><th>Title</th><th>Status</th></tr></thead>
            <tbody>
              <?php if($recentInternships): foreach($recentInternships as $i): ?>
              <tr><td><?= e($i['student_name'] ?? '-') ?></td><td><?= e($i['company_name'] ?? '-') ?></td><td><?= e($i['title']) ?></td><td><span class="status-badge <?= $i['status'] ?>"><?= e($i['status']) ?></span></td></tr>
              <?php endforeach; else: ?>
              <tr><td colspan="4" class="empty-message">No internships yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Recent Activity -->
      <div class="dash-card">
        <div class="dash-card-header">
          <h3 class="dash-card-title">Recent Activity</h3>
        </div>
        <div class="dash-card-body">
          <table class="data-table">
            <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Details</th></tr></thead>
            <tbody>
              <?php if($activities): foreach($activities as $act): ?>
              <tr><td><?= date('M d, H:i', strtotime($act['created_at'])) ?></td><td><?= e($act['full_name'] ?? 'System') ?></td><td><?= e($act['action']) ?></td><td><?= e($act['entity_type']) ?> #<?= $act['entity_id'] ?></td></tr>
              <?php endforeach; else: ?>
              <tr><td colspan="4" class="empty-message">No activity yet</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
const App = { csrfToken: '<?= $csrf ?>', userId: <?= $user['id'] ?> };

// Setup nav items
document.querySelectorAll('.nav-item').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
  });
});

function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = '<span>' + msg + '</span>';
  c.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

function openModal(type) {
  const modal = document.getElementById('modal');
  modal.classList.add('show');
  App.modalType = type;
  const title = document.getElementById('modal-title');
  const fields = document.getElementById('modal-fields');

  const configs = {
    student: { title: 'Add Student', html: '<div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" required></div><div class="form-group"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div><div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div><div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>' },
    company: { title: 'Add Company', html: '<div class="form-group"><label class="form-label">Company Name</label><input type="text" name="name" class="form-control" required></div><div class="form-group"><label class="form-label">Industry</label><input type="text" name="industry" class="form-control"></div><div class="form-group"><label class="form-label">Website</label><input type="url" name="website" class="form-control"></div><div class="form-group"><label class="form-label">Location</label><input type="text" name="location" class="form-control"></div><div class="form-group"><label class="form-label">Contact Person</label><input type="text" name="contact_person" class="form-control"></div><div class="form-group"><label class="form-label">Contact Email</label><input type="email" name="contact_email" class="form-control"></div>' },
    internship: { title: 'Add Internship', html: '<div class="form-group"><label class="form-label">Student</label><select name="student_id" class="form-control"></select></div><div class="form-group"><label class="form-label">Company</label><select name="company_id" class="form-control"></select></div><div class="form-group"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div><div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div><div class="form-row"><div class="form-group"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" required></div><div class="form-group"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control" required></div></div>' }
  };

  if (configs[type]) {
    title.textContent = configs[type].title;
    fields.innerHTML = configs[type].html;
  }
}

function closeModal() {
  document.getElementById('modal').classList.remove('show');
}

// Handle modal form submit
document.getElementById('modal-form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'add_' + App.modalType);
  fd.append('csrf_token', App.csrfToken);

  try {
    const res = await fetch('admin.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) {
      toast(data.message, 'success');
      closeModal();
      setTimeout(() => location.reload(), 500);
    } else {
      toast(data.message, 'error');
    }
  } catch(err) {
    toast('Error: ' + err.message, 'error');
  }
});

async function handleLogout() {
  try {
    await fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
    window.location.href = 'admin_login.php';
  } catch(e) {
    window.location.href = 'admin_login.php';
  }
}
</script>
</body>
</html>