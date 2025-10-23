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
$event_id = $_GET['id'] ?? null;

// Input sanitization function
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Video süre kontrolü fonksiyonu
function getVideoDuration($video_path) {
    // FFmpeg kullanarak video süresini al
    $command = "ffprobe -v quiet -show_entries format=duration -of csv=p=0 " . escapeshellarg($video_path);
    $output = shell_exec($command);
    
    if ($output) {
        $duration = floatval(trim($output));
        return ['duration' => $duration];
    }
    
    return null;
}

if (!$event_id) {
    header('Location: user_dashboard.php');
    exit;
}

// Kullanıcının bu düğünde engellenip engellenmediğini kontrol et
$stmt = $pdo->prepare("SELECT id FROM blocked_users WHERE dugun_id = ? AND blocked_user_id = ?");
$stmt->execute([$event_id, $user_id]);
if ($stmt->fetch()) {
    // Engellenen kullanıcıyı dashboard'a yönlendir
    header('Location: user_dashboard.php?blocked=1');
    exit;
}

// Stori yükleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_story') {
    $event_id = (int)$_POST['event_id'];
    $story_description = sanitizeInput($_POST['story_description'] ?? '');
    
    // Kullanıcının bu düğünde katılımcı olup olmadığını ve hikaye paylaşma yetkisini kontrol et
    $stmt = $pdo->prepare("SELECT rol, yetkiler FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
    $stmt->execute([$event_id, $user_id]);
    $participant = $stmt->fetch();
    
    if (!$participant) {
        header('Location: event.php?id=' . $event_id . '&error=not_participant');
        exit;
    }
    
    // ✅ Yeni yetki sistemi - JSON'dan yetkileri parse et
    $permissions = [];
    if ($participant['yetkiler']) {
        $permissions = json_decode($participant['yetkiler'], true) ?: [];
    }
    
    // Hikaye paylaşma yetkisi kontrolü
    if (!in_array('hikaye_paylasabilir', $permissions)) {
        header('Location: event.php?id=' . $event_id . '&error=no_story_permission');
        exit;
    }
    
    if (isset($_FILES['story_photo']) && $_FILES['story_photo']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/stories/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        $file_extension = strtolower(pathinfo($_FILES['story_photo']['name'], PATHINFO_EXTENSION));
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'mp4', 'mov', 'avi'];
        
        // Dosya boyutu kontrolü (10MB)
        $max_size = 10 * 1024 * 1024; // 10MB
        if ($_FILES['story_photo']['size'] > $max_size) {
            header('Location: event.php?id=' . $event_id . '&error=file_too_large');
            exit;
        }
        
        // Video süre kontrolü (59 saniye)
        $file_extension = strtolower(pathinfo($_FILES['story_photo']['name'], PATHINFO_EXTENSION));
        if (in_array($file_extension, ['mp4', 'mov', 'avi'])) {
            // Video süresini kontrol et
            $video_info = getVideoDuration($_FILES['story_photo']['tmp_name']);
            if ($video_info && $video_info['duration'] > 59) {
                header('Location: event.php?id=' . $event_id . '&error=video_too_long');
                exit;
            }
        }
        
        if (in_array($file_extension, $allowed_extensions)) {
            $filename = 'story_' . $user_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['story_photo']['tmp_name'], $file_path)) {
                // Storiyi veritabanına kaydet (24 saat sonra silinecek)
                $hikaye_bitis_tarihi = date('Y-m-d H:i:s', strtotime('+24 hours'));
                $stmt = $pdo->prepare("
                    INSERT INTO medyalar (dugun_id, kullanici_id, dosya_yolu, aciklama, tur, hikaye_bitis_tarihi, created_at) 
                    VALUES (?, ?, ?, ?, 'hikaye', ?, NOW())
                ");
                $stmt->execute([$event_id, $user_id, $file_path, $story_description, $hikaye_bitis_tarihi]);
                
                header('Location: event.php?id=' . $event_id . '&success=story_uploaded');
                exit;
            }
        } else {
            header('Location: event.php?id=' . $event_id . '&error=invalid_file_type');
            exit;
        }
    }
    
    header('Location: event.php?id=' . $event_id);
    exit;
}

// Event bilgilerini al
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        k.ad as moderator_ad,
        k.soyad as moderator_soyad,
        p.ad as paket_ad,
        (SELECT COUNT(DISTINCT dk2.kullanici_id) FROM dugun_katilimcilar dk2 WHERE dk2.dugun_id = d.id) as katilimci_sayisi,
        (SELECT COUNT(DISTINCT m.id) FROM medyalar m WHERE m.dugun_id = d.id) as medya_sayisi,
        dk.rol as katilimci_rol,
        dk.katilim_tarihi,
        dk.yetkiler
    FROM dugunler d
    LEFT JOIN kullanicilar k ON d.moderator_id = k.id
    LEFT JOIN paketler p ON d.paket_id = p.id
    LEFT JOIN dugun_katilimcilar dk ON d.id = dk.dugun_id AND dk.kullanici_id = ?
    WHERE d.id = ?
");
$stmt->execute([$user_id, $event_id]);
$event = $stmt->fetch();

if (!$event) {
    header('Location: user_dashboard.php');
    exit;
}

// ✅ Yeni yetki sistemi - JSON'dan yetkileri parse et
$permissions = [];
if ($event['yetkiler']) {
    $permissions = json_decode($event['yetkiler'], true) ?: [];
}

// Yetki değerlerini boolean'a çevir (yeni sistem)
$event['medya_silebilir'] = in_array('medya_silebilir', $permissions);
$event['yorum_silebilir'] = in_array('yorum_silebilir', $permissions);
$event['kullanici_engelleyebilir'] = in_array('kullanici_engelleyebilir', $permissions);
$event['medya_paylasabilir'] = in_array('medya_paylasabilir', $permissions);
$event['yorum_yapabilir'] = in_array('yorum_yapabilir', $permissions);
$event['hikaye_paylasabilir'] = in_array('hikaye_paylasabilir', $permissions);
$event['profil_degistirebilir'] = in_array('profil_degistirebilir', $permissions);
$event['yetki_duzenleyebilir'] = in_array('yetki_duzenleyebilir', $permissions);

// Event medyalarını al (hikaye hariç) - engellenen kullanıcıların medyalarını gizle
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        k.ad as user_name,
        k.soyad as user_surname,
        k.profil_fotografi as user_profile,
        (SELECT COUNT(*) FROM begeniler WHERE medya_id = m.id) as begeni_sayisi,
        (SELECT COUNT(*) FROM yorumlar WHERE medya_id = m.id AND durum = 'aktif') as yorum_sayisi,
        (SELECT COUNT(*) FROM begeniler WHERE medya_id = m.id AND kullanici_id = ?) as user_liked
    FROM medyalar m
    JOIN kullanicilar k ON m.kullanici_id = k.id
    WHERE m.dugun_id = ? AND m.tur != 'hikaye'
    AND (
        m.kullanici_id NOT IN (
            SELECT blocked_user_id 
            FROM blocked_users 
            WHERE dugun_id = ?
        )
        OR ? IN (
            SELECT kullanici_id 
            FROM dugun_katilimcilar 
            WHERE dugun_id = ? AND (
                rol = 'yetkili_kullanici' OR 
                kullanici_engelleyebilir = 1
            )
        )
        OR ? IN (
            SELECT moderator_id 
            FROM dugunler 
            WHERE id = ?
        )
        OR ? = 'super_admin'
    )
    ORDER BY m.created_at DESC
");
$stmt->execute([$user_id, $event_id, $event_id, $user_id, $event_id, $user_id, $event_id, $user_role]);
$event_media = $stmt->fetchAll();

// Event hikayelerini al (kullanıcı bazında gruplandırılmış) - engellenen kullanıcıların hikayelerini gizle
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        k.ad as user_name,
        k.soyad as user_surname,
        k.profil_fotografi as user_profile,
        COUNT(*) as story_count
    FROM medyalar m
    JOIN kullanicilar k ON m.kullanici_id = k.id
    WHERE m.dugun_id = ? AND m.tur = 'hikaye'
    AND (m.hikaye_bitis_tarihi IS NULL OR m.hikaye_bitis_tarihi > NOW())
    AND (
        m.kullanici_id NOT IN (
            SELECT blocked_user_id 
            FROM blocked_users 
            WHERE dugun_id = ?
        )
        OR ? IN (
            SELECT kullanici_id 
            FROM dugun_katilimcilar 
            WHERE dugun_id = ? AND (
                rol = 'yetkili_kullanici' OR 
                kullanici_engelleyebilir = 1
            )
        )
        OR ? IN (
            SELECT moderator_id 
            FROM dugunler 
            WHERE id = ?
        )
        OR ? = 'super_admin'
    )
    GROUP BY m.kullanici_id
    ORDER BY MAX(m.created_at) DESC
");
$stmt->execute([$event_id, $event_id, $user_id, $event_id, $user_id, $event_id, $user_role]);
$event_stories = $stmt->fetchAll();

