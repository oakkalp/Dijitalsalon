<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $surname = $_POST['surname'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $device_info = $_POST['device_info'] ?? '';
    $ip_address = $_POST['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? '';

    // Validation
    if (empty($name) || empty($surname) || empty($email) || empty($phone) || empty($username) || empty($password)) {
        json_err(400, 'Tüm alanlar doldurulmalıdır');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        json_err(400, 'Geçerli bir e-posta adresi girin');
    }

    if (strlen($phone) != 10 || !preg_match('/^5[0-9]{9}$/', $phone)) {
        json_err(400, 'Geçerli bir telefon numarası girin (5XXXXXXXXX)');
    }

    if (strlen($username) < 3) {
        json_err(400, 'Kullanıcı adı en az 3 karakter olmalıdır');
    }

    if (strlen($password) < 6) {
        json_err(400, 'Şifre en az 6 karakter olmalıdır');
    }

    try {
        $pdo->beginTransaction();

        // Check if email already exists
        $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            json_err(400, 'Bu e-posta adresi zaten kullanılıyor');
        }

        // Check if username already exists
        $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE kullanici_adi = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            json_err(400, 'Bu kullanıcı adı zaten kullanılıyor');
        }

        // Check if phone already exists
        $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE telefon = ?");
        $stmt->execute([$phone]);
        if ($stmt->fetch()) {
            json_err(400, 'Bu telefon numarası zaten kullanılıyor');
        }

        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // Insert user
        $stmt = $pdo->prepare("
            INSERT INTO kullanicilar (
                ad, soyad, email, telefon, kullanici_adi, sifre, rol, 
                created_at, durum
            ) VALUES (?, ?, ?, ?, ?, ?, 'kullanici', NOW(), 'aktif')
        ");
        
        $result = $stmt->execute([$name, $surname, $email, $phone, $username, $hashed_password]);
        
        if (!$result) {
            throw new Exception('Kullanıcı kaydı oluşturulamadı');
        }

        $user_id = $pdo->lastInsertId();

        // Log registration activity
        $stmt = $pdo->prepare("
            INSERT INTO user_logs (
                user_id, action, details, ip_address, device_info, 
                created_at
            ) VALUES (?, 'register', ?, ?, ?, NOW())
        ");
        
        $log_details = json_encode([
            'name' => $name,
            'surname' => $surname,
            'email' => $email,
            'phone' => $phone,
            'username' => $username,
            'registration_method' => 'mobile_app'
        ]);
        
        $stmt->execute([$user_id, $log_details, $ip_address, $device_info]);

        $pdo->commit();

        // Return success response
        json_ok([
            'success' => true,
            'message' => 'Kayıt başarılı! Giriş yapabilirsiniz.',
            'user_id' => $user_id
        ]);

    } catch (Exception $e) {
        $pdo->rollback();
        error_log("Register API Error: " . $e->getMessage());
        json_err(500, 'Kayıt sırasında hata oluştu: ' . $e->getMessage());
    }
} else {
    json_err(405, 'Method not allowed');
}
?>
