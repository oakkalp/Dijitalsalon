import 'package:flutter/material.dart';

/// ✅ Ortak Error Handling Utility
/// Tüm hata durumları için kullanıcı dostu mesajlar sağlar
class ErrorHandler {
  /// ✅ Kullanıcıya SnackBar ile hata göster
  static void showError(BuildContext context, String message, {Duration? duration}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.red,
        duration: duration ?? const Duration(seconds: 3),
      ),
    );
  }

  /// ✅ Kullanıcıya başarı mesajı göster
  static void showSuccess(BuildContext context, String message, {Duration? duration}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.green,
        duration: duration ?? const Duration(seconds: 2),
      ),
    );
  }

  /// ✅ Kullanıcıya bilgi mesajı göster
  static void showInfo(BuildContext context, String message, {Duration? duration}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: Colors.blue,
        duration: duration ?? const Duration(seconds: 2),
      ),
    );
  }

  /// ✅ Hata mesajını kullanıcı dostu formata çevir
  static String formatError(dynamic error) {
    final errorString = error.toString();
    
    // Yaygın hata mesajlarını düzelt
    if (errorString.contains('Exception: ')) {
      return errorString.replaceFirst('Exception: ', '');
    }
    
    if (errorString.contains('Failed host lookup')) {
      return 'İnternet bağlantısı yok. Lütfen bağlantınızı kontrol edin.';
    }
    
    if (errorString.contains('timeout')) {
      return 'İstek zaman aşımına uğradı. Lütfen tekrar deneyin.';
    }
    
    if (errorString.contains('401') || errorString.contains('unauthorized')) {
      return 'Oturum süresi doldu. Lütfen tekrar giriş yapın.';
    }
    
    if (errorString.contains('403') || errorString.contains('forbidden')) {
      return 'Bu işlem için yetkiniz bulunmamaktadır.';
    }
    
    if (errorString.contains('404') || errorString.contains('not found')) {
      return 'İstenen kaynak bulunamadı.';
    }
    
    if (errorString.contains('500') || errorString.contains('server error')) {
      return 'Sunucu hatası oluştu. Lütfen daha sonra tekrar deneyin.';
    }
    
    return errorString;
  }
}

