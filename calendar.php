<?php
session_start();
require_once 'php/config.php';
$user = requireAuth();
require_once __DIR__ . '/php/partials/header.php';
$csrf = generateCSRF();

// Admin may filter by student id
$filterStudentId = null;
if ($user['role'] === 'admin' && !empty($_GET['student_id'])) {
    $filterStudentId = (int)$_GET['student_id'];
}
$events = calendarEvents($filterStudentId ?? (int)$user['id'], $filterStudentId);
$eventsJson = json_encode($events, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Calendar</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <link rel="stylesheet" href="css/calendar.css">
  <style>
    :root {
      --bg-deep: #090A0F;
      --bg-charcoal: #0F1020;
      --bg-panel: #12131F;
      --bg-card: #18162B;
      --bg-elevated: #211C3A;
      --border-subtle: rgba(167,139,250,.18);
      --border-light: rgba(196,181,253,.32);
      --primary: #7C3AED;
      --primary-hover: #6D28D9;
      --primary-glow: #A78BFA;
      --green-neon: var(--primary);
      --green-emerald: var(--primary-hover);
      --green-glow: var(--primary-glow);
      --text-primary: #F5F3FF;
      --text-secondary: rgba(245,243,255,.76);
      --text-muted: rgba(245,243,255,.56);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
      --transition: 180ms cubic-bezier(.4,0,.2,1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.55; }

    .dashboard-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--primary-hover), var(--primary)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 0 20px rgba(124,58,237,.3); }
    .logo-text { font-size: 1.35rem; font-weight: 800; color: var(--text-primary); }
    .logo-text span { color: var(--primary); }
    .nav-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(124,58,237,.12); color: var(--primary); box-shadow: inset 0 0 0 1px rgba(124,58,237,.3), 0 0 20px rgba(124,58,237,.1); }
    .nav-item .icon { font-size: 1.1rem; width: 22px; text-align: center; }
    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--primary-hover), var(--primary)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; color: var(--bg-deep); flex-shrink: 0; }
    .user-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.75rem; color: var(--text-muted); text-transform: capitalize; }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; text-align: left; margin-top: 0.75rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }

    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; overflow-y: auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .page-title { font-size: 1.8rem; font-weight: 700; }
    .page-title span { color: var(--primary); }
    .page-sub { color: var(--text-muted); font-size: 0.9rem; margin-top: 0.25rem; }
    .header-actions { display: flex; align-items: center; gap: 1rem; }
    .icon-btn { width: 40px; height: 40px; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all var(--transition); font-size: 1.1rem; }
    .icon-btn:hover { border-color: var(--primary); box-shadow: 0 0 15px rgba(124,58,237,.15); }

    .panel { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; }
    .panel-title { font-weight: 700; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; color: var(--text-primary); }

    @media (max-width: 768px) {
      .dashboard-layout { grid-template-columns: 1fr; }
      .main-content { padding: 1rem; }
    }
  </style>
</head>
<body>
  <div id="toast-container" class="toast-container"></div>
  <canvas id="starfield" aria-hidden="true"></canvas>
  <div class="dashboard-layout">
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-icon"><i class="fas fa-clipboard-list"></i></div>
        <div class="logo-text">Intern<span>Track</span></div>
      </div>
      <div class="nav-label">Main Navigation</div>
      <nav class="nav-menu">
        <button class="nav-item" onclick="window.location.href='dashboard.php'">
          <span class="icon"><i class="fas fa-chart-pie"></i></span> Dashboard
        </button>
        <button class="nav-item" onclick="window.location.href='browse_internships.php'">
          <span class="icon"><i class="fas fa-search"></i></span> Browse Internships
        </button>
        <button class="nav-item active" onclick="window.location.href='calendar.php'">
          <span class="icon"><i class="fas fa-calendar-alt"></i></span> Calendar
        </button>
        <button class="nav-item" onclick="window.location.href='progress.php'">
          <span class="icon"><i class="fas fa-book"></i></span> Progress Logs
        </button>
        <button class="nav-item" onclick="window.location.href='companies.php'">
          <span class="icon"><i class="fas fa-building"></i></span> Companies
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
        <button class="logout-btn" onclick="handleLogout()">
          <span class="icon"><i class="fas fa-sign-out-alt"></i></span> Logout
        </button>
      </div>
    </aside>

    <main class="main-content">
      <header class="page-header">
        <div>
          <h1 class="page-title">Calendar <span>&amp; Timeline</span></h1>
          <p class="page-sub">Your internship milestones, applications, and progress logs</p>
        </div>
        <div class="header-actions">
          <?= renderNotifBell($user) ?>
          <button class="icon-btn" onclick="window.location.href='profile.php'" title="Profile"><i class="fas fa-user" style="color:var(--primary);"></i></button>
        </div>
      </header>

      <?php if ($user['role'] === 'admin'): ?>
        <div class="panel" style="margin-bottom:1.5rem;padding:1rem;">
          <span style="font-size:0.85rem;color:var(--text-muted);">Filter by student ID: </span>
          <input type="number" id="calStudentFilter" min="1" placeholder="student id" style="background:var(--bg-elevated);color:var(--text-primary);border:1px solid var(--border-subtle);border-radius:8px;padding:0.45rem 0.6rem;width:120px;">
          <button class="icon-btn" style="width:auto;padding:0.45rem 1rem;font-size:0.85rem;" onclick="location.href='calendar.php?student_id=' + encodeURIComponent(document.getElementById('calStudentFilter').value)"><i class="fas fa-filter"></i> Apply</button>
        </div>
      <?php endif; ?>

      <div class="calendar-layout">
        <div id="calGrid" class="cal-grid"></div>
        <div id="calDayDetail" class="cal-day-detail" hidden></div>
      </div>

      <div class="panel" style="margin-top:1.5rem;">
        <div class="panel-title"><i class="fas fa-list-ul"></i> Timeline</div>
        <div id="calTimeline"></div>
      </div>
    </main>
  </div>
  <script src="js/app.js"></script>
  <script src="js/interactive.js"></script>
  <script src="js/notifications.js"></script>
  <script>window.CAL_EVENTS = <?= $eventsJson ?>;</script>
  <script src="js/calendar.js"></script>
</body>
</html>
