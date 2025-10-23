<?php
session_start();
header('Content-Type: application/json');

try {
    // Database connection
    $pdo = new PDO('mysql:host=localhost;dbname=digitalsalon_db;charset=utf8mb4', 'root', '');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $user_id = (int)$_GET['user_id'];
    $event_id = (int)$_GET['event_id'];
    
    // Get user details
    $stmt = $pdo->prepare("
        SELECT 
            k.ad, k.soyad, k.email, k.profil_fotografi,
            dk.rol as participant_role,
            dk.medya_silebilir,
            dk.yorum_silebilir,
            dk.kullanici_engelleyebilir,
            dk.hikaye_paylasabilir
        FROM kullanicilar k
        LEFT JOIN dugun_katilimcilar dk ON k.id = dk.kullanici_id AND dk.dugun_id = ?
        WHERE k.id = ?
    ");
    $stmt->execute([$event_id, $user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['success' => false, 'error' => 'Kullanıcı bulunamadı.']);
        exit;
    }
    
    // Determine current participant status
    $participant_status = 'not_participant';
    if ($user['participant_role']) {
        $participant_status = $user['participant_role'] === 'yetkili_kullanici' ? 'authorized_participant' : 'normal_participant';
    }
    
    // Generate HTML form
    $html = '
    <form id="permissionsForm" method="POST">
        <input type="hidden" name="update_permissions" value="1">
        <input type="hidden" name="user_id" value="' . $user_id . '">
        <input type="hidden" name="event_id" value="' . $event_id . '">
        
        <div class="row mb-4">
            <div class="col-md-3 text-center">
                <img src="' . ($user['profil_fotografi'] ?: 'assets/images/default_profile.svg') . '" 
                     alt="' . htmlspecialchars($user['ad'] . ' ' . $user['soyad']) . '" 
                     class="rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">
                <h6>' . htmlspecialchars($user['ad'] . ' ' . $user['soyad']) . '</h6>
                <small class="text-muted">' . htmlspecialchars($user['email']) . '</small>
            </div>
            <div class="col-md-9">
                <div class="row">
                    <div class="col-md-6">
                        <h6>Katılımcı Durumu</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="participant_status" id="not_participant" value="not_participant" ' . ($participant_status === 'not_participant' ? 'checked' : '') . '>
                            <label class="form-check-label" for="not_participant">
                                <i class="fas fa-user-times me-2"></i>Katılımcı Değil
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="participant_status" id="normal_participant" value="normal_participant" ' . ($participant_status === 'normal_participant' ? 'checked' : '') . '>
                            <label class="form-check-label" for="normal_participant">
                                <i class="fas fa-user me-2"></i>Normal Katılımcı
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="participant_status" id="authorized_participant" value="authorized_participant" ' . ($participant_status === 'authorized_participant' ? 'checked' : '') . '>
                            <label class="form-check-label" for="authorized_participant">
                                <i class="fas fa-user-shield me-2"></i>Yetkili Katılımcı
                            </label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <h6>Özel Yetkiler</h6>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="can_delete_media" name="can_delete_media" value="1" ' . ($user['medya_silebilir'] ? 'checked' : '') . '>
                            <label class="form-check-label" for="can_delete_media">
                                <i class="fas fa-trash me-2"></i>Medya Silebilir
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="can_delete_comments" name="can_delete_comments" value="1" ' . ($user['yorum_silebilir'] ? 'checked' : '') . '>
                            <label class="form-check-label" for="can_delete_comments">
                                <i class="fas fa-comment-slash me-2"></i>Yorum Silebilir
                            </label>
                        </div>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="can_block_users" name="can_block_users" value="1" ' . ($user['kullanici_engelleyebilir'] ? 'checked' : '') . '>
                            <label class="form-check-label" for="can_block_users">
                                <i class="fas fa-ban me-2"></i>Kullanıcı Engelleyebilir
                            </label>
                        </div>
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="can_share_stories" name="can_share_stories" value="1" ' . ($user['hikaye_paylasabilir'] ? 'checked' : '') . '>
                            <label class="form-check-label" for="can_share_stories">
                                <i class="fas fa-camera me-2"></i>Hikaye Paylaşabilir
                            </label>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    <strong>Not:</strong> Tüm kullanıcılar kendi medya ve yorumlarını her zaman düzenleyebilir/silebilir.
                </div>
            </div>
        </div>
        
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-2"></i>Yetkileri Kaydet
            </button>
        </div>
    </form>
    
    <script>
        document.getElementById("permissionsForm").addEventListener("submit", function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch("manage_participants.php?event_id=' . $event_id . '", {
                method: "POST",
                body: formData
            })
            .then(response => response.text())
            .then(data => {
                location.reload();
            })
            .catch(error => {
                console.error("Error:", error);
                alert("Yetkiler güncellenirken hata oluştu.");
            });
        });
    </script>';
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Yetki düzenleme formu yüklenirken hata oluştu.'
    ]);
}
?>
