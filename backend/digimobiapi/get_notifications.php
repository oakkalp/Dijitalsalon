<?php
require_once 'bootstrap.php';
require_once __DIR__ . '/cache_helper.php';

header('Content-Type: application/json');

// Session kontrolü
if (!isset($_SESSION['user_id'])) {
    json_err(401, 'Unauthorized');
}

$user_id = $_SESSION['user_id'];
$is_read = $_GET['is_read'] ?? null;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 3; // ✅ 3'er 3'er yükleme
$offset = ($page - 1) * $limit;

try {
    // ✅ Cache'den kontrol et (user_id + page + is_read bazlı)
    $cache_key_query = "SELECT * FROM notifications WHERE user_id = ?";
    $cache_params = [$user_id];
    if ($is_read !== null) {
        $cache_params[] = $is_read;
    }
    
    // ✅ Sadece ilk sayfa için cache kullan (pagination için)
    if ($page === 1) {
        $cached_data = QueryCache::get($cache_key_query, $cache_params);
        if ($cached_data !== null) {
            // Cache'den döndür ama pagination uygula
            $total_count = count($cached_data);
            $final_notifications = array_slice($cached_data, $offset, $limit);
            $has_more = ($offset + $limit) < $total_count;
            $unread_count = 0;
            foreach ($final_notifications as $notif) {
                if (!$notif['is_read']) {
                    $unread_count++;
                }
            }
            
            json_ok([
                'notifications' => $final_notifications,
                'unread_count' => $unread_count,
                'total' => $total_count,
                'page' => $page,
                'limit' => $limit,
                'has_more' => $has_more,
                'cached' => true
            ]);
            exit;
        }
    }
    
    $pdo = get_pdo();
    
    // ✅ Bildirimleri grup halinde al (media_id bazlı)
    // Like bildirimleri: Aynı medya için son 4 beğeni
    // Comment bildirimleri: Her yorum ayrı
    // Custom bildirimleri: Her biri ayrı
    
    // ✅ OPTIMIZED: Index kullanımı (idx_notifications_user_created)
    // ✅ LIMIT 100 yerine pagination kullan (performans için)
    $query = "
        SELECT 
            n.id,
            n.user_id,
            n.sender_id,
            n.event_id,
            n.type,
            n.title,
            n.message,
            n.data,
            n.is_read,
            n.created_at,
            k_sender.ad as sender_ad,
            k_sender.soyad as sender_soyad,
            k_sender.profil_fotografi as sender_profil_fotografi_raw,
            CASE 
                WHEN k_sender.profil_fotografi IS NOT NULL AND k_sender.profil_fotografi != '' 
                THEN CONCAT('https://dijitalsalon.cagapps.app/', k_sender.profil_fotografi)
                ELSE NULL 
            END as sender_profile_image,
            e.baslik as event_title
        FROM notifications n
        LEFT JOIN kullanicilar k_sender ON k_sender.id = n.sender_id
        LEFT JOIN dugunler e ON e.id = n.event_id
        WHERE n.user_id = ?
    ";
    
    if ($is_read !== null) {
        $query .= " AND n.is_read = " . ($is_read == '1' ? '1' : '0');
    }
    
    // ✅ OPTIMIZED: LIMIT 100 yerine pagination (daha hızlı)
    $query .= " ORDER BY n.created_at DESC LIMIT 100";
    
    $stmt = $pdo->prepare($query);
    $stmt->execute([$user_id]);
    $all_notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // ✅ Grup halinde işle
    $grouped = [];
    $comment_grouped = []; // ✅ Comment bildirimleri için gruplama
    $custom_notifs = [];
    
    foreach ($all_notifications as $notif) {
        // ✅ Kullanıcının kendi beğeni/yorum bildirimlerini filtrele (kendi medyasını beğendi/yorum yaptı)
        // sender_id == user_id ise bildirimi atla
        if ($notif['sender_id'] == $user_id) {
            continue; // Kendi beğeni/yorumunu görmesin
        }
        
        // ✅ Null veya boş string kontrolü
        $dataStr = $notif['data'] ?? '{}';
        if (empty($dataStr)) {
            $dataStr = '{}';
        }
        $data = json_decode($dataStr, true) ?? [];
        
        // ✅ data JSON'dan sender_id kontrol et (eski bildirimler için)
        $data_sender_id = $data['sender_id'] ?? null;
        if ($data_sender_id && (int)$data_sender_id == (int)$user_id) {
            continue; // data'daki sender_id de kullanıcının kendisi ise atla
        }
        
        if ($notif['type'] === 'like') {
            // Like bildirimleri media_id'ye göre grupla
            // ✅ Önce data'dan çek, yoksa direkt kolondan (eski bildirimler için)
            $media_id = $data['media_id'] ?? $notif['media_id'] ?? null;
            
            // ✅ media_id yoksa (eski bildirimler veya silinmiş medya) bildirimi atla
            if (!$media_id) {
                continue; // Media ID yoksa bildirimi atla
            }
            
            // ✅ Medya thumbnail URL'sini PHP tarafında çek
            $group_key = 'like_' . $media_id;
            
            if (!isset($grouped[$group_key])) {
                    // ✅ Medya thumbnail URL'sini PHP tarafında çek
                    $media_thumbnail = null;
                    $media_exists = false;
                    if ($media_id) {
                        try {
                            $media_stmt = $pdo->prepare("
                                SELECT kucuk_resim_yolu, dosya_yolu 
                                FROM medyalar 
                                WHERE id = ?
                            ");
                            $media_stmt->execute([$media_id]);
                            $media_info = $media_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($media_info) {
                                $media_exists = true;
                                // Önce thumbnail, yoksa orijinal dosya
                                if ($media_info['kucuk_resim_yolu']) {
                                    $media_thumbnail = $media_info['kucuk_resim_yolu'];
                                } elseif ($media_info['dosya_yolu']) {
                                    $media_thumbnail = $media_info['dosya_yolu'];
                                }
                                
                                // URL tam yol olarak ayarla
                                if ($media_thumbnail && !str_starts_with($media_thumbnail, 'http')) {
                                    $media_thumbnail = 'https://dijitalsalon.cagapps.app/' . ltrim($media_thumbnail, '/');
                                }
                            } else {
                                // ✅ Medya silinmiş - bu bildirimi atla
                                continue; // Dış döngüye (foreach) geri dön
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching media thumbnail: " . $e->getMessage());
                            // Hata durumunda da atla
                            continue;
                        }
                    }
                    
                    $grouped[$group_key] = [
                        'type' => 'like',
                        'media_id' => $media_id,
                        'event_id' => $data['event_id'] ?? $notif['event_id'] ?? null,
                        'event_title' => $notif['event_title'] ?? ($notif['title'] ?? 'Bilinmeyen Etkinlik'),
                        'media_type' => $data['media_type'] ?? $notif['media_type'] ?? 'foto',
                        'media_thumbnail' => $media_thumbnail,
                        'likers' => [],
                        'total_likes' => 0,
                        'is_read' => $notif['is_read'],
                        'created_at' => $notif['created_at'],
                        'latest_created_at' => $notif['created_at']
                    ];
                }
                
                // Son 4 beğeni ekle
                if (count($grouped[$group_key]['likers']) < 4) {
                    $sender_name = 'Bilinmeyen Kullanıcı';
                    // ✅ Önce data'dan sender_id'yi çek (eski bildirimler için)
                    $sender_id = $data['sender_id'] ?? $notif['sender_id'] ?? null;
                    
                    // ✅ Önce data JSON'dan sender_name kontrol et (en güvenilir kaynak)
                    if (!empty($data['sender_name'])) {
                        $sender_name = trim($data['sender_name']);
                    }
                    
                    // ✅ sender_name hala boşsa veya "Bilinmeyen Kullanıcı" ise, sender_id'den çek
                    if (empty($sender_name) || $sender_name === 'Bilinmeyen Kullanıcı') {
                        // ✅ Önce JOIN'den gelen sender_ad/sender_soyad'ı kontrol et
                        $join_sender_name = trim(($notif['sender_ad'] ?? '') . ' ' . ($notif['sender_soyad'] ?? ''));
                        if (!empty($join_sender_name)) {
                            $sender_name = $join_sender_name;
                        }
                        
                        // ✅ Hala boşsa ve sender_id varsa (data'dan veya direkt kolondan), database'den çek
                        if ((empty($sender_name) || $sender_name === 'Bilinmeyen Kullanıcı') && $sender_id) {
                            try {
                                $sender_stmt = $pdo->prepare("SELECT email, ad, soyad FROM kullanicilar WHERE id = ?");
                                $sender_stmt->execute([$sender_id]);
                                $sender_info = $sender_stmt->fetch(PDO::FETCH_ASSOC);
                                if ($sender_info) {
                                    // Önce ad soyad dene
                                    $db_sender_name = trim(($sender_info['ad'] ?? '') . ' ' . ($sender_info['soyad'] ?? ''));
                                    if (!empty($db_sender_name)) {
                                        $sender_name = $db_sender_name;
                                    } else if (!empty($sender_info['email'])) {
                                        // Email'den kullanıcı adı çıkar (örn: test@example.com -> test)
                                        $email_parts = explode('@', $sender_info['email']);
                                        $sender_name = !empty($email_parts[0]) ? $email_parts[0] : 'Bilinmeyen Kullanıcı';
                                    }
                                }
                            } catch (Exception $e) {
                                error_log("Error fetching sender info for sender_id $sender_id: " . $e->getMessage());
                            }
                        }
                    }
                    
                    // ✅ Eğer hala "Bilinmeyen Kullanıcı" ise ve sender_id null ise, bu bildirimi atla (geçersiz bildirim)
                    if ($sender_name === 'Bilinmeyen Kullanıcı' && !$sender_id && empty($notif['sender_ad']) && empty($notif['sender_soyad'])) {
                        continue; // Geçersiz bildirim - atla
                    }
                    
                    // ✅ sender_id olsa da olmasa da likers array'ine ekle (frontend fallback için)
                    // Ama sadece isim bulunduysa veya sender_id varsa
                    if ($sender_name !== 'Bilinmeyen Kullanıcı' || $sender_id || !empty($notif['sender_ad']) || !empty($notif['sender_soyad'])) {
                        $grouped[$group_key]['likers'][] = [
                            'id' => $sender_id,
                            'name' => $sender_name,
                            'profile_image' => $notif['sender_profile_image'] ?? null,
                            'sender_profile_image' => $notif['sender_profile_image'] ?? null,
                            'sender_ad' => $notif['sender_ad'] ?? null,
                            'sender_soyad' => $notif['sender_soyad'] ?? null
                        ];
                    }
                }
                
                $grouped[$group_key]['total_likes']++;
                
                // En yeni tarihi güncelle
                if ($notif['created_at'] > $grouped[$group_key]['latest_created_at']) {
                    $grouped[$group_key]['latest_created_at'] = $notif['created_at'];
                }
                
                // Okunmamış varsa is_read = false
                if (!$notif['is_read']) {
                    $grouped[$group_key]['is_read'] = false;
                }
            
        } else if ($notif['type'] === 'comment') {
            // ✅ Comment bildirimleri media_id bazlı grupla
            // ✅ Önce data'dan çek, yoksa direkt kolondan (eski bildirimler için)
            $media_id = $data['media_id'] ?? $notif['media_id'] ?? null;
            
            // ✅ media_id yoksa (eski bildirimler veya silinmiş medya) bildirimi atla
            if (!$media_id) {
                continue; // Media ID yoksa bildirimi atla
            }
            
            // ✅ Medya thumbnail URL'sini PHP tarafında çek
            $comment_group_key = 'comment_' . $media_id;
            
            if (!isset($comment_grouped[$comment_group_key])) {
                    // ✅ Medya thumbnail URL'sini PHP tarafında çek
                    $media_thumbnail = null;
                    $media_exists = false;
                    if ($media_id) {
                        try {
                            $media_stmt = $pdo->prepare("
                                SELECT kucuk_resim_yolu, dosya_yolu 
                                FROM medyalar 
                                WHERE id = ?
                            ");
                            $media_stmt->execute([$media_id]);
                            $media_info = $media_stmt->fetch(PDO::FETCH_ASSOC);
                            
                            if ($media_info) {
                                $media_exists = true;
                                // Önce thumbnail, yoksa orijinal dosya
                                if ($media_info['kucuk_resim_yolu']) {
                                    $media_thumbnail = $media_info['kucuk_resim_yolu'];
                                } elseif ($media_info['dosya_yolu']) {
                                    $media_thumbnail = $media_info['dosya_yolu'];
                                }
                                
                                // URL tam yol olarak ayarla
                                if ($media_thumbnail && !str_starts_with($media_thumbnail, 'http')) {
                                    $media_thumbnail = 'https://dijitalsalon.cagapps.app/' . ltrim($media_thumbnail, '/');
                                }
                            } else {
                                // ✅ Medya silinmiş - bu bildirimi atla
                                continue; // Dış döngüye (foreach) geri dön
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching media thumbnail: " . $e->getMessage());
                            // Hata durumunda da atla
                            continue;
                        }
                    }
                    
                    $comment_grouped[$comment_group_key] = [
                        'type' => 'comment',
                        'media_id' => $media_id,
                        'event_id' => $data['event_id'] ?? $notif['event_id'] ?? null,
                        'event_title' => $notif['event_title'] ?? ($notif['title'] ?? 'Bilinmeyen Etkinlik'),
                        'media_type' => $data['media_type'] ?? $notif['media_type'] ?? 'foto',
                        'media_thumbnail' => $media_thumbnail,
                        'comments' => [],
                        'commenters' => [], // ✅ commenters anahtarını ekle
                        'unique_commenters' => [], // ✅ unique_commenters'ı da burada oluştur
                        'total_comments' => 0,
                        'is_read' => $notif['is_read'],
                        'created_at' => $notif['created_at'],
                        'latest_created_at' => $notif['created_at']
                    ];
                }
                
                // ✅ Benzersiz kullanıcıları takip et
                if (!isset($comment_grouped[$comment_group_key]['unique_commenters'])) {
                    $comment_grouped[$comment_group_key]['unique_commenters'] = [];
                }
                
                // ✅ Sadece benzersiz kullanıcıları ekle ve son 3 yorumu göster
                // ✅ sender_id olsa da olmasa da data JSON'dan commenter_name kontrol et
                $sender_id_for_check = $notif['sender_id'] ?? $data['commenter_id'] ?? null;
                
                // ✅ Önce data JSON'dan commenter_name kontrol et (silinmiş kullanıcılar için)
                $sender_name = $data['commenter_name'] ?? null;
                if (empty($sender_name)) {
                    $sender_name = trim(($notif['sender_ad'] ?? '') . ' ' . ($notif['sender_soyad'] ?? ''));
                    if (empty($sender_name) && $sender_id_for_check) {
                        // Database'den çek
                        try {
                            $commenter_stmt = $pdo->prepare("SELECT email, ad, soyad FROM kullanicilar WHERE id = ?");
                            $commenter_stmt->execute([$sender_id_for_check]);
                            $commenter_info = $commenter_stmt->fetch(PDO::FETCH_ASSOC);
                            if ($commenter_info) {
                                $db_commenter_name = trim(($commenter_info['ad'] ?? '') . ' ' . ($commenter_info['soyad'] ?? ''));
                                if (!empty($db_commenter_name)) {
                                    $sender_name = $db_commenter_name;
                                } else if (!empty($commenter_info['email'])) {
                                    $email_parts = explode('@', $commenter_info['email']);
                                    $sender_name = !empty($email_parts[0]) ? $email_parts[0] : 'Bilinmeyen Kullanıcı';
                                }
                            }
                        } catch (Exception $e) {
                            error_log("Error fetching commenter info for commenter_id $sender_id_for_check: " . $e->getMessage());
                        }
                    }
                    if (empty($sender_name)) {
                        $sender_name = 'Bilinmeyen Kullanıcı';
                    }
                }
                
                // ✅ Eğer hala "Bilinmeyen Kullanıcı" ise ve sender_id null ise, bu bildirimi atla
                if ($sender_name === 'Bilinmeyen Kullanıcı' && !$sender_id_for_check && empty($notif['sender_ad']) && empty($notif['sender_soyad'])) {
                    continue; // Geçersiz bildirim - atla
                }
                
                if ($sender_id_for_check && !in_array($sender_id_for_check, $comment_grouped[$comment_group_key]['unique_commenters'])) {
                    // Son 4 yorumcuyu göster
                    if (count($comment_grouped[$comment_group_key]['commenters']) < 4) {
                        $comment_grouped[$comment_group_key]['commenters'][] = [
                            'id' => $sender_id_for_check,
                            'name' => $sender_name,
                            'profile_image' => $notif['sender_profile_image'] ?? null,
                            'sender_profile_image' => $notif['sender_profile_image'] ?? null,
                            'sender_ad' => $notif['sender_ad'] ?? null,
                            'sender_soyad' => $notif['sender_soyad'] ?? null
                        ];
                    }
                    // Benzersiz kullanıcı listesine ekle
                    $comment_grouped[$comment_group_key]['unique_commenters'][] = $sender_id_for_check;
                }
                
                // ✅ Toplam yorum sayısını benzersiz kullanıcı sayısı olarak güncelle
                $comment_grouped[$comment_group_key]['total_comments'] = count($comment_grouped[$comment_group_key]['unique_commenters']);
                
                // En yeni tarihi güncelle
                if ($notif['created_at'] > $comment_grouped[$comment_group_key]['latest_created_at']) {
                    $comment_grouped[$comment_group_key]['latest_created_at'] = $notif['created_at'];
                }
                
                // Okunmamış varsa is_read = false
                if (!$notif['is_read']) {
                    $comment_grouped[$comment_group_key]['is_read'] = false;
                }
            
        } else {
            // Custom/diğer bildirimler
            $custom_notifs[] = [
                'id' => $notif['id'],
                'type' => $notif['type'],
                'title' => $notif['title'],
                'message' => $notif['message'],
                'event_id' => $notif['event_id'],
                'sender' => $notif['sender_id'] ? [
                    'id' => $notif['sender_id'],
                    'name' => trim($notif['sender_ad'] . ' ' . $notif['sender_soyad']),
                    'profile_image' => $notif['sender_profile_image']
                ] : null,
                'is_read' => (bool)$notif['is_read'],
                'created_at' => $notif['created_at']
            ];
        }
    }
    
    // ✅ Grupları listeye çevir ve sırala
    $final_notifications = array_merge(
        array_values($grouped), 
        array_values($comment_grouped), // ✅ Gruplu comment bildirimleri
        $custom_notifs
    );
    
    // En yeni tarih bazlı sırala
    usort($final_notifications, function($a, $b) {
        $date_a = $a['latest_created_at'] ?? $a['created_at'] ?? '';
        $date_b = $b['latest_created_at'] ?? $b['created_at'] ?? '';
        return strcmp($date_b, $date_a);
    });
    
    // ✅ Sayfalama uygula
    $total_count = count($final_notifications);
    $final_notifications = array_slice($final_notifications, $offset, $limit);
    $has_more = ($offset + $limit) < $total_count;
    
    // ✅ Okunmamış sayı (sadece mevcut sayfadaki bildirimler için)
    $unread_count = 0;
    foreach ($final_notifications as $notif) {
        if (!$notif['is_read']) {
            $unread_count++;
        }
    }
    
    // ✅ Cache'e kaydet (sadece ilk sayfa için, 1 dakika TTL - bildirimler sık değişir)
    if ($page === 1) {
        QueryCache::set(
            $cache_key_query, 
            $cache_params, 
            $final_notifications, 
            60 // 1 dakika
        );
    }
    
    json_ok([
        'notifications' => $final_notifications,
        'unread_count' => $unread_count,
        'total' => $total_count,
        'page' => $page,
        'limit' => $limit,
        'has_more' => $has_more,
        'cached' => false
    ]);
    
} catch (PDOException $e) {
    error_log("Get Notifications Error: " . $e->getMessage());
    json_err(500, 'Database error');
}
?>
