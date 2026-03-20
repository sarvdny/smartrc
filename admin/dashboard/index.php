<?php
// ============================================================
//  admin/dashboard/index.php — Token auth barrier
//  BUGS FIXED:
//  1. Includes loaded BEFORE any mysqli_query call
//  2. $_GET['token'] has null coalescing default
//  3. exit after all header() redirects
// ============================================================

include __DIR__ . '/../../auth/api/adminDbConnection.php';
include __DIR__ . '/../../auth/api/getuserdata.php';

// FIX: Accept token via POST (preferred — keeps token out of URL/logs)
// or GET as fallback for backward compat
$usertoken = $_POST['token'] ?? $_GET['token'] ?? '';

// Already authenticated via cookie → go straight to dashboard
if (isset($_COOKIE['isLoggedIn']) && isset($_COOKIE['adminToken'])) {
    header('Location: ./home/');
    exit;
}

// No token provided → back to login
if ($usertoken === '') {
    header('Location: /smartrc/auth/login/');
    exit;
}

// FIX: escape token before SQL — never interpolate raw GET input
$safeToken = mysqli_real_escape_string($adminConn, $usertoken);
$result = mysqli_query($adminConn, "SELECT * FROM `admin_details` WHERE `token` = '$safeToken' LIMIT 1");

if (!$result) {
    header('Location: /smartrc/auth/login/');
    exit;
}

switch (mysqli_num_rows($result)) {
    case 1:
        $user     = mysqli_fetch_assoc($result);
        $newToken = password_hash(generateRandomString(32), PASSWORD_DEFAULT);

        // FIX: escape both values before interpolation
        $safeNew = mysqli_real_escape_string($adminConn, $newToken);
        mysqli_query($adminConn,
            "UPDATE `admin_details` SET `token` = '$safeNew' WHERE `token` = '$safeToken'"
        );
        mysqli_close($adminConn);

        $ttl = time() + 60 * 60 * 8; // 8 hour session
        setcookie('isLoggedIn', 'true', $ttl, '/');
        setcookie('adminToken', $newToken, $ttl, '/');
        // Store name for header display (non-sensitive display value)
        setcookie('adminName', $user['name'] ?? $user['email'], $ttl, '/');

        header('Location: ./home/');
        exit;

    default:
        mysqli_close($adminConn);
        // Invalid token → show JSON error (matching original behaviour)
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['user' => 'not found', 'access' => 'denied']);
        exit;
}
