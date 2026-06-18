<?php
session_start();
require_once 'php/config.php';
$user = requireAuth();
$csrf = generateCSRF();
$isAdmin = $user['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Dashboard</title>
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
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.55; }

    .dashboard-layout {
      display: grid;
      grid-template-columns: 260px 1fr;
      min-height: 100vh;
    }

    /* Sidebar */
    .sidebar {
      background: var(--bg-charcoal);
      border-right: 1px solid var(--border-subtle);
      padding: 1.5rem 1rem;
      display: flex;
      flex-direction: column;
      position: sticky;
      top: 0;
      height: 100vh;
      overflow-y: auto;
    }

    .sidebar-logo {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0 0.75rem 1.5rem;
      border-bottom: 1px solid var(--border-subtle);
      margin-bottom: 1.5rem;
    }

    .logo-icon {
      width: 40px;
      height: 40px;
      background: linear-gradient(135deg, var(--green-emerald), var(--green-neon));
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      box-shadow: 0 0 20px rgba(34,197,94,0.3);
    }

    .logo-text {
      font-size: 1.35rem;
      font-weight: 800;
      background: linear-gradient(135deg, var(--text-primary), var(--green-glow));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .nav-label {
      font-size: 0.7rem;
      font-weight: 700;
      letter-spacing: 0.12em;
      text-transform: uppercase;
      color: var(--text-muted);
      padding: 0 0.75rem;
      margin-bottom: 0.5rem;
    }

    .nav-menu {
      display: flex;
      flex-direction: column;
      gap: 0.25rem;
      flex: 1;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem;
      border-radius: var(--radius-md);
      color: var(--text-secondary);
      font-size: 0.9rem;
      font-weight: 500;
      cursor: pointer;
      transition: all var(--transition);
      border: none;
      background: transparent;
      width: 100%;
      text-align: left;
    }

    .nav-item:hover {
      background: var(--bg-card);
      color: var(--text-primary);
    }

    .nav-item.active {
      background: rgba(34,197,94,0.12);
      color: var(--green-neon);
      box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3), 0 0 20px rgba(34,197,94,0.1);
    }

    .nav-item .icon {
      font-size: 1.1rem;
      width: 22px;
      text-align: center;
    }

    .sidebar-footer {
      margin-top: auto;
      padding-top: 1rem;
      border-top: 1px solid var(--border-subtle);
    }

    .user-chip {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem;
      background: var(--bg-card);
      border-radius: var(--radius-md);
      border: 1px solid var(--border-subtle);
    }

    .user-avatar {
      width: 36px;
      height: 36px;
      background: linear-gradient(135deg, var(--green-emerald), var(--green-neon));
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.9rem;
      color: var(--bg-deep);
      flex-shrink: 0;
    }

    .user-info {
      flex: 1;
      min-width: 0;
    }

    .user-name {
      font-size: 0.9rem;
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .user-role {
      font-size: 0.75rem;
      color: var(--text-muted);
      text-transform: capitalize;
    }

    .logout-btn {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.75rem;
      border-radius: var(--radius-md);
      color: var(--text-muted);
      font-size: 0.9rem;
      font-weight: 500;
      cursor: pointer;
      transition: all var(--transition);
      border: 1px solid var(--border-subtle);
      background: transparent;
      width: 100%;
      text-align: left;
      margin-top: 0.75rem;
    }

    .logout-btn:hover {
      border-color: rgba(239,68,68,0.4);
      color: #F87171;
      background: rgba(239,68,68,0.08);
    }

    /* Main Content */
    .main-content {
      background: var(--bg-deep);
      padding: 1.5rem 2rem;
      overflow-y: auto;
    }

    .top-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 2rem;
      padding-bottom: 1.5rem;
      border-bottom: 1px solid var(--border-subtle);
    }

    .welcome-section h1 {
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 0.25rem;
    }

    .welcome-section h1 span {
      color: var(--green-neon);
    }

    .welcome-section p {
      color: var(--text-muted);
      font-size: 0.95rem;
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 1rem;
    }

    .search-box {
      display: flex;
      align-items: center;
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-md);
      padding: 0.5rem 1rem;
      gap: 0.5rem;
      min-width: 240px;
    }

    .search-box input {
      background: none;
      border: none;
      outline: none;
      color: var(--text-primary);
      font-size: 0.9rem;
      width: 100%;
    }

    .search-box input::placeholder {
      color: var(--text-muted);
    }

    .icon-btn {
      width: 40px;
      height: 40px;
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: all var(--transition);
      font-size: 1.1rem;
    }

    .icon-btn:hover {
      border-color: var(--green-neon);
      box-shadow: 0 0 15px rgba(34,197,94,0.15);
    }

    /* KPI Grid */
    .kpi-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin-bottom: 1.5rem;
    }

    .kpi-card {
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-lg);
      padding: 1.25rem;
      transition: all var(--transition);
      position: relative;
      overflow: hidden;
    }

    .kpi-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 3px;
      background: linear-gradient(90deg, var(--green-emerald), var(--green-neon));
      opacity: 0;
      transition: opacity var(--transition);
    }

    .kpi-card:hover::before {
      opacity: 1;
    }

    .kpi-card:hover {
      border-color: var(--border-light);
      transform: translateY(-2px);
      box-shadow: var(--shadow-soft);
    }

    .kpi-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.75rem;
    }

    .kpi-label {
      font-size: 0.8rem;
      color: var(--text-muted);
      font-weight: 600;
    }

    .kpi-icon {
      width: 36px;
      height: 36px;
      background: rgba(34,197,94,0.1);
      border-radius: var(--radius-sm);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1rem;
    }

    .kpi-value {
      font-size: 2rem;
      font-weight: 800;
      margin-bottom: 0.25rem;
    }

    .kpi-value.green {
      color: var(--green-neon);
    }

    .kpi-sub {
      font-size: 0.8rem;
      color: var(--text-muted);
    }

    /* Dashboard Grid */
    .dash-grid {
      display: grid;
      grid-template-columns: 1fr 380px;
      gap: 1.5rem;
    }

    /* Cards */
    .dash-card {
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-lg);
      overflow: hidden;
    }

    .dash-card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 1.25rem 1.5rem;
      border-bottom: 1px solid var(--border-subtle);
    }

    .dash-card-title {
      font-size: 1rem;
      font-weight: 700;
    }

    .dash-card-subtitle {
      font-size: 0.8rem;
      color: var(--text-muted);
    }

    .dash-card-body {
      padding: 1.25rem 1.5rem;
    }

    /* Bar Chart */
    .bar-chart {
      display: flex;
      align-items: flex-end;
      gap: 0.75rem;
      height: 140px;
      padding-top: 1rem;
    }

    .bar-item {
      flex: 1;
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 0.5rem;
    }

    .bar-fill {
      width: 100%;
      background: linear-gradient(to top, var(--green-emerald), var(--green-neon));
      border-radius: var(--radius-sm) var(--radius-sm) 0 0;
      min-height: 8px;
      transition: height 0.6s ease;
      position: relative;
    }

    .bar-fill::after {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
      opacity: 0.5;
    }

    .bar-label {
      font-size: 0.7rem;
      color: var(--text-muted);
      text-align: center;
    }

    .bar-value {
      font-size: 0.75rem;
      font-weight: 700;
      color: var(--text-primary);
    }

    /* Progress Ring */
    .progress-ring-section {
      display: flex;
      flex-direction: column;
      align-items: center;
      padding: 1.5rem;
    }

    .progress-ring {
      position: relative;
      width: 140px;
      height: 140px;
    }

    .progress-ring svg {
      transform: rotate(-90deg);
    }

    .progress-ring circle {
      fill: none;
      stroke-width: 10;
    }

    .progress-ring .bg {
      stroke: var(--border-subtle);
    }

    .progress-ring .progress {
      stroke: var(--green-neon);
      stroke-linecap: round;
      stroke-dasharray: 408;
      stroke-dashoffset: 90;
      filter: drop-shadow(0 0 8px rgba(34,197,94,0.5));
    }

    .progress-ring-value {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      text-align: center;
    }

    .progress-ring-value .percent {
      font-size: 2rem;
      font-weight: 800;
      color: var(--green-neon);
    }

    .progress-ring-value .label {
      font-size: 0.75rem;
      color: var(--text-muted);
    }

    /* Task List */
    .task-list {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .task-item {
      display: flex;
      align-items: center;
      gap: 0.75rem;
      padding: 0.875rem 1rem;
      background: var(--bg-panel);
      border-radius: var(--radius-md);
      cursor: pointer;
      transition: all var(--transition);
    }

    .task-item:hover {
      background: var(--bg-elevated);
    }

    .task-checkbox {
      width: 20px;
      height: 20px;
      border: 2px solid var(--border-light);
      border-radius: 6px;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all var(--transition);
      flex-shrink: 0;
    }

    .task-item.completed .task-checkbox {
      background: var(--green-neon);
      border-color: var(--green-neon);
    }

    .task-item.completed .task-checkbox::after {
      content: '✓';
      color: var(--bg-deep);
      font-size: 0.7rem;
      font-weight: 700;
    }

    .task-text {
      flex: 1;
      font-size: 0.9rem;
      font-weight: 500;
    }

    .task-item.completed .task-text {
      text-decoration: line-through;
      color: var(--text-muted);
    }

    /* Deadlines */
    .deadline-list {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .deadline-item {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 1rem;
      background: var(--bg-panel);
      border-radius: var(--radius-md);
      border-left: 3px solid var(--green-neon);
    }

    .deadline-info h4 {
      font-size: 0.9rem;
      font-weight: 600;
      margin-bottom: 0.2rem;
    }

    .deadline-info p {
      font-size: 0.8rem;
      color: var(--text-muted);
    }

    .deadline-date {
      text-align: right;
    }

    .deadline-date .days {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--green-neon);
    }

    .deadline-date .label {
      font-size: 0.7rem;
      color: var(--text-muted);
    }

    /* Performance */
    .perf-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-top: 0.5rem;
    }

    .perf-row span:first-child {
      font-size: 0.9rem;
      color: var(--text-secondary);
    }

    .perf-row span:last-child {
      font-weight: 700;
      color: var(--green-neon);
    }

    .progress-bar {
      height: 8px;
      background: var(--border-subtle);
      border-radius: 4px;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, var(--green-emerald), var(--green-neon));
      border-radius: 4px;
      box-shadow: 0 0 10px rgba(34,197,94,0.4);
    }

    /* Stats Row */
    .stats-row {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: 1rem;
      margin-top: 1.5rem;
    }

    .stat-box {
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-md);
      padding: 1.25rem;
      text-align: center;
    }

    .stat-box .icon {
      font-size: 1.5rem;
      margin-bottom: 0.5rem;
    }

    .stat-box .value {
      font-size: 1.5rem;
      font-weight: 800;
      color: var(--green-neon);
    }

    .stat-box .label {
      font-size: 0.75rem;
      color: var(--text-muted);
      margin-top: 0.25rem;
    }

    /* Right Panel */
    .right-panel {
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-lg);
      padding: 1.25rem;
    }

    .rp-title {
      font-size: 0.95rem;
      font-weight: 700;
      margin-bottom: 1rem;
    }

    .rp-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 0.5rem 0;
      border-bottom: 1px solid var(--border-subtle);
    }

    .rp-row:last-child {
      border-bottom: none;
    }

    .rp-label {
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    .rp-value {
      font-size: 0.85rem;
      font-weight: 600;
    }

    /* Responsive */
    @media (max-width: 1100px) {
      .dashboard-layout {
        grid-template-columns: 220px 1fr;
      }
      .dash-grid {
        grid-template-columns: 1fr;
      }
      .kpi-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      .stats-row {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 768px) {
      .dashboard-layout {
        grid-template-columns: 1fr;
      }
      .sidebar {
        display: none;
      }
      .kpi-grid {
        grid-template-columns: 1fr 1fr;
      }
    }
  </style>
</head>
<body>
  <div id="toast-container" class="toast-container"></div>

  <div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-icon">⚡</div>
        <span class="logo-text">InternTrack</span>
      </div>

      <div class="nav-label">Main Navigation</div>
      <nav class="nav-menu">
        <button class="nav-item active" onclick="navTo('dashboard')">
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
          <span class="icon">⏻</span> Logout
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <!-- Top Header -->
      <header class="top-header">
        <div class="welcome-section">
          <h1>Welcome back, <span><?= e($user['full_name']) ?></span></h1>
          <p>Track your applications, tasks, deadlines, and internship progress</p>
        </div>
        <div class="header-actions">
          <div class="search-box">
            <span>🔍</span>
            <input type="text" placeholder="Search...">
          </div>
          <button class="icon-btn">🔔</button>
          <button class="icon-btn" onclick="window.location.href='profile.php'" title="Profile">👤</button>
        </div>
      </header>

      <!-- Statistics Cards -->
      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-header">
            <span class="kpi-label">Total Applications</span>
            <div class="kpi-icon">📋</div>
          </div>
          <div class="kpi-value green">0</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-header">
            <span class="kpi-label">Ongoing</span>
            <div class="kpi-icon">🚀</div>
          </div>
          <div class="kpi-value green">0</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-header">
            <span class="kpi-label">Interviews</span>
            <div class="kpi-icon">🎯</div>
          </div>
          <div class="kpi-value green">0</div>
        </div>

        <div class="kpi-card">
          <div class="kpi-header">
            <span class="kpi-label">Completed</span>
            <div class="kpi-icon">✅</div>
          </div>
          <div class="kpi-value green">0</div>
        </div>
      </div>

      <!-- Main Grid -->
      <div class="dash-grid">
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
          <!-- Recent Applications Section -->
          <div class="dash-card">
            <div class="dash-card-header">
              <h3 class="dash-card-title">Recent Applications</h3>
            </div>
            <div class="dash-card-body">
              <table class="data-table">
                <thead>
                  <tr>
                    <th>Role</th>
                    <th>Company</th>
                    <th>Status</th>
                    <th>Start Date</th>
                  </tr>
                </thead>
                <tbody>
                  <tr>
                    <td colspan="4" class="empty-message">No internships yet</td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>

          <!-- Status Breakdown Panel -->
          <div class="dash-card">
            <div class="dash-card-header">
              <h3 class="dash-card-title">Status Breakdown</h3>
            </div>
            <div class="dash-card-body">
              <div class="bar-chart">
                <div class="bar-item">
                  <div class="bar-value">0</div>
                  <div class="bar-fill" style="height:8px;"></div>
                  <div class="bar-label">Applied</div>
                </div>
                <div class="bar-item">
                  <div class="bar-value">0</div>
                  <div class="bar-fill" style="height:8px;"></div>
                  <div class="bar-label">Interview</div>
                </div>
                <div class="bar-item">
                  <div class="bar-value">0</div>
                  <div class="bar-fill" style="height:8px;"></div>
                  <div class="bar-label">Accepted</div>
                </div>
                <div class="bar-item">
                  <div class="bar-value">0</div>
                  <div class="bar-fill" style="height:8px;"></div>
                  <div class="bar-label">Ongoing</div>
                </div>
                <div class="bar-item">
                  <div class="bar-value">0</div>
                  <div class="bar-fill" style="height:8px;"></div>
                  <div class="bar-label">Completed</div>
                </div>
                <div class="bar-item">
                  <div class="bar-value">0</div>
                  <div class="bar-fill" style="height:8px;"></div>
                  <div class="bar-label">Rejected</div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Right Column -->
        <div style="display:flex;flex-direction:column;gap:1.5rem;">
          <!-- Recent Activity Section -->
          <div class="dash-card">
            <div class="dash-card-header">
              <h3 class="dash-card-title">Recent Activity</h3>
            </div>
            <div class="dash-card-body">
              <div class="empty-message">No activity yet</div>
            </div>
          </div>

          <!-- Upcoming Interviews Panel -->
          <div class="dash-card">
            <div class="dash-card-header">
              <h3 class="dash-card-title">Upcoming Interviews</h3>
            </div>
            <div class="dash-card-body">
              <div class="empty-message">No upcoming interviews</div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
</body>
</html>

<script src="js/app.js"></script>