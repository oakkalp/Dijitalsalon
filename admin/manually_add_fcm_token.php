<?php
/**
 * Manuel FCM Token Ekleme (Test için)
 * Firebase çalışmazsa bu sayfadan manuel token eklenebilir
 */
require_once '../config/database.php';

header('Content-Type: text/html; charset=utf-8');

$pdo = get_pdo();

// Form submit edildi mi?
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? null;
    $fcm_token = $_POST['fcm_token'] ?? '';
    
    if ($user_id && !empty($fcm_token)) {
        try {
            // Mevcut token varsa güncelle, yoksa ekle
            $stmt = $pdo->prepare("
                INSERT INTO fcm_tokens (user_id, token, created_at, updated_at)
                VALUES (?, ?, NOW(), NOW())
                ON DUPLICATE KEY UPDATE token = ?, updated_at = NOW()
            ");
            $stmt->execute([$user_id, $fcm_token, $fcm_token]);
            
            $success = "✅ FCM token başarıyla kaydedildi!";
        } catch (PDOException $e) {
            $error = "❌ Hata: " . $e->getMessage();
        }
    } else {
        $error = "❌ Lütfen kullanıcı ve token bilgisi girin!";
    }
}

// Kullanıcıları listele
$stmt = $pdo->query("
    SELECT id, ad, soyad, email 
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
    <title>Manuel FCM Token Ekle</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 2rem; }
        .container { max-width: 800px; margin: 0 auto; background: white; border-radius: 20px; padding: 2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.2); }
        h1 { color: #333; margin-bottom: 1rem; }
        .alert { padding: 1rem; border-radius: 10px; margin-bottom: 1rem; }
        .alert-success { background: #d4edda; color: #155724; }
        .alert-error { background: #f8d7da; color: #721c24; }
        .form-group { margin-bottom: 1.5rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.5rem; }
        select, input, textarea { width: 100%; padding: 0.75rem; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 1rem; }
        textarea { resize: vertical; min-height: 100px; font-family: monospace; }
        .btn { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 1rem 2rem; border: none; border-radius: 10px; font-size: 1.1rem; font-weight: 600; cursor: pointer; width: 100%; }
        .btn:hover { transform: translateY(-2px); }
        .info { background: #e7f3ff; padding: 1rem; border-radius: 8px; margin-bottom: 1.5rem; border-left: 4px solid #2196F3; }
    </style>
</head>
<body>
    <div class="container">
        <h1>📱 Manuel FCM Token Ekle</h1>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="info">
            <strong>ℹ️ Bilgi:</strong> Firebase çalışmıyorsa, bu sayfadan test için manuel FCM token ekleyebilirsiniz.
            <br><br>
            <strong>Test Token Örneği:</strong> 
            <code>dY1234567890:APA91bH...</code> (Firebase'den alınan gerçek token)
        </div>
        
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
                <label for="fcm_token">FCM Token:</label>
                <textarea name="fcm_token" id="fcm_token" required placeholder="dY1234567890:APA91bH..."></textarea>
            </div>
            
            <button type="submit" class="btn">💾 Token'ı Kaydet</button>
        </form>
        
        <a href="test_notification.php" class="btn" style="display: inline-block; text-align: center; text-decoration: none; margin-top: 1rem; background: #28a745;">
            🔔 Test Bildirimi Gönder
        </a>
    </div>
</body>
</html>


