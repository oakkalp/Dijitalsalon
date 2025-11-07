<?php
require_once __DIR__ . '/bootstrap.php';

// Session kontrolü
if (!isset($_SESSION['user_id'])) {
    json_err(401, 'Unauthorized');
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $target_user_id = $_GET['user_id'] ?? null;
    
    if (empty($target_user_id)) {
        json_err(400, 'User ID is required');
    }
    
    try {
        // Hedef kullanıcının katıldığı etkinlikleri al
        $stmt = $pdo->prepare("
            SELECT 
                d.*,
                k.ad as moderator_ad,
                k.soyad as moderator_soyad,
                p.ad as paket_ad,
                p.ucretsiz_erisim_gun,
                (SELECT COUNT(DISTINCT dk2.kullanici_id) FROM dugun_katilimcilar dk2 WHERE dk2.dugun_id = d.id) as katilimci_sayisi,
                (SELECT COUNT(DISTINCT m.id) FROM medyalar m WHERE m.dugun_id = d.id) as medya_sayisi,
                (SELECT COUNT(DISTINCT m2.id) FROM medyalar m2 WHERE m2.dugun_id = d.id AND m2.tur = 'hikaye') as hikaye_sayisi,
                dk.rol as katilimci_rol,
                dk.katilim_tarihi,
                dk.yetkiler
            FROM dugun_katilimcilar dk
            JOIN dugunler d ON dk.dugun_id = d.id
            JOIN kullanicilar k ON d.moderator_id = k.id
            LEFT JOIN paketler p ON d.paket_id = p.id
            WHERE dk.kullanici_id = ? AND dk.durum = 'aktif'
            ORDER BY d.dugun_tarihi DESC
        ");
        $stmt->execute([$target_user_id]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format events for mobile app
        $formatted_events = [];
        foreach ($events as $event) {
            // Event date calculations
            $event_date = new DateTime($event['dugun_tarihi']);
            $today = new DateTime();
            $today->setTime(0, 0, 0);
            $event_date->setTime(0, 0, 0);
            
            // Free access days (default 7 days)
            $free_access_days = (int)($event['ucretsiz_erisim_gun'] ?? 7);
            $access_end_date = clone $event_date;
            $access_end_date->add(new DateInterval("P{$free_access_days}D"));
            
            // Determine event status
            $event_status = 'past';
            if ($event_date == $today) {
                $event_status = 'today';
            } elseif ($event_date > $today) {
                $event_status = 'upcoming';
            }
            
            // Format cover photo URL
            $cover_photo = null;
            if ($event['kapak_fotografi']) {
                if (strpos($event['kapak_fotografi'], 'http') !== 0) {
                    $cover_photo = 'https://dijitalsalon.cagapps.app/uploads/events/' . basename($event['kapak_fotografi']);
                } else {
                    $cover_photo = $event['kapak_fotografi'];
                }
            }
            
            $formatted_events[] = [
                'id' => $event['id'],
                'baslik' => $event['baslik'],
                'aciklama' => $event['aciklama'],
                'tarih' => $event['dugun_tarihi'],
                'konum' => $event['salon_adresi'] ?? $event['konum'] ?? null,
                'olusturan_id' => $event['moderator_id'],
                'kapak_fotografi' => $cover_photo,
                'kapak_fotografi_thumbnail' => $cover_photo ? str_replace('.jpg', '_thumb.jpg', $cover_photo) : null,
                'kapak_fotografi_preview' => $cover_photo ? str_replace('.jpg', '_preview.jpg', $cover_photo) : null,
                'qr_kod' => $event['qr_kod'],
                'moderator_ad' => $event['moderator_ad'],
                'moderator_soyad' => $event['moderator_soyad'],
                'paket_ad' => $event['paket_ad'],
                'katilimci_sayisi' => $event['katilimci_sayisi'],
                'medya_sayisi' => $event['medya_sayisi'],
                'hikaye_sayisi' => $event['hikaye_sayisi'],
                'katilimci_rol' => $event['katilimci_rol'],
                'katilim_tarihi' => $event['katilim_tarihi'],
                'created_at' => $event['created_at'],
                'user_role' => $event['katilimci_rol'],
                'user_permissions' => json_decode($event['yetkiler'] ?? '{}', true),
                'participant_count' => $event['katilimci_sayisi'],
                'media_count' => $event['medya_sayisi'],
                'story_count' => $event['hikaye_sayisi'],
                'package_type' => $event['paket_ad'],
                'free_access_days' => $free_access_days,
                'access_end_date' => $access_end_date->format('Y-m-d'),
                'event_status' => $event_status,
            ];
        }
        
        json_ok(['events' => $formatted_events]);
        
    } catch (Exception $e) {
        error_log("Get User Events API Error: " . $e->getMessage());
        json_err(500, 'Database error');
    }
} else {
    json_err(405, 'Method not allowed');
}
?>
