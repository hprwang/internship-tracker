<?php
session_start();
require_once __DIR__ . '/config.php';
$user = requireAuth();
if (!in_array($user['role'] ?? '', ['admin', 'super_admin'])) {
    http_response_code(403);
    die('<h3>Access Denied</h3><p>Admin access required.</p>');
}
if (!function_exists('e')) {
    function e($value): string {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
}

$csrf = generateCSRF();
$db = Database::getConnection();

// Get all supervisors
$supervisors = $db->query("SELECT * FROM users WHERE role = 'supervisor' OR role = 'faculty' ORDER BY full_name")->fetchAll();

// Get pending requests
$requests = $db->query("
    SELECT r.*, s.full_name as student_name, sup.full_name as supervisor_name
    FROM supervisor_requests r
    JOIN users s ON r.student_id = s.id
    JOIN users sup ON r.supervisor_id = sup.id
    WHERE r.status = 'pending'
    ORDER BY r.created_at DESC
")->fetchAll();

// Get active assignments
$assignments = $db->query("
    SELECT a.*, s.full_name as student_name, sup.full_name as supervisor_name
    FROM supervisor_assignments a
    JOIN users s ON a.student_id = s.id
    JOIN users sup ON a.supervisor_id = sup.id
    WHERE a.status = 'active'
    ORDER BY a.assigned_at DESC
")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Supervisors</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    :root {
      --bg-deep: #050505;
      --bg-charcoal: #0A0A0A;
      --bg-panel: #111111;
      --bg-card: #161616;
      --border-subtle: #222222;
      --green-neon: #22C55E;
      --green-emerald: #16A34A;
      --green-glow: #4ADE80;
      --text-primary: #FFFFFF;
      --text-secondary: #A1A1AA;
      --text-muted: #71717A;
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
      --transition: 200ms;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; }
    .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
    .logo-text { font-size: 1.3rem; font-weight: 800; background: linear-gradient(135deg, #FFFFFF, var(--green-glow)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    .nav-section { margin-bottom: 2rem; }
    .nav-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 0.875rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.875rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); }
    .nav-item .icon { font-size: 1rem; width: 20px; }
    .sidebar-footer { margin-top: auto; padding-top: 1.25rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.875rem; color: var(--bg-deep); }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.875rem; font-weight: 600; }
    .user-role { font-size: 0.7rem; color: var(--text-muted); }
    .main-content { padding: 2rem; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .page-title { font-size: 1.8rem; font-weight: 700; }
    .page-title span { color: var(--green-neon); }
    .page-subtitle { color: var(--text-muted); font-size: 0.95rem; }
    .content-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); margin-bottom: 1.5rem; }
    .card-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .card-title { font-size: 1rem; font-weight: 700; }
    .card-body { padding: 1.5rem; }
    .data-table { width: 100%; }
    .data-table th, .data-table td { padding: 1rem 1.5rem; text-align: left; border-bottom: 1px solid var(--border-subtle); }
    .data-table th { font-size: 0.7rem; font-weight: 700; text-transform: uppercase; color: var(--text-muted); letter-spacing: 0.05em; }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr:hover td { background: var(--bg-panel); }
    .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.6rem 1.25rem; border-radius: var(--radius-md); font-size: 0.85rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: none; }
    .btn-primary { background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); color: var(--text-primary); }
    .btn-primary:hover { transform: translateY(-2px); }
    .btn-success { background: rgba(34,197,94,0.15); color: var(--green-neon); border: 1px solid rgba(34,197,94,0.3); }
    .btn-danger { background: rgba(239,68,68,0.15); color: #EF4444; border: 1px solid rgba(239,68,68,0.3); }
    .empty-state { text-align: center; padding: 3rem; color: var(--text-muted); }
    .toast-container { position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999; }
    .toast { padding: 1rem 1.5rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 10px; display: flex; gap: 0.75rem; font-size: 0.9rem; animation: slideIn .3s ease; }
    .toast.success { border-color: var(--green-neon); }
    @keyframes slideIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
    @media (max-width: 768px) { .admin-layout { grid-template-columns: 1fr; } .sidebar { display: none; } }
  </style>
</head>
<body>
  <div id="toast-container" class="toast-container"></div>
  <div class="admin-layout">
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-icon">⚡</div>
        <span class="logo-text">InternTrack</span>
      </div>
      <div class="nav-section">
        <div class="nav-label">Dashboard</div>
        <nav class="nav-menu">
          <a href="admin_dashboard.php" class="nav-item"><span class="icon">◉</span> Overview</a>
          <a href="admin_students.php" class="nav-item"><span class="icon">👥</span> Students</a>
          <a href="admin_companies.php" class="nav-item"><span class="icon">🏢</span> Companies</a>
          <a href="admin_internships.php" class="nav-item"><span class="icon">💼</span> Internships</a>
          <a href="admin_applications.php" class="nav-item"><span class="icon">📝</span> Applications</a>
          <a href="admin_reports.php" class="nav-item"><span class="icon">📈</span> Reports</a>
        </nav>
      </div>
      <div class="nav-section">
        <div class="nav-label">Academic</div>
        <nav class="nav-menu">
          <a href="admin_supervisors.php" class="nav-item active"><span class="icon">👨‍🏫</span> Supervisors</a>
          <a href="admin_feedback.php" class="nav-item"><span class="icon">💬</span> Feedback</a>
          <a href="admin_evaluations.php" class="nav-item"><span class="icon">📋</span> Evaluations</a>
          <a href="admin_grades.php" class="nav-item"><span class="icon">📊</span> Grades</a>
        </nav>
      </div>
      <div class="sidebar-footer">
        <div class="user-chip">
          <div class="user-avatar"><?= strtoupper(substr($user['full_name'],0,1)) ?></div>
          <div class="user-info">
            <div class="user-name"><?= e($user['full_name']) ?></div>
            <div class="user-role">Administrator</div>
          </div>
        </div>
      </div>
    </aside>
    <main class="main-content">
      <div class="page-header">
        <div>
          <h1 class="page-title">Supervisor <span>Management</span></h1>
          <p class="page-subtitle">Manage faculty supervisors and assignments</p>
        </div>
      </div>

      <!-- Pending Requests -->
      <?php if (count($requests) > 0): ?>
      <div class="content-card">
        <div class="card-header">
          <h3 class="card-title">Pending Requests</h3>
          <span style="color: var(--text-muted); font-size: 0.85rem;"><?= count($requests) ?> new</span>
        </div>
        <div class="card-body" style="padding:0">
          <table class="data-table">
            <thead>
              <tr>
                <th>Student</th>
                <th>Requested Supervisor</th>
                <th>Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($requests as $req): ?>
              <tr>
                <td><?= e($req['student_name']) ?></td>
                <td><?= e($req['supervisor_name']) ?></td>
                <td><?= date('M j, Y', strtotime($req['created_at'])) ?></td>
                <td>
                  <button class="btn btn-success" onclick="handleRequest(<?= $req['id'] ?>, 'approve')">Approve</button>
                  <button class="btn btn-danger" onclick="handleRequest(<?= $req['id'] ?>, 'reject')">Reject</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>

      <!-- Active Assignments -->
      <div class="content-card">
        <div class="card-header">
          <h3 class="card-title">Active Assignments</h3>
        </div>
        <div class="card-body" style="padding:0">
          <?php if (count($assignments) > 0): ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Student</th>
                <th>Supervisor</th>
                <th>Assigned Date</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($assignments as $a): ?>
              <tr>
                <td><?= e($a['student_name']) ?></td>
                <td><?= e($a['supervisor_name']) ?></td>
                <td><?= date('M j, Y', strtotime($a['assigned_at'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div class="empty-state">No active assignments</div>
          <?php endif; ?>
        </div>
      </div>

      <!-- All Supervisors -->
      <div class="content-card">
        <div class="card-header">
          <h3 class="card-title">All Supervisors</h3>
        </div>
        <div class="card-body" style="padding:0">
          <?php if (count($supervisors) > 0): ?>
          <table class="data-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($supervisors as $s): ?>
              <tr>
                <td><?= e($s['full_name']) ?></td>
                <td><?= e($s['email']) ?></td>
                <td><?= $s['is_active'] ? 'Active' : 'Inactive' ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
          <?php else: ?>
          <div class="empty-state">No supervisors found</div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
  <script src="../js/app.js"></script>
  <script>
  function handleRequest(id, action) {
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    fetch('api/admin_supervisor_request.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrfToken, 'Content-Type': 'application/json' },
      body: JSON.stringify({ request_id: id, action: action })
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('Request ' + action + 'ed!', 'success');
        setTimeout(() => location.reload(), 1500);
      } else {
        showToast(data.error || 'Failed', 'error');
      }
    });
  }
  </script>
</body>
</html>