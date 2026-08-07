<?php
session_start();
require_once __DIR__ . '/config.php';

// If already logged in as company admin, go to company dashboard
if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: company_dashboard.php');
    exit;
}

// Destroy any previous student/admin session so the company login form is always shown
if (!empty($_SESSION['user'])) {
    session_destroy();
    session_start();
}

$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Company Login</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/responsive.css">
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

    * { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: var(--black);
      color: var(--white);
      min-height: 100vh;
      line-height: 1.55;
      -webkit-font-smoothing: antialiased;
    }

    .login-page {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 2rem 1rem;
      position: relative;
      overflow: hidden;
    }

    .login-page::before {
      content: '';
      position: fixed;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background:
        radial-gradient(ellipse 80% 60% at 10% 0%, rgba(34, 197, 94, 0.08) 0%, transparent 50%),
        radial-gradient(ellipse 60% 50% at 90% 100%, rgba(16, 185, 129, 0.06) 0%, transparent 50%);
      pointer-events: none;
    }

    .login-card {
      width: 100%;
      max-width: 440px;
      background: rgba(17, 17, 17, 0.85);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2.5rem;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(34, 197, 94, 0.1);
      position: relative;
      z-index: 1;
    }

    .login-card-header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .login-card-icon {
      width: 64px;
      height: 64px;
      margin: 0 auto 1rem;
      border-radius: 16px;
      background: linear-gradient(135deg, var(--emerald), var(--primary-green));
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.6rem;
      color: var(--black);
      box-shadow: 0 0 30px rgba(34, 197, 94, 0.35);
    }

    .login-card-title {
      font-size: 1.4rem;
      font-weight: 800;
      letter-spacing: -0.01em;
    }

    .login-card-subtitle {
      font-size: 0.85rem;
      color: var(--muted);
      margin-top: 0.35rem;
    }

    .form-group { margin-bottom: 1.25rem; }
    .form-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--muted); margin-bottom: 0.45rem; }
    .form-control {
      width: 100%;
      background: var(--input-bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 0.8rem 1rem;
      color: var(--white);
      font-size: 0.95rem;
      outline: none;
      transition: border-color 200ms, box-shadow 200ms;
    }
    .form-control:focus { border-color: var(--primary-green); box-shadow: 0 0 0 3px rgba(34,197,94,0.12); }

    .password-wrapper { position: relative; }
    .password-wrapper .form-control { padding-right: 3rem; }
    .password-toggle {
      position: absolute;
      right: 0.5rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      color: var(--muted);
      font-size: 1rem;
      cursor: pointer;
      padding: 0.5rem;
    }
    .password-toggle:hover { color: var(--primary-green); }

    .btn-signin {
      width: 100%;
      background: linear-gradient(135deg, var(--emerald), var(--primary-green));
      color: var(--black);
      font-weight: 800;
      font-size: 1rem;
      padding: 0.85rem;
      border: none;
      border-radius: 10px;
      cursor: pointer;
      transition: all 200ms;
    }
    .btn-signin:hover { box-shadow: 0 0 25px rgba(34,197,94,0.45); transform: translateY(-1px); }
    .btn-signin:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }

    .auth-links {
      margin-top: 1.5rem;
      text-align: center;
      font-size: 0.85rem;
      color: var(--muted);
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
    }
    .auth-links a { color: var(--primary-green); text-decoration: none; font-weight: 600; }
    .auth-links a:hover { text-decoration: underline; }

    .back-link {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      margin-bottom: 1.5rem;
      color: var(--muted);
      font-size: 0.85rem;
      text-decoration: none;
    }
    .back-link:hover { color: var(--primary-green); }

    .toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { background: var(--dark-gray); border: 1px solid var(--border); border-left: 3px solid var(--primary-green); border-radius: 10px; padding: 0.8rem 1.2rem; font-size: 0.88rem; min-width: 260px; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
    .toast.error { border-left-color: #EF4444; }
  </style>
</head>
<body>
  <div id="toast-container" class="toast-container"></div>

  <a href="../landing.php" class="back-link" style="position:fixed;top:1.5rem;left:1.5rem;z-index:100;">
    <i class="fas fa-arrow-left"></i> Back to Home
  </a>

  <div class="login-page">
    <div class="login-card">
      <div class="login-card-header">
        <div class="login-card-icon"><i class="fas fa-building"></i></div>
        <h2 class="login-card-title">Company Login</h2>
        <p class="login-card-subtitle">Post internships and manage applications</p>
      </div>

      <form onsubmit="handleCompanyLogin(event)">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="role_hint" value="company">

        <div class="form-group">
          <label class="form-label">Email / Username</label>
          <input type="text" name="username" class="form-control" placeholder="Enter your email or username" required autocomplete="username">
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="password-wrapper">
            <input type="password" name="password" class="form-control password-input" placeholder="Enter your password" required autocomplete="current-password">
            <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
          </div>
        </div>

        <button type="submit" id="login-btn" class="btn-signin">Sign In</button>
      </form>

      <div class="auth-links">
        <span>New company? <a href="company_register.php">Create an account</a></span>
        <span><a href="../landing.php">Back to Home</a> · <a href="../index.php">Student Login</a></span>
      </div>
    </div>
  </div>

  <script>
  function togglePassword(btn) {
    const wrapper = btn.parentElement;
    const input = wrapper.querySelector('input');
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      if (icon) icon.className = 'fas fa-eye-slash';
    } else {
      input.type = 'password';
      if (icon) icon.className = 'fas fa-eye';
    }
  }

  function toast(msg, type) {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const el = document.createElement('div');
    el.className = 'toast' + (type === 'error' ? ' error' : '');
    el.textContent = msg;
    container.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = '.3s'; setTimeout(() => el.remove(), 300); }, 4000);
  }

  async function handleCompanyLogin(e) {
    e.preventDefault();
    const btn = document.getElementById('login-btn');
    const form = e.target;
    btn.textContent = 'Signing in…';
    btn.disabled = true;

    try {
      const fd = new FormData(form);
      fd.set('action', 'login');
      fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

      const res = await fetch('auth.php', { method: 'POST', body: fd });
      if (!res.ok) throw new Error('Server error ' + res.status);

      const data = await res.json();
      if (data.success) {
        toast(data.message || 'Login successful!', 'success');
        // Always go to the company dashboard; ignore any server redirect
        setTimeout(() => { window.location.href = 'company_dashboard.php'; }, 700);
      } else {
        toast(data.message || 'Invalid username or password.', 'error');
        btn.textContent = 'Sign In';
        btn.disabled = false;
      }
    } catch (err) {
      toast('Network error. Check your connection.', 'error');
      btn.textContent = 'Sign In';
      btn.disabled = false;
    }
  }
  </script>
  <script src="../js/interactive.js"></script>
</body>
</html>