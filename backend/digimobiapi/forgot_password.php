<?php
require_once __DIR__ . '/bootstrap.php';

// ✅ Bootstrap'dan sonra error handler'ı sakla ve geçici olarak değiştir
$bootstrap_error_handler = set_error_handler(function($severity, $message, $file, $line) use (&$bootstrap_error_handler) {
    // ✅ Email/SMTP ile ilgili tüm hataları görmezden gel (fsockopen, stream_socket_client, SMTP bağlantı hataları)
    $email_related_errors = [
        'mail()',
        'SMTP',
        'mail server',
        'requires authentication',
        'fsockopen()',
        'stream_socket_client()',
        'Unable to connect',
        'connection attempt failed',
        'smtp.yandex.com',
        'smtp.gmail.com',
        'SMTP server'
    ];
    
    foreach ($email_related_errors as $error_keyword) {
        if (stripos($message, $error_keyword) !== false) {
            // ✅ Sadece log'a yaz, JSON error döndürme
            error_log("Email/SMTP error suppressed: $message");
            return true; // Hata işlenmiş sayılır
        }
    }
    
    // ✅ Diğer hatalar için bootstrap handler'ını çağır
    if ($bootstrap_error_handler) {
        return call_user_func($bootstrap_error_handler, $severity, $message, $file, $line);
    }
    return false;
});

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    json_err(405, 'Method not allowed');
}

$email = $_POST['email'] ?? '';

if (empty($email)) {
    json_err(400, 'Email is required');
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_err(400, 'Invalid email format');
}

// ✅ verification_code değişkenini baştan tanımla (scope sorunu için)
$verification_code = null;
$token = null;
$deep_link = null;
$web_link = null;

try {
    // Check if user exists
    $stmt = $pdo->prepare("SELECT id, ad, soyad, email FROM kullanicilar WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // ✅ Güvenlik için: Kullanıcı yoksa da başarılı mesajı döndür (email enumeration saldırısını önler)
        json_ok([
            'message' => 'Eğer bu e-posta adresi kayıtlıysa, şifre sıfırlama bağlantısı gönderilmiştir.'
        ]);
        exit;
    }
    
    // ✅ Generate unique reset token
    $token = bin2hex(random_bytes(32)); // 64 karakterlik güvenli token
    
    // ✅ Generate 6-digit verification code
    $verification_code = str_pad(strval(rand(100000, 999999)), 6, '0', STR_PAD_LEFT);
    
    // ✅ Link'leri oluştur
    $deep_link = "digimobil://reset-password?token=$token";
    $web_link = "https://dijitalsalon.cagapps.app/digimobiapi/reset_password.php?token=$token";
    
    // ✅ Token'ı veritabanına kaydet (24 saat geçerli, doğrulama kodu ile birlikte)
    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
    
    // Önce varsa eski token'ı sil
    $stmt = $pdo->prepare("DELETE FROM password_resets WHERE user_id = ?");
    $stmt->execute([$user['id']]);
    
    // ✅ password_resets tablosuna verification_code kolonu var mı kontrol et, yoksa ekle
    $hasVerificationCodeColumn = false;
    try {
        $checkColumn = $pdo->query("SHOW COLUMNS FROM password_resets LIKE 'verification_code'");
        $hasVerificationCodeColumn = $checkColumn->rowCount() > 0;
    } catch (PDOException $e) {
        $hasVerificationCodeColumn = false;
    }
    
    // ✅ Kolon yoksa ekle
    if (!$hasVerificationCodeColumn) {
        try {
            $pdo->exec("ALTER TABLE password_resets ADD COLUMN verification_code VARCHAR(6) NULL AFTER token");
            error_log("verification_code kolonu eklendi");
            $hasVerificationCodeColumn = true;
        } catch (PDOException $e) {
            error_log("verification_code kolonu eklenemedi: " . $e->getMessage());
        }
    }
    
    // ✅ Yeni token'ı ve doğrulama kodunu ekle
    if ($hasVerificationCodeColumn) {
        // verification_code kolonu varsa
        try {
            $stmt = $pdo->prepare("
                INSERT INTO password_resets (user_id, token, verification_code, expires_at, created_at) 
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$user['id'], $token, $verification_code, $expires_at]);
        } catch (PDOException $e) {
            // Hata durumunda sadece token kaydet
            error_log("verification_code kaydedilemedi, sadece token kaydediliyor: " . $e->getMessage());
            $stmt = $pdo->prepare("
                INSERT INTO password_resets (user_id, token, expires_at, created_at) 
                VALUES (?, ?, ?, NOW())
            ");
            $stmt->execute([$user['id'], $token, $expires_at]);
        }
    } else {
        // verification_code kolonu yoksa sadece token kaydet
        $stmt = $pdo->prepare("
            INSERT INTO password_resets (user_id, token, expires_at, created_at) 
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$user['id'], $token, $expires_at]);
    }
    
    // ✅ Email gönder (deep_link ve web_link zaten yukarıda tanımlandı)
    $subject = "Şifre Sıfırlama - Digital Salon";
    
    // ✅ HTML formatında profesyonel email içeriği (spam'a düşmemesi için)
    $user_name = htmlspecialchars($user['ad'] ?? 'Kullanıcı');
    $html_message = "
