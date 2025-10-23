<?php
// Direct database connection without bootstrap
$host = 'localhost';
$dbname = 'digitalsalon_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    echo "Checking for orphaned replies...\n";
    
    // Find replies where parent comment doesn't exist
    $stmt = $pdo->query("
        SELECT r.id, r.parent_comment_id, r.yorum_metni as content, r.created_at
        FROM yorumlar r 
        LEFT JOIN yorumlar p ON r.parent_comment_id = p.id 
        WHERE r.parent_comment_id IS NOT NULL 
        AND p.id IS NULL
    ");
    
    $orphaned = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "Found " . count($orphaned) . " orphaned replies:\n";
    
    if (count($orphaned) > 0) {
        foreach ($orphaned as $reply) {
            echo "ID: " . $reply['id'] . 
                 ", Parent: " . $reply['parent_comment_id'] . 
                 ", Content: " . substr($reply['content'], 0, 50) . "..." . 
                 ", Date: " . $reply['created_at'] . "\n";
        }
        
        echo "\nDeleting orphaned replies...\n";
        
        // Delete orphaned replies
        $deleteStmt = $pdo->prepare("
            DELETE FROM yorumlar 
            WHERE id IN (
                SELECT r.id FROM (
                    SELECT r.id
                    FROM yorumlar r 
                    LEFT JOIN yorumlar p ON r.parent_comment_id = p.id 
                    WHERE r.parent_comment_id IS NOT NULL 
                    AND p.id IS NULL
                ) as r
            )
        ");
        
        $deleteStmt->execute();
        $deletedCount = $deleteStmt->rowCount();
        
        echo "Deleted " . $deletedCount . " orphaned replies.\n";
        
        // Also delete any likes on orphaned replies
        $deleteLikesStmt = $pdo->prepare("
            DELETE FROM yorum_begeniler 
            WHERE yorum_id IN (
                SELECT r.id FROM (
                    SELECT r.id
                    FROM yorumlar r 
                    LEFT JOIN yorumlar p ON r.parent_comment_id = p.id 
                    WHERE r.parent_comment_id IS NOT NULL 
                    AND p.id IS NULL
                ) as r
            )
        ");
        
        $deleteLikesStmt->execute();
        $deletedLikesCount = $deleteLikesStmt->rowCount();
        
        echo "Deleted " . $deletedLikesCount . " likes on orphaned replies.\n";
        
    } else {
        echo "No orphaned replies found.\n";
    }
    
    // Check current comment counts for media
    echo "\nCurrent comment counts:\n";
    $mediaStmt = $pdo->query("
        SELECT m.id, m.dugun_id, 
               COUNT(y.id) as total_comments,
               COUNT(CASE WHEN y.parent_comment_id IS NULL THEN 1 END) as main_comments,
               COUNT(CASE WHEN y.parent_comment_id IS NOT NULL THEN 1 END) as replies
        FROM medyalar m
        LEFT JOIN yorumlar y ON m.id = y.medya_id
        GROUP BY m.id
        HAVING total_comments > 0
        ORDER BY m.id
    ");
    
    $mediaCounts = $mediaStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($mediaCounts as $media) {
        echo "Media ID: " . $media['id'] . 
             ", Event: " . $media['dugun_id'] . 
             ", Total: " . $media['total_comments'] . 
             ", Main: " . $media['main_comments'] . 
             ", Replies: " . $media['replies'] . "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>