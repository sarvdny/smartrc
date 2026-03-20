<?php
// ============================================================
//  auth/api/adminDbConnection.php
//  BUG FIXED: $pasword typo → $password
//  BUG FIXED: forbidden() called before responsegenerator loaded
// ============================================================

$servername         = 'localhost';
$username           = 'root';
$password           = '';              // was: $pasword (typo — always sent wrong password)
$login_database_name = 'admin_login';

$adminConn = mysqli_connect($servername, $username, $password, $login_database_name);

if (!$adminConn) {
    // BUG FIX: Cannot call forbidden() here — responsegenerator.php may not be loaded yet.
    // Use raw http response instead.
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['message' => 'Database connection failed: ' . mysqli_connect_error()]);
    exit;
}

mysqli_set_charset($adminConn, 'utf8mb4');
