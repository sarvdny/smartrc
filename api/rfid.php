<?php
// ============================================================
//  api/rfid.php — ESP8266 / RC522 RFID lookup endpoint
//
//  The ESP8266 scans an RFID tag and calls this endpoint.
//  It returns only the fields requested, formatted for easy
//  parsing and display on a 1.3" SSD1306 OLED (128×64px).
//
//  REQUEST:
//    GET /smartrc/api/rfid.php
//      ?tag=RFID_TAG_VALUE
//      &fields=owner_name,vehicle_no,model,insurance,fitness,tax,pollution
//      &key=YOUR_API_KEY         (from api_keys table or config below)
//
//  RESPONSE (success):
//    { "found": true, "rc_no": "MH12AB1234", "data": { ... } }
//
//  RESPONSE (not found):
//    { "found": false, "message": "Tag not registered" }
//
//  OLED LINE BUDGET:
//    SSD1306 128x64 with font size 1 = 8 rows × 21 chars per row
//    Values are truncated to fit. Use &compact=1 for 10-char max values.
// ============================================================

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// ── Simple API key auth ────────────────────────────────────
// Set your key here or move to a config file
define('API_KEY', 'smartrc_rfid_2025');

$requestKey = $_GET['key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? '';
if ($requestKey !== API_KEY) {
    http_response_code(401);
    echo json_encode(['found' => false, 'message' => 'Unauthorized']);
    exit;
}

// ── Input ──────────────────────────────────────────────────
$tag     = trim($_GET['tag']     ?? '');
$compact = isset($_GET['compact']) && $_GET['compact'] === '1';

// Which fields the OLED should display — comma-separated
// Available: owner_name, father_name, vehicle_no, model, manufacturer,
//            class, fuel_type, color, engine_no, chassis_no, rto, state,
//            reg_date, reg_valid, insurance, fitness, tax, pollution
$requestedRaw = trim($_GET['fields'] ?? 'owner_name,vehicle_no,model,insurance');
$requested    = array_filter(array_map('trim', explode(',', $requestedRaw)));

$allowedFields = [
    'owner_name','father_name','vehicle_no','model','manufacturer',
    'class','fuel_type','color','engine_no','chassis_no','rto','state',
    'reg_date','reg_valid','insurance','fitness','tax','pollution'
];
$requested = array_intersect($requested, $allowedFields);

if ($tag === '') {
    http_response_code(400);
    echo json_encode(['found' => false, 'message' => 'No tag provided']);
    exit;
}

// ── DB connection ──────────────────────────────────────────
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
try {
    $db = mysqli_connect('localhost', 'root', '', 'RC_DATA');
    mysqli_set_charset($db, 'utf8mb4');
} catch (mysqli_sql_exception $e) {
    http_response_code(503);
    echo json_encode(['found' => false, 'message' => 'DB unavailable']);
    exit;
}

// ── Lookup by RFID tag ─────────────────────────────────────
$safeTag = mysqli_real_escape_string($db, $tag);
$result  = mysqli_query($db,
    "SELECT r.*, o.`name` AS owner_name, o.`father_name`,
            i.`valid_upto` AS ins_upto, i.`provider` AS ins_provider,
            f.`valid_upto` AS fit_upto,
            t.`paid_upto`  AS tax_upto,
            p.`valid_upto` AS puc_upto, p.`pucc_no`
     FROM `rc` r
     LEFT JOIN `owners`    o ON o.`rc_no` = r.`rc_no`
     LEFT JOIN `insurance` i ON i.`rc_no` = r.`rc_no`
     LEFT JOIN `fitness`   f ON f.`rc_no` = r.`rc_no`
     LEFT JOIN `tax`       t ON t.`rc_no` = r.`rc_no`
     LEFT JOIN `pollution` p ON p.`rc_no` = r.`rc_no`
     WHERE r.`rfid_tag` = '$safeTag'
     LIMIT 1"
);

if (!$result || mysqli_num_rows($result) === 0) {
    mysqli_close($db);
    echo json_encode(['found' => false, 'message' => 'Tag not registered']);
    exit;
}

$row = mysqli_fetch_assoc($result);
mysqli_close($db);

// ── Helper: expiry status ──────────────────────────────────
function expiryStatus(?string $date, bool $compact): string
{
    if (!$date) return $compact ? 'N/A' : 'Not available';
    $diff = (strtotime($date) - time()) / 86400;
    $d    = date('d/m/y', strtotime($date));
    if ($diff < 0)  return $compact ? 'EXP'  : "Expired($d)";
    if ($diff < 30) return $compact ? 'SOON' : "Expiring($d)";
    return $compact ? 'OK' : "Valid($d)";
}

// ── Build response fields ──────────────────────────────────
function truncate(string $val, int $max): string
{
    return mb_strlen($val) > $max ? mb_substr($val, 0, $max - 1) . '~' : $val;
}

$maxLen = $compact ? 10 : 30;
$data   = [];

foreach ($requested as $field) {
    switch ($field) {
        case 'owner_name':
            $data['owner'] = truncate($row['owner_name'] ?? 'N/A', $maxLen);
            break;
        case 'father_name':
            $data['father'] = truncate($row['father_name'] ?? 'N/A', $maxLen);
            break;
        case 'vehicle_no':
            $data['veh_no'] = $row['vehicle_no'] ?? 'N/A';
            break;
        case 'model':
            $data['model'] = truncate(($row['manufacturer'] ?? '') . ' ' . ($row['model'] ?? ''), $maxLen);
            break;
        case 'manufacturer':
            $data['make'] = truncate($row['manufacturer'] ?? 'N/A', $maxLen);
            break;
        case 'class':
            $data['class'] = $row['class'] ?? 'N/A';
            break;
        case 'fuel_type':
            $data['fuel'] = $row['fuel_type'] ?? 'N/A';
            break;
        case 'color':
            $data['color'] = $row['color'] ?? 'N/A';
            break;
        case 'engine_no':
            $data['eng'] = truncate($row['engine_no'] ?? 'N/A', $maxLen);
            break;
        case 'chassis_no':
            $data['chassis'] = truncate($row['chassis_no'] ?? 'N/A', $maxLen);
            break;
        case 'rto':
            $data['rto'] = truncate($row['rto'] ?? 'N/A', $maxLen);
            break;
        case 'state':
            $data['state'] = truncate($row['state'] ?? 'N/A', $maxLen);
            break;
        case 'reg_date':
            $d = $row['registration_date'] ?? '';
            $data['reg'] = $d ? date('d/m/y', strtotime($d)) : 'N/A';
            break;
        case 'reg_valid':
            $data['reg_exp'] = expiryStatus($row['registration_valid_upto'] ?? null, $compact);
            break;
        case 'insurance':
            $data['ins'] = expiryStatus($row['ins_upto'] ?? null, $compact);
            break;
        case 'fitness':
            $data['fit'] = expiryStatus($row['fit_upto'] ?? null, $compact);
            break;
        case 'tax':
            $data['tax'] = expiryStatus($row['tax_upto'] ?? null, $compact);
            break;
        case 'pollution':
            $data['puc'] = expiryStatus($row['puc_upto'] ?? null, $compact);
            break;
    }
}

echo json_encode([
    'found' => true,
    'rc_no' => $row['rc_no'],
    'data'  => $data,
], JSON_UNESCAPED_UNICODE);
