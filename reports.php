<?php
session_start();

// Eğer kullanıcı giriş yapmamışsa login'e yönlendir
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Sadece super admin ve moderator erişebilir
if (!in_array($_SESSION['user_role'], ['super_admin', 'moderator'])) {
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
$user_role = $_SESSION['user_role'];

// Tarih filtreleri
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // Bu ayın başı
$end_date = $_GET['end_date'] ?? date('Y-m-d'); // Bugün
$moderator_id = $_GET['moderator_id'] ?? null;

// Rapor türü
$report_type = $_GET['report_type'] ?? 'overview';

// Moderator filtresi (sadece super admin için)
$moderator_filter = '';
$moderator_params = [];
if ($user_role === 'super_admin' && $moderator_id) {
    $moderator_filter = 'AND d.moderator_id = ?';
    $moderator_params = [$moderator_id];
} elseif ($user_role === 'moderator') {
    $moderator_filter = 'AND d.moderator_id = ?';
    $moderator_params = [$user_id];
}

// Genel istatistikler
$stats = [];

// Toplam düğün sayısı
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM dugunler d 
    WHERE d.olusturma_tarihi BETWEEN ? AND ? $moderator_filter
");
$stmt->execute(array_merge([$start_date, $end_date], $moderator_params));
$stats['total_events'] = $stmt->fetch()['total'];

// Toplam katılımcı sayısı
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT dk.kullanici_id) as total 
    FROM dugun_katilimcilar dk 
    JOIN dugunler d ON dk.dugun_id = d.id 
    WHERE d.olusturma_tarihi BETWEEN ? AND ? $moderator_filter
");
$stmt->execute(array_merge([$start_date, $end_date], $moderator_params));
$stats['total_participants'] = $stmt->fetch()['total'];

