<?php
session_start();
require_once 'php/config.php';
$user = requireAuth();
$csrf = generateCSRF();
$db = Database::getConnection();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Progress Logs</title>
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
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.55; }

    .dashboard-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }

    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }

    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; }

    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 0 20px rgba(34,197,94,0.3); }

    .logo-text { font-size: 1.35rem; font-weight: 800; background: linear-gradient(135deg, var(--text-primary), var(--green-glow)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

    .nav-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }

    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }

    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; }

    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }

    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3), 0 0 20px rgba(34,197,94,0.1); }

    .nav-item .icon { font-size: 1.1rem; width: 22px; text-align: center; }

    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }

    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }

    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; color: var(--bg-deep); flex-shrink: 0; }

    .user-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    .user-role { font-size: 0.75rem; color: var(--text-muted); text-transform: capitalize; }

    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; text-align: left; margin-top: 0.75rem; }

    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }

    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; overflow-y: auto; }

    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }

    .page-title { font-size: 1.8rem; font-weight: 700; }

    .page-title span { color: var(--green-neon); }

    .header-actions { display: flex; align-items: center; gap: 1rem; }

    .icon-btn { width: 40px; height: 40px; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all var(--transition); font-size: 1.1rem; }

    .icon-btn:hover { border-color: var(--green-neon); box-shadow: 0 0 15px rgba(34,197,94,0.15); }

    .add-btn { background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); color: var(--bg-deep); font-weight: 700; padding: 0.75rem 1.5rem; border: none; border-radius: var(--radius-md); cursor: pointer; transition: all var(--transition); }

    .add-btn:hover { box-shadow: 0 0 25px rgba(34,197,94,0.5); transform: translateY(-2px); }

    /* Stats Cards */
    .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }

    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1rem 1.25rem; transition: all var(--transition); }

    .stat-card:hover { border-color: var(--border-light); transform: translateY(-2px); }

    .stat-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.5rem; }

    .stat-value { font-size: 1.75rem; font-weight: 800; color: var(--green-neon); }

    /* Select */
    .select-section { margin-bottom: 1.5rem; }

    .select-group { display: flex; flex-direction: column; gap: 0.5rem; }

    .select-group label { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); }

    .select-group select { padding: 0.75rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.9rem; }

    .select-group select:focus { outline: none; border-color: var(--green-neon); }

    /* Table */
    .table-wrapper { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }

    .data-table { width: 100%; border-collapse: collapse; }

    .data-table th { text-align: left; padding: 1rem 1.25rem; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-subtle); background: var(--bg-panel); }

    .data-table td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); font-size: 0.9rem; }

    .data-table tr:last-child td { border-bottom: none; }

    .data-table tr:hover { background: var(--bg-panel); }

    .table-week { font-weight: 600; color: var(--green-neon); }

    .table-date { color: var(--text-secondary); }

    .table-tasks { color: var(--text-primary); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .table-skills { color: var(--text-secondary); max-width: 150px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

    .table-rating { color: var(--green-glow); }

    .table-hours { font-weight: 600; color: var(--text-primary); }

    .table-actions { display: flex; gap: 0.5rem; }

    .action-btn { padding: 0.4rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: var(--bg-panel); color: var(--text-secondary); }

    .action-btn:hover { border-color: var(--green-neon); color: var(--green-neon); }

    .empty-state { text-align: center; padding: 4rem 2rem; }

    .empty-icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }

    .empty-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.5rem; }

    .empty-text { color: var(--text-muted); margin-bottom: 1.5rem; }

    @media (max-width: 768px) {
      .dashboard-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .table-wrapper { overflow-x: auto; }
    }
  </style>
