/**
 * InternTrack — Main JavaScript
 * Client-side logic, AJAX calls, UI management
 */

/* ── Global State ─────────────────────────────────────── */
const App = {
  csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '',
  currentPage: 'dashboard',
  internships: [],
  companies: [],
  filterStatus: 'all',
  theme: localStorage.getItem('it_theme') || 'light',
};

function applyTheme(theme) {
  document.documentElement.dataset.theme = theme;
  try { localStorage.setItem('it_theme', theme); } catch (e) {}
}

function toggleTheme() {
  App.theme = (App.theme === 'dark') ? 'light' : 'dark';
  applyTheme(App.theme);
  toast(`Theme: ${App.theme}`, 'info');
}

/* ── Toast Notifications ──────────────────────────────── */
function toast(msg, type = 'info') {
  const icons = { success: '✓', error: '✕', info: 'ℹ' };
  const container = document.getElementById('toast-container');
  if (!container) return;
  const el = document.createElement('div');
  el.className = `toast ${type}`;
  el.innerHTML = `<span class="toast-icon">${icons[type] || '•'}</span><span>${msg}</span>`;
  container.appendChild(el);
  setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateX(20px)'; el.style.transition = '.3s'; setTimeout(() => el.remove(), 300); }, 3500);
}

/* ── API Fetch Helper ─────────────────────────────────── */
async function api(url, data = null, method = 'POST') {
  const opts = { method, headers: { 'X-Requested-With': 'XMLHttpRequest' } };
  if (data) {
    if (data instanceof FormData) {
      data.append('csrf_token', App.csrfToken);
      opts.body = data;
    } else {
      const fd = new FormData();
      fd.append('csrf_token', App.csrfToken);
      Object.entries(data).forEach(([k, v]) => fd.append(k, v));
      opts.body = fd;
    }
  }
  const res = await fetch(url, opts);
  if (!res.ok) throw new Error(`HTTP ${res.status}`);
  return res.json();
}

/* ── Modal ────────────────────────────────────────────── */
function openModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el) { el.classList.remove('open'); document.body.style.overflow = ''; }
}

document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) {
    e.target.classList.remove('open');
    document.body.style.overflow = '';
  }
});

/* ── Nav ──────────────────────────────────────────────── */
function navTo(page) {
  document.querySelectorAll('.nav-item').forEach(el => el.classList.toggle('active', el.dataset.page === page));
  document.querySelectorAll('.page-section').forEach(el => el.style.display = el.id === 'page-' + page ? 'block' : 'none');
  App.currentPage = page;

  const titles = { dashboard: 'Dashboard', internships: 'My Internships', progress: 'Progress Logs', companies: 'Companies', admin: 'Admin Panel' };
  const titleEl = document.getElementById('page-title');
  if (titleEl) titleEl.textContent = titles[page] || page;

  if (page === 'dashboard') loadDashboard();
  if (page === 'internships') loadInternships();
  if (page === 'progress') loadProgressPage();
  if (page === 'companies') loadCompanies();
  if (page === 'admin') loadAdmin();
}

