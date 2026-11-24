<?php
require_once __DIR__ . '/../backend/middleware/security.php';
require_login();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Command Dashboard | FreightIQ Marine</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/ocean.css">
<script src="assets/js/theme.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body data-reduced-motion="false">
<canvas id="ocean-canvas"></canvas>
<div class="aurora-sweep"></div>
<div class="wave-layer wave-back fixed-wave"></div>
<div class="wave-layer wave-accent fixed-wave"></div>
<?php include __DIR__ . '/includes/nav.php'; ?>

  <header class="topbar">
    <div>
      <h2>Maritime Command Center</h2>
      <p class="subtle">Real-time-style freight intelligence for bulk cargo procurement across all India coasts</p>
    </div>
    <div class="topbar-right">
      <span class="data-badge">⚠ Simulated demo data</span>
      <div class="bell-wrap" id="notifBell">🔔<span class="bell-dot"></span>
        <div class="notif-panel hidden" id="notifPanel"></div>
      </div>
      <div class="topbar-clock" id="live-clock"></div>
    </div>
  </header>

  <section class="kpi-grid" id="kpiGrid"></section>

  <section class="two-col">
    <div class="panel">
      <h3>Vessel Availability by Class</h3>
      <canvas id="vesselAvailChart" height="180"></canvas>
    </div>
    <div class="panel">
      <h3>Port Congestion Index (live snapshot)</h3>
      <canvas id="congestionChart" height="180"></canvas>
    </div>
  </section>

  <section class="two-col">
    <div class="panel">
      <h3>Recent Alerts</h3>
      <ul class="alert-list" id="recentAlerts"><li class="empty">Loading…</li></ul>
      <a href="pages/alerts.php" class="link-more">View all alerts →</a>
    </div>
    <div class="panel">
      <h3>Quick Actions</h3>
      <div class="quick-actions">
        <a href="pages/forecast.php" class="qa-card">🌊<span>Generate Forecast</span></a>
        <a href="pages/vessels.php" class="qa-card">🧭<span>Optimize Vessel</span></a>
        <a href="pages/shipper.php" class="qa-card">📦<span>New Cargo Request</span></a>
        <a href="pages/fleet.php" class="qa-card">🚢<span>Track Fleet on Map</span></a>
      </div>
    </div>
  </section>

<?php include __DIR__ . '/includes/footer.php'; ?>
<script src="assets/js/ocean.js"></script>
<script src="assets/js/api.js"></script>
<script src="assets/js/i18n.js"></script>
<script src="assets/js/currency.js"></script>
<script>FIQ.setCsrf(<?= json_encode(csrf_token()) ?>);</script>
<script src="assets/js/app.js"></script>
<script src="assets/js/dashboard.js"></script>
</body>
</html>
