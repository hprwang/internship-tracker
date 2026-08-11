<?php
/**
 * Shared admin sidebar partial.
 *
 * Pages `require_once` this after `$user = requireAuth();` (defines
 * renderAdminSidebar, no output), then emit `<?php renderAdminSidebar($user, 'dashboard'); ?>`
 * where the `<aside class="sidebar">` should appear. `$activePage` is one of:
 * dashboard | students | companies | internships | applications | reports | settings.
 */

function renderAdminSidebar(array $user, string $activePage): void {
    $items = [
        'dashboard'    => ['admin_dashboard.php', 'fa-chart-pie', 'Overview'],
        'students'     => ['admin_students.php', 'fa-users', 'Students'],
        'companies'    => ['admin_companies.php', 'fa-building', 'Companies'],
        'internships'  => ['admin_internships.php', 'fa-briefcase', 'Internships'],
        'applications' => ['admin_applications.php', 'fa-clipboard-check', 'Applications'],
        'reports'      => ['admin_reports.php', 'fa-chart-bar', 'Reports'],
        'settings'     => ['admin_settings.php', 'fa-cog', 'Settings'],
    ];
    $name = $user['full_name'] ?? 'Admin';
    $initial = strtoupper(substr($name, 0, 1));
    ?>
  <aside class="sidebar">
    <div class="sidebar-logo">
      <div class="logo-icon"><i class="fas fa-clipboard-list"></i></div>
      <div class="logo-text">Intern<span>Track</span></div>
    </div>

    <div class="nav-section">
      <div class="nav-label">Dashboard</div>
      <nav class="nav-menu">
<?php foreach ($items as $key => [$href, $icon, $label]) { ?>
        <?php if ($key === 'settings') continue; ?>
        <a href="<?= e($href) ?>" class="nav-item<?= $key === $activePage ? ' active' : '' ?>"><span class="icon"><i class="fas <?= e($icon) ?>"></i></span> <?= e($label) ?></a>
<?php } ?>
      </nav>
    </div>

    <div class="nav-section">
      <div class="nav-label">System</div>
      <nav class="nav-menu">
        <?php [$href, $icon, $label] = $items['settings']; ?>
        <a href="<?= e($href) ?>" class="nav-item<?= $activePage === 'settings' ? ' active' : '' ?>"><span class="icon"><i class="fas <?= e($icon) ?>"></i></span> <?= e($label) ?></a>
      </nav>
    </div>

    <div class="sidebar-footer">
      <div class="user-chip">
        <div class="user-avatar"><?= e($initial) ?></div>
        <div class="user-info">
          <div class="user-name"><?= e($name) ?></div>
          <div class="user-role">Administrator</div>
        </div>
      </div>
      <button class="logout-btn" onclick="handleLogout()"><span class="icon"><i class="fas fa-sign-out-alt"></i></span> Logout</button>
    </div>
  </aside>
<?php
}
