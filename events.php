<?php
/**
 * Events Management Page
 * Digital Salon - Modern Düğün Yönetimi
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

// Sadece super admin erişebilir
if ($user_role !== 'super_admin') {
    header('Location: dashboard.php');
    exit();
}

$success_message = '';
$error_message = '';

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
        $stmt = $pdo->prepare("DELETE FROM dugunler WHERE id = ?");
        $stmt->execute([$delete_event_id]);
        
        $pdo->commit();
        $success_message = 'Düğün ve tüm ilişkili veriler başarıyla silindi.';
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
        $stmt = $pdo->prepare("UPDATE dugunler SET durum = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $event_id]);
        
        $success_message = 'Düğün durumu başarıyla güncellendi.';
    } catch (Exception $e) {
        $error_message = 'Düğün durumu güncellenirken bir hata oluştu.';
        error_log("Event status update error: " . $e->getMessage());
    }
}

// Filtreleme
$search = $_GET['search'] ?? '';
$status_filter = $_GET['status'] ?? '';
$moderator_filter = $_GET['moderator'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(d.baslik LIKE ? OR d.aciklama LIKE ? OR k.ad LIKE ? OR k.soyad LIKE ?)";
    $search_param = "%$search%";
    $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
}

if (!empty($status_filter)) {
    $where_conditions[] = "d.durum = ?";
    $params[] = $status_filter;
}

if (!empty($moderator_filter)) {
    $where_conditions[] = "d.moderator_id = ?";
    $params[] = $moderator_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "d.dugun_tarihi >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "d.dugun_tarihi <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Düğünleri listele
$stmt = $pdo->prepare("
    SELECT 
        d.*,
        k.ad as moderator_ad,
        k.soyad as moderator_soyad,
        k.email as moderator_email,
        p.ad as paket_ad,
        p.fiyat as paket_fiyati,
        (SELECT COUNT(*) FROM dugun_katilimcilar WHERE dugun_id = d.id) as katilimci_sayisi,
        (SELECT COUNT(*) FROM medyalar WHERE dugun_id = d.id) as medya_sayisi,
        (SELECT COUNT(*) FROM komisyon_gecmisi WHERE dugun_id = d.id) as komisyon_sayisi
    FROM dugunler d
    LEFT JOIN kullanicilar k ON d.moderator_id = k.id
    LEFT JOIN paketler p ON d.paket_id = p.id
    $where_clause
    ORDER BY d.created_at DESC
");
$stmt->execute($params);
$events = $stmt->fetchAll();

// İstatistikler
$total_events = $pdo->query("SELECT COUNT(*) FROM dugunler")->fetchColumn();
$active_events = $pdo->query("SELECT COUNT(*) FROM dugunler WHERE durum = 'aktif'")->fetchColumn();
$completed_events = $pdo->query("SELECT COUNT(*) FROM dugunler WHERE durum = 'tamamlandi'")->fetchColumn();
$total_revenue = $pdo->query("SELECT SUM(COALESCE(paket_fiyati, 0)) FROM dugunler WHERE odeme_durumu = 'odendi'")->fetchColumn() ?: 0;

// Moderatorları listele (filtre için)
$moderators = $pdo->query("SELECT id, ad, soyad FROM kullanicilar WHERE rol = 'moderator' ORDER BY ad, soyad")->fetchAll();
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Düğün Yönetimi - Digital Salon</title>
    
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
        
        .event-moderator {
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
    </style>
</head>
<body>
    <?php include 'includes/navbar.php'; ?>
    
    <div class="container mt-4">
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="dashboard.php"><i class="fas fa-home me-1"></i>Ana Sayfa</a></li>
                <li class="breadcrumb-item active">Düğün Yönetimi</li>
            </ol>
        </nav>
        
        <!-- Page Header -->
        <div class="page-header">
            <h1 class="page-title">
                <i class="fas fa-calendar-alt me-3"></i>Düğün Yönetimi
            </h1>
            <p class="page-subtitle">
                Tüm düğünleri görüntüleyin, yönetin ve detaylı raporları inceleyin
            </p>
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
                <div class="col-md-3">
                    <label for="search" class="form-label">Arama</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Düğün adı, açıklama veya moderator ara..." 
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
                    <label for="moderator" class="form-label">Moderator</label>
                    <select class="form-control" id="moderator" name="moderator">
                        <option value="">Tüm Moderatorlar</option>
                        <?php foreach ($moderators as $moderator): ?>
                            <option value="<?php echo $moderator['id']; ?>" <?php echo $moderator_filter == $moderator['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($moderator['ad'] . ' ' . $moderator['soyad']); ?>
                            </option>
                        <?php endforeach; ?>
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
                <div class="col-md-1">
                    <button type="button" class="btn btn-primary w-100" onclick="applyFilters()">
                        <i class="fas fa-search"></i>
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
                            <div class="event-header">
                                <div>
                                    <h5 class="event-title"><?php echo htmlspecialchars($event['baslik']); ?></h5>
                                    <div class="event-moderator">
                                        <i class="fas fa-user-tie me-1"></i>
                                        <?php echo htmlspecialchars($event['moderator_ad'] . ' ' . $event['moderator_soyad']); ?>
                                    </div>
                                </div>
                                <span class="status-badge status-<?php echo $event['durum']; ?>">
                                    <?php echo ucfirst($event['durum']); ?>
                                </span>
                            </div>
                            
                            <?php if ($event['aciklama']): ?>
                                <p class="text-muted mb-3"><?php echo htmlspecialchars(substr($event['aciklama'], 0, 100)) . (strlen($event['aciklama']) > 100 ? '...' : ''); ?></p>
                            <?php endif; ?>
                            
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
                                    <div class="event-stat-number"><?php echo number_format($event['paket_fiyati'], 0); ?>₺</div>
                                    <div class="event-stat-label">Fiyat</div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    <?php echo date('d.m.Y', strtotime($event['dugun_tarihi'])); ?>
                                </small>
                                <?php if ($event['paket_ad']): ?>
                                    <br><small class="text-muted">
                                        <i class="fas fa-box me-1"></i>
                                        <?php echo htmlspecialchars($event['paket_ad']); ?>
                                    </small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="event-actions">
                                <button class="btn btn-success btn-sm" onclick="updateEventStatus(<?php echo $event['id']; ?>, 'aktif')">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button class="btn btn-warning btn-sm" onclick="updateEventStatus(<?php echo $event['id']; ?>, 'tamamlandi')">
                                    <i class="fas fa-check"></i>
                                </button>
                                <button class="btn btn-danger btn-sm" onclick="deleteEvent(<?php echo $event['id']; ?>, '<?php echo htmlspecialchars($event['baslik']); ?>')">
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
                    <h4 class="text-muted">Düğün bulunamadı</h4>
                    <p class="text-muted">Arama kriterlerinize uygun düğün bulunmuyor.</p>
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
                <form method="POST" action="events.php">
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
                <form method="POST" action="events.php">
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
        
        function applyFilters() {
            const search = document.getElementById('search').value;
            const status = document.getElementById('status').value;
            const moderator = document.getElementById('moderator').value;
            const dateFrom = document.getElementById('date_from').value;
            const dateTo = document.getElementById('date_to').value;
            
            const params = new URLSearchParams();
            if (search) params.append('search', search);
            if (status) params.append('status', status);
            if (moderator) params.append('moderator', moderator);
            if (dateFrom) params.append('date_from', dateFrom);
            if (dateTo) params.append('date_to', dateTo);
            
            window.location.href = 'events.php?' + params.toString();
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
