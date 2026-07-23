<?php
session_start();
require_once __DIR__ . '/config.php';
$user = requireAuth();
if (!in_array($user['role'] ?? '', ['admin', 'super_admin'])) {
    http_response_code(403);
    die('<h3>Access Denied</h3><p>Admin access required.</p>');
}
if (!function_exists('e')) {
    function e(string $value): string {
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
      --shadow-glow: 0 8px 32px rgba(34,197,94,0.2);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
      --transition: 250ms cubic-bezier(.4,0,.2,1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.5; }

    .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }

    /* Sidebar */
    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 0 24px rgba(34,197,94,0.3); animation: pulse 3s ease-in-out infinite; }
    .logo-text { font-size: 1.3rem; font-weight: 800; color: var(--text-primary); }
    .logo-text span { color: var(--green-neon); }

    .nav-section { margin-bottom: 2rem; }
    .nav-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 0.875rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); transform: translateX(2px); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3); }
    .nav-item .icon { font-size: 1rem; width: 20px; text-align: center; }

    .sidebar-footer { margin-top: auto; padding-top: 1.25rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); transition: all var(--transition); }
    .user-chip:hover { border-color: var(--green-neon); }
    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; color: var(--bg-deep); }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.875rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.7rem; color: var(--text-muted); }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 0.875rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.875rem; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; margin-top: 0.5rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); transform: translateX(2px); }

    /* Main Content */
    .main-content { background: var(--bg-deep); padding: 2rem 2.5rem; overflow-y: auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); position: relative; }
    .page-header::after { content: ''; position: absolute; bottom: -1px; left: 0; width: 120px; height: 2px; background: linear-gradient(90deg, var(--green-neon), transparent); }
    .page-title { font-size: 1.75rem; font-weight: 700; letter-spacing: -0.02em; }
    .page-title span { color: var(--green-neon); }
    .page-subtitle { font-size: 0.9rem; color: var(--text-muted); margin-top: 0.35rem; }
    .header-actions { display: flex; gap: 0.5rem; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.625rem 1.25rem; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; letter-spacing: -0.01em; }
    .btn-primary { background: var(--green-neon); color: var(--bg-deep); }
    .btn-primary:hover { background: var(--green-glow); box-shadow: 0 4px 16px rgba(34,197,94,0.35); transform: translateY(-1px); }
    .btn-secondary { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-subtle); }
    .btn-secondary:hover { border-color: var(--green-neon); color: var(--green-neon); background: rgba(34,197,94,0.08); }
    .btn-sm { padding: 0.375rem 0.75rem; font-size: 0.75rem; }

    /* Stats Grid */
    .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; transition: all var(--transition); position: relative; overflow: hidden; animation: cardSlideIn 0.5s ease-out backwards; }
    .stat-card:nth-child(1) { animation-delay: 0.05s; }
    .stat-card:nth-child(2) { animation-delay: 0.1s; }
    .stat-card:nth-child(3) { animation-delay: 0.15s; }
    .stat-card:nth-child(4) { animation-delay: 0.2s; }
    .stat-card:nth-child(5) { animation-delay: 0.25s; }
    .stat-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, var(--green-neon), var(--green-glow)); opacity: 0; transition: opacity var(--transition); }
    .stat-card::after { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(34,197,94,0.03) 0%, transparent 50%); opacity: 0; transition: opacity var(--transition); pointer-events: none; }
    .stat-card:hover { border-color: var(--green-neon); transform: translateY(-4px) scale(1.01); box-shadow: 0 12px 40px rgba(34,197,94,0.15), var(--shadow-glow); }
    .stat-card:hover::before { opacity: 1; }
    .stat-card:hover::after { opacity: 1; }
    .stat-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.75rem; }
    .stat-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.08em; font-weight: 600; }
    .stat-icon { width: 36px; height: 36px; background: linear-gradient(135deg, rgba(34,197,94,0.15), rgba(34,197,94,0.05)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1rem; border: 1px solid rgba(34,197,94,0.15); transition: all var(--transition); }
    .stat-card:hover .stat-icon { background: rgba(34,197,94,0.2); border-color: var(--green-neon); transform: scale(1.1); }
    .stat-value { font-size: 2rem; font-weight: 800; letter-spacing: -0.02em; transition: all var(--transition); }
    .stat-value.active { color: var(--green-neon); text-shadow: 0 0 20px rgba(34,197,94,0.4); }
    .stat-trend { display: flex; align-items: center; gap: 0.25rem; font-size: 0.7rem; font-weight: 600; margin-top: 0.5rem; }
    .stat-trend.up { color: var(--green-neon); }
    .stat-trend.down { color: #F87171; }

    /* Dashboard Grid */
    .dashboard-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
    .dash-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; transition: all var(--transition); position: relative; animation: cardSlideIn 0.5s ease-out backwards; }
    .dash-card:nth-child(1) { animation-delay: 0.3s; }
    .dash-card:nth-child(2) { animation-delay: 0.4s; }
    .dash-card:nth-child(3) { animation-delay: 0.5s; }
    .dash-card::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 3px; background: linear-gradient(90deg, var(--green-neon), var(--green-glow)); opacity: 0; transition: opacity var(--transition); }
    .dash-card::after { content: ''; position: absolute; inset: 0; background: linear-gradient(135deg, rgba(34,197,94,0.02) 0%, transparent 50%); opacity: 0; transition: opacity var(--transition); pointer-events: none; }
    .dash-card:hover { border-color: var(--green-neon); transform: translateY(-4px) scale(1.01); box-shadow: 0 16px 48px rgba(34,197,94,0.12), var(--shadow-glow); }
    .dash-card:hover::before { opacity: 1; }
    .dash-card:hover::after { opacity: 1; }
    .dash-card-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); background: var(--bg-elevated); position: relative; }
    .dash-card-header::after { content: ''; position: absolute; bottom: -1px; left: 1.5rem; width: 40px; height: 2px; background: linear-gradient(90deg, var(--green-neon), transparent); }
    .dash-card-title { font-size: 1rem; font-weight: 700; letter-spacing: -0.01em; }
    .dash-card-link { font-size: 0.8rem; color: var(--green-neon); text-decoration: none; font-weight: 500; transition: all var(--transition); padding: 0.25rem 0.5rem; border-radius: var(--radius-sm); }
    .dash-card-link:hover { text-decoration: none; background: rgba(34,197,94,0.1); }
    .dash-card-body { padding: 0; }

    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 0.875rem 1.25rem; font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; background: var(--bg-elevated); border-bottom: 1px solid var(--border-subtle); }
    .data-table td { padding: 1rem 1.25rem; font-size: 0.875rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle); transition: background var(--transition); }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tbody tr { transition: all var(--transition); }
    .data-table tbody tr:hover td { background: var(--bg-elevated); color: var(--text-primary); }

    .empty-message { padding: 2rem; text-align: center; color: var(--text-muted); font-size: 0.875rem; background: var(--bg-elevated); border-radius: var(--radius-md); margin: 0.5rem; }

    .status-badge { display: inline-flex; padding: 0.3rem 0.65rem; border-radius: 999px; font-size: 0.7rem; font-weight: 600; text-transform: capitalize; letter-spacing: 0.02em; }
    .status-badge.active { background: rgba(34,197,94,0.12); color: var(--green-neon); border: 1px solid rgba(34,197,94,0.25); }
    .status-badge.pending { background: rgba(245,158,11,0.12); color: #F59E0B; border: 1px solid rgba(245,158,11,0.25); }
    .status-badge.completed { background: rgba(96,165,250,0.12); color: #60A5FA; border: 1px solid rgba(96,165,250,0.25); }
    .status-badge.rejected { background: rgba(239,68,68,0.12); color: #F87171; border: 1px solid rgba(239,68,68,0.25); }

    /* Toast */
    .toast-container { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); animation: slideIn 0.3s ease; max-width: 320px; font-size: 0.85rem; }
    .toast.success { border-color: var(--green-neon); }
    .toast.error { border-color: #F87171; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes pulse { 0%, 100% { box-shadow: 0 0 20px rgba(34,197,94,0.25); } 50% { box-shadow: 0 0 32px rgba(34,197,94,0.45); } }
    @keyframes shimmer { from { background-position: -200% 0; } to { background-position: 200% 0; } }
    @keyframes cardSlideIn { from { opacity: 0; transform: translateY(16px) scale(0.96); } to { opacity: 1; transform: translateY(0) scale(1); } }
    @keyframes float { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
    @keyframes glowPulse { 0%, 100% { box-shadow: 0 0 20px rgba(34,197,94,0.2); } 50% { box-shadow: 0 0 36px rgba(34,197,94,0.4); } }

    /* Responsive */
    @media (max-width: 1200px) {
      .stats-grid { grid-template-columns: repeat(3, 1fr); }
      .dashboard-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 768px) {
      .admin-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .main-content { padding: 1.25rem; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); gap: 0.75rem; }
      .dashboard-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
<div id="toast-container" class="toast-container"></div>

<!-- Modal -->
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
      <div class="logo-icon"><i class="fas fa-clipboard-list"></i></div>
      <div class="logo-text">Intern<span>Track</span></div>
    </div>

    <div class="nav-section">
      <div class="nav-label">Dashboard</div>
      <nav class="nav-menu">
<a href="admin_dashboard.php" class="nav-item active"><span class="icon"><i class="fas fa-chart-pie"></i></span> Overview</a>
        <a href="admin_students.php" class="nav-item"><span class="icon"><i class="fas fa-users"></i></span> Students</a>
        <a href="admin_companies.php" class="nav-item"><span class="icon"><i class="fas fa-building"></i></span> Companies</a>
        <a href="admin_internships.php" class="nav-item"><span class="icon"><i class="fas fa-briefcase"></i></span> Internships</a>
        <a href="admin_reports.php" class="nav-item"><span class="icon"><i class="fas fa-chart-bar"></i></span> Reports</a>
      </nav>
    </div>

    <div class="nav-section">
      <div class="nav-label">System</div>
      <nav class="nav-menu">
        <a href="admin_settings.php" class="nav-item"><span class="icon"><i class="fas fa-cog"></i></span> Settings</a>
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

  <!-- Main Content -->
  <main class="main-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Admin <span>Dashboard</span></h1>
        <p class="page-subtitle">Overview of all students, companies, and internships</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary" onclick="location.reload()"><i class="fas fa-sync-alt"></i> Refresh</button>
      </div>
    </div>

    <!-- Stats Grid -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-header">
          <span class="stat-label">Total Students</span>
          <div class="stat-icon"><i class="fas fa-users"></i></div>
        </div>
        <div class="stat-value"><?= $totalStudents ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <span class="stat-label">Companies</span>
          <div class="stat-icon"><i class="fas fa-building"></i></div>
        </div>
        <div class="stat-value"><?= $totalCompanies ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <span class="stat-label">Active</span>
          <div class="stat-icon"><i class="fas fa-bolt"></i></div>
        </div>
        <div class="stat-value active"><?= $activeInternships ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <span class="stat-label">Completed</span>
          <div class="stat-icon"><i class="fas fa-check"></i></div>
        </div>
        <div class="stat-value" style="color:#60A5FA"><?= $completedInternships ?></div>
      </div>
      <div class="stat-card">
        <div class="stat-header">
          <span class="stat-label">Pending</span>
          <div class="stat-icon"><i class="fas fa-hourglass-half"></i></div>
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