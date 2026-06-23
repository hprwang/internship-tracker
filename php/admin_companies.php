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

$industryFilter = $_GET['industry'] ?? '';
$statusFilter = $_GET['status'] ?? '';

$where = [];
if ($industryFilter) $where[] = "c.industry = " . $db->quote($industryFilter);
if ($statusFilter) $where[] = "c.status = " . $db->quote($statusFilter);
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$companies = $db->query("
    SELECT c.*,
           (SELECT COUNT(*) FROM internships WHERE company_id = c.id) as internship_count
    FROM companies c $whereClause ORDER BY c.created_at DESC
")->fetchAll();

$totalCompanies = count($companies);
$activeCompanies = count(array_filter($companies, fn($c) => ($c['status'] ?? 'active') === 'active'));
$totalInternships = array_sum(array_column($companies, 'internship_count'));

// Get unique industries for filter
$allIndustries = $db->query("SELECT DISTINCT industry FROM companies WHERE industry IS NOT NULL AND industry != '' ORDER BY industry")->fetchAll();
$industries = array_column($allIndustries, 'industry');
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Companies</title>
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

    .stats-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; gap: 1rem; transition: all var(--transition); }
    .stat-card:hover { border-color: var(--green-neon); transform: translateY(-2px); }
    .stat-icon { width: 40px; height: 40px; background: rgba(34,197,94,0.1); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; }
    .stat-info { }
    .stat-value { font-size: 1.75rem; font-weight: 700; }
    .stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

    .content-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
    .card-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); }
    .card-title { font-size: 0.95rem; font-weight: 600; }
    .search-input { padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.85rem; width: 200px; }
    .search-input:focus { outline: none; border-color: var(--green-neon); }

    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; background: var(--bg-elevated); border-bottom: 1px solid var(--border-subtle); }
    .data-table td { padding: 0.875rem 1rem; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle); }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: var(--bg-elevated); }
    .company-name { font-weight: 600; color: var(--text-primary); }
    .company-industry { color: var(--text-muted); font-size: 0.8rem; }
    .status-badge { display: inline-block; padding: 0.2rem 0.5rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
    .status-badge.active { background: rgba(34,197,94,0.15); color: var(--green-neon); }
    .status-badge.inactive { background: rgba(248,113,113,0.15); color: #F87171; }
    .filter-bar { display: flex; gap: 0.5rem; align-items: center; }
    .filter-select { padding: 0.4rem 0.6rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.8rem; }
    .filter-select:focus { outline: none; border-color: var(--green-neon); }
    .th-sortable { cursor: pointer; user-select: none; }
    .th-sortable:hover { color: var(--green-neon); }
    .sort-icon { opacity: 0.5; margin-left: 0.25rem; }
    .th-sortable.sorted .sort-icon { opacity: 1; color: var(--green-neon); }

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
      .stats-row { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div id="toast-container" class="toast-container"></div>

<div id="modal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title" id="modal-title">Add Company</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <form id="modal-form">
      <input type="hidden" name="id" id="company-id" value="">
      <div class="form-group"><label class="form-label">Company Name</label><input type="text" name="name" id="company-name" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Industry</label><input type="text" name="industry" id="company-industry" class="form-control" list="industry-list"></div>
      <datalist id="industry-list"><?php foreach($industries as $ind): ?><option value="<?= e($ind) ?>"><?php endforeach; ?></datalist>
      <div class="form-group"><label class="form-label">Website</label><input type="url" name="website" id="company-website" class="form-control"></div>
      <div class="form-group"><label class="form-label">Location</label><input type="text" name="location" id="company-location" class="form-control"></div>
      <div class="form-group"><label class="form-label">Contact Person</label><input type="text" name="contact_person" id="company-contact_person" class="form-control"></div>
      <div class="form-group"><label class="form-label">Contact Email</label><input type="email" name="contact_email" id="company-contact_email" class="form-control"></div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" id="company-status" class="form-control">
          <option value="active">Active</option>
          <option value="inactive">Inactive</option>
        </select>
      </div>
      <div class="form-row">
        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="modal-submit">Save</button>
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
        <a href="admin_students.php" class="nav-item"><span class="icon">👥</span> Students</a>
        <a href="admin_companies.php" class="nav-item active"><span class="icon">🏢</span> Companies</a>
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
        <h1 class="page-title">Companies <span>Management</span></h1>
        <p class="page-subtitle">Manage all companies and partners</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-primary" onclick="openModal()">+ Add Company</button>
      </div>
    </div>

    <div class="stats-row">
      <div class="stat-card">
        <div class="stat-icon">🏢</div>
        <div class="stat-info">
          <div class="stat-value"><?= $totalCompanies ?></div>
          <div class="stat-label">Total Companies</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">✓</div>
        <div class="stat-info">
          <div class="stat-value"><?= $activeCompanies ?></div>
          <div class="stat-label">Active Companies</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-icon">💼</div>
        <div class="stat-info">
          <div class="stat-value"><?= $totalInternships ?></div>
          <div class="stat-label">Total Internships</div>
        </div>
      </div>
    </div>

    <div class="content-card">
      <div class="card-header">
        <div class="filter-bar">
          <h3 class="card-title">All Companies (<?= $totalCompanies ?>)</h3>
          <select class="filter-select" onchange="applyFilters()">
            <option value="">All Industries</option>
            <?php foreach($industries as $ind): ?>
            <option value="<?= e($ind) ?>" <?= $industryFilter === $ind ? 'selected' : '' ?>><?= e($ind) ?></option>
            <?php endforeach; ?>
          </select>
          <select class="filter-select" onchange="applyFilters()">
            <option value="">All Status</option>
            <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
            <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
          </select>
        </div>
        <div class="filter-bar">
          <input type="text" class="search-input" placeholder="Search..." onkeyup="filterTable(this.value)">
          <button type="button" class="btn btn-secondary" onclick="exportCSV()">Export CSV</button>
        </div>
      </div>
      <table class="data-table" id="companies-table">
        <thead>
          <tr>
            <th class="th-sortable" onclick="sortTable('id')">ID <span class="sort-icon">↕</span></th>
            <th class="th-sortable" onclick="sortTable('name')">Company <span class="sort-icon">↕</span></th>
            <th class="th-sortable" onclick="sortTable('industry')">Industry <span class="sort-icon">↕</span></th>
            <th class="th-sortable" onclick="sortTable('location')">Location <span class="sort-icon">↕</span></th>
            <th>Contact</th>
            <th class="th-sortable" onclick="sortTable('internship_count')">Internships <span class="sort-icon">↕</span></th>
            <th class="th-sortable" onclick="sortTable('status')">Status <span class="sort-icon">↕</span></th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="companies-tbody" data-companies='<?= json_encode(array_map(fn($c) => [
          'id' => $c['id'],
          'name' => $c['name'],
          'industry' => $c['industry'] ?? '',
          'location' => $c['location'] ?? '',
          'contact_person' => $c['contact_person'] ?? '',
          'contact_email' => $c['contact_email'] ?? '',
          'website' => $c['website'] ?? '',
          'status' => $c['status'] ?? 'active',
          'internship_count' => $c['internship_count']
        ], $companies)) ?>'>
          <?php if($companies): foreach($companies as $c): $status = $c['status'] ?? 'active'; ?>
          <tr data-id="<?= $c['id'] ?>" data-name="<?= e($c['name']) ?>" data-industry="<?= e($c['industry'] ?? '') ?>" data-location="<?= e($c['location'] ?? '') ?>" data-contact="<?= e($c['contact_person'] ?? '') ?> <?= e($c['contact_email'] ?? '') ?>" data-count="<?= $c['internship_count'] ?>" data-status="<?= $status ?>">
            <td><?= $c['id'] ?></td>
            <td>
              <div class="company-name"><?= e($c['name']) ?></div>
              <?php if($c['website']): ?>
              <div class="company-industry"><a href="<?= e($c['website']) ?>" target="_blank" style="color:var(--green-neon);text-decoration:none"><?= e($c['website']) ?></a></div>
              <?php endif; ?>
            </td>
            <td><?= e($c['industry'] ?? '-') ?></td>
            <td><?= e($c['location'] ?? '-') ?></td>
            <td><?= e($c['contact_person'] ?? '-') ?><br><small><?= e($c['contact_email'] ?? '') ?></small></td>
            <td><?= $c['internship_count'] ?></td>
            <td><span class="status-badge <?= $status ?>"><?= $status ?></span></td>
            <td>
              <button class="btn btn-secondary action-btn" onclick='editCompany(<?= json_encode($c) ?>)'>Edit</button>
              <button class="btn btn-secondary action-btn" onclick="deleteCompany(<?= $c['id'] ?>)">Delete</button>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8" class="empty-message">No companies found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<script>
