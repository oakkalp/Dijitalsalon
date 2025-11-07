<?php
require_once '../config/database.php';

$pdo = get_pdo();

echo "<h2>🔧 Fix FCM Tokens Table</h2><pre>";

try {
    // Check if updated_at column exists
    $stmt = $pdo->query("SHOW COLUMNS FROM fcm_tokens LIKE 'updated_at'");
    
    if ($stmt->rowCount() === 0) {
        echo "⚠️  'updated_at' column missing. Adding...\n";
        $pdo->exec("ALTER TABLE fcm_tokens ADD COLUMN updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at");
        echo "✅ 'updated_at' column added successfully!\n";
    } else {
        echo "✅ 'updated_at' column already exists.\n";
    }
    
    echo "\n📋 Current fcm_tokens structure:\n";
    $columns = $pdo->query("SHOW COLUMNS FROM fcm_tokens")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']})\n";
    }
    
} catch (PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";
?>


