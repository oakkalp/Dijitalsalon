<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

// Check if user is logged in and is moderator
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'moderator') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = (int)($_GET['event_id'] ?? 0);

if (!$event_id) {
    header('Location: my_events_new.php');
    exit();
}

// Get event details
try {
    $stmt = $pdo->prepare("
        SELECT d.*
        FROM dugunler d
        WHERE d.id = ? AND d.moderator_id = ?
    ");
    $stmt->execute([$event_id, $user_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header('Location: my_events_new.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: my_events_new.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_permissions'])) {
        $target_user_id = (int)$_POST['user_id'];
        
        // Checkbox değerlerini al (checked olanlar 1, olmayanlar 0)
        $medya_silebilir = isset($_POST['medya_silebilir']) ? 1 : 0;
        $yorum_silebilir = isset($_POST['yorum_silebilir']) ? 1 : 0;
        $kullanici_engelleyebilir = isset($_POST['kullanici_engelleyebilir']) ? 1 : 0;
        $medya_paylasabilir = isset($_POST['medya_paylasabilir']) ? 1 : 0;
        $yorum_yapabilir = isset($_POST['yorum_yapabilir']) ? 1 : 0;
        $hikaye_paylasabilir = isset($_POST['hikaye_paylasabilir']) ? 1 : 0;
        $profil_degistirebilir = isset($_POST['profil_degistirebilir']) ? 1 : 0;
        
        try {
            $stmt = $pdo->prepare("
                UPDATE dugun_katilimcilar 
                SET medya_silebilir = ?, 
                    yorum_silebilir = ?, 
                    kullanici_engelleyebilir = ?, 
                    medya_paylasabilir = ?,
                    yorum_yapabilir = ?,
                    hikaye_paylasabilir = ?, 
                    profil_degistirebilir = ?,
                    updated_at = NOW()
                WHERE dugun_id = ? AND kullanici_id = ?
            ");
            $stmt->execute([
                $medya_silebilir, 
                $yorum_silebilir, 
                $kullanici_engelleyebilir, 
                $medya_paylasabilir,
                $yorum_yapabilir,
                $hikaye_paylasabilir, 
                $profil_degistirebilir,
                $event_id, 
                $target_user_id
            ]);
            
            $success_message = 'Kullanıcı yetkileri başarıyla güncellendi.';
        } catch (Exception $e) {
            $error_message = 'Yetkiler güncellenirken bir hata oluştu: ' . $e->getMessage();
        }
    }
}

// Get only participants for this event
try {
    $stmt = $pdo->prepare("
        SELECT 
            k.id,
            k.ad,
            k.soyad,
            k.email,
            k.profil_fotografi,
            k.rol,
            1 as is_participant,
            dk.rol as participant_role,
            bu.id as is_blocked,
            (SELECT COUNT(*) FROM medyalar m WHERE m.kullanici_id = k.id AND m.dugun_id = ?) as media_count,
            (SELECT COUNT(*) FROM yorumlar y JOIN medyalar m ON y.medya_id = m.id WHERE y.kullanici_id = k.id AND m.dugun_id = ?) as comment_count
        FROM kullanicilar k
        INNER JOIN dugun_katilimcilar dk ON k.id = dk.kullanici_id AND dk.dugun_id = ?
        LEFT JOIN blocked_users bu ON k.id = bu.blocked_user_id AND bu.dugun_id = ?
        ORDER BY k.ad, k.soyad
    ");
    $stmt->execute([$event_id, $event_id, $event_id, $event_id]);
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $users = [];
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Katılımcıları Yönet - <?php echo htmlspecialchars($event['baslik']); ?> | Digital Salon</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin: 20px;
            overflow: hidden;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            transition: all 0.2s;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        
        .user-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            border: 2px solid transparent;
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .user-card.participant {
            border-color: #28a745;
        }
        
        .user-card.blocked {
            border-color: #dc3545;
            background: #fff5f5;
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #e9ecef;
        }
        
        .btn-custom {
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .stats-row {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .stat-card {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <a href="my_events_new.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i>Geri Dön
            </a>
            <div class="page-title">
                <i class="fas fa-users me-3"></i>Katılımcıları Yönet
            </div>
            <div class="page-subtitle">
                <?php echo htmlspecialchars($event['baslik']); ?>
            </div>
        </div>
        
        <!-- Stats Row -->
        <div class="stats-row">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($users); ?></div>
                        <div class="stat-label">Katılımcı</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($users, fn($u) => $u['is_blocked'])); ?></div>
                        <div class="stat-label">Engellenen</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo array_sum(array_column($users, 'media_count')); ?></div>
                        <div class="stat-label">Toplam Medya</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo array_sum(array_column($users, 'comment_count')); ?></div>
                        <div class="stat-label">Toplam Yorum</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Users List -->
        <div class="p-3">
            <?php foreach ($users as $user): ?>
                <div class="user-card <?php echo $user['is_participant'] ? 'participant' : ''; ?> <?php echo $user['is_blocked'] ? 'blocked' : ''; ?>">
                    <div class="row align-items-center">
                        <div class="col-md-2">
                            <img src="<?php echo $user['profil_fotografi'] ?: 'assets/images/default_profile.svg'; ?>" 
                                 alt="<?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?>" 
                                 class="user-avatar">
                        </div>
                        
                        <div class="col-md-4">
                            <h6 class="mb-1"><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></h6>
                            <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
                            <div class="mt-2">
                                <span class="badge bg-success">
                                    <i class="fas fa-user-check me-1"></i>Katılımcı
                                </span>
                                
                                <?php if ($user['is_blocked']): ?>
                                    <span class="badge bg-danger ms-1">
                                        <i class="fas fa-ban me-1"></i>Engellenen
                                    </span>
                                <?php endif; ?>
                                
                                <span class="badge bg-<?php echo $user['participant_role'] === 'yetkili_kullanici' ? 'warning' : 'primary'; ?> ms-1">
                                    <?php echo $user['participant_role'] === 'yetkili_kullanici' ? 'Yetkili' : 'Normal'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="col-md-3">
                            <div class="row">
                                <div class="col-6 text-center">
                                    <div class="border rounded p-2">
                                        <i class="fas fa-image text-primary"></i><br>
                                        <small><?php echo $user['media_count']; ?> Medya</small>
                                    </div>
                                </div>
                                <div class="col-6 text-center">
                                    <div class="border rounded p-2">
                                        <i class="fas fa-comment text-info"></i><br>
                                        <small><?php echo $user['comment_count']; ?> Yorum</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-3 text-end">
                            <div class="btn-group-vertical" role="group">
                                <button class="btn btn-outline-primary btn-custom mb-1" 
                                        onclick="quickAction(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?>', <?php echo $user['is_participant'] ? 'true' : 'false'; ?>, <?php echo $user['is_blocked'] ? 'true' : 'false'; ?>)">
                                    <i class="fas fa-cog me-1"></i>Hızlı İşlem
                                </button>
                                
                                <button class="btn btn-outline-warning btn-custom" 
                                        onclick="editPermissions(<?php echo $user['id']; ?>)">
                                    <i class="fas fa-edit me-1"></i>Detaylı Düzenle
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <!-- Edit Permissions Modal -->
    <div class="modal fade" id="editPermissionsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-warning text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-cog me-2"></i>Yetkileri Düzenle
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="editPermissionsContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function quickAction(userId, userName, isParticipant, isBlocked) {
            let actionText = '';
            let confirmText = '';
            let action = '';
            
            if (isBlocked) {
                actionText = 'Engeli Kaldır';
                confirmText = `${userName} kullanıcısının engelini kaldırmak istediğinizden emin misiniz?`;
                action = 'unblock';
            } else {
                actionText = 'Katılımcılıktan Çıkar';
                confirmText = `${userName} kullanıcısını katılımcı listesinden çıkarmak istediğinizden emin misiniz?`;
                action = 'remove_participant';
            }
            
            if (confirm(confirmText)) {
                const formData = new FormData();
                formData.append('update_permissions', '1');
                formData.append('user_id', userId);
                
                if (action === 'unblock') {
                    // Unblock logic here
                    alert('Engeli kaldırma özelliği yakında eklenecek.');
                } else if (action === 'remove_participant') {
                    formData.append('participant_status', 'not_participant');
                }
                
                fetch('manage_participants.php?event_id=<?php echo $event_id; ?>', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('İşlem sırasında hata oluştu.');
                });
            }
        }
        
        function editPermissions(userId) {
            // Load user permissions via AJAX
            fetch(`ajax/get_user_permissions.php?user_id=${userId}&event_id=<?php echo $event_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const permissions = data.permissions;
                        const form = `
                            <form method="POST">
                                <input type="hidden" name="update_permissions" value="1">
                                <input type="hidden" name="user_id" value="${userId}">
                                
                                <div class="mb-3">
                                    <label class="form-label">Kullanıcı Yetkileri</label>
                                    <div class="border rounded p-3">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="medya_silebilir" id="medya_silebilir" ${permissions.medya_silebilir ? 'checked' : ''}>
                                            <label class="form-check-label" for="medya_silebilir">
                                                <i class="fas fa-image text-danger me-1"></i>Medyaları Silebilir
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="yorum_silebilir" id="yorum_silebilir" ${permissions.yorum_silebilir ? 'checked' : ''}>
                                            <label class="form-check-label" for="yorum_silebilir">
                                                <i class="fas fa-comment text-warning me-1"></i>Yorumları Silebilir
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="kullanici_engelleyebilir" id="kullanici_engelleyebilir" ${permissions.kullanici_engelleyebilir ? 'checked' : ''}>
                                            <label class="form-check-label" for="kullanici_engelleyebilir">
                                                <i class="fas fa-ban text-danger me-1"></i>Kullanıcıları Engelleyebilir
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="medya_paylasabilir" id="medya_paylasabilir" ${permissions.medya_paylasabilir ? 'checked' : ''}>
                                            <label class="form-check-label" for="medya_paylasabilir">
                                                <i class="fas fa-upload text-success me-1"></i>Medya Paylaşabilir
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="yorum_yapabilir" id="yorum_yapabilir" ${permissions.yorum_yapabilir ? 'checked' : ''}>
                                            <label class="form-check-label" for="yorum_yapabilir">
                                                <i class="fas fa-comment-dots text-info me-1"></i>Yorum Yapabilir
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" name="hikaye_paylasabilir" id="hikaye_paylasabilir" ${permissions.hikaye_paylasabilir ? 'checked' : ''}>
                                            <label class="form-check-label" for="hikaye_paylasabilir">
                                                <i class="fas fa-story text-info me-1"></i>Hikaye Paylaşabilir
                                            </label>
                                        </div>
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="profil_degistirebilir" id="profil_degistirebilir" ${permissions.profil_degistirebilir ? 'checked' : ''}>
                                            <label class="form-check-label" for="profil_degistirebilir">
                                                <i class="fas fa-user-edit text-primary me-1"></i>Profil Değiştirebilir
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                                    <button type="submit" class="btn btn-primary">Kaydet</button>
                                </div>
                            </form>
                        `;
                        
                        document.getElementById('editPermissionsContent').innerHTML = form;
                        new bootstrap.Modal(document.getElementById('editPermissionsModal')).show();
                    } else {
                        alert('Kullanıcı yetkileri yüklenirken hata oluştu.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Kullanıcı yetkileri yüklenirken hata oluştu.');
                });
        }
    </script>
</body>
</html>
