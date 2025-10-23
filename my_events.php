<?php
/**
 * My Events Page
 * Digital Salon - Moderator Düğün Yönetimi
 */

require_once 'config/database.php';
require_once 'includes/security.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'];

// Sadece moderator erişebilir
if ($user_role !== 'moderator') {
    header('Location: dashboard.php');
    exit();
}

$success_message = '';
$error_message = '';

// Katılımcı ekleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_participant'])) {
    $event_id = (int)$_POST['event_id'];
    $user_email = sanitizeInput($_POST['user_email']);
    $user_role = sanitizeInput($_POST['participant_role']);
    
    try {
        // Kullanıcıyı bul
        $stmt = $pdo->prepare("SELECT id, ad, soyad FROM kullanicilar WHERE email = ?");
        $stmt->execute([$user_email]);
        $user = $stmt->fetch();
        
        if (!$user) {
            $error_message = 'Bu email adresine sahip kullanıcı bulunamadı.';
        } else {
            // Düğünün moderator'ı kontrol et
            $stmt = $pdo->prepare("SELECT moderator_id FROM dugunler WHERE id = ? AND moderator_id = ?");
            $stmt->execute([$event_id, $user_id]);
            
            if ($stmt->fetch()) {
                // Katılımcı zaten var mı kontrol et
                $stmt = $pdo->prepare("SELECT id FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
                $stmt->execute([$event_id, $user['id']]);
                
                if ($stmt->fetch()) {
                    $error_message = 'Bu kullanıcı zaten düğüne katılımcı olarak eklenmiş.';
                } else {
                    // Yetkili kullanıcı için özel yetkiler
                    $medya_silebilir = ($user_role === 'yetkili_kullanici') ? 1 : 0;
                    $yorum_silebilir = ($user_role === 'yetkili_kullanici') ? 1 : 0;
                    $kullanici_engelleyebilir = ($user_role === 'yetkili_kullanici') ? 1 : 0;
                    $hikaye_paylasabilir = 1; // Tüm katılımcılar hikaye paylaşabilir
                    $profil_degistirebilir = 0; // Profil değiştirme yetkisi yok
                    
                    // Katılımcıyı ekle
                    $stmt = $pdo->prepare("
                        INSERT INTO dugun_katilimcilar (dugun_id, kullanici_id, rol, medya_silebilir, yorum_silebilir, kullanici_engelleyebilir, hikaye_paylasabilir, profil_degistirebilir) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$event_id, $user['id'], $user_role, $medya_silebilir, $yorum_silebilir, $kullanici_engelleyebilir, $hikaye_paylasabilir, $profil_degistirebilir]);
                    
                    $role_text = ($user_role === 'yetkili_kullanici') ? 'yetkili kullanıcı olarak' : 'normal kullanıcı olarak';
                    $success_message = $user['ad'] . ' ' . $user['soyad'] . ' düğüne ' . $role_text . ' başarıyla eklendi.';
                }
            } else {
                $error_message = 'Bu düğünü yönetme yetkiniz yok.';
            }
        }
    } catch (Exception $e) {
        $error_message = 'Katılımcı eklenirken bir hata oluştu.';
        error_log("Add participant error: " . $e->getMessage());
    }
}

// Katılımcı rol güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_participant_role'])) {
    $event_id = (int)$_POST['event_id'];
    $participant_id = (int)$_POST['participant_id'];
    $new_role = sanitizeInput($_POST['new_role']);
    
    try {
        // Düğünün moderator'ı kontrol et
        $stmt = $pdo->prepare("SELECT moderator_id FROM dugunler WHERE id = ? AND moderator_id = ?");
        $stmt->execute([$event_id, $user_id]);
        
        if ($stmt->fetch()) {
            // Yetkili kullanıcı için özel yetkiler
            $medya_silebilir = ($new_role === 'yetkili_kullanici') ? 1 : 0;
            $yorum_silebilir = ($new_role === 'yetkili_kullanici') ? 1 : 0;
            $kullanici_engelleyebilir = ($new_role === 'yetkili_kullanici') ? 1 : 0;
            
            // Katılımcı rolünü güncelle
            $stmt = $pdo->prepare("
                UPDATE dugun_katilimcilar 
                SET rol = ?, medya_silebilir = ?, yorum_silebilir = ?, kullanici_engelleyebilir = ?
                WHERE dugun_id = ? AND kullanici_id = ?
            ");
            $stmt->execute([$new_role, $medya_silebilir, $yorum_silebilir, $kullanici_engelleyebilir, $event_id, $participant_id]);
            
            $role_text = ($new_role === 'yetkili_kullanici') ? 'yetkili kullanıcı' : 'normal kullanıcı';
            $success_message = 'Katılımcı rolü ' . $role_text . ' olarak güncellendi.';
        } else {
            $error_message = 'Bu düğünü yönetme yetkiniz yok.';
        }
    } catch (Exception $e) {
        $error_message = 'Katılımcı rolü güncellenirken bir hata oluştu.';
        error_log("Update participant role error: " . $e->getMessage());
    }
}

// Kullanıcı engelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['block_participant'])) {
    $event_id = (int)$_POST['event_id'];
    $participant_id = (int)$_POST['participant_id'];
    $block_reason = sanitizeInput($_POST['block_reason'] ?? '');
    
    try {
        // Düğünün moderator'ı kontrol et
        $stmt = $pdo->prepare("SELECT moderator_id FROM dugunler WHERE id = ? AND moderator_id = ?");
        $stmt->execute([$event_id, $user_id]);
        
        if ($stmt->fetch()) {
            // Kullanıcıyı engelle
            $stmt = $pdo->prepare("
                INSERT INTO blocked_users (dugun_id, blocked_user_id, blocked_by_user_id, reason) 
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE reason = VALUES(reason)
            ");
            $stmt->execute([$event_id, $participant_id, $user_id, $block_reason]);
            
            $success_message = 'Kullanıcı başarıyla engellendi.';
        } else {
            $error_message = 'Bu düğünü yönetme yetkiniz yok.';
        }
    } catch (Exception $e) {
        $error_message = 'Kullanıcı engellenirken bir hata oluştu.';
        error_log("Block participant error: " . $e->getMessage());
    }
}

