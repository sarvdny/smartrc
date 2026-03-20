// ============================================================
//  loginPage.js
//  BUG FIXED: hardcoded /smartrc/ path → derive from window.location
// ============================================================

const togglePw = document.getElementById('togglePw');
const pwInput  = document.getElementById('passwordInput');
const eyeIcon  = document.getElementById('eyeIcon');

const eyeOpen = `<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>`;
const eyeOff  = `<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>`;

togglePw.addEventListener('click', () => {
  const show = pwInput.type === 'password';
  pwInput.type = show ? 'text' : 'password';
  eyeIcon.innerHTML = show ? eyeOff : eyeOpen;
});

const emailInput = document.getElementById('emailInput');
const loginBtn   = document.getElementById('loginBtn');
const errorMsg   = document.getElementById('errorMsg');

function showError(msg) {
  errorMsg.textContent = msg;
  errorMsg.classList.add('show');
  emailInput.classList.add('error');
  pwInput.classList.add('error');
}
function clearError() {
  errorMsg.classList.remove('show');
  emailInput.classList.remove('error');
  pwInput.classList.remove('error');
}
function setLoading(on) {
  loginBtn.classList.toggle('loading', on);
  loginBtn.disabled = on;
}

[emailInput, pwInput].forEach(el => el.addEventListener('input', clearError));

loginBtn.addEventListener('click', async () => {
  clearError();
  const email    = emailInput.value.trim();
  const password = pwInput.value;

  if (!email)                                      { showError('Email address is required.'); return; }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showError('Enter a valid email address.'); return; }
  if (!password)                                   { showError('Password is required.'); return; }

  setLoading(true);
  try {
    const res = await fetch('./login.php', {
      method:  'POST',
      headers: { 'Content-Type': 'application/json' },
      body:    JSON.stringify({ email, password }),
    });

    const data = await res.json().catch(() => ({}));

    if (res.ok) {
      // Store display name in a cookie for the dashboard header
      // Use SameSite=Strict so the cookie isn't sent cross-origin
      const maxAge = 60 * 60 * 8; // 8 hours, matches server session
      document.cookie = `adminName=${encodeURIComponent(data.name || email)}; path=/; max-age=${maxAge}; SameSite=Strict`;

      // FIX: Never put the token in the URL — it leaks into browser history,
      // server logs, and Referer headers. Use a hidden form POST instead.
      const base = window.location.pathname.replace(/\/auth\/login\/?.*$/, '');
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = `${base}/admin/dashboard/`;
      const input = document.createElement('input');
      input.type  = 'hidden';
      input.name  = 'token';
      input.value = data.token;
      form.appendChild(input);
      document.body.appendChild(form);
      form.submit();
    } else {
      showError(data.message || 'Authentication failed. Access denied.');
    }
  } catch {
    showError('Unable to connect to the server. Try again.');
  } finally {
    setLoading(false);
  }
});

[emailInput, pwInput].forEach(el =>
  el.addEventListener('keydown', e => { if (e.key === 'Enter') loginBtn.click(); })
);
