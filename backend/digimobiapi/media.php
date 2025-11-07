<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/image_utils.php';
require_once __DIR__ . '/cache_helper.php';

// Get user_id from session
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

// Debug log
error_log("Media API called - Event ID: " . ($_GET['event_id'] ?? 'none') . ", User ID: $user_id");

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get media for specific event with pagination
    $event_id = $_GET['event_id'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 10);
    $offset = ($page - 1) * $limit;
    
    if (empty($event_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Event ID is required.']);
        exit;
    }
    
    try {
        // ✅ Cache'den kontrol et (event_id + user_id + page bazlı - daha spesifik)
        $cache_key_query = "SELECT * FROM medyalar WHERE dugun_id = ? AND kullanici_id = ? LIMIT ? OFFSET ?";
        $cached_media = QueryCache::get($cache_key_query, [$event_id, $user_id, $limit, $offset]);
        
        if ($cached_media !== null) {
            // Cache'den döndür (<1ms!)
            http_response_code(200);
            echo json_encode(['success' => true, 'media' => $cached_media, 'cached' => true], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }
        
        // ✅ OPTIMIZED: Nested SELECT'leri LEFT JOIN'e çevir (çok daha hızlı)
        // ✅ Index kullanımı: idx_medyalar_dugun_tur, idx_begeniler_medya, idx_yorumlar_medya
        $stmt = $pdo->prepare("
            SELECT 
                m.id,
                m.dugun_id,
                m.kullanici_id as user_id,
                CASE 
                    WHEN m.dosya_yolu IS NOT NULL AND m.dosya_yolu != '' 
                    THEN CONCAT('https://dijitalsalon.cagapps.app/', m.dosya_yolu)
                    ELSE NULL 
                END as url,
                CASE 
                    WHEN m.dosya_yolu IS NOT NULL AND m.dosya_yolu != '' 
                    THEN CONCAT('https://dijitalsalon.cagapps.app/', m.dosya_yolu)
                    ELSE NULL 
                END as media_url,
                m.tur as type,
                m.tur as media_type,
                m.tur as tur,
                m.aciklama as description,
                m.created_at,
                m.kucuk_resim_yolu,
                CONCAT(k.ad, ' ', k.soyad) as user_name,
                k.profil_fotografi as user_avatar,
                COALESCE(COUNT(DISTINCT mb.id), 0) as likes_count,
                COALESCE(COUNT(DISTINCT my.id), 0) as comments_count,
                COUNT(DISTINCT CASE WHEN mb2.kullanici_id = ? THEN mb2.id END) > 0 as is_liked
            FROM medyalar m
            INNER JOIN kullanicilar k ON m.kullanici_id = k.id
            LEFT JOIN begeniler mb ON mb.medya_id = m.id
            LEFT JOIN yorumlar my ON my.medya_id = m.id
            LEFT JOIN begeniler mb2 ON mb2.medya_id = m.id AND mb2.kullanici_id = ?
            WHERE m.dugun_id = ? AND (m.tur IS NULL OR m.tur = '' OR m.tur != 'hikaye')
            GROUP BY m.id, m.dugun_id, m.kullanici_id, m.dosya_yolu, m.tur, m.aciklama, m.created_at, m.kucuk_resim_yolu, k.ad, k.soyad, k.profil_fotografi
            ORDER BY m.created_at DESC
            LIMIT $offset, $limit
        ");
        $stmt->execute([$user_id, $user_id, $event_id]);
        $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // ✅ Debug: SQL sorgusundan gelen ham veriyi logla
        error_log("Media API - Raw SQL results count: " . count($media));
        if (!empty($media)) {
            $first_item = $media[0];
            error_log("Media API - First item keys: " . implode(', ', array_keys($first_item)));
            error_log("Media API - First item aciklama (raw): " . var_export($first_item['aciklama'] ?? 'NOT SET', true));
            error_log("Media API - First item description (raw): " . var_export($first_item['description'] ?? 'NOT SET', true));
        }
        
        // Format media for mobile app
        $formatted_media = [];
        foreach ($media as $item) {
            // Debug log for comments count
            error_log("Media ID: {$item['id']}, Comments Count: {$item['comments_count']}");
            
            // Generate optimized URLs
            $thumbnail_url = null;
            $preview_url = null;
            
            if ($item['url']) {
                // ✅ URL zaten tam URL olarak geliyor, pathinfo ile parse et
                $url_path = parse_url($item['url'], PHP_URL_PATH);
                $path_info = pathinfo($url_path);
                $extension = strtolower($path_info['extension'] ?? '');
                $filename_without_ext = $path_info['filename'] ?? '';
                
                // For videos, use thumbnail as preview
                if (in_array($extension, ['mp4', 'mov', 'avi'])) {
                    $thumbnail_url = (!empty($item['kucuk_resim_yolu'])) ? 'https://dijitalsalon.cagapps.app/' . $item['kucuk_resim_yolu'] : null;
                    $preview_url = $thumbnail_url;
                } else {
                    // ✅ For images, use optimized URLs - dirname zaten path içeriyor
                    $base_path = $path_info['dirname'] ?? '';
                    $thumbnail_url = 'https://dijitalsalon.cagapps.app' . $base_path . '/' . $filename_without_ext . '_thumb.' . $extension;
                    $preview_url = 'https://dijitalsalon.cagapps.app' . $base_path . '/' . $filename_without_ext . '_preview.' . $extension;
                }
            }
            
            // ✅ CRITICAL FIX: SQL sorgusunda 'm.aciklama as description' kullanıldığı için
            // ✅ $item['description'] kullanmalıyız, $item['aciklama'] değil!
            $description_value = $item['description'] ?? '';
            $description_str = (string)$description_value; // Her zaman string olarak döndür (NULL değil)
            
            $formatted_media[] = [
                'id' => (int)$item['id'],
                'event_id' => (int)$item['dugun_id'],
                'type' => $item['tur'], // 'foto' or 'video'
                'url' => $item['url'], // SQL'den gelen tam URL
                'thumbnail' => $thumbnail_url, // Optimized thumbnail URL
                'preview' => $preview_url, // Optimized preview URL
                'user_id' => (int)$item['user_id'],
                'user_name' => $item['user_name'],
                'user_avatar' => $item['user_avatar'] ? 'https://dijitalsalon.cagapps.app/' . $item['user_avatar'] : null,
                'likes' => (int)$item['likes_count'],
                'comments' => (int)$item['comments_count'],
                'is_liked' => (bool)$item['is_liked'],
                'created_at' => $item['created_at'],
                'description' => $description_str, // ✅ SQL'den gelen 'description' anahtarını kullan
                'aciklama' => $description_str, // ✅ Türkçe alan adı için de ekle (aynı değer)
            ];
            
            // ✅ Debug: Açıklama kontrolü - hem description hem aciklama anahtarlarını kontrol et
            error_log("Media API - Media ID: {$item['id']}, Description from SQL (description key): '" . ($item['description'] ?? 'NOT SET') . "' (length: " . strlen($item['description'] ?? '') . "), Description from SQL (aciklama key): '" . ($item['aciklama'] ?? 'NOT SET') . "', Formatted: '" . $description_str . "'");
        }
        
        // ✅ Cache'e kaydet (30 saniye TTL - medya çok sık değişir)
        // ✅ Page bazlı cache key kullan (daha spesifik)
        QueryCache::set(
            $cache_key_query, 
            [$event_id, $user_id, $limit, $offset], 
            $formatted_media, 
            30 // 30 saniye (daha agresif cache)
        );
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'media' => $formatted_media,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'has_more' => count($formatted_media) === $limit
            ],
            'cached' => false
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
        
    } catch (Exception $e) {
        error_log("Media API GET Error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Server error',
            'detail' => $e->getMessage()
        ]);
        exit;
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload new media
    $event_id = $_POST['event_id'] ?? '';
    $description = $_POST['description'] ?? '';
    $media_type = $_POST['media_type'] ?? 'image'; // 'image' or 'video'
    
    if (empty($event_id)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Event ID is required.']);
        exit;
    }
    
    try {
        // Check if user is participant of this event
        $stmt = $pdo->prepare("
            SELECT dk.id 
            FROM dugun_katilimcilar dk 
            WHERE dk.dugun_id = ? AND dk.kullanici_id = ?
        ");
        $stmt->execute([$event_id, $user_id]);
        $participant = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$participant) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You are not a participant of this event.']);
            exit;
        }
        
        // Handle file upload
        if (!isset($_FILES['media']) || $_FILES['media']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No file uploaded or upload error.']);
            exit;
        }
        
        $file = $_FILES['media'];
        $upload_dir = '../uploads/medyalar/';
        
        // Create directory if not exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $base_filename = 'media_' . $user_id . '_' . time() . '_' . uniqid();
        $filename = $base_filename . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save file.']);
            exit;
        }
        
        $thumbnail_path = null;
        $preview_path = null;
        
        if ($media_type === 'image' || in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            // Process image: compress, resize, and create thumbnails
            $image_results = ImageUtils::processImage($file_path, $base_filename, $upload_dir, $file_extension, 'media');
            
            if ($image_results['error']) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => 'Image processing failed: ' . $image_results['error']]);
                exit;
            }
            
            $thumbnail_path = $image_results['thumbnail'] ? basename($image_results['thumbnail']) : null;
            $preview_path = $image_results['preview'] ? basename($image_results['preview']) : null;
            
            // Update file path to use processed version
            $filename = basename($image_results['original']);
            
        } elseif ($media_type === 'video' || in_array($file_extension, ['mp4', 'mov', 'avi'])) {
            // Generate video thumbnail
            $thumbnail_filename = $base_filename . '_thumb.jpg';
            $thumbnail_path_full = $upload_dir . $thumbnail_filename;
            
            if (ImageUtils::generateVideoThumbnail($file_path, $thumbnail_path_full)) {
                $thumbnail_path = $thumbnail_filename;
            }
        }
        
        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO medyalar (
                dugun_id, 
                kullanici_id, 
                medya_tipi, 
                dosya_yolu, 
                kucuk_resim_yolu, 
                aciklama, 
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $event_id,
            $user_id,
            $media_type,
            'uploads/medyalar/' . $filename,
            $thumbnail_path ? 'uploads/medyalar/' . $thumbnail_path : null,
            $description
        ]);
        
        $media_id = $pdo->lastInsertId();
        
        // Log activity to user_logs
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $device_info = $_POST['device_info'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $log_details = json_encode([
            'media_id' => $media_id,
            'media_type' => $media_type,
            'event_id' => $event_id
        ]);
        
        $stmt = $pdo->prepare("
            INSERT INTO user_logs (
                user_id, action, details, ip_address, device_info, user_agent, created_at
            ) VALUES (?, 'media_upload', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $log_details, $ip_address, $device_info, $user_agent]);
        
        // Get optimized URLs
        $image_urls = ImageUtils::getImageUrls('uploads/medyalar/' . $filename, $base_filename, $file_extension);
        
        echo json_encode([
            'success' => true,
            'message' => 'Media uploaded successfully.',
            'media_id' => (int)$media_id,
            'file_url' => $image_urls['original'],
            'thumbnail_url' => $image_urls['thumbnail'],
            'preview_url' => $image_urls['preview'],
            'file_size' => ImageUtils::formatFileSize(filesize($file_path))
        ]);
        exit;
        
    } catch (Exception $e) {
        error_log("Media API POST Error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
        http_response_code(500);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false, 
            'error' => 'Server error',
            'detail' => $e->getMessage()
        ]);
        exit;
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}
?>
