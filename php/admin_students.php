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

// Get all students with internship counts
$students = $db->query("
    SELECT u.id, u.username, u.email, u.full_name, u.is_active, u.created_at,
           (SELECT COUNT(*) FROM internships WHERE student_id = u.id) as internship_count,
           (SELECT MAX(created_at) FROM internships WHERE student_id = u.id) as last_internship_date
    FROM users u WHERE u.role = 'student' ORDER BY u.created_at DESC
")->fetchAll();

$totalStudents = count($students);
$activeStudents = count(array_filter($students, fn($s) => $s['is_active']));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Students</title>
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

    .stats-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; gap: 1rem; transition: all var(--transition); }
    .stat-card:hover { border-color: var(--green-neon); transform: translateY(-2px); }
    .stat-icon { width: 40px; height: 40px; background: rgba(34,197,94,0.1); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .stat-info { }
    .stat-value { font-size: 1.75rem; font-weight: 700; }
    .stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

    .content-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
    .card-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); }
    .card-title { font-size: 0.95rem; font-weight: 600; }
    .search-input { padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.85rem; width: 180px; }
    .search-input:focus { outline: none; border-color: var(--green-neon); }
    .filter-select { padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.85rem; width: 120px; cursor: pointer; }
    .filter-select:focus { outline: none; border-color: var(--green-neon); }
    .data-table th.sortable { cursor: pointer; user-select: none; }
    .data-table th.sortable:hover { color: var(--green-neon); }
    .data-table th.sorted-asc::after { content: ' ↑'; font-size: 0.65rem; }
    .data-table th.sorted-desc::after { content: ' ↓'; font-size: 0.65rem; }
    .pagination { display: flex; justify-content: center; align-items: center; gap: 0.5rem; padding: 1rem; border-top: 1px solid var(--border-subtle); }
    .pagination-btn { padding: 0.375rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-secondary); font-size: 0.8rem; cursor: pointer; }
    .pagination-btn:hover:not(:disabled) { border-color: var(--green-neon); color: var(--green-neon); }
    .pagination-btn:disabled { opacity: 0.4; cursor: not-allowed; }
    .pagination-info { font-size: 0.8rem; color: var(--text-muted); }
    .action-btn.toggle { color: var(--green-neon); }
    .btn-icon { padding: 0.375rem; font-size: 0.85rem; min-width: 28px; }

    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; background: var(--bg-elevated); border-bottom: 1px solid var(--border-subtle); }
    .data-table td { padding: 0.875rem 1rem; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle); }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: var(--bg-elevated); }

    .status-badge { display: inline-flex; padding: 0.2rem 0.5rem; border-radius: 999px; font-size: 0.7rem; font-weight: 600; }
    .status-badge.active { background: rgba(34,197,94,0.15); color: var(--green-neon); }
    .status-badge.inactive { background: rgba(239,68,68,0.15); color: #F87171; }

    .action-btn { padding: 0.375rem 0.625rem; font-size: 0.75rem; border-radius: var(--radius-sm); margin-right: 0.25rem; }
    .empty-message { padding: 2.5rem; text-align: center; color: var(--text-muted); }

    .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center; }
    .modal.show { display: flex; }
    .modal-content { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; width: 100%; max-width: 480px; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
    .modal-title { font-size: 1.1rem; font-weight: 700; }
    .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; padding: 0.25rem; }
    .form-group { margin-bottom: 1rem; }
    .form-label { display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.375rem; }
    .form-control { display: block; width: 100%; padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.85rem; }
    .form-control:focus { outline: none; border-color: var(--green-neon); }
    .form-row { display: flex; gap: 0.5rem; }

    .toast-container { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); animation: slideIn 0.3s ease; font-size: 0.85rem; }
    .toast.success { border-color: var(--green-neon); }
    .toast.error { border-color: #F87171; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    @media (max-width: 900px) {
      .admin-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .main-content { padding: 1rem; }
      .stats-row { grid-template-columns: 1fr 1fr; }
    }
  </style>
</head>
<body>
<div id="toast-container" class="toast-container"></div>

<div id="modal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title" id="modal-title">Add Student</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <form id="modal-form">
      <div class="form-group"><label class="form-label">Full Name</label><input type="text" name="full_name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required></div>
      <div class="form-row">
        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Save</button>
      </div>
    </form>
  </div>
</div>

