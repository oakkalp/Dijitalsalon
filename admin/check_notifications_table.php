<?php
require_once '../config/database.php';

$pdo = get_pdo();

echo "<h2>Notifications Table Structure</h2><pre>";

// Check if table exists
$stmt = $pdo->query("SHOW TABLES LIKE 'notifications'");
if ($stmt->rowCount() > 0) {
    echo "✅ Table 'notifications' exists\n\n";
    
    // Show columns
    $columns = $pdo->query("SHOW COLUMNS FROM notifications")->fetchAll(PDO::FETCH_ASSOC);
    echo "Columns:\n";
    foreach ($columns as $col) {
        echo "  - {$col['Field']} ({$col['Type']}) " . ($col['Null'] === 'NO' ? 'NOT NULL' : 'NULL') . "\n";
    }
    
    // Check if title and data columns exist
    $has_title = false;
    $has_data = false;
    foreach ($columns as $col) {
        if ($col['Field'] === 'title') $has_title = true;
        if ($col['Field'] === 'data') $has_data = true;
    }
    
    echo "\n";
    echo ($has_title ? "✅" : "❌") . " 'title' column exists\n";
    echo ($has_data ? "✅" : "❌") . " 'data' column exists\n";
    
    // If missing, add them
    if (!$has_title) {
        echo "\n🔧 Adding 'title' column...\n";
        $pdo->exec("ALTER TABLE notifications ADD COLUMN title VARCHAR(255) NULL AFTER type");
        echo "✅ 'title' column added!\n";
    }
    
    if (!$has_data) {
        echo "\n🔧 Adding 'data' column...\n";
        $pdo->exec("ALTER TABLE notifications ADD COLUMN data JSON NULL AFTER message");
        echo "✅ 'data' column added!\n";
    }
    
} else {
    echo "❌ Table 'notifications' does NOT exist\n";
    echo "Run install_database_updates.php first!\n";
}

echo "</pre>";
?>


