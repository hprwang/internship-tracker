<?php
session_start();
require_once 'php/config.php';
$user = requireAuth();
$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Profile</title>
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
      --green-muted: #86EFAC;
      --text-primary: #FFFFFF;
      --text-secondary: #A1A1AA;
      --text-muted: #71717A;
      --shadow-soft: 0 4px 24px rgba(0,0,0,0.4);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
      --radius-xl: 24px;
      --transition: 200ms cubic-bezier(.4,0,.2,1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.55; overflow-x: hidden; }

    /* Background Effects */
    .bg-effects { position: fixed; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 0; }
    .bg-effects::before { content: ''; position: absolute; top: -50%; left: -50%; width: 200%; height: 200%; background: radial-gradient(ellipse 80% 60% at 10% 0%, rgba(34,197,94,0.08) 0%, transparent 50%), radial-gradient(ellipse 60% 50% at 90% 100%, rgba(34,197,94,0.06) 0%, transparent 50%); }
    .bg-effects::after { content: ''; position: absolute; top: 15%; left: 10%; width: 400px; height: 400px; background: var(--green-neon); opacity: 0.04; filter: blur(120px); border-radius: 50%; }

    .profile-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; position: relative; z-index: 1; }

    /* Sidebar */
    .sidebar {
      background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem;
      display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto;
    }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--green-neon), var(--green-neon)); border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 0 20px rgba(34,197,94,0.3); }
    .logo-text { font-size: 1.35rem; font-weight: 800; background: linear-gradient(135deg, var(--text-primary), #4ADE80); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .nav-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: 12px; color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s; border: none; background: transparent; width: 100%; text-align: left; }
    .nav-item .icon { font-size: 1.1rem; width: 22px; text-align: center; }
    .nav-item:hover { background: var(--bg-panel); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3), 0 0 20px rgba(34,197,94,0.1); }
    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: 12px; border: 1px solid var(--border-subtle); }
    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green-neon), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; color: var(--bg-deep); }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.75rem; color: var(--text-muted); }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: 12px; color: var(--text-muted); font-size: 0.9rem; cursor: pointer; transition: all 0.2s; border: 1px solid var(--border-subtle); background: transparent; width: 100%; text-align: left; margin-top: 0.75rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }

    /* Main Content */
    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; overflow-y: auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .page-title { font-size: 1.8rem; font-weight: 700; }
    .page-title span { color: var(--green-neon); }
    .edit-btn { padding: 0.75rem 1.5rem; background: linear-gradient(135deg, var(--green-neon), var(--green-neon)); color: var(--bg-deep); border: none; border-radius: 8px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
    .edit-btn:hover { box-shadow: 0 0 20px rgba(34,197,94,0.4); transform: translateY(-2px); }

    /* Profile Header */
    .profile-header { display: flex; gap: 1.5rem; align-items: center; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; padding: 1.5rem; margin-bottom: 1.5rem; position: relative; overflow: hidden; }
    .profile-header::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--green-neon), var(--green-neon)); }
    .profile-pic { width: 100px; height: 100px; background: linear-gradient(135deg, var(--green-neon), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.5rem; font-weight: 800; color: var(--bg-deep); flex-shrink: 0; box-shadow: 0 0 30px rgba(34,197,94,0.4); }
    .profile-pic-wrapper { position: relative; }
    .pic-upload-btn { position: absolute; bottom: 0; right: 0; width: 28px; height: 28px; background: var(--bg-card); border: 2px solid var(--border-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; cursor: pointer; opacity: 0; transition: opacity 0.2s; }
    .profile-pic-wrapper:hover .pic-upload-btn { opacity: 1; }

    /* Editable Inputs */
    .edit-input { background: transparent; border: 1px solid transparent; border-radius: 6px; color: var(--text-primary); font-size: inherit; font-weight: inherit; font-family: inherit; width: 100%; transition: all 0.2s; }
    .edit-input:hover { border-color: var(--border-subtle); background: var(--bg-panel); }
    .edit-input:focus { outline: none; border-color: var(--green-neon); background: var(--bg-panel); box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }
    .edit-input.mini { width: 150px; font-size: 0.85rem; padding: 0.25rem 0.5rem; }
    .name-input { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem; }

    .edit-select { background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: 6px; color: var(--text-primary); font-size: 0.9rem; padding: 0.5rem; cursor: pointer; }
    .edit-select:focus { outline: none; border-color: var(--green-neon); box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }

    .edit-textarea { background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: 8px; color: var(--text-primary); font-size: 0.9rem; padding: 0.75rem; width: 100%; min-height: 100px; resize: vertical; }
    .edit-textarea:focus { outline: none; border-color: var(--green-neon); box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }

    .info-row input, .info-row select { background: transparent; border: 1px solid transparent; border-radius: 4px; color: var(--text-primary); font-size: 0.9rem; padding: 0.25rem 0; width: 60%; text-align: right; transition: all 0.2s; }
    .info-row input:hover, .info-row select:hover { background: var(--bg-panel); border-color: var(--border-subtle); }
    .info-row input:focus, .info-row select:focus { outline: none; border-color: var(--green-neon); background: var(--bg-panel); box-shadow: 0 0 0 2px rgba(34,197,94,0.1); }
    .info-row input::placeholder { color: var(--text-muted); }

    .skill-tag input { background: transparent; border: none; color: var(--green-neon); font-size: 0.8rem; font-weight: 500; width: 80px; padding: 0; }
    .skill-tag input:focus { outline: none; }

    .skills-add { display: flex; gap: 0.5rem; margin-top: 0.5rem; }
    .skills-add input { flex: 1; background: var(--bg-panel); border: 1px dashed var(--border-subtle); border-radius: 20px; color: var(--text-primary); font-size: 0.8rem; padding: 0.5rem 1rem; }
    .skills-add input:focus { outline: none; border-style: solid; border-color: var(--green-neon); }
    .skills-add button { padding: 0.5rem 1rem; background: var(--green-neon); border: none; border-radius: 20px; color: var(--bg-deep); font-size: 0.8rem; font-weight: 600; cursor: pointer; }

    .save-btn { background: linear-gradient(135deg, var(--green-neon), var(--green-neon)); color: var(--bg-deep); font-weight: 700; padding: 0.75rem 2rem; border: none; border-radius: 8px; cursor: pointer; transition: all 0.2s; }
    .save-btn:hover { box-shadow: 0 0 25px rgba(34,197,94,0.5); transform: translateY(-2px); }

    .pref-chip input { display: none; }
    .pref-chip .chip-edit { display: none; }
    .chip-select { background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: 8px; color: var(--text-primary); font-size: 0.8rem; padding: 0.4rem 0.75rem; cursor: pointer; }
    .chip-select.selected { background: rgba(34,197,94,0.15); border-color: var(--green-neon); color: var(--green-neon); }
    .profile-info h2 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.25rem; }
    .profile-info .student-id { color: var(--text-muted); font-size: 0.9rem; margin-bottom: 0.5rem; }
    .profile-info .meta { display: flex; gap: 1rem; flex-wrap: wrap; }
    .meta-item { display: flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); font-size: 0.85rem; }
    .meta-item span:first-child { color: var(--green-neon); }

    /* Quick Stats */
    .stats-grid { display: grid; grid-template-columns: repeat(6, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 12px; padding: 1.25rem; text-align: center; transition: all 0.2s; position: relative; overflow: hidden; }
    .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, var(--green-neon), var(--green-neon)); opacity: 0; transition: opacity 0.2s; }
    .stat-card:hover::before { opacity: 1; }
    .stat-card:hover { border-color: rgba(34,197,94,0.4); transform: translateY(-2px); box-shadow: 0 8px 30px rgba(34,197,94,0.15); }
    .stat-card .stat-value { font-size: 1.75rem; font-weight: 800; color: var(--green-neon); margin-bottom: 0.25rem; }
    .stat-card .stat-label { font-size: 0.75rem; color: var(--text-muted); }
    .stat-card .stat-rate { font-size: 0.8rem; color: var(--green-neon); font-weight: 600; }

    /* Grid Layout */
    .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.5rem; margin-bottom: 1.5rem; }
    .info-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 16px; overflow: hidden; position: relative; }
    .info-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--green-neon), var(--green-neon)); }
    .info-card.full-width { grid-column: 1 / -1; }
    .card-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .card-title { font-size: 1rem; font-weight: 700; }
    .card-body { padding: 1.25rem 1.5rem; }

    /* Info Rows */
    .info-row { display: flex; justify-content: space-between; padding: 0.75rem 0; border-bottom: 1px solid var(--border-subtle); }
    .info-row:last-child { border-bottom: none; }
    .info-label { color: var(--text-muted); font-size: 0.85rem; }
    .info-value { font-weight: 500; font-size: 0.9rem; }

    /* Skills */
    .skills-container { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .skill-tag { padding: 0.5rem 1rem; background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); border-radius: 20px; font-size: 0.8rem; font-weight: 500; color: var(--green-neon); }
    .skill-level { font-size: 0.7rem; color: var(--text-muted); margin-left: 0.5rem; }

    /* Preferences */
    .pref-group { margin-bottom: 1rem; }
    .pref-label { font-size: 0.8rem; color: var(--text-muted); margin-bottom: 0.5rem; }
    .pref-chips { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .pref-chip { padding: 0.4rem 0.8rem; background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: 8px; font-size: 0.8rem; }
    .pref-chip.active { background: rgba(34,197,94,0.15); border-color: var(--green-neon); color: var(--green-neon); }

    /* Documents */
    .doc-list { display: flex; flex-direction: column; gap: 0.75rem; }
    .doc-item { display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--bg-panel); border-radius: 12px; border: 1px solid var(--border-subtle); }
    .doc-info { display: flex; align-items: center; gap: 0.75rem; }
    .doc-icon { font-size: 1.5rem; }
    .doc-name { font-weight: 500; }
    .doc-meta { font-size: 0.8rem; color: var(--text-muted); }
    .doc-action { padding: 0.5rem 1rem; background: transparent; border: 1px solid var(--border-subtle); border-radius: 8px; color: var(--text-secondary); font-size: 0.8rem; cursor: pointer; transition: all 0.2s; }
    .doc-action:hover { border-color: var(--green-neon); color: var(--green-neon); }

    /* Achievements */
    .achievement-list { display: flex; flex-direction: column; gap: 0.75rem; }
    .achievement-item { display: flex; align-items: center; gap: 1rem; padding: 1rem; background: var(--bg-panel); border-radius: 12px; border-left: 3px solid var(--green-neon); }
    .achievement-icon { font-size: 1.5rem; }
    .achievement-info h4 { font-size: 0.9rem; font-weight: 600; margin-bottom: 0.2rem; }
    .achievement-info p { font-size: 0.8rem; color: var(--text-muted); }

    /* Analytics Chart */
    .chart-container { height: 180px; display: flex; align-items: flex-end; gap: 0.75rem; padding-top: 1rem; }
    .chart-bar { flex: 1; background: linear-gradient(to top, var(--green-neon), var(--green-neon)); border-radius: 8px 8px 0 0; min-height: 8px; position: relative; }
    .chart-bar::after { content: ''; position: absolute; inset: 0; background: linear-gradient(90deg, transparent, rgba(255,255,255,0.15), transparent); }
    .chart-label { position: absolute; bottom: -24px; left: 50%; transform: translateX(-50%); font-size: 0.7rem; color: var(--text-muted); white-space: nowrap; }
    .chart-value { position: absolute; top: -24px; left: 50%; transform: translateX(-50%); font-size: 0.75rem; font-weight: 600; color: var(--text-primary); }

    /* Settings */
    .settings-list { display: flex; flex-direction: column; gap: 0.5rem; }
    .settings-item { display: flex; align-items: center; justify-content: space-between; padding: 1rem; background: var(--bg-panel); border-radius: 12px; cursor: pointer; transition: all 0.2s; }
    .settings-item:hover { background: var(--border-subtle); }
    .settings-left { display: flex; align-items: center; gap: 0.75rem; }
    .settings-icon { font-size: 1.2rem; }
    .settings-text h4 { font-size: 0.9rem; font-weight: 500; }
    .settings-text p { font-size: 0.8rem; color: var(--text-muted); }
    .toggle { width: 44px; height: 24px; background: var(--border-subtle); border-radius: 12px; position: relative; cursor: pointer; transition: all 0.2s; }
    .toggle.active { background: var(--green-neon); }
    .toggle::after { content: ''; position: absolute; top: 2px; left: 2px; width: 20px; height: 20px; background: white; border-radius: 50%; transition: all 0.2s; }
    .toggle.active::after { left: 22px; }

    @media (max-width: 1100px) {
      .profile-layout { grid-template-columns: 220px 1fr; }
      .stats-grid { grid-template-columns: repeat(3, 1fr); }
      .content-grid { grid-template-columns: 1fr; }
    }
    @media (max-width: 768px) {
      .profile-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .profile-header { flex-direction: column; text-align: center; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
    }
</style>
</head>
<body>
  <canvas id="starfield" aria-hidden="true"></canvas>
  <div class="bg-effects"></div>
  <div class="profile-layout">
    <!-- Sidebar -->
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
      <div class="sidebar-footer">
        <div class="user-chip">
          <div class="user-avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
          <div class="user-info">
            <div class="user-name"><?= e($user['full_name']) ?></div>
            <div class="user-role"><?= e($user['role']) ?></div>
          </div>
        </div>
        <button class="logout-btn" onclick="logout()">
          <span>⏻</span> Logout
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <!-- Header -->
      <header class="page-header">
        <h1 class="page-title">My <span>Profile</span></h1>
        <button type="submit" form="profile-form" class="save-btn">Save Profile</button>
      </header>

      <!-- Profile Header -->
      <form id="profile-form" method="POST" action="profile.php" enctype="multipart/form-data">
      <div class="profile-header">
        <div class="profile-pic-wrapper">
          <div class="profile-pic"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
          <label class="pic-upload-btn" for="profile_pic">📷</label>
          <input type="file" id="profile_pic" name="profile_pic" accept="image/*" style="display:none">
        </div>
        <div class="profile-info">
          <input type="text" name="full_name" value="<?= e($user['full_name']) ?>" class="edit-input name-input">
          <p class="student-id">Student ID: <?= e($user['id'] ?? 'STU000000') ?></p>
          <div class="meta">
            <span class="meta-item"><span>📧</span> <input type="email" name="email" value="<?= e($user['email'] ?? '') ?>" class="edit-input mini"></span>
            <span class="meta-item"><span>📍</span> <input type="text" name="location" value="" placeholder="Add location" class="edit-input mini"></span>
          </div>
        </div>
      </div>

      <!-- Quick Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value">0</div>
          <div class="stat-label">Applications</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">0</div>
          <div class="stat-label">Interviews</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">0</div>
          <div class="stat-label">Offers</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">0</div>
          <div class="stat-label">Active</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">0</div>
          <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">0%</div>
          <div class="stat-label">Success Rate</div>
        </div>
      </div>

      <!-- Info Grid -->
      <div class="content-grid">
        <!-- Academic Info -->
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">🎓 Academic Information</h3>
          </div>
          <div class="card-body">
            <div class="info-row"><span class="info-label">University</span><input type="text" name="university" value="" placeholder="Enter university"></div>
            <div class="info-row"><span class="info-label">Faculty</span><input type="text" name="faculty" value="" placeholder="Enter faculty"></div>
            <div class="info-row"><span class="info-label">Major</span><input type="text" name="major" value="" placeholder="Enter major"></div>
            <div class="info-row"><span class="info-label">GPA</span><input type="text" name="gpa" value="" placeholder="e.g., 3.75 / 4.0"></div>
            <div class="info-row"><span class="info-label">Graduation</span><input type="text" name="graduation_date" value="" placeholder="e.g., June 2026"></div>
            <div class="info-row"><span class="info-label">Coursework</span><input type="text" name="coursework" value="" placeholder="Enter relevant courses"></div>
          </div>
        </div>

        <!-- Professional Info -->
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">💼 Professional Information</h3>
          </div>
          <div class="card-body">
            <div class="info-row"><span class="info-label">Career Field</span><input type="text" name="career_field" value="" placeholder="e.g., Software Engineering"></div>
            <div class="info-row"><span class="info-label">Portfolio</span><input type="url" name="portfolio" value="" placeholder="https://"></div>
            <div class="info-row"><span class="info-label">LinkedIn</span><input type="url" name="linkedin" value="" placeholder="https://linkedin.com/in/"></div>
            <div class="info-row"><span class="info-label">GitHub</span><input type="url" name="github" value="" placeholder="https://github.com/"></div>
            <div class="info-row"><span class="info-label">Languages</span><input type="text" name="languages" value="" placeholder="e.g., English, Spanish"></div>
          </div>
        </div>

        <!-- Skills -->
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">🛠 Skills</h3>
          </div>
          <div class="card-body">
            <div class="skills-container" id="skills-container">
              <!-- Skills will be added here dynamically -->
            </div>
            <div class="skills-add">
              <input type="text" id="new-skill" placeholder="Add a skill...">
              <select id="skill-level" class="edit-select">
                <option value="Beginner">Beginner</option>
                <option value="Intermediate">Intermediate</option>
                <option value="Advanced">Advanced</option>
                <option value="Expert">Expert</option>
              </select>
              <button type="button" onclick="addSkill()">Add</button>
            </div>
          </div>
        </div>

        <!-- Resume & Documents -->
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">📄 Resume & Documents</h3>
            <label class="doc-action" style="padding:0.5rem 1rem;cursor:pointer">
              📤 Upload
              <input type="file" name="documents[]" multiple accept=".pdf,.doc,.docx" style="display:none">
            </label>
          </div>
          <div class="card-body">
            <div class="doc-list" id="doc-list">
              <div class="empty-message">No documents uploaded yet</div>
            </div>
          </div>
        </div>

        <!-- Career Preferences -->
        <div class="info-card full-width">
          <div class="card-header">
            <h3 class="card-title">🎯 Career Preferences</h3>
          </div>
          <div class="card-body">
            <div class="content-grid" style="margin-bottom:0">
              <div class="pref-group">
                <div class="pref-label">Internship Type</div>
                <div class="pref-chips">
                  <label class="chip-select"><input type="checkbox" name="internship_type[]" value="Remote"> Remote</label>
                  <label class="chip-select"><input type="checkbox" name="internship_type[]" value="Hybrid"> Hybrid</label>
                  <label class="chip-select"><input type="checkbox" name="internship_type[]" value="On-site"> On-site</label>
                </div>
              </div>
              <div class="pref-group">
                <div class="pref-label">Expected Stipend</div>
                <select name="expected_stipend" class="edit-select">
                  <option value="">Select range</option>
                  <option value="5000-10000">Rs. 5,000-10,000/mo</option>
                  <option value="10000-20000">Rs. 10,000-20,000/mo</option>
                  <option value="20000-35000">Rs. 20,000-35,000/mo</option>
                  <option value="35000-50000">Rs. 35,000-50,000/mo</option>
                  <option value="50000+">Rs. 50,000+/mo</option>
                </select>
              </div>
              <div class="pref-group">
                <div class="pref-label">Preferred Industries</div>
                <input type="text" name="industries" class="edit-input" placeholder="e.g., Tech, Finance, Healthcare">
              </div>
              <div class="pref-group">
                <div class="pref-label">Availability Date</div>
                <input type="text" name="availability" class="edit-input" placeholder="e.g., Immediately, Summer 2026">
              </div>
              <div class="pref-group">
                <div class="pref-label">Preferred Locations</div>
                <input type="text" name="locations" class="edit-input" placeholder="e.g., New York, Remote">
              </div>
            </div>
          </div>
        </div>

        <!-- Application Analytics -->
        <div class="info-card" style="display:none">
          <div class="card-header">
            <h3 class="card-title">📈 Application Insights</h3>
          </div>
          <div class="card-body">
            <div class="empty-message">Applications will appear here as you apply.</div>
          </div>
        </div>

        <!-- Achievements -->
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">🏆 Achievements</h3>
          </div>
          <div class="card-body">
            <div class="achievement-list" id="achievement-list">
              <div class="empty-message">No achievements added yet</div>
            </div>
          </div>
        </div>

        <!-- Notification Settings -->
        <div class="info-card">
          <div class="card-header">
            <h3 class="card-title">🔔 Notification Settings</h3>
          </div>
          <div class="card-body">
            <div class="settings-list">
              <div class="settings-item">
                <div class="settings-left">
                  <span class="settings-icon">📧</span>
                  <div class="settings-text">
                    <h4>Email Notifications</h4>
                    <p>Receive updates via email</p>
                  </div>
                </div>
                <input type="checkbox" name="notify_email" checked style="display:none">
                <label class="toggle active" for="notify_email"></label>
              </div>
              <div class="settings-item">
                <div class="settings-left">
                  <span class="settings-icon">🎯</span>
                  <div class="settings-text">
                    <h4>Interview Reminders</h4>
                    <p>24 hours before interviews</p>
                  </div>
                </div>
                <input type="checkbox" name="notify_interview" checked style="display:none">
                <label class="toggle active" for="notify_interview"></label>
              </div>
              <div class="settings-item">
                <div class="settings-left">
                  <span class="settings-icon">⏰</span>
                  <div class="settings-text">
                    <h4>Application Deadlines</h4>
                    <p>Reminder before closing</p>
                  </div>
                </div>
                <input type="checkbox" name="notify_deadlines" checked style="display:none">
                <label class="toggle active" for="notify_deadlines"></label>
              </div>
              <div class="settings-item">
                <div class="settings-left">
                  <span class="settings-icon">📊</span>
                  <div class="settings-text">
                    <h4>Weekly Reports</h4>
                    <p>Progress summary</p>
                  </div>
                </div>
                <input type="checkbox" name="notify_weekly" style="display:none">
                <label class="toggle" for="notify_weekly"></label>
              </div>
            </div>
          </div>
        </div>

        <!-- Account Settings -->
        <div class="info-card full-width">
          <div class="card-header">
            <h3 class="card-title">🔒 Account Settings</h3>
          </div>
          <div class="card-body">
            <div class="settings-list">
              <a href="change_password.php" class="settings-item" style="text-decoration:none;color:inherit">
                <div class="settings-left">
                  <span class="settings-icon">🔑</span>
                  <div class="settings-text">
                    <h4>Change Password</h4>
                    <p>Update your account password</p>
                  </div>
                </div>
                <button type="button" class="doc-action">Change</button>
              </a>
              <div class="settings-item">
                <div class="settings-left">
                  <span class="settings-icon">🔐</span>
                  <div class="settings-text">
                    <h4>Two-Factor Authentication</h4>
                    <p>Add an extra layer of security</p>
                  </div>
                </div>
                <button type="button" class="doc-action">Enable</button>
              </div>
              <div class="settings-item">
                <div class="settings-left">
                  <span class="settings-icon">🛡️</span>
                  <div class="settings-text">
                    <h4>Privacy Settings</h4>
                    <p>Control your profile visibility</p>
                  </div>
                </div>
                <button type="button" class="doc-action">Configure</button>
              </div>
            </div>
          </div>
        </div>

        </form>
    </main>
  </div>
</body>
</html>
<script src="js/app.js"></script>
<script>
  // Chip select functionality
  document.querySelectorAll('.chip-select').forEach(chip => {
    chip.addEventListener('click', function() {
      this.classList.toggle('selected');
    });
  });

  // Toggle functionality
  document.querySelectorAll('.toggle').forEach(toggle => {
    toggle.addEventListener('click', function() {
      this.classList.toggle('active');
      const checkbox = this.previousElementSibling;
      if (checkbox && checkbox.type === 'checkbox') {
        checkbox.checked = this.classList.contains('active');
      }
    });
  });

  // Skills add functionality
  function addSkill() {
    const input = document.getElementById('new-skill');
    const level = document.getElementById('skill-level');
    const container = document.getElementById('skills-container');
    if (input.value.trim()) {
      const tag = document.createElement('span');
      tag.className = 'skill-tag';
      tag.innerHTML = `${input.value} <span class="skill-level">${level.value}</span>`;
      tag.onclick = function() { this.remove(); };
      container.appendChild(tag);
      input.value = '';
    }
  }

  // Achievement add functionality
  function addAchievement() {
    const list = document.getElementById('achievement-list');
    const empty = list.querySelector('.empty-message');
    if (empty) empty.remove();
    const item = document.createElement('div');
    item.className = 'achievement-item';
    item.innerHTML = `
      <span class="achievement-icon">🏅</span>
      <div class="achievement-info">
        <input type="text" name="achievements[]" placeholder="Achievement title" class="edit-input">
        <input type="text" name="achievement_dates[]" placeholder="Date" class="edit-input mini" style="margin-top:0.25rem">
      </div>
    `;
    list.appendChild(item);
  }

  // Make chips toggle on click
  document.querySelectorAll('.pref-chips label').forEach(label => {
    label.addEventListener('click', function(e) {
      e.preventDefault();
      this.classList.toggle('selected');
      const checkbox = this.querySelector('input');
      if (checkbox) checkbox.checked = this.classList.contains('selected');
    });
  });
</script>