<?php
/**
 * Assign User Page
 * Digital Salon - Kullanıcıya yetki verme sayfası
 */

require_once 'config/database.php';
require_once 'includes/security.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Sadece super admin ve moderator erişebilir
if (!in_array($user_role, ['super_admin', 'moderator'])) {
    header('Location: dashboard.php');
    exit();
}

$success_message = '';
$error_message = '';

// Kullanıcıya yetki verme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_user'])) {
    $assign_user_id = (int)$_POST['assign_user_id'];
    $event_id = (int)$_POST['assign_event_id'];
    $permissions = $_POST['permissions'] ?? [];
    
    try {
        // Kullanıcının zaten bu düğünde katılımcı olup olmadığını kontrol et
        $stmt = $pdo->prepare("SELECT id FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
        $stmt->execute([$event_id, $assign_user_id]);
        
        if ($stmt->fetch()) {
            // Zaten katılımcı, yetkileri güncelle
            $stmt = $pdo->prepare("
                UPDATE dugun_katilimcilar 
                SET rol = 'yetkili_kullanici',
                    medya_silebilir = ?,
                    yorum_silebilir = ?,
                    kullanici_engelleyebilir = ?,
                    hikaye_paylasabilir = ?,
                    katilim_tarihi = NOW()
                WHERE dugun_id = ? AND kullanici_id = ?
            ");
            $stmt->execute([
                in_array('medya_silebilir', $permissions) ? 1 : 0,
                in_array('yorum_silebilir', $permissions) ? 1 : 0,
                in_array('kullanici_engelleyebilir', $permissions) ? 1 : 0,
                in_array('hikaye_paylasabilir', $permissions) ? 1 : 0,
                $event_id,
                $assign_user_id
            ]);
        } else {
            // Yeni katılımcı olarak ekle
            $stmt = $pdo->prepare("
                INSERT INTO dugun_katilimcilar 
                (dugun_id, kullanici_id, rol, medya_silebilir, yorum_silebilir, kullanici_engelleyebilir, hikaye_paylasabilir, katilim_tarihi) 
                VALUES (?, ?, 'yetkili_kullanici', ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $event_id,
                $assign_user_id,
                in_array('medya_silebilir', $permissions) ? 1 : 0,
                in_array('yorum_silebilir', $permissions) ? 1 : 0,
                in_array('kullanici_engelleyebilir', $permissions) ? 1 : 0,
                in_array('hikaye_paylasabilir', $permissions) ? 1 : 0
            ]);
        }
        
        $success_message = 'Kullanıcıya yetki başarıyla verildi.';
        
    } catch (Exception $e) {
        $error_message = 'Yetki verilirken bir hata oluştu.';
        error_log("Assign user error: " . $e->getMessage());
    }
}

// Kullanıcı bilgilerini çek
$assign_user_id = (int)($_GET['user_id'] ?? 0);
if ($assign_user_id) {
    $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE id = ?");
    $stmt->execute([$assign_user_id]);
    $assign_user = $stmt->fetch();
    
    if (!$assign_user) {
        header('Location: users.php');
        exit();
    }
} else {
    header('Location: users.php');
    exit();
}

// Düğünleri listele
$events_stmt = $pdo->prepare("
    SELECT d.*, k.ad as moderator_ad, k.soyad as moderator_soyad 
    FROM dugunler d 
    JOIN kullanicilar k ON d.moderator_id = k.id 
    WHERE d.durum = 'aktif'
    ORDER BY d.created_at DESC
");
$events_stmt->execute();
$events = $events_stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcıya Yetki Ver - Digital Salon</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-ui.css" rel="stylesheet">
    
    <style>
        /* Global Styles */
        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            font-family: var(--font-primary);
            color: var(--gray-800);
        }
        
        .assign-container {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            padding: var(--spacing-2xl);
            margin-bottom: var(--spacing-xl);
        }
        
        .assign-header {
            text-align: center;
            margin-bottom: var(--spacing-xl);
            color: var(--gray-800);
        }
        
        .assign-header h1 {
            font-family: var(--font-heading);
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: var(--spacing-sm);
            color: var(--gray-800);
        }
        
        .assign-header p {
            color: var(--gray-600);
            font-size: 0.9rem;
        }
        
        .form-control {
            background: var(--white);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            color: var(--gray-800);
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-md);
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: var(--white);
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            color: var(--gray-800);
        }
        
        .form-label {
            color: var(--gray-700);
            font-weight: 500;
            margin-bottom: var(--spacing-sm);
        }
        
        .btn-assign {
            background: var(--warning-gradient);
            border: none;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 600;
            padding: var(--spacing-md) var(--spacing-xl);
            transition: all 0.3s ease;
        }
        
        .btn-assign:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
        }
        
        .btn-back {
            background: var(--gray-500);
            border: none;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 600;
            padding: var(--spacing-md) var(--spacing-xl);
            transition: all 0.3s ease;
        }
        
        .btn-back:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
        }
        
        .alert {
            border-radius: var(--radius-md);
            border: none;
            margin-bottom: var(--spacing-md);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .form-check {
            margin-bottom: var(--spacing-sm);
        }
        
        .form-check-input {
            margin-right: var(--spacing-sm);
        }
        
        .form-check-label {
            color: var(--gray-700);
            font-weight: 500;
        }
        
        .user-info {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .user-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: var(--secondary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: var(--white);
            margin: 0 auto var(--spacing-md);
        }
        
        .user-name {
            font-family: var(--font-heading);
            font-size: 1.5rem;
            font-weight: 600;
            text-align: center;
            margin-bottom: var(--spacing-sm);
            color: var(--gray-800);
        }
        
        .user-email {
            text-align: center;
            color: var(--gray-600);
            margin-bottom: var(--spacing-md);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-5 flex-grow-1">
        <div class="assign-container">
            <div class="assign-header">
                <h1><i class="fas fa-user-plus me-2"></i>Kullanıcıya Yetki Ver</h1>
                <p>Kullanıcıya düğün yetkisi verin</p>
            </div>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <!-- User Info -->
            <div class="user-info">
                <div class="user-avatar">
                    <i class="fas fa-user"></i>
                </div>
                <div class="user-name"><?php echo htmlspecialchars($assign_user['ad'] . ' ' . $assign_user['soyad']); ?></div>
                <div class="user-email"><?php echo htmlspecialchars($assign_user['email']); ?></div>
                <?php if ($assign_user['firma']): ?>
                    <div class="text-center text-muted"><?php echo htmlspecialchars($assign_user['firma']); ?></div>
                <?php endif; ?>
            </div>
            
            <!-- Assign Form -->
            <form method="POST" action="assign_user.php">
                <input type="hidden" name="assign_user" value="1">
                <input type="hidden" name="assign_user_id" value="<?php echo $assign_user_id; ?>">
                
                <div class="mb-4">
                    <label for="assign_event_id" class="form-label">
                        <i class="fas fa-calendar-check me-2"></i>Düğün Seçin *
                    </label>
                    <select class="form-control" id="assign_event_id" name="assign_event_id" required>
                        <option value="">Düğün seçin</option>
                        <?php foreach ($events as $event): ?>
                            <option value="<?php echo $event['id']; ?>">
                                <?php echo htmlspecialchars($event['baslik']); ?> - <?php echo htmlspecialchars($event['moderator_ad'] . ' ' . $event['moderator_soyad']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label class="form-label">
                        <i class="fas fa-key me-2"></i>Yetkiler
                    </label>
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="medya_silebilir" id="medya_silebilir">
                                <label class="form-check-label" for="medya_silebilir">
                                    <i class="fas fa-trash me-2"></i>Medya Silebilir
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="yorum_silebilir" id="yorum_silebilir">
                                <label class="form-check-label" for="yorum_silebilir">
                                    <i class="fas fa-comment-slash me-2"></i>Yorum Silebilir
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="kullanici_engelleyebilir" id="kullanici_engelleyebilir">
                                <label class="form-check-label" for="kullanici_engelleyebilir">
                                    <i class="fas fa-user-slash me-2"></i>Kullanıcı Engelleyebilir
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="permissions[]" value="hikaye_paylasabilir" id="hikaye_paylasabilir">
                                <label class="form-check-label" for="hikaye_paylasabilir">
                                    <i class="fas fa-story me-2"></i>Hikaye Paylaşabilir
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="text-center">
                    <a href="users.php" class="btn btn-back me-3">
                        <i class="fas fa-arrow-left me-2"></i>Geri Dön
                    </a>
                    <button type="submit" class="btn btn-assign">
                        <i class="fas fa-user-plus me-2"></i>Yetki Ver
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                const eventSelect = document.getElementById('assign_event_id');
                const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
                let hasPermission = false;
                
                checkboxes.forEach(checkbox => {
                    if (checkbox.checked) {
                        hasPermission = true;
                    }
                });
                
                if (!eventSelect.value) {
                    e.preventDefault();
                    alert('Lütfen bir düğün seçin.');
                    return false;
                }
                
                if (!hasPermission) {
                    e.preventDefault();
                    alert('Lütfen en az bir yetki seçin.');
                    return false;
                }
            });
        });
    </script>
</body>
</html>
