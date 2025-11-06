import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/screens/event_detail_screen.dart';
import 'package:digimobil_new/screens/event_profile_screen.dart';
import 'package:digimobil_new/screens/user_profile_screen.dart';
import 'package:digimobil_new/screens/qr_code_scanner_screen.dart';
import 'package:digimobil_new/screens/admin_logs_screen.dart';
import 'package:digimobil_new/screens/user_search_screen.dart';
import 'package:digimobil_new/screens/notifications_screen.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/theme_colors.dart';
import 'package:digimobil_new/widgets/shimmer_loading.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:provider/provider.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:url_launcher/url_launcher.dart';
import 'dart:async';

class InstagramHomeScreen extends StatefulWidget {
  const InstagramHomeScreen({super.key});

  @override
  State<InstagramHomeScreen> createState() => _InstagramHomeScreenState();
}

class _InstagramHomeScreenState extends State<InstagramHomeScreen> {
  List<Event> _events = [];
  List<Event> _todayEvents = [];
  List<Event> _upcomingEvents = [];
  List<Event> _pastEvents = [];
  bool _isLoading = true;
  bool _showAllPastEvents = false;
  final ApiService _apiService = ApiService();
  final TextEditingController _searchController = TextEditingController();
  Timer? _refreshTimer; // ✅ Real-time event güncellemesi için timer
  int _lastEventCount = 0; // ✅ Son event sayısını takip et
  int _unreadNotificationCount = 0; // ✅ Okunmamış bildirim sayısı
  bool _hasInitialized = false; // ✅ İlk yükleme kontrolü
  DateTime? _lastRefreshTime; // ✅ Son yenileme zamanı

