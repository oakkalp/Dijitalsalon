<?php
require_once '../config/database.php';

$pdo = get_pdo();

echo "<h2>🔧 Notifications Table Type Column Fix</h2><pre>";

try {
    // Check current type column
    $stmt = $pdo->query("SHOW COLUMNS FROM notifications WHERE Field = 'type'");
    $typeColumn = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($typeColumn) {
        echo "✅ Current 'type' column: {$typeColumn['Type']}\n\n";
        
        // If it's too small, increase it
        if (strpos($typeColumn['Type'], 'enum') !== false || 
            (strpos($typeColumn['Type'], 'varchar') !== false && 
             preg_match('/varchar\((\d+)\)/', $typeColumn['Type'], $matches) && 
             intval($matches[1]) < 50)) {
            
            echo "⚠️ Type column is too small, increasing to VARCHAR(50)...\n";
            $pdo->exec("ALTER TABLE notifications MODIFY COLUMN type VARCHAR(50) NOT NULL DEFAULT 'custom'");
            echo "✅ Type column updated!\n\n";
        } else {
            echo "✅ Type column size is OK\n\n";
        }
    } else {
        echo "❌ Type column not found!\n";
    }
    
    // Show final structure
    echo "📊 Final notifications table structure:\n";
    $columns = $pdo->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_ASSOC);
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}) " . ($col['Null'] === 'NO' ? 'NOT NULL' : 'NULL');
        if (!empty($col['Default'])) {
            echo " DEFAULT '{$col['Default']}'";
        }
        echo "\n";
    }
    
    echo "\n✅ All checks complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";

echo "<br><a href='test_notification.php' style='display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600;'>→ Test Notification Gönder</a>";
?>

