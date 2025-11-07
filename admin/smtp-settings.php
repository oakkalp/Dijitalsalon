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

$success_message = $_GET['success'] ?? '';
$error_message = $_GET['error'] ?? '';

// SMTP ayarlarını yükle
$smtp_config_file = __DIR__ . '/../config/smtp.php';
$smtp_config = file_exists($smtp_config_file) ? require $smtp_config_file : [];

// SMTP ayarlarını kaydet
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enabled = isset($_POST['enabled']) ? (int)$_POST['enabled'] : 0;
    $host = $_POST['host'] ?? '';
    $port = (int)($_POST['port'] ?? 587);
    $encryption = $_POST['encryption'] ?? 'tls';
    $auth = isset($_POST['auth']) ? (int)$_POST['auth'] : 0;
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $from_email = $_POST['from_email'] ?? '';
    $from_name = $_POST['from_name'] ?? '';
    
    // Şifre boşsa eski şifreyi koru
    if (empty($password) && isset($smtp_config['password'])) {
        $password = $smtp_config['password'];
    }
    
    // SMTP config dosyasını oluştur
    $config_content = "<?php\n";
    $config_content .= "/**\n";
    $config_content .= " * SMTP Email Configuration\n";
    $config_content .= " * \n";
    $config_content .= " * Bu dosya SMTP email ayarlarını içerir.\n";
    $config_content .= " * Production ortamında bu ayarları doldurmalısınız.\n";
    $config_content .= " * \n";
    $config_content .= " * ⚠️ ÖNEMLİ: Bu dosya hassas bilgiler içerir, Git'e commit etmeyin!\n";
    $config_content .= " */\n\n";
    $config_content .= "return [\n";
    $config_content .= "    'enabled' => " . ($enabled ? 'true' : 'false') . ", // ✅ SMTP kullanımını etkinleştir/devre dışı bırak\n\n";
    $config_content .= "    // SMTP Server Ayarları\n";
    $config_content .= "    'host' => " . var_export($host, true) . ", // SMTP sunucu adresi\n";
    $config_content .= "    'port' => " . $port . ", // Genellikle 587 (TLS) veya 465 (SSL)\n";
    $config_content .= "    'encryption' => " . var_export($encryption, true) . ", // 'tls' veya 'ssl' veya '' (yok)\n";
    $config_content .= "    'auth' => " . ($auth ? 'true' : 'false') . ", // Authentication gerekiyor mu?\n\n";
    $config_content .= "    // SMTP Kullanıcı Bilgileri\n";
    $config_content .= "    'username' => " . var_export($username, true) . ", // ✅ Email adresiniz\n";
    $config_content .= "    'password' => " . var_export($password, true) . ", // ✅ Email şifreniz veya uygulama şifresi\n\n";
    $config_content .= "    // Gönderen Bilgileri\n";
    $config_content .= "    'from_email' => " . var_export($from_email, true) . ",\n";
    $config_content .= "    'from_name' => " . var_export($from_name, true) . ",\n\n";
    $config_content .= "    // Alternatif SMTP Servisleri:\n";
    $config_content .= "    // Gmail: smtp.gmail.com:587 (TLS), username: email@gmail.com, password: App Password\n";
    $config_content .= "    // Outlook/Hotmail: smtp-mail.outlook.com:587 (TLS)\n";
    $config_content .= "    // Yandex: smtp.yandex.com:465 (SSL)\n";
    $config_content .= "    // SendGrid: smtp.sendgrid.net:587 (TLS), username: apikey, password: API key\n";
    $config_content .= "    // Mailgun: smtp.mailgun.org:587 (TLS)\n";
    $config_content .= "];\n";
    
    // Dosyayı kaydet
    if (file_put_contents($smtp_config_file, $config_content) !== false) {
        // Ayarları tekrar yükle
        $smtp_config = require $smtp_config_file;
        header('Location: smtp-settings.php?success=SMTP ayarları başarıyla kaydedildi');
        exit;
    } else {
        $error_message = 'SMTP ayarları kaydedilemedi. Dosya yazma izni kontrol edin.';
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Ayarları - Dijitalsalon Admin</title>
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

        .message {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 500;
        }

        .message.success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #22c55e;
        }

        .message.error {
            background: #fee2e2;
            color: #ef4444;
            border: 1px solid #dc2626;
        }

        .settings-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .settings-card h2 {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
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

        .form-group input,
        .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9rem;
            color: #1e293b;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #6366f1;
        }

        .form-group input[type="checkbox"] {
            width: auto;
            margin-right: 0.5rem;
            cursor: pointer;
        }

        .form-group .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
        }

        .form-group .help-text {
            font-size: 0.85rem;
            color: #64748b;
            margin-top: 0.5rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
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

        .btn-test {
            background: #10b981;
            color: white;
        }

        .btn-test:hover {
            background: #059669;
        }

        .info-box {
            background: #eff6ff;
            border: 1px solid #3b82f6;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }

        .info-box h3 {
            font-size: 0.9rem;
            font-weight: 600;
            color: #1e40af;
            margin-bottom: 0.5rem;
        }

        .info-box ul {
            list-style: none;
            padding-left: 0;
        }

        .info-box li {
            font-size: 0.85rem;
            color: #1e40af;
            margin-bottom: 0.25rem;
        }

        .info-box li:before {
            content: "• ";
            font-weight: bold;
            margin-right: 0.5rem;
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
                    <i class="fas fa-envelope"></i>
                    SMTP Email Ayarları
                </h1>
            </div>

            <?php if ($success_message): ?>
                <div class="message success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="message error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="smtp-settings.php">
                <div class="settings-card">
                    <h2><i class="fas fa-cog"></i> Genel Ayarlar</h2>
                    
                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="enabled" value="1" <?php echo (!empty($smtp_config['enabled']) && $smtp_config['enabled']) ? 'checked' : ''; ?>>
                            SMTP'yi Etkinleştir
                        </label>
                        <div class="help-text">SMTP devre dışıysa, PHP mail() fonksiyonu kullanılacaktır.</div>
                    </div>

                    <div class="form-group">
                        <label>SMTP Sunucu (Host) *</label>
                        <input type="text" name="host" value="<?php echo htmlspecialchars($smtp_config['host'] ?? ''); ?>" required placeholder="smtp.yandex.com">
                        <div class="help-text">Örnek: smtp.yandex.com, smtp.gmail.com, smtp-mail.outlook.com</div>
                    </div>

                    <div class="form-group">
                        <label>Port *</label>
                        <input type="number" name="port" value="<?php echo htmlspecialchars($smtp_config['port'] ?? '587'); ?>" required placeholder="587">
                        <div class="help-text">Genellikle 587 (TLS) veya 465 (SSL)</div>
                    </div>

                    <div class="form-group">
                        <label>Şifreleme (Encryption) *</label>
                        <select name="encryption" required>
                            <option value="tls" <?php echo (($smtp_config['encryption'] ?? 'tls') === 'tls') ? 'selected' : ''; ?>>TLS (Port 587)</option>
                            <option value="ssl" <?php echo (($smtp_config['encryption'] ?? '') === 'ssl') ? 'selected' : ''; ?>>SSL (Port 465)</option>
                            <option value="" <?php echo (empty($smtp_config['encryption'])) ? 'selected' : ''; ?>>Yok</option>
                        </select>
                        <div class="help-text">Yandex için genellikle SSL (465), Gmail için TLS (587)</div>
                    </div>

                    <div class="form-group">
                        <label class="checkbox-label">
                            <input type="checkbox" name="auth" value="1" <?php echo (!empty($smtp_config['auth']) && $smtp_config['auth']) ? 'checked' : ''; ?> checked>
                            Authentication Gereksin (AUTH)
                        </label>
                    </div>
                </div>

                <div class="settings-card">
                    <h2><i class="fas fa-user"></i> Kimlik Bilgileri</h2>
                    
                    <div class="form-group">
                        <label>Kullanıcı Adı (Email) *</label>
                        <input type="email" name="username" value="<?php echo htmlspecialchars($smtp_config['username'] ?? ''); ?>" required placeholder="dijitalsalon@cagapps.app">
                        <div class="help-text">SMTP giriş için kullanılacak email adresi</div>
                    </div>

                    <div class="form-group">
                        <label>Şifre *</label>
                        <input type="password" name="password" value="" placeholder="Şifreyi değiştirmek için yeni şifre girin">
                        <div class="help-text">Boş bırakırsanız mevcut şifre korunur. Gmail için App Password kullanın.</div>
                    </div>
                </div>

                <div class="settings-card">
                    <h2><i class="fas fa-paper-plane"></i> Gönderen Bilgileri</h2>
                    
                    <div class="form-group">
                        <label>Gönderen Email *</label>
                        <input type="email" name="from_email" value="<?php echo htmlspecialchars($smtp_config['from_email'] ?? ''); ?>" required placeholder="noreply@dijitalsalon.cagapps.app">
                        <div class="help-text">Gönderilen emaillerin "From" alanında görünecek adres</div>
                    </div>

                    <div class="form-group">
                        <label>Gönderen İsim *</label>
                        <input type="text" name="from_name" value="<?php echo htmlspecialchars($smtp_config['from_name'] ?? 'Digital Salon'); ?>" required placeholder="Digital Salon">
                        <div class="help-text">Gönderilen emaillerin "From" alanında görünecek isim</div>
                    </div>
                </div>

                <div class="info-box">
                    <h3><i class="fas fa-info-circle"></i> Popüler SMTP Ayarları</h3>
                    <ul>
                        <li><strong>Yandex:</strong> smtp.yandex.com:465 (SSL), username: email@yandex.com</li>
                        <li><strong>Gmail:</strong> smtp.gmail.com:587 (TLS), username: email@gmail.com, password: App Password</li>
                        <li><strong>Outlook/Hotmail:</strong> smtp-mail.outlook.com:587 (TLS)</li>
                        <li><strong>SendGrid:</strong> smtp.sendgrid.net:587 (TLS), username: apikey, password: API key</li>
                    </ul>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Ayarları Kaydet
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        İptal
                    </a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>

