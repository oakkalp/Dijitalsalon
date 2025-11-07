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

// Paketleri al
try {
    $stmt = $pdo->query("SELECT * FROM paketler ORDER BY fiyat ASC");
    $paketler = $stmt->fetchAll();
} catch (Exception $e) {
    $error_message = 'Paketler yüklenirken hata oluştu: ' . $e->getMessage();
    $paketler = [];
}

// Paket özelliklerini decode et
function decode_features($features_json) {
    $features = json_decode($features_json, true);
    if (is_array($features)) {
        return array_map(function($f) {
            return html_entity_decode($f, ENT_QUOTES, 'UTF-8');
        }, $features);
    }
    return [];
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Paketler - Dijitalsalon Admin</title>
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

        .paket-table {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 2rem;
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

        .btn-edit {
            background: #f1f5f9;
            color: #3b82f6;
        }

        .btn-edit:hover {
            background: #e0e7ff;
        }

        .btn-delete {
            background: #fee2e2;
            color: #ef4444;
        }

        .btn-delete:hover {
            background: #fecaca;
        }

        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
            z-index: 2000;
        }

        .modal.active {
            display: flex;
        }

        .modal-content {
            background: white;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
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
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #1e293b;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group textarea:focus,
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

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .paket-table {
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
                <h1 class="main-title">
                    <i class="fas fa-box"></i>
                    Paketler
                </h1>
                <button class="btn btn-primary" onclick="openCreateModal()">
                    <i class="fas fa-plus"></i>
                    Yeni Paket Ekle
                </button>
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

            <?php if (!empty($paketler)): ?>
                <div class="paket-table">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Paket Adı</th>
                                <th>Fiyat</th>
                                <th>Açıklama</th>
                                <th>Süre (Ay)</th>
                                <th>Max Katılımcı</th>
                                <th>Medya Limiti</th>
                                <th>Ücretsiz Erişim (Gün)</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($paketler as $paket): ?>
                                <tr>
                                    <td><?php echo $paket['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($paket['ad']); ?></strong></td>
                                    <td>₺<?php echo number_format($paket['fiyat']); ?></td>
                                    <td><?php echo htmlspecialchars($paket['aciklama'] ?? '-'); ?></td>
                                    <td><?php echo $paket['sure_ay']; ?></td>
                                    <td><?php echo number_format($paket['maksimum_katilimci']); ?></td>
                                    <td><?php echo number_format($paket['medya_limiti']); ?></td>
                                    <td><?php echo $paket['ucretsiz_erisim_gun']; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="btn-icon btn-edit" onclick="editPaket(<?php echo $paket['id']; ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn-icon btn-delete" onclick="deletePaket(<?php echo $paket['id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 4rem; color: #64748b;">
                    <i class="fas fa-box-open" style="font-size: 4rem; margin-bottom: 1rem; color: #cbd5e1;"></i>
                    <h3>Henüz paket bulunamadı</h3>
                    <p style="margin-top: 0.5rem;">Yeni paket ekleyerek başlayın.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Create/Edit Paket Modal -->
    <div id="paketModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 style="font-size: 1.5rem; font-weight: 700; color: #1e293b;" id="modalTitle">
                    <i class="fas fa-plus"></i>
                    Yeni Paket Ekle
                </h2>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form id="paketForm" onsubmit="savePaket(event)">
                <input type="hidden" id="paket_id" name="paket_id">
                <div class="form-group">
                    <label>Paket Adı *</label>
                    <input type="text" id="paket_ad" name="ad" required>
                </div>
                <div class="form-group">
                    <label>Fiyat (₺) *</label>
                    <input type="number" id="paket_fiyat" name="fiyat" step="0.01" required>
                </div>
                <div class="form-group">
                    <label>Açıklama</label>
                    <textarea id="paket_aciklama" name="aciklama" rows="3"></textarea>
                </div>
                <div class="form-group">
                    <label>Süre (Ay) *</label>
                    <input type="number" id="paket_sure" name="sure_ay" required>
                </div>
                <div class="form-group">
                    <label>Maksimum Katılımcı *</label>
                    <input type="number" id="paket_max_katilimci" name="maksimum_katilimci" required>
                </div>
                <div class="form-group">
                    <label>Medya Limiti *</label>
                    <input type="number" id="paket_medya_limiti" name="medya_limiti" required>
                </div>
                <div class="form-group">
                    <label>Ücretsiz Erişim Günü *</label>
                    <input type="number" id="paket_erisim_gun" name="ucretsiz_erisim_gun" required>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
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
        // Modal aç/kapat
        function openCreateModal() {
            document.getElementById('modalTitle').innerHTML = '<i class="fas fa-plus"></i> Yeni Paket Ekle';
            document.getElementById('paketForm').reset();
            document.getElementById('paket_id').value = '';
            document.getElementById('paketModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('paketModal').classList.remove('active');
        }

        // Modal dışına tıklanırsa kapat
        document.getElementById('paketModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        // Paket düzenle
        async function editPaket(paketId) {
            try {
                const response = await fetch(`ajax/get-paket.php?id=${paketId}`);
                const result = await response.json();
                
                if (result.success) {
                    const paket = result.data;
                    document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Paket Düzenle';
                    document.getElementById('paket_id').value = paket.id;
                    document.getElementById('paket_ad').value = paket.ad;
                    document.getElementById('paket_fiyat').value = paket.fiyat;
                    document.getElementById('paket_aciklama').value = paket.aciklama || '';
                    document.getElementById('paket_sure').value = paket.sure_ay;
                    document.getElementById('paket_max_katilimci').value = paket.maksimum_katilimci;
                    document.getElementById('paket_medya_limiti').value = paket.medya_limiti;
                    document.getElementById('paket_erisim_gun').value = paket.ucretsiz_erisim_gun;
                    
                    document.getElementById('paketModal').classList.add('active');
                } else {
                    alert('Paket bilgileri alınamadı: ' + result.message);
                }
            } catch (error) {
                alert('Bir hata oluştu: ' + error.message);
            }
        }

        // Paket kaydet
        async function savePaket(event) {
            event.preventDefault();
            
            const formData = new FormData(event.target);
            const paketId = formData.get('paket_id');
            
            try {
                const response = await fetch(paketId ? 'ajax/update-paket.php' : 'ajax/create-paket.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Paket başarıyla ' + (paketId ? 'güncellendi' : 'eklendi') + '!');
                    closeModal();
                    window.location.reload();
                } else {
                    alert('Hata: ' + result.message);
                }
            } catch (error) {
                alert('Bir hata oluştu: ' + error.message);
            }
        }

        // Paket sil
        function deletePaket(paketId) {
            if (confirm('Bu paketi silmek istediğinizden emin misiniz?')) {
                fetch(`ajax/delete-paket.php?id=${paketId}`, {
                    method: 'POST'
                })
                .then(response => response.json())
                .then(result => {
                    if (result.success) {
                        alert('Paket başarıyla silindi!');
                        window.location.reload();
                    } else {
                        alert('Hata: ' + result.message);
                    }
                })
                .catch(error => {
                    alert('Bir hata oluştu: ' + error.message);
                });
            }
        }
    </script>
</body>
</html>
