<?php
/**
 * Security Helper Functions
 * Digital Salon - Güvenlik fonksiyonları
 */

// CSRF Token oluşturma ve doğrulama
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// XSS koruması
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

function sanitizeOutput($output) {
    return htmlspecialchars($output, ENT_QUOTES, 'UTF-8');
}

// SQL Injection koruması için prepared statement wrapper
function secureQuery($pdo, $query, $params = []) {
    try {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("SQL Error: " . $e->getMessage());
        throw new Exception("Veritabanı hatası oluştu.");
    }
}

// Rate limiting
class RateLimiter {
    private $redis;
    private $maxAttempts;
    private $timeWindow;
    
    public function __construct($redis, $maxAttempts = 5, $timeWindow = 300) {
        $this->redis = $redis;
        $this->maxAttempts = $maxAttempts;
        $this->timeWindow = $timeWindow;
    }
    
    public function isAllowed($key) {
        $current = $this->redis->get($key);
        if ($current === false) {
            $this->redis->setex($key, $this->timeWindow, 1);
            return true;
        }
        
        if ($current >= $this->maxAttempts) {
            return false;
        }
        
        $this->redis->incr($key);
        return true;
    }
    
    public function getRemainingTime($key) {
        return $this->redis->ttl($key);
    }
}

// File upload güvenliği
class SecureFileUpload {
    private $allowedTypes = [
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
        'video/mp4',
        'video/webm',
        'video/quicktime'
    ];
    
    private $maxFileSize = 50 * 1024 * 1024; // 50MB
    private $uploadPath;
    
    public function __construct($uploadPath) {
        $this->uploadPath = $uploadPath;
    }
    
    public function validateFile($file) {
        $errors = [];
        
        // Dosya boyutu kontrolü
        if ($file['size'] > $this->maxFileSize) {
            $errors[] = "Dosya boyutu çok büyük. Maksimum 50MB olabilir.";
        }
        
        // Dosya türü kontrolü
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes)) {
            $errors[] = "Geçersiz dosya türü.";
        }
        
        // Dosya uzantısı kontrolü
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm', 'mov'];
        
        if (!in_array($extension, $allowedExtensions)) {
            $errors[] = "Geçersiz dosya uzantısı.";
        }
        
        // Dosya içeriği kontrolü (basit)
        if (strpos($mimeType, 'image/') === 0) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                $errors[] = "Geçersiz resim dosyası.";
            }
        }
        
        return $errors;
    }
    
    public function generateSecureFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $filename = uniqid() . '_' . time() . '.' . $extension;
        return $filename;
    }
    
    public function uploadFile($file, $subfolder = '') {
        $errors = $this->validateFile($file);
        if (!empty($errors)) {
            throw new Exception(implode(' ', $errors));
        }
        
        $filename = $this->generateSecureFilename($file['name']);
        $uploadDir = $this->uploadPath . ($subfolder ? '/' . $subfolder : '');
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $filePath = $uploadDir . '/' . $filename;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception("Dosya yüklenirken hata oluştu.");
        }
        
        return $filePath;
    }
}

// Password güvenliği
class PasswordSecurity {
    public static function hashPassword($password) {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536, // 64 MB
            'time_cost' => 4,       // 4 iterations
            'threads' => 3,         // 3 threads
        ]);
    }
    
    public static function verifyPassword($password, $hash) {
        return password_verify($password, $hash);
    }
    
    public static function generateSecurePassword($length = 12) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        return substr(str_shuffle($chars), 0, $length);
    }
}

// Session güvenliği
class SessionSecurity {
    public static function startSecureSession() {
        if (session_status() === PHP_SESSION_NONE) {
            // Session ayarları
            ini_set('session.cookie_httponly', 1);
            ini_set('session.cookie_secure', 1);
            ini_set('session.use_strict_mode', 1);
            ini_set('session.cookie_samesite', 'Strict');
            
            session_start();
            
            // Session hijacking koruması
            if (!isset($_SESSION['user_agent'])) {
                $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'];
            } elseif ($_SESSION['user_agent'] !== $_SERVER['HTTP_USER_AGENT']) {
                session_destroy();
                session_start();
            }
            
            // Session regeneration
            if (!isset($_SESSION['last_regeneration'])) {
                $_SESSION['last_regeneration'] = time();
            } elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 dakika
                session_regenerate_id(true);
                $_SESSION['last_regeneration'] = time();
            }
        }
    }
    
    public static function destroySession() {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_unset();
            session_destroy();
        }
    }
}

