import 'package:flutter/material.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'dart:async';
import 'package:video_player/video_player.dart';
import 'package:path_provider/path_provider.dart';
import 'package:http/http.dart' as http;
import 'dart:io';
import 'package:digimobil_new/widgets/robust_image_widget.dart';
import 'package:digimobil_new/widgets/comments_modal.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:provider/provider.dart';

class StoryViewerModal extends StatefulWidget {
  final List<Map<String, dynamic>> stories;
  final int initialIndex;
  final Event event; // ✅ Event parametresi eklendi

  const StoryViewerModal({
    super.key,
    required this.stories,
    this.initialIndex = 0,
    required this.event, // ✅ Event parametresi eklendi
  });

  @override
  State<StoryViewerModal> createState() => _StoryViewerModalState();
}

class _StoryViewerModalState extends State<StoryViewerModal>
    with TickerProviderStateMixin {
  late PageController _pageController;
  AnimationController? _progressController;
  Animation<double>? _progressAnimation;
  
  int _currentStoryIndex = 0;
  bool _isPlaying = true;
  Timer? _timer;
  bool _isImageLoaded = false;
  bool _isPausedByUser = false; // ✅ Kullanıcı tarafından durduruldu mu?
  double _pausedProgressValue = 0.0; // ✅ Pause edildiğinde progress değeri
  bool _isTappingCenter = false; // ✅ Orta kısma basıldı mı?
  
  // ✅ Video player için key (her story için unique)
  GlobalKey<_StoryVideoPlayerState> _videoPlayerKey = GlobalKey<_StoryVideoPlayerState>();
  
  final ApiService _apiService = ApiService();

  @override
  void initState() {
    super.initState();
    _currentStoryIndex = widget.initialIndex;
    _pageController = PageController(initialPage: _currentStoryIndex);
    
    // ✅ Progress controller medya yüklendiğinde oluşturulacak (onImageLoaded/onVideoLoaded callback'lerinde)
    // İlk çağrıyı kaldırdık - medya hazır olunca otomatik başlayacak
  }

  @override
  void dispose() {
    _timer?.cancel();
    _progressController?.dispose();
    _pageController.dispose();
    super.dispose();
  }

  void _startProgress() {
    // ✅ Önce timer ve controller'ı temizle
    _timer?.cancel();
    _progressController?.dispose();
    
    // ✅ Sadece medya yüklendiğinde ve oynatılıyorsa başla
    if (_isPlaying && !_isPausedByUser) {
      final currentStory = widget.stories[_currentStoryIndex];
      final mediaUrl = currentStory['url'] ?? currentStory['media_url'];
      
      // ✅ URL'ye bakarak video/resim ayırt et
      final isVideo = mediaUrl != null && (
        mediaUrl.toString().toLowerCase().endsWith('.mp4') ||
        mediaUrl.toString().toLowerCase().endsWith('.mov') ||
        mediaUrl.toString().toLowerCase().endsWith('.avi') ||
        mediaUrl.toString().toLowerCase().endsWith('.mkv') ||
        mediaUrl.toString().toLowerCase().endsWith('.webm')
      );
      
      // ✅ Resim: 15 saniye, Video: video süresi (varsa) veya 59 saniye
      int duration;
      if (isVideo) {
        duration = currentStory['video_duration'] ?? 59; // Video süresi veya default 59s
      } else {
        duration = 15; // Resim: 15 saniye
      }
      
      print('⏱️ Starting progress - isVideo: $isVideo, duration: ${duration}s');
      
      _progressController = AnimationController(
        duration: Duration(seconds: duration),
        vsync: this,
      );
      
      _progressAnimation = Tween<double>(
        begin: 0.0,
        end: 1.0,
      ).animate(_progressController!);
      
      // ✅ Animation listener ekle - her frame'de setState çağır
      _progressController!.addListener(() {
        if (mounted) {
          setState(() {}); // Progress bar güncellenmesi için
        }
      });
      
      _progressController!.forward();
      _timer = Timer(Duration(seconds: duration), () {
        if (mounted && !_isPausedByUser) {
          _nextStory();
        }
      });
    }
  }

  // ✅ Hikaye düzenleme/silme yetkisi kontrolü
  bool _canEditOrDeleteStory(Map<String, dynamic> story) {
    final currentUser = Provider.of<AuthProvider>(context, listen: false).user;
    final userRole = widget.event.userRole;
    final userPermissions = widget.event.userPermissions;
    
    // Kendi hikayesi mi kontrol et
    final isOwnStory = story['user_id'] == currentUser?.id;
    
    // Admin ve Moderator her şeyi yapabilir
    if (userRole == 'admin' || userRole == 'moderator') {
      return true;
    }
    
    // Yetkili kullanıcı ve medya silme yetkisi varsa
    if (userRole == 'yetkili_kullanici' && userPermissions != null) {
      if (userPermissions['medya_silebilir'] == true) {
        return true;
      }
    }
    
    // Kendi hikayesi ise düzenleyebilir/silebilir
    if (isOwnStory) {
      return true;
    }
    
    return false;
  }
  
  void _nextStory() {
    if (_currentStoryIndex < widget.stories.length - 1) {
      // ✅ PageController'ın nextPage'i onPageChanged'i tetikleyecek
      // Bu yüzden _currentStoryIndex'i burada güncellemiyoruz (çift güncelleme önleme)
      setState(() {
        _isImageLoaded = false; // Reset loading state
      });
      _pageController.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    } else {
      // ✅ Son hikayede, kapatma öncesi kısa gecikme ekle
      Future.delayed(const Duration(milliseconds: 500), () {
        if (mounted) {
          Navigator.pop(context);
        }
      });
    }
  }

  void _previousStory() {
    if (_currentStoryIndex > 0) {
      // ✅ PageController'ın previousPage'i onPageChanged'i tetikleyecek
      // Bu yüzden _currentStoryIndex'i burada güncellemiyoruz (çift güncelleme önleme)
      setState(() {
        _isImageLoaded = false; // Reset loading state
      });
      _pageController.previousPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
    } else {
      Navigator.pop(context);
    }
  }

  void _resetProgress() {
    _timer?.cancel();
    _progressController?.dispose();
    _progressController = null;
    _progressAnimation = null;
    _startProgress();
  }

  void _togglePlayPause() {
    setState(() {
      _isPlaying = !_isPlaying;
    });
    
    if (_isPlaying) {
      _startProgress();
    } else {
      _progressController?.stop();
      _timer?.cancel();
    }
  }

  @override
  Widget build(BuildContext context) {
    if (widget.stories.isEmpty) {
      return const Scaffold(
        backgroundColor: Colors.black,
        body: Center(
          child: Text(
            'Hikaye bulunamadı',
            style: TextStyle(color: Colors.white),
          ),
        ),
      );
    }

    final currentStory = widget.stories[_currentStoryIndex];
    
    return Scaffold(
      backgroundColor: Colors.black,
      body: GestureDetector(
        onTapDown: (details) {
          final screenWidth = MediaQuery.of(context).size.width;
          final screenHeight = MediaQuery.of(context).size.height;
          final tapPosition = details.globalPosition.dx;
          final tapPositionY = details.globalPosition.dy;
          
          // ✅ Üst kısımda butonlar var (close, 3-dot menu), oraya basılmışsa işlem yapma
          if (tapPositionY < 100) {
            _isTappingCenter = false;
            return; // Üst butonlara basılmış
          }
          
          // ✅ Alt kısımda butonlar var, oraya basılmışsa işlem yapma
          if (tapPositionY > screenHeight * 0.85) {
            _isTappingCenter = false;
            return; // Yorum/beğeni butonlarına basılmış
          }
          
          // ✅ Ekrana dokunulduğunda pause (sol/sağ kontrolü onTap'te)
          _isTappingCenter = true;
          _pauseStory();
        },
        onTapUp: (details) {
          final screenWidth = MediaQuery.of(context).size.width;
          final screenHeight = MediaQuery.of(context).size.height;
          final tapPosition = details.globalPosition.dx;
          final tapPositionY = details.globalPosition.dy;
          
          // ✅ Parmağı bıraktığında resume
          if (_isPausedByUser && _isTappingCenter) {
            _isTappingCenter = false;
            _resumeStory();
            
            // ✅ Hızlı tap ise (basılı tutmamış) sol/sağ kontrolü yap
            // (Basılı tutma süresi 200ms'den az ise)
            final leftThird = screenWidth / 3;
            final rightThird = screenWidth * 2 / 3;
            
            if (tapPositionY < 100 || tapPositionY > screenHeight * 0.85) {
              return; // Butonlara basılmışsa işlem yapma
            }
            
            if (tapPosition < leftThird) {
              // Sol taraf - önceki hikaye (hızlı tap)
              _previousStory();
            } else if (tapPosition > rightThird) {
              // Sağ taraf - sonraki hikaye (hızlı tap)
              _nextStory();
            }
          }
        },
        onTapCancel: () {
          // ✅ Tap iptal edildiğinde de resume et
          if (_isPausedByUser && _isTappingCenter) {
            _isTappingCenter = false;
            _resumeStory();
          }
        },
        child: Stack(
          children: [
            // Story Content
            PageView.builder(
              controller: _pageController,
              onPageChanged: (index) {
                setState(() {
                  _currentStoryIndex = index;
                  _isImageLoaded = false; // Reset loading state
                  // ✅ Her story için yeni key oluştur (video player'ı reset etmek için)
                  _videoPlayerKey = GlobalKey<_StoryVideoPlayerState>();
                });
                _resetProgress();
              },
              itemCount: widget.stories.length,
              itemBuilder: (context, index) {
                final story = widget.stories[index];
                return _buildStoryContent(story);
              },
            ),
            
            // Progress Bars - Like old theme
            _buildProgressBars(),
            
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
            
            // ✅ Yetki kontrolü ile 3 nokta menüsü
            Positioned(
              top: 50,
              right: 70,
              child: _canEditOrDeleteStory(currentStory)
                  ? PopupMenuButton<String>(
                      onSelected: (value) {
                        if (value == 'edit') {
                          _editStory(currentStory);
                        } else if (value == 'delete') {
                          _deleteStory(currentStory);
                        }
                      },
                      itemBuilder: (context) => [
                        const PopupMenuItem(
                          value: 'edit',
                          child: Row(
                            children: [
                              Icon(Icons.edit, size: 20),
                              SizedBox(width: 8),
                              Text('Düzenle'),
                            ],
                          ),
                        ),
                        const PopupMenuItem(
                          value: 'delete',
                          child: Row(
                            children: [
                              Icon(Icons.delete, size: 20, color: Colors.red),
                              SizedBox(width: 8),
                              Text('Sil', style: TextStyle(color: Colors.red)),
                            ],
                          ),
                        ),
                      ],
                      child: Container(
                        padding: const EdgeInsets.all(8),
                        decoration: BoxDecoration(
                          color: Colors.black.withOpacity(0.3),
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.more_vert,
                          color: Colors.white,
                          size: 24,
                        ),
                      ),
                    )
                  : const SizedBox.shrink(), // ✅ Yetki yoksa hiçbir şey gösterme
            ),
            
            // Navigation Buttons - Left and Right
            Positioned(
              left: 20,
              top: MediaQuery.of(context).size.height / 2 - 30,
              child: GestureDetector(
                onTap: _previousStory,
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
                onTap: _nextStory,
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
            
            // Story Info - Bottom left like old theme
            _buildStoryInfo(currentStory),
            
            // Story Actions - Bottom center like old theme
            _buildStoryActions(currentStory),
          ],
        ),
      ),
    );
  }

  Widget _buildStoryContent(Map<String, dynamic> story) {
    final mediaUrl = story['url'] ?? story['media_url'];
    
    // ✅ URL'ye bakarak video/resim ayırt et
    final isVideo = mediaUrl != null && (
      mediaUrl.toString().toLowerCase().endsWith('.mp4') ||
      mediaUrl.toString().toLowerCase().endsWith('.mov') ||
      mediaUrl.toString().toLowerCase().endsWith('.avi') ||
      mediaUrl.toString().toLowerCase().endsWith('.mkv') ||
      mediaUrl.toString().toLowerCase().endsWith('.webm')
    );
    
    return Container(
      width: double.infinity,
      height: double.infinity,
      child: mediaUrl != null
          ? isVideo
              ? _buildVideoContent(mediaUrl, story)
              : _buildImageContent(mediaUrl)
          : _buildPlaceholderContent(),
    );
  }

  Widget _buildImageContent(String imageUrl) {
    return RobustImageWidget(
      imageUrl: imageUrl,
      fit: BoxFit.cover,
      onImageLoaded: () {
        if (!_isImageLoaded) {
          setState(() {
            _isImageLoaded = true;
          });
          _startProgress();
        }
      },
      placeholder: Container(
        color: Colors.grey.shade900,
        child: const Center(
          child: CircularProgressIndicator(
            color: Colors.white,
          ),
        ),
      ),
      errorWidget: Container(
        color: Colors.grey.shade900,
        child: const Center(
          child: Icon(
            Icons.error_outline,
            color: Colors.white,
            size: 50,
          ),
        ),
      ),
    );
  }

  Widget _buildVideoContent(String videoUrl, Map<String, dynamic> story) {
    return StoryVideoPlayer(
      key: _videoPlayerKey, // ✅ Key ile video player'a erişim
      url: videoUrl,
      onVideoLoaded: (Duration? duration) {
        if (!_isImageLoaded) {
          setState(() {
            _isImageLoaded = true;
          });
          // ✅ Video süresini story'ye kaydet
          if (duration != null) {
            story['video_duration'] = duration.inSeconds;
          }
          _startProgress();
        }
      },
      onVideoCompleted: () {
        // ✅ Video bittiğinde otomatik sonraki hikayeye geç
        if (!_isPausedByUser) {
          _nextStory();
        }
      },
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

  Widget _buildProgressBars() {
    return Positioned(
      top: 50,
      left: 20,
      right: 20,
      child: Container(
        decoration: BoxDecoration(
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.3),
              blurRadius: 4,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: List.generate(widget.stories.length, (index) {
            return Expanded(
              child: Container(
                height: 4, // Increased height for better visibility
                margin: EdgeInsets.only(right: index < widget.stories.length - 1 ? 6 : 0),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(2),
                  color: Colors.white.withOpacity(0.3), // Background color
                ),
                child: ClipRRect(
                  borderRadius: BorderRadius.circular(2),
                  child: LinearProgressIndicator(
                    value: index < _currentStoryIndex
                        ? 1.0
                        : index == _currentStoryIndex
                            ? _progressAnimation?.value ?? 0.0
                            : 0.0,
                    backgroundColor: Colors.transparent, // Remove default background
                    valueColor: const AlwaysStoppedAnimation<Color>(Colors.white),
                    minHeight: 4,
                  ),
                ),
              ),
            );
          }),
        ),
      ),
    );
  }

  Widget _buildStoryInfo(Map<String, dynamic> story) {
    return Positioned(
      bottom: 100,
      left: 20,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            story['user_name'] ?? 'Kullanıcı',
            style: const TextStyle(
              color: Colors.white,
              fontSize: 16,
              fontWeight: FontWeight.bold,
            ),
          ),
          if (story['aciklama'] != null && story['aciklama'].isNotEmpty)
            Text(
              story['aciklama'],
              style: TextStyle(
                color: Colors.white.withOpacity(0.9),
                fontSize: 14,
              ),
            ),
          Text(
            '${_currentStoryIndex + 1} / ${widget.stories.length}',
            style: TextStyle(
              color: Colors.white.withOpacity(0.7),
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStoryActions(Map<String, dynamic> story) {
    return Positioned(
      bottom: 20,
      left: 20,
      right: 20,
      child: Row(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          // Like button - Like old theme style
          GestureDetector(
            onTap: () => _toggleStoryLike(story),
            behavior: HitTestBehavior.opaque, // ✅ Event propagation'ı durdur
            child: Container(
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.5),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white.withOpacity(0.2)),
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: () => _toggleStoryLike(story),
                borderRadius: BorderRadius.circular(20),
                child: Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                  child: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      Icon(
                        story['is_liked'] == true ? Icons.favorite : Icons.favorite_border,
                        color: story['is_liked'] == true ? const Color(0xFFDB61A2) : Colors.white,
                        size: 24,
                      ),
                      const SizedBox(width: 8),
                      Text(
                        '${story['likes'] ?? 0}',
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
          ),
          const SizedBox(width: 16),
          // Comment button - Like old theme style
          GestureDetector(
            onTap: () => _showStoryComments(story),
            behavior: HitTestBehavior.opaque, // ✅ Event propagation'ı durdur
            child: Container(
            decoration: BoxDecoration(
              color: Colors.black.withOpacity(0.5),
              borderRadius: BorderRadius.circular(20),
              border: Border.all(color: Colors.white.withOpacity(0.2)),
            ),
            child: Material(
              color: Colors.transparent,
              child: InkWell(
                onTap: () => _showStoryComments(story),
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
                        '${story['comments'] ?? 0}',
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
          ),
        ],
      ),
    );
  }

  bool _canEditStory(Map<String, dynamic> story) {
    // TODO: Implement proper authorization check
    // For now, allow editing for all stories
    print('Can edit story: ${story['user_name']} - true');
    return true;
  }

  void _editStory(Map<String, dynamic> story) {
    final TextEditingController captionController = TextEditingController(text: story['aciklama'] ?? '');
    
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Hikayeyi Düzenle'),
        content: TextField(
          controller: captionController,
          decoration: const InputDecoration(
            hintText: 'Hikaye açıklaması...',
            border: OutlineInputBorder(),
          ),
          maxLines: 3,
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('İptal'),
          ),
          TextButton(
            onPressed: () async {
              final navigator = Navigator.of(context);
              navigator.pop();
              
              try {
                final success = await _apiService.editStory(
                  story['id'], 
                  captionController.text
                );
                
                if (success) {
                  // ✅ Widget hala mounted ise işlem yap
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Hikaye başarıyla güncellendi'),
                        backgroundColor: AppColors.success,
                      ),
                    );
                    
                    // Update story in local state
                    setState(() {
                      story['aciklama'] = captionController.text;
                    });
                  }
                } else {
                  throw Exception('Failed to update story');
                }
              } catch (e) {
                // ✅ Widget hala mounted ise hata göster
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text('Hikaye güncellenirken hata: $e'),
                      backgroundColor: AppColors.error,
                    ),
                  );
                }
              }
            },
            child: const Text('Kaydet'),
          ),
        ],
      ),
    );
  }

  void _deleteStory(Map<String, dynamic> story) {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Hikayeyi Sil'),
        content: const Text('Bu hikayeyi silmek istediğinizden emin misiniz?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('İptal'),
          ),
          TextButton(
            onPressed: () async {
              final navigator = Navigator.of(context);
              navigator.pop();
              
              try {
                final result = await _apiService.deleteStory(story['id']);
                
                if (result['success'] == true) {
                  // ✅ Widget hala mounted ise işlem yap
                  if (mounted) {
                    ScaffoldMessenger.of(context).showSnackBar(
                      const SnackBar(
                        content: Text('Hikaye başarıyla silindi'),
                        backgroundColor: AppColors.success,
                      ),
                    );
                    
                    // Close story viewer and notify parent
                    Navigator.pop(context, 'story_deleted');
                  }
                } else {
                  throw Exception(result['error'] ?? 'Failed to delete story');
                }
              } catch (e) {
                // ✅ Widget hala mounted ise hata göster
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text('Hikaye silinirken hata: $e'),
                      backgroundColor: AppColors.error,
                    ),
                  );
                }
              }
            },
            child: const Text('Sil', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
  }

  Future<void> _toggleStoryLike(Map<String, dynamic> story) async {
    final storyId = story['id'];
    if (storyId == null) return;
    
    // ✅ Değişkenleri try bloğunun dışında tanımla
    final previousLikeState = story['is_liked'] == true;
    final previousLikesCount = story['likes'] ?? 0;
    
    try {
      // Optimistic update
      setState(() {
        story['is_liked'] = !previousLikeState;
        story['likes'] = previousLikesCount + (previousLikeState ? -1 : 1);
      });
      
      // ✅ Hikayeler de medya tablosunda saklanıyor, medya ID'si ile beğenilebilir
      final mediaId = story['media_id'] ?? story['id'];
      
      // Call API to like/unlike
      final result = await _apiService.toggleLike(mediaId, previousLikeState);
      
      // ✅ API'den gelen güncel beğeni sayısını kullan
      if (result['likes_count'] != null) {
        setState(() {
          story['likes'] = result['likes_count'] as int;
          story['is_liked'] = result['is_liked'] as bool? ?? story['is_liked'];
        });
      }
      
    } catch (e) {
      print('Story like error: $e');
      // Rollback on error
      setState(() {
        story['is_liked'] = previousLikeState;
        story['likes'] = previousLikesCount;
      });
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Beğeni işlemi başarısız: $e'),
            backgroundColor: AppColors.error,
            duration: const Duration(seconds: 2),
          ),
        );
      }
    }
  }

  void _showStoryComments(Map<String, dynamic> story) {
    final storyId = story['id'];
    if (storyId == null) return;
    
    // ✅ Hikayeler de medya tablosunda, medya ID'si ile yorum yapılabilir
    final mediaId = story['media_id'] ?? story['id'];
    
    // ✅ Hikayeyi pause et
    _pauseStory();
    
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => CommentsModal(
        mediaId: mediaId,
        comments: [], // Empty list, will be loaded by the modal
        isLoading: false,
        onLoadComments: () {}, // Empty function
        onAddComment: (comment) {
          // ✅ Yorum sayısını güncelle
          setState(() {
            story['comments'] = (story['comments'] ?? 0) + 1;
          });
        },
        event: widget.event,
      ),
    ).then((_) {
      // ✅ Modal kapandığında resume et
      if (_isPausedByUser) {
        _resumeStory();
      }
    });
  }
  
  void _pauseStory() {
    if (!_isPausedByUser) {
      // ✅ Mevcut progress değerini kaydet
      if (_progressController != null && _progressController!.isAnimating) {
        _pausedProgressValue = _progressController!.value;
        print('⏸️ Pausing story - Current progress: $_pausedProgressValue');
      } else {
        // ✅ Eğer controller yoksa veya animasyon durmuşsa, mevcut değeri al
        _pausedProgressValue = _progressController?.value ?? 0.0;
        print('⏸️ Pausing story - No active animation, using value: $_pausedProgressValue');
      }
      
      setState(() {
        _isPausedByUser = true;
        _isPlaying = false;
      });
      
      // ✅ Timer ve progress'i durdur
      _timer?.cancel();
      if (_progressController != null && _progressController!.isAnimating) {
        _progressController!.stop();
      }
      
      // ✅ Video'yu durdur
      _videoPlayerKey.currentState?.pauseVideo();
      
      print('⏸️ Story paused at progress: $_pausedProgressValue');
    } else {
      print('⚠️ Pause called but story already paused');
    }
  }
  
  void _resumeStory() {
    if (_isPausedByUser) {
      setState(() {
        _isPausedByUser = false;
        _isPlaying = true;
      });
      
      // ✅ Kaldığı yerden devam et
      final currentStory = widget.stories[_currentStoryIndex];
      final isVideo = currentStory['media_type'] == 'video';
      final totalDuration = isVideo ? 59 : 24;
      final remainingSeconds = (totalDuration * (1.0 - _pausedProgressValue)).round();
      
      print('▶️ Resuming story - Progress: $_pausedProgressValue, Remaining: ${remainingSeconds}s, Total: ${totalDuration}s');
      
      if (remainingSeconds > 0 && remainingSeconds <= totalDuration) {
        // ✅ Timer'ı iptal et (zaten iptal edilmiş olmalı ama emin olmak için)
        _timer?.cancel();
        
        // ✅ Mevcut controller'ı dispose et ve yenisini oluştur
        _progressController?.dispose();
        _progressController = null;
        
        _progressController = AnimationController(
          duration: Duration(seconds: totalDuration),
          vsync: this,
        );
        
        _progressAnimation = Tween<double>(
          begin: 0.0,
          end: 1.0,
        ).animate(_progressController!);
        
        // ✅ Kaldığı yerden başlat (0.0 ile 1.0 arasında olmalı)
        final startValue = _pausedProgressValue.clamp(0.0, 1.0);
        _progressController!.value = startValue;
        
        // ✅ İlerlemeyi devam ettir
        _progressController!.forward();
        
        // ✅ Kalan süre için timer oluştur
        _timer = Timer(Duration(seconds: remainingSeconds), () {
          if (mounted && !_isPausedByUser) {
            _nextStory();
          }
        });
        
        print('✅ Story resumed successfully - Controller value: ${_progressController!.value}, Timer set: ${remainingSeconds}s');
        
        // ✅ Video'yu devam ettir
        _videoPlayerKey.currentState?.playVideo();
      } else {
        // ✅ Süre dolmuşsa veya geçersizse bir sonraki hikayeye geç
        print('⚠️ Remaining seconds invalid ($remainingSeconds), moving to next story');
        _nextStory();
      }
    } else {
      print('⚠️ Resume called but story is not paused by user');
    }
  }

  String _formatTime(String? timeString) {
    if (timeString == null) return 'Şimdi';
    
    try {
      final time = DateTime.parse(timeString);
      final now = DateTime.now();
      final difference = now.difference(time);
      
      if (difference.inDays > 0) {
        return '${difference.inDays} gün önce';
      } else if (difference.inHours > 0) {
        return '${difference.inHours} saat önce';
      } else if (difference.inMinutes > 0) {
        return '${difference.inMinutes} dakika önce';
      } else {
        return 'Şimdi';
      }
    } catch (e) {
      return 'Şimdi';
    }
  }
}

