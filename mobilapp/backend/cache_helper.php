R<?php
/**
 * ✅ Cache Helper - Query Result Caching
 * Dosya tabanlı cache sistemi ile API performansını optimize eder
 * 
 * Features:
 * - TTL (Time To Live) desteği
 * - Cache temizleme fonksiyonları
 * - Otomatik cache invalidation
 */

// Cache dizini yolu
define('CACHE_DIR', __DIR__ . '/cache/query_cache/');

/**
 * Cache dizinini oluştur
 */
function ensureCacheDir() {
    if (!file_exists(CACHE_DIR)) {
        mkdir(CACHE_DIR, 0755, true);
    }
}

/**
 * Cache anahtarı oluştur
 * 
 * @param string $key Cache anahtarı
 * @return string Cache dosya yolu
 */
function getCacheKey($key) {
    ensureCacheDir();
    return CACHE_DIR . md5($key) . '.cache';
}

/**
 * Cache'den veri oku
 * 
 * @param string $key Cache anahtarı
 * @param int $ttl Time To Live (saniye cinsinden)
 * @return mixed|null Cache'den okunan veri veya null
 */
function getCache($key, $ttl = 300) {
    $cacheFile = getCacheKey($key);
    
    if (!file_exists($cacheFile)) {
        return null;
    }
    
    // Cache dosyasının yaşı kontrol et
    $fileTime = filemtime($cacheFile);
    $currentTime = time();
    
    if (($currentTime - $fileTime) > $ttl) {
        // Cache süresi dolmuş, dosyayı sil
        @unlink($cacheFile);
        return null;
    }
    
    // Cache dosyasını oku
    $content = file_get_contents($cacheFile);
    if ($content === false) {
        return null;
    }
    
    // JSON decode et
    $data = json_decode($content, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        // JSON decode hatası, cache dosyasını sil
        @unlink($cacheFile);
        return null;
    }
    
    return $data;
}

/**
 * Cache'e veri yaz
 * 
 * @param string $key Cache anahtarı
 * @param mixed $data Cache'lenecek veri
 * @return bool Başarılı ise true
 */
function setCache($key, $data) {
    $cacheFile = getCacheKey($key);
    
    ensureCacheDir();
    
    // JSON encode et
    $content = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($content === false) {
        return false;
    }
    
    // Cache dosyasına yaz
    $result = file_put_contents($cacheFile, $content, LOCK_EX);
    
    return $result !== false;
}

/**
 * Cache'i temizle
 * 
 * @param string $key Cache anahtarı (opsiyonel, belirtilmezse tüm cache temizlenir)
 * @return bool Başarılı ise true
 */
function clearCache($key = null) {
    if ($key !== null) {
        // Belirli bir cache'i temizle
        $cacheFile = getCacheKey($key);
        if (file_exists($cacheFile)) {
            return @unlink($cacheFile);
        }
        return true;
    } else {
        // Tüm cache'i temizle
        ensureCacheDir();
        $files = glob(CACHE_DIR . '*.cache');
        $success = true;
        foreach ($files as $file) {
            if (is_file($file)) {
                if (!@unlink($file)) {
                    $success = false;
                }
            }
        }
        return $success;
    }
}

/**
 * Events cache'ini temizle
 */
function clearEventsCache() {
    $patterns = [
        'events_*',
        'event_*',
    ];
    
    ensureCacheDir();
    $deleted = 0;
    
    foreach ($patterns as $pattern) {
        $files = glob(CACHE_DIR . $pattern . '.cache');
        foreach ($files as $file) {
            if (is_file($file)) {
                @unlink($file);
                $deleted++;
            }
        }
    }
    
    return $deleted;
}

/**
 * Media cache'ini temizle
 */
function clearMediaCache($eventId = null) {
    ensureCacheDir();
    
    if ($eventId !== null) {
        // Belirli bir event'in media cache'ini temizle
        $pattern = 'media_' . $eventId . '_*';
        $files = glob(CACHE_DIR . $pattern . '.cache');
    } else {
        // Tüm media cache'ini temizle
        $files = glob(CACHE_DIR . 'media_*.cache');
    }
    
    $deleted = 0;
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
            $deleted++;
        }
    }
    
    return $deleted;
}

/**
 * Notifications cache'ini temizle
 */
function clearNotificationsCache($userId = null) {
    ensureCacheDir();
    
    if ($userId !== null) {
        // Belirli bir kullanıcının notification cache'ini temizle
        $pattern = 'notifications_' . $userId . '_*';
        $files = glob(CACHE_DIR . $pattern . '.cache');
    } else {
        // Tüm notifications cache'ini temizle
        $files = glob(CACHE_DIR . 'notifications_*.cache');
    }
    
    $deleted = 0;
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
            $deleted++;
        }
    }
    
    return $deleted;
}

/**
 * Profile stats cache'ini temizle
 */
function clearProfileCache($userId = null) {
    ensureCacheDir();
    
    if ($userId !== null) {
        // Belirli bir kullanıcının profile cache'ini temizle
        $pattern = 'profile_' . $userId . '_*';
        $files = glob(CACHE_DIR . $pattern . '.cache');
    } else {
        // Tüm profile cache'ini temizle
        $files = glob(CACHE_DIR . 'profile_*.cache');
    }
    
    $deleted = 0;
    foreach ($files as $file) {
        if (is_file($file)) {
            @unlink($file);
            $deleted++;
        }
    }
    
    return $deleted;
}

/**
 * Eski cache dosyalarını temizle (cron job için)
 * 
 * @param int $maxAge Maksimum yaş (saniye cinsinden)
 * @return int Silinen dosya sayısı
 */
function cleanOldCache($maxAge = 86400) { // 24 saat
    ensureCacheDir();
    $files = glob(CACHE_DIR . '*.cache');
    $deleted = 0;
    $currentTime = time();
    
    foreach ($files as $file) {
        if (is_file($file)) {
            $fileTime = filemtime($file);
            if (($currentTime - $fileTime) > $maxAge) {
                @unlink($file);
                $deleted++;
            }
        }
    }
    
    return $deleted;
}

