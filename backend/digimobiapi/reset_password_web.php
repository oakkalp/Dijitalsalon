<?php
/**
 * Web için Şifre Sıfırlama Sayfası
 * Email linkinden geldiğinde bu sayfa açılır
 * Kullanıcı doğrulama kodunu girer, sonra şifresini sıfırlar
 */

require_once __DIR__ . '/bootstrap.php';

$token = $_GET['token'] ?? '';

if (empty($token)) {
    http_response_code(400);
    include __DIR__ . '/reset_password_error.php';
    exit;
}

// ✅ Token'ı kontrol et ve kullanıcı bilgilerini al
try {
    $stmt = $pdo->prepare("
        SELECT pr.user_id, pr.expires_at, pr.verification_code, u.email
        FROM password_resets pr
        INNER JOIN kullanicilar u ON u.id = pr.user_id
        WHERE pr.token = ? AND pr.expires_at > NOW()
        ORDER BY pr.created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$reset) {
        http_response_code(400);
        include __DIR__ . '/reset_password_error.php';
        exit;
    }
    
    $user_email = $reset['email'];
    $verification_code = $reset['verification_code'] ?? null;
    
    // ✅ verification_code yoksa token'ın son 6 hanesini kullan (fallback)
    if (empty($verification_code)) {
        $verification_code = substr($token, -6);
    }
    
} catch (Exception $e) {
    error_log("Reset password web error: " . $e->getMessage());
    http_response_code(500);
    include __DIR__ . '/reset_password_error.php';
    exit;
}

