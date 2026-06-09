<?php
session_start();
require_once 'php/config.php';
$user = requireAuth();
$csrf = generateCSRF();
$isAdmin = $user['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Dashboard</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<div id="toast-container" class="toast-container"></div>

<div class="app-layout">

  <!-- ── Sidebar ── -->
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon">🎓</div>
      <span>InternTrack</span>
    </div>

    <nav class="nav-section">
      <div class="nav-label">Main</div>
      <button class="nav-item active" data-page="dashboard" onclick="navTo('dashboard')">
        <span class="nav-icon">📊</span> Dashboard
      </button>
      <button class="nav-item" data-page="internships" onclick="navTo('internships')">
        <span class="nav-icon">💼</span> Internships
      </button>
      <button class="nav-item" data-page="progress" onclick="navTo('progress')">
        <span class="nav-icon">📓</span> Progress Logs
      </button>
      <button class="nav-item" data-page="companies" onclick="navTo('companies')">
        <span class="nav-icon">🏢</span> Companies
      </button>
      <?php if ($isAdmin): ?>
      <button class="nav-item" data-page="admin" onclick="navTo('admin')">
        <span class="nav-icon">🔧</span> Admin Panel
      </button>
      <?php endif; ?>
    </nav>

    <div class="sidebar-footer">
      <div class="user-chip">
        <div class="user-avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
        <div class="user-info">
          <div class="user-name"><?= e($user['full_name']) ?></div>
          <div class="user-role"><?= e($user['role']) ?></div>
        </div>
        <button onclick="logout()" title="Logout" style="background:none;border:none;cursor:pointer;color:var(--muted);font-size:1rem;padding:.3rem">⏏</button>
      </div>
    </div>
  </aside>

  <!-- ── Main ── -->
  <main class="main-content">
    <div class="topbar">
      <div id="page-title" class="page-title">Dashboard</div>
      <div class="topbar-actions">
        <span class="badge <?= $isAdmin ? 'badge-admin' : 'badge-student' ?>"><?= e($user['role']) ?></span>
        <button class="btn btn-primary btn-sm" onclick="openAddInternship()">+ Add Internship</button>
      </div>
    </div>

    <div class="content-area">

      <!-- ════ DASHBOARD ════ -->
      <div id="page-dashboard" class="page-section">
        <div id="stats-grid" class="stats-grid">
          <!-- loaded by JS -->
          <div class="stat-card"><div class="stat-icon" style="background:rgba(245,166,35,.15)">📋</div><div><div class="stat-num">…</div><div class="stat-label">Loading</div></div></div>
        </div>

        <div style="display:grid;grid-template-columns:1fr 320px;gap:1.5rem;align-items:start" class="dash-grid">
          <!-- Recent table -->
          <div class="table-wrap">
            <div class="table-header">
              <div class="table-title">Recent Applications</div>
              <button class="btn btn-secondary btn-sm" onclick="navTo('internships')">View All →</button>
            </div>
            <table>
              <thead><tr><th>Role</th><th>Company</th><th>Status</th><th>Start</th></tr></thead>
              <tbody id="recent-list">
                <tr><td colspan="4"><div class="empty-state"><div>Loading…</div></div></td></tr>
              </tbody>
            </table>
          </div>

          <!-- Status chart -->
          <div class="table-wrap" style="padding:1.4rem">
            <div class="table-title" style="margin-bottom:1rem">Status Breakdown</div>
            <canvas id="status-chart" width="200" height="200" style="display:block;margin:0 auto 1rem"></canvas>
            <div id="chart-legend" style="display:flex;flex-direction:column;gap:.4rem"></div>
          </div>
        </div>
      </div>

      <!-- ════ INTERNSHIPS ════ -->
      <div id="page-internships" class="page-section" style="display:none">
        <div class="filter-bar">
          <span style="color:var(--muted);font-size:.85rem;font-weight:600">Filter:</span>
          <?php foreach(['all','applied','interview','accepted','ongoing','completed','rejected'] as $s): ?>
          <button class="filter-btn <?= $s==='all'?'active':'' ?>" data-status="<?= $s ?>" onclick="filterByStatus('<?= $s ?>')"><?= ucfirst($s) ?></button>
          <?php endforeach; ?>
        </div>

        <div class="table-wrap">
          <div class="table-header">
            <div class="table-title">All Internships</div>
            <div style="display:flex;gap:.75rem;align-items:center">
              <div class="search-box">
                <span>🔍</span>
                <input type="text" id="intern-search" placeholder="Search…">
              </div>
              <button class="btn btn-primary btn-sm" onclick="openAddInternship()">+ Add</button>
            </div>
          </div>
          <div style="overflow-x:auto">
            <table>
              <thead>
                <tr>
                  <th>Role</th><th>Company</th><th>Status</th>
                  <th>Start</th><th>End</th><th>Stipend</th><th>Actions</th>
                </tr>
              </thead>
              <tbody id="intern-tbody">
                <tr><td colspan="7"><div class="empty-state"><div>Loading…</div></div></td></tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- ════ PROGRESS ════ -->
      <div id="page-progress" class="page-section" style="display:none">
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1.5rem">
          <!-- Log form -->
          <div class="table-wrap" style="padding:1.5rem">
            <h3 style="margin-bottom:1.2rem">Add Weekly Log</h3>
            <form id="log-form">
              <div class="form-group">
                <label class="form-label">Internship</label>
                <select id="progress-intern-sel" class="form-control" onchange="loadLogs()">
                  <option value="">Select…</option>
                </select>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label class="form-label">Date</label>
                  <input type="date" id="log-date" class="form-control">
                </div>
                <div class="form-group">
                  <label class="form-label">Hours Worked</label>
                  <input type="number" id="log-hours" class="form-control" min="0" max="80" step="0.5" placeholder="e.g. 40">
                </div>
              </div>
              <div class="form-group">
                <label class="form-label">Tasks Completed</label>
                <textarea id="log-tasks" class="form-control" placeholder="What did you accomplish this week?"></textarea>
              </div>
              <div class="form-group">
                <label class="form-label">Skills Learned</label>
                <textarea id="log-skills" class="form-control" placeholder="New skills, tools, or knowledge gained…"></textarea>
              </div>
              <div class="form-group">
                <label class="form-label">Challenges</label>
                <textarea id="log-challenges" class="form-control" placeholder="Any blockers or difficulties?"></textarea>
              </div>
              <div class="form-group">
                <label class="form-label">Overall Rating (1–5)</label>
                <select id="log-rating" class="form-control">
                  <option value="5">⭐⭐⭐⭐⭐ Excellent</option>
                  <option value="4">⭐⭐⭐⭐ Good</option>
                  <option value="3" selected>⭐⭐⭐ Average</option>
                  <option value="2">⭐⭐ Below Average</option>
                  <option value="1">⭐ Poor</option>
                </select>
              </div>
              <button type="button" class="btn btn-primary btn-full" onclick="saveProgressLog()">Save Log</button>
            </form>
          </div>

          <!-- Logs list -->
          <div class="table-wrap" style="padding:1.5rem">
            <h3 style="margin-bottom:1.2rem">Weekly Logs</h3>
            <div id="logs-list">
              <div class="empty-state"><div class="empty-icon">📓</div><div>Select an internship to view logs.</div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- ════ COMPANIES ════ -->
      <div id="page-companies" class="page-section" style="display:none">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
          <h2>Companies</h2>
          <button class="btn btn-primary" onclick="openModal('company-modal')">+ Add Company</button>
        </div>
        <div id="companies-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:1rem">
          <div class="empty-state"><div>Loading…</div></div>
        </div>
      </div>

      <!-- ════ ADMIN ════ -->
      <?php if ($isAdmin): ?>
      <div id="page-admin" class="page-section" style="display:none">
        <div class="table-wrap">
          <div class="table-header">
            <div class="table-title">All Student Internships</div>
          </div>
          <div style="overflow-x:auto">
            <table>
              <thead><tr><th>Student</th><th>Role</th><th>Company</th><th>Status</th><th>Start</th><th>Actions</th></tr></thead>
              <tbody id="admin-tbody"><tr><td colspan="6"><div class="empty-state">Loading…</div></td></tr></tbody>
            </table>
          </div>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /content-area -->
  </main>
</div><!-- /app-layout -->

<!-- ════ ADD/EDIT INTERNSHIP MODAL ════ -->
<div id="intern-modal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <div id="intern-modal-title" class="modal-title">Add Internship</div>
      <button class="modal-close" onclick="closeModal('intern-modal')">✕</button>
    </div>
    <div class="modal-body">
      <form id="intern-form">
        <input type="hidden" id="intern-id">
        <div class="form-group">
          <label class="form-label">Role / Position Title</label>
          <input type="text" id="intern-title" name="title" class="form-control" placeholder="e.g. Software Engineering Intern" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Company</label>
            <select id="intern-company" name="company_id" class="form-control" required>
              <option value="">Loading…</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Status</label>
            <select id="intern-status" name="status" class="form-control">
              <option value="applied">Applied</option>
              <option value="interview">Interview</option>
              <option value="accepted">Accepted</option>
              <option value="ongoing">Ongoing</option>
              <option value="completed">Completed</option>
              <option value="rejected">Rejected</option>
              <option value="withdrawn">Withdrawn</option>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Start Date</label>
            <input type="date" id="intern-start" name="start_date" class="form-control" required>
          </div>
          <div class="form-group">
            <label class="form-label">End Date</label>
            <input type="date" id="intern-end" name="end_date" class="form-control" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Work Mode</label>
            <select id="intern-workmode" name="work_mode" class="form-control">
              <option value="onsite">Onsite</option>
              <option value="remote">Remote</option>
              <option value="hybrid">Hybrid</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Monthly Stipend (NPR)</label>
            <input type="number" id="intern-stipend" name="stipend" class="form-control" min="0" step="100" placeholder="0">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Supervisor Name</label>
            <input type="text" id="intern-supervisor" name="supervisor_name" class="form-control" placeholder="Optional">
          </div>
          <div class="form-group">
            <label class="form-label">Supervisor Email</label>
            <input type="email" id="intern-supervisor-email" name="supervisor_email" class="form-control" placeholder="Optional">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Description</label>
          <textarea id="intern-desc" name="description" class="form-control" placeholder="Role responsibilities, skills used…"></textarea>
        </div>
        <div class="form-group">
          <label class="form-label">Notes</label>
          <textarea id="intern-notes" name="notes" class="form-control" placeholder="Personal notes, reminders…"></textarea>
        </div>
      </form>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('intern-modal')">Cancel</button>
      <button class="btn btn-primary" onclick="saveInternship()">Save Internship</button>
    </div>
  </div>
</div>

<!-- ════ ADD COMPANY MODAL ════ -->
<div id="company-modal" class="modal-overlay">
  <div class="modal">
    <div class="modal-header">
      <div class="modal-title">Add Company</div>
      <button class="modal-close" onclick="closeModal('company-modal')">✕</button>
    </div>
    <div class="modal-body">
      <div class="form-group">
        <label class="form-label">Company Name *</label>
        <input type="text" id="co-name" class="form-control" placeholder="e.g. TechNova Solutions" required>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Industry</label>
          <input type="text" id="co-industry" class="form-control" placeholder="e.g. IT, Finance">
        </div>
        <div class="form-group">
          <label class="form-label">Location</label>
          <input type="text" id="co-location" class="form-control" placeholder="City, Country">
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Website</label>
        <input type="url" id="co-website" class="form-control" placeholder="https://…">
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Contact Person</label>
          <input type="text" id="co-contact" class="form-control" placeholder="Name">
        </div>
        <div class="form-group">
          <label class="form-label">Contact Email</label>
          <input type="email" id="co-email" class="form-control" placeholder="email@company.com">
        </div>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-secondary" onclick="closeModal('company-modal')">Cancel</button>
      <button class="btn btn-primary" onclick="addCompany()">Add Company</button>
    </div>
  </div>
</div>

<style>
@media (max-width: 900px) {
  .dash-grid { grid-template-columns: 1fr !important; }
  #page-progress > div { grid-template-columns: 1fr !important; }
}
</style>

<script src="js/app.js"></script>
<script>
  // Auto-load dashboard on mount
  document.addEventListener('DOMContentLoaded', () => loadDashboard());
</script>
</body>
</html>
