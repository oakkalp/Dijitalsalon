import 'package:flutter/foundation.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/services/api_service.dart';

class EventProvider with ChangeNotifier {
  List<Event> _events = [];
  Map<int, List<Map<String, dynamic>>> _eventMedia = {};
  Map<int, List<Map<String, dynamic>>> _eventStories = {}; // ✅ Hikayeler eklendi
  bool _isLoading = false;
  String? _errorMessage;
  String? _successMessage;
  Event? _lastJoinedEvent;

  List<Event> get events => _events;
  Map<int, List<Map<String, dynamic>>> get eventMedia => _eventMedia;
  Map<int, List<Map<String, dynamic>>> get eventStories => _eventStories; // ✅ Hikayeler getter
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  String? get successMessage => _successMessage;
  Event? get lastJoinedEvent => _lastJoinedEvent;

  final ApiService _apiService = ApiService();

  Future<void> loadEvents({bool loadMedia = true, bool loadStories = true, bool bypassCache = false}) async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      // Check session first
      final sessionValid = await _apiService.checkSession();
      if (!sessionValid) {
        throw Exception('Session expired. Please login again.');
      }
      
      _events = await _apiService.getEvents(bypassCache: bypassCache);
      print('🔍 EventProvider - Loaded ${_events.length} events');
      for (var event in _events) {
        print('🔍 EventProvider - Event: ${event.title} (ID: ${event.id}, StoryCount: ${event.storyCount})');
      }
      
      // ✅ Medya verilerini yükle (isteğe bağlı - profil için false)
      if (loadMedia) {
        print('🔍 EventProvider - Starting to load media for ${_events.length} events');
        await loadAllEventMedia();
      }
      
      // ✅ Hikaye verilerini yükle (isteğe bağlı)
      if (loadStories) {
        print('🔍 EventProvider - Starting to load stories for ${_events.length} events');
        await loadAllEventStories();
        print('🔍 EventProvider - Finished loading media and stories for all events');
      }
    } catch (e) {
      _errorMessage = e.toString();
      if (kDebugMode) {
        debugPrint('Load events error: $e');
      }
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  Future<void> joinEvent(String eventId) async {
    try {
      _isLoading = true;
      notifyListeners();
      
      final result = await _apiService.joinEvent(eventId);
      
      // ✅ Refresh events list after joining (cache bypass ile - yeni event hemen görünsün)
      await loadEvents(bypassCache: true);
      
      // Find and store the joined event by exact ID match
      try {
        _lastJoinedEvent = _events.firstWhere(
          (event) => event.id.toString() == eventId,
        );
      } catch (e) {
        // If exact match not found, try to find by QR code
        _lastJoinedEvent = _events.firstWhere(
          (event) => event.qrCode == eventId,
          orElse: () => _events.first,
        );
      }
      
      _successMessage = result['message'] ?? 'Etkinliğe başarıyla katıldınız!';
      notifyListeners();
      
      // Clear success message after 3 seconds
      Future.delayed(const Duration(seconds: 3), () {
        _successMessage = null;
        notifyListeners();
      });
      
    } catch (e) {
      _errorMessage = e.toString();
      notifyListeners();
      
      // Clear error message after 5 seconds
      Future.delayed(const Duration(seconds: 5), () {
        _errorMessage = null;
        notifyListeners();
      });
      
      // Don't refresh events list on error to preserve session
      if (kDebugMode) {
        debugPrint('Join event error: $e');
      }
    } finally {
      _isLoading = false;
      notifyListeners();
    }
  }

  void clearMessages() {
    _errorMessage = null;
    _successMessage = null;
    notifyListeners();
  }

  void clearError() {
    _errorMessage = null;
    notifyListeners();
  }

  // ✅ Load media for all events (optimize - sadece sayı için count query kullanabiliriz ama şimdilik medya listesi lazım)
  Future<void> loadAllEventMedia({int limit = 500}) async {
    try {
      print('🔍 EventProvider - Starting to load media for ${_events.length} events (limit: $limit)');
      // ✅ Paralel yükleme için Future.wait kullan (daha hızlı)
      await Future.wait(
        _events.map((event) => loadEventMedia(event.id, limit: limit)),
      );
      print('🔍 EventProvider - Finished loading media for all events');
    } catch (e) {
      print('🔍 EventProvider - Load all event media error: $e');
      if (kDebugMode) {
        debugPrint('Load all event media error: $e');
      }
    }
  }

  // ✅ Load media for specific event (optimize - limit artırıldı)
  Future<void> loadEventMedia(int eventId, {int limit = 500}) async {
    try {
      print('🔍 EventProvider - Loading media for event $eventId (limit: $limit)');
      final mediaData = await _apiService.getMedia(eventId, page: 1, limit: limit);
      print('🔍 EventProvider - Media data received: $mediaData');
      _eventMedia[eventId] = List<Map<String, dynamic>>.from(mediaData['media']);
      print('🔍 EventProvider - Media loaded for event $eventId: ${_eventMedia[eventId]?.length} items');
      notifyListeners();
    } catch (e) {
      print('🔍 EventProvider - Load event media error for event $eventId: $e');
      if (kDebugMode) {
        debugPrint('Load event media error for event $eventId: $e');
      }
    }
  }
  
  // ✅ Optimize edilmiş media yükleme (profil için hızlı)
  Future<void> loadEventMediaOptimized(int eventId) async {
    await loadEventMedia(eventId, limit: 100); // ✅ Profil için 100 yeterli
  }

  // ✅ Load stories for all events (paralel yükleme)
  Future<void> loadAllEventStories() async {
    try {
      print('🔍 EventProvider - Starting to load stories for ${_events.length} events');
      // ✅ Paralel yükleme için Future.wait kullan (daha hızlı)
      await Future.wait(
        _events.map((event) => loadEventStories(event.id)),
      );
      print('🔍 EventProvider - Finished loading stories for all events');
    } catch (e) {
      print('🔍 EventProvider - Load all event stories error: $e');
      if (kDebugMode) {
        debugPrint('Load all event stories error: $e');
      }
    }
  }

  // Load stories for specific event
  Future<void> loadEventStories(int eventId) async {
    try {
      print('🔍 EventProvider - Loading stories for event $eventId');
      final storiesList = await _apiService.getStories(eventId);
      print('🔍 EventProvider - Stories data received: $storiesList');
      _eventStories[eventId] = storiesList;
      print('🔍 EventProvider - Stories loaded for event $eventId: ${_eventStories[eventId]?.length} items');
      notifyListeners();
    } catch (e) {
      print('🔍 EventProvider - Load event stories error for event $eventId: $e');
      if (kDebugMode) {
        debugPrint('Load event stories error for event $eventId: $e');
      }
    }
  }

  // Get user's media from all events
  List<Map<String, dynamic>> getUserMedia(int userId) {
    List<Map<String, dynamic>> userMedia = [];
    print('🔍 EventProvider - getUserMedia called for user ID: $userId');
    print('🔍 EventProvider - Available event media: ${_eventMedia.keys}');
    
    for (var eventId in _eventMedia.keys) {
      var mediaList = _eventMedia[eventId]!;
      print('🔍 EventProvider - Event $eventId has ${mediaList.length} media items');
      
      for (var media in mediaList) {
        print('🔍 EventProvider - Media user_id: ${media['user_id']}, target user_id: $userId');
        if (media['user_id'] == userId) {
          userMedia.add(media);
          print('🔍 EventProvider - Added media for user $userId');
        }
      }
    }
    
    print('🔍 EventProvider - Total user media found: ${userMedia.length}');
    return userMedia;
  }
}