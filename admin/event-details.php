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

// Event ID kontrolü
$event_id = (int)($_GET['id'] ?? 0);

if (!$event_id) {
    header('Location: events.php');
    exit;
}

// Düğün bilgilerini al
try {
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            k.ad as moderator_ad,
            k.soyad as moderator_soyad,
            k.email as moderator_email,
            k.telefon as moderator_telefon,
            p.ad as paket_ad,
            p.fiyat as paket_fiyat,
            p.aciklama as paket_aciklama,
            p.sure_ay,
            p.maksimum_katilimci,
            p.medya_limiti,
            p.ucretsiz_erisim_gun,
            (SELECT COUNT(DISTINCT dk2.kullanici_id) FROM dugun_katilimcilar dk2 WHERE dk2.dugun_id = d.id AND dk2.durum = 'aktif') as katilimci_sayisi,
            (SELECT COUNT(DISTINCT m.id) FROM medyalar m WHERE m.dugun_id = d.id) as medya_sayisi,
            (SELECT COUNT(DISTINCT h.id) FROM hikayeler h WHERE h.dugun_id = d.id) as hikaye_sayisi
        FROM dugunler d
        LEFT JOIN kullanicilar k ON d.moderator_id = k.id
        LEFT JOIN paketler p ON d.paket_id = p.id
        WHERE d.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        header('Location: events.php');
        exit;
    }
    
    // Yetki kontrolü - Moderator sadece kendi düğünlerini görebilir
    if ($admin_user_role === 'moderator' && $event['moderator_id'] != $admin_user_id) {
        header('Location: events.php');
        exit;
    }
    
} catch (Exception $e) {
    header('Location: events.php');
    exit;
}

// Katılımcıları al
$stmt = $pdo->prepare("
    SELECT 
        dk.*,
        k.ad,
        k.soyad,
        k.email,
        k.telefon
    FROM dugun_katilimcilar dk
    LEFT JOIN kullanicilar k ON dk.kullanici_id = k.id
    WHERE dk.dugun_id = ?
    ORDER BY dk.katilim_tarihi DESC
");
$stmt->execute([$event_id]);
$participants = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Düğün Detayları - Dijitalsalon Admin</title>
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

        /* Sidebar - Events.php ile aynı stil */
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
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #64748b;
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
        }

        /* Event Details Styling */
        .event-details-card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
        }

        .event-cover-image {
            width: 100%;
            max-height: 400px;
            object-fit: cover;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }

        .event-title {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 1rem;
            color: #1e293b;
        }

        .event-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            margin-bottom: 1.5rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #64748b;
        }

        .meta-item i {
            font-size: 1.2rem;
            color: #3b82f6;
        }

        .event-description {
            color: #64748b;
            line-height: 1.6;
            margin-top: 1.5rem;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .stat-card {
            background: #f8fafc;
            padding: 1rem;
            border-radius: 8px;
            text-align: center;
        }

        .stat-number {
            font-size: 1.5rem;
            font-weight: bold;
            color: #3b82f6;
        }

        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: bold;
            margin: 2rem 0 1rem;
            color: #1e293b;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .info-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
        }

        .info-label {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
        }

        .info-value {
            color: #1e293b;
            font-weight: 600;
        }

        .participant-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
        }

        .participant-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }

        .participant-card i {
            font-size: 3rem;
            color: #3b82f6;
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
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #64748b;
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
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
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>
        
        <div class="main-content">
            <div class="main-header">
                <h1 class="main-title">Düğün Detayları</h1>
                <div class="header-actions">
                    <a href="edit-event.php?id=<?php echo $event_id; ?>" class="btn btn-primary">
                        <i class="fas fa-edit"></i>
                        Düzenle
                    </a>
                    <a href="events.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Geri
                    </a>
                </div>
            </div>
            
            <div class="event-details-card">
                <?php if (!empty($event['kapak_fotografi'])): ?>
                    <img src="../<?php echo htmlspecialchars($event['kapak_fotografi']); ?>" alt="Kapak Fotoğrafı" class="event-cover-image">
                <?php endif; ?>
                
                <h2 class="event-title"><?php echo htmlspecialchars($event['baslik'] ?? 'Başlıksız'); ?></h2>
                
                <div class="event-meta">
                    <div class="meta-item">
                        <i class="fas fa-calendar"></i>
                        <span><?php echo !empty($event['dugun_tarihi']) ? date('d.m.Y', strtotime($event['dugun_tarihi'])) : 'Belirtilmemiş'; ?></span>
                    </div>
                    
                    <?php if (!empty($event['salon_adresi'])): ?>
                    <div class="meta-item">
                        <i class="fas fa-map-marker-alt"></i>
                        <span><?php echo htmlspecialchars($event['salon_adresi']); ?></span>
                    </div>
                    <?php endif; ?>
                    
                    <div class="meta-item">
                        <i class="fas fa-user-shield"></i>
                        <span><?php echo htmlspecialchars(($event['moderator_ad'] ?? '') . ' ' . ($event['moderator_soyad'] ?? '')); ?></span>
                    </div>
                </div>
                
                <?php if (!empty($event['aciklama'])): ?>
                <div class="event-description">
                    <?php echo nl2br(htmlspecialchars($event['aciklama'])); ?>
                </div>
                <?php endif; ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $event['katilimci_sayisi']; ?></div>
                        <div class="stat-label">Katılımcı</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $event['medya_sayisi']; ?></div>
                        <div class="stat-label">Medya</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $event['hikaye_sayisi']; ?></div>
                        <div class="stat-label">Hikaye</div>
                    </div>
                </div>
            </div>
            
            <h3 class="section-title">Düğün Bilgileri</h3>
            <div class="info-grid">
                <div class="info-card">
                    <div class="info-label">Paket</div>
                    <div class="info-value"><?php echo htmlspecialchars($event['paket_ad'] ?? 'Belirtilmemiş'); ?></div>
                </div>
                
                <?php if (!empty($event['paket_fiyat'])): ?>
                <div class="info-card">
                    <div class="info-label">Paket Fiyatı</div>
                    <div class="info-value">₺<?php echo number_format($event['paket_fiyat'], 2); ?></div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($event['qr_kod'])): ?>
                <div class="info-card">
                    <div class="info-label">QR Kod</div>
                    <div class="info-value"><?php echo htmlspecialchars($event['qr_kod']); ?></div>
                </div>
                <?php endif; ?>
                
            </div>
            
            <h3 class="section-title">Katılımcılar (<?php echo count($participants); ?>)</h3>
            <?php if (!empty($participants)): ?>
            <div class="participant-grid">
                <?php foreach ($participants as $participant): ?>
                <div class="participant-card">
                    <i class="fas fa-user-circle"></i>
                    <div style="margin-top: 0.5rem;">
                        <strong><?php echo htmlspecialchars(($participant['ad'] ?? '') . ' ' . ($participant['soyad'] ?? '')); ?></strong>
                    </div>
                    <div style="color: #64748b; font-size: 0.9rem; margin-top: 0.25rem;">
                        <?php echo htmlspecialchars($participant['rol'] ?? ''); ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 2rem; color: #64748b;">
                Henüz katılımcı yok
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        // Mobile sidebar toggle
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('open');
        }

        // Add mobile menu button if needed
        if (window.innerWidth <= 768) {
            const header = document.querySelector('.main-header');
            if (header) {
                const menuBtn = document.createElement('button');
                menuBtn.innerHTML = '<i class="fas fa-bars"></i>';
                menuBtn.onclick = toggleSidebar;
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
        }
    </script>
</body>
</html>
