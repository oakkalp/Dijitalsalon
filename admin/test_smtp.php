<?php
session_start();

// Admin giriş kontrolü
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';

$admin_user_id = $_SESSION['admin_user_id'];
$admin_user_role = $_SESSION['admin_user_role'];

// Sadece super_admin erişebilir
if ($admin_user_role !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

// SMTP ayarlarını yükle
$smtp_config_file = __DIR__ . '/../config/smtp.php';
$smtp_config = file_exists($smtp_config_file) ? require $smtp_config_file : [];

$test_result = null;
$error_message = '';

// Test email gönder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $test_email = $_POST['test_email'] ?? '';
    
    if (empty($test_email)) {
        $error_message = 'Test email adresi gerekli';
    } else {
        try {
            // ✅ send_email_helper.php'yi güvenli bir şekilde yükle
            $helper_file = __DIR__ . '/../digimobiapi/send_email_helper.php';
            if (!file_exists($helper_file)) {
                throw new Exception("send_email_helper.php dosyası bulunamadı: $helper_file");
            }
            
            require_once $helper_file;
            
            // ✅ Fonksiyonun var olup olmadığını kontrol et
            if (!function_exists('sendEmailViaSMTP')) {
                throw new Exception("sendEmailViaSMTP fonksiyonu bulunamadı");
            }
            
            $subject = "SMTP Test - Digital Salon";
            $message = "Bu bir test emailidir.\n\nSMTP ayarları çalışıyor!";
            
            $test_result = sendEmailViaSMTP($test_email, $subject, $message, false);
        } catch (Exception $e) {
            $error_message = "Hata: " . $e->getMessage();
            error_log("SMTP Test Error: " . $e->getMessage());
            $test_result = [
                'success' => false,
                'message' => 'Email gönderilemedi: ' . $e->getMessage(),
                'error' => $e->getMessage()
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Test - Dijitalsalon Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #f8fafc;
            color: #1e293b;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar */
        .sidebar {
            width: 260px;
            background: white;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
        }

        .sidebar-logo {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .sidebar-logo i {
            font-size: 1.5rem;
            color: #6366f1;
        }

        .sidebar-logo h2 {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1e293b;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem;
            background: #f1f5f9;
            border-radius: 10px;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }

        .user-details h4 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e293b;
        }

        .user-details p {
            font-size: 0.8rem;
            color: #64748b;
        }

        .role-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
            text-transform: uppercase;
        }

        .role-super_admin {
            background: #fef3c7;
            color: #d97706;
        }

        .role-moderator {
            background: #dbeafe;
            color: #2563eb;
        }

        .sidebar-nav {
            padding: 1rem 0;
        }

        .nav-item {
            margin: 0.25rem 1rem;
        }

        .nav-link {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            color: #64748b;
            text-decoration: none;
            border-radius: 10px;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link:hover {
            background: #f1f5f9;
            color: #6366f1;
        }

        .nav-link.active {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }

        .nav-link i {
            font-size: 1.1rem;
            width: 20px;
        }

        .main-content {
            flex: 1;
            margin-left: 260px;
            padding: 2rem;
        }

        .main-header {
            margin-bottom: 2rem;
        }

        .main-title {
            font-size: 1.75rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .test-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .test-card h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .config-info {
            background: #f1f5f9;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .config-info h3 {
            font-size: 1rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e2e8f0;
        }

        .info-row:last-child {
            border-bottom: none;
        }

        .info-label {
            color: #64748b;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .info-value {
            font-weight: 600;
            color: #1e293b;
            font-size: 0.9rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .form-group input {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #1e293b;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus {
            outline: none;
            border-color: #6366f1;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .btn-primary {
            background: #3b82f6;
            color: white;
        }

        .btn-primary:hover {
            background: #2563eb;
        }

        .btn-secondary {
            background: #64748b;
            color: white;
        }

        .btn-secondary:hover {
            background: #475569;
        }

        .result-box {
            padding: 1rem;
            border-radius: 8px;
            margin-top: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .result-box.success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #22c55e;
        }

        .result-box.error {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #dc2626;
        }

        .error-details {
            margin-top: 1rem;
            padding: 1rem;
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            font-size: 0.85rem;
            color: #991b1b;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
        }

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .message.error {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #dc2626;
        }

        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="main-header">
                <h1 class="main-title">
                    <i class="fas fa-vial"></i>
                    SMTP Bağlantı Testi
                </h1>
            </div>

            <?php if ($error_message): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <!-- SMTP Ayarları Bilgisi -->
            <div class="config-info">
                <h3><i class="fas fa-info-circle"></i> Mevcut SMTP Ayarları</h3>
                <div class="info-row">
                    <span class="info-label">Durum:</span>
                    <span class="info-value">
                        <?php echo (!empty($smtp_config['enabled']) && $smtp_config['enabled']) ? '<span style="color: #22c55e;">✓ Etkin</span>' : '<span style="color: #ef4444;">✗ Devre Dışı</span>'; ?>
                    </span>
                </div>
                <div class="info-row">
                    <span class="info-label">SMTP Host:</span>
                    <span class="info-value"><?php echo htmlspecialchars($smtp_config['host'] ?? 'Ayarlanmamış'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Port:</span>
                    <span class="info-value"><?php echo htmlspecialchars($smtp_config['port'] ?? 'Ayarlanmamış'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Şifreleme:</span>
                    <span class="info-value"><?php echo htmlspecialchars($smtp_config['encryption'] ?? 'Yok'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Kullanıcı Adı:</span>
                    <span class="info-value"><?php echo htmlspecialchars($smtp_config['username'] ?? 'Ayarlanmamış'); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Şifre:</span>
                    <span class="info-value"><?php echo !empty($smtp_config['password']) ? '••••••••' : 'Ayarlanmamış'; ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Gönderen Email:</span>
                    <span class="info-value"><?php echo htmlspecialchars($smtp_config['from_email'] ?? 'Ayarlanmamış'); ?></span>
                </div>
            </div>

            <!-- Test Formu -->
            <div class="test-card">
                <h2><i class="fas fa-paper-plane"></i> Test Email Gönder</h2>
                
                <form method="POST" action="test_smtp.php">
                    <div class="form-group">
                        <label>Test Email Adresi *</label>
                        <input type="email" name="test_email" required placeholder="test@example.com" value="<?php echo htmlspecialchars($_POST['test_email'] ?? ''); ?>">
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            Test Email Gönder
                        </button>
                    <a href="settings.php?tab=smtp" class="btn btn-secondary">
                        <i class="fas fa-cog"></i>
                        SMTP Ayarlarını Düzenle
                    </a>
                    <a href="test_smtp_connection.php" target="_blank" class="btn btn-secondary" style="background: #10b981;">
                        <i class="fas fa-terminal"></i>
                        Detaylı Test (Yeni Sekme)
                    </a>
                    <a href="test_smtp_outbound.php" target="_blank" class="btn btn-secondary" style="background: #f59e0b;">
                        <i class="fas fa-network-wired"></i>
                        Outbound Test (Yeni Sekme)
                    </a>
                    </div>
                </form>

                <?php if ($test_result !== null): ?>
                    <div class="result-box <?php echo $test_result['success'] ? 'success' : 'error'; ?>">
                        <?php if ($test_result['success']): ?>
                            <i class="fas fa-check-circle"></i>
                            <div>
                                <strong>Başarılı!</strong><br>
                                <span><?php echo htmlspecialchars($test_result['message']); ?></span>
                            </div>
                        <?php else: ?>
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <strong>Hata!</strong><br>
                                <span><?php echo htmlspecialchars($test_result['message']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($test_result['error'])): ?>
                        <div class="error-details">
                            <strong>Hata Detayı:</strong><br>
                            <?php echo htmlspecialchars($test_result['error']); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Bağlantı Testi -->
            <div class="test-card">
                <h2><i class="fas fa-network-wired"></i> Bağlantı Testi</h2>
                <p style="color: #64748b; margin-bottom: 1rem;">SMTP sunucusuna bağlantı testi yapılıyor...</p>
                
                <?php
                if (!empty($smtp_config['host']) && !empty($smtp_config['port'])) {
                    $host = $smtp_config['host'];
                    $port = $smtp_config['port'];
                    $encryption = $smtp_config['encryption'] ?? '';
                    
                    // ✅ Önce normal socket bağlantısı testi
                    echo '<div style="margin-bottom: 1rem;">';
                    echo '<strong style="color: #64748b;">1. TCP Bağlantı Testi (Port ' . $port . '):</strong><br>';
                    
                    $connection = @fsockopen($host, $port, $errno, $errstr, 5);
                    
                    if ($connection) {
                        echo '<span style="color: #22c55e;">✓ Başarılı - Port açık</span><br>';
                        fclose($connection);
                    } else {
                        echo '<span style="color: #ef4444;">✗ Başarısız - ' . htmlspecialchars("$errstr ($errno)") . '</span><br>';
                    }
                    echo '</div>';
                    
                    // ✅ SSL/TLS stream_socket_client testi
                    if ($encryption === 'ssl' || $encryption === 'tls') {
                        echo '<div style="margin-bottom: 1rem;">';
                        echo '<strong style="color: #64748b;">2. ' . strtoupper($encryption) . ' Bağlantı Testi (' . $encryption . '://' . $host . ':' . $port . '):</strong><br>';
                        
                        $context = stream_context_create([
                            'ssl' => [
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true
                            ]
                        ]);
                        
                        if ($encryption === 'ssl') {
                            $socket_address = "ssl://{$host}:{$port}";
                        } else {
                            // TLS için önce normal TCP, sonra STARTTLS
                            $socket_address = "tcp://{$host}:{$port}";
                        }
                        
                        $ssl_connection = @stream_socket_client($socket_address, $ssl_errno, $ssl_errstr, 5, STREAM_CLIENT_CONNECT, $context);
                        
                        if ($ssl_connection) {
                            if ($encryption === 'tls') {
                                // TLS için STARTTLS yapılmalı
                                stream_set_blocking($ssl_connection, true);
                                $response = fgets($ssl_connection, 515);
                                if (preg_match('/^220/', $response)) {
                                    fwrite($ssl_connection, "EHLO {$host}\r\n");
                                    $response = fgets($ssl_connection, 515);
                                    fwrite($ssl_connection, "STARTTLS\r\n");
                                    $response = fgets($ssl_connection, 515);
                                    if (preg_match('/^220/', $response)) {
                                        if (stream_socket_enable_crypto($ssl_connection, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                                            echo '<span style="color: #22c55e;">✓ Başarılı - TLS bağlantısı kuruldu</span><br>';
                                        } else {
                                            echo '<span style="color: #ef4444;">✗ Başarısız - TLS handshake başarısız</span><br>';
                                        }
                                    } else {
                                        echo '<span style="color: #ef4444;">✗ Başarısız - STARTTLS desteklenmiyor</span><br>';
                                    }
                                }
                            } else {
                                echo '<span style="color: #22c55e;">✓ Başarılı - SSL bağlantısı kuruldu</span><br>';
                            }
                            fclose($ssl_connection);
                        } else {
                            echo '<span style="color: #ef4444;">✗ Başarısız - ' . htmlspecialchars("$ssl_errstr ($ssl_errno)") . '</span><br>';
                            echo '<div class="error-details" style="margin-top: 0.5rem;">';
                            echo '<strong>Çözüm Önerileri:</strong><br>';
                            echo '1. Port ' . $port . ' firewall\'da engellenmiş olabilir - Portu açın<br>';
                            echo '2. Alternatif olarak Port 587 (TLS) kullanmayı deneyin<br>';
                            echo '3. PHP\'de OpenSSL extension aktif olmalı: <code>php -m | grep openssl</code><br>';
                            echo '4. Yandex Mail ayarlarında "Uygulama şifreleri" kullanın<br>';
                            echo '5. Hosting sağlayıcınızdan port ' . $port . ' açık mı kontrol edin';
                            echo '</div>';
                        }
                        echo '</div>';
                    }
                    
                    // ✅ Genel sonuç
                    if ($connection || (isset($ssl_connection) && $ssl_connection)) {
                        echo '<div class="result-box success">';
                        echo '<i class="fas fa-check-circle"></i>';
                        echo '<div><strong>Bağlantı Testi Başarılı!</strong><br>SMTP sunucusuna bağlanılabildi.</div>';
                        echo '</div>';
                    } else {
                        echo '<div class="result-box error">';
                        echo '<i class="fas fa-times-circle"></i>';
                        echo '<div><strong>Bağlantı Testi Başarısız!</strong><br>SMTP sunucusuna bağlanılamadı.</div>';
                        echo '</div>';
                    }
                } else {
                    echo '<div class="result-box error">';
                    echo '<i class="fas fa-exclamation-triangle"></i>';
                    echo '<div>SMTP ayarları eksik. Lütfen SMTP ayarlarını düzenleyin.</div>';
                    echo '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>

