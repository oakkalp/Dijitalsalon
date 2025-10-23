<?php
/**
 * Logout Page
 * Digital Salon - Çıkış sayfası
 */

session_start();

// Oturumu sonlandır
session_unset();
session_destroy();

// Login sayfasına yönlendir
header('Location: login.php?message=logout');
exit();
?>
