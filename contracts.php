<?php require_once __DIR__ . '/../../backend/middleware/security.php'; require_permission('contracts.view', false); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Contract Intelligence | FreightIQ Marine</title>
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
    <div><h2>📄 Contract Intelligence</h2>
    <p class="subtle">Spot vs Short-Term vs Medium-Term comparison, and multi-voyage contract creation</p></div>
  </header>

  <section class="panel">
    <h3>Compare Contract Types</h3>
    <form id="compareForm" class="grid-form">
      <label>Route <select name="route_id" id="routeSelect" required></select></label>
      <label>Vessel Class <select name="vessel_class_id" id="classSelect" required></select></label>
      <label>Volume per Voyage (t) <input type="number" name="volume_per_voyage" value="50000" min="1000" required></label>
      <button type="submit" class="btn-primary">Compare 📊</button>
    </form>
  </section>

  <section id="compareResult" class="hidden">
    <section class="three-col" id="compareCards"></section>
    <section class="panel">
      <p class="recommendation-text" id="savingsSummary"></p>
      <p class="subtle" id="disclaimer"></p>
    </section>
  </section>

  <?php if (has_permission('contracts.create')): ?>
  <section class="panel">
    <h3>Create Contract</h3>
    <form id="contractForm" class="grid-form">
      <label>Route <select name="route_id" id="routeSelect2" required></select></label>
      <label>Vessel Class <select name="vessel_class_id" id="classSelect2" required></select></label>
      <label>Contract Type <select name="contract_type"><option>Spot</option><option>Short-Term (COA)</option><option>Medium-Term (COA)</option></select></label>
      <label>Number of Voyages <input type="number" name="num_voyages" min="1" value="1" required></label>
      <label>Volume / Voyage (t) <input type="number" name="cargo_volume_tonnes_per_voyage" min="1000" required></label>
      <label>Target Rate (USD/t) <input type="number" step="0.01" name="target_rate_usd_per_tonne" required></label>
      <label>Start Date <input type="date" name="start_date" required></label>
      <label>End Date <input type="date" name="end_date"></label>
      <button type="submit" class="btn-primary">Create Contract</button>
    </form>
  </section>
  <?php endif; ?>

  <section class="panel">
    <div class="panel-head">
      <h3>Contract Ledger</h3>
      <button class="btn-secondary" id="exportContractsCsv">⬇️ Export CSV</button>
    </div>
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Route</th><th>Vessel</th><th>Type</th><th>Voyages</th><th>Volume/Voyage</th><th>Rate</th><th>Status</th><th>Est. Savings</th></tr></thead>
        <tbody id="contractTableBody"><tr><td colspan="8" class="empty">Loading…</td></tr></tbody>
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
<script src="../assets/js/export_csv.js"></script>
<script src="../assets/js/contracts.js"></script>
</body>
</html>
