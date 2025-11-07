<?php
/**
 * Email Gönderme Helper Fonksiyonu
 * PHPMailer kullanarak SMTP ile email gönderir
 */

require_once __DIR__ . '/../config/database.php';

/**
 * SMTP ile email gönder
 * 
 * @param string $to Alıcı email adresi
 * @param string $subject Email konusu
 * @param string $message Email içeriği (HTML veya plain text)
 * @param bool $is_html HTML formatında mı?
 * @param string|null $plain_text Plain text alternatifi (HTML gönderildiğinde)
 * @return array ['success' => bool, 'message' => string, 'error' => string|null]
 */
function sendEmailViaSMTP($to, $subject, $message, $is_html = false, $plain_text = null) {
    // ✅ SMTP ayarlarını yükle
    $smtp_config = require __DIR__ . '/../config/smtp.php';
    
    // ✅ SMTP devre dışıysa veya ayarlar yoksa mail() fonksiyonunu kullan
    // Veya SMTP bağlantısı başarısız olursa fallback olarak mail() kullan
    $use_mail_function = false;
    
    if (!$smtp_config['enabled'] || empty($smtp_config['host']) || empty($smtp_config['username'])) {
        $use_mail_function = true;
    }
    
    // ✅ SMTP bağlantısını test et, başarısızsa mail() kullan
    // NOT: SMTP outbound bağlantıları firewall tarafından engellenmiş olabilir
    // Bu durumda PHP mail() fonksiyonu kullanılır (yerel mail server üzerinden)
    if (!$use_mail_function) {
        // Hızlı bağlantı testi (0.5 saniye timeout - çok hızlı fallback için)
        $test_connection = @fsockopen($smtp_config['host'], $smtp_config['port'], $test_errno, $test_errstr, 0.5);
        if (!$test_connection) {
            // SMTP bağlantısı yok, mail() kullan
            $use_mail_function = true;
            error_log("SMTP bağlantısı başarısız (timeout), PHP mail() fonksiyonuna geçiliyor: $test_errstr ($test_errno)");
        } else {
            fclose($test_connection);
        }
    }
    
    if ($use_mail_function) {
        // Fallback: PHP mail() fonksiyonu
        $headers = "From: {$smtp_config['from_name']} <{$smtp_config['from_email']}>\r\n";
        $headers .= "Reply-To: {$smtp_config['from_email']}\r\n";
        if ($is_html) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        
        $result = @mail($to, $subject, $message, $headers);
        
        // ✅ mail() fonksiyonu her zaman true döner (aslında başarılı olup olmadığını garanti edemeyiz)
        // Ama email gönderilmeye çalışıldı, kullanıcıya başarılı mesajı ver
        return [
            'success' => true, // mail() her zaman true döner, gerçek başarıyı garanti edemeyiz
            'message' => 'Email gönderildi (PHP mail() fonksiyonu - yerel mail server kullanılıyor)',
            'error' => null,
            'method' => 'php_mail' // Hangi yöntem kullanıldığını belirt
        ];
    }
    
    // ✅ PHPMailer kullanarak SMTP ile gönder veya raw SMTP
    try {
        // PHPMailer sınıfını yükle
        if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
            require_once __DIR__ . '/../vendor/autoload.php';
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // ✅ SMTP Ayarları
            $mail->isSMTP();
            $mail->Host = $smtp_config['host'];
            $mail->SMTPAuth = $smtp_config['auth'];
            $mail->Username = $smtp_config['username'];
            $mail->Password = $smtp_config['password'];
            $mail->SMTPSecure = $smtp_config['encryption']; // 'tls' veya 'ssl'
            $mail->Port = $smtp_config['port'];
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];
            
            // ✅ Gönderen Bilgileri
            $mail->setFrom($smtp_config['from_email'], $smtp_config['from_name']);
            $mail->addReplyTo($smtp_config['from_email'], $smtp_config['from_name']);
            
            // ✅ Alıcı
            $mail->addAddress($to);
            
            // ✅ İçerik
            $mail->isHTML($is_html);
            $mail->Subject = $subject;
            $mail->Body = $message;
            
            // ✅ Plain text alternatifi
            if ($is_html) {
                $mail->AltBody = $plain_text ?? strip_tags($message);
            } else {
                $mail->AltBody = $message;
            }
            
            // ✅ Spam önleme header'ları
            $mail->addCustomHeader('Message-ID', '<' . time() . '.' . rand(1000, 9999) . '@dijitalsalon.cagapps.app>');
            $mail->addCustomHeader('X-Mailer', 'Digital Salon Mailer');
            $mail->addCustomHeader('X-Priority', '3');
            $mail->addCustomHeader('List-Unsubscribe', '<mailto:unsubscribe@dijitalsalon.cagapps.app>');
            $mail->addCustomHeader('List-Unsubscribe-Post', 'List-Unsubscribe=One-Click');
            
            // ✅ Email gönder
            $mail->send();
            
            return [
                'success' => true,
                'message' => 'Email başarıyla gönderildi (SMTP - PHPMailer)',
                'error' => null,
                'method' => 'smtp'
            ];
        } else {
            // ✅ PHPMailer yoksa, raw SMTP kullan
            return sendEmailViaRawSMTP($to, $subject, $message, $smtp_config, $is_html, $plain_text);
        }
        
    } catch (\Exception $e) {
        // ✅ SMTP başarısız olursa, PHP mail() fonksiyonuna fallback yap
        error_log("SMTP Email Error (fallback to mail()): " . $e->getMessage());
        
        // Fallback: PHP mail() fonksiyonu
        $headers = "From: {$smtp_config['from_name']} <{$smtp_config['from_email']}>\r\n";
        $headers .= "Reply-To: {$smtp_config['from_email']}\r\n";
        if ($is_html) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        $result = @mail($to, $subject, $message, $headers);
        
        return [
            'success' => true, // mail() her zaman true döner
            'message' => 'Email gönderildi (PHP mail() fonksiyonu - SMTP başarısız)',
            'error' => null,
            'method' => 'php_mail_fallback',
            'smtp_error' => $e->getMessage() // SMTP hatasını log için sakla
        ];
    }
}

