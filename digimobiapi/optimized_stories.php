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
    $userId = $_GET['user_id'] ?? null;
    
    if (!$eventId) {
        throw new Exception('Event ID is required');
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized - Please login');
    }
    
    $currentUserId = $_SESSION['user_id'];
    
    // Check if user is participant (cached check)
    $isParticipant = $db->fetchOne("
        SELECT COUNT(*) as count 
        FROM dugun_katilimcilar 
        WHERE dugun_id = ? AND kullanici_id = ?
    ", [$eventId, $currentUserId])['count'] > 0;
    
    if (!$isParticipant) {
        throw new Exception('You are not a participant of this event');
    }
    
    if ($userId) {
        // Get specific user's stories
        $stories = $db->fetchAll("
            SELECT 
                m.id,
                m.url,
                m.aciklama,
                m.created_at as olusturma_tarihi,
                m.tur,
                k.ad as user_name,
                k.profil_fotografi as user_avatar
            FROM medyalar m
            JOIN kullanicilar k ON m.kullanici_id = k.id
            WHERE m.dugun_id = ? 
            AND m.kullanici_id = ?
            AND m.tur = 'hikaye'
            AND m.hikaye_bitis_tarihi > NOW()
            ORDER BY m.created_at ASC
        ", [$eventId, $userId]);
        
    } else {
        // Get all users with stories (optimized)
        $stories = $db->fetchAll("
            SELECT 
                m.kullanici_id,
                k.ad as user_name,
                k.profil_fotografi as user_avatar,
                COUNT(m.id) as story_count,
                MAX(m.created_at) as latest_story_time,
                CASE WHEN hi.kullanici_id IS NOT NULL THEN 1 ELSE 0 END as is_viewed
            FROM medyalar m
            JOIN kullanicilar k ON m.kullanici_id = k.id
            LEFT JOIN hikaye_izlenme hi ON m.id = hi.hikaye_id AND hi.kullanici_id = ?
            WHERE m.dugun_id = ? 
            AND m.tur = 'hikaye'
            AND m.hikaye_bitis_tarihi > NOW()
            GROUP BY m.kullanici_id, k.ad, k.profil_fotografi
            ORDER BY latest_story_time DESC
        ", [$currentUserId, $eventId]);
    }
    
    // Return optimized response
    echo json_encode([
        'success' => true,
        'stories' => $stories
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Stories API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
