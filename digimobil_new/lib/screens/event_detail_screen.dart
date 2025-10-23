import 'package:flutter/material.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/widgets/instagram_stories_bar.dart';
import 'package:digimobil_new/widgets/instagram_post_card.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:provider/provider.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:file_picker/file_picker.dart';
import 'package:image_picker/image_picker.dart';
import 'package:digimobil_new/screens/event_profile_screen.dart';
import 'package:digimobil_new/widgets/permission_grant_modal.dart';
import 'dart:io';
import 'dart:async';
import 'package:digimobil_new/widgets/story_viewer_modal.dart';

class EventDetailScreen extends StatefulWidget {
  final Event? event;

  const EventDetailScreen({super.key, this.event});

  @override
  State<EventDetailScreen> createState() => _EventDetailScreenState();
}

class _EventDetailScreenState extends State<EventDetailScreen>
    with SingleTickerProviderStateMixin {
  late TabController _tabController;
  bool _isLoading = false;
  bool _isLoadingMore = false;
  List<Map<String, dynamic>> _media = [];
  List<Map<String, dynamic>> _stories = [];
  final ApiService _apiService = ApiService();
  final ScrollController _scrollController = ScrollController();
  int _currentPage = 1;
  bool _hasMoreMedia = true;
  Timer? _banCheckTimer; // ✅ Yasaklanan kullanıcı kontrolü için timer
  Timer? _dataRefreshTimer; // ✅ Real-time veri yenileme için timer
  static const int _pageSize = 5; // Her seferinde 5 medya yükle
  
  // ✅ Paylaşım yetkisi kontrolü
  bool _canShareContent() {
    if (widget.event == null) return false;
    
    final eventDate = DateTime.tryParse(widget.event!.date ?? '');
    if (eventDate == null) return false;
    
    final today = DateTime.now();
    final todayOnly = DateTime(today.year, today.month, today.day);
    final eventDateOnly = DateTime(eventDate.year, eventDate.month, eventDate.day);
    
    // ✅ Etkinlik henüz başlamamışsa paylaşım yapamaz
    if (todayOnly.isBefore(eventDateOnly)) {
      return false;
    }
    
    // ✅ Ücretsiz erişim günü kontrolü (varsayılan 7 gün)
    final freeAccessDays = widget.event!.freeAccessDays ?? 7;
    final accessEndDate = eventDateOnly.add(Duration(days: freeAccessDays));
    
    // ✅ Ücretsiz erişim süresi bitmişse paylaşım yapamaz
    if (todayOnly.isAfter(accessEndDate)) {
      return false;
    }
    
    return true;
  }

  @override
  void initState() {
    super.initState();
    _tabController = TabController(length: 4, vsync: this);
    _scrollController.addListener(_onScroll);
    if (widget.event != null) {
      _loadEventData();
    }
    
    // ✅ Yasaklanan kullanıcı kontrolü için periyodik timer
    _banCheckTimer = Timer.periodic(const Duration(seconds: 10), (timer) {
      _checkUserBanStatus();
    });
    
    // ✅ Real-time veri yenileme için periyodik timer
    _dataRefreshTimer = Timer.periodic(const Duration(seconds: 15), (timer) {
      _refreshData();
    });
  }

  void _onScroll() {
    if (_scrollController.position.pixels >= 
        _scrollController.position.maxScrollExtent - 200) {
      if (!_isLoadingMore && _hasMoreMedia) {
        _loadMoreMedia();
      }
    }
  }

  @override
  void dispose() {
    _tabController.dispose();
    _scrollController.dispose();
    _banCheckTimer?.cancel(); // ✅ Timer'ı temizle
    _dataRefreshTimer?.cancel(); // ✅ Data refresh timer'ı temizle
    super.dispose();
  }

  Future<void> _loadEventData() async {
    if (widget.event == null) return;
    
    print('Loading event data for event ID: ${widget.event!.id}');
    
    setState(() {
      _isLoading = true;
    });

    try {
      // ✅ Önce kullanıcının bu etkinlikte aktif katılımcı olup olmadığını kontrol et
      final participants = await _apiService.getParticipants(widget.event!.id);
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
      
      // Load media (first page only)
      print('Loading media...');
      final mediaData = await _apiService.getMedia(widget.event!.id, page: 1, limit: _pageSize);
      print('Media data received: ${mediaData}');
      setState(() {
        _media = (mediaData['media'] as List<dynamic>?)?.cast<Map<String, dynamic>>() ?? [];
        _hasMoreMedia = (mediaData['pagination']?['has_more'] ?? false);
      });
      print('Media loaded: ${_media.length} items');

      // Load stories
      print('Loading stories...');
      final storiesData = await _apiService.getStories(widget.event!.id);
      print('Stories data received: ${storiesData}');
      setState(() {
        _stories = storiesData;
      });
      print('Stories loaded: ${_stories.length} items');
    } catch (e) {
      print('Error loading event data: $e');
      
      // ✅ Eğer "Bu etkinliğe katılımcı değilsiniz" hatası ise kullanıcıyı ana ekrana yönlendir
      if (e.toString().contains('Bu etkinliğe katılımcı değilsiniz') || 
          e.toString().contains('Bu etkinliğe katılımcı değilsiniz')) {
        print('🚫 Kullanıcı etkinlikten çıkarıldı, ana ekrana yönlendiriliyor...');
        if (mounted) {
          Navigator.of(context).pushNamedAndRemoveUntil(
            '/',
            (route) => false,
          );
          return;
        }
      }
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Veri yüklenirken hata: $e'),
          backgroundColor: AppColors.error,
        ),
      );
    } finally {
      setState(() {
        _isLoading = false;
      });
      print('Loading completed. Media: ${_media.length}, Stories: ${_stories.length}');
    }
  }

  Future<void> _loadMoreMedia() async {
    if (widget.event == null || !_hasMoreMedia) return;
    
    setState(() {
      _isLoadingMore = true;
    });

    try {
      _currentPage++;
      print('Loading more media - Page: $_currentPage');
      
      final response = await _apiService.getMedia(widget.event!.id, page: _currentPage, limit: _pageSize);
      final newMedia = response['media'] as List<Map<String, dynamic>>;
      
      if (newMedia.isEmpty) {
        _hasMoreMedia = false;
        print('No more media to load');
      } else {
        setState(() {
          _media.addAll(newMedia);
        });
        print('Loaded ${newMedia.length} more media items. Total: ${_media.length}');
      }
    } catch (e) {
      print('Error loading more media: $e');
      _currentPage--; // Rollback page on error
    } finally {
      setState(() {
        _isLoadingMore = false;
      });
    }
  }

  Future<void> _showCameraOptions() async {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        decoration: const BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Handle bar
            Container(
              width: 40,
              height: 4,
              margin: const EdgeInsets.symmetric(vertical: 12),
              decoration: BoxDecoration(
                color: Colors.grey[300],
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            
            // Title
            const Padding(
              padding: EdgeInsets.symmetric(horizontal: 20, vertical: 10),
              child: Text(
                'Paylaşım Seçenekleri',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            
            const Divider(),
            
            // Options
            ListTile(
              leading: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.blue.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.photo_camera,
                  color: Colors.blue,
                  size: 24,
                ),
              ),
              title: const Text('Fotoğraf Çek'),
              subtitle: const Text('Kameradan fotoğraf çek'),
              onTap: () {
                Navigator.pop(context);
                _openCamera('photo');
              },
            ),
            
            ListTile(
              leading: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.red.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.videocam,
                  color: Colors.red,
                  size: 24,
                ),
              ),
              title: const Text('Video Çek'),
              subtitle: const Text('Kameradan video çek'),
              onTap: () {
                Navigator.pop(context);
                _openCamera('video');
              },
            ),
            
            ListTile(
              leading: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.green.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.photo_library,
                  color: Colors.green,
                  size: 24,
                ),
              ),
              title: const Text('Galeriden Fotoğraf Seç'),
              subtitle: const Text('Galeriden fotoğraf seç'),
              onTap: () {
                Navigator.pop(context);
                _pickFromGallery('photo');
              },
            ),
            
            ListTile(
              leading: Container(
                padding: const EdgeInsets.all(8),
                decoration: BoxDecoration(
                  color: Colors.orange.withOpacity(0.1),
                  borderRadius: BorderRadius.circular(8),
                ),
                child: const Icon(
                  Icons.video_library,
                  color: Colors.orange,
                  size: 24,
                ),
              ),
              title: const Text('Galeriden Video Seç'),
              subtitle: const Text('Galeriden video seç'),
              onTap: () {
                Navigator.pop(context);
                _pickFromGallery('video');
              },
            ),
            
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Future<void> _openCamera(String type) async {
    try {
      final ImagePicker picker = ImagePicker();
      XFile? file;
      
      if (type == 'photo') {
        file = await picker.pickImage(source: ImageSource.camera);
      } else if (type == 'video') {
        file = await picker.pickVideo(source: ImageSource.camera);
      }

      if (file == null) return;

      // Show content type selection (Media or Story)
      final contentType = await showDialog<String>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Paylaşım Türü'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.grid_on, color: Colors.blue),
                title: const Text('Gönderi Olarak Paylaş'),
                subtitle: const Text('Ana sayfada görünür'),
                onTap: () => Navigator.pop(context, 'media'),
              ),
              ListTile(
                leading: const Icon(Icons.circle, color: Colors.purple),
                title: const Text('Hikaye Olarak Paylaş'),
                subtitle: const Text('24 saat sonra silinir'),
                onTap: () => Navigator.pop(context, 'story'),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('İptal'),
            ),
          ],
        ),
      );

      if (contentType == null) return;

      // Show description modal
      final TextEditingController descriptionController = TextEditingController();
      final description = await showModalBottomSheet<String>(
        context: context,
        backgroundColor: Colors.transparent,
        builder: (context) => Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Handle bar
              Container(
                width: 40,
                height: 4,
                margin: const EdgeInsets.symmetric(vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.grey[300],
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              
              Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  children: [
                    Text(
                      contentType == 'media' ? 'Gönderi Açıklaması' : 'Hikaye Açıklaması',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      controller: descriptionController,
                      decoration: InputDecoration(
                        hintText: contentType == 'media' ? 'Gönderi açıklaması...' : 'Hikaye açıklaması...',
                        border: const OutlineInputBorder(),
                      ),
                      maxLines: 3,
                    ),
                    const SizedBox(height: 16),
                    Row(
                      mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                      children: [
                        TextButton(
                          onPressed: () => Navigator.pop(context),
                          child: const Text('İptal'),
                        ),
                        ElevatedButton(
                          onPressed: () {
                            Navigator.pop(context, descriptionController.text);
                          },
                          child: const Text('Paylaş'),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      );

      if (description == null) return;

      // Upload media or story
      if (contentType == 'media') {
        await _uploadMedia(file.path, description);
      } else {
        await _uploadStory(file.path, description);
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Kamera hatası: $e'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  Future<void> _pickFromGallery(String type) async {
    try {
      PlatformFile? file;
      
      if (type == 'photo') {
        FilePickerResult? result = await FilePicker.platform.pickFiles(
          type: FileType.image,
          allowMultiple: false,
        );
        file = result?.files.first;
      } else if (type == 'video') {
        FilePickerResult? result = await FilePicker.platform.pickFiles(
          type: FileType.video,
          allowMultiple: false,
        );
        file = result?.files.first;
      }

      if (file == null) return;

      // Show content type selection (Media or Story)
      final contentType = await showDialog<String>(
        context: context,
        builder: (context) => AlertDialog(
          title: const Text('Paylaşım Türü'),
          content: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              ListTile(
                leading: const Icon(Icons.grid_on, color: Colors.blue),
                title: const Text('Gönderi Olarak Paylaş'),
                subtitle: const Text('Ana sayfada görünür'),
                onTap: () => Navigator.pop(context, 'media'),
              ),
              ListTile(
                leading: const Icon(Icons.circle, color: Colors.purple),
                title: const Text('Hikaye Olarak Paylaş'),
                subtitle: const Text('24 saat sonra silinir'),
                onTap: () => Navigator.pop(context, 'story'),
              ),
            ],
          ),
          actions: [
            TextButton(
              onPressed: () => Navigator.pop(context),
              child: const Text('İptal'),
            ),
          ],
        ),
      );

      if (contentType == null) return;

      // Show description modal
      final description = await showModalBottomSheet<String>(
        context: context,
        backgroundColor: Colors.transparent,
        builder: (context) => Container(
          decoration: const BoxDecoration(
            color: Colors.white,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              // Handle bar
              Container(
                width: 40,
                height: 4,
                margin: const EdgeInsets.symmetric(vertical: 12),
                decoration: BoxDecoration(
                  color: Colors.grey[300],
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              
              Padding(
                padding: const EdgeInsets.all(20),
                child: Column(
                  children: [
                    Text(
                      contentType == 'media' ? 'Gönderi Açıklaması' : 'Hikaye Açıklaması',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                    const SizedBox(height: 16),
                    TextField(
                      decoration: InputDecoration(
                        hintText: contentType == 'media' 
                            ? 'Gönderiniz için açıklama yazın...'
                            : 'Hikayeniz için açıklama yazın...',
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                        ),
                        filled: true,
                        fillColor: Colors.grey[50],
                      ),
                      maxLines: 3,
                      controller: TextEditingController(),
                    ),
                    const SizedBox(height: 20),
                    Row(
                      children: [
                        Expanded(
                          child: TextButton(
                            onPressed: () => Navigator.pop(context),
                            child: const Text('İptal'),
                          ),
                        ),
                        const SizedBox(width: 12),
                        Expanded(
                          child: ElevatedButton(
                            onPressed: () {
                              Navigator.pop(context, 'Açıklama');
                            },
                            style: ElevatedButton.styleFrom(
                              backgroundColor: AppColors.primary,
                              foregroundColor: Colors.white,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            child: const Text('Paylaş'),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      );

      if (description == null) return;

      // Upload based on content type
      if (contentType == 'media') {
        await _uploadMedia(file.path!, description);
      } else {
        await _uploadStory(file.path!, description);
      }

    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Dosya seçilirken hata: $e'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  Future<void> _uploadMedia(String filePath, String description) async {
    try {
      final result = await _apiService.addMedia(
        widget.event!.id,
        filePath,
        description,
      );

      if (result['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Gönderi başarıyla eklendi'),
            backgroundColor: AppColors.success,
          ),
        );
        
        // ✅ Medya eklendikten sonra UI'yi güncelle
        await _loadEventData();
        
        // ✅ Medya listesini yeniden yükle
        final mediaData = await _apiService.getMedia(widget.event!.id, page: 1, limit: _pageSize);
        setState(() {
          _media = (mediaData['media'] as List<dynamic>?)?.cast<Map<String, dynamic>>() ?? [];
          _hasMoreMedia = (mediaData['pagination']?['has_more'] ?? false);
        });
      } else {
        throw Exception(result['error'] ?? 'Upload failed');
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Gönderi eklenirken hata: $e'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  Future<void> _uploadStory(String filePath, String description) async {
    try {
      final result = await _apiService.addStory(
        widget.event!.id,
        filePath,
        description,
      );

      if (result['success'] == true) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Hikaye başarıyla eklendi'),
            backgroundColor: AppColors.success,
          ),
        );
        
        // ✅ Hikaye eklendikten sonra UI'yi güncelle
        await _loadEventData();
        
        // ✅ Hikaye listesini yeniden yükle
        final storiesData = await _apiService.getStories(widget.event!.id);
        setState(() {
          _stories = storiesData;
        });
      } else {
        throw Exception(result['error'] ?? 'Upload failed');
      }
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Hikaye eklenirken hata: $e'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  void _openEventProfile() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => EventProfileScreen(event: widget.event!),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    print('Building EventDetailScreen. Event: ${widget.event?.title}, Loading: $_isLoading');
    
    if (widget.event == null) {
      print('Event is null, showing error screen');
      return Scaffold(
        appBar: AppBar(
          title: const Text('Etkinlik Bulunamadı'),
          backgroundColor: Colors.white,
          foregroundColor: Colors.black,
        ),
        body: const Center(
          child: Text('Etkinlik bulunamadı'),
        ),
      );
    }

    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        foregroundColor: Colors.black,
        elevation: 0,
        title: Text(
          widget.event!.title,
          style: const TextStyle(
            fontWeight: FontWeight.bold,
            fontSize: 18,
          ),
        ),
        centerTitle: true,
        actions: [
          GestureDetector(
            onTap: () => _openEventProfile(),
            child: Container(
              margin: const EdgeInsets.only(right: 16),
              padding: const EdgeInsets.all(8),
              decoration: BoxDecoration(
                color: AppColors.primary.withOpacity(0.1),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.person,
                color: AppColors.primary,
                size: 20,
              ),
            ),
          ),
        ],
      ),
      body: _isLoading
          ? const Center(
              child: CircularProgressIndicator(color: AppColors.primary),
            )
          : Column(
              children: [
                // Tab Bar
                TabBar(
                  controller: _tabController,
                  labelColor: AppColors.primary,
                  unselectedLabelColor: Colors.grey,
                  indicatorColor: AppColors.primary,
                  tabs: const [
                    Tab(icon: Icon(Icons.home), text: 'Ana Sayfa'),
                    Tab(icon: Icon(Icons.photo_library), text: 'Medya'),
                    Tab(icon: Icon(Icons.auto_stories), text: 'Hikayeler'),
                    Tab(icon: Icon(Icons.people), text: 'Katılımcılar'),
                  ],
                ),
                // Tab Content
                Expanded(
                  child: TabBarView(
                    controller: _tabController,
                    children: [
                      // Ana Sayfa - Stories + Posts
                      SingleChildScrollView(
                        controller: _scrollController,
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Stories Bar
                            InstagramStoriesBar(
                              events: [widget.event!],
                              stories: _stories,
                              onAddStory: _showCameraOptions,
                              onEventSelected: (event) {
                                // Already in this event
                              },
                            ),
                            const Divider(color: Colors.black26),
                            // Posts Feed
                            _buildPostsFeed(),
                          ],
                        ),
                      ),
                      // Medya Sekmesi
                      SingleChildScrollView(
                        child: _buildPostsFeed(),
                      ),
                      // Hikayeler Sekmesi
                      SingleChildScrollView(
                        child: Column(
                          children: [
                            // Stories Bar
                            InstagramStoriesBar(
                              events: [widget.event!],
                              stories: _stories,
                              onAddStory: _showCameraOptions,
                              onEventSelected: (event) {
                                // Already in this event
                              },
                            ),
                            const Divider(color: Colors.black26),
                            // Stories List
                            _buildStoriesList(),
                          ],
                        ),
                      ),
                      // Katılımcılar Sekmesi
                      _buildParticipantsTab(),
                    ],
                  ),
                ),
              ],
            ),
      floatingActionButton: _canShareContent() ? FloatingActionButton(
        heroTag: "event_detail_camera_fab",
        onPressed: _showCameraOptions,
        backgroundColor: const Color(0xFFE1306C),
        child: const Icon(Icons.camera_alt, color: Colors.white),
      ) : null,
    );
  }

  Widget _buildPostsFeed() {
    print('Building posts feed. Media count: ${_media.length}');
    
    if (_media.isEmpty) {
      print('No media found, showing empty state');
      return const Center(
        child: Padding(
          padding: EdgeInsets.all(50),
          child: Column(
            children: [
              Icon(
                Icons.photo_library_outlined,
                size: 80,
                color: Colors.grey,
              ),
              SizedBox(height: 20),
              Text(
                'Henüz gönderi yok',
                style: TextStyle(
                  fontSize: 18,
                  color: Colors.grey,
                ),
              ),
              SizedBox(height: 10),
              Text(
                'İlk gönderiyi eklemek için + butonuna tıklayın',
                style: TextStyle(
                  fontSize: 14,
                  color: Colors.grey,
                ),
              ),
            ],
          ),
        ),
      );
    }

    // Only show media as posts (no stories)
    List<Map<String, dynamic>> mediaPosts = [];
    
    // Add media as posts
    for (var media in _media) {
      mediaPosts.add({
        ...media,
        'post_type': 'media',
      });
    }
    
    // Sort by creation date (newest first)
    mediaPosts.sort((a, b) {
      final dateA = DateTime.tryParse(a['created_at'] ?? '') ?? DateTime(1970);
      final dateB = DateTime.tryParse(b['created_at'] ?? '') ?? DateTime(1970);
      return dateB.compareTo(dateA);
    });

    return Column(
      children: [
        ...mediaPosts.map((post) => InstagramPostCard(
          event: widget.event!,
          post: post,
          onTap: () {
            // Show post detail
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Gönderi detayı: ${post['description'] ?? 'Açıklama yok'}'),
                backgroundColor: AppColors.info,
              ),
            );
          },
          onMediaDeleted: () {
            // Refresh media list when media is deleted
            _loadEventData();
          },
          onCommentCountChanged: () {
            // Refresh media list when comment count changes
            _loadEventData();
          },
        )).toList(),
        // Loading indicator for pagination
        if (_isLoadingMore)
          const Padding(
            padding: EdgeInsets.all(20),
            child: Center(
              child: CircularProgressIndicator(
                color: AppColors.primary,
                strokeWidth: 2,
              ),
            ),
          ),
        // End of content indicator
        if (!_hasMoreMedia && _media.isNotEmpty)
          const Padding(
            padding: EdgeInsets.all(20),
            child: Center(
              child: Text(
                'Tüm gönderiler yüklendi',
                style: TextStyle(
                  color: Colors.grey,
                  fontSize: 14,
                ),
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildMediaGrid() {
    if (_media.isEmpty) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.photo_library_outlined,
              size: 80,
              color: Colors.grey,
            ),
            SizedBox(height: 20),
            Text(
              'Henüz medya yok',
              style: TextStyle(
                fontSize: 18,
                color: Colors.grey,
              ),
            ),
            SizedBox(height: 10),
            Text(
              'İlk medyayı eklemek için + butonuna tıklayın',
              style: TextStyle(
                fontSize: 14,
                color: Colors.grey,
              ),
            ),
          ],
        ),
      );
    }

    return GridView.builder(
      padding: const EdgeInsets.all(1),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        crossAxisSpacing: 1,
        mainAxisSpacing: 1,
      ),
      itemCount: _media.length,
      itemBuilder: (context, index) {
        final media = _media[index];
        return GestureDetector(
          onTap: () {
            // Show media detail
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(
                content: Text('Medya detayı: ${media['description'] ?? 'Açıklama yok'}'),
                backgroundColor: AppColors.info,
              ),
            );
          },
          child: Container(
            decoration: BoxDecoration(
              image: media['url'] != null
                  ? DecorationImage(
                      image: NetworkImage(media['url']),
                      fit: BoxFit.cover,
                    )
                  : null,
              color: media['url'] == null
                  ? AppColors.primary.withOpacity(0.1)
                  : null,
            ),
            child: media['url'] == null
                ? const Center(
                    child: Icon(
                      Icons.image,
                      color: AppColors.primary,
                      size: 30,
                    ),
                  )
                : null,
          ),
        );
      },
    );
  }

  Widget _buildStoriesList() {
    if (_stories.isEmpty) {
      return const Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.video_library_outlined,
              size: 80,
              color: Colors.grey,
            ),
            SizedBox(height: 20),
            Text(
              'Henüz hikaye yok',
              style: TextStyle(
                fontSize: 18,
                color: Colors.grey,
              ),
            ),
            SizedBox(height: 10),
            Text(
              'İlk hikayeyi eklemek için kamera butonuna tıklayın',
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
      shrinkWrap: true, // ✅ Height sınırı sorunu için
      physics: const NeverScrollableScrollPhysics(), // ✅ Parent scroll ile çakışmayı önle
      padding: const EdgeInsets.all(16),
      itemCount: _stories.length,
      itemBuilder: (context, index) {
        final story = _stories[index];
        return Card(
          margin: const EdgeInsets.only(bottom: 16),
          child: ListTile(
            leading: CircleAvatar(
              backgroundColor: AppColors.primary.withOpacity(0.1),
              child: const Icon(
                Icons.video_library,
                color: AppColors.primary,
              ),
            ),
            title: Text(
              story['user_name'] ?? 'Kullanıcı',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            subtitle: Text(
              story['description'] ?? 'Açıklama yok',
            ),
            trailing: Text(
              story['created_at'] ?? '',
              style: const TextStyle(fontSize: 12, color: Colors.grey),
            ),
            onTap: () {
              ScaffoldMessenger.of(context).showSnackBar(
                SnackBar(
                  content: Text('Hikaye detayı: ${story['description'] ?? 'Açıklama yok'}'),
                  backgroundColor: AppColors.info,
                ),
              );
            },
          ),
        );
      },
    );
  }

  Widget _buildParticipantsTab() {
    final currentUser = Provider.of<AuthProvider>(context, listen: false).user;
    final userRole = widget.event?.userRole;
    
    // Check if user can manage participants
    // Süper Admin, Moderatör ve Yetkili Kullanıcı katılımcıları yönetebilir
    final canManageParticipants = userRole != null && 
        ['admin', 'moderator', 'yetkili_kullanici'].contains(userRole);
    
    if (!canManageParticipants) {
      // ✅ Normal katılımcılar katılımcıları görebilir ama yönetemez
      return FutureBuilder<List<Map<String, dynamic>>>(
        future: _apiService.getParticipants(widget.event!.id),
        builder: (context, snapshot) {
          if (snapshot.connectionState == ConnectionState.waiting) {
            return const Center(child: CircularProgressIndicator(color: AppColors.primary));
          }
          
          if (snapshot.hasError) {
            return Center(
              child: Padding(
                padding: const EdgeInsets.all(50),
                child: Column(
                  children: [
                    const Icon(Icons.error_outline, size: 80, color: Colors.red),
                    const SizedBox(height: 20),
                    Text('Katılımcılar yüklenirken hata: ${snapshot.error}', style: const TextStyle(fontSize: 16, color: Colors.red), textAlign: TextAlign.center),
                  ],
                ),
              ),
            );
          }
          
          final participants = snapshot.data ?? [];
          
          if (participants.isEmpty) {
            return const Center(
              child: Padding(
                padding: EdgeInsets.all(50),
                child: Column(
                  children: [
                    Icon(Icons.people_outline, size: 80, color: Colors.grey),
                    SizedBox(height: 20),
                    Text('Henüz katılımcı yok', style: TextStyle(fontSize: 18, color: Colors.grey)),
                  ],
                ),
              ),
            );
          }
          
          return ListView.builder(
            padding: const EdgeInsets.all(16),
            itemCount: participants.length,
            itemBuilder: (context, index) {
              final participant = participants[index];
              final isCurrentUser = participant['id'] == currentUser?.id;
              
              return Card(
                margin: const EdgeInsets.only(bottom: 12),
                child: ListTile(
                  // ✅ Normal katılımcı için dokunma özelliği yok
                  onTap: null,
                  leading: CircleAvatar(
                    backgroundImage: participant['avatar'] != null
                        ? NetworkImage(participant['avatar'])
                        : null,
                    backgroundColor: AppColors.primary.withOpacity(0.1),
                    child: participant['avatar'] == null
                        ? Icon(
                            Icons.person,
                            color: AppColors.primary,
                          )
                        : null,
                  ),
                  title: Text(
                    participant['name'] ?? 'Kullanıcı',
                    style: const TextStyle(fontWeight: FontWeight.bold),
                  ),
                  subtitle: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text('${participant['media_count']} medya • ${participant['story_count']} hikaye'),
                      Text(
                        'Rol: ${_getRoleDisplayName(participant['role'])}',
                        style: TextStyle(
                          color: _getRoleColor(participant['role']),
                          fontWeight: FontWeight.w500,
                        ),
                      ),
                    ],
                  ),
                  trailing: isCurrentUser
                      ? const Text(
                          'Siz',
                          style: TextStyle(
                            color: AppColors.primary,
                            fontWeight: FontWeight.bold,
                          ),
                        )
                      : null, // ✅ Normal katılımcı için 3 nokta yok
                ),
              );
            },
          );
        },
      );
    }
    
           return FutureBuilder<List<Map<String, dynamic>>>(
             future: _apiService.getParticipants(widget.event!.id), // ✅ Her seferinde fresh data
             builder: (context, snapshot) {
        if (snapshot.connectionState == ConnectionState.waiting) {
          return const Center(
            child: CircularProgressIndicator(color: AppColors.primary),
          );
        }
        
        if (snapshot.hasError) {
          return Center(
            child: Padding(
              padding: const EdgeInsets.all(50),
              child: Column(
                children: [
                  const Icon(
                    Icons.error_outline,
                    size: 80,
                    color: Colors.red,
                  ),
                  const SizedBox(height: 20),
                  Text(
                    'Katılımcılar yüklenirken hata: ${snapshot.error}',
                    style: const TextStyle(
                      fontSize: 16,
                      color: Colors.red,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),
          );
        }
        
        final participants = snapshot.data ?? [];
        
        if (participants.isEmpty) {
          return const Center(
            child: Padding(
              padding: EdgeInsets.all(50),
              child: Column(
                children: [
                  Icon(
                    Icons.people_outline,
                    size: 80,
                    color: Colors.grey,
                  ),
                  SizedBox(height: 20),
                  Text(
                    'Henüz katılımcı yok',
                    style: TextStyle(
                      fontSize: 18,
                      color: Colors.grey,
                    ),
                  ),
                ],
              ),
            ),
          );
        }
        
        return ListView.builder(
          padding: const EdgeInsets.all(16),
          itemCount: participants.length,
          itemBuilder: (context, index) {
            final participant = participants[index];
            final isCurrentUser = participant['id'] == currentUser?.id;
            
            final isBanned = participant['status'] == 'yasakli';
            
            return Card(
              margin: const EdgeInsets.only(bottom: 12),
              color: isBanned ? Colors.red.withOpacity(0.1) : null, // ✅ Yasaklanan kullanıcı için kırmızı arka plan
              child: ListTile(
                onTap: isBanned ? null : () => _showParticipantActionModal(participant, userRole), // ✅ Yasaklanan kullanıcıya dokunma yok
                leading: CircleAvatar(
                  backgroundImage: participant['avatar'] != null
                      ? NetworkImage(participant['avatar'])
                      : null,
                  backgroundColor: isBanned ? Colors.red.withOpacity(0.1) : AppColors.primary.withOpacity(0.1), // ✅ Yasaklanan kullanıcı için kırmızı avatar
                  child: participant['avatar'] == null
                      ? Icon(
                          Icons.person,
                          color: isBanned ? Colors.red : AppColors.primary, // ✅ Yasaklanan kullanıcı için kırmızı ikon
                        )
                      : null,
                ),
                title: Text(
                  participant['name'] ?? 'Kullanıcı',
                  style: TextStyle(
                    fontWeight: FontWeight.bold,
                    color: isBanned ? Colors.red : null, // ✅ Yasaklanan kullanıcı için kırmızı metin
                  ),
                ),
                subtitle: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text('${participant['media_count']} medya • ${participant['story_count']} hikaye'),
                    Text(
                      'Rol: ${_getRoleDisplayName(participant['role'])}',
                      style: TextStyle(
                        color: _getRoleColor(participant['role']),
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    if (isBanned) // ✅ Yasaklanan kullanıcı için durum göster
                      const Text(
                        'Durum: Yasaklı',
                        style: TextStyle(
                          color: Colors.red,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                  ],
                ),
                trailing: isCurrentUser
                    ? const Text(
                        'Siz',
                        style: TextStyle(
                          color: AppColors.primary,
                          fontWeight: FontWeight.bold,
                        ),
                      )
                    : isBanned
                        ? IconButton( // ✅ Yasaklanan kullanıcı için "Aktif Et" butonu
                            icon: const Icon(Icons.check_circle, color: Colors.green),
                            onPressed: () => _handleParticipantAction(participant, 'aktif'),
                            tooltip: 'Yasağı Kaldır',
                          )
                        : const Icon(Icons.touch_app, color: Colors.grey), // ✅ Normal kullanıcı için dokunma ikonu
              ),
            );
          },
        );
      },
    );
  }
  
  // ✅ Participant Action Modal
  void _showParticipantActionModal(Map<String, dynamic> participant, String? userRole) {
    final participantRole = participant['role'];
    final participantStatus = participant['status'];
    final currentUserPermissions = widget.event?.userPermissions as Map<String, dynamic>? ?? {};
    
    // Yetki kontrolü
    bool canManagePermissions = userRole == 'admin' || 
                               userRole == 'moderator' || 
                               currentUserPermissions['yetki_duzenleyebilir'] == true;
    
    bool canManageStatus = currentUserPermissions['kullanici_engelleyebilir'] == true;
    
    // Eğer yetki yoksa modal açma
    if (!canManagePermissions && !canManageStatus) {
      return;
    }
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: Text(participant['name'] ?? 'Katılımcı'),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Text('Rol: ${_getRoleDisplayName(participantRole)}'),
            Text('Durum: ${participantStatus == 'aktif' ? 'Aktif' : 'Yasaklı'}'),
            const SizedBox(height: 16),
            Text('Ne yapmak istiyorsunuz?'),
          ],
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.of(context).pop(),
            child: const Text('İptal'),
          ),
          if (canManagePermissions)
            TextButton(
              onPressed: () {
                Navigator.of(context).pop();
                _showPermissionGrantModal(participant);
              },
              child: const Text('Yetkileri Düzenle'),
            ),
          if (canManageStatus)
            TextButton(
              onPressed: () {
                Navigator.of(context).pop();
                _handleParticipantAction(participant, participantStatus == 'aktif' ? 'yasakla' : 'aktif');
              },
              style: TextButton.styleFrom(
                foregroundColor: participantStatus == 'aktif' ? Colors.red : Colors.green,
              ),
              child: Text(participantStatus == 'aktif' ? 'Kullanıcıyı Yasakla' : 'Kullanıcıyı Aktif Et'),
            ),
        ],
      ),
    );
  }
  
  // ✅ Permission Grant Modal'ı ayrı metod
  void _showPermissionGrantModal(Map<String, dynamic> participant) {
    showDialog(
      context: context,
      builder: (context) => PermissionGrantModal(
        participantName: participant['name'],
        participantId: participant['id'],
        eventId: widget.event!.id,
        onPermissionsGranted: (permissions) async {
          try {
            await _apiService.grantPermissions(
              eventId: widget.event!.id,
              targetUserId: participant['id'],
              permissions: permissions,
            );
            
            // ✅ Context'i modal kapatılmadan önce kaydet
            final scaffoldMessenger = ScaffoldMessenger.of(context);
            
            if (mounted) {
              scaffoldMessenger.showSnackBar(
                SnackBar(
                  content: Text('${participant['name']} için yetkiler güncellendi'),
                  backgroundColor: AppColors.success,
                ),
              );
            }
            
            // ✅ Real-time güncelleme için setState
            setState(() {
              // Force rebuild of participants tab
            });
          } catch (e) {
            // ✅ Context'i modal kapatılmadan önce kaydet
            final scaffoldMessenger = ScaffoldMessenger.of(context);
            
            if (mounted) {
              scaffoldMessenger.showSnackBar(
                SnackBar(
                  content: Text('Yetki güncelleme başarısız: $e'),
                  backgroundColor: AppColors.error,
                ),
              );
            }
          }
        },
      ),
    );
  }
  
  String _getRoleDisplayName(String role) {
    switch (role) {
      case 'admin':
        return 'Yönetici';
      case 'moderator':
        return 'Moderatör';
      case 'yetkili_kullanici':
        return 'Yetkili Katılımcı'; // ✅ Değiştirildi
      default:
        return 'Katılımcı';
    }
  }
  
  Color _getRoleColor(String role) {
    switch (role) {
      case 'admin':
        return Colors.red;
      case 'moderator':
        return Colors.orange;
      case 'yetkili_kullanici':
        return Colors.blue;
      default:
        return Colors.grey;
    }
  }
  
         List<PopupMenuEntry<String>> _buildParticipantMenuItems(Map<String, dynamic> participant, String? userRole) {
           final participantRole = participant['role'];
           final participantStatus = participant['status'];
           // ✅ Type casting'i düzelt - List formatını destekle
           final participantPermissions = participant['permissions'] is List 
               ? <String, dynamic>{} 
               : participant['permissions'] as Map<String, dynamic>? ?? {};
           
           // Debug log
           print('🔍 Debug - UserRole: $userRole, ParticipantRole: $participantRole, Status: $participantStatus');
           print('🔍 Debug - Event userPermissions: ${widget.event?.userPermissions}');
           print('🔍 Debug - Event userPermissions type: ${widget.event?.userPermissions.runtimeType}');
           
           List<PopupMenuEntry<String>> items = [];
    
    // ✅ Yeni mantık: Yetki kontrolü
    final currentUserPermissions = widget.event?.userPermissions as Map<String, dynamic>? ?? {};
    print('🔍 Debug - CurrentUserPermissions: $currentUserPermissions');
    print('🔍 Debug - HasYetkiDuzenleyebilir: ${currentUserPermissions['yetki_duzenleyebilir']}');
    print('🔍 Debug - HasKullaniciEngelleyebilir: ${currentUserPermissions['kullanici_engelleyebilir']}');
    
    // Yetki kontrolü: Admin, Moderator veya "Yetki Düzenleyebilir" yetkisi olanlar
    bool canManagePermissions = userRole == 'admin' || 
                               userRole == 'moderator' || 
                               currentUserPermissions['yetki_duzenleyebilir'] == true;
    
    // Durum kontrolü: Yasaklı kullanıcıları yönetme (sadece yetkilerini al)
    bool canManageStatus = currentUserPermissions['kullanici_engelleyebilir'] == true;
    
    if (canManagePermissions) {
      // Admin ve Moderator hariç herkesin yetkilerini düzenleyebilir
      if (participantRole != 'admin' && participantRole != 'moderator') {
        items.add(const PopupMenuItem(
          value: 'yetki_ver',
          child: Text('Yetki Düzenle'),
        ));
      }
    }
    
    // Durum değiştirme (sadece "Kullanıcı Engelleyebilir" yetkisi olanlar için)
    if (canManageStatus) {
      if (participantStatus == 'aktif') {
        items.add(const PopupMenuItem(
          value: 'yasakla',
          child: Text('Yasakla', style: TextStyle(color: Colors.red)),
        ));
      } else if (participantStatus == 'yasakli') {
        items.add(const PopupMenuItem(
          value: 'aktif',
          child: Text('Aktif Et'),
        ));
      }
    }
    
    return items;
  }
  
  // ✅ Kullanıcının yasaklanıp yasaklanmadığını periyodik kontrol et
  void _checkUserBanStatus() async {
    if (widget.event == null) return;
    
    try {
      final participants = await _apiService.getParticipants(widget.event!.id);
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

  // ✅ Real-time veri yenileme metodu
  Future<void> _refreshData() async {
    if (widget.event == null || _isLoading) return;
    
    try {
      print('🔄 Refreshing data...');
      
      // Medya sayısını kontrol et
      final mediaData = await _apiService.getMedia(widget.event!.id, page: 1, limit: _pageSize);
      final newMedia = (mediaData['media'] as List<dynamic>?)?.cast<Map<String, dynamic>>() ?? [];
      
      // Hikaye sayısını kontrol et
      final storiesData = await _apiService.getStories(widget.event!.id);
      
      // ✅ Her zaman medya detaylarını güncelle (beğeni/yorum sayıları için)
      bool hasChanges = false;
      
      // Medya sayısı değişmişse
      if (newMedia.length != _media.length) {
        hasChanges = true;
        print('📱 Media count changed: ${_media.length} → ${newMedia.length}');
      }
      
      // Hikaye sayısı değişmişse
      if (storiesData.length != _stories.length) {
        hasChanges = true;
        print('📱 Stories count changed: ${_stories.length} → ${storiesData.length}');
      }
      
      // Mevcut medyaların beğeni/yorum sayıları değişmişse
      for (int i = 0; i < newMedia.length && i < _media.length; i++) {
        final oldMedia = _media[i];
        final newMediaItem = newMedia[i];
        
        if (oldMedia['likes'] != newMediaItem['likes'] || 
            oldMedia['comments'] != newMediaItem['comments']) {
          hasChanges = true;
          print('📱 Media ${newMediaItem['id']} stats changed: likes ${oldMedia['likes']}→${newMediaItem['likes']}, comments ${oldMedia['comments']}→${newMediaItem['comments']}');
        }
      }
      
      if (hasChanges) {
        print('📱 Content changes detected! Updating UI...');
        setState(() {
          _media = newMedia;
          _stories = storiesData;
          _hasMoreMedia = (mediaData['pagination']?['has_more'] ?? false);
        });
        print('✅ UI updated. Media: ${_media.length}, Stories: ${_stories.length}');
      }
    } catch (e) {
      print('❌ Error refreshing data: $e');
      
      // ✅ Eğer "Bu etkinliğe katılımcı değilsiniz" hatası ise kullanıcıyı ana ekrana yönlendir
      if (e.toString().contains('Bu etkinliğe katılımcı değilsiniz') || 
          e.toString().contains('Bu etkinliğe katılımcı değilsiniz')) {
        print('🚫 Kullanıcı etkinlikten çıkarıldı (refresh), ana ekrana yönlendiriliyor...');
        if (mounted) {
          Navigator.of(context).pushNamedAndRemoveUntil(
            '/',
            (route) => false,
          );
          return;
        }
      }
    }
  }
  
  // ✅ Yasaklanan kullanıcının diğer cihazlardaki event sayfalarını kontrol et
  void _checkBannedUserAccess(int bannedUserId) async {
    try {
      // Yasaklanan kullanıcının bu event'teki durumunu kontrol et
      final participants = await _apiService.getParticipants(widget.event!.id);
      Map<String, dynamic>? bannedParticipant;
      try {
        bannedParticipant = participants.firstWhere(
          (p) => p['id'] == bannedUserId,
        );
      } catch (e) {
        bannedParticipant = null;
      }
      
      if (bannedParticipant != null && bannedParticipant['status'] == 'yasakli') {
        // Yasaklanan kullanıcı hala event'te aktifse, event sayfalarını yenile
        print('🚫 Yasaklanan kullanıcı ${bannedParticipant['name']} event sayfalarından atılmalı');
        
        // Bu metod sadece bilgilendirme amaçlı
        // Gerçek kontrol _loadEventData() metodunda yapılıyor
      }
    } catch (e) {
      print('❌ Yasaklanan kullanıcı kontrolü hatası: $e');
    }
  }
  
  void _handleParticipantAction(Map<String, dynamic> participant, String action) async {
    try {
      if (action == 'yasakla') {
        await _apiService.updateParticipant(
          eventId: widget.event!.id,
          targetUserId: participant['id'],
          newStatus: 'yasakli',
        );
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('${participant['name']} yasaklandı'),
              backgroundColor: AppColors.success,
            ),
          );
          
          // ✅ Real-time güncelleme için participants listesini yeniden yükle
          setState(() {
            // Force rebuild of participants tab
          });
          
          // ✅ Event data'yı da yeniden yükle
          await _loadEventData();
          
          // ✅ Yasaklanan kullanıcının diğer cihazlardaki event sayfalarını kontrol et
          _checkBannedUserAccess(participant['id']);
        }
      } else if (action == 'aktif') {
        await _apiService.updateParticipant(
          eventId: widget.event!.id,
          targetUserId: participant['id'],
          newStatus: 'aktif',
        );
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('${participant['name']} aktif edildi'),
              backgroundColor: AppColors.success,
            ),
          );
          
          // ✅ Real-time güncelleme için participants listesini yeniden yükle
          setState(() {
            // Force rebuild of participants tab
          });
          
          // ✅ Event data'yı da yeniden yükle
          await _loadEventData();
        }
      } else if (action == 'yetkili_kullanici' || action == 'kullanici') {
        await _apiService.updateParticipant(
          eventId: widget.event!.id,
          targetUserId: participant['id'],
          newRole: action,
        );
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text('${participant['name']} rolü güncellendi'),
              backgroundColor: AppColors.success,
            ),
          );
        }
      }
      
      // Refresh the participants list
      setState(() {});
      
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('İşlem başarısız: $e'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }
}