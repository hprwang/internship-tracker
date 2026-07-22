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

// Get total counts
$totalStudents = $db->query("SELECT COUNT(*) as cnt FROM users WHERE role = 'student'")->fetch()['cnt'] ?? 0;
$totalCompanies = $db->query("SELECT COUNT(*) as cnt FROM companies")->fetch()['cnt'] ?? 0;
$totalInternships = $db->query("SELECT COUNT(*) as cnt FROM internships")->fetch()['cnt'] ?? 0;

// Status breakdown for internships
$statusCounts = $db->query("
    SELECT status, COUNT(*) as cnt FROM internships GROUP BY status
")->fetchAll();
$statusData = array_column($statusCounts, 'cnt', 'status');
$statusLabels = array_keys($statusData);

// Applications over time (last 6 months)
$monthlyApps = $db->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as cnt
    FROM internships
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month
    ORDER BY month
")->fetchAll();

// Top companies by applications
$topCompanies = $db->query("
    SELECT c.name, COUNT(i.id) as app_count
    FROM companies c
    LEFT JOIN internships i ON c.id = i.company_id
    GROUP BY c.id
    ORDER BY app_count DESC
    LIMIT 5
")->fetchAll();

// Students with most applications
$activeStudents = $db->query("
    SELECT u.full_name, COUNT(i.id) as app_count
    FROM users u
    LEFT JOIN internships i ON u.id = i.student_id
    WHERE u.role = 'student'
    GROUP BY u.id
    ORDER BY app_count DESC
    LIMIT 5
")->fetchAll();

// Success rate
$acceptedCount = $statusData['accepted'] ?? 0;
$rejectedCount = $statusData['rejected'] ?? 0;
$totalDecided = $acceptedCount + $rejectedCount;
$successRate = $totalDecided > 0 ? round(($acceptedCount / $totalDecided) * 100) : 0;

// Industry breakdown
$industries = $db->query("
    SELECT industry, COUNT(*) as cnt FROM companies
    WHERE industry IS NOT NULL AND industry != ''
    GROUP BY industry
    ORDER BY cnt DESC
    LIMIT 5
")->fetchAll();

// Additional metrics
$avgAppsPerStudent = $totalStudents > 0 ? round($totalInternships / $totalStudents, 1) : 0;
$avgAppsPerCompany = $totalCompanies > 0 ? round($totalInternships / $totalCompanies, 1) : 0;

// Recent activity (last 7 days)
$recentActivity = $db->query("
    SELECT DATE(created_at) as date, COUNT(*) as cnt
    FROM internships
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(created_at)
    ORDER BY date
")->fetchAll();

// Status trends (monthly)
$statusTrends = $db->query("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, status, COUNT(*) as cnt
    FROM internships
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY month, status
    ORDER BY month
")->fetchAll();

// Trend calculations - compare with previous period
$currentMonth = date('Y-m');
$lastMonth = date('Y-m', strtotime('-1 month'));

// Current month counts
$thisMonthApps = $db->query("
    SELECT COUNT(*) as cnt FROM internships WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'
")->fetch()['cnt'] ?? 0;

$lastMonthApps = $db->query("
    SELECT COUNT(*) as cnt FROM internships WHERE DATE_FORMAT(created_at, '%Y-%m') = '$lastMonth'
")->fetch()['cnt'] ?? 0;

$appsTrend = $lastMonthApps > 0 ? round((($thisMonthApps - $lastMonthApps) / $lastMonthApps) * 100) : ($thisMonthApps > 0 ? 100 : 0);

// Student trends
$thisMonthStudents = $db->query("
    SELECT COUNT(*) as cnt FROM users WHERE role = 'student' AND DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'
")->fetch()['cnt'] ?? 0;

$lastMonthStudents = $db->query("
    SELECT COUNT(*) as cnt FROM users WHERE role = 'student' AND DATE_FORMAT(created_at, '%Y-%m') = '$lastMonth'
")->fetch()['cnt'] ?? 0;

$studentTrend = $lastMonthStudents > 0 ? round((($thisMonthStudents - $lastMonthStudents) / $lastMonthStudents) * 100) : ($thisMonthStudents > 0 ? 100 : 0);

// Company trends
$thisMonthCompanies = $db->query("
    SELECT COUNT(*) as cnt FROM companies WHERE DATE_FORMAT(created_at, '%Y-%m') = '$currentMonth'
")->fetch()['cnt'] ?? 0;

$lastMonthCompanies = $db->query("
    SELECT COUNT(*) as cnt FROM companies WHERE DATE_FORMAT(created_at, '%Y-%m') = '$lastMonth'
")->fetch()['cnt'] ?? 0;

$companyTrend = $lastMonthCompanies > 0 ? round((($thisMonthCompanies - $lastMonthCompanies) / $lastMonthCompanies) * 100) : ($thisMonthCompanies > 0 ? 100 : 0);

// Additional useful metrics
// 7-day average
$avgAppsPerDay = $db->query("
    SELECT COUNT(*) / 7 as avg_cnt FROM internships WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
")->fetch()['avg_cnt'] ?? 0;

// Pending applications
$pendingCount = ($statusData['applied'] ?? 0) + ($statusData['interview'] ?? 0);

// Active internships (accepted, ongoing, completed)
$activeCount = ($statusData['accepted'] ?? 0) + ($statusData['ongoing'] ?? 0) + ($statusData['completed'] ?? 0);

// Recent applications
$recentApps = $db->query("
    SELECT i.id, i.title, i.status, i.created_at, c.name as company_name, u.full_name as student_name
    FROM internships i
    LEFT JOIN companies c ON i.company_id = c.id
    LEFT JOIN users u ON i.student_id = u.id
    ORDER BY i.created_at DESC
    LIMIT 5
")->fetchAll();

// Export data if requested
$export = $_GET['export'] ?? '';
if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="internship_report_' . date('Y-m-d') . '.csv"');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Status', 'Count']);
    foreach ($statusData as $status => $count) {
        fputcsv($output, [ucfirst($status), $count]);
    }
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Reports</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
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

    .stats-overview { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; text-align: center; position: relative; overflow: hidden; transition: all var(--transition); }
    .stat-card:hover { transform: translateY(-2px); }
    .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; }
    .stat-card.students::before { background: linear-gradient(90deg, #3B82F6, #60A5FA); }
    .stat-card.companies::before { background: linear-gradient(90deg, #8B5CF6, #A78BFA); }
    .stat-card.internships::before { background: linear-gradient(90deg, #F59E0B, #FBBF24); }
    .stat-card.success::before { background: linear-gradient(90deg, var(--green-emerald), var(--green-glow)); }
    .stat-value { font-size: 2.25rem; font-weight: 700; margin-bottom: 0.25rem; }
    .stat-card.students .stat-value { color: #60A5FA; }
    .stat-card.companies .stat-value { color: #A78BFA; }
    .stat-card.internships .stat-value { color: #FBBF24; }
    .stat-card.success .stat-value { color: var(--green-neon); }
    .stat-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .trend-indicator { position: absolute; top: 0.75rem; right: 0.75rem; font-size: 0.7rem; font-weight: 600; padding: 0.125rem 0.5rem; border-radius: 4px; }
    .trend-indicator.positive { background: rgba(34,197,94,0.15); color: #22C55E; }
    .trend-indicator.negative { background: rgba(239,68,68,0.15); color: #EF4444; }
    .trend-indicator.neutral { background: rgba(161,161,170,0.15); color: #71717A; }

    .reports-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 2rem; }
    .report-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
    .report-header { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); display: flex; justify-content: space-between; align-items: center; }
    .report-title { font-size: 0.95rem; font-weight: 600; }
    .report-body { padding: 1.25rem; }

    .status-bar { display: flex; height: 28px; border-radius: var(--radius-sm); overflow: hidden; margin-bottom: 1rem; }
    .status-segment { display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: 600; color: var(--bg-deep); }
    .status-segment.applied { background: #F59E0B; }
    .status-segment.interview { background: #8B5CF6; }
    .status-segment.accepted { background: var(--green-neon); }
    .status-segment.ongoing { background: #3B82F6; }
    .status-segment.completed { background: #06B6D4; }
    .status-segment.rejected { background: #EF4444; }

    .status-legend { display: flex; flex-wrap: wrap; gap: 0.75rem; }
    .legend-item { display: flex; align-items: center; gap: 0.375rem; font-size: 0.75rem; color: var(--text-secondary); }
    .legend-dot { width: 8px; height: 8px; border-radius: 50%; }
    .legend-dot.applied { background: #F59E0B; }
    .legend-dot.interview { background: #8B5CF6; }
    .legend-dot.accepted { background: var(--green-neon); }
    .legend-dot.ongoing { background: #3B82F6; }
    .legend-dot.completed { background: #06B6D4; }
    .legend-dot.rejected { background: #EF4444; }

    .chart-list { display: flex; flex-direction: column; gap: 0.75rem; }
    .chart-item { display: flex; align-items: center; gap: 0.75rem; }
    .chart-label { width: 120px; font-size: 0.8rem; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .chart-bar-container { flex: 1; height: 24px; background: var(--bg-elevated); border-radius: var(--radius-sm); overflow: hidden; }
    .chart-bar { height: 100%; background: linear-gradient(90deg, #8B5CF6, #A78BFA); border-radius: var(--radius-sm); transition: width 0.5s ease; }
    .chart-bar.students { background: linear-gradient(90deg, #3B82F6, #60A5FA); }
    .chart-value { width: 40px; text-align: right; font-size: 0.8rem; font-weight: 600; color: var(--text-primary); }

    .activity-list { display: flex; flex-direction: column; gap: 0.5rem; }
    .activity-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem; background: var(--bg-elevated); border-radius: var(--radius-md); }
    .activity-icon { width: 32px; height: 32px; background: rgba(34,197,94,0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--green-neon); }
    .activity-details { flex: 1; }
    .activity-name { font-size: 0.85rem; font-weight: 500; }
    .activity-meta { font-size: 0.7rem; color: var(--text-muted); }

    .full-width { grid-column: 1 / -1; }

    .metric-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1rem; }
    .metric-card { background: var(--bg-elevated); border-radius: var(--radius-md); padding: 1rem; text-align: center; }
    .metric-value { font-size: 1.5rem; font-weight: 700; color: var(--green-neon); }
    .metric-label { font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase; }

    .trend-chart { display: flex; align-items: flex-end; gap: 0.5rem; height: 120px; padding: 1rem 0; }
    .trend-bar { flex: 1; background: linear-gradient(180deg, var(--green-neon), var(--green-emerald)); border-radius: 4px 4px 0 0; position: relative; transition: all var(--transition); }
    .trend-bar:hover { filter: brightness(1.2); }
    .trend-bar::after { content: attr(data-value); position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 0.65rem; color: var(--text-secondary); }
    .trend-label { text-align: center; font-size: 0.65rem; color: var(--text-muted); margin-top: 0.5rem; }

    .donut-chart { width: 140px; height: 140px; margin: 0 auto; position: relative; }
    .donut-center { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); text-align: center; }
    .donut-value { font-size: 1.5rem; font-weight: 700; }
    .donut-label { font-size: 0.65rem; color: var(--text-muted); }

    .insight-pill { display: inline-flex; align-items: center; gap: 0.375rem; padding: 0.375rem 0.75rem; background: rgba(34,197,94,0.12); border-radius: 999px; font-size: 0.75rem; color: var(--green-neon); margin: 0.25rem; }
    .insights-row { display: flex; flex-wrap: wrap; gap: 0.5rem; margin-bottom: 1rem; }

    .progress-ring { width: 100%; height: 100%; transform: rotate(-90deg); }
    .progress-ring-circle { fill: none; stroke: var(--bg-elevated); stroke-width: 8; }
    .progress-ring-progress { fill: none; stroke: var(--green-neon); stroke-width: 8; stroke-linecap: round; transition: stroke-dashoffset 0.5s ease; }

    .status-badge { display: inline-block; padding: 0.25rem 0.625rem; border-radius: 4px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; }
    .status-badge.applied { background: rgba(245,158,11,0.15); color: #F59E0B; }
    .status-badge.interview { background: rgba(139,92,246,0.15); color: #8B5CF6; }
    .status-badge.accepted { background: rgba(34,197,94,0.15); color: #22C55E; }
    .status-badge.ongoing { background: rgba(59,130,246,0.15); color: #3B82F6; }
    .status-badge.completed { background: rgba(6,182,212,0.15); color: #06B6D4; }
    .status-badge.rejected { background: rgba(239,68,68,0.15); color: #EF4444; }

    table tr:hover { background: var(--bg-elevated); }

    .export-btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.5rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.8rem; font-weight: 500; cursor: pointer; transition: all var(--transition); text-decoration: none; }
    .export-btn:hover { border-color: var(--green-neon); color: var(--green-neon); background: rgba(34,197,94,0.08); }

    @media (max-width: 900px) {
      .admin-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .main-content { padding: 1rem; }
      .stats-overview { grid-template-columns: repeat(2, 1fr); }
      .reports-grid { grid-template-columns: 1fr; }
    }
  </style>
</head>
<body>
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
        <a href="admin_reports.php" class="nav-item active"><span class="icon"><i class="fas fa-chart-bar"></i></span> Reports</a>
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

  <main class="main-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Analytics <span>Reports</span></h1>
        <p class="page-subtitle">Track performance and insights across the platform</p>
      </div>
      <div style="display: flex; gap: 0.75rem;">
        <select id="dateRange" onchange="refreshData()" style="padding: 0.5rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.8rem; cursor: pointer;">
          <option value="6">Last 6 Months</option>
          <option value="12">Last 12 Months</option>
          <option value="all">All Time</option>
        </select>
<button onclick="window.location.reload()" class="export-btn"><i class="fas fa-sync-alt"></i> Refresh</button>
        <button onclick="exportToCSV()" class="export-btn" style="background: #22C55E; color: #fff;"><i class="fas fa-download"></i> Export CSV</button>
      </div>
    </div>

    <div class="insights-row">
<span class="insight-pill"><i class="fas fa-check"></i> <?= $acceptedCount ?> Accepted</span>
      <span class="insight-pill"><i class="fas fa-spinner"></i> <?= $statusData['ongoing'] ?? 0 ?> Ongoing</span>
      <span class="insight-pill"><i class="fas fa-calendar-check"></i> <?= $statusData['interview'] ?? 0 ?> In Interview</span>
      <span class="insight-pill"><i class="fas fa-check-double"></i> <?= $statusData['completed'] ?? 0 ?> Completed</span>
    </div>

    <div class="stats-overview">
      <div class="stat-card students">
        <span class="trend-indicator <?= $studentTrend > 0 ? 'positive' : ($studentTrend < 0 ? 'negative' : 'neutral') ?>"><?= $studentTrend > 0 ? '↑' : ($studentTrend < 0 ? '↓' : '→') ?> <?= abs($studentTrend) ?>%</span>
        <div class="stat-value"><?= number_format($totalStudents) ?></div>
        <div class="stat-label">Total Students</div>
      </div>
      <div class="stat-card companies">
        <span class="trend-indicator <?= $companyTrend > 0 ? 'positive' : ($companyTrend < 0 ? 'negative' : 'neutral') ?>"><?= $companyTrend > 0 ? '↑' : ($companyTrend < 0 ? '↓' : '→') ?> <?= abs($companyTrend) ?>%</span>
        <div class="stat-value"><?= number_format($totalCompanies) ?></div>
        <div class="stat-label">Companies</div>
      </div>
      <div class="stat-card internships">
        <span class="trend-indicator <?= $appsTrend > 0 ? 'positive' : ($appsTrend < 0 ? 'negative' : 'neutral') ?>"><?= $appsTrend > 0 ? '↑' : ($appsTrend < 0 ? '↓' : '→') ?> <?= abs($appsTrend) ?>%</span>
        <div class="stat-value"><?= number_format($totalInternships) ?></div>
        <div class="stat-label">Applications</div>
      </div>
      <div class="stat-card success">
        <div class="stat-value"><?= $successRate ?>%</div>
        <div class="stat-label">Success Rate</div>
      </div>
    </div>

    <div class="metric-grid" style="margin-bottom: 2rem;">
      <div class="metric-card">
        <div class="metric-value"><?= $avgAppsPerStudent ?></div>
        <div class="metric-label">Avg Apps/Student</div>
      </div>
      <div class="metric-card">
        <div class="metric-value"><?= $avgAppsPerCompany ?></div>
        <div class="metric-label">Avg Apps/Company</div>
      </div>
      <div class="metric-card">
        <div class="metric-value"><?= round($avgAppsPerDay, 1) ?></div>
        <div class="metric-label">Apps/Day (7-day avg)</div>
      </div>
      <div class="metric-card">
        <div class="metric-value"><?= $pendingCount ?></div>
        <div class="metric-label">Pending</div>
      </div>
      <div class="metric-card">
        <div class="metric-value"><?= $activeCount ?></div>
        <div class="metric-label">Active</div>
      </div>
      <div class="metric-card">
        <div class="metric-value"><?= $thisMonthApps ?></div>
        <div class="metric-label">This Month</div>
      </div>
    </div>

    <div class="reports-grid">
      <div class="report-card">
        <div class="report-header">
          <h3 class="report-title">Status Distribution</h3>
        </div>
        <div class="report-body" style="height: 220px;">
          <canvas id="statusChart"></canvas>
        </div>
      </div>

      <div class="report-card">
        <div class="report-header">
          <h3 class="report-title">Top Companies</h3>
        </div>
        <div class="report-body">
          <?php
          $maxCount = $topCompanies[0]['app_count'] ?? 1;
          if ($topCompanies): ?>
          <div class="chart-list">
            <?php foreach ($topCompanies as $company): ?>
            <div class="chart-item">
              <span class="chart-label"><?= e($company['name']) ?></span>
              <div class="chart-bar-container">
                <div class="chart-bar" style="width: <?= ($company['app_count'] / $maxCount) * 100 ?>%"></div>
              </div>
              <span class="chart-value"><?= $company['app_count'] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p style="color: var(--text-muted); text-align: center; padding: 1rem;">No data available</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="report-card">
        <div class="report-header">
          <h3 class="report-title">Most Active Students</h3>
        </div>
        <div class="report-body">
          <?php
          $maxApps = $activeStudents[0]['app_count'] ?? 1;
          if ($activeStudents): ?>
          <div class="chart-list">
            <?php foreach ($activeStudents as $student): ?>
            <div class="chart-item">
              <span class="chart-label"><?= e($student['full_name']) ?></span>
              <div class="chart-bar-container">
                <div class="chart-bar" style="width: <?= ($student['app_count'] / $maxApps) * 100 ?>%"></div>
              </div>
              <span class="chart-value"><?= $student['app_count'] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p style="color: var(--text-muted); text-align: center; padding: 1rem;">No data available</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="report-card">
        <div class="report-header">
          <h3 class="report-title">Industries</h3>
        </div>
        <div class="report-body" style="height: 200px;">
          <canvas id="industriesChart"></canvas>
        </div>
      </div>

      <div class="report-card full-width">
        <div class="report-header">
          <h3 class="report-title">Monthly Applications Trend</h3>
          <span style="font-size: 0.75rem; color: var(--text-muted);">Last 6 months</span>
        </div>
        <div class="report-body" style="height: 250px;">
          <canvas id="monthlyChart"></canvas>
        </div>
      </div>
    </div>

    <div class="report-card full-width">
      <div class="report-header">
        <h3 class="report-title">Recent Applications</h3>
        <span style="font-size: 0.75rem; color: var(--text-muted);">Latest 5</span>
      </div>
      <div class="report-body">
        <table style="width: 100%; border-collapse: collapse;">
          <thead>
            <tr style="border-bottom: 1px solid var(--border-subtle);">
              <th style="text-align: left; padding: 0.75rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Position</th>
              <th style="text-align: left; padding: 0.75rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Company</th>
              <th style="text-align: left; padding: 0.75rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Student</th>
              <th style="text-align: left; padding: 0.75rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Status</th>
              <th style="text-align: left; padding: 0.75rem; font-size: 0.7rem; color: var(--text-muted); text-transform: uppercase;">Date</th>
            </tr>
          </thead>
          <tbody>
            <?php if ($recentApps): ?>
              <?php foreach ($recentApps as $app): ?>
              <tr style="border-bottom: 1px solid var(--border-subtle);">
                <td style="padding: 0.75rem; font-size: 0.85rem;"><?= e($app['title']) ?></td>
                <td style="padding: 0.75rem; font-size: 0.85rem; color: var(--text-secondary);"><?= e($app['company_name'] ?? 'N/A') ?></td>
                <td style="padding: 0.75rem; font-size: 0.85rem; color: var(--text-secondary);"><?= e($app['student_name'] ?? 'N/A') ?></td>
                <td style="padding: 0.75rem;">
                  <span class="status-badge <?= $app['status'] ?>"><?= ucfirst($app['status']) ?></span>
                </td>
                <td style="padding: 0.75rem; font-size: 0.8rem; color: var(--text-muted);"><?= date('M d, Y', strtotime($app['created_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            <?php else: ?>
              <tr>
                <td colspan="5" style="padding: 2rem; text-align: center; color: var(--text-muted);">No recent applications</td>
              </tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>

<script>
function refreshData() {
  const range = document.getElementById('dateRange').value;
  window.location.href = '?range=' + range;
}

// Export to CSV functionality
function exportToCSV() {
  const data = <?= json_encode([
    'status' => $statusData,
    'monthly' => $monthlyApps,
    'companies' => $topCompanies,
    'students' => $activeStudents,
    'industries' => $industries
  ]) ?>;

  let csv = 'Report Data - Generated ' + new Date().toISOString().split('T')[0] + '\n\n';

  // Status breakdown
  csv += 'Status Distribution\n';
  csv += 'Status,Count\n';
  Object.entries(data.status).forEach(([status, count]) => {
    csv += status.charAt(0).toUpperCase() + status.slice(1) + ',' + count + '\n';
  });

  // Monthly applications
  csv += '\nMonthly Applications\n';
  csv += 'Month,Count\n';
  data.monthly.forEach(row => {
    csv += row.month + ',' + row.cnt + '\n';
  });

  // Top companies
  csv += '\nTop Companies\n';
  csv += 'Company,Applications\n';
  data.companies.forEach(row => {
    csv += '"' + row.name + '",' + row.app_count + '\n';
  });

  // Active students
  csv += '\nMost Active Students\n';
  csv += 'Student,Applications\n';
  data.students.forEach(row => {
    csv += '"' + row.full_name + '",' + row.app_count + '\n';
  });

  // Industries
  csv += '\nIndustries\n';
  csv += 'Industry,Count\n';
  data.industries.forEach(row => {
    csv += '"' + row.industry + '",' + row.cnt + '\n';
  });

  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'internship_report_' + new Date().toISOString().split('T')[0] + '.csv';
  a.click();
  URL.revokeObjectURL(url);
}

async function handleLogout() {
  await fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
  window.location.href = 'admin_login.php';
}

// Initialize Chart.js visualizations
document.addEventListener('DOMContentLoaded', function() {
  // Status Distribution Doughnut Chart
  const statusCtx = document.getElementById('statusChart');
  if (statusCtx) {
    new Chart(statusCtx, {
      type: 'doughnut',
      data: {
        labels: ['Applied', 'Interview', 'Accepted', 'Ongoing', 'Completed', 'Rejected'],
        datasets: [{
          data: [
            <?= $statusData['applied'] ?? 0 ?>,
            <?= $statusData['interview'] ?? 0 ?>,
            <?= $statusData['accepted'] ?? 0 ?>,
            <?= $statusData['ongoing'] ?? 0 ?>,
            <?= $statusData['completed'] ?? 0 ?>,
            <?= $statusData['rejected'] ?? 0 ?>
          ],
          backgroundColor: ['#F59E0B', '#8B5CF6', '#22C55E', '#3B82F6', '#06B6D4', '#EF4444'],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { position: 'right', labels: { color: '#A1A1AA', font: { size: 11 }, padding: 12 } }
        },
        cutout: '65%'
      }
    });
  }

  // Monthly Applications Line Chart
  const monthlyCtx = document.getElementById('monthlyChart');
  if (monthlyCtx) {
    new Chart(monthlyCtx, {
      type: 'line',
      data: {
        labels: <?= json_encode(array_map(function($m) { return date('M', strtotime($m['month'] . '-01')); }, $monthlyApps)) ?>,
        datasets: [{
          label: 'Applications',
          data: <?= json_encode(array_column($monthlyApps, 'cnt')) ?>,
          borderColor: '#22C55E',
          backgroundColor: 'rgba(34, 197, 94, 0.1)',
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#22C55E',
          pointBorderColor: '#050505',
          pointBorderWidth: 2,
          pointRadius: 5
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: { grid: { color: '#222222' }, ticks: { color: '#71717A' } },
          y: { grid: { color: '#222222' }, ticks: { color: '#71717A' }, beginAtZero: true }
        }
      }
    });
  }

  // Industries Bar Chart
  const industriesCtx = document.getElementById('industriesChart');
  if (industriesCtx) {
    new Chart(industriesCtx, {
      type: 'bar',
      data: {
        labels: <?= json_encode(array_column($industries, 'industry')) ?>,
        datasets: [{
          label: 'Companies',
          data: <?= json_encode(array_column($industries, 'cnt')) ?>,
          backgroundColor: ['#8B5CF6', '#3B82F6', '#F59E0B', '#22C55E', '#06B6D4'],
          borderRadius: 6
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        plugins: {
          legend: { display: false }
        },
        scales: {
          x: { grid: { color: '#222222' }, ticks: { color: '#71717A' }, beginAtZero: true },
          y: { grid: { display: false }, ticks: { color: '#A1A1AA' } }
        }
      }
    });
  }
});

// Animate stats on load
document.querySelectorAll('.stat-value').forEach((el, i) => {
  el.style.opacity = '0';
  el.style.transform = 'translateY(10px)';
  setTimeout(() => {
    el.style.transition = 'all 0.4s ease';
    el.style.opacity = '1';
    el.style.transform = 'translateY(0)';
  }, i * 100);
});
</script>
</body>
</html>