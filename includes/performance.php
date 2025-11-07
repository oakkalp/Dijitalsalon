<?php
/**
 * Performance Optimization Helper
 * Digital Salon - Performans optimizasyonu fonksiyonları
 */

// Database connection pooling
class DatabasePool {
    private static $connections = [];
    private static $maxConnections = 10;
    
    public static function getConnection($config) {
        $key = md5(serialize($config));
        
        if (!isset(self::$connections[$key]) || count(self::$connections[$key]) === 0) {
            self::$connections[$key] = [];
        }
        
        if (count(self::$connections[$key]) > 0) {
            return array_pop(self::$connections[$key]);
        }
        
        try {
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset=utf8mb4";
            $pdo = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ]);
            
            return $pdo;
        } catch (PDOException $e) {
            error_log("Database connection error: " . $e->getMessage());
            throw new Exception("Veritabanı bağlantı hatası");
        }
    }
    
    public static function releaseConnection($pdo, $config) {
        $key = md5(serialize($config));
        
        if (!isset(self::$connections[$key])) {
            self::$connections[$key] = [];
        }
        
        if (count(self::$connections[$key]) < self::$maxConnections) {
            self::$connections[$key][] = $pdo;
        }
    }
}

// Query optimization
class QueryOptimizer {
    private $pdo;
    private $cache;
    
    public function __construct($pdo, $cache = null) {
        $this->pdo = $pdo;
        $this->cache = $cache;
    }
    
    public function getEventsWithParticipants($eventId, $useCache = true) {
        $cacheKey = "event_participants_{$eventId}";
        
        if ($useCache && $this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) {
                return json_decode($cached, true);
            }
        }
        
        $query = "
            SELECT 
                d.*,
                COUNT(DISTINCT dk.kullanici_id) as participant_count,
                COUNT(DISTINCT m.id) as media_count,
                GROUP_CONCAT(DISTINCT k.ad, ' ', k.soyad SEPARATOR ', ') as participant_names
            FROM dugunler d
            LEFT JOIN dugun_katilimcilar dk ON d.id = dk.dugun_id
            LEFT JOIN medyalar m ON d.id = m.dugun_id
            LEFT JOIN kullanicilar k ON dk.kullanici_id = k.id
            WHERE d.id = ?
            GROUP BY d.id
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$eventId]);
        $result = $stmt->fetch();
        
        if ($useCache && $this->cache && $result) {
            $this->cache->set($cacheKey, json_encode($result), 300); // 5 dakika cache
        }
        
        return $result;
    }
    
    public function getMediaFeed($eventId, $limit = 20, $offset = 0, $useCache = true) {
        $cacheKey = "media_feed_{$eventId}_{$limit}_{$offset}";
        
        if ($useCache && $this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) {
                return json_decode($cached, true);
            }
        }
        
        $query = "
            SELECT 
                m.*,
                k.ad,
                k.soyad,
                k.profil_fotografi,
                COUNT(DISTINCT b.id) as like_count,
                COUNT(DISTINCT y.id) as comment_count,
                (SELECT COUNT(*) FROM begeniler b2 WHERE b2.medya_id = m.id AND b2.kullanici_id = ?) as user_liked
            FROM medyalar m
            JOIN kullanicilar k ON m.kullanici_id = k.id
            LEFT JOIN begeniler b ON m.id = b.medya_id
            LEFT JOIN yorumlar y ON m.id = y.medya_id
            WHERE m.dugun_id = ? AND m.tur != 'hikaye'
            GROUP BY m.id
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$_SESSION['user_id'] ?? 0, $eventId, $limit, $offset]);
        $results = $stmt->fetchAll();
        
        if ($useCache && $this->cache) {
            $this->cache->set($cacheKey, json_encode($results), 60); // 1 dakika cache
        }
        
        return $results;
    }
    
    public function getUserStats($userId, $useCache = true) {
        $cacheKey = "user_stats_{$userId}";
        
        if ($useCache && $this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) {
                return json_decode($cached, true);
            }
        }
        
        $query = "
            SELECT 
                COUNT(DISTINCT dk.dugun_id) as participated_events,
                COUNT(DISTINCT m.id) as shared_media,
                COUNT(DISTINCT b.id) as total_likes_given,
                COUNT(DISTINCT b2.id) as total_likes_received,
                COUNT(DISTINCT y.id) as total_comments
            FROM kullanicilar k
            LEFT JOIN dugun_katilimcilar dk ON k.id = dk.kullanici_id
            LEFT JOIN medyalar m ON k.id = m.kullanici_id
            LEFT JOIN begeniler b ON k.id = b.kullanici_id
            LEFT JOIN begeniler b2 ON m.id = b2.medya_id
            LEFT JOIN yorumlar y ON k.id = y.kullanici_id
            WHERE k.id = ?
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$userId]);
        $result = $stmt->fetch();
        
        if ($useCache && $this->cache) {
            $this->cache->set($cacheKey, json_encode($result), 600); // 10 dakika cache
        }
        
        return $result;
    }
}

