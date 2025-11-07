import 'package:flutter/material.dart';
import '../utils/colors.dart';
import '../services/api_service.dart';

class PermissionGrantModal extends StatefulWidget {
  final String participantName;
  final int participantId;
  final int eventId;
  final Function(List<String>) onPermissionsGranted;

  const PermissionGrantModal({
    Key? key,
    required this.participantName,
    required this.participantId,
    required this.eventId,
    required this.onPermissionsGranted,
  }) : super(key: key);

  @override
  State<PermissionGrantModal> createState() => _PermissionGrantModalState();
}

class _PermissionGrantModalState extends State<PermissionGrantModal> {
  final ApiService _apiService = ApiService();
  
  final List<String> _availablePermissions = [
    'medya_silebilir',
    'yorum_silebilir',
    'kullanici_engelleyebilir',
    'medya_paylasabilir',
    'yorum_yapabilir',
    'hikaye_paylasabilir',
    'yetki_duzenleyebilir', // ✅ Yeni yetki eklendi
    'bildirim_gonderebilir', // ✅ Manuel bildirim gönderme yetkisi
    // 'profil_degistirebilir', // Kaldırıldı - düğün kapak fotoğrafı değiştirme yetkisi
  ];

  final Map<String, String> _permissionNames = {
    'medya_silebilir': 'Medya Silebilir',
    'yorum_silebilir': 'Yorum Silebilir',
    'kullanici_engelleyebilir': 'Kullanıcı Engelleyebilir',
    'medya_paylasabilir': 'Medya Paylaşabilir',
    'yorum_yapabilir': 'Yorum Yapabilir',
    'hikaye_paylasabilir': 'Hikaye Paylaşabilir',
    'yetki_duzenleyebilir': 'Yetki Düzenleyebilir', // ✅ Yeni yetki eklendi
    'bildirim_gonderebilir': 'Bildirim Gönderebilir', // ✅ Manuel bildirim gönderme
    // 'profil_degistirebilir': 'Profil Değiştirebilir', // Kaldırıldı
  };

  final Map<String, String> _permissionDescriptions = {
    'medya_silebilir': 'Medya ekleme, düzenleme ve silme',
    'yorum_silebilir': 'Yorum ekleme, düzenleme ve silme',
    'kullanici_engelleyebilir': 'Kullanıcıları engelleme ve yasaklama',
    'medya_paylasabilir': 'Medya paylaşma yetkisi',
    'yorum_yapabilir': 'Yorum yapma yetkisi',
    'hikaye_paylasabilir': 'Hikaye paylaşma yetkisi',
    'yetki_duzenleyebilir': 'Diğer kullanıcıların yetkilerini düzenleme', // ✅ Yeni yetki eklendi
    'bildirim_gonderebilir': 'Tüm katılımcılara manuel bildirim gönderme', // ✅ Manuel bildirim
    // 'profil_degistirebilir': 'Profil bilgilerini değiştirme', // Kaldırıldı
  };

  final Set<String> _selectedPermissions = <String>{};

  @override
  void initState() {
    super.initState();
    _loadCurrentPermissions();
  }

  Future<void> _loadCurrentPermissions() async {
    try {
      final participants = await _apiService.getParticipants(widget.eventId);
      final targetParticipant = participants.firstWhere(
        (p) => p['id'] == widget.participantId,
        orElse: () => {},
      );

      if (targetParticipant.isNotEmpty && targetParticipant['permissions'] != null) {
        setState(() {
          _selectedPermissions.clear();
          // ✅ Type casting'i düzelt - List formatını destekle
          final permissions = targetParticipant['permissions'];
          if (permissions is List) {
            // List formatında gelirse, direkt ekle
            _selectedPermissions.addAll(permissions.cast<String>());
          } else if (permissions is Map<String, dynamic>) {
            // Map formatında gelirse, true olanları ekle
            _selectedPermissions.addAll(
              permissions.entries
                  .where((entry) => entry.value == true)
                  .map((entry) => entry.key)
                  .toList(),
            );
          }
        });
      } else {
        // Default yetkileri seçili olarak ayarla (event.php'deki gibi)
        setState(() {
          _selectedPermissions.addAll([
            'medya_paylasabilir',
            'yorum_yapabilir', 
            'hikaye_paylasabilir',
            // 'profil_degistirebilir', // Kaldırıldı
          ]);
        });
      }
    } catch (e) {
      // Hata durumunda default yetkileri kullan
      setState(() {
        _selectedPermissions.addAll([
          'medya_paylasabilir',
          'yorum_yapabilir', 
          'hikaye_paylasabilir',
          // 'profil_degistirebilir', // Kaldırıldı
        ]);
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(16),
      ),
      child: Container(
        constraints: const BoxConstraints(maxHeight: 600),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Header
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: AppColors.primary,
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(16),
                  topRight: Radius.circular(16),
                ),
              ),
              child: Row(
                children: [
                  const Icon(Icons.security, color: Colors.white),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Yetki Ver',
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 20,
                            fontWeight: FontWeight.bold,
                          ),
                        ),
                        Text(
                          widget.participantName,
                          style: const TextStyle(
                            color: Colors.white70,
                            fontSize: 14,
                          ),
                        ),
                      ],
                    ),
                  ),
                  IconButton(
                    onPressed: () => Navigator.of(context).pop(),
                    icon: const Icon(Icons.close, color: Colors.white),
                  ),
                ],
              ),
            ),
            
            // Content
            Flexible(
              child: SingleChildScrollView(
                padding: const EdgeInsets.all(20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Verilecek Yetkileri Seçin:',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    
                    // Permission checkboxes
                    ..._availablePermissions.map((permission) {
                      return Card(
                        margin: const EdgeInsets.only(bottom: 8),
                        child: CheckboxListTile(
                          title: Text(
                            _permissionNames[permission]!,
                            style: const TextStyle(fontWeight: FontWeight.w500),
                          ),
                          subtitle: Text(
                            _permissionDescriptions[permission]!,
                            style: TextStyle(
                              fontSize: 12,
                              color: Colors.grey[600],
                            ),
                          ),
                          value: _selectedPermissions.contains(permission),
                          onChanged: (bool? value) {
                            setState(() {
                              if (value == true) {
                                _selectedPermissions.add(permission);
                              } else {
                                _selectedPermissions.remove(permission);
                              }
                            });
                          },
                          activeColor: AppColors.primary,
                        ),
                      );
                    }).toList(),
                  ],
                ),
              ),
            ),
            
            // Footer
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.grey[50],
                borderRadius: const BorderRadius.only(
                  bottomLeft: Radius.circular(16),
                  bottomRight: Radius.circular(16),
                ),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () => Navigator.of(context).pop(),
                      child: const Text('İptal'),
                    ),
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: () { // ✅ Her zaman aktif - tüm yetkileri alabilir
                        widget.onPermissionsGranted(_selectedPermissions.toList());
                        Navigator.of(context).pop();
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        foregroundColor: Colors.white,
                      ),
                      child: const Text('Yetki Ver'),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}
