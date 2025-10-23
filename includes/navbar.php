<?php
/**
 * Modern Navbar Component
 * Digital Salon - Ortak Navigasyon Çubuğu
 */

if (!isset($_SESSION)) {
    session_start();
}

$user_id = $_SESSION['user_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? 'kullanici';
$user_name = $_SESSION['user_name'] ?? 'Kullanıcı';
$current_page = basename($_SERVER['PHP_SELF']);

// Aktif sayfa kontrolü
function isActive($page) {
    global $current_page;
    return $current_page === $page ? 'active' : '';
}

// Dashboard URL'i belirle
$dashboard_url = 'dashboard.php';
if ($user_role === 'super_admin') {
    $dashboard_url = 'super_admin_dashboard.php';
} elseif ($user_role === 'moderator') {
    $dashboard_url = 'moderator_dashboard.php';
} elseif ($user_role === 'kullanici') {
    $dashboard_url = 'user_dashboard.php';
}
?>
<nav class="navbar navbar-expand-lg navbar-dark fixed-top" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); backdrop-filter: blur(10px); box-shadow: 0 4px 20px rgba(0,0,0,0.1);">
    <div class="container">
        <!-- Brand -->
        <a class="navbar-brand d-flex align-items-center" href="<?php echo $user_id ? $dashboard_url : 'login.php'; ?>">
            <div class="brand-icon me-2">
                <i class="fas fa-heart"></i>
            </div>
            <span class="brand-text">Digital Salon</span>
        </a>
        
        <!-- Mobile Toggle -->
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <!-- Navigation Menu -->
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php if ($user_id): ?>
                    <!-- Dashboard -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive($dashboard_url); ?>" href="<?php echo $dashboard_url; ?>">
                            <i class="fas fa-tachometer-alt me-2"></i>
                            <span>Dashboard</span>
                        </a>
                    </li>
                    
                    <?php if ($user_role === 'super_admin'): ?>
                        <!-- Super Admin Menü -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActive('users.php'); ?>" href="users.php">
                                <i class="fas fa-users me-2"></i>
                                <span>Kullanıcılar</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActive('packages.php'); ?>" href="packages.php">
                                <i class="fas fa-box me-2"></i>
                                <span>Paketler</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActive('events.php'); ?>" href="events.php">
                                <i class="fas fa-calendar-alt me-2"></i>
                                <span>Düğünler</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActive('reports.php'); ?>" href="reports.php">
                                <i class="fas fa-chart-bar me-2"></i>
                                <span>Raporlar</span>
                            </a>
                        </li>
                        
                    <?php elseif ($user_role === 'moderator'): ?>
                        <!-- Moderator Menü -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActive('create_event.php'); ?>" href="create_event.php">
                                <i class="fas fa-plus me-2"></i>
                                <span>Düğün Oluştur</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActive('my_events.php'); ?>" href="my_events.php">
                                <i class="fas fa-calendar-check me-2"></i>
                                <span>Düğünlerim</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActive('my_reports.php'); ?>" href="my_reports.php">
                                <i class="fas fa-chart-line me-2"></i>
                                <span>Raporlarım</span>
                            </a>
                        </li>
                        
                    <?php else: ?>
                        <!-- Normal User Menü -->
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActive('my_weddings.php'); ?>" href="my_weddings.php">
                                <i class="fas fa-heart me-2"></i>
                                <span>Düğünlerim</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link <?php echo isActive('qr_scanner.php'); ?>" href="qr_scanner.php">
                                <i class="fas fa-qrcode me-2"></i>
                                <span>QR Tarayıcı</span>
                            </a>
                        </li>
                    <?php endif; ?>
                    
                    <!-- Ortak Menü -->
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('profile.php'); ?>" href="profile.php">
                            <i class="fas fa-user me-2"></i>
                            <span>Profil</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?php echo isActive('settings.php'); ?>" href="settings.php">
                            <i class="fas fa-cog me-2"></i>
                            <span>Ayarlar</span>
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
            
            <!-- User Menu -->
            <ul class="navbar-nav">
                <?php if ($user_id): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <div class="user-avatar me-2">
                                <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                            </div>
                            <span class="user-name"><?php echo htmlspecialchars($user_name); ?></span>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li class="dropdown-header">
                                <div class="d-flex align-items-center">
                                    <div class="user-avatar me-2">
                                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                                    </div>
                                    <div>
                                        <div class="fw-bold"><?php echo htmlspecialchars($user_name); ?></div>
                                        <small class="text-muted">
                                            <?php 
                                            $role_names = [
                                                'super_admin' => 'Super Admin',
                                                'moderator' => 'Moderator',
                                                'kullanici' => 'Normal Kullanıcı'
                                            ];
                                            echo $role_names[$user_role] ?? ucfirst(str_replace('_', ' ', $user_role));
                                            ?>
                                        </small>
                                    </div>
                                </div>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profil</a></li>
                            <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Ayarlar</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış</a></li>
                        </ul>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link" href="login.php">
                            <i class="fas fa-sign-in-alt me-2"></i>Giriş
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="register.php">
                            <i class="fas fa-user-plus me-2"></i>Kayıt
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </div>
    </div>
