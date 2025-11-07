<?php
session_start();

// Admin giriş kontrolü
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';

// Kullanıcı bilgilerini al
$admin_user_id = $_SESSION['admin_user_id'];
$admin_user_name = $_SESSION['admin_user_name'];
$admin_user_role = $_SESSION['admin_user_role'];
$admin_user_email = $_SESSION['admin_user_email'];

// İstatistikleri al
try {
    // Rol bazlı sorgular
    if ($admin_user_role === 'super_admin') {
        // Super Admin - Tüm verileri görebilir
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM kullanicilar WHERE durum = 'aktif'");
        $total_users = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM dugunler");
        $total_events = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM dugunler WHERE DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $active_events = $stmt->fetch()['total'];
        
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM medyalar");
        $total_media = $stmt->fetch()['total'];
        
        // Son düğünler (tümü)
        $stmt = $pdo->query("
            SELECT d.*, k.ad, k.soyad 
            FROM dugunler d 
            LEFT JOIN kullanicilar k ON d.moderator_id = k.id 
            ORDER BY d.created_at DESC 
            LIMIT 5
        ");
        $recent_events = $stmt->fetchAll();
        
        // Son kullanıcılar (tümü)
        $stmt = $pdo->query("
            SELECT id, ad, soyad, email, rol, created_at 
            FROM kullanicilar 
            WHERE durum = 'aktif' 
            ORDER BY created_at DESC 
            LIMIT 5
        ");
        $recent_users = $stmt->fetchAll();
        
    } else {
        // Moderator - Sadece kendi etkinliklerini görebilir
        $total_users = 0; // Moderator kullanıcı sayısını göremez
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM dugunler WHERE moderator_id = ?");
        $stmt->execute([$admin_user_id]);
        $total_events = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM dugunler WHERE moderator_id = ? AND DATE(created_at) >= DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $stmt->execute([$admin_user_id]);
        $active_events = $stmt->fetch()['total'];
        
        // Moderator'un etkinliklerindeki medya sayısı
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as total 
            FROM medyalar m 
            INNER JOIN dugunler d ON m.dugun_id = d.id 
            WHERE d.moderator_id = ?
        ");
        $stmt->execute([$admin_user_id]);
        $total_media = $stmt->fetch()['total'];
        
        // Son düğünler (sadece kendi etkinlikleri)
        $stmt = $pdo->prepare("
            SELECT d.*, k.ad, k.soyad 
            FROM dugunler d 
            LEFT JOIN kullanicilar k ON d.moderator_id = k.id 
            WHERE d.moderator_id = ?
            ORDER BY d.created_at DESC 
            LIMIT 5
        ");
        $stmt->execute([$admin_user_id]);
        $recent_events = $stmt->fetchAll();
        
        // Moderator kullanıcı listesini göremez
        $recent_users = [];
    }
    
} catch (Exception $e) {
    $error_message = "Veri yüklenirken hata oluştu: " . $e->getMessage();
    // Hata durumunda varsayılan değerler
    $total_users = 0;
    $total_events = 0;
    $active_events = 0;
    $total_media = 0;
    $recent_events = [];
    $recent_users = [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dijitalsalon Admin Panel - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .sidebar-logo i {
            font-size: 1.5rem;
            color: #6366f1;
        }

        .sidebar-logo h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f1f5f9;
            border-radius: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .user-details h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e293b;
        }

        .user-details p {
            font-size: 0.8rem;
            color: #64748b;
        }

        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .role-super_admin {
            background: #fef3c7;
            color: #d97706;
        }

        .role-moderator {
            background: #dbeafe;
            color: #2563eb;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #64748b;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background: #f1f5f9;
            color: #6366f1;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }

        .nav-link i {
            font-size: 1.1rem;
            width: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
        }

        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .main-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .logout-btn {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        .logout-btn:hover {
            background: #fecaca;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .stat-icon.users {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
        }

        .stat-icon.events {
            background: linear-gradient(135deg, #10b981, #059669);
        }

        .stat-icon.media {
            background: linear-gradient(135deg, #f59e0b, #d97706);
        }

        .stat-icon.active {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .stat-change {
            font-size: 0.8rem;
            font-weight: 500;
            margin-top: 0.5rem;
        }

        .stat-change.positive {
            color: #059669;
        }

        .stat-change.negative {
            color: #dc2626;
        }

        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .card-content {
            padding: 1.5rem;
        }

        .list-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .list-item:last-child {
            border-bottom: none;
        }

        .list-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .list-content {
            flex: 1;
        }

        .list-title {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .list-subtitle {
            font-size: 0.8rem;
            color: #64748b;
        }

        .list-meta {
            font-size: 0.75rem;
            color: #9ca3af;
        }

        .view-all {
            text-align: center;
            padding: 1rem;
            border-top: 1px solid #f1f5f9;
        }

        .view-all a {
            color: #6366f1;
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
        }

        .view-all a:hover {
            text-decoration: underline;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .content-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-camera-retro"></i>
                    <h2>Dijitalsalon</h2>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($admin_user_name, 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($admin_user_name); ?></h4>
                        <p><?php echo htmlspecialchars($admin_user_email); ?></p>
                        <span class="role-badge role-<?php echo $admin_user_role; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $admin_user_role)); ?>
                        </span>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="dashboard.php" class="nav-link active">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="events.php" class="nav-link">
                        <i class="fas fa-calendar-alt"></i>
                        Düğünler
                    </a>
                </div>
                <?php if ($admin_user_role === 'super_admin'): ?>
                <div class="nav-item">
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        Kullanıcılar
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($admin_user_role === 'super_admin'): ?>
                <div class="nav-item">
                    <a href="moderators.php" class="nav-link">
                        <i class="fas fa-user-shield"></i>
                        Moderatorler
                    </a>
                </div>
                <?php endif; ?>
                <div class="nav-item">
                    <a href="media.php" class="nav-link">
                        <i class="fas fa-images"></i>
                        Medya
                    </a>
                </div>
                <div class="nav-item">
                    <a href="reports.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        Raporlar
                    </a>
                </div>
                <div class="nav-item">
                    <?php if ($admin_user_role === 'super_admin'): ?>
                        <a href="packages.php" class="nav-link">
                            <i class="fas fa-box"></i>
                            Paketler
                        </a>
                    <?php else: ?>
                        <a href="view-packages.php" class="nav-link">
                            <i class="fas fa-box"></i>
                            Paketler
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="main-header">
                <h1 class="main-title">Dashboard</h1>
                <div class="header-actions">
                    <a href="profile.php" class="btn btn-secondary">
                        <i class="fas fa-user"></i>
                        Profil
                    </a>
                    <a href="logout.php" class="btn logout-btn">
                        <i class="fas fa-sign-out-alt"></i>
                        Çıkış
                    </a>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <?php if ($admin_user_role === 'super_admin'): ?>
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon users">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_users); ?></div>
                    <div class="stat-label">Toplam Kullanıcı</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> +12% bu ay
                    </div>
                </div>
                <?php endif; ?>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon events">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_events); ?></div>
                    <div class="stat-label">Toplam Düğün</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> +8% bu ay
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon active">
                            <i class="fas fa-heart"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($active_events); ?></div>
                    <div class="stat-label">Aktif Düğün</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> Son 30 gün
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon media">
                            <i class="fas fa-images"></i>
                        </div>
                    </div>
                    <div class="stat-value"><?php echo number_format($total_media); ?></div>
                    <div class="stat-label">Toplam Medya</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i> +25% bu ay
                    </div>
                </div>
            </div>

            <!-- Content Grid -->
            <div class="content-grid">
                <!-- Recent Events -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-calendar-alt"></i>
                            Son Düğünler
                        </h3>
                    </div>
                    <div class="card-content">
                        <?php foreach ($recent_events as $event): ?>
                        <div class="list-item">
                            <div class="list-avatar">
                                <?php echo strtoupper(substr($event['ad'] ?? 'U', 0, 1)); ?>
                            </div>
                            <div class="list-content">
                                <div class="list-title"><?php echo htmlspecialchars($event['baslik'] ?? 'Başlıksız'); ?></div>
                                <div class="list-subtitle"><?php echo htmlspecialchars(($event['ad'] ?? '') . ' ' . ($event['soyad'] ?? '')); ?></div>
                                <div class="list-meta"><?php echo date('d.m.Y', strtotime($event['created_at'] ?? 'now')); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="view-all">
                            <a href="events.php">Tümünü Gör <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>

                <?php if ($admin_user_role === 'super_admin'): ?>
                <!-- Recent Users -->
                <div class="content-card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <i class="fas fa-users"></i>
                            Son Kullanıcılar
                        </h3>
                    </div>
                    <div class="card-content">
                        <?php foreach ($recent_users as $user): ?>
                        <div class="list-item">
                            <div class="list-avatar">
                                <?php echo strtoupper(substr($user['ad'], 0, 1)); ?>
                            </div>
                            <div class="list-content">
                                <div class="list-title"><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></div>
                                <div class="list-subtitle"><?php echo htmlspecialchars($user['email']); ?></div>
                                <div class="list-meta"><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <div class="view-all">
                            <a href="users.php">Tümünü Gör <i class="fas fa-arrow-right"></i></a>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
        }

        // Add mobile menu button if needed
        if (window.innerWidth <= 768) {
            const header = document.querySelector('.main-header');
            const menuBtn = document.createElement('button');
            menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            menuBtn.style.cssText = `
                background: none;
                border: none;
                font-size: 1.5rem;
                color: #64748b;
                cursor: pointer;
                padding: 0.5rem;
            `;
            menuBtn.onclick = toggleSidebar;
            header.insertBefore(menuBtn, header.firstChild);
        }

        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            // You can add AJAX call here to refresh stats
        }, 30000);
    </script>
</body>
</html>
