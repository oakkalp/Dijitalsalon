<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    json_err(405, 'Method not allowed');
}

$username = $_POST['username'] ?? '';
$user_id = $_POST['user_id'] ?? null;

if (empty($username)) {
    json_err(400, 'Username is required');
}

try {
    // Check if username exists (excluding current user)
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE kullanici_adi = ? AND id != ?");
        $stmt->execute([$username, $user_id]);
    } else {
        $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE kullanici_adi = ?");
        $stmt->execute([$username]);
    }
    
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_user) {
        json_ok([
            'available' => false,
            'message' => 'Bu kullanıcı adı zaten kullanılıyor'
        ]);
    } else {
        json_ok([
            'available' => true,
            'message' => 'Bu kullanıcı adı kullanılabilir'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Check Username API Error: " . $e->getMessage());
    json_err(500, 'Database error');
}
?>
