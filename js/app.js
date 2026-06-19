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

    renderStatusChart(counts);

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

    const activityEl = document.getElementById('activity-timeline');
    if (activityEl) {
      const items = recent.slice(0, 6).map((r, idx) => ({
        idx, title: r.title, company: r.company, status: r.status, date: r.start_date,
      }));
      if (!items.length) {
        activityEl.innerHTML = `<div class="empty-state" style="padding:1rem 0"><div>No activity yet.</div></div>`;
      } else {
        activityEl.innerHTML = items.map(it => `
          <div style="display:flex;gap:.75rem;align-items:flex-start">
            <div style="width:10px;height:10px;border-radius:999px;margin-top:.45rem;background:var(--primary);box-shadow:0 0 0 4px rgba(37,99,235,.18)"></div>
            <div style="flex:1;min-width:0">
              <div style="font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis">${escapeHtml(it.title)}</div>
              <div style="font-size:.85rem;color:var(--muted)">${escapeHtml(it.company)} · ${escapeHtml(it.status)}</div>
              <div style="font-size:.8rem;color:var(--muted);margin-top:.25rem">${escapeHtml(it.date)}</div>
            </div>
          </div>
        `).join('');
      }
    }

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
              <div style="font-size:.8rem;color:var(--muted)">Soon</div>
            </div>
          </div>
        `).join('');
      }
    }
  } catch(e) { toast('Failed to load dashboard', 'error'); }
}

function escapeHtml(str) {
  return String(str ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'}[m]));
}

function renderDeadlines(recent) {
  const listEl = document.getElementById('deadlines-list');
  if (!listEl) return;
  const items = recent.map(r => ({ title: r.title, company: r.company, status: r.status, due: r.start_date }))
    .filter(x => !!x.due).slice(0, 5);
  if (!items.length) {
    listEl.innerHTML = `<div class="empty-state"><div>No upcoming deadlines</div></div>`;
    return;
  }
  listEl.innerHTML = items.map((it, idx) => `
    <div class="deadlines-row">
      <div class="deadlines-meta">
        <div class="deadlines-title">${escapeHtml(it.title || 'Internship')} — ${escapeHtml(it.company || '')}</div>
        <div class="deadlines-sub">${escapeHtml(it.status || '')} · Due</div>
      </div>
      <div style="display:flex;flex-direction:column;align-items:flex-end;gap:.35rem">
        <div id="dl-count-${idx}" style="font-weight:900;letter-spacing:-.02em">—</div>
        <div class="deadlines-chip">${escapeHtml(it.due)}</div>
      </div>
    </div>
  `).join('');
  const tick = () => {
    items.forEach((it, idx) => {
      const el = document.getElementById(`dl-count-${idx}`);
      if (!el) return;
      const dueMs = new Date(it.due).getTime();
      if (Number.isNaN(dueMs)) { el.textContent = '—'; return; }
      const diff = dueMs - Date.now();
      if (diff <= 0) { el.textContent = 'Due now'; return; }
      const days = Math.floor(diff / (1000*60*60*24));
      const hours = Math.floor((diff / (1000*60*60)) % 24);
      const mins = Math.floor((diff / (1000*60)) % 60);
      el.textContent = `${days}d ${hours}h ${mins}m`;
    });
  };
  tick();
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
    ctx.beginPath(); ctx.moveTo(cx,cy);
    ctx.arc(cx,cy,r,start,start+slice);
    ctx.fillStyle = colors[i]; ctx.fill();
    start += slice;
  });
  ctx.beginPath(); ctx.arc(cx,cy,r*.55,0,Math.PI*2);
  ctx.fillStyle = getComputedStyle(document.documentElement).getPropertyValue('--bg2').trim() || '#181c27';
  ctx.fill();
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
      <td><strong>${escapeHtml(r.title)}</strong><br><small style="color:var(--muted)">${escapeHtml(r.work_mode)}</small></td>
      <td>${escapeHtml(r.company_name)}<br><small style="color:var(--muted)">${escapeHtml(r.industry||'')}</small></td>
      <td><span class="badge badge-${escapeHtml(r.status)}">${escapeHtml(r.status)}</span></td>
      <td>${escapeHtml(r.start_date)}</td>
      <td>${escapeHtml(r.end_date)}</td>
      <td>${r.stipend > 0 ? 'NPR '+Number(r.stipend).toLocaleString() : '—'}</td>
      <td>
        <div style="display:flex;gap:.4rem">
          <button class="btn btn-secondary btn-sm" onclick="editInternship(${Number(r.id)})">Edit</button>
          <button class="btn btn-danger btn-sm" onclick="deleteInternship(${Number(r.id)})">Del</button>
        </div>
      </td>
    </tr>`).join('');
}

