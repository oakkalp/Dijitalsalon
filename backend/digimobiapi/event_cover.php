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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Upload event cover photo
    $event_id = $_POST['event_id'] ?? '';
    
    if (empty($event_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Event ID is required.']);
        exit;
    }
    
    try {
        // Check if user is moderator of this event
        $stmt = $pdo->prepare("
            SELECT d.id, d.baslik
            FROM dugunler d 
            WHERE d.id = ? AND d.moderator_id = ?
        ");
        $stmt->execute([$event_id, $user_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'You are not the moderator of this event.']);
            exit;
        }
        
        // Handle file upload
        if (!isset($_FILES['cover_photo']) || $_FILES['cover_photo']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No cover photo uploaded or upload error.']);
            exit;
        }
        
        $file = $_FILES['cover_photo'];
        
        // Validate image file
        if (!ImageUtils::validateImage($file['tmp_name'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid image file. Only JPG, PNG, GIF, and WebP are allowed.']);
            exit;
        }
        
        $upload_dir = '../uploads/events/' . $event_id . '/';
        
        // Create directory if not exists
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $base_filename = 'event_cover_' . $event_id . '_' . time() . '_' . uniqid();
        $filename = $base_filename . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save cover photo.']);
            exit;
        }
        
        // Process image: compress, resize, and create thumbnails
        $image_results = ImageUtils::processImage($file_path, $base_filename, $upload_dir, $file_extension, 'event_cover');
        
        if ($image_results['error']) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Image processing failed: ' . $image_results['error']]);
            exit;
        }
        
        $thumbnail_path = $image_results['thumbnail'] ? basename($image_results['thumbnail']) : null;
        $preview_path = $image_results['preview'] ? basename($image_results['preview']) : null;
        
        // Update file path to use processed version
        $filename = basename($image_results['original']);
        
        // Update event cover photo in database
        $stmt = $pdo->prepare("
            UPDATE dugunler 
            SET kapak_fotografi = ?, guncelleme_tarihi = NOW()
            WHERE id = ? AND moderator_id = ?
        ");
        $stmt->execute([
            'uploads/events/' . $event_id . '/' . $filename,
            $event_id,
            $user_id
        ]);
        
        // Get optimized URLs
        $image_urls = ImageUtils::getImageUrls('uploads/events/' . $event_id . '/' . $filename, $base_filename, $file_extension);
        
        echo json_encode([
            'success' => true,
            'message' => 'Event cover photo updated successfully.',
            'event_id' => (int)$event_id,
            'event_title' => $event['baslik'],
            'cover_photo_url' => $image_urls['original'],
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
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get event cover photo info
    $event_id = $_GET['event_id'] ?? '';
    
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
        
        // Get event cover photo info
        $stmt = $pdo->prepare("
            SELECT d.id, d.baslik, d.kapak_fotografi
            FROM dugunler d 
            WHERE d.id = ?
        ");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$event) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Event not found.']);
            exit;
        }
        
        $cover_photo_urls = null;
        if ($event['kapak_fotografi']) {
            $path_info = pathinfo($event['kapak_fotografi']);
            $extension = strtolower($path_info['extension']);
            $filename_without_ext = $path_info['filename'];
            
            $cover_photo_urls = [
                'original' => 'https://dijitalsalon.cagapps.app/' . $event['kapak_fotografi'],
                'thumbnail' => 'https://dijitalsalon.cagapps.app/' . $path_info['dirname'] . '/' . $filename_without_ext . '_thumb.' . $extension,
                'preview' => 'https://dijitalsalon.cagapps.app/' . $path_info['dirname'] . '/' . $filename_without_ext . '_preview.' . $extension,
            ];
        }
        
        echo json_encode([
            'success' => true,
            'event_id' => (int)$event['id'],
            'event_title' => $event['baslik'],
            'cover_photo' => $cover_photo_urls
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
