import 'package:flutter/material.dart';
import 'package:flutter/cupertino.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/constants.dart';
import 'package:digimobil_new/models/user.dart';
import 'package:video_player/video_player.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:http/http.dart' as http;
import 'dart:io';
import 'package:path_provider/path_provider.dart';

class PostCard extends StatefulWidget {
  final Map<String, dynamic> media;
  final VoidCallback? onLike;
  final VoidCallback? onComment;
  final VoidCallback? onShare;
  final VoidCallback? onBookmark;
  final VoidCallback? onEdit;
  final VoidCallback? onDelete;
  final User? currentUser;

  const PostCard({
    super.key,
    required this.media,
    this.onLike,
    this.onComment,
    this.onShare,
    this.onBookmark,
    this.onEdit,
    this.onDelete,
    this.currentUser,
  });

  @override
  State<PostCard> createState() => _PostCardState();
}

class _PostCardState extends State<PostCard> {
  ValueNotifier<bool> expandListener = ValueNotifier<bool>(false);
  bool _isLiked = false;
  int _likesCount = 0;

  @override
  void initState() {
    super.initState();
    _isLiked = widget.media['is_liked'] ?? false;
    _likesCount = widget.media['likes'] ?? 0;
  }

  @override
  void dispose() {
    expandListener.dispose();
    super.dispose();
  }

  void _toggleLike() {
    setState(() {
      _isLiked = !_isLiked;
      _likesCount += _isLiked ? 1 : -1;
    });
    widget.onLike?.call();
  }

  bool _canModifyMedia() {
    if (widget.currentUser == null) return false;
    
    // Convert to same type for comparison
    final currentUserId = widget.currentUser!.id.toString();
    final mediaUserId = widget.media['user_id'].toString();
    
    // Own media - herkes kendi içeriğini düzenleyebilir
    if (mediaUserId == currentUserId) {
      return true;
    }
    
    // Yetkili kullanıcılar - herkesin içeriğini düzenleyebilir
    if (widget.currentUser!.role == 'yetkili_kullanici' || 
        widget.currentUser!.role == 'moderator' || 
        widget.currentUser!.role == 'admin') {
      return true;
    }
    
    return false;
  }

