<?php
require_once __DIR__ . '/../digimobiapi/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = get_pdo();
    
    echo "📋 KULLANICILAR TABLE COLUMNS:\n";
    echo "================================\n\n";
    
    $stmt = $pdo->query("SHOW COLUMNS FROM kullanicilar");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($columns as $col) {
        echo "- {$col['Field']} ({$col['Type']})\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage();
}
?>

