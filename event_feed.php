<?php
session_start();

// Eğer kullanıcı giriş yapmamışsa login'e yönlendir
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
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

// Event ID kontrolü
$event_id = (int)($_GET['event_id'] ?? 0);
if (!$event_id) {
    header('Location: user_dashboard.php');
    exit;
}

// Düğün bilgilerini al
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        k.ad as moderator_ad,
        k.soyad as moderator_soyad,
        p.ad as paket_ad
    FROM dugunler d
    JOIN kullanicilar k ON d.moderator_id = k.id
    LEFT JOIN paketler p ON d.paket_id = p.id
    WHERE d.id = ? AND d.durum = 'aktif'
");
$stmt->execute([$event_id]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: user_dashboard.php');
    exit;
}

// Kullanıcının bu düğüne katılımını kontrol et
$stmt = $pdo->prepare("SELECT rol, yetkiler FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
$stmt->execute([$event_id, $user_id]);
$participation = $stmt->fetch();

if (!$participation) {
    header('Location: user_dashboard.php');
    exit;
}

// ✅ Yeni yetki sistemi - JSON'dan yetkileri parse et
$permissions = [];
if ($participation['yetkiler']) {
    $permissions = json_decode($participation['yetkiler'], true) ?: [];
}

// Yetki değerlerini boolean'a çevir (yeni sistem)
$can_upload_media = in_array('medya_paylasabilir', $permissions);
$can_comment = in_array('yorum_yapabilir', $permissions);
$can_upload_story = in_array('hikaye_paylasabilir', $permissions);

// Medya yükleme işlemi
if ($_POST['action'] ?? '' === 'upload_media') {
    // ✅ Medya paylaşma yetkisi kontrolü
    if (!$can_upload_media) {
        header('Location: event_feed.php?event_id=' . $event_id . '&error=no_media_permission');
        exit;
    }
    
    $caption = trim($_POST['caption']);
    $is_story = isset($_POST['is_story']) ? 1 : 0;
    
    if (isset($_FILES['media']) && $_FILES['media']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['media'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            try {
                $pdo->beginTransaction();
                
                // Dosya adını oluştur
                $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $file_extension;
                $upload_dir = "uploads/events/{$event_id}/";
                
                // Upload dizinini oluştur
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file_path = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    $thumbnail_path = null;
                    
                    // Resim ise thumbnail oluştur
                    if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                        $thumbnail_filename = 'thumb_' . $filename;
                        $thumbnail_path = $upload_dir . 'thumbnails/' . $thumbnail_filename;
                        
                        // Thumbnail dizinini oluştur
                        if (!is_dir($upload_dir . 'thumbnails/')) {
                            mkdir($upload_dir . 'thumbnails/', 0755, true);
                        }
                        
                        // Thumbnail oluştur
                        createThumbnail($file_path, $thumbnail_path, 300, 300);
                    }
                    
                    // Medyayı veritabanına kaydet
                    $stmt = $pdo->prepare("
                        INSERT INTO medyalar (dugun_id, kullanici_id, dosya_yolu, kucuk_resim_yolu, tur, aciklama, hikaye_bitis_tarihi) 
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    
                    $hikaye_bitis_tarihi = $is_story ? date('Y-m-d H:i:s', strtotime('+24 hours')) : null;
                    
                    $stmt->execute([
                        $event_id,
                        $user_id,
                        $file_path,
                        $thumbnail_path,
                        $is_story ? 'hikaye' : ($file_extension === 'mp4' ? 'video' : 'fotograf'),
                        $caption,
                        $hikaye_bitis_tarihi
                    ]);
                    
                    $pdo->commit();
                    
                    header('Location: event_feed.php?event_id=' . $event_id . '&success=media_uploaded');
                    exit;
                    
                } else {
                    throw new Exception('Dosya yüklenemedi');
                }
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $error = 'Medya yüklenirken hata oluştu: ' . $e->getMessage();
            }
        } else {
            $error = 'Geçersiz dosya formatı';
        }
    } else {
        $error = 'Dosya seçilmedi';
    }
}

