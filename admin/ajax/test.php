<?php
// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session'ı başlat
session_start();

require_once '../../config/database.php';

// Test için basit JSON response
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'AJAX test başarılı!', 'session' => $_SESSION]);
?>