/* ── Dashboard ────────────────────────────────────────── */
async function loadDashboard() {
  try {
    const res = await api('php/internships.php?action=stats', null, 'GET');
    if (!res.success) return;

    const s = res.by_status || [];
    const counts = {};
    s.forEach(r => counts[r.status] = r.cnt);

    const statMap = [
      { key: 'total', label: 'Total Applications', icon: '📋', color: 'rgba(37,99,235,.12)', val: res.total },
      { key: 'ongoing', label: 'Ongoing', icon: '🚀', color: 'rgba(16,185,129,.14)', val: counts.ongoing || 0 },
      { key: 'interview', label: 'Interviews', icon: '🎯', color: 'rgba(168,85,247,.14)', val: counts.interview || 0 },
      { key: 'completed', label: 'Completed', icon: '✅', color: 'rgba(99,102,241,.14)', val: counts.completed || 0 },
    ];

    const grid = document.getElementById('stats-grid');
    if (grid) {
      grid.innerHTML = statMap.map(x => `
        <div class="stat-card">
          <div class="stat-icon" style="background:${x.color}">${x.icon}</div>
          <div><div class="stat-num">${x.val}</div><div class="stat-label">${x.label}</div></div>
        </div>`).join('');
    }

    // Status chart
    renderStatusChart(counts);

    // Recent table
    const recentEl = document.getElementById('recent-list');
    const recent = res.recent || [];
    if (recentEl) {
      recentEl.innerHTML = recent.length ? recent.map(r => `
        <tr>
          <td><strong>${escapeHtml(r.title)}</strong></td>
          <td>${escapeHtml(r.company)}</td>
          <td><span class="badge badge-${escapeHtml(r.status)}">${escapeHtml(r.status)}</span></td>
          <td>${escapeHtml(r.start_date)}</td>
        </tr>`).join('') : `<tr><td colspan="4"><div class="empty-state"><div>No internships yet</div></div></td></tr>`;
    }

    // Activity timeline (derived from recent)
    const activityEl = document.getElementById('activity-timeline');
    if (activityEl) {
      const items = recent.slice(0, 6).map((r, idx) => ({
        idx,
        title: r.title,
        company: r.company,
        status: r.status,
        date: r.start_date,
      }));

      if (!items.length) {
        activityEl.innerHTML = `<div class="empty-state" style="padding:1rem 0"><div>No activity yet.</div></div>`;
      } else {
        activityEl.innerHTML = items.map(it => `
          <div style="display:flex;gap:.75rem;align-items:flex-start">
            <div style="width:10px;height:10px;border-radius:999px;margin-top:.45rem;background:var(--primary);box-shadow:0 0 0 4px rgba(37,99,235,.18)"></div>
            <div style="flex:1;min-width:0">
              <div style="font-weight:950;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(it.title)}</div>
              <div style="font-size:.85rem;color:var(--muted);font-weight:750">${escapeHtml(it.company)} · ${escapeHtml(it.status)}</div>
              <div style="font-size:.8rem;color:var(--muted);font-weight:700;margin-top:.25rem">${escapeHtml(it.date)}</div>
            </div>
          </div>
        `).join('');
      }
    }

    // Upcoming interviews (derived from recent; show those with status === 'interview')
    const upcomingEl = document.getElementById('upcoming-interviews');
    if (upcomingEl) {
      const interviews = recent
        .filter(r => (r.status || '').toLowerCase() === 'interview' && r.start_date)
        .sort((a, b) => String(a.start_date).localeCompare(String(b.start_date)))
        .slice(0, 4);

      if (!interviews.length) {
        upcomingEl.innerHTML = `<div class="empty-state" style="padding:1.2rem 0"><div>No upcoming interviews.</div></div>`;
      } else {
        upcomingEl.innerHTML = interviews.map(r => `
          <div class="deadlines-row">
            <div class="deadlines-meta">
              <div class="deadlines-title">${escapeHtml(r.title)}</div>
              <div class="deadlines-sub">${escapeHtml(r.company)} · Interview</div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.25rem">
              <div class="deadlines-chip">${escapeHtml(r.start_date)}</div>
              <div style="font-size:.8rem;color:var(--muted);font-weight:700">Soon</div>
            </div>
          </div>
        `).join('');
      }
    }
  } catch(e) { toast('Failed to load dashboard', 'error'); }
}

function statusPillClass(status) {
  const s = (status || '').toLowerCase();
  if (s === 'applied') return 'applied';
  if (s === 'interview') return 'interview';
  if (s === 'accepted') return 'accepted';
  if (s === 'ongoing') return 'ongoing';
  if (s === 'completed') return 'completed';
  if (s === 'rejected') return 'rejected';
  return '';
}

// Safe HTML escaping for values inserted into template literals
function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, m => ({
    '&': '&amp;',
    '<': '<',
    '>': '>',
    '"': '"',
    "'": '&#039;'
  }[m]));
}

