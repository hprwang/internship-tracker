<?php
session_start();
require_once __DIR__ . '/config.php';
$user = requireCompanyAuth();
$csrf = generateCSRF();

$db = Database::getCompanyConnection();
ensureCompanySchema($db);
$companyId = (int)$user['company_id'];

$message = '';
$messageType = 'success';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid request token. Please try again.';
        $messageType = 'error';
    } else {
        switch ($_POST['action']) {
            case 'add_internship':
                $title = trim($_POST['title'] ?? '');
                if ($title === '') {
                    $message = 'Internship title is required.';
                    $messageType = 'error';
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO internships (company_id, title, description, requirements, location, duration, stipend, status)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
                    ");
                    $stmt->execute([
                        $companyId,
                        $title,
                        trim($_POST['description'] ?? ''),
                        trim($_POST['requirements'] ?? ''),
                        trim($_POST['location'] ?? ''),
                        trim($_POST['duration'] ?? ''),
                        $_POST['stipend'] !== '' ? (float)$_POST['stipend'] : null,
                    ]);
                    $message = 'Internship posted successfully!';
                }
                break;

            case 'update_internship':
                $id = (int)($_POST['id'] ?? 0);
                $title = trim($_POST['title'] ?? '');
                if ($id <= 0 || $title === '') {
                    $message = 'Invalid internship data.';
                    $messageType = 'error';
                } else {
                    $stmt = $db->prepare("
                        UPDATE internships SET title=?, description=?, requirements=?, location=?, duration=?, stipend=?
                        WHERE id=? AND company_id=?
                    ");
                    $stmt->execute([
                        $title,
                        trim($_POST['description'] ?? ''),
                        trim($_POST['requirements'] ?? ''),
                        trim($_POST['location'] ?? ''),
                        trim($_POST['duration'] ?? ''),
                        $_POST['stipend'] !== '' ? (float)$_POST['stipend'] : null,
                        $id,
                        $companyId,
                    ]);
                    $message = 'Internship updated successfully!';
                }
                break;

            case 'set_status':
                $id = (int)($_POST['id'] ?? 0);
                $status = $_POST['status'] ?? '';
                if ($id > 0 && in_array($status, ['active', 'closed', 'pending'], true)) {
                    $db->prepare("UPDATE internships SET status=? WHERE id=? AND company_id=?")
                       ->execute([$status, $id, $companyId]);
                    $message = 'Internship status updated to ' . $status . '.';
                } else {
                    $message = 'Invalid status.';
                    $messageType = 'error';
                }
                break;

            case 'delete_internship':
                $id = (int)($_POST['id'] ?? 0);
                if ($id > 0) {
                    $db->prepare("DELETE FROM applications WHERE internship_id = ? AND internship_id IN (SELECT id FROM internships WHERE company_id = ?)")
                       ->execute([$id, $companyId]);
                    $db->prepare("DELETE FROM internships WHERE id=? AND company_id=?")
                       ->execute([$id, $companyId]);
                    $message = 'Internship deleted.';
                } else {
                    $message = 'Invalid internship.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Load internships with application counts
$stmt = $db->prepare("
    SELECT i.*,
           (SELECT COUNT(*) FROM applications a WHERE a.internship_id = i.id) AS app_count,
           (SELECT COUNT(*) FROM applications a WHERE a.internship_id = i.id AND a.status = 'pending') AS pending_count
    FROM internships i
    WHERE i.company_id = ?
    ORDER BY i.created_at DESC
");
$stmt->execute([$companyId]);
$internships = $stmt->fetchAll();

// Editing state
$editInternship = null;
if (isset($_GET['edit'])) {
    $editId = (int)$_GET['edit'];
    foreach ($internships as $row) {
        if ((int)$row['id'] === $editId) { $editInternship = $row; break; }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Manage Internships</title>
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
    .add-btn { background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); color: var(--bg-deep); font-weight: 700; padding: 0.7rem 1.4rem; border: none; border-radius: var(--radius-md); cursor: pointer; transition: all var(--transition); text-decoration: none; font-size: 0.9rem; display: inline-flex; align-items: center; gap: 0.5rem; }
    .add-btn:hover { box-shadow: 0 0 25px rgba(34,197,94,0.5); transform: translateY(-2px); }

    .panel { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; margin-bottom: 1.5rem; }
    .panel-title { font-size: 1.05rem; font-weight: 700; margin-bottom: 1.25rem; display: flex; align-items: center; gap: 0.6rem; } .panel-title i { color: var(--green-neon); }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1rem; }
    .form-group.full { grid-column: 1 / -1; }
    .form-label { display: block; font-size: 0.78rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 0.4rem; }
    .form-control { width: 100%; background: var(--bg-charcoal); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); padding: 0.7rem 0.9rem; color: var(--text-primary); font-size: 0.9rem; outline: none; transition: border-color 200ms, box-shadow 200ms; }
    .form-control:focus { border-color: var(--green-neon); box-shadow: 0 0 0 3px rgba(34,197,94,0.12); }
    textarea.form-control { resize: vertical; min-height: 90px; }
    .btn-primary { background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); color: var(--bg-deep); font-weight: 700; padding: 0.7rem 1.6rem; border: none; border-radius: var(--radius-md); cursor: pointer; font-size: 0.9rem; transition: all var(--transition); }
    .btn-primary:hover { box-shadow: 0 0 25px rgba(34,197,94,0.45); }
    .btn-ghost { background: var(--bg-charcoal); color: var(--text-secondary); border: 1px solid var(--border-subtle); padding: 0.6rem 1.1rem; border-radius: var(--radius-md); text-decoration: none; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all var(--transition); display: inline-flex; align-items: center; gap: 0.4rem; }
    .btn-ghost:hover { border-color: var(--green-neon); color: var(--text-primary); }
    .btn-danger { background: rgba(239,68,68,0.12); color: #F87171; border: 1px solid rgba(239,68,68,0.3); padding: 0.6rem 1.1rem; border-radius: var(--radius-md); text-decoration: none; font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all var(--transition); display: inline-flex; align-items: center; gap: 0.4rem; }
    .btn-danger:hover { background: rgba(239,68,68,0.2); }

    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 0.72rem; text-transform: uppercase; letter-spacing: 0.08em; color: var(--text-muted); padding: 0.6rem 0.75rem; border-bottom: 1px solid var(--border-subtle); }
    td { padding: 0.85rem 0.75rem; font-size: 0.9rem; border-bottom: 1px solid rgba(34,34,34,0.6); vertical-align: top; }
    tr:last-child td { border-bottom: none; }
    tr:hover td { background: rgba(255,255,255,0.02); }

    .badge { display: inline-flex; padding: 0.25rem 0.7rem; border-radius: 999px; font-size: 0.72rem; font-weight: 700; text-transform: uppercase; }
    .badge.active { background: rgba(34,197,94,0.15); color: var(--green-neon); }
    .badge.pending { background: rgba(245,158,11,0.15); color: #FBBF24; }
    .badge.closed { background: rgba(239,68,68,0.15); color: #F87171; }

    .alert { padding: 0.9rem 1.2rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; font-size: 0.9rem; }
    .alert.success { background: rgba(34,197,94,0.1); border: 1px solid rgba(34,197,94,0.3); color: var(--green-neon); }
    .alert.error { background: rgba(239,68,68,0.1); border: 1px solid rgba(239,68,68,0.3); color: #F87171; }

    .empty-state { text-align: center; padding: 2.5rem 1rem; color: var(--text-muted); }
    .empty-state i { font-size: 2rem; margin-bottom: 0.75rem; opacity: 0.5; }

    .row-actions { display: flex; gap: 0.5rem; flex-wrap: wrap; }
    .status-form { display: inline-flex; align-items: center; gap: 0.4rem; }
    .status-form select { background: var(--bg-charcoal); border: 1px solid var(--border-subtle); border-radius: 8px; color: var(--text-primary); font-size: 0.8rem; padding: 0.35rem 0.5rem; outline: none; }
    .status-form button { background: none; border: none; color: var(--green-neon); cursor: pointer; font-size: 0.9rem; padding: 0.25rem; }

    @media (max-width: 1024px) { .form-grid { grid-template-columns: 1fr; } }
    @media (max-width: 768px) {
      .dashboard-layout { grid-template-columns: 1fr; }
      .sidebar { position: static; height: auto; }
      .main-content { padding: 1rem; }
      .page-header { flex-direction: column; align-items: flex-start; }
    }
  </style>
</head>
<body>
  <div class="dashboard-layout">
    <aside class="sidebar">
      <a class="sidebar-logo" href="company_dashboard.php">
        <div class="logo-icon"><i class="fas fa-building"></i></div>
        <div class="logo-text">Intern<span>Track</span></div>
      </a>
      <div class="nav-menu">
        <div class="nav-label">Menu</div>
        <a class="nav-item" href="company_dashboard.php"><span class="icon"><i class="fas fa-chart-pie"></i></span> Dashboard</a>
        <a class="nav-item active" href="company_internships.php"><span class="icon"><i class="fas fa-briefcase"></i></span> Internships</a>
        <a class="nav-item" href="company_applications.php"><span class="icon"><i class="fas fa-file-signature"></i></span> Applications</a>
        <a class="nav-item" href="company_profile.php"><span class="icon"><i class="fas fa-user-cog"></i></span> Company Profile</a>
      </div>
      <div class="sidebar-footer">
        <div class="user-chip">
          <div class="user-avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'C', 0, 1))) ?></div>
          <div>
            <div class="user-name"><?= e($user['full_name'] ?? 'Company User') ?></div>
            <div class="user-role">Company Admin</div>
          </div>
        </div>
        <a class="logout-btn" href="#" onclick="handleLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
      </div>
    </aside>

    <main class="main-content">
      <div class="page-header">
        <div>
          <h1 class="page-title">Manage <span>Internships</span></h1>
          <p class="page-sub">Post new opportunities or manage your existing listings.</p>
        </div>
        <a class="add-btn" href="#post-form"><i class="fas fa-plus"></i> Post New Internship</a>
      </div>

      <?php if ($message): ?>
        <div class="alert <?= e($messageType) ?>"><?= e($message) ?></div>
      <?php endif; ?>

      <div class="panel" id="post-form">
        <div class="panel-title"><i class="fas fa-pen"></i> <?= $editInternship ? 'Edit Internship' : 'Post a New Internship' ?></div>
        <form method="POST">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="<?= $editInternship ? 'update_internship' : 'add_internship' ?>">
          <?php if ($editInternship): ?><input type="hidden" name="id" value="<?= (int)$editInternship['id'] ?>"><?php endif; ?>

          <div class="form-grid">
            <div class="form-group full">
              <label class="form-label">Internship Title *</label>
              <input type="text" name="title" class="form-control" required placeholder="e.g. Software Engineering Intern" value="<?= $editInternship ? e($editInternship['title']) : '' ?>">
            </div>
            <div class="form-group full">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" placeholder="What will the intern do?"><?= $editInternship ? e($editInternship['description'] ?? '') : '' ?></textarea>
            </div>
            <div class="form-group full">
              <label class="form-label">Requirements</label>
              <textarea name="requirements" class="form-control" placeholder="Skills, education, experience required"><?= $editInternship ? e($editInternship['requirements'] ?? '') : '' ?></textarea>
            </div>
            <div class="form-group">
              <label class="form-label">Location</label>
              <input type="text" name="location" class="form-control" placeholder="e.g. Kathmandu / Remote" value="<?= $editInternship ? e($editInternship['location'] ?? '') : '' ?>">
            </div>
            <div class="form-group">
              <label class="form-label">Duration</label>
              <input type="text" name="duration" class="form-control" placeholder="e.g. 3 months" value="<?= $editInternship ? e($editInternship['duration'] ?? '') : '' ?>">
            </div>
            <div class="form-group full">
              <label class="form-label">Stipend (NPR)</label>
              <input type="number" name="stipend" class="form-control" step="0.01" min="0" placeholder="e.g. 15000" value="<?= $editInternship && $editInternship['stipend'] !== null ? e($editInternship['stipend']) : '' ?>">
            </div>
          </div>

          <div style="margin-top:1.2rem;display:flex;gap:0.75rem;">
            <button type="submit" class="btn-primary"><?= $editInternship ? 'Save Changes' : 'Post Internship' ?></button>
            <?php if ($editInternship): ?>
              <a class="btn-ghost" href="company_internships.php">Cancel Edit</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <div class="panel">
        <div class="panel-title"><i class="fas fa-briefcase"></i> Your Internships (<?= count($internships) ?>)</div>
        <?php if (empty($internships)): ?>
          <div class="empty-state">
            <i class="fas fa-briefcase"></i>
            <div>No internships posted yet. Use the form above to create your first one.</div>
          </div>
        <?php else: ?>
          <div style="overflow-x:auto;">
            <table>
              <thead>
                <tr>
                  <th>Title</th>
                  <th>Location</th>
                  <th>Duration</th>
                  <th>Stipend</th>
                  <th>Applications</th>
                  <th>Status</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($internships as $row): ?>
                  <tr>
                    <td>
                      <strong><?= e($row['title']) ?></strong>
                      <?php if (!empty($row['description'])): ?>
                        <br><small style="color:var(--text-muted)"><?= e(mb_strimwidth($row['description'], 0, 60, '…')) ?></small>
                      <?php endif; ?>
                    </td>
                    <td><?= e($row['location'] ?: '—') ?></td>
                    <td><?= e($row['duration'] ?: '—') ?></td>
                    <td><?= $row['stipend'] !== null ? 'Rs. ' . number_format((float)$row['stipend']) : '—' ?></td>
                    <td>
                      <?= (int)$row['app_count'] ?>
                      <?php if ((int)$row['pending_count'] > 0): ?>
                        <span class="badge pending"><?= (int)$row['pending_count'] ?> new</span>
                      <?php endif; ?>
                    </td>
                    <td><span class="badge <?= e($row['status']) ?>"><?= e($row['status']) ?></span></td>
                    <td>
                      <div class="row-actions">
                        <a class="btn-ghost" href="company_internships.php?edit=<?= (int)$row['id'] ?>"><i class="fas fa-edit"></i> Edit</a>
                        <form method="POST" class="status-form" onsubmit="return confirm('Change status to ' + this.status.value + '?')">
                          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                          <input type="hidden" name="action" value="set_status">
                          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                          <select name="status">
                            <?php foreach (['active', 'pending', 'closed'] as $opt): ?>
                              <option value="<?= $opt ?>" <?= $row['status'] === $opt ? 'selected' : '' ?>><?= ucfirst($opt) ?></option>
                            <?php endforeach; ?>
                          </select>
                          <button type="submit" title="Apply status"><i class="fas fa-check"></i></button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Delete this internship and all its applications?')">
                          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                          <input type="hidden" name="action" value="delete_internship">
                          <input type="hidden" name="id" value="<?= (int)$row['id'] ?>">
                          <button type="submit" class="btn-danger"><i class="fas fa-trash"></i></button>
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