<div class="admin-layout">
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">⚡</div>
      <span class="logo-text">InternTrack</span>
    </div>

    <div class="nav-section">
      <div class="nav-label">Dashboard</div>
      <nav class="nav-menu">
        <a href="admin_dashboard.php" class="nav-item"><span class="icon">◉</span> Overview</a>
        <a href="admin_students.php" class="nav-item active"><span class="icon">👥</span> Students</a>
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

  <main class="main-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Students <span>Management</span></h1>
        <p class="page-subtitle">Manage all student accounts and their internships</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary" onclick="openModal()">+ Add Student</button>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon">👥</div>
        <div class="stat-info">
          <div class="stat-value"><?= $totalStudents ?></div>
          <div class="stat-label">Total Students</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">✓</div>
        <div class="stat-info">
          <div class="stat-value" style="color:var(--green-neon)"><?= $activeStudents ?></div>
          <div class="stat-label">Active</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">💼</div>
        <div class="stat-info">
          <div class="stat-value"><?= array_sum(array_column($students, 'internship_count')) ?></div>
          <div class="stat-label">Internships</div>
        </div>
      </div>
    </div>

    <div class="content-card">
      <div class="card-header">
        <h3 class="card-title">All Students (<span id="student-count"><?= $totalStudents ?></span>)</h3>
        <div style="display:flex;gap:0.5rem;align-items:center">
          <select class="filter-select" id="status-filter" onchange="applyFilters()">
            <option value="">All Status</option>
            <option value="1">Active</option>
            <option value="0">Inactive</option>
          </select>
          <input type="text" class="search-input" placeholder="Search..." id="search-input" onkeyup="debounceFilter()">
          <button class="btn btn-secondary" onclick="exportCSV()">Export</button>
        </div>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th class="sortable" onclick="sortTable('id')">ID</th>
            <th class="sortable" onclick="sortTable('full_name')">Name</th>
            <th class="sortable" onclick="sortTable('email')">Email</th>
            <th class="sortable" onclick="sortTable('internship_count')">Internships</th>
            <th class="sortable" onclick="sortTable('created_at')">Joined</th>
            <th class="sortable" onclick="sortTable('is_active')">Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="students-tbody">
          <?php if($students): foreach($students as $s): ?>
          <tr>
            <td><?= $s['id'] ?></td>
            <td><?= e($s['full_name']) ?></td>
            <td><?= e($s['email']) ?></td>
            <td><?= $s['internship_count'] ?></td>
            <td><?= date('M d, Y', strtotime($s['created_at'])) ?></td>
            <td><span class="status-badge <?= $s['is_active'] ? 'active' : 'inactive' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
            <td>
              <button class="btn btn-secondary action-btn btn-icon" onclick="toggleStatus(<?= $s['id'] ?>, <?= $s['is_active'] ?>)" title="<?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>"><?= $s['is_active'] ? '◉' : '○' ?></button>
              <button class="btn btn-secondary action-btn" onclick="editStudent(<?= $s['id'] ?>, '<?= e(addslashes($s['full_name'])) ?>', '<?= e(addslashes($s['email'])) ?>', '<?= e(addslashes($s['username'])) ?>')">Edit</button>
              <button class="btn btn-secondary action-btn" onclick="deleteStudent(<?= $s['id'] ?>)">Delete</button>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="7" class="empty-message">No students found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
      </div>
      <div class="pagination">
        <button class="pagination-btn" id="prev-btn" onclick="changePage(-1)">← Prev</button>
        <span class="pagination-info" id="page-info"></span>
        <button class="pagination-btn" id="next-btn" onclick="changePage(1)">Next →</button>
      </div>
    </div>
  </main>
</div>

<script>
const App = { csrfToken: '<?= $csrf ?>', students: <?= json_encode($students) ?> };
let currentPage = 1, perPage = 10, sortCol = 'id', sortDir = 'desc', filterTimeout = null;

function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = '<span>' + msg + '</span>';
  c.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

function openModal() {
  document.getElementById('modal').classList.add('show');
}

function closeModal() {
  document.getElementById('modal').classList.remove('show');
}

