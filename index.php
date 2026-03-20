<?php
// ============================================================
//  smartrc/index.php — Root redirect
// ============================================================
if (isset($_COOKIE['isLoggedIn']) && isset($_COOKIE['adminToken'])) {
    header('Location: /smartrc/admin/dashboard/?token=' . urlencode($_COOKIE['adminToken']));
} else {
    header('Location: /smartrc/auth/login/');
}
exit;
