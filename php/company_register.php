<?php
session_start();
require_once __DIR__ . '/config.php';

// If already logged in as company admin, go to company dashboard
if (!empty($_SESSION['user']) && in_array($_SESSION['user']['role'] ?? '', ['admin', 'super_admin'])) {
    header('Location: company_dashboard.php');
    exit;
}

// Destroy any previous session so the registration form is always shown
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
  <title>InternTrack — Company Registration</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <link rel="stylesheet" href="../css/style.css">
  <link rel="stylesheet" href="../css/responsive.css">
  <style>
    :root {
      --primary-green: #22C55E;
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
      padding: 5rem 1rem 3rem;
    }

    .auth-page {
      max-width: 640px;
      margin: 0 auto;
      background: rgba(17, 17, 17, 0.9);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2.5rem;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
    }

    .auth-header { text-align: center; margin-bottom: 2rem; }
    .auth-title { font-size: 1.5rem; font-weight: 800; }
    .auth-subtitle { font-size: 0.88rem; color: var(--muted); margin-top: 0.35rem; }

    .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 1.1rem; }
    .form-group.full { grid-column: 1 / -1; }
    .form-group { margin-bottom: 0.25rem; }
    .form-label { display: block; font-size: 0.8rem; font-weight: 600; color: var(--muted); margin-bottom: 0.4rem; }
    .form-control {
      width: 100%;
      background: var(--input-bg);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 0.78rem 1rem;
      color: var(--white);
      font-size: 0.93rem;
      outline: none;
      transition: border-color 200ms, box-shadow 200ms;
    }
    .form-control:focus { border-color: var(--primary-green); box-shadow: 0 0 0 3px rgba(34,197,94,0.12); }

    .password-wrapper { position: relative; }
    .password-wrapper .form-control { padding-right: 3rem; }
    .password-toggle {
      position: absolute; right: 0.5rem; top: 50%; transform: translateY(-50%);
      background: none; border: none; color: var(--muted); font-size: 1rem; cursor: pointer; padding: 0.5rem;
    }
    .password-toggle:hover { color: var(--primary-green); }

    .btn-primary {
      width: 100%;
      margin-top: 1.4rem;
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
    .btn-primary:hover { box-shadow: 0 0 25px rgba(34,197,94,0.45); transform: translateY(-1px); }
    .btn-primary:disabled { opacity: 0.6; cursor: not-allowed; transform: none; box-shadow: none; }

    .auth-footer { margin-top: 1.4rem; text-align: center; font-size: 0.85rem; color: var(--muted); }
    .auth-footer a { color: var(--primary-green); text-decoration: none; font-weight: 600; }
    .auth-footer a:hover { text-decoration: underline; }

    .back-link {
      position: fixed; top: 1.5rem; left: 1.5rem; z-index: 100;
      display: inline-flex; align-items: center; gap: 0.5rem;
      color: var(--muted); font-size: 0.85rem; text-decoration: none;
    }
    .back-link:hover { color: var(--primary-green); }

    .toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 999; display: flex; flex-direction: column; gap: 0.5rem; }
    .toast { background: var(--dark-gray); border: 1px solid var(--border); border-left: 3px solid var(--primary-green); border-radius: 10px; padding: 0.8rem 1.2rem; font-size: 0.88rem; min-width: 260px; box-shadow: 0 10px 30px rgba(0,0,0,0.4); }
    .toast.error { border-left-color: #EF4444; }

    @media (max-width: 640px) {
      .form-grid { grid-template-columns: 1fr; }
      .auth-page { padding: 1.8rem 1.2rem; }
    }
  </style>
</head>
<body>
  <div id="toast-container" class="toast-container"></div>

  <a href="company_login.php" class="back-link"><i class="fas fa-arrow-left"></i> Back to Login</a>

  <div class="auth-page">
    <div class="auth-header">
      <h2 class="auth-title">Company Registration</h2>
      <p class="auth-subtitle">Create your company account and start posting internships</p>
    </div>

    <form onsubmit="handleCompanyRegister(event)">
      <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
      <input type="hidden" name="role_hint" value="company">
      <input type="hidden" name="role" value="admin">

      <div class="form-grid">
        <div class="form-group full">
          <label class="form-label">Company Name</label>
          <input type="text" name="company_name" class="form-control" placeholder="e.g. TechNova Solutions" required autocomplete="organization">
        </div>

        <div class="form-group">
          <label class="form-label">Industry</label>
          <input type="text" name="industry" class="form-control" placeholder="e.g. Information Technology">
        </div>

        <div class="form-group">
          <label class="form-label">Location</label>
          <input type="text" name="location" class="form-control" placeholder="e.g. Kathmandu, Nepal">
        </div>

        <div class="form-group">
          <label class="form-label">Website</label>
          <input type="url" name="website" class="form-control" placeholder="https://example.com">
        </div>

        <div class="form-group">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" placeholder="+977 98XXXXXXXX">
        </div>

        <div class="form-group full">
          <label class="form-label">Contact Full Name</label>
          <input type="text" name="full_name" class="form-control" placeholder="Your full name" required autocomplete="name">
        </div>

        <div class="form-group full">
          <label class="form-label">Contact Email</label>
          <input type="email" name="email" class="form-control" placeholder="hr@company.com" required autocomplete="email">
        </div>

        <div class="form-group full">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" placeholder="Optional — defaults to company name">
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="password-wrapper">
            <input type="password" name="password" class="form-control" placeholder="Min 8 chars, uppercase + number" required autocomplete="new-password" minlength="8">
            <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Toggle password"><i class="fas fa-eye"></i></button>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Confirm Password</label>
          <div class="password-wrapper">
            <input type="password" name="confirm_password" class="form-control" placeholder="Repeat password" required autocomplete="new-password">
            <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Toggle password"><i class="fas fa-eye"></i></button>
          </div>
        </div>
      </div>

      <button type="submit" id="register-btn" class="btn-primary">Create Company Account</button>

      <div class="auth-footer">
        <p>Already have an account? <a href="company_login.php">Sign In</a></p>
        <p style="margin-top:0.6rem;">Are you a student? <a href="../register.php">Student Register</a></p>
      </div>
    </form>
  </div>

  <script>
  function togglePassword(btn) {
    const wrapper = btn.parentElement;
    const input = wrapper.querySelector('input');
    if (!input) return;
    const icon = btn.querySelector('i');
    if (input.type === 'password') { input.type = 'text'; if (icon) icon.className = 'fas fa-eye-slash'; }
    else { input.type = 'password'; if (icon) icon.className = 'fas fa-eye'; }
  }

  function toast(msg, type) {
    const container = document.getElementById('toast-container');
    if (!container) return;
    const el = document.createElement('div');
    el.className = 'toast' + (type === 'error' ? ' error' : '');
    el.textContent = msg;
    container.appendChild(el);
    setTimeout(() => { el.style.opacity = '0'; el.style.transition = '.3s'; setTimeout(() => el.remove(), 300); }, 4500);
  }

  async function handleCompanyRegister(e) {
    e.preventDefault();
    const btn = document.getElementById('register-btn');
    const form = e.target;
    btn.textContent = 'Creating account…';
    btn.disabled = true;

    const pw = form.querySelector('input[name="password"]').value;
    const confirmPw = form.querySelector('input[name="confirm_password"]').value;
    if (pw !== confirmPw) {
      toast('Passwords do not match.', 'error');
      btn.textContent = 'Create Company Account';
      btn.disabled = false;
      return;
    }

    try {
      const fd = new FormData(form);
      fd.set('action', 'register');
      fd.set('csrf_token', document.querySelector('meta[name="csrf-token"]').content);

      const res = await fetch('auth.php', { method: 'POST', body: fd });
      if (!res.ok) throw new Error('Server error ' + res.status);

      const data = await res.json();
      if (data.success) {
        toast(data.message || 'Account created!', 'success');
        setTimeout(() => { window.location.href = 'company_login.php'; }, 1500);
      } else {
        toast(data.message || 'Registration failed.', 'error');
        btn.textContent = 'Create Company Account';
        btn.disabled = false;
      }
    } catch (err) {
      toast('Network error. Please try again.', 'error');
      btn.textContent = 'Create Company Account';
      btn.disabled = false;
    }
  }
  </script>
  <script src="../js/interactive.js"></script>
</body>
</html>