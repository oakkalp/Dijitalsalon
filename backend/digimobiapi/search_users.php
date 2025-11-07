<?php
require_once __DIR__ . '/bootstrap.php';

// Session kontrolü
if (!isset($_SESSION['user_id'])) {
    json_err(401, 'Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $query = $_GET['q'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;

    if (empty($query)) {
        json_ok([
            'users' => [],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => 0,
                'total_pages' => 0,
                'has_more' => false
            ]
        ]);
    }

    try {
        // Kullanıcı arama sorgusu - ad, soyad, kullanıcı adı, email ile arama
        $searchTerm = '%' . $query . '%';
        
        // Toplam sayıyı al
        $count_sql = "
            SELECT COUNT(*) as total
            FROM kullanicilar 
            WHERE (
                ad LIKE ? OR 
                soyad LIKE ? OR 
                CONCAT(ad, ' ', soyad) LIKE ? OR
                kullanici_adi LIKE ? OR
                email LIKE ?
            ) 
            AND rol IN ('kullanici', 'moderator', 'super_admin')
            AND durum = 'aktif'
        ";
        
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
        $total_count = $stmt->fetch()['total'];

        // Kullanıcıları al
        $sql = "
            SELECT 
                id,
                ad,
                soyad,
                CONCAT(ad, ' ', soyad) as full_name,
                email,
                telefon as phone,
                kullanici_adi as username,
                rol as role,
                profil_fotografi as profile_image,
                created_at,
                son_giris as last_login
            FROM kullanicilar 
            WHERE (
                ad LIKE ? OR 
                soyad LIKE ? OR 
                CONCAT(ad, ' ', soyad) LIKE ? OR
                kullanici_adi LIKE ? OR
                email LIKE ?
            ) 
            AND rol IN ('kullanici', 'moderator', 'super_admin')
            AND durum = 'aktif'
            ORDER BY 
                CASE 
                    WHEN kullanici_adi LIKE ? THEN 1
                    WHEN CONCAT(ad, ' ', soyad) LIKE ? THEN 2
                    WHEN ad LIKE ? THEN 3
                    WHEN soyad LIKE ? THEN 4
                    WHEN email LIKE ? THEN 5
                    ELSE 6
                END,
                son_giris DESC
            LIMIT $offset, $limit
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm,
            $searchTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm
        ]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Kullanıcıları formatla
        $formatted_users = [];
        foreach ($users as $user) {
            $formatted_users[] = [
                'id' => $user['id'],
                'name' => $user['full_name'],
                'first_name' => $user['ad'],
                'last_name' => $user['soyad'],
                'email' => $user['email'],
                'phone' => $user['phone'],
                'username' => $user['username'],
                'role' => $user['role'],
                'profile_image' => $user['profile_image'] 
                    ? 'https://dijitalsalon.cagapps.app/' . $user['profile_image'] 
                    : null,
                'created_at' => $user['created_at'],
                'last_login' => $user['last_login'],
                'formatted_created_at' => date('d.m.Y', strtotime($user['created_at'])),
                'formatted_last_login' => $user['last_login'] 
                    ? date('d.m.Y H:i', strtotime($user['last_login'])) 
                    : 'Hiç giriş yapmamış',
                'is_online' => $user['last_login'] 
                    ? (strtotime($user['last_login']) > (time() - 3600)) // Son 1 saat içinde giriş
                    : false
            ];
        }

        json_ok([
            'users' => $formatted_users,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total_count,
                'total_pages' => ceil($total_count / $limit),
                'has_more' => $page < ceil($total_count / $limit)
            ],
            'query' => $query
        ]);

    } catch (Exception $e) {
        error_log("User Search API Error: " . $e->getMessage());
        json_err(500, 'Database error');
    }
} else {
    json_err(405, 'Method not allowed');
}
?>
