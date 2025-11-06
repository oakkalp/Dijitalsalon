import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:digimobil_new/widgets/robust_image_widget.dart';
import 'package:digimobil_new/widgets/comments_modal.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:video_player/video_player.dart';
import 'package:gal/gal.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:path_provider/path_provider.dart';
import 'package:http/http.dart' as http;
import 'dart:io';
import 'dart:typed_data';

class MediaViewerModal extends StatefulWidget {
  final List<Map<String, dynamic>> mediaList;
  final int initialIndex;
  final VoidCallback? onMediaUpdated; // ✅ Callback for media updates

  const MediaViewerModal({
    super.key,
    required this.mediaList,
    required this.initialIndex,
    this.onMediaUpdated,
  });

  @override
  State<MediaViewerModal> createState() => _MediaViewerModalState();
}

class _MediaViewerModalState extends State<MediaViewerModal> {
  late PageController _pageController;
  late int _currentIndex;
  VideoPlayerController? _videoController;
  bool _isVideoPlaying = false;
  final ApiService _apiService = ApiService();
  Map<String, dynamic>? _currentMedia;

  @override
  void initState() {
    super.initState();
    _currentIndex = widget.initialIndex;
    _currentMedia = widget.mediaList[widget.initialIndex];
    _pageController = PageController(initialPage: widget.initialIndex);
    _initializeVideo();
  }

  @override
  void dispose() {
    _pageController.dispose();
    _videoController?.dispose();
    super.dispose();
  }

  void _initializeVideo() {
    final media = widget.mediaList[_currentIndex];
    if (media['type'] == 'video') {
      final videoUrl = media['url'] ?? media['media_url'];
      if (videoUrl != null) {
        _videoController = VideoPlayerController.networkUrl(
          Uri.parse(videoUrl.startsWith('http') ? videoUrl : 'https://dijitalsalon.cagapps.app/$videoUrl'),
        );
        _videoController!.initialize().then((_) {
          setState(() {});
        });
      }
    }
  }

  void _onPageChanged(int index) {
    setState(() {
      _currentIndex = index;
      _currentMedia = widget.mediaList[index];
    });
    
    // Dispose previous video controller
    _videoController?.dispose();
    _videoController = null;
    
    // Initialize new video if needed
    _initializeVideo();
  }

  void _toggleVideoPlayPause() {
    if (_videoController != null) {
      if (_isVideoPlaying) {
        _videoController!.pause();
      } else {
        _videoController!.play();
      }
      setState(() {
        _isVideoPlaying = !_isVideoPlaying;
      });
    }
  }

