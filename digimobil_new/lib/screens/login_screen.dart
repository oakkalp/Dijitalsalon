import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/constants.dart';
import 'package:digimobil_new/screens/register_screen.dart';
import 'package:digimobil_new/widgets/error_modal.dart';
import 'package:digimobil_new/widgets/terms_agreement_modal.dart';
import 'package:digimobil_new/services/firebase_service.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:flutter/foundation.dart';
import 'dart:io';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:sign_in_with_apple/sign_in_with_apple.dart';
import 'package:package_info_plus/package_info_plus.dart';

class LoginScreen extends StatefulWidget {
  const LoginScreen({super.key});

  @override
  State<LoginScreen> createState() => _LoginScreenState();
}

class _LoginScreenState extends State<LoginScreen> {
  final _formKey = GlobalKey<FormState>();
  final _loginController = TextEditingController(); // ✅ Email, telefon veya kullanıcı adı
  final _passwordController = TextEditingController();
  bool _obscurePassword = true;
  bool _isLoading = false;

  @override
  void initState() {
    super.initState();
    // ✅ Uygulama ilk açıldığında tüm izinleri iste
    WidgetsBinding.instance.addPostFrameCallback((_) {
      _requestAllPermissions();
    });
  }

  @override
  void dispose() {
    _loginController.dispose();
    _passwordController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.stretch,
            children: [
              const SizedBox(height: 60),
              
              // Logo and Title
              Column(
                children: [
                  Container(
                    width: 100,
                    height: 100,
                    decoration: BoxDecoration(
                      gradient: const LinearGradient(
                        colors: [Color(0xFFE1306C), Color(0xFFF56040)],
                        begin: Alignment.topLeft,
                        end: Alignment.bottomRight,
                      ),
                      borderRadius: BorderRadius.circular(50),
                      boxShadow: [
                        BoxShadow(
                          color: const Color(0xFFE1306C).withOpacity(0.3),
                          blurRadius: 20,
                          offset: const Offset(0, 10),
                        ),
                      ],
                    ),
                    child: const Icon(
                      Icons.camera_alt,
                      color: Colors.white,
                      size: 50,
                    ),
                  ),
                  const SizedBox(height: 24),
                  const Text(
                    'Digital Salon',
                    style: TextStyle(
                      fontSize: 32,
                      fontWeight: FontWeight.bold,
                      color: Colors.black,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'Etkinliklerinizi paylaşın, anılarınızı saklayın',
                    style: TextStyle(
                      fontSize: 16,
                      color: Colors.grey[600],
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
              
              const SizedBox(height: 60),
              
              // Login Form
              Form(
                key: _formKey,
                child: Column(
                  children: [
                    // ✅ Login Field (Email, telefon veya kullanıcı adı)
                    Container(
                      decoration: BoxDecoration(
                        color: Colors.grey[50],
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: Colors.grey[200]!),
                      ),
                      child: TextFormField(
                        controller: _loginController,
                        keyboardType: TextInputType.text,
                        decoration: InputDecoration(
                          labelText: 'E-posta, telefon veya kullanıcı adı',
                          labelStyle: TextStyle(color: Colors.grey[600]),
                          prefixIcon: Icon(Icons.person_outlined, color: Colors.grey[600]),
                          border: InputBorder.none,
                          contentPadding: const EdgeInsets.all(20),
                        ),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Giriş bilgisi gerekli';
                          }
                          return null;
                        },
                      ),
                    ),
                    
                    const SizedBox(height: 20),
                    
                    // Password Field
                    Container(
                      decoration: BoxDecoration(
                        color: Colors.grey[50],
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: Colors.grey[200]!),
                      ),
                      child: TextFormField(
                        controller: _passwordController,
                        obscureText: _obscurePassword,
                        decoration: InputDecoration(
                          labelText: 'Şifre',
                          labelStyle: TextStyle(color: Colors.grey[600]),
                          prefixIcon: Icon(Icons.lock_outlined, color: Colors.grey[600]),
                          suffixIcon: IconButton(
                            icon: Icon(
                              _obscurePassword ? Icons.visibility_off : Icons.visibility,
                              color: Colors.grey[600],
                            ),
                            onPressed: () {
                              setState(() {
                                _obscurePassword = !_obscurePassword;
                              });
                            },
                          ),
                          border: InputBorder.none,
                          contentPadding: const EdgeInsets.all(20),
                        ),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Şifre gerekli';
                          }
                          if (value.length < 6) {
                            return 'Şifre en az 6 karakter olmalı';
                          }
                          return null;
                        },
                      ),
                    ),
                    
                    const SizedBox(height: 12),
                    
                    // Şifremi Unuttum
                    Align(
                      alignment: Alignment.centerRight,
                      child: TextButton(
                        onPressed: _isLoading ? null : () {
                          Navigator.pushNamed(context, '/forgot-password');
                        },
                        child: const Text(
                          'Şifremi Unuttum',
                          style: TextStyle(
                            color: Color(0xFFE1306C),
                            fontSize: 14,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ),
                    ),
                    
                    const SizedBox(height: 18),
                    
                    // Login Button
                    SizedBox(
                      width: double.infinity,
                      height: 56,
                      child: ElevatedButton(
                        onPressed: _isLoading ? null : _handleLogin,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: const Color(0xFFE1306C),
                          foregroundColor: Colors.white,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                          elevation: 0,
                        ),
                        child: _isLoading
                            ? const SizedBox(
                                width: 24,
                                height: 24,
                                child: CircularProgressIndicator(
                                  color: Colors.white,
                                  strokeWidth: 2,
                                ),
                              )
                            : const Text(
                                'Giriş Yap',
                                style: TextStyle(
                                  fontSize: 18,
                                  fontWeight: FontWeight.w600,
                                ),
                              ),
                      ),
                    ),
                  ],
                ),
              ),
              
              const SizedBox(height: 30),
              
              // Divider with "VEYA"
              Row(
                children: [
                  Expanded(child: Divider(color: Colors.grey[300], thickness: 1)),
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Text(
                      'VEYA',
                      style: TextStyle(
                        color: Colors.grey[600],
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ),
                  Expanded(child: Divider(color: Colors.grey[300], thickness: 1)),
                ],
              ),
              
              const SizedBox(height: 30),
              
              // Google Sign-In Button
              SizedBox(
                width: double.infinity,
                height: 56,
                child: OutlinedButton.icon(
                  onPressed: _isLoading ? null : _handleGoogleSignIn,
                  icon: Image.asset(
                    'assets/icons/google_logo.png',
                    width: 24,
                    height: 24,
                    errorBuilder: (context, error, stackTrace) {
                      // Fallback icon if image not found
                      return Icon(Icons.g_mobiledata, size: 24, color: Colors.grey[800]);
                    },
                  ),
                  label: Text(
                    'Google ile Giriş Yap / Kayıt Ol',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                      color: Colors.grey[800],
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    side: BorderSide(color: Colors.grey[300]!, width: 1.5),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                    backgroundColor: Colors.white,
                  ),
                ),
              ),
              
              // Apple Sign-In Button (sadece iOS'ta göster)
              if (Platform.isIOS) ...[
                const SizedBox(height: 16),
                SizedBox(
                  width: double.infinity,
                  height: 56,
                  child: OutlinedButton.icon(
                    onPressed: _isLoading ? null : _handleAppleSignIn,
                    icon: Icon(Icons.apple, size: 28, color: Colors.grey[800]),
                    label: Text(
                      'Apple ile Giriş Yap',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: Colors.grey[800],
                      ),
                    ),
                    style: OutlinedButton.styleFrom(
                      side: BorderSide(color: Colors.grey[300]!, width: 1.5),
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                      backgroundColor: Colors.white,
                    ),
                  ),
                ),
              ],
              
              const SizedBox(height: 30),
              
              // Register Link
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    'Hesabınız yok mu? ',
                    style: TextStyle(color: Colors.grey[600]),
                  ),
                  GestureDetector(
                    onTap: () {
                      Navigator.push(
                        context,
                        MaterialPageRoute(builder: (context) => const RegisterScreen()),
                      );
                    },
                    child: const Text(
                      'Kayıt Ol',
                      style: TextStyle(
                        color: Color(0xFFE1306C),
                        fontWeight: FontWeight.w600,
                      ),
                    ),
                  ),
                ],
              ),
              
              const SizedBox(height: 40),
              
              // Features
              Container(
                padding: const EdgeInsets.all(20),
                decoration: BoxDecoration(
                  color: Colors.grey[50],
                  borderRadius: BorderRadius.circular(16),
                ),
                child: Column(
                  children: [
                    Row(
                      children: [
                        Icon(Icons.camera_alt, color: Colors.grey[600], size: 20),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'Fotoğraf ve video paylaşımı',
                            style: TextStyle(color: Colors.grey[700]),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Icon(Icons.auto_stories, color: Colors.grey[600], size: 20),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            '24 saatlik hikayeler',
                            style: TextStyle(color: Colors.grey[700]),
                          ),
                        ),
                      ],
                    ),
                    const SizedBox(height: 12),
                    Row(
                      children: [
                        Icon(Icons.event, color: Colors.grey[600], size: 20),
                        const SizedBox(width: 12),
                        Expanded(
                          child: Text(
                            'Etkinlik yönetimi',
                            style: TextStyle(color: Colors.grey[700]),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  Future<void> _handleLogin() async {
    if (!_formKey.currentState!.validate()) return;

    setState(() {
      _isLoading = true;
    });

    try {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      final loginSuccess = await authProvider.login(
        _loginController.text.trim(),
        _passwordController.text.trim(),
      );

      if (mounted) {
        if (loginSuccess) {
          // ✅ FCM Token'ı backend'e kaydet
          try {
            debugPrint('🔔 Requesting FCM token...');
            final firebaseService = FirebaseService();
            
            // Önce notification permission iste
            await firebaseService.requestNotificationPermissions();
            
            // Token'ı al (FirebaseService içinde otomatik kaydedecek)
            final fcmToken = await firebaseService.getFCMToken();
            debugPrint('🔔 FCM Token received: ${fcmToken != null ? "YES" : "NO"}');
            
            if (fcmToken != null) {
              debugPrint('✅ FCM token obtained and saved');
            } else {
              debugPrint('⚠️ FCM token is null');
            }
          } catch (e) {
            debugPrint('⚠️ FCM token error (non-critical): $e');
          }
          
          // ✅ Başarı mesajı gösterilmeden direkt navigate et
          await Future.delayed(const Duration(milliseconds: 200));
          
          if (mounted && authProvider.isLoggedIn) {
            Navigator.of(context).pushReplacementNamed('/home');
          }
        } else {
          // ✅ Hata mesajını modal olarak göster
          ErrorModal.show(
            context,
            title: 'Giriş Hatası',
            message: authProvider.errorMessage ?? 'Kullanıcı adı veya şifre hatalı',
            icon: Icons.error_outline,
            iconColor: AppColors.error,
          );
        }
      }
    } catch (e) {
      if (mounted) {
        // ✅ Hata mesajını modal olarak göster
        String errorMessage = e.toString();
        if (errorMessage.contains('Invalid credentials') || 
            errorMessage.contains('Kullanıcı adı') || 
            errorMessage.contains('şifre')) {
          errorMessage = 'Kullanıcı adı veya şifre hatalı';
        } else {
          errorMessage = errorMessage.replaceFirst('Exception: ', '');
        }
        
        ErrorModal.show(
          context,
          title: 'Giriş Hatası',
          message: errorMessage,
          icon: Icons.error_outline,
          iconColor: AppColors.error,
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }
  
  // ✅ Google Sign In Handler
  Future<void> _handleGoogleSignIn() async {
    setState(() {
      _isLoading = true;
    });

    try {
      debugPrint('🔵 Google Sign In - Başlatılıyor...');
      
      // ✅ Device info hazırla
      String deviceInfo = 'Unknown device';
      try {
        final deviceInfoPlugin = DeviceInfoPlugin();
        final packageInfo = await PackageInfo.fromPlatform();
        
        if (Platform.isAndroid) {
          final androidInfo = await deviceInfoPlugin.androidInfo;
          deviceInfo = 'Android ${androidInfo.version.release} - ${androidInfo.model} - ${androidInfo.brand}';
        } else if (Platform.isIOS) {
          final iosInfo = await deviceInfoPlugin.iosInfo;
          deviceInfo = 'iOS ${iosInfo.systemVersion} - ${iosInfo.model} - ${iosInfo.name}';
        }
        
        deviceInfo += ' | App: ${packageInfo.version}';
        debugPrint('🔵 Google Sign In - Device Info: $deviceInfo');
      } catch (e) {
        deviceInfo = 'Device info error: $e';
        debugPrint('⚠️ Google Sign In - Device info error: $e');
      }

      // ✅ Google Sign In
      debugPrint('🔵 Google Sign In - Google Sign In instance oluşturuluyor...');
      // ✅ Android için serverClientId ile birlikte kullan
      final GoogleSignIn googleSignIn = GoogleSignIn(
        scopes: ['email', 'profile'],
        // Web client ID - Backend'de token doğrulama için gerekli
        serverClientId: '839706849375-0vuj83hhjk5urmdl63odm58v7kk85jnp.apps.googleusercontent.com',
      );

      debugPrint('🔵 Google Sign In - signIn() çağrılıyor...');
      final GoogleSignInAccount? googleUser = await googleSignIn.signIn();
      
      if (googleUser == null) {
        // Kullanıcı iptal etti
        debugPrint('🔵 Google Sign In - Kullanıcı iptal etti');
        if (mounted) {
          setState(() {
            _isLoading = false;
          });
        }
        return;
      }

      debugPrint('🔵 Google Sign In - Kullanıcı seçildi: ${googleUser.email}');
      
      // ✅ Authentication token al
      debugPrint('🔵 Google Sign In - Authentication token alınıyor...');
      final GoogleSignInAuthentication googleAuth = await googleUser.authentication;
      final String? idToken = googleAuth.idToken;

      if (idToken == null) {
        debugPrint('❌ Google Sign In - ID token alınamadı!');
        throw Exception('Google ID token alınamadı');
      }

      debugPrint('🔵 Google Sign In - ID token alındı (uzunluk: ${idToken.length})');
      debugPrint('🔵 Google Sign In - Backend\'e gönderiliyor...');

      // ✅ Backend'e gönder - is_new_user bilgisini almak için direkt API çağrısı
      final apiService = ApiService();
      final result = await apiService.googleOAuthLogin(idToken, deviceInfo);
      
      debugPrint('🔵 Google Sign In - Backend yanıtı: success=${result['success']}, is_new_user=${result['is_new_user']}');

      if (mounted) {
        // ✅ Yeni kullanıcı ise sözleşme göster
        if (result['success'] == true && result['is_new_user'] == true) {
          final bool? termsAgreed = await showDialog<bool>(
            context: context,
            barrierDismissible: false,
            builder: (context) => TermsAgreementModal(
              onAgree: (agreed) => Navigator.of(context).pop(agreed),
              initialValue: false,
            ),
          );
          
          if (termsAgreed != true) {
            // Sözleşmeyi kabul etmedi
            if (mounted) {
              setState(() {
                _isLoading = false;
              });
            }
            return;
          }
        }
        
        // ✅ AuthProvider ile giriş yap
        final authProvider = Provider.of<AuthProvider>(context, listen: false);
        final success = await authProvider.googleLogin(idToken, deviceInfo);
        
        if (success) {
          // ✅ FCM Token'ı backend'e kaydet
          try {
            debugPrint('🔔 Requesting FCM token...');
            final firebaseService = FirebaseService();
            await firebaseService.requestNotificationPermissions();
            final fcmToken = await firebaseService.getFCMToken();
            debugPrint('🔔 FCM Token received: ${fcmToken != null ? "YES" : "NO"}');
          } catch (e) {
            debugPrint('⚠️ FCM token error (non-critical): $e');
          }
          
          await Future.delayed(const Duration(milliseconds: 200));
          
          if (mounted && authProvider.isLoggedIn) {
            Navigator.of(context).pushReplacementNamed('/home');
          }
        } else {
          ErrorModal.show(
            context,
            title: 'Giriş Hatası',
            message: authProvider.errorMessage ?? 'Google ile giriş başarısız',
            icon: Icons.error_outline,
            iconColor: AppColors.error,
          );
        }
      }
    } catch (e, stackTrace) {
      debugPrint('❌ Google Sign In - HATA: $e');
      debugPrint('❌ Google Sign In - Stack Trace: $stackTrace');
      
      if (mounted) {
        String errorMessage = e.toString().replaceFirst('Exception: ', '');
        
        // ✅ Özel hata mesajları
        if (errorMessage.contains('network_error') || errorMessage.contains('NetworkError')) {
          errorMessage = 'İnternet bağlantınızı kontrol edin';
        } else if (errorMessage.contains('sign_in_canceled') || errorMessage.contains('signInCanceled')) {
          // Kullanıcı iptal etti, hata gösterme
          return;
        } else if (errorMessage.contains('sign_in_failed') || errorMessage.contains('SignInException')) {
          errorMessage = 'Google ile giriş yapılamadı. Lütfen tekrar deneyin.';
        }
        
        ErrorModal.show(
          context,
          title: 'Google Giriş Hatası',
          message: 'Google ile giriş yapılırken bir hata oluştu:\n$errorMessage',
          icon: Icons.error_outline,
          iconColor: AppColors.error,
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  // ✅ Apple Sign In Handler
  Future<void> _handleAppleSignIn() async {
    if (!Platform.isIOS) {
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      // ✅ Device info hazırla
      String deviceInfo = 'Unknown device';
      try {
        final deviceInfoPlugin = DeviceInfoPlugin();
        final packageInfo = await PackageInfo.fromPlatform();
        
        final iosInfo = await deviceInfoPlugin.iosInfo;
        deviceInfo = 'iOS ${iosInfo.systemVersion} - ${iosInfo.model} - ${iosInfo.name}';
        deviceInfo += ' | App: ${packageInfo.version}';
      } catch (e) {
        deviceInfo = 'Device info error: $e';
      }

      // ✅ Apple Sign In
      final credential = await SignInWithApple.getAppleIDCredential(
        scopes: [
          AppleIDAuthorizationScopes.email,
          AppleIDAuthorizationScopes.fullName,
        ],
      );

      // ✅ userIdentifier null kontrolü
      if (credential.userIdentifier == null || credential.userIdentifier!.isEmpty) {
        throw Exception('Apple user identifier alınamadı');
      }

      // ✅ Backend'e gönder - is_new_user bilgisini almak için direkt API çağrısı
      final apiService = ApiService();
      final result = await apiService.appleSignInLogin(
        identityToken: credential.identityToken!,
        authorizationCode: credential.authorizationCode,
        email: credential.email,
        givenName: credential.givenName,
        familyName: credential.familyName,
        userId: credential.userIdentifier!,
        deviceInfo: deviceInfo,
      );
      
      debugPrint('🔵 Apple Sign In - Backend yanıtı: success=${result['success']}, is_new_user=${result['is_new_user']}');

      if (mounted) {
        // ✅ Yeni kullanıcı ise sözleşme göster
        if (result['success'] == true && result['is_new_user'] == true) {
          final bool? termsAgreed = await showDialog<bool>(
            context: context,
            barrierDismissible: false,
            builder: (context) => TermsAgreementModal(
              onAgree: (agreed) => Navigator.of(context).pop(agreed),
              initialValue: false,
            ),
          );
          
          if (termsAgreed != true) {
            // Sözleşmeyi kabul etmedi
            if (mounted) {
              setState(() {
                _isLoading = false;
              });
            }
            return;
          }
        }
        
        // ✅ AuthProvider ile giriş yap
        final authProvider = Provider.of<AuthProvider>(context, listen: false);
        final success = await authProvider.appleLogin(
          identityToken: credential.identityToken!,
          authorizationCode: credential.authorizationCode,
          email: credential.email,
          givenName: credential.givenName,
          familyName: credential.familyName,
          userId: credential.userIdentifier!,
          deviceInfo: deviceInfo,
        );
        
        if (success) {
          // ✅ FCM Token'ı backend'e kaydet
          try {
            debugPrint('🔔 Requesting FCM token...');
            final firebaseService = FirebaseService();
            await firebaseService.requestNotificationPermissions();
            final fcmToken = await firebaseService.getFCMToken();
            debugPrint('🔔 FCM Token received: ${fcmToken != null ? "YES" : "NO"}');
          } catch (e) {
            debugPrint('⚠️ FCM token error (non-critical): $e');
          }
          
          await Future.delayed(const Duration(milliseconds: 200));
          
          if (mounted && authProvider.isLoggedIn) {
            Navigator.of(context).pushReplacementNamed('/home');
          }
        } else {
          ErrorModal.show(
            context,
            title: 'Giriş Hatası',
            message: authProvider.errorMessage ?? 'Apple ile giriş başarısız',
            icon: Icons.error_outline,
            iconColor: AppColors.error,
          );
        }
      }
    } catch (e) {
      if (mounted) {
        String errorMessage = e.toString();
        if (errorMessage.contains('Sign in aborted')) {
          // Kullanıcı iptal etti, hata gösterme
          return;
        }
        
        ErrorModal.show(
          context,
          title: 'Apple Giriş Hatası',
          message: 'Apple ile giriş yapılırken bir hata oluştu: ${errorMessage.replaceFirst('Exception: ', '')}',
          icon: Icons.error_outline,
          iconColor: AppColors.error,
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }
  
  // ✅ Tüm izinleri iste (kamera, bildirim, galeri, konum vb.)
  Future<void> _requestAllPermissions() async {
    if (kDebugMode) {
      debugPrint('🔐 Requesting all permissions...');
    }
    
    try {
      // ✅ Bildirim izni (Firebase)
      final firebaseService = FirebaseService();
      await firebaseService.requestNotificationPermissions();
      
      // ✅ Kamera izni
      final cameraStatus = await Permission.camera.request();
      if (kDebugMode) {
        debugPrint('📷 Camera permission: $cameraStatus');
      }
      
      // ✅ Galeri/Storage ve Photos izinleri artık gerektiğinde istenecek
      // İlk açılışta istenmeyecek çünkü Android otomatik galeri açıyor
      // İzinler fotoğraf paylaşırken veya medya seçerken otomatik istenecek
      
      // Sadece izin durumunu kontrol et (gerekirse)
      if (kDebugMode) {
        if (Platform.isAndroid) {
          final storageStatus = await Permission.storage.status;
          debugPrint('📁 Storage permission status: $storageStatus');
        }
        if (Platform.isIOS) {
          final photosStatus = await Permission.photos.status;
          debugPrint('🖼️ Photos permission status: $photosStatus');
        }
      }
      
      // ✅ Android 13+ için notification permission
      if (Platform.isAndroid) {
        try {
          final androidInfo = await DeviceInfoPlugin().androidInfo;
          if (androidInfo.version.sdkInt >= 33) {
            final notificationStatus = await Permission.notification.request();
            if (kDebugMode) {
              debugPrint('📱 Notification permission: $notificationStatus');
            }
          }
        } catch (e) {
          if (kDebugMode) {
            debugPrint('⚠️ Notification permission error: $e');
          }
        }
      }
      
      if (kDebugMode) {
        debugPrint('✅ All permissions requested');
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('❌ Error requesting permissions: $e');
      }
    }
  }
}