async function loadCompaniesForSelect() {
  try {
    const res = await api('php/internships.php?action=companies', null, 'GET');
    if (res.success) {
      App.companies = res.companies;
      const sel = document.getElementById('company-select');
      if (sel) sel.innerHTML = '<option value="">Select Company…</option>' +
        res.companies.map(c => `<option value="${Number(c.id)}">${escapeHtml(c.name)} — ${escapeHtml(c.location||'')}</option>`).join('');
    }
  } catch(e) { /* silent */ }
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
    if (res.success) { toast('Internship deleted successfully!', 'success'); loadInternships(); loadDashboard(); }
    else toast(res.message || 'Failed to delete internship', 'error');
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
        res.internships.map(i => `<option value="${Number(i.id)}">${escapeHtml(i.title)} @ ${escapeHtml(i.company_name)}</option>`).join('');
    }
  } catch(e) { /* silent */ }
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
          <strong>Week ${escapeHtml(String(l.week_number))}</strong>
          <div style="display:flex;gap:.5rem;align-items:center">
            <span class="rating-stars">${'★'.repeat(l.rating||0)}${'☆'.repeat(5-(l.rating||0))}</span>
            <span class="chip">${escapeHtml(String(l.hours_worked))}h</span>
            <span style="color:var(--muted);font-size:.8rem">${escapeHtml(l.log_date)}</span>
          </div>
        </div>
        ${l.tasks_completed ? `<p style="margin-bottom:.5rem"><strong>Tasks:</strong> ${escapeHtml(l.tasks_completed)}</p>` : ''}
        ${l.skills_learned ? `<p style="margin-bottom:.5rem"><strong>Skills:</strong> ${escapeHtml(l.skills_learned)}</p>` : ''}
        ${l.challenges ? `<p><strong>Challenges:</strong> ${escapeHtml(l.challenges)}</p>` : ''}
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
        <h4 style="margin-bottom:.3rem">${escapeHtml(c.name)}</h4>
        <p style="font-size:.85rem">${escapeHtml(c.industry||'—')}</p>
        <div class="chip" style="margin-top:.7rem">${escapeHtml(c.location||'Location N/A')}</div>
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
        <td>${escapeHtml(r.student_name)}</td>
        <td>${escapeHtml(r.title)}</td>
        <td>${escapeHtml(r.company_name)}</td>
        <td><span class="badge badge-${escapeHtml(r.status)}">${escapeHtml(r.status)}</span></td>
        <td>${escapeHtml(r.start_date)}</td>
        <td>
          <button class="btn btn-danger btn-sm" onclick="deleteInternship(${Number(r.id)})">Delete</button>
        </td>
      </tr>`).join('');
  } catch(e) { toast('Failed to load admin data', 'error'); }
}

/* ── Auth ─────────────────────────────────────────────── */
async function handleLogin(e) {
  e.preventDefault();
  const btn = document.getElementById('login-btn');
  if (!btn) return;
  btn.textContent = 'Signing in…';
  btn.disabled = true;

  try {
    const fd = new FormData(e.target);
    const csrfToken = App.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
    fd.set('action', 'login');
    fd.set('csrf_token', csrfToken);

    const username = fd.get('username');
    if (!username || !username.trim()) {
      toast('Username is required.', 'error');
      btn.textContent = 'Sign In';
      btn.disabled = false;
      return;
    }

    const inPhpFolder = window.location.pathname.includes('/php/');
    const authPath = inPhpFolder ? 'auth.php' : 'php/auth.php';

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
        window.location.href = data.redirect || 'dashboard.php';
      }, 800);
    } else {
      toast(data.message || 'Invalid username or password.', 'error');
      btn.textContent = 'Sign In';
      btn.disabled = false;
    }
  } catch(err) {
    toast('Network error. Check your connection.', 'error');
    btn.textContent = 'Sign In';
    btn.disabled = false;
  }
}

async function handleRegister(e) {
  e.preventDefault();
  const btn = document.getElementById('reg-btn') || document.getElementById('register-btn');
  if (!btn) return;

  const form = e.target;

  // ── Client-side validation ──────────────────────────
  const fullName = form.querySelector('input[name="full_name"]')?.value?.trim() || '';
  const username = form.querySelector('input[name="username"]')?.value?.trim() || '';
  const email    = form.querySelector('input[name="email"]')?.value?.trim() || '';
  const password = form.querySelector('input[name="password"]')?.value || '';
  const confirm  = form.querySelector('input[name="confirm_password"]')?.value || '';

  if (fullName.length < 2)             { toast('Full name must be at least 2 characters.', 'error'); return; }
  if (!/^[a-zA-Z0-9_]{3,30}$/.test(username)) { toast('Username must be 3–30 alphanumeric characters.', 'error'); return; }
  if (!email.includes('@'))            { toast('Please enter a valid email address.', 'error'); return; }
  if (password.length < 8)            { toast('Password must be at least 8 characters.', 'error'); return; }
  if (!/[A-Z]/.test(password))        { toast('Password must contain at least one uppercase letter.', 'error'); return; }
  if (!/[0-9]/.test(password))        { toast('Password must contain at least one number.', 'error'); return; }
  if (password !== confirm)           { toast('Passwords do not match.', 'error'); return; }

  btn.textContent = 'Creating…';
  btn.disabled = true;
  let succeeded = false;

  try {
    const fd = new FormData(form);
    const csrfToken = App.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '';
    fd.set('action', 'register');
    fd.set('csrf_token', csrfToken);

    // Path detection: works from root or /php/ subfolder
    const inPhpFolder = window.location.pathname.includes('/php/');
    const authPath = inPhpFolder ? 'auth.php' : 'php/auth.php';

    const res = await fetch(authPath, { method: 'POST', body: fd });
    if (!res.ok) {
      toast('Server error: ' + res.status, 'error');
      return;
    }

    const data = await res.json();
    if (data.success) {
      succeeded = true;
      toast(data.message || 'Account created! Please sign in.', 'success');

      // Use data-on-success="redirect:url" on the form for admin pages.
      // Student registration (index.php) has no such attribute → always switches to Sign In tab.
      const onSuccess = form.dataset.onSuccess || '';
      if (onSuccess.startsWith('redirect:')) {
        const target = onSuccess.slice('redirect:'.length);
        setTimeout(() => { window.location.href = target; }, 1400);
      } else {
        form.reset();
        // Also clear strength / match indicators
        const pwStrength = document.getElementById('pw-strength');
        const pwMatch    = document.getElementById('pw-match-msg');
        if (pwStrength) pwStrength.style.display = 'none';
        if (pwMatch)    pwMatch.textContent = '';
        setTimeout(() => {
          if (typeof switchTab === 'function') switchTab('login');
          else window.location.reload();
        }, 1400);
      }
    } else {
      toast(data.message || 'Registration failed. Please try again.', 'error');
    }
  } catch(err) {
    toast('Network error. Please try again.', 'error');
  } finally {
    btn.disabled = false;
    // Only reset label if we're NOT about to leave the tab
    if (!succeeded) btn.textContent = 'Create Account';
  }
}

async function logout() {
  try { await api('php/auth.php', { action: 'logout' }); } catch(e) {}
  window.location.href = 'index.php';
}
function handleLogout() { logout(); }

/* ── Tab switcher (login page) ──────────────────────── */
function switchTab(tab) {
  document.querySelectorAll('.auth-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
  const loginForm = document.getElementById('login-form');
  const regForm   = document.getElementById('reg-form');
  if (loginForm) loginForm.style.display = tab === 'login'    ? 'block' : 'none';
  if (regForm)   regForm.style.display   = tab === 'register' ? 'block' : 'none';
}

/* ── Password Toggle ─────────────────────────────────── */
function togglePassword(btn) {
  const wrapper = btn.closest('.password-wrapper') || btn.parentElement;
  const input = wrapper.querySelector('input');
  if (!input) return;
  const isPassword = input.type === 'password';
  input.type = isPassword ? 'text' : 'password';
  btn.textContent = isPassword ? '🙈' : '👁️';
}

/* ── Password strength & match (register form) ──────── */
function updatePasswordStrength(val) {
  const wrap  = document.getElementById('pw-strength');
  const label = document.getElementById('pw-label');
  if (!wrap || !label) return;
  wrap.style.display = val.length > 0 ? 'block' : 'none';
  let score = 0;
  if (val.length >= 8)    score++;
  if (/[A-Z]/.test(val)) score++;
  if (/[0-9]/.test(val)) score++;
  const bars   = ['pw-bar-1','pw-bar-2','pw-bar-3'].map(id => document.getElementById(id));
  const colors = ['#EF4444','#F59E0B','#22C55E'];
  const labels = ['Weak','Fair','Strong'];
  bars.forEach((b, i) => { if (b) b.style.background = i < score ? colors[score-1] : 'var(--border)'; });
  label.textContent = score > 0 ? labels[score-1] : '';
  label.style.color = score > 0 ? colors[score-1] : 'var(--muted)';
  checkPasswordMatch();
}

function checkPasswordMatch() {
  const pw      = document.getElementById('reg-password')?.value || '';
  const confirm = document.getElementById('reg-confirm')?.value  || '';
  const msg     = document.getElementById('pw-match-msg');
  if (!msg || !confirm) return;
  if (pw === confirm) {
    msg.textContent = '✓ Passwords match';
    msg.style.color = '#22C55E';
  } else {
    msg.textContent = '✕ Passwords do not match';
    msg.style.color = '#EF4444';
  }
}

/* ── Forgot Password UI ──────────────────────────────── */
function openForgotPasswordModal() {
  const el = document.getElementById('forgot-modal');
  if (el) { el.style.display = 'block'; el.classList.add('open'); document.body.style.overflow = 'hidden'; }
}
function closeForgotPasswordModal() {
  const el = document.getElementById('forgot-modal');
  if (el) { el.style.display = 'none'; el.classList.remove('open'); document.body.style.overflow = ''; }
}

async function handleForgotRequest(e) {
  e.preventDefault();
  const form = e.target;
  const btn  = document.getElementById('forgot-btn');
  if (btn) { btn.textContent = 'Sending…'; btn.disabled = true; }

  try {
    const fd = new FormData(form);
    fd.append('action', 'forgot_request');
    const csrfToken = (App.csrfToken || document.querySelector('meta[name="csrf-token"]')?.content || '').trim();
    if (!csrfToken) {
      toast('Security token missing. Please refresh the page.', 'error');
      if (btn) { btn.textContent = 'Send Reset Link'; btn.disabled = false; }
      return;
    }
    fd.append('csrf_token', csrfToken);
    const inPhpFolder = window.location.pathname.includes('/php/');
    const authPath = inPhpFolder ? 'auth.php' : 'php/auth.php';
    const res = await fetch(authPath, { method: 'POST', body: fd });
    if (!res.ok) {
      toast('Request failed. Please try again.', 'error');
      if (btn) { btn.textContent = 'Send Reset Link'; btn.disabled = false; }
      return;
    }
    const data = await res.json();
    if (data?.success) {
      toast(data.message || 'Password reset email sent!', 'success');
      closeForgotPasswordModal();
      form.reset();
    } else {
      toast(data?.message || 'Failed to send reset link.', 'error');
    }
  } catch(err) {
    toast('Request failed. Check your connection.', 'error');
  } finally {
    if (btn) { btn.textContent = 'Send Reset Link'; btn.disabled = false; }
  }
}

/* ── Reset Password ───────────────────────────────────── */
async function handleResetPassword(e) {
  e.preventDefault();
  const form = e.target;
  const btn  = document.getElementById('reset-btn');
  if (btn) { btn.textContent = 'Updating…'; btn.disabled = true; }

  try {
    const token       = form.dataset.resetToken;
    const email       = form.dataset.resetEmail;
    const newPassword = form.querySelector('input[name="new_password"]').value;
    const confirmPw   = form.querySelector('input[name="confirm_password"]').value;
    const csrfToken   = form.querySelector('input[name="csrf_token"]').value;

    if (!token || !email) { toast('Invalid reset request. Please request a new link.', 'error'); if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; } return; }
    if (newPassword.length < 8)      { toast('Password must be at least 8 characters.', 'error'); if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; } return; }
    if (!/[A-Z]/.test(newPassword))  { toast('Password must contain an uppercase letter.', 'error'); if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; } return; }
    if (!/[0-9]/.test(newPassword))  { toast('Password must contain a number.', 'error'); if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; } return; }
    if (newPassword !== confirmPw)   { toast('Passwords do not match.', 'error'); if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; } return; }

    const fd = new FormData();
    fd.append('action', 'forgot_reset');
    fd.append('token', token);
    fd.append('email', email);
    fd.append('new_password', newPassword);
    fd.append('confirm_password', confirmPw);
    fd.append('csrf_token', csrfToken);

    const inPhpFolder = window.location.pathname.includes('/php/');
    const authPath = inPhpFolder ? 'auth.php' : 'php/auth.php';
    const res = await fetch(authPath, { method: 'POST', body: fd });
    if (!res.ok) { toast('Request failed. Please try again.', 'error'); if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; } return; }

    const data = await res.json();
    if (data?.success) {
      toast('Password updated! Redirecting to login…', 'success');
      setTimeout(() => { window.location.href = 'index.php'; }, 1500);
    } else {
      toast(data?.message || 'Failed to reset password.', 'error');
      if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; }
    }
  } catch(err) {
    toast('Error: ' + err.message, 'error');
    if (btn) { btn.textContent = 'Update Password'; btn.disabled = false; }
  }
}

/* ── Init ─────────────────────────────────────────────── */
document.addEventListener('DOMContentLoaded', () => {
  applyTheme(App.theme);
  document.getElementById('intern-search')?.addEventListener('input', () => {
    renderInternshipTable(App.internships);
  });
  const today = new Date().toISOString().split('T')[0];
  if (document.getElementById('log-date'))    document.getElementById('log-date').value    = today;
  if (document.getElementById('intern-start')) document.getElementById('intern-start').value = today;
});
