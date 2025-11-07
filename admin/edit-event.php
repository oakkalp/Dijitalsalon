<?php
session_start();

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';

$admin_user_id = $_SESSION['admin_user_id'];
$admin_user_role = $_SESSION['admin_user_role'];

if (!in_array($admin_user_role, ['super_admin', 'moderator'])) {
    header('Location: dashboard.php');
    exit;
}

$event_id = (int)($_GET['id'] ?? 0);

if (!$event_id) {
    header('Location: events.php');
    exit;
}

// Thumbnail oluşturma fonksiyonu
function createThumbnail($source_path, $thumbnail_path, $max_width, $max_height) {
    try {
        $image_info = getimagesize($source_path);
        if (!$image_info) return false;
        
        $source_width = $image_info[0];
        $source_height = $image_info[1];
        $mime_type = $image_info['mime'];
        
        switch ($mime_type) {
            case 'image/jpeg':
                $source_image = imagecreatefromjpeg($source_path);
                break;
            case 'image/png':
                $source_image = imagecreatefrompng($source_path);
                break;
            case 'image/gif':
                $source_image = imagecreatefromgif($source_path);
                break;
            default:
                return false;
        }
        
        if (!$source_image) return false;
        
        $ratio = min($max_width / $source_width, $max_height / $source_height);
        $thumbnail_width = intval($source_width * $ratio);
        $thumbnail_height = intval($source_height * $ratio);
        
        $thumbnail_image = imagecreatetruecolor($thumbnail_width, $thumbnail_height);
        
        if ($mime_type === 'image/png') {
            imagealphablending($thumbnail_image, false);
            imagesavealpha($thumbnail_image, true);
            $transparent = imagecolorallocatealpha($thumbnail_image, 255, 255, 255, 127);
            imagefill($thumbnail_image, 0, 0, $transparent);
        }
        
        imagecopyresampled(
            $thumbnail_image, $source_image,
            0, 0, 0, 0,
            $thumbnail_width, $thumbnail_height,
            $source_width, $source_height
        );
        
        $result = false;
        switch ($mime_type) {
            case 'image/jpeg':
                $result = imagejpeg($thumbnail_image, $thumbnail_path, 85);
                break;
            case 'image/png':
                $result = imagepng($thumbnail_image, $thumbnail_path, 8);
                break;
            case 'image/gif':
                $result = imagegif($thumbnail_image, $thumbnail_path);
                break;
        }
        
        imagedestroy($source_image);
        imagedestroy($thumbnail_image);
        
        return $result;
    } catch (Exception $e) {
        return false;
    }
}

// Düğün bilgilerini al
try {
    $stmt = $pdo->prepare("SELECT * FROM dugunler WHERE id = ?");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header('Location: events.php');
        exit;
    }
    
    if ($admin_user_role === 'moderator' && $event['moderator_id'] != $admin_user_id) {
        header('Location: events.php');
        exit;
    }
    
} catch (Exception $e) {
    header('Location: events.php');
    exit;
}

// Paketleri al
try {
    $stmt = $pdo->query("SELECT id, ad, fiyat, sure_ay, maksimum_katilimci, medya_limiti, ucretsiz_erisim_gun FROM paketler ORDER BY id");
    $packages = $stmt->fetchAll();
} catch (Exception $e) {
    $packages = [];
}

$error_message = '';
$success_message = '';

// Form işleme
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'] ?? '';
    $description = $_POST['description'] ?? '';
    $date = $_POST['date'] ?? '';
    $salon_adresi = $_POST['salon_adresi'] ?? '';
    $latitude = $_POST['latitude'] ?? '';
    $longitude = $_POST['longitude'] ?? '';
    $package_id = (int)($_POST['package_id'] ?? 0);
    
    if (!empty($title) && !empty($date)) {
        try {
            $pdo->beginTransaction();
            
            // Slug oluştur
            $slug = 'dugun-' . preg_replace('/[^a-zA-Z0-9]+/', '-', strtolower($title)) . '-' . $event_id;
            
            $stmt = $pdo->prepare("
                UPDATE dugunler 
                SET baslik = ?, slug = ?, aciklama = ?, dugun_tarihi = ?, salon_adresi = ?, latitude = ?, longitude = ?, paket_id = ?
                WHERE id = ?
            ");
            $stmt->execute([$title, $slug, $description, $date, $salon_adresi, $latitude, $longitude, $package_id, $event_id]);
            
            // Kapak fotoğrafı güncelle
            if (isset($_FILES['cover_photo']) && $_FILES['cover_photo']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = '../uploads/events/' . $event_id . '/';
                
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                
                $file = $_FILES['cover_photo'];
                $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $filename = 'event_cover_' . $event_id . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $file_path)) {
                    $cover_path = 'uploads/events/' . $event_id . '/' . $filename;
                    
                    // Thumbnail oluştur
                    $thumbnail_filename = 'event_cover_' . $event_id . '_' . time() . '_thumb.' . $file_extension;
                    $thumbnail_path = $upload_dir . $thumbnail_filename;
                    
                    if (createThumbnail($file_path, $thumbnail_path, 300, 200)) {
                        $thumbnail_db_path = 'uploads/events/' . $event_id . '/' . $thumbnail_filename;
                        $stmt = $pdo->prepare("UPDATE dugunler SET kapak_fotografi = ?, thumbnail_fotografi = ? WHERE id = ?");
                        $stmt->execute([$cover_path, $thumbnail_db_path, $event_id]);
                    } else {
                        $stmt = $pdo->prepare("UPDATE dugunler SET kapak_fotografi = ? WHERE id = ?");
                        $stmt->execute([$cover_path, $event_id]);
                    }
                }
            }
            
            $pdo->commit();
            $success_message = 'Düğün başarıyla güncellendi!';
            header('Location: event-details.php?id=' . $event_id . '&success=' . urlencode($success_message));
            exit;
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error_message = 'Düğün güncellenirken hata oluştu: ' . $e->getMessage();
        }
    } else {
        $error_message = 'Düğün başlığı ve tarihi zorunludur';
    }
}