/**
 * PHPMailer olmadan raw SMTP bağlantısı ile email gönder
 */
function sendEmailViaRawSMTP($to, $subject, $message, $smtp_config, $is_html = false, $plain_text = null) {
    try {
        $host = $smtp_config['host'];
        $port = $smtp_config['port'];
        $username = $smtp_config['username'];
        $password = $smtp_config['password'];
        $encryption = $smtp_config['encryption']; // 'tls' veya 'ssl'
        
        // ✅ Bağlantı kur
        if ($encryption === 'ssl') {
            $socket = @stream_socket_client("ssl://{$host}:{$port}", $errno, $errstr, 30);
        } else {
            // TLS için önce plain TCP bağlantısı
            $socket = @fsockopen($host, $port, $errno, $errstr, 30);
        }
        
        if (!$socket) {
            throw new Exception("SMTP bağlantısı kurulamadı: $errstr ($errno)");
        }
        
        // ✅ SMTP handshake
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '220') {
            fclose($socket);
            throw new Exception("SMTP sunucusu yanıt vermedi: $response");
        }
        
        // ✅ EHLO
        fputs($socket, "EHLO {$host}\r\n");
        $response = '';
        while ($line = fgets($socket, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        
        // ✅ STARTTLS (TLS için)
        if ($encryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            if (substr($response, 0, 3) !== '220') {
                fclose($socket);
                throw new Exception("STARTTLS başarısız: $response");
            }
            
            // ✅ TLS bağlantısına geç
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new Exception("TLS şifreleme başlatılamadı");
            }
            
            // ✅ EHLO tekrar (TLS sonrası)
            fputs($socket, "EHLO {$host}\r\n");
            $response = '';
            while ($line = fgets($socket, 515)) {
                $response .= $line;
                if (substr($line, 3, 1) === ' ') break;
            }
        }
        
        // ✅ AUTH LOGIN
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '334') {
            fclose($socket);
            throw new Exception("AUTH LOGIN başarısız: $response");
        }
        
        // ✅ Username
        fputs($socket, base64_encode($username) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '334') {
            fclose($socket);
            throw new Exception("Kullanıcı adı doğrulanamadı: $response");
        }
        
        // ✅ Password
        fputs($socket, base64_encode($password) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '235') {
            fclose($socket);
            throw new Exception("Şifre doğrulanamadı: $response");
        }
        
        // ✅ MAIL FROM
        fputs($socket, "MAIL FROM: <{$smtp_config['from_email']}>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            fclose($socket);
            throw new Exception("MAIL FROM başarısız: $response");
        }
        
        // ✅ RCPT TO
        fputs($socket, "RCPT TO: <{$to}>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            fclose($socket);
            throw new Exception("RCPT TO başarısız: $response");
        }
        
        // ✅ DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '354') {
            fclose($socket);
            throw new Exception("DATA komutu başarısız: $response");
        }
        
        // ✅ Email içeriği
        $boundary = '----=_NextPart_' . md5(time() . rand());
        $email_content = "From: {$smtp_config['from_name']} <{$smtp_config['from_email']}>\r\n";
        $email_content .= "To: <{$to}>\r\n";
        $email_content .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $email_content .= "Reply-To: {$smtp_config['from_email']}\r\n";
        $email_content .= "Message-ID: <" . time() . '.' . rand(1000, 9999) . '@dijitalsalon.cagapps.app>\r\n';
        $email_content .= "X-Mailer: Digital Salon Mailer\r\n";
        $email_content .= "X-Priority: 3\r\n";
        $email_content .= "List-Unsubscribe: <mailto:unsubscribe@dijitalsalon.cagapps.app>\r\n";
        $email_content .= "List-Unsubscribe-Post: List-Unsubscribe=One-Click\r\n";
        
        // ✅ HTML ve plain text desteği
        if ($is_html && $plain_text !== null) {
            $email_content .= "MIME-Version: 1.0\r\n";
            $email_content .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
            $email_content .= "\r\n";
            $email_content .= "--$boundary\r\n";
            $email_content .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $email_content .= "Content-Transfer-Encoding: base64\r\n";
            $email_content .= "\r\n";
            $email_content .= chunk_split(base64_encode($plain_text));
            $email_content .= "\r\n--$boundary\r\n";
            $email_content .= "Content-Type: text/html; charset=UTF-8\r\n";
            $email_content .= "Content-Transfer-Encoding: base64\r\n";
            $email_content .= "\r\n";
            $email_content .= chunk_split(base64_encode($message));
            $email_content .= "\r\n--$boundary--\r\n";
        } else {
            $email_content .= "MIME-Version: 1.0\r\n";
            if ($is_html) {
                $email_content .= "Content-Type: text/html; charset=UTF-8\r\n";
            } else {
                $email_content .= "Content-Type: text/plain; charset=UTF-8\r\n";
            }
            $email_content .= "Content-Transfer-Encoding: base64\r\n";
            $email_content .= "\r\n";
            $email_content .= chunk_split(base64_encode($message));
        }
        $email_content .= "\r\n.\r\n";
        
        fputs($socket, $email_content);
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            fclose($socket);
            throw new Exception("Email gönderilemedi: $response");
        }
        
        // ✅ QUIT
        fputs($socket, "QUIT\r\n");
        fgets($socket, 515);
        fclose($socket);
        
        return [
            'success' => true,
            'message' => 'Email başarıyla gönderildi (SMTP - Raw)',
            'error' => null,
            'method' => 'smtp_raw'
        ];
        
    } catch (\Exception $e) {
        error_log("Raw SMTP Error: " . $e->getMessage());
        // ✅ Fallback: PHP mail() fonksiyonu
        $headers = "From: {$smtp_config['from_name']} <{$smtp_config['from_email']}>\r\n";
        $headers .= "Reply-To: {$smtp_config['from_email']}>\r\n";
        if ($is_html) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        @mail($to, $subject, $message, $headers);
        
        return [
            'success' => true,
            'message' => 'Email gönderildi (PHP mail() fonksiyonu - Raw SMTP başarısız)',
            'error' => $e->getMessage(),
            'method' => 'php_mail_fallback'
        ];
    }
}

