<?php
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/partials/company_header.php';
$user = requireCompanyAuth();
$csrf = generateCSRF();

$db = Database::getConnection();
$companyId = (int)$user['company_id'];

$message = '';
$messageType = 'success';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request token. Please try again.';
        $messageType = 'error';
    } elseif ($_POST['action'] === 'update_application_status') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if ($appId > 0 && in_array($status, ['pending', 'under_review', 'accepted', 'rejected'], true)) {
            $stmt = $db->prepare("
                UPDATE applications a
                JOIN company_internships ci ON a.company_internship_id = ci.id
                SET a.status = ?, a.updated_at = NOW()
                WHERE a.id = ? AND ci.company_id = ?
            ");
            $stmt->execute([$status, $appId, $companyId]);
            $message = 'Application status updated to ' . $status . '.';
        } else {
            $message = 'Invalid application or status.';
            $messageType = 'error';
        }
    } elseif ($_POST['action'] === 'delete_application') {
        $appId = (int)($_POST['application_id'] ?? 0);
        $stmt = $db->prepare("
            DELETE a FROM applications a
            JOIN company_internships ci ON a.company_internship_id = ci.id
            WHERE a.id = ? AND ci.company_id = ?
        ");
        $stmt->execute([$appId, $companyId]);
        $message = 'Application removed.';
    }
}

// Filters
$filterInternship = (int)($_GET['internship_id'] ?? 0);
$filterStatus = $_GET['status'] ?? '';

$where = "ci.company_id = ?";
$params = [$companyId];
if ($filterInternship > 0) {
    $where .= " AND a.company_internship_id = ?";
    $params[] = $filterInternship;
}
if ($filterStatus !== '' && in_array($filterStatus, ['pending', 'under_review', 'accepted', 'rejected'], true)) {
    $where .= " AND a.status = ?";
    $params[] = $filterStatus;
}

