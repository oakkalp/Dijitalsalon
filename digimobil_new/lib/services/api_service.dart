import 'dart:convert';
import 'dart:async';
import 'dart:io';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:digimobil_new/models/user.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/utils/constants.dart';
import 'package:device_info_plus/device_info_plus.dart';
import 'package:package_info_plus/package_info_plus.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  final String _baseUrl = AppConstants.baseUrl;

  Future<String?> _getSessionKey() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString(AppConstants.sessionKeyKey);
  }

  Future<Map<String, String>> _getHeaders() async {
    final prefs = await SharedPreferences.getInstance();
    final sessionKey = prefs.getString(AppConstants.sessionKeyKey);
    
    if (kDebugMode) {
      debugPrint('Session Key: $sessionKey');
    }
    
    return {
      'Content-Type': 'application/x-www-form-urlencoded',
      'Accept': 'application/json',
      'Connection': 'keep-alive',
      'Cache-Control': 'no-cache',
      if (sessionKey != null) 'Cookie': 'PHPSESSID=$sessionKey',
    };
  }

  // Helper method for HTTP requests with timeout and retry
  Future<http.Response> _makeRequest(
    Future<http.Response> Function() request, {
    int maxRetries = 2,
    Duration timeout = const Duration(seconds: 30),
  }) async {
    int attempts = 0;
    Exception? lastException;
    
    while (attempts < maxRetries) {
      try {
        attempts++;
        if (kDebugMode) {
          debugPrint('API request attempt $attempts/$maxRetries');
        }
        
        final response = await request().timeout(timeout);
        
        // ✅ Session timeout kontrolü (401 Unauthorized)
        if (response.statusCode == 401) {
          if (kDebugMode) {
            debugPrint('⚠️ Session expired (401 Unauthorized)');
          }
          // Session'ı temizle
          final prefs = await SharedPreferences.getInstance();
          await prefs.remove(AppConstants.sessionKeyKey);
          await prefs.remove(AppConstants.userIdKey);
          await prefs.remove(AppConstants.userEmailKey);
          await prefs.remove(AppConstants.userNameKey);
          await prefs.remove(AppConstants.userRoleKey);
          await prefs.remove(AppConstants.userProfileImageKey);
          
          throw Exception('Oturum süresi doldu. Lütfen tekrar giriş yapın.');
        }
        
        return response;
      } catch (e) {
        lastException = e is Exception ? e : Exception(e.toString());
        if (kDebugMode) {
          debugPrint('API request attempt $attempts failed: $e');
        }
        
        // ✅ Session timeout hatası ise tekrar deneme
        if (e.toString().contains('Oturum süresi doldu')) {
          throw lastException!;
        }
        
        if (attempts < maxRetries) {
          final delay = Duration(seconds: attempts);
          if (kDebugMode) {
            debugPrint('Retrying API request in ${delay.inSeconds} seconds...');
          }
          await Future.delayed(delay);
        }
      }
    }
    
    throw lastException ?? Exception('All API request attempts failed');
  }

  // Login using new JSON API
  Future<User> login(String login, String password) async {
    if (kDebugMode) {
      debugPrint('Login -> ${AppConstants.baseUrl}${AppConstants.loginEndpoint}');
    }

    // Get device info for logging
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
    } catch (e) {
      deviceInfo = 'Device info error: $e';
    }

    final headers = await _getHeaders();
    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}${AppConstants.loginEndpoint}'),
      headers: headers,
      body: {
        'login': login, // ✅ Email, telefon veya kullanıcı adı
        'password': password,
        'device_info': deviceInfo,
      },
    );

    if (kDebugMode) {
      debugPrint('Login Status: ${response.statusCode}');
      debugPrint('Login Headers: ${response.headers}');
      debugPrint('Login Body: ${response.body}');
    }

    // Parse JSON response (hem 200 hem de error durumlarında JSON dönebilir)
    final responseData = _tryParseJson(response.body);
    
    if (response.statusCode == 200) {
      // Extract session cookie
      final setCookie = response.headers['set-cookie'];
      String? sessionId;
      if (setCookie != null) {
        final match = RegExp(r'PHPSESSID=([^;]+)').firstMatch(setCookie);
        if (match != null) {
          sessionId = match.group(1)!;
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString(AppConstants.sessionKeyKey, sessionId);
          
          if (kDebugMode) {
            debugPrint('Session saved: $sessionId');
          }
        }
      }

      // Parse JSON response
      if (responseData != null && responseData['success'] == true && responseData['user'] != null) {
        final userData = responseData['user'];
        // Add session key to user data
        userData['session_key'] = sessionId;
        return User.fromJson(userData);
      } else {
        // ✅ Backend'den gelen error mesajını kullan
        throw Exception(responseData?['error'] ?? 'Giriş başarısız');
      }
    } else {
      // ✅ 401 veya diğer error durumlarında backend'den gelen mesajı göster
      if (responseData != null && responseData['error'] != null) {
        throw Exception(responseData['error']);
      }
      throw Exception('Sunucu hatası: ${response.statusCode}');
    }
  }

  // Get events from new JSON API
  Future<List<Event>> getEvents({bool bypassCache = false}) async {
    String url = '${AppConstants.baseUrl}${AppConstants.eventsEndpoint}';
    
    // ✅ Cache bypass için bypass_cache parametresi ekle
    if (bypassCache) {
      url += '?bypass_cache=true';
    }
    
    if (kDebugMode) {
      debugPrint('Get Events -> $url');
    }

    final headers = await _getHeaders();
    final response = await http.get(
      Uri.parse(url),
      headers: headers,
    );

    if (kDebugMode) {
      debugPrint('Events Status: ${response.statusCode}');
      debugPrint('Events Body: ${response.body}');
    }

    // ✅ Session timeout kontrolü (401 Unauthorized)
    if (response.statusCode == 401) {
      if (kDebugMode) {
        debugPrint('⚠️ Session expired (401 Unauthorized) in getEvents');
      }
      // Session'ı temizle
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(AppConstants.sessionKeyKey);
      await prefs.remove(AppConstants.userIdKey);
      await prefs.remove(AppConstants.userEmailKey);
      await prefs.remove(AppConstants.userNameKey);
      await prefs.remove(AppConstants.userRoleKey);
      await prefs.remove(AppConstants.userProfileImageKey);
      
      throw Exception('Oturum süresi doldu. Lütfen tekrar giriş yapın.');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true && responseData['events'] != null) {
        print('🔍 ApiService - Raw events data: ${responseData['events']}');
        final events = (responseData['events'] as List)
            .map((e) => Event.fromJson(e))
            .toList();
        print('🔍 ApiService - Parsed ${events.length} events');
        return events;
      } else {
        throw Exception(responseData['error'] ?? 'Etkinlikler alınamadı');
      }
    } else {
      throw Exception('Etkinlikler alınamadı: ${response.statusCode}');
    }
  }

  Future<Map<String, dynamic>> joinEvent(String eventId) async {
    if (kDebugMode) {
      debugPrint('Join Event -> ${AppConstants.baseUrl}/join_event.php');
      debugPrint('Join Event - eventId: $eventId');
    }

    final headers = await _getHeaders();
    if (kDebugMode) {
      debugPrint('Join Event Headers: $headers');
    }

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/join_event.php'),
      headers: headers,
      body: {
        'event_id': eventId,
      },
    );

    if (kDebugMode) {
      debugPrint('Join Event Status: ${response.statusCode}');
      debugPrint('Join Event Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (kDebugMode) {
        debugPrint('✅ Join Event Response Data: $responseData');
        debugPrint('✅ Join Event - success: ${responseData['success']}');
        debugPrint('✅ Join Event - event_id: ${responseData['event_id']}');
        debugPrint('✅ Join Event - event_title: ${responseData['event_title']}');
        debugPrint('✅ Join Event - message: ${responseData['message']}');
      }
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Etkinliğe katılım başarısız');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Etkinliğe katılım başarısız: ${response.statusCode}');
    }
  }

  // Get media for specific event with pagination
  // Ultra-fast media and stories loading
  Future<Map<String, dynamic>> getMedia(int eventId, {int page = 1, int limit = 10, bool bypassCache = false}) async {
    // ✅ Cache bypass için timestamp ekle
    final timestamp = bypassCache ? '&_t=${DateTime.now().millisecondsSinceEpoch}' : '';
    final url = '${AppConstants.baseUrl}/media.php?event_id=$eventId&page=$page&limit=$limit$timestamp';
    
    if (kDebugMode) {
      debugPrint('Get Media -> $url');
    }

    final headers = await _getHeaders();
    final response = await http.get(
      Uri.parse(url),
      headers: headers,
    );

    if (kDebugMode) {
      debugPrint('Media Status: ${response.statusCode}');
      debugPrint('Media Body: ${response.body}');
    }

    // ✅ Session timeout kontrolü (401 Unauthorized)
    if (response.statusCode == 401) {
      if (kDebugMode) {
        debugPrint('⚠️ Session expired (401 Unauthorized) in getMedia');
      }
      // Session'ı temizle
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(AppConstants.sessionKeyKey);
      await prefs.remove(AppConstants.userIdKey);
      await prefs.remove(AppConstants.userEmailKey);
      await prefs.remove(AppConstants.userNameKey);
      await prefs.remove(AppConstants.userRoleKey);
      await prefs.remove(AppConstants.userProfileImageKey);
      
      throw Exception('Oturum süresi doldu. Lütfen tekrar giriş yapın.');
    }

    // Try to parse JSON response
    final responseData = _tryParseJson(response.body);
    
    if (response.statusCode == 200) {
      if (responseData == null) {
        throw Exception('Sunucu hatası: API beklenmeyen bir yanıt döndü.');
      }
      if (responseData['success'] == true && responseData['media'] != null) {
        return {
          'media': List<Map<String, dynamic>>.from(responseData['media']),
          'pagination': responseData['pagination'] ?? {}
        };
      } else {
        final errorMsg = responseData['error'] ?? responseData['detail'] ?? 'Medya alınamadı';
        throw Exception(errorMsg);
      }
    } else {
      // Handle error responses
      if (responseData != null) {
        final errorMsg = responseData['error'] ?? responseData['detail'] ?? 'Medya alınamadı';
        throw Exception('$errorMsg (${response.statusCode})');
      } else {
        throw Exception('Medya alınamadı: ${response.statusCode}');
      }
    }
  }

  // Like/unlike media
  Future<Map<String, dynamic>> toggleLike(int mediaId, bool isLiked) async {
    if (kDebugMode) {
      debugPrint('Toggle Like -> ${AppConstants.baseUrl}/like.php');
    }

    final headers = await _getHeaders();
    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/like.php'),
      headers: headers,
      body: {
        'media_id': mediaId.toString(),
        'action': isLiked ? 'unlike' : 'like',
      },
    );

    if (kDebugMode) {
      debugPrint('Like Status: ${response.statusCode}');
      debugPrint('Like Body: ${response.body}');
    }

    // Try to parse JSON response
    final responseData = _tryParseJson(response.body);
    
    if (response.statusCode == 200) {
      if (responseData != null && responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData?['error'] ?? 'Beğeni işlemi başarısız');
      }
    } else {
      if (responseData != null) {
        throw Exception(responseData['error'] ?? 'Beğeni işlemi başarısız: ${response.statusCode}');
      } else {
        throw Exception('Beğeni işlemi başarısız: ${response.statusCode}');
      }
    }
  }

  // Get comments for specific media
  Future<List<Map<String, dynamic>>> getComments(int mediaId) async {
    if (kDebugMode) {
      debugPrint('Get Comments -> ${AppConstants.baseUrl}/comments.php?media_id=$mediaId');
    }

    final headers = await _getHeaders();
    final response = await http.get(
      Uri.parse('${AppConstants.baseUrl}/comments.php?media_id=$mediaId'),
      headers: headers,
    );

    if (kDebugMode) {
      debugPrint('Comments Status: ${response.statusCode}');
      debugPrint('Comments Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true && responseData['comments'] != null) {
        return List<Map<String, dynamic>>.from(responseData['comments']);
      } else {
        throw Exception(responseData['error'] ?? 'Yorumlar alınamadı');
      }
    } else {
      throw Exception('Yorumlar alınamadı: ${response.statusCode}');
    }
  }


  // Get stories for specific event
  Future<List<Map<String, dynamic>>> getStories(int eventId) async {
    if (kDebugMode) {
      debugPrint('Get Stories -> ${AppConstants.baseUrl}/stories.php?event_id=$eventId');
    }

    final headers = await _getHeaders();
    final response = await http.get(
      Uri.parse('${AppConstants.baseUrl}/stories.php?event_id=$eventId'),
      headers: headers,
    );

    if (kDebugMode) {
      debugPrint('Stories Status: ${response.statusCode}');
      debugPrint('Stories Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true && responseData['stories'] != null) {
        return List<Map<String, dynamic>>.from(responseData['stories']);
      } else {
        throw Exception(responseData['error'] ?? 'Hikayeler alınamadı');
      }
    } else {
      throw Exception('Hikayeler alınamadı: ${response.statusCode}');
    }
  }

  // Mark story as viewed
  Future<Map<String, dynamic>> markStoryViewed(int storyId) async {
    if (kDebugMode) {
      debugPrint('Mark Story Viewed -> ${AppConstants.baseUrl}/stories.php');
    }

    final headers = await _getHeaders();
    final response = await http.put(
      Uri.parse('${AppConstants.baseUrl}/stories.php'),
      headers: headers,
      body: {
        'story_id': storyId.toString(),
      },
    );

    if (kDebugMode) {
      debugPrint('Mark Story Viewed Status: ${response.statusCode}');
      debugPrint('Mark Story Viewed Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Hikaye görüntüleme işlemi başarısız');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Hikaye görüntüleme işlemi başarısız: ${response.statusCode}');
    }
  }

  // Like/unlike comment
  Future<bool> toggleCommentLike(int commentId, String action) async {
    if (kDebugMode) {
      debugPrint('Toggle Comment Like -> ${AppConstants.baseUrl}/like_comment.php');
    }

    final headers = await _getHeaders();
    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/like_comment.php'),
      headers: headers,
      body: {
        'comment_id': commentId.toString(),
        'action': action, // 'like' or 'unlike'
      },
    );

    if (kDebugMode) {
      debugPrint('Toggle Comment Like Status: ${response.statusCode}');
      debugPrint('Toggle Comment Like Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      return responseData['success'] == true;
    } else {
      throw Exception('Comment like toggle failed: ${response.statusCode}');
    }
  }

  // Get replies for specific comment
  Future<List<Map<String, dynamic>>> getReplies(int commentId) async {
    if (kDebugMode) {
      debugPrint('Get Replies -> ${AppConstants.baseUrl}/get_replies.php?comment_id=$commentId');
    }

    final headers = await _getHeaders();
    final response = await http.get(
      Uri.parse('${AppConstants.baseUrl}/get_replies.php?comment_id=$commentId'),
      headers: headers,
    );

    if (kDebugMode) {
      debugPrint('Replies Status: ${response.statusCode}');
      debugPrint('Replies Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true && responseData['replies'] != null) {
        return List<Map<String, dynamic>>.from(responseData['replies']);
      } else {
        throw Exception(responseData['error'] ?? 'Replies could not be loaded');
      }
    } else {
      throw Exception('Replies could not be loaded: ${response.statusCode}');
    }
  }

  // Add reply to comment
  Future<Map<String, dynamic>> addReply(int parentCommentId, String content) async {
    if (kDebugMode) {
      debugPrint('Add Reply -> ${AppConstants.baseUrl}/add_reply.php');
    }

    final headers = await _getHeaders();
    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/add_reply.php'),
      headers: headers,
      body: {
        'parent_comment_id': parentCommentId.toString(),
        'content': content,
      },
    );

    if (kDebugMode) {
      debugPrint('Add Reply Status: ${response.statusCode}');
      debugPrint('Add Reply Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Reply could not be added');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Reply could not be added: ${response.statusCode}');
    }
  }

  // Refresh session
  Future<bool> refreshSession() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final sessionKey = prefs.getString(AppConstants.sessionKeyKey);
      
      if (sessionKey == null) return false;
      
      final headers = await _getHeaders();
      final response = await http.get(
        Uri.parse('${AppConstants.baseUrl}/me.php'),
        headers: headers,
      );
      
      if (response.statusCode == 200) {
        final responseData = json.decode(response.body);
        if (responseData['success'] == true && responseData['user'] != null) {
          // Update stored user data
          final userData = responseData['user'];
          await prefs.setInt(AppConstants.userIdKey, userData['id'] as int);
          await prefs.setString(AppConstants.userEmailKey, userData['email']);
          await prefs.setString(AppConstants.userNameKey, userData['name']);
          await prefs.setString(AppConstants.userRoleKey, userData['role']);
          return true;
        }
      }
      return false;
    } catch (e) {
      if (kDebugMode) {
        debugPrint('Session refresh error: $e');
      }
      return false;
    }
  }

  // Check if session is valid
  Future<bool> checkSession() async {
    try {
      final headers = await _getHeaders();
      final response = await http.get(
        Uri.parse('${AppConstants.baseUrl}/me.php'),
        headers: headers,
      );

      if (kDebugMode) {
        debugPrint('Session Check Status: ${response.statusCode}');
        debugPrint('Session Check Body: ${response.body}');
      }

      return response.statusCode == 200;
    } catch (e) {
      if (kDebugMode) {
        debugPrint('Session check error: $e');
      }
      return false;
    }
  }

  Future<void> logout() async {
    try {
      final headers = await _getHeaders();
      await http.post(
        Uri.parse('${AppConstants.baseUrl}${AppConstants.logoutEndpoint}'),
        headers: headers,
      );
    } catch (e) {
      if (kDebugMode) {
        debugPrint('Logout API error: $e');
      }
    } finally {
      final prefs = await SharedPreferences.getInstance();
      await prefs.remove(AppConstants.sessionKeyKey);
      await prefs.remove(AppConstants.userIdKey);
      await prefs.remove(AppConstants.userEmailKey);
      await prefs.remove(AppConstants.userNameKey);
      await prefs.remove(AppConstants.userRoleKey);
    }
  }

  // Delete comment
  Future<Map<String, dynamic>> deleteComment(int commentId) async {
    if (kDebugMode) {
      debugPrint('Delete Comment -> ${AppConstants.baseUrl}/delete_comment.php');
    }

    final headers = await _getHeaders();
    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/delete_comment.php'),
      headers: headers,
      body: {
        'comment_id': commentId.toString(),
      },
    );

    if (kDebugMode) {
      debugPrint('Delete Comment Status: ${response.statusCode}');
      debugPrint('Delete Comment Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Comment could not be deleted');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Comment could not be deleted: ${response.statusCode}');
    }
  }

  // Edit comment
  Future<Map<String, dynamic>> editComment(int commentId, String content) async {
    if (kDebugMode) {
      debugPrint('Edit Comment -> ${AppConstants.baseUrl}/edit_comment.php');
    }

    final headers = await _getHeaders();
    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/edit_comment.php'),
      headers: headers,
      body: {
        'comment_id': commentId.toString(),
        'content': content,
      },
    );

    if (kDebugMode) {
      debugPrint('Edit Comment Status: ${response.statusCode}');
      debugPrint('Edit Comment Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Comment could not be edited');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Comment could not be edited: ${response.statusCode}');
    }
  }

  // Delete media
  Future<Map<String, dynamic>> deleteMedia(int mediaId) async {
    if (kDebugMode) {
      debugPrint('Delete Media -> ${AppConstants.baseUrl}/delete_media.php');
    }

    final headers = await _getHeaders();
    headers['Content-Type'] = 'application/json';

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/delete_media.php'),
      headers: headers,
      body: json.encode({
        'media_id': mediaId,
      }),
    );

    if (kDebugMode) {
      debugPrint('Delete Media Status: ${response.statusCode}');
      debugPrint('Delete Media Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Media could not be deleted');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Media could not be deleted: ${response.statusCode}');
    }
  }

  // Get story details
  Future<Map<String, dynamic>> getStoryDetails(int userId, int eventId) async {
    if (kDebugMode) {
      debugPrint('Get Story Details -> ${AppConstants.baseUrl}/get_story_details.php');
    }

    final headers = await _getHeaders();
    final response = await http.get(
      Uri.parse('${AppConstants.baseUrl}/get_story_details.php?user_id=$userId&event_id=$eventId'),
      headers: headers,
    );

    if (kDebugMode) {
      debugPrint('Story Details Status: ${response.statusCode}');
      debugPrint('Story Details Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Story details could not be loaded');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Story details could not be loaded: ${response.statusCode}');
    }
  }

  // Edit media
  Future<Map<String, dynamic>> editMedia(int mediaId, String description) async {
    if (kDebugMode) {
      debugPrint('Edit Media -> ${AppConstants.baseUrl}/edit_media.php');
    }

    final headers = await _getHeaders();
    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/edit_media.php'),
      headers: headers,
      body: {
        'media_id': mediaId.toString(),
        'description': description,
      },
    );

    if (kDebugMode) {
      debugPrint('Edit Media Status: ${response.statusCode}');
      debugPrint('Edit Media Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Media could not be edited');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Media could not be edited: ${response.statusCode}');
    }
  }

  // Delete story
  Future<Map<String, dynamic>> deleteStory(int storyId) async {
    if (kDebugMode) {
      debugPrint('Delete Story -> ${AppConstants.baseUrl}/delete_story.php');
    }

    final headers = await _getHeaders();
    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/delete_story.php'),
      headers: headers,
      body: json.encode({
        'story_id': storyId,
      }),
    );

    if (kDebugMode) {
      debugPrint('Delete Story Status: ${response.statusCode}');
      debugPrint('Delete Story Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Story could not be deleted');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Story could not be deleted: ${response.statusCode}');
    }
  }

  // Helper method to safely parse JSON response
  Map<String, dynamic>? _tryParseJson(String body) {
    try {
      // Check if body starts with HTML/XML tags
      final trimmedBody = body.trim();
      if (trimmedBody.startsWith('<') || 
          trimmedBody.toLowerCase().contains('<!doctype') ||
          trimmedBody.toLowerCase().contains('<html')) {
        return null;
      }
      return json.decode(body) as Map<String, dynamic>;
    } catch (e) {
      return null;
    }
  }

  // Add media
  Future<Map<String, dynamic>> addMedia(int eventId, String filePath, String description) async {
    if (kDebugMode) {
      debugPrint('Add Media -> ${AppConstants.baseUrl}/add_media.php');
    }

    final sessionKey = await _getSessionKey();
    
    if (kDebugMode) {
      debugPrint('🔐 Add Media - Session Key: $sessionKey');
      debugPrint('🔐 Add Media - Event ID: $eventId');
      debugPrint('🔐 Add Media - File Path: $filePath');
    }
    
    // Create multipart request
    var request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConstants.baseUrl}/add_media.php'),
    );
    
    // Add headers (multipart için Content-Type ekleme, otomatik oluşturulur)
    request.headers['Accept'] = 'application/json';
    request.headers['User-Agent'] = 'DigitalSalon-Mobile/1.0';
    
    if (sessionKey != null && sessionKey.isNotEmpty) {
      request.headers['Cookie'] = 'PHPSESSID=$sessionKey';
      if (kDebugMode) {
        debugPrint('🔐 Add Media - Cookie header set: PHPSESSID=$sessionKey');
      }
    } else {
      if (kDebugMode) {
        debugPrint('⚠️ Add Media - WARNING: No session key found!');
      }
    }
    
    // Add fields
    request.fields['event_id'] = eventId.toString();
    request.fields['description'] = description;
    
    // Add file
    request.files.add(await http.MultipartFile.fromPath('media_file', filePath));
    
    // ✅ Debug: Request headers'ı logla
    if (kDebugMode) {
      debugPrint('🔐 Add Media - Request Headers: ${request.headers}');
      debugPrint('🔐 Add Media - Request URL: ${request.url}');
    }
    
    final streamedResponse = await request.send();
    final response = await http.Response.fromStream(streamedResponse);

    if (kDebugMode) {
      debugPrint('Add Media Status: ${response.statusCode}');
      debugPrint('Add Media Body: ${response.body}');
      debugPrint('🔐 Add Media - Response Headers: ${response.headers}');
      
      // ✅ 403 durumunda detaylı log
      if (response.statusCode == 403) {
        debugPrint('❌ Add Media - 403 Forbidden!');
        debugPrint('❌ Add Media - Response contains HTML: ${response.body.contains('<!DOCTYPE html>') || response.body.contains('<html')}');
        debugPrint('❌ Add Media - Session Key used: $sessionKey');
      }
    }

    // Handle different status codes
    if (response.statusCode == 403) {
      final responseData = _tryParseJson(response.body);
      if (responseData != null && responseData['error'] != null) {
        // ✅ Backend'den gelen Türkçe hata mesajını göster
        throw Exception(responseData['error']);
      }
      // ✅ HTML response gelmişse (web server seviyesinde 403)
      if (response.body.contains('<!DOCTYPE html>') || response.body.contains('<html')) {
        throw Exception('Bu işlem için yetkiniz bulunmamaktadır. Lütfen etkinlik yöneticisi ile iletişime geçin.');
      }
      throw Exception('Bu işlem için yetkiniz bulunmamaktadır. Lütfen etkinlik yöneticisi ile iletişime geçin.');
    }

    // Try to parse JSON response
    final responseData = _tryParseJson(response.body);
    
    if (responseData == null) {
      // Response is not JSON (probably HTML error page)
      if (response.statusCode == 403) {
        throw Exception('Bu işlem için yetkiniz bulunmamaktadır. Lütfen etkinlik yöneticisi ile iletişime geçin.');
      }
      throw Exception('Sunucu hatası: API beklenmeyen bir yanıt döndü. Lütfen tekrar deneyin.');
    }

    if (response.statusCode == 200) {
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Gönderi yüklenemedi');
      }
    } else {
      throw Exception(responseData['error'] ?? 'Gönderi yüklenemedi: ${response.statusCode}');
    }
  }

  // Add story
  Future<Map<String, dynamic>> addStory(int eventId, String filePath, String description) async {
    if (kDebugMode) {
      debugPrint('Add Story -> ${AppConstants.baseUrl}/add_story.php');
    }

    final sessionKey = await _getSessionKey();
    
    // Create multipart request
    var request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConstants.baseUrl}/add_story.php'),
    );
    
    // Add headers (multipart için Content-Type ekleme, otomatik oluşturulur)
    request.headers['Accept'] = 'application/json';
    if (sessionKey != null) {
      request.headers['Cookie'] = 'PHPSESSID=$sessionKey';
    }
    
    // Add fields
    request.fields['event_id'] = eventId.toString();
    request.fields['description'] = description;
    
    // Add file
    request.files.add(await http.MultipartFile.fromPath('story_file', filePath));
    
    final streamedResponse = await request.send();
    final response = await http.Response.fromStream(streamedResponse);

    if (kDebugMode) {
      debugPrint('Add Story Status: ${response.statusCode}');
      debugPrint('Add Story Body: ${response.body}');
    }

    // Handle different status codes
    if (response.statusCode == 403) {
      final responseData = _tryParseJson(response.body);
      if (responseData != null && responseData['error'] != null) {
        throw Exception(responseData['error']);
      }
      throw Exception('Bu işlem için yetkiniz bulunmamaktadır. Lütfen etkinlik yöneticisi ile iletişime geçin.');
    }

    // Try to parse JSON response
    final responseData = _tryParseJson(response.body);
    
    if (responseData == null) {
      // Response is not JSON (probably HTML error page)
      if (response.statusCode == 403) {
        throw Exception('Bu işlem için yetkiniz bulunmamaktadır. Lütfen etkinlik yöneticisi ile iletişime geçin.');
      }
      throw Exception('Sunucu hatası: API beklenmeyen bir yanıt döndü. Lütfen tekrar deneyin.');
    }

    if (response.statusCode == 200) {
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Hikaye yüklenemedi');
      }
    } else {
      throw Exception(responseData['error'] ?? 'Hikaye yüklenemedi: ${response.statusCode}');
    }
  }

  // Get user's stories for a specific event
  Future<List<Map<String, dynamic>>> getUserStories(int eventId, int userId) async {
    if (kDebugMode) {
      debugPrint('Get User Stories -> ${AppConstants.baseUrl}/stories.php?event_id=$eventId&user_id=$userId');
    }

    final response = await http.get(
      Uri.parse('${AppConstants.baseUrl}/stories.php?event_id=$eventId&user_id=$userId'),
      headers: await _getHeaders(),
    );

    if (kDebugMode) {
      debugPrint('User Stories Status: ${response.statusCode}');
      debugPrint('User Stories Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return List<Map<String, dynamic>>.from(responseData['stories'] ?? []);
      } else {
        throw Exception(responseData['error'] ?? 'Stories could not be loaded');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Stories could not be loaded: ${response.statusCode}');
    }
  }

  // Join event by QR code
  Future<bool> joinEventByQR(String qrCode) async {
    if (kDebugMode) {
      debugPrint('Join Event by QR -> ${AppConstants.baseUrl}/qr_scanner.php');
    }

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/qr_scanner.php'),
      headers: await _getHeaders(),
      body: {
        'qr_code': qrCode,
      },
    );

    if (kDebugMode) {
      debugPrint('Join Event QR Status: ${response.statusCode}');
      debugPrint('Join Event QR Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      return responseData['success'] == true;
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Failed to join event: ${response.statusCode}');
    }
  }

  // Edit story caption
  Future<bool> editStory(int storyId, String caption) async {
    if (kDebugMode) {
      debugPrint('Edit Story -> ${AppConstants.baseUrl}/edit_story.php');
    }

    final headers = await _getHeaders();
    headers['Content-Type'] = 'application/json';

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/edit_story.php'),
      headers: headers,
      body: json.encode({
        'story_id': storyId,
        'caption': caption,
      }),
    );

    if (kDebugMode) {
      debugPrint('Edit Story Status: ${response.statusCode}');
      debugPrint('Edit Story Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      return responseData['success'] == true;
    } else {
      throw Exception('Failed to edit story: ${response.statusCode}');
    }
  }


  // Get participants for an event
  Future<List<Map<String, dynamic>>> getParticipants(int eventId) async {
    if (kDebugMode) {
      debugPrint('Get Participants -> ${AppConstants.baseUrl}/participants.php?event_id=$eventId');
    }

    final headers = await _getHeaders();
    final response = await _makeRequest(() => http.get(
      Uri.parse('${AppConstants.baseUrl}/participants.php?event_id=$eventId'),
      headers: headers,
    ));

    if (kDebugMode) {
      debugPrint('Participants Status: ${response.statusCode}');
      debugPrint('Participants Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return List<Map<String, dynamic>>.from(responseData['participants']);
      } else {
        throw Exception(responseData['error'] ?? 'Failed to fetch participants');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Failed to fetch participants: ${response.statusCode}');
    }
  }

  // Update participant role or status
  Future<Map<String, dynamic>> updateParticipant({
    required int eventId,
    required int targetUserId,
    String? newRole,
    String? newStatus,
  }) async {
    if (kDebugMode) {
      debugPrint('Update Participant -> ${AppConstants.baseUrl}/update_participant.php');
    }

    final headers = await _getHeaders();
    final body = {
      'event_id': eventId.toString(), // ✅ String'e çevir
      'target_user_id': targetUserId.toString(), // ✅ String'e çevir
      if (newRole != null) 'new_role': newRole,
      if (newStatus != null) 'new_status': newStatus,
    };

    final response = await _makeRequest(() => http.post(
      Uri.parse('${AppConstants.baseUrl}/update_participant.php'),
      headers: headers,
      body: body,
    ));

    if (kDebugMode) {
      debugPrint('Update Participant Status: ${response.statusCode}');
      debugPrint('Update Participant Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Failed to update participant');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Failed to update participant: ${response.statusCode}');
    }
  }

  // Grant permissions to a user
  Future<Map<String, dynamic>> grantPermissions({
    required int eventId,
    required int targetUserId,
    required List<String> permissions,
  }) async {
    final body = {
      'event_id': eventId.toString(),
      'target_user_id': targetUserId.toString(),
      'permissions': json.encode(permissions), // JSON string olarak gönder
    };

    if (kDebugMode) {
      debugPrint('Grant Permissions -> ${AppConstants.baseUrl}/grant_permissions.php');
      debugPrint('Grant Permissions Body: $body');
    }

    final headers = await _getHeaders();
    headers['Content-Type'] = 'application/x-www-form-urlencoded';

    final response = await _makeRequest(() => http.post(
      Uri.parse('${AppConstants.baseUrl}/grant_permissions.php'),
      headers: headers,
      body: Uri(queryParameters: body).query,
    ));

    if (kDebugMode) {
      debugPrint('Grant Permissions Status: ${response.statusCode}');
      debugPrint('Grant Permissions Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Failed to grant permissions');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Failed to grant permissions: ${response.statusCode}');
    }
  }

  // Update user profile
  // Check if username is available
  Future<bool> checkUsernameAvailability(String username, {int? currentUserId}) async {
    final sessionKey = await _getSessionKey();
    
    if (sessionKey == null) {
      throw Exception('Session not found. Please login again.');
    }
    
    final headers = await _getHeaders();
    final response = await http.post(
      Uri.parse('$_baseUrl/check_username.php'),
      headers: headers,
      body: {
        'username': username,
        if (currentUserId != null) 'user_id': currentUserId.toString(),
      },
    );
    
    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      return responseData['available'] == true;
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Failed to check username availability');
    }
  }

  Future<User> updateUserProfile({
    required int userId,
    required String name,
    required String surname,
    required String email,
    required String phone,
    required String username,
    File? profileImage,
  }) async {
    final sessionKey = await _getSessionKey();
    
    if (sessionKey == null) {
      throw Exception('Session not found. Please login again.');
    }
    
    // Create multipart request
    var request = http.MultipartRequest(
      'POST',
      Uri.parse('$_baseUrl/update_profile.php'),
    );
    
    // ✅ Add headers (multipart için Content-Type ekleme, otomatik oluşturulur)
    request.headers['Accept'] = 'application/json';
    request.headers['User-Agent'] = 'DigitalSalon-Mobile/1.0';
    
    if (sessionKey != null && sessionKey.isNotEmpty) {
      request.headers['Cookie'] = 'PHPSESSID=$sessionKey';
      if (kDebugMode) {
        debugPrint('🔐 Update Profile - Cookie header set: PHPSESSID=$sessionKey');
      }
    } else {
      if (kDebugMode) {
        debugPrint('⚠️ Update Profile - WARNING: No session key found!');
      }
    }
    
    // Add form fields
    request.fields['user_id'] = userId.toString();
    request.fields['name'] = name;
    request.fields['surname'] = surname;
    request.fields['email'] = email;
    request.fields['phone'] = phone;
    request.fields['username'] = username;
    
    // Add profile image if provided
    if (profileImage != null) {
      request.files.add(
        await http.MultipartFile.fromPath(
          'profile_image',
          profileImage.path,
        ),
      );
    }
    
    debugPrint('Update Profile -> ${request.url}');
    debugPrint('Session Key: $sessionKey');
    
    final streamedResponse = await request.send();
    final response = await http.Response.fromStream(streamedResponse);
    
    debugPrint('Update Profile Status: ${response.statusCode}');
    debugPrint('Update Profile Body: ${response.body}');
    
    // ✅ Handle 403 Forbidden
    if (response.statusCode == 403) {
      final responseData = _tryParseJson(response.body);
      if (responseData != null && responseData['error'] != null) {
        throw Exception(responseData['error']);
      }
      throw Exception('Yetkiniz bulunmamaktadır. Lütfen tekrar giriş yapın.');
    }
    
    // ✅ Try to parse JSON response
    final responseData = _tryParseJson(response.body);
    
    if (response.statusCode == 200 && responseData != null && responseData['success'] == true) {
      return User.fromJson(responseData['user']);
    } else {
      final error = responseData?['error'] ?? 'Failed to update profile: ${response.statusCode}';
      throw Exception(error);
    }
  }

  // ✅ Forgot Password - Send reset email
  Future<Map<String, dynamic>> forgotPassword(String email) async {
    if (kDebugMode) {
      debugPrint('Forgot Password -> ${AppConstants.baseUrl}/forgot_password.php');
    }

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/forgot_password.php'),
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json',
      },
      body: {
        'email': email,
      },
    );

    if (kDebugMode) {
      debugPrint('Forgot Password Status: ${response.statusCode}');
      debugPrint('Forgot Password Body: ${response.body}');
    }

    final responseData = _tryParseJson(response.body);
    
    if (response.statusCode == 200 && responseData != null) {
      return responseData;
    } else {
      throw Exception(responseData?['message'] ?? 'Şifre sıfırlama isteği başarısız: ${response.statusCode}');
    }
  }

  // ✅ Verify Reset Code - Verify 6-digit code and get token
  Future<Map<String, dynamic>> verifyResetCode(String email, String code) async {
    if (kDebugMode) {
      debugPrint('Verify Reset Code -> ${AppConstants.baseUrl}/verify_reset_code.php');
    }

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/verify_reset_code.php'),
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json',
      },
      body: {
        'email': email,
        'code': code,
      },
    );

    if (kDebugMode) {
      debugPrint('Verify Reset Code Status: ${response.statusCode}');
      debugPrint('Verify Reset Code Body: ${response.body}');
    }

    final responseData = _tryParseJson(response.body);
    
    if (response.statusCode == 200 && responseData != null) {
      return responseData;
    } else {
      throw Exception(responseData?['message'] ?? 'Doğrulama kodu hatalı: ${response.statusCode}');
    }
  }

  // ✅ Reset Password - Set new password with token
  Future<Map<String, dynamic>> resetPassword(String token, String newPassword) async {
    if (kDebugMode) {
      debugPrint('Reset Password -> ${AppConstants.baseUrl}/reset_password.php');
    }

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/reset_password.php'),
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json',
      },
      body: {
        'token': token,
        'password': newPassword,
      },
    );

    if (kDebugMode) {
      debugPrint('Reset Password Status: ${response.statusCode}');
      debugPrint('Reset Password Body: ${response.body}');
    }

    final responseData = _tryParseJson(response.body);
    
    if (response.statusCode == 200 && responseData != null) {
      return responseData;
    } else {
      throw Exception(responseData?['message'] ?? 'Şifre güncelleme başarısız: ${response.statusCode}');
    }
  }

  // ✅ Google OAuth Login
  Future<Map<String, dynamic>> googleOAuthLogin(String idToken, String deviceInfo) async {
    debugPrint('🔵 ApiService - Google OAuth başlatılıyor...');
    debugPrint('🔵 ApiService - URL: ${AppConstants.baseUrl}/oauth/google.php');
    debugPrint('🔵 ApiService - ID Token uzunluk: ${idToken.length}');

    try {
      final response = await http.post(
        Uri.parse('${AppConstants.baseUrl}/oauth/google.php'),
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
          'Accept': 'application/json',
        },
        body: {
          'id_token': idToken,
          'device_info': deviceInfo,
        },
      ).timeout(const Duration(seconds: 30));

      debugPrint('🔵 ApiService - HTTP Status: ${response.statusCode}');
      debugPrint('🔵 ApiService - Response Headers: ${response.headers}');
      debugPrint('🔵 ApiService - Response Body (ilk 500 karakter): ${response.body.length > 500 ? response.body.substring(0, 500) + "..." : response.body}');

      final responseData = _tryParseJson(response.body);
      
      if (responseData == null) {
        debugPrint('❌ ApiService - JSON parse hatası!');
        debugPrint('❌ ApiService - Raw response: ${response.body}');
        throw Exception('Sunucudan geçersiz yanıt alındı');
      }
      
      debugPrint('🔵 ApiService - Parsed JSON: success=${responseData['success']}, user=${responseData['user'] != null ? "var" : "yok"}');
      
      if (response.statusCode == 200 && responseData['success'] == true) {
        debugPrint('🔵 ApiService - Google OAuth başarılı!');
        
        // Extract session cookie
        final setCookie = response.headers['set-cookie'];
        String? sessionId;
        if (setCookie != null) {
          debugPrint('🔵 ApiService - Set-Cookie header bulundu: ${setCookie.substring(0, setCookie.length > 50 ? 50 : setCookie.length)}...');
          final match = RegExp(r'PHPSESSID=([^;]+)').firstMatch(setCookie);
          if (match != null) {
            sessionId = match.group(1)!;
            debugPrint('🔵 ApiService - Session ID alındı: ${sessionId.substring(0, sessionId.length > 20 ? 20 : sessionId.length)}...');
            final prefs = await SharedPreferences.getInstance();
            await prefs.setString(AppConstants.sessionKeyKey, sessionId);
          } else {
            debugPrint('⚠️ ApiService - PHPSESSID bulunamadı');
          }
        } else {
          debugPrint('⚠️ ApiService - Set-Cookie header yok');
        }
        
        // Add session key to response
        if (responseData['user'] != null) {
          if (sessionId != null) {
            responseData['user']['session_key'] = sessionId;
            debugPrint('🔵 ApiService - Session key eklendi');
          }
          debugPrint('🔵 ApiService - User data: ${responseData['user'].keys.join(", ")}');
        }
        
        return responseData;
      } else {
        final error = responseData['error'] ?? responseData['message'] ?? 'Bilinmeyen hata';
        debugPrint('❌ ApiService - Google OAuth başarısız!');
        debugPrint('❌ ApiService - Status Code: ${response.statusCode}');
        debugPrint('❌ ApiService - Error: $error');
        debugPrint('❌ ApiService - Full Response: $responseData');
        throw Exception(error.toString());
      }
    } on TimeoutException {
      debugPrint('❌ ApiService - Request timeout!');
      throw Exception('Bağlantı zaman aşımına uğradı. Lütfen tekrar deneyin.');
    } on SocketException {
      debugPrint('❌ ApiService - Network error!');
      throw Exception('İnternet bağlantınızı kontrol edin');
    } catch (e) {
      debugPrint('❌ ApiService - Exception: $e');
      rethrow;
    }
  }

  // ✅ Apple Sign In Login
  Future<Map<String, dynamic>> appleSignInLogin({
    required String identityToken,
    String? authorizationCode,
    String? email,
    String? givenName,
    String? familyName,
    required String userId,
    String? deviceInfo,
  }) async {
    if (kDebugMode) {
      debugPrint('Apple Sign In -> ${AppConstants.baseUrl}/oauth/apple.php');
    }

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/oauth/apple.php'),
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'Accept': 'application/json',
      },
      body: {
        'identity_token': identityToken,
        if (authorizationCode != null) 'authorization_code': authorizationCode,
        if (email != null) 'email': email,
        if (givenName != null) 'given_name': givenName,
        if (familyName != null) 'family_name': familyName,
        'user_id': userId,
        if (deviceInfo != null) 'device_info': deviceInfo,
      },
    );

    if (kDebugMode) {
      debugPrint('Apple Sign In Status: ${response.statusCode}');
      debugPrint('Apple Sign In Body: ${response.body}');
    }

    final responseData = _tryParseJson(response.body);
    
    if (response.statusCode == 200 && responseData != null && responseData['success'] == true) {
      // Extract session cookie
      final setCookie = response.headers['set-cookie'];
      String? sessionId;
      if (setCookie != null) {
        final match = RegExp(r'PHPSESSID=([^;]+)').firstMatch(setCookie);
        if (match != null) {
          sessionId = match.group(1)!;
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString(AppConstants.sessionKeyKey, sessionId);
        }
      }
      
      // Add session key to response
      if (responseData['user'] != null && sessionId != null) {
        responseData['user']['session_key'] = sessionId;
      }
      
      return responseData;
    } else {
      throw Exception(responseData?['error'] ?? 'Apple Sign In başarısız: ${response.statusCode}');
    }
  }

  // ✅ Debug: Check user permissions for an event
  Future<Map<String, dynamic>> checkPermissions(int eventId) async {
    if (kDebugMode) {
      debugPrint('Check Permissions -> ${AppConstants.baseUrl}/check_permissions.php?event_id=$eventId');
    }

    final headers = await _getHeaders();
    final response = await http.get(
      Uri.parse('${AppConstants.baseUrl}/check_permissions.php?event_id=$eventId'),
      headers: headers,
    );

    if (kDebugMode) {
      debugPrint('Check Permissions Status: ${response.statusCode}');
      debugPrint('Check Permissions Body: ${response.body}');
    }

    final responseData = _tryParseJson(response.body);
    
    if (response.statusCode == 200 && responseData != null) {
      return responseData;
    } else {
      throw Exception(responseData?['error'] ?? 'Yetki kontrolü başarısız: ${response.statusCode}');
    }
  }

  // Check media/story upload limit
  Future<Map<String, dynamic>> checkMediaLimit(int eventId, {String type = 'media'}) async {
    if (kDebugMode) {
      debugPrint('Check Media Limit -> ${AppConstants.baseUrl}/check_media_limit.php?event_id=$eventId&type=$type');
    }

    final headers = await _getHeaders();
    final response = await http.get(
      Uri.parse('${AppConstants.baseUrl}/check_media_limit.php?event_id=$eventId&type=$type'),
      headers: headers,
    );

    if (kDebugMode) {
      debugPrint('Check Media Limit Status: ${response.statusCode}');
      debugPrint('Check Media Limit Body: ${response.body}');
    }

    final responseData = _tryParseJson(response.body);
    
    if (response.statusCode == 200 && responseData != null) {
      return responseData;
    } else {
      throw Exception(responseData?['error'] ?? 'Limit kontrolü başarısız: ${response.statusCode}');
    }
  }

  // ✅ INSTAGRAM STYLE: Tek endpoint ile profil istatistikleri (ÇOK HIZLI!)
  Future<Map<String, dynamic>> getProfileStats({int? userId}) async {
    if (kDebugMode) {
      final url = userId != null 
          ? '${AppConstants.baseUrl}/get_profile_stats.php?user_id=$userId'
          : '${AppConstants.baseUrl}/get_profile_stats.php';
      debugPrint('🚀 Get Profile Stats -> $url');
    }

    final headers = await _getHeaders();
    final url = userId != null 
        ? '${AppConstants.baseUrl}/get_profile_stats.php?user_id=$userId'
        : '${AppConstants.baseUrl}/get_profile_stats.php';
    
    final response = await _makeRequest(
      () => http.get(Uri.parse(url), headers: headers),
      timeout: const Duration(seconds: 10), // ✅ Hızlı timeout
    );

    if (kDebugMode) {
      debugPrint('🚀 Profile Stats Status: ${response.statusCode}');
      if (response.statusCode == 200) {
        debugPrint('🚀 Profile Stats Body: ${response.body}');
      }
    }

    final responseData = _tryParseJson(response.body);
    
    if (response.statusCode == 200 && responseData != null && responseData['success'] == true) {
      return responseData;
    } else {
      throw Exception(responseData?['error'] ?? 'Profil istatistikleri alınamadı: ${response.statusCode}');
    }
  }

  // Register new user
  Future<Map<String, dynamic>> register({
    required String name,
    required String surname,
    required String email,
    required String phone,
    required String username,
    required String password,
    required String deviceInfo,
    required String ipAddress,
  }) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/register.php'),
      headers: await _getHeaders(),
      body: {
        'name': name,
        'surname': surname,
        'email': email,
        'phone': phone,
        'username': username,
        'password': password,
        'device_info': deviceInfo,
        'ip_address': ipAddress,
      },
    );

    debugPrint('Register Status: ${response.statusCode}');
    debugPrint('Register Body: ${response.body}');

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      return responseData;
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Failed to register: ${response.statusCode}');
    }
  }

  // Get logs (super admin only)
  Future<Map<String, dynamic>> getLogs({
    String action = 'all',
    String? userId,
    int page = 1,
    int limit = 50,
  }) async {
    final sessionKey = await _getSessionKey();
    
    if (sessionKey == null) {
      throw Exception('Session not found. Please login again.');
    }

    final queryParams = <String, String>{
      'action': action,
      'page': page.toString(),
      'limit': limit.toString(),
    };

    if (userId != null) {
      queryParams['user_id'] = userId;
    }

    final uri = Uri.parse('$_baseUrl/get_logs.php').replace(
      queryParameters: queryParams,
    );

    final response = await http.get(
      uri,
      headers: await _getHeaders(),
    );

    debugPrint('Get Logs Status: ${response.statusCode}');
    debugPrint('Get Logs Body: ${response.body}');

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      return responseData;
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Failed to get logs: ${response.statusCode}');
    }
  }

  // Check field availability (email, phone, username)
  Future<Map<String, dynamic>> checkField({
    required String field,
    required String value,
    int? currentUserId,
  }) async {
    final response = await http.post(
      Uri.parse('$_baseUrl/check_field.php'),
      headers: await _getHeaders(),
      body: {
        'field': field,
        'value': value,
        if (currentUserId != null) 'current_user_id': currentUserId.toString(),
      },
    );

    debugPrint('Check Field Status: ${response.statusCode}');
    debugPrint('Check Field Body: ${response.body}');

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      return responseData;
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Failed to check field: ${response.statusCode}');
    }
  }

  // Search users
  Future<Map<String, dynamic>> searchUsers({
    required String query,
    int page = 1,
    int limit = 20,
  }) async {
    final sessionKey = await _getSessionKey();
    
    if (sessionKey == null) {
      throw Exception('Session not found. Please login again.');
    }

    final queryParams = <String, String>{
      'q': query,
      'page': page.toString(),
      'limit': limit.toString(),
    };

    final uri = Uri.parse('$_baseUrl/search_users.php').replace(
      queryParameters: queryParams,
    );

    final response = await http.get(
      uri,
      headers: await _getHeaders(),
    );

    debugPrint('Search Users Status: ${response.statusCode}');
    debugPrint('Search Users Body: ${response.body}');

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      return responseData;
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Failed to search users: ${response.statusCode}');
    }
  }

  // Get user by ID
  Future<User> getUserById(int userId) async {
    final sessionKey = await _getSessionKey();
    
    if (sessionKey == null) {
      throw Exception('Session not found. Please login again.');
    }

    final response = await http.get(
      Uri.parse('$_baseUrl/get_user_by_id.php?user_id=$userId'),
      headers: await _getHeaders(),
    );

    debugPrint('Get User By ID Status: ${response.statusCode}');
    debugPrint('Get User By ID Body: ${response.body}');

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return User.fromJson(responseData['user']);
      } else {
        throw Exception(responseData['error'] ?? 'User not found');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Failed to get user: ${response.statusCode}');
    }
  }

  // Get user events by user ID
  Future<List<Event>> getUserEvents(int userId) async {
    final sessionKey = await _getSessionKey();
    
    if (sessionKey == null) {
      throw Exception('Session not found. Please login again.');
    }

    final response = await http.get(
      Uri.parse('$_baseUrl/get_user_events.php?user_id=$userId'),
      headers: await _getHeaders(),
    );

    debugPrint('Get User Events Status: ${response.statusCode}');
    debugPrint('Get User Events Body: ${response.body}');

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        final eventsData = responseData['events'] as List<dynamic>;
        return eventsData.map((eventData) => Event.fromJson(eventData)).toList();
      } else {
        throw Exception(responseData['error'] ?? 'Events not found');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Failed to get user events: ${response.statusCode}');
    }
  }


  // ✅ Manuel Bildirim Gönderme
  Future<Map<String, dynamic>> sendCustomNotification({
    required int eventId,
    required String title,
    required String message,
  }) async {
    if (kDebugMode) {
      debugPrint('Send Custom Notification -> ${AppConstants.baseUrl}/send_custom_notification.php');
    }

    final headers = await _getHeaders();
    headers['Content-Type'] = 'application/json';

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/send_custom_notification.php'),
      headers: headers,
      body: json.encode({
        'event_id': eventId,
        'title': title,
        'message': message,
      }),
    );

    if (kDebugMode) {
      debugPrint('Send Custom Notification Status: ${response.statusCode}');
      debugPrint('Send Custom Notification Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Bildirim gönderilemedi');
      }
    } else {
      throw Exception('Bildirim gönderilemedi: ${response.statusCode}');
    }
  }

  /// ✅ Save FCM token to backend
  Future<void> saveFCMToken(String fcmToken) async {
    if (kDebugMode) {
      debugPrint('Save FCM Token -> ${AppConstants.baseUrl}/save_fcm_token.php');
    }

    final headers = await _getHeaders();
    headers['Content-Type'] = 'application/json';

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/save_fcm_token.php'),
      headers: headers,
      body: json.encode({
        'fcm_token': fcmToken,
      }),
    );

    if (kDebugMode) {
      debugPrint('Save FCM Token Status: ${response.statusCode}');
      debugPrint('Save FCM Token Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] != true) {
        throw Exception(responseData['error'] ?? 'FCM token kaydedilemedi');
      }
    } else {
      throw Exception('FCM token kaydedilemedi: ${response.statusCode}');
    }
  }

  // ✅ Notifications

  /// Get notifications
  Future<Map<String, dynamic>> getNotifications({bool? isRead, int? page, int? limit}) async {
    if (kDebugMode) {
      debugPrint('Get Notifications -> ${AppConstants.baseUrl}/get_notifications.php');
    }

    final headers = await _getHeaders();
    var url = '${AppConstants.baseUrl}/get_notifications.php';
    final params = <String>[];
    if (isRead != null) {
      params.add('is_read=${isRead ? "1" : "0"}');
    }
    if (page != null) {
      params.add('page=$page');
    }
    if (limit != null) {
      params.add('limit=$limit');
    }
    if (params.isNotEmpty) {
      url += '?' + params.join('&');
    }
    
    if (kDebugMode) {
      debugPrint('Get Notifications URL: $url');
    }

    final response = await http.get(
      Uri.parse(url),
      headers: headers,
    );

    if (kDebugMode) {
      debugPrint('Get Notifications Status: ${response.statusCode}');
      debugPrint('Get Notifications Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Bildirimler alınamadı');
      }
    } else {
      throw Exception('Bildirimler alınamadı: ${response.statusCode}');
    }
  }

  /// Mark all notifications as read
  Future<void> markAllNotificationsAsRead() async {
    if (kDebugMode) {
      debugPrint('Mark All Notifications Read -> ${AppConstants.baseUrl}/mark_all_notifications_read.php');
    }

    final headers = await _getHeaders();
    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/mark_all_notifications_read.php'),
      headers: headers,
    );

    if (kDebugMode) {
      debugPrint('Mark All Notifications Read Status: ${response.statusCode}');
      debugPrint('Mark All Notifications Read Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] != true) {
        throw Exception(responseData['error'] ?? 'Bildirimler işaretlenemedi');
      }
    } else {
      throw Exception('Bildirimler işaretlenemedi: ${response.statusCode}');
    }
  }

  /// Mark notification as read
  Future<void> markNotificationAsRead(int notificationId) async {
    if (kDebugMode) {
      debugPrint('Mark Notification Read -> ${AppConstants.baseUrl}/mark_notification_read.php');
    }

    final headers = await _getHeaders();
    headers['Content-Type'] = 'application/json';

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/mark_notification_read.php'),
      headers: headers,
      body: json.encode({
        'notification_id': notificationId,
      }),
    );

    if (kDebugMode) {
      debugPrint('Mark Notification Read Status: ${response.statusCode}');
      debugPrint('Mark Notification Read Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] != true) {
        throw Exception(responseData['error'] ?? 'Bildirim işaretlenemedi');
      }
    } else {
      throw Exception('Bildirim işaretlenemedi: ${response.statusCode}');
    }
  }

  /// Delete notification (by ID or by media_id + event_id + type for grouped notifications)
  Future<void> deleteNotification({
    int? notificationId,
    int? mediaId,
    int? eventId,
    String? type,
  }) async {
    if (kDebugMode) {
      debugPrint('Delete Notification -> ${AppConstants.baseUrl}/delete_notification.php');
    }

    final headers = await _getHeaders();
    headers['Content-Type'] = 'application/json';

    final body = <String, dynamic>{};
    if (notificationId != null) {
      body['notification_id'] = notificationId;
    }
    if (mediaId != null && eventId != null && type != null) {
      body['media_id'] = mediaId;
      body['event_id'] = eventId;
      body['type'] = type;
    }

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/delete_notification.php'),
      headers: headers,
      body: json.encode(body),
    );

    if (kDebugMode) {
      debugPrint('Delete Notification Status: ${response.statusCode}');
      debugPrint('Delete Notification Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] != true) {
        throw Exception(responseData['error'] ?? 'Bildirim silinemedi');
      }
    } else {
      throw Exception('Bildirim silinemedi: ${response.statusCode}');
    }
  }

  /// Clear all notifications
  Future<void> clearAllNotifications() async {
    if (kDebugMode) {
      debugPrint('Clear All Notifications -> ${AppConstants.baseUrl}/clear_all_notifications.php');
    }

    final headers = await _getHeaders();
    headers['Content-Type'] = 'application/json';

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/clear_all_notifications.php'),
      headers: headers,
    );

    if (kDebugMode) {
      debugPrint('Clear All Notifications Status: ${response.statusCode}');
      debugPrint('Clear All Notifications Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] != true) {
        throw Exception(responseData['error'] ?? 'Bildirimler temizlenemedi');
      }
    } else {
      throw Exception('Bildirimler temizlenemedi: ${response.statusCode}');
    }
  }

  /// Like/Unlike media
  Future<Map<String, dynamic>> likeMedia(int mediaId, String action) async {
    if (kDebugMode) {
      debugPrint('Like Media -> ${AppConstants.baseUrl}/like_media.php');
    }

    final headers = await _getHeaders();
    headers['Content-Type'] = 'application/json';

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/like_media.php'),
      headers: headers,
      body: json.encode({
        'media_id': mediaId,
        'action': action, // 'like' or 'unlike'
      }),
    );

    if (kDebugMode) {
      debugPrint('Like Media Status: ${response.statusCode}');
      debugPrint('Like Media Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Beğeni işlemi başarısız');
      }
    } else {
      throw Exception('Beğeni işlemi başarısız: ${response.statusCode}');
    }
  }

  /// Add comment
  Future<Map<String, dynamic>> addComment(int mediaId, String comment) async {
    if (kDebugMode) {
      debugPrint('Add Comment -> ${AppConstants.baseUrl}/add_comment.php');
    }

    final headers = await _getHeaders();
    headers['Content-Type'] = 'application/json';

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/add_comment.php'),
      headers: headers,
      body: json.encode({
        'media_id': mediaId,
        'comment': comment,
      }),
    );

    if (kDebugMode) {
      debugPrint('Add Comment Status: ${response.statusCode}');
      debugPrint('Add Comment Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Yorum eklenemedi');
      }
    } else {
      throw Exception('Yorum eklenemedi: ${response.statusCode}');
    }
  }
}