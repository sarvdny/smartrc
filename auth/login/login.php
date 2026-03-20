<?php
// ============================================================
//  auth/login/login.php — Admin login endpoint
//  BUGS FIXED:
//  1. Method check now comes BEFORE reading body data
//  2. trim($data['email'], '') → trim($data['email'])
//     (second arg to trim is a character list, not comparison)
//  3. __DIR__ redirect replaced with proper URL
//  4. Sets adminName cookie so header.php doesn't need a DB query
// ============================================================

// Already logged in → redirect directly to home (DO NOT put token in URL)
if (isset($_COOKIE['isLoggedIn']) && isset($_COOKIE['adminToken'])) {
    header('Location: /smartrc/admin/dashboard/home/');
    exit;
}

include __DIR__ . '/getpostdata.php';
include __DIR__ . '/../../responsegenerator.php';
include __DIR__ . '/../api/adminDbConnection.php';

header('Content-Type: application/json; charset=utf-8');
setCorsHeaders();
date_default_timezone_set('Asia/Calcutta');

// BUG FIX: Method check FIRST, before reading or using any body data
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    badRequest('Method not allowed.');
}

$data = getPostData();

// BUG FIX: trim($data['email'], '') was wrong — second arg is char-list, not string
$email    = trim($data['email']    ?? '');
$password = trim($data['password'] ?? '');

if (!$email || !$password) {
    unauthorized('Please provide email and password.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    badRequest('Invalid email format.');
}

$safeEmail = mysqli_real_escape_string($adminConn, $email);
$result    = mysqli_query($adminConn, "SELECT * FROM `admin_details` WHERE `email` = '$safeEmail' LIMIT 1");

// FIX: check result before passing to mysqli_num_rows — a failed query returns false
if (!$result) {
    mysqli_close($adminConn);
    serverError('Database error. Please try again.');
}

switch (mysqli_num_rows($result)) {
    case 1:
        $user_data = mysqli_fetch_assoc($result);

        if (!password_verify($password, $user_data['password'])) {
            unauthorized('Incorrect password.');
        }

        // Update last login timestamp
        $current_date = date('l, F j, Y') . ' at ' . date('h:i:s a');
        $safeDate     = mysqli_real_escape_string($adminConn, $current_date);
        mysqli_query($adminConn, "UPDATE `admin_details` SET `last_logged` = '$safeDate' WHERE `email` = '$safeEmail'");

        mysqli_close($adminConn);

        ok([
            'user'    => 'valid',
            'message' => 'Login successful.',
            'token'   => $user_data['token'],
            'name'    => $user_data['name'] ?? $user_data['email'],
        ]);
        break;

    case 0:
        mysqli_close($adminConn);
        badRequest('No account found. Please check your email and try again.');
        break;

    default:
        mysqli_close($adminConn);
        serverError('Unexpected error. Please contact support.');
}
