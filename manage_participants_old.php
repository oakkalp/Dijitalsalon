<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

// Check if user is logged in and is moderator
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'moderator') {
    echo "DEBUG: User not logged in or not moderator. Redirecting to login.<br>";
    echo "User ID: " . ($_SESSION['user_id'] ?? 'Not set') . "<br>";
    echo "User Role: " . ($_SESSION['user_role'] ?? 'Not set') . "<br>";
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$event_id = (int)$_GET['event_id'] ?? 0;

if (!$event_id) {
    echo "DEBUG: No event ID provided. Redirecting to my_events.php.<br>";
    echo "Event ID from GET: " . ($_GET['event_id'] ?? 'Not set') . "<br>";
    header('Location: my_events.php');
    exit();
}

// Get event details
try {
    $stmt = $pdo->prepare("
        SELECT d.*, p.baslik as paket_baslik, p.fiyat as paket_fiyati
        FROM dugunler d
        LEFT JOIN paketler p ON d.paket_id = p.id
        WHERE d.id = ? AND d.moderator_id = ?
    ");
    $stmt->execute([$event_id, $user_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        echo "DEBUG: Event not found or not owned by user. Redirecting to my_events.php.<br>";
        echo "Event ID: " . $event_id . "<br>";
        echo "User ID: " . $user_id . "<br>";
        header('Location: my_events.php');
        exit();
    }
} catch (Exception $e) {
    header('Location: my_events.php');
    exit();
}

$success_message = '';
$error_message = '';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_permissions'])) {
        $target_user_id = (int)$_POST['user_id'];
        $participant_status = $_POST['participant_status'];
        
        try {
            // Check if user has permission to manage this event
            $stmt = $pdo->prepare("SELECT id FROM dugunler WHERE id = ? AND moderator_id = ?");
            $stmt->execute([$event_id, $_SESSION['user_id']]);
            
            if ($stmt->fetch()) {
                $can_delete_media = isset($_POST['can_delete_media']) ? 1 : 0;
                $can_delete_comments = isset($_POST['can_delete_comments']) ? 1 : 0;
                $can_block_users = isset($_POST['can_block_users']) ? 1 : 0;
                $can_share_stories = isset($_POST['can_share_stories']) ? 1 : 0;
                
                if ($participant_status === 'not_participant') {
                    $stmt = $pdo->prepare("DELETE FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
                    $stmt->execute([$event_id, $target_user_id]);
                    $success_message = 'Kullanıcı katılımcı listesinden kaldırıldı.';
                } else {
                    $role = ($participant_status === 'authorized_participant') ? 'yetkili_kullanici' : 'normal_kullanici';
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO dugun_katilimcilar 
                        (dugun_id, kullanici_id, rol, medya_silebilir, yorum_silebilir, kullanici_engelleyebilir, hikaye_paylasabilir, profil_degistirebilir, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                        ON DUPLICATE KEY UPDATE 
                        rol = VALUES(rol),
                        medya_silebilir = VALUES(medya_silebilir),
                        yorum_silebilir = VALUES(yorum_silebilir),
                        kullanici_engelleyebilir = VALUES(kullanici_engelleyebilir),
                        hikaye_paylasabilir = VALUES(hikaye_paylasabilir),
                        updated_at = NOW()
                    ");
                    $stmt->execute([$event_id, $target_user_id, $role, $can_delete_media, $can_delete_comments, $can_block_users, $can_share_stories]);
                    
                    $role_text = ($role === 'yetkili_kullanici') ? 'yetkili katılımcı' : 'normal katılımcı';
                    $success_message = 'Kullanıcı ' . $role_text . ' olarak güncellendi.';
                }
            } else {
                $error_message = 'Bu düğünü yönetme yetkiniz yok.';
            }
        } catch (Exception $e) {
            $error_message = 'Yetkiler güncellenirken bir hata oluştu.';
        }
    }
    
    if (isset($_POST['block_user'])) {
        $target_user_id = (int)$_POST['user_id'];
        $block_reason = htmlspecialchars(trim($_POST['block_reason'] ?? ''), ENT_QUOTES, 'UTF-8');
        
        try {
            // Check if user has permission to manage this event
            $stmt = $pdo->prepare("SELECT id FROM dugunler WHERE id = ? AND moderator_id = ?");
            $stmt->execute([$event_id, $_SESSION['user_id']]);
            
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("
                    INSERT INTO blocked_users (dugun_id, blocked_user_id, blocked_by_user_id, reason, created_at)
                    VALUES (?, ?, ?, ?, NOW())
                    ON DUPLICATE KEY UPDATE reason = VALUES(reason)
                ");
                $stmt->execute([$event_id, $target_user_id, $_SESSION['user_id'], $block_reason]);
                $success_message = 'Kullanıcı başarıyla engellendi.';
            } else {
                $error_message = 'Bu düğünü yönetme yetkiniz yok.';
            }
        } catch (Exception $e) {
            $error_message = 'Kullanıcı engellenirken bir hata oluştu.';
        }
    }
    
    if (isset($_POST['unblock_user'])) {
        $target_user_id = (int)$_POST['user_id'];
        
        try {
            // Check if user has permission to manage this event
            $stmt = $pdo->prepare("SELECT id FROM dugunler WHERE id = ? AND moderator_id = ?");
            $stmt->execute([$event_id, $_SESSION['user_id']]);
            
            if ($stmt->fetch()) {
                $stmt = $pdo->prepare("DELETE FROM blocked_users WHERE dugun_id = ? AND blocked_user_id = ?");
                $stmt->execute([$event_id, $target_user_id]);
                $success_message = 'Kullanıcının engeli kaldırıldı.';
            } else {
                $error_message = 'Bu düğünü yönetme yetkiniz yok.';
            }
        } catch (Exception $e) {
            $error_message = 'Kullanıcı engeli kaldırılırken bir hata oluştu.';
        }
    }
}

// Get all users with their status for this event
try {
    $stmt = $pdo->prepare("
        SELECT 
            k.id,
            k.ad,
            k.soyad,
            k.email,
            k.profil_fotografi,
            k.rol,
            k.created_at as user_created_at,
            CASE 
                WHEN dk.kullanici_id IS NOT NULL THEN 1 
                ELSE 0 
            END as is_participant,
            dk.rol as participant_role,
            dk.medya_silebilir,
            dk.yorum_silebilir,
            dk.kullanici_engelleyebilir,
            dk.hikaye_paylasabilir,
            dk.created_at as participant_created_at,
            bu.id as is_blocked,
            bu.reason as block_reason,
            bu.created_at as blocked_at,
            (SELECT COUNT(*) FROM medyalar m WHERE m.kullanici_id = k.id AND m.dugun_id = ?) as media_count,
            (SELECT COUNT(*) FROM yorumlar y JOIN medyalar m ON y.medya_id = m.id WHERE y.kullanici_id = k.id AND m.dugun_id = ?) as comment_count
        FROM kullanicilar k
        LEFT JOIN dugun_katilimcilar dk ON k.id = dk.kullanici_id AND dk.dugun_id = ?
        LEFT JOIN blocked_users bu ON k.id = bu.blocked_user_id AND bu.dugun_id = ?
        ORDER BY 
            CASE WHEN dk.kullanici_id IS NOT NULL THEN 0 ELSE 1 END,
            k.ad, k.soyad
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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-ui.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
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
            position: relative;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }
        
        .page-title {
            font-family: 'Poppins', sans-serif;
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
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
        
        .filter-section {
            padding: 20px;
            background: #f8f9fa;
            border-bottom: 1px solid #e9ecef;
        }
        
        .user-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
            border: 2px solid transparent;
            cursor: pointer;
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .user-card:active {
            transform: translateY(0);
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
        
        .user-info h6 {
            margin: 0;
            font-weight: 600;
            color: #333;
        }
        
        .user-info small {
            color: #6c757d;
        }
        
        .badge-custom {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 20px;
        }
        
        .btn-custom {
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .permission-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .permission-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
        }
        
        .permission-item.active {
            background: #e7f3ff;
            color: #0066cc;
        }
        
        .search-box {
            position: relative;
        }
        
        .search-box i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        
        .search-box input {
            padding-left: 45px;
            border-radius: 25px;
            border: 2px solid #e9ecef;
        }
        
        .search-box input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        
        .filter-tabs {
            background: white;
            border-radius: 10px;
            padding: 5px;
            margin-bottom: 20px;
        }
        
        .filter-tab {
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            border: none;
            background: transparent;
            color: #6c757d;
        }
        
        .filter-tab.active {
            background: #667eea;
            color: white;
        }
        
        .media-preview {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(60px, 1fr));
            gap: 5px;
            margin-top: 10px;
        }
        
        .media-thumb {
            width: 60px;
            height: 60px;
            border-radius: 8px;
            object-fit: cover;
            border: 2px solid #e9ecef;
        }
        
        .comments-preview {
            max-height: 100px;
            overflow-y: auto;
            margin-top: 10px;
        }
        
        .comment-item {
            background: #f8f9fa;
            padding: 8px;
            border-radius: 6px;
            margin-bottom: 5px;
            font-size: 0.85rem;
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
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <a href="my_events.php" class="back-btn">
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
                        <div class="stat-number"><?php echo count(array_filter($users, fn($u) => $u['is_participant'])); ?></div>
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
        
        <!-- Quick Action Info -->
        <div class="alert alert-info m-3">
            <i class="fas fa-info-circle me-2"></i>
            <strong>Hızlı İşlem:</strong> Kullanıcı kartına tıklayarak hızlıca katılımcı yapabilir, çıkarabilir veya engelini kaldırabilirsiniz.
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" class="form-control" id="searchInput" placeholder="Kullanıcı ara...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="roleFilter">
                        <option value="">Tüm Roller</option>
                        <option value="normal_kullanici">Normal Kullanıcı</option>
                        <option value="yetkili_kullanici">Yetkili Kullanıcı</option>
                        <option value="moderator">Moderator</option>
                        <option value="super_admin">Super Admin</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <select class="form-select" id="statusFilter">
                        <option value="">Tüm Durumlar</option>
                        <option value="participant">Katılımcılar</option>
                        <option value="not_participant">Katılımcı Olmayanlar</option>
                        <option value="blocked">Engellenenler</option>
                    </select>
                </div>
            </div>
            
            <div class="filter-tabs mt-3">
                <button class="filter-tab active" data-filter="all">
                    <i class="fas fa-users me-2"></i>Tümü (<?php echo count($users); ?>)
                </button>
                <button class="filter-tab" data-filter="participant">
                    <i class="fas fa-user-check me-2"></i>Katılımcılar (<?php echo count(array_filter($users, fn($u) => $u['is_participant'])); ?>)
                </button>
                <button class="filter-tab" data-filter="not_participant">
                    <i class="fas fa-user-times me-2"></i>Katılımcı Olmayanlar (<?php echo count(array_filter($users, fn($u) => !$u['is_participant'])); ?>)
                </button>
                <button class="filter-tab" data-filter="blocked">
                    <i class="fas fa-ban me-2"></i>Engellenenler (<?php echo count(array_filter($users, fn($u) => $u['is_blocked'])); ?>)
                </button>
            </div>
        </div>
        
        <!-- Users List -->
        <div class="p-3">
            <div id="usersList">
                <?php foreach ($users as $user): ?>
                    <div class="user-card <?php echo $user['is_participant'] ? 'participant' : ''; ?> <?php echo $user['is_blocked'] ? 'blocked' : ''; ?>" 
                         data-user-id="<?php echo $user['id']; ?>"
                         data-role="<?php echo $user['rol']; ?>"
                         data-status="<?php echo $user['is_participant'] ? 'participant' : 'not_participant'; ?>"
                         data-blocked="<?php echo $user['is_blocked'] ? 'true' : 'false'; ?>"
                         onclick="quickManageUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?>', <?php echo $user['is_participant'] ? 'true' : 'false'; ?>, <?php echo $user['is_blocked'] ? 'true' : 'false'; ?>)">
                        
                        <div class="row align-items-center">
                            <div class="col-md-2">
                                <img src="<?php echo $user['profil_fotografi'] ?: 'assets/images/default_profile.svg'; ?>" 
                                     alt="<?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?>" 
                                     class="user-avatar">
                            </div>
                            
                            <div class="col-md-4">
                                <div class="user-info">
                                    <h6><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></h6>
                                    <small><?php echo htmlspecialchars($user['email']); ?></small>
                                    <div class="mt-2">
                                        <?php if ($user['is_participant']): ?>
                                            <span class="badge bg-success badge-custom">
                                                <i class="fas fa-user-check me-1"></i>Katılımcı
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary badge-custom">
                                                <i class="fas fa-user-times me-1"></i>Katılımcı Değil
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($user['is_blocked']): ?>
                                            <span class="badge bg-danger badge-custom ms-1">
                                                <i class="fas fa-ban me-1"></i>Engellenen
                                            </span>
                                        <?php endif; ?>
                                        
                                        <span class="badge bg-<?php echo $user['rol'] === 'yetkili_kullanici' ? 'warning' : 'primary'; ?> badge-custom ms-1">
                                            <?php echo $user['rol'] === 'yetkili_kullanici' ? 'Yetkili' : 'Normal'; ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="permission-grid">
                                    <div class="permission-item <?php echo $user['media_count'] > 0 ? 'active' : ''; ?>">
                                        <i class="fas fa-image"></i><br>
                                        <small><?php echo $user['media_count']; ?> Medya</small>
                                    </div>
                                    <div class="permission-item <?php echo $user['comment_count'] > 0 ? 'active' : ''; ?>">
                                        <i class="fas fa-comment"></i><br>
                                        <small><?php echo $user['comment_count']; ?> Yorum</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-3 text-end">
                                <div class="btn-group-vertical" role="group">
                                    <button class="btn btn-outline-primary btn-custom mb-1" 
                                            onclick="event.stopPropagation(); showUserDetail(<?php echo $user['id']; ?>)">
                                        <i class="fas fa-eye me-1"></i>Detaylar
                                    </button>
                                    
                                    <?php if ($user['is_participant']): ?>
                                        <button class="btn btn-outline-warning btn-custom mb-1" 
                                                onclick="event.stopPropagation(); editPermissions(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-cog me-1"></i>Yetkileri Düzenle
                                        </button>
                                    <?php endif; ?>
                                    
                                    <?php if ($user['is_blocked']): ?>
                                        <button class="btn btn-outline-success btn-custom" 
                                                onclick="event.stopPropagation(); unblockUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-unlock me-1"></i>Engeli Kaldır
                                        </button>
                                    <?php else: ?>
                                        <button class="btn btn-outline-danger btn-custom" 
                                                onclick="event.stopPropagation(); blockUser(<?php echo $user['id']; ?>)">
                                            <i class="fas fa-ban me-1"></i>Engelle
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- User Detail Modal -->
    <div class="modal fade" id="userDetailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-user me-2"></i>Kullanıcı Detayları
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="userDetailContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
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
        // Search functionality
        document.getElementById('searchInput').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const userCards = document.querySelectorAll('.user-card');
            
            userCards.forEach(card => {
                const userName = card.querySelector('.user-info h6').textContent.toLowerCase();
                const userEmail = card.querySelector('.user-info small').textContent.toLowerCase();
                
                if (userName.includes(searchTerm) || userEmail.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Role filter
        document.getElementById('roleFilter').addEventListener('change', function() {
            const selectedRole = this.value;
            const userCards = document.querySelectorAll('.user-card');
            
            userCards.forEach(card => {
                const userRole = card.getAttribute('data-role');
                
                if (selectedRole === '' || userRole === selectedRole) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Status filter
        document.getElementById('statusFilter').addEventListener('change', function() {
            const selectedStatus = this.value;
            const userCards = document.querySelectorAll('.user-card');
            
            userCards.forEach(card => {
                const userStatus = card.getAttribute('data-status');
                const isBlocked = card.getAttribute('data-blocked') === 'true';
                
                if (selectedStatus === '' || 
                    (selectedStatus === 'participant' && userStatus === 'participant') ||
                    (selectedStatus === 'not_participant' && userStatus === 'not_participant') ||
                    (selectedStatus === 'blocked' && isBlocked)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        });
        
        // Filter tabs
        document.querySelectorAll('.filter-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                document.querySelectorAll('.filter-tab').forEach(t => t.classList.remove('active'));
                // Add active class to clicked tab
                this.classList.add('active');
                
                const filter = this.getAttribute('data-filter');
                const userCards = document.querySelectorAll('.user-card');
                
                userCards.forEach(card => {
                    const userStatus = card.getAttribute('data-status');
                    const isBlocked = card.getAttribute('data-blocked') === 'true';
                    
                    if (filter === 'all' ||
                        (filter === 'participant' && userStatus === 'participant') ||
                        (filter === 'not_participant' && userStatus === 'not_participant') ||
                        (filter === 'blocked' && isBlocked)) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
            });
        });
        
        function quickManageUser(userId, userName, isParticipant, isBlocked) {
            // Stop event propagation to prevent modal opening
            event.stopPropagation();
            
            let actionText = '';
            let confirmText = '';
            let action = '';
            
            if (isBlocked) {
                actionText = 'Engeli Kaldır';
                confirmText = `${userName} kullanıcısının engelini kaldırmak istediğinizden emin misiniz?`;
                action = 'unblock';
            } else if (isParticipant) {
                actionText = 'Katılımcılıktan Çıkar';
                confirmText = `${userName} kullanıcısını katılımcı listesinden çıkarmak istediğinizden emin misiniz?`;
                action = 'remove_participant';
            } else {
                actionText = 'Katılımcı Yap';
                confirmText = `${userName} kullanıcısını katılımcı yapmak istediğinizden emin misiniz?`;
                action = 'add_participant';
            }
            
            if (confirm(confirmText)) {
                const formData = new FormData();
                
                if (action === 'unblock') {
                    formData.append('unblock_user', '1');
                    formData.append('user_id', userId);
                } else if (action === 'remove_participant') {
                    formData.append('update_permissions', '1');
                    formData.append('user_id', userId);
                    formData.append('participant_status', 'not_participant');
                } else if (action === 'add_participant') {
                    formData.append('update_permissions', '1');
                    formData.append('user_id', userId);
                    formData.append('participant_status', 'normal_participant');
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
        
        function showUserDetail(userId) {
            // Load user details via AJAX
            fetch(`ajax/get_user_detail.php?user_id=${userId}&event_id=<?php echo $event_id; ?>`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('userDetailContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('userDetailModal')).show();
                    } else {
                        alert('Kullanıcı detayları yüklenirken hata oluştu.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Kullanıcı detayları yüklenirken hata oluştu.');
                });
        }
        
        function editPermissions(userId) {
            console.log('editPermissions called with userId:', userId);
            
            // Load edit permissions form via AJAX
            fetch(`ajax/test_edit_permissions.php?user_id=${userId}&event_id=<?php echo $event_id; ?>`)
                .then(response => {
                    console.log('Response status:', response.status);
                    return response.json();
                })
                .then(data => {
                    console.log('Response data:', data);
                    if (data.success) {
                        document.getElementById('editPermissionsContent').innerHTML = data.html;
                        new bootstrap.Modal(document.getElementById('editPermissionsModal')).show();
                    } else {
                        alert('Yetki düzenleme formu yüklenirken hata oluştu.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Yetki düzenleme formu yüklenirken hata oluştu.');
                });
        }
        
        function blockUser(userId) {
            const reason = prompt('Bu kullanıcıyı engelleme sebebi (isteğe bağlı):');
            if (reason !== null) {
                const formData = new FormData();
                formData.append('block_user', '1');
                formData.append('user_id', userId);
                formData.append('block_reason', reason);
                
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
                    alert('Kullanıcı engellenirken hata oluştu.');
                });
            }
        }
        
        function unblockUser(userId) {
            if (confirm('Bu kullanıcının engelini kaldırmak istediğinizden emin misiniz?')) {
                const formData = new FormData();
                formData.append('unblock_user', '1');
                formData.append('user_id', userId);
                
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
                    alert('Kullanıcı engeli kaldırılırken hata oluştu.');
                });
            }
        }
    </script>
</body>
</html>
