<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

// ✅ GET request ise (email linkinden geldiğinde), Flutter web view veya redirect için HTML sayfa göster
if ($method === 'GET') {
    $token = $_GET['token'] ?? '';
    
    if (empty($token)) {
        // Invalid token
        http_response_code(400);
        echo "<!DOCTYPE html>
<html>
<head>
    <title>Şifre Sıfırlama - Digital Salon</title>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .container { max-width: 400px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error { color: #e74c3c; }
        .success { color: #27ae60; }
        button { background: #E1306C; color: white; border: none; padding: 12px 24px; border-radius: 8px; cursor: pointer; font-size: 16px; margin-top: 20px; }
    </style>
</head>
<body>
    <div class='container'>
        <h2>Şifre Sıfırlama</h2>
        <p class='error'>Geçersiz veya eksik token. Lütfen e-posta adresinize gönderilen bağlantıyı kullanın.</p>
    </div>
</body>
</html>";
        exit;
    }
    
    // Token'ı kontrol et
    try {
        $stmt = $pdo->prepare("
            SELECT pr.user_id, pr.expires_at 
            FROM password_resets pr
            WHERE pr.token = ? AND pr.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $reset = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$reset) {
            // Invalid or expired token
            http_response_code(400);
            echo "<!DOCTYPE html>
<html>
<head>
    <title>Şifre Sıfırlama - Digital Salon</title>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .container { max-width: 400px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .error { color: #e74c3c; }
    </style>
</head>
<body>
    <div class='container'>
        <h2>Şifre Sıfırlama</h2>
        <p class='error'>Token geçersiz veya süresi dolmuş. Lütfen yeni bir şifre sıfırlama isteği yapın.</p>
    </div>
</body>
</html>";
            exit;
        }
        
        // ✅ Token geçerli
        // Mobile app için: deep link ile yönlendir
        // Web için: reset_password_web.php sayfasına yönlendir (doğrulama kodu ekranı)
        
        // ✅ User-Agent kontrolü - mobil cihaz mı?
        $is_mobile = preg_match('/(android|iphone|ipad|ipod|blackberry|iemobile|opera mini)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
        
        if ($is_mobile) {
            // ✅ Mobil cihaz - önce deep link dene, açılmazsa web sayfasına yönlendir
            echo "<!DOCTYPE html>
<html>
<head>
    <title>Şifre Sıfırlama - Digital Salon</title>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <script>
        // ✅ Mobile app deep link
        window.location.href = 'digimobil://reset-password?token=$token';
        
        // ✅ Deep link açılmazsa 2 saniye sonra web sayfasına yönlendir
        setTimeout(function() {
            window.location.href = 'reset_password_web.php?token=$token';
        }, 2000);
    </script>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f5f5f5; }
        .container { max-width: 400px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { color: #27ae60; }
    </style>
</head>
<body>
    <div class='container'>
        <h2>Şifre Sıfırlama</h2>
        <p class='success'>Uygulama açılıyor...</p>
        <p>Eğer uygulama açılmazsa, doğrulama kodu ekranına yönlendirileceksiniz.</p>
    </div>
</body>
</html>";
        } else {
            // ✅ Web tarayıcı - direkt web sayfasına yönlendir
            header("Location: reset_password_web.php?token=$token");
            exit;
        }
        exit;
    } catch (Exception $e) {
        error_log("Reset password GET error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Bir hata oluştu.']);
        exit;
    }
}

// ✅ POST request (Flutter app'ten şifre güncelleme)
if ($method !== 'POST') {
    json_err(405, 'Method not allowed');
}

$token = $_POST['token'] ?? '';
$password = $_POST['password'] ?? '';

if (empty($token) || empty($password)) {
    json_err(400, 'Token and password are required');
}

if (strlen($password) < 6) {
    json_err(400, 'Password must be at least 6 characters');
}

try {
    // ✅ Token'ı kontrol et ve kullanıcıyı bul
    $stmt = $pdo->prepare("
        SELECT pr.user_id, pr.expires_at 
        FROM password_resets pr
        WHERE pr.token = ? AND pr.expires_at > NOW()
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reset) {
        json_err(400, 'Invalid or expired token');
    }
    
    $user_id = $reset['user_id'];
    
    // ✅ Şifreyi güncelle (bcrypt hash)
    $hashed_password = password_hash($password, PASSWORD_BCRYPT);
    
    error_log("Reset Password - User ID: $user_id");
    error_log("Reset Password - Hash length: " . strlen($hashed_password));
    
    $stmt = $pdo->prepare("UPDATE kullanicilar SET sifre = ? WHERE id = ?");
    $update_result = $stmt->execute([$hashed_password, $user_id]);
    
    if (!$update_result) {
        error_log("Reset Password - UPDATE query failed!");
        json_err(500, 'Şifre güncellenirken bir hata oluştu.');
    }
    
    // ✅ Güncellemenin başarılı olduğunu kontrol et
    $stmt = $pdo->prepare("SELECT sifre FROM kullanicilar WHERE id = ?");
    $stmt->execute([$user_id]);
    $updated_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$updated_user || !password_verify($password, $updated_user['sifre'])) {
        error_log("Reset Password - Password verification failed after update!");
        error_log("Reset Password - Updated password hash: " . substr($updated_user['sifre'] ?? 'NULL', 0, 20) . "...");
        json_err(500, 'Şifre güncellenirken bir hata oluştu.');
    }
    
    error_log("Reset Password - Password successfully updated and verified for user ID: $user_id");
    
    // ✅ Token'ı sil (tek kullanımlık)
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
    $stmt->execute([$token]);
    
    json_ok([
        'message' => 'Şifreniz başarıyla güncellendi. Yeni şifrenizle giriş yapabilirsiniz.'
    ]);
    
} catch (Exception $e) {
    error_log("Reset password error: " . $e->getMessage());
    json_err(500, 'Bir hata oluştu. Lütfen tekrar deneyin.');
}
?>

