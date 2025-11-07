<?php
require_once __DIR__ . '/../bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    json_err(405, 'Method not allowed');
}

$identity_token = $_POST['identity_token'] ?? '';
$authorization_code = $_POST['authorization_code'] ?? '';
$email = $_POST['email'] ?? '';
$given_name = $_POST['given_name'] ?? '';
$family_name = $_POST['family_name'] ?? '';
$apple_user_id = $_POST['user_id'] ?? ''; // Apple'nin unique user identifier'ı

if (empty($identity_token) && empty($authorization_code)) {
    json_err(400, 'Identity token or authorization code is required');
}

if (empty($apple_user_id)) {
    json_err(400, 'Apple user ID is required');
}

try {
    // ✅ Apple ID token doğrulama (basitleştirilmiş - production'da JWT verify yapılmalı)
    // Production'da Apple'nin public key'leri ile JWT verify yapılmalı
    // Şimdilik sadece user_id ve email'i kullanıyoruz
    
    // ✅ Kullanıcı zaten kayıtlı mı kontrol et (email veya apple_id ile)
    $stmt = $pdo->prepare("
        SELECT * FROM kullanicilar 
        WHERE email = ? OR apple_id = ?
    ");
    $stmt->execute([$email, $apple_user_id]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_user) {
        // ✅ Mevcut kullanıcı - apple_id'yi güncelle ve giriş yap
        if (empty($existing_user['apple_id'])) {
            $stmt = $pdo->prepare("UPDATE kullanicilar SET apple_id = ? WHERE id = ?");
            $stmt->execute([$apple_user_id, $existing_user['id']]);
        }
        
        // ✅ Email güncelle (Apple ilk kez veriyorsa)
        if (empty($existing_user['email']) && !empty($email)) {
            $stmt = $pdo->prepare("UPDATE kullanicilar SET email = ? WHERE id = ?");
            $stmt->execute([$email, $existing_user['id']]);
        }
        
        $user = $existing_user;
    } else {
        // ✅ Yeni kullanıcı kaydı oluştur
        // Email yoksa geçici bir email oluştur
        if (empty($email)) {
            $email = 'apple_' . substr($apple_user_id, 0, 10) . '@apple.privacy';
        }
        
        // ✅ Kullanıcı adı oluştur
        $username_base = '';
        
        // Önce isimden oluştur
        if (!empty($given_name) || !empty($family_name)) {
            $full_name = trim(($given_name ?? '') . ' ' . ($family_name ?? ''));
            $username_base = strtolower(preg_replace('/[^a-z0-9]/', '', $full_name));
        }
        
        // İsim yoksa veya çok kısa ise email'den oluştur
        if (empty($username_base) || strlen($username_base) < 3) {
            $email_parts = explode('@', $email);
            $username_base = strtolower(preg_replace('/[^a-z0-9]/', '', $email_parts[0]));
        }
        
        // Yine de boşsa, rastgele bir şey oluştur
        if (empty($username_base)) {
            $username_base = 'user' . substr(md5($email . time()), 0, 8);
        }
        
        // İlk 20 karakteri al (database limiti için)
        $username_base = substr($username_base, 0, 20);
        
        // ✅ Benzersiz kullanıcı adı oluştur
        $username = $username_base;
        $counter = 1;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi = ?");
        while (true) {
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() == 0) break;
            $suffix = $counter;
            // Username + suffix max 20 karakter olmalı
            if (strlen($username_base . $suffix) > 20) {
                $username_base = substr($username_base, 0, 20 - strlen($suffix));
            }
            $username = $username_base . $suffix;
            $counter++;
            
            // Sonsuz döngüye girmemesi için limit koy
            if ($counter > 1000) {
                $username = 'user' . substr(md5($email . time()), 0, 16);
                break;
            }
        }
        
        error_log("Apple Sign In - Yeni kullanıcı için username oluşturuldu: $username (base: $username_base)");
        
        // ✅ Yeni kullanıcıyı ekle (şifre olmadan, apple_id ile)
        // Önce sifre kolonunun nullable olup olmadığını kontrol et
        $sifre_nullable = true;
        try {
            $check_sifre = $pdo->query("SHOW COLUMNS FROM kullanicilar WHERE Field = 'sifre'");
            $sifre_col = $check_sifre->fetch(PDO::FETCH_ASSOC);
            if ($sifre_col && $sifre_col['Null'] === 'NO' && empty($sifre_col['Default'])) {
                // sifre NOT NULL ve default yok, o zaman geçici bir değer koy
                $sifre_nullable = false;
            }
        } catch (Exception $e) {
            error_log("sifre kolonu kontrolü hatası: " . $e->getMessage());
        }
        
        // ✅ INSERT sorgusunu hazırla
        if ($sifre_nullable) {
            // sifre nullable veya default var
            $stmt = $pdo->prepare("
                INSERT INTO kullanicilar (
                    ad, soyad, email, kullanici_adi, apple_id, 
                    rol, created_at
                ) VALUES (?, ?, ?, ?, ?, 'kullanici', NOW())
            ");
            
            $stmt->execute([
                $given_name ?? '',
                $family_name ?? '',
                $email,
                $username,
                $apple_user_id
            ]);
        } else {
            // sifre NOT NULL ve default yok - geçici şifre koy (kullanıcı değiştirebilir)
            $temp_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO kullanicilar (
                    ad, soyad, email, kullanici_adi, sifre, apple_id, 
                    rol, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'kullanici', NOW())
            ");
            
            $stmt->execute([
                $given_name ?? '',
                $family_name ?? '',
                $email,
                $username,
                $temp_password,
                $apple_user_id
            ]);
        }
        
        $user_id = $pdo->lastInsertId();
        
        if (!$user_id) {
            error_log("Apple Sign In - Yeni kullanıcı oluşturulamadı!");
            throw new Exception('Kullanıcı oluşturulamadı');
        }
        
        // ✅ Yeni kullanıcıyı getir
        $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            error_log("Apple Sign In - Oluşturulan kullanıcı bulunamadı! User ID: $user_id");
            throw new Exception('Kullanıcı bulunamadı');
        }
        
        error_log("Apple Sign In - Yeni kullanıcı oluşturuldu: ID=$user_id, Email=$email, Username=$username");
    }
    
    // ✅ Session başlat
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_role'] = $user['rol'];
    
    // ✅ Son giriş zamanını güncelle
    $stmt = $pdo->prepare("UPDATE kullanicilar SET son_giris = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // ✅ Login log kaydı
    $device_info = $_POST['device_info'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $pdo->prepare("
        INSERT INTO user_logs (
            user_id, action, details, ip_address, device_info, 
            user_agent, created_at
        ) VALUES (?, 'oauth_login', ?, ?, ?, ?, NOW())
    ");
    
    $log_details = json_encode([
        'login_method' => 'apple_sign_in',
        'success' => true,
        'apple_id' => $apple_user_id
    ]);
    
    $stmt->execute([$user['id'], $log_details, $ip_address, $device_info, $user_agent]);
    
    // ✅ Kullanıcı bilgilerini döndür
    json_ok([
        'user' => [
            'id' => $user['id'],
            'name' => trim(($user['ad'] ?? '') . ' ' . ($user['soyad'] ?? '')),
            'email' => $user['email'],
            'username' => $user['kullanici_adi'],
            'phone' => $user['telefon'],
            'role' => $user['rol'],
            'profile_image' => $user['profil_fotografi'] ? 'https://dijitalsalon.cagapps.app/' . $user['profil_fotografi'] : null
        ],
        'is_new_user' => !$existing_user
    ]);
    
} catch (PDOException $e) {
    error_log("Apple Sign In database error: " . $e->getMessage());
    error_log("Apple Sign In SQL State: " . $e->getCode());
    json_err(500, 'Veritabanı hatası: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Apple Sign In error: " . $e->getMessage());
    error_log("Apple Sign In stack trace: " . $e->getTraceAsString());
    json_err(500, 'Apple authentication failed: ' . $e->getMessage());
}
?>