function renderTable() {
  let data = [...App.students];
  const query = document.getElementById('search-input').value.toLowerCase();
  const status = document.getElementById('status-filter').value;

  if (query) {
    data = data.filter(s => s.full_name.toLowerCase().includes(query) || s.email.toLowerCase().includes(query) || s.username.toLowerCase().includes(query));
  }
  if (status !== '') {
    data = data.filter(s => String(s.is_active) === status);
  }

  data.sort((a, b) => {
    const av = a[sortCol], bv = b[sortCol];
    if (av < bv) return sortDir === 'asc' ? -1 : 1;
    if (av > bv) return sortDir === 'asc' ? 1 : -1;
    return 0;
  });

  const totalPages = Math.ceil(data.length / perPage) || 1;
  currentPage = Math.min(currentPage, totalPages);
  const start = (currentPage - 1) * perPage;
  const pageData = data.slice(start, start + perPage);

  document.getElementById('student-count').textContent = data.length;
  document.getElementById('page-info').textContent = `Page ${currentPage} of ${totalPages}`;
  document.getElementById('prev-btn').disabled = currentPage === 1;
  document.getElementById('next-btn').disabled = currentPage === totalPages;

  const tbody = document.getElementById('students-tbody');
  if (pageData.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="empty-message">No students found</td></tr>';
    return;
  }

  tbody.innerHTML = pageData.map(s => `
    <tr>
      <td>${s.id}</td>
      <td>${s.full_name}</td>
      <td>${s.email}</td>
      <td>${s.internship_count}</td>
      <td>${new Date(s.created_at).toLocaleDateString('en-US', {month:'short', day:'numeric', year:'numeric'})}</td>
      <td><span class="status-badge ${s.is_active ? 'active' : 'inactive'}">${s.is_active ? 'Active' : 'Inactive'}</span></td>
      <td>
        <button class="btn btn-secondary action-btn btn-icon" onclick="toggleStatus(${s.id}, ${s.is_active})" title="${s.is_active ? 'Deactivate' : 'Activate'}">${s.is_active ? '◉' : '○'}</button>
        <button class="btn btn-secondary action-btn" onclick="editStudent(${s.id}, '${s.full_name.replace(/'/g, "\\'")}', '${s.email.replace(/'/g, "\\'")}', '${s.username.replace(/'/g, "\\'")}')">Edit</button>
        <button class="btn btn-secondary action-btn" onclick="deleteStudent(${s.id})">Delete</button>
      </td>
    </tr>
  `).join('');

  document.querySelectorAll('th.sortable').forEach(th => {
    th.classList.remove('sorted-asc', 'sorted-desc');
    if (th.onclick?.toString().includes(sortCol)) {
      th.classList.add('sorted-' + sortDir);
    }
  });
}

function sortTable(col) {
  if (sortCol === col) sortDir = sortDir === 'asc' ? 'desc' : 'asc';
  else { sortCol = col; sortDir = 'asc'; }
  currentPage = 1;
  renderTable();
}

function debounceFilter() {
  clearTimeout(filterTimeout);
  filterTimeout = setTimeout(() => { currentPage = 1; renderTable(); }, 300);
}

function applyFilters() {
  currentPage = 1;
  renderTable();
}

function changePage(delta) {
  currentPage += delta;
  renderTable();
}

function exportCSV() {
  const headers = ['ID', 'Name', 'Email', 'Username', 'Internships', 'Joined', 'Status'];
  const rows = App.students.map(s => [s.id, s.full_name, s.email, s.username, s.internship_count, s.created_at, s.is_active ? 'Active' : 'Inactive']);
  const csv = [headers, ...rows].map(r => r.map(c => `"${String(c).replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = `students_${new Date().toISOString().split('T')[0]}.csv`;
  a.click();
  toast('Exported ' + App.students.length + ' students', 'success');
}

function toggleStatus(id, currentStatus) {
  const fd = new FormData();
  fd.append('action', 'toggle_student_status');
  fd.append('id', id);
  fd.append('status', currentStatus ? 0 : 1);
  fd.append('csrf_token', App.csrfToken);
  fetch('admin.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      toast(data.message, data.success ? 'success' : 'error');
      if (data.success) {
        const s = App.students.find(x => x.id === id);
        if (s) s.is_active = currentStatus ? 0 : 1;
        renderTable();
      }
    });
}

document.getElementById('modal-form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  fd.append('action', 'add_student');
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

function editStudent(id, name, email, username) {
  document.getElementById('modal-title').textContent = 'Edit Student';
  document.querySelector('input[name="full_name"]').value = name;
  document.querySelector('input[name="username"]').value = username;
  document.querySelector('input[name="email"]').value = email;
  document.querySelector('input[name="password"]').removeAttribute('required');
  document.querySelector('input[name="password"]').placeholder = 'Leave blank to keep current';
  document.getElementById('modal').dataset.editId = id;
  openModal();
}

function deleteStudent(id) {
  if (confirm('Delete this student?')) {
    const fd = new FormData();
    fd.append('action', 'delete_student');
    fd.append('id', id);
    fd.append('csrf_token', App.csrfToken);
    fetch('admin.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        toast(data.message, data.success ? 'success' : 'error');
        if (data.success) {
          App.students = App.students.filter(s => s.id !== id);
          renderTable();
        }
      });
  }
}

async function handleLogout() {
  await fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
  window.location.href = 'admin_login.php';
}

document.addEventListener('DOMContentLoaded', renderTable);
</script>
</body>
</html>