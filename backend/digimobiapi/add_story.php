<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/image_utils.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $event_id = $_POST['event_id'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($event_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Event ID is required.']);
        exit;
    }
    
    // ✅ Önce event'i oluşturan kullanıcıyı kontrol et (moderator/admin her zaman paylaşabilir)
    $stmt_event = $pdo->prepare("
        SELECT 
            d.id,
            d.moderator_id AS olusturan_id,
            d.dugun_tarihi,
            p.ucretsiz_erisim_gun
        FROM dugunler d
        LEFT JOIN paketler p ON d.paket_id = p.id
        WHERE d.id = ?
    ");
    $stmt_event->execute([$event_id]);
    $event_info = $stmt_event->fetch(PDO::FETCH_ASSOC);
    
    if (!$event_info) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Event not found.']);
        exit;
    }
    
    // ✅ Event'i oluşturan kullanıcı mı kontrol et (sadece bu event için bypass)
    $is_event_creator = (intval($event_info['olusturan_id'] ?? 0) === intval($user_id));
    
    // ✅ Sadece event creator participant kontrolünü atlar
    $bypass_participant_check = ($is_event_creator);
    
    // Check if user is participant of the event with package info
    $stmt = $pdo->prepare("
        SELECT 
            dk.kullanici_id, 
            dk.rol, 
            dk.yetkiler,
            d.dugun_tarihi,
            p.ucretsiz_erisim_gun
        FROM dugun_katilimcilar dk
        JOIN dugunler d ON dk.dugun_id = d.id
        LEFT JOIN paketler p ON d.paket_id = p.id
        WHERE dk.dugun_id = ? AND dk.kullanici_id = ?
    ");
    $stmt->execute([$event_id, $user_id]);
    $participant = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // ✅ Participant kontrolü - moderator/admin/event creator ise atla
    if (!$participant && !$bypass_participant_check) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'You are not a participant of this event.']);
        exit;
    }
    
    // ✅ Event bilgilerini participant'dan veya event_info'dan al
    if ($participant) {
        $event_date_str = $participant['dugun_tarihi'];
        $free_access_days = (int)($participant['ucretsiz_erisim_gun'] ?? 7);
        $participant_role = $participant['rol'] ?? 'kullanici';
        $participant_permissions = $participant['yetkiler'] ?? null;
    } else {
        // Moderator/Admin/Event creator için event_info'dan al
        $event_date_str = $event_info['dugun_tarihi'];
        $free_access_days = (int)($event_info['ucretsiz_erisim_gun'] ?? 7);
        $participant_role = 'moderator';
        $participant_permissions = null;
    }

    // ✅ Yetki kontrolü için tarih hesaplamaları
    $event_date = new DateTime($event_date_str);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    $event_date->setTime(0, 0, 0);
    
    $access_end_date = clone $event_date;
    $access_end_date->add(new DateInterval("P{$free_access_days}D"));
    
    $is_before_event = $today < $event_date;
    $is_after_access = $today > $access_end_date;
    
    // ✅ Rol kontrolü (case-insensitive)
    // NOT: Sadece O EVENT içindeki role bakıyoruz, global role bakmıyoruz!
    // Aksi halde başka bir event'te moderator olan kullanıcı, tüm eventlerde sınırsız yetki sahibi olur.
    $user_role_raw = $participant_role;
    $user_role = strtolower(trim($user_role_raw)); // ✅ Case-insensitive karşılaştırma için
    $is_moderator = ($user_role === 'moderator') || $is_event_creator;
    $is_admin = ($user_role === 'admin');
    $is_authorized = ($user_role === 'yetkili_kullanici');
    
    // ✅ Yetki kontrolü
    if ($is_moderator || $is_admin) {
        // Moderator/Admin: Her zaman hikaye paylaşabilir
        $can_share_story = true;
    } elseif ($is_authorized) {
        // Yetkili kullanıcı: JSON yetkiler + tarih kontrolü
        $permissions_raw = $participant_permissions ? json_decode($participant_permissions, true) : [];
        
        // ✅ Yetkiler array olarak geliyorsa (["hikaye_paylasabilir", ...]) in_array kullan
        // ✅ Ya da object olarak geliyorsa ({"hikaye_paylasabilir": true, ...}) key kontrolü yap
        $has_story_permission = false;
        
        if (is_array($permissions_raw)) {
            if (isset($permissions_raw[0])) {
                // Array formatı: ["medya_paylasabilir", "hikaye_paylasabilir"]
                // ✅ Güvenli kontrol: array_values ile numeric index'leri düzelt, array_map ile trim yap
                $permissions_clean = array_map('trim', array_values($permissions_raw));
                $has_story_permission = in_array('hikaye_paylasabilir', $permissions_clean, true); // strict comparison
            } else {
                // Object formatı: {"hikaye_paylasabilir": true}
                $has_story_permission = (bool)($permissions_raw['hikaye_paylasabilir'] ?? false);
            }
        }
        
        $can_share_story = $has_story_permission && !$is_before_event && !$is_after_access;
    } else {
        // Normal kullanıcı: Sadece aktif dönemde
        $can_share_story = !$is_before_event && !$is_after_access;
    }
    
    if (!$can_share_story) {
        $error_message = 'You do not have permission to share stories in this event.';
        if ($is_before_event) {
            $error_message = 'Event has not started yet. You cannot share stories before the event date.';
        } elseif ($is_after_access) {
            $error_message = "Free access period has ended. You cannot share stories after {$free_access_days} days from the event date.";
        }
        
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $error_message]);
        exit;
    }
    
    // ✅ Hikaye limiti kontrolü (medya limiti kadar hikaye paylaşılabilir)
    $story_limit = 0;
    try {
        // Önce paketler tablosunun varlığını kontrol et
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'paketler'");
        $table_exists = $stmt_check->rowCount() > 0;
        
        if ($table_exists) {
            // Paketler tablosu varsa, kolon varlığını kontrol et
            try {
                $stmt_check_col = $pdo->query("SHOW COLUMNS FROM paketler LIKE 'medya_limiti'");
                $col_exists = $stmt_check_col->rowCount() > 0;
            } catch (PDOException $e) {
                $col_exists = false;
            }
        } else {
            $col_exists = false;
        }
        
        if ($table_exists && $col_exists) {
            // Kolon varsa normal sorguyu çalıştır
            $stmt_package = $pdo->prepare("
                SELECT COALESCE(p.medya_limiti, 0) AS media_limit
                FROM dugunler d
                LEFT JOIN paketler p ON d.paket_id = p.id
                WHERE d.id = ?
            ");
            $stmt_package->execute([$event_id]);
            $package_info = $stmt_package->fetch(PDO::FETCH_ASSOC);
            $story_limit = (int)($package_info['media_limit'] ?? 0); // Hikaye limiti = medya limiti
        } else {
            // Kolon yoksa limit yok (zaten 0)
            error_log("Add Story - Paketler tablosu veya medya_limiti kolonu yok, limit kontrolü atlanıyor");
        }
    } catch (PDOException $e) {
        // Eğer paketler tablosu veya medya_limiti kolonu yoksa, limit yok kabul et
        error_log("Add Story - Package limit query error (ignoring): " . $e->getMessage());
        $story_limit = 0;
    }
    
    // ✅ Mevcut hikaye sayısını al (sadece tur='hikaye' olanlar)
    $stmt_count = $pdo->prepare("
        SELECT COUNT(*) as story_count
        FROM medyalar
        WHERE dugun_id = ? AND tur = 'hikaye'
    ");
    $stmt_count->execute([$event_id]);
    $current_stories = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $current_story_count = (int)($current_stories['story_count'] ?? 0);
    
    error_log("Add Story - Hikaye limiti: $story_limit, Mevcut hikaye: $current_story_count");
    
    // ✅ Moderator/Admin değilse limit kontrolü yap
    if (!$is_moderator && !$is_admin && $story_limit > 0 && $current_story_count >= $story_limit) {
        error_log("Add Story - Hikaye limiti doldu!");
        http_response_code(403);
        echo json_encode([
            'success' => false, 
            'error' => 'Etkinlik hikaye alanı doldu. Lütfen eski hikayelerin silinmesini bekleyin.',
            'story_limit' => $story_limit,
            'current_count' => $current_story_count
        ]);
        exit;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['story_file']) || $_FILES['story_file']['error'] !== UPLOAD_ERR_OK) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
        exit;
    }
    
    $file = $_FILES['story_file'];
    
    // Debug: Log file info
    error_log("Story Upload - File Type: " . $file['type']);
    error_log("Story Upload - File Name: " . $file['name']);
    error_log("Story Upload - File Size: " . $file['size']);
    
    // Validate file type (more flexible)
    $allowed_types = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/quicktime', 'video/avi', 'video/mov'
    ];
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'mov', 'avi'];
    
    if (!in_array($file['type'], $allowed_types) && !in_array($file_extension, $allowed_extensions)) {
        error_log("Story Upload - Invalid file type: " . $file['type'] . " or extension: " . $file_extension);
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only images and videos are allowed.']);
        exit;
    }
    
    // Validate file size (max 50MB)
    if ($file['size'] > 50 * 1024 * 1024) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 50MB.']);
        exit;
    }
    
    try {
        // Create upload directory if not exists
        $upload_dir = '../uploads/stories/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'story_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            throw new Exception('Failed to move uploaded file.');
        }
        
        // Determine media type based on file extension
        $file_extension = strtolower($file_extension);
        $media_type = 'foto'; // Default to foto
        if (in_array($file_extension, ['mp4', 'mov', 'avi', 'mkv', 'webm'])) {
            $media_type = 'video';
        } elseif (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $media_type = 'foto';
        }
        
        // ✅ Thumbnail ve preview oluştur
        $thumbnail_path = null;
        $base_filename = pathinfo($filename, PATHINFO_FILENAME);
        
        if ($media_type == 'foto') {
            // Fotoğraflar için thumbnail ve preview oluştur
            $image_results = ImageUtils::processImage($file_path, $base_filename, $upload_dir, $file_extension, 'story');
            if ($image_results['thumbnail']) {
                $thumbnail_path = 'uploads/stories/' . basename($image_results['thumbnail']);
            }
            // Update filename to processed version if needed
            if ($image_results['original']) {
                $filename = basename($image_results['original']);
            }
        } elseif ($media_type == 'video') {
            // Videolar için thumbnail oluştur (FFmpeg ile)
            $thumbnail_filename = $base_filename . '_thumb.jpg';
            $thumbnail_path_full = $upload_dir . $thumbnail_filename;
            if (ImageUtils::generateVideoThumbnail($file_path, $thumbnail_path_full)) {
                $thumbnail_path = 'uploads/stories/' . $thumbnail_filename;
            }
        }
        
        // Insert story into database (medyalar table with tur = 'hikaye')
        $stmt = $pdo->prepare("
            INSERT INTO medyalar (dugun_id, kullanici_id, dosya_yolu, dosya_tipi, tur, aciklama, kucuk_resim_yolu, created_at)
            VALUES (?, ?, ?, ?, 'hikaye', ?, ?, NOW())
        ");
        $stmt->execute([$event_id, $user_id, 'uploads/stories/' . $filename, $media_type, $description, $thumbnail_path]);
        
        $story_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true, 
            'message' => 'Story uploaded successfully.',
            'story_id' => $story_id,
            'file_path' => 'uploads/stories/' . $filename,
            'media_type' => $media_type
        ]);
        
    } catch (Exception $e) {
        error_log("Add story error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Upload failed: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
}
?>
