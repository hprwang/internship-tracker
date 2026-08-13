<?php
session_start();
require_once 'php/config.php';

// If already logged in, redirect based on role
if (!empty($_SESSION['user'])) {
    if ($_SESSION['user']['role'] === 'admin') {
        header('Location: php/admin_dashboard.php');
    } else {
        header('Location: dashboard.php');
    }
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
  <title>InternTrack — Sign In</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="css/style.css">
  <link rel="stylesheet" href="css/responsive.css">
  <style>
    :root {
      --primary: #7C3AED;
      --primary-hover: #6D28D9;
      --bg: #090A0F;
      --card: #12131C;
      --glass: rgba(18,19,28,.85);
      --border: rgba(124,58,237,.25);
      --text: #F1F0FB;
      --muted: rgba(241,240,251,.55);
      --input-bg: #1A1B26;
    }

    * { box-sizing: border-box; margin: 0; padding: 0; }
    html { font-size: 16px; scroll-behavior: smooth; }
    body {
      font-family: Inter, system-ui, -apple-system, sans-serif;
      background: var(--bg);
      color: var(--text);
      min-height: 100vh;
      line-height: 1.55;
      -webkit-font-smoothing: antialiased;
    }

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
      top: 0; left: 0;
      width: 100%; height: 100%;
      pointer-events: none;
      z-index: 0;
    }

    .bg-effects::before {
      content: '';
      position: absolute;
      top: -50%; left: -50%;
      width: 200%; height: 200%;
      background:
        radial-gradient(ellipse 80% 60% at 10% 0%, rgba(124,58,237,0.12) 0%, transparent 50%),
        radial-gradient(ellipse 60% 50% at 90% 100%, rgba(139,92,246,0.08) 0%, transparent 50%);
    }

    .bg-effects::after {
      content: '';
      position: absolute;
      top: 20%; left: 15%;
      width: 400px; height: 400px;
      background: var(--primary);
      opacity: 0.05;
      filter: blur(120px);
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
      background: linear-gradient(135deg, #090A0F 0%, #1E1030 50%, #2D1B4E 100%);
      position: relative;
      overflow: hidden;
    }

    .left-panel::before {
      content: '';
      position: absolute;
      top: 0; left: 0;
      width: 100%; height: 100%;
      background: url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%237C3AED' fill-opacity='0.04'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E");
      opacity: 0.6;
    }

    .left-panel-content {
      position: relative;
      z-index: 1;
    }

    .left-panel-label {
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.25em;
      color: var(--primary);
      margin-bottom: 1.5rem;
      text-transform: uppercase;
    }

    .left-panel-title {
      font-size: 4rem;
      font-weight: 900;
      line-height: 1.05;
      color: var(--text);
      margin-bottom: 1.5rem;
    }

    .left-panel-title span {
      background: linear-gradient(135deg, #A78BFA, #7C3AED);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      filter: drop-shadow(0 0 30px rgba(124,58,237,0.4));
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
      color: var(--primary);
      font-weight: 600;
      font-size: 0.95rem;
      text-decoration: none;
      transition: all 0.3s ease;
    }

    .left-panel-cta:hover {
      gap: 1rem;
      text-shadow: 0 0 20px rgba(124,58,237,0.5);
    }

    .left-panel-cta::after {
      content: '→';
      font-size: 1.2rem;
    }

    .violet-glow {
      position: absolute;
      bottom: -100px; right: -100px;
      width: 500px; height: 500px;
      background: var(--primary);
      opacity: 0.08;
      filter: blur(150px);
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
      background: var(--bg);
    }

    .login-card {
      width: 100%;
      max-width: 420px;
      background: var(--glass);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2.5rem;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5), 0 0 0 1px rgba(124,58,237,0.15);
    }

    .login-card-header { text-align: center; margin-bottom: 2rem; }

    .login-card-title {
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.15em;
      color: var(--muted);
      text-transform: uppercase;
    }

    /* Form Styles */
    .form-group { margin-bottom: 1.25rem; }

    .form-label {
      display: block;
      font-size: 0.85rem;
      font-weight: 500;
      color: var(--muted);
      margin-bottom: 0.5rem;
    }

    .form-control {
      width: 100%;
      padding: 0.875rem 1rem;
      background: var(--input-bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      color: var(--text);
      font-family: inherit;
      font-size: 0.95rem;
      transition: all 0.2s ease;
      outline: none;
    }

    .form-control:focus {
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(124,58,237,0.15);
    }

    .form-control::placeholder { color: var(--muted); }

    .password-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .password-input { padding-right: 3rem; width: 100%; }

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

    .password-toggle:hover { color: var(--primary); }

    .btn-signin {
      width: 100%;
      padding: 1rem 1.5rem;
      background: linear-gradient(135deg, #7C3AED, #8B5CF6);
      color: #fff;
      border: none;
      border-radius: 10px;
      font-family: inherit;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      margin-top: 0.5rem;
    }

    .btn-signin:hover {
      transform: translateY(-2px);
      box-shadow: 0 10px 30px rgba(124,58,237,0.35);
    }

    .btn-signin:disabled {
      opacity: 0.7;
      cursor: not-allowed;
      transform: none;
    }

    .forgot-link {
      display: block;
      text-align: center;
      color: var(--primary);
      font-size: 0.85rem;
      font-weight: 500;
      text-decoration: none;
      margin-top: 1.25rem;
      transition: all 0.2s ease;
    }

    .forgot-link:hover { text-decoration: underline; }

    .login-footer {
      text-align: center;
      margin-top: 1.5rem;
    }

    .login-footer a {
      color: var(--primary);
      text-decoration: none;
      font-weight: 500;
    }

    .login-footer a:hover { text-decoration: underline; }

    /* Responsive */
    @media (max-width: 968px) {
      .login-container { flex-direction: column; }
      .left-panel {
        flex: none;
        min-height: auto;
        padding: 3rem 2rem;
        text-align: center;
      }
      .left-panel-content { display: flex; flex-direction: column; align-items: center; }
      .left-panel-title { font-size: 2.5rem; }
      .right-panel { padding: 2rem; }
    }

    @media (max-width: 480px) {
      .left-panel-title { font-size: 2rem; }
      .left-panel-desc { font-size: 0.9rem; }
      .login-card { padding: 1.5rem; }
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
      background: var(--card);
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

    .toast.success { border-color: var(--accent); }
    .toast.success .toast-icon { color: var(--accent); }
    .toast.error { border-color: var(--error); }
    .toast.error .toast-icon { color: var(--error); }

    .toast-icon { font-weight: 700; font-size: 1rem; }

    @keyframes slideIn {
      from { opacity: 0; transform: translateX(20px); }
      to { opacity: 1; transform: translateX(0); }
    }

    /* Logo */
    .logo {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      text-decoration: none;
    }

    .logo-icon {
      width: 42px; height: 42px;
      background: linear-gradient(135deg, #7C3AED, #8B5CF6);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      box-shadow: 0 0 25px rgba(124,58,237,0.4);
      color: #FFFFFF;
    }

    .logo-text {
      font-size: 1.35rem;
      font-weight: 800;
      color: #F1F0FB;
      letter-spacing: -0.02em;
    }

    .logo-text span { color: #7C3AED; }
  </style>
</head>
<body>
  <div id="toast-container" class="toast-container"></div>
  <div class="bg-effects"></div>

  <!-- Logo -->
  <a href="landing.php" class="logo" style="position:fixed;top:1.5rem;left:1.5rem;z-index:100;">
    <div class="logo-icon"><i class="fas fa-clipboard-list"></i></div>
    <div class="logo-text">Intern<span>Track</span></div>
  </a>

  <div class="login-container">
    <!-- Left Panel -->
    <div class="left-panel">
      <div class="left-panel-content">
        <div class="left-panel-label">Internship Tracker</div>
        <h1 class="left-panel-title">Welcome<br><span>Back</span></h1>
        <p class="left-panel-desc">Track your internships, manage applications, and land your dream job. Sign in to continue.</p>
        <a href="register.php" class="left-panel-cta">Create an account</a>
      </div>
      <div class="violet-glow"></div>
    </div>

    <!-- Right Panel -->
    <div class="right-panel">
      <div class="login-card">
        <div class="login-card-header">
          <h2 class="login-card-title">Sign In to Your Account</h2>
        </div>

        <form onsubmit="handleLogin(event)">
          <div class="form-group">
            <label class="form-label">Username or Email</label>
            <input type="text" name="username" class="form-control" placeholder="Enter your username or email" required autocomplete="username">
          </div>

          <div class="form-group">
            <label class="form-label">Password</label>
            <div class="password-wrapper">
              <input type="password" name="password" class="form-control password-input" placeholder="Enter your password" required autocomplete="current-password">
              <button type="button" class="password-toggle" onclick="this.previousElementSibling.type = this.previousElementSibling.type === 'password' ? 'text' : 'password'; this.innerHTML = this.previousElementSibling.type === 'password' ? '<i class=\'fas fa-eye\'></i>' : '<i class=\'fas fa-eye-slash\'></i>'" aria-label="Toggle password visibility">
                <i class="fas fa-eye"></i>
              </button>
            </div>
          </div>

          <button type="submit" id="login-btn" class="btn-signin">Sign In</button>

          <a href="#" onclick="openForgotPasswordModal(); return false;" class="forgot-link">Forgot Password?</a>
        </form>

        <!-- Forgot Password Modal -->
        <div id="forgot-modal" class="modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:9998;align-items:center;justify-content:center;">
          <div class="modal" style="background:var(--card);border:1px solid var(--border);border-radius:12px;padding:2rem;max-width:400px;width:90%;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem;">
              <strong style="font-size:1.1rem;">Reset Password</strong>
              <button type="button" onclick="closeForgotPasswordModal()" style="background:none;border:none;color:var(--muted);font-size:1.5rem;cursor:pointer;">×</button>
            </div>
            <form onsubmit="handleForgotRequest(event)">
              <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" name="email" class="form-control" placeholder="email@example.com" required autocomplete="email">
              </div>
              <button type="submit" class="btn-signin" id="forgot-btn">Send Reset Link</button>
              <p style="margin-top:.8rem;font-size:.82rem;color:var(--muted)">If your email exists, we'll send a password reset link.</p>
            </form>
          </div>
        </div>

        <div class="login-footer">
          Don't have an account? <a href="register.php">Create one</a>
        </div>
      </div>
    </div>
  </div>

  <script>
  function openForgotPasswordModal() {
    document.getElementById('forgot-modal').style.display = 'flex';
  }
  function closeForgotPasswordModal() {
    document.getElementById('forgot-modal').style.display = 'none';
  }
  </script>
  <script src="js/app.js"></script>
</body>
</html>