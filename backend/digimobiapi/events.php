<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/cache_helper.php';

$method = $_SERVER['REQUEST_METHOD'];

if ($method !== 'GET') {
    json_err(405, 'Method not allowed');
}

$user_id = require_auth();

try {
    // ✅ bypassCache parametresi kontrolü (QR kod tarandıktan sonra cache'i atla)
    $bypass_cache = isset($_GET['bypass_cache']) && $_GET['bypass_cache'] === 'true';
    
    // ✅ Cache'den kontrol et (bypass_cache false ise)
    if (!$bypass_cache) {
        $cache_key_query = "SELECT * FROM dugun_katilimcilar WHERE kullanici_id = ? AND durum = 'aktif'";
        $cached_events = QueryCache::get($cache_key_query, [$user_id]);
        
        if ($cached_events !== null) {
            // Cache'den döndür (<1ms!)
            json_ok(['events' => $cached_events, 'cached' => true]);
            exit;
        }
    } else {
        error_log("Events API - Cache bypassed (bypass_cache=true)");
    }
    
    // ✅ OPTIMIZED: Nested SELECT'leri LEFT JOIN'e çevir (çok daha hızlı)
    // ✅ Index kullanımı: idx_dugun_katilimcilar_user_status, idx_medyalar_dugun_tur
    // ✅ FIX: Medya sayısını subquery ile hesapla (JOIN'lerden etkilenmesin)
    $stmt = $pdo->prepare("
        SELECT 
            d.*,
            k.ad as moderator_ad,
            k.soyad as moderator_soyad,
            p.ad as paket_ad,
            p.ucretsiz_erisim_gun,
            COALESCE(COUNT(DISTINCT dk2.kullanici_id), 0) as katilimci_sayisi,
            -- ✅ Medya sayısı: Subquery ile hesapla (JOIN'lerden etkilenmesin)
            -- ✅ FIX: media.php ile aynı condition kullan (empty string kontrolü dahil)
            (SELECT COUNT(*) 
             FROM medyalar m 
             WHERE m.dugun_id = d.id 
             AND (m.tur IS NULL OR m.tur = '' OR m.tur != 'hikaye')) as medya_sayisi,
            -- ✅ Hikaye sayısı: Subquery ile hesapla (JOIN'lerden etkilenmesin)
            (SELECT COUNT(*) 
             FROM medyalar m 
             WHERE m.dugun_id = d.id 
             AND m.tur = 'hikaye') as hikaye_sayisi,
            dk.rol as katilimci_rol,
            dk.katilim_tarihi,
            dk.yetkiler
        FROM dugun_katilimcilar dk
        INNER JOIN dugunler d ON dk.dugun_id = d.id
        INNER JOIN kullanicilar k ON d.moderator_id = k.id
        LEFT JOIN paketler p ON d.paket_id = p.id
        LEFT JOIN dugun_katilimcilar dk2 ON dk2.dugun_id = d.id AND dk2.durum = 'aktif'
        WHERE dk.kullanici_id = ? AND dk.durum = 'aktif'
        GROUP BY d.id, k.ad, k.soyad, p.ad, p.ucretsiz_erisim_gun, dk.rol, dk.katilim_tarihi, dk.yetkiler
        ORDER BY d.dugun_tarihi DESC
    ");
    $stmt->execute([$user_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✅ Debug: Medya sayısı kontrolü ve düzeltme
    foreach ($events as $index => $event) {
        error_log("Events API - Event ID: {$event['id']}, Title: {$event['baslik']}, Medya Sayısı (from query): {$event['medya_sayisi']}");
        
        // ✅ Doğrulama için gerçek sayıyı kontrol et
        $verify_stmt = $pdo->prepare("
            SELECT COUNT(*) as real_count
            FROM medyalar m 
            WHERE m.dugun_id = ? 
            AND (m.tur IS NULL OR m.tur = '' OR m.tur != 'hikaye')
        ");
        $verify_stmt->execute([$event['id']]);
        $verify_result = $verify_stmt->fetch(PDO::FETCH_ASSOC);
        $real_count = (int)($verify_result['real_count'] ?? 0);
        
        if ($real_count != (int)$event['medya_sayisi']) {
            error_log("⚠️ Events API - MISMATCH for Event ID {$event['id']}: Query says {$event['medya_sayisi']}, Real count: $real_count");
            // ✅ Yanlış sayıyı düzelt
            $events[$index]['medya_sayisi'] = $real_count;
        }
    }
    
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
            // ✅ Participants API ile uyumlu yetkiler
            'baska_kullanici_yetki_degistirebilir' => false,
            'baska_kullanici_yasaklayabilir' => false,
            'baska_kullanici_silebilir' => false,
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
                // ✅ Participants API ile uyumlu yetkiler
                'baska_kullanici_yetki_degistirebilir' => true,
                'baska_kullanici_yasaklayabilir' => true,
                'baska_kullanici_silebilir' => true,
            ];
        } elseif ($is_authorized) {
            // Yetkili kullanıcı: JSON'dan gelen yetkiler + tarih kontrolü
            $json_permissions_raw = $event['yetkiler'] ? json_decode($event['yetkiler'], true) : [];
            
            // ✅ Yetkiler array formatında geliyorsa (["medya_paylasabilir", ...]) Map'e çevir
            // ✅ Ya da object formatında geliyorsa ({"medya_paylasabilir": true, ...}) direkt kullan
            $json_permissions = [];
            if (is_array($json_permissions_raw)) {
                if (isset($json_permissions_raw[0])) {
                    // Array formatı: ["medya_paylasabilir", "hikaye_paylasabilir"]
                    foreach ($json_permissions_raw as $perm) {
                        if (is_string($perm)) {
                            $json_permissions[$perm] = true;
                        }
                    }
                } else {
                    // Object formatı: {"medya_paylasabilir": true}
                    $json_permissions = $json_permissions_raw;
                }
            }
            
            // ✅ Base permissions ile birleştir (array_merge yerine manual merge)
            $permissions = $base_permissions;
            foreach ($json_permissions as $key => $value) {
                $permissions[$key] = $value;
            }
            
            // ✅ Eski yetki isimlerini yeni isimlere map et
            if (isset($json_permissions['medya_yukleyebilir'])) {
                $permissions['medya_paylasabilir'] = $json_permissions['medya_yukleyebilir'];
            }
            if (isset($json_permissions['hikaye_ekleyebilir'])) {
                $permissions['hikaye_paylasabilir'] = $json_permissions['hikaye_ekleyebilir'];
            }
            
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
            'saat' => $event['saat'] ?? null, // ✅ Saat alanı eklendi
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
            // Mobile app için ek alanlar
            'user_role' => $user_role,
            'user_permissions' => $permissions,
            'participant_count' => (int)$event['katilimci_sayisi'],
            'media_count' => (int)$event['medya_sayisi'],
            'story_count' => (int)$event['hikaye_sayisi'],
            'package_type' => $event['paket_ad'] ?? 'Basic',
            'free_access_days' => $free_access_days,
            // ✅ Debug bilgileri
            'access_end_date' => $access_end_date->format('Y-m-d H:i:s'),
            'is_before_event' => $is_before_event,
            'is_after_access' => $is_after_access,
        ];
    }
    
    // ✅ Cache'e kaydet (bypass_cache false ise - normal durumda cache'e kaydet)
    if (!$bypass_cache) {
        QueryCache::set(
            $cache_key_query, 
            [$user_id], 
            $formatted_events, 
            300 // 5 dakika
        );
    } else {
        error_log("Events API - Cache not saved (bypass_cache=true)");
    }
    
    json_ok(['events' => $formatted_events, 'cached' => false]);
    
} catch (Exception $e) {
    json_err(500, 'Database error: ' . $e->getMessage());
}

// Helper functions for generating optimized image URLs
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
