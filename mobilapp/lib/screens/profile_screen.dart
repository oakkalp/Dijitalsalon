import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:flutter/foundation.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:digimobil_new/providers/event_provider.dart';
import 'package:digimobil_new/providers/theme_provider.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/models/user.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/widgets/story_viewer_modal.dart';
import 'package:digimobil_new/widgets/media_viewer_modal.dart';
import 'package:digimobil_new/widgets/shimmer_loading.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/utils/image_cache_config.dart';
import 'package:provider/provider.dart';
import 'package:image_picker/image_picker.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'dart:io';
import 'user_profile_screen.dart';
import 'qr_code_scanner_screen.dart';
import 'user_search_screen.dart';
import 'notifications_screen.dart';
import 'join_event_screen.dart';
import 'instagram_home_screen.dart';
import 'package:digimobil_new/utils/theme_colors.dart';

class ProfileScreen extends StatefulWidget {
  final int? targetUserId;
  final String? targetUserName;
  
  const ProfileScreen({
    super.key,
    this.targetUserId,
    this.targetUserName,
  });

  @override
  State<ProfileScreen> createState() => _ProfileScreenState();
}

class _ProfileScreenState extends State<ProfileScreen> {
  final ApiService _apiService = ApiService();
  final ImagePicker _picker = ImagePicker();
  bool _isLoading = false;
  File? _selectedImage;
  String? _currentProfileImage;
  User? _targetUser; // Başka kullanıcının bilgileri için
  bool _isLoadingTargetUser = false;
  List<Event> _targetUserEvents = []; // Hedef kullanıcının etkinlikleri
  bool _isLoadingTargetUserEvents = false;
  
  // ✅ Lazy loading için state
  List<Map<String, dynamic>> _displayedMedia = []; // Gösterilen medyalar
  List<Map<String, dynamic>> _allUserMedia = []; // Tüm kullanıcı medyaları
  int _mediaPage = 1;
  static const int _initialMediaLimit = 13; // ✅ İlk yüklemede 12+1 = 13 medya göster
  static const int _loadMoreLimit = 5; // ✅ Scroll ile 5'er 5'er yükle
  bool _isLoadingMoreMedia = false;
  bool _hasMoreMedia = true;
  final ScrollController _mediaScrollController = ScrollController();
  
  // ✅ Profil verilerinin yüklenme durumu (tüm veriler aynı anda gelsin diye)
  bool _isLoadingProfileData = true; // ✅ Tüm veriler yüklenene kadar true
  int _cachedEventCount = 0;
  int _cachedMediaCount = 0;
  int _cachedStoryCount = 0;