class StoryVideoPlayer extends StatefulWidget {
  final String url;
  final Function(Duration?)? onVideoLoaded;
  final VoidCallback? onVideoCompleted;

  const StoryVideoPlayer({
    super.key,
    required this.url,
    this.onVideoLoaded,
    this.onVideoCompleted,
  });

  @override
  State<StoryVideoPlayer> createState() => _StoryVideoPlayerState();
}

class _StoryVideoPlayerState extends State<StoryVideoPlayer> {
  VideoPlayerController? _controller;
  bool _isInitialized = false;
  bool _hasError = false;

  @override
  void initState() {
    super.initState();
    _initializeVideo();
  }

  Future<void> _initializeVideo() async {
    try {
      print('Story Video URL: ${widget.url}');
      
      // ✅ Direkt URL'den streaming yap (indirme yok, daha hızlı)
      _controller = VideoPlayerController.networkUrl(
        Uri.parse(widget.url),
        videoPlayerOptions: VideoPlayerOptions(
          mixWithOthers: true,
          allowBackgroundPlayback: false,
        ),
      );
      
      // Event listener ekle
      _controller!.addListener(_videoListener);
      
      // Initialize
      await _controller!.initialize();
      
      if (mounted) {
        setState(() {
          _isInitialized = true;
        });
        
        // Otomatik oynatma başlat
        _controller!.play();
        // ✅ Video hikayeler tek seferlik oynatılmalı (loop yok)
        _controller!.setLooping(false);
        
        // ✅ Call onVideoLoaded callback with video duration
        widget.onVideoLoaded?.call(_controller!.value.duration);
      }
    } catch (e) {
      print('Story video initialization error: $e');
      if (mounted) {
        setState(() {
          _hasError = true;
        });
      }
    }
  }

