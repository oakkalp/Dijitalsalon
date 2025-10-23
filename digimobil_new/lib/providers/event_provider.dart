import 'package:flutter/foundation.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/services/api_service.dart';

class EventProvider with ChangeNotifier {
  List<Event> _events = [];
  bool _isLoading = false;
  String? _errorMessage;
  String? _successMessage;
  Event? _lastJoinedEvent;

  List<Event> get events => _events;
  bool get isLoading => _isLoading;
  String? get errorMessage => _errorMessage;
  String? get successMessage => _successMessage;
  Event? get lastJoinedEvent => _lastJoinedEvent;

  final ApiService _apiService = ApiService();

  Future<void> loadEvents() async {
    _isLoading = true;
    _errorMessage = null;
    notifyListeners();

    try {
      // Check session first
      final sessionValid = await _apiService.checkSession();
      if (!sessionValid) {
        throw Exception('Session expired. Please login again.');
      }
      
      _events = await _apiService.getEvents();
      print('🔍 EventProvider - Loaded ${_events.length} events');
      for (var event in _events) {
        print('🔍 EventProvider - Event: ${event.title} (ID: ${event.id})');
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
      
      // Refresh events list after joining
      await loadEvents();
      
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
}