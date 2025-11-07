<?php
session_start();
require_once 'config/database.php';
require_once 'includes/security.php';

// Check if user is logged in and is moderator
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'moderator') {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success_message = '';
$error_message = '';

// Get user's events
try {
    $stmt = $pdo->prepare("
        SELECT d.*, 
               COUNT(DISTINCT dk.id) as participant_count,
               COUNT(DISTINCT m.id) as media_count
        FROM dugunler d
        LEFT JOIN dugun_katilimcilar dk ON d.id = dk.dugun_id
        LEFT JOIN medyalar m ON d.id = m.dugun_id
        WHERE d.moderator_id = ?
        GROUP BY d.id
        ORDER BY d.olusturma_tarihi DESC
    ");
    $stmt->execute([$user_id]);
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $events = [];
    $error_message = 'Düğünler yüklenirken hata oluştu: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Düğünlerim | Digital Salon</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            margin: 20px;
            overflow: hidden;
        }
        
        .page-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .page-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
        }
        
        .event-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .event-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
        }
        
        .event-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .event-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #333;
            margin: 0;
        }
        
        .event-status {
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status-aktif {
            background: #d4edda;
            color: #155724;
        }
        
        .status-tamamlandi {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-beklemede {
            background: #f8d7da;
            color: #721c24;
        }
        
        .event-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .info-item i {
            margin-right: 10px;
            color: #667eea;
        }
        
        .event-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-custom {
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .stats-row {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #e9ecef;
        }
        
        .stat-card {
            text-align: center;
            padding: 15px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
        }
        
        .stat-label {
            color: #6c757d;
            font-size: 0.9rem;
            margin-top: 5px;
        }
        
        .back-btn {
            position: absolute;
            top: 20px;
            left: 20px;
            background: rgba(255,255,255,0.2);
            border: none;
            color: white;
            padding: 10px 15px;
            border-radius: 10px;
            transition: all 0.2s;
        }
        
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <a href="dashboard.php" class="back-btn">
                <i class="fas fa-arrow-left me-2"></i>Geri Dön
            </a>
            <div class="page-title">
                <i class="fas fa-calendar-alt me-3"></i>Düğünlerim
            </div>
            <div class="page-subtitle">
                Oluşturduğunuz düğünleri yönetin
            </div>
        </div>
        
        <!-- Stats Row -->
        <div class="stats-row">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count($events); ?></div>
                        <div class="stat-label">Toplam Düğün</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo count(array_filter($events, fn($e) => $e['durum'] === 'aktif')); ?></div>
                        <div class="stat-label">Aktif Düğün</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo array_sum(array_column($events, 'participant_count')); ?></div>
                        <div class="stat-label">Toplam Katılımcı</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo array_sum(array_column($events, 'media_count')); ?></div>
                        <div class="stat-label">Toplam Medya</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show m-3" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <!-- Events List -->
        <div class="p-3">
            <?php if (empty($events)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                    <h5 class="text-muted">Henüz düğün oluşturmamışsınız</h5>
                    <p class="text-muted">İlk düğününüzü oluşturmak için aşağıdaki butona tıklayın.</p>
                    <a href="create_event.php" class="btn btn-primary btn-lg">
                        <i class="fas fa-plus me-2"></i>Düğün Oluştur
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($events as $event): ?>
                    <div class="event-card">
                        <div class="event-header">
                            <h3 class="event-title"><?php echo htmlspecialchars($event['baslik']); ?></h3>
                            <span class="event-status status-<?php echo $event['durum']; ?>">
                                <?php echo ucfirst($event['durum']); ?>
                            </span>
                        </div>
                        
                        <div class="event-info">
                            <div class="info-item">
                                <i class="fas fa-calendar"></i>
                                <div>
                                    <strong>Düğün Tarihi:</strong><br>
                                    <?php echo date('d.m.Y', strtotime($event['dugun_tarihi'])); ?>
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-users"></i>
                                <div>
                                    <strong>Katılımcılar:</strong><br>
                                    <?php echo $event['participant_count']; ?> kişi
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-image"></i>
                                <div>
                                    <strong>Medyalar:</strong><br>
                                    <?php echo $event['media_count']; ?> adet
                                </div>
                            </div>
                            <div class="info-item">
                                <i class="fas fa-box"></i>
                                <div>
                                    <strong>Paket:</strong><br>
                                    <?php echo htmlspecialchars($event['paket_id'] ? 'Paket #' . $event['paket_id'] : 'Belirtilmemiş'); ?>
                                </div>
                            </div>
                        </div>
                        
                        <?php if ($event['aciklama']): ?>
                            <div class="mb-3">
                                <strong>Açıklama:</strong>
                                <p class="text-muted mb-0"><?php echo htmlspecialchars($event['aciklama']); ?></p>
                            </div>
                        <?php endif; ?>
                        
                        <div class="event-actions">
                            <a href="manage_participants.php?event_id=<?php echo $event['id']; ?>" 
                               class="btn btn-info btn-custom">
                                <i class="fas fa-users me-1"></i>Katılımcıları Yönet
                            </a>
                            <a href="event.php?id=<?php echo $event['id']; ?>" 
                               class="btn btn-primary btn-custom">
                                <i class="fas fa-eye me-1"></i>Düğünü Görüntüle
                            </a>
                            <a href="edit_event.php?id=<?php echo $event['id']; ?>" 
                               class="btn btn-warning btn-custom">
                                <i class="fas fa-edit me-1"></i>Düzenle
                            </a>
                            <button class="btn btn-success btn-custom" 
                                    onclick="updateStatus(<?php echo $event['id']; ?>, 'aktif')">
                                <i class="fas fa-play me-1"></i>Aktif Yap
                            </button>
                            <button class="btn btn-secondary btn-custom" 
                                    onclick="updateStatus(<?php echo $event['id']; ?>, 'tamamlandi')">
                                <i class="fas fa-check me-1"></i>Tamamlandı Yap
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateStatus(eventId, status) {
            if (confirm('Düğün durumunu güncellemek istediğinizden emin misiniz?')) {
                const formData = new FormData();
                formData.append('update_status', '1');
                formData.append('event_id', eventId);
                formData.append('new_status', status);
                
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
                    alert('Durum güncellenirken hata oluştu.');
                });
            }
        }
    </script>
</body>
</html>
