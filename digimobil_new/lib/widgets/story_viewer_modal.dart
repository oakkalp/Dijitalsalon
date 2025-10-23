import 'package:flutter/material.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'dart:async';
import 'package:video_player/video_player.dart';
import 'package:path_provider/path_provider.dart';
import 'package:http/http.dart' as http;
import 'dart:io';
import 'package:digimobil_new/widgets/robust_image_widget.dart';
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
  
  final ApiService _apiService = ApiService();

  @override
  void initState() {
    super.initState();
    _currentStoryIndex = widget.initialIndex;
    _pageController = PageController(initialPage: _currentStoryIndex);
    
    // Progress controller will be created in _startProgress with correct duration
    _startProgress();
  }

  @override
  void dispose() {
    _timer?.cancel();
    _progressController?.dispose();
    _pageController.dispose();
    super.dispose();
  }

  void _startProgress() {
    if (_isPlaying && _isImageLoaded) {
      final currentStory = widget.stories[_currentStoryIndex];
      final isVideo = currentStory['media_type'] == 'video' || currentStory['type'] == 'video';
      final duration = isVideo ? 59 : 24; // Video: 59s, Photo: 24s
      
      _progressController = AnimationController(
        duration: Duration(seconds: duration),
        vsync: this,
      );
      
      _progressAnimation = Tween<double>(
        begin: 0.0,
        end: 1.0,
      ).animate(_progressController!);
      
      _progressController!.forward();
      _timer = Timer(Duration(seconds: duration), () {
        _nextStory();
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
      setState(() {
        _currentStoryIndex++;
        _isImageLoaded = false; // Reset loading state
      });
      _pageController.nextPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
      _resetProgress();
    } else {
      Navigator.pop(context);
    }
  }

  void _previousStory() {
    if (_currentStoryIndex > 0) {
      setState(() {
        _currentStoryIndex--;
        _isImageLoaded = false; // Reset loading state
      });
      _pageController.previousPage(
        duration: const Duration(milliseconds: 300),
        curve: Curves.easeInOut,
      );
      _resetProgress();
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
          final tapPosition = details.globalPosition.dx;
          
          if (tapPosition < screenWidth / 2) {
            _previousStory();
          } else {
            _nextStory();
          }
        },
        onLongPressStart: (_) {
          _togglePlayPause();
        },
        onLongPressEnd: (_) {
          _togglePlayPause();
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
    final isVideo = story['media_type'] == 'video' || story['type'] == 'video';
    final mediaUrl = story['url'] ?? story['media_url'];
    
    return Container(
      width: double.infinity,
      height: double.infinity,
      child: mediaUrl != null
          ? isVideo
              ? _buildVideoContent(mediaUrl)
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

  Widget _buildVideoContent(String videoUrl) {
    return StoryVideoPlayer(
      url: videoUrl,
      onVideoLoaded: () {
        if (!_isImageLoaded) {
          setState(() {
            _isImageLoaded = true;
          });
          _startProgress();
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
          Container(
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
          const SizedBox(width: 16),
          // Comment button - Like old theme style
          Container(
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
              Navigator.pop(context);
              
              try {
                final success = await _apiService.editStory(
                  story['id'], 
                  captionController.text
                );
                
                if (success) {
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
                } else {
                  throw Exception('Failed to update story');
                }
              } catch (e) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text('Hikaye güncellenirken hata: $e'),
                    backgroundColor: AppColors.error,
                  ),
                );
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
              Navigator.pop(context);
              
              try {
                final result = await _apiService.deleteStory(story['id']);
                
                if (result['success'] == true) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Hikaye başarıyla silindi'),
                      backgroundColor: AppColors.success,
                    ),
                  );
                  
                  // Close story viewer and notify parent
                  Navigator.pop(context, 'story_deleted');
                } else {
                  throw Exception(result['error'] ?? 'Failed to delete story');
                }
              } catch (e) {
                ScaffoldMessenger.of(context).showSnackBar(
                  SnackBar(
                    content: Text('Hikaye silinirken hata: $e'),
                    backgroundColor: AppColors.error,
                  ),
                );
              }
            },
            child: const Text('Sil', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
  }

  void _toggleStoryLike(Map<String, dynamic> story) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Hikaye beğenme özelliği yakında eklenecek'),
        backgroundColor: AppColors.info,
      ),
    );
  }

  void _showStoryComments(Map<String, dynamic> story) {
    ScaffoldMessenger.of(context).showSnackBar(
      const SnackBar(
        content: Text('Hikaye yorumları özelliği yakında eklenecek'),
        backgroundColor: AppColors.info,
      ),
    );
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
  final VoidCallback? onVideoLoaded;

  const StoryVideoPlayer({super.key, required this.url, this.onVideoLoaded});

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
      
      // Video dosyasını local olarak indir
      final tempDir = await getTemporaryDirectory();
      final fileName = 'story_video_${DateTime.now().millisecondsSinceEpoch}.mp4';
      final localFile = File('${tempDir.path}/$fileName');
      
      print('Downloading story video to: ${localFile.path}');
      
      // Video dosyasını indir (retry mekanizması ile)
      final response = await _downloadWithRetry(widget.url, maxRetries: 3);
      if (response.statusCode == 200) {
        await localFile.writeAsBytes(response.bodyBytes);
        
        print('Story video downloaded successfully: ${localFile.path}');
        
        // Local dosyayı oynat
        _controller = VideoPlayerController.file(
          localFile,
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
          _controller!.setLooping(true);
          
          // Call onVideoLoaded callback
          widget.onVideoLoaded?.call();
        }
      } else {
        throw Exception('Failed to download story video: ${response.statusCode}');
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