<?php
require_once __DIR__ . '/../bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'POST') {
    json_err(405, 'Method not allowed');
}

$id_token = $_POST['id_token'] ?? '';

if (empty($id_token)) {
    json_err(400, 'ID token is required');
}

try {
    // ✅ Google ID token'ı doğrula
    // Google'ın token verification endpoint'ini kullan
    $verification_url = 'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($id_token);
    
    $ch = curl_init($verification_url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code !== 200 || empty($response)) {
        error_log("Google OAuth - Token verification failed: HTTP $http_code");
        json_err(401, 'Invalid Google token');
    }
    
    $google_user = json_decode($response, true);
    
    if (!$google_user || !isset($google_user['email'])) {
        error_log("Google OAuth - Invalid token response: " . $response);
        json_err(401, 'Invalid Google token data');
    }
    
    // ✅ Google'dan gelen bilgiler
    $email = $google_user['email'];
    $google_id = $google_user['sub']; // Google User ID
    $name = $google_user['name'] ?? '';
    $picture = $google_user['picture'] ?? null;
    
    // ✅ İsimleri ayır (name ve surname)
    $name_parts = explode(' ', $name, 2);
    $first_name = $name_parts[0] ?? '';
    $last_name = $name_parts[1] ?? '';
    
    // ✅ Kullanıcı zaten kayıtlı mı kontrol et (email veya google_id ile)
    $stmt = $pdo->prepare("
        SELECT * FROM kullanicilar 
        WHERE email = ? OR google_id = ?
    ");
    $stmt->execute([$email, $google_id]);
    $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($existing_user) {
        // ✅ Mevcut kullanıcı - google_id'yi güncelle ve giriş yap
        if (empty($existing_user['google_id'])) {
            $stmt = $pdo->prepare("UPDATE kullanicilar SET google_id = ? WHERE id = ?");
            $stmt->execute([$google_id, $existing_user['id']]);
        }
        
        // ✅ Profil fotoğrafı güncelle (Google'dan geliyorsa)
        if ($picture && empty($existing_user['profil_fotografi'])) {
            // Google profil fotoğrafı URL'ini direkt kaydet (tam URL)
            $stmt = $pdo->prepare("UPDATE kullanicilar SET profil_fotografi = ? WHERE id = ?");
            $stmt->execute([$picture, $existing_user['id']]);
            $existing_user['profil_fotografi'] = $picture;
        }
        
        $user = $existing_user;
    } else {
        // ✅ Yeni kullanıcı kaydı oluştur
        // Kullanıcı adı formatı: dijitaluser, dijitaluser1, dijitaluser2...
        $username_base = 'dijitaluser';
        
        // ✅ Benzersiz kullanıcı adı oluştur
        $username = $username_base;
        $counter = 1;
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi = ?");
        while (true) {
            $stmt->execute([$username]);
            if ($stmt->fetchColumn() == 0) break;
            $username = $username_base . $counter;
            $counter++;
            
            // Sonsuz döngüye girmemesi için limit koy
            if ($counter > 10000) {
                $username = 'dijitaluser' . substr(md5($email . time()), 0, 8);
                break;
            }
        }
        
        error_log("Google OAuth - Yeni kullanıcı için username oluşturuldu: $username (base: $username_base)");
        
        // ✅ Yeni kullanıcıyı ekle (şifre olmadan, google_id ile)
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
                    ad, soyad, email, kullanici_adi, google_id, 
                    profil_fotografi, rol, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'kullanici', NOW())
            ");
            
            // ✅ Google profil fotoğrafı URL'ini direkt kaydet (tam URL)
            $profile_image_url = $picture ? $picture : null;
            
            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $username,
                $google_id,
                $profile_image_url
            ]);
        } else {
            // sifre NOT NULL ve default yok - geçici şifre koy (kullanıcı değiştirebilir)
            $temp_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_BCRYPT);
            $stmt = $pdo->prepare("
                INSERT INTO kullanicilar (
                    ad, soyad, email, kullanici_adi, sifre, google_id, 
                    profil_fotografi, rol, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'kullanici', NOW())
            ");
            
            // ✅ Google profil fotoğrafı URL'ini direkt kaydet (tam URL)
            $profile_image_url = $picture ? $picture : null;
            
            $stmt->execute([
                $first_name,
                $last_name,
                $email,
                $username,
                $temp_password,
                $google_id,
                $profile_image_url
            ]);
        }
        
        $user_id = $pdo->lastInsertId();
        
        if (!$user_id) {
            error_log("Google OAuth - Yeni kullanıcı oluşturulamadı!");
            throw new Exception('Kullanıcı oluşturulamadı');
        }
        
        // ✅ Yeni kullanıcıyı getir
        $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            error_log("Google OAuth - Oluşturulan kullanıcı bulunamadı! User ID: $user_id");
            throw new Exception('Kullanıcı bulunamadı');
        }
        
        error_log("Google OAuth - Yeni kullanıcı oluşturuldu: ID=$user_id, Email=$email, Username=$username");
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
        'login_method' => 'google_oauth',
        'success' => true,
        'google_id' => $google_id
    ]);
    
    $stmt->execute([$user['id'], $log_details, $ip_address, $device_info, $user_agent]);
    
    // ✅ Kullanıcı bilgilerini döndür
    json_ok([
        'user' => [
            'id' => $user['id'],
            'name' => trim($user['ad'] . ' ' . $user['soyad']),
            'email' => $user['email'],
            'username' => $user['kullanici_adi'],
            'phone' => $user['telefon'],
            'role' => $user['rol'],
            'profile_image' => $user['profil_fotografi'] ? (strpos($user['profil_fotografi'], 'http') === 0 ? $user['profil_fotografi'] : 'https://dijitalsalon.cagapps.app/' . $user['profil_fotografi']) : null
        ],
        'is_new_user' => !$existing_user
    ]);
    
} catch (PDOException $e) {
    error_log("Google OAuth database error: " . $e->getMessage());
    error_log("Google OAuth SQL State: " . $e->getCode());
    json_err(500, 'Veritabanı hatası: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Google OAuth error: " . $e->getMessage());
    error_log("Google OAuth stack trace: " . $e->getTraceAsString());
    json_err(500, 'OAuth authentication failed: ' . $e->getMessage());
}
?>

