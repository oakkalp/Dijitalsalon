<?php
session_start();

// Admin giriş kontrolü
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';

$admin_user_id = $_SESSION['admin_user_id'];
$admin_user_role = $_SESSION['admin_user_role'];

// Sadece super_admin ve moderator erişebilir
if (!in_array($admin_user_role, ['super_admin', 'moderator'])) {
    header('Location: dashboard.php');
    exit;
}

$event_id = (int)($_GET['id'] ?? 0);
if (!$event_id) {
    header('Location: events.php');
    exit;
}

$success_message = '';
$error_message = '';

// Event bilgilerini al
try {
    if ($admin_user_role === 'super_admin') {
        // Super Admin - Tüm etkinliklere erişebilir
        $stmt = $pdo->prepare("
            SELECT d.*, k.ad, k.soyad 
            FROM dugunler d
            LEFT JOIN kullanicilar k ON d.moderator_id = k.id
            WHERE d.id = ?
        ");
        $stmt->execute([$event_id]);
        $event = $stmt->fetch();
    } else {
        // Moderator - Sadece kendi etkinliklerine erişebilir
        $stmt = $pdo->prepare("
            SELECT d.*, k.ad, k.soyad 
            FROM dugunler d
            LEFT JOIN kullanicilar k ON d.moderator_id = k.id
            WHERE d.id = ? AND d.moderator_id = ?
        ");
        $stmt->execute([$event_id, $admin_user_id]);
        $event = $stmt->fetch();
    }
    
    if (!$event) {
        header('Location: events.php');
        exit;
    }
} catch (Exception $e) {
    $error_message = 'Event bilgileri alınırken hata oluştu';
}

// Katılımcıları al
try {
    // ✅ Önce duplicate'leri bul ve temizle (agresif yöntem)
    $duplicate_check = $pdo->prepare("
        SELECT kullanici_id, GROUP_CONCAT(id ORDER BY id ASC) as all_ids
        FROM dugun_katilimcilar
        WHERE dugun_id = ?
        GROUP BY kullanici_id
        HAVING COUNT(*) > 1
    ");
    $duplicate_check->execute([$event_id]);
    $duplicates = $duplicate_check->fetchAll();
    
    $deleted_count = 0;
    foreach ($duplicates as $dup) {
        $ids = explode(',', $dup['all_ids']);
        $keep_id = $ids[0]; // İlk ID'yi tut
        array_shift($ids); // İlk ID'yi çıkar
        
        // Diğer tüm ID'leri tek tek sil
        foreach ($ids as $delete_id) {
            $cleanup_stmt = $pdo->prepare("DELETE FROM dugun_katilimcilar WHERE id = ?");
            $cleanup_stmt->execute([$delete_id]);
            $deleted_count += $cleanup_stmt->rowCount();
        }
    }
    
    if ($deleted_count > 0) {
        error_log("🗑️ Deleted $deleted_count duplicate participant records for event $event_id");
    }
    
    // ✅ Şimdi katılımcıları al
    $stmt = $pdo->prepare("
        SELECT dk.*, k.ad, k.soyad, k.email, k.telefon, k.kullanici_adi, k.profil_fotografi
        FROM dugun_katilimcilar dk
        LEFT JOIN kullanicilar k ON dk.kullanici_id = k.id
        WHERE dk.dugun_id = ?
        ORDER BY dk.katilim_tarihi DESC
    ");
    $stmt->execute([$event_id]);
    $participants = $stmt->fetchAll();
    
    // ✅ NULL veya boş yetkileri otomatik güncelle (one-time fix)
    foreach ($participants as $index => $participant) {
        if (empty($participant['yetkiler']) || $participant['yetkiler'] === 'null') {
            // Normal kullanıcılar için default yetkiler ver
            if ($participant['rol'] === 'kullanici') {
                $default_permissions = json_encode([
                    'medya_paylasabilir' => true,
                    'yorum_yapabilir' => true,
                    'hikaye_paylasabilir' => true,
                ]);
                
                $update_stmt = $pdo->prepare("
                    UPDATE dugun_katilimcilar 
                    SET yetkiler = ? 
                    WHERE id = ?
                ");
                $update_stmt->execute([$default_permissions, $participant['id']]);
                
                // Participant array'ini de güncelle
                $participants[$index]['yetkiler'] = $default_permissions;
            }
        }
    }
    unset($participant); // ✅ Referans temizliği
} catch (Exception $e) {
    $error_message = 'Katılımcılar alınırken hata oluştu';
    $participants = [];
}

// Yetkili kullanıcı atama işlemi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'assign_role') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        $new_role = $_POST['new_role'] ?? '';
        
        if ($user_id && in_array($new_role, ['yetkili_kullanici', 'kullanici'])) {
            try {
                // Yetki kullanıcı ise seçilen checkbox'lardan yetkileri al
                if ($new_role === 'yetkili_kullanici') {
                    $selected_permissions = $_POST['permissions'] ?? [];
                    
                    // ✅ Flutter ile uyumlu yetki isimleri kullan - Sadece TRUE olanları sakla
                    $permissions = [];
                    
                    if (in_array('medya_paylasabilir', $selected_permissions)) $permissions['medya_paylasabilir'] = true;
                    if (in_array('medya_silebilir', $selected_permissions)) $permissions['medya_silebilir'] = true;
                    if (in_array('hikaye_paylasabilir', $selected_permissions)) $permissions['hikaye_paylasabilir'] = true;
                    if (in_array('yorum_yapabilir', $selected_permissions)) $permissions['yorum_yapabilir'] = true;
                    if (in_array('yorum_silebilir', $selected_permissions)) $permissions['yorum_silebilir'] = true;
                    if (in_array('kullanici_engelleyebilir', $selected_permissions)) $permissions['kullanici_engelleyebilir'] = true;
                    if (in_array('yetki_duzenleyebilir', $selected_permissions)) $permissions['yetki_duzenleyebilir'] = true;
                    if (in_array('bildirim_gonderebilir', $selected_permissions)) $permissions['bildirim_gonderebilir'] = true;
                    
                    $permissions_json = json_encode($permissions);
                } else {
                    // Normal kullanıcı ise default yetkiler ver
                    $permissions_json = json_encode([
                        'medya_paylasabilir' => true,
                        'yorum_yapabilir' => true,
                        'hikaye_paylasabilir' => true,
                    ]);
                }
                
                $stmt = $pdo->prepare("
                    UPDATE dugun_katilimcilar 
                    SET rol = ?, yetkiler = ? 
                    WHERE dugun_id = ? AND kullanici_id = ?
                ");
                $stmt->execute([$new_role, $permissions_json, $event_id, $user_id]);
                $success_message = 'Kullanıcı rolü başarıyla güncellendi';
                
                // Sayfayı yenile
                header('Location: event-participants.php?id=' . $event_id);
                exit;
                
            } catch (Exception $e) {
                $error_message = 'Rol güncellenirken hata oluştu: ' . $e->getMessage();
            }
        } else {
            $error_message = 'Geçersiz rol seçimi';
        }
    }
    
    if ($_POST['action'] === 'remove_participant') {
        $user_id = (int)($_POST['user_id'] ?? 0);
        
        if ($user_id) {
            try {
                $stmt = $pdo->prepare("
                    DELETE FROM dugun_katilimcilar 
                    WHERE dugun_id = ? AND kullanici_id = ?
                ");
                $stmt->execute([$event_id, $user_id]);
                $success_message = 'Katılımcı başarıyla kaldırıldı';
                
                // Sayfayı yenile
                header('Location: event-participants.php?id=' . $event_id);
                exit;
                
            } catch (Exception $e) {
                $error_message = 'Katılımcı kaldırılırken hata oluştu: ' . $e->getMessage();
            }
        }
    }
    
    if ($_POST['action'] === 'add_participant') {
        $email = trim($_POST['email'] ?? '');
        
        if ($email) {
            try {
                // Kullanıcıyı bul
                $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ? AND durum = 'aktif'");
                $stmt->execute([$email]);
                $user = $stmt->fetch();
                
                if ($user) {
                    // Zaten katılımcı mı kontrol et
                    $stmt = $pdo->prepare("SELECT id FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
                    $stmt->execute([$event_id, $user['id']]);
                    
                    if (!$stmt->fetch()) {
                        // ✅ Default yetkiler (Flutter ile uyumlu)
                        $default_permissions = json_encode([
                            'medya_paylasabilir' => true,
                            'yorum_yapabilir' => true,
                            'hikaye_paylasabilir' => true,
                        ]);
                        
                        // Katılımcı ekle
                        $stmt = $pdo->prepare("
                            INSERT INTO dugun_katilimcilar (dugun_id, kullanici_id, rol, durum, yetkiler, katilim_tarihi)
                            VALUES (?, ?, 'kullanici', 'aktif', ?, NOW())
                        ");
                        $stmt->execute([$event_id, $user['id'], $default_permissions]);
                        $success_message = 'Katılımcı başarıyla eklendi (default yetkilerle)';
                    } else {
                        $error_message = 'Bu kullanıcı zaten katılımcı';
                    }
                } else {
                    $error_message = 'Bu email adresine kayıtlı aktif kullanıcı bulunamadı';
                }
                
                // Sayfayı yenile
                header('Location: event-participants.php?id=' . $event_id);
                exit;
                
            } catch (Exception $e) {
                $error_message = 'Katılımcı eklenirken hata oluştu: ' . $e->getMessage();
            }
        } else {
            $error_message = 'Email adresi gerekli';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dijitalsalon Admin Panel - Katılımcılar</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .sidebar-logo i {
            font-size: 1.5rem;
            color: #6366f1;
        }

        .sidebar-logo h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f1f5f9;
            border-radius: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .user-details h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e293b;
        }

        .user-details p {
            font-size: 0.8rem;
            color: #64748b;
        }

        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .role-super_admin {
            background: #fef3c7;
            color: #d97706;
        }

        .role-moderator {
            background: #dbeafe;
            color: #2563eb;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #64748b;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background: #f1f5f9;
            color: #6366f1;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }

        .nav-link i {
            font-size: 1.1rem;
            width: 20px;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
        }

        .main-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .main-title {
            font-size: 2rem;
            font-weight: 700;
            color: #1e293b;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }

        /* Messages */
        .message {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .message.success {
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            color: #166534;
        }

        .message.error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            color: #dc2626;
        }

        /* Event Info Card */
        .event-info-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .event-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 0.5rem;
        }

        .event-meta {
            display: flex;
            gap: 2rem;
            color: #64748b;
            font-size: 0.9rem;
        }

        .event-meta i {
            color: #6366f1;
            margin-right: 0.5rem;
        }

        /* Participants Table */
        .participants-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        .card-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            background: #f8fafc;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .participants-table {
            width: 100%;
            border-collapse: collapse;
        }

        .participants-table th {
            background: #f8fafc;
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #374151;
            border-bottom: 1px solid #e2e8f0;
        }

        .participants-table td {
            padding: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }

        .participant-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .participant-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
        }

        .participant-details h4 {
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.25rem;
        }

        .participant-details p {
            font-size: 0.8rem;
            color: #64748b;
        }

        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .role-admin {
            background: #fef3c7;
            color: #d97706;
        }

        .role-yetkili_kullanici {
            background: #dbeafe;
            color: #2563eb;
        }

        .role-kullanici {
            background: #f3f4f6;
            color: #374151;
        }

        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }

        .status-aktif {
            background: #f0fdf4;
            color: #166534;
        }

        .status-pasif {
            background: #fef2f2;
            color: #dc2626;
        }

        .actions {
            display: flex;
            gap: 0.5rem;
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            width: 90%;
            max-width: 500px;
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }

        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: #64748b;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: #f1f5f9;
            color: #1e293b;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            color: #374151;
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: #f9fafb;
        }

        .form-control:focus {
            outline: none;
            border-color: #6366f1;
            background: white;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.open {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .participants-table {
                font-size: 0.9rem;
            }

            .participants-table th,
            .participants-table td {
                padding: 0.75rem 0.5rem;
            }

            .main-header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-logo">
                    <i class="fas fa-camera-retro"></i>
                    <h2>Dijitalsalon</h2>
                </div>
                <div class="user-info">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($_SESSION['admin_user_name'], 0, 1)); ?>
                    </div>
                    <div class="user-details">
                        <h4><?php echo htmlspecialchars($_SESSION['admin_user_name']); ?></h4>
                        <p><?php echo htmlspecialchars($_SESSION['admin_user_email']); ?></p>
                        <span class="role-badge role-<?php echo $admin_user_role; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $admin_user_role)); ?>
                        </span>
                    </div>
                </div>
            </div>
            <nav class="sidebar-nav">
                <div class="nav-item">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        Dashboard
                    </a>
                </div>
                <div class="nav-item">
                    <a href="events.php" class="nav-link active">
                        <i class="fas fa-calendar-alt"></i>
                        Düğünler
                    </a>
                </div>
                <?php if ($admin_user_role === 'super_admin'): ?>
                <div class="nav-item">
                    <a href="users.php" class="nav-link">
                        <i class="fas fa-users"></i>
                        Kullanıcılar
                    </a>
                </div>
                <?php endif; ?>
                <?php if ($admin_user_role === 'super_admin'): ?>
                <div class="nav-item">
                    <a href="moderators.php" class="nav-link">
                        <i class="fas fa-user-shield"></i>
                        Moderatorler
                    </a>
                </div>
                <?php endif; ?>
                <div class="nav-item">
                    <a href="media.php" class="nav-link">
                        <i class="fas fa-images"></i>
                        Medya
                    </a>
                </div>
                <div class="nav-item">
                    <a href="reports.php" class="nav-link">
                        <i class="fas fa-chart-bar"></i>
                        Raporlar
                    </a>
                </div>
                <div class="nav-item">
                    <?php if ($admin_user_role === 'super_admin'): ?>
                        <a href="packages.php" class="nav-link">
                            <i class="fas fa-box"></i>
                            Paketler
                        </a>
                    <?php else: ?>
                        <a href="view-packages.php" class="nav-link">
                            <i class="fas fa-box"></i>
                            Paketler
                        </a>
                    <?php endif; ?>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="main-header">
                <h1 class="main-title">Katılımcılar</h1>
                <div class="header-actions">
                    <button onclick="openAddModal()" class="btn btn-success">
                        <i class="fas fa-user-plus"></i>
                        Katılımcı Ekle
                    </button>
                    <a href="events.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Geri
                    </a>
                </div>
            </div>

            <?php if ($success_message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- Event Info -->
            <div class="event-info-card">
                <h2 class="event-title"><?php echo htmlspecialchars($event['baslik'] ?? 'Başlıksız'); ?></h2>
                <div class="event-meta">
                    <span><i class="fas fa-user"></i> <?php echo htmlspecialchars(($event['ad'] ?? '') . ' ' . ($event['soyad'] ?? '')); ?></span>
                    <?php if (!empty($event['dugun_tarihi'])): ?>
                    <span><i class="fas fa-calendar"></i> <?php echo date('d.m.Y', strtotime($event['dugun_tarihi'])); ?></span>
                    <?php endif; ?>
                    <?php if (!empty($event['konum'])): ?>
                    <span><i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($event['konum']); ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Participants Table -->
            <div class="participants-card">
                <div class="card-header">
                    <h3 class="card-title">
                        <i class="fas fa-users"></i>
                        Katılımcılar (<?php echo count($participants); ?>)
                    </h3>
                </div>
                <table class="participants-table">
                    <thead>
                        <tr>
                            <th>Kullanıcı</th>
                            <th>Rol</th>
                            <th>Durum</th>
                            <th>Katılım Tarihi</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($participants as $participant): ?>
                        <tr data-user-id="<?php echo $participant['kullanici_id']; ?>" data-record-id="<?php echo $participant['id']; ?>">
                            <td>
                                <div class="participant-info">
                                    <div class="participant-avatar">
                                        <?php echo strtoupper(substr($participant['ad'], 0, 1)); ?>
                                    </div>
                                    <div class="participant-details">
                                        <h4><?php echo htmlspecialchars($participant['ad'] . ' ' . $participant['soyad']); ?> 
                                            <small style="color: #999;">(ID: <?php echo $participant['kullanici_id']; ?>)</small>
                                        </h4>
                                        <p><?php echo htmlspecialchars($participant['email']); ?></p>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="role-badge role-<?php echo $participant['rol']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $participant['rol'])); ?>
                                </span>
                            </td>
                            <td>
                                <span class="status-badge status-<?php echo $participant['durum']; ?>">
                                    <?php echo ucfirst($participant['durum']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($participant['katilim_tarihi'])); ?></td>
                            <td>
                                <div class="actions">
                                    <?php if ($participant['rol'] !== 'admin'): ?>
                                    <button onclick="openRoleModal(<?php echo $participant['kullanici_id']; ?>, '<?php echo $participant['rol']; ?>', '<?php echo htmlspecialchars($participant['yetkiler'] ?? '{}', ENT_QUOTES); ?>')" 
                                            class="btn btn-primary btn-sm">
                                        <i class="fas fa-user-cog"></i>
                                        Rol & Yetkiler
                                    </button>
                                    <button onclick="removeParticipant(<?php echo $participant['kullanici_id']; ?>)" 
                                            class="btn btn-danger btn-sm">
                                        <i class="fas fa-trash"></i>
                                        Kaldır
                                    </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Add Participant Modal -->
    <div class="modal" id="addModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Katılımcı Ekle</h3>
                <button class="close-btn" onclick="closeAddModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="add_participant">
                
                <div class="form-group">
                    <label for="email">Email Adresi</label>
                    <input type="email" id="email" name="email" class="form-control" 
                           placeholder="Kullanıcının email adresini girin" required>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-success" style="flex: 1;">
                        <i class="fas fa-user-plus"></i>
                        Katılımcı Ekle
                    </button>
                    <button type="button" onclick="closeAddModal()" class="btn btn-secondary" style="flex: 1;">
                        <i class="fas fa-times"></i>
                        İptal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Role Change Modal -->
    <div class="modal" id="roleModal">
        <div class="modal-content" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">Rol ve Yetkiler Değiştir</h3>
                <button class="close-btn" onclick="closeRoleModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="assign_role">
                <input type="hidden" id="role_user_id" name="user_id">
                
                <div class="form-group">
                    <label for="new_role">Rol</label>
                    <select id="new_role" name="new_role" class="form-control" required onchange="togglePermissionFields()">
                        <option value="kullanici">Kullanıcı</option>
                        <option value="yetkili_kullanici">Yetkili Kullanıcı</option>
                    </select>
                </div>

                <div id="permissionFields" style="margin-top: 1.5rem; display: none;">
                    <label style="font-weight: 600; margin-bottom: 0.75rem; display: block;">Yetkiler (Flutter ile Uyumlu)</label>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; border-radius: 8px; background: #f8fafc;">
                            <input type="checkbox" name="permissions[]" value="medya_paylasabilir">
                            <span>Medya Paylaşabilir</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; border-radius: 8px; background: #f8fafc;">
                            <input type="checkbox" name="permissions[]" value="medya_silebilir">
                            <span>Medya Silebilir</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; border-radius: 8px; background: #f8fafc;">
                            <input type="checkbox" name="permissions[]" value="hikaye_paylasabilir">
                            <span>Hikaye Paylaşabilir</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; border-radius: 8px; background: #f8fafc;">
                            <input type="checkbox" name="permissions[]" value="yorum_yapabilir">
                            <span>Yorum Yapabilir</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; border-radius: 8px; background: #f8fafc;">
                            <input type="checkbox" name="permissions[]" value="yorum_silebilir">
                            <span>Yorum Silebilir</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; border-radius: 8px; background: #f8fafc;">
                            <input type="checkbox" name="permissions[]" value="kullanici_engelleyebilir">
                            <span>Kullanıcı Engelleyebilir</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; border-radius: 8px; background: #f8fafc;">
                            <input type="checkbox" name="permissions[]" value="yetki_duzenleyebilir">
                            <span>Yetki Düzenleyebilir</span>
                        </label>
                        
                        <label style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; padding: 0.5rem; border-radius: 8px; background: #f8fafc;">
                            <input type="checkbox" name="permissions[]" value="bildirim_gonderebilir">
                            <span>Bildirim Gönderebilir</span>
                        </label>
                    </div>
                </div>

                <div style="display: flex; gap: 1rem; margin-top: 1.5rem;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">
                        <i class="fas fa-save"></i>
                        Güncelle
                    </button>
                    <button type="button" onclick="closeRoleModal()" class="btn btn-secondary" style="flex: 1;">
                        <i class="fas fa-times"></i>
                        İptal
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openAddModal() {
            document.getElementById('addModal').classList.add('show');
        }

        function closeAddModal() {
            document.getElementById('addModal').classList.remove('show');
        }

        function openRoleModal(userId, currentRole, currentPermissions) {
            document.getElementById('role_user_id').value = userId;
            document.getElementById('new_role').value = currentRole;
            
            console.log('🔍 Opening role modal for user:', userId);
            console.log('🔍 Current role:', currentRole);
            console.log('🔍 Current permissions (raw):', currentPermissions);
            
            // Parse and check existing permissions
            try {
                let permissions = {};
                
                // Eğer boş string veya null ise default yetkiler
                if (!currentPermissions || currentPermissions === '{}' || currentPermissions === 'null') {
                    console.log('⚠️ No permissions found, using defaults for role:', currentRole);
                    
                    // Normal kullanıcı için default yetkiler
                    if (currentRole === 'kullanici') {
                        permissions = {
                            'medya_paylasabilir': true,
                            'yorum_yapabilir': true,
                            'hikaye_paylasabilir': true
                        };
                    } else {
                        permissions = {}; // Yetkili kullanıcı için boş başlat
                    }
                } else {
                    permissions = JSON.parse(currentPermissions);
                }
                
                console.log('✅ Parsed permissions:', permissions);
                
                document.querySelectorAll('#permissionFields input[type="checkbox"]').forEach(cb => {
                    const permissionName = cb.value;
                    
                    // ✅ Geriye dönük uyumluluk: eski isimleri de kontrol et
                    let isChecked = permissions[permissionName] === true;
                    
                    // Eski isimlerle mapping
                    if (permissionName === 'medya_paylasabilir' && permissions['medya_yukleyebilir'] === true) {
                        isChecked = true;
                    }
                    if (permissionName === 'hikaye_paylasabilir' && permissions['hikaye_ekleyebilir'] === true) {
                        isChecked = true;
                    }
                    if (permissionName === 'yetki_duzenleyebilir' && permissions['baska_kullanici_yetki_degistirebilir'] === true) {
                        isChecked = true;
                    }
                    if (permissionName === 'kullanici_engelleyebilir' && permissions['baska_kullanici_yasaklayabilir'] === true) {
                        isChecked = true;
                    }
                    
                    console.log(`Checkbox ${permissionName}: ${isChecked}`);
                    cb.checked = isChecked;
                });
            } catch(e) {
                console.error('❌ Error parsing permissions:', e);
                // Reset if parse fails
                document.querySelectorAll('#permissionFields input[type="checkbox"]').forEach(cb => cb.checked = false);
            }
            
            // Show/hide permission fields based on role
            togglePermissionFields();
            
            document.getElementById('roleModal').classList.add('show');
        }
        
        function togglePermissionFields() {
            const role = document.getElementById('new_role').value;
            const fields = document.getElementById('permissionFields');
            
            if (role === 'yetkili_kullanici') {
                fields.style.display = 'block';
            } else {
                fields.style.display = 'none';
            }
        }

        function closeRoleModal() {
            document.getElementById('roleModal').classList.remove('show');
        }

        function removeParticipant(userId) {
            if (confirm('Bu katılımcıyı kaldırmak istediğinizden emin misiniz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="remove_participant">
                    <input type="hidden" name="user_id" value="${userId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Close modals when clicking outside
        document.querySelectorAll('.modal').forEach(modal => {
            modal.addEventListener('click', function(e) {
                if (e.target === this) {
                    this.classList.remove('show');
                }
            });
        });

        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('open');
        }

        // Add mobile menu button if needed
        if (window.innerWidth <= 768) {
            const header = document.querySelector('.main-header');
            const menuBtn = document.createElement('button');
            menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
            menuBtn.style.cssText = `
                background: none;
                border: none;
                font-size: 1.5rem;
                color: #64748b;
                cursor: pointer;
                padding: 0.5rem;
            `;
            menuBtn.onclick = toggleSidebar;
            header.insertBefore(menuBtn, header.firstChild);
        }
    </script>
</body>
</html>
