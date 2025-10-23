<?php
session_start();

// Eğer kullanıcı giriş yapmamışsa login'e yönlendir
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Sadece super admin erişebilir
if ($_SESSION['user_role'] !== 'super_admin') {
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

// Genel istatistikler
$stats = [];

// Toplam kullanıcı sayısı
$stmt = $pdo->query("SELECT COUNT(*) as total FROM kullanicilar WHERE durum = 'aktif'");
$stats['total_users'] = $stmt->fetch()['total'];

// Toplam moderator sayısı
$stmt = $pdo->query("SELECT COUNT(*) as total FROM kullanicilar WHERE rol = 'moderator' AND durum = 'aktif'");
$stats['total_moderators'] = $stmt->fetch()['total'];

// Toplam düğün sayısı
$stmt = $pdo->query("SELECT COUNT(*) as total FROM dugunler WHERE durum = 'aktif'");
$stats['total_events'] = $stmt->fetch()['total'];

// Toplam satış tutarı
$stmt = $pdo->query("SELECT SUM(paket_fiyati) as total FROM dugunler WHERE odeme_durumu = 'odendi'");
$stats['total_sales'] = $stmt->fetch()['total'] ?? 0;

// Toplam komisyon tutarı
$stmt = $pdo->query("SELECT SUM(komisyon_tutari) as total FROM komisyon_gecmisi WHERE durum = 'odendi'");
$stats['total_commissions'] = $stmt->fetch()['total'] ?? 0;

// Bu ay satışlar
$stmt = $pdo->query("SELECT SUM(paket_fiyati) as total FROM dugunler WHERE odeme_durumu = 'odendi' AND MONTH(odeme_tarihi) = MONTH(CURRENT_DATE()) AND YEAR(odeme_tarihi) = YEAR(CURRENT_DATE())");
$stats['monthly_sales'] = $stmt->fetch()['total'] ?? 0;

// Bu ay komisyonlar
$stmt = $pdo->query("SELECT SUM(komisyon_tutari) as total FROM komisyon_gecmisi WHERE durum = 'odendi' AND MONTH(odeme_tarihi) = MONTH(CURRENT_DATE()) AND YEAR(odeme_tarihi) = YEAR(CURRENT_DATE())");
$stats['monthly_commissions'] = $stmt->fetch()['total'] ?? 0;

// Bekleyen ödemeler
$stmt = $pdo->query("SELECT SUM(komisyon_tutari) as total FROM komisyon_gecmisi WHERE durum = 'beklemede'");
$stats['pending_payments'] = $stmt->fetch()['total'] ?? 0;

// En çok satış yapan moderatörler
$stmt = $pdo->query("
    SELECT 
        k.ad, k.soyad, k.email,
        COUNT(d.id) as dugun_sayisi,
        SUM(d.paket_fiyati) as toplam_satis,
        SUM(kg.komisyon_tutari) as toplam_komisyon
    FROM kullanicilar k
    LEFT JOIN dugunler d ON k.id = d.moderator_id AND d.odeme_durumu = 'odendi'
    LEFT JOIN komisyon_gecmisi kg ON k.id = kg.moderator_id AND kg.durum = 'odendi'
    WHERE k.rol = 'moderator' AND k.durum = 'aktif'
    GROUP BY k.id
    ORDER BY toplam_satis DESC
    LIMIT 5
");
$top_moderators = $stmt->fetchAll();

// Son düğünler
$stmt = $pdo->query("
    SELECT 
        d.*,
        k.ad as moderator_ad,
        k.soyad as moderator_soyad,
        p.ad as paket_ad,
        p.fiyat as paket_fiyati
    FROM dugunler d
    JOIN kullanicilar k ON d.moderator_id = k.id
    LEFT JOIN paketler p ON d.paket_id = p.id
    ORDER BY d.created_at DESC
    LIMIT 10
");
$recent_events = $stmt->fetchAll();

// Aylık satış grafiği için veri
$stmt = $pdo->query("
    SELECT 
        MONTH(created_at) as ay,
        SUM(COALESCE(paket_fiyati, 0)) as satis_tutari
    FROM dugunler 
    WHERE odeme_durumu = 'odendi' 
    AND YEAR(created_at) = YEAR(CURRENT_DATE())
    GROUP BY MONTH(created_at)
    ORDER BY ay
");
$monthly_sales_data = $stmt->fetchAll();

// Komisyon ödeme işlemi
if ($_POST['action'] ?? '' === 'pay_commission') {
    $commission_id = (int)$_POST['commission_id'];
    $payment_amount = (float)$_POST['payment_amount'];
    $payment_method = $_POST['payment_method'];
    $reference_no = $_POST['reference_no'];
    
    try {
        $pdo->beginTransaction();
        
        // Komisyon durumunu güncelle
        $stmt = $pdo->prepare("UPDATE komisyon_gecmisi SET durum = 'odendi', odeme_tarihi = NOW() WHERE id = ?");
        $stmt->execute([$commission_id]);
        
        // Ödeme geçmişine ekle
        $stmt = $pdo->prepare("
            INSERT INTO odeme_gecmisi (moderator_id, komisyon_id, odeme_tutari, odeme_yontemi, referans_no) 
            SELECT moderator_id, ?, ?, ?, ? FROM komisyon_gecmisi WHERE id = ?
        ");
        $stmt->execute([$commission_id, $payment_amount, $payment_method, $reference_no, $commission_id]);
        
        // Moderator'un toplam komisyonunu güncelle
        $stmt = $pdo->prepare("
            UPDATE kullanicilar 
            SET toplam_komisyon = toplam_komisyon + ? 
            WHERE id = (SELECT moderator_id FROM komisyon_gecmisi WHERE id = ?)
        ");
        $stmt->execute([$payment_amount, $commission_id]);
        
        $pdo->commit();
        
        // Bildirim ekle
        $stmt = $pdo->prepare("
            INSERT INTO bildirimler (kullanici_id, baslik, icerik, tur, ilgili_id, ilgili_tur) 
            SELECT moderator_id, 'Komisyon Ödemesi', CONCAT('₺', ?, ' komisyon ödemesi yapıldı.'), 'odeme', ?, 'komisyon' 
            FROM komisyon_gecmisi WHERE id = ?
        ");
        $stmt->execute([$payment_amount, $commission_id, $commission_id]);
        
        header('Location: super_admin_dashboard.php?success=payment_completed');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ödeme işlemi sırasında hata oluştu: ' . $e->getMessage();
    }
}

// Bekleyen komisyonlar
$stmt = $pdo->query("
    SELECT 
        kg.*,
        k.ad as moderator_ad,
        k.soyad as moderator_soyad,
        k.email as moderator_email,
        d.baslik as dugun_baslik,
        p.ad as paket_ad
    FROM komisyon_gecmisi kg
    JOIN kullanicilar k ON kg.moderator_id = k.id
    JOIN dugunler d ON kg.dugun_id = d.id
    JOIN paketler p ON kg.paket_id = p.id
    WHERE kg.durum = 'beklemede'
    ORDER BY kg.olusturma_tarihi ASC
");
$pending_commissions = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Dashboard - Digital Salon</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-ui.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .dashboard-header {
            background: var(--primary-gradient);
            color: white;
            padding: var(--spacing-xl) 0;
            margin-bottom: var(--spacing-xl);
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            border-left: 4px solid var(--primary);
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
            background: linear-gradient(45deg, rgba(102, 126, 234, 0.1), rgba(118, 75, 162, 0.1));
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
        
        .stat-icon.primary { background: var(--primary-gradient); }
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
        
        .badge-status.pending {
            background: rgba(245, 158, 11, 0.1);
            color: #92400e;
        }
        
        .badge-status.paid {
            background: rgba(16, 185, 129, 0.1);
            color: #065f46;
        }
        
        .badge-status.cancelled {
            background: rgba(239, 68, 68, 0.1);
            color: #991b1b;
        }
        
        .floating-stats {
            position: fixed;
            top: 50%;
            right: var(--spacing-xl);
            transform: translateY(-50%);
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-xl);
            z-index: 1000;
            min-width: 200px;
        }
        
        .quick-action {
            background: white;
            border-radius: var(--radius-lg);
            padding: var(--spacing-md);
            box-shadow: var(--shadow-sm);
            border: 1px solid var(--gray-200);
            transition: all var(--transition-fast);
            cursor: pointer;
        }
        
        .quick-action:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }
        
        .quick-action-icon {
            width: 40px;
            height: 40px;
            border-radius: var(--radius-lg);
            background: var(--primary-gradient);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: var(--spacing-sm);
        }
        
        .quick-action-title {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
        }
        
        .quick-action-desc {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        @media (max-width: 768px) {
            .floating-stats {
                display: none;
            }
            
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
                        <i class="fas fa-crown me-3"></i>Super Admin Dashboard
                    </h1>
                    <p class="mb-0 opacity-75">Platform yönetimi ve komisyon takibi</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex align-items-center justify-content-end gap-3">
                        <div class="text-end">
                            <div class="fw-bold">Hoş geldin, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></div>
                            <small class="opacity-75">Son giriş: <?php echo date('d.m.Y H:i'); ?></small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-2"></i>Admin
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Ayarlar</a></li>
                                <li><a class="dropdown-item" href="users.php"><i class="fas fa-users me-2"></i>Kullanıcılar</a></li>
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
                    case 'payment_completed':
                        echo 'Komisyon ödemesi başarıyla tamamlandı!';
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
                    <div class="stat-icon primary">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
                    <div class="stat-label">Toplam Kullanıcı</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+12% bu ay</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card success">
                    <div class="stat-icon success">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_moderators']); ?></div>
                    <div class="stat-label">Aktif Moderator</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+3 bu ay</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="stat-card warning">
                    <div class="stat-icon warning">
                        <i class="fas fa-calendar-alt"></i>
                    </div>
                    <div class="stat-value"><?php echo number_format($stats['total_events']); ?></div>
                    <div class="stat-label">Toplam Düğün</div>
                    <div class="stat-change positive">
                        <i class="fas fa-arrow-up"></i>
                        <span>+8% bu ay</span>
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
                        <span>+15% bu ay</span>
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
                        <span>+22% geçen aya göre</span>
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
                        <span>+18% geçen aya göre</span>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-4">
                <div class="stat-card danger">
                    <div class="stat-icon danger">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-value">₺<?php echo number_format($stats['pending_payments'], 0, ',', '.'); ?></div>
                    <div class="stat-label">Bekleyen Ödeme</div>
                    <div class="stat-change negative">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span><?php echo count($pending_commissions); ?> ödeme</span>
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
                        <i class="fas fa-pie-chart me-2"></i>Paket Dağılımı
                    </h5>
                    <canvas id="packageChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Tables Row -->
        <div class="row mb-4">
            <div class="col-lg-6 mb-4">
                <div class="table-modern">
                    <div class="p-3 border-bottom">
                        <h5 class="mb-0">
                            <i class="fas fa-trophy me-2"></i>En Çok Satış Yapan Moderatorlar
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>Moderator</th>
                                    <th>Düğün</th>
                                    <th>Satış</th>
                                    <th>Komisyon</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_moderators as $moderator): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                <?php echo strtoupper(substr($moderator['ad'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($moderator['ad'] . ' ' . $moderator['soyad']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($moderator['email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo $moderator['dugun_sayisi']; ?></span>
                                    </td>
                                    <td class="fw-bold text-success">
                                        ₺<?php echo number_format($moderator['toplam_satis'] ?? 0, 0, ',', '.'); ?>
                                    </td>
                                    <td class="fw-bold text-warning">
                                        ₺<?php echo number_format($moderator['toplam_komisyon'] ?? 0, 0, ',', '.'); ?>
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
                            <i class="fas fa-clock me-2"></i>Son Düğünler
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>Düğün</th>
                                    <th>Moderator</th>
                                    <th>Paket</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_events as $event): ?>
                                <tr>
                                    <td>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($event['baslik']); ?></div>
                                            <small class="text-muted"><?php echo date('d.m.Y', strtotime($event['dugun_tarihi'])); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($event['moderator_ad'] . ' ' . $event['moderator_soyad']); ?></div>
                                    </td>
                                    <td>
                                        <div>
                                            <div class="fw-bold"><?php echo htmlspecialchars($event['paket_ad'] ?? 'Paket Yok'); ?></div>
                                            <small class="text-success">₺<?php echo number_format($event['paket_fiyati'] ?? 0, 0, ',', '.'); ?></small>
                                        </div>
                                    </td>
                                    <td>
                                        <?php
                                        $status_class = '';
                                        $status_text = '';
                                        switch ($event['odeme_durumu']) {
                                            case 'odendi':
                                                $status_class = 'badge-status paid';
                                                $status_text = 'Ödendi';
                                                break;
                                            case 'beklemede':
                                                $status_class = 'badge-status pending';
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

        <!-- Pending Commissions -->
        <?php if (!empty($pending_commissions)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="table-modern">
                    <div class="p-3 border-bottom">
                        <h5 class="mb-0">
                            <i class="fas fa-exclamation-triangle me-2 text-warning"></i>Bekleyen Komisyon Ödemeleri
                        </h5>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-modern mb-0">
                            <thead>
                                <tr>
                                    <th>Moderator</th>
                                    <th>Düğün</th>
                                    <th>Paket</th>
                                    <th>Satış Tutarı</th>
                                    <th>Komisyon Oranı</th>
                                    <th>Komisyon Tutarı</th>
                                    <th>Tarih</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($pending_commissions as $commission): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-warning text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                                <?php echo strtoupper(substr($commission['moderator_ad'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="fw-bold"><?php echo htmlspecialchars($commission['moderator_ad'] . ' ' . $commission['moderator_soyad']); ?></div>
                                                <small class="text-muted"><?php echo htmlspecialchars($commission['moderator_email']); ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($commission['dugun_baslik']); ?></div>
                                    </td>
                                    <td>
                                        <span class="badge bg-info"><?php echo htmlspecialchars($commission['paket_ad']); ?></span>
                                    </td>
                                    <td class="fw-bold text-success">
                                        ₺<?php echo number_format($commission['satis_tutari'], 0, ',', '.'); ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary">%<?php echo number_format($commission['komisyon_orani'], 1); ?></span>
                                    </td>
                                    <td class="fw-bold text-warning">
                                        ₺<?php echo number_format($commission['komisyon_tutari'], 0, ',', '.'); ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($commission['olusturma_tarihi'])); ?></small>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#paymentModal<?php echo $commission['id']; ?>">
                                            <i class="fas fa-money-bill-wave me-1"></i>Öde
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Payment Modals -->
    <?php foreach ($pending_commissions as $commission): ?>
    <div class="modal fade" id="paymentModal<?php echo $commission['id']; ?>" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-money-bill-wave me-2"></i>Komisyon Ödemesi
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="pay_commission">
                        <input type="hidden" name="commission_id" value="<?php echo $commission['id']; ?>">
                        
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label class="form-label">Moderator</label>
                                <div class="form-control-plaintext">
                                    <?php echo htmlspecialchars($commission['moderator_ad'] . ' ' . $commission['moderator_soyad']); ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Komisyon Tutarı</label>
                                <div class="form-control-plaintext fw-bold text-warning">
                                    ₺<?php echo number_format($commission['komisyon_tutari'], 0, ',', '.'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_amount" class="form-label">Ödeme Tutarı</label>
                            <input type="number" class="form-control" id="payment_amount" name="payment_amount" 
                                   value="<?php echo $commission['komisyon_tutari']; ?>" step="0.01" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="payment_method" class="form-label">Ödeme Yöntemi</label>
                            <select class="form-select" id="payment_method" name="payment_method" required>
                                <option value="banka_havale">Banka Havalesi</option>
                                <option value="kredi_karti">Kredi Kartı</option>
                                <option value="nakit">Nakit</option>
                                <option value="diger">Diğer</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="reference_no" class="form-label">Referans No / Açıklama</label>
                            <input type="text" class="form-control" id="reference_no" name="reference_no" 
                                   placeholder="Ödeme referans numarası veya açıklama">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check me-2"></i>Ödemeyi Onayla
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Floating Stats -->
    <div class="floating-stats d-none d-lg-block">
        <h6 class="mb-3">
            <i class="fas fa-chart-pie me-2"></i>Hızlı İstatistikler
        </h6>
        <div class="quick-stats">
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Toplam Gelir:</span>
                <span class="fw-bold text-success">₺<?php echo number_format($stats['total_sales'], 0, ',', '.'); ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Toplam Komisyon:</span>
                <span class="fw-bold text-warning">₺<?php echo number_format($stats['total_commissions'], 0, ',', '.'); ?></span>
            </div>
            <div class="d-flex justify-content-between mb-2">
                <span class="text-muted">Bu Ay Satış:</span>
                <span class="fw-bold text-info">₺<?php echo number_format($stats['monthly_sales'], 0, ',', '.'); ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="text-muted">Bekleyen:</span>
                <span class="fw-bold text-danger">₺<?php echo number_format($stats['pending_payments'], 0, ',', '.'); ?></span>
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
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
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

        // Package Chart
        const packageCtx = document.getElementById('packageChart').getContext('2d');
        const packageChart = new Chart(packageCtx, {
            type: 'doughnut',
            data: {
                labels: ['Temel', 'Standart', 'Premium', 'VIP'],
                datasets: [{
                    data: [25, 35, 25, 15],
                    backgroundColor: [
                        '#667eea',
                        '#f093fb',
                        '#4facfe',
                        '#fa709a'
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
            packageChart.resize();
        });
    </script>
</body>
</html>
