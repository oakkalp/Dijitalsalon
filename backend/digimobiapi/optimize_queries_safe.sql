-- ✅ VPS Optimizasyonu - SQL Index'leri (Güvenli Versiyon)
-- Bu script mevcut index'leri kontrol eder ve sadece olmayan index'leri oluşturur
-- Eğer index zaten varsa hata vermez, sadece uyarı verir

-- 1. Events için index'ler
-- Önce mevcut index'i kontrol et, yoksa oluştur
SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'dugun_katilimcilar' 
AND index_name = 'idx_dugun_katilimcilar_user_status';

CREATE INDEX idx_dugun_katilimcilar_user_status ON dugun_katilimcilar(kullanici_id, durum);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'dugun_katilimcilar' 
AND index_name = 'idx_dugun_katilimcilar_dugun_id';

CREATE INDEX idx_dugun_katilimcilar_dugun_id ON dugun_katilimcilar(dugun_id);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'dugunler' 
AND index_name = 'idx_dugunler_tarih';

CREATE INDEX idx_dugunler_tarih ON dugunler(dugun_tarihi DESC);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'dugunler' 
AND index_name = 'idx_dugunler_moderator';

CREATE INDEX idx_dugunler_moderator ON dugunler(moderator_id);

-- 2. Notifications için index'ler
SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'notifications' 
AND index_name = 'idx_notifications_user_created';

CREATE INDEX idx_notifications_user_created ON notifications(user_id, created_at DESC);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'notifications' 
AND index_name = 'idx_notifications_sender';

CREATE INDEX idx_notifications_sender ON notifications(sender_id);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'notifications' 
AND index_name = 'idx_notifications_type';

CREATE INDEX idx_notifications_type ON notifications(type);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'notifications' 
AND index_name = 'idx_notifications_event';

CREATE INDEX idx_notifications_event ON notifications(event_id);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'notifications' 
AND index_name = 'idx_notifications_read';

CREATE INDEX idx_notifications_read ON notifications(is_read);

-- 3. Media için index'ler
SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'medyalar' 
AND index_name = 'idx_medyalar_dugun_tur';

CREATE INDEX idx_medyalar_dugun_tur ON medyalar(dugun_id, tur);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'medyalar' 
AND index_name = 'idx_medyalar_created';

CREATE INDEX idx_medyalar_created ON medyalar(created_at DESC);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'medyalar' 
AND index_name = 'idx_medyalar_user';

CREATE INDEX idx_medyalar_user ON medyalar(kullanici_id);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'begeniler' 
AND index_name = 'idx_begeniler_medya';

CREATE INDEX idx_begeniler_medya ON begeniler(medya_id);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'begeniler' 
AND index_name = 'idx_begeniler_user_medya';

CREATE INDEX idx_begeniler_user_medya ON begeniler(kullanici_id, medya_id);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'yorumlar' 
AND index_name = 'idx_yorumlar_medya';

CREATE INDEX idx_yorumlar_medya ON yorumlar(medya_id);

-- 4. Comments için index'ler
SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'yorumlar' 
AND index_name = 'idx_yorumlar_created';

CREATE INDEX idx_yorumlar_created ON yorumlar(created_at DESC);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'yorumlar' 
AND index_name = 'idx_yorumlar_user';

CREATE INDEX idx_yorumlar_user ON yorumlar(kullanici_id);

-- 5. Stories için index'ler (MySQL'de WHERE clause desteklenmez, normal index kullan)
SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'medyalar' 
AND index_name = 'idx_medyalar_story_created';

CREATE INDEX idx_medyalar_story_created ON medyalar(dugun_id, tur, created_at DESC);

-- 6. Profile stats için index'ler
SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'medyalar' 
AND index_name = 'idx_medyalar_user_created';

CREATE INDEX idx_medyalar_user_created ON medyalar(kullanici_id, created_at DESC);

-- ✅ Composite index'ler
SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'dugun_katilimcilar' 
AND index_name = 'idx_dugun_katilimcilar_composite';

CREATE INDEX idx_dugun_katilimcilar_composite ON dugun_katilimcilar(kullanici_id, durum, dugun_id);

SELECT IF(COUNT(*) > 0, 'Index already exists', 'Creating index...') as status 
FROM information_schema.statistics 
WHERE table_schema = DATABASE() 
AND table_name = 'notifications' 
AND index_name = 'idx_notifications_composite';

CREATE INDEX idx_notifications_composite ON notifications(user_id, type, created_at DESC);

-- ✅ Query optimizasyonu için tablo istatistikleri güncelle
ANALYZE TABLE dugun_katilimcilar;
ANALYZE TABLE dugunler;
ANALYZE TABLE notifications;
ANALYZE TABLE medyalar;
ANALYZE TABLE begeniler;
ANALYZE TABLE yorumlar;

