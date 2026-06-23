<?php
session_start();
require_once 'php/config.php';
$user = requireAuth();
$csrf = generateCSRF();
$db = Database::getConnection();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    echo "Invalid internship ID.";
    exit;
}

// Get internship details
$stmt = $db->prepare("
    SELECT i.*, c.name AS company_name, c.industry, c.website, c.location AS company_location,
           c.contact_person, c.contact_email,
           u.full_name AS student_name, u.email AS student_email
    FROM internships i
    JOIN companies c ON i.company_id = c.id
    JOIN users u ON i.student_id = u.id
    WHERE i.id = ? AND i.student_id = ?
");
$stmt->execute([$id, $user['id']]);
$internship = $stmt->fetch();

if (!$internship) {
    // Check if admin
    if ($user['role'] === 'admin') {
        $stmt = $db->prepare("
            SELECT i.*, c.name AS company_name, c.industry, c.website, c.location AS company_location,
                   c.contact_person, c.contact_email,
                   u.full_name AS student_name, u.email AS student_email
            FROM internships i
            JOIN companies c ON i.company_id = c.id
            JOIN users u ON i.student_id = u.id
            WHERE i.id = ?
        ");
        $stmt->execute([$id]);
        $internship = $stmt->fetch();
    }

    if (!$internship) {
        echo "Internship not found.";
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>InternTrack — <?= e($internship['title']) ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
    body { font-family: 'Inter', system-ui, sans-serif; background: var(--bg-deep); color: var(--text-primary); min-height: 100vh; line-height: 1.55; padding: 2rem; }

    .back-btn { display: inline-flex; align-items: center; gap: 0.5rem; color: var(--text-secondary); font-size: 0.9rem; margin-bottom: 1.5rem; cursor: pointer; }
    .back-btn:hover { color: var(--green-neon); }

    .detail-card { background: var(--bg-card); border: 1px solid var(--border-subtle); border-radius: var(--radius-lg); padding: 2rem; max-width: 800px; margin: 0 auto; }

    .detail-header { margin-bottom: 2rem; padding-bottom: 1rem; border-bottom: 1px solid var(--border-subtle); }
    .detail-title { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
    .detail-company { color: var(--green-neon); font-size: 1.1rem; }

    .detail-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 1.5rem; }
    .detail-item { }
    .detail-label { font-size: 0.75rem; font-weight: 600; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 0.25rem; }
    .detail-value { font-size: 1rem; color: var(--text-primary); }

    .status-badge { display: inline-flex; padding: 0.3rem 0.75rem; border-radius: var(--radius-sm); font-size: 0.7rem; font-weight: 700; text-transform: uppercase; }
    .status-badge.applied { background: rgba(59,130,246,0.15); color: #60A5FA; }
    .status-badge.interview { background: rgba(168,85,247,0.15); color: #C084FC; }
    .status-badge.accepted { background: rgba(34,197,94,0.15); color: var(--green-neon); }

    .documents-section { margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-subtle); }
    .doc-title { font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: var(--green-neon); }
    .doc-list { display: flex; flex-direction: column; gap: 0.75rem; }
    .doc-item { display: flex; align-items: center; gap: 0.75rem; padding: 0.75rem 1rem; background: var(--bg-panel); border: 1px solid var(--border-subtle); border-radius: var(--radius-md); }
    .doc-icon { font-size: 1.25rem; }
    .doc-name { flex: 1; }
    .doc-link { color: var(--green-neon); text-decoration: none; font-size: 0.9rem; }
    .doc-link:hover { text-decoration: underline; }
    .no-doc { color: var(--text-muted); font-size: 0.9rem; }
  </style>
</head>
<body>
  <a class="back-btn" href="internships.php">← Back to Internships</a>

  <div class="detail-card">
    <div class="detail-header">
      <h1 class="detail-title"><?= e($internship['title']) ?></h1>
      <div class="detail-company">🏢 <?= e($internship['company_name']) ?></div>
    </div>

    <div class="detail-grid">
      <div class="detail-item">
        <div class="detail-label">Status</div>
        <div class="detail-value"><span class="status-badge <?= $internship['status'] ?>"><?= e($internship['status']) ?></span></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Work Mode</div>
        <div class="detail-value"><?= e($internship['work_mode']) ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Start Date</div>
        <div class="detail-value"><?= e($internship['start_date']) ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">End Date</div>
        <div class="detail-value"><?= e($internship['end_date']) ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Stipend</div>
        <div class="detail-value"><?= $internship['stipend'] ? 'Rs. ' . number_format($internship['stipend'], 2) : 'Not specified' ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Industry</div>
        <div class="detail-value"><?= e($internship['industry'] ?? '-') ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Location</div>
        <div class="detail-value"><?= e($internship['company_location'] ?? '-') ?></div>
      </div>
      <div class="detail-item">
        <div class="detail-label">Company Website</div>
        <div class="detail-value"><?= $internship['website'] ? '<a href="' . e($internship['website']) . '" target="_blank" class="doc-link">' . e($internship['website']) . '</a>' : '-' ?></div>
      </div>
    </div>

    <?php if ($internship['description']): ?>
    <div class="detail-item" style="margin-top: 1.5rem;">
      <div class="detail-label">Description</div>
      <div class="detail-value"><?= e($internship['description']) ?></div>
    </div>
    <?php endif; ?>

    <div class="documents-section">
      <div class="doc-title">📎 Submitted Documents</div>
      <div class="doc-list">
        <?php if ($internship['resume_path']): ?>
        <div class="doc-item">
          <span class="doc-icon">📄</span>
          <span class="doc-name">Resume</span>
          <a href="<?= e($internship['resume_path']) ?>" class="doc-link" target="_blank">View</a>
        </div>
        <?php else: ?>
        <div class="no-doc">No resume uploaded</div>
        <?php endif; ?>

        <?php if ($internship['cover_letter_path']): ?>
        <div class="doc-item">
          <span class="doc-icon">📝</span>
          <span class="doc-name">Cover Letter</span>
          <a href="<?= e($internship['cover_letter_path']) ?>" class="doc-link" target="_blank">View</a>
        </div>
        <?php else: ?>
        <div class="no-doc">No cover letter uploaded</div>
        <?php endif; ?>

        <?php if ($internship['transcripts_path']): ?>
        <div class="doc-item">
          <span class="doc-icon">🎓</span>
          <span class="doc-name">Academic Transcripts</span>
          <a href="<?= e($internship['transcripts_path']) ?>" class="doc-link" target="_blank">View</a>
        </div>
        <?php else: ?>
        <div class="no-doc">No transcripts uploaded</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>