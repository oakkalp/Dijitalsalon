<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Start session
session_start();

// Include fast database connection
require_once 'fast_db.php';

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    $db = FastDB::getInstance();
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        throw new Exception('Unauthorized - Please login');
    }
    
    $user_id = $_SESSION['user_id'];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        $media_id = $input['media_id'] ?? null;
        
        // Debug logging
        error_log("Delete Media Request - Media ID: " . ($media_id ?? 'null'));
        error_log("Delete Media Request - Input: " . json_encode($input));
        
        if (!$media_id) {
            throw new Exception('Media ID is required');
        }
        
        // Get media info
        $media = $db->fetchOne("
            SELECT m.*, m.dugun_id, m.kullanici_id as media_owner_id, m.dosya_yolu
            FROM medyalar m
            WHERE m.id = ? AND m.tur IN ('foto', 'video', 'fotograf')
        ", [$media_id]);
        
        // Debug logging
        error_log("Delete Media Query - Media ID: $media_id");
        error_log("Delete Media Query - Found Media: " . ($media ? 'YES' : 'NO'));
        if ($media) {
            error_log("Delete Media Query - Media Type: " . ($media['tur'] ?? 'null'));
            error_log("Delete Media Query - Media Owner: " . ($media['media_owner_id'] ?? 'null'));
        }
        
        if (!$media) {
            throw new Exception('Media not found');
        }
        
        // Check authorization: own media or authorized user
        $canDelete = false;
        
        // Own media
        if ($media['media_owner_id'] == $user_id) {
            $canDelete = true;
        }
        
        // Check if user is authorized participant
        $participant = $db->fetchOne("
            SELECT dk.kullanici_id, dk.rol
            FROM dugun_katilimcilar dk
            WHERE dk.dugun_id = ? AND dk.kullanici_id = ?
        ", [$media['dugun_id'], $user_id]);
        
        // Authorized user (yetkili_kullanici, moderator, admin)
        if ($participant && in_array($participant['rol'], ['yetkili_kullanici', 'moderator', 'admin'])) {
            $canDelete = true;
        }
        
        if (!$canDelete) {
            throw new Exception('You can only delete your own media');
        }
        
        // Delete media file
        $file_path = $media['dosya_yolu'] ?? null;
        if ($file_path && file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete thumbnail if exists
        if ($file_path) {
            $thumbnail_path = str_replace('/uploads/', '/uploads/thumb_', $file_path);
            if ($thumbnail_path && file_exists($thumbnail_path)) {
                unlink($thumbnail_path);
            }
        }
        
        // Delete media from database
        $db->execute("DELETE FROM medyalar WHERE id = ?", [$media_id]);
        
        // Delete media likes
        $db->execute("DELETE FROM begeniler WHERE medya_id = ?", [$media_id]);
        
        // Delete media comments
        $db->execute("DELETE FROM yorumlar WHERE medya_id = ?", [$media_id]);
        
        // Delete comment likes for this media's comments
        $db->execute("DELETE FROM yorum_begeniler WHERE yorum_id IN (SELECT id FROM yorumlar WHERE medya_id = ?)", [$media_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Media deleted successfully'
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    error_log("Delete Media API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>