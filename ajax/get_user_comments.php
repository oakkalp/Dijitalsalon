<?php
session_start();
header('Content-Type: application/json');

try {
    // Database connection
    $pdo = new PDO('mysql:host=localhost;dbname=digitalsalon_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = (int)$_GET['user_id'];
    $event_id = (int)$_GET['event_id'];
    
    // Get user's comments for this specific event
    $stmt = $pdo->prepare("
        SELECT 
            y.id,
            y.yorum_metni as content,
            y.created_at,
            COALESCE(yb.like_count, 0) as like_count
        FROM yorumlar y
        JOIN medyalar m ON y.medya_id = m.id
        LEFT JOIN (
            SELECT yorum_id, COUNT(*) as like_count
            FROM yorum_begeniler
            GROUP BY yorum_id
        ) yb ON y.id = yb.yorum_id
        WHERE y.kullanici_id = ? AND m.dugun_id = ?
        ORDER BY y.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $event_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Yorumlar yüklenirken hata oluştu.'
    ]);
}
?>
