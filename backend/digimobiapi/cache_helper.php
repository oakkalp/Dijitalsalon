<?php
/**
 * ✅ Query Result Caching Helper
 * Sık kullanılan sorguları cache'ler (dosya tabanlı)
 * Redis yoksa bu dosya cache kullanılır
 */

class QueryCache {
    private static $cache_dir = __DIR__ . '/cache/query_cache/';
    private static $default_ttl = 300; // 5 dakika
    
    /**
     * Cache dizinini oluştur
     */
    private static function ensureCacheDir() {
        if (!is_dir(self::$cache_dir)) {
            mkdir(self::$cache_dir, 0755, true);
        }
    }
    
    /**
     * Cache key oluştur
     */
    private static function getCacheKey($query, $params = []) {
        return md5($query . serialize($params));
    }
    
    /**
     * Cache'den veri al
     */
    public static function get($query, $params = []) {
        self::ensureCacheDir();
        
        $cache_key = self::getCacheKey($query, $params);
        $cache_file = self::$cache_dir . $cache_key . '.cache';
        
        if (file_exists($cache_file)) {
            $data = unserialize(file_get_contents($cache_file));
            
            // TTL kontrolü
            if ($data['expires'] > time()) {
                return $data['result'];
            } else {
                // Süresi dolmuş, dosyayı sil
                unlink($cache_file);
            }
        }
        
        return null;
    }
    
    /**
     * Cache'e veri kaydet
     */
    public static function set($query, $params = [], $result, $ttl = null) {
        self::ensureCacheDir();
        
        $cache_key = self::getCacheKey($query, $params);
        $cache_file = self::$cache_dir . $cache_key . '.cache';
        
        $ttl = $ttl ?? self::$default_ttl;
        $data = [
            'result' => $result,
            'expires' => time() + $ttl,
            'created' => time()
        ];
        
        file_put_contents($cache_file, serialize($data), LOCK_EX);
    }
    
    /**
     * Cache'i temizle
     */
    public static function clear($pattern = null) {
        self::ensureCacheDir();
        
        if ($pattern === null || $pattern === '') {
            // Tüm cache'i temizle
            $files = glob(self::$cache_dir . '*.cache');
            $cleared = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                    $cleared++;
                }
            }
            error_log("QueryCache::clear() - Cleared $cleared cache files");
            return $cleared;
        } else {
            // Pattern ile temizle
            $files = glob(self::$cache_dir . $pattern . '*.cache');
            $cleared = 0;
            foreach ($files as $file) {
                if (is_file($file)) {
                    @unlink($file);
                    $cleared++;
                }
            }
            error_log("QueryCache::clear('$pattern') - Cleared $cleared cache files");
            return $cleared;
        }
    }
    
    /**
     * Süresi dolmuş cache'leri temizle
     */
    public static function cleanup() {
        self::ensureCacheDir();
        
        $files = glob(self::$cache_dir . '*.cache');
        $cleaned = 0;
        
        foreach ($files as $file) {
            $data = unserialize(file_get_contents($file));
            if ($data['expires'] <= time()) {
                unlink($file);
                $cleaned++;
            }
        }
        
        return $cleaned;
    }
}

