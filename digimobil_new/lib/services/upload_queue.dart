import 'dart:convert';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:digimobil_new/utils/constants.dart';

/// Upload queue service - Upload işlemlerini kuyruğa alır ve arka planda işler
class UploadQueue {
  static const String _queueKey = 'upload_queue';

  /// Upload işlemini queue'ya ekle
  static Future<void> addToQueue({
    required int eventId,
    required String filePath,
    required String description,
    required String type, // 'media' veya 'story'
  }) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final queueJson = prefs.getString(_queueKey) ?? '[]';
      final queue = json.decode(queueJson) as List;
      
      queue.add({
        'event_id': eventId,
        'file_path': filePath,
        'description': description,
        'type': type,
        'created_at': DateTime.now().toIso8601String(),
        'status': 'pending',
      });
      
      await prefs.setString(_queueKey, json.encode(queue));
    } catch (e) {
      print('Upload queue error: $e');
    }
  }

  /// Queue'dan tüm pending işlemleri getir
  static Future<List<Map<String, dynamic>>> getPendingUploads() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final queueJson = prefs.getString(_queueKey) ?? '[]';
      final queue = json.decode(queueJson) as List;
      
      return queue
          .where((item) => item['status'] == 'pending')
          .map((item) => item as Map<String, dynamic>)
          .toList();
    } catch (e) {
      print('Get pending uploads error: $e');
      return [];
    }
  }

  /// Upload işlemini tamamlandı olarak işaretle
  static Future<void> markAsCompleted(String filePath) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final queueJson = prefs.getString(_queueKey) ?? '[]';
      final queue = json.decode(queueJson) as List;
      
      for (var item in queue) {
        if (item['file_path'] == filePath) {
          item['status'] = 'completed';
          item['completed_at'] = DateTime.now().toIso8601String();
          break;
        }
      }
      
      await prefs.setString(_queueKey, json.encode(queue));
      
      // Tamamlanan işlemleri temizle (30 günden eski)
      await _cleanCompletedUploads();
    } catch (e) {
      print('Mark as completed error: $e');
    }
  }

  /// Upload işlemini başarısız olarak işaretle
  static Future<void> markAsFailed(String filePath, String error) async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final queueJson = prefs.getString(_queueKey) ?? '[]';
      final queue = json.decode(queueJson) as List;
      
      for (var item in queue) {
        if (item['file_path'] == filePath) {
          item['status'] = 'failed';
          item['error'] = error;
          item['failed_at'] = DateTime.now().toIso8601String();
          break;
        }
      }
      
      await prefs.setString(_queueKey, json.encode(queue));
    } catch (e) {
      print('Mark as failed error: $e');
    }
  }

  /// Tamamlanan işlemleri temizle (30 günden eski)
  static Future<void> _cleanCompletedUploads() async {
    try {
      final prefs = await SharedPreferences.getInstance();
      final queueJson = prefs.getString(_queueKey) ?? '[]';
      final queue = json.decode(queueJson) as List;
      final now = DateTime.now();
      
      queue.removeWhere((item) {
        if (item['status'] == 'completed' && item['completed_at'] != null) {
          final completedAt = DateTime.tryParse(item['completed_at']);
          if (completedAt != null) {
            return now.difference(completedAt).inDays > 30;
          }
        }
        return false;
      });
      
      await prefs.setString(_queueKey, json.encode(queue));
    } catch (e) {
      print('Clean completed uploads error: $e');
    }
  }
}

