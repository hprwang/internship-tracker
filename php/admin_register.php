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
  <link rel="stylesheet" href="css/style.css">
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
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html {
      font-size: 16px;
      scroll-behavior: smooth;
    }

    body {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: var(--black);
      color: var(--white);
      min-height: 100vh;
      line-height: 1.55;
      -webkit-font-smoothing: antialiased;
    }

    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    ::-webkit-scrollbar-thumb {
      background: var(--border);
      border-radius: 3px;
    }
    ::-webkit-scrollbar-track {
      background: var(--black);
    }

    /* Main Container - Split Screen */
    .register-container {
      min-height: 100vh;
      display: flex;
      width: 100%;
      position: relative;
      overflow: hidden;
    }

    /* Background Effects */
    .bg-effects {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 0;
    }

    .bg-effects::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background:
        radial-gradient(ellipse 80% 60% at 10% 0%, rgba(34, 197, 94, 0.08) 0%, transparent 50%),
        radial-gradient(ellipse 60% 50% at 90% 100%, rgba(22, 163, 74, 0.06) 0%, transparent 50%);
    }

    .bg-effects::after {
      content: '';
      position: absolute;
      top: 20%;
      left: 15%;
      width: 300px;
      height: 300px;
      background: var(--primary-green);
      opacity: 0.03;
      filter: blur(100px);
      border-radius: 50%;
    }

    /* Left Panel */
    .left-panel {
      flex: 0 0 35%;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 3rem;
      background: linear-gradient(135deg, var(--black) 0%, #0F3D2E 50%, #16A34A 100%);
      position: relative;
      overflow: hidden;
    }

    .left-panel::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%2322C55E' fill-opacity='0.03'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      opacity: 0.5;
    }

    .left-panel-content {
      position: relative;
      z-index: 1;
    }

    .left-panel-label {
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.25em;
      color: var(--primary-green);
      margin-bottom: 1.5rem;
      text-transform: uppercase;
    }

    .left-panel-title {
      font-size: 4rem;
      font-weight: 900;
      line-height: 1.05;
      color: var(--white);
      margin-bottom: 1.5rem;
    }

    .left-panel-title span {
      background: linear-gradient(135deg, var(--primary-green), var(--emerald));
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      filter: drop-shadow(0 0 30px rgba(34, 197, 94, 0.3));
    }

    .left-panel-desc {
      font-size: 1rem;
      color: var(--muted);
      margin-bottom: 2.5rem;
      max-width: 360px;
      line-height: 1.7;
    }

    .left-panel-cta {
      display: inline-flex;
      align-items: center;
      gap: 0.75rem;
      color: var(--primary-green);
      font-weight: 600;
      font-size: 0.95rem;
      text-decoration: none;
      transition: all 0.3s ease;
    }

    .left-panel-cta:hover {
      gap: 1rem;
      text-shadow: 0 0 20px rgba(34, 197, 94, 0.5);
    }

    .left-panel-cta::after {
      content: '→';
      font-size: 1.2rem;
    }

    /* Green glow effect */
    .green-glow {
      position: absolute;
      bottom: -100px;
      right: -100px;
      width: 400px;
      height: 400px;
      background: var(--primary-green);
      opacity: 0.1;
      filter: blur(120px);
      border-radius: 50%;
    }

    /* Right Panel */
    .right-panel {
      flex: 0 0 65%;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem;
      position: relative;
      z-index: 1;
      background: var(--dark-gray);
    }

    .register-card {
      width: 100%;
      max-width: 580px;
      background: rgba(17, 17, 17, 0.8);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 2.5rem;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(34, 197, 94, 0.1);
    }

    .register-card-header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .register-card-title {
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.15em;
      color: var(--muted);
      text-transform: uppercase;
    }

    /* Form Styles */
    .form-row {
      display: flex;
      gap: 1rem;
      margin-bottom: 1rem;
    }

    .form-group {
      flex: 1;
      margin-bottom: 1rem;
    }

    .form-label {
      display: block;
      font-size: 0.8rem;
      font-weight: 500;
      color: var(--muted);
      margin-bottom: 0.5rem;
    }

    .form-control {
      width: 100%;
      padding: 0.875rem 1rem;
      background: var(--input-bg);
      border: 1px solid var(--border);
      border-radius: 8px;
      color: var(--white);
      font-family: inherit;
      font-size: 0.95rem;
      transition: all 0.2s ease;
      outline: none;
    }

    .form-control:focus {
      border-color: var(--primary-green);
      box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.1);
    }

    .form-control::placeholder {
      color: var(--muted);
    }

    select.form-control {
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke='%239CA3AF'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 0.75rem center;
      background-size: 1.25rem;
      padding-right: 2.5rem;
    }

    .form-control option {
      background: var(--dark-gray);
      color: var(--white);
    }

    /* Password Wrapper */
    .password-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .password-input {
      padding-right: 3rem;
      width: 100%;
    }

    .password-toggle {
      position: absolute;
      right: 0.75rem;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 1rem;
      padding: 0.25rem;
      color: var(--muted);
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .password-toggle:hover {
      color: var(--primary-green);
    }

    /* Buttons */
    .btn-signup {
      width: 100%;
      padding: 1rem 1.5rem;
      background: linear-gradient(135deg, #16A34A, #22C55E);
      color: var(--white);
      border: none;
      border-radius: 8px;
      font-family: inherit;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.1em;
    }

    .btn-signup:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(34, 197, 94, 0.3);
    }

    /* Footer */
    .register-footer {
      text-align: center;
      margin-top: 1.5rem;
      padding-top: 1.5rem;
      border-top: 1px solid var(--border);
    }

    .register-footer p {
      font-size: 0.9rem;
      color: var(--muted);
    }

    .register-footer a {
      color: var(--primary-green);
      text-decoration: none;
      font-weight: 500;
    }

    .register-footer a:hover {
      text-decoration: underline;
    }

    /* Responsive */
    @media (max-width: 968px) {
      .register-container {
        flex-direction: column;
      }

      .left-panel {
        flex: none;
        min-height: auto;
        padding: 3rem 2rem;
        text-align: center;
      }

      .left-panel-content {
        display: flex;
        flex-direction: column;
        align-items: center;
      }

      .left-panel-title {
        font-size: 2.5rem;
      }

      .right-panel {
        padding: 2rem;
      }

      .register-card {
        padding: 1.5rem;
      }

      .form-row {
        flex-direction: column;
        gap: 0;
      }
    }

    @media (max-width: 480px) {
      .left-panel-title {
        font-size: 2rem;
      }

      .left-panel-desc {
        font-size: 0.9rem;
      }
    }

    /* Toast Container */
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
      background: var(--input-bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      box-shadow: 0 8px 30px rgba(0,0,0,0.4);
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-size: 0.9rem;
      animation: slideIn .3s ease;
      min-width: 250px;
    }

    .toast.success {
      border-color: var(--primary-green);
      background: rgba(34, 197, 94, 0.15);
    }

    .toast.success .toast-icon {
      color: var(--primary-green);
    }

    .toast.error {
      border-color: #EF4444;
      background: rgba(239, 68, 68, 0.15);
    }

    .toast.error .toast-icon {
      color: #EF4444;
    }

    .toast-icon {
      font-weight: 700;
      font-size: 1rem;
    }

    @keyframes slideIn {
      from { opacity: 0; transform: translateX(20px); }
      to { opacity: 1; transform: translateX(0); }
    }
  </style>
</head>
<body>
  <div id="toast-container" class="toast-container"></div>
  <div class="bg-effects"></div>

  <div class="register-container">
    <!-- Left Panel -->
    <div class="left-panel">
      <div class="left-panel-content">
        <p class="left-panel-label">INTERNSHIP PORTAL</p>
        <h1 class="left-panel-title">Track Your<br><span>Internship</span> Journey</h1>
        <p class="left-panel-desc">Manage applications, monitor progress, submit reports, and stay connected with mentors through one centralized platform.</p>
        <a href="index.php" class="left-panel-cta">GET STARTED</a>
      </div>
      <div class="green-glow"></div>
    </div>

    <!-- Right Panel -->
    <div class="right-panel">
      <div class="register-card">
        <div class="register-card-header">
          <h2 class="register-card-title">Register With Your Work Email</h2>
        </div>

        <form onsubmit="handleRegister(event)">
          <input type="hidden" name="role_hint" value="admin">
          <input type="hidden" name="role" id="role" value="admin">

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Full Name</label>
              <input type="text" name="full_name" class="form-control" placeholder="Enter your full name" required autocomplete="name">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Email</label>
              <input type="email" name="email" class="form-control" placeholder="admin@company.com" required autocomplete="email">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Username</label>
              <input type="text" name="username" class="form-control" placeholder="Choose a username" required autocomplete="username">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Company</label>
              <select name="company_id" class="form-control" required>
                <option value="">Select a company</option>
                <?php if (empty($companies)): ?>
                  <option value="" disabled>No companies available - please add a company first</option>
                <?php else: ?>
                  <?php foreach ($companies as $company): ?>
                    <option value="<?= e($company['id']) ?>"><?= e($company['name']) ?></option>
                  <?php endforeach; ?>
                <?php endif; ?>
              </select>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Password</label>
              <div class="password-wrapper">
                <input type="password" name="password" class="form-control password-input" placeholder="Create a password" required autocomplete="new-password">
                <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Toggle password visibility">👁️</button>
              </div>
            </div>
          </div>

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Confirm Password</label>
              <div class="password-wrapper">
                <input type="password" name="confirm_password" class="form-control password-input" placeholder="Confirm your password" required autocomplete="new-password">
                <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Toggle password visibility">👁️</button>
              </div>
            </div>
          </div>

          <button type="submit" id="register-btn" class="btn-signup">Sign Up</button>

          <div class="register-footer">
            <p>Already have an account? <a href="admin_login.php">Sign In</a></p>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script>
  function togglePassword(btn) {
    const wrapper = btn.parentElement;
    const input = wrapper.querySelector('input');
    if (!input) return;
    input.type = input.type === 'password' ? 'text' : 'password';
    btn.textContent = input.type === 'password' ? '👁️' : '🙈';
  }
  </script>
  <script src="../js/app.js"></script>
</body>
</html>