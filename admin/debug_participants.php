<?php
require_once '../config/database.php';

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Debug Participants</title></head><body>";
echo "<h1>🔍 Debug Participants for Event ID: 5</h1>";
echo "<pre>";

try {
    $pdo = get_pdo();
    $event_id = 5;
    
    echo "\n" . str_repeat("=", 80) . "\n";
    echo "📊 RAW DATA FROM dugun_katilimcilar TABLE\n";
    echo str_repeat("=", 80) . "\n\n";
    
    // Raw data from dugun_katilimcilar
    $stmt = $pdo->prepare("
        SELECT dk.id, dk.kullanici_id, dk.rol, dk.durum, dk.katilim_tarihi, dk.yetkiler
        FROM dugun_katilimcilar dk
        WHERE dk.dugun_id = ?
        ORDER BY dk.id ASC
    ");
    $stmt->execute([$event_id]);
    $raw_participants = $stmt->fetchAll();
    
    echo "Total records in dugun_katilimcilar: " . count($raw_participants) . "\n\n";
    
    foreach ($raw_participants as $rp) {
        echo "Record ID: {$rp['id']}\n";
        echo "  kullanici_id: {$rp['kullanici_id']}\n";
        echo "  rol: {$rp['rol']}\n";
        echo "  durum: {$rp['durum']}\n";
        echo "  katilim_tarihi: {$rp['katilim_tarihi']}\n";
        echo "  yetkiler: " . substr($rp['yetkiler'], 0, 50) . "...\n";
        echo "\n";
    }
    
    echo str_repeat("=", 80) . "\n";
    echo "👤 USER DATA FROM kullanicilar TABLE\n";
    echo str_repeat("=", 80) . "\n\n";
    
    // User data
    $user_ids = array_unique(array_column($raw_participants, 'kullanici_id'));
    
    foreach ($user_ids as $user_id) {
        $user_stmt = $pdo->prepare("SELECT id, ad, soyad, email, kullanici_adi FROM kullanicilar WHERE id = ?");
        $user_stmt->execute([$user_id]);
        $user = $user_stmt->fetch();
        
        if ($user) {
            echo "User ID: {$user['id']}\n";
            echo "  Name: {$user['ad']} {$user['soyad']}\n";
            echo "  Email: {$user['email']}\n";
            echo "  Username: {$user['kullanici_adi']}\n";
            echo "\n";
        } else {
            echo "User ID: $user_id - ❌ NOT FOUND IN kullanicilar TABLE!\n\n";
        }
    }
    
    echo str_repeat("=", 80) . "\n";
    echo "🔗 JOINED DATA (AS SHOWN IN ADMIN PANEL)\n";
    echo str_repeat("=", 80) . "\n\n";
    
    // Joined data (as admin panel shows)
    $stmt = $pdo->prepare("
        SELECT dk.id as record_id, dk.kullanici_id, k.ad, k.soyad, k.email, dk.rol, dk.durum, dk.katilim_tarihi
        FROM dugun_katilimcilar dk
        LEFT JOIN kullanicilar k ON dk.kullanici_id = k.id
        WHERE dk.dugun_id = ?
        ORDER BY dk.id ASC
    ");
    $stmt->execute([$event_id]);
    $joined = $stmt->fetchAll();
    
    foreach ($joined as $j) {
        echo "Record ID: {$j['record_id']}\n";
        echo "  kullanici_id: {$j['kullanici_id']}\n";
        echo "  Name: {$j['ad']} {$j['soyad']}\n";
        echo "  Email: {$j['email']}\n";
        echo "  Role: {$j['rol']}\n";
        echo "\n";
    }
    
    echo str_repeat("=", 80) . "\n";
    echo "🎯 DUPLICATE ANALYSIS\n";
    echo str_repeat("=", 80) . "\n\n";
    
    // Check for duplicates
    $dup_stmt = $pdo->prepare("
        SELECT kullanici_id, COUNT(*) as count
        FROM dugun_katilimcilar
        WHERE dugun_id = ?
        GROUP BY kullanici_id
        HAVING count > 1
    ");
    $dup_stmt->execute([$event_id]);
    $duplicates = $dup_stmt->fetchAll();
    
    if (empty($duplicates)) {
        echo "✅ No duplicate kullanici_id found!\n";
        echo "The issue is NOT duplicate records.\n";
    } else {
        echo "❌ Found duplicates:\n\n";
        foreach ($duplicates as $dup) {
            echo "  kullanici_id: {$dup['kullanici_id']} appears {$dup['count']} times\n";
        }
    }
    
} catch (Exception $e) {
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
}

echo "</pre>";
echo "<p><a href='event-participants.php?id=5' style='padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px;'>← Back</a></p>";
echo "</body></html>";
?>