const App = { csrfToken: '<?= $csrf ?>', sortCol: null, sortDir: 'asc' };

function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = '<span>' + msg + '</span>';
  c.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

function openModal(isEdit = false) {
  document.getElementById('modal-title').textContent = isEdit ? 'Edit Company' : 'Add Company';
  document.getElementById('modal-submit').textContent = isEdit ? 'Update' : 'Save';
  document.getElementById('modal').classList.add('show');
}

function closeModal() {
  document.getElementById('modal').classList.remove('show');
  document.getElementById('modal-form').reset();
  document.getElementById('company-id').value = '';
}

function filterTable(query) {
  const tbody = document.getElementById('companies-tbody');
  const rows = tbody.querySelectorAll('tr');
  query = query.toLowerCase();
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(query) ? '' : 'none';
  });
}

function applyFilters() {
  const industry = document.querySelector('.filter-select:first-of-type')?.value || '';
  const status = document.querySelector('.filter-select:nth-of-type(2)')?.value || '';
  const params = new URLSearchParams();
  if (industry) params.set('industry', industry);
  if (status) params.set('status', status);
  window.location.search = params.toString() || '?';
}

function sortTable(col) {
  const tbody = document.getElementById('companies-tbody');
  const rows = Array.from(tbody.querySelectorAll('tr[data-id]'));
  if (App.sortCol === col) App.sortDir = App.sortDir === 'asc' ? 'desc' : 'asc';
  else { App.sortCol = col; App.sortDir = 'asc'; }

  document.querySelectorAll('.th-sortable').forEach(th => th.classList.remove('sorted'));
  document.querySelector(`.th-sortable[onclick="sortTable('${col}')"]`)?.classList.add('sorted');

  rows.sort((a, b) => {
    let av = a.getAttribute('data-' + col) || a.cells[col === 'internship_count' ? 5 : col === 'name' ? 1 : col === 'industry' ? 2 : col === 'location' ? 3 : col === 'status' ? 6 : 0].textContent;
    let bv = b.getAttribute('data-' + col) || b.cells[col === 'internship_count' ? 5 : col === 'name' ? 1 : col === 'industry' ? 2 : col === 'location' ? 3 : col === 'status' ? 6 : 0].textContent;
    if (col === 'internship_count') { av = parseInt(av) || 0; bv = parseInt(bv) || 0; return App.sortDir === 'asc' ? av - bv : bv - av; }
    av = av.toLowerCase(); bv = bv.toLowerCase();
    return App.sortDir === 'asc' ? av.localeCompare(bv) : bv.localeCompare(av);
  });
  rows.forEach(r => tbody.appendChild(r));
}

