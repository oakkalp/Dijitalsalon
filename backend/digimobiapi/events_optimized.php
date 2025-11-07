<?php
/**
 * ✅ OPTIMIZED Events Endpoint
 * Connection pooling + Query caching ile optimize edilmiş versiyon
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/cache_helper.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    json_err(405, 'Method not allowed');
}

$user_id = require_auth();

try {
    // ✅ Cache key oluştur (user_id bazlı)
    $cache_key = "events_user_{$user_id}";
    $cached_result = QueryCache::get("SELECT * FROM dugun_katilimcilar WHERE kullanici_id = ?", [$user_id]);
    
    if ($cached_result !== null) {
        // Cache'den döndür (çok hızlı!)
        json_ok(['events' => $cached_result, 'cached' => true]);
        exit;
    }
    
    // ✅ OPTIMIZED: Nested SELECT'leri LEFT JOIN'e çevir (çok daha hızlı)
    // ✅ Index kullanımı: idx_dugun_katilimcilar_user_status, idx_medyalar_dugun_tur
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            k.ad as moderator_ad,
            k.soyad as moderator_soyad,
            p.ad as paket_ad,
            p.ucretsiz_erisim_gun,
            COALESCE(COUNT(DISTINCT dk2.kullanici_id), 0) as katilimci_sayisi,
            COALESCE(SUM(CASE WHEN m.tur IS NULL OR m.tur != 'hikaye' THEN 1 ELSE 0 END), 0) as medya_sayisi,
            COALESCE(SUM(CASE WHEN m.tur = 'hikaye' THEN 1 ELSE 0 END), 0) as hikaye_sayisi,
            dk.rol as katilimci_rol,
            dk.katilim_tarihi,
            dk.yetkiler
        FROM dugun_katilimcilar dk
        INNER JOIN dugunler d ON dk.dugun_id = d.id
        INNER JOIN kullanicilar k ON d.moderator_id = k.id
        LEFT JOIN paketler p ON d.paket_id = p.id
        LEFT JOIN dugun_katilimcilar dk2 ON dk2.dugun_id = d.id AND dk2.durum = 'aktif'
        LEFT JOIN medyalar m ON m.dugun_id = d.id
        WHERE dk.kullanici_id = ? AND dk.durum = 'aktif'
        GROUP BY d.id, k.ad, k.soyad, p.ad, p.ucretsiz_erisim_gun, dk.rol, dk.katilim_tarihi, dk.yetkiler
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
        $today->setTime(0, 0, 0);
        $event_date->setTime(0, 0, 0);
        
        $free_access_days = (int)($event['ucretsiz_erisim_gun'] ?? 7);
        $access_end_date = clone $event_date;
        $access_end_date->add(new DateInterval("P{$free_access_days}D"));
        
        $is_before_event = $today < $event_date;
        $is_after_access = $today > $access_end_date;
        
        $base_permissions = [
            'medya_paylasabilir' => false,
            'yorum_yapabilir' => false,
            'hikaye_paylasabilir' => false,
            'medya_silebilir' => false,
            'yorum_silebilir' => false,
            'kullanici_engelleyebilir' => false,
            'yetki_duzenleyebilir' => false,
            'baska_kullanici_yetki_degistirebilir' => false,
            'baska_kullanici_yasaklayabilir' => false,
            'baska_kullanici_silebilir' => false,
        ];
        
        $user_role = $event['katilimci_rol'] ?: 'kullanici';
        $is_moderator = $user_role === 'moderator';
        $is_authorized = $user_role === 'yetkili_kullanici';
        
        if ($is_moderator) {
            $permissions = [
                'medya_paylasabilir' => true,
                'yorum_yapabilir' => true,
                'hikaye_paylasabilir' => true,
                'medya_silebilir' => true,
                'yorum_silebilir' => true,
                'kullanici_engelleyebilir' => true,
                'yetki_duzenleyebilir' => true,
                'baska_kullanici_yetki_degistirebilir' => true,
                'baska_kullanici_yasaklayabilir' => true,
                'baska_kullanici_silebilir' => true,
            ];
        } elseif ($is_authorized) {
            $json_permissions_raw = $event['yetkiler'] ? json_decode($event['yetkiler'], true) : [];
            $json_permissions = [];
            if (is_array($json_permissions_raw)) {
                if (isset($json_permissions_raw[0])) {
                    foreach ($json_permissions_raw as $perm) {
                        if (is_string($perm)) {
                            $json_permissions[$perm] = true;
                        }
                    }
                } else {
                    $json_permissions = $json_permissions_raw;
                }
            }
            
            $permissions = $base_permissions;
            foreach ($json_permissions as $key => $value) {
                $permissions[$key] = $value;
            }
            
            if (isset($json_permissions['medya_yukleyebilir'])) {
                $permissions['medya_paylasabilir'] = $json_permissions['medya_yukleyebilir'];
            }
            if (isset($json_permissions['hikaye_ekleyebilir'])) {
                $permissions['hikaye_paylasabilir'] = $json_permissions['hikaye_ekleyebilir'];
            }
            
            if ($is_before_event || $is_after_access) {
                $permissions['medya_paylasabilir'] = false;
                $permissions['yorum_yapabilir'] = false;
                $permissions['hikaye_paylasabilir'] = false;
            }
        } else {
            $permissions = $base_permissions;
            if (!$is_before_event && !$is_after_access) {
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
            'saat' => $event['saat'] ?? null,
            'konum' => $event['salon_adresi'] ?? $event['konum'] ?? null,
            'olusturan_id' => (int)$event['moderator_id'],
            'kapak_fotografi' => $event['kapak_fotografi'] ? 'https://dijitalsalon.cagapps.app/' . $event['kapak_fotografi'] : null,
            'kapak_fotografi_thumbnail' => $event['kapak_fotografi'] ? getThumbnailUrl($event['kapak_fotografi']) : null,
            'kapak_fotografi_preview' => $event['kapak_fotografi'] ? getPreviewUrl($event['kapak_fotografi']) : null,
            'qr_kod' => $event['qr_kod'] ?? null,
            'moderator_ad' => $event['moderator_ad'],
            'moderator_soyad' => $event['moderator_soyad'],
            'paket_ad' => $event['paket_ad'],
            'katilimci_sayisi' => (int)$event['katilimci_sayisi'],
            'medya_sayisi' => (int)$event['medya_sayisi'],
            'hikaye_sayisi' => (int)$event['hikaye_sayisi'],
            'katilimci_rol' => $event['katilimci_rol'],
            'katilim_tarihi' => $event['katilim_tarihi'],
            'created_at' => $event['created_at'],
            'user_role' => $user_role,
            'user_permissions' => $permissions,
            'participant_count' => (int)$event['katilimci_sayisi'],
            'media_count' => (int)$event['medya_sayisi'],
            'story_count' => (int)$event['hikaye_sayisi'],
            'package_type' => $event['paket_ad'] ?? 'Basic',
            'free_access_days' => $free_access_days,
            'access_end_date' => $access_end_date->format('Y-m-d H:i:s'),
            'is_before_event' => $is_before_event,
            'is_after_access' => $is_after_access,
        ];
    }
    
    // ✅ Cache'e kaydet (5 dakika TTL)
    QueryCache::set("SELECT * FROM dugun_katilimcilar WHERE kullanici_id = ?", [$user_id], $formatted_events, 300);
    
    json_ok(['events' => $formatted_events, 'cached' => false]);
    
} catch (Exception $e) {
    json_err(500, 'Database error: ' . $e->getMessage());
}

// Helper functions
function getThumbnailUrl($image_path) {
    if (!$image_path) return null;
    $path_info = pathinfo($image_path);
    $extension = strtolower($path_info['extension']);
    $filename_without_ext = $path_info['filename'];
    return 'https://dijitalsalon.cagapps.app/' . $path_info['dirname'] . '/' . $filename_without_ext . '_thumb.' . $extension;
}

function getPreviewUrl($image_path) {
    if (!$image_path) return null;
    $path_info = pathinfo($image_path);
    $extension = strtolower($path_info['extension']);
    $filename_without_ext = $path_info['filename'];
    return 'https://dijitalsalon.cagapps.app/' . $path_info['dirname'] . '/' . $filename_without_ext . '_preview.' . $extension;
}
?>

