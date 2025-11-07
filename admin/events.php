<?php
session_start();

// Admin giriş kontrolü
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';

$admin_user_id = $_SESSION['admin_user_id'];
$admin_user_role = $_SESSION['admin_user_role'];

// Sadece super_admin ve moderator erişebilir
if (!in_array($admin_user_role, ['super_admin', 'moderator'])) {
    header('Location: dashboard.php');
    exit;
}

$success_message = $_GET['success'] ?? '';
$error_message = '';

// Paketleri al
try {
    $stmt = $pdo->query("SELECT id, ad, aciklama FROM paketler ORDER BY id");
    $packages = $stmt->fetchAll();
} catch (Exception $e) {
    $packages = [];
}

// Düğünleri listele
try {
    $page = (int)($_GET['page'] ?? 1);
    $limit = 10;
    $offset = ($page - 1) * $limit;
    
    if ($admin_user_role === 'super_admin') {
        // Super Admin - Tüm düğünleri görebilir
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM dugunler");
        $total_events = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("
            SELECT 
                d.*,
                k.ad as moderator_ad,
                k.soyad as moderator_soyad,
                p.ad as paket_ad,
                p.ucretsiz_erisim_gun,
                (SELECT COUNT(DISTINCT dk2.kullanici_id) FROM dugun_katilimcilar dk2 WHERE dk2.dugun_id = d.id AND dk2.durum = 'aktif') as katilimci_sayisi,
                (SELECT COUNT(DISTINCT m.id) FROM medyalar m WHERE m.dugun_id = d.id) as medya_sayisi,
                (SELECT COUNT(DISTINCT m2.id) FROM medyalar m2 WHERE m2.dugun_id = d.id AND m2.tur = 'hikaye') as hikaye_sayisi
            FROM dugunler d
            LEFT JOIN kullanicilar k ON d.moderator_id = k.id
            LEFT JOIN paketler p ON d.paket_id = p.id
            ORDER BY d.created_at DESC
            LIMIT $offset, $limit
        ");
        $stmt->execute();
        $events = $stmt->fetchAll();
    } else {
        // Moderator - Sadece kendi düğünlerini görebilir
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM dugunler WHERE moderator_id = ?");
        $stmt->execute([$admin_user_id]);
        $total_events = $stmt->fetch()['total'];
        
        $stmt = $pdo->prepare("
            SELECT 
                d.*,
                k.ad as moderator_ad,
                k.soyad as moderator_soyad,
                p.ad as paket_ad,
                p.ucretsiz_erisim_gun,
                (SELECT COUNT(DISTINCT dk2.kullanici_id) FROM dugun_katilimcilar dk2 WHERE dk2.dugun_id = d.id AND dk2.durum = 'aktif') as katilimci_sayisi,
                (SELECT COUNT(DISTINCT m.id) FROM medyalar m WHERE m.dugun_id = d.id) as medya_sayisi,
                (SELECT COUNT(DISTINCT m2.id) FROM medyalar m2 WHERE m2.dugun_id = d.id AND m2.tur = 'hikaye') as hikaye_sayisi
            FROM dugunler d
            LEFT JOIN kullanicilar k ON d.moderator_id = k.id
            LEFT JOIN paketler p ON d.paket_id = p.id
            WHERE d.moderator_id = ?
            ORDER BY d.created_at DESC
            LIMIT $offset, $limit
        ");
        $stmt->execute([$admin_user_id]);
        $events = $stmt->fetchAll();
    }
    
    $total_pages = ceil($total_events / $limit);
    
} catch (Exception $e) {
    $error_message = 'Veriler yüklenirken hata oluştu: ' . $e->getMessage();
    $events = [];
    $total_pages = 0;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dijitalsalon Admin Panel - Düğünler</title>
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

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
        }

        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message.success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .message.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        /* Create Event Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: #374151;
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .form-control:focus {
            outline: none;
            border-color: #6366f1;
            background: white;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        .form-control[type="date"] {
            color: #64748b;
        }

        .form-control[type="date"]:focus {
            color: #1e293b;
        }

        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }

        .form-text {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.25rem;
        }

        /* Events Grid */
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.2rem;
            align-items: start;
        }

        .event-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 3px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            min-height: 520px;
            max-height: 560px;
        }

        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
        }

        .event-header {
            padding: 1.2rem;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }

        .event-title {
            font-size: 1rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .event-creator {
            font-size: 0.85rem;
            opacity: 0.9;
        }

        .creator-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .creator-avatar {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .event-cover {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .creator-avatar i {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.8);
        }

        .creator-details {
            flex: 1;
        }

        .creator-name {
            font-size: 0.9rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .creator-name i {
            font-size: 0.8rem;
        }

        .moderator-name {
            font-size: 0.8rem;
            font-weight: 400;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 0.25rem;
            opacity: 0.8;
        }

        .moderator-name i {
            font-size: 0.7rem;
            color: #fbbf24;
        }

        .event-body {
            padding: 1.2rem;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 0;
        }

        .event-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .event-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.85rem;
            color: #64748b;
        }

        .event-info i {
            width: 16px;
            color: #6366f1;
        }

        .event-stats {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 0.5rem;
            margin: 0.75rem 0;
        }

        .stat-item {
            text-align: center;
            padding: 0.5rem 0.25rem;
            background: #f8fafc;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
        }

        .stat-number {
            font-size: 1.1rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .stat-label {
            font-size: 0.75rem;
            color: #64748b;
            font-weight: 500;
        }

        .event-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.4rem;
            margin-top: 0.75rem;
            flex-shrink: 0;
        }

        .event-actions .btn {
            flex: 1;
            padding: 0.3rem 0.4rem;
            font-size: 0.7rem;
            text-align: center;
            text-decoration: none;
            border-radius: 6px;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.25rem;
        }

        .event-actions .btn-primary {
            background: #6366f1;
            color: white;
        }

        .event-actions .btn-primary:hover {
            background: #4f46e5;
        }

        .event-actions .btn-secondary {
            background: #64748b;
            color: white;
        }

        .event-actions .btn-secondary:hover {
            background: #475569;
        }

        .event-actions .btn-success {
            background: #10b981;
            color: white;
        }

        .event-actions .btn-success:hover {
            background: #059669;
        }

        .event-actions .btn-danger {
            background: #ef4444;
            color: white;
        }

        .event-actions .btn-danger:hover {
            background: #dc2626;
        }

        .qr-code {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.6rem;
            margin: 0.5rem 0;
            font-size: 0.8rem;
            color: #64748b;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .qr-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            flex: 1;
        }

        .qr-info i {
            color: #6366f1;
            font-size: 1rem;
        }

        .download-qr-btn {
            background: #10b981;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 0.5rem 0.75rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .download-qr-btn:hover {
            background: #059669;
            transform: translateY(-1px);
        }

        .download-qr-btn i {
            font-size: 0.75rem;
        }

        .access-info {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 0.6rem;
            margin: 0.5rem 0;
            font-size: 0.8rem;
            color: #92400e;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .access-info i {
            font-size: 1rem;
            color: #f59e0b;
        }

        /* Pagination */
        .pagination {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a {
            padding: 0.5rem 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            color: #64748b;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .pagination a:hover {
            background: #f1f5f9;
            color: #6366f1;
        }

        .pagination a.active {
            background: #6366f1;
            color: white;
            border-color: #6366f1;
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

            .events-grid {
                grid-template-columns: 1fr;
            }

            .main-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
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
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="events.php" class="nav-link active">
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
                <h1 class="main-title">Düğünler</h1>
                <div class="header-actions">
                    <a href="create-event.php" class="btn btn-success">
                        <i class="fas fa-plus"></i>
                        Yeni Düğün
                    </a>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Geri
                    </a>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Events Grid -->
            <div class="events-grid">
                <?php foreach ($events as $event): ?>
                <div class="event-card">
                    <div class="event-header">
                        <div class="event-creator" style="display: flex; align-items: center; gap: 1rem;">
                            <div class="creator-avatar" style="flex-shrink: 0;">
                                <?php if (!empty($event['kapak_fotografi'])): ?>
                                    <img src="https://dijitalsalon.cagapps.app/<?php echo htmlspecialchars($event['kapak_fotografi']); ?>" 
                                         alt="Düğün Fotoğrafı" class="event-cover">
                                <?php else: ?>
                                    <i class="fas fa-camera"></i>
                                <?php endif; ?>
                            </div>
                            <div class="creator-details" style="flex: 1;">
                                <div class="creator-name">
                                    <?php echo htmlspecialchars($event['baslik'] ?? 'Başlıksız'); ?>
                                </div>
                                <div style="font-size: 0.85rem; color: #1e293b; font-weight: 500; margin-top: 0.25rem;">
                                    <i class="fas fa-user-shield" style="color: #3b82f6;"></i>
                                    Moderator: <?php echo htmlspecialchars(($event['moderator_ad'] ?? '') . ' ' . ($event['moderator_soyad'] ?? '')); ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="event-body">
                        <div class="event-content">
                            <?php if (!empty($event['aciklama'])): ?>
                            <div class="event-info">
                                <i class="fas fa-info-circle"></i>
                                <?php echo htmlspecialchars($event['aciklama']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($event['dugun_tarihi'])): ?>
                            <div class="event-info">
                                <i class="fas fa-calendar"></i>
                                <?php echo date('d.m.Y', strtotime($event['dugun_tarihi'])); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($event['salon_adresi'])): ?>
                            <div class="event-info">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($event['salon_adresi']); ?>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!empty($event['konum'])): ?>
                            <div class="event-info">
                                <i class="fas fa-map-marker-alt"></i>
                                <?php echo htmlspecialchars($event['konum']); ?>
                            </div>
                            <?php endif; ?>

                            <div class="event-stats">
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $event['katilimci_sayisi']; ?></div>
                                    <div class="stat-label">Katılımcı</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $event['medya_sayisi']; ?></div>
                                    <div class="stat-label">Medya</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo $event['hikaye_sayisi']; ?></div>
                                    <div class="stat-label">Hikaye</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number"><?php echo htmlspecialchars($event['paket_ad'] ?? 'Temel'); ?></div>
                                    <div class="stat-label">Paket</div>
                                </div>
                            </div>

                            <div class="qr-code">
                                <div class="qr-info">
                                    <i class="fas fa-qrcode"></i>
                                    QR Kod: <?php echo htmlspecialchars($event['qr_kod']); ?>
                                </div>
                                <button class="download-qr-btn" onclick="downloadQR('<?php echo htmlspecialchars($event['qr_kod']); ?>', '<?php echo htmlspecialchars($event['baslik'] ?? 'Dugun'); ?>')">
                                    <i class="fas fa-download"></i>
                                    PNG İndir
                                </button>
                            </div>

                            <?php if (!empty($event['ucretsiz_erisim_gun'])): ?>
                            <div class="access-info">
                                <i class="fas fa-clock"></i>
                                Ücretsiz Erişim: <?php echo $event['ucretsiz_erisim_gun']; ?> gün
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="event-actions">
                            <a href="event-details.php?id=<?php echo $event['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-eye"></i>
                                Detay
                            </a>
                            <a href="event-participants.php?id=<?php echo $event['id']; ?>" class="btn btn-secondary">
                                <i class="fas fa-users"></i>
                                Katılımcılar
                            </a>
                            <button class="btn btn-success" onclick="downloadEventData(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['baslik'] ?? 'Dugun'); ?>')">
                                <i class="fas fa-download"></i>
                                İndir
                            </button>
                            <button class="btn btn-danger" onclick="deleteEvent(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['baslik'] ?? 'Dugun'); ?>')">
                                <i class="fas fa-trash"></i>
                                Sil
                            </button>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="pagination">
                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <a href="?page=<?php echo $i; ?>" class="<?php echo $i === $page ? 'active' : ''; ?>">
                        <?php echo $i; ?>
                    </a>
                <?php endfor; ?>
            </div>
            <?php endif; ?>
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

        // QR Kod indirme fonksiyonu
        window.downloadQR = function(qrCode, eventName) {
            // QR kod için URL oluştur
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(qrCode)}`;
            
            // Geçici bir link oluştur ve indirme işlemini başlat
            const link = document.createElement('a');
            link.href = qrUrl;
            link.download = `${eventName}_QR_Code.png`;
            link.target = '_blank';
            
            // Link'i DOM'a ekle, tıkla ve kaldır
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        };

        // Düğün verilerini indirme fonksiyonu
        window.downloadEventData = function(eventId, eventName) {
            if (confirm(`${eventName} düğününün tüm verilerini indirmek istediğinizden emin misiniz?`)) {
                // Loading göster
                const button = document.querySelector(`button[onclick*="downloadEventData(${eventId}"]`);
                const originalText = button.innerHTML;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                button.disabled = true;
                
                // AJAX ile indirme işlemini başlat
                fetch('ajax/download-event-data.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `event_id=${eventId}`
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.blob();
                })
                .then(blob => {
                    // Dosyayı indir
                    const url = window.URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = `${eventName}_Dugun_Verileri.zip`;
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    window.URL.revokeObjectURL(url);
                    
                    // Butonu eski haline getir
                    button.innerHTML = originalText;
                    button.disabled = false;
                })
                .catch(error => {
                    console.error('Hata:', error);
                    alert('İndirme işlemi sırasında hata oluştu!');
                    button.innerHTML = originalText;
                    button.disabled = false;
                });
            }
        };

        // Düğün silme fonksiyonu
        window.deleteEvent = function(eventId, eventName) {
            if (confirm(`${eventName} düğününü ve tüm verilerini silmek istediğinizden emin misiniz?\n\nBu işlem geri alınamaz!`)) {
                if (confirm('Son kez onaylıyor musunuz? Tüm medyalar, katılımcılar ve veriler silinecek!')) {
                    // Loading göster
                    const button = document.querySelector(`button[onclick*="deleteEvent(${eventId}"]`);
                    const originalText = button.innerHTML;
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    button.disabled = true;
                    
                    // AJAX ile silme işlemini başlat
                    console.log('Silme isteği gönderiliyor:', eventId);
                    
                    fetch('ajax/delete-event.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `event_id=${eventId}`
                    })
                    .then(response => {
                        console.log('Response status:', response.status);
                        console.log('Response headers:', response.headers);
                        return response.text(); // Önce text olarak al
                    })
                    .then(text => {
                        console.log('Raw response:', text);
                        try {
                            const data = JSON.parse(text);
                            if (data.success) {
                                console.log('Silinen dosyalar:', data.deleted_files);
                                alert('Düğün başarıyla silindi!\n\nSilinen dosyalar: ' + (data.deleted_files ? data.deleted_files.length : 0));
                                location.reload();
                            } else {
                                alert('Hata: ' + data.message);
                                button.innerHTML = originalText;
                                button.disabled = false;
                            }
                        } catch (e) {
                            console.error('JSON parse hatası:', e);
                            console.error('Response text:', text);
                            alert('Sunucu hatası: ' + text.substring(0, 200));
                            button.innerHTML = originalText;
                            button.disabled = false;
                        }
                    })
                    .catch(error => {
                        console.error('Hata:', error);
                        alert('Silme işlemi sırasında hata oluştu!');
                        button.innerHTML = originalText;
                        button.disabled = false;
                    });
                }
            }
        };
    </script>
</body>
</html>
