/* global */ const BASE = window.GSM_BASE || '';
let consoleSSE = null;

// ── Live status polling ───────────────────────────────────────────────────────
// Polls /api/servers.php every 5s when on the servers page and updates badges
// and action buttons without a full page reload.
(function startStatusPolling() {
  if (!document.getElementById('servers-tbody')) return;

  async function pollStatuses() {
    try {
      const servers = await (await fetch(BASE + '/api/servers.php')).json();
      if (!Array.isArray(servers)) return;
      servers.forEach(s => {
        const badge = document.getElementById('status-' + s.id);
        if (!badge) return;
        const prev = badge.dataset.status || badge.className.match(/status-(\w+)/)?.[1];
        if (prev === s.status) return; // no change

        // Update badge text and class
        badge.className = 'status-badge status-' + s.status;
        badge.textContent = s.status.charAt(0).toUpperCase() + s.status.slice(1);
        badge.dataset.status = s.status;

        // Reload the page to refresh action buttons when status changes
        // (only if no modal/console is open)
        const anyModalOpen = document.querySelector('.modal-overlay[style*="flex"]');
        if (!anyModalOpen) location.reload();
      });
    } catch {}
  }

  setInterval(pollStatuses, 5000);
})();

// ── Toast ─────────────────────────────────────────────────────────────────────
function toast(msg, type = 'success') {
  const el = document.getElementById('toast');
  if (!el) return;
  el.textContent    = msg;
  el.className      = 'toast toast-' + type;
  el.style.display  = 'block';
  clearTimeout(el._t);
  el._t = setTimeout(() => { el.style.display = 'none'; }, 3500);
}

// ── Modal helpers ─────────────────────────────────────────────────────────────
function openModal(id)  { const m = document.getElementById(id); if (m) m.style.display = 'flex'; }
function closeModal(id) { const m = document.getElementById(id); if (m) m.style.display = 'none'; }

// ── API fetch helper ──────────────────────────────────────────────────────────
async function api(url, opts = {}) {
  opts.headers = Object.assign({ 'Content-Type': 'application/json' }, opts.headers || {});
  const r = await fetch(BASE + url, opts);
  const d = await r.json().catch(() => ({}));
  if (!r.ok) throw new Error(d.error || r.statusText);
  return d;
}

// ── Settings tabs ─────────────────────────────────────────────────────────────
function switchTab(name, btn) {
  document.querySelectorAll('.tab-panel').forEach(p => p.style.display = 'none');
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  const panel = document.getElementById('tab-' + name);
  if (panel) panel.style.display = '';
  if (btn)   btn.classList.add('active');
}

// ── Save settings ─────────────────────────────────────────────────────────────
async function saveSettings(keys) {
  const saved = document.getElementById('settings-saved');
  const err   = document.getElementById('settings-error');
  if (saved) saved.style.display = 'none';
  if (err)   err.style.display   = 'none';
  const body = {};
  keys.forEach(k => {
    const el = document.getElementById('cfg-' + k);
    if (el) body[k] = el.tagName === 'SELECT' ? el.value : el.value;
  });
  try {
    await api('/api/settings.php', { method: 'POST', body: JSON.stringify(body) });
    if (saved) { saved.style.display = 'flex'; setTimeout(() => { saved.style.display = 'none'; }, 2500); }
    toast('Settings saved');
  } catch (e) {
    if (err) { err.textContent = e.message; err.style.display = 'flex'; }
    toast(e.message, 'error');
  }
}

// ── Upload logo ───────────────────────────────────────────────────────────────
async function uploadLogo() {
  const input = document.getElementById('logo-upload');
  if (!input || !input.files.length) { toast('Select a file first', 'error'); return; }
  const form = new FormData();
  form.append('logo', input.files[0]);
  try {
    const r = await fetch(BASE + '/api/settings.php?action=upload-logo', { method: 'POST', body: form });
    const d = await r.json();
    if (!r.ok) throw new Error(d.error || 'Upload failed');
    toast('Logo uploaded — reload to see it');
  } catch (e) { toast(e.message, 'error'); }
}

