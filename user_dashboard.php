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

// Engellenen kullanıcı mesajı
$blocked_message = '';
if (isset($_GET['blocked']) && $_GET['blocked'] == '1') {
    $blocked_message = '<div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-ban me-2"></i>
        <strong>Moderatörler Tarafından Engellendiniz!</strong> Bu düğüne erişim yetkiniz bulunmamaktadır.
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>';
}

// Kullanıcının katıldığı düğünleri al (aktif, pasif ve tamamlanmış)
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        k.ad as moderator_ad,
        k.soyad as moderator_soyad,
        p.ad as paket_ad,
        (SELECT COUNT(DISTINCT dk2.kullanici_id) FROM dugun_katilimcilar dk2 WHERE dk2.dugun_id = d.id) as katilimci_sayisi,
        (SELECT COUNT(DISTINCT m.id) FROM medyalar m WHERE m.dugun_id = d.id) as medya_sayisi,
        dk.rol as katilimci_rol,
        dk.katilim_tarihi
    FROM dugun_katilimcilar dk
    JOIN dugunler d ON dk.dugun_id = d.id
    JOIN kullanicilar k ON d.moderator_id = k.id
    LEFT JOIN paketler p ON d.paket_id = p.id
    WHERE dk.kullanici_id = ?
    ORDER BY d.dugun_tarihi DESC
");
$stmt->execute([$user_id]);
$my_events = $stmt->fetchAll();

// Eğer bir düğün seçilmişse, o düğünün feed'ini göster
$selected_event_id = $_GET['event_id'] ?? null;
$selected_event = null;
$event_media = [];
$event_stories = [];

if ($selected_event_id) {
    // Seçilen düğünün bilgilerini al
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            k.ad as moderator_ad,
            k.soyad as moderator_soyad,
            p.ad as paket_ad,
            COUNT(DISTINCT dk2.kullanici_id) as katilimci_sayisi,
            COUNT(DISTINCT m.id) as medya_sayisi,
            dk.rol as katilimci_rol,
            dk.katilim_tarihi
        FROM dugun_katilimcilar dk
        JOIN dugunler d ON dk.dugun_id = d.id
        JOIN kullanicilar k ON d.moderator_id = k.id
        LEFT JOIN paketler p ON d.paket_id = p.id
        LEFT JOIN dugun_katilimcilar dk2 ON d.id = dk2.dugun_id
        LEFT JOIN medyalar m ON d.id = m.dugun_id
        WHERE dk.kullanici_id = ? AND d.id = ?
        GROUP BY d.id
    ");
    $stmt->execute([$user_id, $selected_event_id]);
    $selected_event = $stmt->fetch();

    if ($selected_event) {
        // Düğünün medyalarını al (hikaye hariç)
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                k.ad as user_name,
                k.soyad as user_surname,
                k.profil_fotografi as user_profile
            FROM medyalar m
            JOIN kullanicilar k ON m.kullanici_id = k.id
            WHERE m.dugun_id = ? AND m.tur != 'hikaye'
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$selected_event_id]);
        $event_media = $stmt->fetchAll();

        // Düğünün hikayelerini al
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                k.ad as user_name,
                k.soyad as user_surname,
                k.profil_fotografi as user_profile
            FROM medyalar m
            JOIN kullanicilar k ON m.kullanici_id = k.id
            WHERE m.dugun_id = ? AND m.tur = 'hikaye'
            ORDER BY m.created_at DESC
        ");
        $stmt->execute([$selected_event_id]);
        $event_stories = $stmt->fetchAll();
    }
}

// Medya yükleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_media') {
    if ($selected_event_id && isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/events/' . $selected_event_id . '/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $file_extension = strtolower(pathinfo($_FILES['media_file']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'];
        
        if (in_array($file_extension, $allowed_extensions)) {
            $file_name = uniqid() . '.' . $file_extension;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($_FILES['media_file']['tmp_name'], $file_path)) {
                // Thumbnail oluştur
                $thumbnail_path = null;
                if (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
                    $thumbnail_path = $upload_dir . 'thumb_' . $file_name;
                    createThumbnail($file_path, $thumbnail_path, 300, 300);
                }

                // Veritabanına kaydet
                $stmt = $pdo->prepare("
                    INSERT INTO medyalar (dugun_id, kullanici_id, dosya_yolu, kucuk_resim_yolu, tur, aciklama, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $selected_event_id,
                    $user_id,
                    $file_path,
                    $thumbnail_path,
                    in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']) ? 'fotograf' : 'video',
                    $_POST['description'] ?? ''
                ]);

                header('Location: user_dashboard.php?event_id=' . $selected_event_id);
                exit;
            }
        }
    }
}