</nav>

<style>
/* Navbar Styling */
.navbar {
    padding: 1rem 0;
    transition: all 0.3s ease;
}

.navbar-brand {
    font-family: 'Poppins', sans-serif;
    font-weight: 700;
    font-size: 1.5rem;
    text-decoration: none;
    transition: all 0.3s ease;
}

.brand-icon {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    transition: all 0.3s ease;
}

.brand-text {
    color: white;
    font-weight: 700;
}

.navbar-brand:hover .brand-icon {
    background: rgba(255, 255, 255, 0.3);
    transform: scale(1.1);
}

.navbar-brand:hover .brand-text {
    color: white;
    text-decoration: none;
}

.nav-link {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 500;
    padding: 0.75rem 1rem !important;
    border-radius: 8px;
    margin: 0 0.25rem;
    transition: all 0.3s ease;
    position: relative;
}

.nav-link:hover {
    color: white !important;
    background: rgba(255, 255, 255, 0.1);
    transform: translateY(-1px);
}

.nav-link.active {
    color: white !important;
    background: rgba(255, 255, 255, 0.2);
    font-weight: 600;
}

.nav-link.active::after {
    content: '';
    position: absolute;
    bottom: -8px;
    left: 50%;
    transform: translateX(-50%);
    width: 20px;
    height: 2px;
    background: white;
    border-radius: 1px;
}

.user-avatar {
    width: 32px;
    height: 32px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.9rem;
    font-weight: 600;
    color: white;
}

.user-name {
    color: white;
    font-weight: 500;
}

.dropdown-menu {
    border: none;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
    border-radius: 12px;
    padding: 0.5rem 0;
    margin-top: 0.5rem;
}

.dropdown-item {
    padding: 0.75rem 1.5rem;
    transition: all 0.3s ease;
    color: #374151;
}

.dropdown-item:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.dropdown-item i {
    width: 16px;
    text-align: center;
}

.dropdown-header {
    padding: 1rem 1.5rem;
    background: #f9fafb;
    border-bottom: 1px solid #e5e7eb;
}

.navbar-toggler {
    border: none;
    padding: 0.25rem 0.5rem;
}

.navbar-toggler:focus {
    box-shadow: none;
}

/* Mobile Responsive */
@media (max-width: 991.98px) {
    .navbar-collapse {
        background: rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 1rem;
        margin-top: 1rem;
        backdrop-filter: blur(10px);
    }
    
    .nav-link {
        margin: 0.25rem 0;
        text-align: center;
    }
    
    .navbar-nav .nav-link {
        padding: 0.75rem 1rem !important;
    }
}

/* Scroll Effect */
.navbar.scrolled {
    background: rgba(102, 126, 234, 0.95) !important;
    backdrop-filter: blur(20px);
    box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
}

/* Animation */
@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.navbar {
    animation: fadeInDown 0.6s ease-out;
}
</style>

<script>
// Navbar scroll effect
window.addEventListener('scroll', function() {
    const navbar = document.querySelector('.navbar');
    if (window.scrollY > 50) {
        navbar.classList.add('scrolled');
    } else {
        navbar.classList.remove('scrolled');
    }
});

// Mobile menu close on click
document.addEventListener('DOMContentLoaded', function() {
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    
    if (navbarToggler && navbarCollapse) {
        navbarToggler.addEventListener('click', function() {
            setTimeout(() => {
                if (navbarCollapse.classList.contains('show')) {
                    navbarCollapse.classList.remove('show');
                }
            }, 300);
        });
    }
});
</script>