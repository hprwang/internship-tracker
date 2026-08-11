/* Analytics charts: role-aware loaders for student/admin/company dashboards. */
const inPhpFolder = window.location.pathname.includes('/php/');
const analyticsPath = inPhpFolder ? 'analytics.php' : 'php/analytics.php';

const STATUS_COLORS = ['#22C55E', '#3B82F6', '#F59E0B', '#10B981', '#8B5CF6', '#EF4444', '#6B7280'];
const APP_STATUS_COLORS = ['#F59E0B', '#3B82F6', '#22C55E', '#EF4444'];

async function fetchAnalytics(scope) {
  try {
    const r = await fetch(analyticsPath + '?scope=' + encodeURIComponent(scope));
    const d = await r.json();
    return d.success && d.data ? d.data : null;
  } catch (e) { return null; }
}

async function loadAnalytics(scope) {
  const el = document.getElementById('analyticsCharts');
  if (!el || typeof Chart === 'undefined') return;
  const data = await fetchAnalytics(scope);
  if (!data) return;
  el.innerHTML = '<canvas id="statusChart"></canvas><canvas id="timelineChart"></canvas>';
  new Chart(document.getElementById('statusChart'), { type: 'doughnut', data: {
    labels: data.status.map(s => s.status),
    datasets: [{ data: data.status.map(s => s.c), backgroundColor: STATUS_COLORS }] } });
  new Chart(document.getElementById('timelineChart'), { type: 'line', data: {
    labels: data.timeline.map(t => t.ym),
    datasets: [{ label: 'Applications', data: data.timeline.map(t => t.c), borderColor: '#22C55E', tension: 0.3 }] } });
}

async function loadAdminAnalytics() {
  const el = document.getElementById('analyticsCharts');
  if (!el || typeof Chart === 'undefined') return;
  const data = await fetchAnalytics('admin');
  if (!data) return;
  const k = data.kpis || {};
  const set = (id, v) => { const n = document.getElementById(id); if (n) n.textContent = v; };
  set('kpi-students', k.students); set('kpi-companies', k.companies);
  set('kpi-internships', k.internships); set('kpi-applications', k.applications);
  el.innerHTML = '<canvas id="regChart"></canvas><canvas id="adminStatusChart"></canvas><canvas id="topChart"></canvas>';
  new Chart(document.getElementById('regChart'), { type: 'bar', data: {
    labels: data.registrations.map(r => r.ym),
    datasets: [{ label: 'New students', data: data.registrations.map(r => r.c), backgroundColor: '#22C55E' }] } });
  new Chart(document.getElementById('adminStatusChart'), { type: 'doughnut', data: {
    labels: data.statusDist.map(s => s.status),
    datasets: [{ data: data.statusDist.map(s => s.c), backgroundColor: STATUS_COLORS }] } });
  new Chart(document.getElementById('topChart'), { type: 'bar', data: {
    labels: data.topCompanies.map(t => t.name),
    datasets: [{ label: 'Internships', data: data.topCompanies.map(t => t.n), backgroundColor: '#3B82F6' }] } });
}

async function loadCompanyAnalytics() {
  const el = document.getElementById('analyticsCharts');
  if (!el || typeof Chart === 'undefined') return;
  const data = await fetchAnalytics('company');
  if (!data) return;
  el.innerHTML = '<canvas id="perChart"></canvas><canvas id="compStatusChart"></canvas><canvas id="compTimelineChart"></canvas>';
  new Chart(document.getElementById('perChart'), { type: 'bar', data: {
    labels: data.perPosting.map(p => p.title),
    datasets: [{ label: 'Applications', data: data.perPosting.map(p => p.n), backgroundColor: '#22C55E' }] } });
  new Chart(document.getElementById('compStatusChart'), { type: 'doughnut', data: {
    labels: data.statusDist.map(s => s.status),
    datasets: [{ data: data.statusDist.map(s => s.c), backgroundColor: APP_STATUS_COLORS }] } });
  new Chart(document.getElementById('compTimelineChart'), { type: 'line', data: {
    labels: data.timeline.map(t => t.ym),
    datasets: [{ label: 'Applications', data: data.timeline.map(t => t.c), borderColor: '#22C55E', tension: 0.3 }] } });
}

const analyticsScope = document.body.dataset.analyticsScope || 'student';
function initAnalytics() {
  if (analyticsScope === 'admin') loadAdminAnalytics();
  else if (analyticsScope === 'company') loadCompanyAnalytics();
  else loadAnalytics('student');
}
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAnalytics);
} else {
  initAnalytics();
}
