# 🚀 Profil Sayfası Optimizasyon Önerileri

## Problem
Profil sayfası 48 saniyede açılıyor çünkü:
- Her event için ayrı API çağrısı yapılıyor (5 event = 5 çağrı)
- Media + Stories ayrı ayrı yükleniyor
- Toplam: ~10-15 API çağrısı = YAVAŞ! ❌

## Instagram Çözümü
Instagram tek bir endpoint ile tüm profil verilerini alır:
```
GET /api/v1/users/{user_id}/profile_stats
→ Tek sorguda: event_count, media_count, story_count, initial_media
```

## Çözüm: Backend'de Optimize Endpoint

### Yeni Endpoint: `digimobiapi/get_profile_stats.php`

```php
<?php
require_once '../config/database.php';
require_once '../digimobiapi/bootstrap.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$user_id = $_SESSION['user_id'];
$target_user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : $user_id;

// ✅ TEK SORGU İLE TÜM VERİLER (Instagram gibi!)
$query = "
    SELECT 
        -- Event count
        (SELECT COUNT(DISTINCT dk.dugun_id) 
         FROM dugun_katilimcilar dk 
         WHERE dk.kullanici_id = ? AND dk.status = 'aktif') as event_count,
        
        -- Media count (sadece görsel medya, hikaye değil)
        (SELECT COUNT(*) 
         FROM medya m
         INNER JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id
         WHERE m.kullanici_id = ? AND dk.kullanici_id = ? AND m.tur != 'hikaye' AND dk.status = 'aktif') as media_count,
        
        -- Story count
        (SELECT COUNT(*) 
         FROM medya m
         INNER JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id
         WHERE m.kullanici_id = ? AND dk.kullanici_id = ? AND m.tur = 'hikaye' 
         AND dk.status = 'aktif'
         AND m.olusturma_tarihi > DATE_SUB(NOW(), INTERVAL 24 HOUR)) as story_count
";

$stmt = $conn->prepare($query);
$stmt->bind_param("iiiiii", $target_user_id, $target_user_id, $user_id, $target_user_id, $target_user_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats = $result->fetch_assoc();

// ✅ İlk 13 medya thumbnail (profil grid için)
$media_query = "
    SELECT 
        m.id,
        m.tur,
        COALESCE(m.kucuk_resim_yolu, m.dosya_yolu) as thumbnail,
        m.olusturma_tarihi
    FROM medya m
    INNER JOIN dugun_katilimcilar dk ON m.dugun_id = dk.dugun_id
    WHERE m.kullanici_id = ? 
    AND dk.kullanici_id = ? 
    AND m.tur != 'hikaye'
    AND dk.status = 'aktif'
    ORDER BY m.olusturma_tarihi DESC
    LIMIT 13
";

$media_stmt = $conn->prepare($media_query);
$media_stmt->bind_param("ii", $target_user_id, $user_id);
$media_stmt->execute();
$media_result = $media_stmt->get_result();
$initial_media = [];
while ($row = $media_result->fetch_assoc()) {
    $initial_media[] = [
        'id' => $row['id'],
        'type' => $row['tur'],
        'thumbnail' => $row['thumbnail'] ? 'https://dijitalsalon.cagapps.app/' . $row['thumbnail'] : null,
        'created_at' => $row['olusturma_tarihi']
    ];
}

echo json_encode([
    'success' => true,
    'stats' => [
        'event_count' => intval($stats['event_count']),
        'media_count' => intval($stats['media_count']),
        'story_count' => intval($stats['story_count'])
    ],
    'initial_media' => $initial_media
]);
```

### Flutter Tarafında Kullanım

```dart
// ✅ ApiService'e ekle
Future<Map<String, dynamic>> getProfileStats(int? userId) async {
  final sessionKey = await _getSessionKey();
  final url = userId != null 
      ? '$_baseUrl/get_profile_stats.php?user_id=$userId'
      : '$_baseUrl/get_profile_stats.php';
  
  final response = await _makeRequest(() => http.get(
    Uri.parse(url),
    headers: await _getHeaders(),
  ));
  
  if (response.statusCode == 200) {
    return json.decode(response.body);
  }
  throw Exception('Failed to load profile stats');
}
```

## Sonuç

**Önceki:** 10-15 API çağrısı = 48 saniye ❌  
**Yeni:** 1 API çağrısı = ~1-2 saniye ✅

## Adımlar

1. ✅ Backend'de `get_profile_stats.php` oluştur
2. ✅ Flutter'da `ApiService.getProfileStats()` ekle
3. ✅ `_loadAllProfileData()` metodunu bu endpoint'i kullanacak şekilde güncelle
4. ✅ Test et!

---

**Not:** Şu an Flutter tarafında Instagram tarzı optimistic UI eklendi (cache'den önce göster). Backend endpoint'i eklendiğinde performans daha da artacak!

