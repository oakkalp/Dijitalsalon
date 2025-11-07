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
    $limit = 20; // Sayfa başına 20 kullanıcı
    $offset = ($page - 1) * $limit;

    // Toplam kullanıcı sayısı
    $stmt = $pdo->query("SELECT COUNT(*) FROM kullanicilar");
    $total_users = $stmt->fetchColumn();

    // Kullanıcıları al
    $stmt = $pdo->prepare("
        SELECT 
            id,
            ad,
            soyad,
            email,
            telefon,
            rol,
            created_at
        FROM kullanicilar
        ORDER BY created_at DESC
        LIMIT ?, ?
    ");
    $stmt->execute([$offset, $limit]);
    $users = $stmt->fetchAll();

    $total_pages = ceil($total_users / $limit);

} catch (Exception $e) {
    $error_message = 'Kullanıcılar yüklenirken hata oluştu: ' . $e->getMessage();
    $users = [];
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
    <title>Kullanıcılar - Dijitalsalon Admin</title>
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

        .users-table {
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
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.75rem;
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

            .users-table {
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
                <h1 class="main-title">Kullanıcı Yönetimi</h1>
                <div class="header-actions">
                    <button class="btn btn-primary" onclick="exportUsers()">
                        <i class="fas fa-download"></i>
                        Kullanıcıları Dışa Aktar
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

            <?php if (!empty($users)): ?>
                <div class="users-table">
                    <table>
                        <thead>
                            <tr>
                                <th>Kullanıcı</th>
                                <th>E-posta</th>
                                <th>Telefon</th>
                                <th>Rol</th>
                                <th>Kayıt Tarihi</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-avatar-cell">
                                            <div class="user-avatar-small">
                                                <?php echo strtoupper(substr($user['ad'], 0, 1)); ?>
                                            </div>
                                            <div>
                                                <div class="user-name">
                                                    <?php echo htmlspecialchars($user['ad'] . ' ' . $user['soyad']); ?>
                                                </div>
                                                <div class="user-email">
                                                    ID: <?php echo $user['id']; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td><?php echo htmlspecialchars($user['telefon'] ?? '-'); ?></td>
                                    <td>
                                        <span class="role-badge" style="<?php echo getRoleColor($user['rol']); ?>">
                                            <?php echo getRoleName($user['rol']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon btn-edit" onclick="editUser(<?php echo $user['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-delete" onclick="deleteUser(<?php echo $user['id']; ?>)">
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
                    <i class="fas fa-users-slash"></i>
                    <h3>Henüz kullanıcı bulunamadı</h3>
                    <p style="margin-top: 0.5rem;">Sistem henüz herhangi bir kullanıcı kaydı içermiyor.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div id="editUserModal" class="modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;">
                    <i class="fas fa-user-edit"></i>
                    Kullanıcı Düzenle
                </h2>
                <button class="modal-close" onclick="closeEditModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="editUserForm" onsubmit="saveUser(event)">
                <input type="hidden" id="edit_user_id" name="user_id">
                <div class="form-group">
                    <label>Ad</label>
                    <input type="text" id="edit_ad" name="ad" required>
                </div>
                <div class="form-group">
                    <label>Soyad</label>
                    <input type="text" id="edit_soyad" name="soyad" required>
                </div>
                <div class="form-group">
                    <label>E-posta</label>
                    <input type="email" id="edit_email" name="email" required>
                </div>
                <div class="form-group">
                    <label>Telefon</label>
                    <input type="text" id="edit_telefon" name="telefon">
                </div>
                <div class="form-group">
                    <label>Rol</label>
                    <select id="edit_rol" name="rol" required>
                        <option value="kullanici">Kullanıcı</option>
                        <option value="yetkili_kullanici">Yetkili Kullanıcı</option>
                        <option value="moderator">Moderator</option>
                        <option value="super_admin">Süper Admin</option>
                    </select>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                        <i class="fas fa-times"></i>
                        İptal
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal stilleri
        const modalStyles = `
            .modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.5);
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 2000;
            }

            .modal-content {
                background: white;
                border-radius: 12px;
                width: 90%;
                max-width: 500px;
                max-height: 90vh;
                overflow-y: auto;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            }

            .modal-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 1.5rem;
                border-bottom: 1px solid #e2e8f0;
            }

            .modal-close {
                background: none;
                border: none;
                font-size: 1.5rem;
                color: #64748b;
                cursor: pointer;
                padding: 0.5rem;
                transition: all 0.3s ease;
            }

            .modal-close:hover {
                color: #1e293b;
                transform: rotate(90deg);
            }

            .form-group {
                margin-bottom: 1.5rem;
                padding: 0 1.5rem;
            }

            .form-group label {
                display: block;
                font-weight: 600;
                color: #1e293b;
                margin-bottom: 0.5rem;
                font-size: 0.9rem;
            }

            .form-group input,
            .form-group select {
                width: 100%;
                padding: 0.75rem;
                border: 2px solid #e2e8f0;
                border-radius: 8px;
                font-size: 0.9rem;
                color: #1e293b;
                transition: all 0.3s ease;
            }

            .form-group input:focus,
            .form-group select:focus {
                outline: none;
                border-color: #6366f1;
            }

            .form-actions {
                display: flex;
                gap: 1rem;
                padding: 1.5rem;
                border-top: 1px solid #e2e8f0;
            }

            .form-actions .btn {
                flex: 1;
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 0.5rem;
            }

            .btn-secondary {
                background: #64748b;
                color: white;
            }

            .btn-secondary:hover {
                background: #475569;
            }
        `;
        
        // Modal stillerini ekle
        const styleSheet = document.createElement("style");
        styleSheet.type = "text/css";
        styleSheet.innerText = modalStyles;
        document.head.appendChild(styleSheet);

        // Kullanıcı bilgilerini getir ve modalı aç
        async function editUser(userId) {
            try {
                const response = await fetch(`ajax/get-user.php?id=${userId}`);
                const user = await response.json();
                
                if (user.success) {
                    document.getElementById('edit_user_id').value = user.data.id;
                    document.getElementById('edit_ad').value = user.data.ad;
                    document.getElementById('edit_soyad').value = user.data.soyad;
                    document.getElementById('edit_email').value = user.data.email;
                    document.getElementById('edit_telefon').value = user.data.telefon || '';
                    document.getElementById('edit_rol').value = user.data.rol;
                    
                    document.getElementById('editUserModal').style.display = 'flex';
                } else {
                    alert('Kullanıcı bilgileri alınamadı: ' + user.message);
                }
            } catch (error) {
                alert('Bir hata oluştu: ' + error.message);
            }
        }

        // Modalı kapat
        function closeEditModal() {
            document.getElementById('editUserModal').style.display = 'none';
        }

        // Kullanıcıyı kaydet
        async function saveUser(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const userId = formData.get('user_id');
            
            try {
                const response = await fetch('ajax/update-user.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Kullanıcı başarıyla güncellendi!');
                    closeEditModal();
                    window.location.reload();
                } else {
                    alert('Hata: ' + result.message);
                }
            } catch (error) {
                alert('Bir hata oluştu: ' + error.message);
            }
        }

        // Modal dışına tıklandığında kapat
        document.getElementById('editUserModal')?.addEventListener('click', function(e) {
            if (e.target === this) {
                closeEditModal();
            }
        });

        function deleteUser(userId) {
            if (confirm('Bu kullanıcıyı silmek istediğinizden emin misiniz?')) {
                alert('Kullanıcı silme özelliği yakında eklenecek. User ID: ' + userId);
                // Implement delete AJAX call here
            }
        }

        function exportUsers() {
            alert('Kullanıcı dışa aktarma özelliği yakında eklenecek.');
            // Implement export functionality
        }
    </script>
</body>
</html>