// Image optimization
class ImageOptimizer {
    private $quality;
    private $maxWidth;
    private $maxHeight;
    
    public function __construct($quality = 85, $maxWidth = 1920, $maxHeight = 1080) {
        $this->quality = $quality;
        $this->maxWidth = $maxWidth;
        $this->maxHeight = $maxHeight;
    }
    
    public function optimizeImage($sourcePath, $destinationPath) {
        $imageInfo = getimagesize($sourcePath);
        
        if ($imageInfo === false) {
            return false;
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        // Resize if necessary
        if ($width > $this->maxWidth || $height > $this->maxHeight) {
            $ratio = min($this->maxWidth / $width, $this->maxHeight / $height);
            $newWidth = intval($width * $ratio);
            $newHeight = intval($height * $ratio);
        } else {
            $newWidth = $width;
            $newHeight = $height;
        }
        
        // Create source image
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }
        
        if ($sourceImage === false) {
            return false;
        }
        
        // Create destination image
        $destImage = imagecreatetruecolor($newWidth, $newHeight);
        
        // Preserve transparency for PNG and GIF
        if ($mimeType === 'image/png' || $mimeType === 'image/gif') {
            imagealphablending($destImage, false);
            imagesavealpha($destImage, true);
            $transparent = imagecolorallocatealpha($destImage, 255, 255, 255, 127);
            imagefilledrectangle($destImage, 0, 0, $newWidth, $newHeight, $transparent);
        }
        
        // Resize
        imagecopyresampled($destImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save optimized image
        $result = imagejpeg($destImage, $destinationPath, $this->quality);
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($destImage);
        
        return $result;
    }
    
    public function generateThumbnail($sourcePath, $destinationPath, $thumbWidth = 300, $thumbHeight = 300) {
        $imageInfo = getimagesize($sourcePath);
        
        if ($imageInfo === false) {
            return false;
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        $mimeType = $imageInfo['mime'];
        
        // Calculate thumbnail dimensions maintaining aspect ratio
        $ratio = min($thumbWidth / $width, $thumbHeight / $height);
        $newWidth = intval($width * $ratio);
        $newHeight = intval($height * $ratio);
        
        // Create source image
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            case 'image/webp':
                $sourceImage = imagecreatefromwebp($sourcePath);
                break;
            default:
                return false;
        }
        
        if ($sourceImage === false) {
            return false;
        }
        
        // Create thumbnail
        $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
        
        // Fill with white background
        $white = imagecolorallocate($thumbImage, 255, 255, 255);
        imagefill($thumbImage, 0, 0, $white);
        
        // Center the thumbnail
        $x = ($thumbWidth - $newWidth) / 2;
        $y = ($thumbHeight - $newHeight) / 2;
        
        imagecopyresampled($thumbImage, $sourceImage, $x, $y, 0, 0, $newWidth, $newHeight, $width, $height);
        
        // Save thumbnail
        $result = imagejpeg($thumbImage, $destinationPath, 90);
        
        // Clean up
        imagedestroy($sourceImage);
        imagedestroy($thumbImage);
        
        return $result;
    }
}

// Lazy loading helper
class LazyLoader {
    private $pdo;
    private $cache;
    
    public function __construct($pdo, $cache = null) {
        $this->pdo = $pdo;
        $this->cache = $cache;
    }
    
