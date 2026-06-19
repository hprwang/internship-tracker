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
$activities = $db->query("SELECT al.*, u.full_name FROM activity_log al LEFT JOIN users u ON al.user_id = u.id ORDER BY al.created_at DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Admin Dashboard</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    :root { --bg-deep: #050505; --bg-charcoal: #0A0A0A; --bg-card: #161616; --bg-elevated: #1A1A1A; --border-subtle: #222222; --green-neon: #22C55E; --green-emerald: #16A34A; --green-glow: #4ADE80; --text-primary: #FFFFFF; --text-secondary: #A1A1AA; --text-muted: #71717A; --shadow-soft: 0 4px 24px rgba(0,0,0,0.4); --radius-sm: 8px; --radius-md: 12px; --radius-lg: 16px; --transition: 200ms cubic-bezier(.4,0,.2,1); }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; }
    .dashboard-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
.logo-text { font-size: 1.35rem; font-weight: 800; background: linear-gradient(135deg, var(--text-primary), var(--green-glow)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; color: transparent; }
    .nav-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3); }
    .nav-item .icon { font-size: 1.1rem; width: 22px; text-align: center; }
    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; color: var(--bg-deep); }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.75rem; color: var(--text-muted); }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.9rem; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; margin-top: 0.75rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }
    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; overflow-y: auto; }
    .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .welcome-section h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.25rem; }
    .welcome-section h1 span { color: var(--green-neon); }
    .welcome-section p { color: var(--text-muted); font-size: 0.95rem; }
    .header-actions { display: flex; gap: 0.75rem; }
    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: var(--radius-md); font-size: 0.875rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; }
    .btn-primary { background: var(--green-neon); color: var(--bg-deep); }
    .btn-primary:hover { background: var(--green-glow); box-shadow: 0 0 20px rgba(34,197,94,0.4); }
    .btn-secondary { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-subtle); }
    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; }
    .stat-card:hover { border-color: var(--green-neon); }
    .stat-label { font-size: 0.8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .stat-value { font-size: 2rem; font-weight: 700; }
    .stat-value.active { color: var(--green-neon); }
    .dash-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; margin-bottom: 1.5rem; }
    .dash-card-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .dash-card-title { font-size: 1.1rem; font-weight: 600; }
    .dash-card-body { padding: 0; }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 0.875rem 1rem; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; background: var(--bg-elevated); border-bottom: 1px solid var(--border-subtle); }
    .data-table td { padding: 1rem; font-size: 0.9rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle); }
    .data-table tr:hover td { background: var(--bg-elevated); }
    .empty-message { padding: 2rem; text-align: center; color: var(--text-muted); }
    .status-badge { display: inline-flex; padding: 0.25rem 0.625rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; }
    .status-badge.active { background: rgba(34,197,94,0.15); color: var(--green-neon); }
    .status-badge.pending { background: rgba(245,158,11,0.15); color: #F59E0B; }
    .status-badge.completed { background: rgba(96,165,250,0.15); color: #60A5FA; }
    .status-badge.rejected { background: rgba(239,68,68,0.15); color: #F87171; }
    .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.8rem; border-radius: var(--radius-sm); }
    .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.8); z-index: 9999; align-items: center; justify-content: center; }
    .modal.show { display: flex; }
    .modal-content { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 2rem; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; }
    .modal-title { font-size: 1.25rem; font-weight: 700; }
    .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; }
    .form-group { margin-bottom: 1rem; }
    .form-label { display: block; font-size: 0.85rem; color: var(--text-secondary); margin-bottom: 0.5rem; }
    .form-control { display: block; width: 100%; padding: 0.625rem 0.875rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.9rem; }
    .form-control:focus { outline: none; border-color: var(--green-neon); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .toast-container { position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.75rem; }
    .toast { display: flex; align-items: center; gap: 0.75rem; padding: 0.875rem 1.25rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); animation: slideIn 0.3s ease; max-width: 360px; }
    .toast.success { border-color: var(--green-neon); }
    .toast.error { border-color: #F87171; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @media (max-width: 768px) { .dashboard-layout { grid-template-columns: 1fr; } .sidebar { display: none; } .main-content { padding: 1rem; } .stats-grid { grid-template-columns: 1fr 1fr; } }
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

<div class="dashboard-layout">
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">⚡</div>
      <span class="logo-text">InternTrack</span>
    </div>
    <div class="nav-label">Admin Panel</div>
    <nav class="nav-menu">
      <button class="nav-item active" data-page="dashboard"><span class="icon">📊</span> Dashboard</button>
      <button class="nav-item" data-page="students"><span class="icon">👥</span> Students</button>
      <button class="nav-item" data-page="companies"><span class="icon">🏢</span> Companies</button>
      <button class="nav-item" data-page="internships"><span class="icon">💼</span> Internships</button>
      <button class="nav-item" data-page="applications"><span class="icon">📝</span> Applications</button>
      <button class="nav-item" data-page="reports"><span class="icon">📈</span> Reports</button>
      <button class="nav-item" data-page="users"><span class="icon">👤</span> Users</button>
      <button class="nav-item" data-page="activity"><span class="icon">📋</span> Activity Logs</button>
    </nav>
    <div class="sidebar-footer">
      <div class="user-chip">
        <div class="user-avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= e($user['full_name']) ?></div>
          <div class="user-role">Admin</div>
        </div>
      </div>
      <button class="logout-btn" onclick="handleLogout()"><span class="icon">⏻</span> Logout</button>
    </div>
  </aside>

  <main class="main-content">
    <div class="top-header">
      <div class="welcome-section">
        <h1>Admin <span>Dashboard</span></h1>
        <p>Manage all students, companies, internships, and reports</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" onclick="location.reload()">↻ Refresh</button>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card"><div class="stat-label">Total Students</div><div class="stat-value"><?= $totalStudents ?></div></div>
      <div class="stat-card"><div class="stat-label">Total Companies</div><div class="stat-value"><?= $totalCompanies ?></div></div>
      <div class="stat-card"><div class="stat-label">Active Internships</div><div class="stat-value active"><?= $activeInternships ?></div></div>
      <div class="stat-card"><div class="stat-label">Completed</div><div class="stat-value" style="color:#60A5FA"><?= $completedInternships ?></div></div>
      <div class="stat-card"><div class="stat-label">Pending</div><div class="stat-value" style="color:#F59E0B"><?= $pendingApps ?></div></div>
    </div>

    <div class="dash-card">
      <div class="dash-card-header">
        <h3 class="dash-card-title">Students</h3>
        <button class="btn btn-primary btn-sm" onclick="openModal('student')">+ Add Student</button>
      </div>
      <div class="dash-card-body">
        <table class="data-table">
          <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Internships</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody id="students-table"><tr><td colspan="6" class="empty-message">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>

    <div class="dash-card">
      <div class="dash-card-header">
        <h3 class="dash-card-title">Companies</h3>
        <button class="btn btn-primary btn-sm" onclick="openModal('company')">+ Add Company</button>
      </div>
      <div class="dash-card-body">
        <table class="data-table">
          <thead><tr><th>ID</th><th>Name</th><th>Industry</th><th>Location</th><th>Contact</th><th>Actions</th></tr></thead>
          <tbody id="companies-table"><tr><td colspan="6" class="empty-message">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>

    <div class="dash-card">
      <div class="dash-card-header">
        <h3 class="dash-card-title">Internships</h3>
        <button class="btn btn-primary btn-sm" onclick="openModal('internship')">+ Add Internship</button>
      </div>
      <div class="dash-card-body">
        <table class="data-table">
          <thead><tr><th>ID</th><th>Student</th><th>Company</th><th>Title</th><th>Status</th><th>Dates</th><th>Actions</th></tr></thead>
          <tbody id="internships-table"><tr><td colspan="7" class="empty-message">Loading...</td></tr></tbody>
        </table>
      </div>
    </div>

    <div class="dash-card">
      <div class="dash-card-header"><h3 class="dash-card-title">Recent Activity</h3></div>
      <div class="dash-card-body">
        <table class="data-table">
          <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
          <tbody>
            <?php if($activities): foreach($activities as $act): ?>
            <tr><td><?= date('M d, H:i', strtotime($act['created_at'])) ?></td><td><?= e($act['full_name'] ?? 'System') ?></td><td><?= e($act['action']) ?></td><td><?= e($act['entity_type']) ?>#<?= $act['entity_id'] ?></td><td><?= e($act['ip_address']) ?></td></tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" class="empty-message">No activity</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<script>
const App = { csrfToken: '<?= $csrf ?>', userId: <?= $user['id'] ?> };

document.querySelectorAll('.nav-item').forEach(btn => btn.addEventListener('click', function() {
  document.querySelectorAll('.nav-item').forEach(b => b.classList.remove('active'));
  this.classList.add('active');
}));

function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = '<span>' + msg + '</span>';
  c.appendChild(el);
  setTimeout(() => el.remove(), 3000);
}

