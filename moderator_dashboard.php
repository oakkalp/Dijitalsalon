<?php
session_start();

// Eğer kullanıcı giriş yapmamışsa login'e yönlendir
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Sadece moderator erişebilir
if ($_SESSION['user_role'] !== 'moderator') {
    header('Location: dashboard.php');
    exit;
}

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=localhost;dbname=digitalsalon_db;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Veritabanı bağlantı hatası: ' . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// Moderator istatistikleri
$stats = [];

// Toplam düğün sayısı
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM dugunler WHERE moderator_id = ?");
$stmt->execute([$user_id]);
$stats['total_events'] = $stmt->fetch()['total'];

// Aktif düğün sayısı
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM dugunler WHERE moderator_id = ? AND durum = 'aktif'");
$stmt->execute([$user_id]);
$stats['active_events'] = $stmt->fetch()['total'];

// Toplam katılımcı sayısı
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT dk.kullanici_id) as total 
    FROM dugun_katilimcilar dk 
    JOIN dugunler d ON dk.dugun_id = d.id 
    WHERE d.moderator_id = ?
");
$stmt->execute([$user_id]);
$stats['total_participants'] = $stmt->fetch()['total'];

// Toplam medya sayısı
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM medyalar m 
    JOIN dugunler d ON m.dugun_id = d.id 
    WHERE d.moderator_id = ?
");
$stmt->execute([$user_id]);
$stats['total_media'] = $stmt->fetch()['total'];

// Toplam satış tutarı
$stmt = $pdo->prepare("SELECT SUM(paket_fiyati) as total FROM dugunler WHERE moderator_id = ? AND odeme_durumu = 'odendi'");
$stmt->execute([$user_id]);
$stats['total_sales'] = $stmt->fetch()['total'] ?? 0;

// Toplam komisyon tutarı
$stmt = $pdo->prepare("SELECT SUM(komisyon_tutari) as total FROM komisyon_gecmisi WHERE moderator_id = ? AND durum = 'odendi'");
$stmt->execute([$user_id]);
$stats['total_commissions'] = $stmt->fetch()['total'] ?? 0;

