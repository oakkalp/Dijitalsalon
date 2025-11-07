<?php
require_once __DIR__ . '/bootstrap.php';

// CORS headers
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$video_url = $_GET['url'] ?? '';

if (empty($video_url)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Video URL is required']);
    exit;
}

// URL'yi temizle ve kontrol et
$video_url = urldecode($video_url);

// HTTP URL'lerini HTTPS'e çevir (localhost için)
if (strpos($video_url, 'http://192.168.1.137') === 0) {
    $video_url = str_replace('http://192.168.1.137', 'https://192.168.1.137', $video_url);
} elseif (strpos($video_url, 'http://localhost') === 0) {
    $video_url = str_replace('http://localhost', 'https://localhost', $video_url);
}

// Video dosyasını stream et
try {
    // Video dosyasının varlığını kontrol et
    $file_path = str_replace('https://dijitalsalon.cagapps.app/', '../', $video_url);
    $file_path = str_replace('https://192.168.1.137/dijitalsalon/', '../', $file_path);
    
    if (!file_exists($file_path)) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Video file not found']);
        exit;
    }
    
    // Video dosyasını oku ve stream et
    $file_size = filesize($file_path);
    $file_extension = pathinfo($file_path, PATHINFO_EXTENSION);
    
    // Content-Type belirle
    $content_type = 'video/mp4';
    if ($file_extension === 'webm') {
        $content_type = 'video/webm';
    } elseif ($file_extension === 'avi') {
        $content_type = 'video/avi';
    }
    
    // HTTP headers
    header('Content-Type: ' . $content_type);
    header('Content-Length: ' . $file_size);
    header('Accept-Ranges: bytes');
    header('Cache-Control: public, max-age=3600');
    
    // Range request desteği (streaming için)
    if (isset($_SERVER['HTTP_RANGE'])) {
        $range = $_SERVER['HTTP_RANGE'];
        $ranges = explode('=', $range);
        $offset = explode('-', $ranges[1]);
        $start = intval($offset[0]);
        $end = isset($offset[1]) ? intval($offset[1]) : $file_size - 1;
        
        if ($end >= $file_size) {
            $end = $file_size - 1;
        }
        
        $length = $end - $start + 1;
        
        header('HTTP/1.1 206 Partial Content');
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $file_size);
        header('Content-Length: ' . $length);
        
        $file = fopen($file_path, 'rb');
        fseek($file, $start);
        
        $buffer_size = 8192;
        while (!feof($file) && ($pos = ftell($file)) <= $end) {
            if ($pos + $buffer_size > $end) {
                $buffer_size = $end - $pos + 1;
            }
            echo fread($file, $buffer_size);
            flush();
        }
        fclose($file);
    } else {
        // Normal dosya okuma
        readfile($file_path);
    }
    
} catch (Exception $e) {
    error_log("Video proxy error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Video streaming error']);
}
?>
