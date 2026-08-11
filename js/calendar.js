/* Calendar + timeline: month grid from window.CAL_EVENTS, day detail, grouped timeline. */
const CAL = window.CAL_EVENTS || [];
const TYPE_COLORS = {
  applied: '#3B82F6',
  interview: '#F59E0B',
  internship_start: '#22C55E',
  internship_end: '#EF4444',
  progress: '#8B5CF6'
};
const MONTH_NAMES = ['January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'];

const calState = { y: 0, m: 0 };

function pad2(n) { return String(n).padStart(2, '0'); }
function calKey(y, m, d) { return y + '-' + pad2(m + 1) + '-' + pad2(d); }
function escCalHtml(s) {
  return String(s == null ? '' : s)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function eventsByDate() {
  const map = {};
  CAL.forEach(ev => { (map[ev.date] = map[ev.date] || []).push(ev); });
  return map;
}

function renderCalendar() {
  const grid = document.getElementById('calGrid');
  if (!grid) return;
  const map = eventsByDate();
  const y = calState.y, m = calState.m;
  const firstDow = (new Date(y, m, 1).getDay() + 6) % 7; // Monday-first
  const daysInMonth = new Date(y, m + 1, 0).getDate();
  const todayKey = calKey(new Date().getFullYear(), new Date().getMonth(), new Date().getDate());

  let html = '<div class="cal-head">'
    + '<button class="cal-nav" type="button" data-delta="-1" aria-label="Previous month">‹</button>'
    + '<div class="cal-title">' + MONTH_NAMES[m] + ' ' + y + '</div>'
    + '<button class="cal-nav" type="button" data-delta="1" aria-label="Next month">›</button>'
    + '</div>';
  html += '<div class="cal-weekdays">'
    + ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'].map(w => '<div class="cal-weekday">' + w + '</div>').join('')
    + '</div>';
  let cells = '';
  for (let i = 0; i < firstDow; i++) cells += '<div class="cal-cell empty"></div>';
  for (let d = 1; d <= daysInMonth; d++) {
    const k = calKey(y, m, d);
    const evs = map[k] || [];
    const dots = evs.map(e =>
      '<span class="cal-dot" style="background:' + (TYPE_COLORS[e.type] || '#6B7280') + '" title="' + escCalHtml(e.title) + '"></span>'
    ).join('');
    cells += '<div class="cal-cell' + (k === todayKey ? ' today' : '') + (evs.length ? ' has-events' : '') + '" data-date="' + k + '">'
      + '<div class="cal-daynum">' + d + '</div>'
      + (dots ? '<div class="cal-dots">' + dots + '</div>' : '')
      + '</div>';
  }
  grid.innerHTML = html + '<div class="cal-body">' + cells + '</div>';

  grid.querySelectorAll('.cal-nav').forEach(btn => {
    btn.addEventListener('click', () => {
      calState.m += parseInt(btn.dataset.delta, 10);
      if (calState.m < 0) { calState.m = 11; calState.y--; }
      if (calState.m > 11) { calState.m = 0; calState.y++; }
      renderCalendar();
    });
  });
  grid.querySelectorAll('.cal-cell[data-date]').forEach(cell => {
    cell.addEventListener('click', () => showDay(cell.dataset.date));
  });
}

function showDay(dateKey) {
  const detail = document.getElementById('calDayDetail');
  if (!detail) return;
  const evs = eventsByDate()[dateKey] || [];
  if (!evs.length) { detail.hidden = true; return; }
  detail.hidden = false;
  detail.innerHTML = '<div class="cal-detail-title">' + escCalHtml(dateKey) + '</div>'
    + evs.map(e =>
      '<div class="cal-event-row">'
      + '<span class="cal-dot" style="background:' + (TYPE_COLORS[e.type] || '#6B7280') + '"></span> '
      + '<span class="cal-event-type">' + escCalHtml(e.type) + '</span> '
      + escCalHtml(e.title)
      + '</div>'
    ).join('');
}

function renderTimeline() {
  const tl = document.getElementById('calTimeline');
  if (!tl) return;
  const sorted = CAL.slice().sort((a, b) => a.date < b.date ? 1 : -1);
  const groups = {};
  sorted.forEach(e => { const ym = e.date.slice(0, 7); (groups[ym] = groups[ym] || []).push(e); });
  const html = Object.keys(groups).map(ym =>
    '<div class="cal-tl-group"><div class="cal-tl-month">' + ym + '</div>'
    + groups[ym].map(e =>
      '<div class="cal-tl-item">'
      + '<span class="badge ' + escCalHtml(e.type) + '">' + escCalHtml(e.type) + '</span> '
      + '<span class="cal-tl-date">' + escCalHtml(e.date.slice(5)) + '</span> '
      + escCalHtml(e.title)
      + '</div>'
    ).join('')
    + '</div>'
  ).join('');
  tl.innerHTML = html || '<div class="empty-state">No events yet. Track an internship to see its milestones here.</div>';
}

function initCalendar() {
  const grid = document.getElementById('calGrid');
  if (!grid) return;
  const now = new Date();
  calState.y = now.getFullYear();
  calState.m = now.getMonth();
  renderCalendar();
  renderTimeline();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initCalendar);
} else {
  initCalendar();
}
