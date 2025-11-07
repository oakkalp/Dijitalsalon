import 'package:flutter/foundation.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:digimobil_new/models/user.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/utils/constants.dart';

class AuthProvider with ChangeNotifier {
  User? _user;
  bool _isLoading = false;
  String? _errorMessage;

  User? get user => _user;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  bool get isLoggedIn => _user != null;
  String? get sessionKey => _user?.sessionKey;

  final ApiService _apiService = ApiService();

  AuthProvider() {
    _checkAuthStatus();
  }

  Future<void> _checkAuthStatus() async {
    _isLoading = true;
    notifyListeners();

    try {
      final prefs = await SharedPreferences.getInstance();
      final userId = prefs.getInt(AppConstants.userIdKey);
      final userEmail = prefs.getString(AppConstants.userEmailKey);
      final userName = prefs.getString(AppConstants.userNameKey);
      final userRole = prefs.getString(AppConstants.userRoleKey);
      final userProfileImage = prefs.getString(AppConstants.userProfileImageKey);
      final sessionKey = prefs.getString(AppConstants.sessionKeyKey);

      if (kDebugMode) {
        debugPrint('🔍 AuthProvider - Checking auth status');
        debugPrint('🔍 AuthProvider - User ID: $userId');
        debugPrint('🔍 AuthProvider - Session Key: $sessionKey');
      }

      if (userId != null && userEmail != null && userName != null && userRole != null && sessionKey != null) {
        if (kDebugMode) {
          debugPrint('🔍 AuthProvider - Found stored user data, validating session');
        }
        
        // Try to refresh session instead of just checking
        final isSessionValid = await _apiService.refreshSession();
        
        if (kDebugMode) {
          debugPrint('🔍 AuthProvider - Session valid: $isSessionValid');
        }
        
        if (isSessionValid) {
          _user = User(
            id: userId,
            name: userName,
            email: userEmail,
            username: userEmail.split('@')[0], // ✅ Varsayılan kullanıcı adı
            role: userRole,
            profileImage: userProfileImage,
            sessionKey: sessionKey,
          );
          
          if (kDebugMode) {
            debugPrint('🔍 AuthProvider - User logged in successfully: ${_user?.name}');
          }
        } else {
          if (kDebugMode) {
            debugPrint('🔍 AuthProvider - Session invalid, clearing stored data');
          }
          
          // Session expired, clear stored data
          await prefs.remove(AppConstants.sessionKeyKey);
          await prefs.remove(AppConstants.userIdKey);
          await prefs.remove(AppConstants.userEmailKey);
          await prefs.remove(AppConstants.userNameKey);
          await prefs.remove(AppConstants.userRoleKey);
          await prefs.remove(AppConstants.userProfileImageKey);
        }
      } else {
        if (kDebugMode) {
          debugPrint('🔍 AuthProvider - No stored user data found');
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('🔍 AuthProvider - Auth check error: $e');
      }
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      if (kDebugMode) {
        debugPrint('🔍 AuthProvider - Starting login for: $email');
      }
      
      _user = await _apiService.login(email, password);
      
      if (_user != null) {
        if (kDebugMode) {
          debugPrint('🔍 AuthProvider - Login successful, saving user data');
          debugPrint('🔍 AuthProvider - User: ${_user!.name} (ID: ${_user!.id})');
        }
        
        final prefs = await SharedPreferences.getInstance();
        await prefs.setInt(AppConstants.userIdKey, _user!.id);
        await prefs.setString(AppConstants.userEmailKey, _user!.email);
        await prefs.setString(AppConstants.userNameKey, _user!.name);
        await prefs.setString(AppConstants.userRoleKey, _user!.role);
        await prefs.setString(AppConstants.userProfileImageKey, _user!.profileImage ?? '');
        if (_user!.sessionKey != null) {
          await prefs.setString(AppConstants.sessionKeyKey, _user!.sessionKey!);
        }
        
        if (kDebugMode) {
          debugPrint('🔍 AuthProvider - User data saved, notifying listeners');
          debugPrint('🔍 AuthProvider - Login completed successfully, returning true');
        }
        
        notifyListeners();
        
        // AuthWrapper'ın state'ini güncellemesi için kısa bir bekleme
        await Future.delayed(const Duration(milliseconds: 50));
        
        return true;
      } else {
        if (kDebugMode) {
          debugPrint('🔍 AuthProvider - Login failed: user is null');
        }
        _errorMessage = 'Kullanıcı adı veya şifre hatalı';
        return false;
      }
    } catch (e) {
      _errorMessage = e.toString();
      if (kDebugMode) {
        debugPrint('🔍 AuthProvider - Login error: $e');
      }
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  // ✅ Google OAuth Login
  Future<bool> googleLogin(String idToken, String deviceInfo) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      debugPrint('🔵 AuthProvider - Google OAuth login başlatılıyor...');
      debugPrint('🔵 AuthProvider - ID Token uzunluk: ${idToken.length}');
      
      final result = await _apiService.googleOAuthLogin(idToken, deviceInfo);
      
      debugPrint('🔵 AuthProvider - API yanıtı alındı');
      debugPrint('🔵 AuthProvider - Success: ${result['success']}');
      debugPrint('🔵 AuthProvider - User: ${result['user'] != null ? "var" : "yok"}');
      
      if (result['success'] == true && result['user'] != null) {
        final userData = result['user'];
        debugPrint('🔵 AuthProvider - User data: ${userData.keys.join(", ")}');
        
        try {
          _user = User.fromJson(userData);
          debugPrint('🔵 AuthProvider - User object oluşturuldu: ${_user!.name} (ID: ${_user!.id})');
        } catch (e) {
          debugPrint('❌ AuthProvider - User.fromJson hatası: $e');
          debugPrint('❌ AuthProvider - User data: $userData');
          throw Exception('Kullanıcı bilgileri işlenirken hata: $e');
        }
        
        final prefs = await SharedPreferences.getInstance();
        await prefs.setInt(AppConstants.userIdKey, _user!.id);
        await prefs.setString(AppConstants.userEmailKey, _user!.email);
        await prefs.setString(AppConstants.userNameKey, _user!.name);
        await prefs.setString(AppConstants.userRoleKey, _user!.role);
        await prefs.setString(AppConstants.userProfileImageKey, _user!.profileImage ?? '');
        if (_user!.sessionKey != null) {
          await prefs.setString(AppConstants.sessionKeyKey, _user!.sessionKey!);
        }
        
        debugPrint('🔵 AuthProvider - User data kaydedildi');
        notifyListeners();
        await Future.delayed(const Duration(milliseconds: 50));
        
        return true;
      } else {
        final error = result['error'] ?? result['message'] ?? 'Bilinmeyen hata';
        _errorMessage = error.toString();
        debugPrint('❌ AuthProvider - Google OAuth başarısız: $_errorMessage');
        debugPrint('❌ AuthProvider - Tam yanıt: $result');
        return false;
      }
    } catch (e, stackTrace) {
      _errorMessage = e.toString();
      debugPrint('❌ AuthProvider - Google OAuth exception: $e');
      debugPrint('❌ AuthProvider - Stack trace: $stackTrace');
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  // ✅ Apple Sign In
  Future<bool> appleLogin({
    required String identityToken,
    String? authorizationCode,
    String? email,
    String? givenName,
    String? familyName,
    required String userId,
    String? deviceInfo,
  }) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      if (kDebugMode) {
        debugPrint('🔍 AuthProvider - Starting Apple Sign In');
      }
      
      final result = await _apiService.appleSignInLogin(
        identityToken: identityToken,
        authorizationCode: authorizationCode,
        email: email,
        givenName: givenName,
        familyName: familyName,
        userId: userId,
        deviceInfo: deviceInfo ?? 'Unknown device',
      );
      
      if (result['success'] == true && result['user'] != null) {
        final userData = result['user'];
        _user = User.fromJson(userData);
        
        if (kDebugMode) {
          debugPrint('🔍 AuthProvider - Apple Sign In successful');
          debugPrint('🔍 AuthProvider - User: ${_user!.name} (ID: ${_user!.id})');
        }
        
        final prefs = await SharedPreferences.getInstance();
        await prefs.setInt(AppConstants.userIdKey, _user!.id);
        await prefs.setString(AppConstants.userEmailKey, _user!.email);
        await prefs.setString(AppConstants.userNameKey, _user!.name);
        await prefs.setString(AppConstants.userRoleKey, _user!.role);
        await prefs.setString(AppConstants.userProfileImageKey, _user!.profileImage ?? '');
        if (_user!.sessionKey != null) {
          await prefs.setString(AppConstants.sessionKeyKey, _user!.sessionKey!);
        }
        
        notifyListeners();
        await Future.delayed(const Duration(milliseconds: 50));
        
        return true;
      } else {
        _errorMessage = 'Apple ile giriş başarısız';
        return false;
      }
    } catch (e) {
      _errorMessage = e.toString();
      if (kDebugMode) {
        debugPrint('🔍 AuthProvider - Apple Sign In error: $e');
      }
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    try {
      if (kDebugMode) {
        debugPrint('🔍 AuthProvider - Starting logout process');
      }
      
      await _apiService.logout();
      
      // Clear all stored user data
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(AppConstants.userIdKey);
      await prefs.remove(AppConstants.userEmailKey);
      await prefs.remove(AppConstants.userNameKey);
      await prefs.remove(AppConstants.userRoleKey);
      await prefs.remove(AppConstants.userProfileImageKey);
      await prefs.remove(AppConstants.sessionKeyKey);
      
      if (kDebugMode) {
        debugPrint('🔍 AuthProvider - All user data cleared from storage');
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('🔍 AuthProvider - Logout error: $e');
      }
    } finally {
      _user = null;
      _errorMessage = null;
      
      if (kDebugMode) {
        debugPrint('🔍 AuthProvider - User state cleared, notifying listeners');
      }
      
      notifyListeners();
    }
  }

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  Future<void> updateUser(User updatedUser) async {
    _user = updatedUser;
    
    // Güncel kullanıcı bilgilerini SharedPreferences'a kaydet
    try {
      final prefs = await SharedPreferences.getInstance();
      await prefs.setInt(AppConstants.userIdKey, updatedUser.id);
      await prefs.setString(AppConstants.userEmailKey, updatedUser.email);
      await prefs.setString(AppConstants.userNameKey, updatedUser.name);
      await prefs.setString(AppConstants.userRoleKey, updatedUser.role);
      await prefs.setString(AppConstants.userProfileImageKey, updatedUser.profileImage ?? '');
      
      if (kDebugMode) {
        debugPrint('🔍 AuthProvider - User data updated and saved to storage');
        debugPrint('🔍 AuthProvider - Updated user: ${updatedUser.name}');
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('🔍 AuthProvider - Error saving updated user data: $e');
      }
    }
    
    // Tüm dinleyicileri güncelle
    notifyListeners();
  }

  /// ✅ Save FCM token to backend
  Future<void> saveFCMToken(String fcmToken) async {
    try {
      await _apiService.saveFCMToken(fcmToken);
      if (kDebugMode) {
        debugPrint('✅ AuthProvider - FCM token saved successfully');
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('❌ AuthProvider - Error saving FCM token: $e');
      }
      // Non-critical error, don't throw
    }
  }
}