function openModal(type) {
  document.getElementById('modal').classList.add('show');
  App.modalType = type;
  const t = document.getElementById('modal-title');
  const f = document.getElementById('modal-fields');
  if (type === 'student') {
    t.textContent = 'Add Student';
    f.innerHTML = '<div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" required></div><div class="form-group"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div><div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div><div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>';
  } else if (type === 'company') {
    t.textContent = 'Add Company';
    f.innerHTML = '<div class="form-group"><label class="form-label">Company Name</label><input type="text" name="name" class="form-control" required></div><div class="form-group"><label class="form-label">Industry</label><input type="text" name="industry" class="form-control"></div><div class="form-group"><label class="form-label">Website</label><input type="url" name="website" class="form-control"></div><div class="form-group"><label class="form-label">Location</label><input type="text" name="location" class="form-control"></div><div class="form-group"><label class="form-label">Contact Person</label><input type="text" name="contact_person" class="form-control"></div><div class="form-group"><label class="form-label">Contact Email</label><input type="email" name="contact_email" class="form-control"></div>';
  } else if (type === 'internship') {
    t.textContent = 'Add Internship';
    f.innerHTML = '<div class="form-group"><label class="form-label">Student</label><select name="student_id" class="form-control" id="intern-student-select"></select></div><div class="form-group"><label class="form-label">Company</label><select name="company_id" class="form-control" id="intern-company-select"></select></div><div class="form-group"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div><div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div><div class="form-row"><div class="form-group"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" required></div><div class="form-group"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control" required></div></div>';
  }
}
function closeModal() { document.getElementById('modal').classList.remove('show'); }

