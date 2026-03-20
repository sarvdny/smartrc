<?php
// ============================================================
//  admin/dashboard/home/change_password.php
//  BUGS FIXED:
//  1. Auth guard redirect → correct path
//  2. Removed session_start() — sessions unused in this project
//  3. generateRandomString() now comes from getuserdata.php (single source)
//  4. Include path for adminDbConnection fixed
// ============================================================

// FIX: db_rc.php (RC_DATA) not needed on this page — removed
if (!isset($_COOKIE['adminToken']) || !isset($_COOKIE['isLoggedIn'])) {
    header('Location: /smartrc/auth/login/');
    exit;
}

include __DIR__ . '/../../../auth/api/adminDbConnection.php';
include __DIR__ . '/../../../auth/api/getuserdata.php';

$msg     = '';
$msgType = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current = $_POST['current_password'] ?? '';
    $newPw   = $_POST['new_password']     ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (empty($current) || empty($newPw) || empty($confirm)) {
        $msg = 'All fields are required.';
        $msgType = 'error';

    } elseif ($newPw !== $confirm) {
        $msg = 'New password and confirmation do not match.';
        $msgType = 'error';

    } elseif (strlen($newPw) < 8) {
        $msg = 'Password must be at least 8 characters.';
        $msgType = 'error';

    } elseif (!preg_match('/[A-Z]/', $newPw) || !preg_match('/[0-9]/', $newPw) || !preg_match('/[^A-Za-z0-9]/', $newPw)) {
        $msg = 'Password must contain an uppercase letter, a number, and a special character.';
        $msgType = 'error';

    } else {
        $admin = getAdminByToken($adminConn, $_COOKIE['adminToken']);

        if (!$admin) {
            // Cookie token is invalid — force re-login
            setcookie('adminToken', '', time() - 3600, '/');
            setcookie('isLoggedIn', '', time() - 3600, '/');
            setcookie('adminName',  '', time() - 3600, '/');
            header('Location: /smartrc/auth/login/');
            exit;
        }

        if (!password_verify($current, $admin['password'])) {
            $msg     = 'Current password is incorrect.';
            $msgType = 'error';

        } else {
            $newHash  = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
            $newToken = password_hash(generateRandomString(32), PASSWORD_DEFAULT);

            $stmt = mysqli_prepare($adminConn,
                'UPDATE `admin_details` SET `password`=?, `token`=? WHERE `token`=?'
            );
            $oldToken = $_COOKIE['adminToken'];
            mysqli_stmt_bind_param($stmt, 'sss', $newHash, $newToken, $oldToken);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            mysqli_close($adminConn);

            // Refresh cookies with new token
            $ttl = time() + 60 * 60 * 8;
            setcookie('adminToken', $newToken, $ttl, '/');
            setcookie('isLoggedIn', 'true',   $ttl, '/');

            $msg     = 'Password changed successfully. Session token rotated.';
            $msgType = 'success';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Change Password — Smart RC Book</title>
  <link rel="stylesheet" href="/smartrc/admin/dashboard/home/adminPage.css"/>
  <style>
    <?php include __DIR__ . '/includes/style.php'; ?>
    .pw-wrap { max-width:480px;margin:0 auto; }
    .strength-bar  { height:3px;background:var(--border);margin-top:6px;border-radius:2px;overflow:hidden; }
    .strength-fill { height:100%;width:0;transition:width .3s,background .3s;border-radius:2px; }
    .strength-label { font-family:var(--mono);font-size:10px;color:var(--muted);margin-top:4px; }
    .rules-list .rule { display:flex;align-items:center;gap:8px;font-family:var(--mono);
      font-size:11px;color:var(--muted);line-height:2; }
    .rules-list .rule.ok { color:var(--success); }
    .rules-list .dot { width:6px;height:6px;border-radius:50%;background:currentColor;flex-shrink:0; }
  </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-body">
<div class="container">

  <div class="page-title">Account Security</div>
  <div class="page-heading">Change Password</div>

  <div class="pw-wrap">

    <?php if ($msg): ?>
      <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>">
        <?= htmlspecialchars($msg) ?>
      </div>
    <?php endif; ?>

    <div class="card">
      <div class="card-title">Update Credentials</div>
      <form method="post" id="pwForm">
        <div style="display:flex;flex-direction:column;gap:18px">
          <div class="field">
            <label>Current Password</label>
            <input type="password" name="current_password" id="currentPw"
                   placeholder="••••••••" autocomplete="current-password" required>
          </div>
          <div class="field">
            <label>New Password</label>
            <input type="password" name="new_password" id="newPw"
                   placeholder="••••••••" autocomplete="new-password" required
                   oninput="checkStrength(this.value)">
            <div class="strength-bar"><div class="strength-fill" id="strengthFill"></div></div>
            <div class="strength-label" id="strengthLabel">Enter a new password</div>
          </div>
          <div class="field">
            <label>Confirm New Password</label>
            <input type="password" name="confirm_password" id="confirmPw"
                   placeholder="••••••••" autocomplete="new-password" required
                   oninput="checkMatch()">
            <div class="strength-label" id="matchLabel"></div>
          </div>
          <div class="rules-list">
            <div class="rule bad" id="r-len">  <span class="dot"></span>Minimum 8 characters</div>
            <div class="rule bad" id="r-upper"><span class="dot"></span>At least one uppercase letter</div>
            <div class="rule bad" id="r-num">  <span class="dot"></span>At least one number</div>
            <div class="rule bad" id="r-sym">  <span class="dot"></span>At least one special character</div>
          </div>
        </div>
        <div style="display:flex;gap:12px;margin-top:24px;justify-content:flex-end">
          <a href="index.php" class="btn btn-ghost">Cancel</a>
          <button type="submit" class="btn btn-primary" id="submitBtn" disabled>Update Password</button>
        </div>
      </form>
    </div>

    <div class="card mt-16" style="padding:16px 20px">
      <div style="font-family:var(--mono);font-size:11px;color:var(--muted);line-height:1.9">
        Changing your password rotates your session token immediately.
        Your current session stays active with the new token.
      </div>
    </div>

  </div>
</div>
</div>

<footer class="site-footer">
  <span>Smart RC Book &nbsp;·&nbsp; Control Panel</span>
  <span>Session encrypted &nbsp;·&nbsp; <?= date('d M Y') ?></span>
</footer>

<script>
function checkStrength(val) {
  const rules = { 'r-len': val.length>=8, 'r-upper': /[A-Z]/.test(val), 'r-num': /[0-9]/.test(val), 'r-sym': /[^A-Za-z0-9]/.test(val) };
  const score  = Object.values(rules).filter(Boolean).length;
  const colors = ['','#c0392b','#e67e22','#f1c40f','#27ae60'];
  const labels = ['','Weak','Fair','Good','Strong'];
  document.getElementById('strengthFill').style.width      = (score*25)+'%';
  document.getElementById('strengthFill').style.background = colors[score];
  document.getElementById('strengthLabel').textContent     = score ? labels[score] : 'Enter a new password';
  document.getElementById('strengthLabel').style.color     = colors[score] || 'var(--muted)';
  Object.entries(rules).forEach(([id,ok]) => document.getElementById(id).className = 'rule '+(ok?'ok':'bad'));
  validate();
}
function checkMatch() {
  const nv=document.getElementById('newPw').value, cv=document.getElementById('confirmPw').value;
  const lbl=document.getElementById('matchLabel');
  if (!cv) { lbl.textContent=''; return; }
  lbl.textContent = nv===cv ? 'Passwords match' : 'Passwords do not match';
  lbl.style.color = nv===cv ? 'var(--success)' : 'var(--danger)';
  validate();
}
function validate() {
  const nv=document.getElementById('newPw').value, cv=document.getElementById('confirmPw').value, cur=document.getElementById('currentPw').value;
  document.getElementById('submitBtn').disabled = !(cur.length>0 && nv===cv && nv.length>=8 && /[A-Z]/.test(nv) && /[0-9]/.test(nv) && /[^A-Za-z0-9]/.test(nv));
}
document.getElementById('currentPw').addEventListener('input', validate);
</script>
<script src="/smartrc/admin/dashboard/home/adminPage.js"></script>
</body>
</html>
