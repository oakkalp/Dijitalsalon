<?php
/**
 * Cron Job: Etkinlik Hatırlatıcıları
 * Her saat çalıştırılmalı (Cron: 0 * * * *)
 * 
 * Kurallar:
 * 1. Etkinlik 14:00'dan sonraya ise → Etkinlik günü saat 09:00'da bildirim
 * 2. Etkinlik 14:00'dan önce ise → 12 saat önce bildirim
 * 3. Tüm etkinlikler için → 1 saat önce bildirim
 */

require_once __DIR__ . '/bootstrap.php';

// ✅ CLI'den çalıştırıldığını kontrol et (güvenlik)
if (php_sapi_name() !== 'cli') {
    // Web'den erişim için basit key kontrolü
    $cron_key = $_GET['key'] ?? '';
    if ($cron_key !== 'dijitalsalon_cron_2025') {
        die('Unauthorized access');
    }
}

echo "🔔 Event Reminder Cron Job Started at " . date('Y-m-d H:i:s') . "\n";

try {
    $pdo = get_pdo();
    $now = new DateTime();
    $current_time = $now->format('H:i:s');
    $current_date = $now->format('Y-m-d');
    
    echo "📅 Current Date/Time: $current_date $current_time\n";
    
    // ✅ Bugün ve gelecekteki etkinlikleri al (saat bilgisi olan)
    $stmt = $pdo->prepare("
        SELECT 
            d.id,
            d.baslik,
            d.dugun_tarihi as tarih,
            d.saat,
            d.salon_adresi as mekan
        FROM dugunler d
        WHERE d.dugun_tarihi >= ? AND d.saat IS NOT NULL
    ");
    $stmt->execute([$current_date]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "📊 Found " . count($events) . " events with time information\n";
    
    foreach ($events as $event) {
        $event_id = $event['id'];
        $event_title = $event['baslik'];
        $event_date = $event['tarih'];
        $event_time = $event['saat'];
        $event_location = $event['mekan'] ?? 'Belirtilmemiş';
        
        $eventDateTime = new DateTime("$event_date $event_time");
        $timeDiff = $now->diff($eventDateTime);
        $hoursUntilEvent = ($timeDiff->days * 24) + $timeDiff->h;
        
        echo "\n🎉 Event: $event_title | Date: $event_date | Time: $event_time\n";
        echo "   Hours until event: $hoursUntilEvent\n";
        
        // ✅ Kural 3: 1 saat öncesi hatırlatıcı (en prioriteli)
        if ($hoursUntilEvent === 1 && abs($timeDiff->i) < 30) {
            echo "   ⏰ Sending 1-hour reminder...\n";
            sendEventReminder($pdo, $event_id, $event_title, $event_location, '1 saat sonra başlayacak!');
            continue;
        }
        
        // ✅ Kural 1: Etkinlik bugün ve 14:00'dan sonra ise → Sabah 09:00'da
        if ($event_date === $current_date) {
            $eventHour = (int)substr($event_time, 0, 2);
            if ($eventHour >= 14 && $current_time >= '09:00:00' && $current_time < '10:00:00') {
                echo "   ☀️ Sending morning reminder (event after 2PM)...\n";
                sendEventReminder($pdo, $event_id, $event_title, $event_location, "bugün saat $event_time'de başlayacak");
                continue;
            }
        }
        
        // ✅ Kural 2: Etkinlik bugün ve 14:00'dan önce ise → 12 saat önce
        if ($event_date === $current_date) {
            $eventHour = (int)substr($event_time, 0, 2);
            if ($eventHour < 14 && $hoursUntilEvent === 12 && abs($timeDiff->i) < 30) {
                echo "   🕐 Sending 12-hour reminder (event before 2PM)...\n";
                sendEventReminder($pdo, $event_id, $event_title, $event_location, "12 saat sonra başlayacak!");
                continue;
            }
        }
    }
    
    echo "\n✅ Cron job completed successfully!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    error_log("Event Reminder Cron Error: " . $e->getMessage());
}

/**
 * Etkinlik hatırlatıcısı gönder
 */
function sendEventReminder($pdo, $event_id, $event_title, $event_location, $time_text) {
    try {
        // ✅ Etkinlikteki tüm katılımcıları al
        $stmt = $pdo->prepare("
            SELECT DISTINCT kullanici_id
            FROM dugun_katilimcilar
            WHERE dugun_id = ?
        ");
        $stmt->execute([$event_id]);
        $participants = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($participants)) {
            echo "   ⚠️  No participants found\n";
            return;
        }
        
        echo "   👥 Sending to " . count($participants) . " participants...\n";
        
        // ✅ Bildirim mesajı
        $message = "$event_title etkinliği $time_text $event_location lokasyonunda.";
        
        // ✅ Her katılımcıya bildirim kaydet
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, event_id, type, message, created_at)
            VALUES (?, ?, 'event_reminder', ?, NOW())
        ");
        
        foreach ($participants as $participant_id) {
            $stmt->execute([$participant_id, $event_id, $message]);
        }
        
        // ✅ FCM ile push notification gönder (opsiyonel)
        // require_once 'notification_service.php';
        // sendNotification($participants, 'Etkinlik Hatırlatıcısı', $message, ['type' => 'event_reminder', 'event_id' => $event_id]);
        
        echo "   ✅ Reminders sent successfully!\n";
        
    } catch (Exception $e) {
        echo "   ❌ Error sending reminder: " . $e->getMessage() . "\n";
        error_log("Send Reminder Error: " . $e->getMessage());
    }
}
?>

