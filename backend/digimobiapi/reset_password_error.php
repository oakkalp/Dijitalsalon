<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Şifre Sıfırlama Hatası - Digital Salon</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
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
            text-align: center;
        }
        
        .error-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        h2 {
            font-size: 24px;
            font-weight: 700;
            color: #dc2626;
            margin-bottom: 15px;
        }
        
        p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 24px;
            background: #E1306C;
            color: white;
            text-decoration: none;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .btn:hover {
            background: #c91d5d;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(225, 48, 108, 0.3);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-icon">❌</div>
        <h2>Geçersiz Bağlantı</h2>
        <p>Bu şifre sıfırlama bağlantısı geçersiz veya süresi dolmuş. Lütfen yeni bir şifre sıfırlama isteği yapın.</p>
        <a href="https://dijitalsalon.cagapps.app" class="btn">Ana Sayfaya Dön</a>
    </div>
</body>
</html>

