<?php
require_once '../config/database.php';

$pdo = get_pdo();

echo "<h2>🔍 FCM Token Duplicate Check</h2><pre>";

try {
    // Her kullanıcı için token sayısını göster
    $stmt = $pdo->query("
        SELECT 
            user_id,
            COUNT(*) as token_count,
            GROUP_CONCAT(CONCAT(id, ':', LEFT(token, 30), '...') SEPARATOR '\n  ') as tokens,
            GROUP_CONCAT(updated_at ORDER BY updated_at DESC SEPARATOR '\n  ') as update_times
        FROM fcm_tokens
        GROUP BY user_id
        HAVING token_count > 1
    ");
    
    $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($duplicates)) {
        echo "✅ Çift token kaydı yok!\n\n";
    } else {
        echo "⚠️ Çift token kayıtları bulundu:\n\n";
        foreach ($duplicates as $dup) {
            echo "👤 User ID: {$dup['user_id']} - {$dup['token_count']} token\n";
            echo "  Tokens:\n  {$dup['tokens']}\n";
            echo "  Update Times:\n  {$dup['update_times']}\n\n";
        }
        
        echo "\n🔧 Çözüm: Her kullanıcı için en eski tokenları silelim mi? (Y/N)\n";
        
        // Form ile onay al
        if (isset($_GET['cleanup']) && $_GET['cleanup'] === 'yes') {
            $deleted = 0;
            foreach ($duplicates as $dup) {
                // En yeni token hariç diğerlerini sil
                $stmt = $pdo->prepare("
                    DELETE FROM fcm_tokens 
                    WHERE user_id = ? 
                    AND id NOT IN (
                        SELECT id FROM (
                            SELECT id FROM fcm_tokens 
                            WHERE user_id = ? 
                            ORDER BY updated_at DESC 
                            LIMIT 1
                        ) as latest
                    )
                ");
                $stmt->execute([$dup['user_id'], $dup['user_id']]);
                $deleted += $stmt->rowCount();
            }
            echo "✅ $deleted eski token silindi!\n";
            echo "<meta http-equiv='refresh' content='2'>";
        }
    }
    
    // Tüm tokenları göster
    echo "\n📊 Tüm FCM Tokenlar:\n";
    $stmt = $pdo->query("
        SELECT 
            ft.id,
            ft.user_id,
            k.ad,
            k.soyad,
            k.email,
            LEFT(ft.token, 50) as short_token,
            ft.created_at,
            ft.updated_at
        FROM fcm_tokens ft
        JOIN kullanicilar k ON ft.user_id = k.id
        ORDER BY ft.user_id, ft.updated_at DESC
    ");
    
    $tokens = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "\nToplam " . count($tokens) . " token:\n\n";
    
    $current_user = null;
    foreach ($tokens as $token) {
        if ($current_user != $token['user_id']) {
            $current_user = $token['user_id'];
            echo "\n👤 {$token['ad']} {$token['soyad']} (ID: {$token['user_id']}, {$token['email']})\n";
        }
        $is_latest = ""; // En son token olup olmadığını göster
        echo "  - ID:{$token['id']} Token:{$token['short_token']}... {$is_latest}\n";
        echo "    Created: {$token['created_at']}, Updated: {$token['updated_at']}\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "</pre>";

// Cleanup butonu
if (!empty($duplicates)) {
    echo '<a href="?cleanup=yes" style="display: inline-block; padding: 10px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 1rem;">🗑️ Eski Tokenları Temizle</a>';
}

echo '<br><a href="test_notification.php" style="display: inline-block; padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; margin-top: 1rem;">→ Test Notification</a>';
?>

