<?php
session_start();
require_once __DIR__ . '/config.php';
$user = requireAuth();
if (($user['role'] ?? '') !== 'admin') {
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

// Handle actions
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRF($_POST['csrf_token'] ?? '')) {
        $message = 'Invalid token';
    } else {
        switch ($_POST['action']) {
            case 'add_internship':
                $stmt = $db->prepare("INSERT INTO internships (student_id, company_id, title, description, start_date, end_date, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'applied', NOW())");
                $stmt->execute([
                    $_POST['student_id'],
                    $_POST['company_id'],
                    $_POST['title'],
                    $_POST['description'] ?? '',
                    $_POST['start_date'],
                    $_POST['end_date']
                ]);
                $message = 'Internship added successfully';
                break;
            case 'update_internship':
                $stmt = $db->prepare("UPDATE internships SET student_id=?, company_id=?, title=?, description=?, start_date=?, end_date=?, status=? WHERE id=?");
                $stmt->execute([
                    $_POST['student_id'],
                    $_POST['company_id'],
                    $_POST['title'],
                    $_POST['description'] ?? '',
                    $_POST['start_date'],
                    $_POST['end_date'],
                    $_POST['status'],
                    $_POST['id']
                ]);
                $message = 'Internship updated successfully';
                break;
            case 'delete_internship':
                $stmt = $db->prepare("DELETE FROM internships WHERE id=?");
                $stmt->execute([$_POST['id']]);
                $message = 'Internship deleted successfully';
                break;
            case 'bulk_delete':
                $ids = $_POST['ids'] ?? [];
                if ($ids) {
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $stmt = $db->prepare("DELETE FROM internships WHERE id IN ($placeholders)");
                    $stmt->execute($ids);
                    $message = count($ids) . ' internship(s) deleted';
                }
                break;
        }
    }
}