// Thumbnail oluşturma fonksiyonu
function createThumbnail($source, $destination, $width, $height) {
    $image_info = getimagesize($source);
    $image_type = $image_info[2];
    
    switch ($image_type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    $source_width = imagesx($source_image);
    $source_height = imagesy($source_image);
    
    // Aspect ratio koruyarak boyutlandır
    $ratio = min($width / $source_width, $height / $source_height);
    $new_width = $source_width * $ratio;
    $new_height = $source_height * $ratio;
    
    $thumbnail = imagecreatetruecolor($new_width, $new_height);
    
    // PNG ve GIF için şeffaflık koru
    if ($image_type == IMAGETYPE_PNG || $image_type == IMAGETYPE_GIF) {
        imagealphablending($thumbnail, false);
        imagesavealpha($thumbnail, true);
        $transparent = imagecolorallocatealpha($thumbnail, 255, 255, 255, 127);
        imagefilledrectangle($thumbnail, 0, 0, $new_width, $new_height, $transparent);
    }
    
    imagecopyresampled($thumbnail, $source_image, 0, 0, 0, 0, $new_width, $new_height, $source_width, $source_height);
    
    // JPEG olarak kaydet
    imagejpeg($thumbnail, $destination, 85);
    
    imagedestroy($source_image);
    imagedestroy($thumbnail);
    
    return true;
}

// Düğünün medyalarını al (engellenen kullanıcılar hariç)
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        k.ad as kullanici_ad,
        k.soyad as kullanici_soyad,
        k.profil_fotografi,
        COUNT(DISTINCT b.id) as begeni_sayisi,
        COUNT(DISTINCT y.id) as yorum_sayisi,
        CASE WHEN b2.id IS NOT NULL THEN 1 ELSE 0 END as ben_begendim
    FROM medyalar m
    JOIN kullanicilar k ON m.kullanici_id = k.id
    LEFT JOIN begeniler b ON m.id = b.medya_id
    LEFT JOIN yorumlar y ON m.id = y.medya_id
    LEFT JOIN begeniler b2 ON m.id = b2.medya_id AND b2.kullanici_id = ?
    LEFT JOIN engellenen_kullanicilar ek ON m.dugun_id = ek.dugun_id AND m.kullanici_id = ek.engellenen_kullanici_id
    WHERE m.dugun_id = ? AND m.tur != 'hikaye' AND ek.id IS NULL
    GROUP BY m.id
    ORDER BY m.created_at DESC
    LIMIT 50
");
$stmt->execute([$user_id, $event_id]);
$event_media = $stmt->fetchAll();

// Düğünün hikayelerini al
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        k.ad as kullanici_ad,
        k.soyad as kullanici_soyad,
        k.profil_fotografi
    FROM medyalar m
    JOIN kullanicilar k ON m.kullanici_id = k.id
    LEFT JOIN engellenen_kullanicilar ek ON m.dugun_id = ek.dugun_id AND m.kullanici_id = ek.engellenen_kullanici_id
    WHERE m.dugun_id = ? AND m.tur = 'hikaye' AND ek.id IS NULL
    AND (m.hikaye_bitis_tarihi IS NULL OR m.hikaye_bitis_tarihi > NOW())
    ORDER BY m.created_at DESC
    LIMIT 20
");
$stmt->execute([$event_id]);
$event_stories = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['baslik']); ?> - Digital Salon</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-ui.css" rel="stylesheet">
    
    <!-- Lightbox -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/css/lightbox.min.css" rel="stylesheet">
    
    <style>
        .feed-header {
            background: var(--success-gradient);
            color: white;
            padding: var(--spacing-xl) 0;
            margin-bottom: var(--spacing-xl);
        }
        
        .feed-container {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            overflow: hidden;
        }
        
        .story-container {
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }
        
        .story-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            cursor: pointer;
            transition: all var(--transition-fast);
            padding: var(--spacing-sm);
            border-radius: var(--radius-lg);
            position: relative;
        }
        
        .story-item:hover {
            background: rgba(16, 185, 129, 0.1);
        }
        
        .story-item::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            border-radius: var(--radius-lg);
            background: linear-gradient(45deg, var(--success), var(--primary));
            opacity: 0;
            transition: opacity var(--transition-fast);
            z-index: -1;
        }
        
        .story-item:hover::after {
            opacity: 0.1;
        }
        
        .story-avatar {
            width: 60px;
            height: 60px;
            border-radius: var(--radius-full);
            background: var(--success-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            border: 3px solid white;
            box-shadow: var(--shadow-md);
            position: relative;
            overflow: hidden;
        }
        
        .story-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--radius-full);
        }
        
        .story-name {
            font-size: 0.75rem;
            font-weight: 500;
            color: var(--gray-700);
        }
        
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: var(--spacing-lg);
            padding: var(--spacing-lg);
        }
        
        .media-card {
            background: white;
            border-radius: var(--radius-xl);
            overflow: hidden;
            box-shadow: var(--shadow-md);
            transition: all var(--transition-normal);
            border: 1px solid var(--gray-200);
        }
        
        .media-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .media-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
            background: var(--gray-100);
            cursor: pointer;
        }
        
        .media-video {
            width: 100%;
            height: 300px;
            background: var(--gray-100);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray-500);
            font-size: 2rem;
            cursor: pointer;
        }
        
        .media-content {
            padding: var(--spacing-md);
        }
        
        .media-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: var(--spacing-sm);
        }
        
        .media-user {
            display: flex;
            align-items: center;
            gap: var(--spacing-sm);
        }
        
        .media-avatar {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-full);
            background: var(--success-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .media-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: var(--radius-full);
        }
        
        .media-user-info {
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--gray-800);
        }
        
        .media-actions {
            display: flex;
            gap: var(--spacing-sm);
        }
        
        .media-action {
            background: none;
            border: none;
            color: var(--gray-500);
            cursor: pointer;
            padding: var(--spacing-xs);
            border-radius: var(--radius-sm);
            transition: all var(--transition-fast);
        }
        
        .media-action:hover {
            background: var(--gray-100);
            color: var(--gray-700);
        }
        
        .media-action.liked {
            color: var(--danger);
        }
        
        .media-caption {
            font-size: 0.875rem;
            color: var(--gray-700);
            margin-bottom: var(--spacing-sm);
        }
        
        .media-stats {
            display: flex;
            gap: var(--spacing-md);
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        .upload-fab {
            position: fixed;
            bottom: var(--spacing-xl);
            right: var(--spacing-xl);
            width: 60px;
            height: 60px;
            border-radius: var(--radius-full);
            background: var(--success-gradient);
            color: white;
            border: none;
            box-shadow: var(--shadow-xl);
            font-size: 1.5rem;
            cursor: pointer;
            z-index: 1000;
            transition: all var(--transition-normal);
        }
        
        .upload-fab:hover {
            transform: scale(1.1);
            box-shadow: var(--shadow-2xl);
        }
        
        .empty-state {
            text-align: center;
            padding: var(--spacing-3xl);
            color: var(--gray-500);
        }
        
        .empty-state i {
            font-size: 4rem;
            margin-bottom: var(--spacing-lg);
            opacity: 0.5;
        }
        
        .empty-state h3 {
            margin-bottom: var(--spacing-md);
            color: var(--gray-600);
        }
        
        /* Modal Fixes */
        .modal {
            z-index: 1055 !important;
        }
        
        .modal-backdrop {
            z-index: 1050 !important;
        }
        
        .modal-dialog {
            pointer-events: auto !important;
        }
        
        .modal-content {
            pointer-events: auto !important;
        }
        
        .modal-header,
        .modal-body,
        .modal-footer {
            pointer-events: auto !important;
        }
        
        .modal input,
        .modal textarea,
        .modal button,
        .modal .form-control,
        .modal .form-check-input,
        .modal .form-check-label {
            pointer-events: auto !important;
            opacity: 1 !important;
        }
        
        .modal .btn-close {
            pointer-events: auto !important;
            opacity: 1 !important;
        }
        
        .empty-state p {
            margin-bottom: var(--spacing-lg);
        }
        
        .story-viewer {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            z-index: 2000;
            display: none;
            align-items: center;
            justify-content: center;
        }
        
        .story-viewer.active {
            display: flex;
        }
        
        .story-content {
            max-width: 400px;
            max-height: 80vh;
            background: white;
            border-radius: var(--radius-xl);
            overflow: hidden;
            position: relative;
        }
        
        .story-image {
            width: 100%;
            height: auto;
            max-height: 60vh;
            object-fit: cover;
        }
        
        .story-info {
            padding: var(--spacing-lg);
        }
        
        .story-close {
            position: absolute;
            top: var(--spacing-md);
            right: var(--spacing-md);
            background: rgba(0, 0, 0, 0.5);
            color: white;
            border: none;
            border-radius: var(--radius-full);
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }
        
        .story-progress {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: rgba(255, 255, 255, 0.3);
        }
        
        .story-progress-bar {
            height: 100%;
            background: white;
            width: 0%;
            transition: width 0.1s linear;
        }
        
        @media (max-width: 768px) {
            .media-grid {
                grid-template-columns: 1fr;
                padding: var(--spacing-md);
            }
            
            .upload-fab {
                bottom: var(--spacing-lg);
                right: var(--spacing-lg);
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }
            
            .story-content {
                max-width: 90vw;
                max-height: 90vh;
            }
        }
    </style>
</head>
<body>
    <!-- Feed Header -->
    <div class="feed-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2"><?php echo htmlspecialchars($event['baslik']); ?></h1>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-calendar me-1"></i>
                        <?php echo date('d.m.Y', strtotime($event['dugun_tarihi'])); ?>
                        <span class="mx-2">•</span>
                        <i class="fas fa-user-tie me-1"></i>
                        <?php echo htmlspecialchars($event['moderator_ad'] . ' ' . $event['moderator_soyad']); ?>
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex align-items-center justify-content-end gap-3">
                        <a href="user_dashboard.php" class="btn btn-outline-light">
                            <i class="fas fa-arrow-left me-2"></i>Geri Dön
                        </a>
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-2"></i>Profil
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="user_dashboard.php"><i class="fas fa-home me-2"></i>Ana Sayfa</a></li>
                                <li><a class="dropdown-item" href="qr_scanner.php"><i class="fas fa-qrcode me-2"></i>QR Tarayıcı</a></li>
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
                    case 'media_uploaded':
                        echo 'Medya başarıyla yüklendi!';
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

        <!-- Feed Container -->
        <div class="feed-container">
            <!-- Stories -->
            <?php if (!empty($event_stories)): ?>
                <div class="story-container">
                    <div class="row">
                        <?php foreach ($event_stories as $story): ?>
                            <div class="col-auto">
                                <div class="story-item" onclick="openStory(<?php echo $story['id']; ?>)">
                                    <div class="story-avatar">
                                        <?php if ($story['profil_fotografi']): ?>
                                            <img src="<?php echo htmlspecialchars($story['profil_fotografi']); ?>" alt="Avatar">
                                        <?php else: ?>
                                            <?php echo strtoupper(substr($story['kullanici_ad'], 0, 1)); ?>
                                        <?php endif; ?>
                                    </div>
                                    <div class="story-name"><?php echo htmlspecialchars($story['kullanici_ad']); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Media Grid -->
            <?php if (!empty($event_media)): ?>
                <div class="media-grid">
                    <?php foreach ($event_media as $media): ?>
                        <div class="media-card">
                            <?php if (in_array($media['tur'], ['fotograf'])): ?>
                                <img src="<?php echo htmlspecialchars($media['kucuk_resim_yolu'] ?: $media['dosya_yolu']); ?>" 
                                     alt="<?php echo htmlspecialchars($media['aciklama'] ?: 'Medya'); ?>" 
                                     class="media-image"
                                     onclick="openLightbox('<?php echo htmlspecialchars($media['dosya_yolu']); ?>')">
                            <?php else: ?>
                                <div class="media-video" onclick="playVideo('<?php echo htmlspecialchars($media['dosya_yolu']); ?>')">
                                    <i class="fas fa-play-circle"></i>
                                </div>
                            <?php endif; ?>
                            
                            <div class="media-content">
                                <div class="media-header">
                                    <div class="media-user">
                                        <div class="media-avatar">
                                            <?php if ($media['profil_fotografi']): ?>
                                                <img src="<?php echo htmlspecialchars($media['profil_fotografi']); ?>" alt="Avatar">
                                            <?php else: ?>
                                                <?php echo strtoupper(substr($media['kullanici_ad'], 0, 1)); ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="media-user-info">
                                            <?php echo htmlspecialchars($media['kullanici_ad'] . ' ' . $media['kullanici_soyad']); ?>
                                        </div>
                                    </div>
                                    <div class="media-actions">
                                        <button class="media-action <?php echo $media['ben_begendim'] ? 'liked' : ''; ?>" 
                                                onclick="toggleLike(<?php echo $media['id']; ?>)">
                                            <i class="fas fa-heart"></i>
                                        </button>
                                        <button class="media-action" onclick="openComments(<?php echo $media['id']; ?>)">
                                            <i class="fas fa-comment"></i>
                                        </button>
                                        <button class="media-action" onclick="shareMedia(<?php echo $media['id']; ?>)">
                                            <i class="fas fa-share"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <?php if ($media['aciklama']): ?>
                                    <div class="media-caption"><?php echo htmlspecialchars($media['aciklama']); ?></div>
                                <?php endif; ?>
                                
                                <div class="media-stats">
                                    <span><i class="fas fa-heart me-1"></i><?php echo $media['begeni_sayisi']; ?></span>
                                    <span><i class="fas fa-comment me-1"></i><?php echo $media['yorum_sayisi']; ?></span>
                                    <span><i class="fas fa-eye me-1"></i><?php echo $media['goruntulenme_sayisi']; ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-camera"></i>
                    <h3>Henüz hiçbir medya paylaşılmamış</h3>
                    <p>İlk paylaşımı siz yapın!</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadModal">
                        <i class="fas fa-plus me-2"></i>Medya Paylaş
                    </button>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upload FAB -->
    <button class="upload-fab" data-bs-toggle="modal" data-bs-target="#uploadModal">
        <i class="fas fa-plus"></i>
    </button>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-upload me-2"></i>Medya Paylaş
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload_media">
                        
                        <div class="mb-3">
                            <label for="media" class="form-label">Medya Seçin</label>
                            <input type="file" class="form-control" id="media" name="media" accept="image/*,video/*" required>
                            <div class="form-text">JPG, PNG, GIF, MP4, MOV formatları desteklenir. Maksimum 10MB.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="caption" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="caption" name="caption" rows="3" placeholder="Medyanız hakkında bir şeyler yazın..."></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_story" name="is_story">
                                <label class="form-check-label" for="is_story">
                                    Hikaye olarak paylaş (24 saat sonra silinir)
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i>Paylaş
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Story Viewer -->
    <div class="story-viewer" id="storyViewer">
        <div class="story-content">
            <div class="story-progress">
                <div class="story-progress-bar" id="storyProgressBar"></div>
            </div>
            <button class="story-close" onclick="closeStory()">
                <i class="fas fa-times"></i>
            </button>
            <img id="storyImage" src="" alt="Story" class="story-image">
            <div class="story-info">
                <h6 id="storyUser"></h6>
                <p id="storyCaption"></p>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/lightbox2/2.11.4/js/lightbox.min.js"></script>
    
    <script>
        let storyTimer = null;
        
        function toggleLike(mediaId) {
            fetch('ajax/toggle_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    media_id: mediaId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
        
        function openComments(mediaId) {
            // Yorum modalını aç
            console.log('Comments for media:', mediaId);
        }
        
        function shareMedia(mediaId) {
            if (navigator.share) {
                navigator.share({
                    title: 'Digital Salon - Medya Paylaşımı',
                    text: 'Bu medyayı görüntüleyin!',
                    url: window.location.href
                });
            } else {
                // Fallback: copy to clipboard
                navigator.clipboard.writeText(window.location.href).then(() => {
                    alert('Link kopyalandı!');
                });
            }
        }
        
        function openStory(storyId) {
            // Hikaye verilerini al ve göster
            fetch(`ajax/get_story.php?id=${storyId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('storyImage').src = data.story.dosya_yolu;
                        document.getElementById('storyUser').textContent = data.story.kullanici_ad + ' ' + data.story.kullanici_soyad;
                        document.getElementById('storyCaption').textContent = data.story.baslik || '';
                        
                        document.getElementById('storyViewer').classList.add('active');
                        
                        // Progress bar animasyonu
                        startStoryProgress();
                    }
                });
        }
        
        function closeStory() {
            document.getElementById('storyViewer').classList.remove('active');
            if (storyTimer) {
                clearInterval(storyTimer);
            }
        }
        
        function startStoryProgress() {
            const progressBar = document.getElementById('storyProgressBar');
            let progress = 0;
            
            storyTimer = setInterval(() => {
                progress += 1;
                progressBar.style.width = progress + '%';
                
                if (progress >= 100) {
                    closeStory();
                }
            }, 240); // 24 saniye / 100 = 240ms
        }
        
        function openLightbox(imagePath) {
            // Lightbox aç
            lightbox.open(imagePath);
        }
        
        function playVideo(videoPath) {
            // Video oynatma modalı
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Video</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <video controls style="width: 100%;">
                                <source src="${videoPath}" type="video/mp4">
                                Tarayıcınız video oynatmayı desteklemiyor.
                            </video>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.appendChild(modal);
            const bsModal = new bootstrap.Modal(modal);
            bsModal.show();
            
            modal.addEventListener('hidden.bs.modal', () => {
                document.body.removeChild(modal);
            });
        }
        
        // Auto refresh every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
        
        // Modal fixes
        document.addEventListener('DOMContentLoaded', function() {
            const uploadModal = document.getElementById('uploadModal');
            if (uploadModal) {
                uploadModal.addEventListener('shown.bs.modal', function() {
                    // Force pointer events on all modal elements
                    const modalElements = uploadModal.querySelectorAll('*');
                    modalElements.forEach(function(element) {
                        element.style.pointerEvents = 'auto';
                        element.style.opacity = '1';
                    });
                    
                    // Focus on first input
                    const firstInput = uploadModal.querySelector('input[type="file"]');
                    if (firstInput) {
                        firstInput.focus();
                    }
                });
                
                uploadModal.addEventListener('hide.bs.modal', function() {
                    // Reset form
                    const form = uploadModal.querySelector('form');
                    if (form) {
                        form.reset();
                    }
                });
            }
        });
        
        // ESC tuşu ile story kapatma
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeStory();
            }
        });
    </script>
</body>
</html>
