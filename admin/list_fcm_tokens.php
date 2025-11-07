<?php
require_once '../config/database.php';

$pdo = get_pdo();

echo "<h2>📱 FCM Tokens</h2><pre>";

$stmt = $pdo->query("
    SELECT 
        f.id,
        f.user_id,
        CONCAT(k.ad, ' ', k.soyad) as user_name,
        k.email,
        SUBSTRING(f.token, 1, 50) as token_preview,
        f.created_at
    FROM fcm_tokens f
    JOIN kullanicilar k ON k.id = f.user_id
    ORDER BY f.created_at DESC
    LIMIT 10
");

$tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($tokens)) {
    echo "❌ No FCM tokens found!\n";
    echo "📱 Users need to login to the app first to register their device tokens.\n";
} else {
    echo "✅ Found " . count($tokens) . " FCM token(s):\n\n";
    foreach ($tokens as $token) {
        echo "ID: {$token['id']}\n";
        echo "User: {$token['user_name']} ({$token['email']})\n";
        echo "User ID: {$token['user_id']}\n";
        echo "Token: {$token['token_preview']}...\n";
        echo "Created: {$token['created_at']}\n";
        echo "---\n";
    }
}

echo "</pre>";
?>


