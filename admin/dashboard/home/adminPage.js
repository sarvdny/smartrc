// ============================================================
//  adminPage.js — Dashboard interactivity
//  Was empty — now handles:
//  - Auto-dismiss alerts
//  - Table row click → vehicle page
//  - Search clear button
//  - Confirm before save
//  - Session expiry warning
// ============================================================

document.addEventListener('DOMContentLoaded', () => {

  // ── Auto-dismiss success alerts after 4s ──────────────────
  document.querySelectorAll('.alert-success').forEach(el => {
    setTimeout(() => {
      el.style.transition = 'opacity .5s';
      el.style.opacity    = '0';
      setTimeout(() => el.remove(), 500);
    }, 4000);
  });

  // ── Table rows clickable (except the Action cell) ─────────
  document.querySelectorAll('.data-table tbody tr').forEach(row => {
    row.style.cursor = 'pointer';
    row.addEventListener('click', e => {
      // Don't trigger when clicking a button or link inside the row
      if (e.target.closest('a, button')) return;
      const link = row.querySelector('a[href*="vehicle.php"]');
      if (link) window.location.href = link.href;
    });
  });

  // ── Search input: show clear (×) button when text is typed ─
  const searchInput = document.querySelector('.search-bar input[name="q"]');
  if (searchInput && searchInput.value.trim() !== '') {
    addClearBtn(searchInput);
  }
  if (searchInput) {
    searchInput.addEventListener('input', () => {
      if (searchInput.value.trim()) addClearBtn(searchInput);
      else removeClearBtn();
    });
  }

  function addClearBtn(input) {
    if (document.getElementById('searchClear')) return;
    const btn = document.createElement('button');
    btn.id        = 'searchClear';
    btn.type      = 'button';
    btn.textContent = '×';
    btn.style.cssText = [
      'position:absolute', 'right:12px', 'top:50%', 'transform:translateY(-50%)',
      'background:none', 'border:none', 'color:var(--muted)', 'font-size:18px',
      'cursor:pointer', 'line-height:1', 'padding:0 4px'
    ].join(';');
    btn.addEventListener('click', () => {
      input.value = '';
      input.form.submit();
    });
    input.parentElement.style.position = 'relative';
    input.parentElement.appendChild(btn);
  }
  function removeClearBtn() {
    const btn = document.getElementById('searchClear');
    if (btn) btn.remove();
  }

  // ── Confirm before any Save button on vehicle.php ─────────
  document.querySelectorAll('.section-card .btn-primary').forEach(btn => {
    btn.addEventListener('click', e => {
      const section = btn.closest('form')?.querySelector('[name="section"]')?.value || 'record';
      if (!confirm(`Save changes to ${section.replace('_', ' ')}?`)) {
        e.preventDefault();
      }
    });
  });

  // ── Session expiry warning (cookies expire in 8h) ─────────
  // Warn 5 minutes before the session cookie disappears
  const warned = sessionStorage.getItem('sessionWarnShown');
  if (!warned) {
    // Cookies were set at login — approximate expiry from page load
    // Show a warning banner 7h55m after first load
    const warnAfterMs = (8 * 60 - 5) * 60 * 1000;
    setTimeout(() => {
      showSessionWarning();
      sessionStorage.setItem('sessionWarnShown', '1');
    }, warnAfterMs);
  }

  function showSessionWarning() {
    const banner = document.createElement('div');
    banner.style.cssText = [
      'position:fixed', 'bottom:24px', 'right:24px', 'z-index:9999',
      'background:var(--panel)', 'border:1px solid var(--accent)',
      'padding:14px 20px', 'font-family:var(--mono)', 'font-size:12px',
      'color:var(--text)', 'max-width:320px', 'animation:fadeIn .3s ease'
    ].join(';');
    banner.innerHTML = `
      <div style="color:var(--accent);letter-spacing:.1em;text-transform:uppercase;
                  font-size:10px;margin-bottom:6px">Session Expiring</div>
      Your session expires in 5 minutes.
      <br>
      <a href="javascript:location.reload()" style="color:var(--accent);
         display:inline-block;margin-top:8px;font-size:11px">Refresh to extend →</a>
      <button onclick="this.parentElement.remove()" style="position:absolute;top:8px;
              right:10px;background:none;border:none;color:var(--muted);
              cursor:pointer;font-size:16px">×</button>
    `;
    banner.style.position = 'fixed';
    document.body.appendChild(banner);
  }

  // ── Keyboard shortcut: / to focus search ──────────────────
  document.addEventListener('keydown', e => {
    if (e.key === '/' && document.activeElement.tagName !== 'INPUT'
        && document.activeElement.tagName !== 'TEXTAREA') {
      e.preventDefault();
      const s = document.querySelector('.search-bar input[name="q"]');
      if (s) s.focus();
    }
  });

});
