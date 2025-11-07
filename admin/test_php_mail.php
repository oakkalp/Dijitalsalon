<?php
/**
 * PHP mail() Fonksiyonu Testi
 * SMTP bağlantısı olmadan yerel mail server üzerinden test
 */

session_start();

// Admin giriş kontrolü
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

require_once '../config/database.php';

$admin_user_role = $_SESSION['admin_user_role'];

// Sadece super_admin erişebilir
if ($admin_user_role !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

$test_result = null;
$error_message = '';

// Test email gönder
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['test_email'])) {
    $test_email = $_POST['test_email'] ?? '';
    
    if (empty($test_email)) {
        $error_message = 'Test email adresi gerekli';
    } else {
        // PHP mail() fonksiyonu ile test
        $subject = "PHP mail() Test - Digital Salon";
        $message = "Bu bir test emailidir.\n\nPHP mail() fonksiyonu çalışıyor!";
        
        $headers = "From: dijitalsalon@cagapps.app\r\n";
        $headers .= "Reply-To: dijitalsalon@cagapps.app\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . PHP_VERSION;
        
        $result = @mail($test_email, $subject, $message, $headers);
        
        $test_result = [
            'success' => $result,
            'message' => $result ? 'Email gönderildi (PHP mail() fonksiyonu)' : 'Email gönderilemedi',
            'error' => $result ? null : error_get_last()['message'] ?? 'Bilinmeyen hata'
        ];
    }
}

// SMTP ayarlarını yükle
$smtp_config = require __DIR__ . '/../config/smtp.php';
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PHP mail() Test - Dijitalsalon Admin</title>
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

        /* Sidebar CSS */
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

        .info-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .info-card h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .warning-box {
            background: #fef3c7;
            border: 1px solid #f59e0b;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .warning-box h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #d97706;
            margin-bottom: 0.5rem;
        }

        .warning-box p {
            font-size: 0.85rem;
            color: #92400e;
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
    </style>
</head>
<body>
    <div class="admin-container">
        <?php include 'includes/sidebar.php'; ?>

        <div class="main-content">
            <div class="main-header">
                <h1 class="main-title">
                    <i class="fas fa-envelope-open"></i>
                    PHP mail() Fonksiyonu Testi
                </h1>
            </div>

            <div class="warning-box">
                <h3><i class="fas fa-exclamation-triangle"></i> Önemli Bilgi</h3>
                <p>
                    SMTP outbound bağlantıları engellenmiş durumda. PHP mail() fonksiyonu sunucunun yerel mail server'ını kullanır 
                    ve Yandex'in mail relay özelliğini kullanabilir. DNS kayıtlarınızda Yandex MX kaydı mevcut, bu yüzden 
                    email'ler Yandex üzerinden gönderilebilir.
                </p>
            </div>

            <?php if ($error_message): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <div class="info-card">
                <h2><i class="fas fa-info-circle"></i> PHP mail() Fonksiyonu Hakkında</h2>
                <p style="color: #64748b; margin-bottom: 1rem;">
                    PHP mail() fonksiyonu, sunucunun yerel mail server'ını kullanarak email gönderir. 
                    Sunucunuz Windows IIS kullanıyorsa, IIS SMTP veya yerel mail server yapılandırması gerekebilir.
                </p>
                <p style="color: #64748b;">
                    <strong>Avantajları:</strong> Firewall sorunları yok, direkt sunucu üzerinden gönderilir.<br>
                    <strong>Dezavantajları:</strong> Sunucu mail server yapılandırması gerekebilir, spam filtrelerinde sorun olabilir.
                </p>
            </div>

            <div class="info-card">
                <h2><i class="fas fa-paper-plane"></i> Test Email Gönder</h2>
                
                <form method="POST" action="test_php_mail.php">
                    <div class="form-group">
                        <label>Test Email Adresi *</label>
                        <input type="email" name="test_email" required placeholder="test@example.com" value="<?php echo htmlspecialchars($_POST['test_email'] ?? ''); ?>">
                    </div>

                    <div style="display: flex; gap: 1rem;">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i>
                            PHP mail() ile Test Gönder
                        </button>
                        <a href="test_smtp.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            SMTP Test'e Dön
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
                                <p style="margin-top: 0.5rem; font-size: 0.85rem;">
                                    Email gönderildi. Gelen kutusunu kontrol edin. Spam klasörüne de bakın.
                                </p>
                            </div>
                        <?php else: ?>
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>
                                <strong>Başarısız!</strong><br>
                                <span><?php echo htmlspecialchars($test_result['message']); ?></span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (!empty($test_result['error'])): ?>
                        <div style="margin-top: 1rem; padding: 1rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 8px; font-size: 0.85rem; color: #991b1b;">
                            <strong>Hata Detayı:</strong><br>
                            <?php echo htmlspecialchars($test_result['error']); ?>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <h2><i class="fas fa-lightbulb"></i> Alternatif Çözümler</h2>
                <p style="color: #64748b; margin-bottom: 1rem;">
                    Eğer PHP mail() de çalışmazsa, aşağıdaki alternatifleri kullanabilirsiniz:
                </p>
                <ul style="color: #64748b; padding-left: 1.5rem; line-height: 2;">
                    <li><strong>SendGrid:</strong> DNS kayıtlarınızda zaten var (include:sendgrid.net). SendGrid API kullanabilirsiniz.</li>
                    <li><strong>Mailgun:</strong> Ücretsiz tier ile ayda 5,000 email gönderebilirsiniz.</li>
                    <li><strong>Amazon SES:</strong> Düşük maliyetli, güvenilir email servisi.</li>
                    <li><strong>IIS SMTP Relay:</strong> IIS üzerinden Yandex'e relay yapılandırması.</li>
                </ul>
            </div>
        </div>
    </div>
</body>
</html>

