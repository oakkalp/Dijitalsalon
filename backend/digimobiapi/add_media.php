<?php
// ✅ Error logging için output buffering başlat
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ✅ İlk satırlarda log ekle
error_log("=== Add Media Request ===");
error_log("Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'UNKNOWN'));
error_log("Content-Length: " . ($_SERVER['CONTENT_LENGTH'] ?? 'UNKNOWN'));

// ✅ Session cookie için önemli: output buffering başlat
ob_start();

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/image_utils.php';
require_once __DIR__ . '/cache_invalidation.php';

error_log("Bootstrap loaded successfully");
error_log("Add Media - Session ID: " . session_id());
error_log("Add Media - Session user_id: " . ($_SESSION['user_id'] ?? 'NULL'));
error_log("Add Media - Session name: " . session_name());
error_log("Add Media - Cookie headers: " . (isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'NOT SET'));
error_log("Add Media - All session data: " . print_r($_SESSION, true));

    if (!isset($_SESSION['user_id'])) {
    error_log("Add Media - User not authenticated");
    error_log("Add Media - Available session keys: " . implode(', ', array_keys($_SESSION)));
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

error_log("Add Media - User ID: " . $_SESSION['user_id']);

$user_id = $_SESSION['user_id'];

// ✅ Multipart form data'dan veri alma (hem $_POST hem $_REQUEST hem $_GET kontrol et)
$event_id = $_POST['event_id'] ?? $_REQUEST['event_id'] ?? $_GET['event_id'] ?? null;
$description = '';

// ✅ CRITICAL: Önce query parameter'dan al (en güvenilir yöntem - multipart form-data sorunlarından etkilenmez)
if (isset($_GET['description']) && $_GET['description'] !== '') {
    $description = urldecode($_GET['description']);
    error_log("Add Media - Description from GET (query parameter): " . substr($description, 0, 100));
}
// ✅ Sonra POST'tan al (multipart form-data için)
elseif (isset($_POST['description']) && $_POST['description'] !== '') {
    $description = $_POST['description'];
    error_log("Add Media - Description from POST: " . substr($description, 0, 100));
}
// ✅ Son çare olarak REQUEST'ten al
elseif (isset($_REQUEST['description']) && $_REQUEST['description'] !== '') {
    $description = $_REQUEST['description'];
    error_log("Add Media - Description from REQUEST: " . substr($description, 0, 100));
}

// ✅ Description'ı normalize et (trim ve boş string kontrolü)
$description = trim($description ?? '');

// ✅ CRITICAL: Eğer hala boşsa, multipart form-data'yı manuel parse et
if (empty($description) && !empty($_SERVER['CONTENT_TYPE']) && strpos($_SERVER['CONTENT_TYPE'], 'multipart/form-data') !== false) {
    // Multipart form-data'yı manuel parse et
    $raw_input = file_get_contents('php://input');
    if (!empty($raw_input)) {
        // Multipart boundary'yi bul
        preg_match('/boundary=(.*)$/', $_SERVER['CONTENT_TYPE'], $matches);
        if (!empty($matches[1])) {
            $boundary = trim($matches[1]);
            $parts = explode('--' . $boundary, $raw_input);
            
            foreach ($parts as $part) {
                // Description alanını bul
                if (preg_match('/name="description"/', $part)) {
                    // Content-Disposition ve Content-Type satırlarını atla
                    $lines = explode("\r\n", $part);
                    $content_start = false;
                    $content = '';
                    
                    foreach ($lines as $line) {
                        if ($content_start) {
                            // Boundary'ye kadar olan içeriği al
                            if (strpos($line, '--' . $boundary) !== false || strpos($line, 'Content-Disposition') !== false) {
                                break;
                            }
                            $content .= $line . "\r\n";
                        } elseif (trim($line) === '') {
                            $content_start = true;
                        }
                    }
                    
                    $description = trim($content);
                    error_log("Add Media - Description parsed from raw input: " . substr($description, 0, 100));
                    break;
                }
            }
        }
    }
}

// ✅ Final normalize - her zaman string olarak kaydet (NULL değil)
$description = trim($description ?? '');
error_log("Add Media - FINAL description: '" . $description . "' (length: " . strlen($description) . ")");
error_log("Add Media - FINAL description type: " . gettype($description));
error_log("Add Media - FINAL description empty check: " . (empty($description) ? 'YES' : 'NO'));

// ✅ Debug: Açıklama kontrolü
error_log("Add Media - Description from POST: " . ($_POST['description'] ?? 'NULL'));
error_log("Add Media - Description from REQUEST: " . ($_REQUEST['description'] ?? 'NULL'));
error_log("Add Media - Final description: " . ($description ?? 'NULL'));
error_log("Add Media - Description length: " . strlen($description ?? ''));
error_log("Add Media - POST array keys: " . implode(', ', array_keys($_POST)));
error_log("Add Media - REQUEST array keys: " . implode(', ', array_keys($_REQUEST)));
error_log("Add Media - POST array: " . print_r($_POST, true));
error_log("Add Media - CONTENT_TYPE: " . ($_SERVER['CONTENT_TYPE'] ?? 'NULL'));

if (empty($event_id)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Event ID is required.']);
    exit;
}

try {
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
        error_log("Add Media - Event not found: $event_id");
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Event not found.']);
        exit;
    }
    
    // ✅ Event'i oluşturan kullanıcı mı kontrol et (sadece bu event için yetki bypass)
    $is_event_creator = (intval($event_info['olusturan_id'] ?? 0) === intval($user_id));
    
    error_log("Add Media - Event creator check: " . ($is_event_creator ? 'Yes' : 'No'));
    
    // ✅ Sadece event creator participant kontrolünü atlar
    $bypass_participant_check = ($is_event_creator);
    
    // Check if user is a participant of the event with package info
    error_log("Add Media - Querying participant record...");
    $stmt = $pdo->prepare("
        SELECT 
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

    error_log("Add Media - Participant check: " . ($participant ? 'Found' : 'Not found'));
    if ($participant) {
        error_log("Add Media - Participant role: " . ($participant['rol'] ?? 'NULL'));
        error_log("Add Media - Participant permissions: " . ($participant['yetkiler'] ?? 'NULL'));
    }

    // ✅ Participant kontrolü - moderator/admin/event creator ise atla
    if (!$participant && !$bypass_participant_check) {
        error_log("Add Media - User is not a participant and not moderator/admin");
        http_response_code(403);
        header('Content-Type: application/json');
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
        $participant_role = 'moderator'; // Etkinlik oluşturucusu için yeterli
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
    
    error_log("Add Media - Event date: $event_date_str");
    error_log("Add Media - Today: " . $today->format('Y-m-d'));
    error_log("Add Media - Access end date: " . $access_end_date->format('Y-m-d'));
    error_log("Add Media - Is before event: " . ($is_before_event ? 'true' : 'false'));
    error_log("Add Media - Is after access: " . ($is_after_access ? 'true' : 'false'));
    
    // ✅ Rol kontrolü (case-insensitive)
    // NOT: Sadece O EVENT içindeki role bakıyoruz, global role bakmıyoruz!
    // Aksi halde başka bir event'te moderator olan kullanıcı, tüm eventlerde sınırsız yetki sahibi olur.
    $user_role_raw = $participant_role;
    $user_role = strtolower(trim($user_role_raw)); // ✅ Case-insensitive karşılaştırma için
    $is_moderator = ($user_role === 'moderator') || $is_event_creator;
    $is_admin = ($user_role === 'admin');
    $is_authorized = ($user_role === 'yetkili_kullanici');
    
    error_log("Add Media - User role (raw): $user_role_raw");
    error_log("Add Media - User role (normalized): $user_role");
    error_log("Add Media - Is moderator: " . ($is_moderator ? 'true' : 'false'));
    error_log("Add Media - Is admin: " . ($is_admin ? 'true' : 'false'));
    error_log("Add Media - Is authorized: " . ($is_authorized ? 'true' : 'false'));
    
    // ✅ Yetki kontrolü
    if ($is_moderator || $is_admin) {
        // Moderator/Admin: Her zaman medya paylaşabilir
        $can_share_media = true;
        error_log("Add Media - Moderator/Admin: can_share_media = true");
    } elseif ($is_authorized) {
        // Yetkili kullanıcı: JSON yetkiler + tarih kontrolü
        $permissions_raw = $participant_permissions ? json_decode($participant_permissions, true) : [];
        error_log("Add Media - Parsed permissions: " . print_r($permissions_raw, true));
        
        // ✅ Yetkiler array olarak geliyorsa (["medya_paylasabilir", ...]) in_array kullan
        // ✅ Ya da object olarak geliyorsa ({"medya_paylasabilir": true, ...}) key kontrolü yap
        $has_permission = false;
        
        if (is_array($permissions_raw)) {
            if (isset($permissions_raw[0])) {
                // Array formatı: ["medya_paylasabilir", "hikaye_paylasabilir"]
                // ✅ Güvenli kontrol: array_values ile numeric index'leri düzelt, array_map ile trim yap
                $permissions_clean = array_map('trim', array_values($permissions_raw));
                $has_permission = in_array('medya_paylasabilir', $permissions_clean, true); // strict comparison
                error_log("Add Media - Permissions cleaned: " . print_r($permissions_clean, true));
                error_log("Add Media - in_array('medya_paylasabilir', cleaned): " . ($has_permission ? 'true' : 'false'));
            } else {
                // Object formatı: {"medya_paylasabilir": true}
                $has_permission = (bool)($permissions_raw['medya_paylasabilir'] ?? false);
            }
        }
        
        error_log("Add Media - Has medya_paylasabilir: " . ($has_permission ? 'true' : 'false'));
        $can_share_media = $has_permission && !$is_before_event && !$is_after_access;
        error_log("Add Media - Yetkili user: can_share_media = " . ($can_share_media ? 'true' : 'false'));
    } else {
        // Normal kullanıcı: Sadece aktif dönemde
        $can_share_media = !$is_before_event && !$is_after_access;
        error_log("Add Media - Normal user: can_share_media = " . ($can_share_media ? 'true' : 'false'));
    }
    
    if (!$can_share_media) {
        $error_message = 'Bu etkinlikte medya paylaşma yetkiniz bulunmamaktadır.';
        if ($is_before_event) {
            $error_message = 'Etkinlik henüz başlamadı. Etkinlik tarihinden önce medya paylaşamazsınız.';
        } elseif ($is_after_access) {
            $error_message = "Ücretsiz erişim süresi doldu. Etkinlik tarihinden itibaren {$free_access_days} gün sonra medya paylaşamazsınız.";
        }
        
        error_log("Add Media - Access denied: $error_message");
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error_message]);
        exit;
    }
    
    error_log("Add Media - Access granted, proceeding with upload");
    
    // ✅ Medya limiti kontrolü (hikayeler hariç)
    // Not: Paketler tablosu yoksa veya media_limit kolonu yoksa 0 olarak kabul et
    $media_limit = 0;
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
                SELECT COALESCE(p.medya_limiti, 0) as media_limit
                FROM dugunler d
                LEFT JOIN paketler p ON d.paket_id = p.id
                WHERE d.id = ?
            ");
            $stmt_package->execute([$event_id]);
            $package_info = $stmt_package->fetch(PDO::FETCH_ASSOC);
            $media_limit = (int)($package_info['media_limit'] ?? 0);
        } else {
            // Kolon yoksa limit yok (zaten 0)
            error_log("Add Media - Paketler tablosu veya medya_limiti kolonu yok, limit kontrolü atlanıyor");
        }
    } catch (PDOException $e) {
        // Eğer paketler tablosu veya medya_limiti kolonu yoksa, limit yok kabul et
        error_log("Add Media - Package limit query error (ignoring): " . $e->getMessage());
        $media_limit = 0;
    }
    
    // ✅ Mevcut medya sayısını al (hikayeler HARİÇ)
    $stmt_count = $pdo->prepare("
        SELECT COUNT(*) as media_count
        FROM medyalar
        WHERE dugun_id = ? AND tur != 'hikaye'
    ");
    $stmt_count->execute([$event_id]);
    $current_media = $stmt_count->fetch(PDO::FETCH_ASSOC);
    $current_media_count = (int)($current_media['media_count'] ?? 0);
    
    error_log("Add Media - Medya limiti: $media_limit, Mevcut medya: $current_media_count");
    
    // ✅ Moderator/Admin değilse limit kontrolü yap
    if (!$is_moderator && !$is_admin && $media_limit > 0 && $current_media_count >= $media_limit) {
        error_log("Add Media - Medya limiti doldu!");
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Etkinlik medya alanı doldu. Bu etkinliğe daha fazla medya eklenemiyor.',
            'media_limit' => $media_limit,
            'current_count' => $current_media_count
        ]);
        exit;
    }
    
    // ✅ IIS/Plesk seviyesinde 403 kontrolü için
    // ✅ Request'in başından itibaren log ekle
    error_log("Add Media - REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? 'UNKNOWN'));
    error_log("Add Media - HTTP_HOST: " . ($_SERVER['HTTP_HOST'] ?? 'UNKNOWN'));
    error_log("Add Media - SERVER_SOFTWARE: " . ($_SERVER['SERVER_SOFTWARE'] ?? 'UNKNOWN'));
    error_log("Add Media - CONTENT_LENGTH: " . ($_SERVER['CONTENT_LENGTH'] ?? 'UNKNOWN'));
    error_log("Add Media - REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));

    // Check if file was uploaded
    error_log("Add Media - Checking uploaded file");
    error_log("Add Media - FILES array exists: " . (isset($_FILES) ? 'YES' : 'NO'));
    if (isset($_FILES)) {
        error_log("Add Media - FILES array keys: " . implode(', ', array_keys($_FILES)));
        error_log("Add Media - FILES array: " . print_r($_FILES, true));
    }
    error_log("Add Media - POST array keys: " . implode(', ', array_keys($_POST)));
    
    if (!isset($_FILES['media_file'])) {
        error_log("Add Media - ERROR: No media_file in FILES array");
        error_log("Add Media - Available FILES keys: " . (isset($_FILES) ? implode(', ', array_keys($_FILES)) : 'FILES not set'));
        error_log("Add Media - Raw input available: " . (file_get_contents('php://input') ? 'YES' : 'NO'));
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Dosya yüklenmedi. IIS/Plesk seviyesinde engellenmiş olabilir.']);
        exit;
    }
    
    if ($_FILES['media_file']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = $_FILES['media_file']['error'];
        $error_messages = [
            UPLOAD_ERR_INI_SIZE => 'Dosya boyutu PHP limitini aşıyor (upload_max_filesize).',
            UPLOAD_ERR_FORM_SIZE => 'Dosya boyutu form limitini aşıyor (MAX_FILE_SIZE).',
            UPLOAD_ERR_PARTIAL => 'Dosya kısmen yüklendi.',
            UPLOAD_ERR_NO_FILE => 'Dosya seçilmedi.',
            UPLOAD_ERR_NO_TMP_DIR => 'Geçici klasör bulunamadı.',
            UPLOAD_ERR_CANT_WRITE => 'Dosya diske yazılamadı.',
            UPLOAD_ERR_EXTENSION => 'Bir PHP extension dosya yüklemeyi durdurdu.',
        ];
        
        $error_message = $error_messages[$upload_error] ?? "Bilinmeyen upload hatası: $upload_error";
        error_log("Add Media - Upload error: $upload_error - $error_message");
        
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $error_message]);
        exit;
    }
    
    error_log("Add Media - File uploaded successfully");

    $file = $_FILES['media_file'];

    // Debug: Log file info
    error_log("Media Upload - File Type: " . $file['type']);
    error_log("Media Upload - File Name: " . $file['name']);
    error_log("Media Upload - File Size: " . $file['size']);

    // Validate file type (more flexible)
    $allowed_types = [
        'image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/avif',
        'video/mp4', 'video/quicktime', 'video/avi', 'video/mov', 'video/webm', 'video/x-matroska'
    ];

    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'mp4', 'mov', 'avi', 'mkv', 'webm'];

    if (!in_array($file['type'], $allowed_types) && !in_array($file_extension, $allowed_extensions)) {
        error_log("Media Upload - Invalid file type: " . $file['type'] . " or extension: " . $file_extension);
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Only images and videos are allowed.']);
        exit;
    }

    // Validate file size (max 50MB)
    if ($file['size'] > 50 * 1024 * 1024) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 50MB.']);
        exit;
    }

    $upload_dir = '../uploads/medyalar/';
    error_log("Add Media - Upload directory: $upload_dir");
    
    // ✅ Upload dizini kontrolü ve oluşturma
    if (!is_dir($upload_dir)) {
        error_log("Add Media - Creating upload directory: $upload_dir");
        if (!mkdir($upload_dir, 0777, true)) {
            error_log("Add Media - Failed to create upload directory");
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Upload dizini oluşturulamadı.']);
            exit;
        }
        error_log("Add Media - Upload directory created");
    }
    
    // ✅ Write permission kontrolü
    if (!is_writable($upload_dir)) {
        error_log("Add Media - Upload directory is not writable: $upload_dir");
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Upload dizinine yazma izni yok.']);
        exit;
    }
    
    error_log("Add Media - Upload directory is writable");

    $file_name = 'media_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
    $target_file = $upload_dir . $file_name;

    if (move_uploaded_file($file['tmp_name'], $target_file)) {
        $file_path_db = 'uploads/medyalar/' . $file_name;
        // Determine media type based on file extension
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $media_type = 'foto'; // Default to foto
        if (in_array($file_extension, ['mp4', 'mov', 'avi', 'mkv', 'webm'])) {
            $media_type = 'video';
        } elseif (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $media_type = 'foto';
        }

        // ✅ Thumbnail ve preview oluştur
        $thumbnail_path = null;
        $base_filename = pathinfo($file_name, PATHINFO_FILENAME);
        
        try {
            if ($media_type == 'foto') {
                // Fotoğraflar için thumbnail ve preview oluştur
                error_log("Media Upload - Processing image for thumbnail: $target_file");
                $image_results = ImageUtils::processImage($target_file, $base_filename, $upload_dir, $file_extension, 'media');
                
                if (isset($image_results['error'])) {
                    error_log("Media Upload - Image processing error: " . $image_results['error']);
                } else {
                    if ($image_results['thumbnail']) {
                        $thumbnail_path = 'uploads/medyalar/' . basename($image_results['thumbnail']);
                        error_log("Media Upload - Thumbnail created: $thumbnail_path");
                    }
                    // İşlenmiş orijinal dosya adı döndüyse onu kullan
                    if (!empty($image_results['original'])) {
                        $processed_original = basename($image_results['original']);
                        $file_name = $processed_original;
                        $file_path_db = 'uploads/medyalar/' . $processed_original;
                        $base_filename = pathinfo($file_name, PATHINFO_FILENAME);
                    }
                    if ($image_results['preview']) {
                        error_log("Media Upload - Preview created: " . basename($image_results['preview']));
                    }
                }
            } elseif ($media_type == 'video') {
                // Videolar için thumbnail oluştur (FFmpeg ile)
                try {
                    $thumbnail_filename = $base_filename . '_thumb.jpg';
                    $thumbnail_path_full = $upload_dir . $thumbnail_filename;
                    error_log("Media Upload - Attempting video thumbnail: $thumbnail_path_full");
                    
                    // Set max execution time for thumbnail generation
                    set_time_limit(30);
                    
                    if (ImageUtils::generateVideoThumbnail($target_file, $thumbnail_path_full)) {
                        $thumbnail_path = 'uploads/medyalar/' . $thumbnail_filename;
                        error_log("Media Upload - Video thumbnail SUCCESS: $thumbnail_path");
                    } else {
                        error_log("Media Upload - Video thumbnail FAILED: FFmpeg not available or error occurred");
                        // Thumbnail yok ama devam et
                        $thumbnail_path = null;
                    }
                } catch (Exception $video_thumb_error) {
                    error_log("Media Upload - Video thumbnail EXCEPTION: " . $video_thumb_error->getMessage());
                    $thumbnail_path = null;
                } catch (Error $video_thumb_fatal) {
                    error_log("Media Upload - Video thumbnail FATAL ERROR: " . $video_thumb_fatal->getMessage());
                    $thumbnail_path = null;
                }
                
                // Reset time limit
                set_time_limit(300);
            }
        } catch (Exception $thumb_error) {
            // Thumbnail oluşturulamazsa bile medya yüklemesi devam etsin
            error_log("Media Upload - Thumbnail Exception (non-fatal): " . $thumb_error->getMessage());
            $thumbnail_path = null;
        } catch (Error $thumb_fatal) {
            error_log("Media Upload - Thumbnail Fatal Error (non-fatal): " . $thumb_fatal->getMessage());
            $thumbnail_path = null;
        }
        
        error_log("Media Upload - Thumbnail processing complete. Thumbnail path: " . ($thumbnail_path ?? 'NULL'));

        // ✅ tur kolonu için değeri kontrol et (max 10 karakter olmalı - 'foto', 'video', 'hikaye')
        $tur_value = $media_type; // 'foto' veya 'video'
        if (strlen($tur_value) > 10) {
            error_log("Media Upload - Warning: tur value too long, truncating: $tur_value");
            $tur_value = substr($tur_value, 0, 10);
        }
        
        error_log("Media Upload - Preparing database INSERT");
        error_log("Media Upload - Values: event_id=$event_id, user_id=$user_id, file_path=$file_path_db, type=$media_type, tur=$tur_value, thumbnail=" . ($thumbnail_path ?? 'NULL'));
        error_log("Media Upload - Description to insert: '$description' (length: " . strlen($description) . ")");
        error_log("Media Upload - Description type before cast: " . gettype($description));
        error_log("Media Upload - Description empty check: " . (empty($description) ? 'YES' : 'NO'));
        
        // ✅ CRITICAL FIX: Description'ı her zaman string olarak kaydet (NULL değil, boş string bile olsa)
        // ✅ PDO boş string'i NULL'a çevirebilir, bu yüzden açıkça kontrol et
        $description_db = '';
        if (!empty($description) && trim($description) !== '') {
            $description_db = trim($description);
        }
        // ✅ Boş string olarak kaydet (NULL değil) - veritabanı NULL kabul ediyorsa bile boş string kullan
        $description_db = (string)$description_db; // ✅ Her zaman string olarak kaydet
        
        error_log("Media Upload - Description DB value: '$description_db' (length: " . strlen($description_db) . ", type: " . gettype($description_db) . ")");
        
        $stmt = $pdo->prepare("
            INSERT INTO medyalar (dugun_id, kullanici_id, dosya_yolu, dosya_tipi, tur, aciklama, kucuk_resim_yolu, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        try {
            $thumbnail_db = $thumbnail_path ?? '';
            // ✅ CRITICAL: Description'ı bind et - PDO'nun NULL'a çevirmesini önle
            $stmt->bindValue(1, $event_id, PDO::PARAM_INT);
            $stmt->bindValue(2, $user_id, PDO::PARAM_INT);
            $stmt->bindValue(3, $file_path_db, PDO::PARAM_STR);
            $stmt->bindValue(4, $media_type, PDO::PARAM_STR);
            $stmt->bindValue(5, $tur_value, PDO::PARAM_STR);
            $stmt->bindValue(6, $description_db, PDO::PARAM_STR); // ✅ Açıkça STRING olarak bind et
            $stmt->bindValue(7, $thumbnail_db, PDO::PARAM_STR);
            
            $result = $stmt->execute();
            error_log("Media Upload - Database INSERT result: " . ($result ? 'SUCCESS' : 'FAILED'));
            if ($result) {
                // ✅ CRITICAL: lastInsertId()'yi HEMEN INSERT'ten sonra al ve kaydet
                $media_id = $pdo->lastInsertId();
                error_log("Media Upload - Inserted media ID: $media_id");
                error_log("Media Upload - Description was inserted: '$description_db' (length: " . strlen($description_db) . ")");
                
                // ✅ INSERT sonrası doğrulama sorgusu - kaydedilen media_id ile kontrol et
                if ($media_id > 0) {
                    $verify_stmt = $pdo->prepare("SELECT aciklama FROM medyalar WHERE id = ?");
                    $verify_stmt->execute([$media_id]);
                    $verify_result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
                    error_log("Media Upload - Verified description in DB for ID $media_id: '" . ($verify_result['aciklama'] ?? 'NULL') . "' (type: " . gettype($verify_result['aciklama'] ?? null) . ")");
                    
                    // ✅ Eğer açıklama NULL ise, UPDATE ile tekrar kaydet
                    if (empty($verify_result['aciklama']) && !empty($description_db)) {
                        error_log("Media Upload - WARNING: Description is NULL in DB, attempting UPDATE...");
                        $update_stmt = $pdo->prepare("UPDATE medyalar SET aciklama = ? WHERE id = ?");
                        $update_result = $update_stmt->execute([$description_db, $media_id]);
                        error_log("Media Upload - UPDATE result: " . ($update_result ? 'SUCCESS' : 'FAILED'));
                        
                        // ✅ UPDATE sonrası tekrar kontrol et
                        $verify_stmt2 = $pdo->prepare("SELECT aciklama FROM medyalar WHERE id = ?");
                        $verify_stmt2->execute([$media_id]);
                        $verify_result2 = $verify_stmt2->fetch(PDO::FETCH_ASSOC);
                        error_log("Media Upload - Verified description AFTER UPDATE: '" . ($verify_result2['aciklama'] ?? 'NULL') . "'");
                    }
                } else {
                    error_log("Media Upload - ERROR: lastInsertId() returned 0, INSERT may have failed!");
                }
            } else {
                error_log("Media Upload - ERROR: INSERT execute() returned false!");
                throw new PDOException("INSERT failed");
            }
        } catch (PDOException $db_error) {
            error_log("Media Upload - Database INSERT error: " . $db_error->getMessage());
            error_log("Media Upload - SQL State: " . $db_error->getCode());
            error_log("Media Upload - tur value used: $tur_value (length: " . strlen($tur_value) . ")");
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $db_error->getMessage()]);
            exit;
        }
        
        // ✅ Media ID'yi zaten yukarıda aldık, tekrar almayalım
        if (!isset($media_id) || $media_id <= 0) {
            error_log("Media Upload - FATAL ERROR: No valid media ID obtained!");
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Failed to get media ID after insert.']);
            exit;
        }
        
        // ✅ TÜM cache'leri TEMİZLE (yeni medya eklendi - kesinlikle cache'i kır)
        // Önce spesifik cache'leri temizle
        clear_media_cache($event_id, $user_id); // Event bazlı media cache temizle
        clear_events_cache($user_id); // Events sayfasında medya sayısı değişti
        clear_profile_cache($user_id); // Profile sayfasında medya sayısı değişti
        
        // ✅ SONRA TÜM cache'i temizle (güvenli tarafta olmak için - kesin çözüm)
        // MD5 hash ile cache key'leri oluşturulduğu için pattern matching yapamıyoruz
        // Bu yüzden tüm cache'leri temizliyoruz (medya upload çok sık olmaz, sorun değil)
        QueryCache::clear();
        error_log("Media Upload - ALL CACHE CLEARED for event_id: $event_id, user_id: $user_id");

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Media uploaded successfully.', 
            'media_id' => $media_id, 
            'file_path' => $file_path_db,
            'description' => $description_db, // ✅ Açıklamayı response'a ekle
            'aciklama' => $description_db // ✅ Türkçe alan adı için de ekle
        ]);
    } else {
        error_log("Media Upload - Failed to move uploaded file: " . $file['tmp_name'] . " to " . $target_file);
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Failed to upload media.']);
    }

} catch (PDOException $e) {
    error_log("Database error adding media: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Error adding media: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Server error: ' . $e->getMessage()]);
}
?>