function renderDeadlines(recent) {
  const listEl = document.getElementById('deadlines-list');
  if (!listEl) return;

  // Build deadlines from recent start_date; show next 5 future-ish dates.
  const items = recent
    .map(r => ({
      title: r.title,
      company: r.company,
      status: r.status,
      due: r.start_date
    }))
    .filter(x => !!x.due)
    .slice(0, 5);

  if (!items.length) {
    listEl.innerHTML = `<div class="empty-state"><div>No upcoming deadlines</div></div>`;
    return;
  }

  const rows = items.map((it, idx) => `
    <div class="deadlines-row">
      <div class="deadlines-meta">
        <div class="deadlines-title">${escapeHtml(it.title || 'Internship')} — ${escapeHtml(it.company || '')}</div>
        <div class="deadlines-sub">${escapeHtml(it.status || '')} · Due</div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.35rem">
        <div id="dl-count-${idx}" style="font-family:var(--font-head);font-weight:900;letter-spacing:-.02em">—</div>
        <div class="deadlines-chip">${escapeHtml(it.due)}</div>
      </div>
    </div>
  `).join('');

  listEl.innerHTML = rows;

  // Countdown ticker
  const tick = () => {
    items.forEach((it, idx) => {
      const el = document.getElementById(`dl-count-${idx}`);
      if (!el) return;

      const dueMs = new Date(it.due).getTime();
      if (Number.isNaN(dueMs)) {
        el.textContent = '—';
        return;
      }
      const now = Date.now();
      const diff = dueMs - now;

      if (diff <= 0) {
        el.textContent = 'Due now';
        return;
      }
      const days = Math.floor(diff / (1000 * 60 * 60 * 24));
      const hours = Math.floor((diff / (1000 * 60 * 60)) % 24);
      const mins = Math.floor((diff / (1000 * 60)) % 60);

      el.textContent = `${days}d ${hours}h ${mins}m`;
    });
  };

  tick();
  // Keep one timer only
  if (window.__ihDeadlineTimer) clearInterval(window.__ihDeadlineTimer);
  window.__ihDeadlineTimer = setInterval(tick, 1000);
}

function renderStatusChart(counts) {
  const canvas = document.getElementById('status-chart');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  const statuses = ['applied','interview','accepted','ongoing','completed','rejected'];
  const colors   = ['#60a5fa','#c084fc','#4ade80','#fbbf24','#a5b4fc','#f87171'];
  const data = statuses.map(s => counts[s] || 0);
  const total = data.reduce((a,b)=>a+b,0) || 1;

  ctx.clearRect(0,0,canvas.width,canvas.height);
  let start = -Math.PI/2;
  const cx = canvas.width/2, cy = canvas.height/2, r = Math.min(cx,cy)-20;

  data.forEach((val,i) => {
    const slice = (val/total) * Math.PI * 2;
    ctx.beginPath();
    ctx.moveTo(cx,cy);
    ctx.arc(cx,cy,r,start,start+slice);
    ctx.fillStyle = colors[i];
    ctx.fill();
    start += slice;
  });

  // Center hole
  ctx.beginPath();
  ctx.arc(cx,cy,r*.55,0,Math.PI*2);
  ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--bg2').trim() || '#181c27';
  ctx.fill();

  // Legend
  const legend = document.getElementById('chart-legend');
  if (legend) legend.innerHTML = statuses.map((s,i) => `
    <div style="display:flex;align-items:center;gap:.4rem;font-size:.8rem;color:#8b90a8">
      <span style="width:10px;height:10px;border-radius:2px;background:${colors[i]};display:inline-block"></span>
      ${s} <strong style="color:#e8eaf0;margin-left:auto">${data[i]}</strong>
    </div>`).join('');
}

/* ── Internships ──────────────────────────────────────── */
async function loadInternships() {
  const url = 'php/internships.php?action=list' + (App.filterStatus !== 'all' ? '&status='+App.filterStatus : '');
  try {
    const res = await api(url, null, 'GET');
    if (!res.success) return;
    App.internships = res.internships || [];
    renderInternshipTable(App.internships);
  } catch(e) { toast('Failed to load internships', 'error'); }
}

