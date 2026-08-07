<?php
session_start();
require_once __DIR__ . '/config.php';
$user = requireCompanyAuth();
$csrf = generateCSRF();

$db = Database::getCompanyConnection();
ensureCompanySchema($db);

$companyId = (int)$user['company_id'];

// Company info
$coStmt = $db->prepare("SELECT * FROM companies WHERE id = ?");
$coStmt->execute([$companyId]);
$company = $coStmt->fetch();

// Stats
$stats = [];
foreach (['total_internships' => "SELECT COUNT(*) FROM internships WHERE company_id = ?",
          'active_internships' => "SELECT COUNT(*) FROM internships WHERE company_id = ? AND status = 'active'",
          'total_applications' => "SELECT COUNT(*) FROM applications a JOIN internships i ON a.internship_id = i.id WHERE i.company_id = ?",
          'pending_applications' => "SELECT COUNT(*) FROM applications a JOIN internships i ON a.internship_id = i.id WHERE i.company_id = ? AND a.status = 'pending'"] as $key => $sql) {
    $st = $db->prepare($sql);
    $st->execute([$companyId]);
    $stats[$key] = (int)$st->fetchColumn();
}

// Recent applications
$recentStmt = $db->prepare("
    SELECT a.*, i.title AS internship_title
    FROM applications a
    JOIN internships i ON a.internship_id = i.id
    WHERE i.company_id = ?
    ORDER BY a.applied_at DESC LIMIT 8
");
$recentStmt->execute([$companyId]);
$recentApps = $recentStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Company Dashboard</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/responsive.css">
  <style>
    :root {
      --bg-deep: #050505; --bg-charcoal: #0A0A0A; --bg-card: #161616; --border-subtle: #222222;
      --green-neon: #22C55E; --green-emerald: #16A34A; --text-primary: #FFFFFF; --text-secondary: #A1A1AA;
      --text-muted: #71717A; --radius-md: 12px; --radius-lg: 16px; --transition: 200ms cubic-bezier(.4,0,.2,1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.55; }

    .dashboard-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; text-decoration: none; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 0 20px rgba(34,197,94,0.3); }
    .logo-text { font-size: 1.35rem; font-weight: 800; color: var(--text-primary); }
    .logo-text span { color: var(--green-neon); }

    .nav-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3); }
    .nav-item .icon { font-size: 1.1rem; width: 22px; text-align: center; }

    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; color: var(--bg-deep); flex-shrink: 0; }
    .user-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.75rem; color: var(--text-muted); }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; text-align: left; margin-top: 0.75rem; text-decoration: none; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }

    .main-content { padding: 1.5rem 2rem; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); flex-wrap: wrap; gap: 1rem; }
    .page-title { font-size: 1.8rem; font-weight: 700; }
    .page-title span { color: var(--green-neon); }
    .page-sub { color: var(--text-muted); font-size: 0.9rem; margin-top: 0.3rem; }
    .header-actions { display: flex; gap: 0.75rem; }
    .add-btn { background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); color: var(--bg-deep); font-weight: 700; padding: 0.7rem 1.4rem; border: none; border-radius: var(--radius-md); cursor: pointer; transition: all var(--transition); text-decoration: none; font-size: 0.9rem; }
    .add-btn:hover { box-shadow: 0 0 25px rgba(34,197,94,0.5); transform: translateY(-2px); }
    .ghost-btn { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-subtle); padding: 0.7rem 1.4rem; border-radius: var(--radius-md); text-decoration: none; font-size: 0.9rem; font-weight: 600; transition: all var(--transition); }
    .ghost-btn:hover { border-color: var(--green-neon); color: var(--text-primary); }

    .kpi-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 1rem; margin-bottom: 1.5rem; }
    .kpi-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.25rem; transition: all var(--transition); position: relative; overflow: hidden; }
    .kpi-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: linear-gradient(90deg, var(--green-emerald), var(--green-neon)); opacity: 0; transition: opacity var(--transition); }
    .kpi-card:hover { border-color: var(--border-subtle); transform: translateY(-3px); box-shadow: 0 8px 30px rgba(0,0,0,0.3); }
    .kpi-card:hover::before { opacity: 1; }
    .kpi-label { font-size: 0.72rem; color: var(--text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: 0.06em; margin-bottom: 0.6rem; }
    .kpi-value { font-size: 2rem; font-weight: 800; line-height: 1.1; }
    .kpi-card.blue::before { background: linear-gradient(90deg, #3B82F6, #60A5FA); }
    .kpi-card.purple::before { background: linear-gradient(90deg, #A855F7, #C084FC); }
    .kpi-card.amber::before { background: linear-gradient(90deg, #F59E0B, #FBBF24); }
    .kpi-value.blue { color: #60A5FA; } .kpi-value.purple { color: #C084FC; } .kpi-value.amber { color: #FBBF24; }

    .panel { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; }
    .panel-title { font-size: 1.05rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem; }
    .panel-title i { color: var(--green-neon); }

    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border-subtle); }
    td { padding: 0.8rem 0.75rem; font-size: 0.9rem; border-bottom: 1px solid rgba(34,34,34,0.6); }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,0.02); }

    .badge { display: inline-flex; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; }
    .badge.pending { background: rgba(245,158,11,0.15); color: #FBBF24; }
    .badge.under_review { background: rgba(59,130,246,0.15); color: #60A5FA; }
    .badge.accepted { background: rgba(34,197,94,0.15); color: var(--green-neon); }
    .badge.rejected { background: rgba(239,68,68,0.15); color: #F87171; }
    .badge.active { background: rgba(34,197,94,0.15); color: var(--green-neon); }
    .badge.closed { background: rgba(239,68,68,0.15); color: #F87171; }

    .empty-state { text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); }
    .empty-state i { font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.5; }
    .company-banner { background: linear-gradient(135deg, rgba(34,197,94,0.12), rgba(16,185,129,0.05)); border: 1px solid rgba(34,197,94,0.25); border-radius: var(--radius-lg); padding: 1.5rem 2rem; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 1.5rem; flex-wrap: wrap; }
    .company-banner h2 { font-size: 1.5rem; font-weight: 800; }
    .company-banner p { color: var(--text-muted); font-size: 0.9rem; margin-top: 0.3rem; }

    @media (max-width: 1024px) { .kpi-grid { grid-template-columns: repeat(2, 1fr); } }
    @media (max-width: 768px) {
      .dashboard-layout { grid-template-columns: 1fr; }
      .sidebar { position: static; height: auto; }
      .main-content { padding: 1rem; }
      .kpi-grid { grid-template-columns: 1fr; }
      .page-header { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body>
  <div id="toast-container" class="toast-container"></div>

  <div class="dashboard-layout">
    <aside class="sidebar">
      <a class="sidebar-logo" href="company_dashboard.php">
        <div class="logo-icon"><i class="fas fa-building"></i></div>
        <div class="logo-text">Intern<span>Track</span></div>
      </a>

      <div class="nav-menu">
        <div class="nav-label">Menu</div>
        <a class="nav-item active" href="company_dashboard.php"><span class="icon"><i class="fas fa-chart-pie"></i></span> Dashboard</a>
        <a class="nav-item" href="company_internships.php"><span class="icon"><i class="fas fa-briefcase"></i></span> Internships</a>
        <a class="nav-item" href="company_applications.php"><span class="icon"><i class="fas fa-file-signature"></i></span> Applications</a>
        <a class="nav-item" href="company_profile.php"><span class="icon"><i class="fas fa-user-cog"></i></span> Company Profile</a>
      </div>

      <div class="sidebar-footer">
        <div class="user-chip">
          <div class="user-avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'C', 0, 1))) ?></div>
          <div>
            <div class="user-name"><?= e($user['full_name'] ?? 'Company User') ?></div>
            <div class="user-role"><?= e($company['name'] ?? 'Company') ?></div>
          </div>
        </div>
        <a class="logout-btn" href="#" onclick="handleLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </aside>

    <main class="main-content">
      <div class="page-header">
        <div>
          <h1 class="page-title">Company <span>Dashboard</span></h1>
          <p class="page-sub">Welcome back — here's what's happening with your internships.</p>
        </div>
        <div class="header-actions">
          <a class="add-btn" href="company_internships.php"><i class="fas fa-plus"></i> Post Internship</a>
          <a class="ghost-btn" href="company_applications.php"><i class="fas fa-inbox"></i> View Applications</a>
        </div>
      </div>

      <div class="company-banner">
        <div>
          <h2><?= e($company['name'] ?? 'Your Company') ?></h2>
          <p>
            <?= e($company['industry'] ?? '') ?>
            <?php if (!empty($company['location'])): ?> · <?= e($company['location']) ?><?php endif; ?>
            <?php if (!empty($company['website'])): ?> · <a href="<?= e($company['website']) ?>" target="_blank" style="color:var(--green-neon)"><?= e($company['website']) ?></a><?php endif; ?>
          </p>
        </div>
      </div>

      <div class="kpi-grid">
        <div class="kpi-card">
          <div class="kpi-label">Total Internships</div>
          <div class="kpi-value"><?= (int)$stats['total_internships'] ?></div>
        </div>
        <div class="kpi-card blue">
          <div class="kpi-label">Active Internships</div>
          <div class="kpi-value blue"><?= (int)$stats['active_internships'] ?></div>
        </div>
        <div class="kpi-card purple">
          <div class="kpi-label">Total Applications</div>
          <div class="kpi-value purple"><?= (int)$stats['total_applications'] ?></div>
        </div>
        <div class="kpi-card amber">
          <div class="kpi-label">Pending Applications</div>
          <div class="kpi-value amber"><?= (int)$stats['pending_applications'] ?></div>
        </div>
      </div>

      <div class="panel">
        <div class="panel-title"><i class="fas fa-clock"></i> Recent Applications</div>
        <?php if (empty($recentApps)): ?>
          <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <div>No applications yet. Post an internship to start receiving applications.</div>
            <div style="margin-top:1rem;"><a class="add-btn" href="company_internships.php">Post your first internship</a></div>
          </div>
        <?php else: ?>
          <div style="overflow-x:auto;">
            <table>
              <thead>
                <tr>
                  <th>Student</th>
                  <th>Internship</th>
                  <th>Applied</th>
                  <th>Status</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($recentApps as $app): ?>
                  <tr>
                    <td>
                      <strong><?= e($app['student_name'] ?? '—') ?></strong><br>
                      <small style="color:var(--text-muted)"><?= e($app['student_email'] ?? '') ?></small>
                    </td>
                    <td><?= e($app['internship_title']) ?></td>
                    <td><?= e(date('M j, Y', strtotime($app['applied_at']))) ?></td>
                    <td><span class="badge <?= e($app['status']) ?>"><?= e($app['status']) ?></span></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <script src="../js/app.js"></script>
  <script src="../js/interactive.js"></script>
  <script>
  function handleLogout(e) {
    e.preventDefault();
    fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) })
      .finally(() => { window.location.href = '../landing.php'; });
  }
  </script>
</body>
</html>