import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:digimobil_new/widgets/robust_image_widget.dart';
import 'package:digimobil_new/widgets/comments_modal.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:video_player/video_player.dart';

class MediaViewerModal extends StatefulWidget {
  final List<Map<String, dynamic>> mediaList;
  final int initialIndex;

  const MediaViewerModal({
    super.key,
    required this.mediaList,
    required this.initialIndex,
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
          Uri.parse(videoUrl.startsWith('http') ? videoUrl : 'http://192.168.1.137/dijitalsalon/$videoUrl'),
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
    
    try {
      final mediaId = _currentMedia!['id'];
      final isLiked = _currentMedia!['is_liked'] ?? false;
      
      if (isLiked) {
        await _apiService.unlikeMedia(mediaId);
      } else {
        await _apiService.likeMedia(mediaId);
      }
      
      setState(() {
        _currentMedia!['is_liked'] = !isLiked;
        _currentMedia!['likes'] = (_currentMedia!['likes'] ?? 0) + (isLiked ? -1 : 1);
      });
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Beğeni işlemi başarısız: $e'),
          backgroundColor: AppColors.error,
        ),
      );
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
          // Update comment count
          setState(() {
            _currentMedia!['comments'] = (_currentMedia!['comments'] ?? 0) + 1;
          });
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
                    constraints: BoxConstraints(
                      maxHeight: MediaQuery.of(context).size.height * 0.8,
                      maxWidth: MediaQuery.of(context).size.width,
                    ),
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

    return RobustImageWidget(
      imageUrl: imageUrl.startsWith('http') ? imageUrl : 'http://192.168.1.137/dijitalsalon/$imageUrl',
      fit: BoxFit.contain,
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
}
