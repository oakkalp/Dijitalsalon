<?php
require_once 'bootstrap.php';

class NotificationService {
    private $pdo;
    private $serviceAccountPath;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->serviceAccountPath = __DIR__ . '/../config/dijital-salon-3d72ad092cab.json';
    }

    /**
     * ✅ Firebase Cloud Messaging üzerinden bildirim gönder
     */
    public function sendFCMNotification($userId, $title, $message, $data = []) {
        try {
            error_log("=== FCM SEND START === User ID: $userId");
            
            // FCM token'ı al
            $stmt = $this->pdo->prepare("
                SELECT token FROM fcm_tokens 
                WHERE user_id = ? 
                ORDER BY updated_at DESC 
                LIMIT 1
            ");
            $stmt->execute([$userId]);
            $tokenRow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$tokenRow) {
                error_log("❌ No FCM token found for user: $userId");
                return false;
            }

            $fcmToken = $tokenRow['token'];
            error_log("✅ FCM Token: " . substr($fcmToken, 0, 50) . "...");

            // ✅ Access token al
            error_log("🔑 Getting access token...");
            $accessToken = $this->getAccessToken();
            if (!$accessToken) {
                error_log("❌ Failed to get FCM access token");
                return false;
            }
            error_log("✅ Access token: " . substr($accessToken, 0, 30) . "...");

            // ✅ FCM API isteği
            $url = 'https://fcm.googleapis.com/v1/projects/dijital-salon/messages:send';
            
            // ✅ FCM requires all data values to be strings
            $stringData = [];
            foreach ($data as $key => $value) {
                $stringData[$key] = (string)$value;
            }
            
            $payload = [
                'message' => [
                    'token' => $fcmToken,
                    'notification' => [
                        'title' => $title,
                        'body' => $message,
                    ],
                    'data' => $stringData,
                    'android' => [
                        'priority' => 'high'
                    ],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10'
                        ]
                    ]
                ]
            ];

            error_log("📤 Sending to: $url");

            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $accessToken,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            error_log("📥 Response HTTP: $httpCode");
            error_log("📥 Response Body: $response");

            if ($httpCode == 200) {
                error_log("✅ FCM notification sent successfully to user: $userId");
                return true;
            } else {
                error_log("❌ FCM notification failed. HTTP: $httpCode, Response: $response");
                return false;
            }

        } catch (Exception $e) {
            error_log("❌ FCM Exception: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Service account'tan Access Token al
     */
    private function getAccessToken() {
        try {
            if (!file_exists($this->serviceAccountPath)) {
                error_log("Service account file not found: " . $this->serviceAccountPath);
                return null;
            }

            $serviceAccount = json_decode(file_get_contents($this->serviceAccountPath), true);
            
            $now = time();
            
            // ✅ URL-safe base64 encoding
            $jwtHeader = $this->base64UrlEncode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            
            $jwtClaim = $this->base64UrlEncode(json_encode([
                'iss' => $serviceAccount['client_email'],
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
                'aud' => 'https://oauth2.googleapis.com/token',
                'exp' => $now + 3600,
                'iat' => $now
            ]));

            $signatureInput = $jwtHeader . '.' . $jwtClaim;
            openssl_sign($signatureInput, $signature, $serviceAccount['private_key'], 'SHA256');
            $jwtSignature = $this->base64UrlEncode($signature);

            $jwt = $signatureInput . '.' . $jwtSignature;

            // Token isteği
            $ch = curl_init('https://oauth2.googleapis.com/token');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt
            ]));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode != 200) {
                error_log("OAuth Token Error: HTTP $httpCode - $response");
                return null;
            }

            $tokenData = json_decode($response, true);
            return $tokenData['access_token'] ?? null;

        } catch (Exception $e) {
            error_log("Access Token Error: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * ✅ URL-safe base64 encoding
     */
    private function base64UrlEncode($data) {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    /**
     * ✅ Database'e bildirim kaydet
     */
    public function saveNotification($userId, $senderId, $type, $title, $message, $data = []) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO notifications (user_id, sender_id, type, title, message, data, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $senderId,
                $type,
                $title,
                $message,
                json_encode($data)
            ]);

            return $this->pdo->lastInsertId();

        } catch (PDOException $e) {
            error_log("Save Notification Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Beğeni bildirimi gönder
     */
    public function sendLikeNotification($mediaId, $likerId) {
        try {
            // Medya sahibini bul
            $stmt = $this->pdo->prepare("
                SELECT m.kullanici_id, m.tur, k.ad, k.soyad
                FROM medyalar m
                JOIN kullanicilar k ON k.id = ?
                WHERE m.id = ?
            ");
            $stmt->execute([$likerId, $mediaId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data || $data['kullanici_id'] == $likerId) {
                return false; // Kendi medyasını beğenmişse bildirim gönderme
            }

            $mediaOwnerId = $data['kullanici_id'];
            $likerName = trim($data['ad'] . ' ' . $data['soyad']);
            $mediaType = $data['tur'] == 'hikaye' ? 'hikayeni' : 'medyanı';

            $title = 'Yeni Beğeni!';
            $message = "$likerName $mediaType beğendi.";

            // Database'e kaydet
            $this->saveNotification(
                $mediaOwnerId,
                $likerId,
                'like',
                $title,
                $message,
                ['media_id' => $mediaId, 'type' => $data['tur']]
            );

            // FCM gönder
            $this->sendFCMNotification($mediaOwnerId, $title, $message, [
                'type' => 'like',
                'media_id' => (string)$mediaId
            ]);

            return true;

        } catch (Exception $e) {
            error_log("Send Like Notification Error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * ✅ Yorum bildirimi gönder
     */
    public function sendCommentNotification($mediaId, $commenterId) {
        try {
            // Medya sahibini bul
            $stmt = $this->pdo->prepare("
                SELECT m.kullanici_id, k.ad, k.soyad
                FROM medyalar m
                JOIN kullanicilar k ON k.id = ?
                WHERE m.id = ?
            ");
            $stmt->execute([$commenterId, $mediaId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data || $data['kullanici_id'] == $commenterId) {
                return false; // Kendi medyasına yorum yapmışsa bildirim gönderme
            }

            $mediaOwnerId = $data['kullanici_id'];
            $commenterName = trim($data['ad'] . ' ' . $data['soyad']);

            $title = 'Yeni Yorum!';
            $message = "$commenterName medyanıza yorum yaptı.";

            // Database'e kaydet
            $this->saveNotification(
                $mediaOwnerId,
                $commenterId,
                'comment',
                $title,
                $message,
                ['media_id' => $mediaId]
            );

            // FCM gönder
            $this->sendFCMNotification($mediaOwnerId, $title, $message, [
                'type' => 'comment',
                'media_id' => (string)$mediaId
            ]);

            return true;

        } catch (Exception $e) {
            error_log("Send Comment Notification Error: " . $e->getMessage());
            return false;
        }
    }
}

/**
 * ✅ Helper function: Toplu bildirim gönder
 * @param array $user_ids - Kullanıcı ID'leri
 * @param string $title - Bildirim başlığı
 * @param string $message - Bildirim mesajı
 * @param array $data - Ekstra data
 * @return array - ['success_count' => int, 'failures' => array]
 */
function sendNotification($user_ids, $title, $message, $data = []) {
    if (empty($user_ids)) {
        return ['success_count' => 0, 'failures' => []];
    }
    
    $pdo = get_pdo();
    $notificationService = new NotificationService($pdo);
    
    $success_count = 0;
    $failures = [];
    
    foreach ($user_ids as $user_id) {
        try {
            // FCM bildirimi gönder
            $sent = $notificationService->sendFCMNotification($user_id, $title, $message, $data);
            
            if ($sent) {
                $success_count++;
                
                // Veritabanına kaydet
                $stmt = $pdo->prepare("
                    INSERT INTO notifications (
                        user_id, 
                        sender_id, 
                        event_id,
                        type, 
                        title,
                        message, 
                        data,
                        created_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $user_id,
                    $data['sender_id'] ?? null,
                    $data['event_id'] ?? null,
                    $data['type'] ?? 'custom',
                    $title,
                    $message,
                    json_encode($data)
                ]);
            } else {
                $failures[] = $user_id;
            }
        } catch (Exception $e) {
            error_log("Failed to send notification to user $user_id: " . $e->getMessage());
            $failures[] = $user_id;
        }
    }
    
    return [
        'success_count' => $success_count,
        'failures' => $failures
    ];
}