// Toplam medya sayısı
$stmt = $pdo->prepare("
    SELECT COUNT(*) as total 
    FROM medyalar m 
    JOIN dugunler d ON m.dugun_id = d.id 
    WHERE d.olusturma_tarihi BETWEEN ? AND ? $moderator_filter
");
$stmt->execute(array_merge([$start_date, $end_date], $moderator_params));
$stats['total_media'] = $stmt->fetch()['total'];

// Toplam satış tutarı
$stmt = $pdo->prepare("
    SELECT SUM(paket_fiyati) as total 
    FROM dugunler d 
    WHERE d.olusturma_tarihi BETWEEN ? AND ? AND d.odeme_durumu = 'odendi' $moderator_filter
");
$stmt->execute(array_merge([$start_date, $end_date], $moderator_params));
$stats['total_sales'] = $stmt->fetch()['total'] ?? 0;

// Toplam komisyon tutarı
$stmt = $pdo->prepare("
    SELECT SUM(kg.komisyon_tutari) as total 
    FROM komisyon_gecmisi kg 
    JOIN dugunler d ON kg.dugun_id = d.id 
    WHERE d.olusturma_tarihi BETWEEN ? AND ? AND kg.durum = 'odendi' $moderator_filter
");
$stmt->execute(array_merge([$start_date, $end_date], $moderator_params));
$stats['total_commissions'] = $stmt->fetch()['total'] ?? 0;

// Aylık trend verisi
$stmt = $pdo->prepare("
    SELECT 
        DATE_FORMAT(d.olusturma_tarihi, '%Y-%m') as ay,
        COUNT(*) as dugun_sayisi,
        SUM(d.paket_fiyati) as satis_tutari,
        SUM(kg.komisyon_tutari) as komisyon_tutari
    FROM dugunler d
    LEFT JOIN komisyon_gecmisi kg ON d.id = kg.dugun_id AND kg.durum = 'odendi'
    WHERE d.olusturma_tarihi BETWEEN ? AND ? $moderator_filter
    GROUP BY DATE_FORMAT(d.olusturma_tarihi, '%Y-%m')
    ORDER BY ay
");
$stmt->execute(array_merge([$start_date, $end_date], $moderator_params));
$monthly_trends = $stmt->fetchAll();

// Paket dağılımı
$stmt = $pdo->prepare("
    SELECT 
        p.ad as paket_ad,
        COUNT(*) as dugun_sayisi,
        SUM(d.paket_fiyati) as toplam_satis,
        AVG(d.paket_fiyati) as ortalama_fiyat
    FROM dugunler d
    JOIN paketler p ON d.paket_id = p.id
    WHERE d.olusturma_tarihi BETWEEN ? AND ? $moderator_filter
    GROUP BY p.id, p.ad
    ORDER BY dugun_sayisi DESC
");
$stmt->execute(array_merge([$start_date, $end_date], $moderator_params));
$package_distribution = $stmt->fetchAll();

// En aktif kullanıcılar
$stmt = $pdo->prepare("
    SELECT 
        k.ad,
        k.soyad,
        COUNT(DISTINCT dk.dugun_id) as katildigi_dugun_sayisi,
        COUNT(m.id) as paylastigi_medya_sayisi,
        COUNT(DISTINCT b.id) as toplam_begeni_sayisi
    FROM kullanicilar k
    JOIN dugun_katilimcilar dk ON k.id = dk.kullanici_id
    JOIN dugunler d ON dk.dugun_id = d.id
    LEFT JOIN medyalar m ON k.id = m.kullanici_id AND m.dugun_id = d.id
    LEFT JOIN begeniler b ON m.id = b.medya_id
    WHERE d.olusturma_tarihi BETWEEN ? AND ? $moderator_filter
    GROUP BY k.id
    ORDER BY katildigi_dugun_sayisi DESC, paylastigi_medya_sayisi DESC
    LIMIT 10
");
$stmt->execute(array_merge([$start_date, $end_date], $moderator_params));
$top_users = $stmt->fetchAll();

// Moderator performansı (sadece super admin için)
$moderator_performance = [];
if ($user_role === 'super_admin') {
    $stmt = $pdo->prepare("
        SELECT 
            k.ad,
            k.soyad,
            k.email,
            COUNT(d.id) as dugun_sayisi,
            SUM(d.paket_fiyati) as toplam_satis,
            SUM(kg.komisyon_tutari) as toplam_komisyon,
            COUNT(DISTINCT dk.kullanici_id) as toplam_katilimci,
            COUNT(m.id) as toplam_medya
        FROM kullanicilar k
        LEFT JOIN dugunler d ON k.id = d.moderator_id AND d.olusturma_tarihi BETWEEN ? AND ?
        LEFT JOIN komisyon_gecmisi kg ON d.id = kg.dugun_id AND kg.durum = 'odendi'
        LEFT JOIN dugun_katilimcilar dk ON d.id = dk.dugun_id
        LEFT JOIN medyalar m ON d.id = m.dugun_id
        WHERE k.rol = 'moderator' AND k.durum = 'aktif'
        GROUP BY k.id
        ORDER BY toplam_satis DESC
    ");
    $stmt->execute([$start_date, $end_date]);
    $moderator_performance = $stmt->fetchAll();
}

// Moderator listesi (super admin için)
$moderators = [];
if ($user_role === 'super_admin') {
    $stmt = $pdo->query("SELECT id, ad, soyad FROM kullanicilar WHERE rol = 'moderator' AND durum = 'aktif' ORDER BY ad, soyad");
    $moderators = $stmt->fetchAll();
}

// Detaylı düğün raporu
$event_details = [];
if ($report_type === 'events') {
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            k.ad as moderator_ad,
            k.soyad as moderator_soyad,
            p.ad as paket_ad,
            COUNT(DISTINCT dk.kullanici_id) as katilimci_sayisi,
            COUNT(m.id) as medya_sayisi,
            SUM(kg.komisyon_tutari) as komisyon_tutari
        FROM dugunler d
        JOIN kullanicilar k ON d.moderator_id = k.id
        LEFT JOIN paketler p ON d.paket_id = p.id
        LEFT JOIN dugun_katilimcilar dk ON d.id = dk.dugun_id
        LEFT JOIN medyalar m ON d.id = m.dugun_id
        LEFT JOIN komisyon_gecmisi kg ON d.id = kg.dugun_id AND kg.durum = 'odendi'
        WHERE d.olusturma_tarihi BETWEEN ? AND ? $moderator_filter
        GROUP BY d.id
        ORDER BY d.olusturma_tarihi DESC
    ");
    $stmt->execute(array_merge([$start_date, $end_date], $moderator_params));
    $event_details = $stmt->fetchAll();
}

// CSV Export
if ($_GET['export'] ?? '' === 'csv') {
    $filename = 'rapor_' . date('Y-m-d_H-i-s') . '.csv';
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);
    
    $output = fopen('php://output', 'w');
    
    // BOM for UTF-8
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    if ($report_type === 'events') {
        fputcsv($output, ['Düğün Başlığı', 'Moderator', 'Paket', 'Düğün Tarihi', 'Katılımcı Sayısı', 'Medya Sayısı', 'Satış Tutarı', 'Komisyon Tutarı', 'Durum']);
        
        foreach ($event_details as $event) {
            fputcsv($output, [
                $event['baslik'],
                $event['moderator_ad'] . ' ' . $event['moderator_soyad'],
                $event['paket_ad'] ?? 'Paket Yok',
                date('d.m.Y', strtotime($event['dugun_tarihi'])),
                $event['katilimci_sayisi'],
                $event['medya_sayisi'],
                number_format($event['paket_fiyati'], 2, ',', '.'),
                number_format($event['komisyon_tutari'] ?? 0, 2, ',', '.'),
                ucfirst($event['durum'])
            ]);
        }
    } else {
        fputcsv($output, ['Metrik', 'Değer']);
        fputcsv($output, ['Toplam Düğün', $stats['total_events']]);
        fputcsv($output, ['Toplam Katılımcı', $stats['total_participants']]);
        fputcsv($output, ['Toplam Medya', $stats['total_media']]);
        fputcsv($output, ['Toplam Satış', number_format($stats['total_sales'], 2, ',', '.')]);
        fputcsv($output, ['Toplam Komisyon', number_format($stats['total_commissions'], 2, ',', '.')]);
    }
    
    fclose($output);
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Raporlar ve Analitik - Digital Salon</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-ui.css" rel="stylesheet">
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        .reports-header {
            background: var(--primary-gradient);
            color: white;
            padding: var(--spacing-xl) 0;
            margin-bottom: var(--spacing-xl);
        }
        
        .filter-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--spacing-xl);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .stat-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            border-left: 4px solid var(--primary);
            transition: all var(--transition-normal);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.danger { border-left-color: var(--danger); }
        .stat-card.info { border-left-color: var(--info); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: var(--gray-600);
            font-weight: 500;
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
            margin-bottom: var(--spacing-xl);
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
        
        .export-buttons {
            display: flex;
            gap: var(--spacing-sm);
            margin-bottom: var(--spacing-lg);
        }
        
        .btn-export {
            background: var(--success-gradient);
            color: white;
            border: none;
            border-radius: var(--radius-lg);
            padding: var(--spacing-sm) var(--spacing-md);
            font-weight: 500;
            cursor: pointer;
            transition: all var(--transition-normal);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .btn-export:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: white;
        }
        
        .moderator-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            border-left: 4px solid var(--primary);
            margin-bottom: var(--spacing-lg);
        }
        
        .moderator-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
        }
        
        .moderator-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .moderator-email {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .moderator-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: var(--spacing-md);
        }
        
        .moderator-stat {
            text-align: center;
        }
        
        .moderator-stat-value {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
        }
        
        .moderator-stat-label {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .export-buttons {
                flex-direction: column;
            }
            
            .moderator-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Reports Header -->
    <div class="reports-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-chart-line me-3"></i>Raporlar ve Analitik
                    </h1>
                    <p class="mb-0 opacity-75">Detaylı analiz ve performans raporları</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex align-items-center justify-content-end gap-3">
                        <div class="text-end">
                            <div class="fw-bold">Hoş geldin, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı'); ?></div>
                            <small class="opacity-75">Raporlar ve analitik</small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-2"></i><?php echo ucfirst($user_role); ?>
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="<?php echo $user_role === 'super_admin' ? 'super_admin_dashboard.php' : 'moderator_dashboard.php'; ?>"><i class="fas fa-home me-2"></i>Dashboard</a></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profil</a></li>
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
        <!-- Filter Card -->
        <div class="filter-card">
            <h5 class="mb-3">
                <i class="fas fa-filter me-2"></i>Rapor Filtreleri
            </h5>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                    <input type="date" class="form-control" id="start_date" name="start_date" value="<?php echo $start_date; ?>">
                </div>
                <div class="col-md-3">
                    <label for="end_date" class="form-label">Bitiş Tarihi</label>
                    <input type="date" class="form-control" id="end_date" name="end_date" value="<?php echo $end_date; ?>">
                </div>
                <?php if ($user_role === 'super_admin'): ?>
                <div class="col-md-3">
                    <label for="moderator_id" class="form-label">Moderator</label>
                    <select class="form-select" id="moderator_id" name="moderator_id">
                        <option value="">Tüm Moderatorlar</option>
                        <?php foreach ($moderators as $moderator): ?>
                            <option value="<?php echo $moderator['id']; ?>" <?php echo $moderator_id == $moderator['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($moderator['ad'] . ' ' . $moderator['soyad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <label for="report_type" class="form-label">Rapor Türü</label>
                    <select class="form-select" id="report_type" name="report_type">
                        <option value="overview" <?php echo $report_type === 'overview' ? 'selected' : ''; ?>>Genel Bakış</option>
                        <option value="events" <?php echo $report_type === 'events' ? 'selected' : ''; ?>>Düğün Detayları</option>
                    </select>
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Filtrele
                    </button>
                    <a href="reports.php" class="btn btn-outline-secondary">
                        <i class="fas fa-refresh me-2"></i>Sıfırla
                    </a>
                </div>
            </form>
        </div>

        <!-- Export Buttons -->
        <div class="export-buttons">
            <a href="?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv'])); ?>" class="btn-export">
                <i class="fas fa-file-csv"></i>CSV İndir
            </a>
            <button class="btn-export" onclick="window.print()">
                <i class="fas fa-print"></i>Yazdır
            </button>
        </div>

        <!-- Stats Grid -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?php echo number_format($stats['total_events']); ?></div>
                <div class="stat-label">Toplam Düğün</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-value"><?php echo number_format($stats['total_participants']); ?></div>
                <div class="stat-label">Toplam Katılımcı</div>
            </div>
            
            <div class="stat-card warning">
                <div class="stat-value"><?php echo number_format($stats['total_media']); ?></div>
                <div class="stat-label">Toplam Medya</div>
            </div>
            
            <div class="stat-card info">
                <div class="stat-value">₺<?php echo number_format($stats['total_sales'], 0, ',', '.'); ?></div>
                <div class="stat-label">Toplam Satış</div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-value">₺<?php echo number_format($stats['total_commissions'], 0, ',', '.'); ?></div>
                <div class="stat-label">Toplam Komisyon</div>
            </div>
        </div>

        <!-- Charts Row -->
        <div class="row mb-4">
            <div class="col-lg-8 mb-4">
                <div class="chart-container">
                    <h5 class="mb-3">
                        <i class="fas fa-chart-bar me-2"></i>Aylık Trend Analizi
                    </h5>
                    <canvas id="trendChart"></canvas>
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

        <!-- Top Users -->
        <?php if (!empty($top_users)): ?>
        <div class="table-modern">
            <div class="p-3 border-bottom">
                <h5 class="mb-0">
                    <i class="fas fa-trophy me-2"></i>En Aktif Kullanıcılar
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Katıldığı Düğün</th>
                            <th>Paylaştığı Medya</th>
                            <th>Toplam Beğeni</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($top_users as $user): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $user['katildigi_dugun_sayisi']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-success"><?php echo $user['paylastigi_medya_sayisi']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-warning"><?php echo $user['toplam_begeni_sayisi']; ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Moderator Performance (Super Admin Only) -->
        <?php if ($user_role === 'super_admin' && !empty($moderator_performance)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-3">
                    <i class="fas fa-user-tie me-2"></i>Moderator Performansı
                </h4>
                <div class="row">
                    <?php foreach ($moderator_performance as $moderator): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="moderator-card">
                                <div class="moderator-header">
                                    <div>
                                        <div class="moderator-name"><?php echo htmlspecialchars($moderator['ad'] . ' ' . $moderator['soyad']); ?></div>
                                        <div class="moderator-email"><?php echo htmlspecialchars($moderator['email']); ?></div>
                                    </div>
                                </div>
                                
                                <div class="moderator-stats">
                                    <div class="moderator-stat">
                                        <div class="moderator-stat-value"><?php echo $moderator['dugun_sayisi']; ?></div>
                                        <div class="moderator-stat-label">Düğün</div>
                                    </div>
                                    <div class="moderator-stat">
                                        <div class="moderator-stat-value text-success">₺<?php echo number_format($moderator['toplam_satis'], 0, ',', '.'); ?></div>
                                        <div class="moderator-stat-label">Satış</div>
                                    </div>
                                    <div class="moderator-stat">
                                        <div class="moderator-stat-value text-warning">₺<?php echo number_format($moderator['toplam_komisyon'], 0, ',', '.'); ?></div>
                                        <div class="moderator-stat-label">Komisyon</div>
                                    </div>
                                    <div class="moderator-stat">
                                        <div class="moderator-stat-value text-info"><?php echo $moderator['toplam_katilimci']; ?></div>
                                        <div class="moderator-stat-label">Katılımcı</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Event Details -->
        <?php if ($report_type === 'events' && !empty($event_details)): ?>
        <div class="table-modern">
            <div class="p-3 border-bottom">
                <h5 class="mb-0">
                    <i class="fas fa-calendar me-2"></i>Düğün Detayları
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead>
                        <tr>
                            <th>Düğün</th>
                            <th>Moderator</th>
                            <th>Paket</th>
                            <th>Tarih</th>
                            <th>Katılımcı</th>
                            <th>Medya</th>
                            <th>Satış</th>
                            <th>Komisyon</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($event_details as $event): ?>
                        <tr>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($event['baslik']); ?></div>
                            </td>
                            <td>
                                <div class="fw-bold"><?php echo htmlspecialchars($event['moderator_ad'] . ' ' . $event['moderator_soyad']); ?></div>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo htmlspecialchars($event['paket_ad'] ?? 'Paket Yok'); ?></span>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo date('d.m.Y', strtotime($event['dugun_tarihi'])); ?></small>
                            </td>
                            <td>
                                <span class="badge bg-primary"><?php echo $event['katilimci_sayisi']; ?></span>
                            </td>
                            <td>
                                <span class="badge bg-success"><?php echo $event['medya_sayisi']; ?></span>
                            </td>
                            <td class="fw-bold text-success">
                                ₺<?php echo number_format($event['paket_fiyati'], 0, ',', '.'); ?>
                            </td>
                            <td class="fw-bold text-warning">
                                ₺<?php echo number_format($event['komisyon_tutari'] ?? 0, 0, ',', '.'); ?>
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
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        const trendChart = new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: [
                    <?php foreach ($monthly_trends as $trend): ?>
                        '<?php echo date('M Y', strtotime($trend['ay'] . '-01')); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    label: 'Düğün Sayısı',
                    data: [
                        <?php foreach ($monthly_trends as $trend): ?>
                            <?php echo $trend['dugun_sayisi']; ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    yAxisID: 'y'
                }, {
                    label: 'Satış Tutarı (₺)',
                    data: [
                        <?php foreach ($monthly_trends as $trend): ?>
                            <?php echo $trend['satis_tutari'] ?? 0; ?>,
                        <?php endforeach; ?>
                    ],
                    borderColor: '#f093fb',
                    backgroundColor: 'rgba(240, 147, 251, 0.1)',
                    borderWidth: 3,
                    fill: false,
                    tension: 0.4,
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: true
                    }
                },
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Düğün Sayısı'
                        }
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Satış Tutarı (₺)'
                        },
                        grid: {
                            drawOnChartArea: false,
                        },
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
                labels: [
                    <?php foreach ($package_distribution as $package): ?>
                        '<?php echo htmlspecialchars($package['paket_ad']); ?>',
                    <?php endforeach; ?>
                ],
                datasets: [{
                    data: [
                        <?php foreach ($package_distribution as $package): ?>
                            <?php echo $package['dugun_sayisi']; ?>,
                        <?php endforeach; ?>
                    ],
                    backgroundColor: [
                        '#667eea',
                        '#f093fb',
                        '#4facfe',
                        '#fa709a',
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

        // Chart resize on window resize
        window.addEventListener('resize', function() {
            if (trendChart) {
                trendChart.resize();
            }
            if (packageChart) {
                packageChart.resize();
            }
        });
    </script>
</body>
</html>
