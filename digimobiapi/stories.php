<?php
require_once __DIR__ . '/bootstrap.php';

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
            // Get specific user's stories
            $stmt = $pdo->prepare("
                SELECT 
                    m.id,
                    m.kullanici_id,
                    m.dosya_yolu,
                    m.aciklama,
                    m.created_at,
                    m.tur,
                    k.ad as user_name,
                    k.soyad as user_surname,
                    k.profil_fotografi as user_profile
                FROM medyalar m
                JOIN kullanicilar k ON m.kullanici_id = k.id
                WHERE m.dugun_id = ? AND m.tur = 'hikaye' AND m.kullanici_id = ?
                ORDER BY m.created_at ASC
            ");
            $stmt->execute([$event_id, $specific_user_id]);
            $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Format individual stories
            $formatted_stories = [];
            foreach ($stories as $story) {
                $formatted_stories[] = [
                    'id' => (int)$story['id'],
                    'user_id' => (int)$story['kullanici_id'],
                    'user_name' => $story['user_name'] . ' ' . $story['user_surname'],
                    'user_avatar' => $story['user_profile'] ? 'http://192.168.1.137/dijitalsalon/' . $story['user_profile'] : null,
                    'url' => $story['dosya_yolu'] ? 'http://192.168.1.137/dijitalsalon/' . $story['dosya_yolu'] : null,
                    'description' => $story['aciklama'] ?? '',
                    'created_at' => $story['created_at'],
                    'type' => $story['tur'],
                ];
            }
        } else {
            // Get stories for this event (grouped by user) - exclude blocked users
            $stmt = $pdo->prepare("
                SELECT 
                    m.kullanici_id,
                    k.ad as user_name,
                    k.soyad as user_surname,
                    k.profil_fotografi as user_profile,
                    COUNT(*) as story_count,
                    MAX(m.created_at) as latest_story_time
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
                    'user_name' => $story['user_name'] . ' ' . $story['user_surname'],
                    'user_avatar' => $story['user_profile'] ? 'http://192.168.1.137/dijitalsalon/' . $story['user_profile'] : null,
                    'story_count' => (int)$story['story_count'],
                    'latest_story_time' => $story['latest_story_time'],
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
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'story_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
        $file_path = $upload_dir . $filename;
        
        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $file_path)) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Failed to save file.']);
            exit;
        }
        
        // Save to database
        $stmt = $pdo->prepare("
            INSERT INTO hikayeler (
                dugun_id, 
                kullanici_id, 
                medya_tipi, 
                dosya_yolu, 
                olusturma_tarihi
            ) VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $event_id,
            $user_id,
            $media_type,
            'uploads/hikayeler/' . $filename
        ]);
        
        $story_id = $pdo->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'message' => 'Story uploaded successfully.',
            'story_id' => (int)$story_id,
            'file_url' => 'http://192.168.1.137/dijitalsalon/uploads/hikayeler/' . $filename
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
        // Check if story exists and user has access
        $stmt = $pdo->prepare("
            SELECT h.id, h.dugun_id
            FROM hikayeler h
            JOIN dugun_katilimcilar dk ON h.dugun_id = dk.dugun_id
            WHERE h.id = ? AND dk.kullanici_id = ?
        ");
        $stmt->execute([$story_id, $user_id]);
        $story = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$story) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Story not found or access denied.']);
            exit;
        }
        
        // Check if already viewed
        $stmt = $pdo->prepare("SELECT id FROM hikaye_izlenme WHERE hikaye_id = ? AND kullanici_id = ?");
        $stmt->execute([$story_id, $user_id]);
        $existing_view = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_view) {
            echo json_encode(['success' => true, 'message' => 'Already viewed.']);
            exit;
        }
        
        // Mark as viewed
        $stmt = $pdo->prepare("
            INSERT INTO hikaye_izlenme (hikaye_id, kullanici_id, izlenme_tarihi) 
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