function renderInternshipTable(list) {
  const tbody = document.getElementById('intern-tbody');
  if (!tbody) return;

  const q = document.getElementById('intern-search')?.value.toLowerCase() || '';
  const filtered = q ? list.filter(r =>
    r.title?.toLowerCase().includes(q) ||
    r.company_name?.toLowerCase().includes(q) ||
    r.status?.toLowerCase().includes(q)
  ) : list;

  if (!filtered.length) {
    tbody.innerHTML = `<tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📭</div><div>No internships found</div></div></td></tr>`;
    return;
  }

  tbody.innerHTML = filtered.map(r => `
    <tr>
      <td><strong>${r.title}</strong><br><small style="color:var(--muted)">${r.work_mode}</small></td>
      <td>${r.company_name}<br><small style="color:var(--muted)">${r.industry||''}</small></td>
      <td><span class="badge badge-${r.status}">${r.status}</span></td>
      <td>${r.start_date}</td>
      <td>${r.end_date}</td>
      <td>${r.stipend > 0 ? 'NPR '+Number(r.stipend).toLocaleString() : '—'}</td>
      <td>
        <div style="display:flex;gap:.4rem">
          <button class="btn btn-secondary btn-sm" onclick="editInternship(${r.id})">Edit</button>
          <button class="btn btn-danger btn-sm" onclick="deleteInternship(${r.id})">Del</button>
        </div>
      </td>
    </tr>`).join('');
}

async function loadCompaniesForSelect() {
  if (App.companies.length) return;
  try {
    const res = await api('php/internships.php?action=companies', null, 'GET');
    if (res.success) {
      App.companies = res.companies;
      const sel = document.getElementById('intern-company');
      if (sel) sel.innerHTML = '<option value="">Select Company…</option>' +
        res.companies.map(c => `<option value="${c.id}">${c.name} — ${c.location||''}</option>`).join('');
    }
  } catch(e) { console.error(e); }
}

function openAddInternship() {
  document.getElementById('intern-form')?.reset();
  document.getElementById('intern-id').value = '';
  document.getElementById('intern-modal-title').textContent = 'Add Internship';
  loadCompaniesForSelect();
  openModal('intern-modal');
}

async function editInternship(id) {
  try {
    const res = await api(`php/internships.php?action=get&id=${id}`, null, 'GET');
    if (!res.success) return toast(res.message, 'error');
    const d = res.internship;
    document.getElementById('intern-id').value = d.id;
    document.getElementById('intern-title').value = d.title;
    document.getElementById('intern-desc').value = d.description || '';
    document.getElementById('intern-start').value = d.start_date;
    document.getElementById('intern-end').value = d.end_date;
    document.getElementById('intern-status').value = d.status;
    document.getElementById('intern-stipend').value = d.stipend;
    document.getElementById('intern-workmode').value = d.work_mode;
    document.getElementById('intern-supervisor').value = d.supervisor_name || '';
    document.getElementById('intern-supervisor-email').value = d.supervisor_email || '';
    document.getElementById('intern-notes').value = d.notes || '';
    document.getElementById('intern-modal-title').textContent = 'Edit Internship';
    await loadCompaniesForSelect();
    document.getElementById('intern-company').value = d.company_id;
    openModal('intern-modal');
  } catch(e) { toast('Failed to load internship', 'error'); }
}

async function saveInternship() {
  const form = document.getElementById('intern-form');
  const fd = new FormData(form);
  const id = document.getElementById('intern-id').value;
  fd.set('action', id ? 'update' : 'create');
  if (id) fd.set('id', id);

  try {
    const res = await api('php/internships.php', fd);
    if (res.success) { toast(res.message, 'success'); closeModal('intern-modal'); loadInternships(); loadDashboard(); }
    else toast(res.message, 'error');
  } catch(e) { toast('Save failed', 'error'); }
}