// Update participant permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_permissions'])) {
    $user_id = (int)$_POST['user_id'];
    $event_id = (int)$_POST['event_id'];
    $participant_status = $_POST['participant_status'];
    
    try {
        // Check if user has permission to manage this event
        $stmt = $pdo->prepare("SELECT id FROM dugunler WHERE id = ? AND moderator_id = ?");
        $stmt->execute([$event_id, $user_id]);
        
        if ($stmt->fetch()) {
            // Get special permissions
            $can_delete_media = isset($_POST['can_delete_media']) ? 1 : 0;
            $can_delete_comments = isset($_POST['can_delete_comments']) ? 1 : 0;
            $can_block_users = isset($_POST['can_block_users']) ? 1 : 0;
            $can_share_stories = isset($_POST['can_share_stories']) ? 1 : 0;
            
            if ($participant_status === 'not_participant') {
                // Remove from participants
                $stmt = $pdo->prepare("DELETE FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
                $stmt->execute([$event_id, $user_id]);
                $success_message = 'Kullanıcı katılımcı listesinden kaldırıldı.';
            } else {
                // Determine role based on status
                $role = ($participant_status === 'authorized_participant') ? 'yetkili_kullanici' : 'normal_kullanici';
                
                // Add or update participant
                $stmt = $pdo->prepare("
                    INSERT INTO dugun_katilimcilar 
                    (dugun_id, kullanici_id, rol, medya_silebilir, yorum_silebilir, kullanici_engelleyebilir, hikaye_paylasabilir, profil_degistirebilir, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, 1, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE 
                    rol = VALUES(rol),
                    medya_silebilir = VALUES(medya_silebilir),
                    yorum_silebilir = VALUES(yorum_silebilir),
                    kullanici_engelleyebilir = VALUES(kullanici_engelleyebilir),
                    hikaye_paylasabilir = VALUES(hikaye_paylasabilir),
                    updated_at = NOW()
                ");
                $stmt->execute([$event_id, $user_id, $role, $can_delete_media, $can_delete_comments, $can_block_users, $can_share_stories]);
                
                $role_text = ($role === 'yetkili_kullanici') ? 'yetkili katılımcı' : 'normal katılımcı';
                $success_message = 'Kullanıcı ' . $role_text . ' olarak güncellendi.';
            }
        } else {
            $error_message = 'Bu düğünü yönetme yetkiniz yok.';
        }
    } catch (Exception $e) {
        $error_message = 'Yetkiler güncellenirken bir hata oluştu.';
    }
}

// Düğün düzenleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_event'])) {
    $event_id = (int)$_POST['event_id'];
    $baslik = sanitizeInput($_POST['baslik'] ?? '');
    $aciklama = sanitizeInput($_POST['aciklama'] ?? '');
    $dugun_tarihi = sanitizeInput($_POST['dugun_tarihi'] ?? '');
    $bitis_tarihi = sanitizeInput($_POST['bitis_tarihi'] ?? '');
    $paket_id = (int)($_POST['paket_id'] ?? 0);
    
    try {
        // Düğünün moderator'ı kontrol et
        $stmt = $pdo->prepare("SELECT moderator_id FROM dugunler WHERE id = ? AND moderator_id = ?");
        $stmt->execute([$event_id, $user_id]);
        
        if ($stmt->fetch()) {
            // Paket bilgilerini al
            $paket_fiyati = 0;
            if ($paket_id > 0) {
                $stmt = $pdo->prepare("SELECT fiyat FROM paketler WHERE id = ?");
                $stmt->execute([$paket_id]);
                $paket = $stmt->fetch();
                if ($paket) {
                    $paket_fiyati = $paket['fiyat'];
                }
            }
            
            // Kapak fotoğrafı yükleme işlemi
            $cover_photo_update = '';
            if (isset($_FILES['kapak_fotografi']) && $_FILES['kapak_fotografi']['error'] === UPLOAD_ERR_OK) {
                $upload_dir = 'uploads/events/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }

                $file_extension = strtolower(pathinfo($_FILES['kapak_fotografi']['name'], PATHINFO_EXTENSION));
                $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif'];
                
                if (in_array($file_extension, $allowed_extensions)) {
                    $file_name = 'cover_' . uniqid() . '.' . $file_extension;
                    $file_path = $upload_dir . $file_name;
                    
                    if (move_uploaded_file($_FILES['kapak_fotografi']['tmp_name'], $file_path)) {
                        $cover_photo_update = $file_path;
                    }
                }
            }
            
            // Düğünü güncelle
            if ($cover_photo_update) {
                $stmt = $pdo->prepare("
                    UPDATE dugunler 
                    SET baslik = ?, aciklama = ?, dugun_tarihi = ?, bitis_tarihi = ?, 
                        paket_id = ?, paket_fiyati = ?, kapak_fotografi = ?, guncelleme_tarihi = NOW()
                    WHERE id = ? AND moderator_id = ?
                ");
                $stmt->execute([$baslik, $aciklama, $dugun_tarihi, $bitis_tarihi, $paket_id, $paket_fiyati, $cover_photo_update, $event_id, $user_id]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE dugunler 
                    SET baslik = ?, aciklama = ?, dugun_tarihi = ?, bitis_tarihi = ?, 
                        paket_id = ?, paket_fiyati = ?, guncelleme_tarihi = NOW()
                    WHERE id = ? AND moderator_id = ?
                ");
                $stmt->execute([$baslik, $aciklama, $dugun_tarihi, $bitis_tarihi, $paket_id, $paket_fiyati, $event_id, $user_id]);
            }
            
            $success_message = 'Düğün başarıyla güncellendi.';
        } else {
            $error_message = 'Bu düğünü düzenleme yetkiniz yok.';
        }
    } catch (Exception $e) {
        $error_message = 'Düğün güncellenirken bir hata oluştu.';
        error_log("Edit event error: " . $e->getMessage());
    }
}

// Katılımcı silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_participant'])) {
    $event_id = (int)$_POST['event_id'];
    $participant_id = (int)$_POST['participant_id'];
    
    try {
        // Düğünün moderator'ı kontrol et
        $stmt = $pdo->prepare("SELECT moderator_id FROM dugunler WHERE id = ? AND moderator_id = ?");
        $stmt->execute([$event_id, $user_id]);
        
        if ($stmt->fetch()) {
            // Katılımcıyı sil
            $stmt = $pdo->prepare("DELETE FROM dugun_katilimcilar WHERE dugun_id = ? AND kullanici_id = ?");
            $stmt->execute([$event_id, $participant_id]);
            
            $success_message = 'Katılımcı başarıyla kaldırıldı.';
        } else {
            $error_message = 'Bu düğünü yönetme yetkiniz yok.';
        }
    } catch (Exception $e) {
        $error_message = 'Katılımcı kaldırılırken bir hata oluştu.';
        error_log("Remove participant error: " . $e->getMessage());
    }
}

// Düğün silme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event'])) {
    $delete_event_id = (int)$_POST['delete_event_id'];
    
    try {
        // İlişkili verileri de sil
        $pdo->beginTransaction();
        
        // Medyaları sil
        $stmt = $pdo->prepare("DELETE FROM medyalar WHERE dugun_id = ?");
        $stmt->execute([$delete_event_id]);
        
        // Yorumları sil
        $stmt = $pdo->prepare("DELETE FROM yorumlar WHERE medya_id IN (SELECT id FROM medyalar WHERE dugun_id = ?)");
        $stmt->execute([$delete_event_id]);
        
        // Beğenileri sil
        $stmt = $pdo->prepare("DELETE FROM begeniler WHERE medya_id IN (SELECT id FROM medyalar WHERE dugun_id = ?)");
        $stmt->execute([$delete_event_id]);
        
        // Katılımcıları sil
        $stmt = $pdo->prepare("DELETE FROM dugun_katilimcilar WHERE dugun_id = ?");
        $stmt->execute([$delete_event_id]);
        
        // Engellenen kullanıcıları sil
        $stmt = $pdo->prepare("DELETE FROM engellenen_kullanicilar WHERE dugun_id = ?");
        $stmt->execute([$delete_event_id]);
        
        // Komisyon geçmişini sil
        $stmt = $pdo->prepare("DELETE FROM komisyon_gecmisi WHERE dugun_id = ?");
        $stmt->execute([$delete_event_id]);
        
        // Düğünü sil
        $stmt = $pdo->prepare("DELETE FROM dugunler WHERE id = ? AND moderator_id = ?");
        $stmt->execute([$delete_event_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $pdo->commit();
            $success_message = 'Düğün ve tüm ilişkili veriler başarıyla silindi.';
        } else {
            $pdo->rollBack();
            $error_message = 'Bu düğünü silme yetkiniz yok.';
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $error_message = 'Düğün silinirken bir hata oluştu.';
        error_log("Event delete error: " . $e->getMessage());
    }
}

// Düğün durumu güncelleme
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $event_id = (int)$_POST['event_id'];
    $new_status = sanitizeInput($_POST['new_status']);
    
    try {
        $stmt = $pdo->prepare("UPDATE dugunler SET durum = ?, updated_at = NOW() WHERE id = ? AND moderator_id = ?");
        $stmt->execute([$new_status, $event_id, $user_id]);
        
        if ($stmt->rowCount() > 0) {
            $success_message = 'Düğün durumu başarıyla güncellendi.';
        } else {
            $error_message = 'Bu düğünü güncelleme yetkiniz yok.';
        }
    } catch (Exception $e) {
        $error_message = 'Düğün durumu güncellenirken bir hata oluştu.';
        error_log("Event status update error: " . $e->getMessage());
    }
}

// Filtreleme
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = ["d.moderator_id = ?"];
$params = [$user_id];

if (!empty($search)) {
    $where_conditions[] = "(d.baslik LIKE ? OR d.aciklama LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "d.durum = ?";
    $params[] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "d.dugun_tarihi >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "d.dugun_tarihi <= ?";
    $params[] = $date_to;
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

        // Düğünleri listele
        $stmt = $pdo->prepare("
            SELECT 
                d.*,
                p.ad as paket_ad,
                p.fiyat as paket_fiyati,
                p.sure_ay as paket_sure,
                p.medya_limiti as paket_medya_limiti,
                (SELECT COUNT(*) FROM dugun_katilimcilar WHERE dugun_id = d.id) as katilimci_sayisi,
                (SELECT COUNT(*) FROM medyalar WHERE dugun_id = d.id) as medya_sayisi,
                (SELECT COUNT(*) FROM komisyon_gecmisi WHERE dugun_id = d.id) as komisyon_sayisi
            FROM dugunler d
            LEFT JOIN paketler p ON d.paket_id = p.id
            $where_clause
            ORDER BY d.created_at DESC
        ");
        $stmt->execute($params);
        $events = $stmt->fetchAll();

        // Her düğün için katılımcıları ve engellenen kullanıcıları al
        foreach ($events as $key => $event) {
            // Katılımcıları al
            $stmt = $pdo->prepare("
                SELECT k.id, k.ad, k.soyad, k.email, dk.rol, dk.katilim_tarihi, 'participant' as type
                FROM dugun_katilimcilar dk
                JOIN kullanicilar k ON dk.kullanici_id = k.id
                WHERE dk.dugun_id = ?
                ORDER BY dk.katilim_tarihi ASC
            ");
            $stmt->execute([$event['id']]);
            $participants = $stmt->fetchAll();
            
            // Engellenen kullanıcıları al
            $stmt = $pdo->prepare("
                SELECT k.id, k.ad, k.soyad, k.email, bu.reason, bu.created_at as block_date, 'blocked' as type
                FROM blocked_users bu
                JOIN kullanicilar k ON bu.blocked_user_id = k.id
                WHERE bu.dugun_id = ?
                ORDER BY bu.created_at DESC
            ");
            $stmt->execute([$event['id']]);
            $blocked_users = $stmt->fetchAll();
            
            $events[$key]['participants'] = $participants;
            $events[$key]['blocked_users'] = $blocked_users;
        }

// Paketleri çek (düzenleme için)
$stmt = $pdo->prepare("SELECT id, ad, fiyat, sure_ay, medya_limiti FROM paketler WHERE durum = 'aktif' ORDER BY fiyat ASC");
$stmt->execute();
$packages = $stmt->fetchAll();

// İstatistikler
$total_events = $pdo->prepare("SELECT COUNT(*) FROM dugunler WHERE moderator_id = ?");
$total_events->execute([$user_id]);
$total_events = $total_events->fetchColumn();

$active_events = $pdo->prepare("SELECT COUNT(*) FROM dugunler WHERE moderator_id = ? AND durum = 'aktif'");
$active_events->execute([$user_id]);
$active_events = $active_events->fetchColumn();

$completed_events = $pdo->prepare("SELECT COUNT(*) FROM dugunler WHERE moderator_id = ? AND durum = 'tamamlandi'");
$completed_events->execute([$user_id]);
$completed_events = $completed_events->fetchColumn();

$total_revenue = $pdo->prepare("SELECT SUM(COALESCE(paket_fiyati, 0)) FROM dugunler WHERE moderator_id = ? AND odeme_durumu = 'odendi'");
$total_revenue->execute([$user_id]);
$total_revenue = $total_revenue->fetchColumn() ?: 0;

// Moderator bilgilerini al
$moderator_stmt = $pdo->prepare("SELECT ad, soyad, email FROM kullanicilar WHERE id = ?");
$moderator_stmt->execute([$user_id]);
$moderator = $moderator_stmt->fetch();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Düğünlerim - Digital Salon</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-ui.css" rel="stylesheet">
    
    <style>
        body {
            background: var(--primary-gradient);
            min-height: 100vh;
            font-family: var(--font-primary);
            color: var(--gray-800);
        }
        
        .events-container {
            background: var(--white);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            padding: var(--spacing-2xl);
            margin-bottom: var(--spacing-xl);
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: var(--white);
            padding: var(--spacing-2xl);
            border-radius: var(--radius-xl);
            margin-bottom: var(--spacing-xl);
            position: relative;
            overflow: hidden;
        }
        
        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            transform: translate(50%, -50%);
        }
        
        .page-title {
            font-family: var(--font-heading);
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: var(--spacing-sm);
            position: relative;
            z-index: 1;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .moderator-info {
            background: rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
            backdrop-filter: blur(10px);
        }
        
        .moderator-avatar {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--white);
            font-size: 2rem;
            font-weight: 600;
            margin-right: var(--spacing-lg);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .stat-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            text-align: center;
            transition: all 0.3s ease;
            border: 1px solid var(--gray-100);
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto var(--spacing-md);
            font-size: 1.5rem;
            color: var(--white);
        }
        
        .stat-icon.events { background: var(--primary-gradient); }
        .stat-icon.active { background: var(--success-gradient); }
        .stat-icon.completed { background: var(--warning-gradient); }
        .stat-icon.revenue { background: var(--info); }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
        }
        
        .stat-label {
            color: var(--gray-600);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .filter-section {
            background: var(--gray-50);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            margin-bottom: var(--spacing-xl);
        }
        
        .form-control {
            background: #ffffff !important;
            border: 1px solid #d1d5db !important;
            border-radius: var(--radius-md);
            color: #374151 !important;
            padding: var(--spacing-md);
            margin-bottom: var(--spacing-md);
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            background: #ffffff !important;
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.25);
            color: #374151 !important;
        }
        
        .form-label {
            color: #374151 !important;
            font-weight: 500;
            margin-bottom: var(--spacing-sm);
        }
        
        .btn-primary {
            background: var(--primary-gradient);
            border: none;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 600;
            padding: var(--spacing-md) var(--spacing-lg);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
        }
        
        .btn-success {
            background: var(--success);
            border: none;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 500;
            padding: var(--spacing-sm) var(--spacing-md);
            transition: all 0.3s ease;
        }
        
        .btn-success:hover {
            background: #059669;
            color: var(--white);
        }
        
        .btn-warning {
            background: var(--warning);
            border: none;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 500;
            padding: var(--spacing-sm) var(--spacing-md);
            transition: all 0.3s ease;
        }
        
        .btn-warning:hover {
            background: #d97706;
            color: var(--white);
        }
        
        .btn-danger {
            background: var(--danger);
            border: none;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 500;
            padding: var(--spacing-sm) var(--spacing-md);
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            background: #dc2626;
            color: var(--white);
        }
        
        .btn-info {
            background: var(--info);
            border: none;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 500;
            padding: var(--spacing-sm) var(--spacing-md);
            transition: all 0.3s ease;
        }
        
        .btn-info:hover {
            background: #2563eb;
            color: var(--white);
        }
        
        .participants-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
        }
        
        .participant-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: var(--spacing-sm);
            border-bottom: 1px solid var(--gray-100);
            margin-bottom: var(--spacing-sm);
        }
        
        .participant-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .participant-info {
            flex: 1;
        }
        
        .participant-name {
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: 2px;
        }
        
        .participant-email {
            font-size: 0.85rem;
            color: var(--gray-600);
        }
        
        .participant-role {
            font-size: 0.8rem;
            padding: 2px 8px;
            border-radius: 12px;
            background: var(--primary);
            color: var(--white);
            margin-right: var(--spacing-sm);
        }
        
        .participant-role.normal_kullanici {
            background: var(--gray-500);
        }
        
        .participant-role.yetkili_kullanici {
            background: var(--warning);
        }
        
        .btn-create-event {
            background: var(--success-gradient);
            border: none;
            border-radius: var(--radius-md);
            color: var(--white);
            font-weight: 600;
            padding: var(--spacing-md) var(--spacing-lg);
            transition: all 0.3s ease;
            box-shadow: var(--shadow-md);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
        }
        
        .btn-create-event:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--white);
            text-decoration: none;
        }
        
        .event-card {
            background: var(--white);
            border-radius: var(--radius-lg);
            padding: var(--spacing-lg);
            box-shadow: var(--shadow-md);
            margin-bottom: var(--spacing-lg);
            transition: all 0.3s ease;
            border: 1px solid var(--gray-100);
        }
        
        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .event-header {
            display: flex;
            justify-content-between;
            align-items-start;
            margin-bottom: var(--spacing-md);
        }
        
        .event-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--gray-800);
            margin-bottom: var(--spacing-xs);
        }
        
        .event-package {
            color: var(--primary);
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .status-aktif { background: rgba(16, 185, 129, 0.1); color: var(--success); }
        .status-pasif { background: rgba(107, 114, 128, 0.1); color: var(--gray-500); }
        .status-tamamlandi { background: rgba(245, 158, 11, 0.1); color: var(--warning); }
        
        .event-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
            gap: var(--spacing-md);
            margin: var(--spacing-md) 0;
        }
        
        .event-stat {
            text-align: center;
            padding: var(--spacing-sm);
            background: var(--gray-50);
            border-radius: var(--radius-md);
        }
        
        .event-stat-number {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-800);
        }
        
        .event-stat-label {
            font-size: 0.8rem;
            color: var(--gray-600);
        }
        
        .event-actions {
            display: flex;
            gap: var(--spacing-sm);
            flex-wrap: wrap;
        }
        
        .event-details {
            background: var(--gray-50);
            border-radius: var(--radius-md);
            padding: var(--spacing-md);
            margin: var(--spacing-md) 0;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: var(--spacing-xs);
        }
        
        .detail-label {
            font-weight: 500;
            color: var(--gray-600);
        }
        
        .detail-value {
            color: var(--gray-800);
        }
        
        .alert {
            border-radius: var(--radius-md);
            border: none;
            margin-bottom: var(--spacing-lg);
        }
        
        .alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin-bottom: var(--spacing-lg);
        }
        
        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
        }
        
        .breadcrumb-item.active {
            color: var(--gray-600);
        }
        
        .modal .form-control {
            background: #ffffff !important;
            border: 2px solid #d1d5db !important;
            border-radius: 8px !important;
            color: #374151 !important;
            padding: 12px !important;
            margin-bottom: 16px !important;
            transition: all 0.3s ease !important;
            pointer-events: auto !important;
            opacity: 1 !important;
            width: 100% !important;
            font-size: 14px !important;
            line-height: 1.5 !important;
            box-sizing: border-box !important;
        }
        
        .modal .form-control:focus {
            background: #ffffff !important;
            border-color: #6366f1 !important;
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1) !important;
            color: #374151 !important;
            outline: none !important;
        }
        
        .modal .form-label {
            color: #374151 !important;
            font-weight: 500 !important;
            margin-bottom: 8px !important;
            pointer-events: auto !important;
            opacity: 1 !important;
        }
        
        .modal .btn {
            pointer-events: auto !important;
            opacity: 1 !important;
            cursor: pointer !important;
            padding: 12px 24px !important;
            border-radius: 8px !important;
            font-weight: 500 !important;
            transition: all 0.3s ease !important;
        }
        
        .modal .btn-primary {
            background: #6366f1 !important;
            border-color: #6366f1 !important;
            color: white !important;
        }
        
        .modal .btn-primary:hover {
            background: #4f46e5 !important;
            border-color: #4f46e5 !important;
            color: white !important;
        }
        
        .modal .btn-secondary {
            background: #6b7280 !important;
            border-color: #6b7280 !important;
            color: white !important;
        }
        
        .modal .btn-secondary:hover {
            background: #4b5563 !important;
            border-color: #4b5563 !important;
            color: white !important;
        }
        
        .modal .btn-danger {
            background: #ef4444 !important;
            border-color: #ef4444 !important;
            color: white !important;
        }
        
        .modal .btn-danger:hover {
            background: #dc2626 !important;
            border-color: #dc2626 !important;
            color: white !important;
        }
        
        .modal .btn-close {
            pointer-events: auto !important;
            opacity: 1 !important;
            cursor: pointer !important;
        }
        
        .modal-backdrop {
            z-index: 1040 !important;
        }
        
        .modal {
            z-index: 1050 !important;
        }
        
        .user-card {
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .user-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .comments-list {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .comment-item {
            background-color: #f8f9fa;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-bottom: 2px solid transparent;
        }
        
        .nav-tabs .nav-link.active {
            border-bottom-color: #0d6efd;
            background-color: transparent;
        }
        
        .nav-tabs .nav-link:hover {
            border-bottom-color: #0d6efd;
        }
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i>Ana Sayfa</a></li>
                <li class="breadcrumb-item active">Düğünlerim</li>
            </ol>
        </nav>
        
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-calendar-check me-3"></i>Düğünlerim
            </h1>
            <p class="page-subtitle">
                Oluşturduğunuz düğünleri yönetin ve detaylı istatistikleri görüntüleyin
            </p>
        </div>
        
        <!-- Moderator Info -->
        <div class="moderator-info">
            <div class="d-flex align-items-center">
                <div class="moderator-avatar">
                    <?php echo strtoupper(substr($moderator['ad'], 0, 1) . substr($moderator['soyad'], 0, 1)); ?>
                </div>
                <div>
                    <h4 class="mb-1"><?php echo htmlspecialchars($moderator['ad'] . ' ' . $moderator['soyad']); ?></h4>
                    <p class="mb-0 opacity-75">
                        <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($moderator['email']); ?>
                    </p>
                </div>
                <div class="ms-auto">
                    <a href="create_event.php" class="btn-create-event">
                        <i class="fas fa-plus me-2"></i>Yeni Düğün Oluştur
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon events">
                    <i class="fas fa-calendar-alt"></i>
                </div>
                <div class="stat-number"><?php echo number_format($total_events); ?></div>
                <div class="stat-label">Toplam Düğün</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon active">
                    <i class="fas fa-play-circle"></i>
                </div>
                <div class="stat-number"><?php echo number_format($active_events); ?></div>
                <div class="stat-label">Aktif Düğün</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon completed">
                    <i class="fas fa-check-circle"></i>
                </div>
                <div class="stat-number"><?php echo number_format($completed_events); ?></div>
                <div class="stat-label">Tamamlanan</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon revenue">
                    <i class="fas fa-lira-sign"></i>
                </div>
                <div class="stat-number"><?php echo number_format($total_revenue, 0); ?>₺</div>
                <div class="stat-label">Toplam Gelir</div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="row align-items-end">
                <div class="col-md-4">
                    <label for="search" class="form-label">Arama</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Düğün adı veya açıklama ara..." 
                           value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2">
                    <label for="status" class="form-label">Durum</label>
                    <select class="form-control" id="status" name="status">
                        <option value="">Tüm Durumlar</option>
                        <option value="aktif" <?php echo $status_filter === 'aktif' ? 'selected' : ''; ?>>Aktif</option>
                        <option value="pasif" <?php echo $status_filter === 'pasif' ? 'selected' : ''; ?>>Pasif</option>
                        <option value="tamamlandi" <?php echo $status_filter === 'tamamlandi' ? 'selected' : ''; ?>>Tamamlandı</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label for="date_from" class="form-label">Başlangıç</label>
                    <input type="date" class="form-control" id="date_from" name="date_from" 
                           value="<?php echo htmlspecialchars($date_from); ?>">
                </div>
                <div class="col-md-2">
                    <label for="date_to" class="form-label">Bitiş</label>
                    <input type="date" class="form-control" id="date_to" name="date_to" 
                           value="<?php echo htmlspecialchars($date_to); ?>">
                </div>
                <div class="col-md-2">
                    <button type="button" class="btn btn-primary w-100" onclick="applyFilters()">
                        <i class="fas fa-search me-1"></i>Filtrele
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Events List -->
        <div class="events-container">
            <div class="row">
                <?php foreach ($events as $event): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="event-card">
                            <?php if (!empty($event['kapak_fotografi'])): ?>
                            <div class="event-photo mb-3">
                                <img src="<?php echo htmlspecialchars($event['kapak_fotografi']); ?>" 
                                     alt="<?php echo htmlspecialchars($event['baslik']); ?>" 
                                     class="img-fluid rounded" 
                                     style="width: 100%; height: 200px; object-fit: cover;">
                            </div>
                            <?php endif; ?>
                            <div class="event-header">
                                <div>
                                    <h5 class="event-title"><?php echo htmlspecialchars($event['baslik']); ?></h5>
                                    <div class="event-package">
                                        <i class="fas fa-box me-1"></i>
                                        <?php echo htmlspecialchars($event['paket_ad'] ?? 'Paket Yok'); ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $event['durum']; ?>">
                                    <?php echo ucfirst($event['durum']); ?>
                                </span>
                            </div>
                            
                            <?php if ($event['aciklama']): ?>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars(substr($event['aciklama'], 0, 100)) . (strlen($event['aciklama']) > 100 ? '...' : ''); ?></p>
                            <?php endif; ?>
                            
                            <div class="event-details">
                                <div class="detail-row">
                                    <span class="detail-label">Düğün Tarihi:</span>
                                    <span class="detail-value"><?php echo date('d.m.Y', strtotime($event['dugun_tarihi'])); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Bitiş Tarihi:</span>
                                    <span class="detail-value"><?php echo date('d.m.Y', strtotime($event['bitis_tarihi'])); ?></span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Paket Fiyatı:</span>
                                    <span class="detail-value"><?php echo number_format($event['paket_fiyati'], 0); ?>₺</span>
                                </div>
                                <div class="detail-row">
                                    <span class="detail-label">Ödeme Durumu:</span>
                                    <span class="detail-value">
                                        <?php 
                                        $payment_status = [
                                            'beklemede' => 'Beklemede',
                                            'odendi' => 'Ödendi',
                                            'iptal' => 'İptal'
                                        ];
                                        echo $payment_status[$event['odeme_durumu']] ?? $event['odeme_durumu'];
                                        ?>
                                    </span>
                                </div>
                            </div>
                            
                            <div class="event-stats">
                                <div class="event-stat">
                                    <div class="event-stat-number"><?php echo $event['katilimci_sayisi']; ?></div>
                                    <div class="event-stat-label">Katılımcı</div>
                                </div>
                                <div class="event-stat">
                                    <div class="event-stat-number"><?php echo $event['medya_sayisi']; ?></div>
                                    <div class="event-stat-label">Medya</div>
                                </div>
                                <div class="event-stat">
                                    <div class="event-stat-number"><?php echo $event['paket_medya_limiti'] ?? '∞'; ?></div>
                                    <div class="event-stat-label">Limit</div>
                                </div>
                            </div>
                            
                            <div class="event-actions">
                                <button class="btn btn-primary btn-sm" onclick="showEditEventModal(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['baslik']); ?>', '<?php echo htmlspecialchars($event['aciklama']); ?>', '<?php echo $event['dugun_tarihi']; ?>', '<?php echo $event['bitis_tarihi']; ?>', <?php echo $event['paket_id'] ?? 0; ?>, '<?php echo $event['qr_kod']; ?>', '<?php echo htmlspecialchars($event['kapak_fotografi'] ?? ''); ?>')" title="Düzenle">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <a href="manage_participants.php?event_id=<?php echo $event['id']; ?>" class="btn btn-info btn-sm" title="Katılımcıları Yönet">
                                    <i class="fas fa-users"></i>
                                </a>
                                <button class="btn btn-success btn-sm" onclick="updateEventStatus(<?php echo $event['id']; ?>, 'aktif')" title="Aktif Yap">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button class="btn btn-warning btn-sm" onclick="updateEventStatus(<?php echo $event['id']; ?>, 'tamamlandi')" title="Tamamlandı Yap">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteEvent(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['baslik']); ?>')" title="Sil">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if (empty($events)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">Henüz düğün oluşturmamışsınız</h4>
                    <p class="text-muted">İlk düğününüzü oluşturmak için aşağıdaki butona tıklayın.</p>
                    <a href="create_event.php" class="btn-create-event">
                        <i class="fas fa-plus me-2"></i>Yeni Düğün Oluştur
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateStatusModalLabel">
                        <i class="fas fa-edit me-2"></i>Düğün Durumu Güncelle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="my_events.php">
                    <div class="modal-body">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="event_id" id="update_event_id">
                        
                        <div class="mb-3">
                            <label for="new_status" class="form-label">Yeni Durum *</label>
                            <select class="form-control" id="new_status" name="new_status" required>
                                <option value="aktif">Aktif</option>
                                <option value="pasif">Pasif</option>
                                <option value="tamamlandi">Tamamlandı</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Durumu Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Event Modal -->
    <div class="modal fade" id="deleteEventModal" tabindex="-1" aria-labelledby="deleteEventModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteEventModalLabel">
                        <i class="fas fa-exclamation-triangle me-2"></i>Düğün Sil
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="my_events.php">
                    <div class="modal-body">
                        <input type="hidden" name="delete_event" value="1">
                        <input type="hidden" name="delete_event_id" id="delete_event_id">
                        
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong>Dikkat!</strong> Bu işlem geri alınamaz. Düğün ve tüm ilişkili veriler kalıcı olarak silinecektir.
                        </div>
                        
                        <p>Şu düğünü silmek istediğinizden emin misiniz?</p>
                        <div class="form-control" id="delete_event_name" style="background: var(--gray-100);"></div>
                        
                        <div class="mt-3">
                            <h6>Silinecek Veriler:</h6>
                            <ul class="text-muted">
                                <li>Düğün bilgileri</li>
                                <li>Tüm medyalar (fotoğraf/video)</li>
                                <li>Tüm yorumlar</li>
                                <li>Tüm beğeniler</li>
                                <li>Katılımcı listesi</li>
                                <li>Engellenen kullanıcılar</li>
                                <li>Komisyon geçmişi</li>
                            </ul>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash me-2"></i>Düğünü Sil
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Manage Participants Modal -->
    <div class="modal fade" id="manageParticipantsModal" tabindex="-1" aria-labelledby="manageParticipantsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title" id="manageParticipantsModalLabel">
                        <i class="fas fa-users me-2"></i>Katılımcıları Yönet
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0">
                    <!-- Tab Navigation -->
                    <ul class="nav nav-tabs nav-fill" id="participantTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="all-users-tab" data-bs-toggle="tab" data-bs-target="#all-users" type="button" role="tab">
                                <i class="fas fa-users me-2"></i>Tüm Kullanıcılar
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="participants-tab" data-bs-toggle="tab" data-bs-target="#participants" type="button" role="tab">
                                <i class="fas fa-user-check me-2"></i>Katılımcılar
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="blocked-tab" data-bs-toggle="tab" data-bs-target="#blocked" type="button" role="tab">
                                <i class="fas fa-ban me-2"></i>Engellenenler
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="participantTabsContent">
                        <!-- All Users Tab -->
                        <div class="tab-pane fade show active" id="all-users" role="tabpanel">
                            <div class="p-4">
                                <div class="row mb-3">
                                    <div class="col-md-8">
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-search"></i></span>
                                            <input type="text" class="form-control" id="searchUsers" placeholder="Kullanıcı ara...">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <select class="form-select" id="filterRole">
                                            <option value="">Tüm Roller</option>
                                            <option value="normal_kullanici">Normal Kullanıcı</option>
                                            <option value="yetkili_kullanici">Yetkili Kullanıcı</option>
                                            <option value="moderator">Moderator</option>
                                            <option value="super_admin">Super Admin</option>
                                        </select>
                                    </div>
                                </div>
                                <div id="allUsersList" class="row">
                                    <!-- All users will be loaded here -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Participants Tab -->
                        <div class="tab-pane fade" id="participants" role="tabpanel">
                            <div class="p-4">
                                <div id="participantsList" class="row">
                                    <!-- Participants will be loaded here -->
                                </div>
                            </div>
                        </div>
                        
                        <!-- Blocked Users Tab -->
                        <div class="tab-pane fade" id="blocked" role="tabpanel">
                            <div class="p-4">
                                <div id="blockedUsersList" class="row">
                                    <!-- Blocked users will be loaded here -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- User Detail Modal -->
    <div class="modal fade" id="userDetailModal" tabindex="-1" aria-labelledby="userDetailModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title" id="userDetailModalLabel">
                        <i class="fas fa-user me-2"></i>Kullanıcı Detayları
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <!-- User Info -->
                    <div class="row mb-4">
                        <div class="col-md-3 text-center">
                            <img id="userDetailPhoto" src="" alt="" class="rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">
                            <h6 id="userDetailName" class="mb-1"></h6>
                            <small id="userDetailEmail" class="text-muted"></small>
                        </div>
                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Bu Düğündeki Medyaları</h6>
                                    <div id="userMedias" class="row">
                                        <!-- User medias will be loaded here -->
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6>Bu Düğündeki Yorumları</h6>
                                    <div id="userComments" class="comments-list">
                                        <!-- User comments will be loaded here -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Permission Management -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Yetki Yönetimi</h6>
                        </div>
                        <div class="card-body">
                            <form id="permissionForm">
                                <input type="hidden" id="detailUserId" name="user_id">
                                <input type="hidden" id="detailEventId" name="event_id">
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6>Katılımcı Durumu</h6>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="participant_status" id="not_participant" value="not_participant">
                                            <label class="form-check-label" for="not_participant">
                                                Katılımcı Değil
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" name="participant_status" id="normal_participant" value="normal_participant">
                                            <label class="form-check-label" for="normal_participant">
                                                Normal Katılımcı
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="radio" name="participant_status" id="authorized_participant" value="authorized_participant">
                                            <label class="form-check-label" for="authorized_participant">
                                                Yetkili Katılımcı
                                            </label>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <h6>Özel Yetkiler</h6>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="can_delete_media" name="can_delete_media">
                                            <label class="form-check-label" for="can_delete_media">
                                                Medya Silebilir
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="can_delete_comments" name="can_delete_comments">
                                            <label class="form-check-label" for="can_delete_comments">
                                                Yorum Silebilir
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="checkbox" id="can_block_users" name="can_block_users">
                                            <label class="form-check-label" for="can_block_users">
                                                Kullanıcı Engelleyebilir
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="can_share_stories" name="can_share_stories">
                                            <label class="form-check-label" for="can_share_stories">
                                                Hikaye Paylaşabilir
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-danger" id="blockUserBtn">
                                        <i class="fas fa-ban me-2"></i>Kullanıcıyı Engelle
                                    </button>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-save me-2"></i>Yetkileri Kaydet
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Edit Event Modal -->
    <div class="modal fade" id="editEventModal" tabindex="-1" aria-labelledby="editEventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editEventModalLabel">
                        <i class="fas fa-edit me-2"></i>Düğün Düzenle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="my_events.php" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="edit_event" value="1">
                        <input type="hidden" name="event_id" id="edit_event_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_baslik" class="form-label">Düğün Başlığı *</label>
                                    <input type="text" class="form-control" id="edit_baslik" name="baslik" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_paket_id" class="form-label">Paket</label>
                                    <select class="form-control" id="edit_paket_id" name="paket_id">
                                        <option value="">Paket Seçin</option>
                                        <?php foreach ($packages as $package): ?>
                                            <option value="<?php echo $package['id']; ?>">
                                                <?php echo htmlspecialchars($package['ad']); ?> - <?php echo number_format($package['fiyat'], 0); ?>₺
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_aciklama" class="form-label">Açıklama</label>
                            <textarea class="form-control" id="edit_aciklama" name="aciklama" rows="3"></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_kapak_fotografi" class="form-label">Düğün Fotoğrafı</label>
                            <input type="file" class="form-control" id="edit_kapak_fotografi" name="kapak_fotografi" 
                                   accept="image/*" onchange="previewEditProfilePhoto(this)">
                            <div class="form-text">Yeni fotoğraf seçin (JPG, PNG, GIF). Boş bırakırsanız mevcut fotoğraf korunur.</div>
                            <div id="edit_profile_photo_preview" class="mt-3" style="display: none;">
                                <img id="edit_preview_img" src="" alt="Önizleme" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                            </div>
                            <div id="current_photo_info" class="mt-2">
                                <small class="text-muted">Mevcut fotoğraf: <span id="current_photo_name">Yok</span></small>
                                <div id="current_photo_preview" class="mt-2" style="display: none;">
                                    <img id="current_photo_img" src="" alt="Mevcut Fotoğraf" class="img-thumbnail" style="max-width: 200px; max-height: 200px;">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_dugun_tarihi" class="form-label">Düğün Tarihi *</label>
                                    <input type="date" class="form-control" id="edit_dugun_tarihi" name="dugun_tarihi" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="edit_bitis_tarihi" class="form-label">Bitiş Tarihi *</label>
                                    <input type="date" class="form-control" id="edit_bitis_tarihi" name="bitis_tarihi" required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- QR Code Section -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-qrcode me-2"></i>QR Kod Bilgileri</h6>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">QR Kod</label>
                                            <div class="qr-code-preview">
                                                <div id="edit_qr_placeholder" class="text-center text-muted">
                                                    <i class="fas fa-qrcode fa-3x mb-2"></i>
                                                    <p>QR kod yükleniyor...</p>
                                                </div>
                                                <img id="edit_qr_image" src="" style="display: none; max-width: 200px; height: auto;" class="img-fluid">
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Manuel Kod</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="edit_manual_code" readonly>
                                                <button class="btn btn-outline-secondary" type="button" onclick="copyManualCode('edit_manual_code')">
                                                    <i class="fas fa-copy"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="downloadQRPNG('edit_qr_image', 'edit_baslik')">
                                                <i class="fas fa-download me-1"></i>PNG İndir
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="downloadQRPDF('edit_qr_image', 'edit_baslik')">
                                                <i class="fas fa-file-pdf me-1"></i>PDF İndir
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Düğünü Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Update Participant Role Modal -->
    <div class="modal fade" id="updateRoleModal" tabindex="-1" aria-labelledby="updateRoleModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="updateRoleModalLabel">
                        <i class="fas fa-user-edit me-2"></i>Katılımcı Rolünü Güncelle
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="my_events.php">
                    <div class="modal-body">
                        <input type="hidden" name="update_participant_role" value="1">
                        <input type="hidden" name="event_id" id="update_role_event_id">
                        <input type="hidden" name="participant_id" id="update_role_participant_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Katılımcı</label>
                            <p class="form-control-plaintext" id="update_role_participant_name"></p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_role" class="form-label">Yeni Rol *</label>
                            <select class="form-control" id="new_role" name="new_role" required>
                                <option value="normal_kullanici">Normal Kullanıcı</option>
                                <option value="yetkili_kullanici">Yetkili Kullanıcı</option>
                            </select>
                            <small class="form-text text-muted">
                                <i class="fas fa-info-circle me-1"></i>
                                <strong>Normal Kullanıcı:</strong> Sadece kendi medya/yorumlarını düzenleyebilir<br>
                                <strong>Yetkili Kullanıcı:</strong> Diğer kullanıcıların medyalarını silebilir, kendi yorumlarını düzenleyebilir
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Rolü Güncelle
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php include 'includes/footer.php'; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateEventStatus(eventId, status) {
            document.getElementById('update_event_id').value = eventId;
            document.getElementById('new_status').value = status;
            
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }
        
        function deleteEvent(eventId, eventName) {
            document.getElementById('delete_event_id').value = eventId;
            document.getElementById('delete_event_name').textContent = eventName;
            
            new bootstrap.Modal(document.getElementById('deleteEventModal')).show();
        }
        
        function showManageParticipantsModal(eventId, eventName) {
            document.getElementById('manageParticipantsModalLabel').innerHTML = 
                '<i class="fas fa-users me-2"></i>Katılımcıları Yönet - ' + eventName;
            
            // Store current event ID for use in other functions
            window.currentEventId = eventId;
            
            // Load all data
            loadAllUsers(eventId);
            loadParticipants(eventId);
            loadBlockedUsers(eventId);
            
            new bootstrap.Modal(document.getElementById('manageParticipantsModal')).show();
        }
        
        function loadAllUsers(eventId) {
            const allUsersList = document.getElementById('allUsersList');
            allUsersList.innerHTML = '<div class="col-12 text-center"><i class="fas fa-spinner fa-spin"></i> Yükleniyor...</div>';
            
            fetch(`ajax/get_all_users.php?event_id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayAllUsers(data.users);
                    } else {
                        allUsersList.innerHTML = '<div class="col-12 text-center text-danger">Kullanıcılar yüklenirken hata oluştu.</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    allUsersList.innerHTML = '<div class="col-12 text-center text-danger">Kullanıcılar yüklenirken hata oluştu.</div>';
                });
        }
        
        function displayAllUsers(users) {
            const allUsersList = document.getElementById('allUsersList');
            let html = '';
            
            if (users.length === 0) {
                html = '<div class="col-12 text-center text-muted"><i class="fas fa-users fa-2x mb-2"></i><p>Kullanıcı bulunamadı</p></div>';
            } else {
                users.forEach(user => {
                    const statusClass = user.is_participant ? 'success' : 'secondary';
                    const statusText = user.is_participant ? 'Katılımcı' : 'Katılımcı Değil';
                    const roleClass = user.rol === 'yetkili_kullanici' ? 'warning' : 'primary';
                    const roleText = user.rol === 'yetkili_kullanici' ? 'Yetkili' : 'Normal';
                    
                    html += `
                        <div class="col-md-6 col-lg-4 mb-3">
                            <div class="card user-card" onclick="showUserDetail(${user.id}, '${user.ad} ${user.soyad}', '${user.email}', '${user.profil_fotografi || 'assets/images/default_profile.svg'}')">
                                <div class="card-body text-center">
                                    <img src="${user.profil_fotografi || 'assets/images/default_profile.svg'}" 
                                         alt="${user.ad} ${user.soyad}" 
                                         class="rounded-circle mb-2" 
                                         style="width: 60px; height: 60px; object-fit: cover;">
                                    <h6 class="card-title mb-1">${user.ad} ${user.soyad}</h6>
                                    <small class="text-muted d-block mb-2">${user.email}</small>
                                    <div class="d-flex justify-content-center gap-2">
                                        <span class="badge bg-${statusClass}">${statusText}</span>
                                        <span class="badge bg-${roleClass}">${roleText}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            
            allUsersList.innerHTML = html;
        }
        
        function showUserDetail(userId, userName, userEmail, userPhoto) {
            document.getElementById('userDetailPhoto').src = userPhoto;
            document.getElementById('userDetailName').textContent = userName;
            document.getElementById('userDetailEmail').textContent = userEmail;
            document.getElementById('detailUserId').value = userId;
            document.getElementById('detailEventId').value = window.currentEventId;
            
            // Load user's medias and comments for this event
            loadUserMedias(userId, window.currentEventId);
            loadUserComments(userId, window.currentEventId);
            
            // Load user's current permissions
            loadUserPermissions(userId, window.currentEventId);
            
            new bootstrap.Modal(document.getElementById('userDetailModal')).show();
        }
        
        function loadUserMedias(userId, eventId) {
            const userMedias = document.getElementById('userMedias');
            userMedias.innerHTML = '<div class="col-12 text-center"><i class="fas fa-spinner fa-spin"></i> Yükleniyor...</div>';
            
            fetch(`ajax/get_user_medias.php?user_id=${userId}&event_id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayUserMedias(data.medias);
                    } else {
                        userMedias.innerHTML = '<div class="col-12 text-center text-muted">Medya bulunamadı</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    userMedias.innerHTML = '<div class="col-12 text-center text-muted">Medya yüklenirken hata oluştu</div>';
                });
        }
        
        function displayUserMedias(medias) {
            const userMedias = document.getElementById('userMedias');
            let html = '';
            
            if (medias.length === 0) {
                html = '<div class="col-12 text-center text-muted"><i class="fas fa-image fa-2x mb-2"></i><p>Medya bulunamadı</p></div>';
            } else {
                medias.forEach(media => {
                    html += `
                        <div class="col-6 col-md-4 mb-2">
                            <div class="card">
                                <img src="${media.kucuk_resim_yolu || media.dosya_yolu}" 
                                     class="card-img-top" 
                                     style="height: 100px; object-fit: cover;"
                                     alt="Medya">
                                <div class="card-body p-2">
                                    <small class="text-muted">${new Date(media.created_at).toLocaleDateString('tr-TR')}</small>
                                </div>
                            </div>
                        </div>
                    `;
                });
            }
            
            userMedias.innerHTML = html;
        }
        
        function loadUserComments(userId, eventId) {
            const userComments = document.getElementById('userComments');
            userComments.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Yükleniyor...</div>';
            
            fetch(`ajax/get_user_comments.php?user_id=${userId}&event_id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayUserComments(data.comments);
                    } else {
                        userComments.innerHTML = '<div class="text-center text-muted">Yorum bulunamadı</div>';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    userComments.innerHTML = '<div class="text-center text-muted">Yorumlar yüklenirken hata oluştu</div>';
                });
        }
        
        function displayUserComments(comments) {
            const userComments = document.getElementById('userComments');
            let html = '';
            
            if (comments.length === 0) {
                html = '<div class="text-center text-muted"><i class="fas fa-comment fa-2x mb-2"></i><p>Yorum bulunamadı</p></div>';
            } else {
                comments.forEach(comment => {
                    html += `
                        <div class="comment-item mb-2 p-2 border rounded">
                            <div class="d-flex justify-content-between">
                                <small class="text-muted">${new Date(comment.created_at).toLocaleDateString('tr-TR')}</small>
                                <small class="text-muted">${comment.like_count} beğeni</small>
                            </div>
                            <p class="mb-0 small">${comment.content}</p>
                        </div>
                    `;
                });
            }
            
            userComments.innerHTML = html;
        }
        
        function loadUserPermissions(userId, eventId) {
            fetch(`ajax/get_user_permissions.php?user_id=${userId}&event_id=${eventId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Set participant status
                        const statusRadio = document.querySelector(`input[name="participant_status"][value="${data.permissions.participant_status}"]`);
                        if (statusRadio) statusRadio.checked = true;
                        
                        // Set special permissions
                        document.getElementById('can_delete_media').checked = data.permissions.can_delete_media;
                        document.getElementById('can_delete_comments').checked = data.permissions.can_delete_comments;
                        document.getElementById('can_block_users').checked = data.permissions.can_block_users;
                        document.getElementById('can_share_stories').checked = data.permissions.can_share_stories;
                    }
                })
                .catch(error => {
                    console.error('Error loading permissions:', error);
                });
        }
        
        // Permission form submission
        document.getElementById('permissionForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('update_permissions', '1');
            
            fetch('my_events.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                // Reload the page to show updated data
                location.reload();
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Yetkiler güncellenirken hata oluştu.');
            });
        });
        
        // Block user button
        document.getElementById('blockUserBtn').addEventListener('click', function() {
            const userId = document.getElementById('detailUserId').value;
            const eventId = document.getElementById('detailEventId').value;
            const reason = prompt('Bu kullanıcıyı engelleme sebebi (isteğe bağlı):');
            
            if (reason !== null) {
                const formData = new FormData();
                formData.append('block_participant', '1');
                formData.append('event_id', eventId);
                formData.append('participant_id', userId);
                formData.append('block_reason', reason);
                
                fetch('my_events.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    location.reload();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Kullanıcı engellenirken hata oluştu.');
                });
            }
        });
        
        // Search functionality
        document.getElementById('searchUsers').addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const userCards = document.querySelectorAll('.user-card');
            
            userCards.forEach(card => {
                const userName = card.querySelector('.card-title').textContent.toLowerCase();
                const userEmail = card.querySelector('.text-muted').textContent.toLowerCase();
                
                if (userName.includes(searchTerm) || userEmail.includes(searchTerm)) {
                    card.closest('.col-md-6').style.display = 'block';
                } else {
                    card.closest('.col-md-6').style.display = 'none';
                }
            });
        });
        
        // Role filter functionality
        document.getElementById('filterRole').addEventListener('change', function() {
            const selectedRole = this.value;
            const userCards = document.querySelectorAll('.user-card');
            
            userCards.forEach(card => {
                const roleBadge = card.querySelector('.badge:last-child');
                const roleText = roleBadge.textContent.toLowerCase();
                
                if (selectedRole === '' || roleText.includes(selectedRole.replace('_', ' '))) {
                    card.closest('.col-md-6').style.display = 'block';
                } else {
                    card.closest('.col-md-6').style.display = 'none';
                }
            });
        });
        
        function loadParticipants(eventId) {
            const participantsList = document.getElementById('participantsList');
            participantsList.innerHTML = '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Yükleniyor...</div>';
            
            // Get participants data from PHP
            const events = <?php echo json_encode($events); ?>;
            const event = events.find(e => e.id == eventId);
            
            let html = '';
            
            // Katılımcıları göster
            if (event && event.participants && event.participants.length > 0) {
                html += '<h6 class="mb-3"><i class="fas fa-users me-2"></i>Katılımcılar</h6>';
                event.participants.forEach(participant => {
                    const roleClass = participant.rol === 'yetkili_kullanici' ? 'yetkili_kullanici' : 'normal_kullanici';
                    const roleText = participant.rol === 'yetkili_kullanici' ? 'Yetkili' : 'Normal';
                    
                    html += `
                        <div class="participant-item mb-2">
                            <div class="participant-info">
                                <div class="participant-name">${participant.ad} ${participant.soyad}</div>
                                <div class="participant-email">${participant.email}</div>
                            </div>
                            <div class="d-flex align-items-center">
                                <span class="participant-role ${roleClass}">${roleText}</span>
                                <button class="btn btn-sm btn-outline-primary me-1" 
                                        onclick="showUpdateRoleModal(${eventId}, ${participant.id}, '${participant.ad} ${participant.soyad}', '${participant.rol}')"
                                        title="Rol Değiştir">
                                    <i class="fas fa-user-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-info me-1" 
                                        onclick="showUpdatePermissionsModal(${eventId}, ${participant.id}, '${participant.ad} ${participant.soyad}', '${participant.rol}')"
                                        title="Yetkileri Düzenle">
                                    <i class="fas fa-cog"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-warning me-1" 
                                        onclick="blockParticipant(${eventId}, ${participant.id}, '${participant.ad} ${participant.soyad}')"
                                        title="Kullanıcıyı Engelle">
                                    <i class="fas fa-ban"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" 
                                        onclick="removeParticipant(${eventId}, ${participant.id}, '${participant.ad} ${participant.soyad}')"
                                        title="Katılımcıyı Kaldır">
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
            }
            
            // Engellenen kullanıcıları göster
            if (event && event.blocked_users && event.blocked_users.length > 0) {
                html += '<hr class="my-3">';
                html += '<h6 class="mb-3 text-danger"><i class="fas fa-ban me-2"></i>Engellenen Kullanıcılar</h6>';
                event.blocked_users.forEach(blocked => {
                    html += `
                        <div class="participant-item mb-2 p-2 rounded" style="background-color: rgba(220, 53, 69, 0.1); border-color: rgba(220, 53, 69, 0.3);">
                            <div class="participant-info">
                                <div class="participant-name text-danger">${blocked.ad} ${blocked.soyad}</div>
                                <div class="participant-email">${blocked.email}</div>
                                <div class="text-muted small">Engelleme sebebi: ${blocked.reason || 'Belirtilmemiş'}</div>
                                <div class="text-muted small">Engellenme tarihi: ${new Date(blocked.block_date).toLocaleDateString('tr-TR')}</div>
                            </div>
                            <div class="d-flex align-items-center">
                                <button class="btn btn-sm btn-outline-success" 
                                        onclick="unblockParticipant(${eventId}, ${blocked.id}, '${blocked.ad} ${blocked.soyad}')"
                                        title="Engeli Kaldır">
                                    <i class="fas fa-unlock"></i>
                                </button>
                            </div>
                        </div>
                    `;
                });
            }
            
            if (html === '') {
                html = `
                    <div class="text-center text-muted">
                        <i class="fas fa-users fa-2x mb-2"></i>
                        <p>Henüz katılımcı eklenmemiş</p>
                    </div>
                `;
            }
            
            participantsList.innerHTML = html;
        }
        
        // Edit profile photo preview function
        function previewEditProfilePhoto(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('edit_preview_img').src = e.target.result;
                    document.getElementById('edit_profile_photo_preview').style.display = 'block';
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
        
        function showEditEventModal(eventId, baslik, aciklama, dugunTarihi, bitisTarihi, paketId, qrKod, profilFotografi) {
            document.getElementById('edit_event_id').value = eventId;
            document.getElementById('edit_baslik').value = baslik;
            document.getElementById('edit_aciklama').value = aciklama;
            document.getElementById('edit_dugun_tarihi').value = dugunTarihi;
            document.getElementById('edit_bitis_tarihi').value = bitisTarihi;
            document.getElementById('edit_paket_id').value = paketId;
            document.getElementById('edit_manual_code').value = qrKod;
            
            // Mevcut fotoğraf bilgisini göster
            const currentPhotoName = document.getElementById('current_photo_name');
            const currentPhotoPreview = document.getElementById('current_photo_preview');
            const currentPhotoImg = document.getElementById('current_photo_img');
            
            if (profilFotografi && profilFotografi.trim() !== '') {
                const fileName = profilFotografi.split('/').pop();
                currentPhotoName.textContent = fileName;
                
                // Mevcut fotoğrafı göster
                currentPhotoImg.src = profilFotografi;
                currentPhotoPreview.style.display = 'block';
            } else {
                currentPhotoName.textContent = 'Yok';
                currentPhotoPreview.style.display = 'none';
            }
            
            // Önizleme alanını temizle
            document.getElementById('edit_profile_photo_preview').style.display = 'none';
            document.getElementById('edit_kapak_fotografi').value = '';
            
            // QR kod oluştur
            generateEditQRCode(qrKod, baslik);
            
            new bootstrap.Modal(document.getElementById('editEventModal')).show();
        }
        
        function generateEditQRCode(qrCode, title) {
            const platformName = 'Digital Salon';
            
            const qrData = {
                qr_code: qrCode,
                event_title: title,
                platform: platformName,
                timestamp: new Date().toISOString()
            };
            
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(JSON.stringify(qrData))}`;
            
            const img = new Image();
            img.crossOrigin = 'anonymous';
            
            img.onload = function() {
                document.getElementById('edit_qr_image').src = qrUrl;
                document.getElementById('edit_qr_image').style.display = 'block';
                document.getElementById('edit_qr_placeholder').style.display = 'none';
            };
            
            img.onerror = function() {
                // Fallback to Google Charts API
                const fallbackUrl = `https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=${encodeURIComponent(JSON.stringify(qrData))}`;
                const fallbackImg = new Image();
                fallbackImg.crossOrigin = 'anonymous';
                
                fallbackImg.onload = function() {
                    document.getElementById('edit_qr_image').src = fallbackUrl;
                    document.getElementById('edit_qr_image').style.display = 'block';
                    document.getElementById('edit_qr_placeholder').style.display = 'none';
                };
                
                fallbackImg.src = fallbackUrl;
            };
            
            img.src = qrUrl;
        }
        
        function copyManualCode(inputId) {
            const input = document.getElementById(inputId);
            if (!input.value || input.value.trim() === '') {
                alert('Manuel kod henüz oluşturulmadı.');
                return;
            }
            
            try {
                input.focus();
                input.select();
                input.setSelectionRange(0, 99999);
                
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(input.value).then(function() {
                        showCopySuccess(input);
                    }).catch(function(err) {
                        console.error('Clipboard API hatası:', err);
                        fallbackCopy(input);
                    });
                } else {
                    fallbackCopy(input);
                }
            } catch (error) {
                console.error('Kopyalama hatası:', error);
                alert('Kopyalama hatası: ' + error.message);
            }
        }
        
        function fallbackCopy(input) {
            try {
                const successful = document.execCommand('copy');
                if (successful) {
                    showCopySuccess(input);
                } else {
                    alert('Kopyalama başarısız. Manuel olarak kopyalayın.');
                }
            } catch (err) {
                console.error('execCommand hatası:', err);
                alert('Kopyalama desteklenmiyor. Manuel olarak kopyalayın.');
            }
        }
        
        function showCopySuccess(input) {
            const originalValue = input.value;
            input.value = 'Kopyalandı!';
            input.style.backgroundColor = '#d4edda';
            input.style.color = '#155724';
            
            setTimeout(function() {
                input.value = originalValue;
                input.style.backgroundColor = '';
                input.style.color = '';
            }, 2000);
        }
        
        function downloadQRPNG(imageId, titleId) {
            const img = document.getElementById(imageId);
            const title = document.getElementById(titleId).value || 'Dugun';
            
            if (!img || !img.src || img.src.includes('data:image/svg')) {
                alert('QR kod henüz yüklenmedi. Lütfen bekleyin ve tekrar deneyin.');
                return;
            }
            
            try {
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                canvas.width = 400;
                canvas.height = 500;
                
                ctx.fillStyle = '#ffffff';
                ctx.fillRect(0, 0, canvas.width, canvas.height);
                
                ctx.strokeStyle = '#000000';
                ctx.lineWidth = 2;
                ctx.strokeRect(0, 0, canvas.width, canvas.height);
                
                const newImg = new Image();
                newImg.crossOrigin = 'anonymous';
                
                newImg.onload = function() {
                    try {
                        ctx.drawImage(newImg, 50, 50, 300, 300);
                        
                        ctx.fillStyle = '#000000';
                        ctx.font = 'bold 16px Arial';
                        ctx.textAlign = 'center';
                        ctx.fillText(title, canvas.width / 2, 380);
                        
                        ctx.font = '14px Arial';
                        ctx.fillText('Digital Salon', canvas.width / 2, 400);
                        
                        const link = document.createElement('a');
                        const fileName = `${title.replace(/[^a-zA-Z0-9]/g, '_')}_QR_Code.png`;
                        link.download = fileName;
                        link.href = canvas.toDataURL('image/png');
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    } catch (error) {
                        downloadImageDirectly(img.src, fileName);
                    }
                };
                
                newImg.onerror = function() {
                    downloadImageDirectly(img.src, `${title.replace(/[^a-zA-Z0-9]/g, '_')}_QR_Code.png`);
                };
                
                newImg.src = img.src;
            } catch (error) {
                console.error('PNG indirme hatası:', error);
                alert('PNG indirme hatası: ' + error.message);
            }
        }
        
        function downloadQRPDF(imageId, titleId) {
            const img = document.getElementById(imageId);
            const title = document.getElementById(titleId).value || 'Dugun';
            
            if (!img || !img.src || img.src.includes('data:image/svg')) {
                alert('QR kod henüz yüklenmedi. Lütfen bekleyin ve tekrar deneyin.');
                return;
            }
            
            try {
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <html>
                        <head>
                            <title>QR Code - ${title}</title>
                            <style>
                                body { 
                                    font-family: Arial, sans-serif; 
                                    text-align: center; 
                                    padding: 20px;
                                    margin: 0;
                                }
                                .qr-container {
                                    margin: 20px auto;
                                    max-width: 400px;
                                    border: 2px solid #000;
                                    padding: 20px;
                                }
                                .qr-image {
                                    max-width: 300px;
                                    height: auto;
                                    display: block;
                                    margin: 0 auto;
                                }
                                .qr-title {
                                    font-size: 18px;
                                    font-weight: bold;
                                    margin: 15px 0 5px 0;
                                }
                                .qr-platform {
                                    font-size: 14px;
                                    color: #666;
                                    margin-bottom: 10px;
                                }
                                @media print {
                                    body { margin: 0; padding: 10px; }
                                    .qr-container { border: 2px solid #000; }
                                }
                            </style>
                        </head>
                        <body>
                            <div class="qr-container">
                                <img src="${img.src}" alt="QR Code" class="qr-image">
                                <div class="qr-title">${title}</div>
                                <div class="qr-platform">Digital Salon</div>
                            </div>
                        </body>
                    </html>
                `);
                printWindow.document.close();
                
                setTimeout(function() {
                    printWindow.print();
                }, 500);
            } catch (error) {
                console.error('PDF indirme hatası:', error);
                alert('PDF indirme hatası: ' + error.message);
            }
        }
        
        function downloadImageDirectly(imageSrc, fileName) {
            try {
                const link = document.createElement('a');
                link.href = imageSrc;
                link.download = fileName;
                link.target = '_blank';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            } catch (error) {
                console.error('Direkt indirme hatası:', error);
                alert('QR kod indirilemedi. Lütfen QR kodu sağ tıklayıp "Resmi farklı kaydet" seçeneğini kullanın.');
            }
        }
        
        function showUpdateRoleModal(eventId, participantId, participantName, currentRole) {
            document.getElementById('update_role_event_id').value = eventId;
            document.getElementById('update_role_participant_id').value = participantId;
            document.getElementById('update_role_participant_name').textContent = participantName;
            document.getElementById('new_role').value = currentRole;
            
            new bootstrap.Modal(document.getElementById('updateRoleModal')).show();
        }
        
        function blockParticipant(eventId, participantId, participantName) {
            const reason = prompt('Bu kullanıcıyı engelleme sebebi (isteğe bağlı):');
            if (reason !== null) { // Cancel butonuna basılmadıysa
                if (confirm('Bu kullanıcıyı engellemek istediğinizden emin misiniz?\n\n' + participantName + '\n\nBu kullanıcı artık bu düğüne erişemeyecek.')) {
                    // Create a form and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'my_events.php';
                    
                    const eventIdInput = document.createElement('input');
                    eventIdInput.type = 'hidden';
                    eventIdInput.name = 'event_id';
                    eventIdInput.value = eventId;
                    
                    const participantIdInput = document.createElement('input');
                    participantIdInput.type = 'hidden';
                    participantIdInput.name = 'participant_id';
                    participantIdInput.value = participantId;
                    
                    const reasonInput = document.createElement('input');
                    reasonInput.type = 'hidden';
                    reasonInput.name = 'block_reason';
                    reasonInput.value = reason;
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'block_participant';
                    actionInput.value = '1';
                    
                    form.appendChild(eventIdInput);
                    form.appendChild(participantIdInput);
                    form.appendChild(reasonInput);
                    form.appendChild(actionInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            }
        }
        
        function unblockParticipant(eventId, participantId, participantName) {
            if (confirm('Bu kullanıcının engelini kaldırmak istediğinizden emin misiniz?\n\n' + participantName)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'my_events.php';
                
                const eventIdInput = document.createElement('input');
                eventIdInput.type = 'hidden';
                eventIdInput.name = 'event_id';
                eventIdInput.value = eventId;
                
                const participantIdInput = document.createElement('input');
                participantIdInput.type = 'hidden';
                participantIdInput.name = 'participant_id';
                participantIdInput.value = participantId;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'unblock_participant';
                actionInput.value = '1';
                
                form.appendChild(eventIdInput);
                form.appendChild(participantIdInput);
                form.appendChild(actionInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function removeParticipant(eventId, participantId, participantName) {
            if (confirm('Bu katılımcıyı düğünden kaldırmak istediğinizden emin misiniz?\n\n' + participantName)) {
                // Create a form and submit it
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'my_events.php';
                
                const eventIdInput = document.createElement('input');
                eventIdInput.type = 'hidden';
                eventIdInput.name = 'event_id';
                eventIdInput.value = eventId;
                
                const participantIdInput = document.createElement('input');
                participantIdInput.type = 'hidden';
                participantIdInput.name = 'participant_id';
                participantIdInput.value = participantId;
                
                const actionInput = document.createElement('input');
                actionInput.type = 'hidden';
                actionInput.name = 'remove_participant';
                actionInput.value = '1';
                
                form.appendChild(eventIdInput);
                form.appendChild(participantIdInput);
                form.appendChild(actionInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function applyFilters() {
            const search = document.getElementById('search').value;
            const status = document.getElementById('status').value;
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            
            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (status) params.append('status', status);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            window.location.href = 'my_events.php?' + params.toString();
        }
        
        // Enter tuşu ile filtreleme
        document.getElementById('search').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                applyFilters();
            }
        });
        
        // Modal aktivasyon kodları
        document.addEventListener('DOMContentLoaded', function() {
            // Update Status Modal
            const updateStatusModal = document.getElementById('updateStatusModal');
            if (updateStatusModal) {
                updateStatusModal.addEventListener('shown.bs.modal', function() {
                    console.log('Update Status Modal açıldı');
                    
                    const allInputs = this.querySelectorAll('input, select, button');
                    allInputs.forEach(element => {
                        element.style.pointerEvents = 'auto';
                        element.style.opacity = '1';
                        element.disabled = false;
                        element.readOnly = false;
                        
                        if (element.tagName === 'SELECT') {
                            element.style.background = '#ffffff';
                            element.style.border = '2px solid #d1d5db';
                            element.style.color = '#374151';
                        }
                    });
                });
            }
            
            // Delete Event Modal
            const deleteEventModal = document.getElementById('deleteEventModal');
            if (deleteEventModal) {
                deleteEventModal.addEventListener('shown.bs.modal', function() {
                    console.log('Delete Event Modal açıldı');
                    
                    const allButtons = this.querySelectorAll('button');
                    allButtons.forEach(button => {
                        button.style.pointerEvents = 'auto';
                        button.style.opacity = '1';
                        button.disabled = false;
                    });
                });
            }
        });
    </script>
</body>
</html>
