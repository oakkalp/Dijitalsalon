import 'package:flutter/material.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:digimobil_new/widgets/robust_image_widget.dart';
import 'package:digimobil_new/widgets/media_viewer_modal.dart';
import 'package:digimobil_new/widgets/story_viewer_modal.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:provider/provider.dart';
import 'dart:async';

class EventProfileScreen extends StatefulWidget {
  final Event event;

  const EventProfileScreen({super.key, required this.event});

  @override
  State<EventProfileScreen> createState() => _EventProfileScreenState();
}

class _EventProfileScreenState extends State<EventProfileScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  bool _isLoading = false;
  List<Map<String, dynamic>> _media = [];
  List<Map<String, dynamic>> _stories = [];
  final ApiService _apiService = ApiService();
  Timer? _banCheckTimer; // ✅ Yasaklanan kullanıcı kontrolü için timer

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 2, vsync: this);
    _loadEventData();
    
    // ✅ Yasaklanan kullanıcı kontrolü için periyodik timer
    _banCheckTimer = Timer.periodic(const Duration(seconds: 10), (timer) {
      _checkUserBanStatus();
    });
  }

  @override
  void dispose() {
    _tabController.dispose();
    _banCheckTimer?.cancel(); // ✅ Timer'ı temizle
    super.dispose();
  }

  Future<void> _loadEventData() async {
    setState(() {
      _isLoading = true;
    });

    try {
      // ✅ Önce kullanıcının bu etkinlikte aktif katılımcı olup olmadığını kontrol et
      final participants = await _apiService.getParticipants(widget.event.id);
      final currentUser = Provider.of<AuthProvider>(context, listen: false).user;
      
      // Kullanıcı katılımcılar arasında var mı ve aktif mi kontrol et
      Map<String, dynamic>? participant;
      try {
        participant = participants.firstWhere(
          (p) => p['id'] == currentUser?.id,
        );
      } catch (e) {
        participant = null;
      }
      
      if (participant == null) {
        // ✅ Kullanıcı etkinlikte değil, ana ekrana dön
        if (mounted) {
          Navigator.of(context).pushNamedAndRemoveUntil(
            '/',
            (route) => false,
          );
          return;
        }
      } else if (participant['status'] == 'yasakli') {
        // ✅ Kullanıcı yasaklanmış, ana ekrana dön
        if (mounted) {
          Navigator.of(context).pushNamedAndRemoveUntil(
            '/',
            (route) => false,
          );
          return;
        }
      }
      
      // ✅ Paralel olarak medya ve hikayeleri yükle
      final mediaFuture = _apiService.getMedia(widget.event.id, page: 1, limit: 50);
      final storiesFuture = _apiService.getStories(widget.event.id);

      final results = await Future.wait([mediaFuture, storiesFuture]);
      
      final mediaData = results[0] as Map<String, dynamic>;
      final storiesData = results[1] as List<dynamic>;
      print('Raw stories data: ${storiesData.length} stories');
      
      final now = DateTime.now();
      final activeStories = storiesData.where((story) {
        // Use latest_story_time instead of created_at
        final latestStoryTime = story['latest_story_time'];
        if (latestStoryTime == null) return false;
        
        final createdAt = DateTime.parse(latestStoryTime);
        final daysDifference = now.difference(createdAt).inDays;
        print('Story: ${story['user_name']}, Days old: $daysDifference');
        return daysDifference < 7; // Only show stories less than 7 days old
      }).toList();
      
      print('Active stories after filter: ${activeStories.length}');
      
      if (mounted) {
        setState(() {
          _media = (mediaData['media'] as List<dynamic>?)?.cast<Map<String, dynamic>>() ?? [];
          _stories = activeStories.cast<Map<String, dynamic>>();
          _isLoading = false;
        });
      }
    } catch (e) {
      print('Error loading event data: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Veri yüklenirken hata: $e'),
            backgroundColor: AppColors.error,
          ),
        );
        setState(() {
          _isLoading = false;
        });
      }
    }
  }
  
  // ✅ Kullanıcının yasaklanıp yasaklanmadığını periyodik kontrol et
  void _checkUserBanStatus() async {
    try {
      final participants = await _apiService.getParticipants(widget.event.id);
      final currentUser = Provider.of<AuthProvider>(context, listen: false).user;
      
      // Kullanıcı katılımcılar arasında var mı ve aktif mi kontrol et
      Map<String, dynamic>? participant;
      try {
        participant = participants.firstWhere(
          (p) => p['id'] == currentUser?.id,
        );
      } catch (e) {
        participant = null;
      }
      
      if (participant == null || participant['status'] == 'yasakli') {
        // ✅ Kullanıcı yasaklanmış veya etkinlikte değil, ana ekrana dön
        if (mounted) {
          Navigator.of(context).pushNamedAndRemoveUntil(
            '/',
            (route) => false,
          );
        }
      }
    } catch (e) {
      print('❌ Yasaklanan kullanıcı kontrolü hatası: $e');
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      body: NestedScrollView(
        headerSliverBuilder: (context, innerBoxIsScrolled) {
          return [
            SliverAppBar(
              expandedHeight: 300,
              floating: false,
              pinned: true,
              backgroundColor: Colors.white,
              elevation: 0,
              leading: Container(
                margin: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.white.withOpacity(0.9),
                  shape: BoxShape.circle,
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.1),
                      blurRadius: 4,
                      offset: const Offset(0, 2),
                    ),
                  ],
                ),
                child: IconButton(
                  icon: const Icon(Icons.arrow_back, color: Colors.black),
                  onPressed: () => Navigator.pop(context),
                ),
              ),
              flexibleSpace: FlexibleSpaceBar(
                background: _buildEventHeader(),
              ),
            ),
          ];
        },
        body: Column(
          children: [
            // Tab Bar
            Container(
              color: Colors.white,
              child: TabBar(
                controller: _tabController,
                indicatorColor: const Color(0xFFE1306C),
                labelColor: const Color(0xFFE1306C),
                unselectedLabelColor: Colors.grey[600],
                tabs: const [
                  Tab(
                    icon: Icon(Icons.grid_on),
                    text: 'Medyalar',
                  ),
                  Tab(
                    icon: Icon(Icons.auto_stories),
                    text: 'Hikayeler',
                  ),
                ],
              ),
            ),
            
            // Tab Content
            Expanded(
              child: _isLoading 
                ? const Center(
                    child: CircularProgressIndicator(
                      valueColor: AlwaysStoppedAnimation<Color>(Color(0xFFE1306C)),
                    ),
                  )
                : TabBarView(
                    controller: _tabController,
                    children: [
                      _buildMediaGrid(),
                      _buildStoriesGrid(),
                    ],
                  ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildEventHeader() {
    return Container(
      decoration: BoxDecoration(
        gradient: LinearGradient(
          begin: Alignment.topCenter,
          end: Alignment.bottomCenter,
          colors: [
            const Color(0xFFE1306C).withOpacity(0.8),
            const Color(0xFFF56040).withOpacity(0.8),
          ],
        ),
      ),
      child: Stack(
        children: [
          // Background Image
          if (widget.event.coverPhoto != null)
            Positioned.fill(
              child: CachedNetworkImage(
                imageUrl: widget.event.coverPhoto!.startsWith('http') 
                    ? widget.event.coverPhoto! 
                    : 'https://dijitalsalon.cagapps.app/${widget.event.coverPhoto!}',
                fit: BoxFit.cover,
                placeholder: (context, url) => Container(
                  color: Colors.grey[200],
                ),
                errorWidget: (context, url, error) => Container(
                  color: Colors.grey[200],
                ),
              ),
            ),
          
          // Gradient Overlay
          Positioned.fill(
            child: Container(
              decoration: BoxDecoration(
                gradient: LinearGradient(
                  begin: Alignment.topCenter,
                  end: Alignment.bottomCenter,
                  colors: [
                    Colors.black.withOpacity(0.3),
                    Colors.black.withOpacity(0.7),
                  ],
                ),
              ),
            ),
          ),
          
          // Event Info
          Positioned(
            bottom: 20,
            left: 20,
            right: 20,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  widget.event.title,
                  style: const TextStyle(
                    color: Colors.white,
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                if (widget.event.description != null) ...[
                  const SizedBox(height: 8),
                  Text(
                    widget.event.description!,
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.9),
                      fontSize: 16,
                    ),
                  ),
                ],
                const SizedBox(height: 16),
                Row(
                  children: [
                    _buildHeaderStat(Icons.people, '${widget.event.participantCount}'),
                    const SizedBox(width: 20),
                    _buildHeaderStat(Icons.photo_library, '${_media.length}'),
                    const SizedBox(width: 20),
                    _buildHeaderStat(Icons.auto_stories, '${_stories.length}'),
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildHeaderStat(IconData icon, String count) {
    return Row(
      children: [
        Icon(icon, color: Colors.white, size: 18),
        const SizedBox(width: 4),
        Text(
          count,
          style: const TextStyle(
            color: Colors.white,
            fontSize: 16,
            fontWeight: FontWeight.w600,
          ),
        ),
      ],
    );
  }

  Widget _buildMediaGrid() {

    if (_media.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.photo_library_outlined,
              size: 80,
              color: Colors.grey[400],
            ),
            const SizedBox(height: 16),
            Text(
              'Henüz medya paylaşılmamış',
              style: TextStyle(
                fontSize: 18,
                color: Colors.grey[600],
              ),
            ),
          ],
        ),
      );
    }

    return GridView.builder(
      padding: const EdgeInsets.all(16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        crossAxisSpacing: 2,
        mainAxisSpacing: 2,
      ),
      itemCount: _media.length,
      itemBuilder: (context, index) {
        final media = _media[index];
        return _buildMediaItem(media);
      },
    );
  }

  Widget _buildMediaItem(Map<String, dynamic> media) {
    final mediaType = media['tur'] ?? media['type'] ?? '';
    final isVideo = mediaType == 'video';
    final imageUrl = media['url'] ?? media['media_url'];
    final thumbnailUrl = media['thumbnail'];
    final previewUrl = media['preview']; // Small version for grid

    return GestureDetector(
      onTap: () {
        // Open media viewer modal
        Navigator.push(
          context,
          MaterialPageRoute(
            builder: (context) => MediaViewerModal(
              mediaList: _media,
              initialIndex: _media.indexOf(media),
            ),
          ),
        );
      },
      child: Container(
        decoration: BoxDecoration(
          color: Colors.grey[200],
        ),
        child: Stack(
          fit: StackFit.expand,
          children: [
            // Use original image for grid (preview files are missing)
            if (imageUrl != null)
              RobustImageWidget(
                imageUrl: imageUrl,
                fit: BoxFit.cover,
                onImageLoaded: () => print('Event Profile - Media URL: $imageUrl'),
              )
            else if (isVideo && thumbnailUrl != null)
              RobustImageWidget(
                imageUrl: thumbnailUrl,
                fit: BoxFit.cover,
              ),
            if (isVideo)
              const Center(
                child: Icon(
                  Icons.play_circle_filled,
                  color: Colors.white,
                  size: 30,
                ),
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildStoriesGrid() {

    if (_stories.isEmpty) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.auto_stories_outlined,
              size: 80,
              color: Colors.grey[400],
            ),
            const SizedBox(height: 16),
            Text(
              'Henüz hikaye paylaşılmamış',
              style: TextStyle(
                fontSize: 18,
                color: Colors.grey[600],
              ),
            ),
            const SizedBox(height: 8),
            Text(
              'Hikayeler 7 gün sonra otomatik silinir',
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey[500],
              ),
            ),
          ],
        ),
      );
    }

    return GridView.builder(
      padding: const EdgeInsets.all(16),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        crossAxisSpacing: 2,
        mainAxisSpacing: 2,
      ),
      itemCount: _stories.length,
      itemBuilder: (context, index) {
        final story = _stories[index];
        return _buildStoryItem(story);
      },
    );
  }

  Widget _buildStoryItem(Map<String, dynamic> story) {
    final isVideo = (story['tur'] ?? story['media_type'] ?? '') == 'video';
    final imageUrl = story['url'] ?? story['media_url'];

    return GestureDetector(
      onTap: () async {
        // Open story viewer with user's stories
        final userId = story['user_id'];
        if (userId != null) {
          try {
            final userStories = await _apiService.getUserStories(widget.event.id, userId);
            if (userStories.isNotEmpty && mounted) {
              Navigator.push(
                context,
                MaterialPageRoute(
                  builder: (context) => StoryViewerModal(
                    stories: userStories,
                    initialIndex: 0,
                    event: widget.event, // ✅ Event parametresi eklendi
                  ),
                ),
              );
            }
          } catch (e) {
            if (mounted) {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('Hikayeler yüklenirken hata: $e'),
                  backgroundColor: AppColors.error,
                ),
              );
            }
          }
        }
      },
      child: Container(
        decoration: BoxDecoration(
          color: Colors.grey[200],
          borderRadius: BorderRadius.circular(8),
        ),
        child: Stack(
          fit: StackFit.expand,
          children: [
            if (imageUrl != null)
              ClipRRect(
                borderRadius: BorderRadius.circular(8),
                child: RobustImageWidget(
                  imageUrl: imageUrl,
                  fit: BoxFit.cover,
                ),
              ),
            if (isVideo)
              const Center(
                child: Icon(
                  Icons.play_circle_filled,
                  color: Colors.white,
                  size: 30,
                ),
              ),
            // Story indicator
            Positioned(
              top: 4,
              right: 4,
              child: Container(
                width: 8,
                height: 8,
                decoration: const BoxDecoration(
                  color: Color(0xFFE1306C),
                  shape: BoxShape.circle,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