async function deleteInternship(id) {
  if (!confirm('Delete this internship? This cannot be undone.')) return;
  try {
    const res = await api('php/internships.php', { action: 'delete', id });
    if (res.success) { toast('Deleted.', 'success'); loadInternships(); loadDashboard(); }
    else toast(res.message, 'error');
  } catch(e) { toast('Delete failed', 'error'); }
}

function filterByStatus(status) {
  App.filterStatus = status;
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.toggle('active', b.dataset.status === status));
  loadInternships();
}

/* ── Progress Logs ────────────────────────────────────── */
async function loadProgressPage() {
  const sel = document.getElementById('progress-intern-sel');
  if (!sel) return;
  try {
    const res = await api('php/internships.php?action=list', null, 'GET');
    if (res.success) {
      sel.innerHTML = '<option value="">Select Internship…</option>' +
        res.internships.map(i => `<option value="${i.id}">${i.title} @ ${i.company_name}</option>`).join('');
    }
  } catch(e) { console.error(e); }
}

async function loadLogs() {
  const id = document.getElementById('progress-intern-sel').value;
  if (!id) return;
  try {
    const res = await api(`php/internships.php?action=log_list&internship_id=${id}`, null, 'GET');
    const el = document.getElementById('logs-list');
    if (!res.success || !res.logs.length) {
      el.innerHTML = `<div class="empty-state"><div class="empty-icon">📓</div><div>No logs yet. Add your first weekly log!</div></div>`;
      return;
    }
    el.innerHTML = res.logs.map(l => `
      <div style="background:var(--bg3);border:1px solid var(--border);border-radius:12px;padding:1.2rem;margin-bottom:.8rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.8rem">
          <strong>Week ${l.week_number}</strong>
          <div style="display:flex;gap:.5rem;align-items:center">
            <span class="rating-stars">${'★'.repeat(l.rating||0)}${'☆'.repeat(5-(l.rating||0))}</span>
            <span class="chip">${l.hours_worked}h</span>
            <span style="color:var(--muted);font-size:.8rem">${l.log_date}</span>
          </div>
        </div>
        ${l.tasks_completed ? `<p style="margin-bottom:.5rem"><strong style="color:var(--text)">Tasks:</strong> ${l.tasks_completed}</p>` : ''}
        ${l.skills_learned ? `<p style="margin-bottom:.5rem"><strong style="color:var(--text)">Skills:</strong> ${l.skills_learned}</p>` : ''}
        ${l.challenges ? `<p><strong style="color:var(--text)">Challenges:</strong> ${l.challenges}</p>` : ''}
      </div>`).join('');
  } catch(e) { toast('Failed to load logs', 'error'); }
}

async function saveProgressLog() {
  const internId = document.getElementById('progress-intern-sel').value;
  if (!internId) return toast('Select an internship first.', 'error');
  const data = {
    action: 'log_add',
    internship_id: internId,
    log_date: document.getElementById('log-date').value,
    tasks_completed: document.getElementById('log-tasks').value,
    skills_learned: document.getElementById('log-skills').value,
    challenges: document.getElementById('log-challenges').value,
    hours_worked: document.getElementById('log-hours').value,
    rating: document.getElementById('log-rating').value,
  };
  try {
    const res = await api('php/internships.php', data);
    if (res.success) { toast(`Week ${res.week} log saved!`, 'success'); document.getElementById('log-form')?.reset(); loadLogs(); }
    else toast(res.message, 'error');
  } catch(e) { toast('Failed to save log', 'error'); }
}

/* ── Companies ────────────────────────────────────────── */
async function loadCompanies() {
  try {
    const res = await api('php/internships.php?action=companies', null, 'GET');
    const el = document.getElementById('companies-grid');
    if (!el || !res.success) return;
    el.innerHTML = res.companies.map(c => `
      <div style="background:var(--bg2);border:1px solid var(--border);border-radius:14px;padding:1.4rem">
        <h4 style="margin-bottom:.3rem">${c.name}</h4>
        <p style="font-size:.85rem">${c.industry||'—'}</p>
        <div class="chip" style="margin-top:.7rem">${c.location||'Location N/A'}</div>
      </div>`).join('') || '<div class="empty-state"><div>No companies yet.</div></div>';
  } catch(e) { toast('Failed to load companies', 'error'); }
}

