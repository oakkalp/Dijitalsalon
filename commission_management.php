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

// Komisyon ödeme işlemi
if ($_POST['action'] ?? '' === 'pay_commission') {
    $commission_id = (int)$_POST['commission_id'];
    $payment_amount = (float)$_POST['payment_amount'];
    $payment_method = $_POST['payment_method'];
    $reference_no = $_POST['reference_no'];
    $notes = trim($_POST['notes']);
    
    try {
        $pdo->beginTransaction();
        
        // Komisyon durumunu güncelle
        $stmt = $pdo->prepare("UPDATE komisyon_gecmisi SET durum = 'odendi', odeme_tarihi = NOW() WHERE id = ?");
        $stmt->execute([$commission_id]);
        
        // Ödeme geçmişine ekle
        $stmt = $pdo->prepare("
            INSERT INTO odeme_gecmisi (moderator_id, komisyon_id, odeme_tutari, odeme_yontemi, referans_no, aciklama) 
            SELECT moderator_id, ?, ?, ?, ?, ? FROM komisyon_gecmisi WHERE id = ?
        ");
        $stmt->execute([$commission_id, $payment_amount, $payment_method, $reference_no, $notes, $commission_id]);
        
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
        
        header('Location: commission_management.php?success=payment_completed');
        exit;
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = 'Ödeme işlemi sırasında hata oluştu: ' . $e->getMessage();
    }
}

// Toplu ödeme işlemi
if ($_POST['action'] ?? '' === 'bulk_payment') {
    $commission_ids = $_POST['commission_ids'] ?? [];
    $payment_method = $_POST['payment_method'];
    $reference_no = $_POST['reference_no'];
    $notes = trim($_POST['notes']);
    
    if (!empty($commission_ids)) {
        try {
            $pdo->beginTransaction();
            
            $total_amount = 0;
            $moderator_ids = [];
            
            // Her komisyon için işlem yap
            foreach ($commission_ids as $commission_id) {
                $commission_id = (int)$commission_id;
                
                // Komisyon bilgilerini al
                $stmt = $pdo->prepare("SELECT * FROM komisyon_gecmisi WHERE id = ? AND durum = 'beklemede'");
                $stmt->execute([$commission_id]);
                $commission = $stmt->fetch();
                
                if ($commission) {
                    // Komisyon durumunu güncelle
                    $stmt = $pdo->prepare("UPDATE komisyon_gecmisi SET durum = 'odendi', odeme_tarihi = NOW() WHERE id = ?");
                    $stmt->execute([$commission_id]);
                    
                    // Ödeme geçmişine ekle
                    $stmt = $pdo->prepare("
                        INSERT INTO odeme_gecmisi (moderator_id, komisyon_id, odeme_tutari, odeme_yontemi, referans_no, aciklama) 
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$commission['moderator_id'], $commission_id, $commission['komisyon_tutari'], $payment_method, $reference_no, $notes]);
                    
                    $total_amount += $commission['komisyon_tutari'];
                    $moderator_ids[] = $commission['moderator_id'];
                }
            }
            
            // Moderator'ların toplam komisyonunu güncelle
            foreach ($moderator_ids as $moderator_id) {
                $stmt = $pdo->prepare("
                    UPDATE kullanicilar 
                    SET toplam_komisyon = (
                        SELECT SUM(komisyon_tutari) 
                        FROM komisyon_gecmisi 
                        WHERE moderator_id = ? AND durum = 'odendi'
                    )
                    WHERE id = ?
                ");
                $stmt->execute([$moderator_id, $moderator_id]);
            }
            
            $pdo->commit();
            
            header('Location: commission_management.php?success=bulk_payment_completed&amount=' . $total_amount);
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Toplu ödeme işlemi sırasında hata oluştu: ' . $e->getMessage();
        }
    } else {
        $error = 'Ödeme yapılacak komisyon seçilmedi';
    }
}

// Bekleyen komisyonlar
$stmt = $pdo->query("
    SELECT 
        kg.*,
        k.ad as moderator_ad,
        k.soyad as moderator_soyad,
        k.email as moderator_email,
        k.telefon as moderator_telefon,
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

// Ödenen komisyonlar
$stmt = $pdo->query("
    SELECT 
        kg.*,
        k.ad as moderator_ad,
        k.soyad as moderator_soyad,
        d.baslik as dugun_baslik,
        p.ad as paket_ad,
        og.odeme_tarihi,
        og.odeme_yontemi,
        og.referans_no
    FROM komisyon_gecmisi kg
    JOIN kullanicilar k ON kg.moderator_id = k.id
    JOIN dugunler d ON kg.dugun_id = d.id
    JOIN paketler p ON kg.paket_id = p.id
    LEFT JOIN odeme_gecmisi og ON kg.id = og.komisyon_id
    WHERE kg.durum = 'odendi'
    ORDER BY kg.odeme_tarihi DESC
    LIMIT 50
");
$paid_commissions = $stmt->fetchAll();

// Moderator istatistikleri
$stmt = $pdo->query("
    SELECT 
        k.id,
        k.ad,
        k.soyad,
        k.email,
        COUNT(kg.id) as toplam_komisyon_sayisi,
        SUM(CASE WHEN kg.durum = 'beklemede' THEN kg.komisyon_tutari ELSE 0 END) as bekleyen_komisyon,
        SUM(CASE WHEN kg.durum = 'odendi' THEN kg.komisyon_tutari ELSE 0 END) as odenen_komisyon,
        SUM(kg.komisyon_tutari) as toplam_komisyon
    FROM kullanicilar k
    LEFT JOIN komisyon_gecmisi kg ON k.id = kg.moderator_id
    WHERE k.rol = 'moderator' AND k.durum = 'aktif'
    GROUP BY k.id
    ORDER BY bekleyen_komisyon DESC
");
$moderator_stats = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Komisyon Yönetimi - Digital Salon</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-ui.css" rel="stylesheet">
    
    <style>
        .management-header {
            background: var(--primary-gradient);
            color: white;
            padding: var(--spacing-xl) 0;
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
        
        .stat-card.warning { border-left-color: var(--warning); }
        .stat-card.success { border-left-color: var(--success); }
        .stat-card.danger { border-left-color: var(--danger); }
        
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
        
        .action-buttons {
            display: flex;
            gap: var(--spacing-sm);
        }
        
        .btn-action {
            padding: var(--spacing-xs) var(--spacing-sm);
            border-radius: var(--radius-sm);
            border: none;
            cursor: pointer;
            font-size: 0.75rem;
            transition: all var(--transition-fast);
        }
        
        .btn-pay {
            background: var(--success);
            color: white;
        }
        
        .btn-pay:hover {
            background: #059669;
        }
        
        .btn-view {
            background: var(--info);
            color: white;
        }
        
        .btn-view:hover {
            background: #2563eb;
        }
        
        .bulk-actions {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--spacing-xl);
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
            justify-content: between;
            align-items: center;
            margin-bottom: var(--spacing-md);
        }
        
        .moderator-info {
            flex: 1;
        }
        
        .moderator-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
        }
        
        .moderator-contact {
            font-size: 0.875rem;
            color: var(--gray-600);
        }
        
        .moderator-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
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
        
        .checkbox-column {
            width: 40px;
        }
        
        .checkbox-column input[type="checkbox"] {
            transform: scale(1.2);
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .moderator-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</head>
<body>
    <!-- Management Header -->
    <div class="management-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-money-bill-wave me-3"></i>Komisyon Yönetimi
                    </h1>
                    <p class="mb-0 opacity-75">Moderator komisyonları ve ödeme takibi</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex align-items-center justify-content-end gap-3">
                        <div class="text-end">
                            <div class="fw-bold">Hoş geldin, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Admin'); ?></div>
                            <small class="opacity-75">Komisyon yönetimi</small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-2"></i>Admin
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="super_admin_dashboard.php"><i class="fas fa-home me-2"></i>Dashboard</a></li>
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
                    case 'bulk_payment_completed':
                        echo 'Toplu ödeme başarıyla tamamlandı! Toplam: ₺' . number_format($_GET['amount'], 0, ',', '.');
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
        <div class="stats-grid">
            <div class="stat-card warning">
                <div class="stat-value"><?php echo count($pending_commissions); ?></div>
                <div class="stat-label">Bekleyen Komisyon</div>
            </div>
            
            <div class="stat-card success">
                <div class="stat-value"><?php echo count($paid_commissions); ?></div>
                <div class="stat-label">Ödenen Komisyon</div>
            </div>
            
            <div class="stat-card danger">
                <div class="stat-value">₺<?php echo number_format(array_sum(array_column($pending_commissions, 'komisyon_tutari')), 0, ',', '.'); ?></div>
                <div class="stat-label">Bekleyen Tutar</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-value"><?php echo count($moderator_stats); ?></div>
                <div class="stat-label">Aktif Moderator</div>
            </div>
        </div>

        <!-- Bulk Actions -->
        <?php if (!empty($pending_commissions)): ?>
        <div class="bulk-actions">
            <h5 class="mb-3">
                <i class="fas fa-layer-group me-2"></i>Toplu İşlemler
            </h5>
            <form method="POST" id="bulkPaymentForm">
                <input type="hidden" name="action" value="bulk_payment">
                
                <div class="row mb-3">
                    <div class="col-md-4">
                        <label for="bulk_payment_method" class="form-label">Ödeme Yöntemi</label>
                        <select class="form-select" id="bulk_payment_method" name="payment_method" required>
                            <option value="">Seçin</option>
                            <option value="banka_havale">Banka Havalesi</option>
                            <option value="kredi_karti">Kredi Kartı</option>
                            <option value="nakit">Nakit</option>
                            <option value="diger">Diğer</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label for="bulk_reference_no" class="form-label">Referans No</label>
                        <input type="text" class="form-control" id="bulk_reference_no" name="reference_no" placeholder="Ödeme referans numarası">
                    </div>
                    <div class="col-md-4">
                        <label for="bulk_notes" class="form-label">Açıklama</label>
                        <input type="text" class="form-control" id="bulk_notes" name="notes" placeholder="Toplu ödeme açıklaması">
                    </div>
                </div>
                
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-primary" onclick="selectAllPending()">
                        <i class="fas fa-check-square me-2"></i>Tümünü Seç
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="deselectAll()">
                        <i class="fas fa-square me-2"></i>Seçimi Kaldır
                    </button>
                    <button type="submit" class="btn btn-success" onclick="return confirmBulkPayment()">
                        <i class="fas fa-money-bill-wave me-2"></i>Seçilenleri Öde
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Moderator Statistics -->
        <div class="row mb-4">
            <div class="col-12">
                <h4 class="mb-3">
                    <i class="fas fa-chart-bar me-2"></i>Moderator İstatistikleri
                </h4>
                <div class="row">
                    <?php foreach ($moderator_stats as $moderator): ?>
                        <div class="col-lg-4 col-md-6 mb-4">
                            <div class="moderator-card">
                                <div class="moderator-header">
                                    <div class="moderator-info">
                                        <div class="moderator-name"><?php echo htmlspecialchars($moderator['ad'] . ' ' . $moderator['soyad']); ?></div>
                                        <div class="moderator-contact">
                                            <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($moderator['email']); ?>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="moderator-stats">
                                    <div class="moderator-stat">
                                        <div class="moderator-stat-value"><?php echo $moderator['toplam_komisyon_sayisi']; ?></div>
                                        <div class="moderator-stat-label">Toplam</div>
                                    </div>
                                    <div class="moderator-stat">
                                        <div class="moderator-stat-value text-warning">₺<?php echo number_format($moderator['bekleyen_komisyon'], 0, ',', '.'); ?></div>
                                        <div class="moderator-stat-label">Bekleyen</div>
                                    </div>
                                    <div class="moderator-stat">
                                        <div class="moderator-stat-value text-success">₺<?php echo number_format($moderator['odenen_komisyon'], 0, ',', '.'); ?></div>
                                        <div class="moderator-stat-label">Ödenen</div>
                                    </div>
                                    <div class="moderator-stat">
                                        <div class="moderator-stat-value text-primary">₺<?php echo number_format($moderator['toplam_komisyon'], 0, ',', '.'); ?></div>
                                        <div class="moderator-stat-label">Toplam</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- Pending Commissions -->
        <?php if (!empty($pending_commissions)): ?>
        <div class="table-modern">
            <div class="p-3 border-bottom">
                <h5 class="mb-0">
                    <i class="fas fa-clock me-2 text-warning"></i>Bekleyen Komisyon Ödemeleri
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead>
                        <tr>
                            <th class="checkbox-column">
                                <input type="checkbox" id="selectAllPending" onchange="toggleAllPending(this)">
                            </th>
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
                                <td class="checkbox-column">
                                    <input type="checkbox" name="commission_ids[]" value="<?php echo $commission['id']; ?>" class="pending-checkbox">
                                </td>
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
                                    <div class="action-buttons">
                                        <button class="btn-action btn-pay" data-bs-toggle="modal" data-bs-target="#paymentModal<?php echo $commission['id']; ?>">
                                            <i class="fas fa-money-bill-wave"></i> Öde
                                        </button>
                                        <button class="btn-action btn-view" onclick="viewModeratorDetails(<?php echo $commission['moderator_id']; ?>)">
                                            <i class="fas fa-eye"></i> Detay
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Paid Commissions -->
        <?php if (!empty($paid_commissions)): ?>
        <div class="table-modern">
            <div class="p-3 border-bottom">
                <h5 class="mb-0">
                    <i class="fas fa-check-circle me-2 text-success"></i>Ödenen Komisyonlar
                </h5>
            </div>
            <div class="table-responsive">
                <table class="table table-modern mb-0">
                    <thead>
                        <tr>
                            <th>Moderator</th>
                            <th>Düğün</th>
                            <th>Komisyon Tutarı</th>
                            <th>Ödeme Tarihi</th>
                            <th>Ödeme Yöntemi</th>
                            <th>Referans No</th>
                            <th>Durum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($paid_commissions as $commission): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($commission['moderator_ad'] . ' ' . $commission['moderator_soyad']); ?></div>
                                </td>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($commission['dugun_baslik']); ?></div>
                                </td>
                                <td class="fw-bold text-success">
                                    ₺<?php echo number_format($commission['komisyon_tutari'], 0, ',', '.'); ?>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo date('d.m.Y H:i', strtotime($commission['odeme_tarihi'])); ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $commission['odeme_yontemi'])); ?></span>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo htmlspecialchars($commission['referans_no'] ?: '-'); ?></small>
                                </td>
                                <td>
                                    <span class="badge-status paid">Ödendi</span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
                        
                        <div class="mb-3">
                            <label for="notes" class="form-label">Notlar</label>
                            <textarea class="form-control" id="notes" name="notes" rows="2" placeholder="Ek notlar..."></textarea>
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        function toggleAllPending(checkbox) {
            const checkboxes = document.querySelectorAll('.pending-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
        }
        
        function selectAllPending() {
            const checkboxes = document.querySelectorAll('.pending-checkbox');
            checkboxes.forEach(cb => cb.checked = true);
            document.getElementById('selectAllPending').checked = true;
        }
        
        function deselectAll() {
            const checkboxes = document.querySelectorAll('.pending-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            document.getElementById('selectAllPending').checked = false;
        }
        
        function confirmBulkPayment() {
            const selected = document.querySelectorAll('.pending-checkbox:checked');
            if (selected.length === 0) {
                alert('Lütfen ödeme yapılacak komisyonları seçin.');
                return false;
            }
            
            const total = Array.from(selected).reduce((sum, cb) => {
                const row = cb.closest('tr');
                const amount = row.querySelector('td:nth-child(7)').textContent.replace(/[^\d]/g, '');
                return sum + parseInt(amount);
            }, 0);
            
            return confirm(`Seçilen ${selected.length} komisyon için toplam ₺${total.toLocaleString('tr-TR')} ödeme yapılacak. Onaylıyor musunuz?`);
        }
        
        function viewModeratorDetails(moderatorId) {
            // Moderator detaylarını göster
            console.log('View moderator details:', moderatorId);
        }
        
        // Auto refresh every 5 minutes
        setInterval(function() {
            location.reload();
        }, 300000);
    </script>
</body>
</html>
