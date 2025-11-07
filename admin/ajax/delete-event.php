<?php
// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session'ı başlat
session_start();

require_once '../../config/database.php';

// Admin kontrolü
if (!isset($_SESSION['admin_user_id']) || !isset($_SESSION['admin_user_role'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
    exit;
}

$event_id = (int)($_POST['event_id'] ?? 0);

if (!$event_id) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz düğün ID']);
    exit;
}

try {
    $pdo->beginTransaction();
    
    // Düğün bilgilerini al
    $stmt = $pdo->prepare("SELECT * FROM dugunler WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        throw new Exception('Düğün bulunamadı');
    }
    
    // Moderator kontrolü (moderator sadece kendi düğünlerini silebilir)
    if ($_SESSION['admin_user_role'] === 'moderator' && $event['moderator_id'] != $_SESSION['admin_user_id']) {
        throw new Exception('Bu düğünü silme yetkiniz yok');
    }
    
    $deleted_files = [];
    
    // Medya dosyalarını sil (hem normal medyalar hem hikayeler - tur='hikaye' olanlar)
    $stmt = $pdo->prepare("SELECT * FROM medyalar WHERE dugun_id = ?");
    $stmt->execute([$event_id]);
    $medias = $stmt->fetchAll();
    
    foreach ($medias as $media) {
        // Ana medya dosyası
        if (!empty($media['dosya_yolu'])) {
            $file_path = '../../' . $media['dosya_yolu'];
            if (file_exists($file_path) && unlink($file_path)) {
                $deleted_files[] = $media['dosya_yolu'];
            }
        }
        
        // ✅ Küçük resim yolu (thumbnail)
        if (!empty($media['kucuk_resim_yolu'])) {
            $thumb_path = '../../' . $media['kucuk_resim_yolu'];
            if (file_exists($thumb_path) && unlink($thumb_path)) {
                $deleted_files[] = $media['kucuk_resim_yolu'];
            }
        }
        
        // Preview dosyası (dosya adına _preview ekleyerek)
        if (!empty($media['dosya_yolu'])) {
            $path_info = pathinfo($media['dosya_yolu']);
            $preview_path = '../../' . $path_info['dirname'] . '/' . $path_info['filename'] . '_preview.' . $path_info['extension'];
            if (file_exists($preview_path) && unlink($preview_path)) {
                $deleted_files[] = $path_info['dirname'] . '/' . $path_info['filename'] . '_preview.' . $path_info['extension'];
            }
        }
        
        // Thumbnail dosyası (dosya adına _thumb ekleyerek)
        if (!empty($media['dosya_yolu'])) {
            $path_info = pathinfo($media['dosya_yolu']);
            $thumb_path_from_filename = '../../' . $path_info['dirname'] . '/' . $path_info['filename'] . '_thumb.' . $path_info['extension'];
            if (file_exists($thumb_path_from_filename) && unlink($thumb_path_from_filename)) {
                $deleted_files[] = $path_info['dirname'] . '/' . $path_info['filename'] . '_thumb.' . $path_info['extension'];
            }
        }
    }
    
    // ✅ Hikayeler medyalar tablosunda tur='hikaye' olarak saklanıyor
    // Yukarıdaki medya silme işlemi zaten hikayeleri de içeriyor (tur='hikaye' olanlar)
    // Bu nedenle ayrı bir hikayeler tablosu kontrolüne gerek yok
    // NOT: Eğer gelecekte ayrı bir hikayeler tablosu kullanılırsa, burada ek kod eklenebilir
    
    // Kapak fotoğrafını sil
    if (!empty($event['kapak_fotografi'])) {
        $cover_path = '../../' . $event['kapak_fotografi'];
        if (file_exists($cover_path) && unlink($cover_path)) {
            $deleted_files[] = $event['kapak_fotografi'];
        }
    }
    
    // Thumbnail fotoğrafını sil
    if (!empty($event['thumbnail_fotografi'])) {
        $thumb_path = '../../' . $event['thumbnail_fotografi'];
        if (file_exists($thumb_path) && unlink($thumb_path)) {
            $deleted_files[] = $event['thumbnail_fotografi'];
        }
    }
    
    // Upload klasörünü tamamen sil (recursive)
    // admin/ajax/ klasöründen kök dizine çıkmak için ../../ gerekli
    $upload_dirs = [
        '../../uploads/events/' . $event_id,
        '../../uploads/media/' . $event_id,
        '../../uploads/stories/' . $event_id
    ];
    
    function deleteDirectory($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                deleteDirectory($path);
            } else {
                if (file_exists($path)) {
                    unlink($path);
                }
            }
        }
        return rmdir($dir);
    }
    
    foreach ($upload_dirs as $upload_dir) {
        $target_dir = realpath($upload_dir);
        
        if ($target_dir && is_dir($target_dir)) {
            if (deleteDirectory($target_dir)) {
                $deleted_files[] = $upload_dir . " klasörü";
            }
        }
    }
    
    // Veritabanından sil - TÜM İLİŞKİLİ TABLOLAR
    
    // 1. Önce medya yorumlarını sil
    $stmt = $pdo->prepare("SELECT id FROM medyalar WHERE dugun_id = ?");
    $stmt->execute([$event_id]);
    $media_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (!empty($media_ids)) {
        $placeholders = rtrim(str_repeat('?,', count($media_ids)), ',');
        
        // Medya yorumlarını sil
        try {
            $stmt = $pdo->prepare("DELETE FROM yorumlar WHERE medya_id IN ($placeholders)");
            $stmt->execute($media_ids);
        } catch (Exception $e) {
            // Tablo yoksa devam et
        }
        
        // Medya beğenilerini sil
        try {
            $stmt = $pdo->prepare("DELETE FROM medya_begenileri WHERE medya_id IN ($placeholders)");
            $stmt->execute($media_ids);
        } catch (Exception $e) {
            // Tablo yoksa devam et
        }
    }
    
    // 2. Medyaları sil
    $stmt = $pdo->prepare("DELETE FROM medyalar WHERE dugun_id = ?");
    $stmt->execute([$event_id]);
    
    // 3. Katılımcıları sil
    $stmt = $pdo->prepare("DELETE FROM dugun_katilimcilar WHERE dugun_id = ?");
    $stmt->execute([$event_id]);
    
    // 4. Hikayeleri sil (hikayeler tablosu varsa)
    try {
        $stmt = $pdo->prepare("SELECT id FROM hikayeler WHERE dugun_id = ?");
        $stmt->execute([$event_id]);
        $story_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($story_ids)) {
            $placeholders = rtrim(str_repeat('?,', count($story_ids)), ',');
            
            // Hikaye yorumlarını sil
            try {
                $stmt = $pdo->prepare("DELETE FROM hikaye_yorumlari WHERE hikaye_id IN ($placeholders)");
                $stmt->execute($story_ids);
            } catch (Exception $e) {
                // Tablo yoksa devam et
            }
        }
        
        $stmt = $pdo->prepare("DELETE FROM hikayeler WHERE dugun_id = ?");
        $stmt->execute([$event_id]);
    } catch (Exception $e) {
        // Hikayeler tablosu yoksa devam et
    }
    
    // 5. Düğünü sil
    $stmt = $pdo->prepare("DELETE FROM dugunler WHERE id = ?");
    $stmt->execute([$event_id]);
    
    $pdo->commit();
    
    $message = 'Düğün ve tüm verileri başarıyla silindi. Silinen dosyalar: ' . count($deleted_files);
    echo json_encode(['success' => true, 'message' => $message, 'deleted_files' => $deleted_files]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