  @override
  void initState() {
    super.initState();
    _loadUserData();
    // Eğer başka kullanıcının profilini görüntülüyorsak, o kullanıcının bilgilerini al
    if (widget.targetUserId != null) {
      _loadTargetUserData();
    }
    // ✅ TÜM PROFİL VERİLERİNİ AYNI ANDA YÜKLE (kesik kesik gelmesin diye)
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      await _loadAllProfileData();
    });
    
    // ✅ Scroll listener ekle (lazy loading için)
    _mediaScrollController.addListener(_onMediaScroll);
  }
  
  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    // ✅ Sayfa görünür olduğunda stories'leri refresh et (hikaye paylaşıldıktan sonra görünsün)
    WidgetsBinding.instance.addPostFrameCallback((_) async {
      final eventProvider = Provider.of<EventProvider>(context, listen: false);
      if (eventProvider.events.isNotEmpty) {
        // Kullanıcının katıldığı etkinliklerdeki stories'leri refresh et
        final userEvents = eventProvider.events.where((Event event) => event.participantCount > 0).toList();
        for (Event event in userEvents) {
          // Stories'leri yeniden yükle (cache'i bypass et)
          await eventProvider.loadEventStories(event.id);
        }
      }
    });
  }
  
  @override
  void dispose() {
    _mediaScrollController.dispose();
    super.dispose();
  }
  
  // ✅ Scroll listener (lazy loading için)
  void _onMediaScroll() {
    if (!_mediaScrollController.hasClients) return;
    
    final maxScroll = _mediaScrollController.position.maxScrollExtent;
    final currentScroll = _mediaScrollController.position.pixels;
    final remainingScroll = maxScroll - currentScroll;
    final scrollPercentage = maxScroll > 0 ? (currentScroll / maxScroll) * 100 : 0;
    
    // ✅ 400px kala veya %80 scroll yapıldıysa yükle
    if (maxScroll > 0 && (remainingScroll <= 400 || scrollPercentage >= 80)) {
      if (!_isLoadingMoreMedia && _hasMoreMedia) {
        if (kDebugMode) {
          debugPrint('📜 ScrollController - Loading more media (scroll: ${scrollPercentage.toStringAsFixed(1)}%, remaining: ${remainingScroll.toStringAsFixed(0)}px)');
        }
        _loadMoreMedia();
      }
    }
  }
  
  // ✅ INSTAGRAM STYLE: Tek endpoint ile tüm verileri al (1-2 saniye!)
  Future<void> _loadAllProfileData() async {
    if (!mounted) return;
    
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final currentUser = authProvider.user;
    final isViewingOtherProfile = widget.targetUserId != null && widget.targetUserId != currentUser?.id;
    
    // ✅ targetUserId'yi direkt widget'tan al (async yükleme tamamlanmadan önce de kullanılabilir)
    int? targetUserId;
    if (isViewingOtherProfile) {
      targetUserId = widget.targetUserId; // ✅ widget.targetUserId direkt kullan
    } else if (currentUser != null) {
      targetUserId = currentUser.id;
    } else {
      return;
    }
    
    try {
      // ✅ TEK API ÇAĞRISI İLE TÜM VERİLER (Instagram gibi!)
      final profileData = await _apiService.getProfileStats(
        userId: isViewingOtherProfile ? targetUserId : null
      );
      
      if (!mounted) return;
      
      final stats = profileData['stats'] as Map<String, dynamic>;
      final initialMedia = List<Map<String, dynamic>>.from(profileData['initial_media'] ?? []);
      
      // ✅ Medyaları tarih sırasına göre sırala
      initialMedia.sort((a, b) {
        final dateA = a['created_at'] ?? '';
        final dateB = b['created_at'] ?? '';
        return dateB.compareTo(dateA);
      });
      
      // ✅ TEK SETSTATE İLE TÜM VERİLERİ GÖSTER (anında!)
      if (mounted) {
        setState(() {
          // ✅ Sayıları set et
          _cachedEventCount = stats['event_count'] ?? 0;
          _cachedMediaCount = stats['media_count'] ?? 0;
          _cachedStoryCount = stats['story_count'] ?? 0;
          
          // ✅ Medyaları set et
          _allUserMedia = initialMedia;
          _displayedMedia = initialMedia.take(_initialMediaLimit).toList();
          _hasMoreMedia = initialMedia.length > _initialMediaLimit;
          
          // ✅ Yükleme tamamlandı
          _isLoadingProfileData = false;
        });
      }
      
      // ✅ Background'da eventProvider'ı güncelle (cache için)
      final eventProvider = Provider.of<EventProvider>(context, listen: false);
      
      // ✅ Stories'leri yükle (profil sayfasında hikaye barı için)
      if (eventProvider.events.isNotEmpty) {
        // Kullanıcının katıldığı etkinliklerdeki stories'leri yükle
        final userEvents = eventProvider.events.where((Event event) => event.participantCount > 0).toList();
        for (Event event in userEvents) {
          if (!eventProvider.eventStories.containsKey(event.id)) {
            // Stories henüz yüklenmemişse yükle
            await eventProvider.loadEventStories(event.id);
          }
        }
        // ✅ Notify listeners yap ki UI güncellensin
        eventProvider.notifyListeners();
      }
      
      if (initialMedia.isNotEmpty) {
        // ✅ Event media cache'ini güncelle
        for (var media in initialMedia) {
          final eventId = media['event_id'] as int;
          if (!eventProvider.eventMedia.containsKey(eventId)) {
            eventProvider.eventMedia[eventId] = [];
          }
          // ✅ Eğer medya cache'de yoksa ekle
          final existingMedia = eventProvider.eventMedia[eventId]!.firstWhere(
            (m) => m['id'] == media['id'],
            orElse: () => {},
          );
          if (existingMedia.isEmpty) {
            eventProvider.eventMedia[eventId]!.add(media);
          }
        }
      }
      
    } catch (e) {
      if (kDebugMode) {
        debugPrint('❌ getProfileStats error: $e');
      }
      
      // ✅ Hata durumunda fallback: Eski yöntem (cache'den)
      final eventProvider = Provider.of<EventProvider>(context, listen: false);
      final cachedMedia = eventProvider.getUserMedia(targetUserId!);
      if (cachedMedia.isNotEmpty && mounted) {
        setState(() {
          _allUserMedia = cachedMedia;
          _displayedMedia = cachedMedia.take(_initialMediaLimit).toList();
          _cachedMediaCount = cachedMedia.length;
          _cachedEventCount = eventProvider.events.where((e) => e.participantCount > 0).length;
          _isLoadingProfileData = false;
        });
      } else if (mounted) {
        setState(() {
          _isLoadingProfileData = false;
        });
      }
    }
  }
  
  // ✅ Profil sayılarını güncelle (cache güncellemesi)
  void _updateProfileCounts(EventProvider eventProvider, int targetUserId, bool isViewingOtherProfile) {
    final allUserMedia = eventProvider.getUserMedia(targetUserId);
    allUserMedia.sort((a, b) {
      final dateA = a['created_at'] ?? a['olusturma_tarihi'] ?? '';
      final dateB = b['created_at'] ?? b['olusturma_tarihi'] ?? '';
      return dateB.compareTo(dateA);
    });
    
    if (mounted) {
      setState(() {
        _allUserMedia = allUserMedia;
        if (_displayedMedia.length < _initialMediaLimit && allUserMedia.length >= _initialMediaLimit) {
          _displayedMedia = allUserMedia.take(_initialMediaLimit).toList();
        }
        _hasMoreMedia = allUserMedia.length > _displayedMedia.length;
        
        // ✅ Sayıları güncelle
        _cachedEventCount = isViewingOtherProfile 
            ? _targetUserEvents.length 
            : eventProvider.events.where((Event event) => event.participantCount > 0).length;
        _cachedMediaCount = allUserMedia.length;
        
        final eventsToCheck = isViewingOtherProfile 
            ? _targetUserEvents 
            : eventProvider.events.where((Event event) => event.participantCount > 0).toList();
        _cachedStoryCount = 0;
        for (Event event in eventsToCheck) {
          final eventStories = eventProvider.eventStories[event.id] ?? [];
          if (eventStories.isNotEmpty) {
            _cachedStoryCount += eventStories.where((story) => story['user_id'] == targetUserId).length;
          }
        }
      });
    }
  }
  
  // ✅ Kullanıcı medyalarını lazy load et (optimize edilmiş - HIZLI başlangıç)
  Future<void> _loadUserMediaLazy(EventProvider eventProvider) async {
    if (!mounted) return;
    
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    final currentUser = authProvider.user;
    final isViewingOtherProfile = widget.targetUserId != null && widget.targetUserId != currentUser?.id;
    
    // ✅ targetUserId'yi direkt widget'tan al (async yükleme tamamlanmadan önce de kullanılabilir)
    int? targetUserId;
    if (isViewingOtherProfile) {
      targetUserId = widget.targetUserId; // ✅ widget.targetUserId direkt kullan
    } else if (currentUser != null) {
      targetUserId = currentUser.id;
    } else {
      return;
    }
    
    if (targetUserId == null) return; // ✅ Null check
    
    // ✅ ÖNCE mevcut event media'lardan kullanıcının medyalarını al (anında göster)
    _allUserMedia = eventProvider.getUserMedia(targetUserId);
    
    // ✅ Medyaları tarih sırasına göre sırala (en yeni önce)
    _allUserMedia.sort((a, b) {
      final dateA = a['created_at'] ?? a['olusturma_tarihi'] ?? '';
      final dateB = b['created_at'] ?? b['olusturma_tarihi'] ?? '';
      return dateB.compareTo(dateA); // Descending (yeni önce)
    });
    
    // ✅ HEMEN ilk 13 medyayı göster (12+1) - UI bloklanmasın
    if (mounted) {
      setState(() {
        _displayedMedia = _allUserMedia.take(_initialMediaLimit).toList();
        _hasMoreMedia = _allUserMedia.length > _initialMediaLimit;
        _isLoadingMoreMedia = false;
      });
    }
    
    // ✅ Eğer medya az ise veya event media yüklenmemişse, background'da yükle
    if (eventProvider.events.isNotEmpty) {
      final userEvents = eventProvider.events.where((e) => e.participantCount > 0).toList();
      
      // ✅ Sadece event media yüklenmemiş event'ler için yükle
      final eventsToLoad = userEvents.where((event) => 
        !eventProvider.eventMedia.containsKey(event.id) || 
        (eventProvider.eventMedia[event.id]?.isEmpty ?? true)
      ).toList();
      
      if (eventsToLoad.isNotEmpty) {
        // ✅ Background'da paralel yükleme (limit düşürüldü - sadece profil için yeterli)
        Future.wait(
          eventsToLoad.map((event) => eventProvider.loadEventMediaOptimized(event.id)),
        ).then((_) {
          if (mounted && targetUserId != null) {
            // ✅ Yeni yüklenen medyaları ekle
            final updatedMedia = eventProvider.getUserMedia(targetUserId!);
            updatedMedia.sort((a, b) {
              final dateA = a['created_at'] ?? a['olusturma_tarihi'] ?? '';
              final dateB = b['created_at'] ?? b['olusturma_tarihi'] ?? '';
              return dateB.compareTo(dateA);
            });
            
            setState(() {
              _allUserMedia = updatedMedia;
              // ✅ Eğer henüz 13'ten az gösteriliyorsa, güncelle
              if (_displayedMedia.length < _initialMediaLimit && updatedMedia.length >= _initialMediaLimit) {
                _displayedMedia = updatedMedia.take(_initialMediaLimit).toList();
              }
              _hasMoreMedia = updatedMedia.length > _displayedMedia.length;
            });
          }
        });
      }
    }
  }
  
  // ✅ Daha fazla medya yükle (scroll ile)
  Future<void> _loadMoreMedia() async {
    if (_isLoadingMoreMedia || !_hasMoreMedia || !mounted) return;
    
    if (kDebugMode) {
      debugPrint('🔄 Loading more media: displayed=${_displayedMedia.length}, total=${_allUserMedia.length}');
    }
    
    setState(() {
      _isLoadingMoreMedia = true;
    });
    
    // ✅ Kısa bir gecikme (smooth UX için)
    await Future.delayed(const Duration(milliseconds: 200));
    
    if (!mounted) return;
    
    final startIndex = _displayedMedia.length;
    final endIndex = (startIndex + _loadMoreLimit).clamp(0, _allUserMedia.length);
    
    if (startIndex < _allUserMedia.length) {
      final newMedia = _allUserMedia.skip(startIndex).take(_loadMoreLimit).toList();
      
      setState(() {
        _displayedMedia.addAll(newMedia);
        _mediaPage++;
        _hasMoreMedia = endIndex < _allUserMedia.length;
        _isLoadingMoreMedia = false;
      });
      
      if (kDebugMode) {
        debugPrint('✅ Loaded more media: ${newMedia.length} items, total displayed: ${_displayedMedia.length}');
      }
    } else {
      setState(() {
        _hasMoreMedia = false;
        _isLoadingMoreMedia = false;
      });
    }
  }

  void _loadUserData() {
    final user = Provider.of<AuthProvider>(context, listen: false).user;
    if (user != null) {
      _currentProfileImage = user.profileImage;
    }
  }

  Future<void> _loadTargetUserData() async {
    if (widget.targetUserId == null) return;
    
    setState(() {
      _isLoadingTargetUser = true;
      _isLoadingTargetUserEvents = true;
    });

    try {
      // API'den hedef kullanıcının bilgilerini al
      final userData = await _apiService.getUserById(widget.targetUserId!);
      
      // API'den hedef kullanıcının etkinliklerini al
      final userEvents = await _apiService.getUserEvents(widget.targetUserId!);
      
      setState(() {
        _targetUser = userData;
        _targetUserEvents = userEvents;
        _isLoadingTargetUser = false;
        _isLoadingTargetUserEvents = false;
      });
    } catch (e) {
      print('Hedef kullanıcı bilgileri alınamadı: $e');
      setState(() {
        _isLoadingTargetUser = false;
        _isLoadingTargetUserEvents = false;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    final authProvider = Provider.of<AuthProvider>(context);
    final currentUser = authProvider.user;
    final isViewingOtherProfile = widget.targetUserId != null && widget.targetUserId != currentUser?.id;
    
    // Eğer başka kullanıcının profilini görüntülüyorsak, o kullanıcının bilgilerini al
    User? displayUser;
    if (isViewingOtherProfile) {
      if (_isLoadingTargetUser) {
        return Scaffold(
          backgroundColor: Theme.of(context).scaffoldBackgroundColor,
          body: const ProfileShimmer(),
        );
      }
      displayUser = _targetUser;
    } else {
      displayUser = currentUser;
    }
    
    if (displayUser == null) {
      return const Center(
        child: Text('Kullanıcı bilgisi bulunamadı'),
      );
    }

    final eventProvider = Provider.of<EventProvider>(context);

    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      body: DefaultTabController(
        length: 2,
        child: NestedScrollView(
          headerSliverBuilder: (context, index) {
            return [
              _buildAppBar(displayUser, isViewingOtherProfile),
              _buildProfileInformation(displayUser, eventProvider, isViewingOtherProfile),
            ];
          },
          body: _buildPublications(eventProvider),
        ),
      ),
      bottomNavigationBar: _buildBottomNavigationBar(),
    );
  }

  Widget _buildAppBar(user, bool isViewingOtherProfile) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? Colors.white : Colors.black;
    
    return SliverAppBar(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      elevation: 0,
      pinned: true,
      systemOverlayStyle: isDark ? SystemUiOverlayStyle.light : SystemUiOverlayStyle.dark,
      centerTitle: false,
      leading: IconButton(
        onPressed: () => Navigator.pop(context),
        icon: Icon(Icons.arrow_back, color: textColor),
      ),
      title: Row(
        mainAxisAlignment: MainAxisAlignment.start,
        mainAxisSize: MainAxisSize.min,
        children: [
          Icon(Icons.lock_outline, color: textColor, size: 20),
          const SizedBox(width: 5),
          Flexible(
            child: Text(
              user.username ?? user.name,
              style: TextStyle(
                color: textColor,
                fontSize: 20,
                fontWeight: FontWeight.w500,
              ),
              overflow: TextOverflow.ellipsis,
              maxLines: 1,
            ),
          ),
        ],
      ),
      actions: isViewingOtherProfile ? [] : [
        // ✅ User Search Button
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
        // ✅ QR Scanner Button
        IconButton(
          icon: Icon(
            Icons.qr_code_scanner,
            color: Theme.of(context).brightness == Brightness.dark ? Colors.white : Colors.black,
          ),
          onPressed: () async {
            final result = await Navigator.push(
              context,
              MaterialPageRoute(
                builder: (context) => const QRCodeScannerScreen(),
              ),
            );
            // QR scanner'dan döndüğünde başarılı katılım varsa profil verilerini yenile
            if (result == true && mounted) {
              _loadAllProfileData();
            }
          },
        ),
        // ✅ Notification button with badge
        Consumer<AuthProvider>(
          builder: (context, authProvider, _) {
            // TODO: Unread notification count eklenebilir
            return IconButton(
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
              },
            );
          },
        ),
        // ✅ Dark Mode Toggle
        Consumer<ThemeProvider>(
          builder: (context, themeProvider, _) {
            final isDark = themeProvider.isDarkMode;
            return IconButton(
              onPressed: () {
                themeProvider.toggleTheme();
              },
              icon: Icon(
                isDark ? Icons.light_mode : Icons.dark_mode,
                color: Theme.of(context).brightness == Brightness.dark
                    ? Colors.white
                    : Colors.black,
              ),
              tooltip: isDark ? 'Açık Tema' : 'Koyu Tema',
            );
          },
        ),
        IconButton(
          onPressed: () {
            _showLogoutDialog(context);
          },
          icon: const Icon(Icons.logout, color: Colors.red),
        ),
      ],
    );
  }

  Widget _buildBottomNavigationBar() {
    final authProvider = Provider.of<AuthProvider>(context);
    final currentUser = authProvider.user;
    final isViewingOtherProfile = widget.targetUserId != null && widget.targetUserId != currentUser?.id;
    
    // ✅ Başka kullanıcının profilinde bottom navigation bar gösterme
    if (isViewingOtherProfile) {
      return const SizedBox.shrink();
    }
    
    return BottomNavigationBar(
      iconSize: 30,
      elevation: 0,
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      type: BottomNavigationBarType.fixed,
      selectedItemColor: ThemeColors.primary(context),
      unselectedItemColor: Theme.of(context).brightness == Brightness.dark 
          ? Colors.grey[400] 
          : Colors.grey[600],
      currentIndex: 3, // ✅ Profil sayfası aktif
      onTap: (index) {
        if (index == 0) {
          // Ana sayfa
          Navigator.pushReplacementNamed(context, '/home');
        } else if (index == 1) {
          // Etkinliğe katıl
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => JoinEventScreen(
                onEventJoined: () {
                  // Event joined callback
                },
              ),
            ),
          );
        } else if (index == 2) {
          // Arama
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => const UserSearchScreen(),
            ),
          );
        } else if (index == 3) {
          // Profil (zaten buradayız, bir şey yapma)
        }
      },
      items: <BottomNavigationBarItem>[
        BottomNavigationBarItem(
          icon: Icon(
            Icons.home,
            color: Theme.of(context).brightness == Brightness.dark ? Colors.grey[400] : Colors.grey[600],
          ),
          label: '',
        ),
        BottomNavigationBarItem(
          icon: Icon(
            Icons.add_box_outlined,
            color: Theme.of(context).brightness == Brightness.dark ? Colors.grey[400] : Colors.grey[600],
          ),
          label: '',
        ),
        BottomNavigationBarItem(
          icon: Icon(
            Icons.search,
            color: Theme.of(context).brightness == Brightness.dark ? Colors.grey[400] : Colors.grey[600],
          ),
          label: '',
        ),
        BottomNavigationBarItem(
          icon: CircleAvatar(
            backgroundImage: currentUser?.profileImage != null
                ? NetworkImage(currentUser!.profileImage!)
                : null,
            radius: 15,
            backgroundColor: Theme.of(context).brightness == Brightness.dark 
                ? Colors.grey[700] 
                : Colors.grey.shade300,
            child: currentUser?.profileImage == null
                ? Icon(
                    Icons.person,
                    size: 20,
                    color: ThemeColors.primary(context), // ✅ Aktif profil ikonu
                  )
                : null,
          ),
          label: '',
        ),
      ],
    );
  }

  Widget _buildProfileInformation(user, eventProvider, bool isViewingOtherProfile) {
    // ✅ Loading durumunda shimmer göster
    if (_isLoadingProfileData) {
      return SliverToBoxAdapter(
        child: Padding(
          padding: const EdgeInsets.all(16),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Profile picture shimmer
              Row(
                children: [
                  ShimmerLoading(
                    width: 80,
                    height: 80,
                    borderRadius: BorderRadius.circular(40),
                  ),
                  const SizedBox(width: 20),
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        ShimmerLoading(width: 120, height: 16),
                        const SizedBox(height: 8),
                        ShimmerLoading(width: 80, height: 14),
                      ],
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 16),
              // Stats shimmer
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  Column(
                    children: [
                      ShimmerLoading(width: 40, height: 20),
                      const SizedBox(height: 4),
                      ShimmerLoading(width: 60, height: 14),
                    ],
                  ),
                  Column(
                    children: [
                      ShimmerLoading(width: 40, height: 20),
                      const SizedBox(height: 4),
                      ShimmerLoading(width: 60, height: 14),
                    ],
                  ),
                  Column(
                    children: [
                      ShimmerLoading(width: 40, height: 20),
                      const SizedBox(height: 4),
                      ShimmerLoading(width: 60, height: 14),
                    ],
                  ),
                ],
              ),
              const SizedBox(height: 16),
              // Edit Profile button shimmer
              ShimmerLoading(
                width: double.infinity,
                height: 40,
                borderRadius: BorderRadius.circular(8),
              ),
            ],
          ),
        ),
      );
    }
    
    return SliverToBoxAdapter(
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildProfileLabelCount(user, eventProvider),
          _buildBio(user),
                 const SizedBox(height: 12),
                 _buildEditProfile(isViewingOtherProfile),
                 const SizedBox(height: 15),
                 // ✅ Hikaye barını koşullu göster
                 Builder(
                   builder: (context) {
                     final authProvider = Provider.of<AuthProvider>(context);
                     final currentUser = authProvider.user;
                     final isViewingOtherProfile = widget.targetUserId != null && widget.targetUserId != currentUser?.id;
                     
                     // ✅ targetUserId'yi direkt widget'tan al
                     int? targetUserId;
                     if (isViewingOtherProfile) {
                       targetUserId = widget.targetUserId; // ✅ widget.targetUserId direkt kullan
                     } else if (currentUser != null) {
                       targetUserId = currentUser.id;
                     } else {
                       return const SizedBox.shrink();
                     }
                     if (targetUserId == null) return const SizedBox.shrink();
                     
                     // ✅ Kullanıcının hikayesi var mı kontrol et
                     bool hasStories = false;
                     final eventsToCheck = isViewingOtherProfile 
                         ? _targetUserEvents 
                         : eventProvider.events.where((Event event) => event.participantCount > 0).toList();
                     
                     for (Event event in eventsToCheck) {
                       final eventStories = eventProvider.eventStories[event.id] ?? [];
                       if (eventStories.isNotEmpty) {
                         final userStoriesInEvent = eventStories.where((story) => story['user_id'] == targetUserId).toList();
                         if (userStoriesInEvent.isNotEmpty) {
                           hasStories = true;
                           break;
                         }
                       }
                     }
                     
                     // ✅ Hikaye varsa göster, yoksa gizle
                     return hasStories ? _buildHighlights(eventProvider) : const SizedBox.shrink();
                   },
                 ),
          _buildTabBar(),
        ],
      ),
    );
  }

  Widget _buildTabBar() {
    return const TabBar(
      indicatorWeight: 1,
      indicatorColor: Colors.black,
      labelColor: Colors.black,
      unselectedLabelColor: Colors.grey,
      tabs: [
        Tab(icon: Icon(Icons.grid_on, color: Colors.black)),
        Tab(icon: Icon(Icons.event_note, color: Colors.black)),
      ],
    );
  }

  Widget _buildHighlights(eventProvider) {
    final authProvider = Provider.of<AuthProvider>(context);
    final currentUser = authProvider.user;
    final isViewingOtherProfile = widget.targetUserId != null && widget.targetUserId != currentUser?.id;
    
    // Hangi kullanıcının hikayelerini göstereceğimizi belirle
    // ✅ targetUserId'yi direkt widget'tan al
    int? targetUserId;
    if (isViewingOtherProfile) {
      targetUserId = widget.targetUserId; // ✅ widget.targetUserId direkt kullan
    } else if (currentUser != null) {
      targetUserId = currentUser.id;
    } else {
      return const SizedBox.shrink();
    }
    if (targetUserId == null) return const SizedBox.shrink();
    
    // Tüm etkinlikleri göster (hikayesi olsun ya da olmasın)
    List<Event> eventsWithStories = [];
    
    if (isViewingOtherProfile) {
      // Hedef kullanıcının etkinliklerindeki hikayelerini kontrol et
      for (Event event in _targetUserEvents) {
        final eventStories = eventProvider.eventStories[event.id] ?? [];
        // Bu kullanıcının hikayelerini kontrol et
        final userStoriesInEvent = eventStories.where((story) => story['user_id'] == targetUserId).toList();
        
        // Hikayesi olan etkinlikleri ekle
        if (userStoriesInEvent.isNotEmpty) {
          eventsWithStories.add(event);
          print('🔍 ProfileScreen - Added event with stories: ${event.title} (${userStoriesInEvent.length} stories)');
        }
      }
    } else {
      // Kendi etkinliklerimizdeki hikayelerimizi kontrol et
      for (Event event in eventProvider.events) {
        final eventStories = eventProvider.eventStories[event.id] ?? [];
        // Bu kullanıcının hikayelerini kontrol et
        final userStoriesInEvent = eventStories.where((story) => story['user_id'] == targetUserId).toList();
        
        // Hikayesi olan etkinlikleri ekle
        if (userStoriesInEvent.isNotEmpty) {
          eventsWithStories.add(event);
          print('🔍 ProfileScreen - Added event with stories: ${event.title} (${userStoriesInEvent.length} stories)');
        }
      }
    }
    
    print('🔍 ProfileScreen - Total events with stories: ${eventsWithStories.length}');
    
    
    return SizedBox(
      height: 100,
      child: ListView.builder(
          itemCount: eventsWithStories.length,
          scrollDirection: Axis.horizontal,
          itemBuilder: (_, index) {
            final event = eventsWithStories[index];
            final eventStories = eventProvider.eventStories[event.id] ?? [];
            final userStoriesInEvent = eventStories.where((story) => story['user_id'] == targetUserId).toList();
            
            return GestureDetector(
              onTap: () async {
                if (userStoriesInEvent.isNotEmpty) {
                  try {
                    // ✅ Kullanıcının bu etkinlikteki TÜM hikayelerini çek
                    if (targetUserId == null) return;
                    final userStories = await _apiService.getUserStories(event.id, targetUserId);
                    if (userStories.isNotEmpty && mounted) {
                      Navigator.push(
                        context,
                        MaterialPageRoute(
                          builder: (context) => StoryViewerModal(
                            stories: userStories,
                            initialIndex: 0,
                            event: event,
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
                } else {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text('${event?.title ?? 'Bilinmeyen'} etkinliğinde henüz hikaye yok'),
                      backgroundColor: AppColors.info,
                    ),
                  );
                }
              },
              child: Container(
                width: 80,
                alignment: Alignment.topCenter,
                padding: const EdgeInsets.symmetric(horizontal: 5),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Container(
                      width: 60,
                      height: 60,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(color: Colors.grey.shade400, width: 2),
                      ),
                      child: ClipOval(
                        child: _getEventCoverImage(event),
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      event?.title != null && event!.title.length > 12 
                          ? '${event.title.substring(0, 12)}...'
                          : event?.title ?? 'Etkinlik',
                      style: TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w500,
                        color: Colors.black,
                      ),
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                  ],
                ),
              ),
            );
          }),
    );
  }

  // ✅ Medya thumbnail widget'ı - hızlı yükleme için thumbnail kullan
  Widget _buildMediaThumbnail(Map<String, dynamic> media) {
    final mediaType = media['tur'] ?? media['type'] ?? '';
    final isVideo = mediaType == 'video';
    
    // ✅ Önce kucuk_resim_yolu (thumbnail), yoksa dosya_yolu (orijinal)
    // Backend'den gelen kolon adlarına göre
    String? imageUrl = media['kucuk_resim_yolu'] ?? media['thumbnail'] ?? media['dosya_yolu'] ?? media['url'];
    
    // ✅ URL tam yol olarak ayarla
    if (imageUrl != null && !imageUrl.startsWith('http')) {
      imageUrl = 'https://dijitalsalon.cagapps.app/${imageUrl.startsWith('/') ? imageUrl.substring(1) : imageUrl}';
    }
    
    if (imageUrl == null || imageUrl.isEmpty) {
      return Container(
        color: Colors.grey.shade200,
        child: const Icon(
          Icons.image,
          color: Colors.grey,
          size: 40,
        ),
      );
    }
    
    return Stack(
      fit: StackFit.expand,
      children: [
        // ✅ Thumbnail resim - CachedNetworkImage ile optimized cache
        CachedNetworkImage(
          imageUrl: imageUrl,
          fit: BoxFit.cover,
          cacheManager: ImageCacheConfig.getCacheManager(imageUrl),
          memCacheWidth: ImageCacheConfig.getMemCacheWidth(imageUrl, maxWidth: 300),
          memCacheHeight: ImageCacheConfig.getMemCacheHeight(imageUrl, maxHeight: 300),
          // ❌ maxWidthDiskCache ve maxHeightDiskCache kaldırıldı (ImageCacheManager gerektirir)
          httpHeaders: {
            'Connection': 'keep-alive',
            'Cache-Control': 'max-age=2592000', // 30 days for thumbnails
          },
          placeholder: (context, url) => Container(
            color: Colors.grey.shade200,
            child: const Center(
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          ),
          errorWidget: (context, url, error) => Container(
            color: Colors.grey.shade200,
            child: const Icon(
              Icons.image,
              color: Colors.grey,
              size: 40,
            ),
          ),
        ),
        // ✅ Video için play ikonu
        if (isVideo)
          Container(
            color: Colors.black.withOpacity(0.3),
            child: const Center(
              child: Icon(
                Icons.play_circle_filled,
                color: Colors.white,
                size: 30,
              ),
            ),
          ),
      ],
    );
  }

  Widget _buildEditProfile(bool isViewingOtherProfile) {
    if (isViewingOtherProfile) {
      return const SizedBox.shrink(); // Başka kullanıcının profilinde düzenle butonu gösterme
    }
    
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Expanded(
            child: GestureDetector(
              onTap: () {
                Navigator.push(
                  context,
                  MaterialPageRoute(
                    builder: (context) => const UserProfileScreen(),
                  ),
                );
              },
              child: Container(
                alignment: Alignment.center,
                height: 35,
                decoration: BoxDecoration(
                  color: const Color(0xFFEEEEEE),
                  borderRadius: BorderRadius.circular(5),
                ),
                child: const Text(
                  'Profili Düzenle',
                  style: TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w500,
                    color: Colors.black,
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: 5),
          Container(
              alignment: Alignment.center,
              width: 50,
              height: 35,
              decoration: BoxDecoration(
                  color: const Color(0xFFEEEEEE),
                  borderRadius: BorderRadius.circular(5)),
              child: const Icon(Icons.person_add, size: 18)),
        ],
      ),
    );
  }

  Widget _buildBio(user) {
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      user.name,
            style: const TextStyle(fontWeight: FontWeight.w600),
          ),
        ],
      ),
    );
  }

  Widget _buildProfileLabelCount(user, eventProvider) {
    final authProvider = Provider.of<AuthProvider>(context);
    final currentUser = authProvider.user;
    final isViewingOtherProfile = widget.targetUserId != null && widget.targetUserId != currentUser?.id;
    
    // Hangi kullanıcının verilerini göstereceğimizi belirle
    // ✅ targetUserId'yi direkt widget'tan al
    int? targetUserId;
    if (isViewingOtherProfile) {
      targetUserId = widget.targetUserId; // ✅ widget.targetUserId direkt kullan
    } else if (currentUser != null) {
      targetUserId = currentUser.id;
    } else {
      return const SizedBox.shrink();
    }
    if (targetUserId == null) return const SizedBox.shrink();
    
    // ✅ Yüklenme durumunu kontrol et - eğer yükleniyorsa cache'den göster
    if (_isLoadingProfileData) {
      // ✅ Henüz yükleniyor - cache'deki sayıları göster (0'dan başlasın, sonra güncellensin)
      return Padding(
        padding: const EdgeInsets.only(top: 8, right: 10),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.spaceEvenly,
          children: [
            _userStories(targetUserId),
            ProfileLabelCount(count: _cachedEventCount.toString(), labelText: 'Etkinlikler'),
            ProfileLabelCount(count: _cachedMediaCount.toString(), labelText: 'Fotoğraflar'),
            ProfileLabelCount(count: _cachedStoryCount.toString(), labelText: 'Hikayeler'),
          ],
        ),
      );
    }
    
    // ✅ Veriler hazır - cache'den göster (zaten _loadAllProfileData'da güncellenmiş)
    int totalEvents = _cachedEventCount;
    int totalUserMedia = _cachedMediaCount;
    int totalStories = _cachedStoryCount;
    
    
    return Padding(
      padding: const EdgeInsets.only(top: 8, right: 10),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceEvenly,
        children: [
          _userStories(targetUserId),
          ProfileLabelCount(count: totalEvents.toString(), labelText: 'Etkinlikler'),
          ProfileLabelCount(count: totalUserMedia.toString(), labelText: 'Fotoğraflar'),
          ProfileLabelCount(count: totalStories.toString(), labelText: 'Hikayeler'),
        ],
      ),
    );
  }

  Widget _buildPublications(eventProvider) {
    final authProvider = Provider.of<AuthProvider>(context);
    final currentUser = authProvider.user;
    final isViewingOtherProfile = widget.targetUserId != null && widget.targetUserId != currentUser?.id;
    
    // Hangi kullanıcının medyalarını göstereceğimizi belirle
    // ✅ targetUserId'yi direkt widget'tan al
    int? targetUserId;
    if (isViewingOtherProfile) {
      targetUserId = widget.targetUserId; // ✅ widget.targetUserId direkt kullan
    } else if (currentUser != null) {
      targetUserId = currentUser.id;
    } else {
      return const Center(child: Text('Kullanıcı bilgisi bulunamadı'));
    }
    if (targetUserId == null) return const Center(child: Text('Kullanıcı bilgisi bulunamadı'));
    
    var size = MediaQuery.of(context).size;
    
    // ✅ Lazy loading: Sadece gösterilen medyaları kullan (_displayedMedia)
    final userMedia = _displayedMedia;
    
    return TabBarView(
      children: [
        // Grid Tab - Kullanıcının Paylaştığı Fotoğraflar
        userMedia.isEmpty 
          ? Center(
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
                    'Henüz fotoğraf paylaşılmamış',
                    style: TextStyle(
                      fontSize: 16,
                      color: Colors.grey[600],
                      fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                    'Etkinliklerde fotoğraf paylaşmaya başla!',
                    style: TextStyle(
                      fontSize: 14,
                      color: Colors.grey[500],
                ),
              ),
            ],
          ),
            )
          : NotificationListener<ScrollNotification>(
              onNotification: (ScrollNotification notification) {
                if (notification is ScrollUpdateNotification || notification is ScrollEndNotification) {
                  // ✅ Scroll position'ı kontrol et
                  final metrics = notification.metrics;
                  // ✅ Daha erken tetikle (400px kala veya %80 scroll yapıldıysa)
                  final remainingScroll = metrics.maxScrollExtent - metrics.pixels;
                  final scrollPercentage = metrics.maxScrollExtent > 0 
                      ? (metrics.pixels / metrics.maxScrollExtent) * 100 
                      : 0;
                  
                  if (metrics.maxScrollExtent > 0 && 
                      (remainingScroll <= 400 || scrollPercentage >= 80)) {
                    if (!_isLoadingMoreMedia && _hasMoreMedia) {
                      if (kDebugMode) {
                        debugPrint('📜 NotificationListener - Loading more media (scroll: ${scrollPercentage.toStringAsFixed(1)}%, remaining: ${remainingScroll.toStringAsFixed(0)}px)');
                      }
                      _loadMoreMedia();
                    }
                  }
                }
                return false;
              },
              child: GridView.builder(
                controller: _mediaScrollController,
                padding: EdgeInsets.zero,
                gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                  crossAxisCount: 3,
                  mainAxisSpacing: 1,
                  crossAxisSpacing: 1,
                  childAspectRatio: 1,
                ),
                itemCount: _displayedMedia.length + (_isLoadingMoreMedia && _hasMoreMedia ? 1 : 0),
                itemBuilder: (context, index) {
                  // ✅ Loading indicator (son eleman)
                  if (index >= _displayedMedia.length && _isLoadingMoreMedia) {
                    return const ShimmerLoading(
                      width: double.infinity,
                      height: double.infinity,
                    );
                  }
                  
                  // ✅ Index kontrolü
                  if (index >= _displayedMedia.length) {
                    return const SizedBox.shrink();
                  }
                  
                  final media = _displayedMedia[index];
                  return GestureDetector(
                    onTap: () {
                      // ✅ Tüm medyaları kullan (orijinal boyutlu açılsın)
                      // Önce _allUserMedia'yı doldur (henüz doldurulmamışsa)
                      final eventProvider = Provider.of<EventProvider>(context, listen: false);
                      if (_allUserMedia.isEmpty) {
                        final authProvider = Provider.of<AuthProvider>(context, listen: false);
                        final currentUser = authProvider.user;
                        final isViewingOtherProfile = widget.targetUserId != null && widget.targetUserId != currentUser?.id;
                        // ✅ targetUserId'yi direkt widget'tan al
                        int? targetUserId;
                        if (isViewingOtherProfile) {
                          targetUserId = widget.targetUserId; // ✅ widget.targetUserId direkt kullan
                        } else if (currentUser != null) {
                          targetUserId = currentUser.id;
                        } else {
                          return;
                        }
                        if (targetUserId == null) return;
                        _allUserMedia = eventProvider.getUserMedia(targetUserId);
                      }
                      final allMediaIndex = _allUserMedia.indexWhere((m) => m['id'] == media['id']);
                      if (allMediaIndex != -1) {
                        _showMediaFullScreen(context, media, _allUserMedia, allMediaIndex);
                      } else {
                        // Fallback - _displayedMedia kullan
                        _showMediaFullScreen(context, media, _displayedMedia, index);
                      }
                    },
                    child: Container(
                      decoration: BoxDecoration(
                        color: Colors.grey.shade200,
                        border: Border.all(color: Colors.white, width: 0.5),
                      ),
                      child: _buildMediaThumbnail(media),
                    ),
                  );
                },
              ),
            ),
        // Person Tab - Etkinlik Bilgileri
        _isLoadingTargetUserEvents 
          ? const EventListShimmer(count: 3)
          : ListView.builder(
              padding: const EdgeInsets.all(16),
              itemCount: isViewingOtherProfile ? _targetUserEvents.length : eventProvider.events.where((Event event) => event.participantCount > 0).length,
              itemBuilder: (context, index) {
                final event = isViewingOtherProfile 
                  ? _targetUserEvents[index]
                  : eventProvider.events.where((Event event) => event.participantCount > 0).toList()[index];
            return Card(
              margin: const EdgeInsets.only(bottom: 12),
              child: ListTile(
                leading: event.coverPhoto != null
                    ? ClipRRect(
                        borderRadius: BorderRadius.circular(8),
                        child: Image.network(
                          event.coverPhoto!,
                          width: 60,
                          height: 60,
                          fit: BoxFit.cover,
                          errorBuilder: (context, error, stackTrace) {
                            return Container(
                              width: 60,
                              height: 60,
                              color: Colors.grey.shade300,
                              child: const Icon(Icons.event, color: Colors.grey),
                            );
                          },
                        ),
                      )
                    : Container(
                        width: 60,
                        height: 60,
                        color: Colors.grey.shade300,
                        child: const Icon(Icons.event, color: Colors.grey),
                      ),
                title: Text(
                  event.title,
                  style: const TextStyle(fontWeight: FontWeight.bold),
                ),
                subtitle: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(event.description),
                    const SizedBox(height: 4),
                    Text(
                      'Tarih: ${event.date}',
                      style: const TextStyle(fontSize: 12, color: Colors.grey),
                    ),
                    Text(
                      'Medya: ${event.mediaCount} | Hikaye: ${event.storyCount}',
                      style: const TextStyle(fontSize: 12, color: Colors.grey),
                    ),
                  ],
                ),
              ),
            );
          },
        ),
      ],
    );
  }

  Widget _userStories(int targetUserId) {
    final authProvider = Provider.of<AuthProvider>(context);
    final currentUser = authProvider.user;
    final isViewingOtherProfile = widget.targetUserId != null && widget.targetUserId != currentUser?.id;
    
    // Hangi kullanıcının profil resmini göstereceğimizi belirle
    String? profileImage;
    if (isViewingOtherProfile) {
      // ✅ _targetUser null olsa bile widget.targetUserId kullan
      if (_targetUser != null) {
        profileImage = _targetUser!.profileImage;
      }
    } else if (currentUser != null) {
      profileImage = currentUser.profileImage;
    }
    return Column(
      children: [
        SingleChildScrollView(
          scrollDirection: Axis.horizontal,
          child: Row(
            children: [
              Padding(
                padding: const EdgeInsets.only(right: 20, left: 15, bottom: 10),
                child: Column(
                  children: [
                    SizedBox(
                      width: 100,
                      height: 100,
                      child: Stack(
                        children: [
                          Container(
                            width: 100,
                            height: 100,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: Colors.grey.shade300,
                            ),
                            child: _selectedImage != null
                                ? ClipOval(
                                    child: Image.file(
                                      _selectedImage!,
                                      fit: BoxFit.cover,
                                      width: 100,
                                      height: 100,
                                    ),
                                  )
                                : (profileImage != null && profileImage!.isNotEmpty)
                                    ? ClipOval(
                                        child: Image.network(
                                          profileImage!,
                                          fit: BoxFit.cover,
                                          width: 100,
                                          height: 100,
                                          errorBuilder: (context, error, stackTrace) {
                                            return const Icon(
                                              Icons.person,
                                              size: 50,
                                              color: Colors.grey,
                                            );
                                          },
                                        ),
                                      )
                                    : Container(
                                        child: const Icon(
                                          Icons.person,
                                          size: 50,
                                          color: Colors.grey,
                                        ),
                                      ),
                          ),
                          // Sadece kendi profilinde fotoğraf değiştirme butonu göster
                          if (!isViewingOtherProfile)
                            Positioned(
                              bottom: 0,
                              right: 0,
                              child: GestureDetector(
                                onTap: _isLoading ? null : _showImagePicker,
                                child: Container(
                                  width: 29,
                                  height: 29,
                                  decoration: const BoxDecoration(
                                      shape: BoxShape.circle, color: Colors.white),
                                  child: _isLoading 
                                      ? const SizedBox(
                                          width: 15,
                                          height: 15,
                                          child: CircularProgressIndicator(
                                            strokeWidth: 2,
                                            valueColor: AlwaysStoppedAnimation<Color>(AppColors.primary),
                                          ),
                                        )
                                      : const Icon(Icons.add_circle,
                                          color: AppColors.primary, size: 29),
                                ),
                              ),
                            ),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        ),
      ],
    );
  }

  Future<void> _pickImage() async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 800,
        maxHeight: 800,
        imageQuality: 80,
      );
      
      if (image != null) {
        setState(() {
          _selectedImage = File(image.path);
        });
        await _updateProfileImage();
      }
    } catch (e) {
      _showSnackBar('Resim seçilirken hata: $e', isError: true);
    }
  }

  Future<void> _takePhoto() async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.camera,
        maxWidth: 800,
        maxHeight: 800,
        imageQuality: 80,
      );
      
      if (image != null) {
        setState(() {
          _selectedImage = File(image.path);
        });
        await _updateProfileImage();
      }
    } catch (e) {
      _showSnackBar('Fotoğraf çekilirken hata: $e', isError: true);
    }
  }

  Future<void> _updateProfileImage() async {
    if (_selectedImage == null) return;

    setState(() {
      _isLoading = true;
    });

    try {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      final currentUser = authProvider.user;
      
      if (currentUser == null) {
        throw Exception('Kullanıcı bilgisi bulunamadı');
      }

      // Profil resmini güncelle
      final nameParts = currentUser.name.split(' ');
      final firstName = nameParts.isNotEmpty ? nameParts.first : '';
      final lastName = nameParts.length > 1 ? nameParts.sublist(1).join(' ') : '';
      
      final updatedUser = await _apiService.updateUserProfile(
        userId: currentUser.id,
        name: firstName,
        surname: lastName,
        email: currentUser.email,
        phone: currentUser.phone ?? '',
        username: currentUser.username ?? '',
        profileImage: _selectedImage,
      );

      if (updatedUser != null) {
        authProvider.updateUser(updatedUser);
        setState(() {
          _currentProfileImage = updatedUser.profileImage;
          _selectedImage = null;
        });
        _showSnackBar('Profil resmi başarıyla güncellendi');
        
        // UI'yi zorla yenile
        if (mounted) {
          setState(() {});
        }
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('❌ Profil resmi güncelleme hatası: $e');
        debugPrint('❌ Stack trace: ${StackTrace.current}');
      }
      _showSnackBar('Profil resmi güncellenirken hata: ${e.toString()}', isError: true);
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  void _showImagePicker() {
    showModalBottomSheet(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Galeriden Seç'),
              onTap: () {
                Navigator.pop(context);
                _pickImage();
              },
            ),
            ListTile(
              leading: const Icon(Icons.camera_alt),
              title: const Text('Fotoğraf Çek'),
              onTap: () {
                Navigator.pop(context);
                _takePhoto();
              },
            ),
            if (_currentProfileImage != null || _selectedImage != null)
              ListTile(
                leading: const Icon(Icons.delete, color: Colors.red),
                title: const Text('Profil Resmini Sil', style: TextStyle(color: Colors.red)),
                onTap: () {
                  Navigator.pop(context);
                  _deleteProfileImage();
                },
              ),
          ],
        ),
      ),
    );
  }

  Future<void> _deleteProfileImage() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      final currentUser = authProvider.user;
      
      if (currentUser == null) {
        throw Exception('Kullanıcı bilgisi bulunamadı');
      }

      // Profil resmini sil
      final nameParts = currentUser.name.split(' ');
      final firstName = nameParts.isNotEmpty ? nameParts.first : '';
      final lastName = nameParts.length > 1 ? nameParts.sublist(1).join(' ') : '';
      
      final updatedUser = await _apiService.updateUserProfile(
        userId: currentUser.id,
        name: firstName,
        surname: lastName,
        email: currentUser.email,
        phone: currentUser.phone ?? '',
        username: currentUser.username ?? '',
        profileImage: null, // null göndererek sil
      );

      if (updatedUser != null) {
        authProvider.updateUser(updatedUser);
        setState(() {
          _currentProfileImage = null;
          _selectedImage = null;
        });
        _showSnackBar('Profil resmi silindi');
        
        // UI'yi zorla yenile
        if (mounted) {
          setState(() {});
        }
      }
    } catch (e) {
      _showSnackBar('Profil resmi silinirken hata: $e', isError: true);
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  void _showSnackBar(String message, {bool isError = false}) {
    ScaffoldMessenger.of(context).showSnackBar(
      SnackBar(
        content: Text(message),
        backgroundColor: isError ? Colors.red : Colors.green,
        duration: const Duration(seconds: 3),
      ),
    );
  }

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
              Navigator.pushReplacementNamed(context, '/login');
            },
            child: const Text('Çıkış Yap', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
  }

  Widget _buildStatItem(IconData icon, String text) {
    return Row(
      mainAxisSize: MainAxisSize.min,
            children: [
        Icon(icon, color: Colors.white, size: 20),
        const SizedBox(width: 4),
        Text(
          text,
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w600,
            fontSize: 14,
          ),
        ),
      ],
    );
  }
}

