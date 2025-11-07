<?php
require_once 'bootstrap.php';

header('Content-Type: application/json');

try {
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        json_err(401, 'Unauthorized');
        exit;
    }

    $user_id = $_SESSION['user_id'];
    $pdo = get_pdo();

    // ✅ Tüm bildirimleri okunmuş olarak işaretle
    $stmt = $pdo->prepare("
        UPDATE notifications 
        SET is_read = 1 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$user_id]);

    $affected_rows = $stmt->rowCount();

    echo json_encode([
        'success' => true,
        'message' => 'Tüm bildirimler okunmuş olarak işaretlendi',
        'updated_count' => $affected_rows
    ]);

} catch (Exception $e) {
    error_log("mark_all_notifications_read.php error: " . $e->getMessage());
    json_err(500, 'Server error');
}

