<?php
// ============================================================
//  admin/dashboard/home/vehicle.php
//  BUGS FIXED:
//  1. Auth guard redirect to correct path
//  2. Owner bind_param type 'sisis' → 'sssis'
//     (name=s, father_name=s, address=s, id=i, rc_no=s)
//  3. All SQL identifiers backtick-quoted
// ============================================================

require_once __DIR__ . '/includes/db_rc.php';

if (!isset($_COOKIE['adminToken']) || !isset($_COOKIE['isLoggedIn'])) {
    header('Location: /smartrc/auth/login/');
    exit;
}

$db      = getRcDB();
$rcNo    = trim($_GET['rc'] ?? '');
$msg     = '';
$msgType = 'success';

if (!$rcNo) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $section = $_POST['section'] ?? '';
    try {
        if ($section === 'rc') {
            $stmt = mysqli_prepare($db,
                'UPDATE `rc` SET `registration_date`=?, `class`=?, `model`=?, `manufacturer`=?,
                 `fuel_type`=?, `color`=?, `engine_no`=?, `chassis_no`=?, `rto`=?, `state`=?,
                 `registration_valid_upto`=? WHERE `rc_no`=?'
            );
            $rd = $_POST['registration_date']       ?: null;
            $cl = $_POST['class']                   ?: null;
            $mo = $_POST['model']                   ?: null;
            $mf = $_POST['manufacturer']            ?: null;
            $ft = $_POST['fuel_type']               ?: null;
            $co = $_POST['color']                   ?: null;
            $en = $_POST['engine_no']               ?: null;
            $ch = $_POST['chassis_no']              ?: null;
            $rt = $_POST['rto']                     ?: null;
            $st = $_POST['state']                   ?: null;
            $rv = $_POST['registration_valid_upto'] ?: null;
            mysqli_stmt_bind_param($stmt, 'ssssssssssss',
                $rd, $cl, $mo, $mf, $ft, $co, $en, $ch, $rt, $st, $rv, $rcNo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = 'Vehicle details updated successfully.';

        } elseif ($section === 'owner') {
            $stmt = mysqli_prepare($db,
                'UPDATE `owners` SET `name`=?, `father_name`=?, `address`=? WHERE `id`=? AND `rc_no`=?'
            );
            $nm = $_POST['name']        ?: null;
            $fn = $_POST['father_name'] ?: null;
            $ad = $_POST['address']     ?: null;
            $oi = (int)($_POST['owner_id'] ?? 0);
            // BUG FIX: was 'sisis' — correct is 'sssis' (3 strings, 1 int, 1 string)
            mysqli_stmt_bind_param($stmt, 'sssis', $nm, $fn, $ad, $oi, $rcNo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = 'Owner details updated successfully.';

        } elseif ($section === 'insurance') {
            $stmt = mysqli_prepare($db,
                'UPDATE `insurance` SET `policy_no`=?, `provider`=?, `valid_upto`=? WHERE `rc_no`=?'
            );
            $pn = $_POST['policy_no']      ?: null;
            $pr = $_POST['provider']       ?: null;
            $vu = $_POST['ins_valid_upto'] ?: null;
            mysqli_stmt_bind_param($stmt, 'ssss', $pn, $pr, $vu, $rcNo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = 'Insurance details updated successfully.';

        } elseif ($section === 'fitness') {
            $stmt = mysqli_prepare($db, 'UPDATE `fitness` SET `valid_upto`=? WHERE `rc_no`=?');
            $vu = $_POST['fit_valid_upto'] ?: null;
            mysqli_stmt_bind_param($stmt, 'ss', $vu, $rcNo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = 'Fitness certificate updated successfully.';

        } elseif ($section === 'tax') {
            $stmt = mysqli_prepare($db, 'UPDATE `tax` SET `paid_upto`=? WHERE `rc_no`=?');
            $pu = $_POST['tax_paid_upto'] ?: null;
            mysqli_stmt_bind_param($stmt, 'ss', $pu, $rcNo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = 'Tax details updated successfully.';

        } elseif ($section === 'pollution') {
            $stmt = mysqli_prepare($db,
                'UPDATE `pollution` SET `pucc_no`=?, `valid_upto`=? WHERE `rc_no`=?'
            );
            $pn = $_POST['pucc_no']        ?: null;
            $vu = $_POST['puc_valid_upto'] ?: null;
            mysqli_stmt_bind_param($stmt, 'sss', $pn, $vu, $rcNo);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_close($stmt);
            $msg = 'Pollution (PUC) details updated successfully.';
        }
    } catch (mysqli_sql_exception $e) {
        // FIX: never expose $e->getMessage() to users — leaks table/column names
        error_log('[SmartRC] vehicle update error: ' . $e->getMessage());
        $msg     = 'Update failed. Please check your input and try again.';
        $msgType = 'error';
    }
}

$rc        = db_fetch_one($db, 'SELECT * FROM `rc`        WHERE `rc_no`=? LIMIT 1', 's', [$rcNo]);
if (!$rc) { header('Location: index.php'); exit; }

$owner     = db_fetch_one($db, 'SELECT * FROM `owners`    WHERE `rc_no`=? LIMIT 1', 's', [$rcNo]);
$insurance = db_fetch_one($db, 'SELECT * FROM `insurance` WHERE `rc_no`=? LIMIT 1', 's', [$rcNo]);
$fitness   = db_fetch_one($db, 'SELECT * FROM `fitness`   WHERE `rc_no`=? LIMIT 1', 's', [$rcNo]);
$tax       = db_fetch_one($db, 'SELECT * FROM `tax`       WHERE `rc_no`=? LIMIT 1', 's', [$rcNo]);
$pollution = db_fetch_one($db, 'SELECT * FROM `pollution` WHERE `rc_no`=? LIMIT 1', 's', [$rcNo]);

function expiry(string $date = ''): string
{
    if (!$date) return '<span class="badge badge-warn">N/A</span>';
    $diff = (strtotime($date) - time()) / 86400;
    if ($diff < 0)  return '<span class="badge badge-danger">Expired ' . abs((int)$diff) . 'd ago</span>';
    if ($diff < 30) return '<span class="badge badge-warn">Expiring in ' . (int)$diff . 'd</span>';
    return '<span class="badge badge-ok">Valid till ' . date('d M Y', strtotime($date)) . '</span>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>RC: <?= htmlspecialchars($rcNo) ?> — Smart RC Book</title>
  <link rel="stylesheet" href="/smartrc/admin/dashboard/home/adminPage.css"/>
  <style>
    <?php include __DIR__ . '/includes/style.php'; ?>
    .back-link { font-family:var(--mono);font-size:11px;color:var(--muted);
      display:inline-flex;align-items:center;gap:6px;margin-bottom:20px;
      text-decoration:none;letter-spacing:.08em;text-transform:uppercase;
      transition:color .15s; }
    .back-link:hover { color:var(--accent); }
    .rfid-box { background:var(--panel);border:1px solid var(--border);
      padding:10px 14px;font-family:var(--mono);font-size:12px;
      color:var(--muted);display:flex;align-items:center;gap:10px;margin-bottom:24px; }
    .rfid-label { font-size:10px;letter-spacing:.14em;text-transform:uppercase;
      color:var(--muted);min-width:60px; }
    .rfid-val { color:var(--text); }
    .rfid-lock { font-size:10px;background:rgba(200,168,75,.08);color:var(--accent);
      border:1px solid rgba(200,168,75,.2);padding:2px 8px;border-radius:2px;
      letter-spacing:.1em;text-transform:uppercase;margin-left:auto; }
    .form-actions { display:flex;gap:12px;margin-top:20px;justify-content:flex-end; }
    .section-card { background:var(--surface);border:1px solid var(--border);
      padding:24px 28px;margin-bottom:20px; }
  </style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-body">
<div class="container">

  <a href="index.php" class="back-link">← Back to Dashboard</a>

  <div class="page-title">Vehicle Record</div>
  <div class="page-heading" style="margin-bottom:6px">
    <?= htmlspecialchars($rc['manufacturer'] . ' ' . $rc['model']) ?>
  </div>
  <div style="font-family:var(--mono);font-size:13px;color:var(--accent);margin-bottom:24px;letter-spacing:.06em">
    <?= htmlspecialchars($rcNo) ?> &nbsp;·&nbsp;
    <span style="color:var(--muted)"><?= htmlspecialchars($rc['vehicle_no']) ?></span>
  </div>

  <?php if ($msg): ?>
    <div class="alert alert-<?= $msgType === 'error' ? 'error' : 'success' ?>">
      <?= htmlspecialchars($msg) ?>
    </div>
  <?php endif; ?>

  <div class="rfid-box">
    <span class="rfid-label">RFID Tag</span>
    <span class="rfid-val"><?= htmlspecialchars($rc['rfid_tag'] ?? 'Not assigned') ?></span>
    <span class="rfid-lock">System-locked</span>
  </div>

  <!-- Vehicle Details -->
  <div class="section-card">
    <div class="section-label">Vehicle Details</div>
    <form method="post">
      <input type="hidden" name="section" value="rc">
      <div class="form-grid">
        <div class="field">
          <label>RC Number</label>
          <input type="text" value="<?= htmlspecialchars($rc['rc_no']) ?>" readonly>
        </div>
        <div class="field">
          <label>Registration Date</label>
          <input type="date" name="registration_date"
                 value="<?= htmlspecialchars($rc['registration_date'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Vehicle Number</label>
          <input type="text" value="<?= htmlspecialchars($rc['vehicle_no']) ?>" readonly>
          <span class="field-note">Cannot be changed</span>
        </div>
        <div class="field">
          <label>Vehicle Class</label>
          <select name="class">
            <?php foreach (['LMV','MCWG','HMV','MGV','LGV','Transport','Non-Transport'] as $cl): ?>
              <option value="<?= $cl ?>" <?= ($rc['class'] ?? '') === $cl ? 'selected' : '' ?>><?= $cl ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Model</label>
          <input type="text" name="model" value="<?= htmlspecialchars($rc['model'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Manufacturer</label>
          <input type="text" name="manufacturer" value="<?= htmlspecialchars($rc['manufacturer'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Fuel Type</label>
          <select name="fuel_type">
            <?php foreach (['Petrol','Diesel','CNG','Electric','Hybrid','LPG'] as $ft): ?>
              <option value="<?= $ft ?>" <?= ($rc['fuel_type'] ?? '') === $ft ? 'selected' : '' ?>><?= $ft ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label>Color</label>
          <input type="text" name="color" value="<?= htmlspecialchars($rc['color'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Engine Number</label>
          <input type="text" name="engine_no" value="<?= htmlspecialchars($rc['engine_no'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Chassis Number</label>
          <input type="text" name="chassis_no" value="<?= htmlspecialchars($rc['chassis_no'] ?? '') ?>">
        </div>
        <div class="field">
          <label>RTO Office</label>
          <input type="text" name="rto" value="<?= htmlspecialchars($rc['rto'] ?? '') ?>">
        </div>
        <div class="field">
          <label>State</label>
          <input type="text" name="state" value="<?= htmlspecialchars($rc['state'] ?? '') ?>">
        </div>
        <div class="field">
          <label>Registration Valid Upto</label>
          <input type="date" name="registration_valid_upto"
                 value="<?= htmlspecialchars($rc['registration_valid_upto'] ?? '') ?>">
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Vehicle Details</button>
      </div>
    </form>
  </div>

  <!-- Owner Details -->
  <?php if ($owner): ?>
  <div class="section-card">
    <div class="section-label">Owner Details</div>
    <form method="post">
      <input type="hidden" name="section" value="owner">
      <input type="hidden" name="owner_id" value="<?= (int)$owner['id'] ?>">
      <div class="form-grid">
        <div class="field">
          <label>Full Name</label>
          <input type="text" name="name" value="<?= htmlspecialchars($owner['name']) ?>">
        </div>
        <div class="field">
          <label>Father's Name</label>
          <input type="text" name="father_name" value="<?= htmlspecialchars($owner['father_name'] ?? '') ?>">
        </div>
        <div class="field" style="grid-column:1/-1">
          <label>Address</label>
          <textarea name="address"><?= htmlspecialchars($owner['address'] ?? '') ?></textarea>
        </div>
      </div>
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Save Owner Details</button>
      </div>
    </form>
  </div>
  <?php endif; ?>

  <!-- Compliance panels 2-col -->
  <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px">

    <?php if ($insurance): ?>
    <div class="section-card" style="margin-bottom:0">
      <div class="section-label">Insurance &nbsp;<?= expiry($insurance['valid_upto'] ?? '') ?></div>
      <form method="post">
        <input type="hidden" name="section" value="insurance">
        <div class="form-grid" style="grid-template-columns:1fr 1fr">
          <div class="field">
            <label>Policy Number</label>
            <input type="text" name="policy_no" value="<?= htmlspecialchars($insurance['policy_no'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Provider</label>
            <input type="text" name="provider" value="<?= htmlspecialchars($insurance['provider'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Valid Upto</label>
            <input type="date" name="ins_valid_upto" value="<?= htmlspecialchars($insurance['valid_upto'] ?? '') ?>">
          </div>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($fitness): ?>
    <div class="section-card" style="margin-bottom:0">
      <div class="section-label">Fitness Certificate &nbsp;<?= expiry($fitness['valid_upto'] ?? '') ?></div>
      <form method="post">
        <input type="hidden" name="section" value="fitness">
        <div class="field">
          <label>Valid Upto</label>
          <input type="date" name="fit_valid_upto" value="<?= htmlspecialchars($fitness['valid_upto'] ?? '') ?>">
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($tax): ?>
    <div class="section-card" style="margin-bottom:0">
      <div class="section-label">Road Tax &nbsp;<?= expiry($tax['paid_upto'] ?? '') ?></div>
      <form method="post">
        <input type="hidden" name="section" value="tax">
        <div class="field">
          <label>Paid Upto</label>
          <input type="date" name="tax_paid_upto" value="<?= htmlspecialchars($tax['paid_upto'] ?? '') ?>">
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
    <?php endif; ?>

    <?php if ($pollution): ?>
    <div class="section-card" style="margin-bottom:0">
      <div class="section-label">Pollution (PUC) &nbsp;<?= expiry($pollution['valid_upto'] ?? '') ?></div>
      <form method="post">
        <input type="hidden" name="section" value="pollution">
        <div class="form-grid" style="grid-template-columns:1fr 1fr">
          <div class="field">
            <label>PUCC Number</label>
            <input type="text" name="pucc_no" value="<?= htmlspecialchars($pollution['pucc_no'] ?? '') ?>">
          </div>
          <div class="field">
            <label>Valid Upto</label>
            <input type="date" name="puc_valid_upto" value="<?= htmlspecialchars($pollution['valid_upto'] ?? '') ?>">
          </div>
        </div>
        <div class="form-actions"><button type="submit" class="btn btn-primary">Save</button></div>
      </form>
    </div>
    <?php endif; ?>

  </div>

</div>
</div>

<footer class="site-footer">
  <span>Smart RC Book &nbsp;·&nbsp; Control Panel</span>
  <span>Session encrypted &nbsp;·&nbsp; <?= date('d M Y') ?></span>
</footer>
<script src="/smartrc/admin/dashboard/home/adminPage.js"></script>
</body>
</html>