$stmt = $db->prepare("
    SELECT a.*, ci.title AS internship_title, ci.location AS internship_location,
           u.full_name AS student_name, u.email AS student_email
    FROM applications a
    JOIN company_internships ci ON a.company_internship_id = ci.id
    LEFT JOIN users u ON a.student_id = u.id
    WHERE $where
    ORDER BY a.applied_at DESC
");
$stmt->execute($params);
$applications = $stmt->fetchAll();

// Internship filter dropdown
$intStmt = $db->prepare("SELECT id, title FROM company_internships WHERE company_id = ? ORDER BY title");
$intStmt->execute([$companyId]);
$internships = $intStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Applications</title>
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
    .logo-text { font-size: 1.35rem; font-weight: 800; color: var(--text-primary); } .logo-text span { color: var(--green-neon); }
    .nav-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
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
    .page-title { font-size: 1.8rem; font-weight: 700; } .page-title span { color: var(--green-neon); }
    .page-sub { color: var(--text-muted); font-size: 0.9rem; margin-top: 0.3rem; }

    .filter-bar { display: flex; align-items: flex-end; gap: 1rem; flex-wrap: wrap; margin-bottom: 1.5rem; }
    .filter-group label { display: block; font-size: 0.75rem; font-weight: 600; color: var(--text-muted); margin-bottom: 0.35rem; text-transform: uppercase; letter-spacing: 0.05em; }
    .filter-control { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.55rem 0.8rem; color: var(--text-primary); font-size: 0.88rem; outline: none; min-width: 200px; }
    .filter-control:focus { border-color: var(--green-neon); }
    .btn-ghost { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-subtle); padding: 0.55rem 1.2rem; border-radius: var(--radius-md); text-decoration: none; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all var(--transition); display: inline-flex; align-items: center; gap: 0.4rem; }
    .btn-ghost:hover { border-color: var(--green-neon); color: var(--text-primary); }

    .panel { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; }
    .panel-title { font-size: 1.05rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem; } .panel-title i { color: var(--green-neon); }

    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border-subtle); }
    td { padding: 0.9rem 0.75rem; font-size: 0.9rem; border-bottom: 1px solid rgba(34,34,34,0.6); vertical-align: top; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,0.02); }

    .badge { display: inline-flex; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; }
    .badge.pending { background: rgba(245,158,11,0.15); color: #FBBF24; }
    .badge.under_review { background: rgba(59,130,246,0.15); color: #60A5FA; }
    .badge.accepted { background: rgba(34,197,94,0.15); color: var(--green-neon); }
    .badge.rejected { background: rgba(239,68,68,0.15); color: #F87171; }

    .alert { padding: 0.9rem 1.2rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem; }
    .alert.success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: var(--green-neon); }
    .alert.error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #F87171; }

    .empty-state { text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); }
    .empty-state i { font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.5; }

    .row-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; align-items: center; }
    .status-select { background: var(--bg-charcoal); border: 1px solid var(--border-subtle); border-radius: 8px; color: var(--text-primary); font-size: 0.8rem; padding: 0.4rem 0.5rem; outline: none; }
    .mini-btn { background: var(--bg-charcoal); border: 1px solid var(--border-subtle); color: var(--green-neon); border-radius: 8px; padding: 0.4rem 0.7rem; font-size: 0.78rem; font-weight: 600; cursor: pointer; transition: all var(--transition); }
    .mini-btn:hover { border-color: var(--green-neon); }
    .mini-btn.danger { color: #F87171; }
    .mini-btn.danger:hover { border-color: rgba(239,68,68,0.4); }

    .details-block { margin-top: 0.5rem; font-size: 0.82rem; color: var(--text-secondary); border-top: 1px dashed var(--border-subtle); padding-top: 0.5rem; }
    .details-block p { margin: 0.25rem 0; }

    @media (max-width: 768px) {
      .dashboard-layout { grid-template-columns: 1fr; }
      .sidebar { position: static; height: auto; }
      .main-content { padding: 1rem; }
    }
  </style>
</head>
<body>
  <div class="dashboard-layout">
    <?php renderCompanySidebar($user, 'applications'); ?>

    <main class="main-content">
      <div class="page-header">
        <div>
          <h1 class="page-title">Internship <span>Applications</span></h1>
          <p class="page-sub">Review applicants and update their status.</p>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert <?= e($messageType) ?>"><?= e($message) ?></div>
      <?php endif; ?>

      <form method="GET" class="filter-bar">
        <div class="filter-group">
          <label for="filter-internship">Internship</label>
          <select name="internship_id" id="filter-internship" class="filter-control">
            <option value="">All internships</option>
            <?php foreach ($internships as $row): ?>
              <option value="<?= (int)$row['id'] ?>" <?= $filterInternship === (int)$row['id'] ? 'selected' : '' ?>><?= e($row['title']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="filter-group">
          <label for="filter-status">Status</label>
          <select name="status" id="filter-status" class="filter-control">
            <option value="">All statuses</option>
            <?php foreach (['pending', 'under_review', 'accepted', 'rejected'] as $opt): ?>
              <option value="<?= $opt ?>" <?= $filterStatus === $opt ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $opt)) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <button type="submit" class="btn-ghost"><i class="fas fa-filter"></i> Apply</button>
        <a href="company_applications.php" class="btn-ghost"><i class="fas fa-times"></i> Clear</a>
      </form>

      <div class="panel">
        <div class="panel-title"><i class="fas fa-inbox"></i> Applications (<?= count($applications) ?>)</div>
        <?php if (empty($applications)): ?>
          <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <div>No applications match your filters.</div>
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
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($applications as $app): ?>
                  <tr>
                    <td>
                      <strong><?= e($app['student_name'] ?? '—') ?></strong><br>
                      <small style="color:var(--text-muted)"><?= e($app['student_email'] ?? '') ?></small>
                      <?php if (!empty($app['resume'])): ?>
                        <div class="details-block">
                          <p><strong>Resume:</strong>
                            <a href="../<?= e($app['resume']) ?>" target="_blank" style="color:#22C55E;"><i class="fas fa-file-alt"></i> View Resume</a>
                          </p>
                        </div>
                      <?php endif; ?>
                      <?php if (!empty($app['cover_letter'])): ?>
                        <div class="details-block"><p><strong>Cover letter:</strong> <?= nl2br(e($app['cover_letter'])) ?></p></div>
                      <?php endif; ?>
                      <?php if (!empty($app['notes'])): ?>
                        <div class="details-block"><p><strong>Notes:</strong> <?= nl2br(e($app['notes'])) ?></p></div>
                      <?php endif; ?>
                    </td>
                    <td><?= e($app['internship_title']) ?><br><small style="color:var(--text-muted)"><?= e($app['internship_location'] ?: '') ?></small></td>
                    <td><?= e(date('M j, Y', strtotime($app['applied_at']))) ?></td>
                    <td><span class="badge <?= e($app['status']) ?>"><?= e($app['status']) ?></span></td>
                    <td>
                      <div class="row-actions">
                        <form method="POST" style="display:flex;gap:0.4rem;align-items:center;">
                          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                          <input type="hidden" name="action" value="update_application_status">
                          <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                          <select name="status" class="status-select">
                            <?php foreach (['pending', 'under_review', 'accepted', 'rejected'] as $opt): ?>
                              <option value="<?= $opt ?>" <?= $app['status'] === $opt ? 'selected' : '' ?>><?= ucfirst(str_replace('_', ' ', $opt)) ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button type="submit" class="mini-btn"><i class="fas fa-check"></i> Update</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Remove this application?')">
                          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                          <input type="hidden" name="action" value="delete_application">
                          <input type="hidden" name="application_id" value="<?= (int)$app['id'] ?>">
                          <button type="submit" class="mini-btn danger"><i class="fas fa-trash"></i></button>
                        </form>
                      </div>
                    </td>
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