// Input validation
class InputValidator {
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    public static function validatePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return strlen($phone) >= 10 && strlen($phone) <= 15;
    }
    
    public static function validateDate($date, $format = 'Y-m-d') {
        $d = DateTime::createFromFormat($format, $date);
        return $d && $d->format($format) === $date;
    }
    
    public static function validatePassword($password) {
        // En az 8 karakter, büyük harf, küçük harf, rakam içermeli
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password);
    }
    
    public static function sanitizeString($string, $maxLength = 255) {
        $string = trim($string);
        $string = substr($string, 0, $maxLength);
        return $string;
    }
}

// API güvenliği
class APISecurity {
    private $pdo;
    private $rateLimiter;
    
    public function __construct($pdo, $rateLimiter) {
        $this->pdo = $pdo;
        $this->rateLimiter = $rateLimiter;
    }
    
    public function validateAPIRequest($endpoint, $method = 'GET') {
        // Rate limiting
        $clientIP = $_SERVER['REMOTE_ADDR'];
        $rateLimitKey = "api_rate_limit_{$clientIP}_{$endpoint}";
        
        if (!$this->rateLimiter->isAllowed($rateLimitKey)) {
            http_response_code(429);
            return [
                'success' => false,
                'message' => 'Çok fazla istek gönderildi. Lütfen bekleyin.',
                'retry_after' => $this->rateLimiter->getRemainingTime($rateLimitKey)
            ];
        }
        
        // CSRF token kontrolü (POST, PUT, DELETE için)
        if (in_array($method, ['POST', 'PUT', 'DELETE'])) {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_POST['csrf_token'] ?? '';
            if (!validateCSRFToken($token)) {
                http_response_code(403);
                return [
                    'success' => false,
                    'message' => 'Geçersiz CSRF token.'
                ];
            }
        }
        
        return ['success' => true];
    }
    
    public function logAPIAccess($endpoint, $user_id, $ip, $method) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO api_logs (endpoint, user_id, ip_address, method, timestamp)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$endpoint, $user_id, $ip, $method]);
        } catch (Exception $e) {
            error_log("API log error: " . $e->getMessage());
        }
    }
}

// Database güvenliği
class DatabaseSecurity {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function createIndexes() {
        $indexes = [
            "CREATE INDEX IF NOT EXISTS idx_kullanicilar_email ON kullanicilar(email)",
            "CREATE INDEX IF NOT EXISTS idx_kullanicilar_rol ON kullanicilar(rol)",
            "CREATE INDEX IF NOT EXISTS idx_dugunler_moderator ON dugunler(moderator_id)",
            "CREATE INDEX IF NOT EXISTS idx_dugunler_tarih ON dugunler(dugun_tarihi)",
            "CREATE INDEX IF NOT EXISTS idx_medyalar_dugun ON medyalar(dugun_id)",
            "CREATE INDEX IF NOT EXISTS idx_medyalar_kullanici ON medyalar(kullanici_id)",
            "CREATE INDEX IF NOT EXISTS idx_begeniler_medya ON begeniler(medya_id)",
            "CREATE INDEX IF NOT EXISTS idx_yorumlar_medya ON yorumlar(medya_id)",
            "CREATE INDEX IF NOT EXISTS idx_katilimcilar_dugun ON dugun_katilimcilar(dugun_id)",
            "CREATE INDEX IF NOT EXISTS idx_katilimcilar_kullanici ON dugun_katilimcilar(kullanici_id)",
            "CREATE INDEX IF NOT EXISTS idx_komisyonlar_moderator ON komisyonlar(moderator_id)",
            "CREATE INDEX IF NOT EXISTS idx_odemeler_moderator ON odemeler(moderator_id)"
        ];
        
        foreach ($indexes as $index) {
            try {
                $this->pdo->exec($index);
            } catch (PDOException $e) {
                error_log("Index creation error: " . $e->getMessage());
            }
        }
    }
    
