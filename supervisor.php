<?php
session_start();
require_once 'php/config.php';
$user = requireAuth();
$csrf = generateCSRF();

// Get assigned supervisor data
$db = Database::getConnection();
$userId = (int)$user['id'];
$supervisorData = null;

try {
    // Check if user has an assigned supervisor
    $stmt = $db->prepare("
        SELECT s.*, u.full_name as supervisor_name, u.email as supervisor_email
        FROM supervisor_assignments s
        JOIN users u ON s.supervisor_id = u.id
        WHERE s.student_id = ?
    ");
    $stmt->execute([$userId]);
    $supervisorData = $stmt->fetch();
} catch (Exception $e) {
    error_log("Supervisor data error: " . $e->getMessage());
}

// Get available supervisors for assignment
$availableSupervisors = [];
try {
    $stmt = $db->prepare("SELECT id, full_name, email FROM users WHERE role = 'supervisor' OR role = 'faculty'");
    $stmt->execute();
    $availableSupervisors = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Supervisors error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Supervisor Assignment</title>
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
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.75rem; color: var(--text-muted); text-transform: capitalize; }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; text-align: left; margin-top: 0.75rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }
    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; overflow-y: auto; }
    .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .welcome-section h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.25rem; }
    .welcome-section h1 span { color: var(--green-neon); }
    .welcome-section p { color: var(--text-muted); font-size: 0.95rem; }
    .content-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
    .card-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .card-title { font-size: 1rem; font-weight: 700; }
    .card-subtitle { font-size: 0.8rem; color: var(--text-muted); }
    .card-body { padding: 1.5rem; }
    .supervisor-card { display: flex; align-items: center; gap: 1.5rem; padding: 1.5rem; background: var(--bg-panel); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .supervisor-avatar { width: 80px; height: 80px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; font-weight: 700; color: var(--bg-deep); flex-shrink: 0; }
    .supervisor-info { flex: 1; }
    .supervisor-name { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.25rem; }
    .supervisor-role { font-size: 0.9rem; color: var(--green-neon); margin-bottom: 0.5rem; }
    .supervisor-details { display: flex; flex-direction: column; gap: 0.5rem; }
    .supervisor-detail { display: flex; align-items: center; gap: 0.5rem; font-size: 0.85rem; color: var(--text-secondary); }
    .supervisor-detail span { color: var(--text-muted); }
    .empty-state { text-align: center; padding: 3rem; }
    .empty-icon { font-size: 4rem; margin-bottom: 1rem; }
    .empty-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
    .empty-desc { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; }
    .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: none; }
    .btn-primary { background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); color: var(--text-primary); }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(34,197,94,0.3); }
    .btn-secondary { background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-secondary); }
    .btn-secondary:hover { border-color: var(--green-neon); color: var(--green-neon); }
    .form-group { margin-bottom: 1.25rem; }
    .form-label { display: block; font-size: 0.8rem; font-weight: 500; color: var(--text-muted); margin-bottom: 0.5rem; }
    .form-select { width: 100%; padding: 0.875rem 1rem; background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; outline: none; transition: all var(--transition); }
    .form-select:focus { border-color: var(--green-neon); box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }
    .toast-container { position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.75rem; }
    .toast { padding: 1rem 1.5rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.4); display: flex; align-items: center; gap: 0.75rem; font-size: 0.9rem; animation: slideIn .3s ease; min-width: 250px; }
    .toast.success { border-color: var(--green-neon); background: rgba(34,197,94,0.15); }
    .toast.error { border-color: #EF4444; background: rgba(239,68,68,0.15); }
    @keyframes slideIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
    @media (max-width: 1100px) { .dashboard-layout { grid-template-columns: 220px 1fr; } }
    @media (max-width: 768px) { .dashboard-layout { grid-template-columns: 1fr; } .sidebar { display: none; } }
  </style>
</head>
<body>
  <div id="toast-container" class="toast-container"></div>
  <div class="dashboard-layout">
    <aside class="sidebar">
      <div class="sidebar-logo">
        <div class="logo-icon">⚡</div>
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
        <button class="nav-item" onclick="window.location.href='companies.php'">
          <span class="icon">🏢</span> Companies
        </button>
      </nav>
      <div class="nav-label">Academic Monitoring</div>
      <nav class="nav-menu">
        <button class="nav-item active" onclick="window.location.href='supervisor.php'">
          <span class="icon">👨‍🏫</span> Supervisor
        </button>
        <button class="nav-item" onclick="window.location.href='feedback.php'">
          <span class="icon">💬</span> Supervisor Feedback
        </button>
        <button class="nav-item" onclick="window.location.href='evaluation.php'">
          <span class="icon">📋</span> Evaluation Forms
        </button>
        <button class="nav-item" onclick="window.location.href='grades.php'">
          <span class="icon">📊</span> Grades & Performance
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
    <main class="main-content">
      <header class="top-header">
        <div class="welcome-section">
          <h1>Supervisor <span>Assignment</span></h1>
          <p>View and manage your assigned faculty supervisor</p>
        </div>
      </header>
      <?php if ($supervisorData): ?>
      <div class="content-card">
        <div class="card-header">
          <h3 class="card-title">Your Assigned Supervisor</h3>
          <span class="card-subtitle">Active Assignment</span>
        </div>
        <div class="card-body">
          <div class="supervisor-card">
            <div class="supervisor-avatar"><?= strtoupper(substr($supervisorData['supervisor_name'],0,1)) ?></div>
            <div class="supervisor-info">
              <div class="supervisor-name"><?= e($supervisorData['supervisor_name']) ?></div>
              <div class="supervisor-role">Faculty Supervisor</div>
              <div class="supervisor-details">
                <div class="supervisor-detail"><span>📧</span> <?= e($supervisorData['supervisor_email']) ?></div>
                <div class="supervisor-detail"><span>📅</span> Assigned: <?= date('M j, Y', strtotime($supervisorData['assigned_at'])) ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php else: ?>
      <div class="content-card">
        <div class="card-header">
          <h3 class="card-title">Request Supervisor Assignment</h3>
          <span class="card-subtitle">No active assignment</span>
        </div>
        <div class="card-body">
          <div class="empty-state">
            <div class="empty-icon">👨‍🏫</div>
            <div class="empty-title">No Supervisor Assigned</div>
            <div class="empty-desc">You don't have an assigned supervisor yet. Request one from the faculty list below.</div>
            <form id="request-form" onsubmit="requestSupervisor(event)">
              <div class="form-group">
                <label class="form-label">Select Supervisor</label>
                <select name="supervisor_id" class="form-select" required>
                  <option value="">Choose a supervisor...</option>
                  <?php foreach ($availableSupervisors as $sup): ?>
                  <option value="<?= (int)$sup['id'] ?>"><?= e($sup['full_name']) ?> (<?= e($sup['email']) ?>)</option>
                  <?php endforeach; ?>
                </select>
              </div>
              <button type="submit" class="btn btn-primary">Request Assignment</button>
            </form>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </main>
  </div>
  <script src="js/app.js"></script>
  <script>
  function requestSupervisor(e) {
    e.preventDefault();
    var form = e.target;
    var formData = new FormData(form);
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    fetch('php/api/supervisor_request.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrfToken },
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('Supervisor request submitted!', 'success');
        setTimeout(() => location.reload(), 1500);
      } else {
        showToast(data.error || 'Failed to submit request', 'error');
      }
    })
    .catch(err => {
      showToast('An error occurred', 'error');
    });
  }
  </script>
</body>
</html>