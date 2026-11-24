<?php require_once __DIR__ . '/../backend/middleware/security.php'; if (current_user()) { header('Location: index.php'); exit; } ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>FreightIQ Marine | Secure Login</title>
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
    <p class="tagline" data-i18n="brand_tagline">Intelligent Freight Forecasting · Vessel Chartering · Bulk Cargo Procurement<br>Ministry of Steel / SAIL — All India Coasts</p>
  </div>

  <div id="loginError" class="alert alert-error hidden"></div>

  <form id="loginForm" autocomplete="off">
    <label><span data-i18n="email">Email</span> <input type="email" name="email" required placeholder="admin@sail.co.in"></label>
    <label><span data-i18n="password">Password</span> <input type="password" name="password" required placeholder="••••••••"></label>
    <button type="submit" class="btn-primary"><span data-i18n="btn_signin">Sign In</span> <span>〜</span></button>
  </form>

  <p class="hint">
    Demo accounts (all use password <b>Admin@123</b>):<br>
    admin@sail.co.in (Admin) · chartering@sail.co.in (Chartering Manager)<br>
    analyst@sail.co.in (Analyst) · viewer@sail.co.in (Viewer) · shipper@globalbulk.com (Shipper)
  </p>
  <p class="switch">Shipper without an account? <a href="register.php">Request shipper access</a></p>
</div>

<script src="assets/js/ocean.js"></script>
<script src="assets/js/api.js"></script>
<script src="assets/js/i18n.js"></script>
<script>FIQ.setCsrf(<?= json_encode(csrf_token()) ?>); fiqBuildLanguageSwitcher('authLangSwitcher'); fiqApplyTranslations();</script>
<script>
document.getElementById('loginForm').addEventListener('submit', async (e) => {
  e.preventDefault();
  const errBox = document.getElementById('loginError');
  errBox.classList.add('hidden');
  const fd = new FormData(e.target);
  try {
    const data = await FIQ.post('auth_login.php', { email: fd.get('email'), password: fd.get('password') });
    FIQ.setCsrf(data.csrf_token);
    window.location.href = 'index.php';
  } catch (err) {
    errBox.textContent = err.data?.error || 'Login failed.';
    errBox.classList.remove('hidden');
  }
});
</script>
</body>
</html>
