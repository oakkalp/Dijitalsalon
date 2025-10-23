<?php
/**
 * Settings Page
 * Digital Salon - Modern Ayarlar Sayfası
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

$success_message = '';
$error_message = '';

// Profil fotoğrafı yükleme işlemi
if ($_POST['action'] ?? '' === 'update_profile_photo') {
    if (isset($_POST['remove_photo'])) {
        // Profil fotoğrafını kaldır
        $stmt = $pdo->prepare("UPDATE kullanicilar SET profil_fotografi = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        $success_message = 'Profil fotoğrafınız başarıyla kaldırıldı.';
    } elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_photo'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Dosya türü kontrolü
        if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            $error_message = 'Geçersiz dosya türü. Sadece JPG, PNG, GIF formatları desteklenir.';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error_message = 'Dosya çok büyük. Maksimum 5MB yükleyebilirsiniz.';
        } else {
            // Upload dizini oluştur
            $upload_dir = 'uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Benzersiz dosya adı oluştur
            $file_name = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            // Dosyayı yükle
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // Veritabanını güncelle
                $stmt = $pdo->prepare("UPDATE kullanicilar SET profil_fotografi = ? WHERE id = ?");
                $stmt->execute([$file_path, $user_id]);
                $success_message = 'Profil fotoğrafınız başarıyla güncellendi.';
            } else {
                $error_message = 'Dosya yüklenirken bir hata oluştu. Lütfen tekrar deneyin.';
            }
        }
    }
}

// Profil güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $ad = sanitizeInput($_POST['ad'] ?? '');
    $soyad = sanitizeInput($_POST['soyad'] ?? '');
    $telefon = sanitizeInput($_POST['telefon'] ?? '');
    $firma = sanitizeInput($_POST['firma'] ?? '');
    $adres = sanitizeInput($_POST['adres'] ?? '');
    $sehir = sanitizeInput($_POST['sehir'] ?? '');
    $ilce = sanitizeInput($_POST['ilce'] ?? '');
    $posta_kodu = sanitizeInput($_POST['posta_kodu'] ?? '');
    $website = sanitizeInput($_POST['website'] ?? '');
    $notlar = sanitizeInput($_POST['notlar'] ?? '');
    
    try {
        $stmt = $pdo->prepare("
            UPDATE kullanicilar 
            SET ad = ?, soyad = ?, telefon = ?, firma = ?, adres = ?, 
                sehir = ?, ilce = ?, posta_kodu = ?, website = ?, notlar = ?, 
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$ad, $soyad, $telefon, $firma, $adres, $sehir, $ilce, $posta_kodu, $website, $notlar, $user_id]);
        
        $success_message = 'Profil başarıyla güncellendi.';
    } catch (Exception $e) {
        $error_message = 'Profil güncellenirken bir hata oluştu.';
        error_log("Profile update error: " . $e->getMessage());
    }
}

// Şifre değiştirme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = 'Tüm şifre alanları gereklidir.';
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Yeni şifreler eşleşmiyor.';
    } elseif (strlen($new_password) < 8) {
        $error_message = 'Yeni şifre en az 8 karakter olmalıdır.';
    } else {
        try {
            // Mevcut şifreyi kontrol et
            $stmt = $pdo->prepare("SELECT sifre FROM kullanicilar WHERE id = ?");
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();
            
            if (!password_verify($current_password, $user['sifre'])) {
                $error_message = 'Mevcut şifre yanlış.';
            } else {
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE kullanicilar SET sifre = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $user_id]);
                
                $success_message = 'Şifre başarıyla değiştirildi.';
            }
        } catch (Exception $e) {
            $error_message = 'Şifre değiştirilirken bir hata oluştu.';
            error_log("Password change error: " . $e->getMessage());
        }
    }
}

// Platform ayarları güncelleme (sadece super admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_platform']) && $user_role === 'super_admin') {
    $platform_adi = sanitizeInput($_POST['platform_adi'] ?? '');
    $platform_aciklama = sanitizeInput($_POST['platform_aciklama'] ?? '');
    $platform_email = sanitizeInput($_POST['platform_email'] ?? '');
    $platform_telefon = sanitizeInput($_POST['platform_telefon'] ?? '');
    
    try {
        $settings = [
            'platform_adi' => $platform_adi,
            'platform_aciklama' => $platform_aciklama,
            'platform_email' => $platform_email,
            'platform_telefon' => $platform_telefon
        ];
        
        foreach ($settings as $key => $value) {
            $stmt = $pdo->prepare("
                INSERT INTO ayarlar (anahtar, deger, updated_at) 
                VALUES (?, ?, NOW()) 
                ON DUPLICATE KEY UPDATE deger = VALUES(deger), updated_at = NOW()
            ");
            $stmt->execute([$key, $value]);
        }
        
        $success_message = 'Platform ayarları başarıyla güncellendi.';
    } catch (Exception $e) {
        $error_message = 'Platform ayarları güncellenirken bir hata oluştu.';
        error_log("Platform settings error: " . $e->getMessage());
    }
}

// Logo yükleme (sadece super admin)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_logo']) && $user_role === 'super_admin') {
    if (isset($_FILES['logo_upload']) && $_FILES['logo_upload']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/logos/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['logo_upload']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $new_filename = 'logo_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['logo_upload']['tmp_name'], $upload_path)) {
                // Eski logoyu sil
                $stmt = $pdo->query("SELECT deger FROM ayarlar WHERE anahtar = 'platform_logo'");
                $old_logo = $stmt->fetchColumn();
                if ($old_logo && file_exists($old_logo)) {
                    unlink($old_logo);
                }
                
                // Yeni logoyu kaydet
                $stmt = $pdo->prepare("
                    INSERT INTO ayarlar (anahtar, deger, updated_at) 
                    VALUES ('platform_logo', ?, NOW()) 
                    ON DUPLICATE KEY UPDATE deger = VALUES(deger), updated_at = NOW()
                ");
                $stmt->execute([$upload_path]);
                
                $success_message = 'Logo başarıyla yüklendi.';
            } else {
                $error_message = 'Logo yüklenirken bir hata oluştu.';
            }
        } else {
            $error_message = 'Sadece JPG, PNG ve GIF formatları desteklenir.';
        }
    } else {
        $error_message = 'Logo dosyası seçilmedi veya yükleme hatası.';
    }
}

// Kullanıcı bilgilerini al
$stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Platform ayarlarını al (sadece super admin)
$platform_settings = [];
if ($user_role === 'super_admin') {
    $stmt = $pdo->query("SELECT anahtar, deger FROM ayarlar WHERE anahtar LIKE 'platform_%'");
    while ($row = $stmt->fetch()) {
        $platform_settings[$row['anahtar']] = $row['deger'];
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ayarlar - Digital Salon</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-ui.css" rel="stylesheet">
    
    <style>
        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            font-family: var(--font-primary);
            color: var(--gray-800);
        }
        
        .settings-container {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            padding: var(--spacing-2xl);
            margin-bottom: var(--spacing-xl);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-xl);
            margin-bottom: var(--spacing-xl);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }
        
        .page-title {
            font-family: var(--font-heading);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: var(--spacing-sm);
            position: relative;
            z-index: 1;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .nav-pills .nav-link {
            border-radius: var(--radius-md);
            color: #374151 !important;
            font-weight: 500;
            padding: var(--spacing-md) var(--spacing-lg);
            margin-right: var(--spacing-sm);
            transition: all 0.3s ease;
        }
        
        .nav-pills .nav-link.active {
            background: var(--primary-gradient);
            color: white !important;
        }
        
        .nav-pills .nav-link:hover {
            background: var(--gray-100);
            color: #6366f1 !important;
        }
        
        .form-control {
            background: #ffffff !important;
            border: 1px solid #d1d5db !important;
            border-radius: var(--radius-md);
            color: #374151 !important;
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-md);
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: #ffffff !important;
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
            color: #374151 !important;
        }
        
        .form-label {
            color: #374151 !important;
            font-weight: 500;
            margin-bottom: var(--spacing-sm);
        }
        
        .btn-save {
            background: var(--success-gradient);
            border: none;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 600;
            padding: var(--spacing-md) var(--spacing-lg);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
        }
        
        .btn-save:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
        }
        
        .btn-secondary {
            background: var(--gray-500);
            border: none;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 500;
            padding: var(--spacing-md) var(--spacing-lg);
            transition: all 0.3s ease;
        }
        
        .btn-secondary:hover {
            background: var(--gray-600);
            color: var(--white);
        }
        
        .alert {
            border-radius: var(--radius-md);
            border: none;
            margin-bottom: var(--spacing-lg);
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
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: var(--spacing-lg);
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .tab-content {
            color: #374151 !important;
        }
        
        .tab-content h4 {
            color: #1f2937 !important;
            font-weight: 600;
        }
        
        .tab-content h5 {
            color: #1f2937 !important;
            font-weight: 600;
        }
        
        .tab-content p {
            color: #6b7280 !important;
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 3rem;
            font-weight: 600;
            margin: 0 auto var(--spacing-lg);
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .profile-avatar:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-xl);
        }
        
        .profile-photo-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            color: var(--white);
            font-size: 1.5rem;
        }
        
        .profile-avatar:hover .profile-photo-overlay {
            opacity: 1;
        }
        
        /* Modal Fixes */
        .modal {
            z-index: 1055 !important;
        }
        
        .modal-backdrop {
            z-index: 1050 !important;
        }
        
        .modal-content {
            pointer-events: auto !important;
        }
        
        .modal-content * {
            pointer-events: auto !important;
        }
        
        .modal-content input,
        .modal-content button,
        .modal-content label,
        .modal-content .form-check-input,
        .modal-content .form-check-label {
            pointer-events: auto !important;
            opacity: 1 !important;
        }
        
        .stat-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-100);
            margin-bottom: var(--spacing-lg);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-md);
            font-size: 1.5rem;
            color: var(--white);
        }
        
        .stat-icon.profile { background: var(--primary-gradient); }
        .stat-icon.security { background: var(--warning-gradient); }
        .stat-icon.platform { background: var(--info); }
        .stat-icon.commission { background: var(--success-gradient); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
        }
        
        .stat-label {
            color: var(--gray-600);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .logo-preview {
            max-width: 200px;
            max-height: 100px;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .quick-action-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-100);
            cursor: pointer;
        }
        
        .quick-action-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .quick-action-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-md);
            font-size: 1.2rem;
            color: var(--white);
        }
        
        .quick-action-icon.users { background: var(--primary-gradient); }
        .quick-action-icon.packages { background: var(--success-gradient); }
        .quick-action-icon.settings { background: var(--warning-gradient); }
        .quick-action-icon.dashboard { background: var(--info); }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i>Ana Sayfa</a></li>
                <li class="breadcrumb-item active">Ayarlar</li>
            </ol>
        </nav>
        
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-cog me-3"></i>Ayarlar
            </h1>
            <p class="page-subtitle">
                Profil bilgilerinizi güncelleyin, şifrenizi değiştirin ve sistem ayarlarını yönetin
            </p>
        </div>
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <div class="quick-action-card" onclick="location.href='users.php'">
                <div class="quick-action-icon users">
                    <i class="fas fa-users"></i>
                </div>
                <h5>Kullanıcı Yönetimi</h5>
                <p class="text-muted">Kullanıcıları ekle, düzenle ve yönet</p>
            </div>
            <div class="quick-action-card" onclick="location.href='packages.php'">
                <div class="quick-action-icon packages">
                    <i class="fas fa-box"></i>
                </div>
                <h5>Paket Yönetimi</h5>
                <p class="text-muted">Paketleri oluştur ve düzenle</p>
            </div>
            <div class="quick-action-card" onclick="location.href='settings.php'">
                <div class="quick-action-icon settings">
                    <i class="fas fa-cog"></i>
                </div>
                <h5>Sistem Ayarları</h5>
                <p class="text-muted">Platform ayarlarını yönet</p>
            </div>
            <div class="quick-action-card" onclick="location.href='dashboard.php'">
                <div class="quick-action-icon dashboard">
                    <i class="fas fa-tachometer-alt"></i>
                </div>
                <h5>Dashboard</h5>
                <p class="text-muted">Ana sayfaya dön</p>
            </div>
        </div>
        
        <!-- Messages -->
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
        
        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo htmlspecialchars($success_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Settings Tabs -->
        <div class="settings-container">
            <ul class="nav nav-pills mb-4" id="settingsTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="profile-tab" data-bs-toggle="pill" data-bs-target="#profile" type="button" role="tab">
                        <i class="fas fa-user me-2"></i>Profil Bilgileri
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="password-tab" data-bs-toggle="pill" data-bs-target="#password" type="button" role="tab">
                        <i class="fas fa-lock me-2"></i>Şifre Değiştir
                    </button>
                </li>
                <?php if ($user_role === 'super_admin'): ?>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="platform-tab" data-bs-toggle="pill" data-bs-target="#platform" type="button" role="tab">
                        <i class="fas fa-globe me-2"></i>Platform Ayarları
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="commission-tab" data-bs-toggle="pill" data-bs-target="#commission" type="button" role="tab">
                        <i class="fas fa-percentage me-2"></i>Komisyon Ayarları
                    </button>
                </li>
                <?php endif; ?>
            </ul>
            
            <div class="tab-content" id="settingsTabContent">
                <!-- Profile Tab -->
                <div class="tab-pane fade show active" id="profile" role="tabpanel">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="profile-avatar" onclick="openProfilePhotoModal()" style="cursor: pointer;" title="Profil fotoğrafını değiştir">
                                <?php if ($user['profil_fotografi']): ?>
                                    <img src="<?php echo htmlspecialchars($user['profil_fotografi']); ?>" alt="Profil Fotoğrafı" style="width: 100%; height: 100%; object-fit: cover; border-radius: 50%;">
                                <?php else: ?>
                                    <div style="display: flex; align-items: center; justify-content: center; width: 100%; height: 100%; font-size: 2rem; color: var(--white);">
                                        <?php echo strtoupper(substr($user['ad'], 0, 1) . substr($user['soyad'], 0, 1)); ?>
                                    </div>
                                <?php endif; ?>
                                <div class="profile-photo-overlay">
                                    <i class="fas fa-camera"></i>
                                </div>
                            </div>
                            <h4><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></h4>
                            <p class="text-muted mb-2">
                                <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?>
                            </p>
                            <p class="text-muted">
                                <?php 
                                $role_names = [
                                    'super_admin' => 'Super Admin',
                                    'moderator' => 'Moderator',
                                    'kullanici' => 'Normal Kullanıcı'
                                ];
                                echo $role_names[$user['rol']] ?? ucfirst($user['rol']);
                                ?>
                            </p>
                            <div class="stat-card">
                                <div class="stat-icon profile">
                                    <i class="fas fa-calendar"></i>
                                </div>
                                <div class="stat-number"><?php echo date('d.m.Y', strtotime($user['created_at'])); ?></div>
                                <div class="stat-label">Üyelik Tarihi</div>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <form method="POST" action="settings.php">
                                <input type="hidden" name="update_profile" value="1">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="ad" class="form-label">Ad *</label>
                                            <input type="text" class="form-control" id="ad" name="ad" value="<?php echo htmlspecialchars($user['ad']); ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="soyad" class="form-label">Soyad *</label>
                                            <input type="text" class="form-control" id="soyad" name="soyad" value="<?php echo htmlspecialchars($user['soyad']); ?>" required>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="telefon" class="form-label">Telefon</label>
                                            <input type="tel" class="form-control" id="telefon" name="telefon" value="<?php echo htmlspecialchars($user['telefon']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="firma" class="form-label">Firma</label>
                                            <input type="text" class="form-control" id="firma" name="firma" value="<?php echo htmlspecialchars($user['firma']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="adres" class="form-label">Adres</label>
                                    <textarea class="form-control" id="adres" name="adres" rows="2"><?php echo htmlspecialchars($user['adres']); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="sehir" class="form-label">Şehir</label>
                                            <input type="text" class="form-control" id="sehir" name="sehir" value="<?php echo htmlspecialchars($user['sehir']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="ilce" class="form-label">İlçe</label>
                                            <input type="text" class="form-control" id="ilce" name="ilce" value="<?php echo htmlspecialchars($user['ilce']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="posta_kodu" class="form-label">Posta Kodu</label>
                                            <input type="text" class="form-control" id="posta_kodu" name="posta_kodu" value="<?php echo htmlspecialchars($user['posta_kodu']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="website" class="form-label">Website</label>
                                            <input type="url" class="form-control" id="website" name="website" value="<?php echo htmlspecialchars($user['website']); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="notlar" class="form-label">Notlar</label>
                                            <input type="text" class="form-control" id="notlar" name="notlar" value="<?php echo htmlspecialchars($user['notlar']); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="btn btn-save">
                                        <i class="fas fa-save me-2"></i>Profil Güncelle
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Password Tab -->
                <div class="tab-pane fade" id="password" role="tabpanel">
                    <div class="row justify-content-center">
                        <div class="col-md-6">
                            <div class="stat-card">
                                <div class="stat-icon security">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="stat-number">Güvenlik</div>
                                <div class="stat-label">Şifrenizi düzenli olarak güncelleyin</div>
                            </div>
                            
                            <form method="POST" action="settings.php">
                                <input type="hidden" name="change_password" value="1">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Mevcut Şifre *</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">Yeni Şifre *</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                    <small class="text-muted">En az 8 karakter olmalıdır</small>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Yeni Şifre Tekrar *</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="btn btn-save">
                                        <i class="fas fa-key me-2"></i>Şifre Değiştir
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                
                <?php if ($user_role === 'super_admin'): ?>
                <!-- Platform Tab -->
                <div class="tab-pane fade" id="platform" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <form method="POST" action="settings.php">
                                <input type="hidden" name="update_platform" value="1">
                                
                                <div class="mb-3">
                                    <label for="platform_adi" class="form-label">Platform Adı *</label>
                                    <input type="text" class="form-control" id="platform_adi" name="platform_adi" 
                                           value="<?php echo htmlspecialchars($platform_settings['platform_adi'] ?? 'Digital Salon'); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="platform_aciklama" class="form-label">Platform Açıklaması</label>
                                    <textarea class="form-control" id="platform_aciklama" name="platform_aciklama" rows="3"><?php echo htmlspecialchars($platform_settings['platform_aciklama'] ?? ''); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="platform_email" class="form-label">Platform Email</label>
                                            <input type="email" class="form-control" id="platform_email" name="platform_email" 
                                                   value="<?php echo htmlspecialchars($platform_settings['platform_email'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="platform_telefon" class="form-label">Platform Telefon</label>
                                            <input type="tel" class="form-control" id="platform_telefon" name="platform_telefon" 
                                                   value="<?php echo htmlspecialchars($platform_settings['platform_telefon'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="text-end">
                                    <button type="submit" class="btn btn-save">
                                        <i class="fas fa-save me-2"></i>Platform Ayarlarını Kaydet
                                    </button>
                                </div>
                            </form>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-icon platform">
                                    <i class="fas fa-globe"></i>
                                </div>
                                <div class="stat-number">Platform</div>
                                <div class="stat-label">Genel ayarlar</div>
                            </div>
                            
                            <!-- Logo Upload -->
                            <form method="POST" action="settings.php" enctype="multipart/form-data">
                                <input type="hidden" name="upload_logo" value="1">
                                
                                <div class="mb-3">
                                    <label for="logo_upload" class="form-label">Platform Logosu</label>
                                    <input type="file" class="form-control" id="logo_upload" name="logo_upload" accept="image/*">
                                    <small class="text-muted">PNG, JPG, GIF formatları desteklenir. Maksimum 2MB.</small>
                                </div>
                                
                                <?php 
                                $current_logo = $platform_settings['platform_logo'] ?? '';
                                if ($current_logo && file_exists($current_logo)): 
                                ?>
                                    <div class="mb-3">
                                        <label class="form-label">Mevcut Logo</label>
                                        <img src="<?php echo htmlspecialchars($current_logo); ?>" alt="Mevcut Logo" class="logo-preview d-block">
                                    </div>
                                <?php endif; ?>
                                
                                <button type="submit" class="btn btn-save w-100">
                                    <i class="fas fa-upload me-2"></i>Logo Yükle
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
                
                <!-- Commission Tab -->
                <div class="tab-pane fade" id="commission" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <h4>Moderator Komisyon Oranları</h4>
                            <p class="text-muted">Her moderator için özel komisyon oranları belirleyin</p>
                            
                            <?php
                            $moderators = $pdo->query("SELECT id, ad, soyad, komisyon_orani FROM kullanicilar WHERE rol = 'moderator' ORDER BY ad, soyad")->fetchAll();
                            if (!empty($moderators)):
                            ?>
                                <form method="POST" action="settings.php">
                                    <input type="hidden" name="update_commissions" value="1">
                                    
                                    <?php foreach ($moderators as $moderator): ?>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label class="form-label"><?php echo htmlspecialchars($moderator['ad'] . ' ' . $moderator['soyad']); ?></label>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="input-group">
                                                <input type="number" step="0.01" min="0" max="100" class="form-control" 
                                                       name="commission_rates[<?php echo $moderator['id']; ?>]" 
                                                       value="<?php echo $moderator['komisyon_orani']; ?>">
                                                <span class="input-group-text">%</span>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                    
                                    <div class="text-end">
                                        <button type="submit" class="btn btn-save">
                                            <i class="fas fa-save me-2"></i>Komisyon Oranlarını Kaydet
                                        </button>
                                    </div>
                                </form>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-user-tie fa-3x text-muted mb-3"></i>
                                    <h5 class="text-muted">Moderator bulunamadı</h5>
                                    <p class="text-muted">Henüz sistemde moderator bulunmuyor.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <div class="stat-icon commission">
                                    <i class="fas fa-percentage"></i>
                                </div>
                                <div class="stat-number">Komisyon</div>
                                <div class="stat-label">Moderator oranları</div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Profile Photo Upload Modal -->
    <div class="modal fade" id="profilePhotoModal" tabindex="-1" aria-labelledby="profilePhotoModalLabel" aria-hidden="true" data-bs-backdrop="true" data-bs-keyboard="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="profilePhotoModalLabel">
                        <i class="fas fa-camera me-2"></i>Profil Fotoğrafını Değiştir
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_profile_photo">
                        
                        <div class="mb-3">
                            <label for="profile_photo" class="form-label">Yeni Profil Fotoğrafı</label>
                            <input type="file" class="form-control" id="profile_photo" name="profile_photo" accept="image/*" required>
                            <div class="form-text">JPG, PNG, GIF formatları desteklenir. Maksimum 5MB.</div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="remove_photo" name="remove_photo">
                                <label class="form-check-label" for="remove_photo">
                                    Profil fotoğrafını kaldır
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Profil fotoğrafı modal açma fonksiyonu
        function openProfilePhotoModal() {
            const modal = new bootstrap.Modal(document.getElementById('profilePhotoModal'));
            modal.show();
            
            // Modal açıldığında event listener'ları ekle
            document.getElementById('profilePhotoModal').addEventListener('shown.bs.modal', function() {
                // Form elemanlarını aktif et
                const inputs = this.querySelectorAll('input, button, label');
                inputs.forEach(element => {
                    element.style.pointerEvents = 'auto';
                    element.style.opacity = '1';
                });
                
                // ESC tuşu ile kapatma
                document.addEventListener('keydown', function(e) {
                    if (e.key === 'Escape') {
                        modal.hide();
                    }
                });
            });
            
            // Modal kapandığında event listener'ları temizle
            document.getElementById('profilePhotoModal').addEventListener('hidden.bs.modal', function() {
                document.removeEventListener('keydown', arguments.callee);
            });
        }
        
        // Şifre eşleşme kontrolü
        document.getElementById('confirm_password').addEventListener('input', function() {
            const newPassword = document.getElementById('new_password').value;
            const confirmPassword = this.value;
            
            if (newPassword !== confirmPassword) {
                this.setCustomValidity('Şifreler eşleşmiyor');
            } else {
                this.setCustomValidity('');
            }
        });
        
        // Form validasyonu
        document.addEventListener('DOMContentLoaded', function() {
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
                        alert('Lütfen tüm zorunlu alanları doldurun.');
                    }
                });
            });
        });
    </script>
</body>
</html>