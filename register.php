<?php
/**
 * Register Page
 * Digital Salon - Kayıt sayfası
 */

require_once 'config/database.php';
require_once 'includes/security.php';

// Eğer zaten giriş yapmışsa dashboard'a yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: user_dashboard.php');
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ad = sanitizeInput($_POST['ad'] ?? '');
    $soyad = sanitizeInput($_POST['soyad'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $telefon = sanitizeInput($_POST['telefon'] ?? '');
    $sifre = $_POST['sifre'] ?? '';
    $sifre_tekrar = $_POST['sifre_tekrar'] ?? '';
    
    // Validasyon
    if (empty($ad) || empty($soyad) || empty($email) || empty($telefon) || empty($sifre)) {
        $error_message = 'Tüm alanlar gereklidir.';
    } elseif (!InputValidator::validateEmail($email)) {
        $error_message = 'Geçerli bir email adresi girin.';
    } elseif (!InputValidator::validatePhone($telefon)) {
        $error_message = 'Geçerli bir telefon numarası girin.';
    } elseif (!InputValidator::validatePassword($sifre)) {
        $error_message = 'Şifre en az 8 karakter olmalı ve büyük harf, küçük harf, rakam içermelidir.';
    } elseif ($sifre !== $sifre_tekrar) {
        $error_message = 'Şifreler eşleşmiyor.';
    } else {
        try {
            // Email kontrolü
            $stmt = $pdo->prepare("SELECT id FROM kullanicilar WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetch()) {
                $error_message = 'Bu email adresi zaten kullanılıyor.';
            } else {
                // Kullanıcı oluştur
                $hashedPassword = PasswordSecurity::hashPassword($sifre);
                $stmt = $pdo->prepare("
                    INSERT INTO kullanicilar (ad, soyad, email, sifre, telefon, rol, durum, olusturma_tarihi)
                    VALUES (?, ?, ?, ?, ?, 'normal_kullanici', 'aktif', NOW())
                ");
                $stmt->execute([$ad, $soyad, $email, $hashedPassword, $telefon]);
                
                $success_message = 'Kayıt başarılı! Giriş yapabilirsiniz.';
                header('Location: login.php?success=1');
                exit();
            }
        } catch (Exception $e) {
            $error_message = 'Kayıt sırasında bir hata oluştu.';
            error_log("Registration error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kayıt Ol - Digital Salon</title>
    
    <!-- CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="assets/css/modern-ui.css" rel="stylesheet">
    
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
            padding: 2rem 0;
        }
        
        .register-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 500px;
            color: white;
        }
        
        .register-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .register-header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .register-header p {
            opacity: 0.8;
            font-size: 0.9rem;
        }
        
        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 10px;
            color: white;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
        }
        
        .form-control::placeholder {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .form-control:focus {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 0 0 0.2rem rgba(255, 255, 255, 0.25);
            color: white;
        }
        
        .btn-register {
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            padding: 0.75rem 2rem;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(118, 75, 162, 0.4);
            color: white;
        }
        
        .alert {
            border-radius: 10px;
            border: none;
            margin-bottom: 1rem;
        }
        
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #ff6b6b;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            color: #51cf66;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        
        .register-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .register-footer a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .register-footer a:hover {
            color: white;
        }
        
        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.8rem;
        }
        
        .strength-weak { color: #ff6b6b; }
        .strength-medium { color: #ffd43b; }
        .strength-strong { color: #51cf66; }
        
        .row {
            margin: 0 -0.5rem;
        }
        
        .col-md-6 {
            padding: 0 0.5rem;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h1><i class="fas fa-user-plus me-2"></i>Kayıt Ol</h1>
            <p>Digital Salon'a katılın</p>
        </div>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="register.php">
            <div class="row">
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="ad" class="form-label">
                            <i class="fas fa-user me-2"></i>Ad
                        </label>
                        <input type="text" class="form-control" id="ad" name="ad" 
                               placeholder="Adınız" required value="<?php echo htmlspecialchars($ad ?? ''); ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="mb-3">
                        <label for="soyad" class="form-label">
                            <i class="fas fa-user me-2"></i>Soyad
                        </label>
                        <input type="text" class="form-control" id="soyad" name="soyad" 
                               placeholder="Soyadınız" required value="<?php echo htmlspecialchars($soyad ?? ''); ?>">
                    </div>
                </div>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope me-2"></i>Email Adresi
                </label>
                <input type="email" class="form-control" id="email" name="email" 
                       placeholder="ornek@email.com" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="telefon" class="form-label">
                    <i class="fas fa-phone me-2"></i>Telefon Numarası
                </label>
                <input type="tel" class="form-control" id="telefon" name="telefon" 
                       placeholder="0555 123 45 67" required value="<?php echo htmlspecialchars($telefon ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="sifre" class="form-label">
                    <i class="fas fa-lock me-2"></i>Şifre
                </label>
                <input type="password" class="form-control" id="sifre" name="sifre" 
                       placeholder="Şifrenizi girin" required>
                <div class="password-strength" id="passwordStrength"></div>
            </div>
            
            <div class="mb-3">
                <label for="sifre_tekrar" class="form-label">
                    <i class="fas fa-lock me-2"></i>Şifre Tekrar
                </label>
                <input type="password" class="form-control" id="sifre_tekrar" name="sifre_tekrar" 
                       placeholder="Şifrenizi tekrar girin" required>
            </div>
            
            <button type="submit" class="btn btn-register">
                <i class="fas fa-user-plus me-2"></i>Kayıt Ol
            </button>
        </form>
        
        <div class="register-footer">
            <p>Zaten hesabınız var mı? <a href="login.php">Giriş Yap</a></p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const passwordInput = document.getElementById('sifre');
            const passwordRepeatInput = document.getElementById('sifre_tekrar');
            const passwordStrength = document.getElementById('passwordStrength');
            
            // Password strength checker
            passwordInput.addEventListener('input', function() {
                const password = this.value;
                let strength = 0;
                let message = '';
                
                if (password.length >= 8) strength++;
                if (/[a-z]/.test(password)) strength++;
                if (/[A-Z]/.test(password)) strength++;
                if (/[0-9]/.test(password)) strength++;
                if (/[^A-Za-z0-9]/.test(password)) strength++;
                
                if (strength < 2) {
                    message = '<span class="strength-weak">Zayıf şifre</span>';
                } else if (strength < 4) {
                    message = '<span class="strength-medium">Orta şifre</span>';
                } else {
                    message = '<span class="strength-strong">Güçlü şifre</span>';
                }
                
                passwordStrength.innerHTML = message;
            });
            
            // Password match checker
            passwordRepeatInput.addEventListener('input', function() {
                if (this.value && this.value !== passwordInput.value) {
                    this.setCustomValidity('Şifreler eşleşmiyor');
                } else {
                    this.setCustomValidity('');
                }
            });
            
            // Form validation
            form.addEventListener('submit', function(e) {
                const requiredFields = ['ad', 'soyad', 'email', 'telefon', 'sifre', 'sifre_tekrar'];
                let isValid = true;
                
                requiredFields.forEach(field => {
                    const input = document.getElementById(field);
                    if (!input.value.trim()) {
                        isValid = false;
                        input.classList.add('is-invalid');
                    } else {
                        input.classList.remove('is-invalid');
                    }
                });
                
                // Email validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(document.getElementById('email').value)) {
                    isValid = false;
                    document.getElementById('email').classList.add('is-invalid');
                }
                
                // Phone validation
                const phoneRegex = /^[0-9\s\-\+\(\)]{10,}$/;
                if (!phoneRegex.test(document.getElementById('telefon').value)) {
                    isValid = false;
                    document.getElementById('telefon').classList.add('is-invalid');
                }
                
                if (!isValid) {
                    e.preventDefault();
                    alert('Lütfen tüm alanları doğru şekilde doldurun.');
                    return false;
                }
            });
        });
    </script>
</body>
</html>
