<?php
/**
 * 🚀 INSTAGRAM STYLE - Tek Endpoint ile Profil İstatistikleri
 * Tüm profil verilerini tek sorguda alır (48 saniye → 1-2 saniye)
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

// ✅ Session kontrolü
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$current_user_id = $_SESSION['user_id'];
$target_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $current_user_id;

try {
    // ✅ TEK SORGU İLE TÜM VERİLER (Instagram gibi - ÇOK HIZLI!)
    // Not: dugun_katilimcilar tablosunda 'status' kolonu yoksa 'aktif' kontrolünü kaldırın
    
    // Önce status kolonu var mı kontrol et
    $check_status = $conn->query("SHOW COLUMNS FROM dugun_katilimcilar LIKE 'status'");
    $has_status = $check_status->num_rows > 0;
    
    $status_condition = $has_status ? "AND dk.status = 'aktif'" : "";
    
    // ✅ Ana sorgu - Tek seferde tüm sayıları al
    $stats_query = "
        SELECT 
            -- Event count (katıldığı aktif event'ler)
            (SELECT COUNT(DISTINCT dk.dugun_id) 
             FROM dugun_katilimcilar dk 
             WHERE dk.kullanici_id = ? $status_condition) as event_count,
            
            -- Media count (sadece görsel medya, hikaye değil)
            (SELECT COUNT(*) 
             FROM medya m
             INNER JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id
             WHERE m.kullanici_id = ? 
             AND dk.kullanici_id = ? 
             AND m.tur != 'hikaye' 
             $status_condition) as media_count,
            
            -- Story count (son 24 saat içindeki aktif hikayeler)
            (SELECT COUNT(*) 
             FROM medya m
             INNER JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id
             WHERE m.kullanici_id = ? 
             AND dk.kullanici_id = ? 
             AND m.tur = 'hikaye' 
             AND m.olusturma_tarihi > DATE_SUB(NOW(), INTERVAL 24 HOUR)
             $status_condition) as story_count
    ";
    
    $stmt = $conn->prepare($stats_query);
    if (!$stmt) {
        throw new Exception('SQL Prepare Error: ' . $conn->error);
    }
    
    // Parametreleri bağla
    $stmt->bind_param("iiiiii", 
        $target_user_id,  // event_count için
        $target_user_id, $current_user_id,  // media_count için
        $target_user_id, $current_user_id   // story_count için
    );
    
    $stmt->execute();
    $result = $stmt->get_result();
    $stats = $result->fetch_assoc();
    
    if (!$stats) {
        throw new Exception('Stats fetch failed');
    }
    
    // ✅ İlk 13 medya thumbnail (profil grid için - çok hızlı!)
    $media_query = "
        SELECT 
            m.id,
            m.dugun_id,
            m.tur as type,
            COALESCE(m.kucuk_resim_yolu, m.dosya_yolu) as thumbnail,
            m.dosya_yolu as url,
            m.olusturma_tarihi as created_at,
            m.aciklama as description
        FROM medya m
        INNER JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id
        WHERE m.kullanici_id = ? 
        AND dk.kullanici_id = ? 
        AND m.tur != 'hikaye'
        $status_condition
        ORDER BY m.olusturma_tarihi DESC
        LIMIT 13
    ";
    
    $media_stmt = $conn->prepare($media_query);
    if (!$media_stmt) {
        throw new Exception('Media SQL Prepare Error: ' . $conn->error);
    }
    
    $media_stmt->bind_param("ii", $target_user_id, $current_user_id);
    $media_stmt->execute();
    $media_result = $media_stmt->get_result();
    
    $initial_media = [];
    $base_url = 'https://dijitalsalon.cagapps.app/';
    
    while ($row = $media_result->fetch_assoc()) {
        $thumbnail = $row['thumbnail'];
        if ($thumbnail && !str_starts_with($thumbnail, 'http')) {
            $thumbnail = $base_url . ltrim($thumbnail, '/');
        }
        
        $url = $row['url'];
        if ($url && !str_starts_with($url, 'http')) {
            $url = $base_url . ltrim($url, '/');
        }
        
        $initial_media[] = [
            'id' => intval($row['id']),
            'event_id' => intval($row['dugun_id']),
            'type' => $row['type'],
            'thumbnail' => $thumbnail,
            'url' => $url,
            'description' => $row['description'] ?? '',
            'created_at' => $row['created_at']
        ];
    }
    
    // ✅ Başarılı yanıt
    echo json_encode([
        'success' => true,
        'stats' => [
            'event_count' => intval($stats['event_count'] ?? 0),
            'media_count' => intval($stats['media_count'] ?? 0),
            'story_count' => intval($stats['story_count'] ?? 0)
        ],
        'initial_media' => $initial_media
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    
    // Log error
    error_log("get_profile_stats.php Error: " . $e->getMessage());
}

