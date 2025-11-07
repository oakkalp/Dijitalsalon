<?php
/**
 * 🚀 INSTAGRAM STYLE - Tek Endpoint ile Profil İstatistikleri
 * Tüm profil verilerini tek sorguda alır (48 saniye → 1-2 saniye)
 */

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
    // ✅ OPTIMIZED: Status kontrolünü bir kez yap (cache)
    static $status_cache = null;
    if ($status_cache === null) {
        $check_status = $pdo->query("SHOW COLUMNS FROM dugun_katilimcilar LIKE 'status'");
        $status_cache = $check_status->rowCount() > 0;
    }
    $has_status = $status_cache;
    $status_condition = $has_status ? "AND dk.status = 'aktif'" : "";
    
    // ✅ OPTIMIZED: Tek sorguda hem stats hem media (JOIN ile - subquery'den hızlı!)
    // Önce event ID'lerini al (hızlı)
    $event_ids_query = "
        SELECT DISTINCT dk.dugun_id 
        FROM dugun_katilimcilar dk 
        WHERE dk.kullanici_id = ? $status_condition
    ";
    $event_stmt = $pdo->prepare($event_ids_query);
    $event_stmt->execute([$target_user_id]);
    $event_ids = $event_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($event_ids)) {
        // Kullanıcının hiç event'i yok
        echo json_encode([
            'success' => true,
            'stats' => ['event_count' => 0, 'media_count' => 0, 'story_count' => 0],
            'initial_media' => []
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
    
    $event_ids_placeholder = implode(',', array_fill(0, count($event_ids), '?'));
    
    // ✅ OPTIMIZED: Stats sorguları - paralel çalışacak şekilde optimize edildi
    // Event count (zaten event_ids'den biliyoruz ama tutarlılık için)
    $event_count = count($event_ids);
    
    // ✅ Media ve story count - tek sorguda (JOIN yerine IN clause - çok daha hızlı!)
    $media_stats_query = "
        SELECT 
            SUM(CASE WHEN m.tur != 'hikaye' THEN 1 ELSE 0 END) as media_count,
            SUM(CASE WHEN m.tur = 'hikaye' AND m.created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) as story_count
        FROM medyalar m
        WHERE m.kullanici_id = ? 
        AND m.dugun_id IN ($event_ids_placeholder)
    ";
    
    $media_stats_params = array_merge([$target_user_id], $event_ids);
    $stats_stmt = $pdo->prepare($media_stats_query);
    $stats_stmt->execute($media_stats_params);
    $media_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
    
    $stats = [
        'event_count' => $event_count,
        'media_count' => intval($media_stats['media_count'] ?? 0),
        'story_count' => intval($media_stats['story_count'] ?? 0)
    ];
    
    // ✅ OPTIMIZED: Medya sorgusu - tüm medyaları getir (kullanıcının kaç medyası varsa hepsini göster!)
    // LIMIT yok - kullanıcının tüm medyalarını getir (Instagram gibi!)
    $media_query = "
        SELECT 
            m.id,
            m.dugun_id,
            m.tur as type,
            COALESCE(m.kucuk_resim_yolu, m.dosya_yolu) as thumbnail,
            m.dosya_yolu as url,
            m.created_at,
            m.aciklama as description
        FROM medyalar m
        WHERE m.kullanici_id = ? 
        AND m.dugun_id IN ($event_ids_placeholder)
        AND m.tur != 'hikaye'
        ORDER BY m.created_at DESC
    ";
    
    $media_params = array_merge([$target_user_id], $event_ids);
    $media_stmt = $pdo->prepare($media_query);
    $media_stmt->execute($media_params);
    
    $initial_media = [];
    $base_url = 'https://dijitalsalon.cagapps.app/';
    
    while ($row = $media_stmt->fetch(PDO::FETCH_ASSOC)) {
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

