import 'package:flutter/material.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/screens/event_detail_screen.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:cached_network_image/cached_network_image.dart';

class InstagramHomeScreen extends StatefulWidget {
  const InstagramHomeScreen({super.key});

  @override
  State<InstagramHomeScreen> createState() => _InstagramHomeScreenState();
}

class _InstagramHomeScreenState extends State<InstagramHomeScreen> {
  List<Event> _events = [];
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
      
      // ✅ Eventleri yaklaşan ve geçmiş olarak ayır
      final now = DateTime.now();
      final upcomingEvents = <Event>[];
      final pastEvents = <Event>[];
      
      for (final event in events) {
        if (event.date != null) {
          final eventDate = DateTime.tryParse(event.date!);
          if (eventDate != null) {
            if (eventDate.isAfter(now)) {
              upcomingEvents.add(event);
            } else {
              pastEvents.add(event);
            }
          } else {
            // Tarih parse edilemezse yaklaşan olarak ekle
            upcomingEvents.add(event);
          }
        } else {
          // Tarih yoksa yaklaşan olarak ekle
          upcomingEvents.add(event);
        }
      }
      
      // Geçmiş etkinlikleri tarihe göre sırala (en yeni önce)
      pastEvents.sort((a, b) {
        if (a.date == null && b.date == null) return 0;
        if (a.date == null) return 1;
        if (b.date == null) return -1;
        return DateTime.parse(b.date!).compareTo(DateTime.parse(a.date!));
      });
      
      setState(() {
        _events = events;
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
    if (_events.isEmpty) {
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

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: _events.length,
      itemBuilder: (context, index) {
        final event = _events[index];
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
                            imageUrl: event.coverPhoto!,
                            width: 50,
                            height: 50,
                            fit: BoxFit.cover,
                            placeholder: (context, url) => const Icon(
                              Icons.event,
                              color: AppColors.primary,
                              size: 25,
                            ),
                            errorWidget: (context, url, error) => const Icon(
                              Icons.event,
                              color: AppColors.primary,
                              size: 25,
                            ),
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
              ],
            ),
            trailing: const Icon(
              Icons.arrow_forward_ios,
              color: Colors.grey,
              size: 16,
            ),
            onTap: () {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => EventDetailScreen(event: event),
                ),
              );
            },
          ),
        );
      },
    );
  }
}
