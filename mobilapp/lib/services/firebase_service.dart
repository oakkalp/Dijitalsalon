import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_auth/firebase_auth.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:flutter_local_notifications/flutter_local_notifications.dart';
import 'package:digimobil_new/services/firebase_background_service.dart';

class FirebaseService {
  static final FirebaseService _instance = FirebaseService._internal();
  factory FirebaseService() => _instance;
  FirebaseService._internal();

  final FirebaseAuth _auth = FirebaseAuth.instance;
  final GoogleSignIn _googleSignIn = GoogleSignIn();
  final FirebaseMessaging _messaging = FirebaseMessaging.instance;
  final FlutterLocalNotificationsPlugin _localNotifications = FlutterLocalNotificationsPlugin();

  String? _fcmToken;
  String? get fcmToken => _fcmToken;
  
  // ✅ Navigation callback (bildirime tıklayınca yönlendirme için)
  Function(Map<String, dynamic>)? onNotificationTap;

  // ✅ Firebase başlatma
  static Future<void> initialize() async {
    try {
      // ✅ Firebase zaten başlatılmış mı kontrol et (hot restart için)
      try {
        Firebase.app(); // Eğer varsa exception fırlatmaz
        if (kDebugMode) {
          print('✅ Firebase already initialized');
        }
      } catch (e) {
        // Firebase başlatılmamış, başlat
        await Firebase.initializeApp(
          options: const FirebaseOptions(
            apiKey: 'AIzaSyCq0bSFvHPnU-xN5dqWZevYOuMnrVF1Z_E',
            appId: '1:839706849375:android:bd794e5e6b0d84a166ebea',
            messagingSenderId: '839706849375',
            projectId: 'dijital-salon',
            storageBucket: 'dijital-salon.firebasestorage.app',
          ),
        );
        
        if (kDebugMode) {
          print('✅ Firebase initialized successfully');
        }
      }
      
      // ✅ Background message handler'ı kaydet (Android için) - sadece bir kez
      try {
        FirebaseMessaging.onBackgroundMessage(firebaseMessagingBackgroundHandler);
      } catch (e) {
        // Zaten kayıtlı olabilir, sorun değil
        if (kDebugMode) {
          print('⚠️ Background message handler already registered');
        }
      }
      
      // ✅ Local notifications initialize
      final instance = FirebaseService();
      await instance._initializeLocalNotifications();
      
      // ✅ Push notification handler'ları kur
      await instance._setupNotificationHandlers();
      
    } catch (e) {
      if (kDebugMode) {
        // ✅ Duplicate app hatası ise sadece uyarı ver, hata değil
        if (e.toString().contains('duplicate-app')) {
          print('⚠️ Firebase already initialized (this is normal on hot restart)');
        } else {
          print('❌ Firebase initialization error: $e');
        }
      }
    }
  }
  
  // ✅ Local notifications initialize
  Future<void> _initializeLocalNotifications() async {
    const androidSettings = AndroidInitializationSettings('@mipmap/ic_launcher');
    const iosSettings = DarwinInitializationSettings(
      requestAlertPermission: true,
      requestBadgePermission: true,
      requestSoundPermission: true,
    );
    const initSettings = InitializationSettings(
      android: androidSettings,
      iOS: iosSettings,
    );
    
    await _localNotifications.initialize(
      initSettings,
      onDidReceiveNotificationResponse: (NotificationResponse response) {
        if (kDebugMode) {
          print('📱 Local notification tapped: ${response.payload}');
        }
        
        if (response.payload != null) {
          try {
            final data = jsonDecode(response.payload!);
            onNotificationTap?.call(data);
          } catch (e) {
            if (kDebugMode) {
              print('❌ Error parsing notification payload: $e');
            }
          }
        }
      },
    );
    
    // ✅ Android notification channel oluştur
    const androidChannel = AndroidNotificationChannel(
      'high_importance_channel',
      'Bildirimler',
      description: 'Yüksek öncelikli bildirimler için kanal',
      importance: Importance.high,
    );
    
    await _localNotifications
        .resolvePlatformSpecificImplementation<AndroidFlutterLocalNotificationsPlugin>()
        ?.createNotificationChannel(androidChannel);
  }
  
