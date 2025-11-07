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

$paket_id = (int)($_POST['paket_id'] ?? 0);
$ad = trim($_POST['ad'] ?? '');
$fiyat = (float)($_POST['fiyat'] ?? 0);
$aciklama = trim($_POST['aciklama'] ?? '');
$sure_ay = (int)($_POST['sure_ay'] ?? 0);
$maksimum_katilimci = (int)($_POST['maksimum_katilimci'] ?? 0);
$medya_limiti = (int)($_POST['medya_limiti'] ?? 0);
$ucretsiz_erisim_gun = (int)($_POST['ucretsiz_erisim_gun'] ?? 0);

if (!$paket_id || !$ad || !$fiyat || !$sure_ay || !$maksimum_katilimci || !$medya_limiti || !$ucretsiz_erisim_gun) {
    echo json_encode(['success' => false, 'message' => 'Tüm gerekli alanları doldurun']);
    exit;
}

try {
    $stmt = $pdo->prepare("
        UPDATE paketler
        SET ad = ?, fiyat = ?, aciklama = ?, sure_ay = ?, maksimum_katilimci = ?, medya_limiti = ?, ucretsiz_erisim_gun = ?
        WHERE id = ?
    ");
    $stmt->execute([$ad, $fiyat, $aciklama, $sure_ay, $maksimum_katilimci, $medya_limiti, $ucretsiz_erisim_gun, $paket_id]);

    echo json_encode(['success' => true, 'message' => 'Paket başarıyla güncellendi']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

