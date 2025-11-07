class AppConstants {
  // API Configuration
  static const String baseUrl = 'https://dijitalsalon.cagapps.app/digimobiapi';
  
  // API Endpoints
  static const String loginEndpoint = '/login.php';
  static const String eventsEndpoint = '/events.php';
  static const String logoutEndpoint = '/logout.php';
  
  // Storage Keys
  static const String userIdKey = 'user_id';
  static const String userEmailKey = 'user_email';
  static const String userNameKey = 'user_name';
  static const String userRoleKey = 'user_role';
  static const String userProfileImageKey = 'user_profile_image';
  static const String sessionKeyKey = 'session_key';
  
  // UI Constants
  static const double defaultPadding = 16.0;
  static const double smallPadding = 8.0;
  static const double largePadding = 24.0;
  
  static const double defaultRadius = 12.0;
  static const double smallRadius = 8.0;
  static const double largeRadius = 16.0;
  
  // Animation Durations
  static const Duration shortAnimation = Duration(milliseconds: 200);
  static const Duration mediumAnimation = Duration(milliseconds: 300);
  static const Duration longAnimation = Duration(milliseconds: 500);
  
  // Error Messages
  static const String networkError = 'İnternet bağlantınızı kontrol edin';
  static const String serverError = 'Sunucu hatası oluştu';
  static const String unknownError = 'Bilinmeyen bir hata oluştu';
  static const String loginError = 'Giriş bilgileri hatalı';
  
  // Success Messages
  static const String loginSuccess = 'Giriş başarılı';
  
  // Validation Messages
  static const String emailRequired = 'E-posta adresi gerekli';
  static const String emailInvalid = 'Geçerli bir e-posta adresi girin';
  static const String passwordRequired = 'Şifre gerekli';
  static const String passwordMinLength = 'Şifre en az 6 karakter olmalı';
}
