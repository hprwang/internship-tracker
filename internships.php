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
  <title>InternTrack — My Internships</title>
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

    /* Stats Grid */
    .stats-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: 1rem; margin-bottom: 1.5rem; }

    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1rem 1.25rem; transition: all var(--transition); position: relative; overflow: hidden; }

    .stat-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--green-emerald), var(--green-neon)); opacity: 0; transition: opacity var(--transition); }

    .stat-card:hover::before { opacity: 1; }

    .stat-card:hover { border-color: var(--border-light); transform: translateY(-2px); }

    .stat-label { font-size: 0.75rem; color: var(--text-muted); font-weight: 600; margin-bottom: 0.5rem; }

    .stat-value { font-size: 1.75rem; font-weight: 800; color: var(--green-neon); }

    /* Filter Bar */
    .filter-section { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; gap: 1rem; flex-wrap: wrap; }

    .filter-tabs { display: flex; gap: 0.25rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.25rem; }

    .filter-tab { padding: 0.5rem 1rem; border-radius: var(--radius-sm); color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; }

    .filter-tab:hover { color: var(--text-primary); }

    .filter-tab.active { background: var(--green-neon); color: var(--bg-deep); }

    .search-field { display: flex; align-items: center; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.5rem 1rem; gap: 0.5rem; min-width: 220px; }

    .search-field input { background: none; border: none; outline: none; color: var(--text-primary); font-size: 0.9rem; width: 100%; }

    .search-field input::placeholder { color: var(--text-muted); }

    /* Table */
    .table-wrapper { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }

    .data-table { width: 100%; border-collapse: collapse; }

    .data-table th { text-align: left; padding: 1rem 1.25rem; font-size: 0.75rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; border-bottom: 1px solid var(--border-subtle); background: var(--bg-panel); }

    .data-table td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); font-size: 0.9rem; }

    .data-table tr:last-child td { border-bottom: none; }

    .data-table tr:hover { background: var(--bg-panel); }

    .table-role { font-weight: 600; color: var(--text-primary); }

    .table-company { color: var(--text-secondary); display: flex; align-items: center; gap: 0.5rem; }

    .table-status { display: inline-flex; padding: 0.3rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }

    .table-status.applied { background: rgba(59,130,246,0.15); color: #60A5FA; }

    .table-status.interview { background: rgba(168,85,247,0.15); color: #C084FC; }

    .table-status.accepted { background: rgba(34,197,94,0.15); color: var(--green-neon); }

    .table-status.rejected { background: rgba(239,68,68,0.15); color: #F87171; }

    .table-status.completed { background: rgba(34,197,94,0.15); color: var(--green-glow); }

    .table-dates { color: var(--text-secondary); font-size: 0.85rem; }

    .table-workmode { color: var(--text-secondary); font-size: 0.85rem; }

    .table-stipend { font-weight: 600; color: var(--green-neon); }

    .table-actions { display: flex; gap: 0.5rem; }

    .action-btn { padding: 0.4rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: var(--bg-panel); color: var(--text-secondary); }

    .action-btn:hover { border-color: var(--green-neon); color: var(--green-neon); }

    .action-btn.danger:hover { border-color: rgba(239,68,68,0.5); color: #F87171; }

    .empty-state { text-align: center; padding: 4rem 2rem; }

    .empty-icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }

    .empty-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.5rem; }

    .empty-text { color: var(--text-muted); margin-bottom: 1.5rem; }

    @media (max-width: 768px) {
      .dashboard-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .stats-grid { grid-template-columns: repeat(2, 1fr); }
      .filter-section { flex-direction: column; align-items: stretch; }
      .filter-tabs { overflow-x: auto; }
      .table-wrapper { overflow-x: auto; }
    }
  </style>
</head>
<body>
  <div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-icon">📋</div>
        <span class="logo-text">InternTrack</span>
      </div>

      <div class="nav-label">Main Navigation</div>
      <nav class="nav-menu">
        <button class="nav-item" onclick="window.location.href='dashboard.php'">
          <span class="icon">◉</span> Dashboard
        </button>
        <button class="nav-item active" onclick="window.location.href='internships.php'">
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
        <button class="logout-btn" onclick="handleLogout()">
          <span class="icon">⏻</span> Logout
        </button>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <header class="page-header">
        <h1 class="page-title">My <span>Internships</span></h1>
        <div class="header-actions">
          <button class="add-btn" onclick="openAddInternship()">+ Add Internship</button>
          <button class="icon-btn" onclick="window.location.href='profile.php'" title="Profile">👤</button>
        </div>
      </header>

      <!-- Statistics -->
      <div class="stats-grid" id="stats-grid">
        <div class="stat-card">
          <div class="stat-label">Total</div>
          <div class="stat-value" id="stat-total">0</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Applied</div>
          <div class="stat-value" id="stat-applied">0</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Interview</div>
          <div class="stat-value" id="stat-interview">0</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Accepted</div>
          <div class="stat-value" id="stat-accepted">0</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">Completed</div>
          <div class="stat-value" id="stat-completed">0</div>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="filter-section">
        <div class="filter-tabs">
          <button class="filter-tab active" onclick="filterInternships('all', this)">All</button>
          <button class="filter-tab" onclick="filterInternships('applied', this)">Applied</button>
          <button class="filter-tab" onclick="filterInternships('interview', this)">Interview</button>
          <button class="filter-tab" onclick="filterInternships('accepted', this)">Accepted</button>
          <button class="filter-tab" onclick="filterInternships('rejected', this)">Rejected</button>
          <button class="filter-tab" onclick="filterInternships('completed', this)">Completed</button>
        </div>
        <div class="search-field">
          <span>🔍</span>
          <input type="text" id="search-input" placeholder="Search internships..." onkeyup="searchInternships()">
        </div>
      </div>

      <!-- Internships Table -->
      <div class="table-wrapper">
        <table class="data-table">
          <thead>
            <tr>
              <th>Position</th>
              <th>Company</th>
              <th>Status</th>
              <th>Duration</th>
              <th>Work Mode</th>
              <th>Stipend</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody id="internship-list">
            <tr>
              <td colspan="7" class="empty-state">
                <div class="empty-icon">💼</div>
                <h3 class="empty-title">No internships found</h3>
                <p class="empty-text">Start tracking your internship applications by adding your first one.</p>
                <button class="add-btn" onclick="openAddInternship()">+ Add Internship</button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </main>
  </div>

  <!-- Add Modal -->
  <div class="modal-overlay" id="intern-modal">
    <div class="modal">
      <div class="modal-header">
        <h2 id="intern-modal-title">Add New Internship</h2>
        <button class="modal-close" onclick="document.getElementById('intern-modal').classList.remove('open')">×</button>
      </div>
      <form id="intern-form">
        <input type="hidden" id="intern-id" name="id" value="">
        <div class="modal-body">
          <div class="form-group">
            <label>Company</label>
            <select name="company_id" id="company-select" required>
              <option value="">Select company...</option>
            </select>
          </div>
          <div class="form-group">
            <label>Position Title</label>
            <input type="text" name="title" placeholder="e.g., Software Engineering Intern" required>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Start Date</label>
              <input type="date" name="start_date" required>
            </div>
            <div class="form-group">
              <label>End Date</label>
              <input type="date" name="end_date" required>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Status</label>
              <select name="status" required>
                <option value="applied">Applied</option>
                <option value="interview">Interview</option>
                <option value="accepted">Accepted</option>
                <option value="rejected">Rejected</option>
              </select>
            </div>
            <div class="form-group">
              <label>Work Mode</label>
              <select name="work_mode">
                <option value="on-site">On-site</option>
                <option value="remote">Remote</option>
                <option value="hybrid">Hybrid</option>
              </select>
            </div>
          </div>
          <div class="form-group">
            <label>Stipend (optional)</label>
            <input type="number" name="stipend" placeholder="0.00" step="0.01">
          </div>
          <div class="form-group">
            <label>Description</label>
            <textarea name="description" rows="3" placeholder="Brief description..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" onclick="document.getElementById('intern-modal').classList.remove('open')">Cancel</button>
          <button type="submit" class="add-btn">Save Internship</button>
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
    let currentFilter = 'all';
    let allInternships = [];

    async function loadCompanies() {
      try {
        const res = await fetch('php/internships.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ action: 'companies' })
        });
        const data = await res.json();
        if (data.success) {
          const select = document.getElementById('company-select');
          if (data.companies.length === 0) {
            select.innerHTML = '<option value="">No companies - add one first</option>';
            toast('No companies yet. Add one from Companies page.', 'error');
          } else {
            select.innerHTML = '<option value="">Select company...</option>';
            data.companies.forEach(c => {
              const opt = document.createElement('option');
              opt.value = c.id;
              opt.textContent = c.name;
              select.appendChild(opt);
            });
          }
        }
      } catch (e) { console.error(e); toast('Failed to load companies', 'error'); }
    }

    function filterInternships(status, btn) {
      currentFilter = status;
      document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
      btn.classList.add('active');
      renderInternships();
    }

    function searchInternships() {
      renderInternships();
    }

    function renderInternships() {
      const list = document.getElementById('internship-list');
      const search = document.getElementById('search-input').value.toLowerCase();

      let filtered = allInternships;
      if (currentFilter !== 'all') {
        filtered = filtered.filter(i => i.status === currentFilter);
      }
      if (search) {
        filtered = filtered.filter(i =>
          (i.title && i.title.toLowerCase().includes(search)) ||
          (i.company_name && i.company_name.toLowerCase().includes(search))
        );
      }

      if (filtered.length === 0) {
        list.innerHTML = `
          <tr>
            <td colspan="7" class="empty-state">
              <div class="empty-icon">💼</div>
              <h3 class="empty-title">No internships found</h3>
              <p class="empty-text">${search ? 'Try a different search term.' : 'Start tracking by adding your first internship.'}</p>
              ${!search ? '<button class="add-btn" onclick="openAddInternship()">+ Add Internship</button>' : ''}
            </td>
          </tr>
        `;
        return;
      }

      list.innerHTML = filtered.map(int => `
        <tr>
          <td class="table-role">${int.title}</td>
          <td class="table-company">🏢 ${int.company_name}</td>
          <td><span class="table-status ${int.status}">${int.status}</span></td>
          <td class="table-dates">${int.start_date || '-'} to ${int.end_date || '-'}</td>
          <td class="table-workmode">${int.work_mode || '-'}</td>
          <td class="table-stipend">${int.stipend ? 'Rs. ' + parseFloat(int.stipend).toLocaleString() : '-'}</td>
          <td class="table-actions">
            <button class="action-btn" onclick="viewInternship(${int.id})">View</button>
            <button class="action-btn danger" onclick="deleteInternship(${int.id})">Delete</button>
          </td>
        </tr>
      `).join('');
    }

    async function loadInternships() {
      try {
        const res = await fetch('php/internships.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ action: 'list' })
        });
        const data = await res.json();
        console.log('loadInternships:', data);
        if (data.success) {
          allInternships = data.internships || [];
          updateStats();
          renderInternships();
          if (allInternships.length > 0) {
            toast('Loaded ' + allInternships.length + ' internship(s)', 'success');
          } else {
            toast('No internships found. Add one!', 'error');
          }
        }
      } catch (e) {
        console.error(e);
        toast('Failed to load internships', 'error');
      }
    }

    function updateStats() {
      const stats = { total: allInternships.length, applied: 0, interview: 0, accepted: 0, completed: 0 };
      allInternships.forEach(i => {
        if (stats[i.status] !== undefined) stats[i.status]++;
      });
      document.getElementById('stat-total').textContent = stats.total;
      document.getElementById('stat-applied').textContent = stats.applied;
      document.getElementById('stat-interview').textContent = stats.interview;
      document.getElementById('stat-accepted').textContent = stats.accepted;
      document.getElementById('stat-completed').textContent = stats.completed;
    }

    function viewInternship(id) { window.location.href = 'internship-details.php?id=' + id; }

    async function deleteInternship(id) {
      if (!confirm('Delete this internship?')) return;
      const res = await api('php/internships.php', { action: 'delete', id });
      if (res.success) { toast('Internship deleted', 'success'); loadInternships(); }
      else toast(res.message, 'error');
    }

    document.getElementById('intern-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const form = e.target;
      const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
      const params = new URLSearchParams();
      params.append('action', 'create');
      params.append('csrf_token', csrfToken);
      params.append('company_id', form.company_id.value);
      params.append('title', form.title.value);
      params.append('start_date', form.start_date.value);
      params.append('end_date', form.end_date.value);
      params.append('status', form.status.value);
      params.append('work_mode', form.work_mode.value);
      params.append('description', form.description.value || '');
      params.append('stipend', form.stipend.value || '0');
      params.append('supervisor_name', form.supervisor_name.value || '');
      params.append('supervisor_email', form.supervisor_email.value || '');
      params.append('notes', form.notes.value || '');
      console.log('Saving internship with params:', params.toString());
      const res = await fetch('php/internships.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: params
      });
      const data = await res.json();
      console.log('Save response:', data);
      if (data.success) {
        toast('Internship added!', 'success');
        document.getElementById('intern-modal').classList.remove('open');
        form.reset();
        loadInternships();
      } else {
        toast(data.message || 'Failed to add internship', 'error');
      }
    });

    loadCompanies();
    loadInternships();
  </script>
</body>
</html>