<?php
// Sidebar component - tüm admin sayfalarında kullanılır

if (!isset($admin_user_id) || !isset($admin_user_role)) {
    header('Location: index.php');
    exit;
}

// Aktif sayfa belirleme
$current_page = basename($_SERVER['PHP_SELF']);
?>
<div class="sidebar">
    <div class="sidebar-header">
        <div class="sidebar-logo">
            <i class="fas fa-camera-retro"></i>
            <h2>Dijitalsalon</h2>
        </div>
        <div class="user-info">
            <div class="user-avatar">
                <?php echo strtoupper(substr($_SESSION['admin_user_name'], 0, 1)); ?>
            </div>
            <div class="user-details">
                <h4><?php echo htmlspecialchars($_SESSION['admin_user_name']); ?></h4>
                <p><?php echo htmlspecialchars($_SESSION['admin_user_email']); ?></p>
                <span class="role-badge role-<?php echo $admin_user_role; ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $admin_user_role)); ?>
                </span>
            </div>
        </div>
    </div>
    <nav class="sidebar-nav">
        <div class="nav-item">
            <a href="dashboard.php" class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                Dashboard
            </a>
        </div>
        <div class="nav-item">
            <a href="events.php" class="nav-link <?php echo in_array($current_page, ['events.php', 'event-details.php', 'edit-event.php', 'create-event.php']) ? 'active' : ''; ?>">
                <i class="fas fa-calendar-alt"></i>
                Düğünler
            </a>
        </div>
        <?php if ($admin_user_role === 'super_admin'): ?>
        <div class="nav-item">
            <a href="users.php" class="nav-link <?php echo $current_page === 'users.php' ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                Kullanıcılar
            </a>
        </div>
        <?php endif; ?>
        <?php if ($admin_user_role === 'super_admin'): ?>
        <div class="nav-item">
            <a href="moderators.php" class="nav-link <?php echo $current_page === 'moderators.php' ? 'active' : ''; ?>">
                <i class="fas fa-user-shield"></i>
                Moderatorler
            </a>
        </div>
        <?php endif; ?>
        <div class="nav-item">
            <a href="media.php" class="nav-link <?php echo $current_page === 'media.php' ? 'active' : ''; ?>">
                <i class="fas fa-images"></i>
                Medya
            </a>
        </div>
        <div class="nav-item">
            <a href="reports.php" class="nav-link <?php echo $current_page === 'reports.php' ? 'active' : ''; ?>">
                <i class="fas fa-chart-bar"></i>
                Raporlar
            </a>
        </div>
        <div class="nav-item">
            <?php if ($admin_user_role === 'super_admin'): ?>
                <a href="packages.php" class="nav-link <?php echo $current_page === 'packages.php' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i>
                    Paketler
                </a>
            <?php else: ?>
                <a href="view-packages.php" class="nav-link <?php echo $current_page === 'view-packages.php' ? 'active' : ''; ?>">
                    <i class="fas fa-box"></i>
                    Paketler
                </a>
            <?php endif; ?>
        </div>
        <?php if ($admin_user_role === 'super_admin'): ?>
        <div class="nav-item">
            <a href="system.php" class="nav-link <?php echo $current_page === 'system.php' ? 'active' : ''; ?>">
                <i class="fas fa-server"></i>
                Sistem
            </a>
        </div>
        <?php endif; ?>
        <?php if ($admin_user_role === 'super_admin'): ?>
        <div class="nav-item">
            <a href="settings.php" class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                Ayarlar
            </a>
        </div>
        <?php endif; ?>
        <?php if ($admin_user_role === 'super_admin'): ?>
        <div class="nav-item">
            <a href="test_smtp.php" class="nav-link <?php echo $current_page === 'test_smtp.php' ? 'active' : ''; ?>">
                <i class="fas fa-vial"></i>
                SMTP Test
            </a>
        </div>
        <?php endif; ?>
        <?php if ($admin_user_role === 'super_admin'): ?>
        <div class="nav-item">
            <a href="test_php_mail.php" class="nav-link <?php echo $current_page === 'test_php_mail.php' ? 'active' : ''; ?>">
                <i class="fas fa-envelope-open"></i>
                PHP mail() Test
            </a>
        </div>
        <?php endif; ?>
    </nav>
</div>