  @override
  Widget build(BuildContext context) {
    final description = widget.media['description'] ?? '';
    final shouldShowExpand = description.length > 50;

    return Container(
      padding: const EdgeInsets.only(top: 10, left: 10, right: 10, bottom: 30),
      decoration: BoxDecoration(
        color: AppColors.surface.withOpacity(.4),
        borderRadius: BorderRadius.circular(30),
        border: Border.all(
          color: AppColors.border.withOpacity(0.3),
          width: 1,
        ),
      ),
      child: Column(
        mainAxisSize: MainAxisSize.min,
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Media
          ClipRRect(
            borderRadius: BorderRadius.circular(30),
            child: AspectRatio(
              aspectRatio: 1,
              child: widget.media['type'] == 'video'
                  ? PostVideoPlayer(url: widget.media['url'])
                  : widget.media['url'] != null
                      ? Image.network(
                          widget.media['url'],
                          fit: BoxFit.cover,
                          width: double.infinity,
                          height: double.infinity,
                          errorBuilder: (context, error, stackTrace) {
                            return Container(
                              color: AppColors.primary.withOpacity(0.1),
                              child: const Center(
                                child: Icon(
                                  Icons.image,
                                  size: 50,
                                  color: AppColors.primary,
                                ),
                              ),
                            );
                          },
                        )
                      : Container(
                          color: AppColors.primary.withOpacity(0.1),
                          child: const Center(
                            child: Icon(
                              Icons.image,
                              size: 50,
                              color: AppColors.primary,
                            ),
                          ),
                        ),
            ),
          ),

          // User info
          Padding(
            padding: const EdgeInsets.all(12.0),
            child: Row(
              children: [
                CircleAvatar(
                  radius: 16,
                  backgroundImage: widget.media['user_avatar'] != null
                      ? NetworkImage(widget.media['user_avatar'])
                      : null,
                  child: widget.media['user_avatar'] == null
                      ? const Icon(Icons.person, size: 16, color: AppColors.textPrimary)
                      : null,
                ),
                const SizedBox(width: 8),
                Expanded(
                  child: Text(
                    widget.media['user_name'] ?? 'Bilinmeyen Kullanıcı',
                    style: const TextStyle(
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                      fontSize: 14,
                    ),
                  ),
                ),
              ],
            ),
          ),
          
          // Action Buttons
          SizedBox(
            height: 70,
            child: Row(
              children: [
                IconButton(
                  onPressed: _toggleLike,
                  icon: Icon(
                    _isLiked ? CupertinoIcons.heart_fill : CupertinoIcons.heart,
                    color: _isLiked ? Colors.red : AppColors.textSecondary,
                    size: 30,
                  ),
                ),
                IconButton(
                  onPressed: widget.onComment,
                  icon: Row(
                    mainAxisSize: MainAxisSize.min,
                    children: [
                      const Icon(
                        CupertinoIcons.chat_bubble_2,
                        size: 30,
                        color: AppColors.textSecondary,
                      ),
                      if ((widget.media['comments'] ?? 0) > 0) ...[
                        const SizedBox(width: 4),
                        Text(
                          '${widget.media['comments']}',
                          style: const TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 12,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ],
                  ),
                ),
                IconButton(
                  onPressed: widget.onShare,
                  icon: const Icon(
                    Icons.share,
                    size: 30,
                    color: AppColors.textSecondary,
                  ),
                ),
                const Spacer(),
                if (_canModifyMedia())
                  PopupMenuButton<String>(
                    icon: const Icon(
                      Icons.more_vert,
                      color: AppColors.textSecondary,
                      size: 30,
                    ),
                    onSelected: (value) {
                      switch (value) {
                        case 'edit':
                          widget.onEdit?.call();
                          break;
                        case 'delete':
                          widget.onDelete?.call();
                          break;
                      }
                    },
                    itemBuilder: (context) => [
                      const PopupMenuItem(
                        value: 'edit',
                        child: Row(
                          children: [
                            Icon(Icons.edit, color: AppColors.textPrimary),
                            SizedBox(width: 8),
                            Text('Düzenle', style: TextStyle(color: AppColors.textPrimary)),
                          ],
                        ),
                      ),
                      const PopupMenuItem(
                        value: 'delete',
                        child: Row(
                          children: [
                            Icon(Icons.delete, color: AppColors.error),
                            SizedBox(width: 8),
                            Text('Sil', style: TextStyle(color: AppColors.error)),
                          ],
                        ),
                      ),
                    ],
                  ),
                IconButton(
                  onPressed: widget.onBookmark,
                  icon: const Icon(
                    CupertinoIcons.bookmark,
                    color: AppColors.textSecondary,
                  ),
                ),
              ],
            ),
          ),
          
          // Likes Count
          if (_likesCount > 0)
            Padding(
              padding: const EdgeInsets.only(left: 16, bottom: 8),
              child: Text(
                '$_likesCount beğeni',
                style: const TextStyle(
                  fontWeight: FontWeight.w600,
                  color: AppColors.textPrimary,
                ),
              ),
            ),
          
          // Comments Count
          if ((widget.media['comments'] ?? 0) > 0)
            Padding(
              padding: const EdgeInsets.only(left: 16, bottom: 8),
              child: GestureDetector(
                onTap: widget.onComment,
                child: Text(
                  '${widget.media['comments']} yorumu görüntüle',
                  style: const TextStyle(
                    fontWeight: FontWeight.w600,
                    color: AppColors.textSecondary,
                  ),
                ),
              ),
            ),
          
          // Caption
          if (description.isNotEmpty)
            ValueListenableBuilder<bool>(
              valueListenable: expandListener,
              builder: (context, value, _) {
                return GestureDetector(
                  onTap: shouldShowExpand
                      ? () {
                          expandListener.value = !value;
                        }
                      : null,
                  child: Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16),
                    child: Text.rich(
                      TextSpan(
                        text: widget.media['user_name'] ?? 'Kullanıcı',
                        style: const TextStyle(
                          fontWeight: FontWeight.w900,
                          color: AppColors.textPrimary,
                        ),
                        children: [
                          TextSpan(
                            text: shouldShowExpand && !value
                                ? " ${description.substring(0, 50)}"
                                : " $description",
                            style: const TextStyle(
                              fontWeight: FontWeight.normal,
                              color: AppColors.textSecondary,
                            ),
                          ),
                          if (shouldShowExpand)
                            TextSpan(
                              text: value ? "" : " ...daha fazla",
                              style: const TextStyle(
                                fontWeight: FontWeight.w200,
                                color: AppColors.textTertiary,
                              ),
                            ),
                        ],
                      ),
                    ),
                  ),
                );
              },
            ),
        ],
      ),
    );
  }
}

class PostVideoPlayer extends StatefulWidget {
  final String? url;

  const PostVideoPlayer({super.key, required this.url});

  @override
  State<PostVideoPlayer> createState() => _PostVideoPlayerState();
}

class _PostVideoPlayerState extends State<PostVideoPlayer> {
  VideoPlayerController? _controller;
  bool _isInitialized = false;
  bool _hasError = false;
  String? _errorMessage;

