<?php
/**
 * Test Notification Sender
 * Bu script ile herhangi bir kullanıcıya test bildirimi gönderebilirsiniz
 */
require_once '../config/database.php';

header('Content-Type: text/html; charset=utf-8');

$pdo = get_pdo();

// Form submit edildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $title = $_POST['title'] ?? 'Test Bildirimi';
    $message = $_POST['message'] ?? 'Bu bir test bildirimidir.';
    
    if ($user_id) {
        try {
            // 1. FCM token'ı al
            $stmt = $pdo->prepare("SELECT token FROM fcm_tokens WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$user_id]);
            $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$token_data) {
                $error = "❌ Bu kullanıcının FCM token'ı bulunamadı. Kullanıcı uygulamaya giriş yapmamış olabilir.";
            } else {
                $fcm_token = $token_data['token'];
                
                // 2. Firebase Service Account Key'i oku
                $service_account_path = __DIR__ . '/../config/dijital-salon-3d72ad092cab.json';
                if (!file_exists($service_account_path)) {
                    $error = "❌ Firebase service account dosyası bulunamadı: $service_account_path";
                } else {
                    // 3. notification_service.php kullan
                    require_once __DIR__ . '/../digimobiapi/notification_service.php';
                    
                    try {
                        // Debug: FCM token'ı logla
                        error_log("TEST NOTIFICATION - User ID: $user_id");
                        error_log("TEST NOTIFICATION - FCM Token: " . substr($fcm_token, 0, 50) . "...");
                        
                        // Bildirimi gönder
                        $result = sendNotification(
                            [$user_id], 
                            $title, 
                            $message, 
                            ['type' => 'test', 'timestamp' => time()]
                        );
                        
                        error_log("TEST NOTIFICATION - Result: " . json_encode($result));
                        
                        $success = "✅ Bildirim başarıyla gönderildi!<br>";
                        $success .= "📊 Gönderilen: " . ($result['success_count'] ?? 0) . " başarılı";
                        
                        if (!empty($result['failures'])) {
                            $success .= "<br>⚠️ Başarısız: " . count($result['failures']);
                            $success .= "<br>📝 Debug: Token ilk 50 karakter = " . substr($fcm_token, 0, 50);
                        }
                        
                    } catch (Exception $e) {
                        error_log("TEST NOTIFICATION - Exception: " . $e->getMessage());
                        $error = "❌ Bildirim gönderme hatası: " . $e->getMessage();
                    }
                }
            }
        } catch (Exception $e) {
            $error = "❌ Hata: " . $e->getMessage();
        }
    }
}

// Kullanıcıları listele
$stmt = $pdo->query("
    SELECT id, ad, soyad, email, kullanici_adi 
    FROM kullanicilar 
    WHERE durum = 'aktif' 
    ORDER BY id DESC 
    LIMIT 50
");
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Notification Sender</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 20px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        h1 { color: #333; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.5rem; color: #333; }
        select, input, textarea { width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem; transition: border 0.3s; }
        select:focus, input:focus, textarea:focus { outline: none; border-color: #667eea; }
        textarea { resize: vertical; min-height: 100px; }
        .btn { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1rem 2rem; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 600; cursor: pointer; transition: transform 0.2s; width: 100%; }
        .btn:hover { transform: translateY(-2px); box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4); }
        .back-link { display: inline-block; margin-top: 1rem; color: #667eea; text-decoration: none; font-weight: 600; }
        .back-link:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 Test Notification Sender</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label for="user_id">Kullanıcı Seç:</label>
                <select name="user_id" id="user_id" required>
                    <option value="">-- Kullanıcı Seçin --</option>
                    <?php foreach ($users as $user): ?>
                        <option value="<?php echo $user['id']; ?>">
                            <?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad'] . ' (' . $user['email'] . ')'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label for="title">Bildirim Başlığı:</label>
                <input type="text" name="title" id="title" value="🎉 Dijital Salon Test" required>
            </div>
            
            <div class="form-group">
                <label for="message">Bildirim Mesajı:</label>
                <textarea name="message" id="message" required>Merhaba! Bu bir test bildirimidir. Uygulama bildirim sistemi başarıyla çalışıyor! 🎉</textarea>
            </div>
            
            <div class="form-group">
                <label>📊 Debug Bilgileri:</label>
                <div style="background: #f8f9fa; padding: 1rem; border-radius: 8px; font-size: 0.9rem;">
                    <?php
                    // FCM token sayısını göster
                    $stmt_count = $pdo->query("SELECT COUNT(DISTINCT user_id) as user_count, COUNT(*) as token_count FROM fcm_tokens");
                    $counts = $stmt_count->fetch(PDO::FETCH_ASSOC);
                    echo "✅ <strong>{$counts['user_count']}</strong> kullanıcı, <strong>{$counts['token_count']}</strong> FCM token kayıtlı<br>";
                    
                    // Son token zamanını göster
                    $stmt_last = $pdo->query("SELECT MAX(created_at) as last_token_time FROM fcm_tokens");
                    $last = $stmt_last->fetch(PDO::FETCH_ASSOC);
                    if ($last['last_token_time']) {
                        echo "🕒 Son token kaydı: <strong>{$last['last_token_time']}</strong>";
                    }
                    ?>
                </div>
            </div>
            
            <button type="submit" class="btn">📤 Bildirimi Gönder</button>
        </form>
        
        <a href="event-participants.php?id=5" class="back-link">← Admin Panele Dön</a>
    </div>
</body>
</html>

