<?php
session_start();
require_once __DIR__ . '/config.php';

// If already logged in as admin, redirect to admin dashboard
if (!empty($_SESSION['user']) && ($_SESSION['user']['role'] ?? '') === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
}

$csrf = generateCSRF();

// Fetch companies for dropdown
$companies = [];
try {
    $db = Database::getConnection();
    $stmt = $db->query("SELECT MIN(id) as id, name FROM companies GROUP BY name ORDER BY name");
    $companies = $stmt->fetchAll();

    // If no companies exist, create a default one
    if (empty($companies)) {
        $db->exec("INSERT INTO companies (name, industry, description) VALUES ('Default Company', 'Technology', 'Default company for admin accounts')");
        $stmt = $db->query("SELECT MIN(id) as id, name FROM companies GROUP BY name ORDER BY name");
        $companies = $stmt->fetchAll();
    }
} catch (Exception $e) {
    // Try to create companies table and a default company
    try {
        $db = Database::getConnection();
        $db->exec("CREATE TABLE IF NOT EXISTS companies (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(200) NOT NULL,
            industry VARCHAR(100),
            website VARCHAR(255),
            location VARCHAR(200),
            contact_person VARCHAR(150),
            contact_email VARCHAR(150),
            contact_phone VARCHAR(30),
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_name (name)
        ) ENGINE=InnoDB");
        $db->exec("INSERT INTO companies (name, industry, description) VALUES ('Default Company', 'Technology', 'Default company for admin accounts')");
        $stmt = $db->query("SELECT MIN(id) as id, name FROM companies GROUP BY name ORDER BY name");
        $companies = $stmt->fetchAll();
    } catch (Exception $e2) {
        error_log("Failed to create default company: " . $e2->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Admin Register</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    :root {
      --primary-green: #22C55E;
      --dark-green: #166534;
      --emerald: #10B981;
      --black: #0A0A0A;
      --dark-gray: #111111;
      --input-bg: #161616;
      --border: #2A2A2A;
      --white: #FFFFFF;
      --muted: #9CA3AF;
      --bg-deep: #050505;
      --bg-charcoal: #0A0A0A;
      --bg-card: #161616;
      --bg-elevated: #1A1A1A;
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
      --transition: 200ms cubic-bezier(.4,0,.2,1);
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: var(--bg-deep);
      color: var(--white);
      min-height: 100vh;
      line-height: 1.55;
      -webkit-font-smoothing: antialiased;
    }

    .auth-container {
      min-height: 100vh;
      display: grid;
      grid-template-columns: 420px 1fr;
    }

    /* Left Side - Branding */
    .auth-sidebar {
      background: linear-gradient(135deg, var(--bg-charcoal) 0%, #0F3D2E 50%, #16A34A 100%);
      padding: 3rem;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
      overflow: hidden;
    }

    .auth-sidebar::before {
      content: '';
      position: absolute;
      inset: 0;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%2322C55E' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      opacity: 0.5;
    }

    .sidebar-content {
      position: relative;
      z-index: 1;
    }

    .brand-label {
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.25em;
      color: var(--primary-green);
      margin-bottom: 1.5rem;
      text-transform: uppercase;
    }

    .brand-title {
      font-size: 3.5rem;
      font-weight: 900;
      line-height: 1.05;
      color: var(--white);
      margin-bottom: 1.5rem;
    }

    .brand-title span {
      background: linear-gradient(135deg, var(--primary-green), var(--emerald));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
    }

    .brand-desc {
      font-size: 1rem;
      color: var(--muted);
      line-height: 1.7;
      max-width: 320px;
    }

    .brand-cta {
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      color: var(--primary-green);
      font-weight: 600;
      font-size: 0.95rem;
      text-decoration: none;
      transition: all 0.3s ease;
      margin-top: 2rem;
    }

    .brand-cta:hover { gap: 1rem; }
    .brand-cta::after { content: '→'; font-size: 1.2rem; }

    .sidebar-footer-info {
      position: relative;
      z-index: 1;
      font-size: 0.8rem;
      color: var(--text-muted);
    }

    /* Right Side - Form */
    .auth-main {
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem;
      background: var(--bg-deep);
      position: relative;
      overflow: hidden;
    }

    .auth-main::before {
      content: '';
      position: absolute;
      inset: 0;
      background: transparent;
      pointer-events: none;
    }

    .auth-card {
      width: 100%;
      max-width: 520px;
      background: rgba(17, 17, 17, 0.85);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-lg);
      padding: 2.5rem;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
      position: relative;
      z-index: 1;
    }

    .auth-header {
      margin-bottom: 2rem;
    }

    .auth-title {
      font-size: 1.75rem;
      font-weight: 700;
      margin-bottom: 0.5rem;
    }

    .auth-subtitle {
      font-size: 0.9rem;
      color: var(--text-muted);
    }

    /* Form */
    .form-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }

    .form-group {
      margin-bottom: 1rem;
    }

    .form-group.full {
      grid-column: 1 / -1;
    }

    .form-label {
      display: block;
      font-size: 0.8rem;
      font-weight: 500;
      color: var(--text-secondary);
      margin-bottom: 0.5rem;
    }

    .form-control {
      width: 100%;
      padding: 0.875rem 1rem;
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-md);
      color: var(--white);
      font-family: inherit;
      font-size: 0.95rem;
      transition: all var(--transition);
      outline: none;
    }

    .form-control:focus {
      border-color: var(--green-neon);
      box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.15);
    }

    .form-control::placeholder {
      color: var(--text-muted);
    }

    select.form-control {
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239CA3AF'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 1.25rem;
      padding-right: 2.5rem;
      cursor: pointer;
    }

    select.form-control option {
      background: var(--bg-charcoal);
      color: var(--white);
    }

    .password-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .password-wrapper .form-control {
      padding-right: 3rem;
    }

    .password-toggle {
      position: absolute;
      right: 0.75rem;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1rem;
      padding: 0.25rem;
      color: var(--text-muted);
      transition: all 0.2s ease;
    }

    .password-toggle:hover {
      color: var(--green-neon);
    }

    /* Password Strength */
    .password-strength {
      margin-top: 0.5rem;
      display: none;
    }

    .password-strength.visible {
      display: block;
    }

    .strength-bars {
      display: flex;
      gap: 4px;
      margin-bottom: 0.375rem;
    }

    .strength-bar {
      flex: 1;
      height: 4px;
      background: var(--border-subtle);
      border-radius: 2px;
      transition: background 0.3s ease;
    }

    .strength-bar.weak { background: #EF4444; }
    .strength-bar.medium { background: #F59E0B; }
    .strength-bar.strong { background: #22C55E; }

    .strength-label {
      font-size: 0.75rem;
      color: var(--text-muted);
      display: flex;
      justify-content: space-between;
    }

    .strength-label.weak { color: #EF4444; }
    .strength-label.medium { color: #F59E0B; }
    .strength-label.strong { color: #22C55E; }

    /* Button */
    .btn-primary {
      width: 100%;
      padding: 0.875rem 1.5rem;
      background: #16A34A;
      color: var(--white);
      border: none;
      border-radius: var(--radius-md);
      font-family: inherit;
      font-size: 0.9rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 0.5rem;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 0 20px rgba(34, 197, 94, 0.4), 0 0 40px rgba(34, 197, 94, 0.2);
    }

    .btn-primary:disabled {
      background: #16A34A;
      opacity: 0.6;
      cursor: not-allowed;
      box-shadow: none;
    }

    /* Footer Link */
    .auth-footer {
      text-align: center;
      margin-top: 1.5rem;
      padding-top: 1.5rem;
      border-top: 1px solid var(--border-subtle);
    }

    .auth-footer p {
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    .auth-footer a {
      color: var(--green-neon);
      text-decoration: none;
      font-weight: 500;
    }

    .auth-footer a:hover {
      text-decoration: underline;
    }

    /* Toast */
    .toast-container {
      position: fixed;
      top: 1.5rem;
      right: 1.5rem;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
    }

    .toast {
      padding: 1rem 1.5rem;
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-md);
      box-shadow: 0 8px 30px rgba(0,0,0,0.4);
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 0.9rem;
      animation: slideIn 0.3s ease;
      min-width: 280px;
    }

    .toast.success {
      border-color: var(--green-neon);
      background: rgba(34, 197, 94, 0.1);
    }

    .toast.error {
      border-color: #EF4444;
      background: rgba(239, 68, 68, 0.1);
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateX(20px); }
      to { opacity: 1; transform: translateX(0); }
    }

    /* Responsive */
    @media (max-width: 900px) {
      .auth-container {
        grid-template-columns: 1fr;
      }

      .auth-sidebar {
        padding: 2rem;
        min-height: auto;
      }

      .brand-title {
        font-size: 2.5rem;
      }

      .form-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body>
  <div id="toast-container" class="toast-container"></div>

  <div class="auth-container">
    <!-- Left Sidebar -->
    <aside class="auth-sidebar">
      <div class="sidebar-content">
        <div>
          <p class="brand-label">INTERNSHIP PORTAL</p>
          <h1 class="brand-title">Create<br><span>Admin</span> Account</h1>
          <p class="brand-desc">Register as an administrator to manage students, companies, internships, and track all applications across the platform.</p>
          <a href="../index.php" class="brand-cta">Back to Home</a>
        </div>
      </div>
      </aside>

    <!-- Main Form -->
    <main class="auth-main">
      <div class="auth-card">
        <div class="auth-header">
          <h2 class="auth-title">Admin Registration</h2>
          <p class="auth-subtitle">Fill in your details to create an admin account</p>
        </div>

        <form onsubmit="handleRegister(event)" data-on-success="redirect:admin_login.php">
          <input type="hidden" name="role_hint" value="admin">
          <input type="hidden" name="role" value="admin">

          <div class="form-grid">
            <div class="form-group full">
              <label class="form-label">Full Name</label>
              <input type="text" name="full_name" class="form-control" placeholder="Enter your full name" required autocomplete="name">
            </div>

            <div class="form-group full">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-control" placeholder="admin@company.com" required autocomplete="email">
            </div>

            <div class="form-group full">
              <label class="form-label">Username</label>
              <input type="text" name="username" class="form-control" placeholder="Choose a username" required autocomplete="username">
            </div>

            <div class="form-group full">
              <label class="form-label">Company</label>
              <select name="company_id" class="form-control" required>
                <option value="">Select a company</option>
                <?php if (empty($companies)): ?>
                  <option value="" disabled>No companies available</option>
                <?php else: ?>
                  <?php foreach ($companies as $company): ?>
                    <option value="<?= e($company['id']) ?>"><?= e($company['name']) ?></option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>

            <div class="form-group full">
              <label class="form-label">Password</label>
              <div class="password-wrapper">
                <input type="password" name="password" id="reg-password" class="form-control" placeholder="Create a password (min 8 characters)" required autocomplete="new-password" minlength="8" oninput="checkPasswordStrength(this)">
                <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Toggle password">👁️</button>
              </div>
              <div class="password-strength" id="password-strength">
                <div class="strength-bars">
                  <div class="strength-bar" id="bar1"></div>
                  <div class="strength-bar" id="bar2"></div>
                  <div class="strength-bar" id="bar3"></div>
                  <div class="strength-bar" id="bar4"></div>
                </div>
                <div class="strength-label">
                  <span id="strength-text">Password strength</span>
                  <span id="strength-hint"></span>
                </div>
              </div>
            </div>

            <div class="form-group full">
              <label class="form-label">Confirm Password</label>
              <div class="password-wrapper">
                <input type="password" name="confirm_password" class="form-control" placeholder="Confirm your password" required autocomplete="new-password">
                <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Toggle password">👁️</button>
              </div>
            </div>
          </div>

          <button type="submit" id="register-btn" class="btn-primary">Create Admin Account</button>

          <div class="auth-footer">
            <p>Already have an account? <a href="admin_login.php">Sign In</a></p>
          </div>
        </form>
      </div>
    </main>
  </div>

  <script>
  function togglePassword(btn) {
    const wrapper = btn.parentElement;
    const input = wrapper.querySelector('input');
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁️' : '🙈';
  }

  function checkPasswordStrength(input) {
    const password = input.value;
    const strengthEl = document.getElementById('password-strength');
    const bar1 = document.getElementById('bar1');
    const bar2 = document.getElementById('bar2');
    const bar3 = document.getElementById('bar3');
    const bar4 = document.getElementById('bar4');
    const textEl = document.getElementById('strength-text');
    const labelEl = textEl.parentElement;

    // Hide if empty
    if (!password) {
      strengthEl.classList.remove('visible');
      return;
    }
    strengthEl.classList.add('visible');

    // Reset bars
    bar1.className = 'strength-bar';
    bar2.className = 'strength-bar';
    bar3.className = 'strength-bar';
    bar4.className = 'strength-bar';
    labelEl.className = 'strength-label';
    textEl.textContent = 'Password strength';

    // Calculate strength
    let score = 0;
    if (password.length >= 8) score++;
    if (password.length >= 12) score++;
    if (/[a-z]/.test(password) && /[A-Z]/.test(password)) score++;
    if (/\d/.test(password)) score++;
    if (/[^a-zA-Z0-9]/.test(password)) score++;

    // Update UI based on score
    if (score >= 4) {
      bar1.classList.add('strong');
      bar2.classList.add('strong');
      bar3.classList.add('strong');
      bar4.classList.add('strong');
      labelEl.classList.add('strong');
      textEl.textContent = 'Strong';
    } else if (score >= 3) {
      bar1.classList.add('medium');
      bar2.classList.add('medium');
      bar3.classList.add('medium');
      labelEl.classList.add('medium');
      textEl.textContent = 'Medium';
    } else if (score >= 2) {
      bar1.classList.add('medium');
      bar2.classList.add('medium');
      labelEl.classList.add('medium');
      textEl.textContent = 'Fair';
    } else {
      bar1.classList.add('weak');
      labelEl.classList.add('weak');
      textEl.textContent = 'Weak';
    }
  }
  </script>
  <script src="../js/app.js"></script>
</body>
</html>