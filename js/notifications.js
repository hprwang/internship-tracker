/* Notification bell: unread count badge + dropdown list. */
const inPhpFolder = window.location.pathname.includes('/php/');
const notifPath = inPhpFolder ? 'notifications.php' : 'php/notifications.php';

function escNotif(str) {
  return String(str == null ? '' : str)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

async function refreshNotifCount() {
  const badge = document.getElementById('notifBadge');
  if (!badge) return;
  try {
    const r = await fetch(notifPath + '?action=unread_count');
    const d = await r.json();
    if (d.success) { badge.textContent = d.count; badge.hidden = d.count === 0; }
  } catch (e) { /* silent */ }
}

async function loadNotifList() {
  const box = document.getElementById('notifDropdown');
  if (!box) return;
  try {
    const r = await fetch(notifPath + '?action=list');
    const d = await r.json();
    const items = (d.success && d.notifications) ? d.notifications : [];
    if (!items.length) {
      box.innerHTML = '<div class="notif-empty">You are all caught up.</div>';
      return;
    }
    box.innerHTML = items.map(n =>
      '<div class="notif-item" data-id="' + escNotif(n.id) + '">' +
        '<div><div class="notif-title">' + escNotif(n.title) + '</div>' +
        '<div class="notif-msg">' + escNotif(n.message) + '</div></div></div>'
    ).join('');
    box.querySelectorAll('.notif-item').forEach(el => {
      el.addEventListener('click', () => markNotifRead(el.dataset.id, el));
    });
  } catch (e) { /* silent */ }
}

async function markNotifRead(id, el) {
  try {
    await fetch(notifPath + '?action=mark_read&id=' + encodeURIComponent(id));
    if (el) el.remove();
    refreshNotifCount();
  } catch (e) { /* silent */ }
}

function initNotifications() {
  const bell = document.getElementById('notifBell');
  if (!bell) return;
  refreshNotifCount();
  setInterval(refreshNotifCount, 60000);
  bell.addEventListener('click', (e) => {
    const dd = document.getElementById('notifDropdown');
    if (!dd) return;
    if (dd.hidden) { loadNotifList(); dd.hidden = false; }
    else { dd.hidden = true; }
    e.stopPropagation();
  });
  document.addEventListener('click', () => {
    const dd = document.getElementById('notifDropdown');
    if (dd && !dd.hidden) dd.hidden = true;
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initNotifications);
} else {
  initNotifications();
}
