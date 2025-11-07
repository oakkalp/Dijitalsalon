<?php
require_once __DIR__ . '/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $field = $_POST['field'] ?? '';
    $value = $_POST['value'] ?? '';
    $current_user_id = $_POST['current_user_id'] ?? null; // Edit mode için

    if (empty($field) || empty($value)) {
        json_err(400, 'Field and value are required');
    }

    try {
        $available = true;
        $message = '';

        switch ($field) {
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $available = false;
                    $message = 'Geçerli bir e-posta adresi girin';
                } else {
                    $sql = "SELECT COUNT(*) FROM kullanicilar WHERE email = ?";
                    $params = [$value];
                    
                    if ($current_user_id !== null) {
                        $sql .= " AND id != ?";
                        $params[] = $current_user_id;
                    }
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $count = $stmt->fetchColumn();
                    
                    $available = $count == 0;
                    $message = $available ? 'E-posta kullanılabilir' : 'Bu e-posta adresi zaten kullanılıyor';
                }
                break;

            case 'phone':
                if (strlen($value) != 10 || !preg_match('/^5[0-9]{9}$/', $value)) {
                    $available = false;
                    $message = 'Geçerli bir telefon numarası girin (5XXXXXXXXX)';
                } else {
                    $sql = "SELECT COUNT(*) FROM kullanicilar WHERE telefon = ?";
                    $params = [$value];
                    
                    if ($current_user_id !== null) {
                        $sql .= " AND id != ?";
                        $params[] = $current_user_id;
                    }
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $count = $stmt->fetchColumn();
                    
                    $available = $count == 0;
                    $message = $available ? 'Telefon numarası kullanılabilir' : 'Bu telefon numarası zaten kullanılıyor';
                }
                break;

            case 'username':
                if (strlen($value) < 3) {
                    $available = false;
                    $message = 'Kullanıcı adı en az 3 karakter olmalıdır';
                } else {
                    $sql = "SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi = ?";
                    $params = [$value];
                    
                    if ($current_user_id !== null) {
                        $sql .= " AND id != ?";
                        $params[] = $current_user_id;
                    }
                    
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute($params);
                    $count = $stmt->fetchColumn();
                    
                    $available = $count == 0;
                    $message = $available ? 'Kullanıcı adı kullanılabilir' : 'Bu kullanıcı adı zaten kullanılıyor';
                }
                break;

            default:
                json_err(400, 'Invalid field type');
        }

        json_ok([
            'available' => $available,
            'message' => $message,
            'field' => $field,
            'value' => $value
        ]);

    } catch (Exception $e) {
        error_log("Field Check API Error: " . $e->getMessage());
        json_err(500, 'Database error');
    }
} else {
    json_err(405, 'Method not allowed');
}
?>
