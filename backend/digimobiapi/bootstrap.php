<?php
// Digital Salon Mobile API Bootstrap
// 192.168.1.137 IP ile çalışacak şekilde yapılandırıldı

// ✅ Cookie header'ından PHPSESSID'i manuel olarak parse et (multipart için)
// Özellikle gerçek telefonlarda cookie header'ı doğru parse edilmiyor olabilir
$parsed_session_id = null;
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookies = [];
    $cookie_string = $_SERVER['HTTP_COOKIE'];
    
    error_log("Bootstrap - Cookie string: $cookie_string");
    
    // Cookie string'i parse et: "PHPSESSID=abc123; other=cookie"
    $parts = explode(';', $cookie_string);
    foreach ($parts as $part) {
        $part = trim($part);
        if (strpos($part, '=') !== false) {
            list($key, $value) = explode('=', $part, 2);
            $cookies[trim($key)] = trim($value);
        }
    }
    
    // PHPSESSID varsa manuel olarak set et
    if (isset($cookies['PHPSESSID']) && !empty($cookies['PHPSESSID'])) {
        $parsed_session_id = $cookies['PHPSESSID'];
        
        // Session başlamadan önce session ID'yi set et
        if (session_status() === PHP_SESSION_NONE) {
            session_id($parsed_session_id);
            error_log("Bootstrap - Session ID set before session_start: $parsed_session_id");
        }
        
        error_log("Bootstrap - Manual cookie parsing: PHPSESSID=$parsed_session_id");
    } else {
        error_log("Bootstrap - WARNING: PHPSESSID not found in cookies! Available cookies: " . implode(', ', array_keys($cookies)));
    }
}

// ✅ Session ayarları (timeout'u 24 saate çıkar)
ini_set('session.gc_maxlifetime', 86400); // 24 saat
ini_set('session.cookie_lifetime', 86400); // 24 saat
ini_set('session.use_cookies', 1);
ini_set('session.use_only_cookies', 1);

// Start session with extended timeout
if (session_status() === PHP_SESSION_NONE) {
    session_start();
    
    error_log("Bootstrap - Session started, ID: " . session_id());
    error_log("Bootstrap - Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
    
    // ✅ Session başladıktan sonra cookie'den gelen ID ile eşleştiğini kontrol et
    if ($parsed_session_id !== null && session_id() !== $parsed_session_id) {
        error_log("Bootstrap - WARNING: Session ID mismatch! Expected: $parsed_session_id, Got: " . session_id());
        // ✅ Eşleşmiyorsa, cookie'deki ID'yi kullan
        session_write_close();
        session_id($parsed_session_id);
        session_start();
        error_log("Bootstrap - Session restarted with cookie ID: " . session_id());
        error_log("Bootstrap - Session user_id after restart: " . ($_SESSION['user_id'] ?? 'STILL NOT SET'));
    }
} else {
    error_log("Bootstrap - Session already active, ID: " . session_id());
    error_log("Bootstrap - Session user_id: " . ($_SESSION['user_id'] ?? 'NOT SET'));
}

// ✅ Content-Type header'ını sadece JSON response'larda set et (multipart request'ler için değil)
// Content-Type, her endpoint'te kendi ihtiyacına göre set edilmeli
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === false) {
    header('Content-Type: application/json; charset=utf-8');
}

// ✅ Response Compression (Gzip) - Response boyutunu küçült
if (!ob_get_level() && extension_loaded('zlib') && !headers_sent()) {
    // Gzip compression kontrolü
    $accept_encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
    if (strpos($accept_encoding, 'gzip') !== false) {
        ob_start('ob_gzhandler');
    } else {
        ob_start();
    }
} else if (!ob_get_level()) {
    ob_start();
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Cookie');
header('Access-Control-Allow-Credentials: true');
// ✅ Cache headers (static content için)
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// OPTIONS request için
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Error handling as JSON
ini_set('display_errors', '0');
set_error_handler(function($severity, $message, $file, $line) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server error', 'detail' => $message]);
    exit;
});
set_exception_handler(function($e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Server exception', 'detail' => $e->getMessage()]);
    exit;
});

// Database connection
require_once __DIR__ . '/../config/database.php';

// Security functions (loaded after session start)
require_once __DIR__ . '/../includes/security.php';

// Helper functions
function json_ok($data = []) {
    echo json_encode(['success' => true] + $data);
    exit;
}

function json_success($data = []) {
    echo json_encode(['success' => true] + $data);
    exit;
}

function json_err($code, $msg) {
    http_response_code($code);
    echo json_encode(['success' => false, 'error' => $msg]);
    exit;
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function require_auth() {
    $user_id = get_user_id();
    if (!$user_id) {
        json_err(401, 'Unauthorized - Please login');
    }
    return $user_id;
}
?>

