<?php require_once __DIR__ . '/../../backend/middleware/security.php'; require_login(false); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Reports Center | FreightIQ Marine</title>
<link rel="stylesheet" href="../assets/css/style.css">
<link rel="stylesheet" href="../assets/css/ocean.css">
<script src="../assets/js/theme.js"></script>
<style>
  @media print {
    .sidebar, .topbar, .panel form, #reportControls, .grid-overlay, .wave-layer, #ocean-canvas, .bubble-field, .btn-primary, .btn-secondary { display: none !important; }
    body, .main-content { background: #fff !important; color: #000 !important; padding: 0 !important; }
    .report-sheet { border: none !important; box-shadow: none !important; color: #000 !important; background: #fff !important; }
    .report-sheet * { color: #000 !important; }
    .app-shell { display: block !important; }
  }
  .report-sheet { background: #0d2634; border: 1px solid var(--card-border); border-radius: 12px; padding: 28px; }
  .report-sheet h2 { border-bottom: 2px solid var(--cyan); padding-bottom: 10px; }
  .report-meta { font-size: 12px; color: var(--text-muted); margin-bottom: 18px; }
  .report-table { width: 100%; border-collapse: collapse; margin: 14px 0; }
  .report-table th, .report-table td { border: 1px solid var(--card-border); padding: 8px 10px; text-align: left; font-size: 13px; }
  .report-footer { margin-top: 24px; font-size: 11px; color: var(--text-muted); border-top: 1px solid var(--card-border); padding-top: 10px; }
</style>
</head>
<body data-reduced-motion="false">
<canvas id="ocean-canvas"></canvas>
<div class="aurora-sweep"></div>
<div class="wave-layer wave-back fixed-wave"></div>
<div class="wave-layer wave-accent fixed-wave"></div>
<?php include __DIR__ . '/../includes/nav.php'; ?>

  <header class="topbar">
    <div><h2>🖨️ Reports Center</h2>
    <p class="subtle">Generate professional, printable/downloadable reports for stakeholders</p></div>
  </header>

  <section class="panel" id="reportControls">
    <form id="reportForm" class="grid-form">
      <label>Report Type
        <select name="report_type" id="reportType" required>
          <option value="forecast">Freight Forecast</option>
          <option value="vessel">Vessel Recommendation</option>
          <option value="port">Port Compatibility</option>
          <option value="risk">Risk Analysis</option>
          <option value="contract">Contract Comparison</option>
          <option value="cargo">Shipper Cargo Requirement</option>
        </select>
      </label>
      <div id="dynamicFields" class="grid-form" style="display:contents;"></div>
      <button type="submit" class="btn-primary">Generate Report 📄</button>
    </form>
  </section>

  <div id="loadingState" class="hidden loading-ship">🚢 <span>Compiling report…</span></div>

  <section id="reportOutput" class="hidden">
    <div style="margin-bottom:12px;">
      <button class="btn-secondary" onclick="window.print()">🖨️ Print / Save as PDF</button>
      <button class="btn-secondary" id="downloadCsvBtn">⬇️ Download Data (CSV)</button>
    </div>
    <div class="report-sheet" id="reportSheet"></div>
  </section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="../assets/js/ocean.js"></script>
<script src="../assets/js/api.js"></script>
<script src="../assets/js/i18n.js"></script>
<script src="../assets/js/currency.js"></script>
<script>FIQ.setCsrf(<?= json_encode(csrf_token()) ?>);</script>
<script src="../assets/js/app.js"></script>
<script src="../assets/js/export_csv.js"></script>
<script src="../assets/js/reports.js"></script>
</body>
</html>