    public function loadComments($mediaId, $limit = 10, $offset = 0) {
        $cacheKey = "comments_{$mediaId}_{$limit}_{$offset}";
        
        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) {
                return json_decode($cached, true);
            }
        }
        
        $query = "
            SELECT 
                y.*,
                k.ad,
                k.soyad,
                k.profil_fotografi
            FROM yorumlar y
            JOIN kullanicilar k ON y.kullanici_id = k.id
            WHERE y.medya_id = ?
            ORDER BY y.olusturma_tarihi ASC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$mediaId, $limit, $offset]);
        $results = $stmt->fetchAll();
        
        if ($this->cache) {
            $this->cache->set($cacheKey, json_encode($results), 300);
        }
        
        return $results;
    }
    
    public function loadLikes($mediaId, $limit = 20, $offset = 0) {
        $cacheKey = "likes_{$mediaId}_{$limit}_{$offset}";
        
        if ($this->cache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== false) {
                return json_decode($cached, true);
            }
        }
        
        $query = "
            SELECT 
                b.*,
                k.ad,
                k.soyad,
                k.profil_fotografi
            FROM begeniler b
            JOIN kullanicilar k ON b.kullanici_id = k.id
            WHERE b.medya_id = ?
            ORDER BY b.olusturma_tarihi DESC
            LIMIT ? OFFSET ?
        ";
        
        $stmt = $this->pdo->prepare($query);
        $stmt->execute([$mediaId, $limit, $offset]);
        $results = $stmt->fetchAll();
        
        if ($this->cache) {
            $this->cache->set($cacheKey, json_encode($results), 300);
        }
        
        return $results;
    }
}

// Background job processor
class BackgroundJobProcessor {
    private $pdo;
    private $redis;
    
    public function __construct($pdo, $redis) {
        $this->pdo = $pdo;
        $this->redis = $redis;
    }
    
    public function addJob($jobType, $data, $priority = 'normal') {
        $job = [
            'id' => uniqid(),
            'type' => $jobType,
            'data' => $data,
            'priority' => $priority,
            'created_at' => time(),
            'attempts' => 0,
            'max_attempts' => 3
        ];
        
        $queue = $priority === 'high' ? 'high_priority_jobs' : 'jobs';
        $this->redis->lpush($queue, json_encode($job));
        
        return $job['id'];
    }
    
    public function processJobs() {
        $queues = ['high_priority_jobs', 'jobs'];
        
        foreach ($queues as $queue) {
            while ($jobData = $this->redis->rpop($queue)) {
                $job = json_decode($jobData, true);
                
                if ($this->processJob($job)) {
                    // Job completed successfully
                    $this->logJobCompletion($job);
                } else {
                    // Job failed, retry or move to failed queue
                    $job['attempts']++;
                    
                    if ($job['attempts'] < $job['max_attempts']) {
                        $this->redis->lpush($queue, json_encode($job));
                    } else {
                        $this->redis->lpush('failed_jobs', json_encode($job));
                    }
                }
            }
        }
    }
    
    private function processJob($job) {
        try {
            switch ($job['type']) {
                case 'optimize_image':
                    return $this->optimizeImageJob($job['data']);
                case 'send_notification':
                    return $this->sendNotificationJob($job['data']);
                case 'cleanup_expired_stories':
                    return $this->cleanupExpiredStoriesJob($job['data']);
                case 'generate_thumbnails':
                    return $this->generateThumbnailsJob($job['data']);
                default:
                    error_log("Unknown job type: " . $job['type']);
                    return false;
            }
        } catch (Exception $e) {
            error_log("Job processing error: " . $e->getMessage());
            return false;
        }
    }
    
    private function optimizeImageJob($data) {
        $optimizer = new ImageOptimizer();
        return $optimizer->optimizeImage($data['source_path'], $data['destination_path']);
    }
    
    private function sendNotificationJob($data) {
        // Implement notification sending logic
        return true;
    }
    
    private function cleanupExpiredStoriesJob($data) {
        $stmt = $this->pdo->prepare("DELETE FROM medyalar WHERE tur = 'hikaye' AND hikaye_bitis_tarihi < NOW()");
        return $stmt->execute();
    }
    
