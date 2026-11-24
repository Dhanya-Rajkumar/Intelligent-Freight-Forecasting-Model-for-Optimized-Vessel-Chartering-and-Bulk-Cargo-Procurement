<?php require_once __DIR__ . '/../../backend/middleware/security.php'; require_permission('gps.view', false); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Fleet & GPS Tracking | FreightIQ Marine</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/ocean.css">
<script src="../assets/js/theme.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
</head>
<body data-reduced-motion="false">
<canvas id="ocean-canvas"></canvas>
<div class="aurora-sweep"></div>
<div class="wave-layer wave-back fixed-wave"></div>
<div class="wave-layer wave-accent fixed-wave"></div>
<?php include __DIR__ . '/../includes/nav.php'; ?>

  <header class="topbar">
    <div style="display:flex; align-items:center; gap:12px;">
      <div class="radar-sweep"></div>
      <div><h2>🚢 Fleet & GPS Tracking</h2>
      <p class="subtle">Vessel positions, routes, and port congestion overlay</p></div>
    </div>
    <span class="data-badge">Simulated positions — not live AIS</span>
  </header>

  <section class="panel">
    <div id="gpsMap"></div>
    <p class="subtle" style="margin-top:8px;">🔵 Vessel &nbsp; 🟠 Port &nbsp; 🔴 Congested Port &nbsp;·&nbsp; Click "Use my location" to show your own GPS position (requires browser permission).</p>
    <button class="btn-secondary" id="useMyLocationBtn" style="margin-top:8px;">📍 Use my location</button>
  </section>

  <section class="panel">
    <div class="panel-head">
      <h3>Fleet Roster <span class="subtle">(click a vessel for its Digital Twin)</span></h3>
      <div class="panel-controls">
        <input type="text" id="vesselSearch" placeholder="Search vessel name or IMO…">
        <select id="statusFilter">
          <option value="">All statuses</option>
          <option>Available</option><option>On Voyage</option><option>Idle</option><option>Under Repair</option>
        </select>
        <button class="btn-secondary" id="exportVesselsCsv">⬇️ Export CSV</button>
      </div>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Vessel</th><th>Class</th><th>IMO</th><th>DWT</th><th>Flag</th><th>Status</th><th>ETA</th></tr></thead>
        <tbody id="vesselTableBody"><tr><td colspan="7" class="empty">Loading…</td></tr></tbody>
      </table>
    </div>
  </section>

  <!-- Digital Twin modal -->
  <div id="twinOverlay" class="twin-overlay hidden">
    <div class="twin-modal">
      <button class="twin-close" id="twinClose">✕</button>
      <div id="twinContent"></div>
    </div>
  </div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="../assets/js/ocean.js"></script>
<script src="../assets/js/api.js"></script>
<script src="../assets/js/i18n.js"></script>
<script src="../assets/js/currency.js"></script>
<script>FIQ.setCsrf(<?= json_encode(csrf_token()) ?>);</script>
<script src="../assets/js/app.js"></script>
<script src="../assets/js/export_csv.js"></script>
<script src="../assets/js/ship_svg.js"></script>
<script src="../assets/js/fleet.js"></script>
</body>
</html>
