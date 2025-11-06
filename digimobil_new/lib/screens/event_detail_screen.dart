import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/widgets/instagram_stories_bar.dart';
import 'package:digimobil_new/widgets/instagram_post_card.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:provider/provider.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/theme_colors.dart';
import 'package:digimobil_new/widgets/shimmer_loading.dart';
import 'package:digimobil_new/widgets/event_detail_shimmer.dart';
import 'package:file_picker/file_picker.dart';
import 'package:image_picker/image_picker.dart';
import 'package:digimobil_new/screens/event_profile_screen.dart';
import 'package:digimobil_new/screens/profile_screen.dart';
import 'package:digimobil_new/widgets/permission_grant_modal.dart';
import 'package:digimobil_new/widgets/media_viewer_modal.dart';
import 'dart:io';
import 'dart:async';
import 'package:digimobil_new/widgets/story_viewer_modal.dart';
import 'package:digimobil_new/widgets/error_modal.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:dio/dio.dart' as dio;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:digimobil_new/utils/app_transitions.dart';
import 'package:digimobil_new/widgets/camera_modal.dart';
import 'package:digimobil_new/widgets/media_select_modal.dart';
import 'package:digimobil_new/widgets/share_modal.dart';
import 'package:digimobil_new/screens/media_editor_screen.dart';

class EventDetailScreen extends StatefulWidget {
  final Event? event;

  const EventDetailScreen({super.key, this.event});

  @override
  State<EventDetailScreen> createState() => _EventDetailScreenState();
}

