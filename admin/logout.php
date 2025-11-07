<?php
session_start();

// Admin giriş kontrolü
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: index.php');
    exit;
}

// Çıkış işlemi
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dijitalsalon Admin Panel - Çıkış</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .logout-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            padding: 3rem;
            text-align: center;
            max-width: 400px;
            width: 100%;
        }

        .logout-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            animation: pulse 2s infinite;
        }

        .logout-icon i {
            font-size: 2rem;
            color: white;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
            100% {
                transform: scale(1);
            }
        }

        .logout-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #1e293b;
            margin-bottom: 1rem;
        }

        .logout-message {
            color: #64748b;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.875rem 2rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .btn-primary {
            background: linear-gradient(135deg, #6366f1, #8b5cf6);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(99, 102, 241, 0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #64748b;
            border: 1px solid #e2e8f0;
            margin-left: 1rem;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
        }
    </style>
</head>
<body>
    <div class="logout-container">
        <div class="logout-icon">
            <i class="fas fa-sign-out-alt"></i>
        </div>
        <h1 class="logout-title">Çıkış Yapılıyor</h1>
        <p class="logout-message">
            Admin panelinden çıkış yapmak istediğinizden emin misiniz?<br>
            Güvenliğiniz için oturumunuz sonlandırılacak.
        </p>
        <div>
            <a href="?action=logout" class="btn btn-primary">
                <i class="fas fa-check"></i>
                Evet, Çıkış Yap
            </a>
            <a href="dashboard.php" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                İptal
            </a>
        </div>
    </div>

    <script>
        // Auto logout after 5 seconds if user doesn't interact
        let timeout;
        
        function resetTimeout() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                window.location.href = '?action=logout';
            }, 5000);
        }

        // Reset timeout on any user interaction
        document.addEventListener('click', resetTimeout);
        document.addEventListener('keypress', resetTimeout);
        document.addEventListener('mousemove', resetTimeout);

        // Start the timeout
        resetTimeout();
    </script>
</body>
</html>
