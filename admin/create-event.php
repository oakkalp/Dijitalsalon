<?php
require_once '../config/database.php';

// Thumbnail oluşturma fonksiyonu
function createThumbnail($source_path, $thumbnail_path, $max_width, $max_height) {
    try {
        // Dosya türünü kontrol et
        $image_info = getimagesize($source_path);
        if (!$image_info) {
            return false;
        }
        
        $source_width = $image_info[0];
        $source_height = $image_info[1];
        $mime_type = $image_info['mime'];
        
        // Kaynak görüntüyü yükle
        switch ($mime_type) {
            case 'image/jpeg':
                $source_image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $source_image = imagecreatefrompng($source_path);
                break;
            case 'image/gif':
                $source_image = imagecreatefromgif($source_path);
                break;
            default:
                return false;
        }
        
        if (!$source_image) {
            return false;
        }
        
        // Thumbnail boyutlarını hesapla (aspect ratio korunarak)
        $ratio = min($max_width / $source_width, $max_height / $source_height);
        $thumbnail_width = intval($source_width * $ratio);
        $thumbnail_height = intval($source_height * $ratio);
        
        // Thumbnail görüntüsü oluştur
        $thumbnail_image = imagecreatetruecolor($thumbnail_width, $thumbnail_height);
        
        // PNG şeffaflığını koru
        if ($mime_type === 'image/png') {
            imagealphablending($thumbnail_image, false);
            imagesavealpha($thumbnail_image, true);
            $transparent = imagecolorallocatealpha($thumbnail_image, 255, 255, 255, 127);
            imagefill($thumbnail_image, 0, 0, $transparent);
        }
        
        // Görüntüyü yeniden boyutlandır
        imagecopyresampled(
            $thumbnail_image, $source_image,
            0, 0, 0, 0,
            $thumbnail_width, $thumbnail_height,
            $source_width, $source_height
        );
        
        // Thumbnail'i kaydet
        $result = false;
        switch ($mime_type) {
            case 'image/jpeg':
                $result = imagejpeg($thumbnail_image, $thumbnail_path, 85);
                break;
            case 'image/png':
                $result = imagepng($thumbnail_image, $thumbnail_path, 8);
                break;
            case 'image/gif':
                $result = imagegif($thumbnail_image, $thumbnail_path);
                break;
        }
        
        // Belleği temizle
        imagedestroy($source_image);
        imagedestroy($thumbnail_image);
        
        return $result;
        
    } catch (Exception $e) {
        return false;
    }
}

// Admin kontrolü
session_start();
if (!isset($_SESSION['admin_user_id']) || !isset($_SESSION['admin_user_role'])) {
    header('Location: index.php');
    exit;
}

$admin_user_id = $_SESSION['admin_user_id'];
$admin_user_role = $_SESSION['admin_user_role'];

// Sadece moderator ve super admin erişebilir
if (!in_array($admin_user_role, ['super_admin', 'moderator'])) {
    header('Location: dashboard.php');
    exit;
}

$success_message = '';
$error_message = '';

// Paketleri al
try {
    $stmt = $pdo->query("SELECT id, ad, aciklama, fiyat, sure_ay, maksimum_katilimci, medya_limiti, ucretsiz_erisim_gun FROM paketler WHERE durum = 'aktif' ORDER BY id");
    $packages = $stmt->fetchAll();
} catch (Exception $e) {
    $packages = [];
}

