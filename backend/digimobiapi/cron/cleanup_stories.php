<?php
/**
 * Hikaye Temizleme Scripti
 * 24 saat (veya daha eski) hikayeleri otomatik siler
 * 
 * Çalıştırma: php cleanup_stories.php
 * Cron: Her saat başı çalıştır (0 * * * *)
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

// ✅ Script'in bulunduğu dizinden parent dizine çık ve database.php'yi bul
$script_dir = __DIR__; // cron klasörü
$api_dir = dirname($script_dir); // digimobiapi klasörü
$config_dir = dirname($api_dir) . DIRECTORY_SEPARATOR . 'config'; // config klasörü
$database_path = $config_dir . DIRECTORY_SEPARATOR . 'database.php';

error_log("Cleanup Stories - Script dir: $script_dir");
error_log("Cleanup Stories - API dir: $api_dir");
error_log("Cleanup Stories - Config dir: $config_dir");
error_log("Cleanup Stories - Database path: $database_path");

if (!file_exists($database_path)) {
    error_log("Cleanup Stories - ERROR: database.php not found at: $database_path");
    die(json_encode(['success' => false, 'error' => 'database.php not found']));
}

// ✅ Database connection'ı require et
require_once $database_path;

// ✅ PDO'yu al (database.php'den global $pdo zaten oluşturulmuş)
global $pdo;
if (!$pdo) {
    $pdo = get_pdo();
}

try {
    // ✅ Web root path'i hesapla (dosya silme için)
    $web_root = dirname($api_dir); // digimobiapi/ -> dijitalsalon.cagapps.app/ (web root)
    error_log("Story Cleanup - Web root: $web_root");
    
    // ✅ 24 saat öncesini hesapla
    $cutoff_time = date('Y-m-d H:i:s', strtotime('-24 hours'));
    
    error_log("Story Cleanup - START");
    error_log("Story Cleanup - Cutoff time: $cutoff_time");
    
    // ✅ 24 saat önce yüklenmiş hikayeleri bul
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            dosya_yolu, 
            kucuk_resim_yolu,
            created_at
        FROM medyalar
        WHERE tur = 'hikaye' 
        AND created_at < ?
        ORDER BY created_at ASC
    ");
    $stmt->execute([$cutoff_time]);
    $old_stories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $deleted_count = 0;
    $failed_count = 0;
    
    if (count($old_stories) > 0) {
        error_log("Story Cleanup - Found " . count($old_stories) . " old stories to delete");
        
        foreach ($old_stories as $story) {
            $story_id = $story['id'];
            $file_path = $story['dosya_yolu'];
            $thumbnail_path = $story['kucuk_resim_yolu'];
            $created_at = $story['created_at'];
            
            error_log("Story Cleanup - Deleting story ID: $story_id, Created: $created_at");
            
            try {
                // ✅ Fiziksel dosyaları sil
                $files_to_delete = [$file_path];
                
                // Thumbnail varsa ekle
                if (!empty($thumbnail_path)) {
                    $files_to_delete[] = $thumbnail_path;
                }
                
                // Preview dosyası varsa (thumbnail yerine preview) ekle
                $preview_path = str_replace('_thumb.', '_preview.', $thumbnail_path);
                if (!empty($preview_path) && $preview_path !== $thumbnail_path) {
                    $files_to_delete[] = $preview_path;
                }
                
                foreach ($files_to_delete as $file) {
                    if (!empty($file)) {
                        // ✅ Full path oluştur
                        $full_path = $web_root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $file);
                        
                        error_log("Story Cleanup - Attempting to delete: $full_path");
                        
                        if (file_exists($full_path)) {
                            if (@unlink($full_path)) {
                                error_log("Story Cleanup - ✅ Deleted file: $full_path");
                            } else {
                                error_log("Story Cleanup - ❌ Failed to delete file: $full_path");
                            }
                        } else {
                            error_log("Story Cleanup - ⚠️ File not found: $full_path");
                        }
                    }
                }
                
                // ✅ Veritabanından sil
                $delete_stmt = $pdo->prepare("DELETE FROM medyalar WHERE id = ?");
                $delete_stmt->execute([$story_id]);
                
                $deleted_count++;
                error_log("Story Cleanup - Successfully deleted story ID: $story_id");
                
            } catch (Exception $e) {
                $failed_count++;
                error_log("Story Cleanup - Error deleting story ID $story_id: " . $e->getMessage());
            }
        }
        
        error_log("Story Cleanup - COMPLETED: $deleted_count deleted, $failed_count failed");
        
        // ✅ Sonuç mesajı
        echo json_encode([
            'success' => true,
            'deleted' => $deleted_count,
            'failed' => $failed_count,
            'message' => "$deleted_count hikaye silindi, $failed_count başarısız"
        ]);
        
    } else {
        error_log("Story Cleanup - No old stories found");
        echo json_encode([
            'success' => true,
            'deleted' => 0,
            'message' => 'Silinecek eski hikaye bulunamadı'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Story Cleanup - Database error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}

