<?php
session_start();
header('Content-Type: application/json');

try {
    // Database connection
    $pdo = new PDO('mysql:host=localhost;dbname=digitalsalon_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = (int)$_GET['user_id'];
    $event_id = (int)$_GET['event_id'];
    
    // Get user details
    $stmt = $pdo->prepare("
        SELECT 
            k.*,
            dk.rol as participant_role,
            dk.medya_silebilir,
            dk.yorum_silebilir,
            dk.kullanici_engelleyebilir,
            dk.hikaye_paylasabilir,
            dk.created_at as participant_created_at,
            bu.reason as block_reason,
            bu.created_at as blocked_at
        FROM kullanicilar k
        LEFT JOIN dugun_katilimcilar dk ON k.id = dk.kullanici_id AND dk.dugun_id = ?
        LEFT JOIN blocked_users bu ON k.id = bu.blocked_user_id AND bu.dugun_id = ?
        WHERE k.id = ?
    ");
    $stmt->execute([$event_id, $event_id, $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Kullanıcı bulunamadı.']);
        exit;
    }
    
    // Get user's medias for this event
    $stmt = $pdo->prepare("
        SELECT m.*, COUNT(ml.id) as like_count, COUNT(y.id) as comment_count
        FROM medyalar m
        LEFT JOIN medya_begeniler ml ON m.id = ml.medya_id
        LEFT JOIN yorumlar y ON m.id = y.medya_id
        WHERE m.kullanici_id = ? AND m.dugun_id = ?
        GROUP BY m.id
        ORDER BY m.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $event_id]);
    $medias = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's comments for this event
    $stmt = $pdo->prepare("
        SELECT y.*, m.dosya_yolu as media_file, COUNT(yb.id) as like_count
        FROM yorumlar y
        JOIN medyalar m ON y.medya_id = m.id
        LEFT JOIN yorum_begeniler yb ON y.id = yb.yorum_id
        WHERE y.kullanici_id = ? AND m.dugun_id = ?
        GROUP BY y.id
        ORDER BY y.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$user_id, $event_id]);
    $comments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Generate HTML
    $html = '
    <div class="row">
        <div class="col-md-4 text-center">
            <img src="' . ($user['profil_fotografi'] ?: 'assets/images/default_profile.svg') . '" 
                 alt="' . htmlspecialchars($user['ad'] . ' ' . $user['soyad']) . '" 
                 class="rounded-circle mb-3" style="width: 120px; height: 120px; object-fit: cover;">
            <h5>' . htmlspecialchars($user['ad'] . ' ' . $user['soyad']) . '</h5>
            <p class="text-muted">' . htmlspecialchars($user['email']) . '</p>
            
            <div class="mb-3">';
    
    if ($user['participant_role']) {
        $html .= '<span class="badge bg-success me-2">Katılımcı</span>';
        $html .= '<span class="badge bg-' . ($user['participant_role'] === 'yetkili_kullanici' ? 'warning' : 'primary') . '">';
        $html .= $user['participant_role'] === 'yetkili_kullanici' ? 'Yetkili' : 'Normal';
        $html .= '</span>';
    } else {
        $html .= '<span class="badge bg-secondary">Katılımcı Değil</span>';
    }
    
    if ($user['block_reason']) {
        $html .= '<br><span class="badge bg-danger mt-2">Engellenen</span>';
    }
    
    $html .= '
            </div>
            
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">Yetkiler</h6>
                </div>
                <div class="card-body">';
    
    if ($user['participant_role']) {
        $html .= '<div class="mb-2">';
        $html .= '<i class="fas fa-' . ($user['medya_silebilir'] ? 'check text-success' : 'times text-danger') . '"></i> Medya Silebilir<br>';
        $html .= '<i class="fas fa-' . ($user['yorum_silebilir'] ? 'check text-success' : 'times text-danger') . '"></i> Yorum Silebilir<br>';
        $html .= '<i class="fas fa-' . ($user['kullanici_engelleyebilir'] ? 'check text-success' : 'times text-danger') . '"></i> Kullanıcı Engelleyebilir<br>';
        $html .= '<i class="fas fa-' . ($user['hikaye_paylasabilir'] ? 'check text-success' : 'times text-danger') . '"></i> Hikaye Paylaşabilir';
        $html .= '</div>';
    } else {
        $html .= '<p class="text-muted mb-0">Katılımcı olmadığı için yetki yok.</p>';
    }
    
    $html .= '
                </div>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="row">
                <div class="col-md-6">
                    <h6><i class="fas fa-image me-2"></i>Bu Düğündeki Medyaları (' . count($medias) . ')</h6>
                    <div class="row">';
    
    if (empty($medias)) {
        $html .= '<div class="col-12 text-center text-muted"><i class="fas fa-image fa-2x mb-2"></i><p>Medya bulunamadı</p></div>';
    } else {
        foreach ($medias as $media) {
            $html .= '
                <div class="col-6 col-md-4 mb-2">
                    <div class="card">
                        <img src="' . ($media['kucuk_resim_yolu'] ?: $media['dosya_yolu']) . '" 
                             class="card-img-top" 
                             style="height: 100px; object-fit: cover;"
                             alt="Medya">
                        <div class="card-body p-2">
                            <small class="text-muted d-block">' . date('d.m.Y', strtotime($media['created_at'])) . '</small>
                            <small class="text-muted">' . $media['like_count'] . ' beğeni, ' . $media['comment_count'] . ' yorum</small>
                        </div>
                    </div>
                </div>';
        }
    }
    
    $html .= '
                    </div>
                </div>
                
                <div class="col-md-6">
                    <h6><i class="fas fa-comment me-2"></i>Bu Düğündeki Yorumları (' . count($comments) . ')</h6>
                    <div class="comments-preview" style="max-height: 300px; overflow-y: auto;">';
    
    if (empty($comments)) {
        $html .= '<div class="text-center text-muted"><i class="fas fa-comment fa-2x mb-2"></i><p>Yorum bulunamadı</p></div>';
    } else {
        foreach ($comments as $comment) {
            $html .= '
                <div class="comment-item mb-2 p-2 border rounded">
                    <div class="d-flex justify-content-between">
                        <small class="text-muted">' . date('d.m.Y H:i', strtotime($comment['created_at'])) . '</small>
                        <small class="text-muted">' . $comment['like_count'] . ' beğeni</small>
                    </div>
                    <p class="mb-0 small">' . htmlspecialchars($comment['yorum_metni']) . '</p>
                </div>';
        }
    }
    
    $html .= '
                    </div>
                </div>
            </div>
        </div>
    </div>';
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Kullanıcı detayları yüklenirken hata oluştu.'
    ]);
}
?>
