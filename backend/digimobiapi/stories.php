<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/image_utils.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'kullanici';

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get stories for specific event
    $event_id = $_GET['event_id'] ?? '';
    $specific_user_id = $_GET['user_id'] ?? null; // Optional: get stories for specific user
    
    if (empty($event_id)) {
        http_response_code(400);
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
        
        if ($specific_user_id) {
            // Get specific user's stories from medyalar table (tur = 'hikaye')
            $stmt = $pdo->prepare("
                SELECT 
                    m.id,
                    m.kullanici_id,
                    m.dosya_yolu,
                    m.kucuk_resim_yolu,
                    m.aciklama,
                    m.created_at as olusturma_tarihi,
                    m.tur as medya_tipi,
                    CONCAT(k.ad, ' ', k.soyad) as user_name,
                    k.profil_fotografi as user_profile
                FROM medyalar m
                JOIN kullanicilar k ON m.kullanici_id = k.id
                WHERE m.dugun_id = ? AND m.kullanici_id = ? AND m.tur = 'hikaye'
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$event_id, $specific_user_id]);
            $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format individual stories
            $formatted_stories = [];
            foreach ($stories as $story) {
                // Generate optimized URLs
                $thumbnail_url = null;
                $preview_url = null;
                
                if ($story['dosya_yolu']) {
                    $path_info = pathinfo($story['dosya_yolu']);
                    $extension = strtolower($path_info['extension']);
                    $filename_without_ext = $path_info['filename'];
                    
                    // For videos, use thumbnail as preview
                    if (in_array($extension, ['mp4', 'mov', 'avi', 'mkv', 'webm'])) {
                        // Video: Use kucuk_resim_yolu if available
                        $thumbnail_url = !empty($story['kucuk_resim_yolu']) ? 'https://dijitalsalon.cagapps.app/' . $story['kucuk_resim_yolu'] : null;
                        $preview_url = $thumbnail_url;
                    } else {
                        // Image: Use kucuk_resim_yolu if available, otherwise generate
                        if (!empty($story['kucuk_resim_yolu'])) {
                            $thumbnail_url = 'https://dijitalsalon.cagapps.app/' . $story['kucuk_resim_yolu'];
                            $preview_url = $thumbnail_url;
                        } else {
                            // Generate optimized URLs
                            $thumbnail_url = 'https://dijitalsalon.cagapps.app/' . $path_info['dirname'] . '/' . $filename_without_ext . '_thumb.' . $extension;
                            $preview_url = 'https://dijitalsalon.cagapps.app/' . $path_info['dirname'] . '/' . $filename_without_ext . '_preview.' . $extension;
                        }
                    }
                }
                
                $formatted_stories[] = [
                    'id' => (int)$story['id'],
                    'user_id' => (int)$story['kullanici_id'],
                    'user_name' => $story['user_name'],
                    'user_avatar' => $story['user_profile'] ? 'https://dijitalsalon.cagapps.app/' . $story['user_profile'] : null,
                    'url' => $story['dosya_yolu'] ? 'https://dijitalsalon.cagapps.app/' . $story['dosya_yolu'] : null,
                    'media_url' => $story['dosya_yolu'] ? 'https://dijitalsalon.cagapps.app/' . $story['dosya_yolu'] : null,
                    'thumbnail_url' => $thumbnail_url,
                    'preview_url' => $preview_url,
                    'description' => $story['aciklama'] ?? '',
                    'aciklama' => $story['aciklama'] ?? '',
                    'created_at' => $story['olusturma_tarihi'],
                    'type' => $story['medya_tipi'],
                    'media_type' => $story['medya_tipi'],
                    'tur' => $story['medya_tipi'],
                ];
            }
        } else {
            // Get stories for this event (grouped by user) from medyalar table (tur = 'hikaye')
            $stmt = $pdo->prepare("
                SELECT 
                    m.kullanici_id,
                    CONCAT(k.ad, ' ', k.soyad) as user_name,
                    k.profil_fotografi as user_profile,
                    COUNT(*) as story_count,
                    MAX(m.created_at) as latest_story_time,
                    m.dosya_yolu as latest_story_url,
                    m.tur as latest_story_type
                FROM medyalar m
                JOIN kullanicilar k ON m.kullanici_id = k.id
                WHERE m.dugun_id = ? AND m.tur = 'hikaye'
                GROUP BY m.kullanici_id
                ORDER BY latest_story_time DESC
            ");
            $stmt->execute([$event_id]);
            $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format stories for mobile app
            $formatted_stories = [];
            foreach ($stories as $story) {
                $formatted_stories[] = [
                    'user_id' => (int)$story['kullanici_id'],
                    'user_name' => $story['user_name'],
                    'user_avatar' => $story['user_profile'] ? 'https://dijitalsalon.cagapps.app/' . $story['user_profile'] : null,
                    'story_count' => (int)$story['story_count'],
                    'latest_story_time' => $story['latest_story_time'],
                    'url' => $story['latest_story_url'] ? 'https://dijitalsalon.cagapps.app/' . $story['latest_story_url'] : null,
                    'media_url' => $story['latest_story_url'] ? 'https://dijitalsalon.cagapps.app/' . $story['latest_story_url'] : null,
                    'type' => $story['latest_story_type'],
                    'media_type' => $story['latest_story_type'],
                    'tur' => $story['latest_story_type'],
                    'is_viewed' => false, // TODO: Implement story viewing tracking
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'stories' => $formatted_stories
        ]);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload new story
    $event_id = $_POST['event_id'] ?? '';
    $media_type = $_POST['media_type'] ?? 'image'; // 'image' or 'video'
    
    if (empty($event_id)) {
        http_response_code(400);
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
        $upload_dir = '../uploads/hikayeler/';
        
        // Create directory if not exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $base_filename = 'story_' . $user_id . '_' . time() . '_' . uniqid();
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
            $image_results = ImageUtils::processImage($file_path, $base_filename, $upload_dir, $file_extension, 'story');
            
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
        
        // Save to database (using medyalar table with tur = 'hikaye')
        $stmt = $pdo->prepare("
            INSERT INTO medyalar (
                dugun_id, 
                kullanici_id, 
                medya_tipi, 
                dosya_yolu, 
                kucuk_resim_yolu, 
                tur,
                created_at
            ) VALUES (?, ?, ?, ?, ?, 'hikaye', NOW())
        ");
        $stmt->execute([
            $event_id,
            $user_id,
            $media_type,
            'uploads/hikayeler/' . $filename,
            $thumbnail_path ? 'uploads/hikayeler/' . $thumbnail_path : null
        ]);
        
        $story_id = $pdo->lastInsertId();
        
        // Log activity to user_logs
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
        $device_info = $_POST['device_info'] ?? '';
        $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $log_details = json_encode([
            'story_id' => $story_id,
            'media_type' => $media_type,
            'event_id' => $event_id
        ]);
        
        $stmt = $pdo->prepare("
            INSERT INTO user_logs (
                user_id, action, details, ip_address, device_info, user_agent, created_at
            ) VALUES (?, 'story_upload', ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$user_id, $log_details, $ip_address, $device_info, $user_agent]);
        
        // Get optimized URLs
        $image_urls = ImageUtils::getImageUrls('uploads/hikayeler/' . $filename, $base_filename, $file_extension);
        
        echo json_encode([
            'success' => true,
            'message' => 'Story uploaded successfully.',
            'story_id' => (int)$story_id,
            'file_url' => $image_urls['original'],
            'thumbnail_url' => $image_urls['thumbnail'],
            'preview_url' => $image_urls['preview'],
            'file_size' => ImageUtils::formatFileSize(filesize($file_path))
        ]);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    // Mark story as viewed
    $story_id = $_POST['story_id'] ?? '';
    
    if (empty($story_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Story ID is required.']);
        exit;
    }
    
    try {
        // Check if story exists and user has access (medyalar tablosunda tur='hikaye')
        $stmt = $pdo->prepare("
            SELECT m.id, m.dugun_id
            FROM medyalar m
            JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id
            WHERE m.id = ? AND dk.kullanici_id = ? AND m.tur = 'hikaye'
        ");
        $stmt->execute([$story_id, $user_id]);
        $story = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$story) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Story not found or access denied.']);
            exit;
        }
        
        // Hikaye izlenme sistemi için basit bir log tablosu kullan
        try {
            $stmt = $pdo->prepare("
                CREATE TABLE IF NOT EXISTS story_views (
                    id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                    medya_id bigint(20) unsigned NOT NULL,
                    kullanici_id bigint(20) unsigned NOT NULL,
                    viewed_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
                    PRIMARY KEY (id),
                    UNIQUE KEY unique_view (medya_id, kullanici_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            $stmt->execute();
        } catch (Exception $e) {
            // Table already exists
        }
        
        // Check if already viewed
        $stmt = $pdo->prepare("SELECT id FROM story_views WHERE medya_id = ? AND kullanici_id = ?");
        $stmt->execute([$story_id, $user_id]);
        $existing_view = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_view) {
            echo json_encode(['success' => true, 'message' => 'Already viewed.']);
            exit;
        }
        
        // Mark as viewed
        $stmt = $pdo->prepare("
            INSERT INTO story_views (medya_id, kullanici_id, viewed_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$story_id, $user_id]);
        
        echo json_encode(['success' => true, 'message' => 'Story marked as viewed.']);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}
?>
