<?php
// ✅ Error logging için output buffering başlat
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// ✅ İlk satırlarda log ekle
error_log("=== Update Profile Request ===");
error_log("Request Method: " . ($_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN'));
error_log("Content-Type: " . ($_SERVER['CONTENT_TYPE'] ?? 'UNKNOWN'));
error_log("Content-Length: " . ($_SERVER['CONTENT_LENGTH'] ?? 'UNKNOWN'));
error_log("HTTP_COOKIE: " . ($_SERVER['HTTP_COOKIE'] ?? 'NOT SET'));

// ✅ Session cookie için önemli: output buffering başlat
ob_start();

// ✅ Multipart request'lerde cookie header'ından manuel session ID al
$manual_session_id = null;
if (isset($_SERVER['HTTP_COOKIE'])) {
    $cookie_string = $_SERVER['HTTP_COOKIE'];
    $cookies = [];
    $parts = explode(';', $cookie_string);
    foreach ($parts as $part) {
        $part = trim($part);
        if (strpos($part, '=') !== false) {
            list($key, $value) = explode('=', $part, 2);
            $cookies[trim($key)] = trim($value);
        }
    }
    
    if (isset($cookies['PHPSESSID']) && !empty($cookies['PHPSESSID'])) {
        $manual_session_id = $cookies['PHPSESSID'];
        // Session başlamadan önce ID'yi set et
        if (session_status() === PHP_SESSION_NONE) {
            session_id($manual_session_id);
            error_log("Update Profile - Manual session ID set before bootstrap: $manual_session_id");
        }
    }
}

require_once __DIR__ . '/bootstrap.php';

error_log("Bootstrap loaded successfully");
error_log("Update Profile - Session ID: " . session_id());
error_log("Update Profile - Session user_id: " . ($_SESSION['user_id'] ?? 'NULL'));
error_log("Update Profile - Session name: " . session_name());
error_log("Update Profile - Cookie headers: " . (isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : 'NOT SET'));
error_log("Update Profile - All session data: " . print_r($_SESSION, true));

// ✅ Session kontrolü - Bootstrap.php'de session başlatıldı, kontrol et
if (!isset($_SESSION['user_id'])) {
    error_log("Update Profile - ERROR: Session user_id not found!");
    error_log("Update Profile - Session ID from cookie: " . session_id());
    error_log("Update Profile - Manual session ID was: " . ($manual_session_id ?? 'NULL'));
    error_log("Update Profile - Session Status: " . (session_status() === PHP_SESSION_ACTIVE ? 'ACTIVE' : 'INACTIVE'));
    error_log("Update Profile - HTTP_COOKIE: " . ($_SERVER['HTTP_COOKIE'] ?? 'NOT SET'));
    error_log("Update Profile - All session keys: " . (empty($_SESSION) ? 'EMPTY' : implode(', ', array_keys($_SESSION))));
    
    // Session dosyasının var olup olmadığını kontrol et
    $session_file = session_save_path() . '/sess_' . session_id();
    error_log("Update Profile - Session file path: $session_file");
    error_log("Update Profile - Session file exists: " . (file_exists($session_file) ? 'YES' : 'NO'));
    
    // ✅ Eğer manual session ID varsa ama session'da user_id yoksa, session dosyasını yeniden yükle
    if ($manual_session_id !== null && session_status() === PHP_SESSION_ACTIVE) {
        error_log("Update Profile - Attempting to reload session with manual ID: $manual_session_id");
        session_write_close();
        session_id($manual_session_id);
        session_start();
        error_log("Update Profile - Session reloaded, user_id: " . ($_SESSION['user_id'] ?? 'STILL NOT SET'));
    }
    
    // ✅ Tekrar kontrol et
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Yetkiniz bulunmamaktadır. Lütfen tekrar giriş yapın.',
            'debug' => [
                'session_id' => session_id(),
                'session_status' => session_status() === PHP_SESSION_ACTIVE ? 'active' : 'inactive',
                'cookie_set' => isset($_SERVER['HTTP_COOKIE']),
                'manual_session_id' => $manual_session_id ?? 'null',
            ]
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input_user_id = $_POST['user_id'] ?? null;
        $name = $_POST['name'] ?? '';
        $surname = $_POST['surname'] ?? '';
        $email = $_POST['email'] ?? '';
        $phone = $_POST['phone'] ?? '';
        $username = $_POST['username'] ?? '';
        
        // ✅ Session'daki user_id ile kontrol et (güvenlik)
        $session_user_id = $_SESSION['user_id'];
        
        // Debug logging
        error_log("Update Profile Request - Session User ID: $session_user_id");
        error_log("Update Profile Request - Input User ID: " . ($input_user_id ?? 'null'));
        error_log("Update Profile Request - Name: $name, Email: $email, Phone: $phone, Username: $username");
        
        if (!$input_user_id) {
            throw new Exception('User ID is required');
        }
        
        // ✅ Kullanıcı sadece kendi profilini güncelleyebilir
        if ((int)$input_user_id !== (int)$session_user_id) {
            http_response_code(403);
            throw new Exception('You can only update your own profile');
        }
        
        // Validate required fields
        if (empty($name) || empty($email)) {
            throw new Exception('Name and email are required');
        }
        
        // Check if user exists
        $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE id = ?");
        $stmt->execute([$input_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Check if email is already taken by another user
        $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ? AND id != ?");
        $stmt->execute([$email, $input_user_id]);
        $existingEmail = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existingEmail) {
            throw new Exception('Email is already taken by another user');
        }
        
        // Check if username is already taken by another user
        if (!empty($username)) {
            $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE kullanici_adi = ? AND id != ?");
            $stmt->execute([$username, $input_user_id]);
            $existingUsername = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($existingUsername) {
                throw new Exception('Username is already taken by another user');
            }
        }
        
        // Handle profile image upload
        $profile_image_path = null;
        if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = dirname(__DIR__) . '/uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            $file_extension = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
            
            if (!in_array($file_extension, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only JPG, JPEG, PNG, GIF are allowed.');
            }
            
            $file_size = $_FILES['profile_image']['size'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            if ($file_size > $max_size) {
                error_log("Profile upload - File too large: $file_size bytes (max: $max_size)");
                throw new Exception('File size too large. Maximum 5MB allowed.');
            }
            
            // ✅ Dosya yazılabilirliğini kontrol et
            if (!is_writable($upload_dir)) {
                error_log("Profile upload - Upload directory not writable: $upload_dir");
                throw new Exception('Upload directory is not writable. Please contact administrator.');
            }
            
            $filename = 'profile_' . $input_user_id . '_' . time() . '.' . $file_extension;
            $file_path = $upload_dir . $filename;
            
            error_log("Profile upload - Upload dir: $upload_dir");
            error_log("Profile upload - File path: $file_path");
            error_log("Profile upload - File size: $file_size bytes");
            error_log("Profile upload - Temp file: " . $_FILES['profile_image']['tmp_name']);
            error_log("Profile upload - Temp file exists: " . (file_exists($_FILES['profile_image']['tmp_name']) ? 'YES' : 'NO'));
            error_log("Profile upload - Temp file readable: " . (is_readable($_FILES['profile_image']['tmp_name']) ? 'YES' : 'NO'));
            
            if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $file_path)) {
                // ✅ Dosyanın başarıyla yüklendiğini kontrol et
                if (!file_exists($file_path)) {
                    error_log("Profile upload - ERROR: File moved but does not exist at: $file_path");
                    throw new Exception('File upload failed: File not found after upload');
                }
                
                $profile_image_path = 'uploads/profiles/' . $filename;
                error_log("Profile upload - Success! Profile image path: $profile_image_path");
                
                // Delete old profile image if exists (only if it's a local file, not a URL)
                $stmt = $pdo->prepare("SELECT profil_fotografi FROM kullanicilar WHERE id = ?");
                $stmt->execute([$input_user_id]);
                $old_profile = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($old_profile && $old_profile['profil_fotografi']) {
                    $old_profile_path = $old_profile['profil_fotografi'];
                    
                    // ✅ Sadece yerel dosya ise sil (URL değilse)
                    // Google/Apple OAuth profil fotoğrafları URL olarak gelir, silme
                    if (strpos($old_profile_path, 'http://') !== 0 && strpos($old_profile_path, 'https://') !== 0) {
                        // Yerel dosya yolu
                        $old_file_path = dirname(__DIR__) . '/' . $old_profile_path;
                        if (file_exists($old_file_path)) {
                            unlink($old_file_path);
                            error_log("Profile upload - Old local file deleted: $old_file_path");
                        }
                    } else {
                        error_log("Profile upload - Old profile is a URL (OAuth), skipping deletion: $old_profile_path");
                    }
                }
            } else {
                $upload_error = $_FILES['profile_image']['error'];
                $error_messages = [
                    UPLOAD_ERR_INI_SIZE => 'File exceeds upload_max_filesize directive',
                    UPLOAD_ERR_FORM_SIZE => 'File exceeds MAX_FILE_SIZE directive',
                    UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                    UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                    UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                    UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                    UPLOAD_ERR_EXTENSION => 'File upload stopped by extension',
                ];
                $error_message = $error_messages[$upload_error] ?? "Unknown upload error ($upload_error)";
                error_log("Profile upload - Failed to move file. Upload error: $upload_error ($error_message)");
                error_log("Profile upload - Temp file: " . ($_FILES['profile_image']['tmp_name'] ?? 'NOT SET'));
                error_log("Profile upload - Target file: $file_path");
                error_log("Profile upload - Upload dir writable: " . (is_writable($upload_dir) ? 'YES' : 'NO'));
                throw new Exception("Failed to upload profile image: $error_message");
            }
        }
        
        // Update user data
        if ($profile_image_path) {
            $stmt = $pdo->prepare("
                UPDATE kullanicilar 
                SET ad = ?, soyad = ?, email = ?, telefon = ?, kullanici_adi = ?, profil_fotografi = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $surname, $email, $phone, $username, $profile_image_path, $input_user_id]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE kullanicilar 
                SET ad = ?, soyad = ?, email = ?, telefon = ?, kullanici_adi = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $surname, $email, $phone, $username, $input_user_id]);
        }
        
        // Get updated user data
        $stmt = $pdo->prepare("
            SELECT 
                id,
                CONCAT(ad, ' ', soyad) as name,
                email,
                telefon as phone,
                kullanici_adi as username,
                rol as role,
                profil_fotografi as profile_image
            FROM kullanicilar 
            WHERE id = ?
        ");
        $stmt->execute([$input_user_id]);
        $updated_user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$updated_user) {
            throw new Exception('Failed to retrieve updated user data');
        }
        
        // ✅ Format profile image URL
        if ($updated_user['profile_image']) {
            // Eğer zaten tam URL ise (Google/Apple OAuth), olduğu gibi kullan
            if (strpos($updated_user['profile_image'], 'http') === 0) {
                // Tam URL, değiştirme
            } else {
                // Yerel dosya yolu, tam URL'e çevir
                $updated_user['profile_image'] = 'https://dijitalsalon.cagapps.app/' . $updated_user['profile_image'];
            }
            error_log("Profile update - Final URL: " . $updated_user['profile_image']);
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'message' => 'Profile updated successfully',
            'user' => $updated_user
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    error_log("Update Profile API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
