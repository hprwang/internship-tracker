<?php
/**
 * Shared header partial — notification bell component.
 *
 * Pages `require_once` this after `$user = requireAuth();` (defines functions,
 * no output), then emit `<?= renderNotifBell($user) ?>` where the bell should
 * appear (typically the sidebar's user area). `js/notifications.js` drives the
 * badge and dropdown. Styles are scoped and fall back to the page's dark tokens.
 */

function notifBellCount(int $userId): int {
    try {
        $n = Database::getConnection()->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $n->execute([$userId]);
        return (int)$n->fetchColumn();
    } catch (Throwable $e) {
        error_log('notif bell: ' . $e->getMessage());
        return 0;
    }
}

function renderNotifBell(array $user): string {
    $count = notifBellCount((int)($user['id'] ?? 0));
    return '<style>
      .notif-bell { position: relative; display: inline-flex; align-items: center; justify-content: center; width: 38px; height: 38px; border-radius: 10px; background: var(--bg-card, #18162B); border: 1px solid var(--border-subtle, rgba(167,139,250,.18)); color: var(--text-secondary, rgba(245,243,255,.76)); cursor: pointer; flex-shrink: 0; transition: color var(--transition, 180ms), border-color var(--transition, 180ms), box-shadow var(--transition, 180ms); }
      .notif-bell:hover, .notif-bell:focus-visible { color: var(--primary, #7C3AED); border-color: var(--primary, #7C3AED); box-shadow: 0 0 0 3px rgba(124,58,237,.18); outline: none; }
      .notif-badge { position: absolute; top: -6px; right: -6px; min-width: 18px; height: 18px; padding: 0 4px; border-radius: 9px; background: var(--danger, #EF4444); color: #fff; font-size: 0.68rem; font-weight: 700; display: flex; align-items: center; justify-content: center; }
      .notif-dropdown { position: absolute; right: 0; top: calc(100% + 8px); width: 320px; max-height: 380px; overflow-y: auto; background: var(--bg-card, #18162B); border: 1px solid var(--border-subtle, rgba(167,139,250,.18)); border-radius: 12px; box-shadow: 0 12px 32px rgba(30,27,75,.45); z-index: 100; }
      .notif-item { display: flex; gap: 0.6rem; padding: 0.75rem; border-bottom: 1px solid var(--border-subtle, rgba(167,139,250,.18)); cursor: pointer; }
      .notif-item:last-child { border-bottom: none; }
      .notif-item:hover { background: var(--bg-elevated, #211C3A); }
      .notif-title { font-weight: 600; font-size: 0.85rem; color: var(--text-primary, #F5F3FF); }
      .notif-msg { font-size: 0.78rem; color: var(--text-muted, rgba(245,243,255,.56)); }
      .notif-empty { padding: 1.25rem; text-align: center; color: var(--text-muted, rgba(245,243,255,.56)); font-size: 0.85rem; }
    </style>
    <div class="notif-bell" id="notifBell" tabindex="0" role="button" aria-label="Notifications" title="Notifications">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
      </svg>
      <span class="notif-badge" id="notifBadge"' . ($count > 0 ? '' : ' hidden') . '>' . $count . '</span>
      <div class="notif-dropdown" id="notifDropdown" hidden></div>
    </div>';
}