// ── Test DB ───────────────────────────────────────────────────────────────────
async function testDbConn() {
  const result = document.getElementById('db-test-result');
  if (result) result.textContent = 'Testing…';
  const body = {};
  ['db_type','db_host','db_port','db_name','db_user','db_password'].forEach(k => {
    const el = document.getElementById('cfg-' + k);
    if (el) body[k] = el.value;
  });
  try {
    const d = await api('/api/settings.php?action=test-db', { method: 'POST', body: JSON.stringify(body) });
    if (result) { result.textContent = '✓ ' + d.message; result.style.color = 'var(--green)'; }
  } catch (e) {
    if (result) { result.textContent = '✕ ' + e.message; result.style.color = 'var(--red)'; }
  }
}

// ── Change password ───────────────────────────────────────────────────────────
async function changePassword() {
  const msg = document.getElementById('pw-msg');
  const curr = document.getElementById('pw-current')?.value;
  const newpw = document.getElementById('pw-new')?.value;
  const conf  = document.getElementById('pw-confirm')?.value;
  if (msg) msg.style.display = 'none';
  if (newpw !== conf) { if (msg) { msg.textContent = 'Passwords do not match'; msg.className = 'alert alert-error'; msg.style.display = 'flex'; } return; }
  try {
    await api('/api/auth.php?action=change-password', { method: 'POST', body: JSON.stringify({ current_password: curr, new_password: newpw }) });
    if (msg) { msg.textContent = 'Password updated successfully'; msg.className = 'alert alert-success'; msg.style.display = 'flex'; }
    document.getElementById('pw-current').value = '';
    document.getElementById('pw-new').value     = '';
    document.getElementById('pw-confirm').value  = '';
  } catch (e) {
    if (msg) { msg.textContent = e.message; msg.className = 'alert alert-error'; msg.style.display = 'flex'; }
  }
}

// ── Run update ────────────────────────────────────────────────────────────────
async function runUpdate() {
  const el = document.getElementById('update-console');
  if (!el) return;
  el.style.display = 'block';
  el.textContent   = 'Starting update…\n';
  try {
    await api(`${BASE}/api/settings.php?action=update`, { method: 'POST' });
    // Fetch the log directly — no long-running SSE needed for static instructions
    const res = await fetch(`${BASE}/api/console.php?type=update`);
    const reader = res.body.getReader();
    const decoder = new TextDecoder();
    let buf = '';
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;
      buf += decoder.decode(value, { stream: true });
      // Parse SSE data lines
      buf.split('\n').forEach(line => {
        if (line.startsWith('data:')) {
          try { el.textContent += JSON.parse(line.slice(5).trim()) + '\n'; } catch {}
        }
      });
      // Stop once we see the Done marker
      if (buf.includes('--- Done ---')) { reader.cancel(); break; }
    }
  } catch (e) { el.textContent += 'Error: ' + e.message; }
}

// ── Templates ─────────────────────────────────────────────────────────────────
async function loadTemplates() {
  const container = document.getElementById('templates-list');
  if (!container) return;
  try {
    const templates = await api('/api/servers.php?templates=1');
    container.innerHTML = templates.map(t =>
      `<button type="button" class="tpl-btn" onclick='applyTemplate(${JSON.stringify(t)})'>${t.name}</button>`
    ).join('');
  } catch { container.textContent = 'Could not load templates'; }
}

function applyTemplate(t) {
  const serversPath = document.getElementById('cfg-servers_path')?.value || '/opt/servers';
  setValue('sf-name',  t.name);
  setValue('sf-appid', t.app_id);
  setValue('sf-dir',   serversPath + '/' + t.name.toLowerCase().replace(/[^a-z0-9]/g, '-'));
  setValue('sf-exec',  t.launch_executable);
  setValue('sf-args',  t.launch_args);
  setValue('sf-port',  t.port  || '');
  setValue('sf-maxp',  t.max_players || '');
}

function setValue(id, val) { const el = document.getElementById(id); if (el) el.value = val; }

// ── Add / Edit server modal ───────────────────────────────────────────────────
function openServerModal(server) {
  const isEdit = !!server;
  document.getElementById('server-modal-title').textContent = isEdit ? 'Edit Server' : 'Add Game Server';
  document.getElementById('sf-submit').textContent          = isEdit ? 'Save Changes' : 'Add Server';
  document.getElementById('sf-error').style.display  = 'none';
  setValue('sf-id',    server?.id    || '');
  setValue('sf-name',  server?.name  || '');
  setValue('sf-appid', server?.app_id || '');
  setValue('sf-dir',   server?.install_dir || '');
  setValue('sf-exec',  server?.launch_executable || '');
  setValue('sf-args',  server?.launch_args || '');
  setValue('sf-port',  server?.port  || '');
  setValue('sf-maxp',  server?.max_players || '');
  setValue('sf-notes', server?.notes || '');
  if (!isEdit) loadTemplates();
  else {
    const tl = document.getElementById('templates-list');
    if (tl) tl.closest('div').style.display = 'none'; // hide templates on edit
  }
  openModal('server-modal');
}

