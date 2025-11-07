<?php
/**
 * Database Structure Checker
 * Mevcut tablo ve kolon isimlerini kontrol eder
 */
require_once 'bootstrap.php';

$pdo = get_pdo();

echo "<h2>🔍 Database Structure Check</h2>";
echo "<pre>";

// ✅ 1. Kullanıcı tablosu ismini bul
echo "\n📋 Looking for users table...\n";
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
$user_table = null;
foreach ($tables as $table) {
    if (stripos($table, 'kullanici') !== false || stripos($table, 'user') !== false) {
        echo "✅ Found: $table\n";
        $user_table = $table;
        
        // Kolon isimlerini göster
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        echo "   Columns: ";
        foreach ($columns as $col) {
            echo $col['Field'] . ", ";
        }
        echo "\n";
    }
}

// ✅ 2. Etkinlik (düğün) tablosunu bul
echo "\n📋 Looking for events table...\n";
$event_table = null;
foreach ($tables as $table) {
    if (stripos($table, 'dugun') !== false || stripos($table, 'event') !== false) {
        echo "✅ Found: $table\n";
        $event_table = $table;
        
        // Kolon isimlerini göster
        $columns = $pdo->query("SHOW COLUMNS FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
        echo "   Columns: ";
        foreach ($columns as $col) {
            echo $col['Field'] . ", ";
        }
        echo "\n";
    }
}

// ✅ 3. Medya tablosunu bul
echo "\n📋 Looking for media table...\n";
$media_table = null;
foreach ($tables as $table) {
    if (stripos($table, 'medya') !== false || stripos($table, 'media') !== false) {
        echo "✅ Found: $table\n";
        $media_table = $table;
    }
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "📊 SUMMARY:\n";
echo "   User Table: $user_table\n";
echo "   Event Table: $event_table\n";
echo "   Media Table: $media_table\n";
echo str_repeat('=', 60) . "\n";
echo "</pre>";
?>

