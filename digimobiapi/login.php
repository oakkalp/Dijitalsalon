<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    json_err(405, 'Method not allowed');
}

$login = $_POST['login'] ?? ''; // Email, telefon veya kullanıcı adı
$password = $_POST['password'] ?? '';

if (empty($login) || empty($password)) {
    json_err(400, 'Login and password are required');
}

try {
    // ✅ Kullanıcı adı, email veya telefon ile giriş
    $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE email = ? OR telefon = ? OR kullanici_adi = ?");
    $stmt->execute([$login, $login, $login]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && password_verify($password, $user['sifre'])) {
        // Check if user role allows mobile app access
        if (!in_array($user['rol'], ['kullanici', 'moderator', 'super_admin'])) {
            json_err(403, 'Access denied for this role');
        }
        
        // Start session
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_role'] = $user['rol'];
        
        // Update last login
        $stmt = $pdo->prepare("UPDATE kullanicilar SET son_giris = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        json_ok([
            'user' => [
                'id' => $user['id'],
                'name' => $user['ad'] . ' ' . $user['soyad'],
                'email' => $user['email'],
                'username' => $user['kullanici_adi'],
                'phone' => $user['telefon'],
                'role' => $user['rol'],
                'profile_image' => $user['profil_fotografi'] ? 'http://192.168.1.137/dijitalsalon/' . $user['profil_fotografi'] : null
            ]
        ]);
    } else {
        json_err(401, 'Invalid credentials');
    }
} catch (Exception $e) {
    json_err(500, 'Database error');
}
?>

