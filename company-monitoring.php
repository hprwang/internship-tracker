<?php
session_start();
require_once __DIR__ . '/php/config.php';
$user = requireAuth();
$role = $user['role'] ?? '';
if ($role !== 'admin') {
    http_response_code(403);
    die('<h3>Access Denied</h3><p>Company access required.</p>');
}
$companyId = $user['company_id'] ?? null;

$db = Database::getCompanyConnection();
$company = $companyId ? $db->prepare("SELECT * FROM companies WHERE id = ?")->execute([$companyId])->fetch() : ['name' => 'Company', 'status' => 'active'];
if (!$company) $company = ['name' => 'Your Company', 'status' => 'active'];

// Get monitoring data
$activeInterns = 0;
$completedThisMonth = 0;
$avgProgress = 0;
if ($companyId) {
    $activeInterns = $db->prepare("SELECT COUNT(*) as c FROM internships WHERE company_id = ? AND status = 'active'")->execute([$companyId])->fetch()['c'] ?? 0;
    $completedThisMonth = $db->prepare("SELECT COUNT(*) as c FROM internships WHERE company_id = ? AND status = 'completed' AND updated_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->execute([$companyId])->fetch()['c'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Monitoring — InternTrack</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root { --bg-deep: #0D0D0D; --bg-charcoal: #141414; --bg-card: #1F1F1F; --bg-elevated: #252525; --border-subtle: #2A2A2A; --green-primary: #00C853; --green-emerald: #00E676; --green-muted: #69F0AE; --text-primary: #FFFFFF; --text-secondary: #B0B0B0; --text-muted: #707070; --radius-sm: 10px; --radius-md: 14px; --radius-lg: 18px; --transition: 280ms cubic-bezier(.4,0,.2,1); }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Outfit', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; }
    .dashboard-layout { display: grid; grid-template-columns: 270px 1fr; min-height: 100vh; }
    .sidebar { background: linear-gradient(180deg, var(--bg-charcoal) 0%, #0F0F0F 100%); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.85rem; padding: 0.5rem 0.75rem 1.75rem; margin-bottom: 1.5rem; text-decoration: none; }
    .logo-icon { width: 44px; height: 44px; background: linear-gradient(135deg, var(--green-primary), var(--green-emerald)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }
    .logo-text { font-size: 1.35rem; font-weight: 800; background: linear-gradient(135deg, var(--text-primary), var(--green-muted)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
    .nav-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.6rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.3rem; }
    .nav-item { display: flex; align-items: center; gap: 0.85rem; padding: 0.8rem 1rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: rgba(255,255,255,0.04); color: var(--text-primary); }
    .nav-item.active { background: linear-gradient(135deg, rgba(0,200,83,0.12), rgba(0,200,83,0.05)); color: var(--green-primary); border: 1px solid rgba(0,200,83,0.2); }
    .sidebar-footer { margin-top: auto; padding-top: 1.25rem; border-top: 1px solid var(--border-subtle); }
    .company-card { display: flex; align-items: center; gap: 0.75rem; padding: 0.9rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); margin-bottom: 0.75rem; }
    .company-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, var(--green-primary), var(--green-emerald)); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--bg-deep); }
    .company-info { flex: 1; }
    .company-name { font-size: 0.9rem; font-weight: 600; }
    .company-verified { font-size: 0.7rem; color: var(--green-primary); }
    .logout-btn { display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.7rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.85rem; cursor: pointer; border: 1px solid var(--border-subtle); background: transparent; width: 100%; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; }
    .main-content { background: var(--bg-deep); padding: 1.75rem 2rem; }
    .page-header { margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .page-title { font-size: 1.65rem; font-weight: 700; }
    .page-title span { color: var(--green-primary); }
    .page-subtitle { color: var(--text-muted); font-size: 0.9rem; }
    .kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.25rem; margin-bottom: 2rem; }
    .kpi-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; }
    .kpi-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.85rem; }
    .kpi-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; }
    .kpi-icon { width: 40px; height: 40px; background: linear-gradient(135deg, rgba(0,200,83,0.15), rgba(0,200,83,0.05)); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; color: var(--green-primary); }
    .kpi-value { font-size: 2.35rem; font-weight: 700; }
    .kpi-trend { font-size: 0.75rem; font-weight: 600; color: var(--green-primary); }
    .card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
    .card-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .card-title { font-size: 1rem; font-weight: 600; }
    @media (max-width: 1200px) { .sidebar { display: none; } .dashboard-layout { grid-template-columns: 1fr; } .kpi-grid { grid-template-columns: 1fr; } }
  </style>
</head>
<body>
  <div class="dashboard-layout">
    <aside class="sidebar">
      <a href="company-dashboard.php" class="sidebar-logo"><div class="logo-icon">📋</div><div class="logo-text">Intern<span style="color:var(--green-primary)">Track</span></div></a>
      <div class="nav-label">Main Menu</div>
      <nav class="nav-menu">
        <a href="company-dashboard.php" class="nav-item"><i class="fas fa-th-large"></i> Dashboard</a>
        <a href="company-internships.php" class="nav-item"><i class="fas fa-briefcase"></i> Posted Internships</a>
        <a href="company-applicants.php" class="nav-item"><i class="fas fa-users"></i> Applicants</a>
        <a href="company-monitoring.php" class="nav-item active"><i class="fas fa-chart-line"></i> Monitoring</a>
        <a href="company-messages.php" class="nav-item"><i class="fas fa-envelope"></i> Messages</a>
        <a href="company-analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a>
        <a href="company-reports.php" class="nav-item"><i class="fas fa-file-alt"></i> Reports</a>
        <a href="company-settings.php" class="nav-item"><i class="fas fa-cog"></i> Settings</a>
      </nav>
      <div class="sidebar-footer">
        <div class="company-card">
          <div class="company-avatar"><?= strtoupper(substr(e($company['name']), 0, 2)) ?></div>
          <div class="company-info"><div class="company-name"><?= e($company['name']) ?></div><div class="company-verified"><i class="fas fa-check-circle"></i> <?= ucfirst($company['status']) ?></div></div>
        </div>
        <button class="logout-btn" onclick="handleLogout()"><i class="fas fa-sign-out-alt"></i> Logout</button>
      </div>
    </aside>
    <main class="main-content">
      <header class="page-header">
        <div><h1 class="page-title">Performance <span>Monitoring</span></h1><p class="page-subtitle">Track intern progress and performance metrics</p></div>
      </header>
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-header"><div class="kpi-label">Active Interns</div><div class="kpi-icon"><i class="fas fa-users"></i></div></div>
          <div class="kpi-value"><?= $activeInterns ?></div>
          <div class="kpi-trend"><i class="fas fa-arrow-up"></i> Currently working</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-header"><div class="kpi-label">Completed</div><div class="kpi-icon"><i class="fas fa-check-circle"></i></div></div>
          <div class="kpi-value"><?= $completedThisMonth ?></div>
          <div class="kpi-trend"><i class="fas fa-arrow-up"></i> This month</div>
        </div>
        <div class="kpi-card">
          <div class="kpi-header"><div class="kpi-label">Avg. Rating</div><div class="kpi-icon"><i class="fas fa-star"></i></div></div>
          <div class="kpi-value">4.5</div>
          <div class="kpi-trend"><i class="fas fa-arrow-up"></i> Out of 5.0</div>
        </div>
      </div>
      <div class="card">
        <div class="card-header"><h3 class="card-title">Intern Progress</h3></div>
      </div>
    </main>
  </div>
  <script>async function handleLogout() { await fetch('php/auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) }); window.location.href = 'company-login.php'; }</script>
</body>
</html>