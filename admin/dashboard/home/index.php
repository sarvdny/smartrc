<?php
// ============================================================
//  admin/dashboard/home/index.php — RC Dashboard
//  BUG FIXED: auth guard redirect was /login/ → now correct path
//  BUG FIXED: SQL queries backtick all identifiers
// ============================================================

require_once __DIR__ . '/includes/db_rc.php';

if (!isset($_COOKIE['adminToken']) || !isset($_COOKIE['isLoggedIn'])) {
    header('Location: /smartrc/auth/login/');
    exit;
}

$db    = getRcDB();
$today = mysqli_real_escape_string($db, date('Y-m-d'));

$totalVehicles    = (int) db_scalar($db, 'SELECT COUNT(*) FROM `rc`');
$expiredIns       = (int) db_scalar($db, "SELECT COUNT(*) FROM `insurance` WHERE `valid_upto` < '$today'");
$expiredFitness   = (int) db_scalar($db, "SELECT COUNT(*) FROM `fitness` WHERE `valid_upto` < '$today'");
$expiredPollution = (int) db_scalar($db, "SELECT COUNT(*) FROM `pollution` WHERE `valid_upto` < '$today'");

$recent = db_fetch_all($db,
    'SELECT r.`rc_no`, r.`vehicle_no`, r.`model`, r.`manufacturer`, r.`state`,
            r.`registration_date`, r.`fuel_type`,
            o.`name` AS owner_name,
            i.`valid_upto` AS insurance_upto
     FROM `rc` r
     LEFT JOIN `owners` o ON o.`rc_no` = r.`rc_no`
     LEFT JOIN `insurance` i ON i.`rc_no` = r.`rc_no`
     ORDER BY r.`registration_date` DESC
     LIMIT 8'
);

$search        = trim($_GET['q'] ?? '');
$searchResults = [];
if ($search !== '') {
    $like          = "%$search%";
    $searchResults = db_fetch_all($db,
        'SELECT r.`rc_no`, r.`vehicle_no`, r.`model`, r.`manufacturer`, r.`state`,
                r.`registration_date`, r.`fuel_type`,
                o.`name` AS owner_name,
                i.`valid_upto` AS insurance_upto
         FROM `rc` r
         LEFT JOIN `owners` o ON o.`rc_no` = r.`rc_no`
         LEFT JOIN `insurance` i ON i.`rc_no` = r.`rc_no`
         WHERE r.`rc_no` LIKE ? OR r.`vehicle_no` LIKE ?
            OR o.`name` LIKE ? OR r.`model` LIKE ?
         LIMIT 20',
        'ssss', [$like, $like, $like, $like]
    );
}

function statusBadge(string $date): string
{
    if (!$date) return '<span class="badge badge-warn">Unknown</span>';
    $diff = (strtotime($date) - time()) / 86400;
    if ($diff < 0)  return '<span class="badge badge-danger">Expired</span>';
    if ($diff < 30) return '<span class="badge badge-warn">Expiring</span>';
    return '<span class="badge badge-ok">Valid</span>';
}

$rows = $search ? $searchResults : $recent;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>RC Dashboard — Smart RC Book</title>
  <link rel="stylesheet" href="/smartrc/admin/dashboard/home/adminPage.css"/>
  <style><?php include __DIR__ . '/includes/style.php'; ?></style>
</head>
<body>
<?php include __DIR__ . '/includes/header.php'; ?>

<div class="page-body">
<div class="container">

  <div class="page-title">Registration Certificate System</div>
  <div class="page-heading">Dashboard</div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-label">Total Vehicles</div>
      <div class="stat-value"><?= $totalVehicles ?></div>
      <div class="stat-sub">registered in system</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Insurance Expired</div>
      <div class="stat-value" style="color:<?= $expiredIns > 0 ? 'var(--danger)' : 'var(--success)' ?>">
        <?= $expiredIns ?>
      </div>
      <div class="stat-sub">policies lapsed</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">Fitness Expired</div>
      <div class="stat-value" style="color:<?= $expiredFitness > 0 ? 'var(--warning)' : 'var(--success)' ?>">
        <?= $expiredFitness ?>
      </div>
      <div class="stat-sub">certificates overdue</div>
    </div>
    <div class="stat-card">
      <div class="stat-label">PUC Expired</div>
      <div class="stat-value" style="color:<?= $expiredPollution > 0 ? 'var(--warning)' : 'var(--success)' ?>">
        <?= $expiredPollution ?>
      </div>
      <div class="stat-sub">PUCC overdue</div>
    </div>
  </div>

  <div class="card">
    <div class="card-title">Vehicle Registry</div>
    <form method="get" action="">
      <div class="search-bar">
        <input type="text" name="q" value="<?= htmlspecialchars($search) ?>"
               placeholder="Search by RC number, vehicle no, owner name or model..."/>
        <button type="submit">Search</button>
      </div>
    </form>

    <?php if ($search && empty($searchResults)): ?>
      <div class="alert alert-warn">No records found for "<?= htmlspecialchars($search) ?>".</div>
    <?php endif; ?>

    <table class="data-table">
      <thead>
        <tr>
          <th>RC Number</th><th>Owner</th><th>Vehicle</th><th>Fuel</th>
          <th>State</th><th>Reg. Date</th><th>Insurance</th><th>Action</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $row): ?>
        <tr>
          <td><span class="rc-no"><?= htmlspecialchars($row['rc_no']) ?></span></td>
          <td><?= htmlspecialchars($row['owner_name'] ?? '—') ?></td>
          <td style="font-family:var(--mono);font-size:12px">
            <?= htmlspecialchars($row['manufacturer'] . ' ' . $row['model']) ?>
          </td>
          <td style="font-family:var(--mono);font-size:12px"><?= htmlspecialchars($row['fuel_type'] ?? '—') ?></td>
          <td style="font-size:12px;color:var(--muted)"><?= htmlspecialchars($row['state'] ?? '—') ?></td>
          <td style="font-family:var(--mono);font-size:12px"><?= htmlspecialchars($row['registration_date']) ?></td>
          <td><?= statusBadge($row['insurance_upto'] ?? '') ?></td>
          <td>
            <a href="vehicle.php?rc=<?= urlencode($row['rc_no']) ?>"
               class="btn btn-ghost" style="padding:5px 12px;font-size:10px">View</a>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($rows)): ?>
        <tr>
          <td colspan="8" style="text-align:center;color:var(--muted);padding:32px;
              font-family:var(--mono);font-size:12px">No records found.</td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>

    <?php if (!$search): ?>
    <div class="mt-16 text-muted" style="text-align:right">
      Showing last <?= count($rows) ?> registrations
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
