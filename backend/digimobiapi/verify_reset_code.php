<?php
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    json_err(405, 'Method not allowed');
}

$email = $_POST['email'] ?? '';
$code = $_POST['code'] ?? '';

if (empty($email) || empty($code)) {
    json_err(400, 'Email and verification code are required');
}

// Validate email format
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_err(400, 'Invalid email format');
}

// Validate code format (6 digits)
if (!preg_match('/^\d{6}$/', $code)) {
    json_err(400, 'Verification code must be 6 digits');
}

try {
    // ✅ Kullanıcıyı bul
    $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        // ✅ Güvenlik için: Kullanıcı yoksa da genel hata mesajı döndür
        json_err(400, 'Invalid verification code');
    }
    
    // ✅ Doğrulama kodunu kontrol et
    // verification_code kolonu yoksa ekle ve kullan
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
        } catch (PDOException $e) {
            error_log("verification_code kolonu eklenemedi: " . $e->getMessage());
        }
    }
    
    // ✅ Doğrulama kodunu kontrol et - önce verification_code kolonunu dene
    $stmt = null;
    try {
        $stmt = $pdo->prepare("
            SELECT pr.token, pr.expires_at, pr.verification_code
            FROM password_resets pr
            WHERE pr.user_id = ? 
            AND pr.expires_at > NOW()
            AND (pr.verification_code = ? OR RIGHT(pr.token, 6) = ?)
            ORDER BY pr.created_at DESC
            LIMIT 1
        ");
        $stmt->execute([$user['id'], $code, $code]);
    } catch (PDOException $e) {
        // verification_code kolonu yoksa sadece token kontrolü yap
        if (strpos($e->getMessage(), 'verification_code') !== false) {
            $stmt = $pdo->prepare("
                SELECT pr.token, pr.expires_at
                FROM password_resets pr
                WHERE pr.user_id = ? 
                AND pr.expires_at > NOW()
                AND RIGHT(pr.token, 6) = ?
                ORDER BY pr.created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$user['id'], $code]);
        } else {
            throw $e;
        }
    }
    
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reset) {
        json_err(400, 'Invalid or expired verification code');
    }
    
    // ✅ Kod doğru, token'ı döndür
    json_ok([
        'message' => 'Verification code verified successfully',
        'token' => $reset['token']
    ]);
    
} catch (Exception $e) {
    error_log("Verify reset code error: " . $e->getMessage());
    json_err(500, 'Bir hata oluştu. Lütfen tekrar deneyin.');
}
?>

