<?php
require_once 'bootstrap.php';

header('Content-Type: application/json');

// ✅ Session kontrolü (bootstrap.php already starts session)
if (!isset($_SESSION['user_id'])) {
    json_err('Unauthorized', 401);
}

$user_id = $_SESSION['user_id'];

// ✅ JSON input al
$input = json_decode(file_get_contents('php://input'), true);
$fcm_token = $input['fcm_token'] ?? null;

if (!$fcm_token) {
    json_err('FCM token is required');
}

try {
    // ✅ Kullanıcının mevcut token'ını kontrol et
    $stmt = $pdo->prepare("
        SELECT id FROM fcm_tokens 
        WHERE user_id = ? AND token = ?
        LIMIT 1
    ");
    $stmt->execute([$user_id, $fcm_token]);
    $existing = $stmt->fetch();

    if ($existing) {
        // ✅ Token zaten kayıtlı - updated_at güncelle
        $stmt = $pdo->prepare("
            UPDATE fcm_tokens 
            SET updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$existing['id']]);
    } else {
        // ✅ Yeni token ekle
        $stmt = $pdo->prepare("
            INSERT INTO fcm_tokens (user_id, token, created_at, updated_at)
            VALUES (?, ?, NOW(), NOW())
        ");
        $stmt->execute([$user_id, $fcm_token]);
    }

    json_ok(['message' => 'FCM token saved successfully']);

} catch (PDOException $e) {
    error_log("FCM Token Save Error: " . $e->getMessage());
    json_err('Database error');
}