// Düğün oluşturma işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_event') {
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date = $_POST['date'] ?? '';
    $time = $_POST['time'] ?? ''; // ✅ Saat alanı eklendi
    $salon_adresi = trim($_POST['salon_adresi'] ?? '');
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    $package_id = (int)($_POST['package_id'] ?? 1);
    
    if (empty($title) || empty($date) || empty($time)) {
        $error_message = 'Düğün başlığı, tarihi ve saati zorunludur';
    } else {
        try {
            $pdo->beginTransaction();
            
            // Paket bilgilerini al
            $stmt = $pdo->prepare("SELECT sure_ay FROM paketler WHERE id = ?");
            $stmt->execute([$package_id]);
            $package = $stmt->fetch(PDO::FETCH_ASSOC);
            $sure_ay = $package ? (int)$package['sure_ay'] : 6; // Varsayılan 6 ay
            
            // Tarih hesaplamaları
            $olusturulma_tarihi = date('Y-m-d');
            $dugun_tarihi = $date;
            
            // Bitiş tarihi = oluşturulma tarihi + süre
            $bitis_tarihi_obj = new DateTime($olusturulma_tarihi);
            $bitis_tarihi_obj->modify('+' . $sure_ay . ' months');
            $bitis_tarihi = $bitis_tarihi_obj->format('Y-m-d');
            
            // Düğün oluştur
            $slug = 'dugun-' . preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($title)) . '-' . time();
            
            // QR kod oluştur (mobil uygulama formatında) - önce ID olmadan oluştur, sonra güncelle
            $qr_code = 'QR_' . substr(md5(time() . uniqid()), 0, 10) . '_' . substr(md5(uniqid()), 0, 8);
            
            $stmt = $pdo->prepare("
                INSERT INTO dugunler (
                    baslik, slug, aciklama, dugun_tarihi, saat, salon_adresi, latitude, longitude, moderator_id, 
                    paket_id, qr_kod, olusturulma_tarihi, bitis_tarihi, sure_ay, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$title, $slug, $description, $dugun_tarihi, $time, $salon_adresi, $latitude, $longitude, $admin_user_id, $package_id, $qr_code, $olusturulma_tarihi, $bitis_tarihi, $sure_ay]);
            $event_id = $pdo->lastInsertId();
            
            // QR kod'u event_id ile güncelle (daha unique olsun)
            $qr_code = 'QR_' . substr(md5($event_id . time()), 0, 10) . '_' . substr(md5($event_id . uniqid()), 0, 8);
            $stmt = $pdo->prepare("UPDATE dugunler SET qr_kod = ? WHERE id = ?");
            $stmt->execute([$qr_code, $event_id]);
            
            // Creator'ı otomatik olarak katılımcı yap
            $stmt = $pdo->prepare("
                INSERT INTO dugun_katilimcilar (
                    dugun_id, kullanici_id, rol, durum, katilim_tarihi
                ) VALUES (?, ?, 'admin', 'aktif', NOW())
            ");
            $stmt->execute([$event_id, $admin_user_id]);
            
            // Kapak fotoğrafı yükleme
            if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/events/' . $event_id . '/';
                
                // Klasör oluştur
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file = $_FILES['cover_photo'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'event_cover_' . $event_id . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    $cover_path = 'uploads/events/' . $event_id . '/' . $filename;
                    
                    // Thumbnail oluştur (mobil uygulama için)
                    $thumbnail_filename = 'event_cover_' . $event_id . '_' . time() . '_thumb.' . $file_extension;
                    $thumbnail_path = $upload_dir . $thumbnail_filename;
                    
                    if (createThumbnail($file_path, $thumbnail_path, 300, 200)) {
                        $thumbnail_db_path = 'uploads/events/' . $event_id . '/' . $thumbnail_filename;
                        $stmt = $pdo->prepare("UPDATE dugunler SET kapak_fotografi = ?, thumbnail_fotografi = ? WHERE id = ?");
                        $stmt->execute([$cover_path, $thumbnail_db_path, $event_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE dugunler SET kapak_fotografi = ? WHERE id = ?");
                        $stmt->execute([$cover_path, $event_id]);
                    }
                }
            }
            
            $pdo->commit();
            $success_message = 'Düğün başarıyla oluşturuldu! QR Kod: ' . $qr_code;
            
            // Başarılı oluşturma sonrası events sayfasına yönlendir
            header('Location: events.php?success=' . urlencode($success_message));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = 'Düğün oluşturulurken hata oluştu: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Yeni Düğün Oluştur - Dijitalsalon Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyC-L4E5--L2M9dDvyLmcP-t9G2r84Y8GDY&callback=initMap" async defer></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .header h1 {
            color: #2d3748;
            font-size: 2rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header p {
            color: #718096;
            font-size: 1.1rem;
        }

        .form-container {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
            margin-bottom: 2rem;
        }

        .form-section {
            background: #f8fafc;
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid #e2e8f0;
        }

        .form-section h3 {
            color: #2d3748;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1.2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #2d3748;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        textarea.form-control {
            resize: vertical;
            min-height: 120px;
        }

        .package-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .package-card {
            border: 2px solid #e2e8f0;
            border-radius: 15px;
            padding: 1.5rem;
            background: white;
            transition: all 0.3s ease;
            cursor: pointer;
            position: relative;
        }

        .package-card:hover {
            border-color: #6366f1;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.15);
        }

        .package-card.selected {
            border-color: #6366f1;
            background: #f0f4ff;
        }

        .package-card input[type="radio"] {
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 20px;
            height: 20px;
        }

        .package-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .package-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #6366f1;
            margin-bottom: 1rem;
        }

        .package-description {
            color: #718096;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .package-features {
            list-style: none;
        }

        .package-features li {
            padding: 0.25rem 0;
            color: #4a5568;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .package-features li i {
            color: #48bb78;
            font-size: 0.9rem;
        }

        .file-upload {
            border: 2px dashed #cbd5e0;
            border-radius: 15px;
            padding: 2rem;
            text-align: center;
            background: #f8fafc;
            transition: all 0.3s ease;
        }

        .file-upload:hover {
            border-color: #6366f1;
            background: #f0f4ff;
        }

        .file-upload.dragover {
            border-color: #6366f1;
            background: #f0f4ff;
        }

        .file-upload i {
            font-size: 3rem;
            color: #a0aec0;
            margin-bottom: 1rem;
        }

        .file-upload p {
            color: #718096;
            margin-bottom: 1rem;
        }

        .file-upload input[type="file"] {
            display: none;
        }

        .upload-btn {
            background: #6366f1;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .upload-btn:hover {
            background: #5b21b6;
            transform: translateY(-1px);
        }

        .preview-image {
            max-width: 200px;
            max-height: 200px;
            border-radius: 10px;
            margin-top: 1rem;
            display: none;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }

        .btn {
            padding: 1rem 2rem;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
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
            box-shadow: 0 8px 25px rgba(99, 102, 241, 0.3);
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background: #cbd5e0;
            transform: translateY(-1px);
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #f0fff4;
            border: 1px solid #9ae6b4;
            color: #22543d;
        }

        .alert-error {
            background: #fed7d7;
            border: 1px solid #feb2b2;
            color: #742a2a;
        }

        .required {
            color: #e53e3e;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }
            
            .package-grid {
                grid-template-columns: 1fr;
            }
            
            .container {
                padding: 1rem;
            }
        }

        .form-text {
            font-size: 0.875rem;
            color: #a0aec0;
            margin-top: 0.25rem;
        }

        .map-container {
            margin-top: 1rem;
        }

        .map-info {
            margin-top: 1rem;
            padding: 1rem;
            background: #f7fafc;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .map-info p {
            margin: 0 0 0.5rem 0;
            color: #4a5568;
            font-size: 0.9rem;
        }

        .coordinates {
            display: flex;
            gap: 2rem;
            font-size: 0.875rem;
            color: #2d3748;
            font-weight: 500;
        }

        .coordinates span {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        /* Adres Arama Butonu Stilleri */
        .address-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .address-input-container .form-control {
            padding-right: 50px;
        }

        .search-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            background: #6366f1;
            color: white;
            border: none;
            border-radius: 6px;
            padding: 8px 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
        }

        .search-btn:hover {
            background: #4f46e5;
            transform: translateY(-50%) scale(1.05);
        }

        .search-btn:active {
            transform: translateY(-50%) scale(0.95);
        }

        .search-btn i {
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>
                <i class="fas fa-plus-circle"></i>
                Yeni Düğün Oluştur
            </h1>
            <p>Mobil uygulama ile uyumlu düğün etkinliği oluşturun</p>
        </div>

        <!-- Form Container -->
        <div class="form-container">
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="createEventForm">
                <input type="hidden" name="action" value="create_event">
                
                <div class="form-grid">
                    <!-- Temel Bilgiler -->
                    <div class="form-section">
                        <h3>
                            <i class="fas fa-info-circle"></i>
                            Temel Bilgiler
                        </h3>
                        
                        <div class="form-group">
                            <label for="title">Düğün Başlığı <span class="required">*</span></label>
                            <input type="text" id="title" name="title" class="form-control" required
                                   placeholder="Örn: Seda & Cem'in Düğünü">
                        </div>

                        <div class="form-group">
                            <label for="description">Açıklama</label>
                            <textarea id="description" name="description" class="form-control"
                                      placeholder="Düğün hakkında detaylı bilgi..."></textarea>
                        </div>

                        <div class="form-group">
                            <label for="date">Düğün Tarihi <span class="required">*</span></label>
                            <input type="date" id="date" name="date" class="form-control" required>
                        </div>

                        <div class="form-group">
                            <label for="time">Düğün Saati <span class="required">*</span></label>
                            <input type="time" id="time" name="time" class="form-control" required>
                            <small class="form-text">Düğünün başlayacağı saati girin</small>
                        </div>

                        <div class="form-group">
                            <label for="salon_adresi">Düğün Salonu Adresi</label>
                            <div class="address-input-container">
                                <input type="text" id="salon_adresi" name="salon_adresi" class="form-control"
                                       placeholder="Örn: Hilton Otel, Beşiktaş, İstanbul">
                                <button type="button" id="searchAddressBtn" class="search-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <small class="form-text">Adresi yazın ve haritadan konumu seçin veya arama butonuna tıklayın</small>
                            
                            <!-- Harita Konumu -->
                            <div class="map-container">
                                <div id="map" style="height: 400px; width: 100%; border-radius: 10px; border: 2px solid #e2e8f0;"></div>
                                <div class="map-info">
                                    <p><i class="fas fa-info-circle"></i> Haritaya tıklayarak düğün salonunun konumunu belirleyin</p>
                                    <div class="coordinates">
                                        <span>Enlem: <span id="lat-display">-</span></span>
                                        <span>Boylam: <span id="lng-display">-</span></span>
                                    </div>
                                </div>
                                <input type="hidden" id="latitude" name="latitude" value="">
                                <input type="hidden" id="longitude" name="longitude" value="">
                            </div>
                        </div>
                    </div>

                    <!-- Paket Seçimi -->
                    <div class="form-section">
                        <h3>
                            <i class="fas fa-gem"></i>
                            Paket Seçimi
                        </h3>
                        
                        <div class="package-grid">
                            <?php foreach ($packages as $index => $package): ?>
                                <div class="package-card <?php echo $index === 0 ? 'selected' : ''; ?>" onclick="selectPackage(<?php echo $package['id']; ?>)">
                                    <input type="radio" name="package_id" value="<?php echo $package['id']; ?>" 
                                           id="package_<?php echo $package['id']; ?>" required <?php echo $index === 0 ? 'checked' : ''; ?>>
                                    
                                    <div class="package-name"><?php echo htmlspecialchars($package['ad']); ?></div>
                                    
                                    <?php if (!empty($package['fiyat'])): ?>
                                        <div class="package-price">₺<?php echo number_format($package['fiyat']); ?></div>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($package['aciklama'])): ?>
                                        <div class="package-description"><?php echo htmlspecialchars($package['aciklama']); ?></div>
                                    <?php endif; ?>
                                    
                                    <!-- Gerçek Paket Özellikleri -->
                                    <ul class="package-features">
                                        <?php if (!empty($package['sure_ay'])): ?>
                                            <li><i class="fas fa-calendar-alt"></i> <?php echo $package['sure_ay']; ?> Ay Saklama Süresi</li>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($package['maksimum_katilimci'])): ?>
                                            <li><i class="fas fa-users"></i> Maksimum <?php echo number_format($package['maksimum_katilimci']); ?> Katılımcı</li>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($package['medya_limiti'])): ?>
                                            <li><i class="fas fa-images"></i> <?php echo number_format($package['medya_limiti']); ?> Medya Limiti</li>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($package['ucretsiz_erisim_gun'])): ?>
                                            <li><i class="fas fa-clock"></i> <?php echo $package['ucretsiz_erisim_gun']; ?> Gün Ücretsiz Erişim</li>
                                        <?php endif; ?>
                                    </ul>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Kapak Fotoğrafı -->
                <div class="form-section">
                    <h3>
                        <i class="fas fa-camera"></i>
                        Kapak Fotoğrafı
                    </h3>
                    
                    <div class="file-upload" id="fileUpload">
                        <i class="fas fa-cloud-upload-alt"></i>
                        <p>Düğün kapak fotoğrafını yükleyin</p>
                        <p style="font-size: 0.9rem; color: #a0aec0;">JPG, PNG, GIF formatları desteklenir. Maksimum 5MB.</p>
                        <button type="button" class="upload-btn" onclick="document.getElementById('cover_photo').click()">
                            <i class="fas fa-upload"></i>
                            Fotoğraf Seç
                        </button>
                        <input type="file" id="cover_photo" name="cover_photo" accept="image/*" onchange="previewImage(this)">
                        <img id="preview" class="preview-image" alt="Önizleme">
                    </div>
                </div>

                <!-- Form Actions -->
                <div class="form-actions">
                    <a href="events.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Geri Dön
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i>
                        Düğün Oluştur
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Paket seçimi
        function selectPackage(packageId) {
            // Tüm paket kartlarından seçili sınıfını kaldır
            document.querySelectorAll('.package-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Seçilen paketi işaretle
            document.getElementById('package_' + packageId).checked = true;
            document.getElementById('package_' + packageId).closest('.package-card').classList.add('selected');
        }

        // Fotoğraf önizleme
        function previewImage(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const preview = document.getElementById('preview');
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Drag & Drop
        const fileUpload = document.getElementById('fileUpload');
        
        fileUpload.addEventListener('dragover', (e) => {
            e.preventDefault();
            fileUpload.classList.add('dragover');
        });
        
        fileUpload.addEventListener('dragleave', () => {
            fileUpload.classList.remove('dragover');
        });
        
        fileUpload.addEventListener('drop', (e) => {
            e.preventDefault();
            fileUpload.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                document.getElementById('cover_photo').files = files;
                previewImage(document.getElementById('cover_photo'));
            }
        });

        // Form validasyonu
        document.getElementById('createEventForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const date = document.getElementById('date').value;
            const packageSelected = document.querySelector('input[name="package_id"]:checked');
            
            if (!title) {
                e.preventDefault();
                alert('Düğün başlığı zorunludur!');
                document.getElementById('title').focus();
                return;
            }
            
            if (!date) {
                e.preventDefault();
                alert('Düğün tarihi zorunludur!');
                document.getElementById('date').focus();
                return;
            }
            
            if (!packageSelected) {
                e.preventDefault();
                alert('Lütfen bir paket seçin!');
                return;
            }
        });

        // Bugünün tarihini minimum tarih olarak ayarla
        document.getElementById('date').min = new Date().toISOString().split('T')[0];

        // Harita fonksiyonları
        let map;
        let marker;

        function initMap() {
            // İstanbul merkez koordinatları
            const istanbul = { lat: 41.0082, lng: 28.9784 };
            
            map = new google.maps.Map(document.getElementById('map'), {
                zoom: 12,
                center: istanbul,
                mapTypeId: 'roadmap'
            });

            // Manuel adres arama fonksiyonu
            function searchAddress() {
                const address = document.getElementById('salon_adresi').value.trim();
                
                if (!address) {
                    alert('Lütfen bir adres girin');
                    return;
                }

                const geocoder = new google.maps.Geocoder();
                
                geocoder.geocode({ 
                    address: address,
                    componentRestrictions: { country: 'TR' }
                }, function(results, status) {
                    if (status === 'OK' && results[0]) {
                        const place = results[0];
                        
                        console.log('Bulunan yer:', place);
                        
                        // Haritayı bulunan yere odakla
                        map.setCenter(place.geometry.location);
                        map.setZoom(16);

                        // Önceki marker'ı sil
                        if (marker) {
                            marker.setMap(null);
                        }

                        // Yeni marker ekle
                        marker = new google.maps.Marker({
                            position: place.geometry.location,
                            map: map,
                            title: place.formatted_address,
                            animation: google.maps.Animation.DROP
                        });

                        // Koordinatları form alanlarına kaydet
                        const lat = place.geometry.location.lat();
                        const lng = place.geometry.location.lng();
                        
                        document.getElementById('latitude').value = lat;
                        document.getElementById('longitude').value = lng;
                        
                        // Koordinatları ekranda göster
                        document.getElementById('lat-display').textContent = lat.toFixed(6);
                        document.getElementById('lng-display').textContent = lng.toFixed(6);
                        
                        // Adres alanını tam adres ile güncelle
                        document.getElementById('salon_adresi').value = place.formatted_address;
                        
                        console.log('Adres bulundu ve koordinatlar kaydedildi:', lat, lng);
                    } else {
                        console.log('Adres bulunamadı:', status);
                        alert('Adres bulunamadı. Lütfen daha detaylı bir adres girin.\n\nÖrnekler:\n- "Hilton Otel, Beşiktaş, İstanbul"\n- "Çırağan Sarayı, Beşiktaş, İstanbul"\n- "Düğün Salonu, Kadıköy, İstanbul"');
                    }
                });
            }

            // Arama butonu event listener
            document.getElementById('searchAddressBtn').addEventListener('click', searchAddress);

            // Enter tuşu ile arama
            document.getElementById('salon_adresi').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    searchAddress();
                }
            });

            // Harita tıklama olayı
            map.addListener('click', function(event) {
                const lat = event.latLng.lat();
                const lng = event.latLng.lng();
                
                // Önceki marker'ı sil
                if (marker) {
                    marker.setMap(null);
                }
                
                // Yeni marker ekle
                marker = new google.maps.Marker({
                    position: { lat: lat, lng: lng },
                    map: map,
                    title: 'Düğün Salonu Konumu'
                });
                
                // Koordinatları form alanlarına kaydet
                document.getElementById('latitude').value = lat;
                document.getElementById('longitude').value = lng;
                
                // Koordinatları ekranda göster
                document.getElementById('lat-display').textContent = lat.toFixed(6);
                document.getElementById('lng-display').textContent = lng.toFixed(6);

                // Tersine geocoding ile adres bilgisini al
                const geocoder = new google.maps.Geocoder();
                geocoder.geocode({ location: { lat: lat, lng: lng } }, function(results, status) {
                    if (status === 'OK' && results[0]) {
                        document.getElementById('salon_adresi').value = results[0].formatted_address;
                    }
                });
            });
        }

        // Sayfa yüklendiğinde haritayı başlat
        window.onload = function() {
            initMap();
        };
    </script>
</body>
</html>