$internships = $db->query("
    SELECT i.*, u.full_name as student_name, c.name as company_name
    FROM internships i
    LEFT JOIN users u ON i.student_id = u.id
    LEFT JOIN companies c ON i.company_id = c.id
    ORDER BY i.created_at DESC
")->fetchAll();

// Get stats
$statusCounts = [
    'applied' => 0,
    'interview' => 0,
    'accepted' => 0,
    'ongoing' => 0,
    'completed' => 0,
    'rejected' => 0,
    'withdrawn' => 0
];
foreach ($internships as $i) {
    $status = $i['status'] ?? 'applied';
    if (isset($statusCounts[$status])) $statusCounts[$status]++;
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Internships</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
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
      --shadow-soft: 0 4px 24px rgba(0,0,0,0.4);
      --radius-sm: 8px;
      --radius-md: 12px;
      --radius-lg: 16px;
      --transition: 200ms cubic-bezier(.4,0,.2,1);
    }
    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; }
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.5; }

    .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }

    .sidebar { background: var(--bg-charcoal); border-right: 1px solid var(--border-subtle); padding: 1.25rem 1rem; display: flex; flex-direction: column; position: sticky; top: 0; height: 100vh; overflow-y: auto; }
    .sidebar-logo { display: flex; align-items: center; gap: 0.75rem; padding: 0 0.75rem 1.25rem; border-bottom: 1px solid var(--border-subtle); margin-bottom: 1.25rem; }
    .logo-icon { width: 38px; height: 38px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.1rem; box-shadow: 0 0 20px rgba(34,197,94,0.25); }
    .logo-text { font-size: 1.25rem; font-weight: 800; background: linear-gradient(135deg, var(--text-primary), var(--green-glow)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; background-clip: text; }

    .nav-section { margin-bottom: 1.5rem; }
    .nav-label { font-size: 0.65rem; font-weight: 700; letter-spacing: 0.12em; text-transform: uppercase; color: var(--text-muted); padding: 0 0.75rem; margin-bottom: 0.5rem; }
    .nav-menu { display: flex; flex-direction: column; gap: 0.25rem; }
    .nav-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); color: var(--text-secondary); font-size: 0.85rem; font-weight: 500; cursor: pointer; transition: all var(--transition); border: none; background: transparent; width: 100%; text-align: left; text-decoration: none; }
    .nav-item:hover { background: var(--bg-card); color: var(--text-primary); }
    .nav-item.active { background: rgba(34,197,94,0.12); color: var(--green-neon); box-shadow: inset 0 0 0 1px rgba(34,197,94,0.3); }
    .nav-item .icon { font-size: 1rem; width: 20px; text-align: center; }

    .sidebar-footer { margin-top: auto; padding-top: 1rem; border-top: 1px solid var(--border-subtle); }
    .user-chip { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem; background: var(--bg-card); border-radius: var(--radius-md); border: 1px solid var(--border-subtle); }
    .user-avatar { width: 34px; height: 34px; background: linear-gradient(135deg, var(--green-emerald), var(--green-neon)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; color: var(--bg-deep); }
    .user-info { flex: 1; min-width: 0; }
    .user-name { font-size: 0.85rem; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .user-role { font-size: 0.7rem; color: var(--text-muted); }
    .logout-btn { display: flex; align-items: center; gap: 0.75rem; padding: 0.625rem 0.75rem; border-radius: var(--radius-md); color: var(--text-muted); font-size: 0.85rem; cursor: pointer; transition: all var(--transition); border: 1px solid var(--border-subtle); background: transparent; width: 100%; margin-top: 0.5rem; }
    .logout-btn:hover { border-color: rgba(239,68,68,0.4); color: #F87171; background: rgba(239,68,68,0.08); }

    .main-content { background: var(--bg-deep); padding: 1.5rem 2rem; overflow-y: auto; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem; padding-bottom: 1.25rem; border-bottom: 1px solid var(--border-subtle); }
    .page-title { font-size: 1.6rem; font-weight: 700; }
    .page-title span { color: var(--green-neon); }
    .page-subtitle { font-size: 0.85rem; color: var(--text-muted); margin-top: 0.25rem; }
    .header-actions { display: flex; gap: 0.5rem; }

    .btn { display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; padding: 0.5rem 1rem; border-radius: var(--radius-md); font-size: 0.8rem; font-weight: 600; cursor: pointer; transition: all var(--transition); border: none; text-decoration: none; }
    .btn-primary { background: var(--green-neon); color: var(--bg-deep); }
    .btn-primary:hover { background: var(--green-glow); box-shadow: 0 0 20px rgba(34,197,94,0.4); }
    .btn-secondary { background: var(--bg-card); color: var(--text-secondary); border: 1px solid var(--border-subtle); }
    .btn-secondary:hover { border-color: var(--green-neon); color: var(--green-neon); }

    .status-grid { display: grid; grid-template-columns: repeat(7, 1fr); gap: 1rem; margin-bottom: 2rem; }
    .status-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.25rem; text-align: center; transition: all var(--transition); cursor: pointer; position: relative; overflow: hidden; }
    .status-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3px; background: var(--status-color, #666); opacity: 0.8; }
    .status-card:hover { border-color: var(--status-color, var(--green-neon)); transform: translateY(-2px); box-shadow: 0 4px 20px rgba(0,0,0,0.3); }
    .status-card.active { border-color: var(--status-color, var(--green-neon)); background: rgba(34,197,94,0.08); box-shadow: 0 0 20px rgba(34,197,94,0.2); }
    .status-card[data-status="applied"] { --status-color: #F59E0B; }
    .status-card[data-status="interview"] { --status-color: #8B5CF6; }
    .status-card[data-status="accepted"] { --status-color: #EC4899; }
    .status-card[data-status="ongoing"] { --status-color: #06B6D4; }
    .status-card[data-status="completed"] { --status-color: #60A5FA; }
    .status-card[data-status="rejected"] { --status-color: #F87171; }
    .status-card.active[data-status="rejected"] { border-color: #F87171; background: rgba(248,113,113,0.12); box-shadow: 0 0 20px rgba(248,113,113,0.25); }
    .status-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; margin-right: 0.375rem; }
    .status-dot.applied { background: #F59E0B; }
    .status-dot.interview { background: #8B5CF6; }
    .status-dot.accepted { background: #EC4899; }
    .status-dot.ongoing { background: #06B6D4; }
    .status-dot.completed { background: #60A5FA; }
    .status-dot.rejected { background: #F87171; }
    .status-card .status-value { font-size: 1.75rem; font-weight: 700; }
    .status-card .status-label { font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; }

    .content-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); overflow: hidden; }
    .card-header { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-bottom: 1px solid var(--border-subtle); }
    .card-title { font-size: 0.95rem; font-weight: 600; }
    .search-input, .filter-select, .sort-select { padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.85rem; }
    .search-input { width: 180px; }
    .filter-select, .sort-select { width: 140px; }
    .search-input:focus, .filter-select:focus, .sort-select:focus { outline: none; border-color: var(--green-neon); }

    .stats-bar { display: flex; gap: 1.5rem; padding: 0.75rem 1.25rem; background: var(--bg-elevated); border-bottom: 1px solid var(--border-subtle); font-size: 0.8rem; }
    .stats-bar .stat-item { display: flex; align-items: center; gap: 0.5rem; }
    .stats-bar .stat-value { font-weight: 700; color: var(--text-primary); }
    .stats-bar .stat-label { color: var(--text-muted); }
    .stats-bar .stat-dot { width: 6px; height: 6px; border-radius: 50%; }

    .shortcut-hint { font-size: 0.7rem; color: var(--text-muted); margin-left: 0.5rem; }
    .shortcut-hint kbd { padding: 0.125rem 0.375rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: 3px; font-family: inherit; }

    .data-table { width: 100%; border-collapse: collapse; }
    .data-table th { text-align: left; padding: 0.75rem 1rem; font-size: 0.7rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; background: var(--bg-elevated); border-bottom: 1px solid var(--border-subtle); cursor: pointer; user-select: none; }
    .data-table th:hover { color: var(--text-primary); }
    .data-table th.sorted { color: var(--green-neon); }
    .data-table th .sort-icon { margin-left: 0.25rem; opacity: 0.5; }
    .data-table th.sorted .sort-icon { opacity: 1; }
    .data-table td { padding: 0.875rem 1rem; font-size: 0.85rem; color: var(--text-secondary); border-bottom: 1px solid var(--border-subtle); }
    .data-table tr:last-child td { border-bottom: none; }
    .data-table tr { transition: all var(--transition); cursor: pointer; }
    .data-table tr:hover td { background: var(--bg-elevated); }
    .data-table tr.row-selected:hover td { background: rgba(34,197,94,0.2); }
    .data-table tr.row-selected td { background: rgba(34,197,94,0.15); border-color: rgba(34,197,94,0.3); }
    .data-table tr.row-selected { outline: 1px solid var(--green-neon); outline-offset: -1px; }

    
    .status-badge { display: inline-flex; padding: 0.2rem 0.5rem; border-radius: 999px; font-size: 0.7rem; font-weight: 600; text-transform: capitalize; }
    .status-badge.applied { background: rgba(245,158,11,0.15); color: #F59E0B; }
    .status-badge.interview { background: rgba(139,92,246,0.15); color: #8B5CF6; }
    .status-badge.accepted { background: rgba(236,72,153,0.15); color: #EC4899; }
    .status-badge.ongoing { background: rgba(6,182,212,0.15); color: #06B6D4; }
    .status-badge.completed { background: rgba(96,165,250,0.15); color: #60A5FA; }
    .status-badge.rejected { background: rgba(239,68,68,0.15); color: #F87171; }
    .status-badge.withdrawn { background: rgba(107,114,128,0.15); color: #6B7280; }

    .action-btn { padding: 0.375rem 0.625rem; font-size: 0.75rem; border-radius: var(--radius-sm); margin-right: 0.25rem; }
    .action-btn.danger:hover { border-color: #F87171; color: #F87171; }
    .empty-message { padding: 2.5rem; text-align: center; color: var(--text-muted); }

    /* Action buttons container */
    .actions-cell { display: flex; gap: 0.25rem; flex-wrap: wrap; }

    .action-btn { color: #ccc; border: 1px solid #333; background: #000; }
    .action-btn:hover { background: #111; border-color: #444; color: #fff; }
    .actions-cell .action-btn:hover { background: rgba(34,197,94,0.15); border-color: var(--green-neon); color: var(--green-neon); }
    .action-btn.danger:hover { background: #200; border-color: #f87171; color: #f87171; }
    .action-btn.view-btn { background: transparent; border-color: var(--green-neon); color: var(--green-neon); }
    .action-btn.view-btn:hover { background: var(--green-neon); color: #000; }
    .action-btn.delete { border-color: #F87171; color: #F87171; }
    .action-btn.delete:hover { background: #F87171; color: #000; }

    .pagination { display: flex; justify-content: space-between; align-items: center; padding: 1rem 1.25rem; border-top: 1px solid var(--border-subtle); }
    .pagination-info { font-size: 0.85rem; color: var(--text-muted); }
    .pagination-buttons { display: flex; gap: 0.5rem; }
    .page-btn { padding: 0.375rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-sm); color: var(--text-secondary); font-size: 0.8rem; cursor: pointer; }
    .page-btn:hover { border-color: var(--green-neon); color: var(--green-neon); }
    .page-btn:disabled { opacity: 0.5; cursor: not-allowed; }

    .export-btn { background: var(--bg-elevated); border: 1px solid var(--border-subtle); }

    .modal { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.85); z-index: 9999; align-items: center; justify-content: center; }
    .modal.show { display: flex; }
    .modal-content { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 1.5rem; width: 100%; max-width: 500px; max-height: 90vh; overflow-y: auto; }
    .modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem; }
    .modal-title { font-size: 1.1rem; font-weight: 700; }
    .modal-close { background: none; border: none; color: var(--text-muted); font-size: 1.25rem; cursor: pointer; padding: 0.25rem; }
    .form-group { margin-bottom: 1rem; }
    .form-label { display: block; font-size: 0.8rem; color: var(--text-secondary); margin-bottom: 0.375rem; }
    .form-control { display: block; width: 100%; padding: 0.5rem 0.75rem; background: var(--bg-elevated); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); color: var(--text-primary); font-size: 0.85rem; }
    .form-control:focus { outline: none; border-color: var(--green-neon); }
    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; }
    .form-actions { display: flex; gap: 0.5rem; margin-top: 1rem; }

    .toast-container { position: fixed; top: 1.25rem; right: 1.25rem; z-index: 9999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { display: flex; align-items: center; gap: 0.5rem; padding: 0.75rem 1rem; background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); box-shadow: var(--shadow-soft); animation: slideIn 0.3s ease; font-size: 0.85rem; }
    .toast.success { border-color: var(--green-neon); }
    .toast.error { border-color: #F87171; }
    @keyframes slideIn { from { transform: translateX(100%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

    @media (max-width: 1100px) { .status-grid { grid-template-columns: repeat(4, 1fr); } }
    @media (max-width: 900px) {
      .admin-layout { grid-template-columns: 1fr; }
      .sidebar { display: none; }
      .main-content { padding: 1rem; }
      .status-grid { grid-template-columns: repeat(3, 1fr); }
    }
  </style>
</head>
<body>
<div id="toast-container" class="toast-container"></div>

<div id="view-modal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title">View Internship</h3>
      <button class="modal-close" onclick="closeViewModal()">&times;</button>
    </div>
    <div id="view-content" style="color: var(--text-secondary); font-size: 0.9rem; line-height: 1.8;">
    </div>
    <div class="form-actions">
      <button type="button" class="btn btn-primary" onclick="closeViewModal()">Close</button>
    </div>
  </div>
</div>

<div id="status-modal" class="modal">
  <div class="modal-content" style="max-width: 380px;">
    <div class="modal-header">
      <h3 class="modal-title">Update Status</h3>
      <button class="modal-close" onclick="closeStatusModal()">&times;</button>
    </div>
    <form id="status-form">
      <input type="hidden" name="id" id="status-internship-id" value="">
      <div class="form-group">
        <label class="form-label">Select Status</label>
        <select name="status" class="form-control" id="new-status" required>
          <option value="applied">Applied</option>
          <option value="interview">Interview</option>
          <option value="accepted">Accepted</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
          <option value="rejected">Rejected</option>
          <option value="withdrawn">Withdrawn</option>
        </select>
      </div>
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="closeStatusModal()">Cancel</button>
        <button type="submit" class="btn btn-primary">Update</button>
      </div>
    </form>
  </div>
</div>

<div id="modal" class="modal">
  <div class="modal-content">
    <div class="modal-header">
      <h3 class="modal-title" id="modal-title">Add Internship</h3>
      <button class="modal-close" onclick="closeModal()">&times;</button>
    </div>
    <form id="modal-form">
      <input type="hidden" name="id" id="internship-id" value="">
      <div class="form-group">
        <label class="form-label">Student</label>
        <select name="student_id" class="form-control" id="student-select" required>
          <option value="">Select student</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">Company</label>
        <select name="company_id" class="form-control" id="company-select" required>
          <option value="">Select company</option>
        </select>
      </div>
      <div class="form-group"><label class="form-label">Title</label><input type="text" name="title" class="form-control" required></div>
      <div class="form-group"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="3"></textarea></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Start Date</label><input type="date" name="start_date" class="form-control" required></div>
        <div class="form-group"><label class="form-label">End Date</label><input type="date" name="end_date" class="form-control" required></div>
      </div>
      <div class="form-group" id="status-group" style="display:none">
        <label class="form-label">Status</label>
        <select name="status" class="form-control" id="status-select">
          <option value="applied">Applied</option>
          <option value="interview">Interview</option>
          <option value="accepted">Accepted</option>
          <option value="ongoing">Ongoing</option>
          <option value="completed">Completed</option>
          <option value="rejected">Rejected</option>
          <option value="withdrawn">Withdrawn</option>
        </select>
      </div>
      <div class="form-actions">
        <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
        <button type="submit" class="btn btn-primary" id="save-btn">Save</button>
      </div>
    </form>
  </div>
</div>

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
        <a href="admin_internships.php" class="nav-item active"><span class="icon">💼</span> Internships</a>
        <a href="admin_applications.php" class="nav-item"><span class="icon">📝</span> Applications</a>
        <a href="admin_reports.php" class="nav-item"><span class="icon">📈</span> Reports</a>
      </nav>
    </div>

    <div class="nav-section">
      <div class="nav-label">System</div>
      <nav class="nav-menu">
        <a href="#" class="nav-item"><span class="icon">⚙</span> Settings</a>
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
      <button class="logout-btn" onclick="handleLogout()"><span class="icon">⏻</span> Logout</button>
    </div>
  </aside>

  <main class="main-content">
    <div class="page-header">
      <div>
        <h1 class="page-title">Internships <span>Management</span></h1>
        <p class="page-subtitle">Track and manage all internship positions</p>
      </div>
      <div class="header-actions">
        <button class="btn btn-secondary export-btn" onclick="exportCSV()">⬇ Export CSV</button>
        <button class="btn btn-primary" onclick="openModal()">+ Add Internship</button>
      </div>
    </div>

    <!-- Status Filter -->
    <div class="status-grid">
      <div class="status-card active" data-status="" onclick="filterByStatus('')">
        <div class="status-value"><?= count($internships) ?></div>
        <div class="status-label">All</div>
      </div>
      <div class="status-card" data-status="applied" onclick="filterByStatus('applied')">
        <div class="status-value"><?= $statusCounts['applied'] ?></div>
        <div class="status-label">Applied</div>
      </div>
      <div class="status-card" data-status="interview" onclick="filterByStatus('interview')">
        <div class="status-value"><?= $statusCounts['interview'] ?></div>
        <div class="status-label">Interview</div>
      </div>
      <div class="status-card" data-status="accepted" onclick="filterByStatus('accepted')">
        <div class="status-value" style="color:#EC4899"><?= $statusCounts['accepted'] ?></div>
        <div class="status-label">Accepted</div>
      </div>
      <div class="status-card" data-status="ongoing" onclick="filterByStatus('ongoing')">
        <div class="status-value" style="color:#06B6D4"><?= $statusCounts['ongoing'] ?></div>
        <div class="status-label">Ongoing</div>
      </div>
      <div class="status-card" data-status="completed" onclick="filterByStatus('completed')">
        <div class="status-value" style="color:#60A5FA"><?= $statusCounts['completed'] ?></div>
        <div class="status-label">Completed</div>
      </div>
      <div class="status-card" data-status="rejected" onclick="filterByStatus('rejected')">
        <div class="status-value" style="color:#F87171"><?= $statusCounts['rejected'] ?></div>
        <div class="status-label">Rejected</div>
      </div>
    </div>

    <div class="content-card">
      <div class="card-header">
        <h3 class="card-title">All Internships (<?= count($internships) ?>)</h3>
        <div class="stats-bar">
          <div class="stat-item"><span class="stat-dot" style="background:var(--green-neon)"></span><span class="stat-value" id="stat-active">0</span><span class="stat-label">Active</span></div>
          <div class="stat-item"><span class="stat-dot" style="background:#60A5FA"></span><span class="stat-value" id="stat-completed">0</span><span class="stat-label">Completed</span></div>
          <div class="stat-item"><span class="stat-dot" style="background:#F59E0B"></span><span class="stat-value" id="stat-pending">0</span><span class="stat-label">Pending</span></div>
        </div>
        <div style="display:flex;gap:0.5rem">
          <input type="text" class="search-input" placeholder="Search..." onkeyup="filterTable(this.value)">
          <select class="filter-select" onchange="filterByStatus(this.value)">
            <option value="">All Status</option>
            <option value="applied">Applied</option>
            <option value="interview">Interview</option>
            <option value="accepted">Accepted</option>
            <option value="ongoing">Ongoing</option>
            <option value="completed">Completed</option>
            <option value="rejected">Rejected</option>
          </select>
        </div>
      </div>
      <table class="data-table">
        <thead>
          <tr>
            <th onclick="sortTable('id')">ID <span class="sort-icon">↕</span></th>
            <th onclick="sortTable('student_name')">Student <span class="sort-icon">↕</span></th>
            <th onclick="sortTable('company_name')">Company <span class="sort-icon">↕</span></th>
            <th onclick="sortTable('title')">Title <span class="sort-icon">↕</span></th>
            <th onclick="sortTable('status')">Status <span class="sort-icon">↕</span></th>
            <th onclick="sortTable('start_date')">Duration <span class="sort-icon">↕</span></th>
            <th onclick="sortTable('start_date')">Dates <span class="sort-icon">↕</span></th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody id="internships-tbody">
          <?php
          $internshipsJson = json_encode(array_map(function($i) {
              $duration = '-';
              if (!empty($i['start_date']) && !empty($i['end_date'])) {
                  $start = strtotime($i['start_date']);
                  $end = strtotime($i['end_date']);
                  $months = round(($end - $start) / (30*86400), 1);
                  $duration = $months > 0 ? $months . ' mo' : '-';
              }
              return array_merge($i, ['duration' => $duration]);
          }, $internships));
          ?>
          <?php if($internships): foreach($internships as $i): ?>
          <tr data-status="<?= e($i['status']) ?>" data-id="<?= $i['id'] ?>" onclick="selectRow(this)">
            <td><?= $i['id'] ?></td>
            <td><?= e($i['student_name'] ?? '-') ?></td>
            <td><?= e($i['company_name'] ?? '-') ?></td>
            <td><?= e($i['title']) ?></td>
            <td><span class="status-badge <?= e($i['status'] ?? 'applied') ?>"><?= e($i['status'] ?? 'applied') ?></span></td>
            <td><?php
              $duration = '-';
              if (!empty($i['start_date']) && !empty($i['end_date'])) {
                  $start = strtotime($i['start_date']);
                  $end = strtotime($i['end_date']);
                  $months = round(($end - $start) / (30*86400), 1);
                  $duration = $months > 0 ? $months . ' mo' : '-';
              }
              echo $duration;
            ?></td>
            <td><?= e($i['start_date'] ?? '-') ?> - <?= e($i['end_date'] ?? '-') ?></td>
            <td class="actions-cell" onclick="event.stopPropagation()">
              <button class="btn btn-secondary action-btn view-btn" onclick="viewInternship(<?= $i['id'] ?>)">View</button>
              <button class="btn btn-secondary action-btn view-btn" onclick="editInternship(<?= $i['id'] ?>)">Edit</button>
              <button class="btn btn-secondary action-btn view-btn delete" onclick="deleteInternship(<?= $i['id'] ?>)">Delete</button>
            </td>
          </tr>
          <?php endforeach; else: ?>
          <tr><td colspan="8" class="empty-message">No internships found</td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </main>
</div>

<script>
const App = {
  csrfToken: '<?= $csrf ?>',
  internships: <?= $internshipsJson ?: '[]' ?>
};

function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const el = document.createElement('div');
  el.className = 'toast ' + type;
  el.innerHTML = '<span>' + msg + '</span>';
  c.appendChild(el);
  setTimeout(() => el.remove(), 4000);
}

function openModal(isEdit = false) {
  document.getElementById('modal').classList.add('show');
  document.getElementById('modal-title').textContent = isEdit ? 'Edit Internship' : 'Add Internship';
  document.getElementById('status-group').style.display = isEdit ? '' : 'none';
  document.getElementById('save-btn').textContent = isEdit ? 'Update' : 'Save';
  if (!isEdit) {
    document.getElementById('modal-form').reset();
    document.getElementById('internship-id').value = '';
  }
  loadDropdowns();
}

function closeModal() {
  document.getElementById('modal').classList.remove('show');
}

function updateStatusModal(id, currentStatus) {
  document.getElementById('status-internship-id').value = id;
  document.getElementById('new-status').value = currentStatus || 'applied';
  document.getElementById('status-modal').classList.add('show');
}

function closeStatusModal() {
  document.getElementById('status-modal').classList.remove('show');
}

async function loadDropdowns() {
  try {
    const [studentsRes, companiesRes] = await Promise.all([
      fetch('admin.php?action=list_students'),
      fetch('admin.php?action=list_companies')
    ]);
    const studentsData = await studentsRes.json();
    const companiesData = await companiesRes.json();

    const studentSelect = document.getElementById('student-select');
    const companySelect = document.getElementById('company-select');

    if (studentsData.success) {
      studentSelect.innerHTML = '<option value="">Select student</option>' +
        studentsData.students.map(s => `<option value="${s.id}">${s.full_name}</option>`).join('');
    }
    if (companiesData.success) {
      companySelect.innerHTML = '<option value="">Select company</option>' +
        companiesData.companies.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    }
  } catch(e) { console.error(e); }
}

function filterTable(query) {
  const tbody = document.getElementById('internships-tbody');
  const rows = tbody.querySelectorAll('tr[data-id]');
  query = query.toLowerCase();
  rows.forEach(row => {
    const text = row.textContent.toLowerCase();
    row.style.display = text.includes(query) ? '' : 'none';
  });
}

function filterByStatus(status) {
  const tbody = document.getElementById('internships-tbody');
  const rows = tbody.querySelectorAll('tr[data-id]');
  const cards = document.querySelectorAll('.status-card');
  cards.forEach(c => {
    const cardStatus = c.getAttribute('data-status');
    // Only the clicked card should glow
    c.classList.toggle('active', cardStatus === status || (status === '' && cardStatus === ''));
  });
  rows.forEach(row => {
    if (!status) {
      row.style.display = '';
    } else {
      const rowStatus = row.getAttribute('data-status');
      row.style.display = rowStatus === status ? '' : 'none';
    }
  });
  // Clear selection when filter changes (keep selection only on 'All')
  if (status) {
    document.querySelectorAll('#internships-tbody tr').forEach(r => r.classList.remove('row-selected'));
  }
  updateStats();
}

// Sorting
let sortCol = 'id';
let sortDir = 'desc';

function sortTable(col) {
  const th = event.target.closest('th');
  document.querySelectorAll('.data-table th').forEach(t => t.classList.remove('sorted'));
  if (sortCol === col) {
    sortDir = sortDir === 'asc' ? 'desc' : 'asc';
  } else {
    sortCol = col;
    sortDir = 'asc';
  }
  th.classList.add('sorted');
  th.querySelector('.sort-icon').textContent = sortDir === 'asc' ? '↑' : '↓';

  const rows = Array.from(document.querySelectorAll('#internships-tbody tr[data-id]'));
  rows.sort((a, b) => {
    let aVal = a.querySelector(`td:nth-child(${getColIndex(sortCol)})`)?.textContent || '';
    let bVal = b.querySelector(`td:nth-child(${getColIndex(sortCol)})`)?.textContent || '';
    if (sortDir === 'asc') return aVal.localeCompare(bVal);
    return bVal.localeCompare(aVal);
  });
  const tbody = document.getElementById('internships-tbody');
  rows.forEach(r => tbody.appendChild(r));
}

function getColIndex(col) {
  const map = { id: 1, student_name: 2, company_name: 3, title: 4, status: 5, duration: 6, start_date: 7 };
  return map[col] || 1;
}

// Update stats bar
function updateStats() {
  const rows = document.querySelectorAll('#internships-tbody tr[data-id]');
  let active = 0, completed = 0, pending = 0;
  rows.forEach(row => {
    if (row.style.display === 'none') return;
    const status = row.getAttribute('data-status');
    if (status === 'ongoing' || status === 'accepted') active++;
    else if (status === 'completed') completed++;
    else pending++;
  });
  document.getElementById('stat-active').textContent = active;
  document.getElementById('stat-completed').textContent = completed;
  document.getElementById('stat-pending').textContent = pending;
}

// Initialize stats on load
document.addEventListener('DOMContentLoaded', updateStats);

// Single row selection
function selectRow(row) {
  // If clicking the already selected row, deselect it
  if (row.classList.contains('row-selected')) {
    row.classList.remove('row-selected');
  } else {
    // Remove previous selection from all data rows only
    document.querySelectorAll('#internships-tbody tr').forEach(r => r.classList.remove('row-selected'));
    // Add selection to clicked row
    row.classList.add('row-selected');
  }
}

function viewInternship(id) {
  const internship = App.internships.find(i => i.id == id);
  if (!internship) return;
  const details = `
    <div style="margin-bottom: 1rem;">
      <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">Student</div>
      <div style="color: var(--text-primary); font-weight: 500;">${internship.student_name || '-'}</div>
    </div>
    <div style="margin-bottom: 1rem;">
      <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">Company</div>
      <div style="color: var(--text-primary); font-weight: 500;">${internship.company_name || '-'}</div>
    </div>
    <div style="margin-bottom: 1rem;">
      <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">Title</div>
      <div style="color: var(--text-primary); font-weight: 500;">${internship.title || '-'}</div>
    </div>
    <div style="margin-bottom: 1rem;">
      <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">Description</div>
      <div style="color: var(--text-primary);">${internship.description || 'N/A'}</div>
    </div>
    <div style="margin-bottom: 1rem;">
      <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">Status</div>
      <div><span class="status-badge ${internship.status || 'applied'}">${internship.status || 'applied'}</span></div>
    </div>
    <div style="margin-bottom: 1rem;">
      <div style="color: var(--text-muted); font-size: 0.75rem; text-transform: uppercase; margin-bottom: 0.25rem;">Duration</div>
      <div style="color: var(--text-primary);">${internship.start_date || '-'} to ${internship.end_date || '-'}</div>
    </div>
  `.trim();
  document.getElementById('view-content').innerHTML = details;
  document.getElementById('view-modal').classList.add('show');
}

function closeViewModal() {
  document.getElementById('view-modal').classList.remove('show');
}

function editInternship(id) {
  const internship = App.internships.find(i => i.id == id);
  if (!internship) return;
  document.getElementById('internship-id').value = internship.id;
  document.querySelector('input[name="title"]').value = internship.title || '';
  document.querySelector('textarea[name="description"]').value = internship.description || '';
  document.querySelector('input[name="start_date"]').value = internship.start_date || '';
  document.querySelector('input[name="end_date"]').value = internship.end_date || '';
  document.getElementById('status-select').value = internship.status || 'applied';
  loadDropdowns().then(() => {
    document.getElementById('student-select').value = internship.student_id || '';
    document.getElementById('company-select').value = internship.company_id || '';
  });
  openModal(true);
}

function deleteInternship(id) {
  if (!confirm('Delete this internship?')) return;
  const fd = new FormData();
  fd.append('action', 'delete_internship');
  fd.append('id', id);
  fd.append('csrf_token', App.csrfToken);
  fetch('admin_internships.php', { method: 'POST', body: fd })
    .then(() => { toast('Deleted', 'success'); setTimeout(() => location.reload(), 500); });
}

document.getElementById('modal-form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const id = document.getElementById('internship-id').value;
  fd.append('action', id ? 'update_internship' : 'add_internship');
  fd.append('csrf_token', App.csrfToken);
  if (id) fd.append('id', id);

  try {
    await fetch('admin_internships.php', { method: 'POST', body: fd });
    toast(id ? 'Updated' : 'Added', 'success');
    closeModal();
    setTimeout(() => location.reload(), 500);
  } catch(err) {
    toast('Error: ' + err.message, 'error');
  }
});

document.getElementById('status-form').addEventListener('submit', async e => {
  e.preventDefault();
  const fd = new FormData();
  fd.append('action', 'update_internship');
  fd.append('id', document.getElementById('status-internship-id').value);
  fd.append('status', document.getElementById('new-status').value);
  fd.append('csrf_token', App.csrfToken);

  // Also update other fields from hidden inputs
  const internship = App.internships.find(i => i.id == document.getElementById('status-internship-id').value);
  if (internship) {
    fd.append('student_id', internship.student_id);
    fd.append('company_id', internship.company_id);
    fd.append('title', internship.title);
    fd.append('description', internship.description || '');
    fd.append('start_date', internship.start_date);
    fd.append('end_date', internship.end_date);
  }

  await fetch('admin_internships.php', { method: 'POST', body: fd });
  toast('Status updated', 'success');
  closeStatusModal();
  setTimeout(() => location.reload(), 500);
});

function exportCSV() {
  const rows = document.querySelectorAll('#internships-tbody tr[data-id]');
  let csv = 'ID,Student,Company,Title,Status,Start Date,End Date\n';
  rows.forEach(row => {
    if (row.style.display === 'none') return;
    const cols = row.querySelectorAll('td');
    csv += Array.from(cols).slice(0, 7).map((c, i) => {
      let txt = c.textContent.trim().replace(/"/g, '""');
      return i === 4 ? txt : '"' + txt + '"';
    }).join(',') + '\n';
  });
  const blob = new Blob([csv], { type: 'text/csv' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = 'internships_' + new Date().toISOString().split('T')[0] + '.csv';
  a.click();
  URL.revokeObjectURL(url);
}

async function handleLogout() {
  await fetch('auth.php', { method: 'POST', body: new URLSearchParams({ action: 'logout' }) });
  window.location.href = 'admin_login.php';
}
</script>
</body>
</html>