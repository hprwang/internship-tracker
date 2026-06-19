(function () {
  'use strict';

  const GREEN = '#22C55E';
  const EMERALD = '#16A34A';
  const GREEN_GLOW = '#4ADE80';

  function clamp(n, a, b) { return Math.max(a, Math.min(b, n)); }

  function prefersReducedMotion() {
    try {
      return window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    } catch (e) {
      return false;
    }
  }

  function resizeCanvas(canvas, dpr) {
    const w = Math.max(1, Math.floor(window.innerWidth));
    const h = Math.max(1, Math.floor(window.innerHeight));
    canvas.width = Math.floor(w * dpr);
    canvas.height = Math.floor(h * dpr);
    canvas.style.width = w + 'px';
    canvas.style.height = h + 'px';
    return { w, h };
  }

  function hexToRgb(hex) {
    const s = hex.replace('#', '').trim();
    const full = s.length === 3 ? s.split('').map(ch => ch + ch).join('') : s;
    const n = parseInt(full, 16);
    return { r: (n >> 16) & 255, g: (n >> 8) & 255, b: n & 255 };
  }

  function drawGlow(ctx, x, y, radius, rgb, alpha) {
    ctx.globalAlpha = alpha;
    ctx.beginPath();
    ctx.fillStyle = `rgba(${rgb.r},${rgb.g},${rgb.b},1)`;
    ctx.arc(x, y, radius, 0, Math.PI * 2);
    ctx.fill();
  }

  function StarfieldParallax(canvas) {
    this.canvas = canvas;
    this.ctx = canvas.getContext('2d', { alpha: true, desynchronized: true });
    this.dpr = clamp(window.devicePixelRatio || 1, 1, 2);
    this.running = false;

    const reduced = prefersReducedMotion();

    // Layer counts tuned for performance.
    const base = reduced ? 110 : 240;
    this.layers = [
      { // Far
        count: Math.floor(base * 0.55),
        speed: 0.10,
        size: [0.6, 1.4],
        twinkle: 0.55,
        alpha: 0.65
      },
      { // Mid
        count: Math.floor(base * 0.30),
        speed: 0.22,
        size: [0.9, 1.9],
        twinkle: 0.85,
        alpha: 0.75
      },
      { // Near
        count: Math.floor(base * 0.15),
        speed: 0.40,
        size: [1.3, 2.4],
        twinkle: 1.00,
        alpha: 0.85
      }
    ];

    this.rgb1 = hexToRgb(GREEN);
    this.rgb2 = hexToRgb(EMERALD);
    this.rgb3 = hexToRgb(GREEN_GLOW);

    this.stars = [];
    this.particles = [];
    this.dust = [];

    this.lastT = 0;
    this.pointer = { x: 0.5, y: 0.5 };
    this.vx = 0;

    this.init();
  }

  StarfieldParallax.prototype.init = function () {
    this.rebuild();
    this.attach();
  };

  StarfieldParallax.prototype.rebuild = function () {
    const dpr = this.dpr;
    const { w, h } = resizeCanvas(this.canvas, dpr);

    const W = w;
    const H = h;

    this.stars.length = 0;
    for (const layer of this.layers) {
      for (let i = 0; i < layer.count; i++) {
        const depth = layer.speed;
        const x = Math.random() * W;
        const y = Math.random() * H;
        const s = layer.size[0] + Math.random() * (layer.size[1] - layer.size[0]);
        const phase = Math.random() * Math.PI * 2;
        const huePick = Math.random();
        const rgb = huePick < 0.45 ? this.rgb1 : (huePick < 0.75 ? this.rgb2 : this.rgb3);
        this.stars.push({ x, y, s, phase, depth, rgb, layer });
      }
    }

    const reduced = prefersReducedMotion();
    const particleCount = reduced ? 55 : 105;
    this.particles.length = 0;
    for (let i = 0; i < particleCount; i++) {
      this.particles.push({
        x: Math.random() * W,
        y: Math.random() * H,
        r: 0.6 + Math.random() * 1.3,
        vx: -0.05 - Math.random() * 0.10,
        vy: 0.05 + Math.random() * 0.10,
        a: 0.05 + Math.random() * 0.15,
        phase: Math.random() * Math.PI * 2,
        life: 0.4 + Math.random() * 0.8,
        rgb: (Math.random() < 0.5) ? this.rgb2 : this.rgb1,
      });
    }

    const dustCount = reduced ? 40 : 80;
    this.dust.length = 0;
    for (let i = 0; i < dustCount; i++) {
      this.dust.push({
        x: Math.random() * W,
        y: Math.random() * H,
        r: 0.8 + Math.random() * 2.2,
        speed: 0.08 + Math.random() * 0.25,
        a: 0.02 + Math.random() * 0.08,
        phase: Math.random() * Math.PI * 2,
        rgb: (Math.random() < 0.5) ? this.rgb3 : this.rgb2,
      });
    }

    this.W = W;
    this.H = H;
  };

  StarfieldParallax.prototype.attach = function () {
    window.addEventListener('resize', () => {
      this.dpr = clamp(window.devicePixelRatio || 1, 1, 2);
      this.rebuild();
    });

    // Subtle parallax via pointer.
    const onMove = (e) => {
      const x = e.clientX / Math.max(1, window.innerWidth);
      const y = e.clientY / Math.max(1, window.innerHeight);
      this.pointer.x = x;
      this.pointer.y = y;
    };

    window.addEventListener('mousemove', onMove, { passive: true });
    window.addEventListener('touchmove', (e) => {
      if (!e.touches || !e.touches.length) return;
      const t = e.touches[0];
      onMove({ clientX: t.clientX, clientY: t.clientY });
    }, { passive: true });
  };

  StarfieldParallax.prototype.start = function () {
    if (this.running) return;
    this.running = true;
    this.lastT = performance.now();
    this.frame();
  };

  StarfieldParallax.prototype.stop = function () {
    this.running = false;
  };

  StarfieldParallax.prototype.frame = function () {
    if (!this.running) return;

    const now = performance.now();
    const dt = Math.min(0.05, (now - this.lastT) / 1000);
    this.lastT = now;

    const ctx = this.ctx;
    const dpr = this.dpr;
    const Wpx = this.W;
    const Hpx = this.H;

    // Clear (with slight alpha for trails).
    ctx.setTransform(1, 0, 0, 1, 0, 0);
    ctx.globalCompositeOperation = 'source-over';
    ctx.fillStyle = 'rgba(5,5,5,0.28)';
    ctx.fillRect(0, 0, Wpx * dpr, Hpx * dpr);

    // Neon additive layer.
    ctx.globalCompositeOperation = 'lighter';

    // Pointer drift.
    const px = (this.pointer.x - 0.5);
    const py = (this.pointer.y - 0.5);

    const reduced = prefersReducedMotion();

    // Stars.
    for (const st of this.stars) {
      st.x += (-px * st.layer.speed * 18) * dt * 60;
      st.y += (py * st.layer.speed * 10 + st.layer.speed * 6) * dt * 60;

      if (st.y > Hpx + 10) {
        st.y = -10;
        st.x = Math.random() * Wpx;
      }
      if (st.x < -10) {
        st.x = Wpx + 10;
      }
      if (st.x > Wpx + 10) {
        st.x = -10;
      }

      const tw = 0.65 + 0.35 * Math.sin(st.phase + now * 0.006 * st.layer.twinkle);
      const alpha = st.layer.alpha * tw;
      const r = st.s * (0.9 + 0.2 * tw);

      const x = st.x * dpr;
      const y = st.y * dpr;

      // Light trails for near layer.
      if (!reduced && st.layer.speed >= 0.22) {
        const streak = st.layer.speed * 10;
        ctx.globalAlpha = alpha * 0.65;
        ctx.strokeStyle = `rgba(${st.rgb.r},${st.rgb.g},${st.rgb.b},1)`;
        ctx.lineWidth = Math.max(1, r * 0.9);
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(x + px * streak * dpr, y - py * streak * dpr);
        ctx.stroke();
      }

      drawGlow(ctx, x, y, r * 0.65 * dpr, st.rgb, alpha);

      // Crisp twinkle core.
      ctx.globalAlpha = alpha * 0.9;
      ctx.fillStyle = `rgba(255,255,255,1)`;
      ctx.beginPath();
      ctx.arc(x, y, Math.max(0.6, r * 0.25) * dpr, 0, Math.PI * 2);
      ctx.fill();
    }

    // Dust (very soft).
    for (const d of this.dust) {
      d.x += px * d.speed * 12 * dt * 60;
      d.y += d.speed * 2 * dt * 60;
      if (d.y > Hpx + 30) { d.y = -30; d.x = Math.random() * Wpx; }
      const x = d.x * dpr;
      const y = d.y * dpr;
      const tw = 0.7 + 0.3 * Math.sin(d.phase + now * 0.002);
      const alpha = d.a * tw;
      drawGlow(ctx, x, y, d.r * dpr, d.rgb, alpha);
    }

    // Particles (sparklets).
    for (const p of this.particles) {
      p.x += p.vx * dt * 60 + px * dt * 20;
      p.y += p.vy * dt * 60 + py * dt * 14;

      if (p.y > Hpx + 20) {
        p.y = -10;
        p.x = Math.random() * Wpx;
      }
      if (p.x < -20) p.x = Wpx + 20;
      if (p.x > Wpx + 20) p.x = -20;

      const x = p.x * dpr;
      const y = p.y * dpr;
      const tw = 0.6 + 0.4 * Math.sin(p.phase + now * 0.01);
      const alpha = p.a * tw;

      if (!reduced) {
        ctx.globalAlpha = alpha;
        ctx.strokeStyle = `rgba(${p.rgb.r},${p.rgb.g},${p.rgb.b},1)`;
        ctx.lineWidth = Math.max(0.8, p.r * dpr * 0.45);
        ctx.beginPath();
        ctx.moveTo(x, y);
        ctx.lineTo(x - p.vx * 18 * dpr, y + p.vy * 10 * dpr);
        ctx.stroke();
      }

      drawGlow(ctx, x, y, p.r * dpr, p.rgb, alpha);
    }

    requestAnimationFrame(this.frame.bind(this));
  };

  function initStarfield() {
    const canvas = document.getElementById('starfield');
    if (!canvas) return;

    // Ensure it sits under UI.
    canvas.setAttribute('aria-hidden', 'true');

    // If reduced motion, render a single frame and stop.
    const reduced = prefersReducedMotion();

    const sf = new StarfieldParallax(canvas);
    if (reduced) {
      sf.running = true;
      sf.frame();
      setTimeout(() => sf.stop(), 300);
    } else {
      sf.start();
    }

    // Pause when tab hidden to save CPU.
    document.addEventListener('visibilitychange', () => {
      if (!sf) return;
      if (document.hidden) sf.stop();
      else {
        sf.running = true;
        sf.lastT = performance.now();
        sf.frame();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', initStarfield);
})();

