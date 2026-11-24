<?php
$u = current_user();
$page = basename($_SERVER['PHP_SELF']);
$root = '/FreightIQ-Marine/frontend/';
function navlink(string $root, string $href, string $icon, string $label, string $page, string $current, string $perm = '', string $i18nKey = ''): string {
    if ($perm && !has_permission($perm)) return '';
    $active = ($page === $current) ? 'active' : '';
    $i18nAttr = $i18nKey ? " data-i18n=\"{$i18nKey}\"" : '';
    return "<a href=\"{$root}{$href}\" class=\"{$active}\">{$icon} <span{$i18nAttr}>{$label}</span></a>";
}
?>
<div class="grid-overlay"></div>
<div class="app-shell">
<aside class="sidebar">
  <div class="brand-mini">⚓ FreightIQ<span> Marine</span></div>
  <div class="brand-sub">SAIL · All-India Coast Bulk Cargo</div>
  <nav>
    <?= navlink($root, 'index.php', '📊', 'Command Dashboard', 'index.php', $page, 'dashboard.view', 'nav_dashboard') ?>
    <?= navlink($root, 'pages/forecast.php', '🌊', 'Freight Forecast', 'forecast.php', $page, 'forecast.view', 'nav_forecast') ?>
    <?= navlink($root, 'pages/fleet.php', '🚢', 'Fleet & GPS Tracking', 'fleet.php', $page, 'gps.view', 'nav_fleet') ?>
    <?= navlink($root, 'pages/ports.php', '⚓', 'Port Intelligence', 'ports.php', $page, 'ports.view', 'nav_ports') ?>
    <?= navlink($root, 'pages/vessels.php', '🧭', 'Vessel Optimizer', 'vessels.php', $page, 'vessels.optimize', 'nav_vessels') ?>
    <?= navlink($root, 'pages/shipper.php', '📦', 'Shipper Workspace', 'shipper.php', $page, 'cargo.view', 'nav_shipper') ?>
    <?= navlink($root, 'pages/contracts.php', '📄', 'Contract Intelligence', 'contracts.php', $page, 'contracts.view', 'nav_contracts') ?>
    <?= navlink($root, 'pages/risk.php', '⚠️', 'Risk Center', 'risk.php', $page, 'risk.view', 'nav_risk') ?>
    <?= navlink($root, 'pages/alerts.php', '🔔', 'Alerts', 'alerts.php', $page, 'alerts.view', 'nav_alerts') ?>
    <?= navlink($root, 'pages/audit.php', '🛡️', 'Audit Log', 'audit.php', $page, 'audit.view', 'nav_audit') ?>
    <?= navlink($root, 'pages/admin_users.php', '👤', 'User Management', 'admin_users.php', $page, 'admin.manage_users', 'nav_users') ?>
    <?= navlink($root, 'pages/reports.php', '🖨️', 'Reports Center', 'reports.php', $page, 'dashboard.view', 'nav_reports') ?>
  </nav>
  <div class="sidebar-footer">
    <div class="settings-row">
      <label class="settings-label" data-i18n="language">Language</label>
      <div id="langSwitcherBox"></div>
    </div>
    <div class="settings-row">
      <label class="settings-label" data-i18n="currency">Currency</label>
      <div id="currencySwitcherBox"></div>
    </div>
    <div class="settings-row">
      <label class="settings-label" data-i18n="theme">Theme</label>
      <div id="themeSwitcherBox"></div>
    </div>
    <div class="motion-toggle"><span data-i18n="reduced_motion">Reduced motion</span> <input type="checkbox" id="reducedMotionToggle"></div>
    <div class="user-chip">
      <div class="avatar"><?= strtoupper(substr($u['name'],0,1)) ?></div>
      <div><div class="uname"><?= clean($u['name']) ?></div><div class="urole"><?= clean($u['role_name']) ?></div></div>
    </div>
    <a href="#" id="logoutBtn" class="logout-link" data-i18n="btn_signout">Sign out ⏻</a>
  </div>
</aside>
<main class="main-content">
