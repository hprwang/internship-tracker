<?php
/**
 * Shared company sidebar partial.
 *
 * Pages `require_once` this after `$user = requireCompanyAuth();` (defines
 * renderCompanySidebar, no output), then emit `<?php renderCompanySidebar($user, 'dashboard'); ?>`
 * where the `<aside class="sidebar">` should appear. `$activePage` is one of:
 * dashboard | internships | applications | profile.
 * `$roleText` defaults to "Company Admin"; company_dashboard passes the company name.
 */

function renderCompanySidebar(array $user, string $activePage, ?string $roleText = null): void {
    $items = [
        'dashboard'    => ['company_dashboard.php', 'fa-chart-pie', 'Dashboard'],
        'internships'  => ['company_internships.php', 'fa-briefcase', 'Internships'],
        'applications' => ['company_applications.php', 'fa-file-signature', 'Applications'],
        'profile'      => ['company_profile.php', 'fa-user-cog', 'Company Profile'],
    ];
    $name = $user['full_name'] ?? 'Company User';
    $roleText = $roleText ?? 'Company Admin';
    $initial = strtoupper(substr($name, 0, 1));
    ?>
  <aside class="sidebar">
    <a class="sidebar-logo" href="company_dashboard.php">
      <div class="logo-icon"><i class="fas fa-building"></i></div>
      <div class="logo-text">Intern<span>Track</span></div>
    </a>

    <div class="nav-menu">
      <div class="nav-label">Menu</div>
<?php foreach ($items as $key => [$href, $icon, $label]) { ?>
      <a class="nav-item<?= $key === $activePage ? ' active' : '' ?>" href="<?= e($href) ?>"><span class="icon"><i class="fas <?= e($icon) ?>"></i></span> <?= e($label) ?></a>
<?php } ?>
    </div>

    <div class="sidebar-footer">
      <div class="user-chip">
        <div class="user-avatar"><?= e($initial) ?></div>
        <div>
          <div class="user-name"><?= e($name) ?></div>
          <div class="user-role"><?= e($roleText) ?></div>
        </div>
      </div>
      <a class="logout-btn" href="#" onclick="handleLogout(event)"><i class="fas fa-sign-out-alt"></i> Logout</a>
    </div>
  </aside>
<?php
}