  // ✅ Push notification handler'ları kur
  Future<void> _setupNotificationHandlers() async {
    // ✅ Uygulama açıkken bildirim geldiğinde (Foreground)
    FirebaseMessaging.onMessage.listen((RemoteMessage message) {
      if (kDebugMode) {
        print('📱 Foreground notification received: ${message.notification?.title}');
      }
      
      _showLocalNotification(message);
    });
    
    // ✅ Uygulama kapalıyken bildirime tıklandığında (Background/Quit)
    FirebaseMessaging.onMessageOpenedApp.listen((RemoteMessage message) {
      if (kDebugMode) {
        print('📱 Notification opened app: ${message.notification?.title}');
      }
      
      _handleNotificationTap(message.data);
    });
    
    // ✅ Uygulama kapalıyken bildirime tıklandığında (Initial message)
    RemoteMessage? initialMessage = await _messaging.getInitialMessage();
    if (initialMessage != null) {
      if (kDebugMode) {
        print('📱 Initial notification: ${initialMessage.notification?.title}');
      }
      
      // Navigator hazır olana kadar bekle
      WidgetsBinding.instance.addPostFrameCallback((_) {
        _handleNotificationTap(initialMessage.data);
      });
    }
  }
  
  // ✅ Lokal bildirim göster (Foreground için)
  Future<void> _showLocalNotification(RemoteMessage message) async {
    final notification = message.notification;
    final android = message.notification?.android;
    
    if (notification == null) return;
    
    final androidDetails = AndroidNotificationDetails(
      'high_importance_channel',
      'Bildirimler',
      channelDescription: 'Yüksek öncelikli bildirimler için kanal',
      importance: Importance.high,
      priority: Priority.high,
      icon: android?.smallIcon ?? '@mipmap/ic_launcher',
    );
    
    const iosDetails = DarwinNotificationDetails(
      presentAlert: true,
      presentBadge: true,
      presentSound: true,
    );
    
    final details = NotificationDetails(
      android: androidDetails,
      iOS: iosDetails,
    );
    
    await _localNotifications.show(
      notification.hashCode,
      notification.title ?? 'Yeni Bildirim',
      notification.body ?? '',
      details,
      payload: jsonEncode(message.data), // ✅ Data'yı payload olarak gönder
    );
  }
  
  // ✅ Bildirime tıklayınca yönlendirme
  void _handleNotificationTap(Map<String, dynamic> data) {
    if (kDebugMode) {
      print('📱 Handling notification tap: $data');
    }
    
    onNotificationTap?.call(data);
  }

  // ✅ FCM Token al ve backend'e kaydet
  Future<String?> getFCMToken() async {
    try {
      _fcmToken = await _messaging.getToken();
      if (kDebugMode) {
        print('📱 FCM Token: $_fcmToken');
      }
      
      // Token'ı backend'e kaydet
      if (_fcmToken != null) {
        await _saveFCMTokenToBackend(_fcmToken!);
      }
      
      return _fcmToken;
    } catch (e) {
      if (kDebugMode) {
        print('❌ FCM Token error: $e');
      }
      return null;
    }
  }

  // ✅ Token'ı backend'e kaydet
  Future<void> _saveFCMTokenToBackend(String token) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final sessionKey = prefs.getString('session_key');
      
      if (sessionKey == null) return;

      final response = await http.post(
        Uri.parse('https://dijitalsalon.cagapps.app/digimobiapi/save_fcm_token.php'),
        headers: {
          'Content-Type': 'application/json',
          'Cookie': 'PHPSESSID=$sessionKey',
        },
        body: jsonEncode({'fcm_token': token}),
      );

