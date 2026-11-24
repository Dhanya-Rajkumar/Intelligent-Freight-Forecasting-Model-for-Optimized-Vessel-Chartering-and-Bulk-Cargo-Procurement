<?php require_once __DIR__ . '/../../backend/middleware/security.php'; require_permission('cargo.view', false); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Shipper Workspace | FreightIQ Marine</title>
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
    <div><h2>📦 Shipper Workspace</h2>
    <p class="subtle">Submit a cargo requirement and get an instant vessel + freight recommendation</p></div>
  </header>

  <?php if (has_permission('cargo.create')): ?>
  <section class="panel">
    <h3>New Cargo Requirement</h3>
    <form id="cargoForm" class="grid-form">
      <label>Commodity <select name="commodity_id" id="commoditySelect" required></select></label>
      <label>Origin <select name="origin_id" id="originSelect" required></select></label>
      <label>Destination Port <select name="destination_port_id" id="destSelect" required></select></label>
      <label>Quantity (tonnes) <input type="number" name="quantity_tonnes" min="1000" required></label>
      <label>Loading Window Start <input type="date" name="loading_window_start"></label>
      <label>Loading Window End <input type="date" name="loading_window_end"></label>
      <label>Delivery Window Start <input type="date" name="delivery_window_start"></label>
      <label>Delivery Window End <input type="date" name="delivery_window_end"></label>
      <label>Contract Duration <select name="contract_duration"><option>Spot</option><option>Short-Term</option><option>Medium-Term</option></select></label>
      <button type="submit" class="btn-primary">Submit & Get Recommendation</button>
    </form>
  </section>
  <?php endif; ?>

  <div id="loadingState" class="hidden loading-ship">🚢 <span>Matching cargo to fleet…</span></div>

  <section id="recResult" class="hidden panel">
    <h3>Instant Recommendation</h3>
    <div id="recContent"></div>
  </section>

  <section class="panel">
    <h3>My Cargo Requests</h3>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Commodity</th><th>Route</th><th>Qty</th><th>Duration</th><th>Recommended Vessel</th><th>Est. Freight</th><th>Risk</th><th>Status</th></tr></thead>
        <tbody id="cargoTableBody"><tr><td colspan="8" class="empty">Loading…</td></tr></tbody>
      </table>
    </div>
  </section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="../assets/js/ocean.js"></script>
<script src="../assets/js/api.js"></script>
<script src="../assets/js/i18n.js"></script>
<script src="../assets/js/currency.js"></script>
<script>FIQ.setCsrf(<?= json_encode(csrf_token()) ?>);</script>
<script src="../assets/js/app.js"></script>
<script src="../assets/js/shipper.js"></script>
</body>
</html>