  Future<http.Response> _downloadWithRetry(String url, {int maxRetries = 3}) async {
    int attempts = 0;
    Exception? lastException;
    
    while (attempts < maxRetries) {
      try {
        attempts++;
        print('Story download attempt $attempts/$maxRetries for URL: $url');
        
        final response = await http.get(
          Uri.parse(url),
          headers: {
            'Connection': 'keep-alive',
            'Cache-Control': 'no-cache',
          },
        ).timeout(
          const Duration(seconds: 30),
          onTimeout: () {
            throw Exception('Download timeout after 30 seconds');
          },
        );
        
        if (response.statusCode == 200) {
          print('Story download successful on attempt $attempts');
          return response;
        } else {
          throw Exception('HTTP ${response.statusCode}: ${response.reasonPhrase}');
        }
      } catch (e) {
        lastException = e is Exception ? e : Exception(e.toString());
        print('Story download attempt $attempts failed: $e');
        
        if (attempts < maxRetries) {
          // Exponential backoff: 1s, 2s, 4s
          final delay = Duration(seconds: attempts);
          print('Retrying story download in ${delay.inSeconds} seconds...');
          await Future.delayed(delay);
        }
      }
    }
    
    throw lastException ?? Exception('All story download attempts failed');
  }

  void _videoListener() {
    if (_controller != null) {
      if (_controller!.value.hasError) {
        print('Story video player error: ${_controller!.value.errorDescription}');
        if (mounted) {
          setState(() {
            _hasError = true;
          });
        }
      }
      
      // ✅ Video bittiğinde callback çağır
      if (_controller!.value.position >= _controller!.value.duration && 
          _controller!.value.duration.inSeconds > 0) {
        widget.onVideoCompleted?.call();
      }
    }
  }