      if (kDebugMode) {
        print('FCM Token Backend Response: ${response.statusCode}');
      }
    } catch (e) {
      if (kDebugMode) {
        print('❌ FCM Token save error: $e');
      }
    }
  }

  // ✅ Notification permissions
  Future<void> requestNotificationPermissions() async {
    try {
      NotificationSettings settings = await _messaging.requestPermission(
        alert: true,
        announcement: false,
        badge: true,
        carPlay: false,
        criticalAlert: false,
        provisional: false,
        sound: true,
      );

      if (kDebugMode) {
        print('Notification permission: ${settings.authorizationStatus}');
      }

      if (settings.authorizationStatus == AuthorizationStatus.authorized) {
        await getFCMToken();
      }
    } catch (e) {
      if (kDebugMode) {
        print('❌ Notification permission error: $e');
      }
    }
  }

  // ✅ Google Sign-In
  Future<Map<String, dynamic>?> signInWithGoogle() async {
    try {
      final GoogleSignInAccount? googleUser = await _googleSignIn.signIn();
      if (googleUser == null) return null; // Kullanıcı iptal etti

      final GoogleSignInAuthentication googleAuth = await googleUser.authentication;
      final credential = GoogleAuthProvider.credential(
        accessToken: googleAuth.accessToken,
        idToken: googleAuth.idToken,
      );

      final UserCredential userCredential = await _auth.signInWithCredential(credential);
      final User? user = userCredential.user;

      if (user != null) {
        // Backend'e kullanıcı bilgilerini gönder
        return await _socialLoginToBackend(
          email: user.email!,
          name: user.displayName ?? '',
          provider: 'google',
          providerId: user.uid,
          photoUrl: user.photoURL,
        );
      }
    } catch (e) {
      if (kDebugMode) {
        print('❌ Google Sign-In error: $e');
      }
    }
    return null;
  }

  // ✅ Apple Sign-In
  Future<Map<String, dynamic>?> signInWithApple() async {
    try {
      final appleCredential = await SignInWithApple.getAppleIDCredential(
        scopes: [
          AppleIDAuthorizationScopes.email,
          AppleIDAuthorizationScopes.fullName,
        ],
      );

      final oAuthProvider = OAuthProvider('apple.com');
      final credential = oAuthProvider.credential(
        idToken: appleCredential.identityToken,
        accessToken: appleCredential.authorizationCode,
      );

      final UserCredential userCredential = await _auth.signInWithCredential(credential);
      final User? user = userCredential.user;

      if (user != null) {
        String name = '';
        if (appleCredential.givenName != null || appleCredential.familyName != null) {
          name = '${appleCredential.givenName ?? ''} ${appleCredential.familyName ?? ''}'.trim();
        }

        return await _socialLoginToBackend(
          email: user.email ?? appleCredential.email ?? '',
          name: name.isNotEmpty ? name : user.displayName ?? 'Apple User',
          provider: 'apple',
          providerId: user.uid,
          photoUrl: null,
        );
      }
    } catch (e) {
      if (kDebugMode) {
        print('❌ Apple Sign-In error: $e');
      }
    }
    return null;
  }

  // ✅ Backend'e sosyal login bilgilerini gönder
  Future<Map<String, dynamic>?> _socialLoginToBackend({
    required String email,
    required String name,
    required String provider,
    required String providerId,
    String? photoUrl,
  }) async {
    try {
      final response = await http.post(
        Uri.parse('https://dijitalsalon.cagapps.app/digimobiapi/social_login.php'),
        headers: {'Content-Type': 'application/json'},
        body: jsonEncode({
          'email': email,
          'name': name,
          'provider': provider,
          'provider_id': providerId,
          'photo_url': photoUrl,
        }),
      );

      if (response.statusCode == 200) {
        final data = jsonDecode(response.body);
        if (data['success'] == true) {
          // Session'ı kaydet
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString('session_key', data['session_key']);
          await prefs.setInt('user_id', data['user']['id']);
          await prefs.setString('user_email', data['user']['email']);
          await prefs.setString('user_name', data['user']['name']);
          
          // FCM Token'ı backend'e kaydet
          if (_fcmToken != null) {
            await _saveFCMTokenToBackend(_fcmToken!);
          }

          return data;
        }
      }
    } catch (e) {
      if (kDebugMode) {
        print('❌ Social login backend error: $e');
      }
    }
    return null;
  }

  // ✅ Sign out
  Future<void> signOut() async {
    try {
      await _googleSignIn.signOut();
      await _auth.signOut();
    } catch (e) {
      if (kDebugMode) {
        print('❌ Sign out error: $e');
      }
    }
  }
}

