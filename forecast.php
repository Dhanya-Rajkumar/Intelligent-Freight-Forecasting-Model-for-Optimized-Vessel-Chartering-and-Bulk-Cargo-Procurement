<?php require_once __DIR__ . '/../../backend/middleware/security.php'; require_permission('forecast.view', false); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Freight Forecast | FreightIQ Marine</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/ocean.css">
<script src="../assets/js/theme.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>
</head>
<body data-reduced-motion="false">
<canvas id="ocean-canvas"></canvas>
<div class="aurora-sweep"></div>
<div class="wave-layer wave-back fixed-wave"></div>
<div class="wave-layer wave-accent fixed-wave"></div>
<?php include __DIR__ . '/../includes/nav.php'; ?>

  <header class="topbar">
    <div><h2>🌊 Freight Forecasting Engine</h2>
    <p class="subtle">Explainable multi-horizon predictions — clearly labelled as simulated demo data</p></div>
    <span class="data-badge">Model: Weighted Trend + Seasonality</span>
  </header>

  <section class="panel">
    <form id="forecastForm" class="grid-form">
      <label>Trade Route <select name="route_id" id="routeSelect" required></select></label>
      <label>Vessel Class <select name="vessel_class_id" id="classSelect" required></select></label>
      <button type="submit" class="btn-primary">Run Forecast 🌊</button>
    </form>
  </section>

  <div id="loadingState" class="hidden loading-ship">🚢 <span>Charting the currents…</span></div>

  <section id="resultArea" class="hidden">
    <section class="three-col">
      <div class="panel"><h3>7-Day Forecast</h3><div class="big-number" id="f7rate">--</div><p class="subtle" id="f7range"></p><p class="subtle" id="f7conf"></p></div>
      <div class="panel"><h3>14-Day Forecast</h3><div class="big-number accent" id="f14rate">--</div><p class="subtle" id="f14range"></p><p class="subtle" id="f14conf"></p></div>
      <div class="panel"><h3>30-Day Forecast</h3><div class="big-number" id="f30rate">--</div><p class="subtle" id="f30range"></p><p class="subtle" id="f30conf"></p></div>
    </section>

    <section class="panel">
      <h3>Historical Trend + Forecast Points</h3>
      <canvas id="forecastChart" height="90"></canvas>
      <p class="subtle" id="dataFreshness" style="margin-top:8px;"></p>
    </section>

    <section class="panel">
      <h3>🧭 Market-Entry Recommendation</h3>
      <p class="recommendation-text" id="marketEntryLabel"></p>
      <ul class="reason-list" id="marketEntryReasons"></ul>
    </section>

    <section class="panel">
      <h3>🔀 Counterfactual What-If Simulator</h3>
      <p class="subtle" style="margin-bottom:10px;">Adjust assumptions and see the causal impact vs. the 30-day baseline forecast.</p>
      <form id="cfForm" class="grid-form">
        <label>Horizon (days) <input type="number" name="horizon" min="7" max="180" value="30"></label>
        <label>Demand Change (%) <input type="range" name="demand_change_pct" min="-50" max="50" value="0" oninput="this.nextElementSibling.textContent=this.value+'%'"><output>0%</output></label>
        <label>Port Congestion Change (%) <input type="range" name="congestion_change_pct" min="-50" max="100" value="0" oninput="this.nextElementSibling.textContent=this.value+'%'"><output>0%</output></label>
        <label>Bunker Price Change (%) <input type="range" name="bunker_change_pct" min="-50" max="100" value="0" oninput="this.nextElementSibling.textContent=this.value+'%'"><output>0%</output></label>
        <button type="submit" class="btn-secondary">Run Simulation</button>
      </form>
      <div id="cfResult" class="hidden" style="margin-top:14px;">
        <div class="two-col">
          <div><span class="explain-label">Baseline</span><div class="big-number" id="cfBaseline">--</div></div>
          <div><span class="explain-label">Counterfactual</span><div class="big-number accent" id="cfAdjusted">--</div><p class="subtle" id="cfDelta"></p></div>
        </div>
        <ul class="reason-list" id="cfAssumptions" style="margin-top:10px;"></ul>
      </div>
    </section>
  </section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="../assets/js/ocean.js"></script>
<script src="../assets/js/api.js"></script>
<script src="../assets/js/i18n.js"></script>
<script src="../assets/js/currency.js"></script>
<script>FIQ.setCsrf(<?= json_encode(csrf_token()) ?>);</script>
<script src="../assets/js/app.js"></script>
<script src="../assets/js/forecast.js"></script>
</body>
</html>
