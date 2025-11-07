<?php
require_once __DIR__ . '/bootstrap.php';

// Sadece super_admin kullanıcıları erişebilir
if (!isset($_SESSION['user_id'])) {
    json_err(401, 'Unauthorized');
}

// Kullanıcı rolünü kontrol et
$stmt = $pdo->prepare("SELECT rol FROM kullanicilar WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user || $user['rol'] !== 'super_admin') {
    json_err(403, 'Access denied. Super admin required.');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $action = $_GET['action'] ?? 'all';
    $user_id = $_GET['user_id'] ?? null;
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 50);
    $offset = ($page - 1) * $limit;

    try {
        $where_conditions = [];
        $params = [];

        if ($action !== 'all') {
            $where_conditions[] = "ul.action = ?";
            $params[] = $action;
        }

        if ($user_id !== null) {
            $where_conditions[] = "ul.user_id = ?";
            $params[] = $user_id;
        }

        $where_clause = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Toplam kayıt sayısını al
        $count_sql = "
            SELECT COUNT(*) as total
            FROM user_logs ul
            JOIN kullanicilar k ON ul.user_id = k.id
            $where_clause
        ";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total_count = $stmt->fetch()['total'];

        // Logları al
        $sql = "
            SELECT 
                ul.id,
                ul.user_id,
                ul.action,
                ul.details,
                ul.ip_address,
                ul.device_info,
                ul.user_agent,
                ul.created_at,
                CONCAT(k.ad, ' ', k.soyad) as user_name,
                k.email,
                k.telefon,
                k.kullanici_adi
            FROM user_logs ul
            JOIN kullanicilar k ON ul.user_id = k.id
            $where_clause
            ORDER BY ul.created_at DESC
            LIMIT $offset, $limit
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Logları formatla
        $formatted_logs = [];
        foreach ($logs as $log) {
            $details = json_decode($log['details'], true) ?? [];
            
            $formatted_logs[] = [
                'id' => $log['id'],
                'user_id' => $log['user_id'],
                'user_name' => $log['user_name'],
                'user_email' => $log['email'],
                'user_phone' => $log['telefon'],
                'user_username' => $log['kullanici_adi'],
                'action' => $log['action'],
                'action_text' => _getActionText($log['action']),
                'details' => $details,
                'ip_address' => $log['ip_address'],
                'device_info' => $log['device_info'],
                'user_agent' => $log['user_agent'],
                'created_at' => $log['created_at'],
                'formatted_time' => date('d.m.Y H:i:s', strtotime($log['created_at']))
            ];
        }

        json_ok([
            'logs' => $formatted_logs,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total_count,
                'total_pages' => ceil($total_count / $limit),
                'has_more' => $page < ceil($total_count / $limit)
            ]
        ]);

    } catch (Exception $e) {
        error_log("Get Logs API Error: " . $e->getMessage());
        json_err(500, 'Database error');
    }
} else {
    json_err(405, 'Method not allowed');
}

function _getActionText($action) {
    $actions = [
        'register' => 'Kayıt Olma',
        'login' => 'Giriş Yapma',
        'logout' => 'Çıkış Yapma',
        'profile_update' => 'Profil Güncelleme',
        'password_change' => 'Şifre Değiştirme',
        'media_upload' => 'Medya Yükleme',
        'media_delete' => 'Medya Silme',
        'story_upload' => 'Hikaye Yükleme',
        'story_delete' => 'Hikaye Silme',
        'comment_add' => 'Yorum Ekleme',
        'comment_delete' => 'Yorum Silme',
        'like' => 'Medya Beğenme',
        'unlike' => 'Medya Beğeniyi Geri Alma',
        'story_like' => 'Hikaye Beğenme',
        'story_unlike' => 'Hikaye Beğeniyi Geri Alma',
        'event_join' => 'Etkinliğe Katılma',
        'event_leave' => 'Etkinlikten Ayrılma',
        'profile_visit' => 'Profil Ziyareti'
    ];
    
    return $actions[$action] ?? ucfirst($action);
}
?>
