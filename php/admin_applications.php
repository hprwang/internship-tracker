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
$filter = $_GET['filter'] ?? 'all';

// Get applications - all internships that have status applied or interview
$statusWhere = match($filter) {
    'pending' => "i.status = 'applied'",
    'interview' => "i.status = 'interview'",
    default => "i.status IN ('applied', 'interview')"
};

$applications = $db->query("
    SELECT i.*, u.full_name as student_name, u.email as student_email, c.name as company_name, c.industry as company_industry
    FROM internships i
    LEFT JOIN users u ON i.student_id = u.id
    LEFT JOIN companies c ON i.company_id = c.id
    WHERE $statusWhere
    ORDER BY i.created_at DESC
")->fetchAll();

// Stats for all applications
$allApps = $db->query("
    SELECT i.* FROM internships i WHERE i.status IN ('applied', 'interview')
")->fetchAll();
$total = count($allApps);
$pending = count(array_filter($allApps, fn($a) => $a['status'] === 'applied'));
$interview = count(array_filter($allApps, fn($a) => $a['status'] === 'interview'));
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Applications</title>
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

    .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; text-align: center; transition: all var(--transition); }
    .stat-card:hover { border-color: var(--green-neon); transform: translateY(-2px); }
    .stat-value { font-size: 2rem; font-weight: 700; }
    .stat-value.all { color: var(--text-primary); }
    .stat-value.pending { color: #F59E0B; }
    .stat-value.interview { color: #8B5CF6; }
    .stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

    .content-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
    .card-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); }
    .card-title { font-size: 0.95rem; font-weight: 600; }
    .search-input { padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.85rem; width: 200px; }
    .search-input:focus { outline: none; border-color: var(--green-neon); }

    .filter-tabs { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; }
    .filter-tab { padding: 0.5rem 1rem; border-radius: var(--radius-md); font-size: 0.8rem; font-weight: 500; color: var(--text-secondary); background: var(--bg-card); border: 1px solid var(--border-subtle); cursor: pointer; transition: all var(--transition); text-decoration: none; }
    .filter-tab:hover { border-color: var(--green-neon); color: var(--text-primary); }
    .filter-tab.active { background: var(--green-neon); color: var(--bg-deep); border-color: var(--green-neon); }

    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; background: var(--bg-elevated); border-bottom: 1px solid var(--border-subtle); }
    .data-table td { padding: 0.875rem 1rem; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle); vertical-align: middle; }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: var(--bg-elevated); }

    .status-badge { display: inline-flex; padding: 0.2rem 0.5rem; border-radius: 999px; font-size: 0.7rem; font-weight: 600; text-transform: capitalize; }
    .status-badge.applied { background: rgba(245,158,11,0.15); color: #F59E0B; }
    .status-badge.interview { background: rgba(139,92,246,0.15); color: #8B5CF6; }

    .company-badge { display: inline-flex; align-items: center; gap: 0.375rem; }
    .company-badge .industry-dot { width: 6px; height: 6px; border-radius: 50%; background: var(--green-neon); }

    .action-btn-group { display: flex; gap: 0.25rem; }
    .btn-accept { background: rgba(34,197,94,0.15); color: var(--green-neon); border: 1px solid transparent; }
    .btn-accept:hover { background: var(--green-neon); color: var(--bg-deep); }
    .btn-reject { background: rgba(239,68,68,0.15); color: #F87171; border: 1px solid transparent; }
    .btn-reject:hover { background: #F87171; color: white; }
    .btn-interview { background: rgba(139,92,246,0.15); color: #8B5CF6; border: 1px solid transparent; }
    .btn-interview:hover { background: #8B5CF6; color: white; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: var(--radius-md); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; }
    .btn-primary { background: var(--green-neon); color: var(--bg-deep); }
    .btn-primary:hover { background: var(--green-glow); box-shadow: 0 0 20px rgba(34,197,94,0.4); }
    .btn-secondary { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-subtle); }
    .btn-secondary:hover { border-color: var(--green-neon); color: var(--green-neon); }
    .action-btn { padding: 0.375rem 0.625rem; font-size: 0.75rem; border-radius: var(--radius-sm); margin-right: 0.25rem; }
    .empty-message { padding: 2.5rem; text-align: center; color: var(--text-muted); }

    .toast-container { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); animation: slideIn 0.3s ease; font-size: 0.85rem; }
    .toast.success { border-color: var(--green-neon); }
    .toast.error { border-color: #F87171; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    @media (max-width: 900px) {
      .admin-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .main-content { padding: 1rem; }
      .stats-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div id="toast-container" class="toast-container"></div>

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
        <a href="admin_companies.php" class="nav-item"><span class="icon">🏢</span> Companies</a>
        <a href="admin_internships.php" class="nav-item"><span class="icon">💼</span> Internships</a>
        <a href="admin_applications.php" class="nav-item active"><span class="icon">📝</span> Applications</a>
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
        <h1 class="page-title">Applications <span>Review</span></h1>
        <p class="page-subtitle">Review and manage job applications</p>
      </div>
    </div>

    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-value all"><?= $total ?></div>
        <div class="stat-label">Total Applications</div>
      </div>
      <div class="stat-card">
        <div class="stat-value pending"><?= $pending ?></div>
        <div class="stat-label">Pending</div>
      </div>
      <div class="stat-card">
        <div class="stat-value interview"><?= $interview ?></div>
        <div class="stat-label">Interview</div>
      </div>
    </div>

    <div class="content-card">
      <div class="filter-tabs">
        <a href="?filter=all" class="filter-tab <?= $filter === 'all' ? 'active' : '' ?>">All (<?= $total ?>)</a>
        <a href="?filter=pending" class="filter-tab <?= $filter === 'pending' ? 'active' : '' ?>">Pending (<?= $pending ?>)</a>
        <a href="?filter=interview" class="filter-tab <?= $filter === 'interview' ? 'active' : '' ?>">Interview (<?= $interview ?>)</a>
      </div>

      <table class="data-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Student</th>
            <th>Company</th>
            <th>Position</th>
            <th>Status</th>
            <th>Applied</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="applications-tbody">
          <?php if($applications): foreach($applications as $a): ?>
          <tr data-id="<?= $a['id'] ?>">
            <td>#<?= $a['id'] ?></td>
            <td>
              <div><?= e($a['student_name'] ?? '-') ?></div>
              <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($a['student_email'] ?? '-') ?></div>
            </td>
            <td>
              <div class="company-badge">
                <span class="industry-dot"></span>
                <?= e($a['company_name'] ?? '-') ?>
              </div>
              <div style="font-size: 0.75rem; color: var(--text-muted);"><?= e($a['company_industry'] ?? '') ?></div>
            </td>
            <td><?= e($a['title']) ?></td>
            <td><span class="status-badge <?= e($a['status']) ?>"><?= e($a['status']) ?></span></td>
            <td><?= date('M d, Y', strtotime($a['created_at'])) ?></td>
            <td>
              <div class="action-btn-group">
                <button class="btn btn-accept action-btn" onclick="reviewApplication(<?= $a['id'] ?>, 'accepted')">Accept</button>
                <button class="btn btn-interview action-btn" onclick="reviewApplication(<?= $a['id'] ?>, 'interview')">Interview</button>
                <button class="btn btn-reject action-btn" onclick="reviewApplication(<?= $a['id'] ?>, 'rejected')">Reject</button>
              </div>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8" class="empty-message">No applications found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<script>
const App = { csrfToken: '<?= $csrf ?>' };

function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = '<span>' + msg + '</span>';
  c.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

function reviewApplication(id, status) {
  const statusText = status === 'accepted' ? 'accept' : status === 'interview' ? 'schedule interview for' : 'reject';
  if (!confirm(`Are you sure you want to ${statusText} application #${id}?`)) return;

  const fd = new FormData();
  fd.append('action', 'update_internship_status');
  fd.append('id', id);
  fd.append('status', status);
  fd.append('csrf_token', App.csrfToken);
  fetch('admin.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      toast(data.message, data.success ? 'success' : 'error');
      if (data.success) setTimeout(() => location.reload(), 500);
    });
}

async function handleLogout() {
  await fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
  window.location.href = 'admin_login.php';
}
</script>
</body>
</html>