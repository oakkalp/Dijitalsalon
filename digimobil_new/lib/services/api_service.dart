import 'dart:convert';
import 'package:flutter/foundation.dart';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';
import 'package:digimobil_new/models/user.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/utils/constants.dart';

class ApiService {
  static final ApiService _instance = ApiService._internal();
  factory ApiService() => _instance;
  ApiService._internal();

  final String _baseUrl = AppConstants.baseUrl;

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
        return response;
      } catch (e) {
        lastException = e is Exception ? e : Exception(e.toString());
        if (kDebugMode) {
          debugPrint('API request attempt $attempts failed: $e');
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

    final headers = await _getHeaders();
    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}${AppConstants.loginEndpoint}'),
      headers: headers,
      body: {
        'login': login, // ✅ Email, telefon veya kullanıcı adı
        'password': password,
      },
    );

    if (kDebugMode) {
      debugPrint('Login Status: ${response.statusCode}');
      debugPrint('Login Headers: ${response.headers}');
      debugPrint('Login Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      // Extract session cookie
      final setCookie = response.headers['set-cookie'];
      if (setCookie != null) {
        final match = RegExp(r'PHPSESSID=([^;]+)').firstMatch(setCookie);
        if (match != null) {
          final sessionId = match.group(1)!;
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString(AppConstants.sessionKeyKey, sessionId);
          
          if (kDebugMode) {
            debugPrint('Session saved: $sessionId');
          }
        }
      }

      // Parse JSON response
      final responseData = json.decode(response.body);
      if (responseData['success'] == true && responseData['user'] != null) {
        return User.fromJson(responseData['user']);
      } else {
        throw Exception(responseData['error'] ?? 'Giriş başarısız');
      }
    } else {
      throw Exception('Sunucu hatası: ${response.statusCode}');
    }
  }

  // Get events from new JSON API
  Future<List<Event>> getEvents() async {
    if (kDebugMode) {
      debugPrint('Get Events -> ${AppConstants.baseUrl}${AppConstants.eventsEndpoint}');
    }

    final headers = await _getHeaders();
    final response = await http.get(
      Uri.parse('${AppConstants.baseUrl}${AppConstants.eventsEndpoint}'),
      headers: headers,
    );

    if (kDebugMode) {
      debugPrint('Events Status: ${response.statusCode}');
      debugPrint('Events Body: ${response.body}');
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
  Future<Map<String, dynamic>> getMedia(int eventId, {int page = 1, int limit = 10}) async {
    if (kDebugMode) {
      debugPrint('Get Media -> ${AppConstants.baseUrl}/media.php?event_id=$eventId&page=$page&limit=$limit');
    }

    final headers = await _getHeaders();
    final response = await http.get(
      Uri.parse('${AppConstants.baseUrl}/media.php?event_id=$eventId&page=$page&limit=$limit'),
      headers: headers,
    );

    if (kDebugMode) {
      debugPrint('Media Status: ${response.statusCode}');
      debugPrint('Media Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true && responseData['media'] != null) {
        return {
          'media': List<Map<String, dynamic>>.from(responseData['media']),
          'pagination': responseData['pagination'] ?? {}
        };
      } else {
        throw Exception(responseData['error'] ?? 'Medya alınamadı');
      }
    } else {
      throw Exception('Medya alınamadı: ${response.statusCode}');
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

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Beğeni işlemi başarısız');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Beğeni işlemi başarısız: ${response.statusCode}');
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

  // Add comment to media
  Future<Map<String, dynamic>> addComment(int mediaId, String content) async {
    if (kDebugMode) {
      debugPrint('Add Comment -> ${AppConstants.baseUrl}/comments.php');
    }

    final headers = await _getHeaders();
    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/comments.php'),
      headers: headers,
      body: {
        'media_id': mediaId.toString(),
        'content': content,
      },
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
        throw Exception(responseData['error'] ?? 'Yorum ekleme başarısız');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Yorum ekleme başarısız: ${response.statusCode}');
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

  // Add media
  Future<Map<String, dynamic>> addMedia(int eventId, String filePath, String description) async {
    if (kDebugMode) {
      debugPrint('Add Media -> ${AppConstants.baseUrl}/add_media.php');
    }

    final headers = await _getHeaders();
    
    // Create multipart request
    var request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConstants.baseUrl}/add_media.php'),
    );
    
    // Add headers
    request.headers.addAll(headers);
    
    // Add fields
    request.fields['event_id'] = eventId.toString();
    request.fields['description'] = description;
    
    // Add file
    request.files.add(await http.MultipartFile.fromPath('media_file', filePath));
    
    final streamedResponse = await request.send();
    final response = await http.Response.fromStream(streamedResponse);

    if (kDebugMode) {
      debugPrint('Add Media Status: ${response.statusCode}');
      debugPrint('Add Media Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Media could not be uploaded');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Media could not be uploaded: ${response.statusCode}');
    }
  }

  // Add story
  Future<Map<String, dynamic>> addStory(int eventId, String filePath, String description) async {
    if (kDebugMode) {
      debugPrint('Add Story -> ${AppConstants.baseUrl}/add_story.php');
    }

    final headers = await _getHeaders();
    
    // Create multipart request
    var request = http.MultipartRequest(
      'POST',
      Uri.parse('${AppConstants.baseUrl}/add_story.php'),
    );
    
    // Add headers
    request.headers.addAll(headers);
    
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

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Story could not be uploaded');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Story could not be uploaded: ${response.statusCode}');
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

  // Like a media item
  Future<Map<String, dynamic>> likeMedia(int mediaId) async {
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
        throw Exception(responseData['error'] ?? 'Media could not be liked');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Media could not be liked: ${response.statusCode}');
    }
  }

  // Unlike a media item
  Future<Map<String, dynamic>> unlikeMedia(int mediaId) async {
    if (kDebugMode) {
      debugPrint('Unlike Media -> ${AppConstants.baseUrl}/unlike_media.php');
    }

    final headers = await _getHeaders();
    headers['Content-Type'] = 'application/json';

    final response = await http.post(
      Uri.parse('${AppConstants.baseUrl}/unlike_media.php'),
      headers: headers,
      body: json.encode({
        'media_id': mediaId,
      }),
    );

    if (kDebugMode) {
      debugPrint('Unlike Media Status: ${response.statusCode}');
      debugPrint('Unlike Media Body: ${response.body}');
    }

    if (response.statusCode == 200) {
      final responseData = json.decode(response.body);
      if (responseData['success'] == true) {
        return responseData;
      } else {
        throw Exception(responseData['error'] ?? 'Media could not be unliked');
      }
    } else {
      final responseData = json.decode(response.body);
      throw Exception(responseData['error'] ?? 'Media could not be unliked: ${response.statusCode}');
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
}