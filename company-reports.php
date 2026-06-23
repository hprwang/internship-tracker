<?php
session_start();
require_once __DIR__ . '/php/config.php';
$user = requireAuth();
$companyId = $user['company_id'] ?? null;
$db = Database::getCompanyConnection();
$company = $companyId ? $db->prepare("SELECT * FROM companies WHERE id = ?")->execute([$companyId])->fetch() : ['name' => 'Company'];
if (!$company) $company = ['name' => 'Your Company'];

$reportType = $_GET['report'] ?? 'summary';
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');

$dbError = null;
$internships = [];
$monthlyStats = [];
$recentActivity = [];
$totalInternships = 0;
$totalApplications = 0;
$totalAccepted = 0;
$totalRejected = 0;
$totalPending = 0;

// Create tables if they don't exist
try {
    $db->exec("CREATE TABLE IF NOT EXISTS applications (
        id INT AUTO_INCREMENT PRIMARY KEY,
        internship_id INT NOT NULL,
        student_id INT DEFAULT NULL,
        student_name VARCHAR(150),
        student_email VARCHAR(150),
        student_phone VARCHAR(50),
        student_resume TEXT,
        cover_letter TEXT,
        status ENUM('pending', 'accepted', 'rejected', 'under_review') DEFAULT 'pending',
        notes TEXT,
        applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_internship (internship_id),
        INDEX idx_student (student_id),
        INDEX idx_status (status)
    )");
} catch (PDOException $e) {
    // Continue without applications table
}

