<?php
/**
 * Add Reply AJAX Handler
 * Digital Salon - Yorum yanıtı ekleme işlemi
 */

session_start();

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=localhost;dbname=digitalsalon_db;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Veritabanı bağlantı hatası: ' . $e->getMessage());
}

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);
$parent_comment_id = $input['parent_comment_id'] ?? null;
$content = trim($input['content'] ?? '');

if (!$parent_comment_id || !$content) {
    echo json_encode(['success' => false, 'message' => 'Parent comment ID and content are required']);
    exit;
}

if (strlen($content) > 500) {
    echo json_encode(['success' => false, 'message' => 'Reply is too long (max 500 characters)']);
    exit;
}

try {
    $pdo->beginTransaction();

    // Get media_id from parent comment
    $stmt = $pdo->prepare("SELECT medya_id FROM yorumlar WHERE id = ?");
    $stmt->execute([$parent_comment_id]);
    $parent_comment = $stmt->fetch();
    
    if (!$parent_comment) {
        throw new Exception('Parent comment not found');
    }
    
    // Insert reply
    $stmt = $pdo->prepare("INSERT INTO yorumlar (medya_id, kullanici_id, yorum_metni, parent_comment_id, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$parent_comment['medya_id'], $user_id, $content, $parent_comment_id]);

    $pdo->commit();
    echo json_encode(['success' => true, 'message' => 'Reply added successfully']);

} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Add reply error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Add reply error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>
