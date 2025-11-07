import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:provider/provider.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/screens/login_screen.dart';
import 'package:digimobil_new/widgets/terms_agreement_modal.dart';
import 'package:digimobil_new/widgets/error_modal.dart';
import 'package:digimobil_new/services/firebase_service.dart';
import 'package:google_sign_in/google_sign_in.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:package_info_plus/package_info_plus.dart';
import 'package:flutter/foundation.dart';
import 'dart:io';

class RegisterScreen extends StatefulWidget {
  const RegisterScreen({super.key});

  @override
  State<RegisterScreen> createState() => _RegisterScreenState();
}

class _RegisterScreenState extends State<RegisterScreen> {
  final _formKey = GlobalKey<FormState>();
  final _nameController = TextEditingController();
  final _surnameController = TextEditingController();
  final _emailController = TextEditingController();
  final _phoneController = TextEditingController();
  final _usernameController = TextEditingController();
  final _passwordController = TextEditingController();
  final _confirmPasswordController = TextEditingController();
  final ApiService _apiService = ApiService();
  
  bool _isLoading = false;
  bool _obscurePassword = true;
  bool _obscureConfirmPassword = true;
  bool _termsAgreed = false; // ✅ Sözleşme onayı
  String? _deviceInfo;
  String? _ipAddress;
  
  // Real-time validation states
  Map<String, bool> _fieldValidation = {
    'email': false,
    'phone': false,
    'username': false,
  };
  Map<String, String> _fieldMessages = {
    'email': '',
    'phone': '',
    'username': '',
  };
  Map<String, bool> _fieldLoading = {
    'email': false,
    'phone': false,
    'username': false,
  };

  @override
  void initState() {
    super.initState();
    _getDeviceInfo();
  }

  Future<void> _getDeviceInfo() async {
    try {
      final deviceInfo = DeviceInfoPlugin();
      final packageInfo = await PackageInfo.fromPlatform();
      
      String deviceData = '';
      
      if (Platform.isAndroid) {
        final androidInfo = await deviceInfo.androidInfo;
        deviceData = 'Android ${androidInfo.version.release} - ${androidInfo.model} - ${androidInfo.brand}';
      } else if (Platform.isIOS) {
        final iosInfo = await deviceInfo.iosInfo;
        deviceData = 'iOS ${iosInfo.systemVersion} - ${iosInfo.model} - ${iosInfo.name}';
      }
      
      setState(() {
        _deviceInfo = '$deviceData | App: ${packageInfo.version}';
      });
    } catch (e) {
      setState(() {
        _deviceInfo = 'Cihaz bilgisi alınamadı';
      });
    }
  }

  // Real-time field validation
  Future<void> _checkField(String field, String value) async {
    if (value.isEmpty) {
      setState(() {
        _fieldValidation[field] = false;
        _fieldMessages[field] = '';
        _fieldLoading[field] = false;
      });
      return;
    }

    setState(() {
      _fieldLoading[field] = true;
    });

    try {
      final result = await _apiService.checkField(
        field: field,
        value: value,
      );

      setState(() {
        _fieldValidation[field] = result['available'] == true;
        _fieldMessages[field] = result['message'] ?? '';
        _fieldLoading[field] = false;
      });
    } catch (e) {
      setState(() {
        _fieldValidation[field] = false;
        _fieldMessages[field] = 'Kontrol edilemedi: $e';
        _fieldLoading[field] = false;
      });
    }
  }

