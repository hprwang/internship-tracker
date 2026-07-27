/**
 * InternTrack — Interactive Enhancement Module
 * Adds super-interactive features: keyboard shortcuts, animations,
 * loading skeletons, auto-save, drag-drop, charts, and more.
 */

/* ── Interactive Engine ─────────────────────────── */
const Interactive = {
  initialized: false,
  debounceTimers: {},
  pollIntervals: [],
  shortcuts: {},
};

/* ── Initialize All Interactive Features ─────────── */
Interactive.init = function() {
  if (this.initialized) return;
  this.initialized = true;

  this.setupKeyboardShortcuts();
  this.setupScrollToTop();
  this.setupProgressBar();
  this.setupBreadcrumb();
  this.setupConnectionMonitor();
  this.setupInlineEdit();
  this.setupSidebarToggle();
  this.setupSmoothScroll();
  this.setupTableResponsive();
  this.setupBulkActions();
  this.setupFilePreview();
  this.setupAutoRefresh();
  this.setupTouchOptimization();

  console.log('[Interactive] All features initialized');
};

/* ── Debounce Utility ────────────────────────────── */
Interactive.debounce = function(key, fn, delay = 300) {
  clearTimeout(this.debounceTimers[key]);
  this.debounceTimers[key] = setTimeout(fn, delay);
};

/* ── Keyboard Shortcuts ──────────────────────────── */
Interactive.setupKeyboardShortcuts = function() {
  this.shortcuts = {
    'Escape': () => {
      // Close all modals
      document.querySelectorAll('.modal-overlay.open, #modal.show, .fullscreen-preview.open, .shortcuts-help.open')
        .forEach(el => {
          el.classList.remove('open', 'show');
          document.body.style.overflow = '';
        });
    },
    'Ctrl+k': () => {
      // Focus global search
      const search = document.querySelector('.search-box input, .search-field input, #search-input');
      if (search) { search.focus(); search.select(); }
    },
    '?': () => {
      // Show keyboard shortcuts help
      this.showShortcutsHelp();
    },
    'n': () => {
      // Find and click "Add" / "New" button
      const addBtn = document.querySelector('.add-btn, .btn-primary:not(.nav-item)');
      if (addBtn && addBtn.offsetParent !== null) addBtn.click();
    },
  };

  document.addEventListener('keydown', (e) => {
    // Don't trigger shortcuts when typing in inputs
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA' || e.target.tagName === 'SELECT') {
      if (e.key === 'Escape') {
        const modal = e.target.closest('.modal-overlay, #modal');
        if (modal) { e.target.blur(); }
      }
      return;
    }

    const key = e.ctrlKey && e.key === 'k' ? 'Ctrl+k' : e.key;
    if (this.shortcuts[key]) {
      e.preventDefault();
      this.shortcuts[key]();
    }
  });
};

Interactive.showShortcutsHelp = function() {
  let overlay = document.getElementById('shortcuts-help');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.id = 'shortcuts-help';
    overlay.className = 'shortcuts-help';
    overlay.innerHTML = `
      <div class="shortcuts-panel">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1.5rem">
          <h2 style="margin:0;font-size:1.2rem;font-weight:700;color:var(--green-neon, #22C55E)">
            ⌨️ Keyboard Shortcuts
          </h2>
          <button onclick="document.getElementById('shortcuts-help').classList.remove('open')"
                  style="background:none;border:none;color:var(--text-muted);font-size:1.5rem;cursor:pointer">×</button>
        </div>
        <div class="shortcut-row"><span>Close modal / panel</span> <kbd>Esc</kbd></div>
        <div class="shortcut-row"><span>Search</span> <kbd>Ctrl+K</kbd></div>
        <div class="shortcut-row"><span>Add new item</span> <kbd>N</kbd></div>
        <div class="shortcut-row"><span>Keyboard shortcuts</span> <kbd>?</kbd></div>
      </div>
    `;
    document.body.appendChild(overlay);

    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) overlay.classList.remove('open');
    });
  }
  overlay.classList.add('open');
};

