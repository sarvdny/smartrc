<?php
// ============================================================
//  admin/dashboard/home/logout.php — Clear session and redirect
//  This file did not exist — logout nav link had no destination
// ============================================================

$past = time() - 3600;
setcookie('isLoggedIn', '', $past, '/');
setcookie('adminToken', '', $past, '/');
setcookie('adminName',  '', $past, '/');

header('Location: /smartrc/auth/login/');
exit;
