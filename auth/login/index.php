<?php
// BUG FIXED: __DIR__ gives filesystem path, not URL — use proper URL redirect
if (isset($_COOKIE['isLoggedIn']) && isset($_COOKIE['adminToken'])) {
    header('Location: /smartrc/admin/dashboard/?token=' . urlencode($_COOKIE['adminToken']));
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Secure Portal — Authorized Access Only</title>
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@300;400;500&family=IBM+Plex+Sans:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="./loginPage.css"/>
</head>
<body>

<div class="top-bar"></div>

<div class="wrapper">
  <div class="header">
    <div class="seal">
      <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#c8a84b" stroke-width="1.3">
        <path d="M12 2L2 7l10 5 10-5-10-5z"/>
        <path d="M2 17l10 5 10-5"/>
        <path d="M2 12l10 5 10-5"/>
      </svg>
    </div>
    <h1>Smart RC Book</h1>
    <p>Control Panel &nbsp;·&nbsp; Authorized Access Only</p>
  </div>

  <div class="card">
    <div class="error-msg" id="errorMsg"></div>

    <div class="field">
      <label for="emailInput">Official Email Address</label>
      <div class="input-wrap">
        <input type="email" id="emailInput" placeholder="admin@domain.com" autocomplete="email"/>
      </div>
    </div>

    <div class="field">
      <label for="passwordInput">Password</label>
      <div class="input-wrap">
        <input type="password" id="passwordInput" placeholder="••••••••••••"
               autocomplete="current-password" style="padding-right:40px"/>
        <button class="toggle-pw" id="togglePw" type="button" tabindex="-1">
          <svg id="eyeIcon" width="14" height="14" viewBox="0 0 24 24"
               fill="none" stroke="currentColor" stroke-width="1.5">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        </button>
      </div>
    </div>

    <button class="btn-login" id="loginBtn" type="button">
      <span class="btn-text">Authenticate</span>
      <span class="btn-loader"><span class="spinner"></span></span>
    </button>

    <div class="card-footer">
      Unauthorised access is a criminal offence under the Information Technology Act, 2000.
      All sessions are recorded and audited.
    </div>
  </div>

  <div class="bottom-note">TLS 1.3 &nbsp;·&nbsp; SESSION ENCRYPTED &nbsp;·&nbsp; GOV-CERT COMPLIANT</div>
</div>

<script src="./loginPage.js"></script>
</body>
</html>