async function loadStudents() {
  try {
    const res = await fetch('admin.php?action=list_students', { credentials: 'same-origin' });
    const data = await res.json();
    const tbody = document.getElementById('students-table');
    if (data.success && data.students.length) {
      tbody.innerHTML = data.students.map(s => '<tr><td>'+s.id+'</td><td>'+s.full_name+'</td><td>'+s.email+'</td><td>'+(s.internship_count||0)+'</td><td><span class="status-badge '+(s.is_active?'active':'rejected')+'">'+(s.is_active?'Active':'Inactive')+'</span></td><td><button class="btn btn-secondary btn-sm" onclick="editStudent('+s.id+')">Edit</button></td></tr>').join('');
    } else { tbody.innerHTML = '<tr><td colspan="6" class="empty-message">No students</td></tr>'; }
  } catch(e) { document.getElementById('students-table').innerHTML = '<tr><td colspan="6" class="empty-message">Error</td></tr>'; }
}
async function loadCompanies() {
  try {
    const res = await fetch('admin.php?action=list_companies', { credentials: 'same-origin' });
    const data = await res.json();
    const tbody = document.getElementById('companies-table');
    if (data.success && data.companies.length) {
      tbody.innerHTML = data.companies.map(c => '<tr><td>'+c.id+'</td><td>'+c.name+'</td><td>'+(c.industry||'-')+'</td><td>'+(c.location||'-')+'</td><td>'+(c.contact_person||'-')+'</td><td><button class="btn btn-secondary btn-sm" onclick="editCompany('+c.id+')">Edit</button></td></tr>').join('');
    } else { tbody.innerHTML = '<tr><td colspan="6" class="empty-message">No companies</td></tr>'; }
  } catch(e) { document.getElementById('companies-table').innerHTML = '<tr><td colspan="6" class="empty-message">Error</td></tr>'; }
}
async function loadInternships() {
  try {
    const res = await fetch('admin.php?action=list_internships', { credentials: 'same-origin' });
    const data = await res.json();
    const tbody = document.getElementById('internships-table');
    if (data.success && data.internships.length) {
      tbody.innerHTML = data.internships.map(i => '<tr><td>'+i.id+'</td><td>'+(i.student_name||'-')+'</td><td>'+(i.company_name||'-')+'</td><td>'+i.title+'</td><td><span class="status-badge '+i.status+'">'+i.status+'</span></td><td>'+(i.start_date||'-')+' to '+(i.end_date||'-')+'</td><td><button class="btn btn-secondary btn-sm" onclick="editInternship('+i.id+')">Edit</button></td></tr>').join('');
    } else { tbody.innerHTML = '<tr><td colspan="7" class="empty-message">No internships</td></tr>'; }
  } catch(e) { document.getElementById('internships-table').innerHTML = '<tr><td colspan="7" class="empty-message">Error</td></tr>'; }
}

document.getElementById('modal-form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'add_' + App.modalType);
  fd.append('csrf_token', App.csrfToken);
  try {
    const res = await fetch('admin.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) { toast(data.message, 'success'); closeModal(); loadStudents(); loadCompanies(); loadInternships(); }
    else { toast(data.message, 'error'); }
  } catch(err) { toast('Error: ' + err.message, 'error'); }
});

async function handleLogout() {
  await fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
  window.location.href = 'admin_login.php';
}

document.addEventListener('DOMContentLoaded', () => { loadStudents(); loadCompanies(); loadInternships(); });
</script>
</body>
</html>