async function addCompany() {
  const data = {
    action: 'add_company',
    name: document.getElementById('co-name').value,
    industry: document.getElementById('co-industry').value,
    website: document.getElementById('co-website').value,
    location: document.getElementById('co-location').value,
    contact_person: document.getElementById('co-contact').value,
    contact_email: document.getElementById('co-email').value,
  };
  if (!data.name) return toast('Company name required.', 'error');
  try {
    const res = await api('php/internships.php', data);
    if (res.success) { toast('Company added!', 'success'); closeModal('company-modal'); App.companies = []; loadCompanies(); }
    else toast(res.message, 'error');
  } catch(e) { toast('Failed to add company', 'error'); }
}

/* ── Admin ────────────────────────────────────────────── */
async function loadAdmin() {
  try {
    const res = await api('php/internships.php?action=list', null, 'GET');
    const tbody = document.getElementById('admin-tbody');
    if (!tbody || !res.success) return;
    tbody.innerHTML = res.internships.map(r => `
      <tr>
        <td>${r.student_name}</td>
        <td>${r.title}</td>
        <td>${r.company_name}</td>
        <td><span class="badge badge-${r.status}">${r.status}</span></td>
        <td>${r.start_date}</td>
        <td>
          <button class="btn btn-danger btn-sm" onclick="deleteInternship(${r.id})">Delete</button>
        </td>
      </tr>`).join('');
  } catch(e) { toast('Failed to load admin data', 'error'); }
}

/* ── Auth ─────────────────────────────────────────────── */
function signInAsAdmin() {
  // Admin shortcut for the normal login page (still validates credentials server-side)
  const form = document.querySelector('#login-form form');
  const roleHint = document.getElementById('role_hint');
  const usernameInput = form?.querySelector('input[name="username"]');

  if (usernameInput && (!usernameInput.value || usernameInput.value.trim() === '')) {
    usernameInput.value = 'admin';
  }
  if (roleHint) roleHint.value = 'admin';

  // Focus password field for quick entry
  const passwordInput = form?.querySelector('input[name="password"]');
  passwordInput?.focus();
}

async function handleLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('login-btn');
  if (!btn) { alert('Login button not found!'); return; }
  btn.textContent = 'Signing in…'; btn.disabled = true;
  try {
    const fd = new FormData(e.target);
    // Get CSRF from meta tag OR from hidden input field
    let csrfToken = App.csrfToken;
    if (!csrfToken) {
      const csrfInput = document.querySelector('input[name="csrf_token"]');
      csrfToken = csrfInput?.value || document.querySelector('meta[name="csrf-token"]')?.content;
    }
    fd.set('action', 'login');
    fd.set('csrf_token', csrfToken || '');
    const username = fd.get('username');
    if (!username) {
      toast('Username is required.', 'error');
      btn.textContent = 'Sign In';
      btn.disabled = false;
      return;
    }
    // Fix auth.php path - check if current page is in php/ folder
    const pathParts = window.location.pathname.split('/').filter(Boolean);
    const authPath = pathParts.includes('php') ? 'auth.php' : 'php/auth.php';
    const res = await fetch(authPath, { method: 'POST', body: fd });
    if (!res.ok) {
      toast('Server error: ' + res.status, 'error');
      btn.textContent = 'Sign In';
      btn.disabled = false;
      return;
    }
    const data = await res.json();
    if (data.success) {
      toast(data.message || 'Login successful!', 'success');
      setTimeout(() => {
        const redirectPath = data.redirect || 'dashboard.php';
        window.location.href = redirectPath;
      }, 800);
    } else {
      toast(data.message || 'Login failed', 'error');
      btn.textContent = 'Sign In';
      btn.disabled = false;
    }
  } catch(e) {
    console.error('Login error:', e);
    alert('Login error: ' + e.message);
    toast('Network error. Check connection.', 'error');
    btn.textContent = 'Sign In';
    btn.disabled = false;
  }
}

