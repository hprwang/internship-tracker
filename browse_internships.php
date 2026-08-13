<?php
session_start();
require_once 'php/config.php';
$user = requireAuth();
require_once __DIR__ . '/php/partials/header.php';
$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Browse Internships</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/responsive.css">
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
    .logo-text span { color: var(--green-neon); }
    .nav-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(124,58,237,.12); color: var(--primary); box-shadow: inset 0 0 0 1px rgba(124,58,237,.3), 0 0 20px rgba(124,58,237,.1); }
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
    .icon-btn:hover { border-color: var(--primary); box-shadow: 0 0 15px rgba(124,58,237,.15); }

    .tab-bar { display: flex; gap: 0.5rem; margin-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); padding-bottom: 0.75rem; }
    .tab-btn { background: transparent; border: 1px solid var(--border-subtle); color: var(--text-secondary); padding: 0.6rem 1.25rem; border-radius: var(--radius-md); font-weight: 600; font-size: 0.85rem; cursor: pointer; transition: all var(--transition); }
    .tab-btn.active { background: rgba(124,58,237,.12); border-color: var(--primary); color: var(--primary); box-shadow: 0 0 15px rgba(124,58,237,.15); }

    .search-field { display: flex; align-items: center; gap: 0.5rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.55rem 0.9rem; flex: 1; max-width: 340px; margin-bottom: 1.5rem; }
    .search-field input { background: none; border: none; outline: none; color: var(--text-primary); font-size: 0.9rem; width: 100%; }

    .job-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap: 1.25rem; }
    .job-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; display: flex; flex-direction: column; gap: 0.9rem; transition: all var(--transition); }
    .job-card:hover { border-color: var(--border-light); transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0,0,0,0.35); }
    .job-card-top { display: flex; justify-content: space-between; align-items: flex-start; gap: 1rem; }
    .job-title { font-size: 1.15rem; font-weight: 700; line-height: 1.3; }
    .job-company { display: flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); font-size: 0.9rem; margin-top: 0.3rem; }
    .job-company i { color: var(--green-neon); }
    .job-tags { display: flex; flex-wrap: wrap; gap: 0.5rem; }
    .job-tag { background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: 999px; padding: 0.3rem 0.75rem; font-size: 0.75rem; color: var(--text-secondary); }
    .job-tag i { margin-right: 0.35rem; color: var(--green-neon); }
    .job-desc { color: var(--text-secondary); font-size: 0.87rem; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
    .job-requirements { color: var(--text-muted); font-size: 0.82rem; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
    .job-requirements strong { color: var(--text-secondary); }
    .job-footer { margin-top: auto; display: flex; justify-content: space-between; align-items: center; }
    .apply-btn { background: linear-gradient(135deg, var(--primary-hover), var(--primary)); color: var(--bg-deep); font-weight: 700; padding: 0.6rem 1.4rem; border: none; border-radius: var(--radius-md); cursor: pointer; transition: all var(--transition); font-size: 0.9rem; }
    .apply-btn:hover { box-shadow: 0 0 25px rgba(124,58,237,.5); transform: translateY(-2px); }
    .apply-btn:disabled { background: var(--bg-elevated); color: var(--text-muted); cursor: not-allowed; box-shadow: none; transform: none; }
    .applied-badge { display: inline-flex; align-items: center; gap: 0.4rem; background: rgba(124,58,237,.12); color: var(--primary); border: 1px solid rgba(124,58,237,.3); padding: 0.5rem 1rem; border-radius: var(--radius-md); font-size: 0.82rem; font-weight: 600; }

    .empty-state { text-align: center; padding: 4rem 2rem; color: var(--text-muted); }
    .empty-icon { font-size: 3rem; margin-bottom: 1rem; opacity: 0.5; }
    .empty-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 0.5rem; color: var(--text-primary); }

    /* Table for my applications */
    .table-container { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow-x: auto; }
    .data-table { width: 100%; border-collapse: collapse; min-width: 640px; }
    .data-table th { text-align: left; padding: 0.9rem 1.25rem; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); border-bottom: 1px solid var(--border-subtle); }
    .data-table td { padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); font-size: 0.9rem; vertical-align: middle; }
    .data-table tr:last-child td { border-bottom: none; }
    .status-badge-cell { display: inline-flex; align-items: center; gap: 0.4rem; padding: 0.35rem 0.8rem; border-radius: 999px; font-size: 0.75rem; font-weight: 600; text-transform: capitalize; }
    .status-badge-cell.pending { background: rgba(245,158,11,0.12); color: #FBBF24; }
    .status-badge-cell.under_review { background: rgba(59,130,246,0.12); color: #60A5FA; }
    .status-badge-cell.accepted { background: rgba(34,197,94,0.12); color: var(--green-neon); }
    .status-badge-cell.rejected { background: rgba(239,68,68,0.12); color: #F87171; }
    .status-badge-cell .dot { width: 6px; height: 6px; border-radius: 50%; background: currentColor; }

    /* Modal */
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.7); backdrop-filter: blur(4px); display: none; align-items: center; justify-content: center; z-index: 100; padding: 1rem; }
    .modal-overlay.open { display: flex; }
    .modal { background: var(--bg-panel); border: 1px solid var(--border-light); border-radius: var(--radius-lg); width: 100%; max-width: 520px; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .modal-header h2 { font-size: 1.15rem; font-weight: 700; }
    .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.4rem; cursor: pointer; line-height: 1; }
    .modal-body { padding: 1.5rem; display: flex; flex-direction: column; gap: 1rem; }
    .form-group { display: flex; flex-direction: column; gap: 0.4rem; }
    .form-label { font-size: 0.8rem; font-weight: 600; color: var(--text-secondary); }
    .form-control { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.7rem 0.9rem; color: var(--text-primary); font-size: 0.9rem; font-family: inherit; }
    .form-control:focus { outline: none; border-color: var(--green-neon); box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }
    textarea.form-control { resize: vertical; min-height: 90px; }
    .form-readonly { background: var(--bg-panel); border-color: var(--border-subtle); color: var(--text-muted); cursor: default; }
    .modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1rem 1.5rem; border-top: 1px solid var(--border-subtle); }
    .btn-secondary { background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-secondary); padding: 0.65rem 1.25rem; border-radius: var(--radius-md); cursor: pointer; font-weight: 600; font-size: 0.85rem; }
    .btn-primary { background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); color: var(--bg-deep); font-weight: 700; padding: 0.65rem 1.5rem; border: none; border-radius: var(--radius-md); cursor: pointer; font-size: 0.9rem; }
    .btn-primary:hover { box-shadow: 0 0 25px rgba(34,197,94,0.5); }
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; }
    .file-hint { font-size: 0.72rem; color: var(--text-muted); }

    @media (max-width: 768px) {
      .dashboard-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .job-grid { grid-template-columns: 1fr; }
      .page-header { flex-direction: column; align-items: flex-start; gap: 0.75rem; }
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
        <button class="nav-item active" onclick="window.location.href='browse_internships.php'">
          <span class="icon"><i class="fas fa-search"></i></span> Browse Internships
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
        <h1 class="page-title">Browse <span>Internships</span></h1>
        <div class="header-actions">
          <?= renderNotifBell($user) ?>
          <button class="icon-btn" onclick="window.location.href='profile.php'" title="Profile"><i class="fas fa-user" style="color:#22C55E;"></i></button>
        </div>
      </header>

      <div class="tab-bar">
        <button class="tab-btn active" id="tab-open" onclick="showTab('open')"><i class="fas fa-search"></i> Open Internships</button>
        <button class="tab-btn" id="tab-apps" onclick="showTab('apps')"><i class="fas fa-file-signature"></i> My Applications</button>
      </div>

      <div id="tab-open-panel">
        <div class="search-field">
          <span><i class="fas fa-search" style="color:var(--text-muted);"></i></span>
          <input type="text" id="search-input" placeholder="Search by title, company, or location..." onkeyup="searchJobs()">
        </div>
        <div class="job-grid" id="job-grid">
          <div class="empty-state" style="grid-column:1/-1;">
            <div class="empty-icon"><i class="fas fa-spinner fa-spin"></i></div>
            <h3 class="empty-title">Loading internships...</h3>
          </div>
        </div>
      </div>

      <div id="tab-apps-panel" style="display:none;">
        <div class="table-container">
          <table class="data-table">
            <thead>
              <tr>
                <th>Internship</th>
                <th>Company</th>
                <th>Location</th>
                <th>Stipend</th>
                <th>Applied On</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody id="applications-list">
              <tr>
                <td colspan="6" class="empty-state">
                  <div class="empty-icon"><i class="fas fa-inbox"></i></div>
                  <h3 class="empty-title">No applications yet</h3>
                  <p class="empty-text">Browse open internships and apply to see your applications here.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <!-- Apply Modal -->
  <div class="modal-overlay" id="apply-modal">
    <div class="modal">
      <div class="modal-header">
        <h2>Apply to <span id="apply-title"></span></h2>
        <button class="modal-close" onclick="closeApplyModal()">×</button>
      </div>
      <form id="apply-form">
        <input type="hidden" id="apply-internship-id" name="internship_id" value="">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control form-readonly" id="apply-name" value="<?= e($user['full_name']) ?>" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="text" class="form-control form-readonly" id="apply-email" value="<?= e($user['email']) ?>" readonly>
          </div>
          <div class="form-group">
            <label class="form-label">Phone <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
            <input type="text" class="form-control" id="apply-phone" name="phone" placeholder="e.g. 98XXXXXXXX">
          </div>
          <div class="form-group">
            <label class="form-label">Cover Letter <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
            <textarea class="form-control" id="apply-cover" name="cover_letter" placeholder="Tell the company why you're a great fit..."></textarea>
          </div>
          <div class="form-group">
            <label class="form-label">Resume <span style="color:var(--text-muted);font-weight:400;">(optional)</span></label>
            <input type="file" class="form-control" id="apply-resume" name="resume" accept=".pdf,.doc,.docx">
            <span class="file-hint">PDF, DOC, or DOCX up to 5MB</span>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" onclick="closeApplyModal()">Cancel</button>
          <button type="submit" class="btn-primary" id="apply-submit"><i class="fas fa-paper-plane"></i> Submit Application</button>
        </div>
      </form>
    </div>
  </div>

  <script src="js/app.js"></script>
  <script src="js/interactive.js"></script>
  <script src="js/notifications.js"></script>
  <script>
    let allJobs = [];
    let allApplications = [];

    async function loadJobs() {
      try {
        const res = await fetch('php/internships.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ action: 'browse_list' })
        });
        const data = await res.json();
        if (data.success) {
          allJobs = data.internships || [];
          renderJobs();
          if (allJobs.length === 0) {
            document.getElementById('job-grid').innerHTML = `
              <div class="empty-state" style="grid-column:1/-1;">
                <div class="empty-icon"><i class="fas fa-search"></i></div>
                <h3 class="empty-title">No open internships right now</h3>
                <p class="empty-text">Check back later — companies post new opportunities regularly.</p>
              </div>`;
          }
        } else {
          toast(data.message || 'Failed to load internships', 'error');
        }
      } catch (e) {
        console.error(e);
        document.getElementById('job-grid').innerHTML = `
          <div class="empty-state" style="grid-column:1/-1;">
            <div class="empty-icon"><i class="fas fa-exclamation-triangle"></i></div>
            <h3 class="empty-title">Could not load internships</h3>
            <p class="empty-text">Please try again later.</p>
          </div>`;
      }
    }

    function renderJobs() {
      const query = (document.getElementById('search-input').value || '').toLowerCase();
      const grid = document.getElementById('job-grid');
      const filtered = allJobs.filter(j =>
        !query ||
        (j.title || '').toLowerCase().includes(query) ||
        (j.company_name || '').toLowerCase().includes(query) ||
        (j.location || '').toLowerCase().includes(query)
      );

      if (filtered.length === 0) {
        grid.innerHTML = `
          <div class="empty-state" style="grid-column:1/-1;">
            <div class="empty-icon"><i class="fas fa-search"></i></div>
            <h3 class="empty-title">No internships found</h3>
            <p class="empty-text">Try a different search term.</p>
          </div>`;
        return;
      }

      grid.innerHTML = filtered.map(job => {
        const stipend = parseFloat(job.stipend);
        const stipendDisplay = stipend > 0 ? 'Rs. ' + stipend.toLocaleString() : 'Unpaid';
        const req = (job.requirements || '').substring(0, 120) + ((job.requirements || '').length > 120 ? '…' : '');
        return `
        <div class="job-card">
          <div class="job-card-top">
            <div>
              <div class="job-title">${escapeHtml(job.title)}</div>
              <div class="job-company"><i class="fas fa-building"></i> ${escapeHtml(job.company_name)}</div>
            </div>
          </div>
          <div class="job-tags">
            ${job.location ? `<span class="job-tag"><i class="fas fa-map-marker-alt"></i>${escapeHtml(job.location)}</span>` : ''}
            ${job.duration ? `<span class="job-tag"><i class="fas fa-clock"></i>${escapeHtml(job.duration)}</span>` : ''}
            <span class="job-tag"><i class="fas fa-money-bill-wave"></i>${stipendDisplay}</span>
          </div>
          ${job.description ? `<p class="job-desc">${escapeHtml(job.description)}</p>` : ''}
          ${req ? `<p class="job-requirements"><strong>Requirements:</strong> ${escapeHtml(req)}</p>` : ''}
          <div class="job-footer">
            ${job.applied
              ? '<span class="applied-badge"><i class="fas fa-check-circle"></i> Applied</span>'
              : `<button class="apply-btn" onclick="openApply(${job.id}, '${escapeAttr(job.title)}')"><i class="fas fa-paper-plane"></i> Apply Now</button>`}
          </div>
        </div>`;
      }).join('');
    }

    function searchJobs() { renderJobs(); }

    function escapeHtml(str) {
      return String(str ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    }
    function escapeAttr(str) {
      return String(str ?? '').replace(/'/g, "\\'").replace(/"/g, '&quot;');
    }

    function openApply(id, title) {
      document.getElementById('apply-internship-id').value = id;
      document.getElementById('apply-title').textContent = title;
      document.getElementById('apply-phone').value = '';
      document.getElementById('apply-cover').value = '';
      document.getElementById('apply-resume').value = '';
      document.getElementById('apply-modal').classList.add('open');
      document.body.style.overflow = 'hidden';
    }

    function closeApplyModal() {
      document.getElementById('apply-modal').classList.remove('open');
      document.body.style.overflow = '';
    }

    document.getElementById('apply-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = document.getElementById('apply-submit');
      btn.disabled = true;
      btn.textContent = 'Submitting…';
      const fd = new FormData(e.target);
      fd.append('action', 'browse_apply');
      fd.append('csrf_token', App.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '');
      try {
        const res = await fetch('php/internships.php', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.success) {
          toast(data.message, 'success');
          closeApplyModal();
          loadJobs();
        } else {
          toast(data.message || 'Application failed', 'error');
        }
      } catch (err) {
        console.error(err);
        toast('Error submitting application', 'error');
      } finally {
        btn.disabled = false;
        btn.textContent = 'Submit Application';
      }
    });

    async function loadApplications() {
      try {
        const res = await fetch('php/internships.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ action: 'my_applications' })
        });
        const data = await res.json();
        if (data.success) {
          allApplications = data.applications || [];
          renderApplications();
        } else {
          toast(data.message || 'Failed to load applications', 'error');
        }
      } catch (e) {
        console.error(e);
        toast('Failed to load applications', 'error');
      }
    }

    function renderApplications() {
      const tbody = document.getElementById('applications-list');
      if (allApplications.length === 0) {
        tbody.innerHTML = `
          <tr>
            <td colspan="6" class="empty-state">
              <div class="empty-icon"><i class="fas fa-inbox"></i></div>
              <h3 class="empty-title">No applications yet</h3>
              <p class="empty-text">Browse open internships and apply to see your applications here.</p>
            </td>
          </tr>`;
        return;
      }
      tbody.innerHTML = allApplications.map(app => {
        const stipend = parseFloat(app.stipend);
        const stipendDisplay = stipend > 0 ? 'Rs. ' + stipend.toLocaleString() : 'Unpaid';
        const status = app.status || 'pending';
        const date = app.applied_at ? new Date(app.applied_at).toLocaleDateString(undefined, { year:'numeric', month:'short', day:'numeric' }) : '—';
        return `
        <tr>
          <td><strong>${escapeHtml(app.internship_title)}</strong></td>
          <td>${escapeHtml(app.company_name)}</td>
          <td>${escapeHtml(app.internship_location || '—')}</td>
          <td>${stipendDisplay}</td>
          <td>${date}</td>
          <td><span class="status-badge-cell ${status}"><span class="dot"></span>${status.replace('_',' ')}</span></td>
        </tr>`;
      }).join('');
    }

    function showTab(tab) {
      document.getElementById('tab-open').classList.toggle('active', tab === 'open');
      document.getElementById('tab-apps').classList.toggle('active', tab === 'apps');
      document.getElementById('tab-open-panel').style.display = tab === 'open' ? 'block' : 'none';
      document.getElementById('tab-apps-panel').style.display = tab === 'apps' ? 'block' : 'none';
      if (tab === 'apps') loadApplications();
    }

    loadJobs();
  </script>
</body>
</html>