  @override
  void initState() {
    super.initState();
    _loadEvents();
    _loadUnreadNotificationCount(); // ✅ Bildirim sayısını yükle
    _hasInitialized = true;
    
    // ✅ Real-time event kontrolü: Her 5 saniyede bir arka planda kontrol et (sessiz - sayfa refresh yok)
    // Sadece değişiklik varsa UI'ı güncelle
    _refreshTimer = Timer.periodic(const Duration(seconds: 5), (timer) {
      if (mounted) {
        _checkForNewEventsSilently();
        _loadUnreadNotificationCount(); // ✅ Bildirim sayısını da güncelle
      }
    });
  }

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // ✅ Sayfa tekrar görünür olduğunda sessiz kontrol yap (sayfa refresh yok)
    // Sadece ilk yükleme değilse ve son yenilemeden 2 saniye geçmişse kontrol et
    if (_hasInitialized && mounted && !_isLoading) {
      final now = DateTime.now();
      if (_lastRefreshTime == null || 
          now.difference(_lastRefreshTime!).inSeconds > 2) {
        WidgetsBinding.instance.addPostFrameCallback((_) {
          if (mounted && !_isLoading) {
            // ✅ QR scanner'dan veya başka sayfadan dönünce sessiz kontrol yap
            _checkForNewEventsSilently();
          }
        });
      }
    }
  }

  @override
  void dispose() {
    _searchController.dispose();
    _refreshTimer?.cancel(); // ✅ Timer'ı temizle
    super.dispose();
  }
  
  // ✅ Arka planda sessiz kontrol - sayfa refresh yok, sadece değişiklik varsa UI güncelle
  Future<void> _checkForNewEventsSilently() async {
    if (!mounted || _isLoading) return;
    
    try {
      // ✅ Önce cache'den kontrol et (hızlı)
      final cachedEvents = await _apiService.getEvents(bypassCache: false);
      
      // ✅ Event sayısı veya içerik değişmiş mi kontrol et
      bool needsUpdate = false;
      
      if (cachedEvents.length != _lastEventCount) {
        needsUpdate = true;
        if (kDebugMode) {
          debugPrint('🔄 Event count changed: ${_lastEventCount} -> ${cachedEvents.length}');
        }
      } else {
        // ✅ Event sayısı aynıysa ama içerik değişmiş olabilir
        final currentEventIds = _events.map((e) => e.id).toSet();
        final newEventIds = cachedEvents.map((e) => e.id).toSet();
        
        if (currentEventIds != newEventIds) {
          needsUpdate = true;
          if (kDebugMode) {
            debugPrint('🔄 Event IDs changed (new event added or removed)');
          }
        } else {
          // ✅ Event ID'leri aynı ama içerik değişmiş olabilir (yetki değişiklikleri)
          for (var newEvent in cachedEvents) {
            final oldEvent = _events.firstWhere(
              (e) => e.id == newEvent.id,
              orElse: () => newEvent,
            );
            
            // ✅ Yetkiler değişmişse güncelle (bildirim ikonu için)
            final oldPermissions = oldEvent.userPermissions?.toString() ?? '';
            final newPermissions = newEvent.userPermissions?.toString() ?? '';
            
            if (oldPermissions != newPermissions) {
              needsUpdate = true;
              if (kDebugMode) {
                debugPrint('🔄 Event permissions changed for event: ${newEvent.title}');
              }
              break;
            }
            
            // ✅ Medya veya hikaye sayısı değişmişse de güncelle
            if (oldEvent.mediaCount != newEvent.mediaCount ||
                oldEvent.storyCount != newEvent.storyCount ||
                oldEvent.participantCount != newEvent.participantCount) {
              needsUpdate = true;
              if (kDebugMode) {
                debugPrint('🔄 Event counts changed for event: ${newEvent.title}');
              }
              break;
            }
          }
        }
      }
      
      // ✅ Değişiklik varsa cache bypass ile güncel veriyi al ve UI'ı güncelle
      if (needsUpdate) {
        if (kDebugMode) {
          debugPrint('🔄 Changes detected, updating UI silently...');
        }
        // ✅ Cache bypass ile güncel veriyi al (sessiz - sayfa refresh yok)
        await _updateEventsSilently();
        _lastEventCount = cachedEvents.length;
      }
      
      _lastEventCount = cachedEvents.length;
    } catch (e) {
      // Hata durumunda sessizce devam et
      if (kDebugMode) {
        debugPrint('Silent event check error: $e');
      }
    }
  }
  
  // ✅ Sessiz güncelleme - sayfa refresh yok, sadece state güncelle
  Future<void> _updateEventsSilently() async {
    if (!mounted || _isLoading) return;
    
    try {
      // ✅ Cache bypass ile güncel veriyi al
      final events = await _apiService.getEvents(bypassCache: true);
      
      // ✅ Eventleri bugünkü, yaklaşan ve geçmiş olarak ayır
      final now = DateTime.now();
      final today = DateTime(now.year, now.month, now.day);
      final todayEvents = <Event>[];
      final upcomingEvents = <Event>[];
      final pastEvents = <Event>[];
      
      for (final event in events) {
        if (event.date != null) {
          final eventDate = DateTime.tryParse(event.date!);
          if (eventDate != null) {
            final eventDateOnly = DateTime(eventDate.year, eventDate.month, eventDate.day);
            
            if (eventDateOnly.year == today.year && 
                eventDateOnly.month == today.month && 
                eventDateOnly.day == today.day) {
              todayEvents.add(event);
            } else if (eventDateOnly.isAfter(today)) {
              upcomingEvents.add(event);
            } else {
              pastEvents.add(event);
            }
          } else {
            upcomingEvents.add(event);
          }
        } else {
          upcomingEvents.add(event);
        }
      }
      
      // Geçmiş etkinlikleri tarihe göre sırala
      pastEvents.sort((a, b) {
        if (a.date == null && b.date == null) return 0;
        if (a.date == null) return 1;
        if (b.date == null) return -1;
        return DateTime.parse(b.date!).compareTo(DateTime.parse(a.date!));
      });
      
      // ✅ Sadece state'i güncelle (setState - sayfa refresh yok)
      if (mounted) {
        setState(() {
          _events = events;
          _todayEvents = todayEvents;
          _upcomingEvents = upcomingEvents;
          _pastEvents = pastEvents;
          _lastRefreshTime = DateTime.now();
        });
        
        if (kDebugMode) {
          debugPrint('✅ UI updated silently - Events: ${events.length}');
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('Silent update error: $e');
      }
    }
  }

  // ✅ Okunmamış bildirim sayısını yükle
  Future<void> _loadUnreadNotificationCount() async {
    try {
      final data = await _apiService.getNotifications();
      if (mounted && data['unread_count'] != null) {
        setState(() {
          _unreadNotificationCount = data['unread_count'] as int;
        });
      }
    } catch (e) {
      // Hata durumunda sessizce devam et
      if (kDebugMode) {
        debugPrint('Load unread notifications error: $e');
      }
    }
  }

  // QR Kod tarama fonksiyonu
  Future<void> _scanQRCode() async {
    print('🔍 QR scanner açılıyor...');
    final result = await Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => const QRCodeScannerScreen(),
      ),
    );
    
    // ✅ QR scanner'dan döndüğünde başarılı katılım varsa sessiz güncelleme yap
    if (result == true && mounted) {
      print('🔄 QR scanner\'dan başarılı katılım döndü, sessiz güncelleme yapılıyor...');
      
      // ✅ Kısa bir gecikme sonrası sessiz güncelleme (cache temizlenmesi için zaman tanı)
      await Future.delayed(const Duration(milliseconds: 500));
      
      // ✅ Sessiz güncelleme (sayfa refresh yok)
      await _updateEventsSilently();
      
      print('✅ Event listesi sessizce güncellendi, toplam event sayısı: ${_events.length}');
      
      // ✅ Kullanıcıya bilgi ver (event kartı görünecek)
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Etkinlik başarıyla eklendi! Event kartı gösteriliyor...'),
            backgroundColor: AppColors.success,
            duration: Duration(seconds: 2),
          ),
        );
      }
    }
  }

  // Hamburger menu fonksiyonu
  void _showMenu(BuildContext context) {
    print('🔍 DEBUG - Hamburger menu açılıyor');
    final isDark = Theme.of(context).brightness == Brightness.dark;
    
    showModalBottomSheet(
      context: context,
      backgroundColor: ThemeColors.surface(context),
      shape: const RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      builder: (context) => Container(
        padding: const EdgeInsets.all(20),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Handle bar
            Container(
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: isDark ? Colors.grey[600] : Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const SizedBox(height: 20),
            
            // Menu items
            _buildMenuItem(
              icon: Icons.edit,
              title: 'Profil Düzenle',
              onTap: () {
                Navigator.pop(context);
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => const UserProfileScreen(),
                  ),
                );
              },
            ),
            _buildMenuItem(
              icon: Icons.settings,
              title: 'Ayarlar',
              onTap: () {
                Navigator.pop(context);
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(
                    content: Text('Ayarlar özelliği yakında eklenecek'),
                    backgroundColor: AppColors.info,
                  ),
                );
              },
            ),
            _buildMenuItem(
              icon: Icons.logout,
              title: 'Çıkış',
              onTap: () {
                Navigator.pop(context);
                _showLogoutDialog(context);
              },
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _buildMenuItem({
    required IconData icon,
    required String title,
    required VoidCallback onTap,
  }) {
    print('🔍 DEBUG - Menu item oluşturuluyor: $title');
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? Colors.white : Colors.black;
    
    return ListTile(
      leading: Icon(icon, color: ThemeColors.primary(context)),
      title: Text(
        title,
        style: TextStyle(
          fontSize: 16,
          fontWeight: FontWeight.w500,
          color: textColor,
        ),
      ),
      onTap: () {
        print('🔍 DEBUG - Menu item tıklandı: $title');
        onTap();
      },
    );
  }

  // Çıkış onay dialogu
  void _showLogoutDialog(BuildContext context) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Çıkış Yap'),
        content: const Text('Hesabınızdan çıkış yapmak istediğinizden emin misiniz?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('İptal'),
          ),
          TextButton(
            onPressed: () {
              Navigator.pop(context);
              Provider.of<AuthProvider>(context, listen: false).logout();
            },
            child: const Text(
              'Çıkış Yap',
              style: TextStyle(color: Colors.red),
            ),
          ),
        ],
      ),
    );
  }

  // ✅ Konumu haritalarda aç
  Future<void> _openLocationInMaps(String location) async {
    try {
      // URL encode yaparak adresi Google Maps'te aç
      final encodedLocation = Uri.encodeComponent(location);
      
      // Android ve iOS için farklı URL'ler
      String url;
      if (await canLaunchUrl(Uri.parse('comgooglemaps://'))) {
        // Google Maps uygulaması yüklüyse
        url = 'comgooglemaps://?q=$encodedLocation&directionsmode=driving';
      } else {
        // Tarayıcıda aç (hem Android hem iOS)
        url = 'https://www.google.com/maps/search/?api=1&query=$encodedLocation';
      }
      
      final uri = Uri.parse(url);
      if (await canLaunchUrl(uri)) {
        await launchUrl(uri, mode: LaunchMode.externalApplication);
      } else {
        // Fallback: Apple Maps veya genel URL
        final fallbackUrl = 'https://www.google.com/maps/search/?api=1&query=$encodedLocation';
        await launchUrl(Uri.parse(fallbackUrl), mode: LaunchMode.externalApplication);
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Harita açılamadı: $e'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  // ✅ Etkinlik tıklama kontrolü
  void _handleEventTap(Event event) async {
    final eventDate = DateTime.tryParse(event.date ?? '');
    if (eventDate == null) {
      // Tarih yoksa Event Detail Screen'e git
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => EventDetailScreen(event: event),
        ),
      );
      // ✅ Geri dönüldüğünde sessiz güncelleme yap (sayfa refresh yok)
      _updateEventsSilently();
      return;
    }

    final today = DateTime.now();
    final todayOnly = DateTime(today.year, today.month, today.day);
    final eventDateOnly = DateTime(eventDate.year, eventDate.month, eventDate.day);
    
    // ✅ Ücretsiz erişim günü kontrolü
    final freeAccessDays = event.freeAccessDays ?? 7;
    final accessEndDate = eventDateOnly.add(Duration(days: freeAccessDays));
    
    // ✅ Ücretsiz erişim süresi bitmişse Event Profile Screen'e git
    if (todayOnly.isAfter(accessEndDate)) {
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => EventProfileScreen(event: event),
        ),
      );
      // ✅ Geri dönüldüğünde sessiz güncelleme yap (sayfa refresh yok)
      _updateEventsSilently();
    } else {
      // ✅ Aktif dönemde Event Detail Screen'e git
      await Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => EventDetailScreen(event: event),
        ),
      );
      // ✅ Geri dönüldüğünde sessiz güncelleme yap (sayfa refresh yok)
      _updateEventsSilently();
    }
  }

  Future<void> _loadEvents({bool bypassCache = false}) async {
    try {
      setState(() {
        _isLoading = true;
      });

      final events = await _apiService.getEvents(bypassCache: bypassCache);
      
      // ✅ Event sayısını güncelle
      _lastEventCount = events.length;
      
      // ✅ Debug log'ları ekle
      print('📱 Ana ekran - Yüklenen eventler: ${events.length}');
      for (final event in events) {
        print('📱 Event: ${event.title}, CoverPhoto: ${event.coverPhoto}');
      }
      
      // ✅ Eventleri bugünkü, yaklaşan ve geçmiş olarak ayır
      // ✅ Sadece kullanıcının katıldığı etkinlikleri göster (userRole null değilse veya participantCount > 0)
      final now = DateTime.now();
      final today = DateTime(now.year, now.month, now.day); // Sadece tarih kısmı
      final todayEvents = <Event>[];
      final upcomingEvents = <Event>[];
      final pastEvents = <Event>[];
      
      print('📅 Bugünün tarihi: $today');
      
      for (final event in events) {
        // ✅ API zaten sadece kullanıcının katıldığı etkinlikleri döndürüyor
        // Filtreleme mantığını kaldırdık - API'den gelen tüm eventleri göster
        print('📅 Event: ${event.title}, Date: ${event.date}, UserRole: ${event.userRole}, ParticipantCount: ${event.participantCount}');
        if (event.date != null) {
          final eventDate = DateTime.tryParse(event.date!);
          if (eventDate != null) {
            // Sadece tarih kısmını al (saat bilgisini çıkar)
            final eventDateOnly = DateTime(eventDate.year, eventDate.month, eventDate.day);
            print('📅 Event tarihi parse edildi: $eventDateOnly');
            
            // ✅ Tarih karşılaştırması (saat önemsiz - bugünkü etkinlikler bugünkü bölümünde olmalı)
            if (eventDateOnly.year == today.year && 
                eventDateOnly.month == today.month && 
                eventDateOnly.day == today.day) {
              print('📅 Bugünkü etkinlik: ${event.title}');
              todayEvents.add(event);
            } else if (eventDateOnly.isAfter(today)) {
              print('📅 Yaklaşan etkinlik: ${event.title}');
              upcomingEvents.add(event);
            } else {
              print('📅 Geçmiş etkinlik: ${event.title}');
              pastEvents.add(event);
            }
          } else {
            print('📅 Tarih parse edilemedi, yaklaşan olarak eklendi: ${event.title}');
            // Tarih parse edilemezse yaklaşan olarak ekle
            upcomingEvents.add(event);
          }
        } else {
          print('📅 Tarih yok, yaklaşan olarak eklendi: ${event.title}');
          // Tarih yoksa yaklaşan olarak ekle
          upcomingEvents.add(event);
        }
      }
      
      print('📅 Bugünkü etkinlikler: ${todayEvents.length}');
      print('📅 Yaklaşan etkinlikler: ${upcomingEvents.length}');
      print('📅 Geçmiş etkinlikler: ${pastEvents.length}');
      
      // Geçmiş etkinlikleri tarihe göre sırala (en yeni önce)
      pastEvents.sort((a, b) {
        if (a.date == null && b.date == null) return 0;
        if (a.date == null) return 1;
        if (b.date == null) return -1;
        return DateTime.parse(b.date!).compareTo(DateTime.parse(a.date!));
      });
      
      setState(() {
        _events = events;
        _todayEvents = todayEvents;
        _upcomingEvents = upcomingEvents;
        _pastEvents = pastEvents;
        _isLoading = false;
        _lastRefreshTime = DateTime.now(); // ✅ Son yenileme zamanını güncelle
      });
    } catch (e) {
      setState(() {
        _isLoading = false;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Etkinlikler yüklenirken hata: $e'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  // ✅ Arama fonksiyonu
  List<Event> _filterEvents(List<Event> events, String query) {
    if (query.isEmpty) return events;
    return events.where((event) {
      return event.title.toLowerCase().contains(query.toLowerCase()) ||
             (event.description?.toLowerCase().contains(query.toLowerCase()) ?? false);
    }).toList();
  }

  // ✅ Tarih formatı
  String _formatDate(String? dateString) {
    if (dateString == null) return 'Tarih belirtilmemiş';
    try {
      final date = DateTime.parse(dateString);
      return '${date.day}.${date.month}.${date.year}';
    } catch (e) {
      return 'Tarih belirtilmemiş';
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        centerTitle: false,
        elevation: 0,
        leading: IconButton(
          icon: Icon(
            Icons.menu,
            color: Theme.of(context).brightness == Brightness.dark ? Colors.white : Colors.black,
          ),
          onPressed: () => _showMenu(context),
        ),
        title: Text(
          'Digital Salon',
          style: TextStyle(
            color: Theme.of(context).brightness == Brightness.dark ? Colors.white : Colors.black,
            fontSize: 24,
            fontWeight: FontWeight.bold,
          ),
        ),
        actions: [
        // User Search Button
        IconButton(
          icon: Icon(
            Icons.person_search,
            color: Theme.of(context).brightness == Brightness.dark ? Colors.white : Colors.black,
          ),
          onPressed: () {
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (context) => const UserSearchScreen(),
              ),
            );
          },
        ),
        // Admin Logs Button (only for super_admin)
        Consumer<AuthProvider>(
          builder: (context, authProvider, child) {
            if (authProvider.user?.role == 'super_admin') {
              return IconButton(
                icon: const Icon(Icons.admin_panel_settings, color: Colors.red),
                onPressed: () {
                  Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => const AdminLogsScreen(),
                    ),
                  );
                },
              );
            }
            return const SizedBox.shrink();
          },
        ),
          IconButton(
            icon: Icon(
              Icons.qr_code_scanner,
              color: Theme.of(context).brightness == Brightness.dark ? Colors.white : Colors.black,
            ),
            onPressed: _scanQRCode,
          ),
          // ✅ Notification button with badge
          Stack(
            children: [
              IconButton(
                icon: Icon(
                  Icons.favorite_border,
                  color: Theme.of(context).brightness == Brightness.dark ? Colors.white : Colors.black,
                ),
                onPressed: () async {
                  await Navigator.push(
                    context,
                    MaterialPageRoute(
                      builder: (context) => const NotificationsScreen(),
                    ),
                  );
                  // ✅ Geri döndüğünde bildirim sayısını güncelle
                  _loadUnreadNotificationCount();
                },
              ),
              if (_unreadNotificationCount > 0)
                Positioned(
                  right: 8,
                  top: 8,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: Colors.red,
                      shape: BoxShape.circle,
                    ),
                    constraints: const BoxConstraints(
                      minWidth: 18,
                      minHeight: 18,
                    ),
                    child: Text(
                      _unreadNotificationCount > 99 ? '99+' : _unreadNotificationCount.toString(),
                      style: const TextStyle(
                        color: Colors.white,
                        fontSize: 10,
                        fontWeight: FontWeight.bold,
                      ),
                      textAlign: TextAlign.center,
                    ),
                  ),
                ),
            ],
          ),
        ],
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(60),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Etkinlik ara...',
                prefixIcon: Icon(
                  Icons.search,
                  color: Theme.of(context).brightness == Brightness.dark ? Colors.grey[400] : Colors.grey[600],
                ),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(
                    color: ThemeColors.border(context),
                  ),
                ),
                filled: true,
                fillColor: ThemeColors.surface(context),
                hintStyle: TextStyle(
                  color: Theme.of(context).brightness == Brightness.dark ? Colors.grey[400] : Colors.grey[600],
                ),
              ),
              onChanged: (value) {
                setState(() {});
              },
            ),
          ),
        ),
      ),
      body: _isLoading
          ? const EventListShimmer(count: 3)
          : RefreshIndicator(
              onRefresh: _loadEvents,
              color: AppColors.primary,
              child: _buildEventsList(),
            ),
    );
  }

  Widget _buildEventsList() {
    final searchQuery = _searchController.text;
    final filteredToday = _filterEvents(_todayEvents, searchQuery);
    final filteredUpcoming = _filterEvents(_upcomingEvents, searchQuery);
    final filteredPast = _filterEvents(_pastEvents, searchQuery);
    
    if (filteredToday.isEmpty && filteredUpcoming.isEmpty && filteredPast.isEmpty) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.event_busy,
              size: 80,
              color: Colors.grey,
            ),
            SizedBox(height: 20),
            Text(
              'Henüz etkinlik yok',
              style: TextStyle(
                fontSize: 18,
                color: Colors.grey,
              ),
            ),
            SizedBox(height: 10),
            Text(
              'İlk etkinliği oluşturmak için + butonuna tıklayın',
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey,
              ),
            ),
          ],
        ),
      );
    }

    return SingleChildScrollView(
      padding: const EdgeInsets.all(16),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // ✅ Bugünkü Etkinlikler
          if (filteredToday.isNotEmpty) ...[
            _buildSectionHeader('Bugünkü Etkinlikler', filteredToday.length),
            const SizedBox(height: 12),
            ...filteredToday.map((event) => _buildEventCard(event)),
            const SizedBox(height: 24),
          ],
          
          // ✅ Yaklaşan Etkinlikler
          if (filteredUpcoming.isNotEmpty) ...[
            _buildSectionHeader('Yaklaşan Etkinlikler', filteredUpcoming.length),
            const SizedBox(height: 12),
            ...filteredUpcoming.map((event) => _buildUpcomingEventCard(event)),
            const SizedBox(height: 24),
          ],
          
          // ✅ Geçmiş Etkinlikler (ESKİ TASARIM)
          if (filteredPast.isNotEmpty) ...[
            _buildSectionHeader(
              'Geçmiş Etkinlikler', 
              filteredPast.length,
              showAllButton: filteredPast.length > 5,
            ),
            const SizedBox(height: 12),
            ...(_showAllPastEvents ? filteredPast : filteredPast.take(5))
                .map((event) => _buildPastEventCard(event)),
            if (!_showAllPastEvents && filteredPast.length > 5) ...[
              const SizedBox(height: 12),
              Center(
                child: TextButton(
                  onPressed: () {
                    setState(() {
                      _showAllPastEvents = true;
                    });
                  },
                  child: const Text('Tümünü Görüntüle'),
                ),
              ),
            ],
          ],
        ],
      ),
    );
  }

  Widget _buildSectionHeader(String title, int count, {bool showAllButton = false}) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? Colors.white : Colors.black;
    final secondaryColor = isDark ? Colors.grey[400] : Colors.grey[600];
    
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          title,
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: textColor,
          ),
        ),
        Text(
          '$count etkinlik',
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.normal,
            color: secondaryColor,
          ),
        ),
      ],
    );
  }

  Widget _buildEventCard(Event event) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = isDark ? ThemeColors.darkSurface : Colors.white;
    final textColor = isDark ? Colors.white : Colors.black;
    final secondaryTextColor = isDark ? Colors.grey[300] : Colors.grey[600];
    final accentColor = isDark ? ThemeColors.darkPrimary : AppColors.primary;
    
    return Container(
      margin: const EdgeInsets.only(bottom: 16),
      decoration: BoxDecoration(
        color: cardColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: InkWell(
        onTap: () => _handleEventTap(event),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ✅ Sol tarafta square resim (rounded corners)
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: event.coverPhoto != null
                    ? CachedNetworkImage(
                        imageUrl: event.coverPhotoThumbnail ?? event.coverPhoto!,
                        width: 100,
                        height: 100,
                        fit: BoxFit.cover,
                        httpHeaders: {
                          'Connection': 'keep-alive',
                        },
                        placeholder: (context, url) => Container(
                          width: 100,
                          height: 100,
                          color: accentColor.withOpacity(0.1),
                          child: Icon(
                            Icons.event,
                            color: accentColor,
                            size: 40,
                          ),
                        ),
                        errorWidget: (context, url, error) => Container(
                          width: 100,
                          height: 100,
                          color: accentColor.withOpacity(0.1),
                          child: Icon(
                            Icons.event,
                            color: accentColor,
                            size: 40,
                          ),
                        ),
                      )
                    : Container(
                        width: 100,
                        height: 100,
                        decoration: BoxDecoration(
                          color: accentColor.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Icon(
                          Icons.event,
                          color: accentColor,
                          size: 40,
                        ),
                      ),
              ),
              const SizedBox(width: 12),
              
              // ✅ Sağ tarafta detaylar
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Başlık
                    Row(
                      children: [
                        Expanded(
                          child: Text(
                            event.title,
                            style: TextStyle(
                              color: textColor,
                              fontWeight: FontWeight.bold,
                              fontSize: 16,
                            ),
                            maxLines: 1,
                            overflow: TextOverflow.ellipsis,
                          ),
                        ),
                        // ✅ Bildirim Gönder ikonu (eğer yetki varsa)
                        if (event.userPermissions?['bildirim_gonderebilir'] == true) ...[
                          IconButton(
                            icon: Icon(
                              Icons.notifications_active,
                              color: accentColor,
                              size: 20,
                            ),
                            onPressed: () => _showSendNotificationModal(event),
                            tooltip: 'Bildirim Gönder',
                            padding: EdgeInsets.zero,
                            constraints: const BoxConstraints(),
                          ),
                        ],
                      ],
                    ),
                    
                    // Açıklama
                    if (event.description != null && event.description!.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        event.description!,
                        style: TextStyle(
                          color: secondaryTextColor,
                          fontSize: 13,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                    
                    const SizedBox(height: 8),
                    
                    // ✅ Katılımcı ve Medya sayısı (Row 1)
                    Row(
                      children: [
                        Icon(
                          Icons.people,
                          size: 16,
                          color: accentColor,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '${event.participantCount} katılımcı',
                          style: TextStyle(
                            color: textColor,
                            fontSize: 13,
                          ),
                        ),
                        const SizedBox(width: 16),
                        Icon(
                          Icons.photo_library,
                          size: 16,
                          color: accentColor,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '${event.mediaCount} medya',
                          style: TextStyle(
                            color: textColor,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                    
                    const SizedBox(height: 6),
                    
                    // ✅ Konum (Row 2)
                    if (event.location != null && event.location!.isNotEmpty) ...[
                      GestureDetector(
                        onTap: () => _openLocationInMaps(event.location!),
                        child: Row(
                          children: [
                            Icon(
                              Icons.location_on,
                              size: 16,
                              color: accentColor,
                            ),
                            const SizedBox(width: 4),
                            Expanded(
                              child: Text(
                                event.location!,
                                style: TextStyle(
                                  color: textColor,
                                  fontSize: 12,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 6),
                    ],
                    
                    // ✅ Tarih ve Saat (Row 3)
                    Row(
                      children: [
                        Icon(
                          Icons.calendar_today,
                          size: 16,
                          color: accentColor,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          _formatDate(event.date),
                          style: TextStyle(
                            color: textColor,
                            fontSize: 12,
                          ),
                        ),
                        if (event.time != null && event.time!.isNotEmpty) ...[
                          const SizedBox(width: 8),
                          Icon(
                            Icons.access_time,
                            size: 16,
                            color: accentColor,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            event.time!.substring(0, 5), // HH:MM formatı
                            style: TextStyle(
                              color: textColor,
                              fontSize: 12,
                            ),
                          ),
                        ],
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

  // ✅ Yaklaşan etkinlikler için orta boyutlu kart
  Widget _buildUpcomingEventCard(Event event) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = isDark ? ThemeColors.darkSurface : Colors.white;
    final textColor = isDark ? Colors.white : Colors.black;
    final secondaryTextColor = isDark ? Colors.grey[300] : Colors.grey[600];
    final accentColor = isDark ? ThemeColors.darkPrimary : AppColors.primary;
    
    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      decoration: BoxDecoration(
        color: cardColor,
        borderRadius: BorderRadius.circular(12),
      ),
      child: InkWell(
        onTap: () => _handleEventTap(event),
        borderRadius: BorderRadius.circular(12),
        child: Padding(
          padding: const EdgeInsets.all(12),
          child: Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // ✅ Sol tarafta square resim (rounded corners)
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: event.coverPhoto != null
                    ? CachedNetworkImage(
                        imageUrl: event.coverPhotoThumbnail ?? event.coverPhoto!,
                        width: 100,
                        height: 100,
                        fit: BoxFit.cover,
                        httpHeaders: {
                          'Connection': 'keep-alive',
                        },
                        placeholder: (context, url) => Container(
                          width: 100,
                          height: 100,
                          color: accentColor.withOpacity(0.1),
                          child: Icon(
                            Icons.event,
                            color: accentColor,
                            size: 40,
                          ),
                        ),
                        errorWidget: (context, url, error) => Container(
                          width: 100,
                          height: 100,
                          color: accentColor.withOpacity(0.1),
                          child: Icon(
                            Icons.event,
                            color: accentColor,
                            size: 40,
                          ),
                        ),
                      )
                    : Container(
                        width: 100,
                        height: 100,
                        decoration: BoxDecoration(
                          color: accentColor.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(8),
                        ),
                        child: Icon(
                          Icons.event,
                          color: accentColor,
                          size: 40,
                        ),
                      ),
              ),
              const SizedBox(width: 12),
              
              // ✅ Sağ tarafta detaylar
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Başlık
                    Text(
                      event.title,
                      style: TextStyle(
                        color: textColor,
                        fontWeight: FontWeight.bold,
                        fontSize: 16,
                      ),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                    
                    // Açıklama
                    if (event.description != null && event.description!.isNotEmpty) ...[
                      const SizedBox(height: 4),
                      Text(
                        event.description!,
                        style: TextStyle(
                          color: secondaryTextColor,
                          fontSize: 13,
                        ),
                        maxLines: 2,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ],
                    
                    const SizedBox(height: 8),
                    
                    // ✅ Katılımcı ve Medya sayısı (Row 1)
                    Row(
                      children: [
                        Icon(
                          Icons.people,
                          size: 16,
                          color: accentColor,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '${event.participantCount} katılımcı',
                          style: TextStyle(
                            color: textColor,
                            fontSize: 13,
                          ),
                        ),
                        const SizedBox(width: 16),
                        Icon(
                          Icons.photo_library,
                          size: 16,
                          color: accentColor,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          '${event.mediaCount} medya',
                          style: TextStyle(
                            color: textColor,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                    
                    const SizedBox(height: 6),
                    
                    // ✅ Konum (Row 2)
                    if (event.location != null && event.location!.isNotEmpty) ...[
                      GestureDetector(
                        onTap: () => _openLocationInMaps(event.location!),
                        child: Row(
                          children: [
                            Icon(
                              Icons.location_on,
                              size: 16,
                              color: accentColor,
                            ),
                            const SizedBox(width: 4),
                            Expanded(
                              child: Text(
                                event.location!,
                                style: TextStyle(
                                  color: textColor,
                                  fontSize: 12,
                                ),
                                maxLines: 1,
                                overflow: TextOverflow.ellipsis,
                              ),
                            ),
                          ],
                        ),
                      ),
                      const SizedBox(height: 6),
                    ],
                    
                    // ✅ Tarih ve Saat (Row 3)
                    Row(
                      children: [
                        Icon(
                          Icons.calendar_today,
                          size: 16,
                          color: accentColor,
                        ),
                        const SizedBox(width: 4),
                        Text(
                          _formatDate(event.date),
                          style: TextStyle(
                            color: textColor,
                            fontSize: 12,
                          ),
                        ),
                        if (event.time != null && event.time!.isNotEmpty) ...[
                          const SizedBox(width: 8),
                          Icon(
                            Icons.access_time,
                            size: 16,
                            color: accentColor,
                          ),
                          const SizedBox(width: 4),
                          Text(
                            event.time!.substring(0, 5), // HH:MM formatı
                            style: TextStyle(
                              color: textColor,
                              fontSize: 12,
                            ),
                          ),
                        ],
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

  // ✅ Geçmiş etkinlikler için küçük kart
  Widget _buildPastEventCard(Event event) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = isDark ? ThemeColors.darkSurface : Colors.white;
    final textColor = isDark ? Colors.white : Colors.black;
    final secondaryTextColor = isDark ? Colors.grey[400] : Colors.grey[600];
    
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      color: cardColor,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(8),
      ),
      elevation: 1,
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 12, vertical: 8),
        leading: CircleAvatar(
          radius: 18,
          backgroundColor: AppColors.primary.withOpacity(0.1),
          child: event.coverPhoto != null 
              ? ClipOval(
                  child: CachedNetworkImage(
                    imageUrl: event.coverPhotoThumbnail ?? event.coverPhoto!,
                    width: 36,
                    height: 36,
                    fit: BoxFit.cover,
                    httpHeaders: {
                      'Connection': 'keep-alive',
                    },
                    placeholder: (context, url) => const Icon(
                      Icons.event,
                      color: AppColors.primary,
                      size: 18,
                    ),
                    errorWidget: (context, url, error) {
                      print('❌ Image load error for ${event.title}: $error');
                      print('🔍 Event coverPhoto: ${event.coverPhoto}');
                      print('🔍 Event coverPhotoThumbnail: ${event.coverPhotoThumbnail}');
                      return const Icon(
                        Icons.event,
                        color: AppColors.primary,
                        size: 18,
                      );
                    },
                  ),
                )
              : const Icon(
                  Icons.event,
                  color: AppColors.primary,
                  size: 18,
                ),
        ),
        title: Text(
          event.title,
          style: TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: textColor,
          ),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Row(
              children: [
                Icon(
                  Icons.calendar_today,
                  size: 12,
                  color: secondaryTextColor,
                ),
                const SizedBox(width: 4),
                Text(
                  _formatDate(event.date),
                  style: TextStyle(
                    fontSize: 11,
                    color: secondaryTextColor,
                  ),
                ),
                // ✅ Saat bilgisi
                if (event.time != null && event.time!.isNotEmpty) ...[
                  const SizedBox(width: 8),
                  Icon(
                    Icons.access_time,
                    size: 12,
                    color: secondaryTextColor,
                  ),
                  const SizedBox(width: 4),
                  Text(
                    event.time!.substring(0, 5), // HH:MM formatı
                    style: TextStyle(
                      fontSize: 11,
                      color: secondaryTextColor,
                    ),
                  ),
                ],
              ],
            ),
            // ✅ Konum bilgisi (varsa)
            if (event.location != null && event.location!.isNotEmpty) ...[
              const SizedBox(height: 4),
              GestureDetector(
                onTap: () => _openLocationInMaps(event.location!),
                child: Row(
                  children: [
                    Icon(
                      Icons.location_on,
                      size: 12,
                      color: AppColors.primary,
                    ),
                    const SizedBox(width: 4),
                    Flexible(
                      child: Text(
                        event.location!,
                        style: TextStyle(
                          color: AppColors.primary,
                          fontSize: 11,
                          fontWeight: FontWeight.w500,
                        ),
                        maxLines: 1,
                        overflow: TextOverflow.ellipsis,
                      ),
                    ),
                  ],
                ),
              ),
            ],
            const SizedBox(height: 4),
            Row(
              children: [
                Icon(
                  Icons.people,
                  size: 12,
                  color: Colors.grey[600],
                ),
                const SizedBox(width: 4),
                Text(
                  '${event.participantCount}',
                  style: TextStyle(
                    fontSize: 11,
                    color: Colors.grey[600],
                  ),
                ),
              ],
            ),
          ],
        ),
        onTap: () => _handleEventTap(event),
      ),
    );
  }

  // ✅ Manuel Bildirim Gönderme Modalı
  void _showSendNotificationModal(Event event) {
    final TextEditingController messageController = TextEditingController();
    
    showDialog(
      context: context,
      builder: (dialogContext) => AlertDialog(
        shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(20)),
        title: Row(
          children: [
            const Icon(Icons.notifications_active, color: AppColors.primary),
            const SizedBox(width: 8),
            Flexible(
              child: Text(
                'Bildirim Gönder',
                style: const TextStyle(fontSize: 20, fontWeight: FontWeight.bold),
              ),
            ),
          ],
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            Text(
              'Tüm katılımcılara bildirim gönderin (${event.participantCount} kişi)',
              style: TextStyle(color: Colors.grey[600], fontSize: 14),
            ),
            const SizedBox(height: 16),
            TextField(
              controller: messageController,
              maxLines: 4,
              maxLength: 200,
              decoration: InputDecoration(
                hintText: 'Bildirim mesajınız...',
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: const BorderSide(color: AppColors.primary, width: 2),
                ),
              ),
            ),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(dialogContext).pop(),
            child: const Text('İptal', style: TextStyle(color: Colors.grey)),
          ),
          ElevatedButton(
            style: ElevatedButton.styleFrom(
              backgroundColor: AppColors.primary,
              shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(12)),
            ),
            onPressed: () async {
              final message = messageController.text.trim();
              if (message.isEmpty) {
                ScaffoldMessenger.of(context).showSnackBar(
                  const SnackBar(content: Text('Lütfen bir mesaj girin')),
                );
                return;
              }
              
              Navigator.of(dialogContext).pop();
              
              try {
                // API çağrısı - Etkinlik adıyla bildirim gönder
                final response = await _apiService.sendCustomNotification(
                  eventId: event.id,
                  title: '${event.title} Etkinliği',
                  message: message,
                );
                
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Bildirim başarıyla gönderildi!'),
                      backgroundColor: Colors.green,
                    ),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text('Hata: $e'),
                      backgroundColor: Colors.red,
                    ),
                  );
                }
              }
            },
            child: const Text('Gönder', style: TextStyle(color: Colors.white)),
          ),
        ],
      ),
    );
  }
}