class _EventDetailScreenState extends State<EventDetailScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  bool _isLoading = false;
  bool _isLoadingMore = false;
  List<Map<String, dynamic>> _media = [];
  List<Map<String, dynamic>> _stories = [];
  final ApiService _apiService = ApiService();
  final ScrollController _scrollController = ScrollController();
  int _currentPage = 1;
  bool _hasMoreMedia = true;
  Timer? _banCheckTimer; // ✅ Yasaklanan kullanıcı kontrolü için timer
  Timer? _dataRefreshTimer; // ✅ Real-time veri yenileme için timer
  static const int _pageSize = 5; // Her seferinde 5 medya yükle
  int _participantsRefreshKey = 0; // ✅ Katılımcılar listesi için refresh key
  bool _isUploading = false; // ✅ Upload durumu
  
  // ✅ Notification için instance
  static final FlutterLocalNotificationsPlugin _notifications = FlutterLocalNotificationsPlugin();
  
  // ✅ Bildirimleri başlat
  Future<void> _initializeNotifications() async {
    const AndroidInitializationSettings androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const DarwinInitializationSettings iosSettings = DarwinInitializationSettings();
    const InitializationSettings initSettings = InitializationSettings(
      android: androidSettings,
      iOS: iosSettings,
    );
    
    await _notifications.initialize(
      initSettings,
      onDidReceiveNotificationResponse: (NotificationResponse response) {
        // Bildirim tıklandığında
      },
    );
  }
  
  // ✅ Bildirim göster
  Future<void> _showUploadNotification(int id, String title, String body, {bool showProgress = false, int progress = 0}) async {
    final AndroidNotificationDetails androidDetails = AndroidNotificationDetails(
      'upload_channel',
      'Medya Yükleme',
      channelDescription: 'Medya ve hikaye yükleme durumu bildirimleri',
      importance: Importance.high,
      priority: Priority.high,
      showProgress: showProgress,
      maxProgress: 100,
      progress: progress,
      indeterminate: !showProgress,
    );
    
    final NotificationDetails details = NotificationDetails(android: androidDetails);
    
    await _notifications.show(id, title, body, details);
  }
  
  // ✅ Paylaşım yetkisi kontrolü
  bool _canShareContent() {
    if (widget.event == null) return false;
    
    final eventDate = DateTime.tryParse(widget.event!.date ?? '');
    if (eventDate == null) return false;
    
    final today = DateTime.now();
    final todayOnly = DateTime(today.year, today.month, today.day);
    final eventDateOnly = DateTime(eventDate.year, eventDate.month, eventDate.day);
    
    // ✅ Etkinlik henüz başlamamışsa paylaşım yapamaz
    if (todayOnly.isBefore(eventDateOnly)) {
      return false;
    }
    
    // ✅ Ücretsiz erişim günü kontrolü (varsayılan 7 gün)
    final freeAccessDays = widget.event!.freeAccessDays ?? 7;
    final accessEndDate = eventDateOnly.add(Duration(days: freeAccessDays));
    
    // ✅ Ücretsiz erişim süresi bitmişse paylaşım yapamaz
    if (todayOnly.isAfter(accessEndDate)) {
      return false;
    }
    
    return true;
  }

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
    _scrollController.addListener(_onScroll);
    _initializeNotifications(); // ✅ Bildirimleri başlat
    _requestNotificationPermission(); // ✅ Bildirim izni iste
    if (widget.event != null) {
      _loadEventData();
    }
    
    // ✅ Yasaklanan kullanıcı kontrolü için periyodik timer
    _banCheckTimer = Timer.periodic(const Duration(seconds: 10), (timer) {
      _checkUserBanStatus();
    });
    
    // ✅ Real-time veri yenileme için periyodik timer
    _dataRefreshTimer = Timer.periodic(const Duration(seconds: 15), (timer) {
      _refreshData();
    });
  }
  
  Future<void> _requestNotificationPermission() async {
    if (Platform.isAndroid) {
      try {
        final androidInfo = await DeviceInfoPlugin().androidInfo;
        if (androidInfo.version.sdkInt >= 33) {
          // Android 13+ notification permission
          final status = await Permission.notification.request();
          if (kDebugMode) {
            debugPrint('📱 Notification Permission: $status');
          }
        }
      } catch (e) {
        if (kDebugMode) {
          debugPrint('⚠️ Notification permission error: $e');
        }
      }
    }
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= 
        _scrollController.position.maxScrollExtent - 200) {
      if (!_isLoadingMore && _hasMoreMedia) {
        _loadMoreMedia();
      }
    }
  }

  @override
  void dispose() {
    _tabController.dispose();
    _scrollController.dispose();
    _banCheckTimer?.cancel(); // ✅ Timer'ı temizle
    _dataRefreshTimer?.cancel(); // ✅ Data refresh timer'ı temizle
    super.dispose();
  }
  
  // ✅ Arka planda medya upload - progress tracking ile
  Future<void> _performMediaUpload(String filePath, String description, double fileSizeMB) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final sessionKey = prefs.getString('session_key') ?? '';
      
      final dioClient = dio.Dio();
      final fileName = filePath.split('/').last;
      
      final formData = dio.FormData.fromMap({
        'event_id': widget.event!.id.toString(),
        'description': description,
        'media_file': await dio.MultipartFile.fromFile(
          filePath,
          filename: fileName,
        ),
      });
      
      int lastProgress = 0;
      
      final response = await dioClient.post(
        'https://dijitalsalon.cagapps.app/digimobiapi/add_media.php',
        data: formData,
        options: dio.Options(
          headers: {
            'Accept': 'application/json',
            'Cookie': 'PHPSESSID=$sessionKey',
          },
          validateStatus: (status) => status! < 500,
        ),
        onSendProgress: (sent, total) async {
          if (total != -1) {
            final progress = ((sent / total) * 100).toInt();
            
            // Her %10'da bir bildirim güncelle
            if (progress - lastProgress >= 10 || progress == 100) {
              lastProgress = progress;
              await _showUploadNotification(
                1,
                'Medya Yükleniyor',
                '${fileSizeMB.toStringAsFixed(1)} MB - %$progress',
                showProgress: true,
                progress: progress,
              );
              
              if (kDebugMode) {
                debugPrint('📤 Upload Progress: $progress%');
              }
            }
          }
        },
      );
      
      if (kDebugMode) {
        debugPrint('Upload Response Status: ${response.statusCode}');
        debugPrint('Upload Response Body: ${response.data}');
      }
      
      if (response.statusCode == 200) {
        final result = response.data;
        if (result['success'] == true) {
          // ✅ UI'yı HEMEN güncelle (notification öncesi)
          if (mounted) {
            await _refreshData();
          }
          
          // Başarılı
          await _showUploadNotification(
            1,
            'Dosya gönderimi başarılı',
            'Medya başarıyla yüklendi',
            showProgress: true,
            progress: 100,
          );
          
          // ✅ Notification'u daha uzun süre göster (5 saniye)
          await Future.delayed(const Duration(seconds: 5));
          await _notifications.cancel(1);
        } else {
          throw Exception(result['error'] ?? 'Upload failed');
        }
      } else if (response.statusCode == 403) {
        // ✅ 403 Forbidden - Limit veya yetki hatası
        final result = response.data;
        if (result is Map && result['error'] != null) {
          throw Exception(result['error']);
        } else {
          throw Exception('Bu etkinlikte medya paylaşma yetkiniz bulunmamaktadır.');
        }
      } else {
        // Diğer HTTP hataları
        final result = response.data;
        if (result is Map && result['error'] != null) {
          throw Exception(result['error']);
        } else {
          throw Exception('HTTP ${response.statusCode}');
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('❌ Background Upload Error: $e');
      }
      
      // ✅ Notification'u iptal et
      await _notifications.cancel(1);
      
      // ✅ Kullanıcıya modal ile göster ve crash'i engelle
      if (mounted) {
        ErrorModal.show(
          context,
          title: 'Yükleme Hatası',
          message: e.toString().replaceFirst('Exception: ', ''),
          icon: Icons.error_outline,
        );
      }
      return;
    }
  }
  
  // ✅ Arka planda story upload - progress tracking ile
  Future<void> _performStoryUpload(String filePath, String description, double fileSizeMB) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final sessionKey = prefs.getString('session_key') ?? '';
      
      final dioClient = dio.Dio();
      final fileName = filePath.split('/').last;
      
      final formData = dio.FormData.fromMap({
        'event_id': widget.event!.id.toString(),
        'description': description,
        'story_file': await dio.MultipartFile.fromFile(
          filePath,
          filename: fileName,
        ),
      });
      
      int lastProgress = 0;
      
      final response = await dioClient.post(
        'https://dijitalsalon.cagapps.app/digimobiapi/add_story.php',
        data: formData,
        options: dio.Options(
          headers: {
            'Accept': 'application/json',
            'Cookie': 'PHPSESSID=$sessionKey',
          },
          validateStatus: (status) => status! < 500,
        ),
        onSendProgress: (sent, total) async {
          if (total != -1) {
            final progress = ((sent / total) * 100).toInt();
            
            // Her %10'da bir bildirim güncelle
            if (progress - lastProgress >= 10 || progress == 100) {
              lastProgress = progress;
              await _showUploadNotification(
                2,
                'Hikaye Yükleniyor',
                '${fileSizeMB.toStringAsFixed(1)} MB - %$progress',
                showProgress: true,
                progress: progress,
              );
              
              if (kDebugMode) {
                debugPrint('📤 Story Upload Progress: $progress%');
              }
            }
          }
        },
      );
      
      if (kDebugMode) {
        debugPrint('Story Upload Response Status: ${response.statusCode}');
        debugPrint('Story Upload Response Body: ${response.data}');
      }
      
      if (response.statusCode == 200) {
        try {
          final result = response.data;
          if (result is Map && result['success'] == true) {
            // ✅ UI'yı HEMEN güncelle (notification öncesi)
            if (mounted) {
              await _refreshData();
            }
            
            // Başarılı
            await _showUploadNotification(
              2,
              'Dosya gönderimi başarılı',
              'Hikaye başarıyla yüklendi',
              showProgress: true,
              progress: 100,
            );
            
            // ✅ Notification'u daha uzun süre göster (5 saniye)
            await Future.delayed(const Duration(seconds: 5));
            await _notifications.cancel(2);
            return; // başarıyla bitti
          } else {
            throw Exception((result is Map ? result['error'] : null) ?? 'Upload failed');
          }
        } catch (inner) {
          if (kDebugMode) {
            debugPrint('⚠️ Story success handling error (ignored): $inner');
          }
          // Başarı sonrası oluşan beklenmeyen hataları bastır, yine de UI'yı tazele
          try {
            if (mounted) {
              await _refreshData();
            }
          } catch (_) {}
          try {
            await _notifications.cancel(2);
          } catch (_) {}
          return;
        }
      } else if (response.statusCode == 403) {
        // ✅ 403 Forbidden - Limit veya yetki hatası
        final result = response.data;
        if (result is Map && result['error'] != null) {
          throw Exception(result['error']);
        } else {
          throw Exception('Bu etkinlikte hikaye paylaşma yetkiniz bulunmamaktadır.');
        }
      } else {
        // Diğer HTTP hataları
        final result = response.data;
        if (result is Map && result['error'] != null) {
          throw Exception(result['error']);
        } else {
          throw Exception('HTTP ${response.statusCode}');
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('❌ Background Story Upload Error: $e');
      }
      
      // ✅ Notification'u iptal et
      await _notifications.cancel(2);
      
      // ✅ Kullanıcıya modal ile göster ve crash'i engelle
      if (mounted) {
        ErrorModal.show(
          context,
          title: 'Yükleme Hatası',
          message: e.toString().replaceFirst('Exception: ', ''),
          icon: Icons.error_outline,
        );
      }
      return;
    }
  }

  Future<void> _loadEventData() async {
    if (widget.event == null) return;
    
    print('Loading event data for event ID: ${widget.event!.id}');
    
    setState(() {
      _isLoading = true;
    });

    try {
      // ✅ Önce kullanıcının bu etkinlikte aktif katılımcı olup olmadığını kontrol et
      final participants = await _apiService.getParticipants(widget.event!.id);
      final currentUser = Provider.of<AuthProvider>(context, listen: false).user;
      
      // Kullanıcı katılımcılar arasında var mı ve aktif mi kontrol et
      Map<String, dynamic>? participant;
      try {
        participant = participants.firstWhere(
          (p) => p['id'] == currentUser?.id,
        );
      } catch (e) {
        participant = null;
      }
      
      if (participant == null) {
        // ✅ Kullanıcı etkinlikte değil, ana ekrana dön
        if (mounted) {
          Navigator.of(context).pushNamedAndRemoveUntil(
            '/',
            (route) => false,
          );
          return;
        }
      } else if (participant['status'] == 'yasakli') {
        // ✅ Kullanıcı yasaklanmış, ana ekrana dön
        if (mounted) {
          Navigator.of(context).pushNamedAndRemoveUntil(
            '/',
            (route) => false,
          );
          return;
        }
      }
      
      // Load media (first page only)
      print('Loading media...');
      final mediaData = await _apiService.getMedia(widget.event!.id, page: 1, limit: _pageSize);
      print('Media data received: ${mediaData}');
      setState(() {
        _media = (mediaData['media'] as List<dynamic>?)?.cast<Map<String, dynamic>>() ?? [];
        _hasMoreMedia = (mediaData['pagination']?['has_more'] ?? false);
      });
      print('Media loaded: ${_media.length} items');

      // Load stories
      print('Loading stories...');
      final storiesData = await _apiService.getStories(widget.event!.id);
      print('Stories data received: ${storiesData}');
      setState(() {
        _stories = storiesData;
      });
      print('Stories loaded: ${_stories.length} items');
    } catch (e) {
      print('Error loading event data: $e');
      
      // ✅ Eğer "Bu etkinliğe katılımcı değilsiniz" hatası ise kullanıcıyı ana ekrana yönlendir
      if (e.toString().contains('Bu etkinliğe katılımcı değilsiniz') || 
          e.toString().contains('Bu etkinliğe katılımcı değilsiniz')) {
        print('🚫 Kullanıcı etkinlikten çıkarıldı, ana ekrana yönlendiriliyor...');
        if (mounted) {
          Navigator.of(context).pushNamedAndRemoveUntil(
            '/',
            (route) => false,
          );
          return;
        }
      }
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Veri yüklenirken hata: $e'),
          backgroundColor: AppColors.error,
        ),
      );
    } finally {
      setState(() {
        _isLoading = false;
      });
      print('Loading completed. Media: ${_media.length}, Stories: ${_stories.length}');
    }
  }

  Future<void> _loadMoreMedia() async {
    if (widget.event == null || !_hasMoreMedia) return;
    
    setState(() {
      _isLoadingMore = true;
    });

    try {
      _currentPage++;
      print('Loading more media - Page: $_currentPage');
      
      final response = await _apiService.getMedia(widget.event!.id, page: _currentPage, limit: _pageSize);
      final newMedia = response['media'] as List<Map<String, dynamic>>;
      
      if (newMedia.isEmpty) {
        _hasMoreMedia = false;
        print('No more media to load');
      } else {
        setState(() {
          _media.addAll(newMedia);
        });
        print('Loaded ${newMedia.length} more media items. Total: ${_media.length}');
      }
    } catch (e) {
      print('Error loading more media: $e');
      _currentPage--; // Rollback page on error
      
      // ✅ Kullanıcıya hata mesajı göster
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Daha fazla medya yüklenirken bir hata oluştu. Lütfen tekrar deneyin.'),
            backgroundColor: AppColors.error,
            duration: const Duration(seconds: 3),
          ),
        );
      }
    } finally {
      setState(() {
        _isLoadingMore = false;
      });
    }
  }

  Future<void> _openCamera() async {
    // ✅ Direkt CameraModal aç
    File? capturedFile;
    String? selectedShareType = 'post'; // ✅ Seçilen paylaşım türü
    
    await CameraModal.show(
      context,
      onMediaCaptured: (file) {
        capturedFile = file;
      },
      shareType: 'post', // ✅ Varsayılan olarak 'post'
      onShareTypeChanged: (shareType) {
        // ✅ Paylaşım türü değiştiğinde güncelle
        selectedShareType = shareType;
      },
    );
    
    if (capturedFile != null && mounted) {
      // ✅ Seçilen paylaşım türünü kullan
      await _processMediaFile(capturedFile!, selectedShareType ?? 'post');
    }
  }

  Future<void> _openGallery() async {
    // ✅ Direkt MediaSelectModal aç
    File? selectedFile;
    await MediaSelectModal.show(
      context,
      onMediaSelected: (file) {
        selectedFile = file;
      },
      shareType: 'post',
    );
    
    if (selectedFile != null && mounted) {
      await _processMediaFile(selectedFile!, 'post');
    }
  }

  Future<void> _processMediaFile(File file, String contentType) async {
    // ✅ Direkt ShareModal göster
    await ShareModal.show(
      context,
      mediaFile: file,
      onShare: (description, tags) async {
        if (contentType == 'story') {
          await _uploadStory(file.path, description);
        } else {
          await _uploadMedia(file.path, description);
        }
      },
      shareType: contentType,
    );
  }

  Future<void> _showStoryOptions() async {
    // ✅ Yeni Instagram benzeri akış: CameraModal veya MediaSelectModal
    await showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.grey[900],
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library, color: Colors.white),
              title: const Text('Galeriden Seç', style: TextStyle(color: Colors.white)),
              onTap: () async {
                Navigator.pop(context);
                await _openGalleryForStory();
              },
            ),
            ListTile(
              leading: const Icon(Icons.camera_alt, color: Colors.white),
              title: const Text('Kamera', style: TextStyle(color: Colors.white)),
              onTap: () async {
                Navigator.pop(context);
                await _openCameraForStory();
              },
            ),
          ],
        ),
      ),
    );
  }

  Future<void> _openCameraForStory() async {
    // ✅ Hikaye için CameraModal aç
    File? capturedFile;
    
    await CameraModal.show(
      context,
      onMediaCaptured: (file) {
        capturedFile = file;
      },
      shareType: 'story', // ✅ Hikaye modu
    );
    
    if (capturedFile != null && mounted) {
      await _processMediaFile(capturedFile!, 'story');
    }
  }

  Future<void> _openGalleryForStory() async {
    // ✅ Hikaye için MediaSelectModal aç
    File? selectedFile;
    await MediaSelectModal.show(
      context,
      onMediaSelected: (file) {
        selectedFile = file;
      },
      shareType: 'story', // ✅ Hikaye modu
    );
    
    if (selectedFile != null && mounted) {
      await _processMediaFile(selectedFile!, 'story');
    }
  }

  void _showDescriptionDialog(String filePath, bool isStory) {
    final TextEditingController descController = TextEditingController();
    
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: ThemeColors.surface(context),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => Padding(
        padding: EdgeInsets.only(
          bottom: MediaQuery.of(context).viewInsets.bottom,
          left: 20,
          right: 20,
          top: 20,
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            const Text(
              'Açıklama Ekle',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.bold,
                color: AppColors.textPrimary,
              ),
            ),
            const SizedBox(height: 20),
            TextField(
              controller: descController,
              decoration: const InputDecoration(
                hintText: 'Bir şeyler yazın...',
                border: OutlineInputBorder(),
              ),
              maxLines: 3,
              autofocus: true,
            ),
            const SizedBox(height: 20),
            Row(
              mainAxisAlignment: MainAxisAlignment.end,
              children: [
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: const Text('İptal'),
                ),
                const SizedBox(width: 10),
                ElevatedButton(
                  onPressed: () async {
                    Navigator.pop(context);
                    final api = _apiService;
                    final eventId = widget.event!.id;

                    if (isStory) {
                      // Doğrudan hikaye olarak paylaş
                      try {
                        final limitCheck = await api.checkMediaLimit(eventId, type: 'story');
                        if (limitCheck['can_upload'] == false && limitCheck['limit_reached'] == true) {
                          if (mounted) {
                            ErrorModal.show(
                              context,
                              title: 'Hikaye Alanı Doldu',
                              message: limitCheck['message'] ?? 'Hikaye paylaşım limiti doldu.',
                              icon: Icons.auto_stories,
                              iconColor: Colors.orange,
                            );
                          }
                          return;
                        }
                      } catch (_) {}
                      _uploadStory(filePath, descController.text);
                      return;
                    }

                    await showModalBottomSheet(
                      context: context,
                      backgroundColor: Theme.of(context).cardTheme.color ?? ThemeColors.surface(context),
                      shape: const RoundedRectangleBorder(
                        borderRadius: BorderRadius.vertical(top: Radius.circular(16)),
                      ),
                      builder: (ctx) {
                        return SafeArea(
                          child: Column(
                            mainAxisSize: MainAxisSize.min,
                            children: [
                              const SizedBox(height: 10),
                              Container(width: 40, height: 4, decoration: BoxDecoration(color: Colors.black12, borderRadius: BorderRadius.circular(2))),
                              const SizedBox(height: 10),
                              const Text('Nasıl paylaşmak istersiniz?', style: TextStyle(fontSize: 16, fontWeight: FontWeight.w600)),
                              const SizedBox(height: 10),
                              ListTile(
                                leading: const Icon(Icons.auto_stories, color: Colors.orange),
                                title: const Text('Hikaye olarak paylaş'),
                                onTap: () async {
                                  Navigator.pop(ctx);
                                  try {
                                    final limitCheck = await api.checkMediaLimit(eventId, type: 'story');
                                    if (limitCheck['can_upload'] == false && limitCheck['limit_reached'] == true) {
                                      if (mounted) {
                                        ErrorModal.show(
                                          context,
                                          title: 'Hikaye Alanı Doldu',
                                          message: limitCheck['message'] ?? 'Hikaye paylaşım limiti doldu.',
                                          icon: Icons.auto_stories,
                                          iconColor: Colors.orange,
                                        );
                                      }
                                      return;
                                    }
                                  } catch (_) {}
                                  _uploadStory(filePath, descController.text);
                                },
                              ),
                              ListTile(
                                leading: const Icon(Icons.photo_library, color: Colors.blue),
                                title: const Text('Gönderi (Medya) olarak paylaş'),
                                onTap: () async {
                                  Navigator.pop(ctx);
                                  try {
                                    final limitCheck = await api.checkMediaLimit(eventId, type: 'media');
                                    if (limitCheck['can_upload'] == false && limitCheck['limit_reached'] == true) {
                                      if (mounted) {
                                        ErrorModal.show(
                                          context,
                                          title: 'Medya Alanı Doldu',
                                          message: limitCheck['message'] ?? 'Bu etkinliğe daha fazla medya eklenemiyor.',
                                          icon: Icons.storage,
                                          iconColor: Colors.orange,
                                        );
                                      }
                                      return;
                                    }
                                  } catch (_) {}
                                  _uploadMedia(filePath, descController.text);
                                },
                              ),
                              const SizedBox(height: 14),
                            ],
                          ),
                        );
                      },
                    );
                  },
                  child: const Text('Paylaş'),
                ),
              ],
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Future<void> _showCameraOptions_OLD() async {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Handle bar
            Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.symmetric(vertical: 12),
              decoration: BoxDecoration(
                color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            
            // Title
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 20, vertical: 10),
              child: Text(
                'Paylaşım Seçenekleri',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            
            const Divider(),
            
            // Options
            ListTile(
              leading: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.blue.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.photo_camera,
                  color: Colors.blue,
                  size: 24,
                ),
              ),
              title: const Text('Fotoğraf Çek'),
              subtitle: const Text('Kameradan fotoğraf çek'),
              onTap: () {
                Navigator.pop(context);
                _openCameraLegacy('photo');
              },
            ),
            
            ListTile(
              leading: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.red.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.videocam,
                  color: Colors.red,
                  size: 24,
                ),
              ),
              title: const Text('Video Çek'),
              subtitle: const Text('Kameradan video çek'),
              onTap: () {
                Navigator.pop(context);
                _openCameraLegacy('video');
              },
            ),
            
            ListTile(
              leading: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.green.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.photo_library,
                  color: Colors.green,
                  size: 24,
                ),
              ),
              title: const Text('Galeriden Fotoğraf Seç'),
              subtitle: const Text('Galeriden fotoğraf seç'),
              onTap: () {
                Navigator.pop(context);
                _pickFromGallery('photo');
              },
            ),
            
            ListTile(
              leading: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.orange.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.video_library,
                  color: Colors.orange,
                  size: 24,
                ),
              ),
              title: const Text('Galeriden Video Seç'),
              subtitle: const Text('Galeriden video seç'),
              onTap: () {
                Navigator.pop(context);
                _pickFromGallery('video');
              },
            ),
            
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Future<void> _openCameraLegacy(String type) async {
    try {
      final ImagePicker picker = ImagePicker();
      XFile? file;
      
      if (type == 'photo') {
        file = await picker.pickImage(source: ImageSource.camera);
      } else if (type == 'video') {
        file = await picker.pickVideo(source: ImageSource.camera);
      }

      if (file == null) return;

      // Show content type selection (Media or Story)
      final contentType = await showDialog<String>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Paylaşım Türü'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.grid_on, color: Colors.blue),
                title: const Text('Gönderi Olarak Paylaş'),
                subtitle: const Text('Ana sayfada görünür'),
                onTap: () => Navigator.pop(context, 'media'),
              ),
              ListTile(
                leading: const Icon(Icons.circle, color: Colors.purple),
                title: const Text('Hikaye Olarak Paylaş'),
                subtitle: const Text('24 saat sonra silinir'),
                onTap: () => Navigator.pop(context, 'story'),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('İptal'),
            ),
          ],
        ),
      );

      if (contentType == null) return;

      // Show description modal - ✅ Keyboard-aware
      final TextEditingController descriptionController = TextEditingController();
      final description = await showModalBottomSheet<String>(
        context: context,
        backgroundColor: Colors.transparent,
        isScrollControlled: true,
        builder: (context) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
          ),
          child: Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Handle bar
              Container(
                width: 40,
                height: 4,
                margin: const EdgeInsets.symmetric(vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.grey[300],
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              
              Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                    mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      contentType == 'media' ? 'Gönderi Açıklaması' : 'Hikaye Açıklaması',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: descriptionController,
                        autofocus: true,
                      decoration: InputDecoration(
                          hintText: contentType == 'media' 
                              ? 'Gönderiniz için açıklama yazın...' 
                              : 'Hikayeniz için açıklama yazın...',
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(12),
                          ),
                          filled: true,
                          fillColor: Colors.grey[50],
                          contentPadding: const EdgeInsets.all(16),
                        ),
                        maxLines: 4,
                        textInputAction: TextInputAction.done,
                      ),
                      const SizedBox(height: 20),
                      Row(
                      children: [
                          Expanded(
                            child: TextButton(
                          onPressed: () => Navigator.pop(context),
                              style: TextButton.styleFrom(
                                padding: const EdgeInsets.symmetric(vertical: 14),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                ),
                              ),
                          child: const Text('İptal'),
                        ),
                          ),
                          const SizedBox(width: 12),
                          Expanded(
                            flex: 2,
                            child: ElevatedButton(
                          onPressed: () {
                            Navigator.pop(context, descriptionController.text);
                          },
                              style: ElevatedButton.styleFrom(
                                backgroundColor: AppColors.primary,
                                foregroundColor: Colors.white,
                                padding: const EdgeInsets.symmetric(vertical: 14),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                ),
                              ),
                          child: const Text('Paylaş'),
                            ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
                SizedBox(height: MediaQuery.of(context).padding.bottom),
            ],
            ),
          ),
        ),
      );

      if (description == null) return;

      // Upload media or story
      if (contentType == 'media') {
        await _uploadMedia(file.path, description);
      } else {
        await _uploadStory(file.path, description);
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Kamera hatası: $e'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  Future<void> _pickFromGallery(String type) async {
    try {
      PlatformFile? file;
      
      if (type == 'photo') {
        FilePickerResult? result = await FilePicker.platform.pickFiles(
          type: FileType.image,
          allowMultiple: false,
        );
        file = result?.files.first;
      } else if (type == 'video') {
        FilePickerResult? result = await FilePicker.platform.pickFiles(
          type: FileType.video,
          allowMultiple: false,
        );
        file = result?.files.first;
      }

      if (file == null) return;

      // Show content type selection (Media or Story)
      final contentType = await showDialog<String>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Paylaşım Türü'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.grid_on, color: Colors.blue),
                title: const Text('Gönderi Olarak Paylaş'),
                subtitle: const Text('Ana sayfada görünür'),
                onTap: () => Navigator.pop(context, 'media'),
              ),
              ListTile(
                leading: const Icon(Icons.circle, color: Colors.purple),
                title: const Text('Hikaye Olarak Paylaş'),
                subtitle: const Text('24 saat sonra silinir'),
                onTap: () => Navigator.pop(context, 'story'),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('İptal'),
            ),
          ],
        ),
      );

      if (contentType == null) return;

      // Show description modal - ✅ Keyboard-aware
      final TextEditingController descriptionController = TextEditingController();
      final description = await showModalBottomSheet<String>(
        context: context,
        backgroundColor: Colors.transparent,
        isScrollControlled: true,
        builder: (context) => Padding(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
          ),
          child: Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Handle bar
              Container(
                width: 40,
                height: 4,
                margin: const EdgeInsets.symmetric(vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.grey[300],
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              
              Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                    mainAxisSize: MainAxisSize.min,
                  children: [
                    Text(
                      contentType == 'media' ? 'Gönderi Açıklaması' : 'Hikaye Açıklaması',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                        controller: descriptionController,
                        autofocus: true,
                      decoration: InputDecoration(
                        hintText: contentType == 'media' 
                            ? 'Gönderiniz için açıklama yazın...'
                            : 'Hikayeniz için açıklama yazın...',
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        filled: true,
                        fillColor: Colors.grey[50],
                          contentPadding: const EdgeInsets.all(16),
                      ),
                        maxLines: 4,
                        textInputAction: TextInputAction.done,
                    ),
                    const SizedBox(height: 20),
                    Row(
                      children: [
                        Expanded(
                          child: TextButton(
                            onPressed: () => Navigator.pop(context),
                              style: TextButton.styleFrom(
                                padding: const EdgeInsets.symmetric(vertical: 14),
                                shape: RoundedRectangleBorder(
                                  borderRadius: BorderRadius.circular(12),
                                ),
                              ),
                            child: const Text('İptal'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                            flex: 2,
                          child: ElevatedButton(
                            onPressed: () {
                                Navigator.pop(context, descriptionController.text);
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              foregroundColor: Colors.white,
                                padding: const EdgeInsets.symmetric(vertical: 14),
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            child: const Text('Paylaş'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
                SizedBox(height: MediaQuery.of(context).padding.bottom),
            ],
            ),
          ),
        ),
      );

      if (description == null) return;

      // Upload based on content type
      if (contentType == 'media') {
        await _uploadMedia(file.path!, description);
      } else {
        await _uploadStory(file.path!, description);
      }

    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Dosya seçilirken hata: $e'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  Future<void> _uploadMedia(String filePath, String description) async {
    if (_isUploading) {
      return; // Zaten upload devam ediyorsa yeni upload başlatma
    }
    final int previousMediaCount = _media.length;
    setState(() {
      _isUploading = true;
    });
    try {
      // ✅ 1. Limit kontrolü (önce bu yapılmalı)
      try {
        final limitCheck = await _apiService.checkMediaLimit(widget.event!.id, type: 'media');
        if (kDebugMode) {
          debugPrint('🔍 Limit Check Result: $limitCheck');
        }
        
        // Limit doluysa modal göster ve çık
        if (limitCheck['can_upload'] == false && limitCheck['limit_reached'] == true) {
          if (mounted) {
            setState(() {
              _isUploading = false;
            });
            
            ErrorModal.show(
              context,
              title: 'Medya Alanı Doldu',
              message: limitCheck['message'] ?? 'Bu etkinliğe daha fazla medya eklenemiyor.',
              icon: Icons.storage,
              iconColor: Colors.orange,
            );
          }
          return; // Upload başlatma
        }
      } catch (limitError) {
        if (kDebugMode) {
          debugPrint('⚠️ Limit check failed: $limitError');
        }
        // Limit kontrolü başarısız olsa bile devam et (backend'de tekrar kontrol edilecek)
      }
      
      // ✅ 2. Yetkileri kontrol et
      try {
        final permCheck = await _apiService.checkPermissions(widget.event!.id);
        if (kDebugMode) {
          debugPrint('🔍 Permission Check Result: $permCheck');
          debugPrint('🔍 Can Share Media: ${permCheck['can_share_media']}');
          debugPrint('🔍 Reason: ${permCheck['reason']}');
        }
        
        // Yetki yoksa hata göster
        if (permCheck['can_share_media'] != true) {
          throw Exception(permCheck['reason'] ?? 'Bu etkinlikte medya paylaşma yetkiniz bulunmamaktadır.');
        }
      } catch (permError) {
        if (kDebugMode) {
          debugPrint('⚠️ Permission check failed: $permError');
        }
        rethrow;
      }
      
      // ✅ Dosya boyutunu kontrol et
      final file = File(filePath);
      final fileSizeMB = await file.length() / (1024 * 1024);
      if (kDebugMode) {
        debugPrint('📁 Upload File Size: ${fileSizeMB.toStringAsFixed(2)} MB');
      }
      
      // ✅ Bildirim başlat
      await _showUploadNotification(1, 'Medya Yükleniyor', 'Başlatılıyor...', showProgress: true, progress: 0);
      
      // ✅ Kullanıcıya bildir
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Row(
              children: [
                CircularProgressIndicator(
                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                  strokeWidth: 2,
                ),
                SizedBox(width: 16),
                Expanded(
                  child: Text('Medya yükleniyor...\nBildirimlerden takip edebilirsiniz.'),
                ),
              ],
            ),
            backgroundColor: AppColors.primary,
            duration: Duration(seconds: 3),
          ),
        );
      }
      
      // ✅ Upload'u başlat (await etme, arka planda devam etsin)
      _performMediaUpload(filePath, description, fileSizeMB);
      
        setState(() {
        _isUploading = false;
      });
      
      if (mounted) {
        await _refreshData();
        await _refreshUntilChange(type: 'media', previousCount: previousMediaCount);
      }
      
    } catch (e) {
      setState(() {
        _isUploading = false;
      });
      
      if (kDebugMode) {
        debugPrint('❌ Upload Error: $e');
      }
      
      // ✅ Hata mesajını kontrol et ve uygun başlık/mesaj belirle
      String errorMessage = e.toString();
      String title = 'Hata';
      IconData icon = Icons.error_outline;
      
      if (errorMessage.contains('medya alanı doldu') || errorMessage.contains('daha fazla medya eklenemiyor')) {
        title = 'Medya Alanı Doldu';
        icon = Icons.storage;
        errorMessage = 'Etkinlik medya alanı doldu. Bu etkinliğe daha fazla medya eklenemiyor. Lütfen etkinlik sahibi ile iletişime geçin.';
      } else if (errorMessage.contains('yetkiniz bulunmamaktadır') || 
          errorMessage.contains('yetki')) {
        title = 'Erişim Yetkisi Yok';
        icon = Icons.lock_outline;
        
        // Özel mesajları kontrol et
        if (errorMessage.contains('medya paylaşma')) {
          errorMessage = 'Bu etkinlikte medya paylaşma yetkiniz bulunmamaktadır.';
        } else if (errorMessage.contains('hikaye')) {
          errorMessage = 'Bu etkinlikte hikaye paylaşma yetkiniz bulunmamaktadır.';
        } else if (errorMessage.contains('yorum')) {
          errorMessage = 'Bu etkinlikte yorum yapma yetkiniz bulunmamaktadır.';
      } else {
          errorMessage = 'Bu işlem için yetkiniz bulunmamaktadır. Lütfen etkinlik yöneticisi ile iletişime geçin.';
        }
      } else if (errorMessage.contains('Etkinlik henüz başlamadı')) {
        title = 'Etkinlik Başlamadı';
        errorMessage = 'Etkinlik tarihinden önce medya paylaşamazsınız.';
      } else if (errorMessage.contains('erişim süresi doldu')) {
        title = 'Erişim Süresi Doldu';
        errorMessage = 'Ücretsiz erişim süresi doldu. Artık medya paylaşamazsınız.';
      } else {
        // Genel hata mesajından "Exception: " kısmını temizle
        errorMessage = errorMessage.replaceFirst('Exception: ', '');
        title = 'Hata';
      }
      
      if (mounted) {
        ErrorModal.show(
          context,
          title: title,
          message: errorMessage,
          icon: icon,
        );
      }
    }
  }

  Future<void> _uploadStory(String filePath, String description) async {
    if (_isUploading) {
      return; // Zaten upload devam ediyorsa yeni upload başlatma
    }
    final int previousStoryCount = _stories.length;
    setState(() {
      _isUploading = true;
    });
    try {
      // ✅ 1. Limit kontrolü (önce bu yapılmalı)
      try {
        final limitCheck = await _apiService.checkMediaLimit(widget.event!.id, type: 'story');
        if (kDebugMode) {
          debugPrint('🔍 Story Limit Check Result: $limitCheck');
        }
        
        // Limit doluysa modal göster ve çık
        if (limitCheck['can_upload'] == false && limitCheck['limit_reached'] == true) {
          if (mounted) {
            setState(() {
              _isUploading = false;
            });
            
            ErrorModal.show(
              context,
              title: 'Hikaye Alanı Doldu',
              message: limitCheck['message'] ?? 'Hikaye paylaşım limiti doldu.',
              icon: Icons.auto_stories,
              iconColor: Colors.orange,
            );
          }
          return; // Upload başlatma
        }
      } catch (limitError) {
        if (kDebugMode) {
          debugPrint('⚠️ Story limit check failed: $limitError');
        }
        // Limit kontrolü başarısız olsa bile devam et (backend'de tekrar kontrol edilecek)
      }
      
      // ✅ 2. Dosya boyutunu kontrol et
      final file = File(filePath);
      final fileSizeMB = await file.length() / (1024 * 1024);
      if (kDebugMode) {
        debugPrint('📁 Upload Story File Size: ${fileSizeMB.toStringAsFixed(2)} MB');
      }
      
      // ✅ Bildirim başlat
      await _showUploadNotification(2, 'Hikaye Yükleniyor', 'Başlatılıyor...', showProgress: true, progress: 0);
      
      // ✅ Kullanıcıya bildir
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Row(
              children: [
                CircularProgressIndicator(
                  valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                  strokeWidth: 2,
                ),
                SizedBox(width: 16),
                Expanded(
                  child: Text('Hikaye yükleniyor...\nBildirimlerden takip edebilirsiniz.'),
                ),
              ],
            ),
            backgroundColor: AppColors.primary,
            duration: Duration(seconds: 3),
          ),
        );
      }
      
      // ✅ Upload'u başlat (await etme, arka planda devam etsin)
      _performStoryUpload(filePath, description, fileSizeMB);
      
        setState(() {
        _isUploading = false;
      });
      
      if (mounted) {
        await _refreshData();
        await _refreshUntilChange(type: 'story', previousCount: previousStoryCount);
      }
      
    } catch (e) {
      // ✅ Loading dialog'u kapat
      if (mounted) {
        Navigator.of(context).pop();
      }
      
      setState(() {
        _isUploading = false;
      });
      
      // ✅ Hata bildirimi
      await _showUploadNotification(2, 'Yükleme Hatası', 'Hikaye yüklenirken bir hata oluştu');
      await Future.delayed(const Duration(seconds: 3));
      await _notifications.cancel(2);
      
      // ✅ Hata mesajını kontrol et ve uygun başlık/mesaj belirle
      String errorMessage = e.toString();
      String title = 'Hata';
      IconData icon = Icons.error_outline;
      
      if (errorMessage.contains('hikaye alanı doldu') || errorMessage.contains('eski hikayelerin silinmesini bekleyin')) {
        title = 'Hikaye Alanı Doldu';
        icon = Icons.video_library;
        errorMessage = 'Etkinlik hikaye alanı doldu. Lütfen eski hikayelerin otomatik silinmesini bekleyin (24 saat).';
      } else if (errorMessage.contains('yetkiniz bulunmamaktadır') || 
          errorMessage.contains('yetki')) {
        title = 'Erişim Yetkisi Yok';
        icon = Icons.lock_outline;
        
        // Özel mesajları kontrol et
        if (errorMessage.contains('hikaye')) {
          errorMessage = 'Bu etkinlikte hikaye paylaşma yetkiniz bulunmamaktadır.';
        } else if (errorMessage.contains('yorum')) {
          errorMessage = 'Bu etkinlikte yorum yapma yetkiniz bulunmamaktadır.';
      } else {
          errorMessage = 'Bu işlem için yetkiniz bulunmamaktadır. Lütfen etkinlik yöneticisi ile iletişime geçin.';
        }
      } else if (errorMessage.contains('Etkinlik henüz başlamadı')) {
        title = 'Etkinlik Başlamadı';
        errorMessage = 'Etkinlik tarihinden önce hikaye paylaşamazsınız.';
      } else if (errorMessage.contains('erişim süresi doldu')) {
        title = 'Erişim Süresi Doldu';
        errorMessage = 'Ücretsiz erişim süresi doldu. Artık hikaye paylaşamazsınız.';
      } else {
        // Genel hata mesajından "Exception: " kısmını temizle
        errorMessage = errorMessage.replaceFirst('Exception: ', '');
        title = 'Hata';
      }
      
      if (mounted) {
        ErrorModal.show(
          context,
          title: title,
          message: errorMessage,
          icon: icon,
        );
      }
    }
  }

  Future<void> _refreshUntilChange({required String type, required int previousCount, int attempts = 6}) async {
    for (int i = 0; i < attempts; i++) {
      await Future.delayed(const Duration(milliseconds: 800));
      await _refreshData();
      if (!mounted) return;
      if (type == 'media') {
        if (_media.length > previousCount) return;
      } else if (type == 'story') {
        if (_stories.length > previousCount) return;
      }
    }
  }

  void _openEventProfile() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => EventProfileScreen(event: widget.event!),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    print('Building EventDetailScreen. Event: ${widget.event?.title}, Loading: $_isLoading');
    
    if (widget.event == null) {
      print('Event is null, showing error screen');
      return Scaffold(
        appBar: AppBar(
          title: const Text('Etkinlik Bulunamadı'),
          backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        ),
        body: const Center(
          child: Text('Etkinlik bulunamadı'),
        ),
      );
    }

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        elevation: 0,
        title: Text(
          widget.event!.title,
          style: const TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: 18,
          ),
        ),
        centerTitle: true,
        actions: [
          GestureDetector(
            onTap: () => _openEventProfile(),
            child: Container(
              margin: const EdgeInsets.only(right: 16),
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: AppColors.primary.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.person,
                color: AppColors.primary,
                size: 20,
              ),
            ),
          ),
        ],
      ),
      body: _isLoading
          ? const EventDetailShimmer()
          : Column(
              children: [
                // Tab Bar
                TabBar(
                  controller: _tabController,
                  labelColor: AppColors.primary,
                  unselectedLabelColor: Colors.grey,
                  indicatorColor: AppColors.primary,
                  tabs: const [
                    Tab(icon: Icon(Icons.home), text: 'Ana Sayfa'),
                    Tab(icon: Icon(Icons.photo_library), text: 'Medya'),
                    Tab(icon: Icon(Icons.auto_stories), text: 'Hikayeler'),
                    Tab(icon: Icon(Icons.people), text: 'Katılımcılar'),
                  ],
                ),
                // Tab Content
                Expanded(
                  child: TabBarView(
                    controller: _tabController,
                    children: [
                      // Ana Sayfa - Stories + Posts
                      SingleChildScrollView(
                        controller: _scrollController,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Stories Bar
                            InstagramStoriesBar(
                              events: [widget.event!],
                              stories: _stories,
                              onAddStory: _showStoryOptions,
                              onEventSelected: (event) {
                                // Already in this event
                              },
                            ),
                            const Divider(color: Colors.black26),
                            // Posts Feed
                            _buildPostsFeed(),
                          ],
                        ),
                      ),
                      // Medya Sekmesi
                      SingleChildScrollView(
                        child: _buildPostsFeed(),
                      ),
                      // Hikayeler Sekmesi
                      SingleChildScrollView(
                        child: Column(
                          children: [
                            // Stories Bar
                            InstagramStoriesBar(
                              events: [widget.event!],
                              stories: _stories,
                              onAddStory: _showStoryOptions,
                              onEventSelected: (event) {
                                // Already in this event
                              },
                            ),
                            const Divider(color: Colors.black26),
                            // Stories List
                            _buildStoriesList(),
                          ],
                        ),
                      ),
                      // Katılımcılar Sekmesi
                      _buildParticipantsTab(),
                    ],
                  ),
                ),
              ],
            ),
      floatingActionButton: _canShareContent()
        ? Stack(
            alignment: Alignment.bottomRight,
            children: [
              // ✅ Galeri butonu (sol)
              Positioned(
                right: 70,
                bottom: 0,
                child: FloatingActionButton(
                  heroTag: "event_detail_gallery_fab",
                  onPressed: _openGallery,
                  backgroundColor: AppColors.primary,
                  mini: true,
                  child: const Icon(Icons.photo_library, color: Colors.white),
                ),
              ),
              // ✅ Kamera butonu (sağ)
              FloatingActionButton(
                heroTag: "event_detail_camera_fab",
                onPressed: _openCamera,
                backgroundColor: AppColors.primary,
                child: const Icon(Icons.camera_alt, color: Colors.white),
              ),
            ],
          )
        : null,
    );
  }

  Widget _buildPostsFeed() {
    print('Building posts feed. Media count: ${_media.length}');
    
    if (_media.isEmpty) {
      print('No media found, showing empty state');
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(50),
          child: Column(
            children: [
              Icon(
                Icons.photo_library_outlined,
                size: 80,
                color: Colors.grey,
              ),
              SizedBox(height: 20),
              Text(
                'Henüz gönderi yok',
                style: TextStyle(
                  fontSize: 18,
                  color: Colors.grey,
                ),
              ),
              SizedBox(height: 10),
              Text(
                'İlk gönderiyi eklemek için + butonuna tıklayın',
                style: TextStyle(
                  fontSize: 14,
                  color: Colors.grey,
                ),
              ),
            ],
          ),
        ),
      );
    }

    // Only show media as posts (no stories)
    List<Map<String, dynamic>> mediaPosts = [];
    
    // Add media as posts
    for (var media in _media) {
      mediaPosts.add({
        ...media,
        'post_type': 'media',
      });
    }
    
    // Sort by creation date (newest first)
    mediaPosts.sort((a, b) {
      final dateA = DateTime.tryParse(a['created_at'] ?? '') ?? DateTime(1970);
      final dateB = DateTime.tryParse(b['created_at'] ?? '') ?? DateTime(1970);
      return dateB.compareTo(dateA);
    });

    return Column(
      children: [
        ...mediaPosts.map((post) => InstagramPostCard(
          event: widget.event!,
          post: post,
          allMediaList: mediaPosts,
          onTap: () {
            final mediaList = mediaPosts;
            final index = mediaList.indexWhere((m) => m['id'] == post['id']);
            if (index != -1) {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => MediaViewerModal(
                    mediaList: mediaList,
                    initialIndex: index,
                    onMediaUpdated: () {
                      _refreshData();
                    },
                  ),
                ),
              ).then((_) {
                _refreshData();
              });
            }
          },
          onMediaDeleted: () {
            // ✅ Medya silindiğinde hemen cache bypass ile refresh yap
            _refreshDataAfterDelete();
          },
          onCommentCountChanged: () {
            _refreshData();
          },
        )).toList(),
        // Loading indicator for pagination
        if (_isLoadingMore)
          Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              children: [
                _buildPostCardShimmer(),
                const SizedBox(height: 8),
                _buildPostCardShimmer(),
              ],
            ),
          ),
        // End of content indicator
        if (!_hasMoreMedia && _media.isNotEmpty)
          const Padding(
            padding: EdgeInsets.all(20),
            child: Center(
              child: Text(
                'Tüm gönderiler yüklendi',
                style: TextStyle(
                  color: Colors.grey,
                  fontSize: 14,
                ),
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildPostCardShimmer() {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 0, vertical: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Header (user info)
          Padding(
            padding: const EdgeInsets.all(12),
            child: Row(
              children: [
                ShimmerLoading(
                  width: 40,
                  height: 40,
                  borderRadius: BorderRadius.circular(20),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      ShimmerLoading(width: 120, height: 16),
                      const SizedBox(height: 4),
                      ShimmerLoading(width: 80, height: 12),
                    ],
                  ),
                ),
              ],
            ),
          ),
          
          // Image
          ShimmerLoading(
            width: double.infinity,
            height: 300,
          ),
          
          // Actions
          Padding(
            padding: const EdgeInsets.all(12),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ShimmerLoading(width: 100, height: 16),
                const SizedBox(height: 8),
                ShimmerLoading(width: 150, height: 14),
                const SizedBox(height: 4),
                ShimmerLoading(width: 200, height: 14),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildMediaGrid() {
    if (_media.isEmpty) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.photo_library_outlined,
              size: 80,
              color: Colors.grey,
            ),
            SizedBox(height: 20),
            Text(
              'Henüz medya yok',
              style: TextStyle(
                fontSize: 18,
                color: Colors.grey,
              ),
            ),
            SizedBox(height: 10),
            Text(
              'İlk medyayı eklemek için + butonuna tıklayın',
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey,
              ),
            ),
          ],
        ),
      );
    }

    return GridView.builder(
      padding: const EdgeInsets.all(1),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        crossAxisSpacing: 1,
        mainAxisSpacing: 1,
      ),
      itemCount: _media.length,
      itemBuilder: (context, index) {
        final media = _media[index];
        return GestureDetector(
          onTap: () {
            // ✅ Medya tam ekran görüntüleyici aç
            Navigator.push(
              context,
              MaterialPageRoute(
                  builder: (context) => MediaViewerModal(
                    mediaList: _media,
                    initialIndex: index,
                    onMediaUpdated: () {
                      // ✅ Medya silindiğinde cache bypass ile hemen güncelle
                      _refreshDataAfterDelete();
                    },
                  ),
              ),
            ).then((_) {
              // ✅ Modal kapandığında refresh yap (yorum/beğeni değişiklikleri için)
              _refreshData();
            });
          },
          child: Stack(
            fit: StackFit.expand,
            children: [
              Container(
            decoration: BoxDecoration(
              image: media['url'] != null
                      // ✅ Video için thumbnail kullan, resim için orijinal URL kullan
                  ? DecorationImage(
                          image: NetworkImage(
                            (media['type'] == 'video' && media['thumbnail'] != null)
                                ? media['thumbnail']
                                : media['url'],
                          ),
                      fit: BoxFit.cover,
                    )
                  : null,
              color: media['url'] == null
                  ? AppColors.primary.withOpacity(0.1)
                  : null,
            ),
            child: media['url'] == null
                ? const Center(
                    child: Icon(
                      Icons.image,
                      color: AppColors.primary,
                      size: 30,
                    ),
                  )
                : null,
              ),
              // ✅ Video ise play icon göster
              if (media['type'] == 'video')
                Center(
                  child: Container(
                    decoration: BoxDecoration(
                      shape: BoxShape.circle,
                      color: Colors.black.withOpacity(0.6),
                    ),
                    padding: const EdgeInsets.all(8),
                    child: const Icon(
                      Icons.play_arrow,
                      color: Colors.white,
                      size: 24,
                    ),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildStoriesList() {
    if (_stories.isEmpty) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.video_library_outlined,
              size: 80,
              color: Colors.grey,
            ),
            SizedBox(height: 20),
            Text(
              'Henüz hikaye yok',
              style: TextStyle(
                fontSize: 18,
                color: Colors.grey,
              ),
            ),
            SizedBox(height: 10),
            Text(
              'İlk hikayeyi eklemek için kamera butonuna tıklayın',
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey,
              ),
            ),
          ],
        ),
      );
    }

    return ListView.builder(
      shrinkWrap: true, // ✅ Height sınırı sorunu için
      physics: const NeverScrollableScrollPhysics(), // ✅ Parent scroll ile çakışmayı önle
      padding: const EdgeInsets.all(16),
      itemCount: _stories.length,
      itemBuilder: (context, index) {
        final story = _stories[index];
        return Card(
          margin: const EdgeInsets.only(bottom: 16),
          child: ListTile(
            leading: CircleAvatar(
              backgroundColor: AppColors.primary.withOpacity(0.1),
              child: const Icon(
                Icons.video_library,
                color: AppColors.primary,
              ),
            ),
            title: Text(
              story['user_name'] ?? 'Kullanıcı',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            subtitle: Text(
              story['description'] ?? 'Açıklama yok',
            ),
            trailing: Text(
              story['created_at'] ?? '',
              style: const TextStyle(fontSize: 12, color: Colors.grey),
            ),
            onTap: () async {
              try {
                // Kullanıcının bu etkinlikteki tüm hikayelerini çek
                final userId = story['user_id'];
                if (userId != null && widget.event != null) {
                  final userStories = await _apiService.getUserStories(widget.event!.id, userId);
                  if (userStories.isNotEmpty && mounted) {
                    // Tıklanan hikayenin index'ini bul
                    int initialIndex = 0;
                    for (int i = 0; i < userStories.length; i++) {
                      if (userStories[i]['id'] == story['id']) {
                        initialIndex = i;
                        break;
                      }
                    }
                    
                    // Story viewer modal'ı aç
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => StoryViewerModal(
                          stories: userStories,
                          initialIndex: initialIndex,
                          event: widget.event!,
                        ),
                        fullscreenDialog: true,
                      ),
                    ).then((_) {
                      // Modal kapandığında refresh yap
                      _refreshData();
                    });
                  } else {
                    if (mounted) {
                      ScaffoldMessenger.of(context).showSnackBar(
                        const SnackBar(
                          content: Text('Hikaye bulunamadı'),
                          backgroundColor: AppColors.error,
                        ),
                      );
                    }
                  }
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text('Hikaye açılırken hata: $e'),
                      backgroundColor: AppColors.error,
                    ),
                  );
                }
              }
            },
          ),
        );
      },
    );
  }

  Widget _buildParticipantsTab() {
    final currentUser = Provider.of<AuthProvider>(context, listen: false).user;
    final userRole = widget.event?.userRole;
    
    // Check if user can manage participants
    // Süper Admin, Moderatör ve Yetkili Kullanıcı katılımcıları yönetebilir
    final canManageParticipants = userRole != null && 
        ['admin', 'moderator', 'yetkili_kullanici'].contains(userRole);
    
    if (!canManageParticipants) {
      // ✅ Normal katılımcılar katılımcıları görebilir ama yönetemez
      return FutureBuilder<List<Map<String, dynamic>>>(
        key: ValueKey<int>(_participantsRefreshKey), // ✅ Refresh key ile yeniden yükle
        future: _apiService.getParticipants(widget.event!.id),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator(color: AppColors.primary));
          }
          
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(50),
                child: Column(
                  children: [
                    const Icon(Icons.error_outline, size: 80, color: Colors.red),
                    const SizedBox(height: 20),
                    Text('Katılımcılar yüklenirken hata: ${snapshot.error}', style: const TextStyle(fontSize: 16, color: Colors.red), textAlign: TextAlign.center),
                  ],
                ),
              ),
            );
          }
          
          final participants = snapshot.data ?? [];
          
          if (participants.isEmpty) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(50),
                child: Column(
                  children: [
                    Icon(Icons.people_outline, size: 80, color: Colors.grey),
                    SizedBox(height: 20),
                    Text('Henüz katılımcı yok', style: TextStyle(fontSize: 18, color: Colors.grey)),
                  ],
                ),
              ),
            );
          }
          
          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: participants.length,
            itemBuilder: (context, index) {
              final participant = participants[index];
              final isCurrentUser = participant['id'] == currentUser?.id;
              
              return Card(
                margin: const EdgeInsets.only(bottom: 12),
                child: ListTile(
                  // ✅ Normal katılımcı için profil görüntüleme özelliği
                  onTap: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(
                        builder: (context) => ProfileScreen(
                          targetUserId: participant['id'],
                          targetUserName: participant['name'],
                        ),
                      ),
                    );
                  },
                  leading: CircleAvatar(
                    backgroundColor: AppColors.primary.withOpacity(0.1),
                    child: participant['avatar'] != null
                        ? ClipOval(
                            child: Image.network(
                              participant['avatar'],
                              fit: BoxFit.cover,
                              width: 40,
                              height: 40,
                              errorBuilder: (context, error, stackTrace) {
                                return const Icon(
                                  Icons.person,
                                  color: AppColors.primary,
                                );
                              },
                            ),
                          )
                        : const Icon(
                            Icons.person,
                            color: AppColors.primary,
                          ),
                  ),
                  title: Text(
                    participant['name'] ?? 'Kullanıcı',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${participant['media_count']} medya'),
                      Text(
                        'Rol: ${_getRoleDisplayName(participant['role'])}',
                        style: TextStyle(
                          color: _getRoleColor(participant['role']),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                  trailing: isCurrentUser
                      ? const Text(
                          'Siz',
                          style: TextStyle(
                            color: AppColors.primary,
                            fontWeight: FontWeight.bold,
                          ),
                        )
                      : null, // ✅ Normal katılımcı için 3 nokta yok
                ),
              );
            },
          );
        },
      );
    }
    
           return FutureBuilder<List<Map<String, dynamic>>>(
             key: ValueKey<int>(_participantsRefreshKey), // ✅ Refresh key ile yeniden yükle
             future: _apiService.getParticipants(widget.event!.id), // ✅ Her seferinde fresh data
             builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(
            child: CircularProgressIndicator(color: AppColors.primary),
          );
        }
        
        if (snapshot.hasError) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(50),
              child: Column(
                children: [
                  const Icon(
                    Icons.error_outline,
                    size: 80,
                    color: Colors.red,
                  ),
                  const SizedBox(height: 20),
                  Text(
                    'Katılımcılar yüklenirken hata: ${snapshot.error}',
                    style: const TextStyle(
                      fontSize: 16,
                      color: Colors.red,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),
          );
        }
        
        final participants = snapshot.data ?? [];
        
        if (participants.isEmpty) {
          return const Center(
            child: Padding(
              padding: EdgeInsets.all(50),
              child: Column(
                children: [
                  Icon(
                    Icons.people_outline,
                    size: 80,
                    color: Colors.grey,
                  ),
                  SizedBox(height: 20),
                  Text(
                    'Henüz katılımcı yok',
                    style: TextStyle(
                      fontSize: 18,
                      color: Colors.grey,
                    ),
                  ),
                ],
              ),
            ),
          );
        }
        
        return ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: participants.length,
          itemBuilder: (context, index) {
            final participant = participants[index];
            final isCurrentUser = participant['id'] == currentUser?.id;
            
            final isBanned = participant['status'] == 'yasakli';
            
            return Card(
              margin: const EdgeInsets.only(bottom: 12),
              color: isBanned ? Colors.red.withOpacity(0.1) : null, // ✅ Yasaklanan kullanıcı için kırmızı arka plan
              child: ListTile(
                onTap: isBanned ? null : () {
                  // Yetkisi olan kullanıcılar modal açabiliyor
                  final currentUserPermissions = widget.event?.userPermissions as Map<String, dynamic>? ?? {};
                  bool canManagePermissions = userRole == 'admin' || 
                                             userRole == 'moderator' || 
                                             currentUserPermissions['yetki_duzenleyebilir'] == true ||
                                             currentUserPermissions['baska_kullanici_yetki_degistirebilir'] == true;
                  bool canManageStatus = currentUserPermissions['kullanici_engelleyebilir'] == true ||
                                        currentUserPermissions['baska_kullanici_yasaklayabilir'] == true;
                  
                  if (canManagePermissions || canManageStatus) {
                    _showParticipantActionModal(participant, userRole);
                  }
                },
                leading: CircleAvatar(
                  backgroundColor: isBanned ? Colors.red.withOpacity(0.1) : AppColors.primary.withOpacity(0.1), // ✅ Yasaklanan kullanıcı için kırmızı avatar
                  child: participant['avatar'] != null
                      ? ClipOval(
                          child: Image.network(
                            participant['avatar'],
                            fit: BoxFit.cover,
                            width: 40,
                            height: 40,
                            errorBuilder: (context, error, stackTrace) {
                              return Icon(
                                Icons.person,
                                color: isBanned ? Colors.red : AppColors.primary,
                              );
                            },
                          ),
                        )
                      : Icon(
                          Icons.person,
                          color: isBanned ? Colors.red : AppColors.primary, // ✅ Yasaklanan kullanıcı için kırmızı ikon
                        ),
                ),
                title: Text(
                  participant['name'] ?? 'Kullanıcı',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: isBanned ? Colors.red : null, // ✅ Yasaklanan kullanıcı için kırmızı metin
                  ),
                ),
                subtitle: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('${participant['media_count']} medya'),
                    Text(
                      'Rol: ${_getRoleDisplayName(participant['role'])}',
                      style: TextStyle(
                        color: _getRoleColor(participant['role']),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    if (isBanned) // ✅ Yasaklanan kullanıcı için durum göster
                      const Text(
                        'Durum: Yasaklı',
                        style: TextStyle(
                          color: Colors.red,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                  ],
                ),
                trailing: isCurrentUser
                    ? const Text(
                        'Siz',
                        style: TextStyle(
                          color: AppColors.primary,
                          fontWeight: FontWeight.bold,
                        ),
                      )
                    : Builder(
                        builder: (context) {
                          final menuItems = _buildParticipantMenuItems(participant, userRole);
                          // Eğer menu item yoksa (yetki yoksa) boş container göster
                          if (menuItems.isEmpty) {
                            return const SizedBox.shrink();
                          }
                          return PopupMenuButton<String>(
                            onSelected: (value) {
                              if (value == 'yetki_ver') {
                                _showPermissionGrantModal(participant);
                              } else if (value == 'yasakla' || value == 'aktif') {
                                _handleParticipantAction(participant, value);
                              }
                            },
                            itemBuilder: (context) => menuItems,
                          );
                        },
                      ),
              ),
            );
          },
        );
      },
    );
  }
  
  // ✅ Participant Action Modal
  void _showParticipantActionModal(Map<String, dynamic> participant, String? userRole) {
    final participantRole = participant['role'];
    final participantStatus = participant['status'];
    final currentUserPermissions = widget.event?.userPermissions as Map<String, dynamic>? ?? {};
    
    // Yetki kontrolü
    bool canManagePermissions = userRole == 'admin' || 
                               userRole == 'moderator' || 
                               currentUserPermissions['yetki_duzenleyebilir'] == true ||
                               currentUserPermissions['baska_kullanici_yetki_degistirebilir'] == true;
    
    bool canManageStatus = currentUserPermissions['kullanici_engelleyebilir'] == true ||
                          currentUserPermissions['baska_kullanici_yasaklayabilir'] == true;
    
    // Eğer yetki yoksa modal açma
    if (!canManagePermissions && !canManageStatus) {
      return;
    }
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(participant['name'] ?? 'Katılımcı'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Rol: ${_getRoleDisplayName(participant['role'] ?? participantRole)}'),
            Text('Durum: ${participantStatus == 'aktif' ? 'Aktif' : 'Yasaklı'}'),
            const SizedBox(height: 16),
            Text('Ne yapmak istiyorsunuz?'),
          ],
        ),
        actions: [
          // ✅ Sıra: Yasakla/Yasak Kaldır -> İzin Düzenle -> İptal
          if (canManageStatus)
          TextButton(
              onPressed: () {
                Navigator.of(context).pop();
                _handleParticipantAction(participant, participantStatus == 'aktif' ? 'yasakla' : 'aktif');
              },
              style: TextButton.styleFrom(
                foregroundColor: participantStatus == 'aktif' ? Colors.red : Colors.green,
              ),
              child: Text(participantStatus == 'aktif' ? 'Yasakla' : 'Yasağı Kaldır'),
          ),
          if (canManagePermissions)
            TextButton(
              onPressed: () {
                Navigator.of(context).pop();
                _showPermissionGrantModal(participant);
              },
              child: const Text('İzin Düzenle'),
            ),
            TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('İptal'),
            ),
        ],
      ),
    );
  }
  
  // ✅ Permission Grant Modal'ı ayrı metod
  void _showPermissionGrantModal(Map<String, dynamic> participant) {
    // ✅ Ana widget'ın context'ini kaydet (modal kapandıktan sonra kullanmak için)
    final mainContext = context;
    final mainScaffoldMessenger = ScaffoldMessenger.of(mainContext);
    
    showDialog(
      context: context,
      builder: (dialogContext) => PermissionGrantModal(
        participantName: participant['name'],
        participantId: participant['id'],
        eventId: widget.event!.id,
        onPermissionsGranted: (permissions) async {
          // ✅ Modal'ı hemen kapat
          Navigator.of(dialogContext).pop();
          
          try {
            final result = await _apiService.grantPermissions(
              eventId: widget.event!.id,
              targetUserId: participant['id'],
              permissions: permissions,
            );
            
            print('✅ Grant Permissions Result: $result');
            
            // ✅ Modal kapandıktan sonra snackbar göster (ana context kullan)
            if (mounted) {
              mainScaffoldMessenger.showSnackBar(
                SnackBar(
                  content: Text('${participant['name']} için yetkiler güncellendi'),
                  backgroundColor: AppColors.success,
                  duration: const Duration(seconds: 2),
                ),
              );
              
              // ✅ Sadece participants listesini yenile - tüm event data'yı yenileme
              // Kısa bir gecikme sonrası participants listesini yenile (backend'in güncellemesi için)
              await Future.delayed(const Duration(milliseconds: 300));
              
              // ✅ Participants listesini yenile - refresh key'i değiştir
              if (mounted) {
                setState(() {
                  _participantsRefreshKey++; // ✅ FutureBuilder'ı yeniden çalıştır
                });
                
                print('✅ Participants list refreshed, key: $_participantsRefreshKey');
                
                // ✅ Bir kez daha yenile (backend'in tam güncellemesi için)
                await Future.delayed(const Duration(milliseconds: 500));
                if (mounted) {
                  setState(() {
                    _participantsRefreshKey++; // ✅ İkinci refresh
                  });
                  print('✅ Participants list refreshed again, key: $_participantsRefreshKey');
                }
              }
            }
          } catch (e) {
            print('❌ Grant Permissions Error: $e');
            
            // ✅ Modal kapandıktan sonra snackbar göster (ana context kullan)
            if (mounted) {
              mainScaffoldMessenger.showSnackBar(
                SnackBar(
                  content: Text('Yetki güncelleme başarısız: $e'),
                  backgroundColor: AppColors.error,
                  duration: const Duration(seconds: 3),
                ),
              );
            }
          }
        },
      ),
    );
  }
  
  String _getRoleDisplayName(String role) {
    switch (role) {
      case 'admin':
        return 'Yönetici';
      case 'moderator':
        return 'Moderatör';
      case 'yetkili_kullanici':
        return 'Yetkili Katılımcı'; // ✅ Değiştirildi
      default:
        return 'Katılımcı';
    }
  }
  
  Color _getRoleColor(String role) {
    switch (role) {
      case 'admin':
        return Colors.red;
      case 'moderator':
        return Colors.orange;
      case 'yetkili_kullanici':
        return Colors.blue;
      default:
        return Colors.grey;
    }
  }
  
         List<PopupMenuEntry<String>> _buildParticipantMenuItems(Map<String, dynamic> participant, String? userRole) {
           final participantRole = participant['role'];
           final participantStatus = participant['status'];
           final currentUserPermissions = widget.event?.userPermissions as Map<String, dynamic>? ?? {};
           
           List<PopupMenuEntry<String>> items = [];
           
           // Yetki kontrolü: Admin, Moderator veya "Yetki Düzenleyebilir" yetkisi olanlar
           bool canManagePermissions = userRole == 'admin' || 
                                      userRole == 'moderator' || 
                                      currentUserPermissions['yetki_duzenleyebilir'] == true ||
                                      currentUserPermissions['baska_kullanici_yetki_degistirebilir'] == true;
           
           // Durum kontrolü: Yasaklı kullanıcıları yönetme (sadece yetkilerini al)
           bool canManageStatus = currentUserPermissions['kullanici_engelleyebilir'] == true ||
                                 currentUserPermissions['baska_kullanici_yasaklayabilir'] == true;
    
    // ✅ Sıra: Yasakla/Yasak Kaldır -> İzin Düzenle -> İptal
    
    // 1. Durum değiştirme (Yasakla / Yasak Kaldır)
    if (canManageStatus) {
      if (participantStatus == 'aktif') {
        items.add(const PopupMenuItem(
          value: 'yasakla',
          child: Text('Yasakla', style: TextStyle(color: Colors.red)),
        ));
      } else if (participantStatus == 'yasakli') {
        items.add(const PopupMenuItem(
          value: 'aktif',
          child: Text('Yasağı Kaldır', style: TextStyle(color: Colors.green)),
        ));
      }
    }
    
    // 2. Yetki düzenleme
    if (canManagePermissions) {
      // Admin ve Moderator hariç herkesin yetkilerini düzenleyebilir
      if (participantRole != 'admin' && participantRole != 'moderator') {
        items.add(const PopupMenuItem(
          value: 'yetki_ver',
          child: Text('İzin Düzenle'),
        ));
      }
    }
    
    return items;
  }
  
  // ✅ Kullanıcının yasaklanıp yasaklanmadığını periyodik kontrol et
  void _checkUserBanStatus() async {
    if (widget.event == null) return;
    
    try {
      final participants = await _apiService.getParticipants(widget.event!.id);
      final currentUser = Provider.of<AuthProvider>(context, listen: false).user;
      
      // Kullanıcı katılımcılar arasında var mı ve aktif mi kontrol et
      Map<String, dynamic>? participant;
      try {
        participant = participants.firstWhere(
          (p) => p['id'] == currentUser?.id,
        );
      } catch (e) {
        participant = null;
      }
      
      if (participant == null || participant['status'] == 'yasakli') {
        // ✅ Kullanıcı yasaklanmış veya etkinlikte değil, ana ekrana dön
        if (mounted) {
          Navigator.of(context).pushNamedAndRemoveUntil(
            '/',
            (route) => false,
          );
        }
      }
    } catch (e) {
      // ✅ Network hatası olduğunda kullanıcıyı yönlendirme - sadece logla
      // SocketException, ClientException gibi network hatalarında devam et
      if (e.toString().contains('SocketException') || 
          e.toString().contains('Failed host lookup') ||
          e.toString().contains('ClientException')) {
        print('⚠️ Network hatası - yasaklanan kullanıcı kontrolü atlandı: $e');
        return; // Network hatasında işlemi durdur, kullanıcıyı yönlendirme
      }
      print('❌ Yasaklanan kullanıcı kontrolü hatası: $e');
    }
  }

  // ✅ Real-time veri yenileme metodu
  Future<void> _refreshDataAfterDelete() async {
    // ✅ Medya silindiğinde cache bypass ile hemen güncelle
    if (widget.event == null) return;
    
    try {
      print('🔄 Refreshing data after delete (cache bypass)...');
      
      final currentLoadedCount = _media.length;
      final limitToFetch = currentLoadedCount > 0 ? (currentLoadedCount + 5) : _pageSize;
      
      // ✅ Cache bypass ile medya çek
      final mediaData = await _apiService.getMedia(widget.event!.id, page: 1, limit: limitToFetch, bypassCache: true);
      final newMedia = (mediaData['media'] as List<dynamic>?)?.cast<Map<String, dynamic>>() ?? [];
      
      // ✅ Hikayeleri de çek
      final storiesData = await _apiService.getStories(widget.event!.id);
      
      // ✅ Hemen UI'ı güncelle (silinen medya kalkacak)
      setState(() {
        _media = newMedia;
        _stories = storiesData;
        _hasMoreMedia = (mediaData['pagination']?['has_more'] ?? false);
        _currentPage = (newMedia.length / _pageSize).ceil();
      });
      
      print('✅ Media deleted. Updated list: ${_media.length} items');
    } catch (e) {
      print('❌ Error refreshing after delete: $e');
    }
  }

  Future<void> _refreshData() async {
    if (widget.event == null || _isLoading) return;
    
    try {
      print('🔄 Refreshing data...');
      
      // ✅ Mevcut yüklenmiş medya sayısını koru + fazladan çek (pagination sorununu çözmek için)
      // Yeni yüklenen medyalar da dahil olsun diye +5 ekleyelim
      final currentLoadedCount = _media.length;
      final limitToFetch = currentLoadedCount > 0 ? (currentLoadedCount + 5) : _pageSize;
      
      print('📊 Current loaded media: $currentLoadedCount, fetching: $limitToFetch');
      
      // ✅ Her refresh'te pagination'ı başa sar ama mevcut yüklenmiş sayı + yeni eklenenler kadar çek
      _currentPage = 1;
      
      // Medya sayısını kontrol et - mevcut + yeni yüklenebilecek medyaları çek
      final mediaData = await _apiService.getMedia(widget.event!.id, page: 1, limit: limitToFetch);
      final newMedia = (mediaData['media'] as List<dynamic>?)?.cast<Map<String, dynamic>>() ?? [];
      
      // Hikaye sayısını kontrol et
      final storiesData = await _apiService.getStories(widget.event!.id);
      
      // ✅ Her zaman medya detaylarını güncelle (beğeni/yorum sayıları için)
      bool hasChanges = false;
      
      // Medya sayısı değişmişse
      if (newMedia.length != _media.length) {
        hasChanges = true;
        print('📱 Media count changed: ${_media.length} → ${newMedia.length}');
      }
      
      // Hikaye sayısı değişmişse
      if (storiesData.length != _stories.length) {
        hasChanges = true;
        print('📱 Stories count changed: ${_stories.length} → ${storiesData.length}');
      }
      
      // ✅ Mevcut medyaların beğeni/yorum sayılarını ID bazlı kontrol et
      final mediaMap = <int, Map<String, dynamic>>{};
      for (var media in _media) {
        mediaMap[media['id']] = media;
      }
      
      for (var newMediaItem in newMedia) {
        final mediaId = newMediaItem['id'];
        final oldMedia = mediaMap[mediaId];
        
        if (oldMedia != null) {
          // Beğeni veya yorum sayıları değişmişse güncelle
        if (oldMedia['likes'] != newMediaItem['likes'] || 
              oldMedia['comments'] != newMediaItem['comments'] ||
              oldMedia['is_liked'] != newMediaItem['is_liked']) {
          hasChanges = true;
            print('📱 Media $mediaId stats changed: likes ${oldMedia['likes']}→${newMediaItem['likes']}, comments ${oldMedia['comments']}→${newMediaItem['comments']}, is_liked ${oldMedia['is_liked']}→${newMediaItem['is_liked']}');
            
            // ✅ Local media'yı güncelle
            oldMedia['likes'] = newMediaItem['likes'];
            oldMedia['comments'] = newMediaItem['comments'];
            oldMedia['is_liked'] = newMediaItem['is_liked'];
          }
        }
      }
      
      // ✅ Medya listesi veya hikaye listesi değişmişse güncelle
      if (hasChanges || newMedia.length != _media.length || storiesData.length != _stories.length) {
        print('📱 Content changes detected! Updating UI...');
        setState(() {
          // ✅ Medyayı doğrudan ilk sayfayla güncelle (pagination resetlendi)
          _media = newMedia;
          _stories = storiesData;
          _hasMoreMedia = (mediaData['pagination']?['has_more'] ?? false);
          // ✅ Current page'i güncelle (çekilen medya sayısına göre)
          _currentPage = (newMedia.length / _pageSize).ceil();
        });
        print('✅ UI updated. Media: ${_media.length}, Stories: ${_stories.length}, Current Page: $_currentPage');
      }
    } catch (e) {
      print('❌ Error refreshing data: $e');
      
      // ✅ Eğer "Bu etkinliğe katılımcı değilsiniz" hatası ise kullanıcıyı ana ekrana yönlendir
      if (e.toString().contains('Bu etkinliğe katılımcı değilsiniz') || 
          e.toString().contains('Bu etkinliğe katılımcı değilsiniz')) {
        print('🚫 Kullanıcı etkinlikten çıkarıldı (refresh), ana ekrana yönlendiriliyor...');
        if (mounted) {
          Navigator.of(context).pushNamedAndRemoveUntil(
            '/',
            (route) => false,
          );
          return;
        }
      }
    }
  }
  
  // ✅ Yasaklanan kullanıcının diğer cihazlardaki event sayfalarını kontrol et
  void _checkBannedUserAccess(int bannedUserId) async {
    try {
      // Yasaklanan kullanıcının bu event'teki durumunu kontrol et
      final participants = await _apiService.getParticipants(widget.event!.id);
      Map<String, dynamic>? bannedParticipant;
      try {
        bannedParticipant = participants.firstWhere(
          (p) => p['id'] == bannedUserId,
        );
      } catch (e) {
        bannedParticipant = null;
      }
      
      if (bannedParticipant != null && bannedParticipant['status'] == 'yasakli') {
        // Yasaklanan kullanıcı hala event'te aktifse, event sayfalarını yenile
        print('🚫 Yasaklanan kullanıcı ${bannedParticipant['name']} event sayfalarından atılmalı');
        
        // Bu metod sadece bilgilendirme amaçlı
        // Gerçek kontrol _loadEventData() metodunda yapılıyor
      }
    } catch (e) {
      // ✅ Network hatası olduğunda sadece logla - işlemi durdurma
      if (e.toString().contains('SocketException') || 
          e.toString().contains('Failed host lookup') ||
          e.toString().contains('ClientException')) {
        print('⚠️ Network hatası - yasaklanan kullanıcı erişim kontrolü atlandı: $e');
        return;
      }
      print('❌ Yasaklanan kullanıcı kontrolü hatası: $e');
    }
  }
  
  void _handleParticipantAction(Map<String, dynamic> participant, String action) async {
    try {
      if (action == 'yasakla') {
        await _apiService.updateParticipant(
          eventId: widget.event!.id,
          targetUserId: participant['id'],
          newStatus: 'yasakli',
        );
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('${participant['name']} yasaklandı'),
              backgroundColor: AppColors.success,
            ),
          );
          
          // ✅ Real-time güncelleme için participants listesini yeniden yükle
          setState(() {
            // Force rebuild of participants tab
          });
          
          // ✅ Event data'yı da yeniden yükle
          await _loadEventData();
          
          // ✅ Yasaklanan kullanıcının diğer cihazlardaki event sayfalarını kontrol et
          _checkBannedUserAccess(participant['id']);
        }
      } else if (action == 'aktif') {
        await _apiService.updateParticipant(
          eventId: widget.event!.id,
          targetUserId: participant['id'],
          newStatus: 'aktif',
        );
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('${participant['name']} aktif edildi'),
              backgroundColor: AppColors.success,
            ),
          );
          
          // ✅ Sadece participants listesini yenile - tüm event data'yı yenileme
          await Future.delayed(const Duration(milliseconds: 300));
          
          // ✅ Participants listesini de yeniden yükle - refresh key'i değiştir
          if (mounted) {
          setState(() {
              _participantsRefreshKey++; // ✅ FutureBuilder'ı yeniden çalıştır
          });
          }
        }
      } else if (action == 'yetkili_kullanici' || action == 'kullanici') {
        await _apiService.updateParticipant(
          eventId: widget.event!.id,
          targetUserId: participant['id'],
          newRole: action,
        );
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('${participant['name']} rolü güncellendi'),
              backgroundColor: AppColors.success,
            ),
          );
          
          // ✅ Sadece participants listesini yenile - tüm event data'yı yenileme
          await Future.delayed(const Duration(milliseconds: 300));
          
          // ✅ Participants listesini de yeniden yükle - refresh key'i değiştir
          if (mounted) {
            setState(() {
              _participantsRefreshKey++; // ✅ FutureBuilder'ı yeniden çalıştır
            });
          }
        }
      }
      
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('İşlem başarısız: $e'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }
}