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

// Sadece super_admin erişebilir
if ($admin_user_role !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

// Sistem bilgilerini al
try {
    // Aktif kullanıcı sayısı (son 5 dakika içinde aktivite)
    $stmt = $pdo->query("
        SELECT COUNT(DISTINCT user_id) 
        FROM user_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    ");
    $aktif_kullanicilar = $stmt->fetchColumn() ?: 0;

    // Toplam kullanıcı
    $stmt = $pdo->query("SELECT COUNT(*) FROM kullanicilar");
    $toplam_kullanicilar = $stmt->fetchColumn();

    // Toplam düğün
    $stmt = $pdo->query("SELECT COUNT(*) FROM dugunler");
    $toplam_dugun = $stmt->fetchColumn();

    // Toplam medya
    $stmt = $pdo->query("SELECT COUNT(*) FROM medyalar");
    $toplam_medya = $stmt->fetchColumn();

    // Bugünkü yeni kullanıcılar
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM kullanicilar 
        WHERE DATE(created_at) = CURDATE()
    ");
    $bugun_yeni_kullanici = $stmt->fetchColumn();

    // Bugünkü yeni düğünler
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM dugunler 
        WHERE DATE(created_at) = CURDATE()
    ");
    $bugun_yeni_dugun = $stmt->fetchColumn();

    // Bugünkü yeni medya
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM medyalar 
        WHERE DATE(created_at) = CURDATE()
    ");
    $bugun_yeni_medya = $stmt->fetchColumn();

    // Son 24 saatteki aktivite
    $stmt = $pdo->query("
        SELECT COUNT(*) FROM user_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $son_24_saat_aktivite = $stmt->fetchColumn();

    // En aktif kullanıcılar (son 24 saat)
    $stmt = $pdo->query("
        SELECT ul.user_id, u.ad, u.soyad, COUNT(*) as activity_count
        FROM user_logs ul
        LEFT JOIN kullanicilar u ON ul.user_id = u.id
        WHERE ul.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        GROUP BY ul.user_id
        ORDER BY activity_count DESC
        LIMIT 10
    ");
    $aktif_kullanicilar_list = $stmt->fetchAll();

    // Son aktiviteler - detaylı
    $stmt = $pdo->query("
        SELECT ul.*, u.ad, u.soyad, u.email, u.profil_fotografi
        FROM user_logs ul
        LEFT JOIN kullanicilar u ON ul.user_id = u.id
        ORDER BY ul.created_at DESC
        LIMIT 50
    ");
    $son_aktiviteler = $stmt->fetchAll();
    
    // Her aktivite için detayları parse et
    foreach ($son_aktiviteler as &$activity) {
        $activity['details_parsed'] = json_decode($activity['details'], true) ?? [];
    }

    // Sistem uyarıları ve saldırı tespiti
    $sistem_uyarilari = [];
    
    // 1. Şüpheli aktivite kontrolü (son 1 saatte 100+ işlem)
    $stmt = $pdo->query("
        SELECT user_id, COUNT(*) as count
        FROM user_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY user_id
        HAVING count > 100
    ");
    $supheli_aktiviteler = $stmt->fetchAll();
    
    foreach ($supheli_aktiviteler as $activity) {
        // Kullanıcı bilgisini al
        $stmt = $pdo->prepare("SELECT ad, soyad FROM kullanicilar WHERE id = ?");
        $stmt->execute([$activity['user_id']]);
        $kullanici = $stmt->fetch();
        
        $sistem_uyarilari[] = [
            'type' => 'danger',
            'severity' => 'high',
            'message' => "⚠️ ŞÜPHELİ AKTİVİTE: " . ($kullanici ? $kullanici['ad'] . ' ' . $kullanici['soyad'] : "Kullanıcı ID: {$activity['user_id']}") . " son 1 saatte {$activity['count']} işlem yaptı (DOS/DDOS saldırısı olabilir!)",
            'time' => 'Son 1 saat'
        ];
    }
    
    // 2. Şüpheli IP adresleri kontrolü
    $stmt = $pdo->query("
        SELECT ip_address, COUNT(*) as count, COUNT(DISTINCT user_id) as user_count
        FROM user_logs 
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND ip_address IS NOT NULL
        GROUP BY ip_address
        HAVING count > 50
    ");
    $supheli_ips = $stmt->fetchAll();
    
    foreach ($supheli_ips as $ip) {
        if ($ip['user_count'] > 10) {
            $sistem_uyarilari[] = [
                'type' => 'danger',
                'severity' => 'high',
                'message' => "🚨 SALDIRI TESPİT EDİLDİ: IP adresi '{$ip['ip_address']}' son 1 saatte {$ip['count']} istek yaptı ve {$ip['user_count']} farklı hesap kullandı (Hacker saldırısı!)",
                'time' => 'Son 1 saat'
            ];
        }
    }
    
    // 3. Başarısız login denemeleri tespiti
    $stmt = $pdo->query("
        SELECT ip_address, COUNT(*) as count
        FROM user_logs 
        WHERE action = 'login_failed'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY ip_address
        HAVING count > 10
    ");
    $basarisiz_loginler = $stmt->fetchAll();
    
    foreach ($basarisiz_loginler as $login) {
        $sistem_uyarilari[] = [
            'type' => 'warning',
            'severity' => 'medium',
            'message' => "🔓 BRUTE FORCE SALDIRISI: IP '{$login['ip_address']}' son 1 saatte {$login['count']} kez başarısız login denemesi yaptı (Brute Force Attack!)",
            'time' => 'Son 1 saat'
        ];
    }
    
    // 4. Aynı IP'den farklı hesaplara giriş tespiti
    $stmt = $pdo->query("
        SELECT ip_address, COUNT(DISTINCT user_id) as hesap_sayisi
        FROM user_logs 
        WHERE action = 'login'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        AND ip_address IS NOT NULL
        GROUP BY ip_address
        HAVING hesap_sayisi > 5
    ");
    $coklu_girisler = $stmt->fetchAll();
    
    foreach ($coklu_girisler as $giris) {
        $sistem_uyarilari[] = [
            'type' => 'warning',
            'severity' => 'medium',
            'message' => "🤖 ŞÜPHELİ GİRİŞ: IP '{$giris['ip_address']}' son 1 saatte {$giris['hesap_sayisi']} farklı hesaba giriş yaptı (Hesap ele geçirme saldırısı olabilir!)",
            'time' => 'Son 1 saat'
        ];
    }
    
    // 5. Toplam saldırı sayısı
    $toplam_saldiri = count($sistem_uyarilari);

    // Server bilgileri - try bloğunun dışında tanımla
    $sistem_bilgileri = [
        'php_version' => PHP_VERSION,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Bilinmiyor',
        'server_os' => PHP_OS,
        'memory_limit' => ini_get('memory_limit'),
        'max_upload_size' => ini_get('upload_max_filesize'),
        'max_execution_time' => ini_get('max_execution_time'),
        'disk_free_space' => disk_free_space('.'),
        'disk_total_space' => disk_total_space('.'),
    ];
    
    // Aktivite değişkenlerini de try bloğunun dışında tanımla
    if (!isset($aktif_kullanicilar)) $aktif_kullanicilar = 0;
    if (!isset($toplam_kullanicilar)) $toplam_kullanicilar = 0;
    if (!isset($toplam_dugun)) $toplam_dugun = 0;
    if (!isset($toplam_medya)) $toplam_medya = 0;
    if (!isset($bugun_yeni_kullanici)) $bugun_yeni_kullanici = 0;
    if (!isset($bugun_yeni_dugun)) $bugun_yeni_dugun = 0;
    if (!isset($bugun_yeni_medya)) $bugun_yeni_medya = 0;
    if (!isset($aktif_kullanicilar_list)) $aktif_kullanicilar_list = [];
    if (!isset($son_aktiviteler)) $son_aktiviteler = [];
    if (!isset($toplam_saldiri)) $toplam_saldiri = 0;
    if (!isset($sistem_uyarilari)) $sistem_uyarilari = [];

} catch (Exception $e) {
    $e = $e; // Store exception for display
    $error_message = 'Sistem bilgileri alınırken hata oluştu: ' . $e->getMessage();
    error_log("System.php error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Varsayılan değerler
    $aktif_kullanicilar = 0;
    $toplam_kullanicilar = 0;
    $toplam_dugun = 0;
    $toplam_medya = 0;
    $bugun_yeni_kullanici = 0;
    $bugun_yeni_dugun = 0;
    $bugun_yeni_medya = 0;
    $son_24_saat_aktivite = 0;
    $aktif_kullanicilar_list = [];
    $son_aktiviteler = [];
    $sistem_uyarilari = [];
    $sistem_bilgileri = [
        'php_version' => 'Bilinmiyor',
        'server_software' => 'Bilinmiyor',
        'server_os' => 'Bilinmiyor',
        'memory_limit' => 'Bilinmiyor',
        'max_upload_size' => 'Bilinmiyor',
        'max_execution_time' => 'Bilinmiyor',
        'disk_free_space' => 0,
        'disk_total_space' => 0,
    ];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistem Konsolu - Dijitalsalon Admin</title>
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
            margin-bottom: 2rem;
        }

        .main-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            background: #22c55e;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
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
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-left: 4px solid;
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card.primary { border-left-color: #3b82f6; }
        .stat-card.success { border-left-color: #22c55e; }
        .stat-card.warning { border-left-color: #f59e0b; }
        .stat-card.danger { border-left-color: #ef4444; }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .stat-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-icon {
            font-size: 1.5rem;
            opacity: 0.3;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
        }

        .stat-change {
            font-size: 0.85rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stat-change.positive { color: #22c55e; }
        .stat-change.negative { color: #ef4444; }

        /* System Alerts */
        .alerts-container {
            margin-bottom: 2rem;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .alert.warning {
            background: #fef3c7;
            color: #d97706;
            border: 1px solid #f59e0b;
        }

        .alert.danger {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #dc2626;
        }

        .alert.success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #22c55e;
        }

        .alert.danger {
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .severity-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 0.5rem;
        }

        .severity-high {
            background: #ef4444;
            color: white;
        }

        .severity-medium {
            background: #f59e0b;
            color: white;
        }

        /* System Info */
        .system-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: white;
            padding: 1.5rem;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .info-card h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #64748b;
            font-size: 0.9rem;
        }

        .info-value {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.9rem;
        }

        /* Activity Log */
        .activity-log {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .activity-header {
            padding: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .activity-header h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
        }

        .activity-list {
            max-height: 600px;
            overflow-y: auto;
        }

        .activity-item {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #f1f5f9;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: background 0.3s ease;
        }

        .activity-item:hover {
            background: #f8fafc;
        }

        .activity-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.9rem;
        }

        .activity-content {
            flex: 1;
        }

        .activity-user {
            font-weight: 600;
            color: #1e293b;
        }

        .activity-action {
            color: #64748b;
            font-size: 0.9rem;
        }

        .activity-time {
            font-size: 0.8rem;
            color: #94a3b8;
        }

        .btn-refresh {
            padding: 0.5rem 1rem;
            background: #f1f5f9;
            border: none;
            border-radius: 6px;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .btn-refresh:hover {
            background: #e2e8f0;
            color: #6366f1;
        }

        .top-users {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .top-users h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .user-rank {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .user-rank:last-child {
            border-bottom: none;
        }

        .rank-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .rank-number {
            width: 32px;
            height: 32px;
            background: #f1f5f9;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: #64748b;
        }

        .rank-number.top {
            background: linear-gradient(135deg, #fbbf24, #f59e0b);
            color: white;
        }

        .rank-name {
            font-weight: 600;
            color: #1e293b;
        }

        .rank-count {
            color: #64748b;
            font-size: 0.85rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="main-header">
                <h1 class="main-title">
                    <span class="status-indicator"></span>
                    Sistem Konsolu
                </h1>
            </div>

            <!-- System Alerts & Attack Detection -->
            <?php if (!empty($sistem_uyarilari)): ?>
                <div class="alerts-container">
                    <h3 style="margin-bottom: 1rem; color: #ef4444; font-weight: 700;">
                        <i class="fas fa-shield-alt"></i> Güvenlik Uyarıları (<?php echo $toplam_saldiri; ?> Tespit Edildi)
                    </h3>
                    <?php foreach ($sistem_uyarilari as $alert): ?>
                        <div class="alert <?php echo $alert['type']; ?>">
                            <i class="fas fa-exclamation-triangle"></i>
                            <?php echo htmlspecialchars($alert['message']); ?>
                            <?php if (isset($alert['severity'])): ?>
                                <span class="severity-badge severity-<?php echo $alert['severity']; ?>">
                                    <?php echo $alert['severity'] === 'high' ? 'YÜKSEK' : 'ORTA'; ?>
                                </span>
                            <?php endif; ?>
                            <small style="display: block; margin-top: 0.25rem; opacity: 0.7;">
                                <?php echo $alert['time']; ?>
                            </small>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="alert success">
                    <i class="fas fa-check-circle"></i>
                    Herhangi bir güvenlik uyarısı bulunamadı. Sistem güvenli görünüyor.
                </div>
            <?php endif; ?>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card primary">
                    <div class="stat-header">
                        <span class="stat-label">Aktif Kullanıcılar</span>
                        <i class="fas fa-users stat-icon" style="color: #3b82f6;"></i>
                    </div>
                    <div class="stat-value"><?php echo $aktif_kullanicilar; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-clock"></i>
                        Son 5 dakika
                    </div>
                </div>

                <div class="stat-card success">
                    <div class="stat-header">
                        <span class="stat-label">Toplam Kullanıcı</span>
                        <i class="fas fa-user-friends stat-icon" style="color: #22c55e;"></i>
                    </div>
                    <div class="stat-value"><?php echo $toplam_kullanicilar; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        +<?php echo $bugun_yeni_kullanici; ?> bugün
                    </div>
                </div>

                <div class="stat-card warning">
                    <div class="stat-header">
                        <span class="stat-label">Toplam Düğün</span>
                        <i class="fas fa-calendar stat-icon" style="color: #f59e0b;"></i>
                    </div>
                    <div class="stat-value"><?php echo $toplam_dugun; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        +<?php echo $bugun_yeni_dugun; ?> bugün
                    </div>
                </div>

                <div class="stat-card danger">
                    <div class="stat-header">
                        <span class="stat-label">Toplam Medya</span>
                        <i class="fas fa-photo-video stat-icon" style="color: #ef4444;"></i>
                    </div>
                    <div class="stat-value"><?php echo $toplam_medya; ?></div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        +<?php echo $bugun_yeni_medya; ?> bugün
                    </div>
                </div>
            </div>

            <!-- System Info & Top Users -->
            <div class="system-info-grid">
                <div class="info-card">
                    <h3><i class="fas fa-server"></i> Server Bilgileri</h3>
                    <div class="info-row">
                        <span class="info-label">PHP Version</span>
                        <span class="info-value"><?php echo $sistem_bilgileri['php_version']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Server OS</span>
                        <span class="info-value"><?php echo $sistem_bilgileri['server_os']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Memory Limit</span>
                        <span class="info-value"><?php echo $sistem_bilgileri['memory_limit']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Max Upload</span>
                        <span class="info-value"><?php echo $sistem_bilgileri['max_upload_size']; ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Max Execution Time</span>
                        <span class="info-value"><?php echo $sistem_bilgileri['max_execution_time']; ?>s</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Disk Free</span>
                        <span class="info-value"><?php echo round($sistem_bilgileri['disk_free_space'] / 1024 / 1024 / 1024, 2); ?> GB</span>
                    </div>
                </div>

                <?php if (!empty($aktif_kullanicilar_list)): ?>
                    <div class="top-users">
                        <h3><i class="fas fa-fire"></i> En Aktif Kullanıcılar</h3>
                        <?php foreach ($aktif_kullanicilar_list as $index => $user): ?>
                            <div class="user-rank">
                                <div class="rank-info">
                                    <div class="rank-number <?php echo $index < 3 ? 'top' : ''; ?>">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div>
                                        <div class="rank-name"><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></div>
                                        <div class="rank-count"><?php echo $user['activity_count']; ?> işlem</div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Activity Log -->
            <div class="activity-log">
                <div class="activity-header">
                    <h3><i class="fas fa-history"></i> Son Aktiviteler (Anlık)</h3>
                    <button class="btn-refresh" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i> Yenile
                    </button>
                </div>
                <div class="activity-list">
                    <?php if (!empty($son_aktiviteler)): ?>
                        <?php foreach ($son_aktiviteler as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php
                                    $icon_map = [
                                        'login' => 'fa-sign-in-alt',
                                        'logout' => 'fa-sign-out-alt',
                                        'register' => 'fa-user-plus',
                                        'media_upload' => 'fa-upload',
                                        'story_upload' => 'fa-images',
                                        'comment_add' => 'fa-comment',
                                        'like' => 'fa-heart',
                                        'unlike' => 'fa-heart-broken',
                                        'profile_update' => 'fa-user-edit',
                                        'event_join' => 'fa-calendar-check'
                                    ];
                                    $icon = $icon_map[$activity['action']] ?? 'fa-circle';
                                    ?>
                                    <i class="fas <?php echo $icon; ?>"></i>
                                </div>
                                <div class="activity-content">
                                    <div class="activity-user">
                                        <?php echo htmlspecialchars($activity['ad'] . ' ' . $activity['soyad']); ?>
                                    </div>
                                    <div class="activity-action">
                                        <?php 
                                        $action_text = [
                                            'login' => 'Giriş Yaptı',
                                            'register' => 'Kayıt Oldu',
                                            'logout' => 'Çıkış Yaptı',
                                            'media_upload' => 'Medya Paylaştı',
                                            'story_upload' => 'Hikaye Ekledi',
                                            'comment_add' => 'Yorum Yaptı',
                                            'like' => 'Beğendi',
                                            'unlike' => 'Beğeniyi Geri Aldı',
                                            'profile_update' => 'Profil Güncelledi',
                                            'event_join' => 'Etkinliğe Katıldı'
                                        ];
                                        echo $action_text[$activity['action']] ?? $activity['action'];
                                        ?>
                                        <?php if (!empty($activity['details_parsed'])): ?>
                                            <span style="color: #64748b; font-size: 0.85rem;">
                                                <?php
                                                $details = $activity['details_parsed'];
                                                if (isset($details['media_type'])) {
                                                    echo " (" . $details['media_type'] . ")";
                                                }
                                                if (isset($details['event_id'])) {
                                                    echo " (Event ID: " . $details['event_id'] . ")";
                                                }
                                                ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="activity-time">
                                        <?php echo date('d.m.Y H:i:s', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="activity-item" style="justify-content: center; color: #64748b;">
                            Henüz aktivite kaydı yok.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Auto refresh every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>

