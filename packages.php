<?php
/**
 * Package Management Page
 * Digital Salon - Paket yönetimi sayfası
 */

require_once 'config/database.php';
require_once 'includes/security.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Sadece super admin erişebilir
if ($user_role !== 'super_admin') {
    header('Location: dashboard.php');
    exit();
}

$success_message = '';
$error_message = '';

// Paket ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_package'])) {
    $ad = sanitizeInput($_POST['ad'] ?? '');
    $aciklama = sanitizeInput($_POST['aciklama'] ?? '');
    $sure_ay = (int)($_POST['sure_ay'] ?? 1);
    $maksimum_katilimci = (int)($_POST['maksimum_katilimci'] ?? 100);
    $fiyat = (float)($_POST['fiyat'] ?? 0);
    $medya_limiti = (int)($_POST['medya_limiti'] ?? 25);
    $ucretsiz_erisim_gun = (int)($_POST['ucretsiz_erisim_gun'] ?? 7);
    $komisyon_orani = 20.00; // Varsayılan komisyon oranı %20
    $moderator_id = !empty($_POST['moderator_id']) ? (int)$_POST['moderator_id'] : null;
    $ozellikler = isset($_POST['ozellikler']) ? json_encode($_POST['ozellikler']) : null;
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO paketler 
            (ad, aciklama, sure_ay, maksimum_katilimci, fiyat, medya_limiti, ucretsiz_erisim_gun, komisyon_orani, moderator_id, aktif_mi, ozellikler) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE, ?)
        ");
        $stmt->execute([$ad, $aciklama, $sure_ay, $maksimum_katilimci, $fiyat, $medya_limiti, $ucretsiz_erisim_gun, $komisyon_orani, $moderator_id, $ozellikler]);
        
        $success_message = 'Paket başarıyla eklendi.';
    } catch (Exception $e) {
        $error_message = 'Paket eklenirken bir hata oluştu.';
        error_log("Package add error: " . $e->getMessage());
    }
}

// Paket düzenleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_package'])) {
    $edit_package_id = (int)$_POST['edit_package_id'];
    $ad = sanitizeInput($_POST['edit_ad'] ?? '');
    $aciklama = sanitizeInput($_POST['edit_aciklama'] ?? '');
    $sure_ay = (int)($_POST['edit_sure_ay'] ?? 1);
    $maksimum_katilimci = (int)($_POST['edit_maksimum_katilimci'] ?? 100);
    $fiyat = (float)($_POST['edit_fiyat'] ?? 0);
    $medya_limiti = (int)($_POST['edit_medya_limiti'] ?? 25);
    $ucretsiz_erisim_gun = (int)($_POST['edit_ucretsiz_erisim_gun'] ?? 7);
    $komisyon_orani = 20.00; // Varsayılan komisyon oranı %20
    $moderator_id = !empty($_POST['edit_moderator_id']) ? (int)$_POST['edit_moderator_id'] : null;
    $aktif_mi = isset($_POST['edit_aktif_mi']) ? 1 : 0;
    $ozellikler = isset($_POST['edit_ozellikler']) ? json_encode($_POST['edit_ozellikler']) : null;
    
    try {
        $stmt = $pdo->prepare("
            UPDATE paketler 
            SET ad = ?, aciklama = ?, sure_ay = ?, maksimum_katilimci = ?, fiyat = ?, 
                medya_limiti = ?, ucretsiz_erisim_gun = ?, komisyon_orani = ?, moderator_id = ?, aktif_mi = ?, ozellikler = ?
            WHERE id = ?
        ");
        $stmt->execute([$ad, $aciklama, $sure_ay, $maksimum_katilimci, $fiyat, $medya_limiti, $ucretsiz_erisim_gun, $komisyon_orani, $moderator_id, $aktif_mi, $ozellikler, $edit_package_id]);
        
        $success_message = 'Paket başarıyla güncellendi.';
    } catch (Exception $e) {
        $error_message = 'Paket güncellenirken bir hata oluştu.';
        error_log("Package edit error: " . $e->getMessage());
    }
}

