<?php
session_start();
require_once 'php/config.php';
$user = requireAuth();
$csrf = generateCSRF();

// Get grades and performance data
$db = Database::getConnection();
$userId = (int)$user['id'];
$gradesData = null;
$performanceHistory = [];

try {
    // Get overall grades/performance
    $stmt = $db->prepare("SELECT * FROM student_grades WHERE student_id = ? ORDER BY created_at DESC LIMIT 1");
    $stmt->execute([$userId]);
    $gradesData = $stmt->fetch();

    // Get performance history
    $stmt = $db->prepare("SELECT * FROM student_grades WHERE student_id = ? ORDER BY period_start DESC LIMIT 10");
    $stmt->execute([$userId]);
    $performanceHistory = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Grades error: " . $e->getMessage());
}

// Calculate overall grade
$overallGrade = $gradesData['overall_grade'] ?? 0;
$gradeLetter = 'N/A';
$gradeColor = '#71717A';
if ($overallGrade >= 90) { $gradeLetter = 'A'; $gradeColor = '#22C55E'; }
elseif ($overallGrade >= 80) { $gradeLetter = 'B'; $gradeColor = '#4ADE80'; }
elseif ($overallGrade >= 70) { $gradeLetter = 'C'; $gradeColor = '#FACC15'; }
elseif ($overallGrade >= 60) { $gradeLetter = 'D'; $gradeColor = '#F97316'; }
elseif ($overallGrade > 0) { $gradeLetter = 'F'; $gradeColor = '#EF4444'; }
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Grades & Performance</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
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
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
      --transition: 200ms cubic-bezier(.4,0,.2,1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; }
    .dashboard-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 0 20px rgba(34,197,94,0.3); }
    .logo-text { font-size: 1.35rem; font-weight: 800; background: linear-gradient(135deg, var(--text-primary), var(--green-glow)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .nav-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3); }
    .nav-item .icon { font-size: 1.1rem; width: 22px; text-align: center; }
    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; color: var(--bg-deep); flex-shrink: 0; }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.75rem; color: var(--text-muted); text-transform: capitalize; }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; text-align: left; margin-top: 0.75rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }
    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; }
    .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .welcome-section h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.25rem; }
    .welcome-section h1 span { color: var(--green-neon); }
    .welcome-section p { color: var(--text-muted); font-size: 0.95rem; }
    .grades-grid { display: grid; grid-template-columns: 280px 1fr; gap: 1.5rem; }
    .grade-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 2rem; text-align: center; }
    .grade-circle { width: 140px; height: 140px; border-radius: 50%; background: conic-gradient(var(--grade-color) calc(var(--percent) * 1%), var(--border-subtle) 0); display: flex; align-items: center; justify-content: center; margin: 0 auto 1.5rem; position: relative; }
    .grade-circle::before { content: ''; position: absolute; inset: 8px; background: var(--bg-card); border-radius: 50%; }
    .grade-value { position: relative; z-index: 1; }
    .grade-letter { font-size: 3rem; font-weight: 900; color: var(--grade-color); }
    .grade-percent { font-size: 1rem; color: var(--text-muted); }
    .grade-label { font-size: 0.9rem; color: var(--text-secondary); margin-bottom: 1.5rem; }
    .grade-details { text-align: left; border-top: 1px solid var(--border-subtle); padding-top: 1.5rem; }
    .detail-row { display: flex; justify-content: space-between; padding: 0.5rem 0; }
    .detail-label { font-size: 0.85rem; color: var(--text-muted); }
    .detail-value { font-size: 0.85rem; font-weight: 600; }
    .scores-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); }
    .scores-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .scores-title { font-size: 1rem; font-weight: 700; }
    .scores-body { padding: 1.5rem; }
    .score-item { margin-bottom: 1.5rem; }
    .score-item:last-child { margin-bottom: 0; }
    .score-header { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
    .score-label { font-size: 0.9rem; color: var(--text-secondary); }
    .score-value { font-weight: 700; }
    .score-bar { height: 8px; background: var(--border-subtle); border-radius: 4px; overflow: hidden; }
    .score-fill { height: 100%; border-radius: 4px; transition: width 0.5s ease; }
    .score-fill.excellent { background: var(--green-neon); }
    .score-fill.good { background: #4ADE80; }
    .score-fill.average { background: #FACC15; }
    .score-fill.poor { background: #F97316; }
    .history-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); margin-top: 1.5rem; }
    .history-header { padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .history-title { font-size: 1rem; font-weight: 700; }
    .history-body { padding: 0; }
    .history-table { width: 100%; }
    .history-table th, .history-table td { padding: 1rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-subtle); }
    .history-table th { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; }
    .history-table td { font-size: 0.9rem; }
    .history-table tr:last-child td { border-bottom: none; }
    .history-table tr:hover td { background: var(--bg-panel); }
    .grade-badge { padding: 0.25rem 0.6rem; border-radius: 20px; font-size: 0.75rem; font-weight: 700; }
    .grade-badge.A { background: rgba(34,197,94,0.15); color: #22C55E; }
    .grade-badge.B { background: rgba(74,222,128,0.15); color: #4ADE80; }
    .grade-badge.C { background: rgba(250,204,21,0.15); color: #FACC15; }
    .grade-badge.D { background: rgba(249,115,22,0.15); color: #F97316; }
    .grade-badge.F { background: rgba(239,68,68,0.15); color: #EF4444; }
    .empty-state { text-align: center; padding: 4rem; }
    .empty-icon { font-size: 4rem; margin-bottom: 1rem; }
    .empty-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
    .empty-desc { font-size: 0.9rem; color: var(--text-muted); }
    .toast-container { position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999; }
    .toast { padding: 1rem 1.5rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 10px; display: flex; gap: 0.75rem; font-size: 0.9rem; animation: slideIn .3s ease; }
    .toast.success { border-color: var(--green-neon); background: rgba(34,197,94,0.15); }
    @keyframes slideIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
    @media (max-width: 900px) { .grades-grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) { .dashboard-layout { grid-template-columns: 1fr; } .sidebar { display: none; } }
  </style>
</head>
<body>
  <div id="toast-container" class="toast-container"></div>
  <div class="dashboard-layout">
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-icon">⚡</div>
        <span class="logo-text">InternTrack</span>
      </div>
      <div class="nav-label">Main Navigation</div>
      <nav class="nav-menu">
        <button class="nav-item" onclick="window.location.href='dashboard.php'">
          <span class="icon">◉</span> Dashboard
        </button>
        <button class="nav-item" onclick="window.location.href='internships.php'">
          <span class="icon">💼</span> Internships
        </button>
        <button class="nav-item" onclick="window.location.href='progress.php'">
          <span class="icon">📓</span> Progress Logs
        </button>
        <button class="nav-item" onclick="window.location.href='companies.php'">
          <span class="icon">🏢</span> Companies
        </button>
      </nav>
      <div class="nav-label">Academic Monitoring</div>
      <nav class="nav-menu">
        <button class="nav-item" onclick="window.location.href='supervisor.php'">
          <span class="icon">👨‍🏫</span> Supervisor
        </button>
        <button class="nav-item" onclick="window.location.href='feedback.php'">
          <span class="icon">💬</span> Supervisor Feedback
        </button>
        <button class="nav-item" onclick="window.location.href='evaluation.php'">
          <span class="icon">📋</span> Evaluation Forms
        </button>
        <button class="nav-item active" onclick="window.location.href='grades.php'">
          <span class="icon">📊</span> Grades & Performance
        </button>
      </nav>
      <div class="sidebar-footer">
        <div class="user-chip">
          <div class="user-avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
          <div class="user-info">
            <div class="user-name"><?= e($user['full_name']) ?></div>
            <div class="user-role"><?= e($user['role']) ?></div>
          </div>
        </div>
        <button class="logout-btn" onclick="logout()">
          <span class="icon">⏻</span> Logout
        </button>
      </div>
    </aside>
    <main class="main-content">
      <header class="top-header">
        <div class="welcome-section">
          <h1>Grades & <span>Performance</span></h1>
          <p>Track your academic performance and progress</p>
        </div>
      </header>
      <?php if ($gradesData || count($performanceHistory) > 0): ?>
      <div class="grades-grid">
        <div class="grade-card">
          <div class="grade-circle" style="--percent: <?= $overallGrade ?>">
            <div class="grade-value">
              <div class="grade-letter"><?= $gradeLetter ?></div>
              <div class="grade-percent"><?= $overallGrade ?>%</div>
            </div>
          </div>
          <div class="grade-label">Overall Grade</div>
          <div class="grade-details">
            <div class="detail-row">
              <span class="detail-label">Total Evaluations</span>
              <span class="detail-value"><?= (int)($gradesData['total_evaluations'] ?? 0) ?></span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Average Score</span>
              <span class="detail-value"><?= number_format($gradesData['average_score'] ?? 0, 1) ?>/5</span>
            </div>
            <div class="detail-row">
              <span class="detail-label">Last Updated</span>
              <span class="detail-value"><?= $gradesData ? date('M j, Y', strtotime($gradesData['created_at'])) : 'N/A' ?></span>
            </div>
          </div>
        </div>
        <div>
          <div class="scores-card">
            <div class="scores-header">
              <h3 class="scores-title">Performance Breakdown</h3>
            </div>
            <div class="scores-body">
              <div class="score-item">
                <div class="score-header">
                  <span class="score-label">Performance</span>
                  <span class="score-value" style="color: <?= $gradesData ? '#22C55E' : '#71717A' ?>"><?= number_format($gradesData['performance_avg'] ?? 0, 1) ?>/5</span>
                </div>
                <div class="score-bar">
                  <div class="score-fill excellent" style="width: <?= (($gradesData['performance_avg'] ?? 0) / 5) * 100 ?>%"></div>
                </div>
              </div>
              <div class="score-item">
                <div class="score-header">
                  <span class="score-label">Professionalism</span>
                  <span class="score-value" style="color: <?= $gradesData ? '#4ADE80' : '#71717A' ?>"><?= number_format($gradesData['professionalism_avg'] ?? 0, 1) ?>/5</span>
                </div>
                <div class="score-bar">
                  <div class="score-fill good" style="width: <?= (($gradesData['professionalism_avg'] ?? 0) / 5) * 100 ?>%"></div>
                </div>
              </div>
              <div class="score-item">
                <div class="score-header">
                  <span class="score-label">Learning & Growth</span>
                  <span class="score-value" style="color: <?= $gradesData ? '#FACC15' : '#71717A' ?>"><?= number_format($gradesData['learning_avg'] ?? 0, 1) ?>/5</span>
                </div>
                <div class="score-bar">
                  <div class="score-fill average" style="width: <?= (($gradesData['learning_avg'] ?? 0) / 5) * 100 ?>%"></div>
                </div>
              </div>
              <div class="score-item">
                <div class="score-header">
                  <span class="score-label">Reports Submitted</span>
                  <span class="score-value"><?= (int)($gradesData['reports_submitted'] ?? 0) ?></span>
                </div>
                <div class="score-bar">
                  <div class="score-fill" style="width: <?= min(100, ($gradesData['reports_submitted'] ?? 0) * 20) ?>%; background: var(--green-neon)"></div>
                </div>
              </div>
            </div>
          </div>
          <?php if (count($performanceHistory) > 0): ?>
          <div class="history-card">
            <div class="history-header">
              <h3 class="history-title">Performance History</h3>
            </div>
            <div class="history-body">
              <table class="history-table">
                <thead>
                  <tr>
                    <th>Period</th>
                    <th>Grade</th>
                    <th>Score</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($performanceHistory as $h): ?>
                  <tr>
                    <td><?= date('M j, Y', strtotime($h['period_start'])) ?> - <?= date('M j, Y', strtotime($h['period_end'])) ?></td>
                    <td><span class="grade-badge <?= $h['grade_letter'] ?>"><?= $h['grade_letter'] ?></span></td>
                    <td><?= $h['overall_grade'] ?>%</td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">📊</div>
        <div class="empty-title">No Performance Data</div>
        <p class="empty-desc">Your grades and performance data will appear here once evaluations are submitted.</p>
      </div>
      <?php endif; ?>
    </main>
  </div>
  <script src="js/app.js"></script>
</body>
</html>