  @override
  void initState() {
    super.initState();
    if (widget.url != null) {
      _initializeVideo();
    }
  }

  Future<void> _initializeVideo() async {
    try {
      print('Post Video URL: ${widget.url}');
      
      // URL'yi temizle ve kontrol et
      String cleanUrl = widget.url!.trim();
      if (!cleanUrl.startsWith('http://') && !cleanUrl.startsWith('https://')) {
        throw Exception('Invalid URL format');
      }
      
      // Video dosyasını local olarak indir
      final tempDir = await getTemporaryDirectory();
      final fileName = 'post_video_${DateTime.now().millisecondsSinceEpoch}.mp4';
      final localFile = File('${tempDir.path}/$fileName');
      
      print('Downloading post video to: ${localFile.path}');
      
      // Video dosyasını indir
      final response = await http.get(Uri.parse(cleanUrl));
      if (response.statusCode == 200) {
        await localFile.writeAsBytes(response.bodyBytes);
        print('Post video downloaded successfully: ${localFile.path}');
        
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
          
          // Otomatik oynatma başlat, sonra durdur
          _controller!.play();
          _controller!.setLooping(true);
          
          // 3 saniye sonra durdur
          Future.delayed(const Duration(seconds: 3), () {
            if (mounted && _controller != null) {
              _controller!.pause();
            }
          });
        }
      } else {
        throw Exception('Failed to download post video: ${response.statusCode}');
      }
    } catch (e) {
      print('Post video initialization error: $e');
      if (mounted) {
        setState(() {
          _hasError = true;
          _errorMessage = e.toString();
        });
      }
    }
  }

  void _videoListener() {
    if (_controller != null) {
      if (_controller!.value.hasError) {
        print('Post video player error: ${_controller!.value.errorDescription}');
        if (mounted) {
          setState(() {
            _hasError = true;
            _errorMessage = _controller!.value.errorDescription;
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
    if (widget.url == null) {
      return Container(
        color: AppColors.primary.withOpacity(0.1),
        child: const Center(
          child: Icon(
            Icons.play_circle_outline,
            size: 50,
            color: AppColors.primary,
          ),
        ),
      );
    }

    if (_hasError) {
      return GestureDetector(
        onTap: () {
          // Retry video initialization
          _hasError = false;
          _isInitialized = false;
          _errorMessage = null;
          _initializeVideo();
        },
        child: Container(
          color: AppColors.primary.withOpacity(0.1),
          child: const Center(
            child: Column(
              mainAxisAlignment: MainAxisAlignment.center,
              children: [
                Icon(
                  Icons.refresh,
                  size: 50,
                  color: AppColors.primary,
                ),
                SizedBox(height: 8),
                Text(
                  'Video Yüklenemedi',
                  style: TextStyle(
                    color: AppColors.primary,
                    fontSize: 12,
                  ),
                ),
                SizedBox(height: 4),
                Text(
                  'Dokunarak tekrar dene',
                  style: TextStyle(
                    color: AppColors.primary,
                    fontSize: 10,
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    if (!_isInitialized || _controller == null) {
      return Container(
        color: AppColors.primary.withOpacity(0.1),
        child: const Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              CircularProgressIndicator(
                color: AppColors.primary,
              ),
              SizedBox(height: 8),
              Text(
                'Video yükleniyor...',
                style: TextStyle(
                  color: AppColors.primary,
                  fontSize: 12,
                ),
              ),
            ],
          ),
        ),
      );
    }

           return GestureDetector(
             onTap: () {
               // Tap to play/pause
               if (_controller!.value.isPlaying) {
                 _controller!.pause();
               } else {
                 _controller!.play();
               }
             },
      child: Container(
        color: Colors.black,
        child: Stack(
          children: [
            // Video player
            Center(
              child: AspectRatio(
                aspectRatio: _controller!.value.aspectRatio,
                child: VideoPlayer(_controller!),
              ),
            ),
                   // Play/Pause overlay
                   if (!_controller!.value.isPlaying)
                     Center(
                       child: Container(
                         decoration: BoxDecoration(
                           color: Colors.black54,
                           shape: BoxShape.circle,
                         ),
                         padding: const EdgeInsets.all(15),
                         child: const Icon(
                           Icons.play_arrow,
                           size: 40,
                           color: Colors.white,
                         ),
                       ),
                     ),
            // Loading indicator (buffering)
            if (_controller!.value.isBuffering)
              const Center(
                child: CircularProgressIndicator(
                  color: Colors.white,
                ),
              ),
          ],
        ),
      ),
    );
  }
}
