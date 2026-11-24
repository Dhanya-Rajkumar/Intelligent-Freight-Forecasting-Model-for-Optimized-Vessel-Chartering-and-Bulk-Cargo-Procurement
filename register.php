<?php require_once __DIR__ . '/../backend/middleware/security.php'; if (current_user()) { header('Location: index.php'); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>FreightIQ Marine | Shipper Registration</title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="assets/css/ocean.css">
<script src="assets/js/theme.js"></script>
</head>
<body class="auth-page" data-reduced-motion="false">
<canvas id="ocean-canvas"></canvas>
<div class="aurora-sweep"></div>
<div class="wave-layer wave-back"></div>
<div class="wave-layer wave-front"></div>
<div id="authLangSwitcher" style="position:fixed; top:16px; right:16px; z-index:10;"></div>
<div class="auth-card">
  <div class="brand">
    <div class="brand-icon">⚓</div>
    <h1>FreightIQ<span> Marine</span></h1>
    <p class="tagline">Create a Shipper account to submit cargo requirements and track quotations</p>
  </div>
  <div id="regError" class="alert alert-error hidden"></div>
  <div id="regSuccess" class="alert alert-success hidden">Account created. <a href="login.php">Sign in →</a></div>
  <form id="regForm" autocomplete="off">
    <label>Full Name / Contact Person <input type="text" name="full_name" required></label>
    <label>Organisation <input type="text" name="organisation" placeholder="e.g. Global Bulk Shippers Ltd"></label>
    <label>Email <input type="email" name="email" required></label>
    <label>Password <input type="password" name="password" required></label>
    <button type="submit" class="btn-primary">Create Shipper Account</button>
  </form>
  <p class="hint">Admin, Chartering Manager, Analyst and Viewer accounts are provisioned internally by an Administrator, not through self-registration.</p>
  <p class="switch">Already have access? <a href="login.php">Sign in</a></p>
</div>
<script src="assets/js/ocean.js"></script>
<script src="assets/js/api.js"></script>
<script src="assets/js/i18n.js"></script>
<script>FIQ.setCsrf(<?= json_encode(csrf_token()) ?>); fiqBuildLanguageSwitcher('authLangSwitcher'); fiqApplyTranslations();</script>
<script>
document.getElementById('regForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const errBox = document.getElementById('regError'), okBox = document.getElementById('regSuccess');
  errBox.classList.add('hidden'); okBox.classList.add('hidden');
  const fd = new FormData(e.target);
  try {
    await FIQ.post('auth_register.php', {
      full_name: fd.get('full_name'), organisation: fd.get('organisation'),
      email: fd.get('email'), password: fd.get('password'),
    });
    okBox.classList.remove('hidden');
    e.target.reset();
  } catch (err) {
    errBox.textContent = err.data?.error || 'Registration failed.';
    errBox.classList.remove('hidden');
  }
});
</script>
</body>
</html>
