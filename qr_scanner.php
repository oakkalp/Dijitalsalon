<?php
session_start();

// Eğer kullanıcı giriş yapmamışsa login'e yönlendir
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Veritabanı bağlantısı
try {
    $pdo = new PDO("mysql:host=localhost;dbname=digitalsalon_db;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die('Veritabanı bağlantı hatası: ' . $e->getMessage());
}

$user_id = $_SESSION['user_id'];

// QR kod tarama işlemi
if ($_POST['action'] ?? '' === 'scan_qr') {
    $qr_data = trim($_POST['qr_data'] ?? '');
    
    if ($qr_data) {
        try {
            // QR kod verisini parse et
            $qr_info = json_decode($qr_data, true);
            
            if ($qr_info && isset($qr_info['qr_code'])) {
                $qr_code = $qr_info['qr_code'];
                
                // QR koda sahip düğünü bul
                $stmt = $pdo->prepare("
                    SELECT 
                        d.*,
                        k.ad as moderator_ad,
                        k.soyad as moderator_soyad,
                        p.ad as paket_ad
                    FROM dugunler d
                    JOIN kullanicilar k ON d.moderator_id = k.id
                    LEFT JOIN paketler p ON d.paket_id = p.id
                    WHERE d.qr_kod = ? AND d.durum = 'aktif'
                ");
                $stmt->execute([$qr_code]);
                $event = $stmt->fetch();
                
                if ($event) {
                    // Kullanıcının zaten katılımcı olup olmadığını kontrol et
                    $stmt = $pdo->prepare("SELECT * FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
                    $stmt->execute([$event['id'], $user_id]);
                    $existing_participation = $stmt->fetch();
                    
                    if (!$existing_participation) {
                        // Kullanıcıyı düğüne katılımcı olarak ekle
                        $stmt = $pdo->prepare("
                        INSERT INTO dugun_katilimcilar (dugun_id, kullanici_id, rol, medya_silebilir, yorum_silebilir, kullanici_engelleyebilir, hikaye_paylasabilir, profil_degistirebilir) 
                        VALUES (?, ?, 'normal_kullanici', 0, 0, 0, 1, 0)
                        ");
                        $stmt->execute([$event['id'], $user_id]);
                        
                        // Bildirim ekle
                        $stmt = $pdo->prepare("
                            INSERT INTO bildirimler (kullanici_id, tip, icerik, hedef_url) 
                            VALUES (?, 'dugun_davet', ?, ?)
                        ");
                        $stmt->execute([
                            $user_id,
                            $event['baslik'] . ' düğününe başarıyla katıldınız.',
                            'user_dashboard.php?event_id=' . $event['id']
                        ]);
                        
                        $success = 'Düğüne başarıyla katıldınız!';
                    } else {
                        $info = 'Bu düğüne zaten katılımcısınız.';
                    }
                    
                    // Düğün bilgilerini göster
                    $scanned_event = $event;
                } else {
                    $error = 'Geçersiz QR kod veya düğün bulunamadı.';
                }
            } else {
                $error = 'QR kod formatı geçersiz.';
            }
        } catch (Exception $e) {
            $error = 'QR kod işlenirken hata oluştu: ' . $e->getMessage();
        }
    } else {
        $error = 'QR kod verisi boş olamaz.';
    }
}

// Manuel QR kod girişi
if ($_POST['action'] ?? '' === 'manual_qr') {
    $qr_code = trim($_POST['qr_code']);
    
    if ($qr_code) {
        try {
            // QR koda sahip düğünü bul
            $stmt = $pdo->prepare("
                SELECT 
                    d.*,
                    k.ad as moderator_ad,
                    k.soyad as moderator_soyad,
                    p.ad as paket_ad
                FROM dugunler d
                JOIN kullanicilar k ON d.moderator_id = k.id
                LEFT JOIN paketler p ON d.paket_id = p.id
                WHERE d.qr_kod = ? AND d.durum = 'aktif'
            ");
            $stmt->execute([$qr_code]);
            $event = $stmt->fetch();
            
            if ($event) {
                // Kullanıcının zaten katılımcı olup olmadığını kontrol et
                $stmt = $pdo->prepare("SELECT * FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
                $stmt->execute([$event['id'], $user_id]);
                $existing_participation = $stmt->fetch();
                
                if (!$existing_participation) {
                    // Kullanıcıyı düğüne katılımcı olarak ekle
                    $stmt = $pdo->prepare("
                        INSERT INTO dugun_katilimcilar (dugun_id, kullanici_id, rol, medya_silebilir, yorum_silebilir, kullanici_engelleyebilir, hikaye_paylasabilir, profil_degistirebilir) 
                        VALUES (?, ?, 'normal_kullanici', 0, 0, 0, 1, 0)
                    ");
                    $stmt->execute([$event['id'], $user_id]);
                    
                    // Bildirim ekle
                    $stmt = $pdo->prepare("
                        INSERT INTO bildirimler (kullanici_id, tip, icerik, hedef_url) 
                        VALUES (?, 'dugun_davet', ?, ?)
                    ");
                    $stmt->execute([
                        $user_id,
                        $event['baslik'] . ' düğününe başarıyla katıldınız.',
                        'user_dashboard.php?event_id=' . $event['id']
                    ]);
                    
                    $success = 'Düğüne başarıyla katıldınız!';
                } else {
                    $info = 'Bu düğüne zaten katılımcısınız.';
                }
                
                // Düğün bilgilerini göster
                $scanned_event = $event;
            } else {
                $error = 'Geçersiz QR kod veya düğün bulunamadı.';
            }
        } catch (Exception $e) {
            $error = 'QR kod işlenirken hata oluştu: ' . $e->getMessage();
        }
    } else {
        $error = 'QR kod boş olamaz.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Kod Tarayıcı - Digital Salon</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-ui.css" rel="stylesheet">
    
    <!-- QR Code Scanner -->
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
    
    <style>
        .scanner-container {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-md);
            overflow: hidden;
            margin-bottom: var(--spacing-xl);
        }
        
        .scanner-header {
            padding: var(--spacing-lg);
            border-bottom: 1px solid var(--gray-200);
            background: var(--gray-50);
        }
        
        .scanner-content {
            padding: var(--spacing-lg);
        }
        
        #qr-reader {
            width: 100%;
            max-width: 500px;
            margin: 0 auto;
        }
        
        #qr-reader__dashboard_section_csr {
            margin-bottom: var(--spacing-lg);
        }
        
        .qr-result {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-top: var(--spacing-lg);
            border-left: 4px solid var(--success);
        }
        
        .event-card {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            border: 2px solid var(--success);
            margin-top: var(--spacing-lg);
        }
        
        .event-cover {
            width: 100%;
            height: 200px;
            background: var(--success-gradient);
            border-radius: var(--radius-lg);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            margin-bottom: var(--spacing-md);
        }
        
        .event-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: var(--spacing-sm);
        }
        
        .event-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--spacing-md);
        }
        
        .event-date {
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        
        .event-moderator {
            color: var(--gray-600);
            font-size: 0.875rem;
        }
        
        .manual-input {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--spacing-xl);
        }
        
        .scan-button {
            background: var(--success-gradient);
            color: white;
            border: none;
            border-radius: var(--radius-lg);
            padding: var(--spacing-md) var(--spacing-xl);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-normal);
            width: 100%;
            margin-top: var(--spacing-md);
        }
        
        .scan-button:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .scan-button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        
        .camera-preview {
            border-radius: var(--radius-lg);
            overflow: hidden;
            margin-bottom: var(--spacing-md);
        }
        
        .scanner-controls {
            display: flex;
            gap: var(--spacing-md);
            margin-top: var(--spacing-md);
        }
        
        .scanner-control {
            flex: 1;
            padding: var(--spacing-sm) var(--spacing-md);
            border: 2px solid var(--gray-200);
            border-radius: var(--radius-md);
            background: white;
            cursor: pointer;
            transition: all var(--transition-fast);
            text-align: center;
        }
        
        .scanner-control:hover {
            border-color: var(--success);
            background: var(--gray-50);
        }
        
        .scanner-control.active {
            border-color: var(--success);
            background: var(--success);
            color: white;
        }
        
        .recent-events {
            background: white;
            border-radius: var(--radius-xl);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
        }
        
        .recent-event-item {
            display: flex;
            align-items: center;
            padding: var(--spacing-md);
            border-radius: var(--radius-lg);
            margin-bottom: var(--spacing-sm);
            transition: all var(--transition-fast);
            cursor: pointer;
        }
        
        .recent-event-item:hover {
            background: var(--gray-50);
        }
        
        .recent-event-avatar {
            width: 50px;
            height: 50px;
            border-radius: var(--radius-lg);
            background: var(--success-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
            margin-right: var(--spacing-md);
        }
        
        .recent-event-info {
            flex: 1;
        }
        
        .recent-event-title {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
        }
        
        .recent-event-date {
            font-size: 0.75rem;
            color: var(--gray-600);
        }
        
        @media (max-width: 768px) {
            .scanner-content {
                padding: var(--spacing-md);
            }
            
            .event-meta {
                flex-direction: column;
                align-items: flex-start;
                gap: var(--spacing-sm);
            }
            
            .scanner-controls {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="mb-2">
                        <i class="fas fa-qrcode me-3"></i>QR Kod Tarayıcı
                    </h1>
                    <p class="mb-0 opacity-75">Düğünlere katılmak için QR kodları tarayın</p>
                </div>
                <div class="col-md-4 text-end">
                    <div class="d-flex align-items-center justify-content-end gap-3">
                        <div class="text-end">
                            <div class="fw-bold">Hoş geldin, <?php echo htmlspecialchars($_SESSION['user_name'] ?? 'Kullanıcı'); ?></div>
                            <small class="opacity-75">QR kod tarayarak düğünlere katılın</small>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                <i class="fas fa-user-circle me-2"></i>Profil
                            </button>
                            <ul class="dropdown-menu">
                                <li><a class="dropdown-item" href="user_dashboard.php"><i class="fas fa-home me-2"></i>Ana Sayfa</a></li>
                                <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profilim</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="logout.php"><i class="fas fa-sign-out-alt me-2"></i>Çıkış</a></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Success Message -->
        <?php if (isset($success)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Info Message -->
        <?php if (isset($info)): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <i class="fas fa-info-circle me-2"></i>
                <?php echo htmlspecialchars($info); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Error Message -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- QR Scanner -->
                <div class="scanner-container">
                    <div class="scanner-header">
                        <h5 class="mb-0">
                            <i class="fas fa-camera me-2"></i>QR Kod Tarayıcı
                        </h5>
                    </div>
                    <div class="scanner-content">
                        <div id="qr-reader"></div>
                        
                        <div class="scanner-controls">
                            <div class="scanner-control active" id="cameraBtn">
                                <i class="fas fa-camera me-2"></i>Kamera
                            </div>
                            <div class="scanner-control" id="manualBtn">
                                <i class="fas fa-keyboard me-2"></i>Manuel
                            </div>
                        </div>
                        
                        <div id="qr-result" class="qr-result" style="display: none;">
                            <h6><i class="fas fa-check-circle me-2"></i>QR Kod Tarandı</h6>
                            <p id="qr-result-text" class="mb-0"></p>
                        </div>
                    </div>
                </div>

                <!-- Manual QR Input -->
                <div class="manual-input" id="manualInput" style="display: none;">
                    <h5 class="mb-3">
                        <i class="fas fa-keyboard me-2"></i>Manuel QR Kod Girişi
                    </h5>
                    <form method="POST">
                        <input type="hidden" name="action" value="manual_qr">
                        <div class="mb-3">
                            <label for="qr_code" class="form-label">QR Kod</label>
                            <input type="text" class="form-control" id="qr_code" name="qr_code" 
                                   placeholder="QR kod metnini buraya yapıştırın" required>
                            <div class="form-text">QR kod metnini kopyalayıp buraya yapıştırın.</div>
                        </div>
                        <button type="submit" class="scan-button">
                            <i class="fas fa-search me-2"></i>QR Kod Ara
                        </button>
                    </form>
                </div>

                <!-- Scanned Event -->
                <?php if (isset($scanned_event)): ?>
                    <div class="event-card">
                        <div class="event-cover">
                            <i class="fas fa-heart"></i>
                        </div>
                        <div class="event-title"><?php echo htmlspecialchars($scanned_event['baslik']); ?></div>
                        <div class="event-meta">
                            <div class="event-date">
                                <i class="fas fa-calendar me-1"></i>
                                <?php echo date('d.m.Y', strtotime($scanned_event['dugun_tarihi'])); ?>
                            </div>
                            <div class="event-moderator">
                                <i class="fas fa-user-tie me-1"></i>
                                <?php echo htmlspecialchars($scanned_event['moderator_ad'] . ' ' . $scanned_event['moderator_soyad']); ?>
                            </div>
                        </div>
                        <?php if ($scanned_event['aciklama']): ?>
                            <p class="text-muted"><?php echo htmlspecialchars($scanned_event['aciklama']); ?></p>
                        <?php endif; ?>
                        
                        <div class="d-flex gap-2 mt-3">
                            <a href="user_dashboard.php?event_id=<?php echo $scanned_event['id']; ?>" class="btn btn-success">
                                <i class="fas fa-eye me-2"></i>Düğüne Git
                            </a>
                            <button class="btn btn-outline-secondary" onclick="shareEvent(<?php echo $scanned_event['id']; ?>)">
                                <i class="fas fa-share me-2"></i>Paylaş
                            </button>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="col-lg-4">
                <!-- Recent Events -->
                <div class="recent-events">
                    <h5 class="mb-3">
                        <i class="fas fa-history me-2"></i>Son Katıldığınız Düğünler
                    </h5>
                    
                    <?php
                    // Kullanıcının son katıldığı düğünleri al
                    $stmt = $pdo->prepare("
                        SELECT 
                            d.*,
                            k.ad as moderator_ad,
                            k.soyad as moderator_soyad
                        FROM dugun_katilimcilar dk
                        JOIN dugunler d ON dk.dugun_id = d.id
                        JOIN kullanicilar k ON d.moderator_id = k.id
                        WHERE dk.kullanici_id = ? AND d.durum = 'aktif'
                        ORDER BY dk.katilim_tarihi DESC
                        LIMIT 5
                    ");
                    $stmt->execute([$user_id]);
                    $recent_events = $stmt->fetchAll();
                    ?>
                    
                    <?php if (empty($recent_events)): ?>
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-calendar-times fa-2x mb-3"></i>
                            <p>Henüz hiçbir düğüne katılmadınız.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_events as $event): ?>
                            <div class="recent-event-item" onclick="location.href='user_dashboard.php?event_id=<?php echo $event['id']; ?>'">
                                <div class="recent-event-avatar">
                                    <i class="fas fa-heart"></i>
                                </div>
                                <div class="recent-event-info">
                                    <div class="recent-event-title"><?php echo htmlspecialchars($event['baslik']); ?></div>
                                    <div class="recent-event-date">
                                        <?php echo date('d.m.Y', strtotime($event['dugun_tarihi'])); ?>
                                        <span class="mx-1">•</span>
                                        <?php echo htmlspecialchars($event['moderator_ad']); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        let html5QrcodeScanner = null;
        let isScanning = false;
        
        // Scanner initialization
        function initScanner() {
            html5QrcodeScanner = new Html5QrcodeScanner(
                "qr-reader",
                { 
                    fps: 10, 
                    qrbox: { width: 250, height: 250 },
                    aspectRatio: 1.0
                },
                false
            );
            
            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
            isScanning = true;
        }
        
        // Success callback
        function onScanSuccess(decodedText, decodedResult) {
            console.log('QR Code scanned:', decodedText);
            
            // Show result
            document.getElementById('qr-result').style.display = 'block';
            document.getElementById('qr-result-text').textContent = decodedText;
            
            // Stop scanning
            if (html5QrcodeScanner) {
                html5QrcodeScanner.clear();
                isScanning = false;
            }
            
            // Submit form
            const form = document.createElement('form');
            form.method = 'POST';
            form.innerHTML = `
                <input type="hidden" name="action" value="scan_qr">
                <input type="hidden" name="qr_data" value="${decodedText}">
            `;
            document.body.appendChild(form);
            form.submit();
        }
        
        // Failure callback
        function onScanFailure(error) {
            // Ignore common errors
            if (error.includes('No QR code found') || error.includes('NotFoundException')) {
                return;
            }
            console.log('QR scan error:', error);
        }
        
        // Toggle between camera and manual input
        document.getElementById('cameraBtn').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('manualBtn').classList.remove('active');
            document.getElementById('manualInput').style.display = 'none';
            
            if (!isScanning) {
                initScanner();
            }
        });
        
        document.getElementById('manualBtn').addEventListener('click', function() {
            this.classList.add('active');
            document.getElementById('cameraBtn').classList.remove('active');
            document.getElementById('manualInput').style.display = 'block';
            
            if (html5QrcodeScanner && isScanning) {
                html5QrcodeScanner.clear();
                isScanning = false;
            }
        });
        
        // Share event function
        function shareEvent(eventId) {
            if (navigator.share) {
                navigator.share({
                    title: 'Digital Salon - Düğün Daveti',
                    text: 'Bu düğüne katılmak için QR kod tarayın!',
                    url: window.location.origin + '/join_event.php?event_id=' + eventId
                });
            } else {
                // Fallback: copy to clipboard
                const url = window.location.origin + '/join_event.php?event_id=' + eventId;
                navigator.clipboard.writeText(url).then(() => {
                    alert('Davet linki kopyalandı!');
                });
            }
        }
        
        // Initialize scanner on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Check if camera is available
            if (navigator.mediaDevices && navigator.mediaDevices.getUserMedia) {
                initScanner();
            } else {
                // Camera not available, show manual input
                document.getElementById('cameraBtn').classList.remove('active');
                document.getElementById('manualBtn').classList.add('active');
                document.getElementById('manualInput').style.display = 'block';
            }
        });
        
        // Handle page visibility change
        document.addEventListener('visibilitychange', function() {
            if (document.hidden && html5QrcodeScanner && isScanning) {
                html5QrcodeScanner.clear();
                isScanning = false;
            } else if (!document.hidden && !isScanning && document.getElementById('cameraBtn').classList.contains('active')) {
                initScanner();
            }
        });
    </script>
</body>
</html>