async function handleRegister(e) {
  e.preventDefault();
  const btn = document.getElementById('reg-btn') || document.getElementById('register-btn');
  if (!btn) { console.error('Register button not found'); return; }
  btn.textContent = 'Creating…'; btn.disabled = true;
  try {
    const fd = new FormData(e.target);
    // Get CSRF from meta tag OR from hidden input
    let csrfToken = App.csrfToken;
    if (!csrfToken) {
      const csrfInput = document.querySelector('input[name="csrf_token"]');
      csrfToken = csrfInput?.value || document.querySelector('meta[name="csrf-token"]')?.content;
    }
    fd.set('action', 'register');
    fd.set('csrf_token', csrfToken || '');
    // Fix auth.php path - if in php/ folder use same folder, otherwise go to php/
    const pathParts = window.location.pathname.split('/').filter(Boolean);
    const inPhpFolder = pathParts.includes('php');
    const authPath = inPhpFolder ? 'auth.php' : 'php/auth.php';
    console.log('RegisterSubmitting to:', authPath);
    // Log form data for debugging
    for (let [key, value] of fd.entries()) {
      console.log('FormData:', key, '=', value);
    }
    const res = await fetch(authPath, { method: 'POST', body: fd });
    console.log('Registerresponse status:', res.status);
    if (!res.ok) {
      toast('Server error: ' + res.status, 'error');
      btn.textContent = 'Create Account';
      btn.disabled = false;
      // Try to get error text
      const text = await res.text();
      console.error('Server error response:', text);
      return;
    }
    const data = await res.json();
    console.log('Registerresponse data:', data);
    toast(data.message || (data.success ? 'Account created!' : 'Registration failed'), data.success ? 'success' : 'error');
    if (data.success) {
      setTimeout(() => window.location.href = data.redirect || 'admin_login.php', 1200);
    }
  } catch(e) {
    console.error('Register error:', e);
    toast('Network error. Try again.', 'error');
  } finally { btn.disabled = false; btn.textContent = 'Create Account'; }
}

async function logout() {
  await api('php/auth.php', { action: 'logout' });
  window.location.href = 'index.php';
}