// Paket silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_package'])) {
    $delete_package_id = (int)$_POST['delete_package_id'];
    
    try {
        // Önce bu paketi kullanan düğünleri kontrol et
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM dugunler WHERE paket_id = ?");
        $stmt->execute([$delete_package_id]);
        $usage_count = $stmt->fetchColumn();
        
        if ($usage_count > 0) {
            $error_message = 'Bu paket kullanıldığı için silinemez. Önce paketi pasif yapın.';
        } else {
            $stmt = $pdo->prepare("DELETE FROM paketler WHERE id = ?");
            $stmt->execute([$delete_package_id]);
            
            $success_message = 'Paket başarıyla silindi.';
        }
    } catch (Exception $e) {
        $error_message = 'Paket silinirken bir hata oluştu.';
        error_log("Package delete error: " . $e->getMessage());
    }
}

// Paketleri listele
$stmt = $pdo->query("
    SELECT 
        p.*,
        k.ad as moderator_ad,
        k.soyad as moderator_soyad,
        (SELECT COUNT(*) FROM dugunler WHERE paket_id = p.id) as kullanim_sayisi
    FROM paketler p
    LEFT JOIN kullanicilar k ON p.moderator_id = k.id
    ORDER BY p.olusturma_tarihi DESC
");
$packages = $stmt->fetchAll();

// Moderatorları listele
$stmt = $pdo->query("SELECT id, ad, soyad FROM kullanicilar WHERE rol = 'moderator' ORDER BY ad, soyad");
$moderators = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paket Yönetimi - Digital Salon</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-ui.css" rel="stylesheet">
    
    <style>
        /* Global Styles */
        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            font-family: var(--font-primary);
            color: var(--gray-800);
        }
        
        .packages-container {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            padding: var(--spacing-2xl);
            margin-bottom: var(--spacing-xl);
        }
        
        .packages-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
            color: var(--gray-800);
        }
        
        .packages-header h1 {
            font-family: var(--font-heading);
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            color: var(--gray-800);
        }
        
        .packages-header p {
            color: var(--gray-600);
            font-size: 0.9rem;
        }
        
        .btn-add-package {
            background: var(--success-gradient);
            border: none;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 600;
            padding: var(--spacing-md) var(--spacing-xl);
            transition: all 0.3s ease;
        }
        
        .btn-add-package:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
        }
        
        .btn-edit {
            background: var(--primary-gradient);
            border: none;
            border-radius: var(--radius-sm);
            color: var(--white);
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            transition: all 0.3s ease;
        }
        
        .btn-edit:hover {
            transform: translateY(-1px);
            color: var(--white);
        }
        
        .btn-delete {
            background: var(--danger-gradient);
            border: none;
            border-radius: var(--radius-sm);
            color: var(--white);
            font-size: 0.8rem;
            padding: 0.25rem 0.75rem;
            transition: all 0.3s ease;
        }
        
        .btn-delete:hover {
            transform: translateY(-1px);
            color: var(--white);
        }
        
        .table-modern {
            background: var(--white);
            border-radius: var(--radius-lg);
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
            vertical-align: middle;
        }
        
        .table-modern tbody tr:hover {
            background-color: var(--gray-50);
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-aktif {
            background: var(--success);
            color: var(--white);
        }
        
        .status-pasif {
            background: var(--gray-400);
            color: var(--white);
        }
        
        .form-control {
            background: var(--white);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            color: var(--gray-800);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-md);
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: var(--white);
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            color: var(--gray-800);
        }
        
        .form-label {
            color: var(--gray-700);
            font-weight: 500;
            margin-bottom: var(--spacing-sm);
        }
        
        .alert {
            border-radius: var(--radius-md);
            border: none;
            margin-bottom: var(--spacing-md);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .modal-content {
            background: var(--white);
            border-radius: var(--radius-xl);
            border: none;
            box-shadow: var(--shadow-xl);
        }
        
        .modal-header {
            background: var(--primary-gradient);
            color: var(--white);
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
            border: none;
        }
        
        .modal-footer {
            border: none;
            background: var(--gray-50);
            border-radius: 0 0 var(--radius-xl) var(--radius-xl);
        }
        
        .package-card {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-md);
            border: 1px solid var(--gray-200);
        }
        
        .package-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--primary);
            margin-bottom: var(--spacing-sm);
        }
        
        .package-features {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .package-features li {
            padding: 0.25rem 0;
            color: var(--gray-600);
        }
        
        .package-features li i {
            color: var(--success);
            margin-right: var(--spacing-sm);
        }
        
        /* Modal Form Fixes - Daha Güçlü Kurallar */
        .modal .form-control,
        .modal input[type="text"],
        .modal input[type="number"],
        .modal textarea,
        .modal select {
            background: #ffffff !important;
            border: 2px solid #d1d5db !important;
            border-radius: 8px !important;
            color: #374151 !important;
            padding: 12px !important;
            margin-bottom: 16px !important;
            transition: all 0.3s ease !important;
            pointer-events: auto !important;
            opacity: 1 !important;
            width: 100% !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
            box-sizing: border-box !important;
        }
        
        .modal .form-control:focus,
        .modal input:focus,
        .modal textarea:focus,
        .modal select:focus {
            background: #ffffff !important;
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
            color: #374151 !important;
            outline: none !important;
        }
        
        .modal .form-control:hover,
        .modal input:hover,
        .modal textarea:hover,
        .modal select:hover {
            border-color: #6366f1 !important;
        }
        
        .modal .form-check-input {
            pointer-events: auto !important;
            opacity: 1 !important;
            cursor: pointer !important;
        }
        
        .modal .form-check-label {
            pointer-events: auto !important;
            opacity: 1 !important;
            color: #374151 !important;
            cursor: pointer !important;
        }
        
        .modal .form-label {
            color: #374151 !important;
            font-weight: 500 !important;
            margin-bottom: 8px !important;
            display: block !important;
        }
        
        .modal .btn {
            pointer-events: auto !important;
            opacity: 1 !important;
            cursor: pointer !important;
        }
        
        /* Modal backdrop sorunu için */
        .modal-backdrop {
            z-index: 1040 !important;
        }
        
        .modal {
            z-index: 1050 !important;
        }
        
        /* Checkbox özel kuralları */
        .modal input[type="checkbox"] {
            pointer-events: auto !important;
            opacity: 1 !important;
            cursor: pointer !important;
            width: 18px !important;
            height: 18px !important;
            margin-right: 8px !important;
            background: white !important;
            border: 2px solid #ddd !important;
            appearance: checkbox !important;
            -webkit-appearance: checkbox !important;
            -moz-appearance: checkbox !important;
        }
        
        .modal input[type="checkbox"]:checked {
            background: #6366f1 !important;
            border-color: #6366f1 !important;
        }
        
        .modal .form-check-label {
            pointer-events: auto !important;
            cursor: pointer !important;
            color: black !important;
            font-size: 14px !important;
            margin-left: 5px !important;
        }
        
        .modal .form-check {
            margin-bottom: 10px !important;
            display: flex !important;
            align-items: center !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-5 flex-grow-1">
        <div class="packages-container">
            <div class="packages-header">
                <h1><i class="fas fa-box me-2"></i>Paket Yönetimi</h1>
                <p>Düğün paketlerini oluşturun, düzenleyin ve yönetin</p>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- Add Package Button -->
            <div class="text-end mb-4">
                <button class="btn btn-add-package" data-bs-toggle="modal" data-bs-target="#addPackageModal">
                    <i class="fas fa-plus me-2"></i>Yeni Paket Ekle
                </button>
            </div>
            
            <!-- Packages Grid -->
            <div class="row">
                <?php foreach ($packages as $package): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="package-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="mb-0"><?php echo htmlspecialchars($package['ad']); ?></h5>
                                <span class="status-badge status-<?php echo $package['aktif_mi'] ? 'aktif' : 'pasif'; ?>">
                                    <?php echo $package['aktif_mi'] ? 'Aktif' : 'Pasif'; ?>
                                </span>
                            </div>
                            
                            <div class="package-price">₺<?php echo number_format($package['fiyat'], 0, ',', '.'); ?></div>
                            
                            <?php if ($package['aciklama']): ?>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars($package['aciklama']); ?></p>
                            <?php endif; ?>
                            
                            <ul class="package-features">
                                <!-- Paket Özellikleri -->
                                <li><i class="fas fa-camera"></i><?php echo $package['medya_limiti']; ?> fotoğraf ya da videoya kadar</li>
                                <li><i class="fas fa-clock"></i><?php echo $package['ucretsiz_erisim_gun']; ?> gün boyunca ücretsiz erişim</li>
                                <li><i class="fas fa-calendar"></i><?php echo $package['sure_ay']; ?> ay profil erişimi</li>
                                <li><i class="fas fa-cloud-upload-alt"></i>Otomatik Yedekleme</li>
                                <li><i class="fas fa-hd-video"></i>Yüksek Çözünürlük Desteği</li>
                                <li><i class="fas fa-share-alt"></i>Filigran Olmadan Paylaşım</li>
                            </ul>
                            
                            <div class="btn-group w-100" role="group">
                                <button class="btn btn-edit" onclick="editPackage(<?php echo htmlspecialchars(json_encode($package)); ?>)">
                                    <i class="fas fa-edit"></i> Düzenle
                                </button>
                                <?php if ($package['kullanim_sayisi'] == 0): ?>
                                <button class="btn btn-delete" onclick="deletePackage(<?php echo $package['id']; ?>, '<?php echo htmlspecialchars($package['ad']); ?>')">
                                    <i class="fas fa-trash"></i> Sil
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Package Modal -->
    <div class="modal fade" id="addPackageModal" tabindex="-1" aria-labelledby="addPackageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addPackageModalLabel">
                        <i class="fas fa-plus me-2"></i>Yeni Paket Ekle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="packages.php">
                    <div class="modal-body">
                        <input type="hidden" name="add_package" value="1">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ad" class="form-label">Paket Adı *</label>
                                    <input type="text" class="form-control" id="ad" name="ad" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="fiyat" class="form-label">Fiyat (₺) *</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="fiyat" name="fiyat" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="2" style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="sure_ay" class="form-label">Süre (Ay) *</label>
                                    <input type="number" min="1" max="12" class="form-control" id="sure_ay" name="sure_ay" value="1" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="medya_limiti" class="form-label">Medya Limiti *</label>
                                    <input type="number" min="1" class="form-control" id="medya_limiti" name="medya_limiti" value="25" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="ucretsiz_erisim_gun" class="form-label">Ücretsiz Erişim (Gün) *</label>
                                    <input type="number" min="1" max="30" class="form-control" id="ucretsiz_erisim_gun" name="ucretsiz_erisim_gun" value="7" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                    <small class="text-muted">Düğün tarihinden sonra kaç gün medya yüklenebilir</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="maksimum_katilimci" class="form-label">Maks. Katılımcı</label>
                                    <input type="number" min="1" class="form-control" id="maksimum_katilimci" name="maksimum_katilimci" value="100" style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="ozellikler" class="form-label">Paket Özellikleri</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="ozellik1" name="ozellikler[]" value="Otomatik Yedekleme" checked style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; width: 18px !important; height: 18px !important; margin-right: 8px !important;">
                                        <label class="form-check-label" for="ozellik1" style="pointer-events: auto !important; cursor: pointer !important; color: black !important; font-size: 14px !important;">Otomatik Yedekleme</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="ozellik2" name="ozellikler[]" value="Yüksek Çözünürlük Desteği" checked style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; width: 18px !important; height: 18px !important; margin-right: 8px !important;">
                                        <label class="form-check-label" for="ozellik2" style="pointer-events: auto !important; cursor: pointer !important; color: black !important; font-size: 14px !important;">Yüksek Çözünürlük Desteği</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="ozellik3" name="ozellikler[]" value="Filigran Olmadan Paylaşım" checked style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; width: 18px !important; height: 18px !important; margin-right: 8px !important;">
                                        <label class="form-check-label" for="ozellik3" style="pointer-events: auto !important; cursor: pointer !important; color: black !important; font-size: 14px !important;">Filigran Olmadan Paylaşım</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="ozellik4" name="ozellikler[]" value="7/24 Destek" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; width: 18px !important; height: 18px !important; margin-right: 8px !important;">
                                        <label class="form-check-label" for="ozellik4" style="pointer-events: auto !important; cursor: pointer !important; color: black !important; font-size: 14px !important;">7/24 Destek</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="ozellik5" name="ozellikler[]" value="Sınırsız Depolama" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; width: 18px !important; height: 18px !important; margin-right: 8px !important;">
                                        <label class="form-check-label" for="ozellik5" style="pointer-events: auto !important; cursor: pointer !important; color: black !important; font-size: 14px !important;">Sınırsız Depolama</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="ozellik6" name="ozellikler[]" value="Özel Tasarım" style="pointer-events: auto !important; opacity: 1 !important; cursor: pointer !important; width: 18px !important; height: 18px !important; margin-right: 8px !important;">
                                        <label class="form-check-label" for="ozellik6" style="pointer-events: auto !important; cursor: pointer !important; color: black !important; font-size: 14px !important;">Özel Tasarım</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="moderator_id" class="form-label">Moderator (Opsiyonel)</label>
                                    <select class="form-control" id="moderator_id" name="moderator_id" style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                        <option value="">Genel Paket</option>
                                        <?php foreach ($moderators as $moderator): ?>
                                            <option value="<?php echo $moderator['id']; ?>">
                                                <?php echo htmlspecialchars($moderator['ad'] . ' ' . $moderator['soyad']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="pointer-events: auto !important;">İptal</button>
                        <button type="submit" class="btn btn-primary" style="pointer-events: auto !important;">
                            <i class="fas fa-save me-2"></i>Paket Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Package Modal -->
    <div class="modal fade" id="editPackageModal" tabindex="-1" aria-labelledby="editPackageModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editPackageModalLabel">
                        <i class="fas fa-edit me-2"></i>Paket Düzenle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="packages.php">
                    <div class="modal-body">
                        <input type="hidden" name="edit_package" value="1">
                        <input type="hidden" name="edit_package_id" id="edit_package_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_ad" class="form-label">Paket Adı *</label>
                                    <input type="text" class="form-control" id="edit_ad" name="edit_ad" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_fiyat" class="form-label">Fiyat (₺) *</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="edit_fiyat" name="edit_fiyat" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="edit_aciklama" name="edit_aciklama" rows="2"></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_sure_ay" class="form-label">Süre (Ay) *</label>
                                    <input type="number" min="1" max="12" class="form-control" id="edit_sure_ay" name="edit_sure_ay" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_medya_limiti" class="form-label">Medya Limiti *</label>
                                    <input type="number" min="1" class="form-control" id="edit_medya_limiti" name="edit_medya_limiti" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="mb-3">
                                    <label for="edit_ucretsiz_erisim_gun" class="form-label">Ücretsiz Erişim (Gün) *</label>
                                    <input type="number" min="1" max="30" class="form-control" id="edit_ucretsiz_erisim_gun" name="edit_ucretsiz_erisim_gun" required>
                                    <small class="text-muted">Düğün tarihinden sonra kaç gün medya yüklenebilir</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_maksimum_katilimci" class="form-label">Maks. Katılımcı</label>
                                    <input type="number" min="1" class="form-control" id="edit_maksimum_katilimci" name="edit_maksimum_katilimci">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_ozellikler" class="form-label">Paket Özellikleri</label>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_ozellik1" name="edit_ozellikler[]" value="Otomatik Yedekleme">
                                        <label class="form-check-label" for="edit_ozellik1">Otomatik Yedekleme</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_ozellik2" name="edit_ozellikler[]" value="Yüksek Çözünürlük Desteği">
                                        <label class="form-check-label" for="edit_ozellik2">Yüksek Çözünürlük Desteği</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_ozellik3" name="edit_ozellikler[]" value="Filigran Olmadan Paylaşım">
                                        <label class="form-check-label" for="edit_ozellik3">Filigran Olmadan Paylaşım</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_ozellik4" name="edit_ozellikler[]" value="7/24 Destek">
                                        <label class="form-check-label" for="edit_ozellik4">7/24 Destek</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_ozellik5" name="edit_ozellikler[]" value="Sınırsız Depolama">
                                        <label class="form-check-label" for="edit_ozellik5">Sınırsız Depolama</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" id="edit_ozellik6" name="edit_ozellikler[]" value="Özel Tasarım">
                                        <label class="form-check-label" for="edit_ozellik6">Özel Tasarım</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_moderator_id" class="form-label">Moderator (Opsiyonel)</label>
                                    <select class="form-control" id="edit_moderator_id" name="edit_moderator_id">
                                        <option value="">Genel Paket</option>
                                        <?php foreach ($moderators as $moderator): ?>
                                            <option value="<?php echo $moderator['id']; ?>">
                                                <?php echo htmlspecialchars($moderator['ad'] . ' ' . $moderator['soyad']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="edit_aktif_mi" name="edit_aktif_mi">
                                <label class="form-check-label" for="edit_aktif_mi">
                                    Paket Aktif
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Değişiklikleri Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Package Modal -->
    <div class="modal fade" id="deletePackageModal" tabindex="-1" aria-labelledby="deletePackageModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deletePackageModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Paket Sil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="packages.php">
                    <div class="modal-body">
                        <input type="hidden" name="delete_package" value="1">
                        <input type="hidden" name="delete_package_id" id="delete_package_id">
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Dikkat!</strong> Bu işlem geri alınamaz. Paket kalıcı olarak silinecektir.
                        </div>
                        
                        <p>Şu paketi silmek istediğinizden emin misiniz?</p>
                        <div class="form-control" id="delete_package_name" style="background: var(--gray-100);"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Paketi Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editPackage(package) {
            document.getElementById('edit_package_id').value = package.id;
            document.getElementById('edit_ad').value = package.ad;
            document.getElementById('edit_aciklama').value = package.aciklama || '';
            document.getElementById('edit_sure_ay').value = package.sure_ay;
            document.getElementById('edit_maksimum_katilimci').value = package.maksimum_katilimci;
            document.getElementById('edit_fiyat').value = package.fiyat;
            document.getElementById('edit_medya_limiti').value = package.medya_limiti;
            document.getElementById('edit_ucretsiz_erisim_gun').value = package.ucretsiz_erisim_gun || 7;
            document.getElementById('edit_moderator_id').value = package.moderator_id || '';
            document.getElementById('edit_aktif_mi').checked = package.aktif_mi == 1;
            
            // Özellikleri yükle
            const checkboxes = document.querySelectorAll('input[name="edit_ozellikler[]"]');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            
            if (package.ozellikler) {
                try {
                    const features = JSON.parse(package.ozellikler);
                    features.forEach(feature => {
                        checkboxes.forEach(checkbox => {
                            if (checkbox.value === feature) {
                                checkbox.checked = true;
                            }
                        });
                    });
                } catch (e) {
                    console.log('Özellikler parse edilemedi:', e);
                }
            }
            
            new bootstrap.Modal(document.getElementById('editPackageModal')).show();
        }
        
        function deletePackage(packageId, packageName) {
            document.getElementById('delete_package_id').value = packageId;
            document.getElementById('delete_package_name').textContent = packageName;
            
            new bootstrap.Modal(document.getElementById('deletePackageModal')).show();
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            // Modal açıldığında form alanlarını aktif hale getir
            const addPackageModal = document.getElementById('addPackageModal');
            if (addPackageModal) {
                addPackageModal.addEventListener('shown.bs.modal', function() {
                    console.log('Add Package Modal açıldı');
                    
                    // Tüm form alanlarını bul ve aktif hale getir
                    const allInputs = this.querySelectorAll('input, textarea, select, button');
                    allInputs.forEach(element => {
                        element.style.pointerEvents = 'auto';
                        element.style.opacity = '1';
                        element.disabled = false;
                        element.readOnly = false;
                        
                        // Özel olarak input alanları için
                        if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                            element.style.background = '#ffffff';
                            element.style.border = '2px solid #d1d5db';
                            element.style.color = '#374151';
                        }
                    });
                    
                    // Checkbox'ları özel olarak aktif hale getir
                    const checkboxes = this.querySelectorAll('input[type="checkbox"]');
                    checkboxes.forEach(checkbox => {
                        checkbox.style.pointerEvents = 'auto';
                        checkbox.style.opacity = '1';
                        checkbox.disabled = false;
                        checkbox.readOnly = false;
                        checkbox.style.cursor = 'pointer';
                        checkbox.style.width = '18px';
                        checkbox.style.height = '18px';
                        
                        // Checkbox'a click event ekle
                        checkbox.addEventListener('click', function(e) {
                            console.log('Checkbox clicked:', this.value, this.checked);
                        });
                    });
                    
                    // Label'lara da click event ekle
                    const labels = this.querySelectorAll('.form-check-label');
                    labels.forEach(label => {
                        label.style.pointerEvents = 'auto';
                        label.style.cursor = 'pointer';
                        label.style.color = 'black';
                        
                        label.addEventListener('click', function(e) {
                            const checkbox = this.previousElementSibling;
                            if (checkbox && checkbox.type === 'checkbox') {
                                checkbox.checked = !checkbox.checked;
                                console.log('Label clicked, checkbox toggled:', checkbox.value, checkbox.checked);
                            }
                        });
                    });
                    
                    // İlk input'a focus ver
                    const firstInput = this.querySelector('input[type="text"]');
                    if (firstInput) {
                        setTimeout(() => {
                            firstInput.focus();
                        }, 100);
                    }
                });
            }
            
            const editPackageModal = document.getElementById('editPackageModal');
            if (editPackageModal) {
                editPackageModal.addEventListener('shown.bs.modal', function() {
                    console.log('Edit Package Modal açıldı');
                    
                    // Tüm form alanlarını bul ve aktif hale getir
                    const allInputs = this.querySelectorAll('input, textarea, select, button');
                    allInputs.forEach(element => {
                        element.style.pointerEvents = 'auto';
                        element.style.opacity = '1';
                        element.disabled = false;
                        element.readOnly = false;
                        
                        // Özel olarak input alanları için
                        if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA') {
                            element.style.background = '#ffffff';
                            element.style.border = '2px solid #d1d5db';
                            element.style.color = '#374151';
                        }
                    });
                });
            }
            
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            isValid = false;
                            field.classList.add('is-invalid');
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    });
                    
                    if (!isValid) {
                        e.preventDefault();
                        alert('Lütfen tüm gerekli alanları doldurun.');
                        return false;
                    }
                });
            });
        });
    </script>
</body>
</html>
