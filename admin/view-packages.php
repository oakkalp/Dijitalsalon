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

// Paketleri al
try {
    $stmt = $pdo->query("SELECT * FROM paketler ORDER BY fiyat ASC");
    $paketler = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = 'Paketler yüklenirken hata oluştu: ' . $e->getMessage();
    $paketler = [];
}

// Paket özelliklerini decode et
function decode_features($features_json) {
    $features = json_decode($features_json, true);
    if (is_array($features)) {
        return array_map(function($f) {
            return html_entity_decode($f, ENT_QUOTES, 'UTF-8');
        }, $features);
    }
    return [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paketler - Dijitalsalon Admin</title>
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
            margin-bottom: 2rem;
        }

        .main-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .subtitle {
            color: #64748b;
            font-size: 1rem;
        }

        .paket-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 2rem;
            margin-top: 2rem;
        }

        .paket-card {
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            padding: 2rem;
            transition: all 0.3s ease;
            position: relative;
        }

        .paket-card:hover {
            border-color: #6366f1;
            box-shadow: 0 8px 24px rgba(99, 102, 241, 0.15);
            transform: translateY(-4px);
        }

        .paket-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #e2e8f0;
        }

        .paket-adi {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
        }

        .paket-fiyat {
            font-size: 2rem;
            font-weight: 700;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .paket-aciklama {
            color: #64748b;
            font-size: 1rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .paket-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .detail-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem;
            background: #f8fafc;
            border-radius: 10px;
        }

        .detail-item i {
            color: #3b82f6;
            font-size: 1.25rem;
        }

        .detail-label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }

        .detail-value {
            font-size: 1rem;
            color: #1e293b;
            font-weight: 600;
        }

        .features-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .features-list {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .features-list li {
            padding: 0.75rem;
            background: #f1f5f9;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #475569;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .features-list li i {
            color: #22c55e;
            font-size: 1rem;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .paket-grid {
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
                <h1 class="main-title">
                    <i class="fas fa-box"></i>
                    Paketler
                </h1>
                <p class="subtitle">Düğün paketleri ve özellikleri</p>
            </div>

            <?php if (!empty($paketler)): ?>
                <div class="paket-grid">
                    <?php foreach ($paketler as $paket): ?>
                        <?php 
                        $features = decode_features($paket['ozellikler']);
                        // İstenmeyen özellikleri filtrele
                        $excludeFeatures = ['Sınırsız Depolama', 'Özel Tasarım'];
                        $filteredFeatures = array_filter($features, function($feature) use ($excludeFeatures) {
                            return !in_array($feature, $excludeFeatures);
                        });
                        ?>
                        <div class="paket-card">
                            <div class="paket-header">
                                <div class="paket-adi"><?php echo htmlspecialchars($paket['ad']); ?></div>
                                <div class="paket-fiyat">₺<?php echo number_format($paket['fiyat'], 2, ',', '.'); ?></div>
                            </div>

                            <?php if (!empty($paket['aciklama'])): ?>
                                <div class="paket-aciklama">
                                    <?php echo htmlspecialchars($paket['aciklama']); ?>
                                </div>
                            <?php endif; ?>

                            <div class="paket-details">
                                <div class="detail-item">
                                    <i class="fas fa-clock"></i>
                                    <div>
                                        <div class="detail-label">Süre</div>
                                        <div class="detail-value"><?php echo $paket['sure_ay']; ?> Ay</div>
                                    </div>
                                </div>

                                <div class="detail-item">
                                    <i class="fas fa-users"></i>
                                    <div>
                                        <div class="detail-label">Max Katılımcı</div>
                                        <div class="detail-value"><?php echo number_format($paket['maksimum_katilimci']); ?></div>
                                    </div>
                                </div>

                                <div class="detail-item">
                                    <i class="fas fa-image"></i>
                                    <div>
                                        <div class="detail-label">Medya Limiti</div>
                                        <div class="detail-value"><?php echo number_format($paket['medya_limiti']); ?></div>
                                    </div>
                                </div>

                                <div class="detail-item">
                                    <i class="fas fa-unlock"></i>
                                    <div>
                                        <div class="detail-label">Ücretsiz Erişim</div>
                                        <div class="detail-value"><?php echo $paket['ucretsiz_erisim_gun']; ?> Gün</div>
                                    </div>
                                </div>
                            </div>

                            <?php if (!empty($filteredFeatures)): ?>
                                <div class="features-title">
                                    <i class="fas fa-star"></i>
                                    Özellikler:
                                </div>
                                <ul class="features-list">
                                    <?php foreach ($filteredFeatures as $feature): ?>
                                        <li>
                                            <i class="fas fa-check-circle"></i>
                                            <?php echo htmlspecialchars($feature); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-box-open"></i>
                    <h3>Henüz paket bulunamadı</h3>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>


