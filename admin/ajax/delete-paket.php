<?php
session_start();
require_once '../../config/database.php';

// Admin kontrolü
if (!isset($_SESSION['admin_user_id']) || $_SESSION['admin_user_role'] !== 'super_admin') {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Yetkisiz erişim']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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
    // Paket kullanılıyor mu kontrol et
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM dugunler WHERE paket_id = ?");
    $stmt->execute([$paket_id]);
    $count = $stmt->fetchColumn();

    if ($count > 0) {
        echo json_encode(['success' => false, 'message' => 'Bu paket kullanılıyor, silinemez']);
        exit;
    }

    // Paketi sil
    $stmt = $pdo->prepare("DELETE FROM paketler WHERE id = ?");
    $stmt->execute([$paket_id]);

    echo json_encode(['success' => true, 'message' => 'Paket başarıyla silindi']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


