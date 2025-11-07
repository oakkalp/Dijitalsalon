import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:flutter/foundation.dart';

/// ✅ Android için background message handler
/// Bu fonksiyon uygulama arka plandayken bildirim geldiğinde çalışır
@pragma('vm:entry-point')
Future<void> firebaseMessagingBackgroundHandler(RemoteMessage message) async {
  if (kDebugMode) {
    print('📱 Background notification received: ${message.notification?.title}');
    print('📱 Background notification data: ${message.data}');
  }
  
  // ✅ Arka planda bildirim geldiğinde yapılacak işlemler
  // Genellikle burada lokal bildirim gösterilir veya veritabanı güncellemesi yapılır
  // Ancak navigasyon işlemleri burada yapılamaz (Flutter context yok)
}

