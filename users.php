<?php
/**
 * Users Management Page
 * Digital Salon - Modern Kullanıcı Yönetimi
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

// Sadece super admin erişebilir
if ($user_role !== 'super_admin') {
    header('Location: dashboard.php');
    exit();
}

$success_message = '';
$error_message = '';

// Kullanıcı ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $ad = sanitizeInput($_POST['ad'] ?? '');
    $soyad = sanitizeInput($_POST['soyad'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $sifre = $_POST['sifre'] ?? '';
    $telefon = sanitizeInput($_POST['telefon'] ?? '');
    $firma = sanitizeInput($_POST['firma'] ?? '');
    $rol = sanitizeInput($_POST['rol'] ?? 'kullanici');
    
    // Validasyon
    if (empty($ad) || empty($soyad) || empty($email) || empty($sifre)) {
        $error_message = 'Ad, soyad, email ve şifre alanları gereklidir.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Geçerli bir email adresi giriniz.';
    } elseif (strlen($sifre) < 8) {
        $error_message = 'Şifre en az 8 karakter olmalıdır.';
    } else {
        try {
            $hashed_password = password_hash($sifre, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO kullanicilar 
                (ad, soyad, email, sifre, telefon, firma, rol, durum) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'aktif')
            ");
            $stmt->execute([$ad, $soyad, $email, $hashed_password, $telefon, $firma, $rol]);
            
            $success_message = 'Kullanıcı başarıyla eklendi.';
        } catch (Exception $e) {
            $error_message = 'Kullanıcı eklenirken bir hata oluştu.';
            error_log("User add error: " . $e->getMessage());
        }
    }
}

// Kullanıcı düzenleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
    $edit_user_id = (int)$_POST['edit_user_id'];
    $ad = sanitizeInput($_POST['edit_ad'] ?? '');
    $soyad = sanitizeInput($_POST['edit_soyad'] ?? '');
    $email = sanitizeInput($_POST['edit_email'] ?? '');
    $telefon = sanitizeInput($_POST['edit_telefon'] ?? '');
    $firma = sanitizeInput($_POST['edit_firma'] ?? '');
    $rol = sanitizeInput($_POST['edit_rol'] ?? 'kullanici');
    $durum = sanitizeInput($_POST['edit_durum'] ?? 'aktif');
    $sifre = $_POST['edit_sifre'] ?? '';
    $sifre_tekrar = $_POST['edit_sifre_tekrar'] ?? '';
    
    // Şifre kontrolü
    if (!empty($sifre)) {
        if ($sifre !== $sifre_tekrar) {
            $error_message = 'Şifreler eşleşmiyor.';
        } elseif (strlen($sifre) < 8) {
            $error_message = 'Şifre en az 8 karakter olmalıdır.';
        }
    }
    
    if (empty($error_message)) {
        try {
            if (!empty($sifre)) {
                // Şifre ile güncelleme
                $hashed_password = password_hash($sifre, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("
                    UPDATE kullanicilar 
                    SET ad = ?, soyad = ?, email = ?, telefon = ?, firma = ?, rol = ?, durum = ?, sifre = ?
                    WHERE id = ?
                ");
                $stmt->execute([$ad, $soyad, $email, $telefon, $firma, $rol, $durum, $hashed_password, $edit_user_id]);
                $success_message = 'Kullanıcı ve şifre başarıyla güncellendi.';
            } else {
                // Şifre olmadan güncelleme
                $stmt = $pdo->prepare("
                    UPDATE kullanicilar 
                    SET ad = ?, soyad = ?, email = ?, telefon = ?, firma = ?, rol = ?, durum = ?
                    WHERE id = ?
                ");
                $stmt->execute([$ad, $soyad, $email, $telefon, $firma, $rol, $durum, $edit_user_id]);
                $success_message = 'Kullanıcı başarıyla güncellendi.';
            }
        } catch (Exception $e) {
            $error_message = 'Kullanıcı güncellenirken bir hata oluştu.';
            error_log("User edit error: " . $e->getMessage());
        }
    }
}

// Kullanıcı silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $delete_user_id = (int)$_POST['delete_user_id'];
    
    try {
        $stmt = $pdo->prepare("DELETE FROM kullanicilar WHERE id = ? AND id != ?");
        $stmt->execute([$delete_user_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $success_message = 'Kullanıcı başarıyla silindi.';
        } else {
            $error_message = 'Kendi hesabınızı silemezsiniz.';
        }
    } catch (Exception $e) {
        $error_message = 'Kullanıcı silinirken bir hata oluştu.';
        error_log("User delete error: " . $e->getMessage());
    }
}

// Kullanıcıları listele
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(ad LIKE ? OR soyad LIKE ? OR email LIKE ? OR firma LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($role_filter)) {
    $where_conditions[] = "rol = ?";
    $params[] = $role_filter;
}

if (!empty($status_filter)) {
    $where_conditions[] = "durum = ?";
    $params[] = $status_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

$stmt = $pdo->prepare("
    SELECT 
        id, ad, soyad, email, telefon, firma, rol, durum, 
        son_giris, created_at,
        (SELECT COUNT(*) FROM dugunler WHERE moderator_id = kullanicilar.id) as dugun_sayisi
    FROM kullanicilar 
    $where_clause
    ORDER BY created_at DESC
");
$stmt->execute($params);
$users = $stmt->fetchAll();

// İstatistikler
$total_users = $pdo->query("SELECT COUNT(*) FROM kullanicilar")->fetchColumn();
$active_users = $pdo->query("SELECT COUNT(*) FROM kullanicilar WHERE durum = 'aktif'")->fetchColumn();
$moderators = $pdo->query("SELECT COUNT(*) FROM kullanicilar WHERE rol = 'moderator'")->fetchColumn();
$normal_users = $pdo->query("SELECT COUNT(*) FROM kullanicilar WHERE rol = 'kullanici'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kullanıcı Yönetimi - Digital Salon</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-ui.css" rel="stylesheet">
    
    <style>
        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            font-family: var(--font-primary);
            color: var(--gray-800);
        }
        
        .users-container {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            padding: var(--spacing-2xl);
            margin-bottom: var(--spacing-xl);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-xl);
            margin-bottom: var(--spacing-xl);
            position: relative;
            overflow: hidden;
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
            font-family: var(--font-heading);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: var(--spacing-sm);
            position: relative;
            z-index: 1;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .stat-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-100);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-md);
            font-size: 1.5rem;
            color: var(--white);
        }
        
        .stat-icon.users { background: var(--primary-gradient); }
        .stat-icon.active { background: var(--success-gradient); }
        .stat-icon.moderators { background: var(--warning-gradient); }
        .stat-icon.normal { background: var(--info); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
        }
        
        .stat-label {
            color: var(--gray-600);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .filter-section {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .btn-add-user {
            background: var(--success-gradient);
            border: none;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 600;
            padding: var(--spacing-md) var(--spacing-lg);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
        }
        
        .btn-add-user:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
        }
        
        .user-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--spacing-lg);
            transition: all 0.3s ease;
            border: 1px solid var(--gray-100);
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .user-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: var(--primary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 1.5rem;
            font-weight: 600;
            margin-right: var(--spacing-lg);
        }
        
        .user-info h5 {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
        }
        
        .user-email {
            color: var(--gray-600);
            font-size: 0.9rem;
            margin-bottom: var(--spacing-xs);
        }
        
        .user-company {
            color: var(--primary);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .role-super_admin { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        .role-moderator { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
.role-kullanici { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-aktif { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .status-pasif { background: rgba(107, 114, 128, 0.1); color: var(--gray-500); }
        .status-engelli { background: rgba(239, 68, 68, 0.1); color: var(--danger); }
        
        .btn-action {
            padding: 0.5rem 1rem;
            border-radius: var(--radius-sm);
            font-size: 0.8rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
        }
        
        .btn-edit {
            background: var(--info);
            color: var(--white);
        }
        
        .btn-edit:hover {
            background: #2563eb;
            color: var(--white);
        }
        
        .btn-delete {
            background: var(--danger);
            color: var(--white);
        }
        
        .btn-delete:hover {
            background: #dc2626;
            color: var(--white);
        }
        
        .form-control {
            background: var(--white);
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            color: var(--gray-800);
            padding: var(--spacing-md);
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: var(--white);
            border-color: var(--primary);
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
            color: var(--gray-800);
        }
        
        /* Modal Form Fixes */
        .modal .form-control {
            background: #ffffff !important;
            border: 2px solid #d1d5db !important;
            border-radius: 8px !important;
            color: #374151 !important;
            padding: 12px !important;
            margin-bottom: 16px !important;
            transition: all 0.3s ease !important;
            pointer-events: auto !important;
            opacity: 1 !important;
            width: 100% !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
            box-sizing: border-box !important;
        }
        
        .modal .form-control:focus {
            background: #ffffff !important;
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
            color: #374151 !important;
            outline: none !important;
        }
        
        .modal .form-label {
            color: #374151 !important;
            font-weight: 500 !important;
            margin-bottom: 8px !important;
            pointer-events: auto !important;
            opacity: 1 !important;
        }
        
        .modal .btn {
            pointer-events: auto !important;
            opacity: 1 !important;
            cursor: pointer !important;
            padding: 12px 24px !important;
            border-radius: 8px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
        }
        
        .modal .btn-primary {
            background: #6366f1 !important;
            border-color: #6366f1 !important;
            color: white !important;
        }
        
        .modal .btn-primary:hover {
            background: #4f46e5 !important;
            border-color: #4f46e5 !important;
            color: white !important;
        }
        
        .modal .btn-secondary {
            background: #6b7280 !important;
            border-color: #6b7280 !important;
            color: white !important;
        }
        
        .modal .btn-secondary:hover {
            background: #4b5563 !important;
            border-color: #4b5563 !important;
            color: white !important;
        }
        
        .modal .btn-close {
            pointer-events: auto !important;
            opacity: 1 !important;
            cursor: pointer !important;
        }
        
        .modal-backdrop {
            z-index: 1040 !important;
        }
        
        .modal {
            z-index: 1050 !important;
        }
        
        .form-label {
            color: var(--gray-700);
            font-weight: 500;
            margin-bottom: var(--spacing-sm);
        }
        
        .alert {
            border-radius: var(--radius-md);
            border: none;
            margin-bottom: var(--spacing-lg);
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
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: var(--spacing-lg);
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: var(--gray-600);
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i>Ana Sayfa</a></li>
                <li class="breadcrumb-item active">Kullanıcı Yönetimi</li>
            </ol>
        </nav>
        
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-users me-3"></i>Kullanıcı Yönetimi
            </h1>
            <p class="page-subtitle">
                Sistem kullanıcılarını yönetin, yeni kullanıcılar ekleyin ve mevcut kullanıcıları düzenleyin
            </p>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon users">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo number_format($total_users); ?></div>
                <div class="stat-label">Toplam Kullanıcı</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon active">
                    <i class="fas fa-user-check"></i>
                </div>
                <div class="stat-number"><?php echo number_format($active_users); ?></div>
                <div class="stat-label">Aktif Kullanıcı</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon moderators">
                    <i class="fas fa-user-tie"></i>
                </div>
                <div class="stat-number"><?php echo number_format($moderators); ?></div>
                <div class="stat-label">Moderator</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon normal">
                    <i class="fas fa-user"></i>
                </div>
                <div class="stat-number"><?php echo number_format($normal_users); ?></div>
                <div class="stat-label">Normal Kullanıcı</div>
            </div>
        </div>
        
        <!-- Messages -->
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
        
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label for="search" class="form-label">Arama</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Ad, soyad, email veya firma ara..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label for="role" class="form-label">Rol</label>
                    <select class="form-control" id="role" name="role">
                        <option value="">Tüm Roller</option>
                        <option value="super_admin" <?php echo $role_filter === 'super_admin' ? 'selected' : ''; ?>>Super Admin</option>
                        <option value="moderator" <?php echo $role_filter === 'moderator' ? 'selected' : ''; ?>>Moderator</option>
                        <option value="yetkili_kullanici" <?php echo $role_filter === 'yetkili_kullanici' ? 'selected' : ''; ?>>Yetkili Kullanıcı</option>
                        <option value="kullanici" <?php echo $role_filter === 'kullanici' ? 'selected' : ''; ?>>Normal Kullanıcı</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Durum</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">Tüm Durumlar</option>
                        <option value="aktif" <?php echo $status_filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="pasif" <?php echo $status_filter === 'pasif' ? 'selected' : ''; ?>>Pasif</option>
                        <option value="engelli" <?php echo $status_filter === 'engelli' ? 'selected' : ''; ?>>Engelli</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary w-100" onclick="applyFilters()">
                        <i class="fas fa-search me-1"></i>Filtrele
                    </button>
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-add-user w-100" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="fas fa-plus me-1"></i>Yeni Kullanıcı
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Users List -->
        <div class="users-container">
            <div class="row">
                <?php foreach ($users as $user): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="user-card">
                            <div class="d-flex align-items-start">
                                <div class="user-avatar">
                                    <?php echo strtoupper(substr($user['ad'], 0, 1) . substr($user['soyad'], 0, 1)); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="mb-0"><?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?></h5>
                                        <span class="role-badge role-<?php echo $user['rol']; ?>">
                                            <?php 
                                            $role_names = [
                                                'super_admin' => 'Super Admin',
                                                'moderator' => 'Moderator',
                                                'kullanici' => 'Normal'
                                            ];
                                            echo $role_names[$user['rol']] ?? $user['rol'];
                                            ?>
                                        </span>
                                    </div>
                                    <div class="user-email">
                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($user['email']); ?>
                                    </div>
                                    <?php if ($user['firma']): ?>
                                        <div class="user-company">
                                            <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($user['firma']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($user['telefon']): ?>
                                        <div class="user-email">
                                            <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($user['telefon']); ?>
                                        </div>
                                    <?php endif; ?>
                                    <div class="d-flex justify-content-between align-items-center mt-3">
                                        <span class="status-badge status-<?php echo $user['durum']; ?>">
                                            <?php echo ucfirst($user['durum']); ?>
                                        </span>
                                        <div class="btn-group">
                                            <button class="btn btn-action btn-edit" onclick="editUser(<?php echo htmlspecialchars(json_encode($user)); ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <?php if ($user['id'] != $user_id): ?>
                                                <button class="btn btn-action btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($users)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-users fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">Kullanıcı bulunamadı</h4>
                    <p class="text-muted">Arama kriterlerinize uygun kullanıcı bulunmuyor.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">
                        <i class="fas fa-user-plus me-2"></i>Yeni Kullanıcı Ekle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="users.php">
                    <div class="modal-body">
                        <input type="hidden" name="add_user" value="1">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="ad" class="form-label">Ad *</label>
                                    <input type="text" class="form-control" id="ad" name="ad" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="soyad" class="form-label">Soyad *</label>
                                    <input type="text" class="form-control" id="soyad" name="soyad" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="email" name="email" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="sifre" class="form-label">Şifre *</label>
                                    <input type="password" class="form-control" id="sifre" name="sifre" required minlength="8" style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="telefon" class="form-label">Telefon</label>
                                    <input type="tel" class="form-control" id="telefon" name="telefon" style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="firma" class="form-label">Firma</label>
                                    <input type="text" class="form-control" id="firma" name="firma" style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="rol" class="form-label">Rol *</label>
                            <select class="form-control" id="rol" name="rol" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                <option value="kullanici">Normal Kullanıcı</option>
                                <?php if ($user_role === 'super_admin'): ?>
                                    <option value="moderator">Moderator</option>
                                    <option value="super_admin">Super Admin</option>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Kullanıcı Ekle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editUserModalLabel">
                        <i class="fas fa-user-edit me-2"></i>Kullanıcı Düzenle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="users.php">
                    <div class="modal-body">
                        <input type="hidden" name="edit_user" value="1">
                        <input type="hidden" name="edit_user_id" id="edit_user_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_ad" class="form-label">Ad *</label>
                                    <input type="text" class="form-control" id="edit_ad" name="edit_ad" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_soyad" class="form-label">Soyad *</label>
                                    <input type="text" class="form-control" id="edit_soyad" name="edit_soyad" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_email" class="form-label">Email *</label>
                                    <input type="email" class="form-control" id="edit_email" name="edit_email" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_telefon" class="form-label">Telefon</label>
                                    <input type="tel" class="form-control" id="edit_telefon" name="edit_telefon" style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_sifre" class="form-label">Yeni Şifre</label>
                                    <input type="password" class="form-control" id="edit_sifre" name="edit_sifre" placeholder="Değiştirmek için yeni şifre girin" style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                    <div class="form-text">Boş bırakırsanız şifre değişmez</div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_sifre_tekrar" class="form-label">Şifre Tekrar</label>
                                    <input type="password" class="form-control" id="edit_sifre_tekrar" name="edit_sifre_tekrar" placeholder="Şifreyi tekrar girin" style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_firma" class="form-label">Firma</label>
                                    <input type="text" class="form-control" id="edit_firma" name="edit_firma" style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_rol" class="form-label">Rol *</label>
                                    <select class="form-control" id="edit_rol" name="edit_rol" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                <option value="kullanici">Normal Kullanıcı</option>
                                        <?php if ($user_role === 'super_admin'): ?>
                                            <option value="moderator">Moderator</option>
                                            <option value="super_admin">Super Admin</option>
                                        <?php endif; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_durum" class="form-label">Durum *</label>
                            <select class="form-control" id="edit_durum" name="edit_durum" required style="background: white !important; border: 2px solid #ddd !important; color: black !important; padding: 10px !important;">
                                <option value="aktif">Aktif</option>
                                <option value="pasif">Pasif</option>
                                <option value="engelli">Engelli</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Değişiklikleri Kaydet
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteUserModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Kullanıcı Sil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="users.php">
                    <div class="modal-body">
                        <input type="hidden" name="delete_user" value="1">
                        <input type="hidden" name="delete_user_id" id="delete_user_id">
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Dikkat!</strong> Bu işlem geri alınamaz. Kullanıcı kalıcı olarak silinecektir.
                        </div>
                        
                        <p>Şu kullanıcıyı silmek istediğinizden emin misiniz?</p>
                        <div class="form-control" id="delete_user_name" style="background: var(--gray-100);"></div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Kullanıcıyı Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(user) {
            document.getElementById('edit_user_id').value = user.id;
            document.getElementById('edit_ad').value = user.ad;
            document.getElementById('edit_soyad').value = user.soyad;
            document.getElementById('edit_email').value = user.email;
            document.getElementById('edit_telefon').value = user.telefon || '';
            document.getElementById('edit_firma').value = user.firma || '';
            document.getElementById('edit_rol').value = user.rol;
            document.getElementById('edit_durum').value = user.durum;
            
            // Şifre alanlarını temizle
            document.getElementById('edit_sifre').value = '';
            document.getElementById('edit_sifre_tekrar').value = '';
            
            new bootstrap.Modal(document.getElementById('editUserModal')).show();
        }
        
        // Şifre eşleşme kontrolü
        function validatePasswordMatch() {
            const password = document.getElementById('edit_sifre').value;
            const confirmPassword = document.getElementById('edit_sifre_tekrar').value;
            
            if (password && confirmPassword && password !== confirmPassword) {
                document.getElementById('edit_sifre_tekrar').setCustomValidity('Şifreler eşleşmiyor');
            } else {
                document.getElementById('edit_sifre_tekrar').setCustomValidity('');
            }
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('edit_sifre').addEventListener('input', validatePasswordMatch);
            document.getElementById('edit_sifre_tekrar').addEventListener('input', validatePasswordMatch);
        });
        
        function deleteUser(userId, userName) {
            document.getElementById('delete_user_id').value = userId;
            document.getElementById('delete_user_name').textContent = userName;
            
            new bootstrap.Modal(document.getElementById('deleteUserModal')).show();
        }
        
        function applyFilters() {
            const search = document.getElementById('search').value;
            const role = document.getElementById('role').value;
            const status = document.getElementById('status').value;
            
            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (role) params.append('role', role);
            if (status) params.append('status', status);
            
            window.location.href = 'users.php?' + params.toString();
        }
        
        // Enter tuşu ile filtreleme
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
        
        // Modal aktivasyon kodları
        document.addEventListener('DOMContentLoaded', function() {
            // Add User Modal
            const addUserModal = document.getElementById('addUserModal');
            if (addUserModal) {
                addUserModal.addEventListener('shown.bs.modal', function() {
                    console.log('Add User Modal açıldı');
                    
                    const allInputs = this.querySelectorAll('input, textarea, select, button');
                    allInputs.forEach(element => {
                        element.style.pointerEvents = 'auto';
                        element.style.opacity = '1';
                        element.disabled = false;
                        element.readOnly = false;
                        
                        if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT') {
                            element.style.background = '#ffffff';
                            element.style.border = '2px solid #d1d5db';
                            element.style.color = '#374151';
                        }
                    });
                    
                    const firstInput = this.querySelector('input[type="text"]');
                    if (firstInput) {
                        setTimeout(() => {
                            firstInput.focus();
                        }, 100);
                    }
                });
            }
            
            // Edit User Modal
            const editUserModal = document.getElementById('editUserModal');
            if (editUserModal) {
                editUserModal.addEventListener('shown.bs.modal', function() {
                    console.log('Edit User Modal açıldı');
                    
                    const allInputs = this.querySelectorAll('input, textarea, select, button');
                    allInputs.forEach(element => {
                        element.style.pointerEvents = 'auto';
                        element.style.opacity = '1';
                        element.disabled = false;
                        element.readOnly = false;
                        
                        if (element.tagName === 'INPUT' || element.tagName === 'TEXTAREA' || element.tagName === 'SELECT') {
                            element.style.background = '#ffffff';
                            element.style.border = '2px solid #d1d5db';
                            element.style.color = '#374151';
                        }
                    });
                    
                    const firstInput = this.querySelector('input[type="text"]');
                    if (firstInput) {
                        setTimeout(() => {
                            firstInput.focus();
                        }, 100);
                    }
                });
            }
            
            // Delete User Modal
            const deleteUserModal = document.getElementById('deleteUserModal');
            if (deleteUserModal) {
                deleteUserModal.addEventListener('shown.bs.modal', function() {
                    console.log('Delete User Modal açıldı');
                    
                    const allButtons = this.querySelectorAll('button');
                    allButtons.forEach(button => {
                        button.style.pointerEvents = 'auto';
                        button.style.opacity = '1';
                        button.disabled = false;
                    });
                });
            }
        });
    </script>
</body>
</html>