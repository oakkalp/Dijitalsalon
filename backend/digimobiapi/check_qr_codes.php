<?php
// Direct database connection
$host = 'localhost';
$dbname = 'digitalsalon_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking QR codes in database...\n";
    
    // Check specific QR code
    $qr_code = 'QR_wt6d6r9le_mgxq1uo6';
    echo "Looking for QR code: $qr_code\n";
    
    $stmt = $pdo->prepare("
        SELECT d.*
        FROM dugunler d
        WHERE d.qr_kod = ?
    ");
    $stmt->execute([$qr_code]);
    $event = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($event) {
        echo "✅ Event found!\n";
        echo "ID: " . $event['id'] . "\n";
        echo "Title: " . $event['baslik'] . "\n";
        echo "Description: " . $event['aciklama'] . "\n";
        echo "Date: " . $event['tarih'] . "\n";
        echo "Created by: " . $event['olusturan_ad'] . ' ' . $event['olusturan_soyad'] . "\n";
        echo "QR Code: " . $event['qr_kod'] . "\n";
    } else {
        echo "❌ Event not found!\n";
        
        // Show all QR codes in database
        echo "\nAll QR codes in database:\n";
        $stmt = $pdo->query("SELECT id, baslik, qr_kod FROM dugunler WHERE qr_kod IS NOT NULL ORDER BY id");
        $all_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($all_events as $e) {
            echo "ID: {$e['id']}, Title: {$e['baslik']}, QR: {$e['qr_kod']}\n";
        }
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