class MediaViewerScreen extends StatefulWidget {
  final List<Map<String, dynamic>> media;
  final int initialIndex;

  const MediaViewerScreen({
    super.key,
    required this.media,
    this.initialIndex = 0,
  });

  @override
  State<MediaViewerScreen> createState() => _MediaViewerScreenState();
}

class _MediaViewerScreenState extends State<MediaViewerScreen> {
  late PageController _pageController;
  int _currentIndex = 0;

  @override
  void initState() {
    super.initState();
    _currentIndex = widget.initialIndex;
    _pageController = PageController(initialPage: _currentIndex);
  }

  @override
  void dispose() {
    _pageController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (widget.media.isEmpty) {
      return const Scaffold(
        backgroundColor: Colors.black,
        body: Center(
          child: Text(
            'Medya bulunamadı',
            style: TextStyle(color: Colors.white),
          ),
        ),
      );
    }

    final currentMedia = widget.media[_currentIndex];

    return Scaffold(
      backgroundColor: Colors.black,
      body: GestureDetector(
        onTapDown: (details) {
          final screenWidth = MediaQuery.of(context).size.width;
          final tapPosition = details.globalPosition.dx;
          
          if (tapPosition < screenWidth / 2) {
            _previousMedia();
          } else {
            _nextMedia();
          }
        },
        child: Stack(
            children: [
            // Media Content
            PageView.builder(
              controller: _pageController,
              onPageChanged: (index) {
                setState(() {
                  _currentIndex = index;
                });
              },
              itemCount: widget.media.length,
              itemBuilder: (context, index) {
                final media = widget.media[index];
                return _buildMediaContent(media);
              },
            ),
            
            // Close Button - Top right
            Positioned(
              top: 50,
              right: 20,
              child: GestureDetector(
                onTap: () => Navigator.pop(context),
                child: Container(
                  padding: const EdgeInsets.all(8),
                  decoration: BoxDecoration(
                    color: Colors.black.withOpacity(0.3),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.close,
                    color: Colors.white,
                    size: 24,
                  ),
                ),
              ),
            ),
            
            // Navigation Buttons - Left and Right
            Positioned(
              left: 20,
              top: MediaQuery.of(context).size.height / 2 - 30,
              child: GestureDetector(
                onTap: _previousMedia,
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.black.withOpacity(0.3),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.chevron_left,
                    color: Colors.white,
                    size: 30,
                  ),
                ),
              ),
            ),
            
            Positioned(
              right: 20,
              top: MediaQuery.of(context).size.height / 2 - 30,
              child: GestureDetector(
                onTap: _nextMedia,
                child: Container(
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: Colors.black.withOpacity(0.3),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.chevron_right,
                    color: Colors.white,
                    size: 30,
                  ),
                  ),
                ),
              ),
            
            // Media Info - Bottom left
            _buildMediaInfo(currentMedia),
            
            // Media Actions - Bottom center
            _buildMediaActions(currentMedia),
          ],
        ),
      ),
    );
  }

  Widget _buildMediaContent(Map<String, dynamic> media) {
    final mediaUrl = media['url'];
    
    return Container(
      width: double.infinity,
      height: double.infinity,
      child: mediaUrl != null && mediaUrl.isNotEmpty
          ? InteractiveViewer(
              child: Image.network(
                mediaUrl,
                fit: BoxFit.contain,
                errorBuilder: (context, error, stackTrace) {
                  return Container(
                    color: Colors.grey.shade800,
                    child: const Center(
                      child: Icon(
                        Icons.image,
                        color: Colors.white,
                        size: 100,
                      ),
                      ),
                    );
                  },
              ),
            )
          : _buildPlaceholderContent(),
    );
  }

  Widget _buildPlaceholderContent() {
    return Container(
      color: Colors.grey.shade900,
      child: const Center(
        child: Icon(
          Icons.image_not_supported,
          color: Colors.white,
          size: 80,
        ),
      ),
    );
  }

  Widget _buildMediaInfo(Map<String, dynamic> media) {
    return Positioned(
      bottom: 100,
      left: 20,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            media['user_name'] ?? 'Kullanıcı',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.bold,
            ),
          ),
          if (media['description'] != null && media['description'].isNotEmpty)
            Text(
              media['description'],
              style: TextStyle(
                color: Colors.white.withOpacity(0.9),
                fontSize: 14,
              ),
            ),
          Text(
            '${_currentIndex + 1} / ${widget.media.length}',
            style: TextStyle(
              color: Colors.white.withOpacity(0.7),
              fontSize: 12,
            ),
              ),
            ],
          ),
    );
  }

  Widget _buildMediaActions(Map<String, dynamic> media) {
    return Positioned(
      bottom: 20,
      left: 20,
      right: 20,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Like button
          Container(
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.5),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white.withOpacity(0.2)),
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: () => _toggleMediaLike(media),
                borderRadius: BorderRadius.circular(20),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        media['is_liked'] == true ? Icons.favorite : Icons.favorite_border,
                        color: media['is_liked'] == true ? const Color(0xFFDB61A2) : Colors.white,
                        size: 24,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        '${media['likes'] ?? 0}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: 16),
          // Comment button
          Container(
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.5),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white.withOpacity(0.2)),
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: () => _showMediaComments(media),
                borderRadius: BorderRadius.circular(20),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        Icons.chat_bubble_outline,
                        color: Colors.white,
                        size: 24,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        '${media['comments'] ?? 0}',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 14,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ),
          const SizedBox(width: 16),
          // Share button
          Container(
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.5),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white.withOpacity(0.2)),
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: () => _shareMedia(media),
                borderRadius: BorderRadius.circular(20),
                child: const Padding(
                  padding: EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: Icon(
                    Icons.share,
                    color: Colors.white,
                    size: 24,
                  ),
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }

  void _previousMedia() {
    if (_currentIndex > 0) {
      setState(() {
        _currentIndex--;
      });
      _pageController.previousPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    } else {
      Navigator.pop(context);
    }
  }

  void _nextMedia() {
    if (_currentIndex < widget.media.length - 1) {
      setState(() {
        _currentIndex++;
      });
      _pageController.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    } else {
      Navigator.pop(context);
    }
  }

  void _toggleMediaLike(Map<String, dynamic> media) {
    // Beğeni özelliği - şimdilik sessiz
  }

  void _showMediaComments(Map<String, dynamic> media) {
    // Yorum özelliği - şimdilik sessiz
  }

  void _shareMedia(Map<String, dynamic> media) {
    // Paylaşım özelliği - şimdilik sessiz
  }
}

