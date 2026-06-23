<?php
session_start();
require_once 'php/config.php';
$user = requireAuth();
$csrf = generateCSRF();

// Get evaluation forms
$db = Database::getConnection();
$userId = (int)$user['id'];
$evaluations = [];

try {
    $stmt = $db->prepare("
        SELECT e.*, i.title as internship_title, c.name as company_name
        FROM evaluations e
        LEFT JOIN internships i ON e.internship_id = i.id
        LEFT JOIN companies c ON i.company_id = c.id
        WHERE e.student_id = ?
        ORDER BY e.created_at DESC
    ");
    $stmt->execute([$userId]);
    $evaluations = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Evaluations error: " . $e->getMessage());
}

// Get available internships for new evaluation
$internships = [];
try {
    $stmt = $db->prepare("
        SELECT i.id, i.title, c.name as company_name
        FROM internships i
        JOIN companies c ON i.company_id = c.id
        WHERE i.student_id = ? AND i.status IN ('ongoing', 'completed')
    ");
    $stmt->execute([$userId]);
    $internships = $stmt->fetchAll();
} catch (Exception $e) {
    error_log("Internships error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Evaluation Forms</title>
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
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; }
    .dashboard-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.5rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.5rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.5rem; }
    .logo-icon { width: 40px; height: 40px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.2rem; box-shadow: 0 0 20px rgba(34,197,94,0.3); }
    .logo-text { font-size: 1.35rem; font-weight: 800; background: linear-gradient(135deg, var(--text-primary), var(--green-glow)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }
    .nav-label { font-size: 0.7rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; flex: 1; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3); }
    .nav-item .icon { font-size: 1.1rem; width: 22px; text-align: center; }
    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 36px; height: 36px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; color: var(--bg-deep); flex-shrink: 0; }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.9rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.75rem; color: var(--text-muted); text-transform: capitalize; }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; text-align: left; margin-top: 0.75rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }
    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; }
    .top-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding-bottom: 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .welcome-section h1 { font-size: 1.8rem; font-weight: 700; margin-bottom: 0.25rem; }
    .welcome-section h1 span { color: var(--green-neon); }
    .welcome-section p { color: var(--text-muted); font-size: 0.95rem; }
    .btn { display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1.5rem; border-radius: var(--radius-md); font-size: 0.9rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: none; }
    .btn-primary { background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); color: var(--text-primary); }
    .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 10px 30px rgba(34,197,94,0.3); }
    .btn-secondary { background: var(--bg-card); border: 1px solid var(--border-subtle); color: var(--text-secondary); }
    .btn-secondary:hover { border-color: var(--green-neon); color: var(--green-neon); }
    .eval-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 1rem; }
    .eval-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; }
    .eval-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1rem; }
    .eval-title { font-size: 1rem; font-weight: 700; }
    .eval-status { padding: 0.25rem 0.75rem; border-radius: 20px; font-size: 0.75rem; font-weight: 600; }
    .eval-status.draft { background: rgba(234,179,8,0.15); color: #FACC15; border: 1px solid rgba(234,179,8,0.3); }
    .eval-status.submitted { background: rgba(96,165,250,0.15); color: #60A5FA; border: 1px solid rgba(96,165,250,0.3); }
    .eval-status.completed { background: rgba(34,197,94,0.15); color: var(--green-neon); border: 1px solid rgba(34,197,94,0.3); }
    .eval-company { font-size: 0.85rem; color: var(--text-muted); margin-bottom: 1rem; }
    .eval-scores { display: flex; flex-direction: column; gap: 0.75rem; }
    .eval-score { display: flex; justify-content: space-between; align-items: center; }
    .eval-score-label { font-size: 0.85rem; color: var(--text-secondary); }
    .eval-score-value { font-weight: 700; color: var(--green-neon); }
    .eval-date { font-size: 0.75rem; color: var(--text-muted); margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .empty-state { text-align: center; padding: 4rem; }
    .empty-icon { font-size: 4rem; margin-bottom: 1rem; }
    .empty-title { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
    .empty-desc { font-size: 0.9rem; color: var(--text-muted); margin-bottom: 1.5rem; }
    .form-group { margin-bottom: 1.25rem; }
    .form-label { display: block; font-size: 0.8rem; font-weight: 500; color: var(--text-muted); margin-bottom: 0.5rem; }
    .form-select, .form-textarea { width: 100%; padding: 0.875rem 1rem; background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.95rem; outline: none; font-family: inherit; }
    .form-select:focus, .form-textarea:focus { border-color: var(--green-neon); box-shadow: 0 0 0 3px rgba(34,197,94,0.1); }
    .form-textarea { min-height: 120px; resize: vertical; }
    .rating-group { display: flex; gap: 0.5rem; margin-bottom: 1rem; }
    .rating-btn { width: 40px; height: 40px; border: 1px solid var(--border-subtle); background: var(--bg-panel); border-radius: var(--radius-md); color: var(--text-muted); font-size: 1rem; cursor: pointer; transition: all var(--transition); }
    .rating-btn:hover, .rating-btn.selected { border-color: var(--green-neon); background: rgba(34,197,94,0.1); color: var(--green-neon); }
    .modal-overlay { position: fixed; inset: 0; background: rgba(0,0,0,0.8); display: flex; align-items: center; justify-content: center; z-index: 1000; }
    .modal { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 1.25rem 1.5rem; border-bottom: 1px solid var(--border-subtle); }
    .modal-title { font-size: 1.1rem; font-weight: 700; }
    .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.5rem; cursor: pointer; }
    .modal-body { padding: 1.5rem; }
    .modal-footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding: 1.25rem 1.5rem; border-top: 1px solid var(--border-subtle); }
    .toast-container { position: fixed; top: 1.5rem; right: 1.5rem; z-index: 9999; }
    .toast { padding: 1rem 1.5rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: 10px; display: flex; gap: 0.75rem; font-size: 0.9rem; animation: slideIn .3s ease; }
    .toast.success { border-color: var(--green-neon); background: rgba(34,197,94,0.15); }
    @keyframes slideIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: translateX(0); } }
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
        <button class="nav-item" onclick="window.location.href='supervisor.php'">
          <span class="icon">👨‍🏫</span> Supervisor
        </button>
        <button class="nav-item" onclick="window.location.href='feedback.php'">
          <span class="icon">💬</span> Supervisor Feedback
        </button>
        <button class="nav-item active" onclick="window.location.href='evaluation.php'">
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
          <h1>Evaluation <span>Forms</span></h1>
          <p>Complete and view internship evaluation forms</p>
        </div>
        <button class="btn btn-primary" onclick="openEvalModal()">+ New Evaluation</button>
      </header>
      <?php if (count($evaluations) > 0): ?>
      <div class="eval-grid">
        <?php foreach ($evaluations as $eval): ?>
        <div class="eval-card">
          <div class="eval-header">
            <div class="eval-title"><?= e($eval['internship_title'] ?: 'General Evaluation') ?></div>
            <span class="eval-status <?= $eval['status'] ?>"><?= e($eval['status']) ?></span>
          </div>
          <div class="eval-company"><?= e($eval['company_name'] ?: 'N/A') ?></div>
          <div class="eval-scores">
            <div class="eval-score">
              <span class="eval-score-label">Performance</span>
              <span class="eval-score-value"><?= (int)$eval['performance_score'] ?>/5</span>
            </div>
            <div class="eval-score">
              <span class="eval-score-label">Professionalism</span>
              <span class="eval-score-value"><?= (int)$eval['professionalism_score'] ?>/5</span>
            </div>
            <div class="eval-score">
              <span class="eval-score-label">Learning</span>
              <span class="eval-score-value"><?= (int)$eval['learning_score'] ?>/5</span>
            </div>
          </div>
          <div class="eval-date">Submitted: <?= date('M j, Y', strtotime($eval['created_at'])) ?></div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else: ?>
      <div class="empty-state">
        <div class="empty-icon">📋</div>
        <div class="empty-title">No Evaluations Yet</div>
        <p class="empty-desc">Create your first evaluation form for an internship.</p>
        <button class="btn btn-primary" onclick="openEvalModal()">Create Evaluation</button>
      </div>
      <?php endif; ?>
    </main>
  </div>

  <!-- New Evaluation Modal -->
  <div id="eval-modal" class="modal-overlay" style="display:none">
    <div class="modal">
      <div class="modal-header">
        <div class="modal-title">New Evaluation</div>
        <button class="modal-close" onclick="closeEvalModal()">×</button>
      </div>
      <form onsubmit="submitEvaluation(event)">
        <div class="modal-body">
          <div class="form-group">
            <label class="form-label">Internship</label>
            <select name="internship_id" class="form-select">
              <option value="">General (No specific internship)</option>
              <?php foreach ($internships as $int): ?>
              <option value="<?= (int)$int['id'] ?>"><?= e($int['title']) ?> at <?= e($int['company_name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Performance Rating</label>
            <div class="rating-group" data-field="performance_score">
              <?php for ($i = 1; $i <= 5; $i++): ?>
              <button type="button" class="rating-btn" onclick="selectRating(this, <?= $i ?>)"><?= $i ?></button>
              <?php endfor; ?>
            </div>
            <input type="hidden" name="performance_score" id="performance_score" value="0">
          </div>
          <div class="form-group">
            <label class="form-label">Professionalism Rating</label>
            <div class="rating-group" data-field="professionalism_score">
              <?php for ($i = 1; $i <= 5; $i++): ?>
              <button type="button" class="rating-btn" onclick="selectRating(this, <?= $i ?>)"><?= $i ?></button>
              <?php endfor; ?>
            </div>
            <input type="hidden" name="professionalism_score" id="professionalism_score" value="0">
          </div>
          <div class="form-group">
            <label class="form-label">Learning & Growth Rating</label>
            <div class="rating-group" data-field="learning_score">
              <?php for ($i = 1; $i <= 5; $i++): ?>
              <button type="button" class="rating-btn" onclick="selectRating(this, <?= $i ?>)"><?= $i ?></button>
              <?php endfor; ?>
            </div>
            <input type="hidden" name="learning_score" id="learning_score" value="0">
          </div>
          <div class="form-group">
            <label class="form-label">Comments</label>
            <textarea name="comments" class="form-textarea" placeholder="Share your experience..."></textarea>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" onclick="closeEvalModal()">Cancel</button>
          <button type="submit" class="btn btn-primary">Submit Evaluation</button>
        </div>
      </form>
    </div>
  </div>

  <script src="js/app.js"></script>
  <script>
  function openEvalModal() { document.getElementById('eval-modal').style.display = 'flex'; }
  function closeEvalModal() { document.getElementById('eval-modal').style.display = 'none'; }
  function selectRating(btn, value) {
    var group = btn.parentElement;
    group.querySelectorAll('.rating-btn').forEach(function(b) { b.classList.remove('selected'); });
    btn.classList.add('selected');
    document.getElementById(group.dataset.field).value = value;
  }
  function submitEvaluation(e) {
    e.preventDefault();
    var form = e.target;
    var formData = new FormData(form);
    var csrfToken = document.querySelector('meta[name="csrf-token"]').content;
    if (document.getElementById('performance_score').value === '0') {
      showToast('Please select ratings', 'error');
      return;
    }
    fetch('php/api/evaluation_submit.php', {
      method: 'POST',
      headers: { 'X-CSRF-Token': csrfToken },
      body: formData
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        showToast('Evaluation submitted!', 'success');
        closeEvalModal();
        setTimeout(() => location.reload(), 1500);
      } else {
        showToast(data.error || 'Failed to submit', 'error');
      }
    })
    .catch(() => showToast('An error occurred', 'error'));
  }
  </script>
</body>
</html>