try {
    // Use simpler query without subqueries first to check if data exists
    $stmt = $db->prepare("SELECT * FROM internships WHERE company_id = ? ORDER BY created_at DESC");
    $result = $stmt->execute([$companyId]);
    if ($result) {
        $internships = $stmt->fetchAll() ?: [];
    }

    $totalInternships = count($internships);

    // Get application counts if table exists
    if (!empty($internships)) {
        foreach ($internships as &$intern) {
            $appStmt = $db->prepare("SELECT status, COUNT(*) as cnt FROM applications WHERE internship_id = ? GROUP BY status");
            $appStmt->execute([$intern['id']]);
            $appCounts = $appStmt->fetchAll() ?: [];
            $intern['total_applications'] = 0;
            $intern['accepted'] = 0;
            $intern['rejected'] = 0;
            $intern['pending'] = 0;
            foreach ($appCounts as $ac) {
                $intern[$ac['status']] = (int)$ac['cnt'];
                $intern['total_applications'] += (int)$ac['cnt'];
            }
        }

        $totalApplications = array_sum(array_column($internships, 'total_applications'));
        $totalAccepted = array_sum(array_column($internships, 'accepted'));
        $totalRejected = array_sum(array_column($internships, 'rejected'));
        $totalPending = array_sum(array_column($internships, 'pending'));
    }

    // Monthly stats
    $monthlyStmt = $db->prepare("
        SELECT DATE_FORMAT(applied_at, '%Y-%m') as month, COUNT(*) as applications
        FROM applications a
        JOIN internships i ON a.internship_id = i.id
        WHERE i.company_id = ?
        GROUP BY DATE_FORMAT(applied_at, '%Y-%m')
        ORDER BY month DESC LIMIT 12
    ");
    $monthlyStmt->execute([$companyId]);
    $monthlyStats = $monthlyStmt->fetchAll() ?: [];

    // Recent activity
    $activityStmt = $db->prepare("
        SELECT a.*, i.title as intern_title
        FROM applications a
        JOIN internships i ON a.internship_id = i.id
        WHERE i.company_id = ?
        ORDER BY a.applied_at DESC LIMIT 20
    ");
    $activityStmt->execute([$companyId]);
    $recentActivity = $activityStmt->fetchAll() ?: [];

} catch (PDOException $e) {
    $dbError = $e->getMessage();
    error_log("Reports DB error: " . $e->getMessage());
    $internships = [];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports — InternTrack</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root { --bg-deep: #0D0D0D; --bg-charcoal: #141414; --bg-card: #1F1F1F; --border-subtle: #2A2A2A; --green-primary: #00C853; --green-emerald: #00E676; --green-muted: #69F0AE; --text-primary: #FFFFFF; --text-secondary: #B0B0B0; --text-muted: #707070; --radius-sm: 10px; --radius-md: 14px; --radius-lg: 18px; }
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Outfit', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; }
    .dashboard-layout { display: grid; grid-template-columns: 270px 1fr; min-height: 100vh; }
    .sidebar { background: linear-gradient(180deg, var(--bg-charcoal) 0%, #0F0F0F 100%); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.85rem; padding: 0.5rem 0.75rem 1.75rem; margin-bottom: 1.5rem; text-decoration: none; }
    .logo-icon { width: 44px; height: 44px; background: linear-gradient(135deg, var(--green-primary), var(--green-emerald)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; }
    .logo-text { font-size: 1.35rem; font-weight: 800; background: linear-gradient(135deg, var(--text-primary), var(--green-muted)); -webkit-background-clip: text; background-clip: text; -webkit-text-fill-color: transparent; }
    .nav-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.6rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.3rem; }
    .nav-item { display: flex; align-items: center; gap: 0.85rem; padding: 0.8rem 1rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: rgba(255,255,255,0.04); }
    .nav-item.active { background: rgba(0,200,83,0.12); color: var(--green-primary); border: 1px solid rgba(0,200,83,0.2); }
    .sidebar-footer { margin-top: auto; padding-top: 1.25rem; border-top: 1px solid var(--border-subtle); }
    .company-card { display: flex; align-items: center; gap: 0.75rem; padding: 0.9rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); margin-bottom: 0.75rem; }
    .company-avatar { width: 42px; height: 42px; background: linear-gradient(135deg, var(--green-primary), var(--green-emerald)); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: center; font-weight: 800; color: var(--bg-deep); }
    .company-info { flex: 1; }
    .company-name { font-size: 0.9rem; font-weight: 600; }
    .logout-btn { display: flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.7rem; border-radius: var(--radius-md); color: var(--text-muted); border: 1px solid var(--border-subtle); background: transparent; width: 100%; }
    .main-content { background: var(--bg-deep); padding: 1.75rem 2rem; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .page-title { font-size: 1.65rem; font-weight: 700; }
    .page-title span { color: var(--green-primary); }
    .page-subtitle { color: var(--text-muted); font-size: 0.9rem; }
    .btn-primary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.25rem; background: linear-gradient(135deg, var(--green-primary), var(--green-emerald)); border: none; border-radius: var(--radius-md); color: var(--bg-deep); font-size: 0.9rem; font-weight: 600; cursor: pointer; text-decoration: none; }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,200,83,0.25); }
    .btn-secondary { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.85rem; cursor: pointer; }
    .btn-secondary:hover, .btn-secondary.active { border-color: var(--green-primary); color: var(--green-primary); }
    .filter-bar { display: flex; gap: 1rem; align-items: center; margin-bottom: 1.5rem; flex-wrap: wrap; padding: 1rem; background: var(--bg-card); border-radius: var(--radius-lg); border: 1px solid var(--border-subtle); }
    .filter-group { display: flex; align-items: center; gap: 0.5rem; }
    .filter-group label { font-size: 0.8rem; color: var(--text-muted); }
    .filter-group input, .filter-group select { padding: 0.5rem 0.75rem; background: var(--bg-charcoal); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-primary); font-size: 0.85rem; }
    .filter-group input:focus, .filter-group select:focus { outline: none; border-color: var(--green-primary); }
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.25rem; }
    .stat-card .stat-value { font-size: 1.75rem; font-weight: 700; color: var(--text-primary); margin-bottom: 0.25rem; }
    .stat-card .stat-label { font-size: 0.8rem; color: var(--text-muted); }
    .stat-card.primary .stat-value { color: var(--green-primary); }
    .stat-card.accent .stat-value { color: var(--green-emerald); }
    .reports-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
    .report-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; align-items: center; gap: 1rem; cursor: pointer; transition: all 0.2s; }
    .report-card:hover { border-color: var(--green-primary); transform: translateY(-2px); }
    .report-card.active { border-color: var(--green-primary); background: rgba(0,200,83,0.05); }
    .report-icon { width: 50px; height: 50px; background: linear-gradient(135deg, rgba(0,200,83,0.15), rgba(0,200,83,0.05)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; color: var(--green-primary); font-size: 1.25rem; }
    .report-info h4 { font-size: 1rem; font-weight: 600; margin-bottom: 0.25rem; }
    .report-info p { font-size: 0.85rem; color: var(--text-muted); }
    .report-section { display: none; }
    .report-section.active { display: block; }
    .data-table { width: 100%; border-collapse: collapse; background: var(--bg-card); border-radius: var(--radius-lg); overflow: hidden; border: 1px solid var(--border-subtle); }
    .data-table th, .data-table td { padding: 1rem; text-align: left; border-bottom: 1px solid var(--border-subtle); }
    .data-table th { background: var(--bg-charcoal); font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
    .data-table tr:hover { background: rgba(255,255,255,0.02); }
    .data-table tr:last-child td { border-bottom: none; }
    .status-badge { display: inline-block; padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
    .status-badge.accepted { background: rgba(0,200,83,0.15); color: var(--green-primary); }
    .status-badge.rejected { background: rgba(255,82,82,0.15); color: #FF5252; }
    .status-badge.pending { background: rgba(255,193,7,0.15); color: #FFC107; }
    .chart-container { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; }
    .chart-title { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; }
    .bar-chart { display: flex; align-items: flex-end; gap: 0.5rem; height: 150px; padding: 1rem 0; }
    .bar-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 0.5rem; }
    .bar { width: 100%; background: linear-gradient(180deg, var(--green-primary), var(--green-muted)); border-radius: var(--radius-sm) var(--radius-sm) 0 0; transition: height 0.3s; min-height: 4px; }
    .bar-label { font-size: 0.65rem; color: var(--text-muted); }
    .bar-value { font-size: 0.7rem; font-weight: 600; }
    .empty-state { text-align: center; padding: 3rem; color: var(--text-muted); }
    .empty-state i { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
    @media (max-width: 1200px) { .sidebar { display: none; } .dashboard-layout { grid-template-columns: 1fr; } .reports-grid { grid-template-columns: 1fr; } .stats-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 768px) { .stats-grid { grid-template-columns: 1fr; } .filter-bar { flex-direction: column; align-items: stretch; } }
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
        <a href="company-monitoring.php" class="nav-item"><i class="fas fa-chart-line"></i> Monitoring</a>
        <a href="company-messages.php" class="nav-item"><i class="fas fa-envelope"></i> Messages</a>
        <a href="company-analytics.php" class="nav-item"><i class="fas fa-chart-bar"></i> Analytics</a>
        <a href="company-reports.php" class="nav-item active"><i class="fas fa-file-alt"></i> Reports</a>
        <a href="company-settings.php" class="nav-item"><i class="fas fa-cog"></i> Settings</a>
      </nav>
      <div class="sidebar-footer">
        <div class="company-card"><div class="company-avatar"><?= strtoupper(substr(e($company['name']), 0, 2)) ?></div><div class="company-info"><div class="company-name"><?= e($company['name']) ?></div></div></div>
        <button class="logout-btn" onclick="handleLogout()"><i class="fas fa-sign-out-alt"></i> Logout</button>
      </div>
    </aside>
    <main class="main-content">
      <header class="page-header">
        <div><h1 class="page-title">Reports</h1><p class="page-subtitle">Analyze your internship data and performance</p></div>
        <a href="php/api/export_reports.php?company_id=<?= $companyId ?>&date_from=<?= $dateFrom ?>&date_to=<?= $dateTo ?>" class="btn-primary"><i class="fas fa-download"></i> Export All</a>
      </header>

      <!-- Stats Overview -->
      <div class="stats-grid">
        <div class="stat-card primary">
          <div class="stat-value"><?= $totalInternships ?></div>
          <div class="stat-label">Active Internships</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= $totalApplications ?></div>
          <div class="stat-label">Total Applications</div>
        </div>
        <div class="stat-card accent">
          <div class="stat-value"><?= $totalAccepted ?></div>
          <div class="stat-label">Accepted</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?= $totalPending ?></div>
          <div class="stat-label">Pending Review</div>
        </div>
      </div>

      <!-- Filter Bar -->
      <form class="filter-bar" method="get">
        <div class="filter-group">
          <label>From:</label>
          <input type="date" name="date_from" value="<?= $dateFrom ?>">
        </div>
        <div class="filter-group">
          <label>To:</label>
          <input type="date" name="date_to" value="<?= $dateTo ?>">
        </div>
        <button type="submit" class="btn-primary"><i class="fas fa-filter"></i> Apply</button>
      </form>

      <!-- Report Type Tabs -->
      <div class="reports-grid" style="margin-bottom: 2rem;">
        <div class="report-card <?= $reportType === 'summary' || !$reportType ? 'active' : '' ?>" onclick="showReport('summary', this)">
          <div class="report-icon"><i class="fas fa-users"></i></div>
          <div class="report-info"><h4>Intern Summary</h4><p>Overview of all intern activities</p></div>
        </div>
        <div class="report-card <?= $reportType === 'performance' ? 'active' : '' ?>" onclick="showReport('performance', this)">
          <div class="report-icon"><i class="fas fa-chart-line"></i></div>
          <div class="report-info"><h4>Performance Report</h4><p>Intern performance metrics</p></div>
        </div>
        <div class="report-card <?= $reportType === 'applications' ? 'active' : '' ?>" onclick="showReport('applications', this)">
          <div class="report-icon"><i class="fas fa-file-alt"></i></div>
          <div class="report-info"><h4>Application Status</h4><p>List of all applicants</p></div>
        </div>
        <div class="report-card <?= $reportType === 'activity' ? 'active' : '' ?>" onclick="showReport('activity', this)">
          <div class="report-icon"><i class="fas fa-calendar"></i></div>
          <div class="report-info"><h4>Activity Log</h4><p>Timeline of all activities</p></div>
        </div>
      </div>

      <!-- Summary Report Section -->
      <div class="report-section <?= $reportType === 'summary' || !$reportType ? 'active' : '' ?>" id="summary">
        <?php if (empty($internships)): ?>
          <div class="empty-state"><i class="fas fa-inbox"></i><p>No internship data found for the selected period</p></div>
        <?php else: ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Internship Title</th>
                <th>Total Apps</th>
                <th>Accepted</th>
                <th>Rejected</th>
                <th>Pending</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($internships as $intern): ?>
                <tr>
                  <td><?= e($intern['title']) ?></td>
                  <td><?= $intern['total_applications'] ?></td>
                  <td><span class="status-badge accepted"><?= $intern['accepted'] ?></span></td>
                  <td><span class="status-badge rejected"><?= $intern['rejected'] ?></span></td>
                  <td><span class="status-badge pending"><?= $intern['pending'] ?></span></td>
                  <td><?= date('M d, Y', strtotime($intern['created_at'])) ?></td>
                  <td>
                    <a href="php/api/export_reports.php?type=internship&id=<?= $intern['id'] ?>" class="btn-secondary"><i class="fas fa-download"></i></a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- Performance Report Section -->
      <div class="report-section <?= $reportType === 'performance' ? 'active' : '' ?>" id="performance">
        <div class="chart-container">
          <h3 class="chart-title">Monthly Applications</h3>
          <div class="bar-chart">
            <?php foreach (array_reverse($monthlyStats) as $stat): ?>
              <div class="bar-item">
                <div class="bar-value"><?= $stat['applications'] ?></div>
                <div class="bar" style="height: <?= max(10, min(140, $stat['applications'] * 5)) ?>px;"></div>
                <div class="bar-label"><?= date('M', strtotime($stat['month'] . '-01')) ?></div>
              </div>
            <?php endforeach; ?>
            <?php if (empty($monthlyStats)): ?>
              <div class="empty-state"><i class="fas fa-chart-bar"></i><p>No performance data available</p></div>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Applications Report Section -->
      <div class="report-section <?= $reportType === 'applications' ? 'active' : '' ?>" id="applications">
        <?php if (empty($recentActivity)): ?>
          <div class="empty-state"><i class="fas fa-users"></i><p>No applications found</p></div>
        <?php else: ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Student Name</th>
                <th>Email</th>
                <th>Internship</th>
                <th>Status</th>
                <th>Applied Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentActivity as $app): ?>
                <tr>
                  <td><?= e($app['full_name'] ?? 'Unknown') ?></td>
                  <td><?= e($app['email'] ?? '-') ?></td>
                  <td><?= e($app['intern_title']) ?></td>
                  <td><span class="status-badge <?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span></td>
                  <td><?= date('M d, Y', strtotime($app['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>

      <!-- Activity Log Section -->
      <div class="report-section <?= $reportType === 'activity' ? 'active' : '' ?>" id="activity">
        <?php if (empty($recentActivity)): ?>
          <div class="empty-state"><i class="fas fa-history"></i><p>No activity recorded</p></div>
        <?php else: ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Action</th>
                <th>Details</th>
                <th>Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentActivity as $activity): ?>
                <tr>
                  <td>Application <?= ucfirst($activity['status']) ?></td>
                  <td><?= e($activity['full_name'] ?? 'Unknown') ?> applied for <?= e($activity['intern_title']) ?></td>
                  <td><?= date('M d, Y g:i A', strtotime($activity['created_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </div>
    </main>
  </div>
  <script>
async function handleLogout() { await fetch('php/auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) }); window.location.href = 'company-login.php'; }
function showReport(type, el) {
  document.querySelectorAll('.report-section').forEach(function(e) { e.classList.remove('active'); });
  document.querySelectorAll('.report-card').forEach(function(e) { e.classList.remove('active'); });
  document.getElementById(type).classList.add('active');
  if (el) el.classList.add('active');
  const url = new URL(window.location);
  url.searchParams.set('report', type);
  window.history.replaceState({}, '', url);
}
</script>
</body>
</html>