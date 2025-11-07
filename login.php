<?php
/**
 * Login Page
 * Digital Salon - Giriş sayfası
 */

require_once 'config/database.php';
require_once 'includes/security.php';

// Eğer zaten giriş yapmışsa dashboard'a yönlendir
if (isset($_SESSION['user_id'])) {
    $user_role = $_SESSION['user_role'];
    switch ($user_role) {
        case 'super_admin':
            header('Location: super_admin_dashboard.php');
            break;
        case 'moderator':
            header('Location: moderator_dashboard.php');
            break;
        default:
            header('Location: user_dashboard.php');
            break;
    }
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    if (empty($email) || empty($password)) {
        $error_message = 'Email ve şifre gereklidir.';
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM kullanicilar WHERE email = ? AND durum = 'aktif'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user && PasswordSecurity::verifyPassword($password, $user['sifre'])) {
                // Giriş başarılı
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = $user['rol'];
                $_SESSION['user_name'] = $user['ad'] . ' ' . $user['soyad'];
                
                // Son giriş tarihini güncelle
                $stmt = $pdo->prepare("UPDATE kullanicilar SET son_giris = NOW() WHERE id = ?");
                $stmt->execute([$user['id']]);
                
                // Rolüne göre yönlendir
                switch ($user['rol']) {
                    case 'super_admin':
                        header('Location: super_admin_dashboard.php');
                        break;
                    case 'moderator':
                        header('Location: moderator_dashboard.php');
                        break;
                    default:
                        header('Location: user_dashboard.php');
                        break;
                }
                exit();
            } else {
                $error_message = 'Email veya şifre hatalı.';
            }
        } catch (Exception $e) {
            $error_message = 'Giriş sırasında bir hata oluştu.';
            error_log("Login error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Giriş Yap - Digital Salon</title>
    
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
        }
        
        .login-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            width: 100%;
            max-width: 400px;
            color: white;
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            font-family: 'Poppins', sans-serif;
            font-size: 2rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .login-header p {
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
        
        .btn-login {
            background: linear-gradient(45deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 10px;
            color: white;
            font-weight: 600;
            padding: 0.75rem 2rem;
            width: 100%;
            transition: all 0.3s ease;
        }
        
        .btn-login:hover {
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
        
        .login-footer {
            text-align: center;
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .login-footer a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }
        
        .login-footer a:hover {
            color: white;
        }
        
        .demo-accounts {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.8rem;
        }
        
        .demo-accounts h6 {
            margin-bottom: 0.5rem;
            font-weight: 600;
        }
        
        .demo-account {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.25rem;
            opacity: 0.8;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-heart me-2"></i>Digital Salon</h1>
            <p>Dijital Düğün Albümü Sistemi</p>
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
        
        <form method="POST" action="login.php">
            <div class="mb-3">
                <label for="email" class="form-label">
                    <i class="fas fa-envelope me-2"></i>Email Adresi
                </label>
                <input type="email" class="form-control" id="email" name="email" 
                       placeholder="ornek@email.com" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">
                    <i class="fas fa-lock me-2"></i>Şifre
                </label>
                <input type="password" class="form-control" id="password" name="password" 
                       placeholder="Şifrenizi girin" required>
            </div>
            
            <button type="submit" class="btn btn-login">
                <i class="fas fa-sign-in-alt me-2"></i>Giriş Yap
            </button>
        </form>
        
        <div class="login-footer">
            <p>Hesabınız yok mu? <a href="register.php">Kayıt Ol</a></p>
            
            <div class="demo-accounts">
                <h6><i class="fas fa-info-circle me-2"></i>Demo Hesaplar</h6>
                <div class="demo-account">
                    <span>Super Admin:</span>
                    <span>admin@digitalsalon.com / admin123</span>
                </div>
                <div class="demo-account">
                    <span>Moderator:</span>
                    <span>moderator@digitalsalon.com / mod123</span>
                </div>
                <div class="demo-account">
                    <span>Kullanıcı:</span>
                    <span>user@digitalsalon.com / user123</span>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            const emailInput = document.getElementById('email');
            const passwordInput = document.getElementById('password');
            
            form.addEventListener('submit', function(e) {
                if (!emailInput.value || !passwordInput.value) {
                    e.preventDefault();
                    alert('Lütfen tüm alanları doldurun.');
                    return false;
                }
                
                // Email format validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(emailInput.value)) {
                    e.preventDefault();
                    alert('Lütfen geçerli bir email adresi girin.');
                    return false;
                }
            });
            
            // Demo account quick fill
            const demoAccounts = document.querySelectorAll('.demo-account');
            demoAccounts.forEach(account => {
                account.addEventListener('click', function() {
                    const email = this.querySelector('span:last-child').textContent.split(' / ')[0];
                    const password = this.querySelector('span:last-child').textContent.split(' / ')[1];
                    
                    emailInput.value = email;
                    passwordInput.value = password;
                });
            });
        });
    </script>
</body>
</html>
