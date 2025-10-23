<?php
session_start();
require_once 'config/database.php';

// Kullanıcı giriş kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'kullanici';

// Event ID'yi al
$event_id = $_GET['event_id'] ?? null;

if (!$event_id) {
    header('Location: dashboard.php');
    exit();
}

try {
    // Event bilgilerini al
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            p.paket_adi,
            p.ozellikler,
            k.ad as moderator_ad,
            k.soyad as moderator_soyad,
            k.profil_fotografi as moderator_profil
        FROM dugunler d
        LEFT JOIN paketler p ON d.paket_id = p.id
        LEFT JOIN kullanicilar k ON d.moderator_id = k.id
        WHERE d.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header('Location: dashboard.php');
        exit();
    }
    
    // Kullanıcının bu event'e katılım durumunu kontrol et
    $stmt = $pdo->prepare("SELECT rol FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
    $stmt->execute([$event_id, $user_id]);
    $participant = $stmt->fetch();
    
    if (!$participant) {
        header('Location: dashboard.php');
        exit();
    }
    
    // Event medyalarını al
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            k.ad as user_name,
            k.soyad as user_surname,
            k.profil_fotografi as user_profile
        FROM medyalar m
        LEFT JOIN kullanicilar k ON m.kullanici_id = k.id
        WHERE m.dugun_id = ? AND m.tur != 'hikaye'
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$event_id]);
    $event_media = $stmt->fetchAll();
    
    // Event istatistiklerini al
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT m.id) as total_media,
            COUNT(DISTINCT b.id) as total_likes,
            COUNT(DISTINCT y.id) as total_comments,
            COUNT(DISTINCT dk.kullanici_id) as total_participants
        FROM dugunler d
        LEFT JOIN medyalar m ON d.id = m.dugun_id AND m.tur != 'hikaye'
        LEFT JOIN begeniler b ON m.id = b.medya_id
        LEFT JOIN yorumlar y ON m.id = y.medya_id AND y.durum = 'aktif'
        LEFT JOIN dugun_katilimcilar dk ON d.id = dk.dugun_id
        WHERE d.id = ?
    ");
    $stmt->execute([$event_id]);
    $stats = $stmt->fetch();
    
} catch (PDOException $e) {
    error_log("Event profile error: " . $e->getMessage());
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['baslik']); ?> - Digital Salon</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #e91e63;
            --secondary-color: #f8f9fa;
            --text-dark: #262626;
            --text-light: #8e8e8e;
            --border-color: #dbdbdb;
        }
        
        body {
            background-color: #fafafa;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Instagram Profile Header */
        .profile-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 30px 0;
        }
        
        .profile-info {
            display: flex;
            align-items: center;
            gap: 30px;
            max-width: 935px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid var(--border-color);
        }
        
        .profile-details h1 {
            font-size: 28px;
            font-weight: 300;
            margin: 0 0 20px 0;
            color: var(--text-dark);
        }
        
        .profile-stats {
            display: flex;
            gap: 40px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            text-align: center;
        }
        
        .stat-number {
            font-size: 18px;
            font-weight: 600;
            color: var(--text-dark);
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-light);
        }
        
        .profile-description {
            color: var(--text-dark);
            font-size: 16px;
            line-height: 1.5;
        }
        
        .profile-description p {
            margin: 0 0 10px 0;
        }
        
        .profile-description p:last-child {
            margin-bottom: 0;
        }
        
        /* Instagram Style Media Grid */
        .media-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 3px;
            max-width: 935px;
            margin: 0 auto;
            padding: 0 20px;
        }
        
        .media-item {
            position: relative;
            aspect-ratio: 1;
            cursor: pointer;
            overflow: hidden;
        }
        
        .media-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }
        
        .media-item:hover img {
            transform: scale(1.05);
        }
        
        .media-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.3);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        
        .media-item:hover .media-overlay {
            opacity: 1;
        }
        
        .media-stats {
            display: flex;
            gap: 20px;
            color: white;
            font-size: 16px;
            font-weight: 600;
        }
        
        .media-stats i {
            margin-right: 5px;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: var(--text-light);
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 20px;
            color: var(--border-color);
        }
        
        .empty-state h3 {
            font-size: 24px;
            font-weight: 300;
            margin-bottom: 10px;
        }
        
        .empty-state p {
            font-size: 16px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .profile-info {
                flex-direction: column;
                text-align: center;
                gap: 20px;
            }
            
            .profile-avatar {
                width: 100px;
                height: 100px;
            }
            
            .profile-stats {
                justify-content: center;
            }
            
            .media-grid {
                grid-template-columns: repeat(2, 1fr);
                gap: 2px;
            }
        }
        
        @media (max-width: 480px) {
            .media-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-info">
            <img src="<?php echo htmlspecialchars($event['profil_fotografi'] ?: 'assets/images/default_event.png'); ?>" 
                 alt="<?php echo htmlspecialchars($event['baslik']); ?>" 
                 class="profile-avatar">
            
            <div class="profile-details">
                <h1><?php echo htmlspecialchars($event['baslik']); ?></h1>
                
                <div class="profile-stats">
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_media']; ?></div>
                        <div class="stat-label">Medya</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_participants']; ?></div>
                        <div class="stat-label">Katılımcı</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-number"><?php echo $stats['total_likes']; ?></div>
                        <div class="stat-label">Beğeni</div>
                    </div>
                </div>
                
                <div class="profile-description">
                    <p><strong><?php echo htmlspecialchars($event['baslik']); ?></strong></p>
                    <?php if ($event['aciklama']): ?>
                        <p><?php echo nl2br(htmlspecialchars($event['aciklama'])); ?></p>
                    <?php endif; ?>
                    <?php if ($event['tarih']): ?>
                        <p><i class="fas fa-calendar-alt"></i> <?php echo date('d.m.Y', strtotime($event['tarih'])); ?></p>
                    <?php endif; ?>
                    <?php if ($event['moderator_ad']): ?>
                        <p><i class="fas fa-user"></i> Moderator: <?php echo htmlspecialchars($event['moderator_ad'] . ' ' . $event['moderator_soyad']); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Media Grid -->
    <div class="container-fluid">
        <?php if (!empty($event_media)): ?>
            <div class="media-grid">
                <?php foreach ($event_media as $media): ?>
                    <div class="media-item" onclick="openMediaModal(<?php echo $media['id']; ?>, '<?php echo htmlspecialchars($media['dosya_yolu']); ?>', '<?php echo $media['tur']; ?>')">
                        <img src="<?php echo htmlspecialchars($media['kucuk_resim_yolu'] ?: $media['dosya_yolu']); ?>" 
                             alt="<?php echo htmlspecialchars($media['aciklama'] ?: 'Medya'); ?>">
                        
                        <div class="media-overlay">
                            <div class="media-stats">
                                <span><i class="fas fa-heart"></i> <?php echo $media['begeni_sayisi'] ?? 0; ?></span>
                                <span><i class="fas fa-comment"></i> <?php echo $media['yorum_sayisi'] ?? 0; ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-images"></i>
                <h3>Henüz medya yok</h3>
                <p>Bu düğüne henüz fotoğraf veya video yüklenmemiş.</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Back Button -->
    <div class="position-fixed" style="top: 20px; left: 20px; z-index: 1000;">
        <a href="user_dashboard.php?event_id=<?php echo $event_id; ?>" class="btn btn-light btn-sm">
            <i class="fas fa-arrow-left"></i> Geri
        </a>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Media modal functionality (same as user_dashboard.php)
        function openMediaModal(mediaId, mediaSrc, mediaType) {
            console.log('Opening media modal for:', mediaId, mediaSrc, mediaType);
            
            const modalHtml = `
                <div class="modal fade" id="mediaModal" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-xl">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-image me-2"></i>Medya Görüntüleyici
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="media-display">
                                            ${mediaType === 'fotograf' ? 
                                                `<img src="${mediaSrc}" class="img-fluid" alt="Medya">` : 
                                                `<video src="${mediaSrc}" class="img-fluid" controls></video>`
                                            }
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="comments-section">
                                            <h6><i class="fas fa-comments me-2"></i>Yorumlar</h6>
                                            <div id="comments-list-${mediaId}" class="comments-list">
                                                <div class="text-center">
                                                    <div class="spinner-border spinner-border-sm" role="status">
                                                        <span class="visually-hidden">Yükleniyor...</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="add-comment mt-3">
                                                <textarea class="form-control" id="comment-text-${mediaId}" 
                                                          placeholder="Yorumunuzu yazın..." rows="3"></textarea>
                                                <button class="btn btn-primary mt-2 w-100" onclick="addComment(${mediaId})">
                                                    <i class="fas fa-paper-plane me-2"></i>Yorum Yap
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            const modal = new bootstrap.Modal(document.getElementById('mediaModal'));
            modal.show();
            
            loadComments(mediaId);
            
            document.getElementById('mediaModal').addEventListener('hidden.bs.modal', function() {
                this.remove();
            });
        }
        
        function loadComments(mediaId) {
            fetch(`ajax/get_comments.php?media_id=${mediaId}`)
                .then(response => response.json())
                .then(data => {
                    const commentsList = document.getElementById(`comments-list-${mediaId}`);
                    commentsList.innerHTML = '';
                    
                    if (data.comments && data.comments.length > 0) {
                        data.comments.forEach(comment => {
                            const commentDiv = document.createElement('div');
                            commentDiv.className = 'comment-item mb-3 p-3 bg-light rounded';
                            commentDiv.innerHTML = `
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <strong class="text-primary">${comment.user_name}</strong>
                                    <small class="text-muted">${comment.created_at}</small>
                                </div>
                                <div class="comment-content">${comment.content}</div>
                            `;
                            commentsList.appendChild(commentDiv);
                        });
                    } else {
                        commentsList.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-comment-slash me-2"></i>Henüz yorum yok. İlk yorumu siz yapın!</div>';
                    }
                })
                .catch(error => {
                    console.error('Error loading comments:', error);
                    const commentsList = document.getElementById(`comments-list-${mediaId}`);
                    commentsList.innerHTML = '<div class="text-center text-danger py-3"><i class="fas fa-exclamation-triangle me-2"></i>Yorumlar yüklenirken hata oluştu.</div>';
                });
        }
        
        function addComment(mediaId) {
            const commentText = document.getElementById(`comment-text-${mediaId}`).value;
            
            if (!commentText.trim()) {
                alert('Lütfen bir yorum yazın.');
                return;
            }
            
            fetch('ajax/add_comment.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    media_id: mediaId,
                    content: commentText
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    document.getElementById(`comment-text-${mediaId}`).value = '';
                    loadComments(mediaId);
                } else {
                    alert('Yorum eklenirken hata oluştu: ' + (data.message || 'Bilinmeyen hata'));
                }
            })
            .catch(error => {
                console.error('Error adding comment:', error);
                alert('Yorum eklenirken hata oluştu.');
            });
        }
    </script>
</body>
</html>
