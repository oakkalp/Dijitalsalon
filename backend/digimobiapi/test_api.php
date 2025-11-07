<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Start session
session_start();

// Include fast database connection
require_once 'fast_db.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $db = FastDB::getInstance();
    
    // Get parameters
    $eventId = $_GET['event_id'] ?? null;
    
    if (!$eventId) {
        throw new Exception('Event ID is required');
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized - Please login');
    }
    
    $userId = $_SESSION['user_id'];
    
    // Simple test query
    $testQuery = $db->fetchOne("SELECT COUNT(*) as count FROM medyalar WHERE dugun_id = ?", [$eventId]);
    
    // Return test response
    echo json_encode([
        'success' => true,
        'message' => 'API is working',
        'user_id' => $userId,
        'event_id' => $eventId,
        'media_count' => $testQuery['count'],
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    error_log("Test API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