function exportCSV() {
  const rows = document.querySelectorAll('#companies-tbody tr[data-id]');
  let csv = 'ID,Company,Industry,Location,Contact,Contact Email,Internships,Status\n';
  rows.forEach(r => {
    if (r.style.display !== 'none') {
      const cells = r.querySelectorAll('td');
      csv += Array.from(cells).map((c, i) => {
        let v = c.textContent.trim().replace(/"/g, '""');
        return i === 6 ? '' : (i === 1 ? `"${c.querySelector('.company-name')?.textContent || v}"` : `"${v}"`);
      }).join(',') + '\n';
    }
  });
  const blob = new Blob([csv], { type: 'text/csv' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'companies_' + new Date().toISOString().split('T')[0] + '.csv';
  a.click();
}

document.getElementById('modal-form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const id = fd.get('id');
  fd.append('action', id ? 'update_company' : 'add_company');
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

function editCompany(data) {
  if (typeof data === 'string') data = JSON.parse(data.replace(/&quot;/g, '"'));
  document.getElementById('company-id').value = data.id;
  document.getElementById('company-name').value = data.name || '';
  document.getElementById('company-industry').value = data.industry || '';
  document.getElementById('company-website').value = data.website || '';
  document.getElementById('company-location').value = data.location || '';
  document.getElementById('company-contact_person').value = data.contact_person || '';
  document.getElementById('company-contact_email').value = data.contact_email || '';
  document.getElementById('company-status').value = data.status || 'active';
  openModal(true);
}

function deleteCompany(id) {
  if (confirm('Delete this company?')) {
    const fd = new FormData();
    fd.append('action', 'delete_company');
    fd.append('id', id);
    fd.append('csrf_token', App.csrfToken);
    fetch('admin.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        toast(data.message, data.success ? 'success' : 'error');
        if (data.success) setTimeout(() => location.reload(), 500);
      });
  }
}

async function handleLogout() {
  await fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
  window.location.href = 'admin_login.php';
}
</script>
</body>
</html>