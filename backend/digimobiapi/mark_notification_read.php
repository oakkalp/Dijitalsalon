<?php
require_once 'bootstrap.php';

header('Content-Type: application/json');

// ✅ Session kontrolü
session_start();
if (!isset($_SESSION['user_id'])) {
    json_err('Unauthorized', 401);
}

$user_id = $_SESSION['user_id'];

// ✅ JSON input al
$input = json_decode(file_get_contents('php://input'), true);
$notification_id = $input['notification_id'] ?? null;

if (!$notification_id) {
    json_err('Notification ID is required');
}

try {
    // ✅ PDO bağlantısını al
    $pdo = get_pdo();
    
    // ✅ Bildirimi güncelle (sadece kendi bildirimi)
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$notification_id, $user_id]);

    if ($stmt->rowCount() > 0) {
        json_ok(['message' => 'Notification marked as read']);
    } else {
        json_err('Notification not found or not authorized');
    }

} catch (PDOException $e) {
    error_log("Mark Notification Read Error: " . $e->getMessage());
    json_err('Database error');
}