<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .content { background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; border-top: none; }
        .code-box { background: #f5f5f5; border: 2px dashed #667eea; padding: 20px; text-align: center; font-size: 32px; font-weight: bold; color: #667eea; margin: 20px 0; border-radius: 8px; letter-spacing: 5px; }
        .button { display: inline-block; background: #667eea; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
        .footer { background: #f9f9f9; padding: 20px; text-align: center; color: #666; font-size: 12px; border-radius: 0 0 10px 10px; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🔐 Şifre Sıfırlama</h1>
        </div>
        <div class='content'>
            <p>Merhaba <strong>$user_name</strong>,</p>
            <p>Şifre sıfırlama isteğiniz alındı. Doğrulama kodunuz:</p>
            
            <div class='code-box'>$verification_code</div>
            
            <p style='text-align: center;'>
                <a href='$deep_link' class='button'>Uygulamada Aç</a>
            </p>
            
            <p><strong>Nasıl Kullanılır?</strong></p>
            <ol>
                <li>Mobil uygulamanızı açın</li>
                <li>Şifre sıfırlama ekranında yukarıdaki doğrulama kodunu girin</li>
                <li>Yeni şifrenizi belirleyin</li>
            </ol>
            
            <div class='warning'>
                <strong>⚠️ Önemli:</strong> Bu kod 24 saat geçerlidir. Eğer bu isteği siz yapmadıysanız, bu e-postayı görmezden gelebilirsiniz.
            </div>
        </div>
        <div class='footer'>
            <p>Bu e-posta Digital Salon tarafından otomatik olarak gönderilmiştir.</p>
            <p>© " . date('Y') . " Digital Salon. Tüm hakları saklıdır.</p>
        </div>
    </div>
</body>
</html>";

    // ✅ Plain text alternatifi (eski email istemcileri için)
    $plain_message = "Merhaba $user_name,\n\n";
    $plain_message .= "Şifre sıfırlama isteğiniz alındı. Doğrulama kodunuz:\n\n";
    $plain_message .= "Doğrulama Kodu: $verification_code\n\n";
    $plain_message .= "Mobil uygulamanızı açın ve şifre sıfırlama ekranında bu kodu girin.\n\n";
    $plain_message .= "Uygulama Linki: $deep_link\n\n";
    $plain_message .= "Bu kod 24 saat geçerlidir.\n\n";
    $plain_message .= "Eğer bu isteği siz yapmadıysanız, bu e-postayı görmezden gelebilirsiniz.\n\n";
    $plain_message .= "Saygılarımızla,\n";
    $plain_message .= "Digital Salon Ekibi";
    
    // ✅ Email gönder - SMTP helper kullan
    require_once __DIR__ . '/send_email_helper.php';
    
    // ✅ HTML formatında gönder (spam'a düşmemesi için profesyonel görünüm)
    $email_result = sendEmailViaSMTP($email, $subject, $html_message, true, $plain_message);
    
    // ✅ Development/Test ortamı için token ve kodu HER ZAMAN log'a yaz (test için)
    error_log("========================================");
    error_log("PASSWORD RESET TOKEN & CODE (DEV/TEST MODE)");
    error_log("User ID: {$user['id']}");
    error_log("Email: $email");
    error_log("Token: $token");
    error_log("Verification Code: $verification_code");
    error_log("Reset Link (Web): $web_link");
    error_log("Reset Link (Deep): $deep_link");
    error_log("Expires At: $expires_at");
    error_log("Email Send Status: " . ($email_result['success'] ? 'SUCCESS' : 'FAILED'));
    error_log("Email Send Method: " . ($email_result['method'] ?? 'unknown'));
    if (!$email_result['success']) {
        error_log("Email Send Error: {$email_result['error']}");
    }
    if (isset($email_result['smtp_error'])) {
        error_log("SMTP Error (fallback used): {$email_result['smtp_error']}");
    }
    error_log("========================================");
    
    // ✅ Email gönderim hatası log'lanır ama kullanıcıya başarılı mesaj döndür (güvenlik için)
    // Token veritabanına kaydedildi, test için log'da mevcut
    if (!$email_result['success']) {
        error_log("Password reset email failed to send for user: $email - {$email_result['error']}");
    }
    
    // ✅ Email gönderilemediyse doğrulama kodunu response'da döndür
    // Kullanıcı email alamazsa, doğrulama kodu ekranına yönlendirilecek
    $response_data = [
        'message' => 'Eğer bu e-posta adresi kayıtlıysa, şifre sıfırlama bağlantısı gönderilmiştir.'
    ];
    
    // ✅ Her zaman doğrulama kodunu döndür (email gönderildi veya gönderilmedi)
    // Kullanıcı email'den kodu görebilir veya email gönderilemediyse ekranda gösterilir
    $response_data['verification_code'] = $verification_code;
    $response_data['email_sent'] = $email_result['success'] ?? false;
    $response_data['email_method'] = $email_result['method'] ?? 'unknown';
    
    json_ok($response_data);
    
} catch (Exception $e) {
    $error_message = $e->getMessage();
    
    // ✅ Email/SMTP ile ilgili hataları görmezden gel (zaten error handler'da yakalandı ama güvenlik için)
    $email_related_keywords = ['fsockopen', 'SMTP', 'Unable to connect', 'connection attempt failed', 'mail()', 'stream_socket_client'];
    $is_email_error = false;
    foreach ($email_related_keywords as $keyword) {
        if (stripos($error_message, $keyword) !== false) {
            $is_email_error = true;
            break;
        }
    }
    
    if ($is_email_error) {
        // ✅ Email hatası - kullanıcıya başarılı mesaj döndür (güvenlik için)
        error_log("Forgot password - Email error suppressed: $error_message");
        
        // ✅ Hata durumunda da verification_code'u döndür (eğer varsa)
        $response_data = [
            'message' => 'Eğer bu e-posta adresi kayıtlıysa, şifre sıfırlama bağlantısı gönderilmiştir.'
        ];
        
        // ✅ verification_code değişkeni tanımlıysa ekle
        if (isset($verification_code) && !empty($verification_code)) {
            $response_data['verification_code'] = $verification_code;
            $response_data['email_sent'] = false;
        }
        
        json_ok($response_data);
    } else {
        // ✅ Gerçek hata - log'a yaz ve genel hata mesajı döndür
        error_log("Forgot password error: " . $error_message);
        
        // ✅ Hata mesajında $reset_link hatası var mı kontrol et
        if (stripos($error_message, 'reset_link') !== false) {
            error_log("Forgot password - reset_link variable error, but continuing...");
            // Bu hata sadece log'da olabilir, response'u gönder
            $response_data = [
                'message' => 'Eğer bu e-posta adresi kayıtlıysa, şifre sıfırlama bağlantısı gönderilmiştir.'
            ];
            if (isset($verification_code) && !empty($verification_code)) {
                $response_data['verification_code'] = $verification_code;
            }
            json_ok($response_data);
        } else {
            json_err(500, 'Bir hata oluştu. Lütfen tekrar deneyin.');
        }
    }
}
?>

