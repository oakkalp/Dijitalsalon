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
        $story_id = $input['story_id'] ?? null;
        
        if (!$story_id) {
            throw new Exception('Story ID is required');
        }
        
        // Get story info
        $story = $db->fetchOne("
            SELECT m.*, m.dugun_id, m.kullanici_id as story_owner_id, m.dosya_yolu
            FROM medyalar m
            WHERE m.id = ? AND m.tur = 'hikaye'
        ", [$story_id]);
        
        if (!$story) {
            throw new Exception('Story not found');
        }
        
        // Check authorization: own story or authorized user
        $canDelete = false;
        
        // Own story
        if ($story['story_owner_id'] == $user_id) {
            $canDelete = true;
        }
        
        // Check if user is authorized participant
        $participant = $db->fetchOne("
            SELECT dk.kullanici_id, dk.rol
            FROM dugun_katilimcilar dk
            WHERE dk.dugun_id = ? AND dk.kullanici_id = ?
        ", [$story['dugun_id'], $user_id]);
        
        // Authorized user (yetkili_kullanici, moderator, admin)
        if ($participant && in_array($participant['rol'], ['yetkili_kullanici', 'moderator', 'admin'])) {
            $canDelete = true;
        }
        
        if (!$canDelete) {
            throw new Exception('You can only delete your own stories');
        }
        
        // Delete story file
        $file_path = $story['dosya_yolu'] ?? null;
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
        
        // Delete story from database
        $db->execute("DELETE FROM medyalar WHERE id = ?", [$story_id]);
        
        // Delete story views
        $db->execute("DELETE FROM hikaye_izlenme WHERE hikaye_id = ?", [$story_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Story deleted successfully'
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        throw new Exception('Method not allowed');
    }
    
} catch (Exception $e) {
    error_log("Delete Story API Error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>