// ✅ POST ise doğrulama kodu kontrolü
$error_message = '';
$code_verified = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'verify_code') {
        $entered_code = $_POST['code'] ?? '';
        
        if (empty($entered_code) || strlen($entered_code) !== 6) {
            $error_message = 'Lütfen 6 haneli doğrulama kodunu girin.';
        } else {
            // ✅ Kodu kontrol et
            $hasVerificationCodeColumn = false;
            try {
                $checkColumn = $pdo->query("SHOW COLUMNS FROM password_resets LIKE 'verification_code'");
                $hasVerificationCodeColumn = $checkColumn->rowCount() > 0;
            } catch (PDOException $e) {
                $hasVerificationCodeColumn = false;
            }
            
            if ($hasVerificationCodeColumn && !empty($verification_code)) {
                // verification_code kolonu varsa ve kod varsa
                $code_valid = ($entered_code === $verification_code);
            } else {
                // Fallback: token'ın son 6 hanesini kontrol et
                $code_valid = ($entered_code === substr($token, -6));
            }
            
            if ($code_valid) {
                $code_verified = true;
            } else {
                $error_message = 'Doğrulama kodu hatalı. Lütfen tekrar deneyin.';
            }
        }
    } elseif ($_POST['action'] === 'reset_password') {
        // ✅ Şifre sıfırlama
        $new_password = $_POST['password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        if (empty($new_password) || strlen($new_password) < 6) {
            $error_message = 'Şifre en az 6 karakter olmalıdır.';
        } elseif ($new_password !== $confirm_password) {
            $error_message = 'Şifreler eşleşmiyor.';
        } else {
            try {
                // ✅ Şifreyi güncelle
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT);
                $stmt = $pdo->prepare("UPDATE kullanicilar SET sifre = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $reset['user_id']]);
                
                // ✅ Token'ı sil
                $stmt = $pdo->prepare("DELETE FROM password_resets WHERE token = ?");
                $stmt->execute([$token]);
                
                // ✅ Başarı sayfası
                include __DIR__ . '/reset_password_success.php';
                exit;
            } catch (Exception $e) {
                error_log("Reset password error: " . $e->getMessage());
                $error_message = 'Bir hata oluştu. Lütfen tekrar deneyin.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırlama - Digital Salon</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 450px;
            width: 100%;
            padding: 40px;
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo h1 {
            color: #E1306C;
            font-size: 28px;
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .logo p {
            color: #64748b;
            font-size: 14px;
        }
        
        .step-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
            position: relative;
        }
        
        .step-indicator::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: #e2e8f0;
            z-index: 0;
        }
        
        .step {
            position: relative;
            z-index: 1;
            background: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            border: 2px solid #e2e8f0;
            color: #64748b;
        }
        
        .step.active {
            background: #E1306C;
            border-color: #E1306C;
            color: white;
        }
        
        .step.completed {
            background: #22c55e;
            border-color: #22c55e;
            color: white;
        }
        
        .step-label {
            position: absolute;
            top: 45px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 11px;
            color: #64748b;
            white-space: nowrap;
        }
        
        .step.active .step-label {
            color: #E1306C;
            font-weight: 600;
        }
        
        h2 {
            font-size: 24px;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .subtitle {
            color: #64748b;
            font-size: 14px;
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .code-inputs {
            display: flex;
            gap: 10px;
            justify-content: center;
            margin-bottom: 20px;
        }
        
        .code-input {
            width: 50px;
            height: 60px;
            text-align: center;
            font-size: 24px;
            font-weight: 700;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            transition: all 0.3s;
        }
        
        .code-input:focus {
            outline: none;
            border-color: #E1306C;
            box-shadow: 0 0 0 3px rgba(225, 48, 108, 0.1);
        }
        
        .form-group input[type="password"],
        .form-group input[type="text"] {
            width: 100%;
            padding: 14px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #E1306C;
            box-shadow: 0 0 0 3px rgba(225, 48, 108, 0.1);
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #64748b;
            font-size: 20px;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: #E1306C;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn:hover {
            background: #c91d5d;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(225, 48, 108, 0.3);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn:disabled {
            background: #cbd5e1;
            cursor: not-allowed;
            transform: none;
        }
        
        .error-message {
            background: #fee2e2;
            color: #dc2626;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .success-message {
            background: #dcfce7;
            color: #16a34a;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-box {
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #1e40af;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #64748b;
            text-decoration: none;
            font-size: 14px;
        }
        
        .back-link a:hover {
            color: #E1306C;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="logo">
            <h1>🔐 Digital Salon</h1>
            <p>Şifre Sıfırlama</p>
        </div>
        
        <div class="step-indicator">
            <div class="step <?php echo $code_verified ? 'completed' : 'active'; ?>">
                1
                <span class="step-label">Doğrulama</span>
            </div>
            <div class="step <?php echo $code_verified ? 'active' : ''; ?>">
                2
                <span class="step-label">Yeni Şifre</span>
            </div>
        </div>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                <span>⚠️</span>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>
        
        <?php if (!$code_verified): ?>
            <!-- ✅ Adım 1: Doğrulama Kodu -->
            <h2>Doğrulama Kodu</h2>
            <p class="subtitle">E-posta adresinize gönderilen 6 haneli doğrulama kodunu girin</p>
            
            <div class="info-box">
                <strong>📧 Email:</strong> <?php echo htmlspecialchars($user_email); ?><br>
                <strong>💡 İpucu:</strong> E-postanızdaki doğrulama kodunu kontrol edin.
            </div>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="verify_code">
                <div class="code-inputs">
                    <input type="text" name="code" class="code-input" maxlength="6" pattern="[0-9]{6}" 
                           placeholder="000000" required autofocus id="codeInput">
                </div>
                <button type="submit" class="btn">Kodu Doğrula</button>
            </form>
            
            <script>
                // ✅ Otomatik kod girişi (kopyala-yapıştır için)
                document.getElementById('codeInput').addEventListener('input', function(e) {
                    let value = e.target.value.replace(/\D/g, '').substring(0, 6);
                    e.target.value = value;
                    
                    // 6 karakter girildiyse otomatik submit
                    if (value.length === 6) {
                        e.target.form.submit();
                    }
                });
            </script>
        <?php else: ?>
            <!-- ✅ Adım 2: Yeni Şifre -->
            <h2>Yeni Şifre</h2>
            <p class="subtitle">Yeni şifrenizi belirleyin</p>
            
            <form method="POST" action="">
                <input type="hidden" name="action" value="reset_password">
                
                <div class="form-group password-toggle">
                    <label>Yeni Şifre</label>
                    <input type="password" name="password" required minlength="6" 
                           placeholder="En az 6 karakter" id="passwordInput">
                    <span class="password-toggle-icon" onclick="togglePassword('passwordInput')">👁️</span>
                </div>
                
                <div class="form-group password-toggle">
                    <label>Şifre Tekrar</label>
                    <input type="password" name="confirm_password" required minlength="6" 
                           placeholder="Şifrenizi tekrar girin" id="confirmPasswordInput">
                    <span class="password-toggle-icon" onclick="togglePassword('confirmPasswordInput')">👁️</span>
                </div>
                
                <button type="submit" class="btn">Şifreyi Güncelle</button>
            </form>
            
            <script>
                function togglePassword(inputId) {
                    const input = document.getElementById(inputId);
                    input.type = input.type === 'password' ? 'text' : 'password';
                }
            </script>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="https://dijitalsalon.cagapps.app">← Ana Sayfaya Dön</a>
        </div>
    </div>
</body>
</html>