// Bu ay satışlar
$stmt = $pdo->prepare("
    SELECT SUM(paket_fiyati) as total 
    FROM dugunler 
    WHERE moderator_id = ? AND odeme_durumu = 'odendi' 
    AND MONTH(odeme_tarihi) = MONTH(CURRENT_DATE()) 
    AND YEAR(odeme_tarihi) = YEAR(CURRENT_DATE())
");
$stmt->execute([$user_id]);
$stats['monthly_sales'] = $stmt->fetch()['total'] ?? 0;

// Bu ay komisyonlar
$stmt = $pdo->prepare("
    SELECT SUM(komisyon_tutari) as total 
    FROM komisyon_gecmisi 
    WHERE moderator_id = ? AND durum = 'odendi' 
    AND MONTH(odeme_tarihi) = MONTH(CURRENT_DATE()) 
    AND YEAR(odeme_tarihi) = YEAR(CURRENT_DATE())
");
$stmt->execute([$user_id]);
$stats['monthly_commissions'] = $stmt->fetch()['total'] ?? 0;

// Bekleyen komisyonlar
$stmt = $pdo->prepare("SELECT SUM(komisyon_tutari) as total FROM komisyon_gecmisi WHERE moderator_id = ? AND durum = 'beklemede'");
$stmt->execute([$user_id]);
$stats['pending_commissions'] = $stmt->fetch()['total'] ?? 0;

// Moderator'un düğünleri
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        p.ad as paket_ad,
        p.fiyat as paket_fiyati,
        COUNT(DISTINCT dk.kullanici_id) as katilimci_sayisi,
        COUNT(DISTINCT m.id) as medya_sayisi
    FROM dugunler d
    LEFT JOIN paketler p ON d.paket_id = p.id
    LEFT JOIN dugun_katilimcilar dk ON d.id = dk.dugun_id
    LEFT JOIN medyalar m ON d.id = m.dugun_id
    WHERE d.moderator_id = ?
    GROUP BY d.id
    ORDER BY d.olusturma_tarihi DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$my_events = $stmt->fetchAll();

// Komisyon geçmişi
$stmt = $pdo->prepare("
    SELECT 
        kg.*,
        d.baslik as dugun_baslik,
        p.ad as paket_ad
    FROM komisyon_gecmisi kg
    JOIN dugunler d ON kg.dugun_id = d.id
    JOIN paketler p ON kg.paket_id = p.id
    WHERE kg.moderator_id = ?
    ORDER BY kg.olusturma_tarihi DESC
    LIMIT 20
");
$stmt->execute([$user_id]);
$commission_history = $stmt->fetchAll();

// Aylık satış grafiği için veri
$stmt = $pdo->prepare("
    SELECT 
        MONTH(odeme_tarihi) as ay,
        SUM(paket_fiyati) as satis_tutari
    FROM dugunler 
    WHERE moderator_id = ? AND odeme_durumu = 'odendi' 
    AND YEAR(odeme_tarihi) = YEAR(CURRENT_DATE())
    GROUP BY MONTH(odeme_tarihi)
    ORDER BY ay
");
$stmt->execute([$user_id]);
$monthly_sales_data = $stmt->fetchAll();

// Paketler
$stmt = $pdo->query("SELECT * FROM paketler WHERE durum = 'aktif' ORDER BY fiyat ASC");
$packages = $stmt->fetchAll();

// Yeni düğün oluşturma
if ($_POST['action'] ?? '' === 'create_event') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $event_date = $_POST['event_date'];
    $end_date = $_POST['end_date'];
    $package_id = (int)$_POST['package_id'];
    
    if ($title && $event_date && $end_date && $package_id) {
        try {
            $pdo->beginTransaction();
            
            // Paket bilgilerini al
            $stmt = $pdo->prepare("SELECT * FROM paketler WHERE id = ?");
            $stmt->execute([$package_id]);
            $package = $stmt->fetch();
            
            if ($package) {
                // Unique QR kod oluştur
                $qr_code = 'QR_' . bin2hex(random_bytes(16));
                
                // Düğünü oluştur
                $stmt = $pdo->prepare("
                    INSERT INTO dugunler (baslik, aciklama, dugun_tarihi, bitis_tarihi, moderator_id, qr_kod, paket_id, paket_fiyati, odeme_durumu) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'odendi')
                ");
                $stmt->execute([$title, $description, $event_date, $end_date, $user_id, $qr_code, $package_id, $package['fiyat']]);
                $event_id = $pdo->lastInsertId();
                
                // Komisyon kaydı oluştur
                $commission_amount = ($package['fiyat'] * $package['komisyon_orani']) / 100;
                $stmt = $pdo->prepare("
                    INSERT INTO komisyon_gecmisi (moderator_id, dugun_id, paket_id, satis_tutari, komisyon_orani, komisyon_tutari, durum) 
                    VALUES (?, ?, ?, ?, ?, ?, 'odendi')
                ");
                $stmt->execute([$user_id, $event_id, $package_id, $package['fiyat'], $package['komisyon_orani'], $commission_amount]);
                
                // Moderator'u düğüne katılımcı olarak ekle
                $stmt = $pdo->prepare("
                    INSERT INTO dugun_katilimcilar (dugun_id, kullanici_id, rol, medya_silebilir, yorum_silebilir, kullanici_engelleyebilir, hikaye_paylasabilir, profil_degistirebilir) 
                    VALUES (?, ?, 'moderator', 1, 1, 1, 1, 1)
                ");
                $stmt->execute([$event_id, $user_id]);
                
                // Moderator'un toplam satışını güncelle
                $stmt = $pdo->prepare("UPDATE kullanicilar SET toplam_satis = toplam_satis + ? WHERE id = ?");
                $stmt->execute([$package['fiyat'], $user_id]);
                
                $pdo->commit();
                
                header('Location: moderator_dashboard.php?success=event_created&event_id=' . $event_id);
                exit;
                
            } else {
                throw new Exception('Paket bulunamadı');
            }
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Düğün oluşturulurken hata oluştu: ' . $e->getMessage();
        }
    } else {
        $error = 'Lütfen tüm alanları doldurun';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator Dashboard - Digital Salon</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-ui.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .dashboard-header {
            background: var(--secondary-gradient);
            color: white;
            padding: var(--spacing-xl) 0;
            margin-bottom: var(--spacing-xl);
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            border-left: 4px solid var(--secondary);
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: linear-gradient(45deg, rgba(240, 147, 251, 0.1), rgba(245, 87, 108, 0.1));
            border-radius: 50%;
            transform: translate(30px, -30px);
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }
        
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.danger { border-left-color: var(--danger); }
        .stat-card.info { border-left-color: var(--info); }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: var(--spacing-md);
        }
        
        .stat-icon.secondary { background: var(--secondary-gradient); }
        .stat-icon.success { background: var(--success-gradient); }
        .stat-icon.warning { background: var(--warning-gradient); }
        .stat-icon.danger { background: var(--danger-gradient); }
        .stat-icon.info { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
        
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .stat-change {
            font-size: 0.75rem;
            margin-top: var(--spacing-sm);
            display: flex;
            align-items: center;
            gap: var(--spacing-xs);
        }
        
        .stat-change.positive {
            color: var(--success);
        }
        
        .stat-change.negative {
            color: var(--danger);
        }
        
        .chart-container {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--spacing-xl);
            height: 350px;
            position: relative;
            overflow: hidden;
        }
        
        .chart-container canvas {
            max-height: 250px !important;
            width: 100% !important;
            height: auto !important;
        }
        
        .table-modern {
            background: white;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }
        
        .table-modern th {
            background: var(--gray-50);
            font-weight: 600;
            color: var(--gray-700);
            border: none;
            padding: var(--spacing-lg);
        }
        
        .table-modern td {
            border: none;
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--gray-100);
        }
        
        .table-modern tbody tr:hover {
            background: var(--gray-50);
        }
        
        .badge-status {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-full);
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        
        .badge-status.active {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }
        
        .badge-status.completed {
            background: rgba(59, 130, 246, 0.1);
            color: #1e40af;
        }
        
        .badge-status.pending {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
        }
        
        .badge-status.paid {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }
        
        .badge-status.waiting {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
        }
        
        .quick-actions {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--spacing-xl);
        }
        
        .quick-action {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: var(--spacing-md);
            text-align: center;
            transition: all var(--transition-fast);
            cursor: pointer;
            border: 2px solid transparent;
        }
        
        .quick-action:hover {
            background: var(--secondary-gradient);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .quick-action-icon {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-lg);
            background: var(--secondary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-sm);
            font-size: 1.25rem;
        }
        
        .quick-action:hover .quick-action-icon {
            background: white;
            color: var(--secondary);
        }
        
        .quick-action-title {
            font-weight: 600;
            margin-bottom: var(--spacing-xs);
        }
        
        .quick-action-desc {
            font-size: 0.75rem;
            opacity: 0.8;
        }
        
        .package-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            border: 2px solid transparent;
            transition: all var(--transition-normal);
            cursor: pointer;
        }
        
        .package-card:hover {
            border-color: var(--secondary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .package-card.selected {
            border-color: var(--secondary);
            background: rgba(240, 147, 251, 0.05);
        }
        
        .package-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--secondary);
            margin-bottom: var(--spacing-sm);
        }
        
        .package-features {
            list-style: none;
            padding: 0;
            margin: var(--spacing-md) 0;
        }
        
        .package-features li {
            padding: var(--spacing-xs) 0;
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .package-features li i {
            color: var(--success);
            width: 16px;
        }
        
        @media (max-width: 768px) {
            .stat-value {
                font-size: 2rem;
            }
            
            .dashboard-header {
                padding: var(--spacing-lg) 0;
            }
        }
    </style>
</head>
<body>
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-user-tie me-3"></i>Moderator Dashboard
                    </h1>
                    <p class="mb-0 opacity-75">Düğün yönetimi ve gelir takibi</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex align-items-center justify-content-end gap-3">
                        <div class="text-end">
                            <div class="fw-bold">Hoş geldin, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Moderator'); ?></div>
                            <small class="opacity-75">Komisyon Oranı: %<?php echo number_format($_SESSION['komisyon_orani'] ?? 15, 1); ?></small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-2"></i>Moderator
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profil</a></li>
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Ayarlar</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Success Message -->
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php
                switch ($_GET['success']) {
                    case 'event_created':
                        echo 'Düğün başarıyla oluşturuldu! QR kod hazırlanıyor...';
                        break;
                    default:
                        echo 'İşlem başarıyla tamamlandı!';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Grid -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card">
                    <div class="stat-icon secondary">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_events']); ?></div>
                    <div class="stat-label">Toplam Düğün</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span><?php echo $stats['active_events']; ?> aktif</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_participants']); ?></div>
                    <div class="stat-label">Toplam Katılımcı</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+5 bu hafta</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card warning">
                    <div class="stat-icon warning">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_media']); ?></div>
                    <div class="stat-label">Toplam Medya</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+12 bugün</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card info">
                    <div class="stat-icon info">
                        <i class="fas fa-lira-sign"></i>
                    </div>
                    <div class="stat-value">₺<?php echo number_format($stats['total_sales'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Toplam Satış</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+8% bu ay</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Financial Stats -->
        <div class="row mb-4">
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="stat-value">₺<?php echo number_format($stats['monthly_sales'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Bu Ay Satış</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+15% geçen aya göre</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="stat-card warning">
                    <div class="stat-icon warning">
                        <i class="fas fa-percentage"></i>
                    </div>
                    <div class="stat-value">₺<?php echo number_format($stats['monthly_commissions'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Bu Ay Komisyon</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+12% geçen aya göre</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="stat-card danger">
                    <div class="stat-icon danger">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value">₺<?php echo number_format($stats['pending_commissions'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Bekleyen Komisyon</div>
                    <div class="stat-change negative">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span>Ödeme bekleniyor</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="quick-actions">
            <h5 class="mb-3">
                <i class="fas fa-bolt me-2"></i>Hızlı İşlemler
            </h5>
            <div class="row">
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="quick-action" data-bs-toggle="modal" data-bs-target="#createEventModal">
                        <div class="quick-action-icon">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="quick-action-title">Yeni Düğün</div>
                        <div class="quick-action-desc">Düğün oluştur</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="quick-action" onclick="window.open('qr_scanner.php', '_blank')">
                        <div class="quick-action-icon">
                            <i class="fas fa-qrcode"></i>
                        </div>
                        <div class="quick-action-title">QR Tarayıcı</div>
                        <div class="quick-action-desc">QR kod tara</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="quick-action" onclick="location.href='my_events.php'">
                        <div class="quick-action-icon">
                            <i class="fas fa-list"></i>
                        </div>
                        <div class="quick-action-title">Düğünlerim</div>
                        <div class="quick-action-desc">Tüm düğünler</div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6 mb-3">
                    <div class="quick-action" onclick="location.href='reports.php'">
                        <div class="quick-action-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="quick-action-title">Raporlar</div>
                        <div class="quick-action-desc">Detaylı raporlar</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-4">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-bar me-2"></i>Aylık Satış Trendi
                    </h5>
                    <canvas id="salesChart"></canvas>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-pie-chart me-2"></i>Komisyon Durumu
                    </h5>
                    <canvas id="commissionChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tables Row -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="table-modern">
                    <div class="p-3 border-bottom">
                        <h5 class="mb-0">
                            <i class="fas fa-calendar me-2"></i>Son Düğünlerim
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>Düğün</th>
                                    <th>Paket</th>
                                    <th>Katılımcı</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($my_events as $event): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($event['baslik']); ?></div>
                                            <small class="text-muted"><?php echo date('d.m.Y', strtotime($event['dugun_tarihi'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($event['paket_ad'] ?? 'Paket Yok'); ?></div>
                                            <small class="text-success">₺<?php echo number_format($event['paket_fiyati'] ?? 0, 0, ',', '.'); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $event['katilimci_sayisi']; ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        switch ($event['durum']) {
                                            case 'aktif':
                                                $status_class = 'badge-status active';
                                                $status_text = 'Aktif';
                                                break;
                                            case 'tamamlandi':
                                                $status_class = 'badge-status completed';
                                                $status_text = 'Tamamlandı';
                                                break;
                                            case 'pasif':
                                                $status_class = 'badge-status pending';
                                                $status_text = 'Pasif';
                                                break;
                                        }
                                        ?>
                                        <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-6 mb-4">
                <div class="table-modern">
                    <div class="p-3 border-bottom">
                        <h5 class="mb-0">
                            <i class="fas fa-money-bill-wave me-2"></i>Komisyon Geçmişi
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>Düğün</th>
                                    <th>Satış</th>
                                    <th>Komisyon</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($commission_history as $commission): ?>
                                <tr>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($commission['dugun_baslik']); ?></div>
                                        <small class="text-muted"><?php echo date('d.m.Y', strtotime($commission['olusturma_tarihi'])); ?></small>
                                    </td>
                                    <td class="fw-bold text-success">
                                        ₺<?php echo number_format($commission['satis_tutari'], 0, ',', '.'); ?>
                                    </td>
                                    <td class="fw-bold text-warning">
                                        ₺<?php echo number_format($commission['komisyon_tutari'], 0, ',', '.'); ?>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        switch ($commission['durum']) {
                                            case 'odendi':
                                                $status_class = 'badge-status paid';
                                                $status_text = 'Ödendi';
                                                break;
                                            case 'beklemede':
                                                $status_class = 'badge-status waiting';
                                                $status_text = 'Beklemede';
                                                break;
                                            case 'iptal':
                                                $status_class = 'badge-status cancelled';
                                                $status_text = 'İptal';
                                                break;
                                        }
                                        ?>
                                        <span class="<?php echo $status_class; ?>"><?php echo $status_text; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Event Modal -->
    <div class="modal fade" id="createEventModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Yeni Düğün Oluştur
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_event">
                        
                        <div class="row mb-3">
                            <div class="col-md-8">
                                <label for="title" class="form-label">Düğün Başlığı</label>
                                <input type="text" class="form-control" id="title" name="title" required>
                            </div>
                            <div class="col-md-4">
                                <label for="event_date" class="form-label">Düğün Tarihi</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="description" name="description" rows="3"></textarea>
                        </div>
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="end_date" class="form-label">Bitiş Tarihi</label>
                                <input type="date" class="form-control" id="end_date" name="end_date" required>
                            </div>
                            <div class="col-md-6">
                                <label for="package_id" class="form-label">Paket Seçimi</label>
                                <select class="form-select" id="package_id" name="package_id" required>
                                    <option value="">Paket seçin</option>
                                    <?php foreach ($packages as $package): ?>
                                    <option value="<?php echo $package['id']; ?>" data-price="<?php echo $package['fiyat']; ?>">
                                        <?php echo htmlspecialchars($package['ad']); ?> - ₺<?php echo number_format($package['fiyat'], 0, ',', '.'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Package Preview -->
                        <div id="packagePreview" class="mt-3" style="display: none;">
                            <h6>Seçilen Paket:</h6>
                            <div class="package-card">
                                <div class="package-price" id="packagePrice"></div>
                                <div id="packageDescription"></div>
                                <ul class="package-features" id="packageFeatures"></ul>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus me-2"></i>Düğün Oluştur
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sales Chart
        const salesCtx = document.getElementById('salesChart').getContext('2d');
        const salesChart = new Chart(salesCtx, {
            type: 'line',
            data: {
                labels: ['Ocak', 'Şubat', 'Mart', 'Nisan', 'Mayıs', 'Haziran', 'Temmuz', 'Ağustos', 'Eylül', 'Ekim', 'Kasım', 'Aralık'],
                datasets: [{
                    label: 'Satış Tutarı (₺)',
                    data: [
                        <?php 
                        $monthly_data = [];
                        foreach ($monthly_sales_data as $data) {
                            $monthly_data[$data['ay']] = $data['satis_tutari'];
                        }
                        for ($i = 1; $i <= 12; $i++) {
                            echo ($monthly_data[$i] ?? 0) . ($i < 12 ? ',' : '');
                        }
                        ?>
                    ],
                    borderColor: '#f093fb',
                    backgroundColor: 'rgba(240, 147, 251, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '₺' + value.toLocaleString('tr-TR');
                            }
                        }
                    }
                }
            }
        });

        // Commission Chart
        const commissionCtx = document.getElementById('commissionChart').getContext('2d');
        const commissionChart = new Chart(commissionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Ödenen', 'Bekleyen'],
                datasets: [{
                    data: [
                        <?php echo $stats['total_commissions']; ?>,
                        <?php echo $stats['pending_commissions']; ?>
                    ],
                    backgroundColor: [
                        '#10b981',
                        '#f59e0b'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Resize handler
        window.addEventListener('resize', function() {
            salesChart.resize();
            commissionChart.resize();
        });
        document.getElementById('package_id').addEventListener('change', function() {
            const packageId = this.value;
            const packagePreview = document.getElementById('packagePreview');
            
            if (packageId) {
                // Show package preview (in real app, you'd fetch package details via AJAX)
                packagePreview.style.display = 'block';
                document.getElementById('packagePrice').textContent = '₺' + this.selectedOptions[0].dataset.price;
                document.getElementById('packageDescription').textContent = this.selectedOptions[0].textContent;
            } else {
                packagePreview.style.display = 'none';
            }
        });
    </script>
</body>
</html>
