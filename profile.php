<?php
/**
 * Profile Page
 * Digital Salon - Profil sayfası
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

// Profil fotoğrafı yükleme işlemi
if ($_POST['action'] ?? '' === 'update_profile_photo') {
    if (isset($_POST['remove_photo'])) {
        // Profil fotoğrafını kaldır
        $stmt = $pdo->prepare("UPDATE kullanicilar SET profil_fotografi = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        header('Location: profile.php?success=photo_removed');
        exit;
    } elseif (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_photo'];
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        // Dosya türü kontrolü
        if (!in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            header('Location: profile.php?error=invalid_file_type');
            exit;
        }
        
        // Dosya boyutu kontrolü (5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            header('Location: profile.php?error=file_too_large');
            exit;
        }
        
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
            header('Location: profile.php?success=photo_updated');
            exit;
        } else {
            header('Location: profile.php?error=upload_failed');
            exit;
        }
    }
}

// Kullanıcı bilgilerini çek
$stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    header('Location: logout.php');
    exit();
}

// Kullanıcının katıldığı düğünleri çek
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        k.ad as moderator_ad,
        k.soyad as moderator_soyad,
        dk.rol as katilim_rolu,
        dk.katilim_tarihi
    FROM dugunler d
    JOIN dugun_katilimcilar dk ON d.id = dk.dugun_id
    JOIN kullanicilar k ON d.moderator_id = k.id
    WHERE dk.kullanici_id = ?
    ORDER BY d.dugun_tarihi DESC
");
$stmt->execute([$user_id]);
$participated_events = $stmt->fetchAll();

// Kullanıcının paylaştığı medyaları çek
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        d.baslik as dugun_baslik
    FROM medyalar m
    JOIN dugunler d ON m.dugun_id = d.id
    WHERE m.kullanici_id = ?
    ORDER BY m.created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$user_media = $stmt->fetchAll();

// İstatistikler
$stats = [
    'total_events' => count($participated_events),
    'total_media' => 0,
    'total_likes' => 0,
    'total_comments' => 0
];

try {
    // Medya sayısı
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM medyalar WHERE kullanici_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_media'] = $stmt->fetchColumn();

    // Beğeni sayısı
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM begeniler b JOIN medyalar m ON b.medya_id = m.id WHERE m.kullanici_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_likes'] = $stmt->fetchColumn();

    // Yorum sayısı
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM yorumlar y JOIN medyalar m ON y.medya_id = m.id WHERE m.kullanici_id = ?");
    $stmt->execute([$user_id]);
    $stats['total_comments'] = $stmt->fetchColumn();
} catch (Exception $e) {
    // Hata durumunda varsayılan değerleri kullan
    error_log("Profile stats error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profil - <?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?> - Digital Salon</title>
    
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
        
        .profile-header {
            background: var(--primary-gradient);
            color: var(--white);
            padding: 3rem 0;
            margin-bottom: 2rem;
        }
        
        .profile-card {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            padding: var(--spacing-2xl);
            margin-bottom: var(--spacing-xl);
            color: var(--gray-800);
        }
        
        .profile-avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: var(--secondary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            color: var(--white);
            margin: 0 auto 1rem;
            border: 4px solid rgba(255, 255, 255, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
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
            border-radius: 50%;
        }
        
        .profile-avatar:hover .profile-photo-overlay {
            opacity: 1;
        }
        
        .profile-photo-overlay i {
            color: white;
            font-size: 1.5rem;
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
        
        .profile-name {
            font-family: var(--font-heading);
            font-size: 2rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: 0.5rem;
            color: var(--white);
        }
        
        .profile-role {
            text-align: center;
            opacity: 0.8;
            margin-bottom: 1rem;
            color: var(--white);
        }
        
        .profile-info {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .info-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
            color: var(--gray-700);
        }
        
        .info-item:last-child {
            margin-bottom: 0;
        }
        
        .info-item i {
            width: 20px;
            margin-right: 0.75rem;
            opacity: 0.8;
            color: var(--primary);
        }
        
        .info-item a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .info-item a:hover {
            text-decoration: underline;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: var(--spacing-md);
            margin-bottom: var(--spacing-xl);
        }
        
        .stat-card {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            text-align: center;
            border: 1px solid var(--gray-200);
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: var(--primary);
        }
        
        .stat-label {
            font-size: 0.9rem;
            color: var(--gray-600);
        }
        
        .event-card {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-md);
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }
        
        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .event-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--gray-800);
        }
        
        .event-meta {
            font-size: 0.9rem;
            color: var(--gray-600);
            margin-bottom: 0.5rem;
        }
        
        .event-role {
            display: inline-block;
            background: var(--primary);
            color: var(--white);
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .media-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: var(--spacing-md);
        }
        
        .media-item {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            overflow: hidden;
            border: 1px solid var(--gray-200);
            transition: all 0.3s ease;
        }
        
        .media-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .media-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            background: var(--gray-200);
        }
        
        .media-info {
            padding: var(--spacing-md);
        }
        
        .media-title {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--gray-800);
        }
        
        .media-meta {
            font-size: 0.8rem;
            color: var(--gray-600);
        }
        
        .btn-edit-profile {
            background: var(--primary-gradient);
            border: none;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 600;
            padding: var(--spacing-md) var(--spacing-xl);
            transition: all 0.3s ease;
        }
        
        .btn-edit-profile:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
        }
        
        .opacity-50 {
            opacity: 0.5;
        }
        
        .opacity-75 {
            opacity: 0.75;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php
                switch ($_GET['success']) {
                    case 'photo_updated':
                        echo '<i class="fas fa-check-circle me-2"></i>Profil fotoğrafınız başarıyla güncellendi.';
                        break;
                    case 'photo_removed':
                        echo '<i class="fas fa-check-circle me-2"></i>Profil fotoğrafınız başarıyla kaldırıldı.';
                        break;
                    default:
                        echo '<i class="fas fa-check-circle me-2"></i>İşlem başarılı.';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_GET['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php
                switch ($_GET['error']) {
                    case 'invalid_file_type':
                        echo '<i class="fas fa-exclamation-triangle me-2"></i>Geçersiz dosya türü. Sadece JPG, PNG, GIF formatları desteklenir.';
                        break;
                    case 'file_too_large':
                        echo '<i class="fas fa-exclamation-triangle me-2"></i>Dosya çok büyük. Maksimum 5MB yükleyebilirsiniz.';
                        break;
                    case 'upload_failed':
                        echo '<i class="fas fa-exclamation-triangle me-2"></i>Dosya yüklenirken bir hata oluştu. Lütfen tekrar deneyin.';
                        break;
                    default:
                        echo '<i class="fas fa-exclamation-triangle me-2"></i>Beklenmeyen bir hata oluştu. Lütfen daha sonra tekrar deneyin.';
                }
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <div class="profile-avatar" onclick="openProfilePhotoModal()" style="cursor: pointer;" title="Profil fotoğrafını değiştir">
                        <?php if ($user['profil_fotografi']): ?>
                            <img src="<?php echo htmlspecialchars($user['profil_fotografi']); ?>" alt="Profil Fotoğrafı">
                        <?php else: ?>
                            <i class="fas fa-user"></i>
                        <?php endif; ?>
                        <div class="profile-photo-overlay">
                            <i class="fas fa-camera"></i>
                        </div>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></div>
                    <div class="profile-role">
                        <i class="fas fa-user-tag me-2"></i>
                        <?php 
                        $role_names = [
                            'super_admin' => 'Super Admin',
                            'moderator' => 'Moderator',
                            'yetkili_kullanici' => 'Yetkili Kullanıcı',
                            'normal_kullanici' => 'Normal Kullanıcı'
                        ];
                        echo $role_names[$user['rol']] ?? ucfirst($user['rol']);
                        ?>
                    </div>
                </div>
                <div class="col-md-4 text-end">
                    <a href="settings.php" class="btn btn-edit-profile">
                        <i class="fas fa-edit me-2"></i>Profili Düzenle
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container">
        <!-- Profile Info -->
        <div class="profile-card">
            <h4 class="mb-3">
                <i class="fas fa-info-circle me-2"></i>Profil Bilgileri
            </h4>
            <div class="profile-info">
                <div class="info-item">
                    <i class="fas fa-envelope"></i>
                    <span><?php echo htmlspecialchars($user['email']); ?></span>
                </div>
                <?php if ($user['telefon']): ?>
                <div class="info-item">
                    <i class="fas fa-phone"></i>
                    <span><?php echo htmlspecialchars($user['telefon']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($user['firma']): ?>
                <div class="info-item">
                    <i class="fas fa-building"></i>
                    <span><?php echo htmlspecialchars($user['firma']); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($user['sehir']): ?>
                <div class="info-item">
                    <i class="fas fa-map-marker-alt"></i>
                    <span><?php echo htmlspecialchars($user['sehir'] . ($user['ilce'] ? ', ' . $user['ilce'] : '')); ?></span>
                </div>
                <?php endif; ?>
                <?php if ($user['website']): ?>
                <div class="info-item">
                    <i class="fas fa-globe"></i>
                    <a href="<?php echo htmlspecialchars($user['website']); ?>" target="_blank" class="text-white">
                        <?php echo htmlspecialchars($user['website']); ?>
                    </a>
                </div>
                <?php endif; ?>
                <div class="info-item">
                    <i class="fas fa-calendar-plus"></i>
                    <span>Üyelik Tarihi: <?php 
                        if ($user['created_at']) {
                            echo date('d.m.Y', strtotime($user['created_at']));
                        } else {
                            echo 'Kayıt tarihi bilinmiyor';
                        }
                    ?></span>
                </div>
                <?php if ($user['son_giris']): ?>
                <div class="info-item">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Son Giriş: <?php echo date('d.m.Y H:i', strtotime($user['son_giris'])); ?></span>
                </div>
                <?php else: ?>
                <div class="info-item">
                    <i class="fas fa-sign-in-alt"></i>
                    <span>Son Giriş: Henüz giriş yapılmamış</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="profile-card">
            <h4 class="mb-3">
                <i class="fas fa-chart-bar me-2"></i>İstatistikler
            </h4>
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_events']; ?></div>
                    <div class="stat-label">Katıldığı Düğün</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_media']; ?></div>
                    <div class="stat-label">Paylaştığı Medya</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_likes']; ?></div>
                    <div class="stat-label">Aldığı Beğeni</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?php echo $stats['total_comments']; ?></div>
                    <div class="stat-label">Yazdığı Yorum</div>
                </div>
            </div>
        </div>
        
        <!-- Participated Events -->
        <div class="profile-card">
            <h4 class="mb-3">
                <i class="fas fa-calendar-check me-2"></i>Katıldığı Düğünler
            </h4>
            <?php if (count($participated_events) > 0): ?>
                <?php foreach ($participated_events as $event): ?>
                    <div class="event-card">
                        <div class="event-title"><?php echo htmlspecialchars($event['baslik']); ?></div>
                        <div class="event-meta">
                            <i class="fas fa-calendar me-2"></i><?php echo date('d.m.Y', strtotime($event['dugun_tarihi'])); ?>
                            <span class="ms-3">
                                <i class="fas fa-user-tie me-2"></i><?php echo htmlspecialchars($event['moderator_ad'] . ' ' . $event['moderator_soyad']); ?>
                            </span>
                        </div>
                        <div class="event-role">
                            <?php 
                            $role_names = [
                                'moderator' => 'Moderator',
                                'yetkili_kullanici' => 'Yetkili Kullanıcı',
                                'normal_kullanici' => 'Katılımcı'
                            ];
                            echo $role_names[$event['katilim_rolu']] ?? ucfirst($event['katilim_rolu']);
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="text-center py-4">
                    <i class="fas fa-calendar-times fa-3x mb-3 opacity-50"></i>
                    <p class="opacity-75">Henüz katıldığınız bir düğün bulunmamaktadır.</p>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Media -->
        <?php if (count($user_media) > 0): ?>
        <div class="profile-card">
            <h4 class="mb-3">
                <i class="fas fa-images me-2"></i>Son Paylaşımlar
            </h4>
            <div class="media-grid">
                <?php foreach ($user_media as $media): ?>
                    <div class="media-item">
                        <?php if ($media['tur'] === 'fotograf'): ?>
                            <img src="<?php echo htmlspecialchars($media['dosya_yolu']); ?>" 
                                 alt="<?php echo htmlspecialchars($media['aciklama'] ?: 'Medya'); ?>" 
                                 class="media-image">
                        <?php else: ?>
                            <div class="media-image d-flex align-items-center justify-content-center">
                                <i class="fas fa-video fa-2x opacity-50"></i>
                            </div>
                        <?php endif; ?>
                        <div class="media-info">
                            <div class="media-title"><?php echo htmlspecialchars($media['dugun_baslik']); ?></div>
                            <div class="media-meta">
                                <i class="fas fa-calendar me-1"></i><?php echo date('d.m.Y', strtotime($media['created_at'])); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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
        
        // Modal fixes
        document.addEventListener('DOMContentLoaded', function() {
            const profilePhotoModal = document.getElementById('profilePhotoModal');
            if (profilePhotoModal) {
                profilePhotoModal.addEventListener('shown.bs.modal', function() {
                    // Force pointer events on all modal elements
                    const modalElements = profilePhotoModal.querySelectorAll('*');
                    modalElements.forEach(function(element) {
                        element.style.pointerEvents = 'auto';
                        element.style.opacity = '1';
                    });
                });
            }
        });
    </script>
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
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload me-2"></i>Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