  // ✅ Pause video from parent
  void pauseVideo() {
    if (_controller != null && _controller!.value.isPlaying) {
      _controller!.pause();
      print('⏸️ Video paused');
    }
  }

  // ✅ Resume video from parent
  void playVideo() {
    if (_controller != null && !_controller!.value.isPlaying) {
      _controller!.play();
      print('▶️ Video playing');
    }
  }

  @override
  void dispose() {
    _controller?.removeListener(_videoListener);
    _controller?.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    if (_hasError) {
      return Container(
        color: Colors.grey.shade900,
        child: const Center(
          child: Icon(
            Icons.videocam_off,
            color: Colors.white,
            size: 80,
          ),
        ),
      );
    }

    if (!_isInitialized || _controller == null) {
      return Container(
        color: Colors.grey.shade900,
        child: const Center(
          child: CircularProgressIndicator(
            color: Colors.white,
          ),
        ),
      );
    }

    return GestureDetector(
      onTap: () {
        if (_controller!.value.isPlaying) {
          _controller!.pause();
        } else {
          _controller!.play();
        }
      },
      child: Stack(
        alignment: Alignment.center,
        children: [
          AspectRatio(
            aspectRatio: _controller!.value.aspectRatio,
            child: VideoPlayer(_controller!),
          ),
          if (!_controller!.value.isPlaying)
            Container(
              decoration: BoxDecoration(
                color: Colors.black.withOpacity(0.3),
                shape: BoxShape.circle,
              ),
              child: const Icon(
                Icons.play_arrow,
                color: Colors.white,
                size: 60,
              ),
            ),
        ],
      ),
    );
  }
}