<?php
require_once __DIR__ . '/bootstrap.php';

// Session kontrolü
if (!isset($_SESSION['user_id'])) {
    json_err(401, 'Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $target_user_id = $_GET['user_id'] ?? null;
    
    if (empty($target_user_id)) {
        json_err(400, 'User ID is required');
    }
    
    try {
        // Hedef kullanıcının bilgilerini al
        $stmt = $pdo->prepare("
            SELECT 
                id,
                CONCAT(ad, ' ', soyad) as name,
                email,
                telefon as phone,
                kullanici_adi as username,
                rol as role,
                profil_fotografi as profile_image,
                created_at
            FROM kullanicilar
            WHERE id = ? AND durum = 'aktif'
        ");
        $stmt->execute([$target_user_id]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            json_err(404, 'User not found');
        }
        
        // ✅ Profil resmi URL'sini düzelt
        if ($user['profile_image']) {
            // Eğer zaten tam URL ise (Google/Apple OAuth), olduğu gibi kullan
            if (strpos($user['profile_image'], 'http') === 0) {
                // Tam URL, değiştirme
            } else {
                // Yerel dosya yolu, tam URL'e çevir
                $user['profile_image'] = 'https://dijitalsalon.cagapps.app/' . $user['profile_image'];
            }
        }
        
        // ✅ Log profile visit (sadece kendi profili değilse)
        $visitor_id = $_SESSION['user_id'];
        if ($visitor_id != $target_user_id) {
            try {
                $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
                
                $log_details = json_encode([
                    'visited_user_id' => (int)$target_user_id,
                    'visited_user_name' => $user['name'],
                    'created_at' => date('Y-m-d H:i:s')
                ]);
                
                $stmt = $pdo->prepare("
                    INSERT INTO user_logs (
                        user_id, action, details, ip_address, device_info, user_agent, created_at
                    ) VALUES (?, 'profile_visit', ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$visitor_id, $log_details, $ip_address, '', $user_agent]);
            } catch (Exception $e) {
                // Log hatası ana işlemi engellemesin
                error_log("Profile visit log error: " . $e->getMessage());
            }
        }
        
        json_ok(['user' => $user]);
        
    } catch (Exception $e) {
        error_log("Get User By ID API Error: " . $e->getMessage());
        json_err(500, 'Database error');
    }
} else {
    json_err(405, 'Method not allowed');
}
?>