function switchTab(tab) {
  document.querySelectorAll('.auth-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
  document.getElementById('login-form').style.display = tab === 'login' ? 'block' : 'none';
  document.getElementById('reg-form').style.display = tab === 'register' ? 'block' : 'none';
}

/* ── Password Toggle ────────────────────────────────── */
function togglePassword(btn) {
  const wrapper = btn.closest('.password-wrapper') || btn.parentElement;
  const input = wrapper.querySelector('input');
  if (!input) return;
  const isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';
  btn.textContent = isPassword ? '🙈' : '👁️';
}

/* ── Forgot Password UI ─────────────────────────────── */
function openForgotPasswordModal() {
  const el = document.getElementById('forgot-modal');
  if (el) {
    el.style.display = 'block';
    el.classList.add('open');
    document.body.style.overflow = 'hidden';
  }
}

function closeForgotPasswordModal() {
  const el = document.getElementById('forgot-modal');
  if (el) {
    el.style.display = 'none';
    el.classList.remove('open');
    document.body.style.overflow = '';
  }
}

async function handleForgotRequest(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('forgot-btn');
  if (btn) { btn.textContent = 'Sending…'; btn.disabled = true; }

  try {
    const fd = new FormData(form);
    // Explicitly append fields so PHP receives them as expected
    fd.append('action', 'forgot_request');

    // Always read CSRF token from the page meta (robust against App.csrfToken init timing issues)
    const metaToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    const csrfToken = (App.csrfToken || metaToken).trim();
    if (!csrfToken) {
      toast('Security token missing. Please refresh the page.', 'error');
      if (btn) { btn.textContent = 'Send Reset Link'; btn.disabled = false; }
      return;
    }
    fd.append('csrf_token', csrfToken);

    // Dynamic path detection - works from any folder
    const pathParts = window.location.pathname.split('/').filter(Boolean);
    const authPath = pathParts.includes('php') ? 'auth.php' : 'php/auth.php';

    const res = await fetch(authPath, { method: 'POST', body: fd });

    if (!res.ok) {
      console.error('HTTP error:', res.status, res.statusText);
      toast('Request failed. Please try again.', 'error');
      if (btn) { btn.textContent = 'Send Reset Link'; btn.disabled = false; }
      return;
    }

    const data = await res.json();
    if (data && data.success !== undefined) {
      if (data.success) {
        toast(data.message || 'Password reset email sent!', 'success');
        closeForgotPasswordModal();
        form.reset();
      } else {
        toast(data.message || 'Failed to send reset link.', 'error');
      }
    } else {
      console.error('Invalid response:', data);
      toast('Server returned invalid response. Check your connection.', 'error');
    }
  } catch (err) {
    console.error('Fetch error:', err);
    toast('Request failed: ' + err.message, 'error');
  } finally {
    if (btn) { btn.textContent = 'Send Reset Link'; btn.disabled = false; }
  }
}

/* ── Reset Password (reset_password.php) ──────────────── */
async function handleResetPassword(e) {
  e.preventDefault();
  const form = e.target;
  const btn = document.getElementById('reset-btn');
  if (btn) { btn.textContent = 'Updating…'; btn.disabled = true; }

  try {
    const token = form.dataset.resetToken;
    const email = form.dataset.resetEmail;
    const newPassword = form.querySelector('input[name="new_password"]').value;
    const confirmPassword = form.querySelector('input[name="confirm_password"]').value;
    const csrfToken = form.querySelector('input[name="csrf_token"]').value;

    if (!token || !email) {
      toast('Invalid reset request. Please request a new link.', 'error');
      if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; }
      return;
    }

    if (newPassword.length < 8) {
      toast('Password must be at least 8 characters.', 'error');
      if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; }
      return;
    }

    if (!/[A-Z]/.test(newPassword)) {
      toast('Password must contain an uppercase letter.', 'error');
      if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; }
      return;
    }

    if (!/[0-9]/.test(newPassword)) {
      toast('Password must contain a number.', 'error');
      if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; }
      return;
    }

    if (newPassword !== confirmPassword) {
      toast('Passwords do not match.', 'error');
      if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; }
      return;
    }

    const fd = new FormData();
    fd.append('action', 'forgot_reset');
    fd.append('token', token);
    fd.append('email', email);
    fd.append('new_password', newPassword);
    fd.append('confirm_password', confirmPassword);
    fd.append('csrf_token', csrfToken);

    // Dynamic path detection - works from any folder
    const pathParts = window.location.pathname.split('/').filter(Boolean);
    const authPath = pathParts.includes('php') ? 'auth.php' : 'php/auth.php';

    const res = await fetch(authPath, { method: 'POST', body: fd });

    if (!res.ok) {
      toast('Request failed. Please try again.', 'error');
      if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; }
      return;
    }

    const data = await res.json();
    if (data && data.success) {
      toast('Password updated successfully! Redirecting to login…', 'success');
      setTimeout(() => {
        window.location.href = 'index.php';
      }, 1500);
    } else {
      toast(data.message || 'Failed to reset password.', 'error');
      if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; }
    }
  } catch (err) {
    console.error('Reset password error:', err);
    toast('Error: ' + err.message, 'error');
    if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; }
  }
}

/* ── Init ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  // Theme init (default to light unless saved)
  applyTheme(App.theme);

  // Intern search
  document.getElementById('intern-search')?.addEventListener('input', () => {
    renderInternshipTable(App.internships);
  });
  // Date defaults
  const today = new Date().toISOString().split('T')[0];
  document.getElementById('log-date') && (document.getElementById('log-date').value = today);
  document.getElementById('intern-start') && (document.getElementById('intern-start').value = today);
});