// Her kullanıcının tüm hikayelerini al (sıralama için) - engellenen kullanıcıların hikayelerini gizle
$stmt = $pdo->prepare("
    SELECT 
        m.*,
        k.ad as user_name,
        k.soyad as user_surname,
        k.profil_fotografi as user_profile
    FROM medyalar m
    JOIN kullanicilar k ON m.kullanici_id = k.id
    WHERE m.dugun_id = ? AND m.tur = 'hikaye'
    AND (m.hikaye_bitis_tarihi IS NULL OR m.hikaye_bitis_tarihi > NOW())
    AND (
        m.kullanici_id NOT IN (
            SELECT blocked_user_id 
            FROM blocked_users 
            WHERE dugun_id = ?
        )
        OR ? IN (
            SELECT kullanici_id 
            FROM dugun_katilimcilar 
            WHERE dugun_id = ? AND (
                rol = 'yetkili_kullanici' OR 
                kullanici_engelleyebilir = 1
            )
        )
        OR ? IN (
            SELECT moderator_id 
            FROM dugunler 
            WHERE id = ?
        )
        OR ? = 'super_admin'
    )
    ORDER BY m.kullanici_id, m.created_at ASC
");
$stmt->execute([$event_id, $event_id, $user_id, $event_id, $user_id, $event_id, $user_role]);
$all_stories = $stmt->fetchAll();

// Kullanıcı bazında hikayeleri grupla
$stories_by_user = [];
foreach ($all_stories as $story) {
    $stories_by_user[$story['kullanici_id']][] = $story;
}

// Katılımcıları çek (sadece yetkili kullanıcılar için)
$participants = [];
if ($user_role === 'super_admin' || 
    ($user_role === 'moderator' && $event['moderator_id'] == $user_id) ||
    $event['katilimci_rol'] === 'yetkili_kullanici') {
    
    $stmt = $pdo->prepare("
        SELECT 
            dk.*,
            k.ad,
            k.soyad,
            k.email,
            k.profil_fotografi,
            k.son_giris,
            k.durum,
            (SELECT COUNT(*) FROM medyalar m WHERE m.kullanici_id = k.id AND m.dugun_id = ?) as medya_sayisi,
            (SELECT COUNT(*) FROM yorumlar y JOIN medyalar m ON y.medya_id = m.id WHERE m.kullanici_id = k.id AND m.dugun_id = ?) as yorum_sayisi,
            (SELECT COUNT(*) FROM begeniler b JOIN medyalar m ON b.medya_id = m.id WHERE m.kullanici_id = k.id AND m.dugun_id = ?) as begeni_sayisi,
            CASE 
                WHEN k.id IN (SELECT blocked_user_id FROM blocked_users WHERE dugun_id = ?) THEN 'yasakli'
                ELSE 'aktif'
            END as ban_durumu
        FROM dugun_katilimcilar dk
        JOIN kullanicilar k ON dk.kullanici_id = k.id
        WHERE dk.dugun_id = ?
        ORDER BY dk.katilim_tarihi DESC
    ");
    $stmt->execute([$event_id, $event_id, $event_id, $event_id, $event_id]);
    $participants = $stmt->fetchAll();
}

// Medya yükleme işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_media') {
    // Sadece düğüne katılan kullanıcılar medya yükleyebilir
    if (!$event['katilimci_rol']) {
        header('Location: event.php?id=' . $event_id . '&error=not_participant');
        exit;
    }
    
    // Medya paylaşma yetkisi kontrolü
    if (!$event['medya_paylasabilir']) {
        header('Location: event.php?id=' . $event_id . '&error=no_media_permission');
        exit;
    }
    
    if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/events/' . $event_id . '/';
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
                    $event_id,
                    $user_id,
                    $file_path,
                    $thumbnail_path,
                    in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif']) ? 'fotograf' : 'video',
                    $_POST['description'] ?? ''
                ]);

                header('Location: event.php?id=' . $event_id);
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
    <title><?php echo htmlspecialchars($event['baslik']); ?> - Digital Salon</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'bkg': '#0D1117',
                        'bkg-light': '#161B22',
                        'primary': '#C9D1D9',
                        'secondary': '#8B949E',
                        'accent-pink': '#DB61A2',
                        'accent-purple': '#BF9EEE',
                        'accent-blue': '#58A6FF',
                    },
                },
            },
        }
        
        /* Like Animation */
        .like-animation {
            animation: heartBeat 0.6s ease-in-out;
        }
        
        @keyframes heartBeat {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        body {
            background-color: #0D1117;
            color: #C9D1D9;
        }

        .glass-effect {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .gradient-text {
            background: linear-gradient(135deg, #BF9EEE, #DB61A2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .story-gradient {
            background: linear-gradient(45deg, #DB61A2, #BF9EEE);
        }
        
        .story-profile-container {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            padding: 2px;
            background: linear-gradient(45deg, #DB61A2, #BF9EEE);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .story-profile-inner {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: var(--bkg);
            padding: 2px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .story-profile-img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .floating-btn {
            background: linear-gradient(135deg, #DB61A2, #BF9EEE);
            box-shadow: 0 8px 32px rgba(219, 97, 162, 0.3);
        }

        .post-card {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .interaction-btn {
            transition: all 0.3s ease;
        }

        .interaction-btn:hover {
            transform: scale(1.1);
        }

        .like-animation {
            animation: heartBeat 0.6s ease-in-out;
        }

        @keyframes heartBeat {
            0% { transform: scale(1); }
            50% { transform: scale(1.3); }
            100% { transform: scale(1); }
        }

        .sidebar {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(20px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-item {
            transition: all 0.3s ease;
        }

        .nav-item:hover {
            background: rgba(255, 255, 255, 0.05);
            transform: translateX(4px);
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .event-header-card {
            background: rgba(0, 0, 0, 0.2);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .upload-modal {
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(20px);
            z-index: 50;
        }
        
        .participant-menu-modal {
            z-index: 60;
        }
        
        .permissions-modal {
            z-index: 70;
        }

        .upload-content {
            background: rgba(22, 27, 34, 0.95);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
    </style>
</head>
<body class="min-h-screen bg-bkg text-primary">
    <!-- Background Pattern -->
    <div class="absolute inset-0 -z-10 h-full w-full bg-bkg bg-[linear-gradient(to_right,#8080800a_1px,transparent_1px),linear-gradient(to_bottom,#8080800a_1px,transparent_1px)] bg-[size:14px_24px]">
        <div class="absolute left-0 right-0 top-0 -z-10 m-auto h-[310px] w-[310px] rounded-full bg-accent-purple opacity-20 blur-[100px]"></div>
    </div>

    <div class="flex h-screen bg-bkg-light">
        <!-- Sidebar -->
        <aside class="sidebar w-64 h-full p-6 flex flex-col">
            <div>
                <h1 class="text-2xl font-bold gradient-text mb-10">Digital Salon</h1>
                <nav class="space-y-2">
                    <a href="user_dashboard.php" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-secondary hover:text-primary">
                        <i class="fas fa-home w-5 h-5"></i>
                        <span class="font-medium">Dashboard</span>
                    </a>
                    <a href="#" class="nav-item active flex items-center space-x-3 px-4 py-3 rounded-lg text-white">
                        <i class="fas fa-images w-5 h-5"></i>
                        <span class="font-medium">Akış</span>
                    </a>
                    <a href="profile.php" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-secondary hover:text-primary">
                        <i class="fas fa-user w-5 h-5"></i>
                        <span class="font-medium">Profil</span>
                    </a>
                    <a href="qr_scanner.php" class="nav-item flex items-center space-x-3 px-4 py-3 rounded-lg text-secondary hover:text-primary">
                        <i class="fas fa-qrcode w-5 h-5"></i>
                        <span class="font-medium">QR Tarayıcı</span>
                    </a>
                </nav>
            </div>
            <div class="mt-auto">
                <div class="flex items-center space-x-4 p-2 rounded-lg">
                    <img src="<?php echo htmlspecialchars($_SESSION['user_profile_photo'] ?? 'assets/images/default_profile.svg'); ?>" 
                         alt="<?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı'); ?>" 
                         class="w-10 h-10 rounded-full">
                    <div>
                        <p class="font-semibold"><?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı'); ?></p>
                        <p class="text-sm text-secondary"><?php echo htmlspecialchars($_SESSION['user_role'] ?? 'Kullanıcı'); ?></p>
                    </div>
                </div>
                <a href="logout.php" class="w-full flex items-center space-x-3 px-4 py-3 mt-4 rounded-lg text-secondary hover:bg-red-500/10 hover:text-red-400 transition-colors duration-200">
                    <i class="fas fa-sign-out-alt w-5 h-5"></i>
                    <span class="font-medium">Çıkış Yap</span>
                </a>
            </div>
        </aside>

        <!-- Katılımcı Yönetimi (Sadece yetkili kullanıcılar için) -->
        <?php if (!empty($participants)): ?>
        <div class="flex-1 flex flex-col overflow-y-auto">
            <div class="w-full max-w-6xl mx-auto py-6 px-4">
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-2xl font-bold gradient-text">
                            <i class="fas fa-users mr-2"></i>Katılımcı Yönetimi
                        </h2>
                        <div class="flex items-center space-x-4">
                            <span class="text-sm text-secondary">
                                <span id="selectedCount">0</span> / <?php echo count($participants); ?> seçildi
                            </span>
                            <button onclick="selectAllParticipants()" class="text-sm bg-primary/20 text-primary px-3 py-1 rounded-lg hover:bg-primary/30 transition-colors">
                                Tümünü Seç
                            </button>
                            <button onclick="clearSelection()" class="text-sm bg-secondary/20 text-secondary px-3 py-1 rounded-lg hover:bg-secondary/30 transition-colors">
                                Temizle
                            </button>
                        </div>
                    </div>
                    
                    <!-- Toplu İşlemler Paneli -->
                    <div id="bulkActionsPanel" class="bg-bkg-light rounded-2xl p-4 mb-6 hidden">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-4">
                                <span class="text-primary font-semibold" id="bulkActionText">0 kullanıcı seçildi</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <button onclick="bulkBanParticipants()" class="bg-red-500/20 text-red-400 px-4 py-2 rounded-lg hover:bg-red-500/30 transition-colors">
                                    <i class="fas fa-ban mr-2"></i>Toplu Yasakla
                                </button>
                                <button onclick="bulkUnbanParticipants()" class="bg-green-500/20 text-green-400 px-4 py-2 rounded-lg hover:bg-green-500/30 transition-colors">
                                    <i class="fas fa-unlock mr-2"></i>Toplu Yasağı Kaldır
                                </button>
                                <button onclick="bulkChangeRole()" class="bg-blue-500/20 text-blue-400 px-4 py-2 rounded-lg hover:bg-blue-500/30 transition-colors">
                                    <i class="fas fa-user-tag mr-2"></i>Rol Değiştir
                                </button>
                                <button onclick="bulkSendMessage()" class="bg-purple-500/20 text-purple-400 px-4 py-2 rounded-lg hover:bg-purple-500/30 transition-colors">
                                    <i class="fas fa-envelope mr-2"></i>Mesaj Gönder
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Katılımcı Listesi -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        <?php foreach ($participants as $participant): ?>
                        <div class="participant-card bg-bkg-light rounded-2xl p-4 border border-white/10 hover:border-primary/30 transition-all duration-300">
                            <div class="flex items-start space-x-3">
                                <!-- Checkbox -->
                                <input type="checkbox" 
                                       class="participant-checkbox mt-1" 
                                       data-user-id="<?php echo $participant['kullanici_id']; ?>"
                                       onchange="updateSelection()">
                                
                                <!-- Avatar -->
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 rounded-full overflow-hidden bg-gradient-to-r from-primary to-secondary">
                                        <?php if ($participant['profil_fotografi']): ?>
                                            <img src="<?php echo htmlspecialchars($participant['profil_fotografi']); ?>" 
                                                 alt="<?php echo htmlspecialchars($participant['ad']); ?>" 
                                                 class="w-full h-full object-cover">
                                        <?php else: ?>
                                            <div class="w-full h-full flex items-center justify-center text-white font-bold">
                                                <?php echo strtoupper(substr($participant['ad'], 0, 1)); ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <!-- Kullanıcı Bilgileri -->
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center justify-between">
                                        <h3 class="font-semibold text-primary truncate">
                                            <?php echo htmlspecialchars($participant['ad'] . ' ' . $participant['soyad']); ?>
                                        </h3>
                                        <span class="text-xs px-2 py-1 rounded-full <?php 
                                            echo $participant['ban_durumu'] === 'yasakli' ? 'bg-red-500/20 text-red-400' : 'bg-green-500/20 text-green-400';
                                        ?>">
                                            <?php echo $participant['ban_durumu'] === 'yasakli' ? 'Yasaklı' : 'Aktif'; ?>
                                        </span>
                                    </div>
                                    
                                    <p class="text-sm text-secondary truncate"><?php echo htmlspecialchars($participant['email']); ?></p>
                                    
                                    <!-- Rol -->
                                    <div class="flex items-center space-x-2 mt-1">
                                        <span class="text-xs px-2 py-1 rounded-full bg-primary/20 text-primary">
                                            <?php 
                                            $role_names = [
                                                'moderator' => 'Moderator',
                                                'yetkili_kullanici' => 'Yetkili',
                                                'normal_kullanici' => 'Katılımcı'
                                            ];
                                            echo $role_names[$participant['rol']] ?? ucfirst($participant['rol']);
                                            ?>
                                        </span>
                                    </div>
                                    
                                    <!-- İstatistikler -->
                                    <div class="flex items-center space-x-4 mt-2 text-xs text-secondary">
                                        <span><i class="fas fa-camera mr-1"></i><?php echo $participant['medya_sayisi']; ?></span>
                                        <span><i class="fas fa-comment mr-1"></i><?php echo $participant['yorum_sayisi']; ?></span>
                                        <span><i class="fas fa-heart mr-1"></i><?php echo $participant['begeni_sayisi']; ?></span>
                                    </div>
                                    
                                    <!-- Son Aktivite -->
                                    <div class="text-xs text-secondary mt-1">
                                        <?php if ($participant['son_giris']): ?>
                                            Son giriş: <?php echo date('d.m.Y H:i', strtotime($participant['son_giris'])); ?>
                                        <?php else: ?>
                                            Henüz giriş yapmamış
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Hızlı İşlemler -->
                            <div class="flex items-center justify-end space-x-2 mt-3 pt-3 border-t border-white/10">
                                <button onclick="openParticipantMenu(<?php echo $participant['kullanici_id']; ?>, '<?php echo addslashes($participant['ad'] . ' ' . $participant['soyad']); ?>')" 
                                        class="text-xs bg-primary/20 text-primary px-3 py-1 rounded-lg hover:bg-primary/30 transition-colors">
                                    <i class="fas fa-cog mr-1"></i>Yönet
                                </button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Main Content -->
        <main class="flex-1 flex flex-col overflow-y-auto">
            <div class="w-full max-w-2xl mx-auto py-6 px-4">
                <!-- Success Message -->
                <?php if (isset($_GET['success'])): ?>
                    <div class="bg-green-500/10 border border-green-500/20 text-green-400 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-check-circle mr-2"></i>
                        <?php
                        switch ($_GET['success']) {
                            case 'story_uploaded':
                                echo 'Hikaye başarıyla paylaşıldı! 24 saat sonra otomatik olarak silinecek.';
                                break;
                            case 'media_uploaded':
                                echo 'Medya başarıyla yüklendi!';
                                break;
                            default:
                                echo 'İşlem başarıyla tamamlandı!';
                        }
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Error Message -->
                <?php if (isset($_GET['error'])): ?>
                    <div class="bg-red-500/10 border border-red-500/20 text-red-400 px-4 py-3 rounded-lg mb-6">
                        <i class="fas fa-exclamation-triangle mr-2"></i>
                        <?php
                        switch ($_GET['error']) {
                            case 'not_participant':
                                echo 'Bu düğüne katılmadığınız için medya yükleyemezsiniz. QR kod tarayarak düğüne katılabilirsiniz.';
                                break;
                            case 'no_story_permission':
                                echo 'Hikaye paylaşma yetkiniz bulunmuyor.';
                                break;
                            case 'file_too_large':
                                echo 'Dosya boyutu çok büyük! Maksimum 10MB yükleyebilirsiniz.';
                                break;
                            case 'invalid_file_type':
                                echo 'Geçersiz dosya türü! Sadece fotoğraf ve video yükleyebilirsiniz.';
                                break;
                            case 'video_too_long':
                                echo 'Video çok uzun! Maksimum 59 saniye video yükleyebilirsiniz.';
                                break;
                            default:
                                echo 'Bir hata oluştu. Lütfen tekrar deneyin.';
                        }
                        ?>
                    </div>
                <?php endif; ?>

                <!-- Event Header -->
                <div class="event-header-card rounded-2xl overflow-hidden shadow-lg mb-8 p-6">
                    <div class="flex items-center space-x-4 mb-4">
                        <div class="w-16 h-16 rounded-full overflow-hidden">
                            <img src="<?php echo htmlspecialchars($event['profil_fotografi'] ?: 'assets/images/default_event.png'); ?>" 
                                 alt="<?php echo htmlspecialchars($event['baslik']); ?>" 
                                 class="w-full h-full object-cover">
                        </div>
                        <div>
                            <h2 class="text-xl font-bold gradient-text"><?php echo htmlspecialchars($event['baslik']); ?></h2>
                            <p class="text-secondary text-sm"><?php echo date('d.m.Y', strtotime($event['dugun_tarihi'])); ?></p>
                            <p class="text-secondary text-sm"><?php echo $event['katilimci_sayisi']; ?> katılımcı • <?php echo $event['medya_sayisi']; ?> medya</p>
                        </div>
                    </div>
                    <p class="text-secondary text-sm leading-relaxed"><?php echo nl2br(htmlspecialchars($event['aciklama'])); ?></p>
                </div>

                <!-- Stories -->
                <?php if (!empty($event_stories)): ?>
                    <div class="mb-8 overflow-x-auto pb-4">
                        <div class="flex space-x-4 stories-container">
                            <?php foreach ($event_stories as $story): ?>
                                <div class="flex-shrink-0 text-center cursor-pointer group" onclick="openUserStories(<?php echo $story['kullanici_id']; ?>)">
                                    <div class="story-profile-container group-hover:scale-105 transition-transform duration-300 relative mx-auto mb-3">
                                        <div class="story-profile-inner">
                                            <img src="<?php echo htmlspecialchars($story['user_profile'] ?: 'assets/images/default_profile.svg'); ?>" 
                                                 alt="<?php echo htmlspecialchars($story['user_name']); ?>" 
                                                 class="story-profile-img">
                                        </div>
                                        <?php if ($story['story_count'] > 1): ?>
                                            <div class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center font-bold">
                                                <?php echo $story['story_count']; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <p class="text-xs text-secondary text-center break-words leading-tight px-2 mt-2"><?php echo htmlspecialchars($story['user_name']); ?></p>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Posts -->
                <div>
                    <?php if (!empty($event_media)): ?>
                        <?php foreach ($event_media as $media): ?>
                            <div class="post-card rounded-2xl overflow-hidden shadow-lg mb-8" data-media-id="<?php echo $media['id']; ?>">
                                <!-- Post Header -->
                                <div class="p-4 flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <img src="<?php echo htmlspecialchars($media['user_profile'] ?: 'assets/images/default_profile.svg'); ?>" 
                                             alt="<?php echo htmlspecialchars($media['user_name']); ?>" 
                                             class="w-10 h-10 rounded-full object-cover">
                                        <div>
                                            <p class="font-semibold cursor-pointer hover:text-primary transition-colors" 
                                               onclick="openParticipantMenu(<?php echo $media['kullanici_id']; ?>, '<?php echo addslashes($media['user_name']); ?>')">
                                                <?php echo htmlspecialchars($media['user_name']); ?>
                                            </p>
                                            <p class="text-xs text-secondary"><?php echo date('d.m.Y H:i', strtotime($media['created_at'])); ?></p>
                                        </div>
                                    </div>
                                    <?php 
                                    // Medya düzenleme/silme yetkisi kontrolü
                                    $can_manage_media = false;
                                    
                                    // Süper admin her şeyi yapabilir
                                    if ($user_role === 'super_admin') {
                                        $can_manage_media = true;
                                    }
                                    // Moderator kendi düğünlerindeki medyaları yönetebilir
                                    elseif ($user_role === 'moderator' && $event['moderator_id'] == $user_id) {
                                        $can_manage_media = true;
                                    }
                                    // Yetkili kullanıcı kendi düğünlerindeki medyaları yönetebilir
                                    elseif ($event['katilimci_rol'] === 'yetkili_kullanici') {
                                        $can_manage_media = true;
                                    }
                                    // Normal kullanıcı sadece kendi medyalarını yönetebilir
                                    elseif ($media['kullanici_id'] == $user_id) {
                                        $can_manage_media = true;
                                    }
                                    
                                    if ($can_manage_media): ?>
                                        <div class="flex items-center space-x-2">
                                            <button onclick="editMedia(<?php echo $media['id']; ?>, '<?php echo htmlspecialchars($media['aciklama'] ?: ''); ?>')" 
                                                    class="text-secondary hover:text-primary transition-colors p-2 rounded-full hover:bg-white/5">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button onclick="deleteMedia(<?php echo $media['id']; ?>)" 
                                                    class="text-secondary hover:text-red-500 transition-colors p-2 rounded-full hover:bg-white/5">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <!-- Post Media -->
                                <?php if ($media['tur'] === 'fotograf'): ?>
                                    <img src="<?php echo htmlspecialchars($media['dosya_yolu']); ?>" 
                                         alt="<?php echo htmlspecialchars($media['aciklama'] ?: 'Medya'); ?>" 
                                         class="w-full object-cover">
                                <?php else: ?>
                                    <video src="<?php echo htmlspecialchars($media['dosya_yolu']); ?>" 
                                           controls 
                                           class="w-full">
                                    </video>
                                <?php endif; ?>

                                <!-- Post Actions -->
                                <div class="p-4">
                                    <div class="flex items-center space-x-4 mb-3">
                                        <button onclick="toggleLike(<?php echo $media['id']; ?>)" 
                                                class="interaction-btn like-btn" 
                                                data-media-id="<?php echo $media['id']; ?>"
                                                data-liked="<?php echo $media['user_liked'] ? 'true' : 'false'; ?>">
                                            <i class="fas fa-heart w-7 h-7 transition-colors duration-300 <?php echo $media['user_liked'] ? 'text-red-500' : 'text-primary hover:text-red-500'; ?>"></i>
                                        </button>
                                        <button onclick="openComments(<?php echo $media['id']; ?>)" 
                                                class="interaction-btn">
                                            <i class="fas fa-comment w-7 h-7 text-primary hover:text-secondary cursor-pointer"></i>
                                        </button>
                                        <button onclick="sharePost(<?php echo $media['id']; ?>)" 
                                                class="interaction-btn">
                                            <i class="fas fa-share w-7 h-7 text-primary hover:text-secondary cursor-pointer"></i>
                                        </button>
                                    </div>
                                    
                                    <p class="font-semibold text-sm mb-2">
                                        <span class="like-count" data-media-id="<?php echo $media['id']; ?>"><?php echo $media['begeni_sayisi'] ?? 0; ?></span> beğeni
                                    </p>
                                    
                                    <?php if ($media['yorum_sayisi'] > 0): ?>
                                        <p class="text-sm text-secondary mb-2 cursor-pointer" onclick="openComments(<?php echo $media['id']; ?>)">
                                            <span class="comment-count" data-media-id="<?php echo $media['id']; ?>"><?php echo $media['yorum_sayisi']; ?></span> yorumu görüntüle
                                        </p>
                                    <?php endif; ?>
                                    
                                    <p class="text-sm">
                                        <span class="font-semibold mr-2"><?php echo htmlspecialchars($media['user_name']); ?></span>
                                        <span class="text-secondary"><?php echo htmlspecialchars($media['aciklama'] ?: ''); ?></span>
                                    </p>
                                    
                                    <!-- Comments Section -->
                                    <?php if ($event['yorum_yapabilir']): ?>
                                        <div class="mt-4 border-t border-white/10 pt-3">
                                            <div class="flex items-center space-x-3">
                                                <input type="text" 
                                                       placeholder="Yorum ekle..." 
                                                       class="bg-transparent flex-1 focus:outline-none text-sm text-primary placeholder-secondary comment-input"
                                                       data-media-id="<?php echo $media['id']; ?>"
                                                       onkeypress="if(event.key==='Enter') addComment(<?php echo $media['id']; ?>, this)">
                                                <button onclick="addComment(<?php echo $media['id']; ?>, this.previousElementSibling)" 
                                                        class="text-primary hover:text-secondary transition-colors">
                                                    <i class="fas fa-paper-plane"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <!-- Empty State -->
                        <div class="post-card rounded-2xl overflow-hidden shadow-lg mb-8 p-8 text-center">
                            <i class="fas fa-camera text-6xl text-secondary mb-4"></i>
                            <h3 class="text-xl font-semibold mb-2">Henüz medya yüklenmedi</h3>
                            <p class="text-secondary mb-6">Bu düğüne henüz fotoğraf veya video yüklenmemiş.<?php if ($event['katilimci_rol']): ?> İlk paylaşımı siz yapın!<?php endif; ?></p>
                            <?php if ($event['katilimci_rol'] && $event['medya_paylasabilir']): ?>
                                <button onclick="showUploadModal()" 
                                        class="floating-btn text-white px-6 py-3 rounded-lg font-semibold hover:opacity-90 transition-opacity duration-300">
                                    <i class="fas fa-plus mr-2"></i>Medya Yükle
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Floating Action Buttons -->
    <?php if ($event['katilimci_rol']): ?>
        <?php if ($event['medya_paylasabilir']): ?>
            <button onclick="showUploadModal()" 
                    class="fixed bottom-6 right-6 floating-btn text-white w-14 h-14 rounded-full flex items-center justify-center text-xl hover:scale-110 transition-transform duration-300 z-50">
                <i class="fas fa-plus"></i>
            </button>
        <?php endif; ?>
        <?php if ($event['hikaye_paylasabilir']): ?>
            <button onclick="showStoryUploadModal()" 
                    class="fixed bottom-6 right-24 bg-gradient-to-r from-purple-500 to-pink-500 text-white w-14 h-14 rounded-full flex items-center justify-center text-xl hover:scale-110 transition-transform duration-300 z-50">
                <i class="fas fa-camera"></i>
            </button>
        <?php endif; ?>
    <?php endif; ?>

    <!-- Upload Modal -->
    <div id="uploadModal" class="fixed inset-0 upload-modal hidden flex items-center justify-center z-50">
        <div class="upload-content rounded-2xl p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold gradient-text">Medya Yükle</h3>
                <button onclick="hideUploadModal()" class="text-secondary hover:text-primary">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="upload_media">
                
                <div>
                    <label class="block text-sm font-medium mb-2">Dosya Seç</label>
                    <input type="file" 
                           name="media_file" 
                           accept="image/*,video/*" 
                           required 
                           class="w-full px-4 py-3 bg-bkg-light border border-white/10 rounded-lg text-primary focus:outline-none focus:border-accent-pink transition-colors duration-300">
                </div>
                
                <div>
                    <label class="block text-sm font-medium mb-2">Açıklama</label>
                    <textarea name="description" 
                              rows="3" 
                              placeholder="Medya açıklaması..." 
                              class="w-full px-4 py-3 bg-bkg-light border border-white/10 rounded-lg text-primary placeholder-secondary focus:outline-none focus:border-accent-pink transition-colors duration-300 resize-none"></textarea>
                </div>
                
                <div class="flex space-x-3 pt-4">
                    <button type="button" 
                            onclick="hideUploadModal()" 
                            class="flex-1 px-4 py-3 bg-bkg-light border border-white/10 rounded-lg text-primary hover:bg-white/5 transition-colors duration-300">
                        İptal
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-3 floating-btn text-white rounded-lg font-semibold hover:opacity-90 transition-opacity duration-300">
                        Yükle
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Participant Menu Modal -->
    <div id="participantMenuModal" class="fixed inset-0 bg-black/90 hidden flex items-center justify-center z-[60] participant-menu-modal">
        <div class="bg-bkg-light rounded-2xl p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold gradient-text">Katılımcı Yönetimi</h3>
                <button onclick="hideParticipantMenu()" class="text-secondary hover:text-primary">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="mb-4">
                <p class="text-primary font-semibold" id="participantName"></p>
                <p class="text-secondary text-sm">Bu kullanıcı için işlemler:</p>
            </div>
            
            <div class="space-y-3">
                <!-- Ban Button -->
                <button id="banButton" onclick="banParticipant()" 
                        class="w-full px-4 py-3 bg-red-500/20 border border-red-500/30 text-red-400 rounded-lg hover:bg-red-500/30 transition-colors">
                    <i class="fas fa-ban mr-2"></i>
                    Kullanıcıyı Yasakla
                </button>
                
                <!-- Permissions Button -->
                <button onclick="showPermissionsModal()" 
                        class="w-full px-4 py-3 bg-blue-500/20 border border-blue-500/30 text-blue-400 rounded-lg hover:bg-blue-500/30 transition-colors">
                    <i class="fas fa-user-shield mr-2"></i>
                    Yetkileri Düzenle
                </button>
            </div>
        </div>
    </div>

    <!-- Permissions Modal -->
    <div id="permissionsModal" class="fixed inset-0 bg-black/90 hidden flex items-center justify-center z-[70] permissions-modal">
        <div class="bg-bkg-light rounded-2xl p-6 max-w-lg w-full mx-4 max-h-96 overflow-y-auto">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold gradient-text">Yetki Ayarları</h3>
                <button onclick="hidePermissionsModal()" class="text-secondary hover:text-primary">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <div class="mb-4">
                <p class="text-primary font-semibold" id="permissionsParticipantName"></p>
            </div>
            
            <div class="space-y-3">
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" id="perm_medya_silebilir" class="w-4 h-4 text-accent-pink bg-bkg border-white/20 rounded focus:ring-accent-pink focus:ring-2">
                    <span class="text-primary">Medya Silebilir</span>
                </label>
                
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" id="perm_yorum_silebilir" class="w-4 h-4 text-accent-pink bg-bkg border-white/20 rounded focus:ring-accent-pink focus:ring-2">
                    <span class="text-primary">Yorum Silebilir</span>
                </label>
                
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" id="perm_kullanici_engelleyebilir" class="w-4 h-4 text-accent-pink bg-bkg border-white/20 rounded focus:ring-accent-pink focus:ring-2">
                    <span class="text-primary">Kullanıcı Engelleyebilir</span>
                </label>
                
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" id="perm_medya_paylasabilir" class="w-4 h-4 text-accent-pink bg-bkg border-white/20 rounded focus:ring-accent-pink focus:ring-2">
                    <span class="text-primary">Medya Paylaşabilir</span>
                </label>
                
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" id="perm_yorum_yapabilir" class="w-4 h-4 text-accent-pink bg-bkg border-white/20 rounded focus:ring-accent-pink focus:ring-2">
                    <span class="text-primary">Yorum Yapabilir</span>
                </label>
                
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" id="perm_hikaye_paylasabilir" class="w-4 h-4 text-accent-pink bg-bkg border-white/20 rounded focus:ring-accent-pink focus:ring-2">
                    <span class="text-primary">Hikaye Paylaşabilir</span>
                </label>
                
                <label class="flex items-center space-x-3 cursor-pointer">
                    <input type="checkbox" id="perm_profil_degistirebilir" class="w-4 h-4 text-accent-pink bg-bkg border-white/20 rounded focus:ring-accent-pink focus:ring-2">
                    <span class="text-primary">Profil Değiştirebilir</span>
                </label>
            </div>
            
            <div class="flex space-x-3 mt-6">
                <button onclick="savePermissions()" 
                        class="flex-1 px-4 py-2 bg-gradient-to-r from-accent-pink to-accent-purple text-white rounded-lg hover:opacity-90 transition-opacity">
                    <i class="fas fa-save mr-2"></i>
                    Kaydet
                </button>
                <button onclick="hidePermissionsModal()" 
                        class="px-4 py-2 bg-white/10 text-primary rounded-lg hover:bg-white/20 transition-colors">
                    İptal
                </button>
            </div>
        </div>
    </div>

    <!-- Comments Modal -->
    <div id="commentsModal" class="fixed inset-0 upload-modal hidden flex items-center justify-center z-50">
        <div class="bg-bkg-light rounded-2xl shadow-2xl w-full max-w-md mx-4 max-h-[80vh] flex flex-col">
            <!-- Modal Header -->
            <div class="p-6 border-b border-white/10">
                <div class="flex items-center justify-between">
                    <h3 class="text-xl font-semibold text-primary">Yorumlar</h3>
                    <button onclick="hideCommentsModal()" class="text-secondary hover:text-primary transition-colors">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
            </div>
            
            <!-- Comments List -->
            <div id="commentsList" class="flex-1 overflow-y-auto p-6 space-y-4">
                <!-- Comments will be loaded here -->
            </div>
            
            <!-- Add Comment -->
            <div class="p-6 border-t border-white/10">
                <?php if ($event['yorum_yapabilir'] ?? false): ?>
                <div class="flex items-center space-x-3">
                    <input type="text" 
                           id="modalCommentInput"
                           placeholder="Yorum ekle..." 
                           class="bg-transparent flex-1 focus:outline-none text-sm text-primary placeholder-secondary border border-white/10 rounded-lg px-3 py-2"
                           onkeypress="if(event.key==='Enter') addModalComment()">
                    <button onclick="addModalComment()" 
                            class="text-primary hover:text-secondary transition-colors">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Story Upload Modal -->
    <div id="storyUploadModal" class="fixed inset-0 upload-modal hidden flex items-center justify-center z-50">
        <div class="upload-content rounded-2xl p-6 max-w-md w-full mx-4">
            <div class="flex justify-between items-center mb-6">
                <h3 class="text-xl font-bold gradient-text">Stori Paylaş</h3>
                <button onclick="hideStoryUploadModal()" class="text-secondary hover:text-primary transition-colors">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <form method="POST" enctype="multipart/form-data" class="space-y-4">
                <input type="hidden" name="action" value="upload_story">
                <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
                
                <div>
                    <label class="block text-sm font-medium text-secondary mb-2">Stori Medyası</label>
                    <input type="file" name="story_photo" accept="image/*,video/*" required 
                           class="w-full p-3 rounded-lg border-2 border-secondary/20 bg-bkg text-primary focus:border-primary focus:outline-none">
                    <p class="text-xs text-secondary mt-1">Fotoğraf veya video seçebilirsiniz (Maksimum 10MB, Video maksimum 59 saniye)</p>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-secondary mb-2">Açıklama (İsteğe Bağlı)</label>
                    <textarea name="story_description" rows="3" 
                              class="w-full p-3 rounded-lg border-2 border-secondary/20 bg-bkg text-primary focus:border-primary focus:outline-none resize-none"
                              placeholder="Storiniz için bir açıklama yazın..."></textarea>
                </div>
                
                <div class="flex space-x-3">
                    <button type="button" onclick="hideStoryUploadModal()" 
                            class="flex-1 px-4 py-3 rounded-lg border-2 border-secondary/20 text-secondary hover:border-secondary transition-colors">
                        İptal
                    </button>
                    <button type="submit" 
                            class="flex-1 px-4 py-3 rounded-lg bg-gradient-to-r from-primary to-secondary text-white font-semibold hover:opacity-90 transition-opacity">
                        <i class="fas fa-share me-2"></i>Paylaş
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Story Viewer Modal -->
    <div id="storyViewer" class="fixed inset-0 bg-black/90 hidden flex items-center justify-center z-50">
        <div class="story-content relative w-full h-full flex items-center justify-center">
            <div class="story-progress absolute top-4 left-4 right-4 h-1 bg-white/20 rounded-full">
                <div id="storyProgressBar" class="h-full bg-white rounded-full transition-all duration-100" style="width: 0%"></div>
            </div>
            <button onclick="closeStory()" class="absolute top-4 right-4 text-white text-2xl hover:text-gray-300">
                <i class="fas fa-times"></i>
            </button>
            
            <!-- Story Options Menu (3 dots) -->
            <div class="absolute top-4 right-16">
                <button onclick="toggleStoryMenu()" id="storyMenuBtn" class="text-white text-2xl hover:text-gray-300">
                    <i class="fas fa-ellipsis-v"></i>
                </button>
                
                <!-- Story Options Dropdown -->
                <div id="storyMenuDropdown" class="absolute right-0 top-8 bg-black/80 backdrop-blur-sm rounded-lg py-2 min-w-32 hidden">
                    <button onclick="editCurrentStory()" id="editStoryBtn" class="w-full text-left px-4 py-2 text-white hover:bg-white/10 flex items-center">
                        <i class="fas fa-edit mr-2"></i>
                        Düzenle
                    </button>
                    <button onclick="deleteCurrentStory()" id="deleteStoryBtn" class="w-full text-left px-4 py-2 text-red-400 hover:bg-red-500/10 flex items-center">
                        <i class="fas fa-trash mr-2"></i>
                        Sil
                    </button>
                </div>
            </div>
            
            <!-- Navigation Buttons -->
            <button onclick="previousStory()" class="absolute left-4 top-1/2 transform -translate-y-1/2 text-white text-3xl hover:text-gray-300">
                <i class="fas fa-chevron-left"></i>
            </button>
            <button onclick="nextStory()" class="absolute right-4 top-1/2 transform -translate-y-1/2 text-white text-3xl hover:text-gray-300">
                <i class="fas fa-chevron-right"></i>
            </button>
            
            <img id="storyImage" src="" alt="Story" class="max-w-full max-h-full object-contain hidden">
            <video id="storyVideo" src="" alt="Story" class="max-w-full max-h-full object-contain hidden" controls>
                Tarayıcınız video oynatmayı desteklemiyor.
            </video>
            
            <!-- Story Info -->
            <div class="story-info absolute bottom-4 left-4 text-white">
                <h6 id="storyUser" class="font-semibold"></h6>
                <p id="storyCaption" class="text-sm opacity-90"></p>
                <p id="storyCounter" class="text-xs opacity-70"></p>
                
                <!-- Story Actions -->
                <div class="flex items-center space-x-6 mt-4 z-10 relative">
                    <button onclick="toggleStoryLike()" id="storyLikeBtn" class="flex items-center space-x-2 hover:opacity-80 transition-opacity text-white bg-black/50 px-4 py-2 rounded-full border border-white/20">
                        <i id="storyLikeIcon" class="far fa-heart text-2xl"></i>
                        <span id="storyLikeCount" class="text-sm font-semibold">0</span>
                    </button>
                    <button onclick="showStoryComments()" id="storyCommentBtn" class="flex items-center space-x-2 hover:opacity-80 transition-opacity text-white bg-black/50 px-4 py-2 rounded-full border border-white/20">
                        <i class="far fa-comment text-2xl"></i>
                        <span id="storyCommentCount" class="text-sm font-semibold">0</span>
                    </button>
                </div>
            </div>
            
            <!-- Story Comment Input -->
            <?php if ($event['yorum_yapabilir'] ?? false): ?>
            <div id="storyCommentInput" class="absolute bottom-4 left-4 right-4">
                <div class="flex items-center space-x-2 bg-black/50 backdrop-blur-sm rounded-full px-4 py-2">
                    <input type="text" id="storyCommentText" placeholder="Yorum yazın..." 
                           class="flex-1 bg-transparent text-white placeholder-gray-300 focus:outline-none">
                    <button onclick="addStoryComment()" class="text-accent-pink hover:text-accent-purple transition-colors">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Story Comments Modal -->
    <div id="storyCommentsModal" class="fixed inset-0 bg-black/90 hidden flex items-center justify-center z-50">
        <div class="story-comments-content bg-bkg-light rounded-2xl p-6 max-w-md w-full mx-4 max-h-96 flex flex-col">
            <div class="flex justify-between items-center mb-4">
                <h3 class="text-lg font-bold gradient-text">Hikaye Yorumları</h3>
                <button onclick="hideStoryComments()" class="text-secondary hover:text-primary">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            
            <!-- Comments List -->
            <div id="storyCommentsList" class="flex-1 overflow-y-auto mb-4 space-y-3">
                <!-- Comments will be loaded here -->
            </div>
            
            <!-- Comment Input -->
            <?php if ($event['yorum_yapabilir'] ?? false): ?>
            <div class="flex items-center space-x-2">
                <input type="text" id="storyModalCommentText" placeholder="Yorum yazın..." 
                       class="flex-1 px-3 py-2 bg-bkg border border-white/10 rounded-lg text-primary placeholder-secondary focus:outline-none focus:border-accent-pink">
                <button onclick="addStoryCommentFromModal()" class="px-4 py-2 bg-gradient-to-r from-accent-pink to-accent-purple text-white rounded-lg hover:opacity-90 transition-opacity">
                    <i class="fas fa-paper-plane"></i>
                </button>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Upload Modal Functions
        function showUploadModal() {
            document.getElementById('uploadModal').classList.remove('hidden');
            document.getElementById('uploadModal').classList.add('flex');
        }

        function hideUploadModal() {
            document.getElementById('uploadModal').classList.add('hidden');
            document.getElementById('uploadModal').classList.remove('flex');
        }

        function showStoryUploadModal() {
            document.getElementById('storyUploadModal').classList.remove('hidden');
            document.getElementById('storyUploadModal').classList.add('flex');
        }

        function hideStoryUploadModal() {
            document.getElementById('storyUploadModal').classList.add('hidden');
            document.getElementById('storyUploadModal').classList.remove('flex');
        }

        // Story Viewer Functions
        let storyTimer = null;
        let currentUserStories = [];
        let currentStoryIndex = 0;
        let currentStoryId = null;

        function openUserStories(userId) {
            console.log('Opening stories for user:', userId);
            // Kullanıcının tüm hikayelerini al
            fetch(`ajax/get_user_stories.php?user_id=${userId}&event_id=<?php echo $event_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.stories.length > 0) {
                        currentUserStories = data.stories;
                        currentStoryIndex = 0;
                        showCurrentStory();
                        
                        document.getElementById('storyViewer').classList.remove('hidden');
                        document.getElementById('storyViewer').classList.add('flex');
                        
                        // Progress bar animasyonu
                        startStoryProgress();
                    } else {
                        alert('Hikaye bulunamadı.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Hikaye yüklenirken hata oluştu.');
                });
        }

        function showCurrentStory() {
            if (currentUserStories.length === 0) return;
            
            const story = currentUserStories[currentStoryIndex];
            currentStoryId = story.id; // Global değişkende sakla
            
            const storyImage = document.getElementById('storyImage');
            const storyVideo = document.getElementById('storyVideo');
            const deleteBtn = document.getElementById('deleteStoryBtn');
            
            // Dosya türünü kontrol et
            const fileExtension = story.dosya_yolu.split('.').pop().toLowerCase();
            const isVideo = ['mp4', 'mov', 'avi'].includes(fileExtension);
            
            if (isVideo) {
                // Video göster
                storyImage.classList.add('hidden');
                storyVideo.classList.remove('hidden');
                storyVideo.src = story.dosya_yolu;
                storyVideo.load(); // Video'yu yeniden yükle
                
                // Video otomatik oynatma
                storyVideo.addEventListener('loadeddata', function() {
                    storyVideo.play();
                });
                
                // Video bitince sonraki hikayeye geç
                storyVideo.addEventListener('ended', function() {
                    nextStory();
                });
            } else {
                // Resim göster
                storyVideo.classList.add('hidden');
                storyImage.classList.remove('hidden');
                storyImage.src = story.dosya_yolu;
            }
            
            document.getElementById('storyUser').textContent = story.user_name + ' ' + story.user_surname;
            document.getElementById('storyCaption').textContent = story.aciklama || '';
            document.getElementById('storyCounter').textContent = `${currentStoryIndex + 1} / ${currentUserStories.length}`;
            
            // Menü butonunu göster/gizle (yetkili kullanıcılar için)
            const userRole = '<?php echo $_SESSION["user_role"] ?? "kullanici"; ?>';
            const storyMenuBtn = document.getElementById('storyMenuBtn');
            const storyMenuDropdown = document.getElementById('storyMenuDropdown');
            
            // Yetki kontrolü: Super Admin, Moderator, Yetkili Kullanıcı veya hikaye sahibi
            const canManageStory = userRole === 'super_admin' || 
                                 userRole === 'moderator' || 
                                 userRole === 'yetkili_kullanici' || 
                                 story.kullanici_id == <?php echo $user_id; ?>;
            
            if (canManageStory) {
                storyMenuBtn.classList.remove('hidden');
                // Mevcut hikaye ID'sini sakla
                storyMenuBtn.setAttribute('data-story-id', story.id);
                storyMenuBtn.setAttribute('data-story-owner', story.kullanici_id);
            } else {
                storyMenuBtn.classList.add('hidden');
            }
            
            // Hikaye beğeni ve yorum sayılarını yükle
            loadStoryStats(currentStoryId);
            
            // Progress bar'ı sıfırla
            document.getElementById('storyProgressBar').style.width = '0%';
        }

        function nextStory() {
            if (currentStoryIndex < currentUserStories.length - 1) {
                currentStoryIndex++;
                showCurrentStory();
                startStoryProgress();
            } else {
                closeStory();
            }
        }

        function previousStory() {
            if (currentStoryIndex > 0) {
                currentStoryIndex--;
                showCurrentStory();
                startStoryProgress();
            }
        }

        function closeStory() {
            document.getElementById('storyViewer').classList.add('hidden');
            document.getElementById('storyViewer').classList.remove('flex');
            if (storyTimer) {
                clearInterval(storyTimer);
            }
        }

        function startStoryProgress() {
            if (storyTimer) {
                clearInterval(storyTimer);
            }
            
            const progressBar = document.getElementById('storyProgressBar');
            const storyVideo = document.getElementById('storyVideo');
            let progress = 0;
            
            // Video varsa video süresini kullan, yoksa 24 saniye
            let duration = 24; // varsayılan süre (saniye)
            
            if (!storyVideo.classList.contains('hidden') && storyVideo.duration) {
                duration = Math.min(storyVideo.duration, 59); // Maksimum 59 saniye
            }
            
            const interval = (duration * 1000) / 100; // Her %1 için ms
            
            storyTimer = setInterval(() => {
                progress += 1;
                progressBar.style.width = progress + '%';
                
                if (progress >= 100) {
                    nextStory();
                }
            }, interval);
        }

        function toggleStoryMenu() {
            const dropdown = document.getElementById('storyMenuDropdown');
            dropdown.classList.toggle('hidden');
        }

        function editCurrentStory() {
            const storyMenuBtn = document.getElementById('storyMenuBtn');
            const storyId = storyMenuBtn.getAttribute('data-story-id');
            const currentStory = currentUserStories.find(story => story.id == storyId);
            
            if (!currentStory) return;
            
            const newCaption = prompt('Hikaye açıklamasını düzenleyin:', currentStory.aciklama || '');
            
            if (newCaption !== null) {
                fetch('ajax/edit_story.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        story_id: storyId,
                        caption: newCaption
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Hikaye listesini güncelle
                        currentStory.aciklama = newCaption;
                        document.getElementById('storyCaption').textContent = newCaption || '';
                        alert('Hikaye açıklaması güncellendi!');
                    } else {
                        alert('Hikaye düzenlenirken hata oluştu: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Hikaye düzenlenirken hata oluştu.');
                });
            }
            
            // Menüyü kapat
            document.getElementById('storyMenuDropdown').classList.add('hidden');
        }

        function deleteCurrentStory() {
            const storyMenuBtn = document.getElementById('storyMenuBtn');
            const storyId = storyMenuBtn.getAttribute('data-story-id');
            
            if (!confirm('Bu hikayeyi silmek istediğinizden emin misiniz?')) {
                return;
            }
            
            fetch('ajax/delete_story.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    story_id: storyId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Hikayeyi listeden çıkar
                    currentUserStories = currentUserStories.filter(story => story.id != storyId);
                    
                    if (currentUserStories.length === 0) {
                        // Tüm hikayeler silindi, modalı kapat
                        closeStory();
                    } else {
                        // Mevcut hikaye silindiyse, önceki hikayeye geç
                        if (currentStoryIndex >= currentUserStories.length) {
                            currentStoryIndex = currentUserStories.length - 1;
                        }
                        showCurrentStory();
                    }
                    
                    alert('Hikaye başarıyla silindi!');
                } else {
                    alert('Hikaye silinirken hata oluştu: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Hikaye silinirken hata oluştu.');
            });
            
            // Menüyü kapat
            document.getElementById('storyMenuDropdown').classList.add('hidden');
        }

        // Story Like and Comment Functions
        function loadStoryStats(storyId) {
            fetch(`ajax/get_story_stats.php?id=${storyId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('storyLikeCount').textContent = data.likes;
                        document.getElementById('storyCommentCount').textContent = data.comments;
                        
                        // Beğeni durumunu güncelle
                        const likeIcon = document.getElementById('storyLikeIcon');
                        if (data.user_liked) {
                            likeIcon.classList.remove('far');
                            likeIcon.classList.add('fas');
                            likeIcon.style.color = '#DB61A2';
                        } else {
                            likeIcon.classList.remove('fas');
                            likeIcon.classList.add('far');
                            likeIcon.style.color = '';
                        }
                    } else {
                        console.error('Failed to load story stats:', data.message);
                    }
                })
                .catch(error => console.error('Error loading story stats:', error));
        }

        function toggleStoryLike() {
            if (!currentStoryId) {
                console.error('Current story ID not found');
                return;
            }
            
            fetch('ajax/toggle_story_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    story_id: currentStoryId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Beğeni sayısını güncelle
                    document.getElementById('storyLikeCount').textContent = data.likes;
                    
                    // Beğeni ikonunu güncelle
                    const likeIcon = document.getElementById('storyLikeIcon');
                    if (data.liked) {
                        likeIcon.classList.remove('far');
                        likeIcon.classList.add('fas');
                        likeIcon.style.color = '#DB61A2';
                    } else {
                        likeIcon.classList.remove('fas');
                        likeIcon.classList.add('far');
                        likeIcon.style.color = '';
                    }
                } else {
                    console.error('Failed to toggle like:', data.message);
                }
            })
            .catch(error => console.error('Error toggling story like:', error));
        }

        function showStoryComments() {
            if (!currentStoryId) {
                console.error('Current story ID not found');
                return;
            }
            
            // Progress bar'ı durdur
            pauseStoryProgress();
            
            // Yorumları yükle
            loadStoryComments(currentStoryId);
            
            // Modalı göster
            document.getElementById('storyCommentsModal').classList.remove('hidden');
            document.getElementById('storyCommentsModal').classList.add('flex');
        }

        function hideStoryComments() {
            document.getElementById('storyCommentsModal').classList.add('hidden');
            document.getElementById('storyCommentsModal').classList.remove('flex');
            
            // Progress bar'ı devam ettir
            resumeStoryProgress();
        }

        function pauseStoryProgress() {
            if (storyTimer) {
                clearInterval(storyTimer);
                storyTimer = null;
            }
        }

        function resumeStoryProgress() {
            if (!storyTimer) {
                startStoryProgress();
            }
        }

        function loadStoryComments(storyId) {
            fetch(`ajax/get_story_comments.php?id=${storyId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayStoryComments(data.comments);
                    }
                })
                .catch(error => console.error('Error loading story comments:', error));
        }

        function displayStoryComments(comments) {
            const commentsList = document.getElementById('storyCommentsList');
            commentsList.innerHTML = '';
            
            // Yetki kontrolü için kullanıcı bilgileri (fonksiyon dışında tanımla)
            const currentUserId = <?php echo $user_id; ?>;
            const userRole = '<?php echo $_SESSION["user_role"] ?? "kullanici"; ?>';
            const participantRole = '<?php echo $event["katilimci_rol"] ?? "kullanici"; ?>';
            
            comments.forEach(comment => {
                const commentDiv = document.createElement('div');
                commentDiv.className = 'flex items-start space-x-3';
                
                // Yetki kontrolü
                const canEditComment = comment.kullanici_id == currentUserId || 
                                     userRole === 'super_admin' || 
                                     userRole === 'moderator' || 
                                     participantRole === 'yetkili_kullanici';
                
                // Debug için
                console.log('Comment debug:', {
                    commentId: comment.id,
                    commentUserId: comment.kullanici_id,
                    currentUserId: currentUserId,
                    userRole: userRole,
                    participantRole: participantRole,
                    canEditComment: canEditComment,
                    commentData: comment
                });
                
                commentDiv.innerHTML = `
                    <img src="${comment.user_profile || 'assets/images/default_profile.svg'}" 
                         alt="${comment.user_name}" 
                         class="w-8 h-8 rounded-full object-cover">
                    <div class="flex-1">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center space-x-2">
                                <span class="font-semibold text-primary cursor-pointer hover:text-accent-pink transition-colors" 
                                      onclick="openParticipantMenu(${comment.kullanici_id}, '${comment.user_name}')">${comment.user_name}</span>
                                <span class="text-xs text-secondary">${comment.time_ago}</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                ${canEditComment ? `
                                    <button onclick="editStoryComment(${comment.id}, '${comment.yorum_metni.replace(/'/g, "\\'")}')" 
                                            class="text-secondary hover:text-primary text-xs">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button onclick="deleteStoryComment(${comment.id})" 
                                            class="text-red-400 hover:text-red-300 text-xs">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                ` : ''}
                                <button onclick="toggleCommentLike(${comment.id})" 
                                        class="text-secondary hover:text-primary text-xs">
                                    <i class="far fa-heart" id="commentLikeIcon_${comment.id}"></i>
                                    <span id="commentLikeCount_${comment.id}" class="ml-1 text-xs">0</span>
                                </button>
                            </div>
                        </div>
                        <p class="text-primary text-sm mt-1">${comment.yorum_metni.replace(/'/g, "\\'")}</p>
                    </div>
                `;
                commentsList.appendChild(commentDiv);
                
                console.log('Comment HTML added with ID:', comment.id);
                console.log('Looking for elements with IDs:');
                console.log('- commentLikeIcon_' + comment.id);
                console.log('- commentLikeCount_' + comment.id);
                
                // Yorum beğeni sayısını yükle
                loadCommentLikes(comment.id);
            });
        }

        function loadCommentLikes(commentId) {
            console.log('Loading likes for comment:', commentId);
            
            fetch(`ajax/get_comment_likes.php?id=${commentId}`)
                .then(response => response.json())
                .then(data => {
                    console.log('Comment likes response:', data);
                    if (data.success) {
                        const countElement = document.getElementById(`commentLikeCount_${commentId}`);
                        console.log('Count element found:', countElement);
                        console.log('Setting count to:', data.likes);
                        
                        if (countElement) {
                            countElement.textContent = data.likes;
                            console.log('Count set to:', countElement.textContent);
                        } else {
                            console.error('Count element not found!');
                        }
                        
                        // Beğeni durumunu güncelle
                        const likeIcon = document.getElementById(`commentLikeIcon_${commentId}`);
                        console.log('Like icon found:', likeIcon);
                        console.log('User liked:', data.user_liked);
                        
                        if (likeIcon) {
                            if (data.user_liked) {
                                likeIcon.classList.remove('far');
                                likeIcon.classList.add('fas');
                                likeIcon.style.color = '#DB61A2';
                                console.log('Icon set to liked (fas, pink)');
                            } else {
                                likeIcon.classList.remove('fas');
                                likeIcon.classList.add('far');
                                likeIcon.style.color = '';
                                console.log('Icon set to unliked (far, default)');
                            }
                            console.log('Final icon classes:', likeIcon.className);
                        } else {
                            console.error('Like icon not found!');
                        }
                    }
                })
                .catch(error => console.error('Error loading comment likes:', error));
        }

        function addStoryComment() {
            if (!currentStoryId) {
                console.error('Current story ID not found');
                return;
            }
            
            const commentText = document.getElementById('storyCommentText').value.trim();
            
            if (!commentText) return;
            
            // Progress bar'ı durdur
            pauseStoryProgress();
            
            fetch('ajax/add_story_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    story_id: currentStoryId,
                    content: commentText
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Yorum sayısını güncelle
                    const currentCount = parseInt(document.getElementById('storyCommentCount').textContent);
                    document.getElementById('storyCommentCount').textContent = currentCount + 1;
                    
                    // Input'u temizle
                    document.getElementById('storyCommentText').value = '';
                    
                    // Yorumları yeniden yükle
                    loadStoryComments(currentStoryId);
                } else {
                    alert('Yorum eklenirken hata oluştu: ' + data.message);
                }
                
                // Progress bar'ı devam ettir
                resumeStoryProgress();
            })
            .catch(error => {
                console.error('Error adding story comment:', error);
                // Progress bar'ı devam ettir
                resumeStoryProgress();
            });
        }

        function addStoryCommentFromModal() {
            if (!currentStoryId) {
                console.error('Current story ID not found');
                return;
            }
            
            const commentText = document.getElementById('storyModalCommentText').value.trim();
            
            if (!commentText) return;
            
            fetch('ajax/add_story_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    story_id: currentStoryId,
                    content: commentText
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Yorum sayısını güncelle
                    const currentCount = parseInt(document.getElementById('storyCommentCount').textContent);
                    document.getElementById('storyCommentCount').textContent = currentCount + 1;
                    
                    // Input'u temizle
                    document.getElementById('storyModalCommentText').value = '';
                    
                    // Yorumları yeniden yükle
                    loadStoryComments(currentStoryId);
                } else {
                    alert('Yorum eklenirken hata oluştu: ' + data.message);
                }
            })
            .catch(error => console.error('Error adding story comment:', error));
        }

        function editStoryComment(commentId, currentText) {
            const newText = prompt('Yorumu düzenleyin:', currentText);
            
            if (newText !== null && newText.trim() !== '') {
                fetch('ajax/edit_story_comment.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        comment_id: commentId,
                        content: newText.trim()
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Yorumları yeniden yükle
                        loadStoryComments(currentStoryId);
                    } else {
                        alert('Yorum düzenlenirken hata oluştu: ' + data.message);
                    }
                })
                .catch(error => console.error('Error editing story comment:', error));
            }
        }

        function deleteStoryComment(commentId) {
            if (!confirm('Bu yorumu silmek istediğinizden emin misiniz?')) {
                return;
            }
            
            fetch('ajax/delete_story_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    comment_id: commentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Yorum sayısını güncelle
                    const currentCount = parseInt(document.getElementById('storyCommentCount').textContent);
                    document.getElementById('storyCommentCount').textContent = currentCount - 1;
                    
                    // Yorumları yeniden yükle
                    loadStoryComments(currentStoryId);
                } else {
                    alert('Yorum silinirken hata oluştu: ' + data.message);
                }
            })
            .catch(error => console.error('Error deleting story comment:', error));
        }

        function toggleCommentLike(commentId) {
            console.log('Toggling like for comment:', commentId);
            
            fetch('ajax/toggle_comment_like.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    comment_id: commentId
                })
            })
            .then(response => response.json())
            .then(data => {
                console.log('Like response:', data);
                if (data.success) {
                    // Beğeni sayısını güncelle
                    const countElement = document.getElementById(`commentLikeCount_${commentId}`);
                    console.log('Count element found:', countElement);
                    console.log('Current count text:', countElement ? countElement.textContent : 'null');
                    console.log('New count:', data.likes);
                    
                    if (countElement) {
                        countElement.textContent = data.likes;
                        console.log('Count updated to:', countElement.textContent);
                    } else {
                        console.error('Count element not found!');
                    }
                    
                    // Beğeni ikonunu güncelle
                    const likeIcon = document.getElementById(`commentLikeIcon_${commentId}`);
                    console.log('Like icon found:', likeIcon);
                    console.log('Current icon classes:', likeIcon ? likeIcon.className : 'null');
                    
                    if (likeIcon) {
                        if (data.liked) {
                            likeIcon.classList.remove('far');
                            likeIcon.classList.add('fas');
                            likeIcon.style.color = '#DB61A2';
                            console.log('Icon updated to liked (fas, pink)');
                        } else {
                            likeIcon.classList.remove('fas');
                            likeIcon.classList.add('far');
                            likeIcon.style.color = '';
                            console.log('Icon updated to unliked (far, default)');
                        }
                        console.log('Final icon classes:', likeIcon.className);
                    } else {
                        console.error('Like icon not found!');
                    }
                } else {
                    console.error('Failed to toggle comment like:', data.message);
                }
            })
            .catch(error => console.error('Error toggling comment like:', error));
        }

        // Close modal when clicking outside
        document.getElementById('uploadModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideUploadModal();
            }
        });

        // Close story menu when clicking outside
        document.addEventListener('click', function(e) {
            const storyMenuDropdown = document.getElementById('storyMenuDropdown');
            const storyMenuBtn = document.getElementById('storyMenuBtn');
            
            if (storyMenuDropdown && !storyMenuBtn.contains(e.target) && !storyMenuDropdown.contains(e.target)) {
                storyMenuDropdown.classList.add('hidden');
            }
        });

        // Close comments modal when clicking outside
        document.getElementById('commentsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideCommentsModal();
            }
        });

        // Post Interaction Functions
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
                    const button = document.querySelector(`[data-media-id="${mediaId}"].like-btn`);
                    const heartIcon = button.querySelector('i');
                    const likeCount = document.querySelector(`[data-media-id="${mediaId}"].like-count`);
                    
                    // Update heart color
                    if (data.liked) {
                        heartIcon.classList.remove('text-primary', 'hover:text-red-500');
                        heartIcon.classList.add('text-red-500');
                        button.setAttribute('data-liked', 'true');
                    } else {
                        heartIcon.classList.remove('text-red-500');
                        heartIcon.classList.add('text-primary', 'hover:text-red-500');
                        button.setAttribute('data-liked', 'false');
                    }
                    
                    // Add heart animation
                    heartIcon.classList.add('like-animation');
                    setTimeout(() => {
                        heartIcon.classList.remove('like-animation');
                    }, 600);
                    
                    // Update like count
                    if (likeCount) {
                        const currentCount = parseInt(likeCount.textContent);
                        likeCount.textContent = data.liked ? currentCount + 1 : currentCount - 1;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        let currentMediaId = null;

        function openComments(mediaId) {
            currentMediaId = mediaId;
            document.getElementById('commentsModal').classList.remove('hidden');
            document.getElementById('commentsModal').classList.add('flex');
            loadComments(mediaId);
        }

        function hideCommentsModal() {
            document.getElementById('commentsModal').classList.add('hidden');
            document.getElementById('commentsModal').classList.remove('flex');
            currentMediaId = null;
        }

        function loadComments(mediaId) {
            console.log('Loading comments for media:', mediaId);
            
            fetch(`ajax/get_comments.php?media_id=${mediaId}`)
                .then(response => {
                    console.log('Response status:', response.status);
                    console.log('Response headers:', response.headers);
                    return response.text().then(text => {
                        console.log('Raw response:', text);
                        try {
                            return JSON.parse(text);
                        } catch (e) {
                            console.error('JSON parse error:', e);
                            throw new Error('Invalid JSON response: ' + text);
                        }
                    });
                })
                .then(data => {
                    console.log('Comments data:', data);
                    if (data.success) {
                        displayComments(data.comments);
                        // Load replies for each comment
                        data.comments.forEach(comment => {
                            loadReplies(comment.id);
                        });
                    } else {
                        console.error('Error loading comments:', data.message);
                        alert('Yorumlar yüklenirken hata oluştu: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Yorumlar yüklenirken hata oluştu: ' + error.message);
                });
        }

        function displayComments(comments) {
            console.log('Displaying comments:', comments);
            const commentsList = document.getElementById('commentsList');
            
            if (comments.length === 0) {
                console.log('No comments found');
                commentsList.innerHTML = '<p class="text-secondary text-center py-8">Henüz yorum yok</p>';
                return;
            }

            console.log('Rendering', comments.length, 'comments');
            
            let html = '';
            comments.forEach(comment => {
                const editButtons = comment.can_edit ? `
                    <div class="flex items-center space-x-2">
                        <button onclick="editComment(${comment.id}, '${comment.content.replace(/'/g, "\\'")}')" 
                                class="text-secondary hover:text-primary transition-colors text-xs">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button onclick="deleteComment(${comment.id})" 
                                class="text-secondary hover:text-red-500 transition-colors text-xs">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                ` : '';
                
                html += `
                    <div class="flex items-start space-x-3 comment-item" data-comment-id="${comment.id}">
                        <img src="${comment.user_profile_photo}" 
                             alt="${comment.user_name}" 
                             class="w-8 h-8 rounded-full object-cover">
                        <div class="flex-1">
                            <div class="bg-white/5 rounded-lg p-3">
                                <div class="flex items-center justify-between">
                                    <p class="font-semibold text-sm text-primary cursor-pointer hover:text-accent-pink transition-colors" 
                                       onclick="openParticipantMenu(${comment.user_id}, '${comment.user_name}')">${comment.user_name}</p>
                                    ${editButtons}
                                </div>
                                <p class="text-sm text-secondary mt-1 comment-content">${comment.content}</p>
                                
                                <!-- Comment Actions -->
                                <div class="flex items-center space-x-4 mt-2 pt-2 border-t border-white/10">
                                    <button onclick="toggleCommentLike(${comment.id})" 
                                            class="flex items-center space-x-1 text-secondary hover:text-red-500 transition-colors text-xs">
                                        <i class="fas fa-heart ${comment.user_liked ? 'text-red-500' : ''}"></i>
                                        <span class="comment-like-count" data-comment-id="${comment.id}">${comment.like_count || 0}</span>
                                    </button>
                                    <button onclick="toggleReplyForm(${comment.id})" 
                                            class="flex items-center space-x-1 text-secondary hover:text-primary transition-colors text-xs">
                                        <i class="fas fa-reply"></i>
                                        <span>Yanıtla</span>
                                    </button>
                                </div>
                                
                                <!-- Reply Form (Hidden by default) -->
                                <div id="reply-form-${comment.id}" class="mt-3 hidden">
                                    <div class="flex items-center space-x-2">
                                        <input type="text" 
                                               placeholder="Yanıt yazın..." 
                                               class="bg-transparent flex-1 text-sm text-primary placeholder-secondary border border-white/10 rounded-lg px-3 py-2"
                                               id="reply-input-${comment.id}">
                                        <button onclick="addReply(${comment.id})" 
                                                class="text-primary hover:text-secondary transition-colors">
                                            <i class="fas fa-paper-plane"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Replies -->
                                <div id="replies-${comment.id}" class="mt-3 space-y-2">
                                    <!-- Replies will be loaded here -->
                                </div>
                            </div>
                            <p class="text-xs text-secondary mt-1">${comment.created_at}</p>
                        </div>
                    </div>
                `;
            });
            
            commentsList.innerHTML = html;
        }

        function addModalComment() {
            const input = document.getElementById('modalCommentInput');
            const content = input.value.trim();
            
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
                    input.value = '';
                    loadComments(currentMediaId); // Reload comments
                    
                    // Update comment count on main page
                    const commentCount = document.querySelector(`[data-media-id="${currentMediaId}"].comment-count`);
                    if (commentCount) {
                        const currentCount = parseInt(commentCount.textContent);
                        commentCount.textContent = currentCount + 1;
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        function editComment(commentId, currentContent) {
            const newContent = prompt('Yorumu düzenle:', currentContent);
            if (newContent === null || newContent.trim() === '') return;
            
            if (newContent.trim() === currentContent) return;

            fetch('ajax/update_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    comment_id: commentId,
                    content: newContent.trim()
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update comment content in UI
                    const commentItem = document.querySelector(`[data-comment-id="${commentId}"]`);
                    const contentElement = commentItem.querySelector('.comment-content');
                    contentElement.textContent = newContent.trim();
                } else {
                    alert('Yorum güncellenirken hata oluştu: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Yorum güncellenirken hata oluştu.');
            });
        }

        function deleteComment(commentId) {
            if (!confirm('Bu yorumu silmek istediğinizden emin misiniz?')) return;

            fetch('ajax/delete_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    comment_id: commentId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove comment from UI
                    const commentItem = document.querySelector(`[data-comment-id="${commentId}"]`);
                    commentItem.remove();
                    
                    // Update comment count
                    const commentCount = document.querySelector(`[data-media-id="${currentMediaId}"].comment-count`);
                    if (commentCount) {
                        const currentCount = parseInt(commentCount.textContent);
                        commentCount.textContent = currentCount - 1;
                        
                        // Hide comment count if no comments left
                        if (currentCount - 1 === 0) {
                            const commentSection = commentCount.closest('.text-sm.text-secondary');
                            if (commentSection) {
                                commentSection.style.display = 'none';
                            }
                        }
                    }
                    
                    // Reload comments to refresh the list
                    loadComments(currentMediaId);
                } else {
                    alert('Yorum silinirken hata oluştu: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Yorum silinirken hata oluştu.');
            });
        }

        function editMedia(mediaId, currentDescription) {
            const newDescription = prompt('Medya açıklamasını düzenle:', currentDescription);
            if (newDescription === null) return;
            
            if (newDescription.trim() === currentDescription) return;

            fetch('ajax/update_media.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    media_id: mediaId,
                    description: newDescription.trim()
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update media description in UI
                    const mediaCard = document.querySelector(`[data-media-id="${mediaId}"]`)?.closest('.post-card');
                    if (mediaCard) {
                        const descriptionElement = mediaCard.querySelector('.text-secondary');
                        if (descriptionElement) {
                            descriptionElement.textContent = newDescription.trim();
                        }
                    }
                } else {
                    alert('Medya güncellenirken hata oluştu: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Medya güncellenirken hata oluştu.');
            });
        }

        function deleteMedia(mediaId) {
            if (!confirm('Bu medyayı silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.')) return;

            fetch('ajax/delete_media.php', {
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
                    // Remove media card from UI
                    const mediaCard = document.querySelector(`[data-media-id="${mediaId}"]`)?.closest('.post-card');
                    if (mediaCard) {
                        mediaCard.remove();
                    }
                    
                    // Show success message
                    alert('Medya başarıyla silindi.');
                } else {
                    alert('Medya silinirken hata oluştu: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Medya silinirken hata oluştu.');
            });
        }

        function toggleReplyForm(commentId) {
            const replyForm = document.getElementById(`reply-form-${commentId}`);
            if (replyForm.classList.contains('hidden')) {
                replyForm.classList.remove('hidden');
                document.getElementById(`reply-input-${commentId}`).focus();
            } else {
                replyForm.classList.add('hidden');
            }
        }

        function addReply(commentId) {
            const input = document.getElementById(`reply-input-${commentId}`);
            const content = input.value.trim();
            
            if (!content) return;

            fetch('ajax/add_reply.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    parent_comment_id: commentId,
                    content: content
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    input.value = '';
                    document.getElementById(`reply-form-${commentId}`).classList.add('hidden');
                    loadReplies(commentId); // Reload replies
                } else {
                    alert('Yanıt eklenirken hata oluştu: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Yanıt eklenirken hata oluştu.');
            });
        }

        function loadReplies(commentId) {
            fetch(`ajax/get_replies.php?comment_id=${commentId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayReplies(commentId, data.replies);
                    }
                })
                .catch(error => {
                    console.error('Error loading replies:', error);
                });
        }

        function displayReplies(commentId, replies) {
            const repliesContainer = document.getElementById(`replies-${commentId}`);
            
            if (replies.length === 0) {
                repliesContainer.innerHTML = '';
                return;
            }

            repliesContainer.innerHTML = replies.map(reply => `
                <div class="flex items-start space-x-2 ml-4">
                    <img src="${reply.user_profile_photo}" 
                         alt="${reply.user_name}" 
                         class="w-6 h-6 rounded-full object-cover">
                    <div class="flex-1">
                        <div class="bg-white/5 rounded-lg p-2">
                            <p class="font-semibold text-xs text-primary">${reply.user_name}</p>
                            <p class="text-xs text-secondary mt-1">${reply.content}</p>
                        </div>
                        <p class="text-xs text-secondary mt-1">${reply.created_at}</p>
                    </div>
                </div>
            `).join('');
        }

        function sharePost(mediaId) {
            console.log('Share post:', mediaId);
            // Implement share functionality
        }

        function addComment(mediaId, inputElement) {
            const content = inputElement.value.trim();
            if (!content) return;

            fetch('ajax/add_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    media_id: mediaId,
                    content: content
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    inputElement.value = '';
                    
                    // Update comment count
                    const commentCount = document.querySelector(`[data-media-id="${mediaId}"].comment-count`);
                    if (commentCount) {
                        const currentCount = parseInt(commentCount.textContent);
                        commentCount.textContent = currentCount + 1;
                    } else {
                        // If no comment count exists, create it
                        const commentSection = inputElement.closest('.post-card').querySelector('.text-sm.text-secondary');
                        if (commentSection) {
                            commentSection.innerHTML = `<span class="comment-count" data-media-id="${mediaId}">1</span> yorumu görüntüle`;
                        }
                    }
                    
                    console.log('Comment added successfully');
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
        }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideUploadModal();
            }
        });
    </script>
    
    <!-- Debug için JavaScript syntax kontrolü -->
    <script>
        console.log('JavaScript syntax kontrolü başarılı');
        try {
            // displayStoryComments fonksiyonunu test et
            if (typeof displayStoryComments === 'function') {
                console.log('displayStoryComments fonksiyonu tanımlı');
            } else {
                console.error('displayStoryComments fonksiyonu tanımlı değil');
            }
        } catch (error) {
            console.error('JavaScript syntax hatası:', error);
        }
    </script>
    
    <!-- Katılımcı Yönetimi JavaScript -->
    <script>
        let currentParticipantId = null;
        let currentParticipantName = '';
        const eventId = <?php echo $event_id; ?>;
        
        // PHP değişkenlerini güvenli şekilde JavaScript'e geçir
        const userData = <?php echo json_encode([
            'user_id' => $user_id,
            'user_role' => $_SESSION['user_role'] ?? 'kullanici',
            'participant_role' => $event['katilimci_rol'] ?? 'kullanici',
            'can_manage_users' => (bool)($event['kullanici_engelleyebilir'] ?? false)
        ]); ?>;
        
        console.log('User data loaded:', userData);
        
        function openParticipantMenu(userId, userName) {
            // userName'i güvenli hale getir
            userName = userName.replace(/'/g, "\\'").replace(/"/g, '\\"');
            
            console.log('Permission check:', {
                userRole: userData.user_role,
                participantRole: userData.participant_role,
                canManageUsers: userData.can_manage_users,
                userName: userName
            });
            
            // Süper admin ve moderator her zaman erişebilir
            if (userData.user_role === 'super_admin' || userData.user_role === 'moderator') {
                console.log('Access granted: Admin/Moderator');
            }
            // Katılımcı rolü yetkili_kullanici ise ve kullanici_engelleyebilir yetkisi varsa
            else if (userData.participant_role === 'yetkili_kullanici' && userData.can_manage_users) {
                console.log('Access granted: Authorized participant');
            }
            else {
                console.log('Access denied: Insufficient permissions');
                return;
            }
            
            currentParticipantId = userId;
            currentParticipantName = userName;
            
            // Diğer modal'ları kapat
            hideUploadModal();
            hideStoryUploadModal();
            hideStoryComments();
            hideCommentsModal();
            
            // Kullanıcının yasaklı olup olmadığını kontrol et
            checkUserBanStatus(userId);
            
            document.getElementById('participantName').textContent = userName;
            document.getElementById('participantMenuModal').classList.remove('hidden');
            document.getElementById('participantMenuModal').classList.add('flex');
        }
        
        function checkUserBanStatus(userId) {
            fetch(`ajax/check_user_ban_status.php?user_id=${userId}&event_id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateParticipantMenuButtons(data.is_banned);
                    } else {
                        console.error('Error checking ban status:', data.message);
                        updateParticipantMenuButtons(false); // Default olarak yasaklı değil
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    updateParticipantMenuButtons(false); // Default olarak yasaklı değil
                });
        }
        
        function updateParticipantMenuButtons(isBanned) {
            const banButton = document.getElementById('banButton');
            const banIcon = banButton.querySelector('i');
            
            if (isBanned) {
                banButton.className = 'w-full px-4 py-3 bg-green-500/20 border border-green-500/30 text-green-400 rounded-lg hover:bg-green-500/30 transition-colors';
                banIcon.className = 'fas fa-unlock mr-2';
                banButton.innerHTML = '<i class="fas fa-unlock mr-2"></i>Yasağı Kaldır';
                banButton.onclick = unbanParticipant;
            } else {
                banButton.className = 'w-full px-4 py-3 bg-red-500/20 border border-red-500/30 text-red-400 rounded-lg hover:bg-red-500/30 transition-colors';
                banIcon.className = 'fas fa-ban mr-2';
                banButton.innerHTML = '<i class="fas fa-ban mr-2"></i>Kullanıcıyı Yasakla';
                banButton.onclick = banParticipant;
            }
        }
        
        function hideParticipantMenu() {
            document.getElementById('participantMenuModal').classList.add('hidden');
            document.getElementById('participantMenuModal').classList.remove('flex');
            currentParticipantId = null;
            currentParticipantName = '';
        }
        
        function banParticipant() {
            if (!currentParticipantId) return;
            
            if (!confirm(`${currentParticipantName} kullanıcısını yasaklamak istediğinizden emin misiniz?`)) {
                return;
            }
            
            fetch('ajax/ban_participant.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    target_user_id: currentParticipantId,
                    event_id: eventId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Kullanıcı başarıyla yasaklandı!');
                    hideParticipantMenu();
                    // Sayfayı yenile
                    location.reload();
                } else {
                    alert('Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bir hata oluştu!');
            });
        }
        
        function unbanParticipant() {
            if (!currentParticipantId) return;
            
            if (!confirm(`${currentParticipantName} kullanıcısının yasağını kaldırmak istediğinizden emin misiniz?`)) {
                return;
            }
            
            fetch('ajax/unban_participant.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    target_user_id: currentParticipantId,
                    event_id: eventId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Kullanıcının yasağı başarıyla kaldırıldı!');
                    hideParticipantMenu();
                    // Sayfayı yenile
                    location.reload();
                } else {
                    alert('Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bir hata oluştu!');
            });
        }
        
        function showPermissionsModal() {
            if (!currentParticipantId) return;
            
            // Diğer modal'ları kapat
            hideUploadModal();
            hideStoryUploadModal();
            hideStoryComments();
            hideCommentsModal();
            
            document.getElementById('permissionsParticipantName').textContent = currentParticipantName;
            
            // Mevcut yetkileri yükle
            loadParticipantPermissions();
            
            document.getElementById('participantMenuModal').classList.add('hidden');
            document.getElementById('participantMenuModal').classList.remove('flex');
            document.getElementById('permissionsModal').classList.remove('hidden');
            document.getElementById('permissionsModal').classList.add('flex');
        }
        
        function hidePermissionsModal() {
            document.getElementById('permissionsModal').classList.add('hidden');
            document.getElementById('permissionsModal').classList.remove('flex');
        }
        
        function loadParticipantPermissions() {
            fetch(`ajax/get_user_permissions.php?user_id=${currentParticipantId}&event_id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Checkbox'ları güncelle
                        document.getElementById('perm_medya_silebilir').checked = data.permissions.medya_silebilir;
                        document.getElementById('perm_yorum_silebilir').checked = data.permissions.yorum_silebilir;
                        document.getElementById('perm_kullanici_engelleyebilir').checked = data.permissions.kullanici_engelleyebilir;
                        document.getElementById('perm_medya_paylasabilir').checked = data.permissions.medya_paylasabilir;
                        document.getElementById('perm_yorum_yapabilir').checked = data.permissions.yorum_yapabilir;
                        document.getElementById('perm_hikaye_paylasabilir').checked = data.permissions.hikaye_paylasabilir;
                        document.getElementById('perm_profil_degistirebilir').checked = data.permissions.profil_degistirebilir;
                    } else {
                        // Hata durumunda default yetkileri göster
                        console.log('Setting default permissions due to error:', data.message);
                        setDefaultPermissions();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    // Hata durumunda default yetkileri göster
                    setDefaultPermissions();
                });
        }
        
        function setDefaultPermissions() {
            // Default yetkiler - yeni katılımcılar için temel yetkiler
            document.getElementById('perm_medya_silebilir').checked = false;
            document.getElementById('perm_yorum_silebilir').checked = false;
            document.getElementById('perm_kullanici_engelleyebilir').checked = false;
            document.getElementById('perm_medya_paylasabilir').checked = true;  // Default: medya paylaşabilir
            document.getElementById('perm_yorum_yapabilir').checked = true;    // Default: yorum yapabilir
            document.getElementById('perm_hikaye_paylasabilir').checked = true; // Default: hikaye paylaşabilir
            document.getElementById('perm_profil_degistirebilir').checked = true; // Default: profil değiştirebilir
        }
        
        function savePermissions() {
            if (!currentParticipantId) return;
            
            const permissions = {
                medya_silebilir: document.getElementById('perm_medya_silebilir').checked,
                yorum_silebilir: document.getElementById('perm_yorum_silebilir').checked,
                kullanici_engelleyebilir: document.getElementById('perm_kullanici_engelleyebilir').checked,
                medya_paylasabilir: document.getElementById('perm_medya_paylasabilir').checked,
                yorum_yapabilir: document.getElementById('perm_yorum_yapabilir').checked,
                hikaye_paylasabilir: document.getElementById('perm_hikaye_paylasabilir').checked,
                profil_degistirebilir: document.getElementById('perm_profil_degistirebilir').checked
            };
            
            console.log('Saving permissions:', {
                targetUserId: currentParticipantId,
                eventId: eventId,
                permissions: permissions,
                userData: userData
            });
            
            fetch('ajax/update_participant_permissions.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    target_user_id: currentParticipantId,
                    event_id: eventId,
                    permissions: permissions
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('Yetkiler başarıyla güncellendi!');
                    hidePermissionsModal();
                } else {
                    alert('Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bir hata oluştu!');
            });
        }
    </script>
    
    <!-- Toplu Katılımcı Yönetimi -->
    <script>
        // Toplu seçim fonksiyonları
        function updateSelection() {
            const checkboxes = document.querySelectorAll('.participant-checkbox:checked');
            const count = checkboxes.length;
            
            document.getElementById('selectedCount').textContent = count;
            document.getElementById('bulkActionText').textContent = `${count} kullanıcı seçildi`;
            
            const bulkPanel = document.getElementById('bulkActionsPanel');
            if (count > 0) {
                bulkPanel.classList.remove('hidden');
            } else {
                bulkPanel.classList.add('hidden');
            }
        }
        
        function selectAllParticipants() {
            const checkboxes = document.querySelectorAll('.participant-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = true;
            });
            updateSelection();
        }
        
        function clearSelection() {
            const checkboxes = document.querySelectorAll('.participant-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = false;
            });
            updateSelection();
        }
        
        function getSelectedUserIds() {
            const checkboxes = document.querySelectorAll('.participant-checkbox:checked');
            return Array.from(checkboxes).map(checkbox => checkbox.dataset.userId);
        }
        
        // Toplu işlem fonksiyonları
        function bulkBanParticipants() {
            const userIds = getSelectedUserIds();
            if (userIds.length === 0) return;
            
            if (!confirm(`${userIds.length} kullanıcıyı yasaklamak istediğinizden emin misiniz?`)) {
                return;
            }
            
            fetch('ajax/bulk_ban_participants.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_ids: userIds,
                    event_id: eventId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`${data.banned_count} kullanıcı başarıyla yasaklandı!`);
                    location.reload();
                } else {
                    alert('Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bir hata oluştu!');
            });
        }
        
        function bulkUnbanParticipants() {
            const userIds = getSelectedUserIds();
            if (userIds.length === 0) return;
            
            if (!confirm(`${userIds.length} kullanıcının yasağını kaldırmak istediğinizden emin misiniz?`)) {
                return;
            }
            
            fetch('ajax/bulk_unban_participants.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_ids: userIds,
                    event_id: eventId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`${data.unbanned_count} kullanıcının yasağı başarıyla kaldırıldı!`);
                    location.reload();
                } else {
                    alert('Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bir hata oluştu!');
            });
        }
        
        function bulkChangeRole() {
            const userIds = getSelectedUserIds();
            if (userIds.length === 0) return;
            
            const newRole = prompt('Yeni rol seçin:\n1. normal_kullanici\n2. yetkili_kullanici\n3. moderator', 'normal_kullanici');
            if (!newRole) return;
            
            const validRoles = ['normal_kullanici', 'yetkili_kullanici', 'moderator'];
            if (!validRoles.includes(newRole)) {
                alert('Geçersiz rol!');
                return;
            }
            
            if (!confirm(`${userIds.length} kullanıcının rolünü "${newRole}" olarak değiştirmek istediğinizden emin misiniz?`)) {
                return;
            }
            
            fetch('ajax/bulk_change_role.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_ids: userIds,
                    event_id: eventId,
                    new_role: newRole
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`${data.updated_count} kullanıcının rolü başarıyla değiştirildi!`);
                    location.reload();
                } else {
                    alert('Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bir hata oluştu!');
            });
        }
        
        function bulkSendMessage() {
            const userIds = getSelectedUserIds();
            if (userIds.length === 0) return;
            
            const message = prompt(`${userIds.length} kullanıcıya gönderilecek mesajı yazın:`, '');
            if (!message) return;
            
            fetch('ajax/bulk_send_message.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    user_ids: userIds,
                    event_id: eventId,
                    message: message
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`${data.sent_count} kullanıcıya mesaj başarıyla gönderildi!`);
                } else {
                    alert('Hata: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Bir hata oluştu!');
            });
        }
    </script>
    
    <!-- Real-time Updates -->
    <script>
        // Real-time updates
        let eventSource = null;
        let isRealtimeEnabled = true;
        
        function startRealtimeUpdates() {
            if (!isRealtimeEnabled) return;
            
            eventSource = new EventSource(`ajax/realtime_updates.php?event_id=${eventId}`);
            
            eventSource.onmessage = function(event) {
                try {
                    const data = JSON.parse(event.data);
                    
                    if (data.type === 'update') {
                        handleRealtimeUpdate(data.data);
                    }
                } catch (e) {
                    console.error('Error parsing SSE data:', e);
                }
            };
            
            eventSource.onerror = function(event) {
                console.log('SSE connection error, retrying in 5 seconds...');
                setTimeout(() => {
                    if (eventSource) {
                        eventSource.close();
                    }
                    startRealtimeUpdates();
                }, 5000);
            };
        }
        
        function handleRealtimeUpdate(updateData) {
            // Yeni medya varsa sayfayı yenile
            if (updateData.new_media > 0) {
                showNotification(`${updateData.new_media} yeni medya paylaşıldı!`, 'success');
                setTimeout(() => {
                    location.reload();
                }, 2000);
            }
            
            // Yeni hikaye varsa hikaye listesini güncelle
            if (updateData.new_stories > 0) {
                showNotification(`${updateData.new_stories} yeni hikaye paylaşıldı!`, 'info');
                loadStories(); // Hikaye listesini yeniden yükle
            }
            
            // Yeni yorum varsa yorum sayılarını güncelle
            if (updateData.new_comments > 0) {
                showNotification(`${updateData.new_comments} yeni yorum eklendi!`, 'info');
                updateCommentCounts();
            }
            
            // Yeni beğeni varsa beğeni sayılarını güncelle
            if (updateData.new_likes > 0) {
                updateLikeCounts();
            }
        }
        
        function showNotification(message, type = 'info') {
            // Basit bildirim sistemi
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-[80] ${
                type === 'success' ? 'bg-green-500' : 
                type === 'error' ? 'bg-red-500' : 
                'bg-blue-500'
            } text-white`;
            notification.textContent = message;
            
            document.body.appendChild(notification);
            
            // 3 saniye sonra kaldır
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        function updateCommentCounts() {
            // Tüm medya kartlarındaki yorum sayılarını güncelle
            document.querySelectorAll('[id^="commentCount_"]').forEach(element => {
                const mediaId = element.id.replace('commentCount_', '');
                loadMediaStats(mediaId);
            });
        }
        
        function updateLikeCounts() {
            // Tüm medya kartlarındaki beğeni sayılarını güncelle
            document.querySelectorAll('[id^="likeCount_"]').forEach(element => {
                const mediaId = element.id.replace('likeCount_', '');
                loadMediaStats(mediaId);
            });
        }
        
        function loadMediaStats(mediaId) {
            // Medya istatistiklerini yükle
            fetch(`ajax/get_media_stats.php?media_id=${mediaId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Beğeni sayısını güncelle
                        const likeCountElement = document.getElementById(`likeCount_${mediaId}`);
                        if (likeCountElement) {
                            likeCountElement.textContent = data.likes;
                        }
                        
                        // Yorum sayısını güncelle
                        const commentCountElement = document.getElementById(`commentCount_${mediaId}`);
                        if (commentCountElement) {
                            commentCountElement.textContent = data.comments;
                        }
                    }
                })
                .catch(error => {
                    console.error('Error loading media stats:', error);
                });
        }
        
        function loadStories() {
            // Hikaye listesini yeniden yükle
            fetch(`ajax/get_user_stories.php?user_id=${currentUserId}&event_id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        updateStoriesList(data.stories);
                    }
                })
                .catch(error => {
                    console.error('Error loading stories:', error);
                });
        }
        
        function updateStoriesList(stories) {
            const storiesContainer = document.querySelector('.stories-container');
            if (!storiesContainer) return;
            
            // Mevcut hikayeleri temizle
            storiesContainer.innerHTML = '';
            
            // Yeni hikayeleri ekle
            stories.forEach(story => {
                const storyElement = createStoryElement(story);
                storiesContainer.appendChild(storyElement);
            });
        }
        
        function createStoryElement(story) {
            const div = document.createElement('div');
            div.className = 'flex-shrink-0 text-center cursor-pointer group';
            div.onclick = () => openUserStories(story.kullanici_id);
            
            div.innerHTML = `
                <div class="story-profile-container group-hover:scale-105 transition-transform duration-300 relative mx-auto mb-3">
                    <div class="story-profile-inner">
                        <img src="${story.profil_fotografi || 'assets/images/default-avatar.png'}" 
                             alt="${story.ad}" 
                             class="story-profile-img">
                    </div>
                </div>
                <p class="text-xs text-secondary text-center break-words leading-tight px-2 mt-2">${story.ad}</p>
            `;
            
            return div;
        }
        
        // Sayfa yüklendiğinde real-time güncellemeleri başlat
        document.addEventListener('DOMContentLoaded', function() {
            startRealtimeUpdates();
        });
        
        // Sayfa kapatılırken SSE bağlantısını kapat
        window.addEventListener('beforeunload', function() {
            if (eventSource) {
                eventSource.close();
            }
        });
    </script>
    
    <!-- URL temizleme - success parametresini kaldır -->
    <script>
        // URL'de success parametresi varsa temizle
        if (window.location.search.includes('success=')) {
            const url = new URL(window.location);
            url.searchParams.delete('success');
            // History API ile URL'yi değiştir (sayfa yeniden yüklenmez)
            window.history.replaceState({}, document.title, url.pathname + url.search);
        }
    </script>
</body>
</html>