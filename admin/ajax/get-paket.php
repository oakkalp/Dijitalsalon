<?php
session_start();
require_once '../../config/database.php';

// Admin kontrolü
if (!isset($_SESSION['admin_user_id']) || $_SESSION['admin_user_role'] !== 'super_admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
    exit;
}

$paket_id = (int)($_GET['id'] ?? 0);

if (!$paket_id) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz paket ID']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM paketler WHERE id = ?");
    $stmt->execute([$paket_id]);
    $paket = $stmt->fetch();

    if (!$paket) {
        echo json_encode(['success' => false, 'message' => 'Paket bulunamadı']);
        exit;
    }

    echo json_encode(['success' => true, 'data' => $paket]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


