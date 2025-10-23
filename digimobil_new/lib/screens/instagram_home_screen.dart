import 'package:flutter/material.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/screens/event_detail_screen.dart';
import 'package:digimobil_new/screens/event_profile_screen.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:cached_network_image/cached_network_image.dart';

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

  @override
  void initState() {
    super.initState();
    _loadEvents();
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }

  // ✅ Etkinlik tıklama kontrolü
  void _handleEventTap(Event event) {
    final eventDate = DateTime.tryParse(event.date ?? '');
    if (eventDate == null) {
      // Tarih yoksa Event Detail Screen'e git
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => EventDetailScreen(event: event),
        ),
      );
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
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => EventProfileScreen(event: event),
        ),
      );
    } else {
      // ✅ Aktif dönemde Event Detail Screen'e git
      Navigator.push(
        context,
        MaterialPageRoute(
          builder: (context) => EventDetailScreen(event: event),
        ),
      );
    }
  }

  Future<void> _loadEvents() async {
    try {
      setState(() {
        _isLoading = true;
      });

      final events = await _apiService.getEvents();
      
      // ✅ Debug log'ları ekle
      print('📱 Ana ekran - Yüklenen eventler: ${events.length}');
      for (final event in events) {
        print('📱 Event: ${event.title}, CoverPhoto: ${event.coverPhoto}');
      }
      
      // ✅ Eventleri bugünkü, yaklaşan ve geçmiş olarak ayır
      final now = DateTime.now();
      final today = DateTime(now.year, now.month, now.day); // Sadece tarih kısmı
      final todayEvents = <Event>[];
      final upcomingEvents = <Event>[];
      final pastEvents = <Event>[];
      
      print('📅 Bugünün tarihi: $today');
      
      for (final event in events) {
        print('📅 Event: ${event.title}, Date: ${event.date}');
        if (event.date != null) {
          final eventDate = DateTime.tryParse(event.date!);
          if (eventDate != null) {
            // Sadece tarih kısmını al (saat bilgisini çıkar)
            final eventDateOnly = DateTime(eventDate.year, eventDate.month, eventDate.day);
            print('📅 Event tarihi parse edildi: $eventDateOnly');
            
            if (eventDateOnly.isAtSameMomentAs(today)) {
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
      appBar: AppBar(
        backgroundColor: Colors.white,
        centerTitle: false,
        elevation: 0,
        title: const Text(
          'Digital Salon',
          style: TextStyle(
            color: Colors.black,
            fontSize: 24,
            fontWeight: FontWeight.bold,
          ),
        ),
        actions: [
          IconButton(
            icon: const Icon(Icons.add_box_outlined, color: Colors.black),
            onPressed: () {
              ScaffoldMessenger.of(context).showSnackBar(
                const SnackBar(
                  content: Text('Yeni etkinlik ekleme özelliği yakında eklenecek'),
                  backgroundColor: AppColors.info,
                ),
              );
            },
          ),
          IconButton(
            icon: const Icon(Icons.favorite_border, color: Colors.black),
            onPressed: () {},
          ),
          IconButton(
            icon: const Icon(Icons.send_outlined, color: Colors.black),
            onPressed: () {},
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
                prefixIcon: const Icon(Icons.search),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                ),
                filled: true,
                fillColor: Colors.grey[100],
              ),
              onChanged: (value) {
                setState(() {});
              },
            ),
          ),
        ),
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            )
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
          
          // ✅ Geçmiş Etkinlikler
          if (filteredPast.isNotEmpty) ...[
            _buildSectionHeader(
              'Geçmiş Etkinlikler', 
              filteredPast.length,
              showAllButton: filteredPast.length > 5,
            ),
            const SizedBox(height: 12),
            ...(_showAllPastEvents ? filteredPast : filteredPast.take(5)).map((event) => _buildPastEventCard(event)),
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
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: Colors.black,
          ),
        ),
        Text(
          '$count etkinlik',
          style: TextStyle(
            fontSize: 14,
            color: Colors.grey[600],
          ),
        ),
      ],
    );
  }

  Widget _buildEventCard(Event event) {
    return Card(
      margin: const EdgeInsets.only(bottom: 16),
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(12),
      ),
      elevation: 2,
      child: ListTile(
        leading: Builder(
          builder: (context) {
            // ✅ Debug log
            print('📱 CircleAvatar - Event: ${event.title}, CoverPhoto: ${event.coverPhoto}');
            
            return CircleAvatar(
              radius: 25,
              backgroundColor: AppColors.primary.withOpacity(0.1),
              child: event.coverPhoto != null 
                  ? ClipOval(
                      child: CachedNetworkImage(
                        imageUrl: 'http://192.168.1.137/dijitalsalon/${event.coverPhoto!}',
                        width: 50,
                        height: 50,
                        fit: BoxFit.cover,
                        placeholder: (context, url) => const Icon(
                          Icons.event,
                          color: AppColors.primary,
                          size: 25,
                        ),
                        errorWidget: (context, url, error) {
                          print('❌ Image load error for ${event.title}: $error');
                          return const Icon(
                            Icons.event,
                            color: AppColors.primary,
                            size: 25,
                          );
                        },
                      ),
                    )
                  : const Icon(
                      Icons.event,
                      color: AppColors.primary,
                      size: 25,
                    ),
            );
          },
        ),
        title: Text(
          event.title,
          style: const TextStyle(
            color: Colors.black,
            fontWeight: FontWeight.bold,
            fontSize: 16,
          ),
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (event.description != null && event.description!.isNotEmpty)
              Padding(
                padding: const EdgeInsets.only(top: 4),
                child: Text(
                  event.description!,
                  style: const TextStyle(color: Colors.grey),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            const SizedBox(height: 8),
            Row(
              children: [
                Icon(
                  Icons.people,
                  size: 16,
                  color: Colors.grey.shade600,
                ),
                const SizedBox(width: 4),
                Text(
                  '${event.participantCount} katılımcı',
                  style: TextStyle(
                    color: Colors.grey.shade600,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(width: 16),
                Icon(
                  Icons.photo_library,
                  size: 16,
                  color: Colors.grey.shade600,
                ),
                const SizedBox(width: 4),
                Text(
                  '${event.mediaCount} medya',
                  style: TextStyle(
                    color: Colors.grey.shade600,
                    fontSize: 12,
                  ),
                ),
              ],
            ),
            const SizedBox(height: 4),
            Row(
              children: [
                Icon(
                  Icons.calendar_today,
                  size: 16,
                  color: Colors.grey.shade600,
                ),
                const SizedBox(width: 4),
                Text(
                  _formatDate(event.date),
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey.shade600,
                  ),
                ),
              ],
            ),
          ],
        ),
        trailing: const Icon(
          Icons.arrow_forward_ios,
          color: Colors.grey,
          size: 16,
        ),
        onTap: () => _handleEventTap(event),
      ),
    );
  }

  // ✅ Yaklaşan etkinlikler için orta boyutlu kart
  Widget _buildUpcomingEventCard(Event event) {
    return Card(
      margin: const EdgeInsets.only(bottom: 12),
      color: Colors.white,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(10),
      ),
      elevation: 1.5,
      child: ListTile(
        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
        leading: CircleAvatar(
          radius: 22,
          backgroundColor: AppColors.primary.withOpacity(0.1),
          child: event.coverPhoto != null 
              ? ClipOval(
                  child: CachedNetworkImage(
                    imageUrl: 'http://192.168.1.137/dijitalsalon/${event.coverPhoto!}',
                    width: 44,
                    height: 44,
                    fit: BoxFit.cover,
                    placeholder: (context, url) => const Icon(
                      Icons.event,
                      color: AppColors.primary,
                      size: 22,
                    ),
                    errorWidget: (context, url, error) => const Icon(
                      Icons.event,
                      color: AppColors.primary,
                      size: 22,
                    ),
                  ),
                )
              : const Icon(
                  Icons.event,
                  color: AppColors.primary,
                  size: 22,
                ),
        ),
        title: Text(
          event.title,
          style: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w600,
            color: Colors.black,
          ),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            if (event.description != null && event.description!.isNotEmpty) ...[
              const SizedBox(height: 4),
              Text(
                event.description!,
                style: TextStyle(
                  fontSize: 13,
                  color: Colors.grey[600],
                ),
                maxLines: 1,
                overflow: TextOverflow.ellipsis,
              ),
            ],
            const SizedBox(height: 6),
            Row(
              children: [
                Icon(
                  Icons.calendar_today,
                  size: 14,
                  color: Colors.grey[600],
                ),
                const SizedBox(width: 4),
                Text(
                  _formatDate(event.date),
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey[600],
                  ),
                ),
                const SizedBox(width: 16),
                Icon(
                  Icons.people,
                  size: 14,
                  color: Colors.grey[600],
                ),
                const SizedBox(width: 4),
                Text(
                  '${event.participantCount}',
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey[600],
                  ),
                ),
              ],
            ),
          ],
        ),
        trailing: Icon(
          Icons.arrow_forward_ios,
          size: 15,
          color: Colors.grey[400],
        ),
        onTap: () => _handleEventTap(event),
      ),
    );
  }

  // ✅ Geçmiş etkinlikler için küçük kart
  Widget _buildPastEventCard(Event event) {
    return Card(
      margin: const EdgeInsets.only(bottom: 8),
      color: Colors.white,
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
                    imageUrl: 'http://192.168.1.137/dijitalsalon/${event.coverPhoto!}',
                    width: 36,
                    height: 36,
                    fit: BoxFit.cover,
                    placeholder: (context, url) => const Icon(
                      Icons.event,
                      color: AppColors.primary,
                      size: 18,
                    ),
                    errorWidget: (context, url, error) => const Icon(
                      Icons.event,
                      color: AppColors.primary,
                      size: 18,
                    ),
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
          style: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w600,
            color: Colors.black,
          ),
          maxLines: 1,
          overflow: TextOverflow.ellipsis,
        ),
        subtitle: Row(
          children: [
            Icon(
              Icons.calendar_today,
              size: 12,
              color: Colors.grey[600],
            ),
            const SizedBox(width: 4),
            Text(
              _formatDate(event.date),
              style: TextStyle(
                fontSize: 11,
                color: Colors.grey[600],
              ),
            ),
            const SizedBox(width: 12),
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
        trailing: Icon(
          Icons.arrow_forward_ios,
          size: 14,
          color: Colors.grey[400],
        ),
        onTap: () => _handleEventTap(event),
      ),
    );
  }
}