    public function optimizeTables() {
        $tables = [
            'kullanicilar', 'dugunler', 'medyalar', 'begeniler', 'yorumlar',
            'dugun_katilimcilar', 'engellenen_kullanicilar', 'komisyonlar', 'odemeler'
        ];
        
        foreach ($tables as $table) {
            try {
                $this->pdo->exec("OPTIMIZE TABLE {$table}");
            } catch (PDOException $e) {
                error_log("Table optimization error: " . $e->getMessage());
            }
        }
    }
}

// Error handling
class ErrorHandler {
    public static function handleError($errno, $errstr, $errfile, $errline) {
        $error = "Error [{$errno}]: {$errstr} in {$errfile} on line {$errline}";
        error_log($error);
        
        if (ini_get('display_errors')) {
            echo "<div class='alert alert-danger'>Bir hata oluştu. Lütfen daha sonra tekrar deneyin.</div>";
        }
        
        return true;
    }
    
    public static function handleException($exception) {
        $error = "Uncaught exception: " . $exception->getMessage() . " in " . $exception->getFile() . " on line " . $exception->getLine();
        error_log($error);
        
        if (ini_get('display_errors')) {
            echo "<div class='alert alert-danger'>Bir hata oluştu. Lütfen daha sonra tekrar deneyin.</div>";
        }
    }
}

// Security headers
function setSecurityHeaders() {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// Initialize security
function initializeSecurity() {
    setSecurityHeaders();
    SessionSecurity::startSecureSession();
    
    set_error_handler([ErrorHandler::class, 'handleError']);
    set_exception_handler([ErrorHandler::class, 'handleException']);
}

// Utility functions
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'Az önce';
    if ($time < 3600) return floor($time/60) . ' dakika önce';
    if ($time < 86400) return floor($time/3600) . ' saat önce';
    if ($time < 2592000) return floor($time/86400) . ' gün önce';
    if ($time < 31536000) return floor($time/2592000) . ' ay önce';
    
    return floor($time/31536000) . ' yıl önce';
}

function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

function isValidImage($filePath) {
    $imageInfo = getimagesize($filePath);
    return $imageInfo !== false;
}

function compressImage($source, $destination, $quality = 80) {
    $imageInfo = getimagesize($source);
    
    if ($imageInfo === false) {
        return false;
    }
    
    $mimeType = $imageInfo['mime'];
    
    switch ($mimeType) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        case 'image/webp':
            $image = imagecreatefromwebp($source);
            break;
        default:
            return false;
    }
    
    if ($image === false) {
        return false;
    }
    
    $result = imagejpeg($image, $destination, $quality);
    imagedestroy($image);
    
    return $result;
}

// Cache helper
class CacheHelper {
    private $redis;
    
    public function __construct($redis) {
        $this->redis = $redis;
    }
    
    public function get($key) {
        try {
            return $this->redis->get($key);
        } catch (Exception $e) {
            error_log("Cache get error: " . $e->getMessage());
            return false;
        }
    }
    
    public function set($key, $value, $ttl = 3600) {
        try {
            return $this->redis->setex($key, $ttl, $value);
        } catch (Exception $e) {
            error_log("Cache set error: " . $e->getMessage());
            return false;
        }
    }
    
    public function delete($key) {
        try {
            return $this->redis->del($key);
        } catch (Exception $e) {
            error_log("Cache delete error: " . $e->getMessage());
            return false;
        }
    }
    
    public function flush() {
        try {
            return $this->redis->flushAll();
        } catch (Exception $e) {
            error_log("Cache flush error: " . $e->getMessage());
            return false;
        }
    }
}

// Performance monitoring
class PerformanceMonitor {
    private $startTime;
    private $memoryStart;
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->memoryStart = memory_get_usage();
    }
    
    public function getExecutionTime() {
        return microtime(true) - $this->startTime;
    }
    
    public function getMemoryUsage() {
        return memory_get_usage() - $this->memoryStart;
    }
    
    public function getPeakMemoryUsage() {
        return memory_get_peak_usage();
    }
    
    public function logPerformance($endpoint = '') {
        $data = [
            'endpoint' => $endpoint,
            'execution_time' => $this->getExecutionTime(),
            'memory_usage' => $this->getMemoryUsage(),
            'peak_memory' => $this->getPeakMemoryUsage(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        error_log("Performance: " . json_encode($data));
    }
}

// Initialize security when this file is included
if (!defined('SECURITY_INITIALIZED')) {
    define('SECURITY_INITIALIZED', true);
    initializeSecurity();
}
?>
