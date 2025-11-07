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

$user_id = (int)($_POST['user_id'] ?? 0);
$ad = trim($_POST['ad'] ?? '');
$soyad = trim($_POST['soyad'] ?? '');
$email = trim($_POST['email'] ?? '');
$telefon = trim($_POST['telefon'] ?? '');
$rol = $_POST['rol'] ?? '';

if (!$user_id || !$ad || !$soyad || !$email || !$rol) {
    echo json_encode(['success' => false, 'message' => 'Tüm gerekli alanları doldurun']);
    exit;
}

// Email doğrulama
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => 'Geçerli bir e-posta adresi girin']);
    exit;
}

// Rol doğrulama
$allowed_roles = ['kullanici', 'yetkili_kullanici', 'moderator', 'super_admin'];
if (!in_array($rol, $allowed_roles)) {
    echo json_encode(['success' => false, 'message' => 'Geçersiz rol']);
    exit;
}

try {
    // Kullanıcı var mı kontrol et
    $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE id = ?");
    $stmt->execute([$user_id]);
    
    if (!$stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Kullanıcı bulunamadı']);
        exit;
    }

    // E-posta kontrolü (kendi e-postası hariç)
    $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ? AND id != ?");
    $stmt->execute([$email, $user_id]);
    
    if ($stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor']);
        exit;
    }

    // Kullanıcıyı güncelle
    $stmt = $pdo->prepare("
        UPDATE kullanicilar
        SET ad = ?, soyad = ?, email = ?, telefon = ?, rol = ?
        WHERE id = ?
    ");
    $stmt->execute([$ad, $soyad, $email, $telefon, $rol, $user_id]);

    echo json_encode(['success' => true, 'message' => 'Kullanıcı başarıyla güncellendi']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>