  Future<void> _register() async {
    if (!_formKey.currentState!.validate()) return;

    if (_passwordController.text != _confirmPasswordController.text) {
      _showSnackBar('Şifreler eşleşmiyor!', isError: true);
      return;
    }
    
    // ✅ Sözleşme kontrolü
    if (!_termsAgreed) {
      _showSnackBar('Lütfen Kullanım Koşullarını kabul edin!', isError: true);
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      final apiService = ApiService();
      
      final result = await apiService.register(
        name: _nameController.text.trim(),
        surname: _surnameController.text.trim(),
        email: _emailController.text.trim(),
        phone: _phoneController.text.trim(),
        username: _usernameController.text.trim(),
        password: _passwordController.text,
        deviceInfo: _deviceInfo ?? 'Bilinmeyen cihaz',
        ipAddress: _ipAddress ?? 'Bilinmeyen IP',
      );

      if (result['success'] == true) {
        _showSnackBar('Kayıt başarılı! Giriş yapabilirsiniz.');
        
        // Login ekranına yönlendir
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (context) => const LoginScreen()),
        );
      } else {
        _showSnackBar(result['message'] ?? 'Kayıt sırasında hata oluştu!', isError: true);
      }
    } catch (e) {
      _showSnackBar('Kayıt sırasında hata: $e', isError: true);
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  void _showSnackBar(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red : Colors.green,
        duration: const Duration(seconds: 3),
      ),
    );
  }

  // ✅ Google Sign In Handler (Kayıt ekranı için)
  Future<void> _handleGoogleSignIn() async {
    setState(() {
      _isLoading = true;
    });

    try {
      debugPrint('🔵 Google Sign In (Register) - Başlatılıyor...');
      
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
        debugPrint('🔵 Google Sign In (Register) - Device Info: $deviceInfo');
      } catch (e) {
        deviceInfo = 'Device info error: $e';
        debugPrint('⚠️ Google Sign In (Register) - Device info error: $e');
      }

      // ✅ Google Sign In
      debugPrint('🔵 Google Sign In (Register) - Google Sign In instance oluşturuluyor...');
      final GoogleSignIn googleSignIn = GoogleSignIn(
        scopes: ['email', 'profile'],
        serverClientId: '839706849375-0vuj83hhjk5urmdl63odm58v7kk85jnp.apps.googleusercontent.com',
      );

      debugPrint('🔵 Google Sign In (Register) - signIn() çağrılıyor...');
      final GoogleSignInAccount? googleUser = await googleSignIn.signIn();
      
      if (googleUser == null) {
        // Kullanıcı iptal etti
        debugPrint('🔵 Google Sign In (Register) - Kullanıcı iptal etti');
        if (mounted) {
          setState(() {
            _isLoading = false;
          });
        }
        return;
      }

      debugPrint('🔵 Google Sign In (Register) - Kullanıcı seçildi: ${googleUser.email}');
      
      // ✅ Authentication token al
      debugPrint('🔵 Google Sign In (Register) - Authentication token alınıyor...');
      final GoogleSignInAuthentication googleAuth = await googleUser.authentication;
      final String? idToken = googleAuth.idToken;

      if (idToken == null) {
        debugPrint('❌ Google Sign In (Register) - ID token alınamadı!');
        throw Exception('Google ID token alınamadı');
      }

      debugPrint('🔵 Google Sign In (Register) - ID token alındı (uzunluk: ${idToken.length})');
      debugPrint('🔵 Google Sign In (Register) - Backend\'e gönderiliyor...');

      // ✅ Backend'e gönder - is_new_user bilgisini almak için direkt API çağrısı
      final apiService = ApiService();
      final result = await apiService.googleOAuthLogin(idToken, deviceInfo);
      
      debugPrint('🔵 Google Sign In (Register) - Backend yanıtı: success=${result['success']}, is_new_user=${result['is_new_user']}');

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
            debugPrint('❌ Google Sign In (Register) - Sözleşme kabul edilmedi');
            if (mounted) {
              setState(() {
                _isLoading = false;
              });
            }
            return;
          }
        }
        
        // ✅ AuthProvider ile giriş yap (hem yeni hem mevcut kullanıcılar için)
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
            // Ana sayfaya yönlendir
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
      debugPrint('❌ Google Sign In (Register) - HATA: $e');
      debugPrint('❌ Google Sign In (Register) - Stack Trace: $stackTrace');
      
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(20),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 40),
                
                // Logo ve Başlık
                Center(
                  child: Column(
                    children: [
                      Container(
                        width: 80,
                        height: 80,
                        decoration: BoxDecoration(
                          color: AppColors.primary,
                          borderRadius: BorderRadius.circular(20),
                        ),
                        child: const Icon(
                          Icons.person_add,
                          color: Colors.white,
                          size: 40,
                        ),
                      ),
                      const SizedBox(height: 20),
                      const Text(
                        'Kayıt Ol',
                        style: TextStyle(
                          fontSize: 28,
                          fontWeight: FontWeight.bold,
                          color: Colors.black,
                        ),
                      ),
                      const SizedBox(height: 8),
                      Text(
                        'Hesabınızı oluşturun',
                        style: TextStyle(
                          fontSize: 16,
                          color: Colors.grey[600],
                        ),
                      ),
                    ],
                  ),
                ),
                
                const SizedBox(height: 40),
                
                // Ad Soyad Row
                Row(
                  children: [
                    Expanded(
                      child: TextFormField(
                        controller: _nameController,
                        decoration: InputDecoration(
                          labelText: 'Ad',
                          prefixIcon: const Icon(Icons.person),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Ad gerekli';
                          }
                          return null;
                        },
                      ),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: TextFormField(
                        controller: _surnameController,
                        decoration: InputDecoration(
                          labelText: 'Soyad',
                          prefixIcon: const Icon(Icons.person_outline),
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.circular(10),
                          ),
                        ),
                        validator: (value) {
                          if (value == null || value.isEmpty) {
                            return 'Soyad gerekli';
                          }
                          return null;
                        },
                      ),
                    ),
                  ],
                ),
                
                const SizedBox(height: 20),
                
                // Email
                TextFormField(
                  controller: _emailController,
                  keyboardType: TextInputType.emailAddress,
                  onChanged: (value) {
                    // Debounce için timer kullan
                    Future.delayed(const Duration(milliseconds: 500), () {
                      if (_emailController.text == value) {
                        _checkField('email', value);
                      }
                    });
                  },
                  decoration: InputDecoration(
                    labelText: 'E-posta',
                    prefixIcon: const Icon(Icons.email),
                    suffixIcon: _fieldLoading['email']!
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : _fieldValidation['email']!
                            ? const Icon(Icons.check_circle, color: Colors.green)
                            : _fieldMessages['email']!.isNotEmpty
                                ? const Icon(Icons.error, color: Colors.red)
                                : null,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    errorText: _fieldMessages['email']!.isNotEmpty ? _fieldMessages['email'] : null,
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'E-posta gerekli';
                    }
                    if (!RegExp(r'^[\w-\.]+@([\w-]+\.)+[\w-]{2,4}$').hasMatch(value)) {
                      return 'Geçerli bir e-posta girin';
                    }
                    if (!_fieldValidation['email']! && _fieldMessages['email']!.isNotEmpty) {
                      return _fieldMessages['email'];
                    }
                    return null;
                  },
                ),
                
                const SizedBox(height: 20),
                
                // Telefon
                TextFormField(
                  controller: _phoneController,
                  keyboardType: TextInputType.phone,
                  inputFormatters: [
                    FilteringTextInputFormatter.digitsOnly,
                    LengthLimitingTextInputFormatter(10),
                  ],
                  onChanged: (value) {
                    // Debounce için timer kullan
                    Future.delayed(const Duration(milliseconds: 500), () {
                      if (_phoneController.text == value) {
                        _checkField('phone', value);
                      }
                    });
                  },
                  decoration: InputDecoration(
                    labelText: 'Telefon (5XXXXXXXXX)',
                    prefixIcon: const Icon(Icons.phone),
                    suffixIcon: _fieldLoading['phone']!
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : _fieldValidation['phone']!
                            ? const Icon(Icons.check_circle, color: Colors.green)
                            : _fieldMessages['phone']!.isNotEmpty
                                ? const Icon(Icons.error, color: Colors.red)
                                : null,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    errorText: _fieldMessages['phone']!.isNotEmpty ? _fieldMessages['phone'] : null,
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Telefon gerekli';
                    }
                    if (value.length != 10 || !value.startsWith('5')) {
                      return 'Geçerli bir telefon numarası girin (5XXXXXXXXX)';
                    }
                    if (!_fieldValidation['phone']! && _fieldMessages['phone']!.isNotEmpty) {
                      return _fieldMessages['phone'];
                    }
                    return null;
                  },
                ),
                
                const SizedBox(height: 20),
                
                // Kullanıcı Adı
                TextFormField(
                  controller: _usernameController,
                  onChanged: (value) {
                    // Debounce için timer kullan
                    Future.delayed(const Duration(milliseconds: 500), () {
                      if (_usernameController.text == value) {
                        _checkField('username', value);
                      }
                    });
                  },
                  decoration: InputDecoration(
                    labelText: 'Kullanıcı Adı',
                    prefixIcon: const Icon(Icons.alternate_email),
                    suffixIcon: _fieldLoading['username']!
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(strokeWidth: 2),
                          )
                        : _fieldValidation['username']!
                            ? const Icon(Icons.check_circle, color: Colors.green)
                            : _fieldMessages['username']!.isNotEmpty
                                ? const Icon(Icons.error, color: Colors.red)
                                : null,
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    errorText: _fieldMessages['username']!.isNotEmpty ? _fieldMessages['username'] : null,
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Kullanıcı adı gerekli';
                    }
                    if (value.length < 3) {
                      return 'Kullanıcı adı en az 3 karakter olmalı';
                    }
                    if (!_fieldValidation['username']! && _fieldMessages['username']!.isNotEmpty) {
                      return _fieldMessages['username'];
                    }
                    return null;
                  },
                ),
                
                const SizedBox(height: 20),
                
                // Şifre
                TextFormField(
                  controller: _passwordController,
                  obscureText: _obscurePassword,
                  decoration: InputDecoration(
                    labelText: 'Şifre',
                    prefixIcon: const Icon(Icons.lock),
                    suffixIcon: IconButton(
                      icon: Icon(_obscurePassword ? Icons.visibility : Icons.visibility_off),
                      onPressed: () {
                        setState(() {
                          _obscurePassword = !_obscurePassword;
                        });
                      },
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
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
                
                const SizedBox(height: 20),
                
                // Şifre Tekrar
                TextFormField(
                  controller: _confirmPasswordController,
                  obscureText: _obscureConfirmPassword,
                  decoration: InputDecoration(
                    labelText: 'Şifre Tekrar',
                    prefixIcon: const Icon(Icons.lock_outline),
                    suffixIcon: IconButton(
                      icon: Icon(_obscureConfirmPassword ? Icons.visibility : Icons.visibility_off),
                      onPressed: () {
                        setState(() {
                          _obscureConfirmPassword = !_obscureConfirmPassword;
                        });
                      },
                    ),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  validator: (value) {
                    if (value == null || value.isEmpty) {
                      return 'Şifre tekrarı gerekli';
                    }
                    return null;
                  },
                ),
                
                const SizedBox(height: 20),
                
                // ✅ Sözleşme Onayı
                TermsCheckbox(
                  initialValue: _termsAgreed,
                  onChanged: (value) {
                    setState(() {
                      _termsAgreed = value;
                    });
                  },
                ),
                
                const SizedBox(height: 20),
                
                // Kayıt Ol Butonu
                ElevatedButton(
                  onPressed: (_isLoading || !_termsAgreed) ? null : _register,
                  style: ElevatedButton.styleFrom(
                    backgroundColor: AppColors.primary,
                    foregroundColor: Colors.white,
                    padding: const EdgeInsets.symmetric(vertical: 15),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                  ),
                  child: _isLoading
                      ? const CircularProgressIndicator(color: Colors.white)
                      : const Text(
                          'Kayıt Ol',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.bold,
                          ),
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
                
                // Google Sign-Up Button
                OutlinedButton.icon(
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
                    padding: const EdgeInsets.symmetric(vertical: 15),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    backgroundColor: Colors.white,
                  ),
                ),
                
                const SizedBox(height: 16),
                
                // Apple Sign-Up Button
                OutlinedButton.icon(
                  onPressed: _isLoading ? null : () async {
                    // TODO: Apple Sign-Up implementation
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Apple ile kayıt yakında aktif olacak'),
                        backgroundColor: AppColors.info,
                      ),
                    );
                  },
                  icon: Icon(Icons.apple, size: 28, color: Colors.grey[800]),
                  label: Text(
                    'Apple ile Kayıt Ol',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w600,
                      color: Colors.grey[800],
                    ),
                  ),
                  style: OutlinedButton.styleFrom(
                    side: BorderSide(color: Colors.grey[300]!, width: 1.5),
                    padding: const EdgeInsets.symmetric(vertical: 15),
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(10),
                    ),
                    backgroundColor: Colors.white,
                  ),
                ),
                
                const SizedBox(height: 20),
                
                // Giriş Yap Linki
                Row(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Text(
                      'Zaten hesabınız var mı? ',
                      style: TextStyle(color: Colors.grey[600]),
                    ),
                    TextButton(
                      onPressed: () {
                        Navigator.pushReplacement(
                          context,
                          MaterialPageRoute(builder: (context) => const LoginScreen()),
                        );
                      },
                      child: const Text(
                        'Giriş Yap',
                        style: TextStyle(
                          color: AppColors.primary,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ],
                ),
                
                const SizedBox(height: 20),
                
                // Cihaz Bilgisi (Debug)
                if (_deviceInfo != null)
                  Container(
                    padding: const EdgeInsets.all(10),
                    decoration: BoxDecoration(
                      color: Colors.grey[100],
                      borderRadius: BorderRadius.circular(8),
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'Cihaz Bilgisi:',
                          style: TextStyle(
                            fontWeight: FontWeight.bold,
                            fontSize: 12,
                          ),
                        ),
                        const SizedBox(height: 4),
                        Text(
                          _deviceInfo!,
                          style: const TextStyle(fontSize: 11),
                        ),
                      ],
                    ),
                  ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  @override
  void dispose() {
    _nameController.dispose();
    _surnameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _usernameController.dispose();
    _passwordController.dispose();
    _confirmPasswordController.dispose();
    super.dispose();
  }
}
