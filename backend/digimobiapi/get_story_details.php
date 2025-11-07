<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $story_user_id = $_GET['user_id'] ?? null;
    $event_id = $_GET['event_id'] ?? null;

    if (empty($story_user_id) || empty($event_id)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'User ID and Event ID are required.']);
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
        
        // Get stories for specific user
        $stmt = $pdo->prepare("
            SELECT 
                m.id,
                m.kullanici_id,
                m.dosya_yolu,
                m.tur,
                m.aciklama,
                m.created_at,
                k.ad as user_name,
                k.soyad as user_surname,
                k.profil_fotografi as user_avatar,
                (SELECT COUNT(*) FROM begeniler mb WHERE mb.medya_id = m.id) as likes_count,
                (SELECT COUNT(*) FROM yorumlar my WHERE my.medya_id = m.id) as comments_count,
                (SELECT COUNT(*) FROM begeniler mb2 WHERE mb2.medya_id = m.id AND mb2.kullanici_id = ?) as is_liked
            FROM medyalar m
            JOIN kullanicilar k ON m.kullanici_id = k.id
            WHERE m.dugun_id = ? AND m.kullanici_id = ? AND m.tur = 'hikaye'
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$user_id, $event_id, $story_user_id]);
        $stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Debug: Log the query and results
        error_log("Story Details Query - User ID: $user_id, Event ID: $event_id, Story User ID: $story_user_id");
        error_log("Stories found: " . count($stories));
        
        if (empty($stories)) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'No stories found.']);
            exit;
        }
        
        // Format stories for mobile app
        $formatted_stories = [];
        foreach ($stories as $story) {
            $formatted_stories[] = [
                'id' => (int)$story['id'],
                'user_id' => (int)$story['kullanici_id'],
                'media_url' => 'https://dijitalsalon.cagapps.app/' . $story['dosya_yolu'],
                'media_type' => $story['tur'],
                'description' => $story['aciklama'] ?? '',
                'created_at' => $story['created_at'],
                'likes' => (int)($story['likes_count'] ?? 0),
                'comments' => (int)($story['comments_count'] ?? 0),
                'is_liked' => (bool)($story['is_liked'] ?? false),
                'user_name' => $story['user_name'] . ' ' . $story['user_surname'],
                'user_avatar' => $story['user_avatar'] ? 'https://dijitalsalon.cagapps.app/' . $story['user_avatar'] : null,
            ];
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
    
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed.']);
    exit;
}
?>
