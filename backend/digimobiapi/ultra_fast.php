<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Start session
session_start();

// Include fast database connection
require_once 'fast_db.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $db = FastDB::getInstance();
    
    // Get parameters
    $eventId = $_GET['event_id'] ?? null;
    
    if (!$eventId) {
        throw new Exception('Event ID is required');
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized - Please login');
    }
    
    $userId = $_SESSION['user_id'];
    
    // Check if user is participant
    $isParticipant = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM dugun_katilimcilar 
        WHERE dugun_id = ? AND kullanici_id = ?
    ", [$eventId, $userId])['count'] > 0;
    
    if (!$isParticipant) {
        throw new Exception('You are not a participant of this event');
    }
    
    // Get media data
    $media = $db->fetchAll("
        SELECT 
            m.id,
            m.url,
            m.thumbnail,
            m.tur,
            m.aciklama,
            m.olusturma_tarihi,
            k.ad as user_name,
            k.profil_foto as user_avatar,
            COALESCE(like_count.likes, 0) as likes,
            COALESCE(comment_count.comments, 0) as comments,
            CASE WHEN user_likes.user_id IS NOT NULL THEN 1 ELSE 0 END as is_liked
        FROM medyalar m
        JOIN kullanicilar k ON m.kullanici_id = k.id
        LEFT JOIN (
            SELECT medya_id, COUNT(*) as likes
            FROM begeniler
            GROUP BY medya_id
        ) like_count ON m.id = like_count.medya_id
        LEFT JOIN (
            SELECT medya_id, COUNT(*) as comments
            FROM yorumlar
            GROUP BY medya_id
        ) comment_count ON m.id = comment_count.medya_id
        LEFT JOIN (
            SELECT medya_id, kullanici_id
            FROM begeniler
            WHERE kullanici_id = ?
        ) user_likes ON m.id = user_likes.medya_id
        WHERE m.dugun_id = ? 
        AND m.tur IN ('foto', 'video')
        AND (m.hikaye_bitis_tarihi IS NULL OR m.hikaye_bitis_tarihi > NOW())
        ORDER BY m.olusturma_tarihi DESC
    ", [$userId, $eventId]);
    
    // Get stories data
    $stories = $db->fetchAll("
        SELECT 
            m.kullanici_id,
            k.ad as user_name,
            k.profil_foto as user_avatar,
            COUNT(m.id) as story_count,
            MAX(m.olusturma_tarihi) as latest_story_time,
            CASE WHEN hi.kullanici_id IS NOT NULL THEN 1 ELSE 0 END as is_viewed
        FROM medyalar m
        JOIN kullanicilar k ON m.kullanici_id = k.id
        LEFT JOIN hikaye_izlenme hi ON m.id = hi.hikaye_id AND hi.kullanici_id = ?
        WHERE m.dugun_id = ? 
        AND m.tur = 'hikaye'
        AND m.hikaye_bitis_tarihi > NOW()
        GROUP BY m.kullanici_id, k.ad, k.profil_foto
        ORDER BY latest_story_time DESC
    ", [$userId, $eventId]);
    
    // Return ultra-fast response
    echo json_encode([
        'success' => true,
        'media' => $media,
        'stories' => $stories,
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Ultra Fast API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
