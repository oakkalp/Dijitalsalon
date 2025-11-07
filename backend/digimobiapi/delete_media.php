<?php
require_once __DIR__ . '/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $media_id = $input['media_id'] ?? null;
        
        // Debug logging
        error_log("Delete Media Request - Media ID: " . ($media_id ?? 'null'));
        error_log("Delete Media Request - Input: " . json_encode($input));
        
        if (!$media_id) {
            throw new Exception('Media ID is required');
        }
        
        // Get media info
        $stmt = $pdo->prepare("
            SELECT m.*, m.dugun_id, m.kullanici_id as media_owner_id, m.dosya_yolu
            FROM medyalar m
            WHERE m.id = ? AND (m.tur IS NULL OR m.tur = '' OR m.tur IN ('foto', 'video', 'fotograf'))
        ");
        $stmt->execute([$media_id]);
        $media = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug logging
        error_log("Delete Media Query - Media ID: $media_id");
        error_log("Delete Media Query - Found Media: " . ($media ? 'YES' : 'NO'));
        if ($media) {
            error_log("Delete Media Query - Media Type: " . ($media['tur'] ?? 'null'));
            error_log("Delete Media Query - Media Owner: " . ($media['media_owner_id'] ?? 'null'));
        }
        
        if (!$media) {
            throw new Exception('Media not found');
        }
        
        // Check authorization: own media or authorized user
        $canDelete = false;
        
        // Own media
        if ($media['media_owner_id'] == $user_id) {
            $canDelete = true;
        } else {
            // Check if user is authorized participant (not own media)
            $stmt = $pdo->prepare("
                SELECT dk.kullanici_id, dk.rol, dk.yetkiler
                FROM dugun_katilimcilar dk
                WHERE dk.dugun_id = ? AND dk.kullanici_id = ?
            ");
            $stmt->execute([$media['dugun_id'], $user_id]);
            $participant = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($participant) {
                $user_role = $participant['rol'] ?: 'kullanici';
                
                // Admin ve Moderator her şeyi silebilir
                if (in_array($user_role, ['admin', 'moderator'])) {
                    $canDelete = true;
                } 
                // Yetkili kullanıcı: medya_silebilir yetkisi varsa silebilir
                elseif ($user_role === 'yetkili_kullanici') {
                    $permissions = $participant['yetkiler'] ? json_decode($participant['yetkiler'], true) : [];
                    
                    // ✅ Yetkiler array olarak geliyorsa (["medya_silebilir", ...]) in_array kullan
                    // ✅ Ya da object olarak geliyorsa ({"medya_silebilir": true, ...}) key kontrolü yap
                    if (is_array($permissions) && isset($permissions[0])) {
                        $has_delete_permission = in_array('medya_silebilir', $permissions);
                    } else {
                        $has_delete_permission = ($permissions['medya_silebilir'] ?? false);
                    }
                    
                    if ($has_delete_permission) {
                        $canDelete = true;
                    }
                }
            }
        }
        
        if (!$canDelete) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'Bu medyayı silme yetkiniz bulunmamaktadır.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
        
        // Delete media files (original, thumbnail, preview)
        $file_path = $media['dosya_yolu'] ?? null;
        $thumbnail_path = $media['kucuk_resim_yolu'] ?? null;
        $preview_path = $media['onizleme_yolu'] ?? null;
        
        if ($file_path) {
            // Orijinal dosya sil
            $full_path = $file_path;
            if (!file_exists($full_path) && substr($full_path, 0, 1) !== '/') {
                $full_path = __DIR__ . '/../' . $file_path;
            }
            if (file_exists($full_path)) {
                @unlink($full_path);
                error_log("Deleted original media file: $full_path");
            }
        }
        
        if ($thumbnail_path) {
            // Thumbnail dosya sil
            $full_thumbnail_path = $thumbnail_path;
            if (!file_exists($full_thumbnail_path) && substr($full_thumbnail_path, 0, 1) !== '/') {
                $full_thumbnail_path = __DIR__ . '/../' . $thumbnail_path;
            }
            if (file_exists($full_thumbnail_path)) {
                @unlink($full_thumbnail_path);
                error_log("Deleted thumbnail file: $full_thumbnail_path");
            }
        }
        
        if ($preview_path) {
            // Preview dosya sil
            $full_preview_path = $preview_path;
            if (!file_exists($full_preview_path) && substr($full_preview_path, 0, 1) !== '/') {
                $full_preview_path = __DIR__ . '/../' . $preview_path;
            }
            if (file_exists($full_preview_path)) {
                @unlink($full_preview_path);
                error_log("Deleted preview file: $full_preview_path");
            }
        }
        
        // Delete media from database
        $stmt = $pdo->prepare("DELETE FROM medyalar WHERE id = ?");
        $stmt->execute([$media_id]);
        
        // Delete media likes
        $stmt = $pdo->prepare("DELETE FROM begeniler WHERE medya_id = ?");
        $stmt->execute([$media_id]);
        
        // Delete media comments
        $stmt = $pdo->prepare("DELETE FROM yorumlar WHERE medya_id = ?");
        $stmt->execute([$media_id]);
        
        // Delete comment likes for this media's comments
        $stmt = $pdo->prepare("DELETE FROM yorum_begeniler WHERE yorum_id IN (SELECT id FROM yorumlar WHERE medya_id = ?)");
        $stmt->execute([$media_id]);
        
        // ✅ Medya silindiğinde ilgili bildirimleri de sil
        // Bildirimlerin data kolonunda media_id var (JSON formatında)
        // İki şekilde kontrol et: data JSON'dan ve direkt media_id kolonundan (eski bildirimler için)
        try {
            // Önce data JSON'unda media_id olan bildirimleri bul ve sil
            $stmt = $pdo->prepare("
                DELETE FROM notifications 
                WHERE JSON_EXTRACT(data, '$.media_id') = ? 
                OR (JSON_EXTRACT(data, '$.media_id') IS NULL AND media_id = ?)
            ");
            $stmt->execute([$media_id, $media_id]);
            $deleted_notifications = $stmt->rowCount();
            error_log("Deleted $deleted_notifications notifications for media_id: $media_id");
            
            // Eğer JSON_EXTRACT çalışmazsa alternatif yöntem (eski MySQL sürümleri için)
            if ($deleted_notifications == 0) {
                // Tüm bildirimleri çek ve JSON decode ile kontrol et
                $stmt = $pdo->prepare("SELECT id, data FROM notifications WHERE data IS NOT NULL AND data != ''");
                $stmt->execute();
                $all_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $notification_ids_to_delete = [];
                foreach ($all_notifications as $notif) {
                    $data = json_decode($notif['data'], true);
                    if ($data && isset($data['media_id']) && $data['media_id'] == $media_id) {
                        $notification_ids_to_delete[] = $notif['id'];
                    }
                }
                
                if (!empty($notification_ids_to_delete)) {
                    $placeholders = implode(',', array_fill(0, count($notification_ids_to_delete), '?'));
                    $stmt = $pdo->prepare("DELETE FROM notifications WHERE id IN ($placeholders)");
                    $stmt->execute($notification_ids_to_delete);
                    error_log("Deleted " . count($notification_ids_to_delete) . " notifications for media_id: $media_id (alternative method)");
                }
            }
        } catch (Exception $e) {
            error_log("Error deleting notifications for media_id $media_id: " . $e->getMessage());
            // Hata olsa bile medya silme işlemi devam etsin
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Medya başarıyla silindi'
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed'
        ], JSON_UNESCAPED_UNICODE);
    }
    
} catch (Exception $e) {
    error_log("Delete Media API Error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Sunucu hatası: ' . $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>