class ProfileLabelCount extends StatelessWidget {
  const ProfileLabelCount({
    super.key,
    required this.labelText,
    required this.count,
  });

  final String labelText;
  final String count;

  @override
  Widget build(BuildContext context) {
    return Column(
      children: [
        Text(
          count,
          style: const TextStyle(fontWeight: FontWeight.w600, fontSize: 16),
        ),
        Text(
          labelText,
          style: const TextStyle(fontWeight: FontWeight.w400, fontSize: 13.5),
        ),
      ],
    );
  }
}

  void _showMediaFullScreen(BuildContext context, Map<String, dynamic> media, List<Map<String, dynamic>> allMedia, int currentIndex) {
    // ✅ MediaViewerModal kullan (video desteği için)
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => MediaViewerModal(
          mediaList: allMedia,
          initialIndex: currentIndex,
        ),
      ),
    );
  }

  Widget _buildStatItem(IconData icon, String text) {
    return Row(
      mainAxisSize: MainAxisSize.min,
      children: [
        Icon(icon, color: Colors.white, size: 20),
        const SizedBox(width: 4),
        Text(
          text,
          style: const TextStyle(
            color: Colors.white,
            fontWeight: FontWeight.w500,
            fontSize: 16,
          ),
        ),
      ],
    );
  }

  Widget _getEventCoverImage(Event? event) {
    if (event == null) {
      return Container(
        color: Colors.grey.shade200,
        child: const Icon(
          Icons.event,
          color: Colors.grey,
          size: 30,
        ),
      );
    }

    // Önce thumbnail'i dene
    String? imageUrl = event.coverPhotoThumbnail;
    
    // Thumbnail yoksa orijinal cover photo'yu dene
    if (imageUrl == null || imageUrl.isEmpty) {
      imageUrl = event.coverPhoto;
    }

    if (imageUrl != null && imageUrl.isNotEmpty) {
      // ✅ CachedNetworkImage kullan (optimized cache ile)
      return SizedBox(
        width: 60,
        height: 60,
        child: CachedNetworkImage(
          imageUrl: imageUrl,
          fit: BoxFit.cover,
          cacheManager: ImageCacheConfig.getCacheManager(imageUrl),
          memCacheWidth: ImageCacheConfig.getMemCacheWidth(imageUrl, maxWidth: 120),
          memCacheHeight: ImageCacheConfig.getMemCacheHeight(imageUrl, maxHeight: 120),
          // ❌ maxWidthDiskCache ve maxHeightDiskCache kaldırıldı (ImageCacheManager gerektirir)
          httpHeaders: {
            'Connection': 'keep-alive',
            'Cache-Control': ImageCacheConfig.isThumbnail(imageUrl) 
                ? 'max-age=2592000' // 30 days for thumbnails
                : 'max-age=604800', // 7 days for full images
          },
          placeholder: (context, url) => Container(
            color: Colors.grey.shade200,
            child: const Center(
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          ),
          errorWidget: (context, error, stackTrace) {
            return Container(
              color: Colors.grey.shade200,
              child: const Icon(
                Icons.event,
                color: Colors.grey,
                size: 30,
              ),
            );
          },
        ),
      );
    }

    // Hiçbir resim yoksa default icon
    return Container(
      color: Colors.grey.shade200,
      child: const Icon(
        Icons.event,
        color: Colors.grey,
        size: 30,
      ),
    );
  }