$success_message = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Düğün Düzenle - Dijitalsalon Admin</title>
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

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
        }

        .form-container {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #1e293b;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #6366f1;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }

        textarea.form-control {
            min-height: 120px;
            resize: vertical;
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
        }

        .btn-primary {
            background: #6366f1;
            color: white;
        }

        .btn-primary:hover {
            background: #4f46e5;
        }

        .btn-secondary {
            background: #64748b;
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .message.success {
            background: #d1fae5;
            color: #065f46;
        }

        .message.error {
            background: #fee2e2;
            color: #991b1b;
        }

        .package-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .package-card {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .package-card:hover {
            border-color: #6366f1;
        }

        .package-card.selected {
            border-color: #6366f1;
            background: #f8faff;
        }

        .package-card input[type="radio"] {
            display: none;
        }

        /* Sidebar CSS */
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
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div style="margin-bottom: 2rem;">
                <h1 style="font-size: 1.75rem; font-weight: 700; color: #1e293b;">Düğün Düzenle</h1>
            </div>

            <?php if ($error_message): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="form-container">
                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label class="form-label">Düğün Başlığı</label>
                        <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($event['baslik'] ?? ''); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Açıklama</label>
                        <textarea name="description" class="form-control"><?php echo htmlspecialchars($event['aciklama'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Düğün Tarihi</label>
                        <input type="date" name="date" class="form-control" value="<?php echo date('Y-m-d', strtotime($event['dugun_tarihi'])); ?>" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Salon Adresi</label>
                        <input type="text" name="salon_adresi" class="form-control" value="<?php echo htmlspecialchars($event['salon_adresi'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Enlem (Latitude)</label>
                        <input type="text" name="latitude" class="form-control" value="<?php echo htmlspecialchars($event['latitude'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Boylam (Longitude)</label>
                        <input type="text" name="longitude" class="form-control" value="<?php echo htmlspecialchars($event['longitude'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Paket Seçimi</label>
                        <div class="package-grid">
                            <?php foreach ($packages as $package): ?>
                            <div class="package-card <?php echo ($package['id'] == $event['paket_id']) ? 'selected' : ''; ?>" onclick="selectPackage(<?php echo $package['id']; ?>)">
                                <input type="radio" name="package_id" value="<?php echo $package['id']; ?>" <?php echo ($package['id'] == $event['paket_id']) ? 'checked' : ''; ?>>
                                <h3 style="font-weight: 600; margin-bottom: 0.5rem;"><?php echo htmlspecialchars($package['ad']); ?></h3>
                                <p style="color: #64748b; font-size: 0.9rem;">₺<?php echo number_format($package['fiyat'], 2); ?></p>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kapak Fotoğrafı</label>
                        <input type="file" name="cover_photo" class="form-control" accept="image/*">
                        <?php if (!empty($event['kapak_fotografi'])): ?>
                            <small style="color: #64748b; margin-top: 0.5rem; display: block;">Mevcut fotoğraf: <?php echo htmlspecialchars($event['kapak_fotografi']); ?></small>
                        <?php endif; ?>
                    </div>

                    <div style="display: flex; gap: 1rem; margin-top: 2rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i>
                            Güncelle
                        </button>
                        <a href="event-details.php?id=<?php echo $event_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i>
                            İptal
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function selectPackage(packageId) {
            document.querySelectorAll('.package-card').forEach(card => {
                card.classList.remove('selected');
            });
            event.currentTarget.classList.add('selected');
            event.currentTarget.querySelector('input[type="radio"]').checked = true;
        }
    </script>
</body>
</html>

