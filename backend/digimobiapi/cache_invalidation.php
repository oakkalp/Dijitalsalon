<?php
/**
 * ✅ Cache Invalidation Helper
 * Yeni medya/bildirim eklendiğinde cache'i temizlemek için
 */

require_once __DIR__ . '/cache_helper.php';

/**
 * Events cache'ini temizle
 */
function clear_events_cache($user_id = null) {
    if ($user_id) {
        // Belirli kullanıcı için cache'i temizle
        QueryCache::clear("events_user_{$user_id}");
    } else {
        // Tüm events cache'ini temizle
        QueryCache::clear("events_");
    }
}

/**
 * Media cache'ini temizle
 */
function clear_media_cache($event_id = null, $user_id = null) {
    // ✅ TÜM media cache'ini temizle (güvenli tarafta olmak için)
    // Cache key'leri MD5 hash ile oluşturulduğu için pattern matching yapamıyoruz
    // Bu yüzden tüm cache'leri temizliyoruz (medya upload çok sık olmaz)
    $cache_dir = __DIR__ . '/../cache/query_cache/';
    if (is_dir($cache_dir)) {
        $files = glob($cache_dir . '*.cache');
        $cleared = 0;
        foreach ($files as $file) {
            try {
                $data = @unserialize(file_get_contents($file));
                if ($data && isset($data['result'])) {
                    $result = $data['result'];
                    
                    // ✅ Cache'deki verilerde event_id kontrolü yap
                    if (is_array($result)) {
                        // Sonuç bir array ise (genellikle media listesi)
                        foreach ($result as $item) {
                            if (is_array($item) && isset($item['event_id']) && 
                                ($event_id === null || $item['event_id'] == $event_id)) {
                                @unlink($file);
                                $cleared++;
                                break 2; // İç döngüden çık, dış döngüye devam et
                            }
                        }
                    }
                }
            } catch (Exception $e) {
                // Hata olursa bile devam et
                error_log("Cache clear error: " . $e->getMessage());
            }
        }
        
        // ✅ Eğer event_id belirtilmişse ve hiç cache temizlenmediyse, tüm cache'i temizle
        if ($event_id && $cleared == 0) {
            QueryCache::clear();
            error_log("clear_media_cache - Cleared all cache for event_id: $event_id");
        } else if ($cleared > 0) {
            error_log("clear_media_cache - Cleared $cleared cache files for event_id: $event_id");
        }
    } else {
        // Cache dizini yoksa, tüm cache'i temizle (güvenli)
        QueryCache::clear();
    }
}

/**
 * Notifications cache'ini temizle
 */
function clear_notifications_cache($user_id = null) {
    if ($user_id) {
        // Belirli kullanıcı için cache'i temizle
        QueryCache::clear("notifications_user_{$user_id}");
    } else {
        // Tüm notifications cache'ini temizle
        QueryCache::clear("notifications_");
    }
}

/**
 * Profile stats cache'ini temizle
 */
function clear_profile_cache($user_id = null) {
    if ($user_id) {
        QueryCache::clear("profile_stats_{$user_id}");
    } else {
        QueryCache::clear("profile_stats_");
    }
}

