<?php
session_start();
header('Content-Type: application/json');

try {
    // Database connection
    $pdo = new PDO('mysql:host=localhost;dbname=digitalsalon_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $event_id = (int)$_GET['event_id'];
    
    // Get all users with their participation status for this event
    $stmt = $pdo->prepare("
        SELECT 
            k.id,
            k.ad,
            k.soyad,
            k.email,
            k.profil_fotografi,
            k.rol,
            CASE 
                WHEN dk.kullanici_id IS NOT NULL THEN 1 
                ELSE 0 
            END as is_participant,
            dk.rol as participant_role
        FROM kullanicilar k
        LEFT JOIN dugun_katilimcilar dk ON k.id = dk.kullanici_id AND dk.dugun_id = ?
        ORDER BY k.ad, k.soyad
    ");
    $stmt->execute([$event_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'users' => $users
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Kullanıcılar yüklenirken hata oluştu.'
    ]);
}
?>
