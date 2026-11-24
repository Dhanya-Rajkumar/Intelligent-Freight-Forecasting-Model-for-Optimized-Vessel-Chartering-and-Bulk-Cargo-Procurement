<?php require_once __DIR__ . '/../../backend/middleware/security.php'; require_permission('audit.view', false); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Audit Log | FreightIQ Marine</title>
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
    <div><h2>🛡️ Audit Log</h2>
    <p class="subtle">Every authentication, forecast, optimization, contract, and alert action — traceable by user and IP</p></div>
  </header>

  <section class="panel">
    <div class="table-wrap">
      <table class="data-table">
        <thead><tr><th>Time</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
        <tbody id="auditTableBody"><tr><td colspan="6" class="empty">Loading…</td></tr></tbody>
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
<script>
(async function () {
  try {
    const res = await FIQ.get('audit.php');
    document.getElementById('auditTableBody').innerHTML = res.data.length ? res.data.map(a => `
      <tr>
        <td class="mono">${new Date(a.created_at).toLocaleString()}</td>
        <td>${a.full_name || 'System'}</td>
        <td>${a.action}</td>
        <td>${a.entity_type ? a.entity_type + ' #' + a.entity_id : '—'}</td>
        <td>${a.details || '—'}</td>
        <td class="mono">${a.ip_address}</td>
      </tr>`).join('') : '<tr><td colspan="6" class="empty">No audit entries yet.</td></tr>';
  } catch (e) { console.error(e); if (e.status === 401) window.location.href = '../login.php'; }
})();
</script>
</body>
</html>