</head>
<body>
  <div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-icon">📓</div>
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
        <button class="nav-item active" onclick="window.location.href='progress.php'">
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
        <button class="logout-btn" onclick="window.location.href='index.php?logout=1'">
          <span class="icon">→</span> Logout
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <header class="page-header">
        <h1 class="page-title"><span>Progress Logs</span></h1>
        <div class="header-actions">
          <button class="add-btn" onclick="document.getElementById('add-modal').classList.add('open')">+ Add Log</button>
          <button class="icon-btn" onclick="window.location.href='profile.php'" title="Profile">👤</button>
        </div>
      </header>

      <!-- Stats -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Total Logs</div>
          <div class="stat-value" id="stat-total">0</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Weeks Completed</div>
          <div class="stat-value" id="stat-weeks">0</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Total Hours</div>
          <div class="stat-value" id="stat-hours">0</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Avg Rating</div>
          <div class="stat-value" id="stat-rating">-</div>
        </div>
      </div>

      <!-- Select Internship -->
      <div class="select-section">
        <div class="select-group">
          <label>Select Internship</label>
          <select id="internship-select" onchange="loadLogs()">
            <option value="">Choose an internship...</option>
          </select>
        </div>
      </div>

      <!-- Logs Table -->
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>Week</th>
              <th>Date</th>
              <th>Tasks Completed</th>
              <th>Skills Learned</th>
              <th>Hours</th>
              <th>Rating</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="log-list">
            <tr>
              <td colspan="7" class="empty-state">
                <div class="empty-icon">📓</div>
                <h3 class="empty-title">No progress logs</h3>
                <p class="empty-text">Select an internship and add your first progress log.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <!-- Add Modal -->
  <div class="modal-overlay" id="add-modal">
    <div class="modal">
      <div class="modal-header">
        <h2>Add Progress Log</h2>
        <button class="modal-close" onclick="document.getElementById('add-modal').classList.remove('open')">×</button>
      </div>
      <form id="add-form" method="POST">
        <div class="modal-body">
          <div class="form-row">
            <div class="form-group">
              <label>Log Date</label>
              <input type="date" name="log_date" required>
            </div>
            <div class="form-group">
              <label>Hours Worked</label>
              <input type="number" name="hours_worked" placeholder="0" step="0.5" min="0" required>
            </div>
          </div>
          <div class="form-group">
            <label>Tasks Completed</label>
            <textarea name="tasks_completed" rows="3" placeholder="Describe what you accomplished this week..." required></textarea>
          </div>
          <div class="form-group">
            <label>Skills Learned</label>
            <input type="text" name="skills_learned" placeholder="e.g., React, Python, Teamwork">
          </div>
          <div class="form-group">
            <label>Challenges</label>
            <textarea name="challenges" rows="2" placeholder="Any challenges faced..."></textarea>
          </div>
          <div class="form-group">
            <label>Rating</label>
            <select name="rating" required>
              <option value="5">5 - Excellent</option>
              <option value="4">4 - Good</option>
              <option value="3" selected>3 - Average</option>
              <option value="2">2 - Below Average</option>
              <option value="1">1 - Poor</option>
            </select>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" onclick="document.getElementById('add-modal').classList.remove('open')">Cancel</button>
          <button type="submit" class="add-btn">Save Log</button>
        </div>
      </form>
    </div>
  </div>

  <style>
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: none; align-items: center; justify-content: center; z-index: 1000; backdrop-filter: blur(4px); }
    .modal-overlay.open { display: flex; }
    .modal { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); width: 100%; max-width: 540px; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .modal-header h2 { font-size: 1.15rem; font-weight: 700; }
    .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; }
    .modal-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
    .modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1.25rem 1.5rem; border-top: 1px solid var(--border-subtle); }
    .form-group { display: flex; flex-direction: column; gap: 0.5rem; }
    .form-group label { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); }
    .form-group input, .form-group select, .form-group textarea { padding: 0.75rem 1rem; background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.9rem; }
    .form-group input:focus, .form-group select:focus, .form-group textarea:focus { outline: none; border-color: var(--green-neon); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .btn-secondary { padding: 0.75rem 1.5rem; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; border: 1px solid var(--border-subtle); background: var(--bg-panel); color: var(--text-secondary); }
    .btn-secondary:hover { border-color: var(--border-light); color: var(--text-primary); }
  </style>

  <script src="js/app.js"></script>
  <script>
    let allInternships = [];
    let allLogs = [];

    async function loadInternships() {
      try {
        const res = await fetch('php/internships.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ action: 'list', status: 'accepted' })
        });
        const data = await res.json();
        if (data.success) {
          allInternships = data.internships || [];
          const select = document.getElementById('internship-select');
          allInternships.forEach(int => {
            const opt = document.createElement('option');
            opt.value = int.id;
            opt.textContent = int.title + ' - ' + int.company_name;
            select.appendChild(opt);
          });
        }
      } catch (e) { console.error(e); }
    }

    async function loadLogs() {
      const internshipId = document.getElementById('internship-select').value;
      const list = document.getElementById('log-list');

      if (!internshipId) {
        list.innerHTML = `
          <tr>
            <td colspan="7" class="empty-state">
              <div class="empty-icon">📓</div>
              <h3 class="empty-title">No progress logs</h3>
              <p class="empty-text">Select an internship and add your first progress log.</p>
            </td>
          </tr>
        `;
        document.getElementById('stat-total').textContent = '0';
        document.getElementById('stat-weeks').textContent = '0';
        document.getElementById('stat-hours').textContent = '0';
        document.getElementById('stat-rating').textContent = '-';
        return;
      }

      try {
        const res = await fetch('php/internships.php?internship_id=' + internshipId, {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ action: 'log_list' })
        });
        const data = await res.json();
        if (data.success) {
          allLogs = data.logs || [];
          updateStats();
          renderLogs();
        }
      } catch (e) {
        toast('Failed to load logs', 'error');
      }
    }

    function updateStats() {
      document.getElementById('stat-total').textContent = allLogs.length;
      document.getElementById('stat-weeks').textContent = allLogs.length;

      const totalHours = allLogs.reduce((sum, log) => sum + (parseFloat(log.hours_worked) || 0), 0);
      document.getElementById('stat-hours').textContent = totalHours;

      const avgRating = allLogs.length > 0
        ? (allLogs.reduce((sum, log) => sum + (parseInt(log.rating) || 0), 0) / allLogs.length).toFixed(1)
        : '-';
      document.getElementById('stat-rating').textContent = avgRating;
    }

    function renderLogs() {
      const list = document.getElementById('log-list');

      if (allLogs.length === 0) {
        list.innerHTML = `
          <tr>
            <td colspan="7" class="empty-state">
              <div class="empty-icon">📓</div>
              <h3 class="empty-title">No progress logs</h3>
              <p class="empty-text">Start tracking your progress by adding a log.</p>
            </td>
          </tr>
        `;
        return;
      }

      list.innerHTML = allLogs.map(log => `
        <tr>
          <td class="table-week">Week ${log.week_number}</td>
          <td class="table-date">${log.log_date || '-'}</td>
          <td class="table-tasks" title="${log.tasks_completed}">${log.tasks_completed || '-'}</td>
          <td class="table-skills" title="${log.skills_learned}">${log.skills_learned || '-'}</td>
          <td class="table-hours">${log.hours_worked || 0}h</td>
          <td class="table-rating">${'★'.repeat(log.rating)}${'☆'.repeat(5 - log.rating)}</td>
          <td class="table-actions">
            <button class="action-btn" onclick="viewLog(${log.id})">View</button>
            <button class="action-btn danger" onclick="deleteLog(${log.id})">Delete</button>
          </td>
        </tr>
      `).join('');
    }

    function viewLog(id) { alert('View log: ' + id); }
    async function deleteLog(id) {
      if (!confirm('Delete this log?')) return;
      toast('Log deleted', 'success');
      loadLogs();
    }

    document.getElementById('add-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const internshipId = document.getElementById('internship-select').value;
      if (!internshipId) { toast('Please select an internship first', 'error'); return; }

      const formData = new FormData(e.target);
      formData.append('action', 'log_add');
      formData.append('internship_id', internshipId);
      const res = await fetch('php/internships.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      });
      const data = await res.json();
      if (data.success) {
        toast('Progress log added!', 'success');
        document.getElementById('add-modal').classList.remove('open');
        e.target.reset();
        loadLogs();
      } else {
        toast(data.message, 'error');
      }
    });

    loadInternships();
  </script>
</body>
</html>