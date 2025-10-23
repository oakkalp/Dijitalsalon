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
      final sessionKey = prefs.getString(AppConstants.sessionKeyKey);

      if (userId != null && userEmail != null && userName != null && userRole != null && sessionKey != null) {
        // Try to refresh session instead of just checking
        final isSessionValid = await _apiService.refreshSession();
        
        if (isSessionValid) {
          _user = User(
            id: userId,
            name: userName,
            email: userEmail,
            username: userEmail.split('@')[0], // ✅ Varsayılan kullanıcı adı
            role: userRole,
            sessionKey: sessionKey,
          );
        } else {
          // Session expired, clear stored data
          await prefs.remove(AppConstants.sessionKeyKey);
          await prefs.remove(AppConstants.userIdKey);
          await prefs.remove(AppConstants.userEmailKey);
          await prefs.remove(AppConstants.userNameKey);
          await prefs.remove(AppConstants.userRoleKey);
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('Auth check error: $e');
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
      _user = await _apiService.login(email, password);
      
      if (_user != null) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setInt(AppConstants.userIdKey, _user!.id);
        await prefs.setString(AppConstants.userEmailKey, _user!.email);
        await prefs.setString(AppConstants.userNameKey, _user!.name);
        await prefs.setString(AppConstants.userRoleKey, _user!.role);
        if (_user!.sessionKey != null) {
          await prefs.setString(AppConstants.sessionKeyKey, _user!.sessionKey!);
        }
        
        notifyListeners();
        return true;
      } else {
        _errorMessage = 'Giriş başarısız';
        return false;
      }
    } catch (e) {
      _errorMessage = e.toString();
      if (kDebugMode) {
        debugPrint('Login error: $e');
      }
      return false;
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> logout() async {
    try {
      await _apiService.logout();
    } catch (e) {
      if (kDebugMode) {
        debugPrint('Logout error: $e');
      }
    } finally {
      _user = null;
      _errorMessage = null;
      notifyListeners();
    }
  }

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }
}