// Thumbnail oluşturma fonksiyonu
function createThumbnail($source_path, $thumbnail_path, $width, $height) {
    $image_info = getimagesize($source_path);
    if (!$image_info) return false;

    $source_width = $image_info[0];
    $source_height = $image_info[1];
    $source_type = $image_info[2];

    // Kaynak resmi yükle
    switch ($source_type) {
        case IMAGETYPE_JPEG:
            $source_image = imagecreatefromjpeg($source_path);
            break;
        case IMAGETYPE_PNG:
            $source_image = imagecreatefrompng($source_path);
            break;
        case IMAGETYPE_GIF:
            $source_image = imagecreatefromgif($source_path);
            break;
        default:
            return false;
    }

    // Thumbnail boyutlarını hesapla
    $ratio = min($width / $source_width, $height / $source_height);
    $new_width = $source_width * $ratio;
    $new_height = $source_height * $ratio;

    // Thumbnail oluştur
    $thumbnail = imagecreatetruecolor($new_width, $new_height);
    imagecopyresampled($thumbnail, $source_image, 0, 0, 0, 0, $new_width, $new_height, $source_width, $source_height);

    // Kaydet
    $result = imagejpeg($thumbnail, $thumbnail_path, 90);
    
    // Belleği temizle
    imagedestroy($source_image);
    imagedestroy($thumbnail);
    
    return $result;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Salon - Kullanıcı Paneli</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="assets/css/modern-ui.css">
    
    <style>
        :root {
            --primary-color: #e91e63;
            --secondary-color: #f8f9fa;
            --text-dark: #262626;
            --text-light: #8e8e8e;
            --border-color: #dbdbdb;
        }

        body {
            background-color: var(--secondary-color);
            font-family: 'Poppins', sans-serif;
        }

        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-color), #ff6b9d);
            color: white;
            padding: 2rem 0;
            margin-bottom: 2rem;
        }

        .dashboard-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .dashboard-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .event-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }

        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        }

        .event-card-header {
            background: linear-gradient(135deg, var(--primary-color), #ff6b9d);
            color: white;
            padding: 1.5rem;
        }

        .event-card-body {
            padding: 1.5rem;
        }

        .event-status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
        }

        .event-status-badge.status-aktif {
            background-color: #4caf50;
            color: white;
        }

        .event-status-badge.status-pasif {
            background-color: #ff9800;
            color: white;
        }

        .event-status-badge.status-tamamlandi {
            background-color: #9e9e9e;
            color: white;
        }

        .participant-role-badge {
            background: linear-gradient(45deg, #ffd700, #ffed4e);
            color: #333;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .stats-card {
            background: white;
            border-radius: 15px;
            padding: 1.5rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .stats-card .icon {
            font-size: 2.5rem;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }

        .stats-card h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.5rem;
        }

        .stats-card p {
            color: var(--text-light);
            margin: 0;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #ff6b9d);
            border: none;
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: transform 0.3s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            background: linear-gradient(135deg, #d81b60, #e91e63);
        }

        .btn-outline-primary {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: 25px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-outline-primary:hover {
            background: var(--primary-color);
            color: white;
            transform: translateY(-2px);
        }

        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .media-item {
            position: relative;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .media-item:hover {
            transform: scale(1.05);
        }

        .media-item img,
        .media-item video {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }

        .media-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.7);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 1rem;
            opacity: 0;
            transition: opacity 0.3s ease;
            color: white;
        }

        .media-item:hover .media-overlay {
            opacity: 1;
        }

        .media-overlay i {
            font-size: 1.5rem;
            cursor: pointer;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--text-light);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--border-color);
        }

        .floating-action-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), #ff6b9d);
            color: white;
            border: none;
            box-shadow: 0 5px 15px rgba(0,0,0,0.3);
            font-size: 1.5rem;
            cursor: pointer;
            transition: transform 0.3s ease;
            z-index: 1000;
        }

        .floating-action-btn:hover {
            transform: scale(1.1);
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
            opacity: 1 !important;
        }

        .form-control,
        .form-select,
        .btn {
            pointer-events: auto !important;
            opacity: 1 !important;
        }

        /* Lightbox Styles */
        .lightbox {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .lightbox-content {
            max-width: 90%;
            max-height: 90%;
            position: relative;
        }

        .lightbox-content img,
        .lightbox-content video {
            max-width: 100%;
            max-height: 100%;
            border-radius: 10px;
        }

        .lightbox-close {
            position: absolute;
            top: -40px;
            right: 0;
            background: none;
            border: none;
            color: white;
            font-size: 2rem;
            cursor: pointer;
        }

        @media (max-width: 768px) {
            .dashboard-header h1 {
                font-size: 2rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .media-grid {
                grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>

    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1><i class="fas fa-home me-3"></i>Kullanıcı Paneli</h1>
                    <p>Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı'); ?>!</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="qr_scanner.php" class="btn btn-light btn-lg">
                        <i class="fas fa-qrcode me-2"></i>QR Tarayıcı
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <?php echo $blocked_message; ?>
        
        <?php if (empty($my_events)): ?>
            <div class="row">
                <div class="col-12">
                    <div class="empty-state">
                        <i class="fas fa-calendar-times"></i>
                        <h3>Henüz katıldığınız düğün yok</h3>
                        <p>QR kod tarayarak yeni düğünlere katılabilirsiniz.</p>
                        <a href="qr_scanner.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-qrcode me-2"></i>QR Tarayıcı
                        </a>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3><?php echo count($my_events); ?></h3>
                        <p>Katıldığınız Düğün</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="icon">
                            <i class="fas fa-camera"></i>
                        </div>
                        <h3><?php echo array_sum(array_column($my_events, 'medya_sayisi')); ?></h3>
                        <p>Toplam Medya</p>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="stats-card">
                        <div class="icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3><?php echo array_sum(array_column($my_events, 'katilimci_sayisi')); ?></h3>
                        <p>Toplam Katılımcı</p>
                    </div>
                </div>
            </div>

            <div class="row">
                <?php foreach ($my_events as $event): ?>
                    <div class="col-md-6 col-lg-4">
                        <div class="event-card">
                            <div class="event-card-header">
                                <h5 class="mb-2"><?php echo htmlspecialchars($event['baslik']); ?></h5>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="event-status-badge status-<?php echo $event['durum']; ?>">
                                        <?php echo ucfirst($event['durum']); ?>
                                    </span>
                                    <?php if ($event['katilimci_rol'] === 'yetkili_kullanici'): ?>
                                        <span class="participant-role-badge">
                                            <i class="fas fa-crown"></i>
                                            Yetkili Katılımcı
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="event-card-body">
                                <p class="text-muted mb-3">
                                    <i class="fas fa-calendar me-2"></i>
                                    <?php echo date('d.m.Y', strtotime($event['dugun_tarihi'])); ?>
                                </p>
                                <p class="text-muted mb-3">
                                    <i class="fas fa-user me-2"></i>
                                    Moderatör: <?php echo htmlspecialchars($event['moderator_ad'] . ' ' . $event['moderator_soyad']); ?>
                                </p>
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <strong><?php echo $event['katilimci_sayisi']; ?></strong>
                                        <br><small class="text-muted">Katılımcı</small>
                                    </div>
                                    <div class="col-4">
                                        <strong><?php echo $event['medya_sayisi']; ?></strong>
                                        <br><small class="text-muted">Medya</small>
                                    </div>
                                    <div class="col-4">
                                        <strong><?php echo $event['paket_ad'] ?? 'N/A'; ?></strong>
                                        <br><small class="text-muted">Paket</small>
                                    </div>
                                </div>
                                <div class="action-buttons">
                                    <a href="event.php?id=<?php echo $event['id']; ?>" class="btn btn-primary">
                                        <i class="fas fa-eye me-2"></i>Görüntüle
                                    </a>
                                    <a href="user_dashboard.php?event_id=<?php echo $event['id']; ?>" class="btn btn-outline-primary">
                                        <i class="fas fa-images me-2"></i>Feed
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($selected_event_id && $selected_event): ?>
            <div class="row mt-5">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h4><i class="fas fa-images me-2"></i><?php echo htmlspecialchars($selected_event['baslik']); ?> - Medya Feed</h4>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($event_media)): ?>
                                <div class="media-grid">
                                    <?php foreach ($event_media as $media): ?>
                                        <div class="media-item" data-media-id="<?php echo $media['id']; ?>">
                                            <?php if ($media['tur'] === 'fotograf'): ?>
                                                <img src="<?php echo htmlspecialchars($media['kucuk_resim_yolu'] ?: $media['dosya_yolu']); ?>" 
                                                     alt="<?php echo htmlspecialchars($media['aciklama'] ?: 'Medya'); ?>"
                                                     onclick="openLightbox(<?php echo $media['id']; ?>, '<?php echo htmlspecialchars($media['dosya_yolu']); ?>', '<?php echo $media['tur']; ?>')">
                                            <?php else: ?>
                                                <video src="<?php echo htmlspecialchars($media['dosya_yolu']); ?>" 
                                                       onclick="openLightbox(<?php echo $media['id']; ?>, '<?php echo htmlspecialchars($media['dosya_yolu']); ?>', '<?php echo $media['tur']; ?>')"
                                                       preload="metadata"></video>
                                            <?php endif; ?>
                                            <div class="media-overlay">
                                                <i class="fas fa-heart" onclick="toggleLike(<?php echo $media['id']; ?>)"></i>
                                                <i class="fas fa-comment" onclick="openComments(<?php echo $media['id']; ?>)"></i>
                                                <i class="fas fa-share" onclick="shareMedia(<?php echo $media['id']; ?>)"></i>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-camera"></i>
                                    <h5>Henüz medya yüklenmedi</h5>
                                    <p>Bu düğüne henüz fotoğraf veya video yüklenmemiş.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Floating Action Button for Media Upload -->
    <?php if ($selected_event_id): ?>
        <button class="floating-action-btn" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="fas fa-plus"></i>
        </button>
    <?php endif; ?>

    <!-- Media Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Medya Yükle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload_media">
                        <div class="mb-3">
                            <label for="media_file" class="form-label">Dosya Seç</label>
                            <input type="file" class="form-control" id="media_file" name="media_file" accept="image/*,video/*" required>
                        </div>
                        <div class="mb-3">
                            <label for="description" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="description" name="description" rows="3" placeholder="Medya açıklaması..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">Yükle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Media Lightbox Modal -->
    <div class="modal fade" id="mediaModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Medya</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="mediaContent"></div>
                    <div class="mt-3">
                        <button class="btn btn-outline-danger me-2" onclick="toggleLike()">
                            <i class="fas fa-heart"></i> Beğen
                        </button>
                        <button class="btn btn-outline-primary me-2" onclick="openComments()">
                            <i class="fas fa-comment"></i> Yorumlar
                        </button>
                        <button class="btn btn-outline-success" onclick="shareMedia()">
                            <i class="fas fa-share"></i> Paylaş
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Comments Modal -->
    <div class="modal fade" id="commentsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Yorumlar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="commentsList"></div>
                    <div class="mt-3">
                        <div class="input-group">
                            <input type="text" class="form-control" id="commentInput" placeholder="Yorum yazın...">
                            <button class="btn btn-primary" onclick="addComment()">Gönder</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentMediaId = null;

        function openLightbox(mediaId, mediaSrc, mediaType) {
            currentMediaId = mediaId;
            const mediaContent = document.getElementById('mediaContent');
            
            if (mediaType === 'fotograf') {
                mediaContent.innerHTML = `<img src="${mediaSrc}" class="img-fluid" alt="Medya">`;
            } else {
                mediaContent.innerHTML = `<video src="${mediaSrc}" controls class="img-fluid"></video>`;
            }
            
            const modal = new bootstrap.Modal(document.getElementById('mediaModal'));
            modal.show();
        }

        function toggleLike(mediaId = null) {
            const id = mediaId || currentMediaId;
            if (!id) return;

            fetch('ajax/toggle_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    media_id: id
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    console.log('Like toggled:', data.liked);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function openComments(mediaId = null) {
            const id = mediaId || currentMediaId;
            if (!id) return;

            loadComments(id);
            const modal = new bootstrap.Modal(document.getElementById('commentsModal'));
            modal.show();
        }

        function loadComments(mediaId) {
            fetch(`ajax/get_comments.php?media_id=${mediaId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const commentsList = document.getElementById('commentsList');
                        commentsList.innerHTML = '';
                        
                        data.comments.forEach(comment => {
                            const commentDiv = document.createElement('div');
                            commentDiv.className = 'mb-3 p-3 border rounded';
                            commentDiv.innerHTML = `
                                <div class="d-flex align-items-center mb-2">
                                    <img src="${comment.user_profile_photo}" class="rounded-circle me-2" width="32" height="32">
                                    <strong>${comment.user_name}</strong>
                                    <small class="text-muted ms-auto">${comment.created_at}</small>
                                </div>
                                <p class="mb-0">${comment.content}</p>
                            `;
                            commentsList.appendChild(commentDiv);
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                });
        }

        function addComment() {
            const commentInput = document.getElementById('commentInput');
            const content = commentInput.value.trim();
            
            if (!content || !currentMediaId) return;

            fetch('ajax/add_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    media_id: currentMediaId,
                    content: content
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    commentInput.value = '';
                    loadComments(currentMediaId);
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function shareMedia(mediaId = null) {
            const id = mediaId || currentMediaId;
            if (!id) return;

            // Share functionality
            console.log('Sharing media:', id);
        }

        // Modal event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const uploadModal = document.getElementById('uploadModal');
            if (uploadModal) {
                uploadModal.addEventListener('shown.bs.modal', function() {
                    // Force modal interactivity
                    const formControls = uploadModal.querySelectorAll('.form-control, .form-select, .btn');
                    formControls.forEach(control => {
                        control.style.pointerEvents = 'auto';
                        control.style.opacity = '1';
                    });
                });
            }
        });
    </script>
</body>
</html>