<?php require_once __DIR__ . '/../../backend/middleware/security.php'; require_permission('ports.view', false); ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Port Intelligence | FreightIQ Marine</title>
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
    <div><h2>⚓ Port Intelligence Center</h2>
    <p class="subtle">All-India coast port constraints, congestion, and berthing limitations — East &amp; West</p></div>
  </header>

  <section class="panel" style="margin-bottom:16px;">
    <div class="panel-controls">
      <button class="btn-secondary coast-filter active" data-coast="">All Coasts</button>
      <button class="btn-secondary coast-filter" data-coast="East">East Coast (Bay of Bengal)</button>
      <button class="btn-secondary coast-filter" data-coast="West">West Coast (Arabian Sea)</button>
    </div>
  </section>

  <section id="portCards" class="two-col"></section>

<?php include __DIR__ . '/../includes/footer.php'; ?>
<script src="../assets/js/ocean.js"></script>
<script src="../assets/js/api.js"></script>
<script src="../assets/js/i18n.js"></script>
<script src="../assets/js/currency.js"></script>
<script>FIQ.setCsrf(<?= json_encode(csrf_token()) ?>);</script>
<script src="../assets/js/app.js"></script>
<script>
let ALL_PORTS = [];
async function loadPorts() {
  try {
    ALL_PORTS = await FIQ.get('ports.php');
    renderPorts('');
  } catch (e) { console.error(e); if (e.status === 401) window.location.href = '../login.php'; }
}
function renderPorts(coastFilter) {
  const container = document.getElementById('portCards');
  const list = coastFilter ? ALL_PORTS.filter(p => p.coast === coastFilter) : ALL_PORTS;
  container.innerHTML = list.map(p => {
    const lvl = p.congestion_level || 'Low';
    const dot = lvl === 'High' || lvl === 'Severe' ? '🔴' : lvl === 'Moderate' ? '🟡' : '🟢';
    const coastIcon = p.coast === 'West' ? '🌅' : '🌄';
    return `
    <div class="panel">
      <div class="panel-head"><h3>${dot} ${p.name} <span class="subtle">(${p.code})</span></h3><span class="status-badge status-${p.status.toLowerCase()}">${p.status}</span></div>
      <p class="subtle">${coastIcon} ${p.coast} Coast · ${p.state}</p>
      <div class="explain-grid">
        <div><span class="explain-label">Max Draft</span>${p.max_draft_m} m</div>
        <div><span class="explain-label">Max LOA</span>${p.max_loa_m} m</div>
        <div><span class="explain-label">Max Beam</span>${p.max_beam_m} m</div>
        <div><span class="explain-label">Handling Capacity</span>${Number(p.cargo_handling_capacity_tpd).toLocaleString()} t/day</div>
        <div><span class="explain-label">Handling Rate</span>${fiqMoney(p.cargo_handling_rate_usd_per_tonne)}/t</div>
        <div><span class="explain-label">Historical Turnaround</span>${p.historical_turnaround_days} days</div>
        <div><span class="explain-label">Current Congestion</span>${lvl} (${p.congestion_index || '—'}x)</div>
        <div><span class="explain-label">Est. Turnaround Now</span>${p.estimated_turnaround_days || p.historical_turnaround_days} days</div>
        <div><span class="explain-label">Weather Status</span>${p.weather_status || 'Clear'}</div>
      </div>
      <p class="subtle" style="margin-top:10px;">${p.berthing_limitations}</p>
      <p class="subtle" style="margin-top:4px;">Data source: ${p.data_source}</p>
    </div>`;
  }).join('');
}
document.querySelectorAll('.coast-filter').forEach(btn => {
  btn.addEventListener('click', () => {
    document.querySelectorAll('.coast-filter').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    renderPorts(btn.dataset.coast);
  });
});
document.addEventListener('fiq-refresh-money', () => {
  const active = document.querySelector('.coast-filter.active');
  renderPorts(active ? active.dataset.coast : '');
});
loadPorts();
</script>
</body>
</html>
