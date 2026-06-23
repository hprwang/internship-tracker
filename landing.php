<?php
session_start();
require_once 'php/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Internship Tracking System - Home</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/style.css">
  <style>
    :root {
      --bg-primary: #0b0f0c;
      --bg-secondary: #111612;
      --bg-card: #151915;
      --bg-card-hover: #1a1f1b;
      --neon-green: #00ff66;
      --neon-green-dim: #00cc52;
      --neon-green-glow: rgba(0, 255, 102, 0.4);
      --neon-green-subtle: rgba(0, 255, 102, 0.08);
      --text-primary: #ffffff;
      --text-secondary: #a8aba6;
      --text-muted: #6b706b;
      --border: #2a2f2b;
      --border-glow: rgba(0, 255, 102, 0.3);
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
      font-family: 'Poppins', Inter, system-ui, -apple-system, sans-serif;
      background: var(--bg-primary);
      color: var(--text-primary);
      min-height: 100vh;
      line-height: 1.6;
      -webkit-font-smoothing: antialiased;
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
      overflow: hidden;
    }

    .bg-effects::before {
      content: '';
      position: absolute;
      top: -50%;
      left: -50%;
      width: 200%;
      height: 200%;
      background:
        radial-gradient(ellipse 80% 50% at 15% 0%, rgba(0, 255, 102, 0.06) 0%, transparent 50%),
        radial-gradient(ellipse 60% 40% at 85% 100%, rgba(0, 255, 102, 0.04) 0%, transparent 50%);
    }

    .bg-effects::after {
      content: '';
      position: absolute;
      top: 30%;
      left: 10%;
      width: 400px;
      height: 400px;
      background: var(--neon-green);
      opacity: 0.025;
      filter: blur(120px);
      border-radius: 50%;
    }

    /* Glowing Lines */
    .glow-lines {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      pointer-events: none;
      z-index: 0;
    }

    .glow-line {
      position: absolute;
      background: linear-gradient(90deg, transparent, var(--neon-green), transparent);
      opacity: 0.15;
      height: 1px;
    }

    .glow-line-1 {
      top: 25%;
      left: 0;
      width: 100%;
      animation: glowLineMove 8s ease-in-out infinite;
    }

    .glow-line-2 {
      top: 75%;
      left: 0;
      width: 100%;
      animation: glowLineMove 12s ease-in-out infinite reverse;
    }

    @keyframes glowLineMove {
      0%, 100% { opacity: 0.05; }
      50% { opacity: 0.2; }
    }

    /* Navigation */
    .navbar {
      position: fixed;
      top: 0;
      left: 0;
      right: 0;
      z-index: 100;
      padding: 1.25rem 3rem;
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: rgba(11, 15, 12, 0.85);
      backdrop-filter: blur(20px);
      -webkit-backdrop-filter: blur(20px);
      border-bottom: 1px solid rgba(0, 255, 102, 0.08);
    }

    .navbar-logo {
      display: flex;
      align-items: center;
      gap: 0.75rem;
    }

    .logo-icon {
      width: 42px;
      height: 42px;
      background: linear-gradient(135deg, var(--neon-green), #00cc52);
      border-radius: 10px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.3rem;
      box-shadow: 0 0 25px var(--neon-green-glow);
    }

    .logo-text {
      font-size: 1.35rem;
      font-weight: 800;
      color: var(--text-primary);
      letter-spacing: -0.02em;
    }

    .logo-text span {
      color: var(--neon-green);
    }

    .navbar-links {
      display: flex;
      align-items: center;
      gap: 2.5rem;
    }

    .nav-link {
      color: var(--text-secondary);
      text-decoration: none;
      font-weight: 500;
      font-size: 0.95rem;
      transition: all 0.3s ease;
      position: relative;
    }

    .nav-link::after {
      content: '';
      position: absolute;
      bottom: -4px;
      left: 0;
      width: 0;
      height: 2px;
      background: var(--neon-green);
      transition: width 0.3s ease;
      box-shadow: 0 0 10px var(--neon-green-glow);
    }

    .nav-link:hover {
      color: var(--neon-green);
    }

    .nav-link:hover::after {
      width: 100%;
    }

    .nav-link.btn-login {
      padding: 0.6rem 1.5rem;
      background: transparent;
      border: 1px solid var(--neon-green);
      border-radius: 8px;
      color: var(--neon-green);
    }

    .nav-link.btn-login:hover {
      background: var(--neon-green);
      color: var(--bg-primary);
      box-shadow: 0 0 25px var(--neon-green-glow);
    }

    .nav-link.btn-login::after {
      display: none;
    }

    /* Hero Section */
    .hero {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 8rem 3rem 5rem;
      text-align: center;
      position: relative;
      z-index: 1;
    }

    .hero-bg {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      z-index: -1;
      overflow: hidden;
    }

    /* Placeholder for campus image - using abstract illustration instead */
    .hero-illustration {
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 100%;
      max-width: 900px;
      height: 500px;
      opacity: 0.15;
      background:
        radial-gradient(ellipse 60% 40% at 20% 60%, var(--neon-green) 0%, transparent 50%),
        radial-gradient(ellipse 50% 35% at 80% 30%, rgba(0, 255, 102, 0.5) 0%, transparent 50%),
        url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 800 500'%3E%3Cg fill='%2300ff66' fill-opacity='0.1'%3E%3Crect x='50' y='350' width='120' height='150' rx='8'/%3E%3Crect x='200' y='300' width='140' height='200' rx='8'/%3E%3Crect x='380' y='320' width='130' height='180' rx='8'/%3E%3Crect x='550' y='280' width='150' height='220' rx='8'/%3E%3Ccircle cx='110' cy='330' r='30'/%3E%3Ccircle cx='270' cy='280' r='35'/%3E%3Ccircle cx='445' cy='300' r='32'/%3E%3Ccircle cx='625' cy='260' r='38'/%3E%3Crect x='80' y='150' width='80' height='100' rx='4'/%3E%3Crect x='230' y='120' width='90' height='130' rx='4'/%3E%3Crect x='400' y='140' width='85' height='110' rx='4'/%3E%3Crect x='570' y='100' width='95' height='150' rx='4'/%3E%3C/g%3E%3C/svg%3E");
      background-size: cover;
      background-position: center;
    }

    .hero-overlay {
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(180deg, var(--bg-primary) 0%, rgba(11, 15, 12, 0.9) 50%, var(--bg-primary) 100%);
      z-index: 0;
    }

    .hero-badge {
      display: inline-flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      background: var(--neon-green-subtle);
      border: 1px solid rgba(0, 255, 102, 0.2);
      border-radius: 999px;
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--neon-green);
      margin-bottom: 1.5rem;
      animation: fadeInDown 0.6s ease;
    }

    .hero-badge::before {
      content: '';
      width: 6px;
      height: 6px;
      background: var(--neon-green);
      border-radius: 50%;
      box-shadow: 0 0 10px var(--neon-green);
      animation: pulse 2s ease-in-out infinite;
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.5; transform: scale(1.2); }
    }

    .hero-title {
      font-size: 4.5rem;
      font-weight: 900;
      line-height: 1.1;
      margin-bottom: 1.5rem;
      letter-spacing: -0.03em;
      animation: fadeInUp 0.8s ease 0.2s both;
    }

    .hero-title .highlight {
      background: linear-gradient(135deg, var(--neon-green), #00cc52);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      filter: drop-shadow(0 0 30px var(--neon-green-glow));
    }

    .hero-subtitle {
      font-size: 1.35rem;
      color: var(--text-secondary);
      max-width: 600px;
      margin-bottom: 2.5rem;
      animation: fadeInUp 0.8s ease 0.4s both;
    }

    .hero-subtitle span {
      color: var(--neon-green);
      font-weight: 600;
    }

    .hero-cta {
      display: flex;
      align-items: center;
      gap: 1rem;
      animation: fadeInUp 0.8s ease 0.6s both;
    }

    .btn-hero {
      padding: 1rem 2rem;
      background: linear-gradient(135deg, var(--neon-green), #00cc52);
      color: var(--bg-primary);
      border: none;
      border-radius: 10px;
      font-family: inherit;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      box-shadow: 0 10px 30px var(--neon-green-glow);
    }

    .btn-hero:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 40px var(--neon-green-glow);
    }

    .btn-hero-secondary {
      padding: 1rem 2rem;
      background: transparent;
      border: 1px solid var(--border);
      color: var(--text-secondary);
      border-radius: 10px;
      font-family: inherit;
      font-size: 1rem;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
    }

    .btn-hero-secondary:hover {
      border-color: var(--neon-green);
      color: var(--neon-green);
    }

    /* Features Section */
    .features {
      padding: 5rem 3rem;
      position: relative;
      z-index: 1;
      background: var(--bg-secondary);
    }

    .features-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .section-header {
      text-align: center;
      margin-bottom: 4rem;
    }

    .section-label {
      font-size: 0.8rem;
      font-weight: 700;
      letter-spacing: 0.2em;
      color: var(--neon-green);
      text-transform: uppercase;
      margin-bottom: 1rem;
    }

    .section-title {
      font-size: 2.5rem;
      font-weight: 800;
      letter-spacing: -0.02em;
    }

    .features-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 2rem;
    }

    .feature-card {
      background: var(--bg-card);
      border: 1px solid var(--border);
      border-radius: 16px;
      padding: 2.5rem 2rem;
      text-align: center;
      transition: all 0.4s ease;
      position: relative;
      overflow: hidden;
    }

    .feature-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 50%;
      transform: translateX(-50%);
      width: 0;
      height: 3px;
      background: var(--neon-green);
      transition: width 0.4s ease;
      box-shadow: 0 0 20px var(--neon-green-glow);
    }

    .feature-card:hover {
      border-color: var(--neon-green);
      transform: translateY(-8px);
      box-shadow: 0 20px 50px rgba(0, 0, 0, 0.3);
    }

    .feature-card:hover::before {
      width: 100%;
    }

    .feature-icon {
      width: 70px;
      height: 70px;
      background: var(--neon-green-subtle);
      border: 1px solid rgba(0, 255, 102, 0.2);
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      font-size: 2rem;
    }

    .feature-title {
      font-size: 1.35rem;
      font-weight: 700;
      margin-bottom: 0.75rem;
    }

    .feature-desc {
      color: var(--text-secondary);
      font-size: 0.95rem;
    }

    /* Login Cards Section */
    .login-section {
      padding: 6rem 3rem;
      position: relative;
      z-index: 1;
    }

    .login-container {
      max-width: 1200px;
      margin: 0 auto;
    }

    .login-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 2rem;
    }

    .login-card {
      background: linear-gradient(145deg, var(--bg-card), rgba(21, 25, 21, 0.8));
      border: 1px solid var(--border);
      border-radius: 20px;
      padding: 2.5rem 2rem;
      text-align: center;
      transition: all 0.4s ease;
      position: relative;
      overflow: hidden;
      z-index: 1;
    }

    .login-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: linear-gradient(145deg, rgba(0, 255, 102, 0.05), transparent);
      opacity: 0;
      transition: opacity 0.4s ease;
    }

    .login-card:hover {
      border-color: var(--neon-green);
      transform: translateY(-10px);
      box-shadow:
        0 25px 60px rgba(0, 0, 0, 0.4),
        0 0 30px var(--neon-green-subtle),
        inset 0 0 30px var(--neon-green-subtle);
    }

    .login-card:hover::before {
      opacity: 1;
    }

    .login-card-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, var(--neon-green-subtle), rgba(0, 255, 102, 0.03));
      border: 1px solid rgba(0, 255, 102, 0.15);
      border-radius: 20px;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 1.5rem;
      font-size: 2.5rem;
      color: var(--neon-green);
      transition: all 0.4s ease;
    }

    .login-card:hover .login-card-icon {
      background: var(--neon-green);
      color: var(--bg-primary);
      box-shadow: 0 0 30px var(--neon-green-glow);
    }

    .login-card-title {
      font-size: 1.5rem;
      font-weight: 800;
      margin-bottom: 0.75rem;
    }

    .login-card-desc {
      color: var(--text-secondary);
      font-size: 0.95rem;
      margin-bottom: 1.5rem;
      line-height: 1.6;
    }

    .btn-login-card {
      display: inline-block;
      width: 100%;
      padding: 1rem 1.5rem;
      background: transparent;
      border: 2px solid var(--neon-green);
      border-radius: 10px;
      color: var(--neon-green);
      font-family: inherit;
      font-size: 1rem;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      position: relative;
      z-index: 10;
    }

    .btn-login-card:hover {
      background: var(--neon-green);
      color: var(--bg-primary);
      box-shadow: 0 0 30px var(--neon-green-glow);
      transform: scale(1.02);
    }

    /* Footer */
    .footer {
      padding: 3rem;
      text-align: center;
      border-top: 1px solid var(--border);
      position: relative;
      z-index: 1;
    }

    .footer-text {
      color: var(--text-muted);
      font-size: 0.9rem;
    }

    .footer-text span {
      color: var(--neon-green);
    }

    /* Animations */
    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    @keyframes fadeInDown {
      from {
        opacity: 0;
        transform: translateY(-20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Responsive */
    @media (max-width: 1024px) {
      .features-grid,
      .login-grid {
        grid-template-columns: 1fr;
        max-width: 450px;
        margin: 0 auto;
      }

      .hero-title {
        font-size: 3rem;
      }

      .features,
      .login-section {
        padding: 4rem 1.5rem;
      }
    }

    @media (max-width: 768px) {
      .navbar {
        padding: 1rem 1.5rem;
      }

      .navbar-links {
        display: none;
      }

      .hero {
        padding: 7rem 1.5rem 4rem;
      }

      .hero-title {
        font-size: 2.25rem;
      }

      .hero-subtitle {
        font-size: 1.1rem;
      }

      .hero-cta {
        flex-direction: column;
      }

      .section-title {
        font-size: 2rem;
      }
    }
  </style>
</head>
<body>

  <!-- Background Effects -->
  <div class="bg-effects"></div>
  <div class="glow-lines">
    <div class="glow-line glow-line-1"></div>
    <div class="glow-line glow-line-2"></div>
  </div>

  <!-- Navigation -->
  <nav class="navbar">
    <div class="navbar-logo">
      <div class="logo-icon">📋</div>
      <div class="logo-text">Intern<span>Track</span></div>
    </div>
    <div class="navbar-links">
      <a href="#features" class="nav-link">Features</a>
      <a href="#login" class="nav-link">About System</a>
      <a href="#login" class="nav-link btn-login">Get Started</a>
    </div>
  </nav>

  <!-- Hero Section -->
  <section class="hero">
    <div class="hero-bg">
      <div class="hero-illustration"></div>
      <div class="hero-overlay"></div>
    </div>
    <div class="hero-badge">Platform Live</div>
    <h1 class="hero-title">
      Welcome to <span class="highlight">Internship Tracking System</span>
    </h1>
    <p class="hero-subtitle">
      Your complete platform for managing internships across all roles — <span>Students</span> can track their journey, <span>Companies</span> can find talent, and <span>Admins</span> can oversee everything.
    </p>
    <div class="hero-cta">
      <a href="#login" class="btn-hero">Get Started</a>
      <a href="#features" class="btn-hero-secondary">Learn More</a>
    </div>
  </section>

  <!-- Features Section -->
  <section class="features" id="features">
    <div class="features-container">
      <div class="section-header">
        <p class="section-label">Why Choose Us</p>
        <h2 class="section-title">Everything You Need</h2>
      </div>
      <div class="features-grid">
        <div class="feature-card">
          <div class="feature-icon">📊</div>
          <h3 class="feature-title">Track Progress</h3>
          <p class="feature-desc">Monitor your internship journey in real-time with detailed dashboards and progress indicators.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">🔗</div>
          <h3 class="feature-title">Seamless Connection</h3>
          <p class="feature-desc">Connect effortlessly with companies, supervisors, and fellow students in one unified platform.</p>
        </div>
        <div class="feature-card">
          <div class="feature-icon">🔒</div>
          <h3 class="feature-title">Secure & Reliable</h3>
          <p class="feature-desc">Your data is protected with enterprise-grade security and reliable infrastructure.</p>
        </div>
      </div>
    </div>
  </section>

  <!-- Login Cards Section -->
  <section class="login-section" id="login">
    <div class="login-container">
      <div class="section-header">
        <p class="section-label">Access Portal</p>
        <h2 class="section-title">Choose Your Role</h2>
      </div>
      <div class="login-grid">
        <div class="login-card">
          <div class="login-card-icon">🎓</div>
          <h3 class="login-card-title">Student Login</h3>
          <p class="login-card-desc">Access your applications, track progress, submit reports, and connect with mentors.</p>
          <a href="index.php" class="btn-login-card">Login as Student</a>
        </div>
        <div class="login-card">
          <div class="login-card-icon">🏢</div>
          <h3 class="login-card-title">Company Login</h3>
          <p class="login-card-desc">Post opportunities, review applications, manage interns, and connect with universities.</p>
          <a href="company-login.php" class="btn-login-card">Login as Company</a>
        </div>
        <div class="login-card">
          <div class="login-card-icon">🛡️</div>
          <h3 class="login-card-title">Admin Login</h3>
          <p class="login-card-desc">Oversee all operations, manage users, generate reports, and configure system settings.</p>
          <a href="php/admin_login.php" class="btn-login-card">Login as Admin</a>
        </div>
      </div>
    </div>
  </section>

  <!-- Footer -->
  <footer class="footer">
    <p class="footer-text">
      &copy; 2026 <span>InternTrack</span>. All rights reserved.
    </p>
  </footer>

  <script>
    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
      anchor.addEventListener('click', function (e) {
        e.preventDefault();
        const target = document.querySelector(this.getAttribute('href'));
        if (target) {
          target.scrollIntoView({
            behavior: 'smooth',
            block: 'start'
          });
        }
      });
    });

    // Add subtle parallax effect on mouse move
    document.addEventListener('mousemove', (e) => {
      const cards = document.querySelectorAll('.feature-card, .login-card');
      const x = e.clientX / window.innerWidth;
      const y = e.clientY / window.innerHeight;

      cards.forEach((card, index) => {
        const depth = (index + 1) * 2;
        const moveX = (x - 0.5) * depth;
        const moveY = (y - 0.5) * depth;
        card.style.transform = `translate(${moveX}px, ${moveY}px)`;
      });
    });
  </script>

</body>
</html>