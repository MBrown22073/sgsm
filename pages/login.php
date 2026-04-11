<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= htmlspecialchars($db->getSetting('app_name') ?: 'Game Server Manager') ?> — Login</title>
<link rel="stylesheet" href="<?= BASE ?>/assets/style.css">
</head>
<body class="auth-body">
<div class="auth-card">
  <div class="auth-logo">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2">
      <rect x="2" y="3" width="20" height="14" rx="2"/>
      <path d="M8 21h8M12 17v4"/>
    </svg>
    <h1><?= htmlspecialchars($db->getSetting('app_name') ?: 'Game Server Manager') ?></h1>
  </div>
  <div id="auth-error" class="alert alert-error" style="display:none"></div>
  <form id="login-form">
    <div class="form-group">
      <label class="form-label">Username</label>
      <input class="form-control" type="text" id="login-user" autocomplete="username" autofocus required>
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <input class="form-control" type="password" id="login-pass" autocomplete="current-password" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block" id="login-btn">Sign In</button>
  </form>
</div>
<script>
window.GSM_BASE = '<?= BASE ?>';
document.getElementById('login-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('login-btn');
  const err = document.getElementById('auth-error');
  btn.disabled = true; btn.textContent = 'Signing in…';
  err.style.display = 'none';
  try {
    const r = await fetch(GSM_BASE + '/api/auth.php?action=login', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        username: document.getElementById('login-user').value,
        password: document.getElementById('login-pass').value,
      })
    });
    const d = await r.json();
    if (d.ok) { location.reload(); }
    else { err.textContent = d.error || 'Login failed'; err.style.display = 'flex'; }
  } catch { err.textContent = 'Server unreachable'; err.style.display = 'flex'; }
  btn.disabled = false; btn.textContent = 'Sign In';
});
</script>
</body>
</html>
