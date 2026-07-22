<?php
session_start();
require_once 'php/config.php';

// If already logged in as admin, redirect to admin login page
if (!empty($_SESSION['user']) && $_SESSION['user']['role'] === 'admin') {
    header('Location: php/admin_login.php');
    exit;
} elseif (!empty($_SESSION['user'])) {
    // If logged in as student, redirect to student dashboard
    header('Location: dashboard.php');
    exit;
}

$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Student Login</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
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
    .login-container {
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

    /* Main Panel */
    .main-panel {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 3rem;
      position: relative;
      z-index: 1;
      background: var(--dark-gray);
    }

    .login-card {
      width: 100%;
      max-width: 420px;
      background: rgba(17, 17, 17, 0.8);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid var(--border);
      border-radius: 12px;
      padding: 2.5rem;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(34, 197, 94, 0.1);
    }

    .login-card-header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .login-card-title {
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.15em;
      color: var(--muted);
      text-transform: uppercase;
    }

    /* Auth Tabs */
    .auth-tabs {
      display: flex;
      background: var(--input-bg);
      border-radius: 8px;
      padding: 4px;
      margin-bottom: 1.5rem;
      gap: 4px;
      border: 1px solid var(--border);
    }

    .auth-tab {
      flex: 1;
      padding: 0.7rem;
      border: none;
      background: transparent;
      color: var(--muted);
      font-family: inherit;
      font-weight: 600;
      font-size: 0.85rem;
      border-radius: 6px;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .auth-tab.active {
      background: linear-gradient(135deg, #16A34A, #22C55E);
      color: var(--white);
    }

    .auth-tab:not(.active):hover {
      background: rgba(34, 197, 94, 0.1);
      color: var(--primary-green);
    }

    /* Form Styles */
    .form-group {
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
    .btn-signin {
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
      margin-top: 0.5rem;
    }

    .btn-signin:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(34, 197, 94, 0.3);
    }

    .btn-secondary {
      width: 100%;
      padding: 0.875rem 1.25rem;
      background: transparent;
      color: var(--muted);
      border: 1px solid var(--border);
      border-radius: 8px;
      font-family: inherit;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .btn-secondary:hover {
      border-color: var(--primary-green);
      color: var(--primary-green);
    }

    /* Forgot Link */
    .forgot-link {
      display: block;
      text-align: center;
      color: var(--primary-green);
      font-size: 0.85rem;
      font-weight: 500;
      text-decoration: none;
      margin-top: 1rem;
      transition: all 0.2s ease;
    }

    .forgot-link:hover {
      text-decoration: underline;
    }

    /* Divider */
    .auth-divider {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin: 1.25rem 0;
    }

    .auth-divider::before,
    .auth-divider::after {
      content: "";
      flex: 1;
      height: 1px;
      background: var(--border);
    }

    .auth-divider span {
      font-size: 0.7rem;
      color: var(--muted);
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.08em;
    }

    /* Form Row */
    .form-row {
      display: flex;
      gap: 1rem;
    }

    .form-row .form-group {
      flex: 1;
    }

    /* Footer */
    .login-footer {
      text-align: center;
      margin-top: 1.5rem;
    }

    .login-footer a {
      color: var(--primary-green);
      text-decoration: none;
      font-weight: 500;
    }

    .login-footer a:hover {
      text-decoration: underline;
    }

    /* Responsive */
    @media (max-width: 968px) {
      .login-container {
        flex-direction: column;
      }

      .main-panel {
        padding: 2rem;
      }
    }

    @media (max-width: 480px) {
      .login-card {
        padding: 1.5rem;
      }

      .form-row {
        flex-direction: column;
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
      margin-right: 0.5rem;
    }

    .toast span:last-child {
      flex: 1;
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

  <!-- Logo in top left -->
  <a href="landing.php" style="position:fixed;top:1.5rem;left:1.5rem;text-decoration:none;z-index:100;display:flex;align-items:center;gap:0.5rem;">
    <div style="width:40px;height:40px;background:linear-gradient(135deg,#22C55E,#16A34A);border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:1.3rem;"><i class="fas fa-clipboard-list"></i></div>
    <div style="font-size:1.1rem;font-weight:800;color:var(--white);">Intern<span style="color:#22C55E;">Track</span></div>
  </a>

  <div class="login-container" style="display:block;">
    <!-- Main Panel -->
    <div class="main-panel">
      <div class="login-card">
        <div class="login-card-header">
          <h2 class="login-card-title">Sign In to Your Account</h2>
        </div>

        <div class="auth-tabs" role="tablist" aria-label="Student authentication tabs">
          <button class="auth-tab active" type="button" data-tab="login">Sign In</button>
          <button class="auth-tab" type="button" data-tab="register" onclick="window.location.href='register.php'">Register</button>
        </div>

        <!-- Login Form -->
        <div id="login-form">
          <form onsubmit="handleLogin(event)">
            <input type="hidden" name="role_hint" id="role_hint" value="student">

            <div class="form-group">
              <label class="form-label">Username</label>
              <input type="text" name="username" class="form-control" placeholder="Enter your username" required>
            </div>

            <div class="form-group">
              <label class="form-label">Password</label>
              <div class="password-wrapper">
                <input type="password" name="password" class="form-control password-input" placeholder="Enter your password" required>
                <button type="button" class="password-toggle" onclick="var w=this.parentElement, i=w.querySelector('input'); i.type=i.type==='password'?'text':'password'; this.innerHTML=i.type==='password'?'<i class=\'fas fa-eye\'></i>':'<i class=\'fas fa-eye-slash\'></i>'" aria-label="Toggle password visibility" tabindex="0"><i class="fas fa-eye"></i></button>
              </div>
            </div>

            <button type="submit" id="login-btn" class="btn-signin">Sign In</button>

            
            <a href="#" onclick="openForgotPasswordModal(); return false;" class="forgot-link">Forgot Password?</a>
          </form>

          <!-- Forgot Password Modal -->
          <div id="forgot-modal" class="modal-overlay" style="display:none">
            <div class="modal">
              <div class="modal-header">
                <strong>Reset Password</strong>
                <button type="button" class="modal-close" onclick="closeForgotPasswordModal()" aria-label="Close">×</button>
              </div>

              <div class="modal-body">
                <form onsubmit="handleForgotRequest(event)">
                  <div class="form-group">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
                  </div>

                  <button type="submit" class="btn-signin" id="forgot-btn">
                    Send Reset Link
                  </button>

                  <p style="margin-top:.8rem;font-size:.82rem;color:var(--muted)">
                    If your email exists, we'll send a password reset link.
                  </p>
                </form>
              </div>
            </div>
          </div>
        </div>

        <div class="login-footer">
          Don't have an account? <a href="register.php">Create one</a>
        </div>
      </div>
    </div>
  </div>


  <script src="js/app.js"></script>
</body>
</html>