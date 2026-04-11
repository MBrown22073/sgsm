<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Setup — Game Server Manager</title>
<link rel="stylesheet" href="<?= BASE ?>/assets/style.css">
</head>
<body class="auth-body">
<div class="auth-card" style="max-width:480px">
  <div class="auth-logo">
    <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2">
      <rect x="2" y="3" width="20" height="14" rx="2"/><path d="M8 21h8M12 17v4"/>
    </svg>
    <h1>First-Time Setup</h1>
    <p style="color:var(--text-muted);font-size:.85rem;margin-top:4px">Create your admin account to get started</p>
  </div>
  <div id="setup-error" class="alert alert-error" style="display:none"></div>
  <form id="setup-form">
    <div class="form-group">
      <label class="form-label">Application Name</label>
      <input class="form-control" type="text" id="s-appname" value="Game Server Manager" required>
    </div>
    <div class="form-group">
      <label class="form-label">Admin Username <span class="required">*</span></label>
      <input class="form-control" type="text" id="s-user" autocomplete="username" autofocus required minlength="3">
    </div>
    <div class="form-group">
      <label class="form-label">Admin Password <span class="required">*</span></label>
      <input class="form-control" type="password" id="s-pass" autocomplete="new-password" required minlength="8">
      <span class="form-hint">Minimum 8 characters</span>
    </div>
    <div class="form-group">
      <label class="form-label">Confirm Password <span class="required">*</span></label>
      <input class="form-control" type="password" id="s-pass2" autocomplete="new-password" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block" id="setup-btn">Create Account &amp; Continue</button>
  </form>
</div>
<script>
window.GSM_BASE = '<?= BASE ?>';
document.getElementById('setup-form').addEventListener('submit', async (e) => {
  e.preventDefault();
  const btn = document.getElementById('setup-btn');
  const err = document.getElementById('setup-error');
  err.style.display = 'none';
  const pass = document.getElementById('s-pass').value;
  if (pass !== document.getElementById('s-pass2').value) {
    err.textContent = 'Passwords do not match'; err.style.display = 'flex'; return;
  }
  btn.disabled = true; btn.textContent = 'Creating…';
  try {
    const r = await fetch(GSM_BASE + '/api/auth.php?action=setup', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        app_name: document.getElementById('s-appname').value,
        username: document.getElementById('s-user').value,
        password: pass,
      })
    });
    const d = await r.json();
    if (d.ok) { location.reload(); }
    else { err.textContent = d.error || 'Setup failed'; err.style.display = 'flex'; }
  } catch { err.textContent = 'Server unreachable'; err.style.display = 'flex'; }
  btn.disabled = false; btn.textContent = 'Create Account & Continue';
});
</script>
</body>
</html>