async function submitServerForm(e) {
  e.preventDefault();
  const err    = document.getElementById('sf-error');
  const btn    = document.getElementById('sf-submit');
  const id     = document.getElementById('sf-id').value;
  const isEdit = !!id;
  err.style.display = 'none';
  btn.disabled      = true;
  btn.textContent   = 'Saving…';
  const body = {
    name:              document.getElementById('sf-name').value.trim(),
    app_id:            document.getElementById('sf-appid').value.trim(),
    install_dir:       document.getElementById('sf-dir').value.trim(),
    launch_executable: document.getElementById('sf-exec').value.trim(),
    launch_args:       document.getElementById('sf-args').value.trim(),
    port:              document.getElementById('sf-port').value || null,
    max_players:       document.getElementById('sf-maxp').value || 0,
    notes:             document.getElementById('sf-notes').value.trim(),
  };
  try {
    const url    = isEdit ? `${BASE}/api/servers.php?id=${id}` : `${BASE}/api/servers.php`;
    const method = isEdit ? 'PUT' : 'POST';
    await api(url, { method, body: JSON.stringify(body) });
    closeModal('server-modal');
    toast(isEdit ? 'Server updated' : 'Server added');
    setTimeout(() => location.reload(), 500);
  } catch (ex) {
    err.textContent = ex.message;
    err.style.display = 'flex';
  } finally {
    btn.disabled    = false;
    btn.textContent = isEdit ? 'Save Changes' : 'Add Server';
  }
}

// ── Server actions (start/stop/restart/install/cancel-install) ────────────────
async function serverAction(id, action) {
  try {
    await api(`${BASE}/api/servers.php?id=${id}&action=${action}`, { method: 'POST' });
    const labels = { start:'Starting', stop:'Stopping', restart:'Restarting', install:'Installing', 'cancel-install':'Cancelling' };
    toast((labels[action] || action) + '…');
    if (action === 'install') openConsole(id, 'install');
    else setTimeout(() => location.reload(), 1000);
  } catch (e) { toast(e.message, 'error'); }
}

// ── Delete server ─────────────────────────────────────────────────────────────
async function deleteServer(id, name) {
  if (!confirm(`Delete "${name}"? This cannot be undone.`)) return;
  try {
    await api(`${BASE}/api/servers.php?id=${id}`, { method: 'DELETE' });
    toast('Server deleted');
    // Remove the row immediately without a full page reload
    const row = document.getElementById(`server-row-${id}`);
    if (row) row.remove();
    else location.reload();
  } catch (e) { toast(e.message, 'error'); }
}

// ── Console modal ─────────────────────────────────────────────────────────────
function openConsole(id, type) {
  document.getElementById('console-title').textContent = type === 'install' ? 'Install Console' : 'Server Console';
  document.getElementById('console-output').textContent = '';
  openModal('console-modal');
  startConsolePolling(id, type, document.getElementById('console-output'));
}

function startConsolePolling(id, type, outputEl) {
  stopConsolePolling();
  let offset = 0;
  const url = id
    ? `${BASE}/api/console.php?id=${id}&type=${type}`
    : `${BASE}/api/console.php?type=update`;

  async function poll() {
    try {
      const data = await (await fetch(url + `&offset=${offset}`)).json();
      if (data.lines && data.lines.length) {
        data.lines.forEach(line => { outputEl.textContent += line + '\n'; });
        outputEl.scrollTop = outputEl.scrollHeight;
      }
      offset = data.offset ?? offset;
    } catch {}
  }

  poll(); // immediate first fetch
  consoleSSE = setInterval(poll, 1500);
}

function stopConsolePolling() {
  if (consoleSSE) { clearInterval(consoleSSE); consoleSSE = null; }
}

function closeConsole() {
  stopConsolePolling();
  closeModal('console-modal');
}

// Keep old name as alias so any other callers don't break
function closeConsoleSSE() { stopConsolePolling(); }
