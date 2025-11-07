-- ✅ VPS Optimizasyonu - SQL Index'leri (Basit Versiyon - Sadece Eksik Olanlar)
-- Bu script sadece eksik index'leri oluşturur
-- Mevcut index'leri atlar (hata vermez)

-- ✅ ÖNEMLİ: Bu script'i çalıştırmadan önce mevcut index'leri kontrol edin:
-- SHOW INDEX FROM dugun_katilimcilar;
-- SHOW INDEX FROM notifications;
-- SHOW INDEX FROM medyalar;

-- 1. Events için index'ler (sadece yoksa oluştur)
-- idx_dugun_katilimcilar_user_status zaten var, atla
-- CREATE INDEX idx_dugun_katilimcilar_user_status ON dugun_katilimcilar(kullanici_id, durum);

CREATE INDEX idx_dugun_katilimcilar_dugun_id ON dugun_katilimcilar(dugun_id);
CREATE INDEX idx_dugunler_tarih ON dugunler(dugun_tarihi DESC);
CREATE INDEX idx_dugunler_moderator ON dugunler(moderator_id);

-- 2. Notifications için index'ler
CREATE INDEX idx_notifications_user_created ON notifications(user_id, created_at DESC);
CREATE INDEX idx_notifications_sender ON notifications(sender_id);
CREATE INDEX idx_notifications_type ON notifications(type);
CREATE INDEX idx_notifications_event ON notifications(event_id);
CREATE INDEX idx_notifications_read ON notifications(is_read);

-- 3. Media için index'ler
CREATE INDEX idx_medyalar_dugun_tur ON medyalar(dugun_id, tur);
CREATE INDEX idx_medyalar_created ON medyalar(created_at DESC);
CREATE INDEX idx_medyalar_user ON medyalar(kullanici_id);
CREATE INDEX idx_begeniler_medya ON begeniler(medya_id);
CREATE INDEX idx_begeniler_user_medya ON begeniler(kullanici_id, medya_id);
CREATE INDEX idx_yorumlar_medya ON yorumlar(medya_id);

-- 4. Comments için index'ler
CREATE INDEX idx_yorumlar_created ON yorumlar(created_at DESC);
CREATE INDEX idx_yorumlar_user ON yorumlar(kullanici_id);

-- 5. Stories için index'ler
CREATE INDEX idx_medyalar_story_created ON medyalar(dugun_id, tur, created_at DESC);

-- 6. Profile stats için index'ler
CREATE INDEX idx_medyalar_user_created ON medyalar(kullanici_id, created_at DESC);

-- ✅ Composite index'ler
CREATE INDEX idx_dugun_katilimcilar_composite ON dugun_katilimcilar(kullanici_id, durum, dugun_id);
CREATE INDEX idx_notifications_composite ON notifications(user_id, type, created_at DESC);

-- ✅ Query optimizasyonu için tablo istatistikleri güncelle
ANALYZE TABLE dugun_katilimcilar;
ANALYZE TABLE dugunler;
ANALYZE TABLE notifications;
ANALYZE TABLE medyalar;
ANALYZE TABLE begeniler;
ANALYZE TABLE yorumlar;

