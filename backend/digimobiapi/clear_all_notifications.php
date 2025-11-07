<?php
require_once 'bootstrap.php';

header('Content-Type: application/json');

// Session kontrolü
if (!isset($_SESSION['user_id'])) {
    json_err(401, 'Unauthorized');
}

$user_id = $_SESSION['user_id'];

try {
    $pdo = get_pdo();
    
    // ✅ Kullanıcının tüm bildirimlerini sil
    $stmt = $pdo->prepare("DELETE FROM notifications WHERE user_id = ?");
    $stmt->execute([$user_id]);
    
    $deletedCount = $stmt->rowCount();
    
    json_ok([
        'message' => 'Tüm bildirimler temizlendi',
        'deleted_count' => $deletedCount
    ]);
    
} catch (PDOException $e) {
    error_log("Clear All Notifications Error: " . $e->getMessage());
    json_err(500, 'Database error');
}
?>

