<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

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
        
        // Get media for this event (excluding stories)
        $stmt = $pdo->prepare("
            SELECT 
                m.*,
                m.kullanici_id as user_id,
                k.ad as user_name,
                k.soyad as user_surname,
                k.profil_fotografi as user_avatar,
                (SELECT COUNT(*) FROM begeniler mb WHERE mb.medya_id = m.id) as likes_count,
                (SELECT COUNT(*) FROM yorumlar my WHERE my.medya_id = m.id) as comments_count,
                (SELECT COUNT(*) FROM begeniler mb2 WHERE mb2.medya_id = m.id AND mb2.kullanici_id = ?) as is_liked
            FROM medyalar m
            JOIN kullanicilar k ON m.kullanici_id = k.id
            WHERE m.dugun_id = ? AND m.tur != 'hikaye'
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ");
        $stmt->execute([$user_id, $event_id, $limit, $offset]);
        $media = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format media for mobile app
        $formatted_media = [];
        foreach ($media as $item) {
            // Debug log for comments count
            error_log("Media ID: {$item['id']}, Comments Count: {$item['comments_count']}");
            
            // Generate preview URL (small version for grid)
            $preview_url = null;
            if ($item['dosya_yolu']) {
                $path_info = pathinfo($item['dosya_yolu']);
                $extension = strtolower($path_info['extension']);
                
                // For videos, use thumbnail as preview
                if (in_array($extension, ['mp4', 'mov', 'avi'])) {
                    $preview_url = $item['kucuk_resim_yolu'] ? 'http://192.168.1.137/dijitalsalon/' . $item['kucuk_resim_yolu'] : null;
                } else {
                    // For images, use actual preview file
                    $preview_filename = $path_info['filename'] . '_preview.' . $path_info['extension'];
                    $preview_path = $path_info['dirname'] . '/' . $preview_filename;
                    $preview_url = 'http://192.168.1.137/dijitalsalon/' . $preview_path;
                }
            }
            
            $formatted_media[] = [
                'id' => (int)$item['id'],
                'type' => $item['tur'], // 'foto' or 'video'
                'url' => $item['dosya_yolu'] ? 'http://192.168.1.137/dijitalsalon/' . $item['dosya_yolu'] : null,
                'thumbnail' => $item['kucuk_resim_yolu'] ? 'http://192.168.1.137/dijitalsalon/' . $item['kucuk_resim_yolu'] : null,
                'preview' => $preview_url, // Small version for grid
                'user_id' => (int)$item['user_id'],
                'user_name' => $item['user_name'] . ' ' . $item['user_surname'],
                'user_avatar' => $item['user_avatar'] ? 'http://192.168.1.137/dijitalsalon/' . $item['user_avatar'] : null,
                'likes' => (int)$item['likes_count'],
                'comments' => (int)$item['comments_count'],
                'is_liked' => (bool)$item['is_liked'],
                'created_at' => $item['created_at'],
                'description' => $item['aciklama'] ?? '',
            ];
        }
        
        echo json_encode([
            'success' => true,
            'media' => $formatted_media,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'has_more' => count($formatted_media) === $limit
            ]
        ]);
        exit;
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload new media
    $event_id = $_POST['event_id'] ?? '';
    $description = $_POST['description'] ?? '';
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
        $upload_dir = '../uploads/medyalar/';
        
        // Create directory if not exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'media_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save file.']);
            exit;
        }
        
        // Generate thumbnail for videos
        $thumbnail_path = null;
        if ($media_type === 'video') {
            $thumbnail_filename = 'thumb_' . pathinfo($filename, PATHINFO_FILENAME) . '.jpg';
            $thumbnail_path = $upload_dir . $thumbnail_filename;
            
            // TODO: Generate video thumbnail using FFmpeg
            // For now, we'll skip thumbnail generation
        }
        
        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO medyalar (
                dugun_id, 
                kullanici_id, 
                medya_tipi, 
                dosya_yolu, 
                kucuk_resim, 
                aciklama, 
                olusturma_tarihi
            ) VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $event_id,
            $user_id,
            $media_type,
            'uploads/medyalar/' . $filename,
            $thumbnail_path ? 'uploads/medyalar/' . basename($thumbnail_path) : null,
            $description
        ]);
        
        $media_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Media uploaded successfully.',
            'media_id' => (int)$media_id,
            'file_url' => 'http://192.168.1.137/dijitalsalon/uploads/medyalar/' . $filename
        ]);
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
