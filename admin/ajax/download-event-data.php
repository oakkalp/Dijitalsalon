<?php
// Hata raporlamayı aç
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Session'ı başlat
session_start();

require_once '../../config/database.php';

// Admin kontrolü
if (!isset($_SESSION['admin_user_id']) || !isset($_SESSION['admin_user_role'])) {
    http_response_code(401);
    exit('Yetkisiz erişim');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Geçersiz istek');
}

$event_id = (int)($_POST['event_id'] ?? 0);

if (!$event_id) {
    exit('Geçersiz düğün ID');
}

try {
    // Düğün bilgilerini al
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            k.ad as moderator_ad,
            k.soyad as moderator_soyad,
            p.ad as paket_ad,
            p.fiyat as paket_fiyat,
            p.ucretsiz_erisim_gun
        FROM dugunler d
        LEFT JOIN kullanicilar k ON d.moderator_id = k.id
        LEFT JOIN paketler p ON d.paket_id = p.id
        WHERE d.id = ?
    ");
    $stmt->execute([$event_id]);
    $event = $stmt->fetch();
    
    if (!$event) {
        throw new Exception('Düğün bulunamadı');
    }
    
    // Moderator kontrolü
    if ($_SESSION['admin_user_role'] === 'moderator' && $event['moderator_id'] != $_SESSION['admin_user_id']) {
        throw new Exception('Bu düğünü görme yetkiniz yok');
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
        ORDER BY dk.created_at DESC
    ");
    $stmt->execute([$event_id]);
    $participants = $stmt->fetchAll();
    
    // Medyaları al
    $stmt = $pdo->prepare("
        SELECT 
            m.*,
            k.ad as kullanici_ad,
            k.soyad as kullanici_soyad
        FROM medyalar m
        LEFT JOIN kullanicilar k ON m.kullanici_id = k.id
        WHERE m.dugun_id = ?
        ORDER BY m.created_at DESC
    ");
    $stmt->execute([$event_id]);
    $medias = $stmt->fetchAll();
    
    // Hikayeleri al (hikayeler tablosu yoksa boş array)
    $stories = [];
    try {
        $stmt = $pdo->prepare("
            SELECT 
                h.*,
                k.ad as kullanici_ad,
                k.soyad as kullanici_soyad
            FROM hikayeler h
            LEFT JOIN kullanicilar k ON h.kullanici_id = k.id
            WHERE h.dugun_id = ?
            ORDER BY h.created_at DESC
        ");
        $stmt->execute([$event_id]);
        $stories = $stmt->fetchAll();
    } catch (Exception $e) {
        // Hikayeler tablosu yoksa boş array kullan
        $stories = [];
    }
    
    // HTML sayfası oluştur
    $html = generateEventHTML($event, $participants, $medias, $stories);
    
    // Geçici dosya oluştur
    $temp_dir = sys_get_temp_dir() . '/event_' . $event_id . '_' . time();
    mkdir($temp_dir, 0755, true);
    
    // HTML dosyasını kaydet
    file_put_contents($temp_dir . '/index.html', $html);
    
    // Medya dosyalarını kopyala
    $media_dir = $temp_dir . '/media';
    mkdir($media_dir, 0755, true);
    
    foreach ($medias as $media) {
        if (!empty($media['dosya_yolu'])) {
            $source_path = '../' . $media['dosya_yolu'];
            if (file_exists($source_path)) {
                $filename = basename($media['dosya_yolu']);
                copy($source_path, $media_dir . '/' . $filename);
            }
        }
    }
    
    // Kapak fotoğrafını kopyala
    if (!empty($event['kapak_fotografi'])) {
        $source_path = '../' . $event['kapak_fotografi'];
        if (file_exists($source_path)) {
            copy($source_path, $temp_dir . '/cover.jpg');
        }
    }
    
    // ZIP oluştur
    $zip_file = $temp_dir . '.zip';
    createZipFromDirectory($temp_dir, $zip_file);
    
    // ZIP dosyasını gönder
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . $event['baslik'] . '_Dugun_Verileri.zip"');
    header('Content-Length: ' . filesize($zip_file));
    
    readfile($zip_file);
    
    // Geçici dosyaları temizle
    unlink($zip_file);
    deleteDirectory($temp_dir);
    
} catch (Exception $e) {
    http_response_code(500);
    exit('Hata: ' . $e->getMessage());
}

function generateEventHTML($event, $participants, $medias, $stories) {
    $event_name = htmlspecialchars($event['baslik'] ?? 'Düğün');
    $event_date = !empty($event['dugun_tarihi']) ? date('d.m.Y', strtotime($event['dugun_tarihi'])) : 'Belirtilmemiş';
    $event_location = htmlspecialchars($event['salon_adresi'] ?? '');
    $event_description = htmlspecialchars($event['aciklama'] ?? '');
    $moderator_name = htmlspecialchars(($event['moderator_ad'] ?? '') . ' ' . ($event['moderator_soyad'] ?? ''));
    $package_name = htmlspecialchars($event['paket_ad'] ?? 'Temel');
    $package_price = number_format($event['paket_fiyat'] ?? 0);
    $free_access_days = $event['ucretsiz_erisim_gun'] ?? 0;
    
    $html = '<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . $event_name . ' - Düğün Verileri</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; color: #1e293b; }
        .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .header { background: linear-gradient(135deg, #6366f1, #8b5cf6); color: white; padding: 2rem; border-radius: 15px; margin-bottom: 2rem; text-align: center; }
        .header h1 { font-size: 2.5rem; margin-bottom: 0.5rem; }
        .header p { font-size: 1.2rem; opacity: 0.9; }
        .info-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .info-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .info-card h3 { color: #6366f1; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        .info-item { display: flex; justify-content: space-between; margin-bottom: 0.5rem; padding: 0.5rem 0; border-bottom: 1px solid #e2e8f0; }
        .info-item:last-child { border-bottom: none; }
        .info-label { font-weight: 600; color: #64748b; }
        .info-value { color: #1e293b; }
        .section { background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .section h2 { color: #1e293b; margin-bottom: 1.5rem; display: flex; align-items: center; gap: 0.5rem; }
        .participant-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 1rem; }
        .participant-card { background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; }
        .participant-name { font-weight: 600; color: #1e293b; margin-bottom: 0.5rem; }
        .participant-info { font-size: 0.9rem; color: #64748b; }
        .media-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(200px, 1fr)); gap: 1rem; }
        .media-item { background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; text-align: center; }
        .media-item img { max-width: 100%; height: 150px; object-fit: cover; border-radius: 8px; margin-bottom: 0.5rem; }
        .media-info { font-size: 0.9rem; color: #64748b; }
        .story-item { background: #f8fafc; padding: 1rem; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 1rem; }
        .story-content { margin-bottom: 0.5rem; }
        .story-meta { font-size: 0.9rem; color: #64748b; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 1rem; margin-bottom: 2rem; }
        .stat-card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); text-align: center; }
        .stat-number { font-size: 2rem; font-weight: 700; color: #6366f1; margin-bottom: 0.5rem; }
        .stat-label { color: #64748b; font-weight: 600; }
        .cover-image { width: 100%; max-width: 400px; height: 300px; object-fit: cover; border-radius: 12px; margin: 1rem auto; display: block; }
        @media (max-width: 768px) { .container { padding: 1rem; } .header h1 { font-size: 2rem; } .info-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-heart"></i> ' . $event_name . '</h1>
            <p>Düğün Verileri ve Medya Arşivi</p>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <div class="stat-number">' . count($participants) . '</div>
                <div class="stat-label">Katılımcı</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . count($medias) . '</div>
                <div class="stat-label">Medya</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">' . count($stories) . '</div>
                <div class="stat-label">Hikaye</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">₺' . $package_price . '</div>
                <div class="stat-label">Paket Fiyatı</div>
            </div>
        </div>
        
        <div class="info-grid">
            <div class="info-card">
                <h3><i class="fas fa-info-circle"></i> Düğün Bilgileri</h3>
                <div class="info-item">
                    <span class="info-label">Düğün Adı:</span>
                    <span class="info-value">' . $event_name . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Tarih:</span>
                    <span class="info-value">' . $event_date . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Konum:</span>
                    <span class="info-value">' . $event_location . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Paket:</span>
                    <span class="info-value">' . $package_name . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Ücretsiz Erişim:</span>
                    <span class="info-value">' . $free_access_days . ' gün</span>
                </div>
            </div>
            
            <div class="info-card">
                <h3><i class="fas fa-user-shield"></i> Moderator Bilgileri</h3>
                <div class="info-item">
                    <span class="info-label">Moderator:</span>
                    <span class="info-value">' . $moderator_name . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">QR Kod:</span>
                    <span class="info-value">' . htmlspecialchars($event['qr_kod'] ?? '') . '</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Oluşturulma:</span>
                    <span class="info-value">' . date('d.m.Y H:i', strtotime($event['created_at'])) . '</span>
                </div>
            </div>
        </div>';
    
    if (!empty($event_description)) {
        $html .= '
        <div class="section">
            <h2><i class="fas fa-align-left"></i> Düğün Açıklaması</h2>
            <p>' . nl2br($event_description) . '</p>
        </div>';
    }
    
    if (!empty($event['kapak_fotografi'])) {
        $html .= '
        <div class="section">
            <h2><i class="fas fa-camera"></i> Kapak Fotoğrafı</h2>
            <img src="cover.jpg" alt="Kapak Fotoğrafı" class="cover-image">
        </div>';
    }
    
    if (!empty($participants)) {
        $html .= '
        <div class="section">
            <h2><i class="fas fa-users"></i> Katılımcılar (' . count($participants) . ')</h2>
            <div class="participant-grid">';
        
        foreach ($participants as $participant) {
            $name = htmlspecialchars(($participant['ad'] ?? '') . ' ' . ($participant['soyad'] ?? ''));
            $email = htmlspecialchars($participant['email'] ?? '');
            $phone = htmlspecialchars($participant['telefon'] ?? '');
            $role = htmlspecialchars($participant['rol'] ?? 'katılımcı');
            $status = htmlspecialchars($participant['durum'] ?? 'aktif');
            $join_date = date('d.m.Y H:i', strtotime($participant['created_at']));
            
            $html .= '
                <div class="participant-card">
                    <div class="participant-name">' . $name . '</div>
                    <div class="participant-info">
                        <div><i class="fas fa-envelope"></i> ' . $email . '</div>
                        <div><i class="fas fa-phone"></i> ' . $phone . '</div>
                        <div><i class="fas fa-user-tag"></i> ' . $role . '</div>
                        <div><i class="fas fa-circle" style="color: ' . ($status === 'aktif' ? '#10b981' : '#ef4444') . '"></i> ' . $status . '</div>
                        <div><i class="fas fa-calendar"></i> ' . $join_date . '</div>
                    </div>
                </div>';
        }
        
        $html .= '
            </div>
        </div>';
    }
    
    if (!empty($medias)) {
        $html .= '
        <div class="section">
            <h2><i class="fas fa-images"></i> Medyalar (' . count($medias) . ')</h2>
            <div class="media-grid">';
        
        foreach ($medias as $media) {
            $filename = basename($media['dosya_yolu']);
            $uploader = htmlspecialchars(($media['kullanici_ad'] ?? '') . ' ' . ($media['kullanici_soyad'] ?? ''));
            $upload_date = date('d.m.Y H:i', strtotime($media['created_at']));
            $media_type = htmlspecialchars($media['tur'] ?? 'medya');
            
            $html .= '
                <div class="media-item">
                    <img src="media/' . $filename . '" alt="' . htmlspecialchars($media['aciklama'] ?? '') . '">
                    <div class="media-info">
                        <div><strong>' . htmlspecialchars($media['aciklama'] ?? 'Başlıksız') . '</strong></div>
                        <div><i class="fas fa-user"></i> ' . $uploader . '</div>
                        <div><i class="fas fa-tag"></i> ' . $media_type . '</div>
                        <div><i class="fas fa-calendar"></i> ' . $upload_date . '</div>
                    </div>
                </div>';
        }
        
        $html .= '
            </div>
        </div>';
    }
    
    if (!empty($stories)) {
        $html .= '
        <div class="section">
            <h2><i class="fas fa-book"></i> Hikayeler (' . count($stories) . ')</h2>';
        
        foreach ($stories as $story) {
            $author = htmlspecialchars(($story['kullanici_ad'] ?? '') . ' ' . ($story['kullanici_soyad'] ?? ''));
            $created_date = date('d.m.Y H:i', strtotime($story['created_at']));
            
            $html .= '
                <div class="story-item">
                    <div class="story-content">' . nl2br(htmlspecialchars($story['icerik'] ?? '')) . '</div>
                    <div class="story-meta">
                        <i class="fas fa-user"></i> ' . $author . ' | 
                        <i class="fas fa-calendar"></i> ' . $created_date . '
                    </div>
                </div>';
        }
        
        $html .= '
        </div>';
    }
    
    $html .= '
        <div class="section">
            <h2><i class="fas fa-download"></i> İndirme Bilgileri</h2>
            <p>Bu dosya ' . date('d.m.Y H:i') . ' tarihinde oluşturulmuştur.</p>
            <p>Düğün: <strong>' . $event_name . '</strong></p>
            <p>Toplam Katılımcı: <strong>' . count($participants) . '</strong></p>
            <p>Toplam Medya: <strong>' . count($medias) . '</strong></p>
            <p>Toplam Hikaye: <strong>' . count($stories) . '</strong></p>
        </div>
    </div>
</body>
</html>';
    
    return $html;
}

function createZipFromDirectory($source, $destination) {
    $zip = new ZipArchive();
    if ($zip->open($destination, ZipArchive::CREATE) !== TRUE) {
        throw new Exception('ZIP dosyası oluşturulamadı');
    }
    
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $zip->addFile($file->getRealPath(), $iterator->getSubPathName());
        }
    }
    
    $zip->close();
}

function deleteDirectory($dir) {
    if (!is_dir($dir)) return;
    
    $files = array_diff(scandir($dir), array('.', '..'));
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }
    rmdir($dir);
}
?>
