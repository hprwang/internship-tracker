<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Company Register — InternTrack</title>
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
      padding: 2rem 1rem;
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

    /* Register Card */
    .register-container {
      display: block;
      width: 100%;
      max-width: 520px;
    }

    .register-card {
      width: 100%;
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

    .register-card-header {
      text-align: center;
      margin-bottom: 1.5rem;
    }

    .register-card-title {
      font-size: 0.8rem;
      font-weight: 600;
      letter-spacing: 0.15em;
      color: var(--text-muted);
      text-transform: uppercase;
    }

    .step-indicator {
      display: flex;
      gap: 0.5rem;
      margin-bottom: 1.5rem;
    }

    .step {
      flex: 1;
      height: 4px;
      background: var(--border-subtle);
      border-radius: 2px;
    }

    .step.active {
      background: var(--green-primary);
    }

    .form-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 1rem;
    }

    .form-group {
      margin-bottom: 1rem;
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

    .form-select {
      width: 100%;
      padding: 0.875rem 1rem;
      background: var(--bg-card);
      border: 1px solid var(--border-subtle);
      border-radius: var(--radius-md);
      color: var(--text-primary);
      font-size: 0.9rem;
      cursor: pointer;
      transition: all var(--transition);
    }

    .form-select:focus {
      outline: none;
      border-color: var(--green-primary);
    }

    .form-checkbox {
      display: flex;
      align-items: flex-start;
      gap: 0.6rem;
      margin-bottom: 1.25rem;
    }

    .form-checkbox input {
      width: 16px;
      height: 16px;
      accent-color: var(--green-primary);
      margin-top: 2px;
    }

    .form-checkbox span {
      font-size: 0.8rem;
      color: var(--text-muted);
      line-height: 1.4;
    }

    .form-checkbox a {
      color: var(--green-primary);
      text-decoration: none;
    }

    .form-checkbox a:hover {
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

    @media (max-width: 500px) {
      .form-row {
        grid-template-columns: 1fr;
      }

      .register-card {
        padding: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <div class="bg-effects"></div>

  <a href="landing.php" class="logo">
    <div class="logo-icon"><i class="fas fa-bolt"></i></div>
    <div class="logo-text">Intern<span>Track</span></div>
  </a>

  <div class="register-container">
    <div class="register-card">
      <div class="register-card-header">
        <h2 class="register-card-title">Create Company Account</h2>
      </div>

      <div class="step-indicator">
        <div class="step active"></div>
        <div class="step"></div>
      </div>

      <form id="registerForm">
        <input type="hidden" name="action" value="register">
        <input type="hidden" name="csrf_token" id="csrf_token" value="">
        <input type="hidden" name="role_hint" value="admin">
        <input type="hidden" name="role" value="admin">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Company Name</label>
            <input type="text" class="form-input" placeholder="TechNova Solutions" required>
          </div>
          <div class="form-group">
            <label class="form-label">Industry</label>
            <select class="form-select" required>
              <option value="">Select industry</option>
              <option value="technology">Technology</option>
              <option value="finance">Finance</option>
              <option value="healthcare">Healthcare</option>
              <option value="retail">Retail & E-commerce</option>
              <option value="education">Education</option>
              <option value="marketing">Marketing & Media</option>
              <option value="consulting">Consulting</option>
              <option value="other">Other</option>
            </select>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Company Website</label>
          <input type="url" class="form-input" placeholder="https://yourcompany.com">
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Contact Person Name</label>
            <input type="text" class="form-input" placeholder="John Smith" required>
          </div>
          <div class="form-group">
            <label class="form-label">Job Title</label>
            <input type="text" class="form-input" placeholder="HR Manager" required>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Work Email</label>
          <input type="email" class="form-input" placeholder="hr@company.com" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Password</label>
            <div class="password-wrapper">
              <input type="password" class="form-input" id="password" placeholder="Create a password" required>
              <button type="button" class="password-toggle" onclick="togglePassword('password', 'toggle1')"><i class="fas fa-eye" id="toggle1"></i></button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Confirm Password</label>
            <div class="password-wrapper">
              <input type="password" class="form-input" id="confirmPassword" placeholder="Confirm password" required>
              <button type="button" class="password-toggle" onclick="togglePassword('confirmPassword', 'toggle2')"><i class="fas fa-eye" id="toggle2"></i></button>
            </div>
          </div>
        </div>

        <div class="form-checkbox">
          <input type="checkbox" id="terms" required>
          <span>I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></span>
        </div>

        <button type="submit" class="btn-primary">Create Account</button>
      </form>

      <div class="form-footer">
        Already have an account? <a href="company-login.php">Sign in</a>
      </div>
    </div>
  </div>

  <script>
    function togglePassword(inputId, toggleIconId) {
      const input = document.getElementById(inputId);
      const icon = document.getElementById(toggleIconId);
      if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
      } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
      }
    }

    document.getElementById('registerForm').addEventListener('submit', async function(e) {
      e.preventDefault();
      const formData = new FormData(this);
      try {
        const response = await fetch('php/auth.php', {
          method: 'POST',
          body: formData
        });
        const result = await response.json();
        if (result.success) {
          window.location.href = 'company-login.php';
        } else {
          alert(result.message || 'Registration failed');
        }
      } catch (err) {
        alert('Error: ' + err.message);
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