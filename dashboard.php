<?php
/**
 * Dashboard Router
 * Digital Salon - Dashboard yönlendirici
 */

require_once 'config/database.php';
require_once 'includes/security.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_role = $_SESSION['user_role'];

// Rolüne göre yönlendir
switch ($user_role) {
    case 'super_admin':
        header('Location: super_admin_dashboard.php');
        break;
    case 'moderator':
        header('Location: moderator_dashboard.php');
        break;
    default:
        header('Location: user_dashboard.php');
        break;
}
exit();
?>
