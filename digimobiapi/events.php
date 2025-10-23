<?php
require_once __DIR__ . '/bootstrap.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    json_err(405, 'Method not allowed');
}

$user_id = require_auth();

try {
    // Kullanıcının katıldığı düğünleri al (paket bilgileri ile)
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            k.ad as moderator_ad,
            k.soyad as moderator_soyad,
            p.ad as paket_ad,
            p.ucretsiz_erisim_gun,
            (SELECT COUNT(DISTINCT dk2.kullanici_id) FROM dugun_katilimcilar dk2 WHERE dk2.dugun_id = d.id) as katilimci_sayisi,
            (SELECT COUNT(DISTINCT m.id) FROM medyalar m WHERE m.dugun_id = d.id) as medya_sayisi,
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
    $stmt->execute([$user_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format events for mobile app with permission control
    $formatted_events = [];
    foreach ($events as $event) {
        // ✅ Yetki kontrolü için tarih hesaplamaları
        $event_date = new DateTime($event['dugun_tarihi']);
        $today = new DateTime();
        $today->setTime(0, 0, 0); // Bugünün başlangıcı
        $event_date->setTime(0, 0, 0); // Etkinlik tarihinin başlangıcı
        
        // ✅ Ücretsiz erişim günü (varsayılan 7 gün)
        $free_access_days = (int)($event['ucretsiz_erisim_gun'] ?? 7);
        $access_end_date = clone $event_date;
        $access_end_date->add(new DateInterval("P{$free_access_days}D"));
        
        // ✅ Yetki durumunu belirle
        $is_before_event = $today < $event_date; // Etkinlik henüz başlamamış
        $is_after_access = $today > $access_end_date; // Ücretsiz erişim süresi bitmiş
        
        // ✅ Temel yetkiler (moderator ve yetkili kullanıcılar hariç)
        $base_permissions = [
            'medya_paylasabilir' => false,
            'yorum_yapabilir' => false,
            'hikaye_paylasabilir' => false,
            'medya_silebilir' => false,
            'yorum_silebilir' => false,
            'kullanici_engelleyebilir' => false,
            'yetki_duzenleyebilir' => false,
        ];
        
        // ✅ Rol kontrolü
        $user_role = $event['katilimci_rol'] ?: 'kullanici';
        $is_moderator = $user_role === 'moderator';
        $is_authorized = $user_role === 'yetkili_kullanici';
        
        // ✅ Yetkileri belirle
        if ($is_moderator) {
            // Moderator: Her zaman tüm yetkiler
            $permissions = [
                'medya_paylasabilir' => true,
                'yorum_yapabilir' => true,
                'hikaye_paylasabilir' => true,
                'medya_silebilir' => true,
                'yorum_silebilir' => true,
                'kullanici_engelleyebilir' => true,
                'yetki_duzenleyebilir' => true,
            ];
        } elseif ($is_authorized) {
            // Yetkili kullanıcı: JSON'dan gelen yetkiler + tarih kontrolü
            $json_permissions = $event['yetkiler'] ? json_decode($event['yetkiler'], true) : [];
            $permissions = array_merge($base_permissions, $json_permissions);
            
            // ✅ Tarih kontrolü: Etkinlik başlamadan veya erişim süresi bitmişse paylaşım yetkilerini kaldır
            if ($is_before_event || $is_after_access) {
                $permissions['medya_paylasabilir'] = false;
                $permissions['yorum_yapabilir'] = false;
                $permissions['hikaye_paylasabilir'] = false;
            }
        } else {
            // Normal kullanıcı: Sadece görüntüleme
            $permissions = $base_permissions;
            
            // ✅ Tarih kontrolü: Etkinlik başlamadan veya erişim süresi bitmişse hiçbir yetki yok
            if ($is_before_event || $is_after_access) {
                // Zaten false, değişiklik yok
            } else {
                // Etkinlik aktif ve erişim süresi içinde: Temel paylaşım yetkileri
                $permissions['medya_paylasabilir'] = true;
                $permissions['yorum_yapabilir'] = true;
                $permissions['hikaye_paylasabilir'] = true;
            }
        }
        
        $formatted_events[] = [
            'id' => (int)$event['id'],
            'baslik' => $event['baslik'],
            'aciklama' => $event['aciklama'],
            'tarih' => $event['dugun_tarihi'],
            'konum' => $event['konum'] ?? null,
            'olusturan_id' => (int)$event['moderator_id'],
            'kapak_fotografi' => $event['kapak_fotografi'] ?? null,
            'qr_kod' => $event['qr_kod'] ?? null,
            'moderator_ad' => $event['moderator_ad'],
            'moderator_soyad' => $event['moderator_soyad'],
            'paket_ad' => $event['paket_ad'],
            'katilimci_sayisi' => (int)$event['katilimci_sayisi'],
            'medya_sayisi' => (int)$event['medya_sayisi'],
            'katilimci_rol' => $event['katilimci_rol'],
            'katilim_tarihi' => $event['katilim_tarihi'],
            'created_at' => $event['created_at'],
            // Mobile app için ek alanlar
            'user_role' => $user_role,
            'user_permissions' => $permissions,
            'participant_count' => (int)$event['katilimci_sayisi'],
            'media_count' => (int)$event['medya_sayisi'],
            'package_type' => $event['paket_ad'] ?? 'Basic',
            'free_access_days' => $free_access_days,
            // ✅ Debug bilgileri
            'access_end_date' => $access_end_date->format('Y-m-d H:i:s'),
            'is_before_event' => $is_before_event,
            'is_after_access' => $is_after_access,
        ];
    }
    
    json_ok(['events' => $formatted_events]);
    
} catch (Exception $e) {
    json_err(500, 'Database error: ' . $e->getMessage());
}
?>