/* ── Scroll to Top Button ────────────────────────── */
Interactive.setupScrollToTop = function() {
  let btn = document.getElementById('scroll-top-btn');
  if (!btn) {
    btn = document.createElement('button');
    btn.id = 'scroll-top-btn';
    btn.className = 'scroll-top-btn';
    btn.innerHTML = '↑';
    btn.setAttribute('aria-label', 'Scroll to top');
    document.body.appendChild(btn);

    btn.addEventListener('click', () => {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  }

  let ticking = false;
  window.addEventListener('scroll', () => {
    if (!ticking) {
      requestAnimationFrame(() => {
        btn.classList.toggle('visible', window.scrollY > 300);
        ticking = false;
      });
      ticking = true;
    }
  }, { passive: true });
};

/* ── Page Load Progress Bar ──────────────────────── */
Interactive.setupProgressBar = function() {
  let bar = document.getElementById('page-progress');
  if (!bar) {
    bar = document.createElement('div');
    bar.id = 'page-progress';
    bar.className = 'page-progress';
    document.body.prepend(bar);
  }

  // Simulate progress on page load
  bar.style.width = '30%';
  document.addEventListener('readystatechange', () => {
    if (document.readyState === 'interactive') bar.style.width = '60%';
    if (document.readyState === 'complete') {
      bar.style.width = '100%';
      setTimeout(() => { bar.style.width = '0%'; }, 500);
    }
  });
};

/* ── Breadcrumb Builder ──────────────────────────── */
Interactive.setupBreadcrumb = function() {
  const container = document.querySelector('.breadcrumb-container');
  if (!container) return;

  const path = window.location.pathname;
  const parts = path.replace(/\.php$/, '').split('/').filter(Boolean);
  const pageNames = {
    'dashboard': 'Dashboard',
    'internships': 'My Internships',
    'progress': 'Progress Logs',
    'companies': 'Companies',
    'profile': 'Profile',
    'admin_dashboard': 'Admin Dashboard',
    'admin_students': 'Students',
    'admin_companies': 'Companies',
    'admin_internships': 'Internships',
    'admin_reports': 'Reports',
    'admin_settings': 'Settings',
    'internship-details': 'Details',
    'index': 'Login',
    'register': 'Register',
    'landing': 'Home',
    'change_password': 'Change Password',
    'reset_password': 'Reset Password',
  };

  let html = '<nav class="breadcrumb" aria-label="Breadcrumb">';
  html += '<a href="dashboard.php"><i class="fas fa-home"></i></a>';
  parts.forEach((part, i) => {
    const name = pageNames[part] || part.replace(/[-_]/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
    const isLast = i === parts.length - 1;
    html += `<span class="separator">›</span>`;
    if (isLast) {
      html += `<span class="current">${name}</span>`;
    } else {
      html += `<a href="${part}.php">${name}</a>`;
    }
  });
  html += '</nav>';
  container.innerHTML = html;
};

/* ── Connection Monitor ──────────────────────────── */
Interactive.setupConnectionMonitor = function() {
  let statusEl = document.getElementById('connection-status');
  if (!statusEl) {
    statusEl = document.createElement('div');
    statusEl.id = 'connection-status';
    statusEl.className = 'connection-status';
    document.body.prepend(statusEl);
  }

  let wasOffline = false;
  const updateStatus = () => {
    const online = navigator.onLine;
    if (!online) {
      wasOffline = true;
      statusEl.textContent = '🔴 You are offline. Some features may be unavailable.';
      statusEl.className = 'connection-status offline';
    } else if (wasOffline) {
      wasOffline = false;
      statusEl.textContent = '🟢 Back online!';
      statusEl.className = 'connection-status back-online';
      setTimeout(() => { statusEl.className = 'connection-status'; }, 3000);
    } else {
      statusEl.className = 'connection-status';
    }
  };

  window.addEventListener('online', updateStatus);
  window.addEventListener('offline', updateStatus);
  updateStatus();
};

/* ── Inline Editing ──────────────────────────────── */
Interactive.setupInlineEdit = function() {
  document.querySelectorAll('.inline-edit').forEach(el => {
    el.addEventListener('click', function() {
      if (this.tagName !== 'INPUT' && this.tagName !== 'TEXTAREA' && !this.querySelector('input')) {
        const text = this.textContent.trim();
        const isTextarea = this.dataset.multiline === 'true';
        const input = document.createElement(isTextarea ? 'textarea' : 'input');
        input.type = 'text';
        input.className = 'inline-edit';
        input.value = text;
        input.style.width = Math.max(text.length * 0.8 + 2, 10) + 'rem';

        if (isTextarea) {
          input.style.width = '100%';
          input.style.minHeight = '60px';
          input.style.resize = 'vertical';
        }

        this.replaceWith(input);
        input.focus();
        input.select();

        const save = () => {
          const span = document.createElement('span');
          span.className = 'inline-edit';
          span.textContent = input.value || text;
          span.dataset.multiline = isTextarea ? 'true' : '';
          input.replaceWith(span);
          Interactive.setupInlineEdit();
          // Trigger save event
          span.dispatchEvent(new CustomEvent('inline-save', { detail: { value: input.value } }));
        };

        input.addEventListener('blur', save);
        input.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' && !e.shiftKey && !isTextarea) save();
          if (e.key === 'Escape') { input.value = text; input.blur(); }
        });
      }
    });
  });
};

/* ── Sidebar Toggle ──────────────────────────────── */
Interactive.setupSidebarToggle = function() {
  const sidebar = document.querySelector('.sidebar');
  if (!sidebar) return;

  // Create hamburger if not exists
  let hamburger = document.querySelector('.hamburger');
  if (!hamburger) {
    hamburger = document.createElement('button');
    hamburger.className = 'hamburger';
    hamburger.setAttribute('aria-label', 'Toggle sidebar');
    hamburger.innerHTML = '<span></span><span></span><span></span>';

    // Insert before sidebar in parent
    const topbar = document.querySelector('.topbar, .top-header, .page-header');
    if (topbar) {
      topbar.prepend(hamburger);
    } else {
      document.querySelector('.main-content')?.prepend(hamburger);
    }
  }

  // Create overlay
  let overlay = document.querySelector('.sidebar-overlay');
  if (!overlay) {
    overlay = document.createElement('div');
    overlay.className = 'sidebar-overlay';
    document.body.appendChild(overlay);
  }

  hamburger.addEventListener('click', () => {
    hamburger.classList.toggle('active');
    sidebar.classList.toggle('open');
    overlay.classList.toggle('active');
    document.body.style.overflow = sidebar.classList.contains('open') ? 'hidden' : '';
  });

  overlay.addEventListener('click', () => {
    hamburger.classList.remove('active');
    sidebar.classList.remove('open');
    overlay.classList.remove('active');
    document.body.style.overflow = '';
  });

  // Close sidebar on nav item click (mobile)
  sidebar.querySelectorAll('.nav-item').forEach(item => {
    item.addEventListener('click', () => {
      if (window.innerWidth <= 768) {
        hamburger.classList.remove('active');
        sidebar.classList.remove('open');
        overlay.classList.remove('active');
        document.body.style.overflow = '';
      }
    });
  });
};

/* ── Smooth Scroll for anchor links ──────────────── */
Interactive.setupSmoothScroll = function() {
  document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function(e) {
      const target = document.querySelector(this.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
};

/* ── Responsive Table Wrapper ────────────────────── */
Interactive.setupTableResponsive = function() {
  document.querySelectorAll('.data-table, table').forEach(table => {
    if (!table.closest('.table-responsive') && !table.closest('.table-wrapper')) {
      const wrapper = document.createElement('div');
      wrapper.className = 'table-responsive';
      table.parentNode.insertBefore(wrapper, table);
      wrapper.appendChild(table);
    }
  });
};

/* ── Bulk Actions ──────────────────────────────────── */
Interactive.setupBulkActions = function() {
  const tables = document.querySelectorAll('.data-table');
  tables.forEach(table => {
    if (table.dataset.bulkSetup) return;
    table.dataset.bulkSetup = 'true';

    const rows = table.querySelectorAll('tbody tr');
    if (rows.length === 0) return;

    const th = table.querySelector('thead tr');
    if (!th) return;

    // Add checkbox column header
    const checkAll = document.createElement('th');
    checkAll.style.width = '40px';
    checkAll.innerHTML = '<span class="animated-checkbox" id="check-all" role="checkbox" tabindex="0"></span>';
    th.insertBefore(checkAll, th.firstChild);

    // Create bulk action bar
    let bar = document.querySelector('.bulk-action-bar');
    if (!bar) {
      bar = document.createElement('div');
      bar.className = 'bulk-action-bar';
      bar.innerHTML = `
        <span class="bulk-count">Selected: <strong id="bulk-count-val">0</strong></span>
        <div class="bulk-actions">
          <button class="btn btn-sm btn-danger" id="bulk-delete" disabled>Delete Selected</button>
        </div>
      `;
      document.body.appendChild(bar);
    }

    // Add checkboxes to each row
    rows.forEach(row => {
      const td = document.createElement('td');
      td.style.width = '40px';
      td.innerHTML = '<span class="animated-checkbox" role="checkbox" tabindex="0"></span>';
      row.insertBefore(td, row.firstChild);
      row.dataset.selected = 'false';
    });

    // Check all logic
    const checkAllEl = document.getElementById('check-all');
    checkAllEl.addEventListener('click', function() {
      const checked = !this.classList.contains('checked');
      this.classList.toggle('checked');
      rows.forEach(row => {
        const cb = row.querySelector('.animated-checkbox');
        cb.classList.toggle('checked', checked);
        row.dataset.selected = checked ? 'true' : 'false';
      });
      updateBulkBar();
    });

    // Individual checkboxes
    rows.forEach(row => {
      const cb = row.querySelector('.animated-checkbox');
      cb.addEventListener('click', function(e) {
        e.stopPropagation();
        this.classList.toggle('checked');
        row.dataset.selected = this.classList.contains('checked') ? 'true' : 'false';
        updateBulkBar();
      });
    });

    const updateBulkBar = () => {
      const selected = document.querySelectorAll('.data-table tbody tr[data-selected="true"]');
      const count = selected.length;
      const countEl = document.getElementById('bulk-count-val');
      const deleteBtn = document.getElementById('bulk-delete');
      if (countEl) countEl.textContent = count;
      bar.classList.toggle('visible', count > 0);
      if (deleteBtn) deleteBtn.disabled = count === 0;
    };

    // Bulk delete
    document.getElementById('bulk-delete')?.addEventListener('click', () => {
      const selected = document.querySelectorAll('.data-table tbody tr[data-selected="true"]');
      if (selected.length === 0) return;
      if (!confirm(`Delete ${selected.length} selected items?`)) return;

      selected.forEach(row => {
        const deleteBtn = row.querySelector('.action-btn.danger, .btn-danger');
        if (deleteBtn) deleteBtn.click();
      });
    });
  });
};

/* ── Fullscreen Preview ───────────────────────────── */
Interactive.setupFilePreview = function() {
  document.querySelectorAll('[data-preview]').forEach(el => {
    el.addEventListener('click', function(e) {
      e.preventDefault();
      const src = this.getAttribute('href') || this.dataset.src;
      const type = this.dataset.preview || 'image';

      let overlay = document.getElementById('fullscreen-preview');
      if (!overlay) {
        overlay = document.createElement('div');
        overlay.id = 'fullscreen-preview';
        overlay.className = 'fullscreen-preview';
        overlay.innerHTML = `
          <button class="preview-close">×</button>
          <div class="preview-content"></div>
        `;
        document.body.appendChild(overlay);

        overlay.querySelector('.preview-close').addEventListener('click', () => {
          overlay.classList.remove('open');
          document.body.style.overflow = '';
        });

        overlay.addEventListener('click', (e) => {
          if (e.target === overlay) {
            overlay.classList.remove('open');
            document.body.style.overflow = '';
          }
        });

        document.addEventListener('keydown', (e) => {
          if (e.key === 'Escape') overlay.classList.remove('open');
        });
      }

      const content = overlay.querySelector('.preview-content');

      if (type === 'image') {
        content.innerHTML = `<img src="${src}" alt="Preview" loading="lazy">`;
      } else if (type === 'pdf') {
        content.innerHTML = `<embed src="${src}" type="application/pdf">`;
      } else {
        content.innerHTML = `<iframe src="${src}" frameborder="0"></iframe>`;
      }

      overlay.classList.add('open');
      document.body.style.overflow = 'hidden';
    });
  });
};

/* ── Auto-Refresh (polls for new data) ────────────── */
Interactive.setupAutoRefresh = function() {
  const refreshInterval = 60000; // every 60 seconds

  // Add refresh indicator
  const topHeader = document.querySelector('.top-header, .page-header, .topbar');
  if (topHeader) {
    const indicator = document.createElement('div');
    indicator.className = 'refresh-indicator';
    indicator.id = 'refresh-indicator';
    indicator.innerHTML = '<span class="dot"></span><span>Auto-refresh</span>';
    indicator.style.display = 'none';
    topHeader.appendChild(indicator);
  }
};

/* ── Touch Optimization ───────────────────────────── */
Interactive.setupTouchOptimization = function() {
  // Detect touch device
  if ('ontouchstart' in window || navigator.maxTouchPoints > 0) {
    document.body.classList.add('touch-friendly');
  }
};

/* ── Count-Up Animation ────────────────────────────── */
Interactive.countUp = function(el, target, duration = 1000) {
  if (!el) return;
  const start = parseInt(el.textContent) || 0;
  const diff = target - start;
  const startTime = performance.now();

  // Add class for styling
  el.classList.add('count-up');

  const animate = (currentTime) => {
    const elapsed = currentTime - startTime;
    const progress = Math.min(elapsed / duration, 1);
    // Ease out quad
    const eased = progress * (2 - progress);
    const current = Math.round(start + diff * eased);
    el.textContent = current;

    if (progress < 1) {
      requestAnimationFrame(animate);
    } else {
      el.textContent = target;
    }
  };

  requestAnimationFrame(animate);
};

/* ── Get Filtered Count ────────────────────────────── */
Interactive.filterCount = function(items, filters) {
  return items.filter(item => {
    return Object.entries(filters).every(([key, value]) => {
      if (!value) return true;
      const itemVal = String(item[key] || '').toLowerCase();
      return itemVal.includes(String(value).toLowerCase());
    });
  });
};

/* ── Smart Search with Debounce ───────────────────── */
Interactive.smartSearch = function(inputSelector, callback) {
  const input = document.querySelector(inputSelector);
  if (!input) return;

  input.addEventListener('input', () => {
    Interactive.debounce('search', () => {
      const query = input.value.trim();
      // Update counter
      const counter = document.querySelector('.filter-counter');
      if (counter && callback) {
        const count = callback(query);
        counter.textContent = count;
      } else if (callback) {
        callback(query);
      }
    }, 300);
  });
};

/* ── Loading Skeleton ──────────────────────────────── */
Interactive.showSkeleton = function(container, type = 'table', count = 3) {
  const el = typeof container === 'string' ? document.querySelector(container) : container;
  if (!el) return;

  const templates = {
    table: Array(count).fill('<div class="skeleton skeleton-row"></div>').join(''),
    card: Array(count).fill('<div class="skeleton skeleton-card"></div>').join(''),
    list: Array(count).fill(`
      <div style="display:flex;gap:0.75rem;align-items:center;padding:0.75rem 0">
        <div class="skeleton skeleton-avatar"></div>
        <div style="flex:1">
          <div class="skeleton skeleton-text"></div>
          <div class="skeleton skeleton-text short"></div>
        </div>
      </div>
    `).join(''),
    stats: `
      <div style="display:grid;grid-template-columns:repeat(4,1fr);gap:1rem">
        ${Array(4).fill('<div class="skeleton skeleton-card" style="height:100px"></div>').join('')}
      </div>
    `,
    text: Array(count).fill('<div class="skeleton skeleton-text"></div>').join(''),
  };

  el.innerHTML = templates[type] || templates.table;
};

Interactive.hideSkeleton = function(container) {
  // Skeletons get replaced when real content is rendered
};

/* ── Chart — Donut (Canvas-based) ──────────────────── */
Interactive.drawDonut = function(canvasId, data, colors, options = {}) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  const dpr = window.devicePixelRatio || 1;
  const rect = canvas.getBoundingClientRect();

  canvas.width = rect.width * dpr;
  canvas.height = rect.height * dpr;
  ctx.scale(dpr, dpr);

  const width = rect.width;
  const height = rect.height;
  const cx = width / 2;
  const cy = height / 2;
  const radius = Math.min(cx, cy) - 10;
  const innerRadius = radius * 0.6;

  const total = data.reduce((a, b) => a + b, 0) || 1;
  let startAngle = -Math.PI / 2;

  ctx.clearRect(0, 0, width, height);

  // Draw segments
  data.forEach((val, i) => {
    const sliceAngle = (val / total) * Math.PI * 2;

    ctx.beginPath();
    ctx.moveTo(
      cx + innerRadius * Math.cos(startAngle),
      cy + innerRadius * Math.sin(startAngle)
    );
    ctx.arc(cx, cy, radius, startAngle, startAngle + sliceAngle);
    ctx.arc(cx, cy, innerRadius, startAngle + sliceAngle, startAngle, true);
    ctx.closePath();

    ctx.fillStyle = colors[i] || '#22C55E';
    ctx.fill();

    // Subtle highlight
    ctx.beginPath();
    ctx.moveTo(cx, cy);
    ctx.arc(cx, cy, radius, startAngle, startAngle + sliceAngle);
    ctx.fillStyle = colors[i] || '#22C55E';
    ctx.globalAlpha = 0.15;
    ctx.fill();
    ctx.globalAlpha = 1;

    startAngle += sliceAngle;
  });

  // Center circle
  ctx.beginPath();
  ctx.arc(cx, cy, innerRadius * 0.85, 0, Math.PI * 2);
  ctx.fillStyle = options.centerColor || getComputedStyle(document.documentElement)
    .getPropertyValue('--bg-card').trim() || '#161616';
  ctx.fill();

  // Center text
  if (options.centerText) {
    ctx.fillStyle = options.centerTextColor || '#fff';
    ctx.font = `bold ${options.centerFontSize || 18}px Inter, system-ui, sans-serif`;
    ctx.textAlign = 'center';
    ctx.textBaseline = 'middle';
    ctx.fillText(options.centerText, cx, cy);
  }

  // Legend
  if (options.legend && options.labels) {
    const legendX = width + 10;
    const legendY = 10;
    options.labels.forEach((label, i) => {
      const y = legendY + i * 22;
      ctx.fillStyle = colors[i] || '#22C55E';
      ctx.fillRect(legendX, y, 12, 12);
      ctx.fillStyle = '#A1A1AA';
      ctx.font = '12px Inter, system-ui, sans-serif';
      ctx.textAlign = 'left';
      ctx.textBaseline = 'middle';
      ctx.fillText(`${label} (${data[i]})`, legendX + 18, y + 6);
    });
  }
};

/* ── Chart — Bar (Canvas-based) ────────────────────── */
Interactive.drawBarChart = function(canvasId, data, labels, colors, options = {}) {
  const canvas = document.getElementById(canvasId);
  if (!canvas) return;

  const ctx = canvas.getContext('2d');
  const dpr = window.devicePixelRatio || 1;
  const rect = canvas.getBoundingClientRect();

  canvas.width = rect.width * dpr;
  canvas.height = rect.height * dpr;
  ctx.scale(dpr, dpr);

  const width = rect.width;
  const height = rect.height;
  const padding = { top: 20, right: 20, bottom: 30, left: 40 };
  const chartWidth = width - padding.left - padding.right;
  const chartHeight = height - padding.top - padding.bottom;

  const maxVal = Math.max(...data, 1);
  const barWidth = chartWidth / data.length * 0.6;
  const gap = chartWidth / data.length;

  ctx.clearRect(0, 0, width, height);

  // Y-axis gridlines
  const gridLines = 5;
  for (let i = 0; i <= gridLines; i++) {
    const y = padding.top + (chartHeight / gridLines) * i;
    ctx.beginPath();
    ctx.moveTo(padding.left, y);
    ctx.lineTo(width - padding.right, y);
    ctx.strokeStyle = 'rgba(255,255,255,0.05)';
    ctx.stroke();

    const val = Math.round(maxVal - (maxVal / gridLines) * i);
    ctx.fillStyle = '#71717A';
    ctx.font = '10px Inter, system-ui, sans-serif';
    ctx.textAlign = 'right';
    ctx.fillText(val, padding.left - 8, y + 4);
  }

  // Bars
  data.forEach((val, i) => {
    const barHeight = (val / maxVal) * chartHeight;
    const x = padding.left + gap * i + (gap - barWidth) / 2;
    const y = padding.top + chartHeight - barHeight;

    // Bar
    const gradient = ctx.createLinearGradient(x, y, x, padding.top + chartHeight);
    gradient.addColorStop(0, colors[i] || '#22C55E');
    gradient.addColorStop(1, (colors[i] || '#22C55E') + '44');
    ctx.fillStyle = gradient;

    // Rounded top
    const r = 4;
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.lineTo(x + barWidth - r, y);
    ctx.arcTo(x + barWidth, y, x + barWidth, y + r, r);
    ctx.lineTo(x + barWidth, padding.top + chartHeight);
    ctx.lineTo(x, padding.top + chartHeight);
    ctx.lineTo(x, y + r);
    ctx.arcTo(x, y, x + r, y, r);
    ctx.fill();

    // Value on top
    ctx.fillStyle = '#fff';
    ctx.font = 'bold 10px Inter, system-ui, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(val, x + barWidth / 2, y - 6);

    // Label below
    ctx.fillStyle = '#71717A';
    ctx.font = '10px Inter, system-ui, sans-serif';
    ctx.fillText(labels[i], x + barWidth / 2, padding.top + chartHeight + 18);
  });
};

/* ── Startup ───────────────────────────────────────── */
// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  Interactive.init();
});