    private function generateThumbnailsJob($data) {
        $optimizer = new ImageOptimizer();
        return $optimizer->generateThumbnail($data['source_path'], $data['thumbnail_path']);
    }
    
    private function logJobCompletion($job) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO job_logs (job_id, job_type, status, completed_at)
                VALUES (?, ?, 'completed', NOW())
            ");
            $stmt->execute([$job['id'], $job['type']]);
        } catch (Exception $e) {
            error_log("Job logging error: " . $e->getMessage());
        }
    }
}

// CDN helper
class CDNHelper {
    private $cdnUrl;
    private $localPath;
    
    public function __construct($cdnUrl = '', $localPath = '') {
        $this->cdnUrl = $cdnUrl;
        $this->localPath = $localPath;
    }
    
    public function getImageUrl($path, $size = 'original') {
        if (empty($this->cdnUrl)) {
            return $path;
        }
        
        $filename = basename($path);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
        
        if ($size !== 'original') {
            $filename = $nameWithoutExt . '_' . $size . '.' . $extension;
        }
        
        return $this->cdnUrl . '/' . $filename;
    }
    
    public function uploadToCDN($localPath, $remotePath) {
        // Implement CDN upload logic
        // This would typically use AWS S3, Cloudinary, or similar service
        return true;
    }
}

// Memory optimization
class MemoryOptimizer {
    public static function optimizeMemoryUsage() {
        // Clear unnecessary variables
        if (isset($GLOBALS['unused_vars'])) {
            unset($GLOBALS['unused_vars']);
        }
        
        // Force garbage collection
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }
        
        // Clear opcache if available
        if (function_exists('opcache_reset')) {
            opcache_reset();
        }
    }
    
    public static function getMemoryUsage() {
        return [
            'current' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'limit' => ini_get('memory_limit')
        ];
    }
}

// Database query analyzer
class QueryAnalyzer {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function analyzeQuery($query) {
        $explainQuery = "EXPLAIN " . $query;
        
        try {
            $stmt = $this->pdo->prepare($explainQuery);
            $stmt->execute();
            $result = $stmt->fetchAll();
            
            $analysis = [
                'query' => $query,
                'explain' => $result,
                'suggestions' => $this->generateSuggestions($result)
            ];
            
            return $analysis;
        } catch (Exception $e) {
            error_log("Query analysis error: " . $e->getMessage());
            return false;
        }
    }
    
    private function generateSuggestions($explain) {
        $suggestions = [];
        
        foreach ($explain as $row) {
            if ($row['type'] === 'ALL') {
                $suggestions[] = "Full table scan detected on {$row['table']}. Consider adding an index.";
            }
            
            if ($row['Extra'] && strpos($row['Extra'], 'Using filesort') !== false) {
                $suggestions[] = "Filesort detected. Consider optimizing ORDER BY clause.";
            }
            
            if ($row['Extra'] && strpos($row['Extra'], 'Using temporary') !== false) {
                $suggestions[] = "Temporary table created. Consider optimizing GROUP BY or ORDER BY.";
            }
        }
        
        return $suggestions;
    }
}

// Performance monitoring
class PerformanceMonitor {
    private $startTime;
    private $startMemory;
    private $checkpoints = [];
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage();
    }
    
    public function checkpoint($name) {
        $this->checkpoints[$name] = [
            'time' => microtime(true) - $this->startTime,
            'memory' => memory_get_usage() - $this->startMemory
        ];
    }
    
    public function getReport() {
        $report = [
            'total_time' => microtime(true) - $this->startTime,
            'total_memory' => memory_get_usage() - $this->startMemory,
            'peak_memory' => memory_get_peak_usage(),
            'checkpoints' => $this->checkpoints
        ];
        
        return $report;
    }
    
    public function logReport($endpoint = '') {
        $report = $this->getReport();
        $report['endpoint'] = $endpoint;
        $report['timestamp'] = date('Y-m-d H:i:s');
        
        error_log("Performance Report: " . json_encode($report));
    }
}

// Initialize performance monitoring
if (!defined('PERFORMANCE_INITIALIZED')) {
    define('PERFORMANCE_INITIALIZED', true);
    
    // Start performance monitoring
    $GLOBALS['performance_monitor'] = new PerformanceMonitor();
}
?>
