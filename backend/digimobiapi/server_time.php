<?php
/**
 * ✅ Sunucu Zamanı Endpoint'i
 * Uygulamanın tarih/saat karşılaştırmalarında kullanılacak sunucu zamanını döndürür
 */

require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // ✅ Sunucu zamanını al (MySQL server timezone'u kullan)
    $pdo = get_pdo();
    $stmt = $pdo->query("SELECT NOW() as server_time");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $serverTime = $result['server_time'];
    
    // ✅ DateTime objesi oluştur
    $serverDateTime = new DateTime($serverTime);
    
    // ✅ ISO 8601 formatında döndür
    $serverTimeISO = $serverDateTime->format('Y-m-d H:i:s');
    $serverTimestamp = $serverDateTime->getTimestamp();
    
    // ✅ Timezone bilgisi
    $timezone = $serverDateTime->getTimezone()->getName();
    
    json_ok([
        'server_time' => $serverTimeISO,
        'server_timestamp' => $serverTimestamp,
        'timezone' => $timezone,
        'date' => $serverDateTime->format('Y-m-d'),
        'time' => $serverDateTime->format('H:i:s'),
    ]);
} catch (Exception $e) {
    error_log("Server Time API Error: " . $e->getMessage());
    json_err(500, 'Sunucu zamanı alınamadı: ' . $e->getMessage());
}
?>

