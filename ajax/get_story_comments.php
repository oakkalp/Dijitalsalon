<?php
session_start();
require_once '../config/database.php';

header('Content-Type: application/json');

try {
    $story_id = $_GET['id'] ?? null;
    
    if (!$story_id) {
        throw new Exception('Hikaye ID gerekli');
    }
    
    // Hikaye yorumlarını al
    $stmt = $pdo->prepare("
        SELECT 
            y.*,
            k.ad as user_name,
            k.soyad as user_surname,
            k.profil_fotografi as user_profile,
            TIMESTAMPDIFF(MINUTE, y.created_at, NOW()) as minutes_ago
        FROM yorumlar y
        JOIN kullanicilar k ON y.kullanici_id = k.id
        WHERE y.medya_id = ?
        ORDER BY y.created_at ASC
    ");
    $stmt->execute([$story_id]);
    $comments = $stmt->fetchAll();
    
    // Zaman formatını düzenle
    foreach ($comments as &$comment) {
        if ($comment['minutes_ago'] < 1) {
            $comment['time_ago'] = 'şimdi';
        } elseif ($comment['minutes_ago'] < 60) {
            $comment['time_ago'] = $comment['minutes_ago'] . ' dakika önce';
        } elseif ($comment['minutes_ago'] < 1440) {
            $comment['time_ago'] = floor($comment['minutes_ago'] / 60) . ' saat önce';
        } else {
            $comment['time_ago'] = floor($comment['minutes_ago'] / 1440) . ' gün önce';
        }
    }
    
    echo json_encode([
        'success' => true,
        'comments' => $comments
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>

