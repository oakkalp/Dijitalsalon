<?php
require_once 'bootstrap.php';

header('Content-Type: application/json');

// ✅ JSON input al
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    json_err('Invalid JSON');
}

$email = $input['email'] ?? null;
$name = $input['name'] ?? null;
$provider = $input['provider'] ?? null; // 'google' veya 'apple'
$provider_id = $input['provider_id'] ?? null;
$photo_url = $input['photo_url'] ?? null;

if (!$email || !$provider || !$provider_id) {
    json_err('Email, provider and provider_id are required');
}

try {
    // ✅ Kullanıcıyı email'e göre ara
    $stmt = $pdo->prepare("
        SELECT * FROM kullanicilar 
        WHERE email = ? OR provider_id = ?
        LIMIT 1
    ");
    $stmt->execute([$email, $provider_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        // ✅ Kullanıcı mevcut - bilgileri güncelle
        $stmt = $pdo->prepare("
            UPDATE kullanicilar 
            SET 
                provider = ?,
                provider_id = ?,
                profil_fotografi = ?,
                last_login = NOW()
            WHERE id = ?
        ");
        $stmt->execute([
            $provider,
            $provider_id,
            $photo_url ?? $user['profil_fotografi'],
            $user['id']
        ]);

        $user_id = $user['id'];
    } else {
        // ✅ Yeni kullanıcı oluştur
        $name_parts = explode(' ', trim($name), 2);
        $ad = $name_parts[0] ?? 'User';
        $soyad = $name_parts[1] ?? '';

        $stmt = $pdo->prepare("
            INSERT INTO kullanicilar (
                email, ad, soyad, sifre, rol, 
                provider, provider_id, profil_fotografi,
                created_at, last_login
            ) VALUES (?, ?, ?, ?, 'kullanici', ?, ?, ?, NOW(), NOW())
        ");
        
        // Sosyal login için şifre gerekmiyor ama boş olamaz
        $random_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);
        
        $stmt->execute([
            $email,
            $ad,
            $soyad,
            $random_password,
            $provider,
            $provider_id,
            $photo_url
        ]);

        $user_id = $pdo->lastInsertId();
    }

    // ✅ Session oluştur
    session_start();
    $_SESSION['user_id'] = $user_id;
    $_SESSION['user_email'] = $email;
    $_SESSION['login_time'] = time();

    // ✅ Kullanıcı bilgilerini tekrar çek
    $stmt = $pdo->prepare("
        SELECT id, email, ad, soyad, rol, profil_fotografi, telefon, username
        FROM kullanicilar 
        WHERE id = ?
    ");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);

    json_ok([
        'session_key' => session_id(),
        'user' => [
            'id' => (int)$user_data['id'],
            'email' => $user_data['email'],
            'name' => trim($user_data['ad'] . ' ' . $user_data['soyad']),
            'role' => $user_data['rol'],
            'profile_image' => $user_data['profil_fotografi'] 
                ? 'https://dijitalsalon.cagapps.app/' . $user_data['profil_fotografi']
                : null,
            'phone' => $user_data['telefon'],
            'username' => $user_data['username']
        ]
    ]);

} catch (PDOException $e) {
    error_log("Social Login Error: " . $e->getMessage());
    json_err('Database error: ' . $e->getMessage());
}

