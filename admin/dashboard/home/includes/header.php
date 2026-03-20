<?php
// ============================================================
//  admin/dashboard/home/includes/header.php
// ============================================================

// FIX: PHP already URL-decodes cookie values automatically.
// urldecode() would double-decode and corrupt names containing '+' or '%xx'.
$adminDisplayName = $_COOKIE['adminName'] ?? 'Admin';
$currentPage      = basename($_SERVER['PHP_SELF'], '.php');
$base             = '/smartrc/admin/dashboard/home';
?>
<div class="top-bar"></div>
<header class="site-header">
  <div class="logo">Smart RC Book</div>
  <nav class="header-nav">
    <a href="<?= $base ?>/index.php"
       class="<?= $currentPage === 'index' ? 'active' : '' ?>">Dashboard</a>
    <a href="<?= $base ?>/vehicle.php"
       class="<?= $currentPage === 'vehicle' ? 'active' : '' ?>">Search RC</a>
    <a href="<?= $base ?>/change_password.php"
       class="<?= $currentPage === 'change_password' ? 'active' : '' ?>">Change Password</a>
    <a href="<?= $base ?>/logout.php"
       onclick="return confirm('End your current session?')"
       style="color:var(--muted)">Logout</a>
  </nav>
  <div class="header-user">
    <span><?= htmlspecialchars($adminDisplayName) ?></span>
    &nbsp;·&nbsp; ADMIN
  </div>
</header>
