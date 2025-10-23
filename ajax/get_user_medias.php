<?php
session_start();
header('Content-Type: application/json');

try {
    // Database connection
    $pdo = new PDO('mysql:host=localhost;dbname=digitalsalon_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = (int)$_GET['user_id'];
    $event_id = (int)$_GET['event_id'];
    
    // Get user's medias for this specific event
    $stmt = $pdo->prepare("
        SELECT 
            m.id,
            m.dosya_yolu,
            m.kucuk_resim_yolu,
            m.aciklama,
            m.created_at
        FROM medyalar m
        WHERE m.kullanici_id = ? AND m.dugun_id = ?
        ORDER BY m.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$user_id, $event_id]);
    $medias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'medias' => $medias
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Medyalar yüklenirken hata oluştu.'
    ]);
}
?>
