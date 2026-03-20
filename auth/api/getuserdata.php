<?php
// ============================================================
//  auth/api/getuserdata.php
//  Shared utility functions used across auth + dashboard
//  WAS EMPTY — now contains generateRandomString() centrally
//  so it's not duplicated across 3 files
// ============================================================

function generateRandomString(int $length = 32): string
{
    $characters       = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $charactersLength = strlen($characters);
    $randomString     = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[random_int(0, $charactersLength - 1)];
    }
    return $randomString;
}

// Fetch admin record by token — returns assoc array or false
function getAdminByToken($conn, string $token)
{
    $token  = mysqli_real_escape_string($conn, $token);
    $result = mysqli_query($conn, "SELECT * FROM `admin_details` WHERE `token` = '$token' LIMIT 1");
    if (!$result || mysqli_num_rows($result) === 0) return false;
    return mysqli_fetch_assoc($result);
}
