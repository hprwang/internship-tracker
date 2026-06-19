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
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Reports</title>
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

    .stats-overview { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; text-align: center; position: relative; overflow: hidden; }
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

    .reports-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; margin-bottom: 1.5rem; }
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
    .chart-bar { height: 100%; background: linear-gradient(90deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-sm); transition: width 0.5s ease; }
    .chart-value { width: 40px; text-align: right; font-size: 0.8rem; font-weight: 600; color: var(--text-primary); }

    .activity-list { display: flex; flex-direction: column; gap: 0.5rem; }
    .activity-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem; background: var(--bg-elevated); border-radius: var(--radius-md); }
    .activity-icon { width: 32px; height: 32px; background: rgba(34,197,94,0.15); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: var(--green-neon); }
    .activity-details { flex: 1; }
    .activity-name { font-size: 0.85rem; font-weight: 500; }
    .activity-meta { font-size: 0.7rem; color: var(--text-muted); }

    .full-width { grid-column: 1 / -1; }

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
        <a href="admin_applications.php" class="nav-item"><span class="icon">📝</span> Applications</a>
        <a href="admin_reports.php" class="nav-item active"><span class="icon">📈</span> Reports</a>
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
        <h1 class="page-title">Analytics <span>Reports</span></h1>
        <p class="page-subtitle">Track performance and insights across the platform</p>
      </div>
    </div>

    <div class="stats-overview">
      <div class="stat-card students">
        <div class="stat-value"><?= number_format($totalStudents) ?></div>
        <div class="stat-label">Total Students</div>
      </div>
      <div class="stat-card companies">
        <div class="stat-value"><?= number_format($totalCompanies) ?></div>
        <div class="stat-label">Companies</div>
      </div>
      <div class="stat-card internships">
        <div class="stat-value"><?= number_format($totalInternships) ?></div>
        <div class="stat-label">Applications</div>
      </div>
      <div class="stat-card success">
        <div class="stat-value"><?= $successRate ?>%</div>
        <div class="stat-label">Success Rate</div>
      </div>
    </div>

    <div class="reports-grid">
      <div class="report-card">
        <div class="report-header">
          <h3 class="report-title">Status Distribution</h3>
        </div>
        <div class="report-body">
          <?php
          $total = array_sum($statusData);
          $segments = [
            'applied' => ['Applied', $statusData['applied'] ?? 0],
            'interview' => ['Interview', $statusData['interview'] ?? 0],
            'accepted' => ['Accepted', $statusData['accepted'] ?? 0],
            'ongoing' => ['Ongoing', $statusData['ongoing'] ?? 0],
            'completed' => ['Completed', $statusData['completed'] ?? 0],
            'rejected' => ['Rejected', $statusData['rejected'] ?? 0],
          ];
          if ($total > 0): ?>
          <div class="status-bar">
            <?php foreach ($segments as $key => $item): ?>
            <?php if ($item[1] > 0): ?>
            <div class="status-segment <?= $key ?>" style="width: <?= ($item[1] / $total) * 100 ?>%"><?= $item[1] ?></div>
            <?php endif; ?>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>
          <div class="status-legend">
            <?php foreach ($segments as $key => $item): ?>
            <div class="legend-item">
              <span class="legend-dot <?= $key ?>"></span>
              <?= $item[0] ?>: <?= $item[1] ?>
            </div>
            <?php endforeach; ?>
          </div>
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
        <div class="report-body">
          <?php
          $maxInd = $industries[0]['cnt'] ?? 1;
          if ($industries): ?>
          <div class="chart-list">
            <?php foreach ($industries as $ind): ?>
            <div class="chart-item">
              <span class="chart-label"><?= e($ind['industry']) ?></span>
              <div class="chart-bar-container">
                <div class="chart-bar" style="width: <?= ($ind['cnt'] / $maxInd) * 100 ?>%"></div>
              </div>
              <span class="chart-value"><?= $ind['cnt'] ?></span>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p style="color: var(--text-muted); text-align: center; padding: 1rem;">No data available</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="report-card full-width">
        <div class="report-header">
          <h3 class="report-title">Monthly Applications</h3>
        </div>
        <div class="report-body">
          <?php if ($monthlyApps): ?>
          <div class="activity-list">
            <?php foreach ($monthlyApps as $month): ?>
            <div class="activity-item">
              <div class="activity-icon">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 20V10M18 20V4M6 20v-4"/></svg>
              </div>
              <div class="activity-details">
                <div class="activity-name"><?= date('F Y', strtotime($month['month'] . '-01')) ?></div>
                <div class="activity-meta"><?= $month['cnt'] ?> applications</div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php else: ?>
          <p style="color: var(--text-muted); text-align: center; padding: 1rem;">No data available</p>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </main>
</div>

<script>
async function handleLogout() {
  await fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
  window.location.href = 'admin_login.php';
}
</script>
</body>
</html>