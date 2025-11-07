<?php
session_start();
require_once '../config/database.php';
require_once '../includes/security.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$story_id = (int)($_GET['id'] ?? 0);

if (!$story_id) {
    echo json_encode(['success' => false, 'message' => 'Story ID required']);
    exit();
}

try {
    // Get story details
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            k.ad as user_name,
            k.soyad as user_surname,
            k.profil_fotografi as user_profile
        FROM medyalar m
        JOIN kullanicilar k ON m.kullanici_id = k.id
        WHERE m.id = ? AND m.tur = 'hikaye'
        AND (m.hikaye_bitis_tarihi IS NULL OR m.hikaye_bitis_tarihi > NOW())
    ");
    $stmt->execute([$story_id]);
    $story = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$story) {
        echo json_encode(['success' => false, 'message' => 'Story not found or expired', 'debug' => ['story_id' => $story_id]]);
        exit();
    }
    
    // Check if user is participant of the event
    $stmt = $pdo->prepare("SELECT id FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
    $stmt->execute([$story['dugun_id'], $user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Not a participant of this event']);
        exit();
    }
    
    echo json_encode([
        'success' => true,
        'story' => $story
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false, 
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?>
