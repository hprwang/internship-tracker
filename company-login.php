<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Company Login — InternTrack</title>
  <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --bg-deep: #0D0D0D;
      --bg-charcoal: #141414;
      --bg-panel: #1A1A1A;
      --bg-card: #1F1F1F;
      --bg-elevated: #252525;
      --border-subtle: #2A2A2A;
      --border-light: #333333;
      --green-primary: #00C853;
      --green-emerald: #00E676;
      --green-muted: #69F0AE;
      --green-glow: #B9F6CA;
      --text-primary: #FFFFFF;
      --text-secondary: #B0B0B0;
      --text-muted: #707070;
      --glass-bg: rgba(30,30,30,0.7);
      --glass-border: rgba(255,255,255,0.08);
      --shadow-soft: 0 4px 24px rgba(0,0,0,0.4);
      --shadow-glow: 0 8px 32px rgba(0,200,83,0.2);
      --radius-sm: 10px;
      --radius-md: 14px;
      --radius-lg: 18px;
      --radius-xl: 24px;
      --transition: 280ms cubic-bezier(.4,0,.2,1);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: 'Outfit', system-ui, sans-serif;
      background: var(--bg-deep);
      color: var(--text-primary);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

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
      background: radial-gradient(ellipse 80% 60% at 10% 0%, rgba(0,200,83,0.08) 0%, transparent 50%),
                  radial-gradient(ellipse 60% 50% at 90% 100%, rgba(0,230,118,0.06) 0%, transparent 50%);
    }

    /* Logo */
    .logo {
      position: fixed;
      top: 1.5rem;
      left: 1.5rem;
      text-decoration: none;
      z-index: 100;
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .logo-icon {
      width: 40px;
      height: 40px;
      background: linear-gradient(135deg, var(--green-primary), var(--green-emerald));
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.2rem;
      box-shadow: 0 0 20px rgba(0,200,83,0.3);
    }

    .logo-text {
      font-size: 1.1rem;
      font-weight: 800;
      color: var(--text-primary);
    }

    .logo-text span {
      color: var(--green-primary);
    }

    /* Login Card */
    .login-container {
      display: block;
      width: 100%;
      padding: 2rem;
    }

    .login-card {
      width: 100%;
      max-width: 420px;
      margin: 0 auto;
      background: rgba(26,26,26,0.8);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-lg);
      padding: 2.5rem;
      box-shadow: 0 25px 50px -12px rgba(0,0,0,0.5), 0 0 0 1px rgba(0,200,83,0.1);
      position: relative;
      z-index: 1;
    }

    .login-card-header {
      text-align: center;
      margin-bottom: 2rem;
    }

    .login-card-title {
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.15em;
      color: var(--text-muted);
      text-transform: uppercase;
    }

    /* Form */
    .form-group {
      margin-bottom: 1.1rem;
    }

    .form-label {
      display: block;
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--text-secondary);
      margin-bottom: 0.5rem;
    }

    .form-input {
      width: 100%;
      padding: 0.875rem 1rem;
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-md);
      color: var(--text-primary);
      font-size: 0.9rem;
      transition: all var(--transition);
    }

    .form-input:focus {
      outline: none;
      border-color: var(--green-primary);
      box-shadow: 0 0 0 3px rgba(0,200,83,0.1);
    }

    .form-input::placeholder {
      color: var(--text-muted);
    }

    .password-wrapper {
      position: relative;
      display: flex;
      align-items: center;
    }

    .password-wrapper .form-input {
      padding-right: 3rem;
    }

    .password-toggle {
      position: absolute;
      right: 0.75rem;
      background: none;
      border: none;
      cursor: pointer;
      font-size: 0.9rem;
      color: var(--text-muted);
      transition: all var(--transition);
    }

    .password-toggle:hover {
      color: var(--green-primary);
    }

    .form-options {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
    }

    .form-checkbox {
      display: flex;
      align-items: center;
      gap: 0.5rem;
      cursor: pointer;
    }

    .form-checkbox input {
      width: 16px;
      height: 16px;
      accent-color: var(--green-primary);
    }

    .form-checkbox span {
      font-size: 0.8rem;
      color: var(--text-muted);
    }

    .form-forgot {
      font-size: 0.8rem;
      color: var(--green-primary);
      text-decoration: none;
    }

    .form-forgot:hover {
      text-decoration: underline;
    }

    .btn-primary {
      width: 100%;
      padding: 0.9rem 1.5rem;
      background: linear-gradient(135deg, var(--green-primary), var(--green-emerald));
      border: none;
      border-radius: var(--radius-md);
      color: var(--bg-deep);
      font-size: 0.95rem;
      font-weight: 700;
      cursor: pointer;
      transition: all var(--transition);
    }

    .btn-primary:hover {
      box-shadow: 0 8px 24px rgba(0,200,83,0.35);
      transform: translateY(-2px);
    }

    .form-divider {
      display: flex;
      align-items: center;
      gap: 1rem;
      margin: 1.5rem 0;
    }

    .form-divider::before,
    .form-divider::after {
      content: '';
      flex: 1;
      height: 1px;
      background: var(--border-subtle);
    }

    .form-divider span {
      font-size: 0.7rem;
      color: var(--text-muted);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .social-login {
      display: flex;
      gap: 0.75rem;
    }

    .social-btn {
      flex: 1;
      padding: 0.75rem;
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-md);
      color: var(--text-secondary);
      font-size: 1.1rem;
      cursor: pointer;
      transition: all var(--transition);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .social-btn:hover {
      border-color: var(--border-light);
      color: var(--text-primary);
    }

    .form-footer {
      text-align: center;
      margin-top: 1.5rem;
      font-size: 0.85rem;
      color: var(--text-muted);
    }

    .form-footer a {
      color: var(--green-primary);
      text-decoration: none;
      font-weight: 600;
    }

    .form-footer a:hover {
      text-decoration: underline;
    }

    .error-message {
      background: rgba(239,68,68,0.1);
      border: 1px solid rgba(239,68,68,0.25);
      border-radius: var(--radius-md);
      padding: 0.75rem 1rem;
      margin-bottom: 1rem;
      font-size: 0.85rem;
      color: #F87171;
      display: none;
    }

    .error-message.show {
      display: block;
    }

    @media (max-width: 480px) {
      .login-card {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="bg-effects"></div>

  <a href="landing.php" class="logo">
    <div class="logo-icon">📋</div>
    <div class="logo-text">Intern<span>Track</span></div>
  </a>

  <div class="login-container">
    <div class="login-card">
      <div class="login-card-header">
        <h2 class="login-card-title">Welcome Back</h2>
      </div>

      <div class="error-message" id="error-message">Invalid email or password. Please try again.</div>

      <form id="loginForm">
        <input type="hidden" name="action" value="login">
        <input type="hidden" name="csrf_token" id="csrf_token" value="">
        <input type="hidden" name="role_hint" value="admin">

        <div class="form-group">
          <label class="form-label">Email Address</label>
          <input type="email" name="username" class="form-input" placeholder="company@example.com" required autocomplete="username">
        </div>

        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="password-wrapper">
            <input type="password" name="password" class="form-input" id="password" placeholder="Enter your password" required autocomplete="current-password">
            <button type="button" class="password-toggle" onclick="togglePassword()"><i class="fas fa-eye"></i></button>
          </div>
        </div>

        <div class="form-options">
          <label class="form-checkbox">
            <input type="checkbox">
            <span>Remember me</span>
          </label>
          <a href="company-forgot-password.php" class="form-forgot">Forgot password?</a>
        </div>

        <button type="submit" class="btn-primary" id="login-btn">Sign In</button>
      </form>

      <div class="form-footer">
        Don't have an account? <a href="company-register.php">Create one</a>
      </div>
    </div>
  </div>

  <script>
    function togglePassword() {
      const passwordInput = document.getElementById('password');
      const toggleBtn = document.querySelector('.password-toggle i');
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        toggleBtn.classList.remove('fa-eye');
        toggleBtn.classList.add('fa-eye-slash');
      } else {
        passwordInput.type = 'password';
        toggleBtn.classList.remove('fa-eye-slash');
        toggleBtn.classList.add('fa-eye');
      }
    }

    document.getElementById('loginForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const btn = document.getElementById('login-btn');
      const errorMsg = document.getElementById('error-message');
      btn.disabled = true;
      btn.textContent = 'Signing in...';

      try {
        const formData = new FormData(this);
        const response = await fetch('php/auth.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        if (result.success) {
          window.location.href = 'company-dashboard.php';
        } else {
          errorMsg.textContent = result.message || 'Invalid email or password.';
          errorMsg.classList.add('show');
          btn.disabled = false;
          btn.textContent = 'Sign In';
        }
      } catch (err) {
        errorMsg.textContent = 'An error occurred. Please try again.';
        errorMsg.classList.add('show');
        btn.disabled = false;
        btn.textContent = 'Sign In';
      }
    });

    // Fetch CSRF token on load
    fetch('php/auth.php?action=get_csrf')
      .then(r => r.json())
      .then(data => {
        document.getElementById('csrf_token').value = data.token;
      })
      .catch(() => {});
  </script>
</body>
</html>