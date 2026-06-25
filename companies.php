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
  <title>InternTrack — Companies</title>
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

    /* Stats Header */
    .stats-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 1rem; margin-bottom: 1.5rem; }

    .stat-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 1rem 1.25rem; display: flex; align-items: center; gap: 1rem; transition: all var(--transition); }

    .stat-card:hover { border-color: var(--border-light); transform: translateY(-2px); }

    .stat-icon { width: 44px; height: 44px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.25rem; }

    .stat-icon.green { background: rgba(34,197,94,0.12); }

    .stat-icon.blue { background: rgba(59,130,246,0.12); }

    .stat-icon.purple { background: rgba(168,85,247,0.12); }

    .stat-icon.orange { background: rgba(251,146,60,0.12); }

    .stat-info { display: flex; flex-direction: column; gap: 0.25rem; }

    .stat-value { font-size: 1.5rem; font-weight: 800; }

    .stat-label { font-size: 0.75rem; color: var(--text-muted); }

    /* View Toggle */
    .view-toggle { display: flex; gap: 0.25rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.25rem; }

    .view-btn { padding: 0.4rem 0.75rem; border-radius: var(--radius-sm); color: var(--text-secondary); font-size: 0.85rem; cursor: pointer; transition: all var(--transition); border: none; background: transparent; }

    .view-btn:hover { color: var(--text-primary); }

    .view-btn.active { background: var(--green-neon); color: var(--bg-deep); }

    /* Company Cards */
    .companies-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 1rem; }

    .company-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.25rem; transition: all var(--transition); cursor: pointer; position: relative; overflow: hidden; }

    .company-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 4px; background: var(--green-neon); opacity: 0; transition: opacity var(--transition); }

    .company-card:hover { border-color: var(--green-neon); transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.3); }

    .company-card:hover::before { opacity: 1; }

    .company-card-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }

    .company-card-icon { width: 48px; height: 48px; background: linear-gradient(135deg, var(--bg-panel), var(--bg-elevated)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; border: 1px solid var(--border-subtle); }

    .company-card-actions { display: flex; gap: 0.5rem; }

    .company-card-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.25rem; }

    .company-card-industry { display: inline-flex; align-items: center; gap: 0.35rem; padding: 0.25rem 0.6rem; border-radius: 20px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }

    .company-card-industry.tech { background: rgba(59,130,246,0.15); color: #60A5FA; }
    .company-card-industry.finance { background: rgba(16,185,129,0.15); color: #34D399; }
    .company-card-industry.healthcare { background: rgba(239,68,68,0.15); color: #F87171; }
    .company-card-industry.retail { background: rgba(251,191,36,0.15); color: #FCD34D; }
    .company-card-industry.marketing { background: rgba(168,85,247,0.15); color: #A78BFA; }
    .company-card-industry.consulting { background: rgba(6,182,212,0.15); color: #22D3EE; }
    .company-card-industry.other { background: rgba(107,114,128,0.15); color: #9CA3AF; }

    .company-card-details { display: flex; flex-direction: column; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }

    .company-card-detail { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-secondary); }

    .company-card-detail .icon { width: 18px; text-align: center; color: var(--text-muted); }

    .company-card-footer { display: flex; gap: 0.5rem; margin-top: 1rem; }

    .quick-action-btn { flex: 1; padding: 0.5rem; border-radius: var(--radius-sm); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: var(--bg-panel); color: var(--text-secondary); display: flex; align-items: center; justify-content: center; gap: 0.35rem; text-decoration: none; }

    .quick-action-btn:hover { border-color: var(--green-neon); color: var(--green-neon); }

    /* Filter Section */
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

    .table-name { font-weight: 600; color: var(--text-primary); }

    .table-industry { color: var(--text-secondary); }

    .table-location { color: var(--text-secondary); }

    .table-website { color: var(--green-neon); text-decoration: none; }

    .table-website:hover { text-decoration: underline; }

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
      .filter-section { flex-direction: column; align-items: stretch; }
      .filter-tabs { overflow-x: auto; }
      .table-wrapper { overflow-x: auto; }
    }
  </style>
</head>
<body>
  <canvas id="starfield" aria-hidden="true"></canvas>
  <div class="dashboard-layout">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-icon">🏢</div>
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
        <button class="nav-item active" onclick="window.location.href='companies.php'">
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
        <h1 class="page-title"><span>Companies</span></h1>
        <div class="header-actions">
          <?php if ($user['role'] === 'admin'): ?>
          <button class="add-btn" onclick="document.getElementById('add-modal').classList.add('open')">+ Add Company</button>
          <?php endif; ?>
          <button class="icon-btn" onclick="window.location.href='profile.php'" title="Profile">👤</button>
        </div>
      </header>

      <!-- Filter Section -->
      <div class="filter-section">
        <div class="filter-tabs">
          <button class="filter-tab active" onclick="filterCompanies('all', this)">All</button>
          <button class="filter-tab" onclick="filterCompanies('tech', this)">Tech</button>
          <button class="filter-tab" onclick="filterCompanies('finance', this)">Finance</button>
          <button class="filter-tab" onclick="filterCompanies('healthcare', this)">Healthcare</button>
          <button class="filter-tab" onclick="filterCompanies('retail', this)">Retail</button>
          <button class="filter-tab" onclick="filterCompanies('marketing', this)">Marketing</button>
          <button class="filter-tab" onclick="filterCompanies('consulting', this)">Consulting</button>
          <button class="filter-tab" onclick="filterCompanies('other', this)">Other</button>
        </div>
        <div style="display: flex; align-items: center; gap: 1rem;">
          <div class="view-toggle">
            <button class="view-btn active" onclick="setView('table', this)" title="Table View">☰</button>
            <button class="view-btn" onclick="setView('grid', this)" title="Card View">▦</button>
          </div>
          <div class="search-field">
            <span>🔍</span>
            <input type="text" id="search-input" placeholder="Search companies..." onkeyup="searchCompanies()">
          </div>
        </div>
      </div>

      <!-- Stats Header -->
      <div class="stats-row" id="stats-row"></div>

      <!-- Companies Grid/Table -->
      <div id="companies-container">
        <div class="table-wrapper" id="table-view">
          <table class="data-table">
            <thead>
              <tr>
                <th>Company Name</th>
                <th>Industry</th>
                <th>Location</th>
                <th>Website</th>
                <th>Contact</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="company-list">
              <tr>
                <td colspan="6" class="empty-state">
                  <div class="empty-icon">🏢</div>
                  <h3 class="empty-title">No companies found</h3>
                  <p class="empty-text">Start by adding companies you've applied to.</p>
                  <button class="add-btn" onclick="document.getElementById('add-modal').classList.add('open')">+ Add Company</button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
        <div class="companies-grid" id="grid-view" style="display: none;"></div>
      </div>
    </main>
  </div>

  <!-- View Company Modal -->
  <div class="modal-overlay" id="view-modal">
    <div class="modal">
      <div class="modal-header">
        <h2>🏢 Company Details</h2>
        <button class="modal-close" onclick="document.getElementById('view-modal').classList.remove('open')">×</button>
      </div>
      <div class="modal-body">
        <div class="detail-row">
          <div class="detail-label">Company Name</div>
          <div class="detail-value" id="view-name">-</div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Industry</div>
          <div class="detail-value" id="view-industry">-</div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Location</div>
          <div class="detail-value" id="view-location">-</div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Website</div>
          <div class="detail-value"><a href="#" id="view-website" target="_blank" class="detail-link">-</a></div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Contact Person</div>
          <div class="detail-value" id="view-contact_person">-</div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Contact Email</div>
          <div class="detail-value"><a href="#" id="view-contact_email" class="detail-link">-</a></div>
        </div>
        <div class="detail-row">
          <div class="detail-label">Contact Phone</div>
          <div class="detail-value" id="view-contact_phone">-</div>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn-secondary" onclick="document.getElementById('view-modal').classList.remove('open')">Close</button>
      </div>
    </div>
  </div>

  <!-- Add Modal -->
  <div class="modal-overlay" id="add-modal">
    <div class="modal">
      <div class="modal-header">
        <h2>Add New Company</h2>
        <button class="modal-close" onclick="document.getElementById('add-modal').classList.remove('open')">×</button>
      </div>
      <form id="add-form" method="POST">
        <div class="modal-body">
          <div class="form-group">
            <label>Company Name</label>
            <input type="text" name="name" placeholder="e.g., Google" required>
          </div>
          <div class="form-group">
            <label>Industry</label>
            <select name="industry" required>
              <option value="">Select industry...</option>
              <option value="tech">Technology</option>
              <option value="finance">Finance</option>
              <option value="healthcare">Healthcare</option>
              <option value="retail">Retail</option>
              <option value="marketing">Marketing</option>
              <option value="consulting">Consulting</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Location</label>
            <input type="text" name="location" placeholder="e.g., New York, NY">
          </div>
          <div class="form-group">
            <label>Website</label>
            <input type="url" name="website" placeholder="https://example.com">
          </div>
          <div class="form-group">
            <label>Contact Person</label>
            <input type="text" name="contact_person" placeholder="John Doe">
          </div>
          <div class="form-group">
            <label>Contact Email</label>
            <input type="email" name="contact_email" placeholder="john@example.com">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" onclick="document.getElementById('add-modal').classList.remove('open')">Cancel</button>
          <button type="submit" class="add-btn">Save Company</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Edit Modal -->
  <div class="modal-overlay" id="edit-modal">
    <div class="modal">
      <div class="modal-header">
        <h2>Edit Company</h2>
        <button class="modal-close" onclick="document.getElementById('edit-modal').classList.remove('open')">×</button>
      </div>
      <form id="edit-form" method="POST">
        <div class="modal-body">
          <input type="hidden" name="id" id="edit-id">
          <div class="form-group">
            <label>Company Name</label>
            <input type="text" name="name" id="edit-name" placeholder="e.g., Google" required>
          </div>
          <div class="form-group">
            <label>Industry</label>
            <select name="industry" id="edit-industry" required>
              <option value="">Select industry...</option>
              <option value="tech">Technology</option>
              <option value="finance">Finance</option>
              <option value="healthcare">Healthcare</option>
              <option value="retail">Retail</option>
              <option value="marketing">Marketing</option>
              <option value="consulting">Consulting</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="form-group">
            <label>Location</label>
            <input type="text" name="location" id="edit-location" placeholder="e.g., New York, NY">
          </div>
          <div class="form-group">
            <label>Website</label>
            <input type="url" name="website" id="edit-website" placeholder="https://example.com">
          </div>
          <div class="form-group">
            <label>Contact Person</label>
            <input type="text" name="contact_person" id="edit-contact_person" placeholder="John Doe">
          </div>
          <div class="form-group">
            <label>Contact Email</label>
            <input type="email" name="contact_email" id="edit-contact_email" placeholder="john@example.com">
          </div>
          <div class="form-group">
            <label>Contact Phone</label>
            <input type="tel" name="contact_phone" id="edit-contact_phone" placeholder="+1 234 567 8900">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn-secondary" onclick="document.getElementById('edit-modal').classList.remove('open')">Cancel</button>
          <button type="submit" class="add-btn">Update Company</button>
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
    .form-group input, .form-group select { padding: 0.75rem 1rem; background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.9rem; }
    .form-group input:focus, .form-group select:focus { outline: none; border-color: var(--green-neon); }
    .btn-secondary { padding: 0.75rem 1.5rem; border-radius: var(--radius-md); font-weight: 600; cursor: pointer; border: 1px solid var(--border-subtle); background: var(--bg-panel); color: var(--text-secondary); }
    .btn-secondary:hover { border-color: var(--border-light); color: var(--text-primary); }
    /* Detail view styles */
    .detail-row { display: flex; flex-direction: column; gap: 0.25rem; padding: 0.75rem 0; border-bottom: 1px solid var(--border-subtle); }
    .detail-row:last-child { border-bottom: none; }
    .detail-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }
    .detail-value { font-size: 1rem; color: var(--text-primary); }
    .detail-link { color: var(--green-neon); text-decoration: none; }
    .detail-link:hover { text-decoration: underline; }
  </style>

  <script src="js/app.js"></script>
  <script>
    let currentFilter = 'all';
    let allCompanies = [];
    let currentView = 'table';
    const isAdmin = <?= $user['role'] === 'admin' ? 'true' : 'false' ?>;

    function filterCompanies(industry, btn) {
      currentFilter = industry;
      document.querySelectorAll('.filter-tab').forEach(tab => tab.classList.remove('active'));
      btn.classList.add('active');
      renderCompanies();
      renderStats();
    }

    function normalizeIndustry(ind) {
      if (!ind) return 'other';
      const i = ind.toLowerCase().trim();
      // Tech - handles "Information Technology", "Technology", "tech", etc.
      if (i.includes('tech') || i.includes('information') || i.includes('software') || i === 'technology') return 'tech';
      // Finance - handles "Finance & Banking", "Financial", "finance", etc.
      if (i.includes('finance') || i.includes('banking') || i.includes('financial')) return 'finance';
      // Healthcare - handles "Healthcare", "health", "medical", etc.
      if (i.includes('health') || i.includes('medical') || i.includes('pharma')) return 'healthcare';
      // Retail
      if (i.includes('retail') || i.includes('e-commerce') || i.includes('ecommerce')) return 'retail';
      // Marketing
      if (i.includes('marketing') || i.includes('advertising') || i.includes('media')) return 'marketing';
      // Consulting
      if (i.includes('consulting') || i.includes('advisory')) return 'consulting';
      return 'other';
    }

    function searchCompanies() {
      renderCompanies();
      renderStats();
    }

    function setView(view, btn) {
      currentView = view;
      document.querySelectorAll('.view-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      renderCompanies();
    }

    function renderStats() {
      const statsRow = document.getElementById('stats-row');
      const search = document.getElementById('search-input').value.toLowerCase();

      let filtered = allCompanies;
      if (currentFilter !== 'all') {
        filtered = filtered.filter(c => normalizeIndustry(c.industry) === currentFilter);
      }
      if (search) {
        filtered = filtered.filter(c =>
          (c.name && c.name.toLowerCase().includes(search)) ||
          (c.industry && normalizeIndustry(c.industry).includes(search.replace('technology', 'tech').replace('financial', 'finance'))) ||
          (c.location && c.location.toLowerCase().includes(search))
        );
      }

      const total = filtered.length;
      const tech = filtered.filter(c => normalizeIndustry(c.industry) === 'tech').length;
      const finance = filtered.filter(c => normalizeIndustry(c.industry) === 'finance').length;
      const healthcare = filtered.filter(c => normalizeIndustry(c.industry) === 'healthcare').length;
      const retail = filtered.filter(c => normalizeIndustry(c.industry) === 'retail').length;
      const marketing = filtered.filter(c => normalizeIndustry(c.industry) === 'marketing').length;
      const consulting = filtered.filter(c => normalizeIndustry(c.industry) === 'consulting').length;
      const other = filtered.filter(c => normalizeIndustry(c.industry) === 'other').length;

      // Show stats for currently filtered category or all stats when "all" is selected
      const categories = currentFilter === 'all' ? [
        { name: 'Total', value: total, icon: '🏢', color: 'green' },
        { name: 'Technology', value: tech, icon: '💻', color: 'blue' },
        { name: 'Finance', value: finance, icon: '💰', color: 'purple' },
        { name: 'Healthcare', value: healthcare, icon: '🏥', color: 'orange' },
        { name: 'Retail', value: retail, icon: '🏪', color: 'purple' },
        { name: 'Marketing', value: marketing, icon: '📣', color: 'green' },
        { name: 'Consulting', value: consulting, icon: '💼', color: 'blue' },
        { name: 'Other', value: other, icon: '📦', color: 'orange' }
      ] : [
        { name: currentFilter.charAt(0).toUpperCase() + currentFilter.slice(1), value: total, icon: currentFilter === 'tech' ? '💻' : currentFilter === 'finance' ? '💰' : currentFilter === 'healthcare' ? '🏥' : currentFilter === 'retail' ? '🏪' : currentFilter === 'marketing' ? '📣' : currentFilter === 'consulting' ? '💼' : '📦', color: 'green' }
      ];

      statsRow.innerHTML = categories.map(cat => `
        <div class="stat-card">
          <div class="stat-icon ${cat.color}">${cat.icon}</div>
          <div class="stat-info">
            <div class="stat-value">${cat.value}</div>
            <div class="stat-label">${cat.name}</div>
          </div>
        </div>
      `).join('');
    }

    function renderCompanies() {
      const tableList = document.getElementById('company-list');
      const gridView = document.getElementById('grid-view');
      const search = document.getElementById('search-input').value.toLowerCase();

      let filtered = allCompanies;
      if (currentFilter !== 'all') {
        filtered = filtered.filter(c => normalizeIndustry(c.industry) === currentFilter);
      }
      if (search) {
        filtered = filtered.filter(c =>
          (c.name && c.name.toLowerCase().includes(search)) ||
          (c.industry && normalizeIndustry(c.industry).includes(search)) ||
          (c.location && c.location.toLowerCase().includes(search))
        );
      }

      // Table view
      if (filtered.length === 0) {
        tableList.innerHTML = `
          <tr>
            <td colspan="6" class="empty-state">
              <div class="empty-icon">🏢</div>
              <h3 class="empty-title">No companies found</h3>
              <p class="empty-text">${search ? 'Try a different search term.' : 'Start by adding companies you\'ve applied to.'}</p>
              ${!search ? '<button class="add-btn" onclick="document.getElementById(\'add-modal\').classList.add(\'open\')">+ Add Company</button>' : ''}
            </td>
          </tr>
        `;
        gridView.innerHTML = '';
      } else {
        const adminActions = isAdmin ? `
              <button class="action-btn" onclick="editCompany(${c.id})">Edit</button>
              <button class="action-btn danger" onclick="deleteCompany(${c.id})">Delete</button>
            ` : '';

        tableList.innerHTML = filtered.map(c => `
          <tr>
            <td class="table-name">🏢 ${c.name}</td>
            <td><span class="company-card-industry ${c.industry || 'other'}">${c.industry || '-'}</span></td>
            <td class="table-location">${c.location || '-'}</td>
            <td>${c.website ? `<a href="${c.website}" target="_blank" class="table-website">${c.website.replace(/^https?:\/\//, '')}</a>` : '-'}</td>
            <td class="table-location">${c.contact_email || '-'}</td>
            <td class="table-actions">
              <button class="action-btn" onclick="viewCompany(${c.id})">View</button>
              ${isAdmin ? `<button class="action-btn" onclick="editCompany(${c.id})">Edit</button><button class="action-btn danger" onclick="deleteCompany(${c.id})">Delete</button>` : ''}
            </td>
          </tr>
        `).join('');

        // Card/Grid view
        gridView.innerHTML = filtered.map(c => `
          <div class="company-card" onclick="viewCompany(${c.id})">
            <div class="company-card-header">
              <div class="company-card-icon">🏢</div>
              <div class="company-card-actions">
                ${isAdmin ? `<button class="action-btn" onclick="event.stopPropagation(); editCompany(${c.id})">Edit</button><button class="action-btn danger" onclick="event.stopPropagation(); deleteCompany(${c.id})">Delete</button>` : ''}
              </div>
            </div>
            <div class="company-card-title">${c.name}</div>
            <span class="company-card-industry ${c.industry || 'other'}">${c.industry || 'Other'}</span>
            <div class="company-card-details">
              <div class="company-card-detail">
                <span class="icon">📍</span>
                <span>${c.location || 'No location'}</span>
              </div>
              <div class="company-card-detail">
                <span class="icon">🌐</span>
                <span>${c.website ? c.website.replace(/^https?:\/\//, '') : 'No website'}</span>
              </div>
              <div class="company-card-detail">
                <span class="icon">👤</span>
                <span>${c.contact_person || 'No contact'}</span>
              </div>
            </div>
            <div class="company-card-footer">
              ${c.contact_email ? `<a href="mailto:${c.contact_email}" class="quick-action-btn" onclick="event.stopPropagation()">✉ Email</a>` : ''}
              ${c.contact_phone ? `<a href="tel:${c.contact_phone}" class="quick-action-btn" onclick="event.stopPropagation()">📞 Call</a>` : ''}
              ${c.website ? `<a href="${c.website}" target="_blank" class="quick-action-btn" onclick="event.stopPropagation()">🌐 Visit</a>` : ''}
            </div>
          </div>
        `).join('');
      }

      // Toggle views
      document.getElementById('table-view').style.display = currentView === 'table' ? 'block' : 'none';
      gridView.style.display = currentView === 'grid' ? 'grid' : 'none';
      renderStats();
    }

    async function loadCompanies() {
      try {
        const res = await fetch('php/internships.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ action: 'companies' })
        });
        const data = await res.json();
        if (data.success) {
          allCompanies = data.companies || [];
          renderCompanies();
          renderStats();
          if (allCompanies.length > 0) {
            toast('Loaded ' + allCompanies.length + ' company(s)', 'success');
          }
        }
      } catch (e) {
        console.error(e);
        toast('Failed to load companies', 'error');
      }
    }

    async function viewCompany(id) {
      try {
        const res = await fetch('php/internships.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ action: 'get_company', id: id })
        });
        const data = await res.json();
        if (data.success && data.company) {
          const c = data.company;
          document.getElementById('view-name').textContent = c.name || '-';
          document.getElementById('view-industry').textContent = c.industry || '-';
          document.getElementById('view-location').textContent = c.location || '-';

          const webEl = document.getElementById('view-website');
          if (c.website) {
            webEl.textContent = c.website.replace(/^https?:\/\//, '');
            webEl.href = c.website;
          } else {
            webEl.textContent = '-';
            webEl.href = '#';
          }

          document.getElementById('view-contact_person').textContent = c.contact_person || '-';

          const emailEl = document.getElementById('view-contact_email');
          if (c.contact_email) {
            emailEl.textContent = c.contact_email;
            emailEl.href = 'mailto:' + c.contact_email;
          } else {
            emailEl.textContent = '-';
            emailEl.href = '#';
          }

          document.getElementById('view-contact_phone').textContent = c.contact_phone || '-';

          document.getElementById('view-modal').classList.add('open');
        } else {
          toast(data.message || 'Failed to load company', 'error');
        }
      } catch (e) {
        toast('Failed to load company', 'error');
      }
    }

    async function editCompany(id) {
      try {
        const res = await fetch('php/internships.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ action: 'get_company', id: id })
        });
        const data = await res.json();
        if (data.success && data.company) {
          const c = data.company;
          document.getElementById('edit-id').value = c.id;
          document.getElementById('edit-name').value = c.name || '';
          document.getElementById('edit-industry').value = c.industry || '';
          document.getElementById('edit-location').value = c.location || '';
          document.getElementById('edit-website').value = c.website || '';
          document.getElementById('edit-contact_person').value = c.contact_person || '';
          document.getElementById('edit-contact_email').value = c.contact_email || '';
          document.getElementById('edit-contact_phone').value = c.contact_phone || '';
          document.getElementById('edit-modal').classList.add('open');
        } else {
          toast(data.message || 'Failed to load company', 'error');
        }
      } catch (e) {
        toast('Failed to load company', 'error');
      }
    }

    async function deleteCompany(id) {
      if (!confirm('Delete this company?')) return;
      try {
        const res = await fetch('php/internships.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ action: 'delete_company', id })
        });
        const data = await res.json();
        if (data.success) {
          toast('Company deleted successfully!', 'success');
          loadCompanies();
        } else {
          toast(data.message || 'Failed to delete company', 'error');
        }
      } catch (e) {
        toast('Failed to delete company', 'error');
      }
    }

    document.getElementById('add-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(e.target);
      formData.append('action', 'add_company');
      const res = await fetch('php/internships.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      });
      const data = await res.json();
      if (data.success) {
        toast('Company added!', 'success');
        document.getElementById('add-modal').classList.remove('open');
        e.target.reset();
        loadCompanies();
      } else {
        toast(data.message, 'error');
      }
    });

    document.getElementById('edit-form').addEventListener('submit', async (e) => {
      e.preventDefault();
      const formData = new FormData(e.target);
      formData.append('action', 'update_company');
      const res = await fetch('php/internships.php', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: formData
      });
      const data = await res.json();
      if (data.success) {
        toast('Company updated!', 'success');
        document.getElementById('edit-modal').classList.remove('open');
        e.target.reset();
        loadCompanies();
      } else {
        toast(data.message, 'error');
      }
    });

    loadCompanies();
  </script>
</body>
</html>