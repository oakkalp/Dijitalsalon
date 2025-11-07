import 'dart:async';
import 'dart:convert';
import 'dart:ui';
import 'package:flutter/foundation.dart';
import 'package:flutter/widgets.dart';
import 'package:flutter_background_service/flutter_background_service.dart';
import 'package:flutter_background_service_android/flutter_background_service_android.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:http/http.dart' as http;
import 'dart:io';

class BackgroundUploadService {
  static final BackgroundUploadService _instance = BackgroundUploadService._internal();
  factory BackgroundUploadService() => _instance;
  BackgroundUploadService._internal();

  static const String _queueKey = 'upload_queue';
  static const int _notificationId = 888;

  Future<void> initialize() async {
    final service = FlutterBackgroundService();

    const AndroidNotificationChannel channel = AndroidNotificationChannel(
      'upload_channel',
      'Medya Yükleme',
      description: 'Medya ve hikaye yükleme işlemleri',
      importance: Importance.low,
    );

    final FlutterLocalNotificationsPlugin flutterLocalNotificationsPlugin =
        FlutterLocalNotificationsPlugin();

    await flutterLocalNotificationsPlugin
        .resolvePlatformSpecificImplementation<
            AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(channel);

    await service.configure(
      iosConfiguration: IosConfiguration(
        autoStart: false,
        onForeground: onStart,
        onBackground: onIosBackground,
      ),
      androidConfiguration: AndroidConfiguration(
        onStart: onStart,
        isForegroundMode: true,
        autoStart: false,
        autoStartOnBoot: false,
        notificationChannelId: 'upload_channel',
        initialNotificationTitle: 'DigitalSalon',
        initialNotificationContent: 'Yükleme işlemi devam ediyor...',
        foregroundServiceNotificationId: _notificationId,
      ),
    );
  }

  @pragma('vm:entry-point')
  static Future<bool> onIosBackground(ServiceInstance service) async {
    WidgetsFlutterBinding.ensureInitialized();
    DartPluginRegistrant.ensureInitialized();
    return true;
  }

  @pragma('vm:entry-point')
  static void onStart(ServiceInstance service) async {
    DartPluginRegistrant.ensureInitialized();

    if (service is AndroidServiceInstance) {
      service.on('stopService').listen((event) {
        service.stopSelf();
      });

      service.on('upload').listen((event) async {
        if (event == null) return;

        final Map<String, dynamic> data = event as Map<String, dynamic>;
        final int eventId = data['event_id'];
        final String filePath = data['file_path'];
        final String description = data['description'];
        final String type = data['type'];
        final String sessionKey = data['session_key'];

        try {
          // Update notification
          if (service is AndroidServiceInstance) {
            await service.setForegroundNotificationInfo(
              title: 'Yükleniyor...',
              content: '${type == 'media' ? 'Medya' : 'Hikaye'} yükleniyor',
            );
          }

          // Upload using HTTP directly
          final file = File(filePath);
          final fileName = filePath.split('/').last;
          
          final uri = Uri.parse(type == 'media' 
              ? 'https://dijitalsalon.cagapps.app/digimobiapi/add_media.php'
              : 'https://dijitalsalon.cagapps.app/digimobiapi/add_story.php');
          
          final request = http.MultipartRequest('POST', uri);
          request.headers['Accept'] = 'application/json';
          request.headers['Cookie'] = 'PHPSESSID=$sessionKey';
          
          request.fields['event_id'] = eventId.toString();
          request.fields['description'] = description;
          
          request.files.add(await http.MultipartFile.fromPath(
            type == 'media' ? 'media_file' : 'story_file',
            filePath,
            filename: fileName,
          ));
          
          final streamedResponse = await request.send();
          final response = await http.Response.fromStream(streamedResponse);
          
          final Map<String, dynamic> result = json.decode(response.body);

          if (result['success'] == true) {
            // Success notification
            if (service is AndroidServiceInstance) {
              await service.setForegroundNotificationInfo(
                title: 'Yükleme Tamamlandı',
                content: '${type == 'media' ? 'Medya' : 'Hikaye'} başarıyla yüklendi',
              );
            }
            
            // Send success event
            service.invoke('uploadComplete', {'success': true, 'type': type});
          } else {
            throw Exception(result['error'] ?? 'Upload failed');
          }
        } catch (e) {
          if (kDebugMode) {
            print('Background upload error: $e');
          }
          
          // Error notification
          if (service is AndroidServiceInstance) {
            await service.setForegroundNotificationInfo(
              title: 'Yükleme Başarısız',
              content: 'Hata: ${e.toString()}',
            );
          }
          
          // Send error event
          service.invoke('uploadComplete', {'success': false, 'error': e.toString()});
        }

        // Stop service after upload attempt
        await Future.delayed(const Duration(seconds: 2));
        service.stopSelf();
      });
    }
  }

  Future<void> addUploadTask({
    required int eventId,
    required String filePath,
    required String description,
    required String type,
    required String sessionKey,
  }) async {
    final service = FlutterBackgroundService();

    // Start service if not running
    final isRunning = await service.isRunning();
    if (!isRunning) {
      await service.startService();
    }

    // Send upload task
    service.invoke('upload', {
      'event_id': eventId,
      'file_path': filePath,
      'description': description,
      'type': type,
      'session_key': sessionKey,
    });
  }

  Future<void> stopService() async {
    final service = FlutterBackgroundService();
    service.invoke('stopService');
  }

  // Queue management (for offline scenarios)
  Future<void> _saveQueue(List<Map<String, dynamic>> queue) async {
    final prefs = await SharedPreferences.getInstance();
    final String queueJson = json.encode(queue);
    await prefs.setString(_queueKey, queueJson);
  }

  Future<List<Map<String, dynamic>>> _loadQueue() async {
    final prefs = await SharedPreferences.getInstance();
    final String? queueJson = prefs.getString(_queueKey);
    if (queueJson != null && queueJson.isNotEmpty) {
      final List<dynamic> decoded = json.decode(queueJson);
      return decoded.cast<Map<String, dynamic>>();
    }
    return [];
  }
}

