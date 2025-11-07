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

// Sadece super_admin erişebilir
if ($admin_user_role !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

$success_message = $_GET['success'] ?? '';
$error_message = '';

try {
    $page = (int)($_GET['page'] ?? 1);
    $limit = 20; // Sayfa başına 20 moderator
    $offset = ($page - 1) * $limit;

    // Toplam moderator sayısı
    $stmt = $pdo->query("SELECT COUNT(*) FROM kullanicilar WHERE rol = 'moderator'");
    $total_moderators = $stmt->fetchColumn();

    // Moderatorları al
    $stmt = $pdo->prepare("
        SELECT 
            k.id,
            k.ad,
            k.soyad,
            k.email,
            k.telefon,
            k.created_at,
            COUNT(d.id) as dugun_sayisi
        FROM kullanicilar k
        LEFT JOIN dugunler d ON k.id = d.moderator_id
        WHERE k.rol = 'moderator'
        GROUP BY k.id
        ORDER BY k.created_at DESC
        LIMIT ?, ?
    ");
    $stmt->execute([$offset, $limit]);
    $moderators = $stmt->fetchAll();

    $total_pages = ceil($total_moderators / $limit);

} catch (Exception $e) {
    $error_message = 'Moderatorler yüklenirken hata oluştu: ' . $e->getMessage();
    $moderators = [];
    $total_pages = 0;
}

// Rol renkleri
function getRoleColor($rol) {
    switch ($rol) {
        case 'super_admin':
            return 'background: #fef3c7; color: #d97706;';
        case 'moderator':
            return 'background: #dbeafe; color: #2563eb;';
        case 'yetkili_kullanici':
            return 'background: #ddd6fe; color: #7c3aed;';
        case 'kullanici':
            return 'background: #d1fae5; color: #059669;';
        default:
            return 'background: #f1f5f9; color: #64748b;';
    }
}

function getRoleName($rol) {
    switch ($rol) {
        case 'super_admin':
            return 'Süper Admin';
        case 'moderator':
            return 'Moderator';
        case 'yetkili_kullanici':
            return 'Yetkili Kullanıcı';
        case 'kullanici':
            return 'Kullanıcı';
        default:
            return $rol;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderatorler - Dijitalsalon Admin</title>
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
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
        }

        .header-actions {
            display: flex;
            gap: 1rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .message.success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #22c55e;
        }

        .message.error {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #dc2626;
        }

        .moderators-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: #f8fafc;
        }

        th {
            padding: 1rem;
            text-align: left;
            font-weight: 600;
            color: #1e293b;
            font-size: 0.85rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #e2e8f0;
        }

        td {
            padding: 1rem;
            border-bottom: 1px solid #e2e8f0;
            color: #475569;
        }

        tr:hover {
            background: #f8fafc;
        }

        .user-avatar-cell {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .user-avatar-small {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.85rem;
        }

        .user-name {
            font-weight: 600;
            color: #1e293b;
        }

        .user-email {
            font-size: 0.85rem;
            color: #64748b;
        }

        .role-badge {
            display: inline-block;
            padding: 0.35rem 0.75rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-icon {
            padding: 0.5rem;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
        }

        .btn-icon.btn-edit {
            background: #f1f5f9;
            color: #3b82f6;
        }

        .btn-icon.btn-edit:hover {
            background: #e0e7ff;
        }

        .btn-icon.btn-delete {
            background: #fee2e2;
            color: #ef4444;
        }

        .btn-icon.btn-delete:hover {
            background: #fecaca;
        }

        .stat-badge {
            display: inline-block;
            padding: 0.4rem 0.8rem;
            background: #dbeafe;
            color: #2563eb;
            border-radius: 8px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
        }

        .pagination a, .pagination span {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 8px;
            text-decoration: none;
            color: #64748b;
            background: white;
            border: 1px solid #e2e8f0;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .pagination a:hover {
            background: #f1f5f9;
            color: #6366f1;
            border-color: #6366f1;
        }

        .pagination span.current {
            background: #6366f1;
            color: white;
            border-color: #6366f1;
        }

        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #64748b;
        }

        .empty-state i {
            font-size: 4rem;
            color: #cbd5e1;
            margin-bottom: 1rem;
        }

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

            .moderators-table {
                overflow-x: auto;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="main-header">
                <h1 class="main-title">Moderator Yönetimi</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="createModerator()">
                        <i class="fas fa-user-plus"></i>
                        Yeni Moderator Ekle
                    </button>
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

            <?php if (!empty($moderators)): ?>
                <div class="moderators-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Moderator</th>
                                <th>E-posta</th>
                                <th>Telefon</th>
                                <th>Düğün Sayısı</th>
                                <th>Kayıt Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($moderators as $moderator): ?>
                                <tr>
                                    <td>
                                        <div class="user-avatar-cell">
                                            <div class="user-avatar-small">
                                                <?php echo strtoupper(substr($moderator['ad'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="user-name">
                                                    <?php echo htmlspecialchars($moderator['ad'] . ' ' . $moderator['soyad']); ?>
                                                </div>
                                                <div class="user-email">
                                                    ID: <?php echo $moderator['id']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($moderator['email']); ?></td>
                                    <td><?php echo htmlspecialchars($moderator['telefon'] ?? '-'); ?></td>
                                    <td>
                                        <span class="stat-badge">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo $moderator['dugun_sayisi']; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($moderator['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon btn-edit" onclick="editModerator(<?php echo $moderator['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-delete" onclick="deleteModerator(<?php echo $moderator['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($total_pages > 1): ?>
                    <div class="pagination">
                        <?php if ($page > 1): ?>
                            <a href="?page=<?php echo $page - 1; ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        <?php endif; ?>

                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <a href="?page=<?php echo $i; ?>" class="<?php echo ($i === $page) ? 'current' : ''; ?>">
                                <?php echo $i; ?>
                            </a>
                        <?php endfor; ?>

                        <?php if ($page < $total_pages): ?>
                            <a href="?page=<?php echo $page + 1; ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-user-shield"></i>
                    <h3>Henüz moderator bulunamadı</h3>
                    <p style="margin-top: 0.5rem;">Yeni moderator ekleyerek başlayın.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        function createModerator() {
            alert('Moderator ekleme özelliği yakında eklenecek.');
            // Implement create moderator functionality
        }

        function editModerator(moderatorId) {
            alert('Moderator düzenleme özelliği yakında eklenecek. ID: ' + moderatorId);
            // Implement edit moderator functionality
        }

        function deleteModerator(moderatorId) {
            if (confirm('Bu moderator\'ü silmek istediğinizden emin misiniz?')) {
                alert('Moderator silme özelliği yakında eklenecek. ID: ' + moderatorId);
                // Implement delete moderator functionality
            }
        }
    </script>
</body>
</html>


