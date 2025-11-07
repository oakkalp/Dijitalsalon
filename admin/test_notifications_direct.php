<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Test user olarak session başlat
session_start();
$_SESSION['user_id'] = 3; // User 3 (Test Kullanc)

echo "Testing get_notifications.php...\n\n";

// Bootstrap'i include et
require_once __DIR__ . '/../digimobiapi/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

echo "User ID: " . $_SESSION['user_id'] . "\n";
echo "Session ID: " . session_id() . "\n\n";

try {
    $pdo = get_pdo();
    echo "✅ PDO connection OK\n\n";
    
    // Test query
    echo "Testing notification query...\n";
    $query = "
        SELECT 
            n.id,
            n.user_id,
            n.sender_id,
            n.event_id,
            n.type,
            n.title,
            n.message,
            n.data,
            n.is_read,
            n.created_at,
            k_sender.ad as sender_ad,
            k_sender.soyad as sender_soyad,
            k_sender.profil_resmi as sender_profile_image
        FROM notifications n
        LEFT JOIN kullanicilar k_sender ON k_sender.id = n.sender_id
        WHERE n.user_id = ?
        ORDER BY n.created_at DESC LIMIT 10
    ";
    
    echo "Query:\n$query\n\n";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([3]);
    $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Query executed successfully!\n";
    echo "Found " . count($notifications) . " notifications\n\n";
    
    if (count($notifications) > 0) {
        echo "First notification:\n";
        print_r($notifications[0]);
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack trace:\n" . $e->getTraceAsString() . "\n";
}
?>

