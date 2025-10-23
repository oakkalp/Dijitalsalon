<?php
// Digital Salon Mobile API Bootstrap
// 192.168.1.137 IP ile çalışacak şekilde yapılandırıldı

// Start session first
session_start();

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, Cookie');
header('Access-Control-Allow-Credentials: true');

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
require_once __DIR__ . '/../includes/security.php';

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

