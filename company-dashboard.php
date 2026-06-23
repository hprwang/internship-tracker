<?php
session_start();
require_once __DIR__ . '/php/config.php';
$user = requireAuth();
// Allow admins to access company dashboard (even without company_id assigned yet)
$role = $user['role'] ?? '';
if ($role !== 'admin') {
    http_response_code(403);
    die('<h3>Access Denied</h3><p>Company access required.</p>');
}
$companyId = $user['company_id'] ?? null;

$csrf = generateCSRF();
$db = Database::getCompanyConnection();

// Get company info (if company_id is assigned)
if ($companyId) {
    $stmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$companyId]);
    $company = $stmt->fetch() ?: ['name' => 'Company', 'status' => 'active'];
} else {
    $company = ['name' => 'Your Company', 'status' => 'active'];
}

// Get company internships stats
$totalInternships = 0;
$activeInternships = 0;
$completedInternships = 0;
$pendingApplications = 0;

if ($companyId) {
    $stmt = $db->prepare("SELECT COUNT(*) as c FROM internships WHERE company_id = ?");
    $stmt->execute([$companyId]);
    $totalInternships = $stmt->fetch()['c'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as c FROM internships WHERE company_id = ? AND status = 'active'");
    $stmt->execute([$companyId]);
    $activeInternships = $stmt->fetch()['c'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as c FROM internships WHERE company_id = ? AND status = 'closed'");
    $stmt->execute([$companyId]);
    $completedInternships = $stmt->fetch()['c'] ?? 0;

    $stmt = $db->prepare("SELECT COUNT(*) as c FROM internships WHERE company_id = ? AND status = 'pending'");
    $stmt->execute([$companyId]);
    $pendingApplications = $stmt->fetch()['c'] ?? 0;
}

// Get company's posted internships
if ($companyId) {
    $stmt = $db->prepare("
        SELECT i.*, u.full_name as student_name
        FROM internships i
        LEFT JOIN users u ON i.student_id = u.id
        WHERE i.company_id = ?
        ORDER BY i.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$companyId]);
    $internships = $stmt->fetchAll();

    // Get activity
    $stmt = $db->prepare("
        SELECT al.*, u.full_name
        FROM activity_log al
        LEFT JOIN users u ON al.user_id = u.id
        WHERE al.entity_type = 'internship' AND al.entity_id IN (SELECT id FROM internships WHERE company_id = ?)
        ORDER BY al.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$companyId]);
    $activities = $stmt->fetchAll();
} else {
    $internships = [];
    $activities = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>Company Dashboard — InternTrack</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root { --bg-deep: #0D0D0D; --bg-charcoal: #141414; --bg-panel: #1A1A1A; --bg-card: #1F1F1F; --bg-elevated: #252525; --border-subtle: #2A2A2A; --border-light: #333333; --green-primary: #00C853; --green-emerald: #00E676; --green-muted: #69F0AE; --text-primary: #FFFFFF; --text-secondary: #B0B0B0; --text-muted: #707070; --glass-bg: rgba(30,30,30,0.7); --glass-border: rgba(255,255,255,0.08); --shadow-soft: 0 4px 24px rgba(0,0,0,0.4); --shadow-glow: 0 8px 32px rgba(0,200,83,0.15); --radius-sm: 10px; --radius-md: 14px; --radius-lg: 18px; --transition: 280ms cubic-bezier(.4,0,.2,1); }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Outfit', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; }
    .dashboard-layout { display: grid; grid-template-columns: 270px 1fr; min-height: 100vh; }
    .sidebar { background: linear-gradient(180deg, var(--bg-charcoal) 0%, #0F0F0F 100%); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.85rem; padding: 0.5rem 0.75rem 1.75rem; margin-bottom: 1.5rem; text-decoration: none; }
    .logo-icon { width: 44px; height: 44px; background: linear-gradient(135deg, var(--green-primary), var(--green-emerald)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; box-shadow: 0 0 28px rgba(0,200,83,0.35); }
    .logo-text { font-size: 1.35rem; font-weight: 800; background: linear-gradient(135deg, var(--text-primary), var(--green-muted)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
    .nav-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.6rem; text-decoration: none; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.3rem; }
    .nav-item { display: flex; align-items: center; gap: 0.85rem; padding: 0.8rem 1rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: rgba(255,255,255,0.04); color: var(--text-primary); transform: translateX(4px); }
    .nav-item.active { background: linear-gradient(135deg, rgba(0,200,83,0.12), rgba(0,200,83,0.05)); color: var(--green-primary); border: 1px solid rgba(0,200,83,0.2); box-shadow: 0 0 20px rgba(0,200,83,0.1); }
    .sidebar-footer { margin-top: auto; padding-top: 1.25rem; border-top: 1px solid var(--border-subtle); }
    .company-card { display: flex; align-items: center; gap: 0.75rem; padding: 0.9rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); margin-bottom: 0.75rem; }
    .company-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, var(--green-primary), var(--green-emerald)); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1rem; color: var(--bg-deep); }
    .company-info { flex: 1; }
    .company-name { font-size: 0.9rem; font-weight: 600; }
    .company-verified { font-size: 0.7rem; color: var(--green-primary); }
    .logout-btn { display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.7rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.85rem; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }
    .main-content { background: var(--bg-deep); padding: 1.75rem 2rem; overflow-y: auto; }
    .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .welcome-section h1 { font-size: 1.65rem; font-weight: 700; margin-bottom: 0.2rem; }
    .welcome-section h1 span { color: var(--green-primary); }
    .welcome-section p { color: var(--text-muted); font-size: 0.9rem; }
    .header-actions { display: flex; align-items: center; gap: 0.75rem; }
    .search-box { display: flex; align-items: center; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.55rem 1rem; gap: 0.65rem; min-width: 260px; }
    .search-box input { background: none; border: none; outline: none; color: var(--text-primary); font-size: 0.875rem; width: 100%; }
    .search-box input::placeholder { color: var(--text-muted); }
    .icon-btn { width: 42px; height: 42px; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all var(--transition); color: var(--text-secondary); font-size: 1rem; }
    .icon-btn:hover { border-color: var(--green-primary); color: var(--green-primary); }
    .profile-btn { width: 42px; height: 42px; background: linear-gradient(135deg, var(--green-primary), var(--green-emerald)); border: none; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; cursor: pointer; font-weight: 700; color: var(--bg-deep); font-size: 0.95rem; }
    .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1.25rem; margin-bottom: 2rem; }
    .kpi-card { background: var(--glass-bg); backdrop-filter: blur(20px); border: 1px solid var(--glass-border); border-radius: var(--radius-lg); padding: 1.5rem; transition: all var(--transition); position: relative; overflow: hidden; }
    .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--green-primary), var(--green-emerald)); opacity: 0; transition: opacity var(--transition); }
    .kpi-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-glow); border-color: rgba(0,200,83,0.3); }
    .kpi-card:hover::before { opacity: 1; }
    .kpi-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; }
    .kpi-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; }
    .kpi-icon { width: 40px; height: 40px; background: linear-gradient(135deg, rgba(0,200,83,0.15), rgba(0,200,83,0.05)); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: var(--green-primary); font-size: 1.1rem; border: 1px solid rgba(0,200,83,0.15); }
    .kpi-value { font-size: 2.35rem; font-weight: 700; margin-bottom: 0.15rem; }
    .kpi-trend { font-size: 0.75rem; font-weight: 600; }
    .kpi-trend.up { color: var(--green-primary); }
    .content-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 1.5rem; }
    .card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
    .card-header { display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .card-title { font-size: 1rem; font-weight: 600; }
    .card-action { color: var(--green-primary); font-size: 0.85rem; text-decoration: none; }
    .card-action:hover { text-decoration: underline; }
    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 1rem 1.5rem; font-size: 0.7rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-subtle); }
    .data-table td { padding: 1rem 1.5rem; font-size: 0.9rem; border-bottom: 1px solid var(--border-subtle); }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: var(--bg-elevated); }
    .table-title { font-weight: 500; }
    .table-meta { color: var(--text-muted); font-size: 0.8rem; margin-top: 0.25rem; }
    .status-badge { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.3rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
    .status-badge.ongoing, .status-badge.active { background: rgba(0,200,83,0.1); color: var(--green-primary); }
    .status-badge.applied, .status-badge.pending { background: rgba(251,191,36,0.1); color: #FBBF24; }
    .status-badge.completed { background: rgba(59,130,246,0.1); color: #3B82F6; }
    .status-badge.ongoing::before, .status-badge.active::before, .status-badge.applied::before, .status-badge.pending::before, .status-badge.completed::before { content: ''; width: 6px; height: 6px; background: currentColor; border-radius: 50%; }
    .activity-list { padding: 0.5rem; }
    .activity-item { display: flex; gap: 1rem; padding: 1rem; border-radius: var(--radius-md); transition: all var(--transition); }
    .activity-item:hover { background: var(--bg-elevated); }
    .activity-icon { width: 36px; height: 36px; border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-size: 0.85rem; flex-shrink: 0; }
    .activity-icon.apply { background: rgba(0,200,83,0.1); color: var(--green-primary); }
    .activity-icon.progress { background: rgba(59,130,246,0.1); color: #3B82F6; }
    .activity-icon.update { background: rgba(168,85,247,0.1); color: #A855F7; }
    .activity-icon.feedback { background: rgba(251,191,36,0.1); color: #FBBF24; }
    .activity-content { flex: 1; min-width: 0; }
    .activity-title { font-size: 0.9rem; font-weight: 500; margin-bottom: 0.2rem; }
    .activity-meta { color: var(--text-muted); font-size: 0.8rem; }
    .empty-state { text-align: center; padding: 3rem 1.5rem; color: var(--text-muted); }
    .empty-state i { font-size: 2.5rem; margin-bottom: 1rem; opacity: 0.5; }
    @media (max-width: 1200px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } .content-grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) { .sidebar { display: none; } .dashboard-layout { grid-template-columns: 1fr; } .kpi-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="dashboard-layout">
    <aside class="sidebar">
      <a href="company-dashboard.php" class="sidebar-logo">
        <div class="logo-icon">📋</div>
        <div class="logo-text">Intern<span style="color:var(--green-primary)">Track</span></div>
      </a>

      <div class="nav-label">Main Menu</div>
      <nav class="nav-menu">
        <a href="company-dashboard.php" class="nav-item active"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="company-internships.php" class="nav-item"><i class="fas fa-briefcase"></i> Posted Internships</a>
        <a href="company-applicants.php" class="nav-item"><i class="fas fa-users"></i> Applicants</a>
        <a href="company-monitoring.php" class="nav-item"><i class="fas fa-chart-line"></i> Monitoring</a>
        <a href="company-messages.php" class="nav-item"><i class="fas fa-envelope"></i> Messages</a>
        <a href="company-analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a>
        <a href="company-reports.php" class="nav-item"><i class="fas fa-file-alt"></i> Reports</a>
        <a href="company-settings.php" class="nav-item"><i class="fas fa-cog"></i> Settings</a>
      </nav>

      <div class="sidebar-footer">
        <div class="company-card">
          <div class="company-avatar"><?= strtoupper(substr(e($company['name']), 0, 2)) ?></div>
          <div class="company-info">
            <div class="company-name"><?= e($company['name']) ?></div>
            <div class="company-verified"><i class="fas fa-check-circle"></i> <?= ucfirst($company['status']) ?></div>
          </div>
        </div>
        <button class="logout-btn" onclick="handleLogout()"><i class="fas fa-sign-out-alt"></i> Logout</button>
      </div>
    </aside>

    <main class="main-content">
      <header class="top-header">
        <div class="welcome-section">
          <h1>Welcome back, <span><?= e($company['name']) ?></span></h1>
          <p>Manage your internship programs and track applicants in one place</p>
        </div>
        <div class="header-actions">
          <div class="search-box">
            <i class="fas fa-search"></i>
            <input type="text" placeholder="Search internships, applicants...">
          </div>
          <button class="icon-btn"><i class="fas fa-bell"></i></button>
          <button class="icon-btn"><i class="fas fa-cog"></i></button>
        </div>
      </header>

      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-header">
            <div class="kpi-label">Total Internships</div>
            <div class="kpi-icon"><i class="fas fa-briefcase"></i></div>
          </div>
          <div class="kpi-value"><?= $totalInternships ?></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-header">
            <div class="kpi-label">Active</div>
            <div class="kpi-icon"><i class="fas fa-play"></i></div>
          </div>
          <div class="kpi-value"><?= $activeInternships ?></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-header">
            <div class="kpi-label">Completed</div>
            <div class="kpi-icon"><i class="fas fa-check-circle"></i></div>
          </div>
          <div class="kpi-value"><?= $completedInternships ?></div>
        </div>
        <div class="kpi-card">
          <div class="kpi-header">
            <div class="kpi-label">Pending</div>
            <div class="kpi-icon"><i class="fas fa-clock"></i></div>
          </div>
          <div class="kpi-value"><?= $pendingApplications ?></div>
        </div>
      </div>

      <div class="content-grid">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Posted Internships</h3>
            <a href="#" class="card-action">View All</a>
          </div>
          <?php if (empty($internships)): ?>
            <div class="empty-state">
              <i class="fas fa-briefcase"></i>
              <p>No internships posted yet</p>
            </div>
          <?php else: ?>
            <table class="data-table">
              <thead>
                <tr>
                  <th>Position</th>
                  <th>Student</th>
                  <th>Status</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($internships as $internship): ?>
                  <tr>
                    <td>
                      <div class="table-title"><?= e($internship['title'] ?? 'N/A') ?></div>
                      <div class="table-meta"><?= e($internship['location'] ?? 'Remote') ?></div>
                    </td>
                    <td><?= e($internship['student_name'] ?? 'Unassigned') ?></td>
                    <td><span class="status-badge <?= $internship['status'] ?? 'applied' ?>"><?= ucfirst($internship['status'] ?? 'applied') ?></span></td>
                    <td><?= $internship['start_date'] ? date('M d, Y', strtotime($internship['start_date'])) : '-' ?></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          <?php endif; ?>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Recent Activity</h3>
          </div>
          <?php if (empty($activities)): ?>
            <div class="empty-state">
              <i class="fas fa-history"></i>
              <p>No recent activity</p>
            </div>
          <?php else: ?>
            <div class="activity-list">
              <?php foreach ($activities as $activity): ?>
                <div class="activity-item">
                  <div class="activity-icon <?= in_array($activity['action'], ['create', 'apply']) ? 'apply' : (in_array($activity['action'], ['update', 'complete']) ? 'progress' : 'update') ?>">
                    <i class="fas fa-<?= $activity['action'] === 'create' ? 'plus' : ($activity['action'] === 'apply' ? 'user-plus' : ($activity['action'] === 'complete' ? 'check' : 'edit')) ?>"></i>
                  </div>
                  <div class="activity-content">
                    <div class="activity-title"><?= e(ucfirst($activity['action'])) ?></div>
                    <div class="activity-meta"><?= $activity['created_at'] ? date('M d, Y g:i A', strtotime($activity['created_at'])) : 'Recently' ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <script>
    async function handleLogout() {
      try {
        await fetch('php/auth.php', {
          method: 'POST',
          body: new URLSearchParams({ action: 'logout' })
        });
      } catch (e) {
        console.error('Logout error:', e);
      }
      window.location.href = 'company-login.php';
    }
  </script>
</body>
</html>