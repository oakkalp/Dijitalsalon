<?php
/**
 * Server-Sent Events for Real-time Updates
 * Digital Salon - Real-time event updates
 */

session_start();
require_once 'config/database.php';

// SSE headers
header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Cache-Control');

// Event ID'yi al
$event_id = (int)($_GET['event_id'] ?? 0);
$user_id = $_SESSION['user_id'] ?? 0;

if (!$event_id || !$user_id) {
    echo "data: {\"error\": \"Missing parameters\"}\n\n";
    exit;
}

// Kullanıcının bu düğüne erişim yetkisi var mı kontrol et
$stmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM dugun_katilimcilar 
    WHERE dugun_id = ? AND kullanici_id = ?
");
$stmt->execute([$event_id, $user_id]);
$has_access = $stmt->fetchColumn() > 0;

if (!$has_access) {
    echo "data: {\"error\": \"Access denied\"}\n\n";
    exit;
}

// Son kontrol zamanını al
$last_check = time();

// Sonsuz döngü - her 2 saniyede bir kontrol et
while (true) {
    // Yeni medya kontrolü
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM medyalar 
        WHERE dugun_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 2 SECOND)
    ");
    $stmt->execute([$event_id]);
    $new_media = $stmt->fetchColumn();
    
    // Yeni hikaye kontrolü
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM medyalar 
        WHERE dugun_id = ? AND tur = 'hikaye' AND created_at > DATE_SUB(NOW(), INTERVAL 2 SECOND)
    ");
    $stmt->execute([$event_id]);
    $new_stories = $stmt->fetchColumn();
    
    // Yeni yorum kontrolü
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM yorumlar y
        JOIN medyalar m ON y.medya_id = m.id
        WHERE m.dugun_id = ? AND y.created_at > DATE_SUB(NOW(), INTERVAL 2 SECOND)
    ");
    $stmt->execute([$event_id]);
    $new_comments = $stmt->fetchColumn();
    
    // Yeni beğeni kontrolü
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM begeniler b
        JOIN medyalar m ON b.medya_id = m.id
        WHERE m.dugun_id = ? AND b.created_at > DATE_SUB(NOW(), INTERVAL 2 SECOND)
    ");
    $stmt->execute([$event_id]);
    $new_likes = $stmt->fetchColumn();
    
    // Güncelleme varsa gönder
    if ($new_media > 0 || $new_stories > 0 || $new_comments > 0 || $new_likes > 0) {
        $update_data = [
            'type' => 'update',
            'timestamp' => time(),
            'data' => [
                'new_media' => $new_media,
                'new_stories' => $new_stories,
                'new_comments' => $new_comments,
                'new_likes' => $new_likes
            ]
        ];
        
        echo "data: " . json_encode($update_data) . "\n\n";
        ob_flush();
        flush();
    }
    
    // Bağlantı kontrolü
    if (connection_aborted()) {
        break;
    }
    
    // 2 saniye bekle
    sleep(2);
}
?>
