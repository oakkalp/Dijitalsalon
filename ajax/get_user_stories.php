<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $user_id = $_GET['user_id'] ?? null;
    $event_id = $_GET['event_id'] ?? null;
    
    if (!$user_id || !$event_id) {
        throw new Exception('Kullanıcı ID ve Etkinlik ID gerekli');
    }
    
    // Kullanıcının tüm hikayelerini al (sıralı)
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            k.ad as user_name,
            k.soyad as user_surname,
            k.profil_fotografi as user_profile
        FROM medyalar m
        JOIN kullanicilar k ON m.kullanici_id = k.id
        WHERE m.dugun_id = ? AND m.kullanici_id = ? AND m.tur = 'hikaye'
        AND (m.hikaye_bitis_tarihi IS NULL OR m.hikaye_bitis_tarihi > NOW())
        ORDER BY m.created_at ASC
    ");
    $stmt->execute([$event_id, $user_id]);
    $stories = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'stories' => $stories
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

