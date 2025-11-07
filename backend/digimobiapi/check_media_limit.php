<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

try {
    // Kullanıcı oturumu kontrolü
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized'
        ]);
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $event_id = isset($_GET['event_id']) ? intval($_GET['event_id']) : 0;
    $type = isset($_GET['type']) ? $_GET['type'] : 'media'; // 'media' veya 'story'

    if ($event_id === 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Event ID required'
        ]);
        exit;
    }

    // Event bilgilerini al
    // ✅ Paketler tablosu veya medya_limiti kolonu yoksa hata vermemeli, sadece limit yok kabul et
    try {
        // Önce paketler tablosunun varlığını kontrol et
        $stmt_check = $pdo->query("SHOW TABLES LIKE 'paketler'");
        $table_exists = $stmt_check->rowCount() > 0;
        
        if ($table_exists) {
            // Paketler tablosu varsa, kolon varlığını kontrol et
            try {
                $stmt_check_col = $pdo->query("SHOW COLUMNS FROM paketler LIKE 'medya_limiti'");
                $col_exists = $stmt_check_col->rowCount() > 0;
            } catch (PDOException $e) {
                $col_exists = false;
            }
        } else {
            $col_exists = false;
        }
        
        if ($table_exists && $col_exists) {
            // Kolon varsa normal sorguyu çalıştır
            $stmt = $pdo->prepare("
                SELECT d.*, COALESCE(p.medya_limiti, 0) as media_limit
                FROM dugunler d
                LEFT JOIN paketler p ON d.paket_id = p.id
                WHERE d.id = ?
            ");
            $stmt->execute([$event_id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            // Kolon yoksa sadece event bilgilerini al
            $stmt = $pdo->prepare("SELECT * FROM dugunler WHERE id = ?");
            $stmt->execute([$event_id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($event) {
                $event['media_limit'] = 0; // Limit yok
            }
        }
    } catch (PDOException $e) {
        // Hata durumunda sadece event bilgilerini al
        error_log("Check Media Limit - Package query error (ignoring): " . $e->getMessage());
        try {
            $stmt = $pdo->prepare("SELECT * FROM dugunler WHERE id = ?");
            $stmt->execute([$event_id]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($event) {
                $event['media_limit'] = 0; // Limit yok
            }
        } catch (PDOException $e2) {
            error_log("Check Media Limit - Event query error: " . $e2->getMessage());
            $event = null;
        }
    }

    if (!$event) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Event not found'
        ]);
        exit;
    }

    // Kullanıcının rolünü kontrol et
    $stmt = $pdo->prepare("
        SELECT k.rol, k.yetkiler, u.rol as user_global_role
        FROM dugun_katilimcilar k
        LEFT JOIN kullanicilar u ON k.kullanici_id = u.id
        WHERE k.kullanici_id = ? AND k.dugun_id = ?
    ");
    $stmt->execute([$user_id, $event_id]);
    $participant = $stmt->fetch(PDO::FETCH_ASSOC);

    // Etkinlik oluşturucusu kontrolü (sadece bu event için bypass)
    $is_event_creator = (isset($event['olusturan_id']) && intval($event['olusturan_id']) === intval($user_id));

    // Katılımcı rolünü event özelinde değerlendir
    $event_role = strtolower(trim($participant['rol'] ?? ''));
    $is_event_moderator = ($event_role === 'moderator');
    $is_event_admin = ($event_role === 'admin');

    // Yalnızca etkinlik oluşturucusu için tam bypass; event moderator/admin de sınırsız kabul edilir
    if ($is_event_creator || $is_event_moderator || $is_event_admin) {
        echo json_encode([
            'success' => true,
            'can_upload' => true,
            'is_moderator' => true,
            'message' => 'Etkinlik yöneticisi - Limit yok'
        ]);
        exit;
    }

    // Limit kontrolü (sadece normal kullanıcılar için)
    $media_limit = isset($event['media_limit']) ? intval($event['media_limit']) : 0;
    $current_count = 0;
    
    if ($media_limit > 0) {
        // Mevcut medya/hikaye sayısını al
        if ($type === 'story') {
            // Hikaye limiti = Medya limiti
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM medyalar 
                WHERE dugun_id = ? AND tur = 'hikaye'
            ");
            $stmt->execute([$event_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_count = intval($result['count']);
            
            if ($current_count >= $media_limit) {
                echo json_encode([
                    'success' => true,
                    'can_upload' => false,
                    'limit_reached' => true,
                    'limit' => $media_limit,
                    'current' => $current_count,
                    'type' => 'story',
                    'message' => 'Hikaye paylaşım limiti doldu. Yeni hikaye paylaşmak için eski hikayelerinizi silebilirsiniz.'
                ]);
                exit;
            }
        } else {
            // Medya limiti (hikayeler hariç)
            $stmt = $pdo->prepare("
                SELECT COUNT(*) as count 
                FROM medyalar 
                WHERE dugun_id = ? AND tur != 'hikaye'
            ");
            $stmt->execute([$event_id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $current_count = intval($result['count']);
            
            if ($current_count >= $media_limit) {
                echo json_encode([
                    'success' => true,
                    'can_upload' => false,
                    'limit_reached' => true,
                    'limit' => $media_limit,
                    'current' => $current_count,
                    'type' => 'media',
                    'message' => 'Medya paylaşım limiti doldu. Bu etkinliğe daha fazla medya eklenemiyor.'
                ]);
                exit;
            }
        }
    }

    // Limit dolu değil, upload edilebilir
    echo json_encode([
        'success' => true,
        'can_upload' => true,
        'limit_reached' => false,
        'limit' => $media_limit,
        'current' => $current_count ?? 0,
        'type' => $type,
        'message' => 'Upload edilebilir'
    ]);

} catch (Exception $e) {
    error_log('Check Media Limit Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error: ' . $e->getMessage()
    ]);
}
?>