  Future<void> _toggleLike() async {
    if (_currentMedia == null) return;
    
    final mediaId = _currentMedia!['id'];
    // ✅ Değişkenleri try bloğunun dışında tanımla
    final previousLikeState = _currentMedia!['is_liked'] ?? false;
    final previousLikesCount = _currentMedia!['likes'] ?? 0;
    
    try {
      // Optimistic update
      setState(() {
        _currentMedia!['is_liked'] = !previousLikeState;
        _currentMedia!['likes'] = previousLikesCount + (previousLikeState ? -1 : 1);
      });
      
      // ✅ Use toggleLike API for consistency
      final result = await _apiService.toggleLike(mediaId, previousLikeState);
      
      // ✅ API'den gelen güncel beğeni sayısını kullan
      if (result['likes_count'] != null) {
        setState(() {
          _currentMedia!['likes'] = result['likes_count'] as int;
          _currentMedia!['is_liked'] = result['is_liked'] as bool? ?? _currentMedia!['is_liked'];
        });
        
        // ✅ Widget'ın mediaList'ini de güncelle
        final mediaIndex = widget.mediaList.indexWhere((m) => m['id'] == mediaId);
        if (mediaIndex != -1) {
          widget.mediaList[mediaIndex]['likes'] = _currentMedia!['likes'];
          widget.mediaList[mediaIndex]['is_liked'] = _currentMedia!['is_liked'];
        }
      }
    } catch (e) {
      print('Media viewer like error: $e');
      
      // Rollback on error
      setState(() {
        _currentMedia!['is_liked'] = previousLikeState;
        _currentMedia!['likes'] = previousLikesCount;
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

  void _showComments() {
    if (_currentMedia == null) return;
    
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => CommentsModal(
        mediaId: _currentMedia!['id'],
        comments: [], // Empty list, will be loaded by the modal
        isLoading: false,
        onLoadComments: () {}, // Empty function
        onAddComment: (comment) {
          // ✅ Update comment count
          setState(() {
            if (comment.isEmpty) {
              // Yorum silindi
              _currentMedia!['comments'] = (_currentMedia!['comments'] ?? 0) - 1;
              if (_currentMedia!['comments'] < 0) _currentMedia!['comments'] = 0;
            } else {
              // Yorum eklendi
            _currentMedia!['comments'] = (_currentMedia!['comments'] ?? 0) + 1;
            }
            
            // ✅ Widget'ın mediaList'ini de güncelle
            final mediaIndex = widget.mediaList.indexWhere((m) => m['id'] == _currentMedia!['id']);
            if (mediaIndex != -1) {
              widget.mediaList[mediaIndex]['comments'] = _currentMedia!['comments'];
            }
          });
          
          // ✅ Parent widget'a bildir
          widget.onMediaUpdated?.call();
        },
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.close, color: Colors.white),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: Text(
          '${_currentIndex + 1} / ${widget.mediaList.length}',
          style: const TextStyle(color: Colors.white),
        ),
        centerTitle: true,
        actions: [
          // ✅ İndirme butonu (fotoğraf ve video için)
          if (_currentMedia != null)
            IconButton(
              icon: const Icon(Icons.download, color: Colors.white),
              onPressed: () => _downloadMedia(),
              tooltip: 'İndir',
            ),
        ],
      ),
      body: Stack(
        children: [
          // Media content
          PageView.builder(
            controller: _pageController,
            onPageChanged: _onPageChanged,
            itemCount: widget.mediaList.length,
            itemBuilder: (context, index) {
              final media = widget.mediaList[index];
              final mediaType = media['type'] ?? '';
              final isVideo = mediaType == 'video';
              final imageUrl = media['url'] ?? media['media_url'];

              return Center(
                child: GestureDetector(
                  onTap: () {
                    if (isVideo) {
                      _toggleVideoPlayPause();
                    }
                    // Don't close modal on tap for images
                  },
                  child: Container(
                    // ✅ Orijinal boyutta göster - constraints kaldırıldı
                    width: double.infinity,
                    height: double.infinity,
                    child: isVideo
                        ? _buildVideoPlayer()
                        : _buildImageViewer(imageUrl),
                  ),
                ),
              );
            },
          ),

          // Navigation arrows
          if (widget.mediaList.length > 1) ...[
            // Previous button
            if (_currentIndex > 0)
              Positioned(
                left: 20,
                top: MediaQuery.of(context).size.height / 2 - 30,
                child: GestureDetector(
                  onTap: () {
                    _pageController.previousPage(
                      duration: const Duration(milliseconds: 300),
                      curve: Curves.easeInOut,
                    );
                  },
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.black54,
                      borderRadius: BorderRadius.circular(25),
                    ),
                    child: const Icon(
                      Icons.chevron_left,
                      color: Colors.white,
                      size: 30,
                    ),
                  ),
                ),
              ),

            // Next button
            if (_currentIndex < widget.mediaList.length - 1)
              Positioned(
                right: 20,
                top: MediaQuery.of(context).size.height / 2 - 30,
                child: GestureDetector(
                  onTap: () {
                    _pageController.nextPage(
                      duration: const Duration(milliseconds: 300),
                      curve: Curves.easeInOut,
                    );
                  },
                  child: Container(
                    padding: const EdgeInsets.all(12),
                    decoration: BoxDecoration(
                      color: Colors.black54,
                      borderRadius: BorderRadius.circular(25),
                    ),
                    child: const Icon(
                      Icons.chevron_right,
                      color: Colors.white,
                      size: 30,
                    ),
                  ),
                ),
              ),
          ],

          // Video play/pause overlay
          if (widget.mediaList[_currentIndex]['type'] == 'video')
            Positioned.fill(
              child: GestureDetector(
                onTap: _toggleVideoPlayPause,
                child: Container(
                  color: Colors.transparent,
                  child: Center(
                    child: AnimatedOpacity(
                      opacity: _isVideoPlaying ? 0.0 : 1.0,
                      duration: const Duration(milliseconds: 300),
                      child: Container(
                        padding: const EdgeInsets.all(20),
                        decoration: BoxDecoration(
                          color: Colors.black54,
                          borderRadius: BorderRadius.circular(50),
                        ),
                        child: Icon(
                          _isVideoPlaying ? Icons.pause : Icons.play_arrow,
                          color: Colors.white,
                          size: 50,
                        ),
                      ),
                    ),
                  ),
                ),
              ),
            ),

          // Like and Comment buttons at bottom
          if (_currentMedia != null)
            Positioned(
              bottom: 50,
              left: 20,
              right: 20,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceEvenly,
                children: [
                  // Like button
                  GestureDetector(
                    onTap: _toggleLike,
                    child: Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.black54,
                        borderRadius: BorderRadius.circular(25),
                      ),
                      child: Row(
                        mainAxisSize: MainAxisSize.min,
                        children: [
                          Icon(
                            (_currentMedia!['is_liked'] ?? false) 
                                ? Icons.favorite 
                                : Icons.favorite_border,
                            color: (_currentMedia!['is_liked'] ?? false) 
                                ? Colors.red 
                                : Colors.white,
                            size: 24,
                          ),
                          const SizedBox(width: 8),
                          Text(
                            '${_currentMedia!['likes'] ?? 0}',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 16,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                  
                  // Comment button
                  GestureDetector(
                    onTap: _showComments,
                    child: Container(
                      padding: const EdgeInsets.all(12),
                      decoration: BoxDecoration(
                        color: Colors.black54,
                        borderRadius: BorderRadius.circular(25),
                      ),
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
                            '${_currentMedia!['comments'] ?? 0}',
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 16,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildImageViewer(String? imageUrl) {
    if (imageUrl == null) {
      return const Center(
        child: Icon(
          Icons.image_not_supported,
          color: Colors.white,
          size: 100,
        ),
      );
    }

    // ✅ Orijinal boyutta göster - tam ekran, zoom desteği
    return InteractiveViewer(
      minScale: 0.5,
      maxScale: 4.0,
      child: Center(
        child: RobustImageWidget(
      imageUrl: imageUrl.startsWith('http') ? imageUrl : 'https://dijitalsalon.cagapps.app/$imageUrl',
      fit: BoxFit.contain,
        ),
      ),
    );
  }

  Widget _buildVideoPlayer() {
    if (_videoController == null || !_videoController!.value.isInitialized) {
      return const Center(
        child: CircularProgressIndicator(
          color: Colors.white,
        ),
      );
    }

    return AspectRatio(
      aspectRatio: _videoController!.value.aspectRatio,
      child: VideoPlayer(_videoController!),
    );
  }
  
  Future<void> _downloadMedia() async {
    if (_currentMedia == null) return;
    
    final mediaUrl = _currentMedia!['url'] ?? _currentMedia!['media_url'];
    if (mediaUrl == null) return;
    
    final isVideo = _currentMedia!['type'] == 'video' || 
                   mediaUrl.toLowerCase().endsWith('.mp4') ||
                   mediaUrl.toLowerCase().endsWith('.mov') ||
                   mediaUrl.toLowerCase().endsWith('.avi') ||
                   mediaUrl.toLowerCase().endsWith('.mkv');
    
    try {
      // ✅ İzin kontrolü - Android versiyonuna göre
      bool hasPermission = false;
      
      if (Platform.isAndroid) {
        // Android için önce photos/videos izinlerini dene, sonra storage
        if (isVideo) {
          // Video için önce videos, sonra photos, son olarak storage
          var videosStatus = await Permission.videos.status;
          if (!videosStatus.isGranted) {
            videosStatus = await Permission.videos.request();
          }
          
          if (videosStatus.isGranted) {
            hasPermission = true;
          } else {
            // Videos izni yok, photos dene
            var photosStatus = await Permission.photos.status;
            if (!photosStatus.isGranted) {
              photosStatus = await Permission.photos.request();
            }
            
            if (photosStatus.isGranted) {
              hasPermission = true;
            } else {
              // Photos izni de yok, storage dene
              var storageStatus = await Permission.storage.status;
              if (!storageStatus.isGranted) {
                storageStatus = await Permission.storage.request();
              }
              hasPermission = storageStatus.isGranted;
            }
          }
        } else {
          // Fotoğraf için önce photos, sonra storage
          var photosStatus = await Permission.photos.status;
          if (!photosStatus.isGranted) {
            photosStatus = await Permission.photos.request();
          }
          
          if (photosStatus.isGranted) {
            hasPermission = true;
          } else {
            // Photos izni yok, storage dene
            var storageStatus = await Permission.storage.status;
            if (!storageStatus.isGranted) {
              storageStatus = await Permission.storage.request();
            }
            hasPermission = storageStatus.isGranted;
          }
        }
      } else if (Platform.isIOS) {
        // iOS için photos izni
        var photosStatus = await Permission.photos.status;
        if (!photosStatus.isGranted) {
          photosStatus = await Permission.photos.request();
        }
        hasPermission = photosStatus.isGranted;
      }
      
      if (!hasPermission) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Galeri erişim izni gereklidir. Ayarlardan izin verin.'),
              backgroundColor: AppColors.error,
              duration: Duration(seconds: 3),
            ),
          );
        }
        return;
      }
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('İndiriliyor...'),
            backgroundColor: AppColors.info,
            duration: Duration(seconds: 1),
          ),
        );
      }
      
      // ✅ Dosyayı indir
      final fullUrl = mediaUrl.startsWith('http') ? mediaUrl : 'https://dijitalsalon.cagapps.app/$mediaUrl';
      final response = await http.get(Uri.parse(fullUrl));
      
      if (response.statusCode == 200) {
        // ✅ Galeriye kaydet
        Map<String, dynamic> result;
        
        if (isVideo) {
          // ✅ Video için geçici dosya oluştur
          final tempDir = await getTemporaryDirectory();
          final fileName = 'digital_salon_${_currentMedia!['id']}_${DateTime.now().millisecondsSinceEpoch}.mp4';
          final filePath = '${tempDir.path}/$fileName';
          final file = File(filePath);
          await file.writeAsBytes(response.bodyBytes);
          
          // ✅ Videoyu galeriye kaydet - gal paketi kullan (Android 13+ uyumlu)
          try {
            await Gal.putVideo(filePath);
            result = {'isSuccess': true};
          } catch (galError) {
            // Fallback: gal paketi olmadıysa hata göster
            print('Gal.putVideo error: $galError');
            result = {'isSuccess': false, 'error': galError.toString()};
          }
        } else {
          // ✅ Fotoğraf için direkt kaydet - gal paketi kullan (Android 13+ uyumlu)
          try {
            await Gal.putImageBytes(
              response.bodyBytes,
              name: 'digital_salon_${_currentMedia!['id']}_${DateTime.now().millisecondsSinceEpoch}',
            );
            result = {'isSuccess': true};
          } catch (galError) {
            // Fallback: gal paketi olmadıysa hata göster
            print('Gal.putImageBytes error: $galError');
            result = {'isSuccess': false, 'error': galError.toString()};
          }
        }
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(
              content: Text(result['isSuccess'] == true 
                  ? '${isVideo ? "Video" : "Fotoğraf"} galerinize kaydedildi' 
                  : 'İndirme başarısız'),
              backgroundColor: result['isSuccess'] == true ? AppColors.success : AppColors.error,
              duration: const Duration(seconds: 2),
            ),
          );
        }
      } else {
        throw Exception('Dosya indirilemedi: ${response.statusCode}');
      }
    } catch (e) {
      print('Download error: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('İndirme başarısız: $e'),
            backgroundColor: AppColors.error,
            duration: const Duration(seconds: 3),
          ),
        );
      }
    }
  }
}
