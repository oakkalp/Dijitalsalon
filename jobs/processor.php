<?php
/**
 * Background Job Processor
 * Digital Salon - Arka plan işlemleri
 */

require_once '../config/database.php';
require_once '../includes/security.php';
require_once '../includes/performance.php';

// Job processor configuration
$config = [
    'redis_host' => '127.0.0.1',
    'redis_port' => 6379,
    'max_execution_time' => 300, // 5 minutes
    'memory_limit' => '256M'
];

// Set execution limits
ini_set('max_execution_time', $config['max_execution_time']);
ini_set('memory_limit', $config['memory_limit']);

// Initialize Redis
try {
    $redis = new Redis();
    $redis->connect($config['redis_host'], $config['redis_port']);
} catch (Exception $e) {
    error_log("Redis connection failed: " . $e->getMessage());
    exit(1);
}

// Initialize database
try {
    $pdo = new PDO("mysql:host=localhost;dbname=digitalsalon_db;charset=utf8mb4", 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    exit(1);
}

// Initialize job processor
$jobProcessor = new BackgroundJobProcessor($pdo, $redis);
$imageOptimizer = new ImageOptimizer(85, 1920, 1080);

// Process jobs
echo "Starting job processor...\n";

while (true) {
    try {
        // Process high priority jobs first
        $highPriorityJobs = $redis->lrange('high_priority_jobs', 0, -1);
        foreach ($highPriorityJobs as $jobData) {
            $job = json_decode($jobData, true);
            if ($job && processJob($job, $pdo, $redis, $imageOptimizer)) {
                $redis->lrem('high_priority_jobs', $jobData, 1);
            }
        }
        
        // Process normal priority jobs
        $normalJobs = $redis->lrange('jobs', 0, -1);
        foreach ($normalJobs as $jobData) {
            $job = json_decode($jobData, true);
            if ($job && processJob($job, $pdo, $redis, $imageOptimizer)) {
                $redis->lrem('jobs', $jobData, 1);
            }
        }
        
        // Sleep for 1 second before next iteration
        sleep(1);
        
        // Check memory usage
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = ini_get('memory_limit');
        $memoryLimitBytes = return_bytes($memoryLimit);
        
        if ($memoryUsage > ($memoryLimitBytes * 0.8)) {
            echo "Memory usage high, restarting...\n";
            break;
        }
        
    } catch (Exception $e) {
        error_log("Job processor error: " . $e->getMessage());
        sleep(5); // Wait 5 seconds before retrying
    }
}

echo "Job processor stopped.\n";

/**
 * Process individual job
 */
function processJob($job, $pdo, $redis, $imageOptimizer) {
    try {
        $jobId = $job['id'];
        $jobType = $job['type'];
        $jobData = $job['data'];
        $attempts = $job['attempts'] ?? 0;
        $maxAttempts = $job['max_attempts'] ?? 3;
        
        echo "Processing job {$jobId} of type {$jobType} (attempt {$attempts})\n";
        
        $success = false;
        
        switch ($jobType) {
            case 'optimize_image':
                $success = optimizeImageJob($jobData, $imageOptimizer);
                break;
                
            case 'generate_thumbnail':
                $success = generateThumbnailJob($jobData, $imageOptimizer);
                break;
                
            case 'cleanup_expired_stories':
                $success = cleanupExpiredStoriesJob($jobData, $pdo);
                break;
                
            case 'send_notification':
                $success = sendNotificationJob($jobData, $pdo);
                break;
                
            case 'process_commission':
                $success = processCommissionJob($jobData, $pdo);
                break;
                
            case 'generate_qr_code':
                $success = generateQRCodeJob($jobData, $pdo);
                break;
                
            case 'backup_database':
                $success = backupDatabaseJob($jobData, $pdo);
                break;
                
            case 'cleanup_old_logs':
                $success = cleanupOldLogsJob($jobData, $pdo);
                break;
                
            case 'update_statistics':
                $success = updateStatisticsJob($jobData, $pdo);
                break;
                
            default:
                echo "Unknown job type: {$jobType}\n";
                $success = false;
                break;
        }
        
        if ($success) {
            echo "Job {$jobId} completed successfully\n";
            logJobCompletion($jobId, $jobType, 'completed', $pdo);
            return true;
        } else {
            echo "Job {$jobId} failed\n";
            $job['attempts'] = $attempts + 1;
            
            if ($job['attempts'] < $maxAttempts) {
                // Retry job
                $redis->lpush('jobs', json_encode($job));
                echo "Job {$jobId} queued for retry\n";
            } else {
                // Move to failed jobs
                $redis->lpush('failed_jobs', json_encode($job));
                logJobCompletion($jobId, $jobType, 'failed', $pdo);
                echo "Job {$jobId} moved to failed queue\n";
            }
            
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Job processing error: " . $e->getMessage());
        return false;
    }
}

/**
 * Optimize image job
 */
function optimizeImageJob($data, $imageOptimizer) {
    try {
        $sourcePath = $data['source_path'];
        $destinationPath = $data['destination_path'];
        
        if (!file_exists($sourcePath)) {
            echo "Source file not found: {$sourcePath}\n";
            return false;
        }
        
        $result = $imageOptimizer->optimizeImage($sourcePath, $destinationPath);
        
        if ($result) {
            echo "Image optimized: {$sourcePath} -> {$destinationPath}\n";
            return true;
        } else {
            echo "Image optimization failed: {$sourcePath}\n";
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Image optimization error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate thumbnail job
 */
function generateThumbnailJob($data, $imageOptimizer) {
    try {
        $sourcePath = $data['source_path'];
        $thumbnailPath = $data['thumbnail_path'];
        $width = $data['width'] ?? 300;
        $height = $data['height'] ?? 300;
        
        if (!file_exists($sourcePath)) {
            echo "Source file not found: {$sourcePath}\n";
            return false;
        }
        
        $result = $imageOptimizer->generateThumbnail($sourcePath, $thumbnailPath, $width, $height);
        
        if ($result) {
            echo "Thumbnail generated: {$sourcePath} -> {$thumbnailPath}\n";
            return true;
        } else {
            echo "Thumbnail generation failed: {$sourcePath}\n";
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Thumbnail generation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Cleanup expired stories job
 */
function cleanupExpiredStoriesJob($data, $pdo) {
    try {
        $stmt = $pdo->prepare("DELETE FROM medyalar WHERE hikaye_mi = 1 AND expires_at < NOW()");
        $result = $stmt->execute();
        
        $deletedCount = $stmt->rowCount();
        echo "Cleaned up {$deletedCount} expired stories\n";
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Story cleanup error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send notification job
 */
function sendNotificationJob($data, $pdo) {
    try {
        $userId = $data['user_id'];
        $title = $data['title'];
        $message = $data['message'];
        $type = $data['type'] ?? 'info';
        
        // Insert notification into database
        $stmt = $pdo->prepare("
            INSERT INTO bildirimler (kullanici_id, baslik, mesaj, tur, okundu, olusturma_tarihi)
            VALUES (?, ?, ?, ?, 0, NOW())
        ");
        $result = $stmt->execute([$userId, $title, $message, $type]);
        
        if ($result) {
            echo "Notification sent to user {$userId}: {$title}\n";
            return true;
        } else {
            echo "Failed to send notification to user {$userId}\n";
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Notification error: " . $e->getMessage());
        return false;
    }
}

/**
 * Process commission job
 */
function processCommissionJob($data, $pdo) {
    try {
        $eventId = $data['event_id'];
        $moderatorId = $data['moderator_id'];
        $packageId = $data['package_id'];
        $packagePrice = $data['package_price'];
        
        // Get commission rate for this moderator and package
        $stmt = $pdo->prepare("
            SELECT komisyon_orani FROM komisyonlar 
            WHERE moderator_id = ? AND paket_id = ? 
            ORDER BY gecerlilik_tarihi DESC 
            LIMIT 1
        ");
        $stmt->execute([$moderatorId, $packageId]);
        $commission = $stmt->fetch();
        
        if (!$commission) {
            echo "No commission rate found for moderator {$moderatorId} and package {$packageId}\n";
            return false;
        }
        
        $commissionRate = $commission['komisyon_orani'];
        $commissionAmount = ($packagePrice * $commissionRate) / 100;
        
        // Insert commission record
        $stmt = $pdo->prepare("
            INSERT INTO komisyon_gecmisi (dugun_id, moderator_id, paket_id, paket_fiyati, komisyon_orani, komisyon_tutari, durum, olusturma_tarihi)
            VALUES (?, ?, ?, ?, ?, ?, 'beklemede', NOW())
        ");
        $result = $stmt->execute([$eventId, $moderatorId, $packageId, $packagePrice, $commissionRate, $commissionAmount]);
        
        if ($result) {
            echo "Commission processed for event {$eventId}: {$commissionAmount} TL\n";
            return true;
        } else {
            echo "Failed to process commission for event {$eventId}\n";
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Commission processing error: " . $e->getMessage());
        return false;
    }
}

/**
 * Generate QR code job
 */
function generateQRCodeJob($data, $pdo) {
    try {
        $eventId = $data['event_id'];
        $qrCode = $data['qr_code'];
        $eventTitle = $data['event_title'];
        
        // Generate QR code image
        $qrData = "Digital Salon/join_event.php?qr={$qrCode}";
        $qrImagePath = "uploads/qr_codes/{$eventId}_qr.png";
        
        // Ensure directory exists
        $qrDir = dirname($qrImagePath);
        if (!is_dir($qrDir)) {
            mkdir($qrDir, 0755, true);
        }
        
        // Generate QR code using Google Charts API
        $qrUrl = "https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=" . urlencode($qrData);
        $qrImage = file_get_contents($qrUrl);
        
        if ($qrImage !== false) {
            file_put_contents($qrImagePath, $qrImage);
            echo "QR code generated for event {$eventId}: {$qrImagePath}\n";
            return true;
        } else {
            echo "Failed to generate QR code for event {$eventId}\n";
            return false;
        }
        
    } catch (Exception $e) {
        error_log("QR code generation error: " . $e->getMessage());
        return false;
    }
}

/**
 * Backup database job
 */
function backupDatabaseJob($data, $pdo) {
    try {
        $backupPath = $data['backup_path'] ?? 'backups/';
        $filename = 'backup_' . date('Y-m-d_H-i-s') . '.sql';
        $fullPath = $backupPath . $filename;
        
        // Ensure backup directory exists
        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }
        
        // Get database configuration
        $dsn = $pdo->getAttribute(PDO::ATTR_CONNECTION_STATUS);
        
        // Use mysqldump for backup
        $command = "mysqldump -u root -p digitalsalon_db > {$fullPath}";
        $result = exec($command);
        
        if (file_exists($fullPath) && filesize($fullPath) > 0) {
            echo "Database backup created: {$fullPath}\n";
            return true;
        } else {
            echo "Database backup failed\n";
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Database backup error: " . $e->getMessage());
        return false;
    }
}

/**
 * Cleanup old logs job
 */
function cleanupOldLogsJob($data, $pdo) {
    try {
        $daysToKeep = $data['days_to_keep'] ?? 30;
        
        // Cleanup old API logs
        $stmt = $pdo->prepare("DELETE FROM api_logs WHERE timestamp < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$daysToKeep]);
        $apiLogsDeleted = $stmt->rowCount();
        
        // Cleanup old job logs
        $stmt = $pdo->prepare("DELETE FROM job_logs WHERE completed_at < DATE_SUB(NOW(), INTERVAL ? DAY)");
        $stmt->execute([$daysToKeep]);
        $jobLogsDeleted = $stmt->rowCount();
        
        echo "Cleaned up {$apiLogsDeleted} API logs and {$jobLogsDeleted} job logs older than {$daysToKeep} days\n";
        
        return true;
        
    } catch (Exception $e) {
        error_log("Log cleanup error: " . $e->getMessage());
        return false;
    }
}

/**
 * Update statistics job
 */
function updateStatisticsJob($data, $pdo) {
    try {
        $eventId = $data['event_id'] ?? null;
        
        if ($eventId) {
            // Update specific event statistics
            $stmt = $pdo->prepare("
                UPDATE dugunler SET 
                    katilimci_sayisi = (SELECT COUNT(*) FROM dugun_katilimcilar WHERE dugun_id = ?),
                    medya_sayisi = (SELECT COUNT(*) FROM medyalar WHERE dugun_id = ?),
                    guncelleme_tarihi = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$eventId, $eventId, $eventId]);
            
            echo "Statistics updated for event {$eventId}\n";
        } else {
            // Update global statistics
            $stmt = $pdo->prepare("
                UPDATE ayarlar SET deger = (
                    SELECT COUNT(*) FROM kullanicilar WHERE durum = 'aktif'
                ) WHERE anahtar = 'toplam_kullanici'
            ");
            $stmt->execute();
            
            $stmt = $pdo->prepare("
                UPDATE ayarlar SET deger = (
                    SELECT COUNT(*) FROM dugunler WHERE durum = 'aktif'
                ) WHERE anahtar = 'toplam_dugun'
            ");
            $stmt->execute();
            
            echo "Global statistics updated\n";
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Statistics update error: " . $e->getMessage());
        return false;
    }
}

/**
 * Log job completion
 */
function logJobCompletion($jobId, $jobType, $status, $pdo) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO job_logs (job_id, job_type, status, completed_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$jobId, $jobType, $status]);
    } catch (Exception $e) {
        error_log("Job logging error: " . $e->getMessage());
    }
}

/**
 * Convert memory limit string to bytes
 */
function return_bytes($val) {
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    $val = (int) $val;
    
    switch($last) {
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }
    
    return $val;
}

// Handle graceful shutdown
function shutdown() {
    global $redis;
    echo "Shutting down job processor...\n";
    if ($redis) {
        $redis->close();
    }
}

register_shutdown_function('shutdown');

// Handle signals
if (function_exists('pcntl_signal')) {
    pcntl_signal(SIGTERM, function() {
        echo "Received SIGTERM, shutting down...\n";
        exit(0);
    });
    
    pcntl_signal(SIGINT, function() {
        echo "Received SIGINT, shutting down...\n";
        exit(0);
    });
}
?>
