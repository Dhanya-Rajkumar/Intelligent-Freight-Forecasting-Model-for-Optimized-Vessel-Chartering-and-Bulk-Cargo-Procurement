<?php require_once __DIR__ . '/../../backend/middleware/security.php'; require_permission('vessels.optimize', false); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vessel Optimizer | FreightIQ Marine</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/ocean.css">
<script src="../assets/js/theme.js"></script>
</head>
<body data-reduced-motion="false">
<canvas id="ocean-canvas"></canvas>
<div class="aurora-sweep"></div>
<div class="wave-layer wave-back fixed-wave"></div>
<div class="wave-layer wave-accent fixed-wave"></div>
<?php include __DIR__ . '/../includes/nav.php'; ?>

  <header class="topbar">
    <div><h2>🧭 Vessel Optimization Engine</h2>
    <p class="subtle">Best-fit vessel class by cargo volume, port constraints, congestion and availability — every result explained</p></div>
  </header>

  <section class="panel">
    <form id="optForm" class="grid-form">
      <label>Destination Port <select name="port_id" id="portSelect" required></select></label>
      <label>Route (optional, for distance/voyage-time accuracy) <select name="route_id" id="routeSelect"><option value="">— none —</option></select></label>
      <label>Cargo Quantity (tonnes) <input type="number" name="cargo_volume" min="1000" required placeholder="e.g. 60000"></label>
      <button type="submit" class="btn-primary">Optimize Vessel Selection 🧭</button>
    </form>
  </section>

  <div id="loadingState" class="hidden loading-ship">🚢 <span>Sounding the harbour…</span></div>

  <section id="optResult" class="hidden">
    <section class="panel">
      <h3>Port Snapshot: <span id="portName"></span></h3>
      <div class="explain-grid" id="portSnapshot"></div>
    </section>
    <section class="panel">
      <h3>Ranked Vessel Recommendations</h3>
      <div id="candidateList"></div>
    </section>
  </section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="../assets/js/ocean.js"></script>
<script src="../assets/js/api.js"></script>
<script src="../assets/js/i18n.js"></script>
<script src="../assets/js/currency.js"></script>
<script>FIQ.setCsrf(<?= json_encode(csrf_token()) ?>);</script>
<script src="../assets/js/app.js"></script>
<script src="../assets/js/ship_svg.js"></script>
<script src="../assets/js/vessels.js"></script>
</body>
</html>
