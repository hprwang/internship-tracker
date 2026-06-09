<?php
session_start();
require_once 'php/config.php';

// Redirect if already logged in
if (!empty($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit;
}

$csrf = generateCSRF();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <title>InternTrack — Sign In</title>
  <link rel="stylesheet" href="css/style.css">
  <script>
    function togglePassword(btn) {
      const input = btn.parentElement.querySelector('.password-input');
      const isPassword = input.type === 'password';
      input.type = isPassword ? 'text' : 'password';
      btn.textContent = isPassword ? '🙈' : '👁️';
    }
  </script>
</head>
<body>
<div id="toast-container" class="toast-container"></div>

<div class="auth-page">
  <div class="auth-card">
    <div class="auth-logo">
      <div class="logo-icon">🎓</div>
      <span>InternTrack</span>
    </div>

    <div class="auth-tabs">
      <button class="auth-tab active" data-tab="login" onclick="switchTab('login')">Sign In</button>
      <button class="auth-tab" data-tab="register" onclick="switchTab('register')">Register</button>
    </div>

    <!-- Login Form -->
    <div id="login-form">
      <form onsubmit="handleLogin(event)">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input type="text" name="username" class="form-control" placeholder="e.g. admin" required>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="password-wrapper">
            <input type="password" name="password" class="form-control password-input" placeholder="••••••••" required>
            <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Toggle password visibility">👁️</button>
          </div>
        </div>
        <button type="submit" id="login-btn" class="btn btn-primary btn-full" style="margin-top:.5rem">Sign In</button>
      </form>

      <div style="text-align:center;margin-top:1rem">
        <a href="#" onclick="openForgotPasswordModal(); return false;" style="color:var(--accent);text-decoration:none;font-size:.86rem">
          Forgot Password?
        </a>
      </div>

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

              <button type="submit" class="btn btn-primary btn-full" id="forgot-btn" style="margin-top:1rem">
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

    <!-- Register Form -->
    <div id="reg-form" style="display:none">
      <form onsubmit="handleRegister(event)">
        <div class="form-group">
          <label class="form-label">Full Name</label>
          <input type="text" name="full_name" class="form-control" placeholder="Your full name" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Username</label>
            <input type="text" name="username" class="form-control" placeholder="username" required>
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" placeholder="email@example.com" required>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="password-wrapper">
            <input type="password" name="password" class="form-control password-input" placeholder="Min. 8 chars, 1 uppercase, 1 number" required>
            <button type="button" class="password-toggle" onclick="togglePassword(this)" aria-label="Toggle password visibility">👁️</button>
          </div>
        </div>
        <button type="submit" id="reg-btn" class="btn btn-primary btn-full" style="margin-top:.5rem">Create Account</button>
      </form>
    </div>
  </div>
</div>

<script src="js/app.js"></script>
</body>
</html>
