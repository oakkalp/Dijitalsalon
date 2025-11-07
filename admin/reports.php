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

// İstatistikleri al
try {
    if ($admin_user_role === 'super_admin') {
        // Super Admin - Tüm sistem istatistikleri
        $stmt = $pdo->query("SELECT COUNT(*) FROM kullanicilar");
        $stats['toplam_kullanici'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM dugunler");
        $stats['toplam_dugun'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM medyalar");
        $stats['toplam_medya'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM dugun_katilimcilar");
        $stats['toplam_katilimci'] = $stmt->fetchColumn();
        
        $stmt = $pdo->query("SELECT COUNT(*) FROM kullanicilar WHERE rol = 'moderator'");
        $stats['aktif_moderator'] = $stmt->fetchColumn();
        
        // Son 7 gün için günlük istatistikler
        $daily_stats = $pdo->query("
            SELECT 
                DATE(created_at) as tarih,
                COUNT(*) as sayi
            FROM kullanicilar
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY DATE(created_at)
            ORDER BY tarih ASC
        ")->fetchAll();
        
        // En çok medya yükleyen kullanıcılar
        $top_uploaders = $pdo->query("
            SELECT 
                k.ad,
                k.soyad,
                COUNT(m.id) as medya_sayisi
            FROM kullanicilar k
            LEFT JOIN medyalar m ON k.id = m.kullanici_id
            GROUP BY k.id
            ORDER BY medya_sayisi DESC
            LIMIT 5
        ")->fetchAll();
        
    } else {
        // Moderator - Sadece kendi istatistikleri
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dugunler WHERE moderator_id = ?");
        $stmt->execute([$admin_user_id]);
        $stats['toplam_dugun'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM medyalar m
            LEFT JOIN dugunler d ON m.dugun_id = d.id
            WHERE d.moderator_id = ?
        ");
        $stmt->execute([$admin_user_id]);
        $stats['toplam_medya'] = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM dugun_katilimcilar dk
            LEFT JOIN dugunler d ON dk.dugun_id = d.id
            WHERE d.moderator_id = ?
        ");
        $stmt->execute([$admin_user_id]);
        $stats['toplam_katilimci'] = $stmt->fetchColumn();
        
        $stats['toplam_kullanici'] = 0;
        $stats['aktif_moderator'] = 1;
        
        $daily_stats = [];
        $top_uploaders = [];
    }
    
} catch (Exception $e) {
    $error_message = 'İstatistikler yüklenirken hata oluştu: ' . $e->getMessage();
    $stats = [];
}

$success_message = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar - Dijitalsalon Admin</title>
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
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
        }

        .chart-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .chart-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
        }

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
            }

            .stats-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="main-header">
                <h1 class="main-title">Raporlar ve İstatistikler</h1>
            </div>

            <?php if (isset($error_message) && $error_message): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($stats)): ?>
            <div class="stats-grid">
                <?php if ($admin_user_role === 'super_admin'): ?>
                <div class="stat-card">
                    <div class="stat-label">
                        <i class="fas fa-users"></i>
                        Toplam Kullanıcı
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['toplam_kullanici']); ?></div>
                </div>
                <?php endif; ?>

                <div class="stat-card">
                    <div class="stat-label">
                        <i class="fas fa-calendar-alt"></i>
                        Toplam Düğün
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['toplam_dugun']); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">
                        <i class="fas fa-images"></i>
                        Toplam Medya
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['toplam_medya']); ?></div>
                </div>

                <div class="stat-card">
                    <div class="stat-label">
                        <i class="fas fa-user-check"></i>
                        Toplam Katılımcı
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['toplam_katilimci']); ?></div>
                </div>

                <?php if ($admin_user_role === 'super_admin'): ?>
                <div class="stat-card">
                    <div class="stat-label">
                        <i class="fas fa-user-shield"></i>
                        Aktif Moderator
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['aktif_moderator']); ?></div>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if ($admin_user_role === 'super_admin' && !empty($top_uploaders)): ?>
            <div class="chart-card">
                <h2 class="chart-title">
                    <i class="fas fa-trophy"></i>
                    En Çok Medya Yükleyen Kullanıcılar
                </h2>
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #e2e8f0;">
                            <th style="padding: 0.75rem; text-align: left;">Sıra</th>
                            <th style="padding: 0.75rem; text-align: left;">Kullanıcı</th>
                            <th style="padding: 0.75rem; text-align: left;">Medya Sayısı</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_uploaders as $index => $user): ?>
                        <tr style="border-bottom: 1px solid #e2e8f0;">
                            <td style="padding: 0.75rem;"><?php echo $index + 1; ?></td>
                            <td style="padding: 0.75rem;">
                                <?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?>
                            </td>
                            <td style="padding: 0.75rem;">
                                <?php echo number_format($user['medya_sayisi']); ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

