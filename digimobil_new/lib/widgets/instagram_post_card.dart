import 'package:flutter/material.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:digimobil_new/widgets/comments_modal.dart';
import 'package:digimobil_new/widgets/media_viewer_modal.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:provider/provider.dart';
import 'package:video_player/video_player.dart';
import 'package:path_provider/path_provider.dart';
import 'package:http/http.dart' as http;
import 'dart:io';
import 'package:intl/intl.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/widgets/robust_image_widget.dart';
import 'package:digimobil_new/widgets/aspect_ratio_image.dart';
import 'package:flutter/cupertino.dart';

class InstagramPostCard extends StatefulWidget {
  final Event event;
  final Map<String, dynamic>? post;
  final VoidCallback onTap;
  final VoidCallback? onMediaDeleted;
  final VoidCallback? onCommentCountChanged; // Add callback for comment count changes
  final List<Map<String, dynamic>>? allMediaList; // ✅ Tüm medya listesi (tam ekran görüntüleyici için)

  const InstagramPostCard({
    super.key,
    required this.event,
    this.post,
    required this.onTap,
    this.onMediaDeleted,
    this.onCommentCountChanged,
    this.allMediaList, // ✅ Tüm medya listesi
  });

  @override
  State<InstagramPostCard> createState() => _InstagramPostCardState();
}

class _InstagramPostCardState extends State<InstagramPostCard> {
  bool _isLiked = false;
  int _likesCount = 0;
  int _commentsCount = 0; // Add local comment count
  final ApiService _apiService = ApiService();
  final TextEditingController _commentController = TextEditingController(); // Add comment controller

  @override
  void initState() {
    super.initState();
    // Initialize like status from post data
    if (widget.post != null) {
      _likesCount = widget.post!['likes'] ?? 0;
      _isLiked = widget.post!['is_liked'] ?? false;
      _commentsCount = widget.post!['comments'] ?? 0; // Initialize comment count
    } else {
      _likesCount = widget.event.mediaCount;
    }
    
    // Add listener to comment controller
    _commentController.addListener(() {
      setState(() {}); // Rebuild when text changes
    });
  }

  @override
  void dispose() {
    _commentController.dispose();
    super.dispose();
  }

  @override
  void didUpdateWidget(InstagramPostCard oldWidget) {
    super.didUpdateWidget(oldWidget);
    
    // ✅ Widget.post değiştiğinde local state'i güncelle
    if (widget.post != null && oldWidget.post != null) {
      if (widget.post!['id'] == oldWidget.post!['id']) {
        // Aynı medya ama veriler güncellenmiş
        _likesCount = widget.post!['likes'] ?? 0;
        _isLiked = widget.post!['is_liked'] ?? false;
        _commentsCount = widget.post!['comments'] ?? 0;
        
        print('📱 InstagramPostCard updated: Media ${widget.post!['id']}, Comments: ${widget.post!['comments']}, Likes: ${widget.post!['likes']}');
      }
    }
  }

  void _addComment() async {
    if (_commentController.text.trim().isEmpty) return;
    
    final mediaId = widget.post?['id'] ?? widget.post?['media_id'] ?? 0;
    if (mediaId == 0) return;

    try {
      final result = await _apiService.addComment(mediaId, _commentController.text.trim());
      
      if (result['success'] == true) {
        setState(() {
          _commentsCount++;
          _commentController.clear();
        });
        
        // Notify parent widget about comment count change
        widget.onCommentCountChanged?.call();
        
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(
              content: Text('Yorum eklendi'),
              backgroundColor: AppColors.success,
            ),
          );
        }
      } else {
        throw Exception(result['error'] ?? 'Yorum eklenemedi');
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Yorum eklenirken hata: $e'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  void _showCommentsModal() {
    final mediaId = widget.post?['id'] ?? widget.post?['media_id'] ?? 0;
    final currentUser = Provider.of<AuthProvider>(context, listen: false).user;
    
    if (mediaId == 0) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Medya ID bulunamadı'),
            backgroundColor: AppColors.error,
          ),
        );
      }
      return;
    }
    
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => CommentsModal(
        mediaId: mediaId,
        comments: [],
        isLoading: false,
        onLoadComments: () {},
        onAddComment: (comment) {
          // Notify parent widget about comment count change
          widget.onCommentCountChanged?.call();
        },
        currentUser: currentUser,
        event: widget.event, // ✅ Event parametresi eklendi
      ),
    );
  }

  void _editMedia() {
    final mediaId = widget.post?['id'] ?? widget.post?['media_id'] ?? 0;
    if (mediaId == 0) return;

    if (mounted) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Medya düzenleme özelliği yakında eklenecek'),
          backgroundColor: AppColors.info,
        ),
      );
    }
  }

  void _deleteMedia() {
    final mediaId = widget.post?['id'] ?? widget.post?['media_id'] ?? 0;
    if (mediaId == 0) return;

    // Debug logging
    print('Delete Media - Post Data: ${widget.post}');
    print('Delete Media - Media ID: $mediaId');

    // ✅ ScaffoldMessenger reference'ı kaydet
    final scaffoldMessenger = ScaffoldMessenger.of(context);

    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Medyayı Sil'),
        content: const Text('Bu medyayı silmek istediğinizden emin misiniz?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('İptal'),
          ),
          TextButton(
            onPressed: () async {
              Navigator.pop(context);
              
              try {
                print('Delete Media - Calling API with ID: $mediaId');
                final result = await _apiService.deleteMedia(mediaId);
                
                if (result['success'] == true) {
                  // ✅ mounted kontrolü ile güvenli ScaffoldMessenger kullanımı
                  if (mounted) {
                    scaffoldMessenger.showSnackBar(
                      const SnackBar(
                        content: Text('Medya başarıyla silindi'),
                        backgroundColor: AppColors.success,
                      ),
                    );
                  }
                  
                  // Call media deletion callback
                  widget.onMediaDeleted?.call();
                } else {
                  throw Exception(result['error'] ?? 'Failed to delete media');
                }
              } catch (e) {
                print('Delete Media - Error: $e');
                // ✅ mounted kontrolü ile güvenli ScaffoldMessenger kullanımı
                if (mounted) {
                  scaffoldMessenger.showSnackBar(
                    SnackBar(
                      content: Text('Medya silinirken hata: $e'),
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

  @override
  Widget build(BuildContext context) {
    // If post is provided, show post content, otherwise show event info
    if (widget.post != null) {
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildPostHeader(),
          _buildPostImage(),
          _buildPostActions(),
          _buildLikesCount(),
          const SizedBox(height: 5),
          _buildPostInfo(),
          const SizedBox(height: 5),
          _buildComments(),
          const SizedBox(height: 10),
          _buildAddComment(),
          const SizedBox(height: 5),
          _buildPostTime(),
        ],
      );
    } else {
      // Show event as post
      return Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          _buildPostHeader(),
          _buildPostImage(),
          _buildPostActions(),
          _buildLikesCount(),
          const SizedBox(height: 5),
          _buildEventInfo(),
          const SizedBox(height: 5),
          _buildComments(),
          const SizedBox(height: 10),
          _buildAddComment(),
          const SizedBox(height: 5),
          _buildEventTime(),
        ],
      );
    }
  }

  Widget _buildPostHeader() {
    final userName = widget.post?['user_name'] ?? widget.event.title;
    final userAvatar = widget.post?['user_avatar'];
    
    return Padding(
      padding: const EdgeInsets.symmetric(vertical: 10, horizontal: 15),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              CircleAvatar(
                radius: 20,
                backgroundColor: AppColors.primary.withOpacity(0.1),
                backgroundImage: userAvatar != null 
                    ? NetworkImage(userAvatar) 
                    : null,
                child: userAvatar == null 
                    ? const Icon(
                        Icons.person,
                        color: AppColors.primary,
                        size: 20,
                      )
                    : null,
              ),
              const SizedBox(width: 15),
              Text(
                userName,
                style: const TextStyle(
                  color: Colors.black,
                  fontSize: 15,
                  fontWeight: FontWeight.w600,
                ),
              ),
            ],
          ),
          // ✅ Yetki kontrolü ile 3 nokta menüsü
          _canEditOrDeleteMedia() 
            ? PopupMenuButton<String>(
                onSelected: (value) {
                  if (value == 'edit') {
                    _editMedia();
                  } else if (value == 'delete') {
                    _deleteMedia();
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
                child: const Icon(Icons.more_vert, color: Colors.black),
              )
            : const SizedBox.shrink(), // ✅ Yetki yoksa hiçbir şey gösterme
        ],
      ),
    );
  }

  // ✅ Medya düzenleme/silme yetkisi kontrolü
  bool _canEditOrDeleteMedia() {
    final currentUser = Provider.of<AuthProvider>(context, listen: false).user;
    final userRole = widget.event.userRole;
    final userPermissions = widget.event.userPermissions;
    
    // Kendi medyası mı kontrol et
    final isOwnMedia = widget.post?['user_id'] == currentUser?.id;
    
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
    
    // Kendi medyası ise düzenleyebilir/silebilir
    if (isOwnMedia) {
      return true;
    }
    
    return false;
  }
  
  Widget _buildPostImage() {
    final imageUrl = widget.post?['url'] ?? widget.post?['media_url'] ?? widget.event.coverPhoto;
    final isVideo = widget.post?['media_type'] == 'video' || widget.post?['type'] == 'video';
    
    // ✅ Video için thumbnail URL'sini al
    final thumbnailUrl = widget.post?['thumbnail'] ?? widget.post?['preview'];
    
    // Check if URL is a video file
    final isVideoFile = imageUrl != null && (imageUrl.toLowerCase().endsWith('.mp4') || 
                                            imageUrl.toLowerCase().endsWith('.mov') || 
                                            imageUrl.toLowerCase().endsWith('.avi') ||
                                            imageUrl.toLowerCase().endsWith('.mkv'));
    
    return GestureDetector(
      onTap: () {
        // ✅ Medya tam ekran görüntüleyici aç
        if (widget.post != null) {
          // ✅ Tüm medya listesi varsa onu kullan, yoksa sadece bu post'u göster
          final mediaList = widget.allMediaList ?? [widget.post!];
          final index = mediaList.indexWhere((m) => m['id'] == widget.post!['id']);
          final initialIndex = index != -1 ? index : 0;
          
          Navigator.push(
            context,
            MaterialPageRoute(
              builder: (context) => MediaViewerModal(
                mediaList: mediaList,
                initialIndex: initialIndex,
                onMediaUpdated: () {
                  // ✅ Real-time güncelleme için parent widget'a bildir
                  widget.onMediaDeleted?.call();
                  widget.onCommentCountChanged?.call();
                },
              ),
            ),
          ).then((_) {
            // ✅ Modal kapandığında refresh yap
            widget.onMediaDeleted?.call();
          });
        } else {
          widget.onTap();
        }
      },
      child: Stack(
        alignment: Alignment.center,
        children: [
          // ✅ Dinamik aspect ratio ile görüntü göster (portrait fotoğraflar kesilmez)
          isVideoFile && thumbnailUrl != null
              // ✅ Video için thumbnail göster (cover kullan - video thumbnail'lar genelde kare)
              ? SizedBox(
                  height: MediaQuery.of(context).size.height * 0.35,
                  width: double.infinity,
                  child: RobustImageWidget(
                    imageUrl: thumbnailUrl,
                    width: null,
                    height: null,
                    fit: BoxFit.cover,
                    placeholder: Container(
                      color: AppColors.primary.withOpacity(0.1),
                      child: const Center(
                        child: CircularProgressIndicator(
                          color: AppColors.primary,
                        ),
                      ),
                    ),
                    errorWidget: Container(
                      color: AppColors.primary.withOpacity(0.1),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          const Icon(
                            Icons.videocam,
                            color: AppColors.primary,
                            size: 50,
                          ),
                          const SizedBox(height: 8),
                          Text(
                            'Video Thumbnail Yüklenemedi',
                            style: TextStyle(
                              color: AppColors.primary,
                              fontSize: 12,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                )
              : imageUrl != null && !isVideoFile
                  // ✅ Fotoğraf için dinamik aspect ratio kullan (portrait fotoğraflar tamamen görünür)
                  ? AspectRatioImage(
                      imageUrl: imageUrl,
                      fit: BoxFit.contain, // ✅ contain kullan - kesilme yok
                      maxHeight: MediaQuery.of(context).size.height * 0.6, // ✅ Maksimum yükseklik
                      maxWidth: MediaQuery.of(context).size.width, // ✅ Maksimum genişlik
                      backgroundColor: AppColors.primary.withOpacity(0.05), // ✅ Arka plan rengi
                      placeholder: Container(
                        color: AppColors.primary.withOpacity(0.1),
                        child: const Center(
                          child: CircularProgressIndicator(
                            color: AppColors.primary,
                          ),
                        ),
                      ),
                      errorWidget: Container(
                        color: AppColors.primary.withOpacity(0.1),
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            const Icon(
                              Icons.error_outline,
                              color: AppColors.primary,
                              size: 50,
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'Yüklenemedi',
                              style: TextStyle(
                                color: AppColors.primary,
                                fontSize: 12,
                              ),
                            ),
                          ],
                        ),
                      ),
                    )
                  : Container(
                      height: MediaQuery.of(context).size.height * 0.35,
                      width: double.infinity,
                      color: AppColors.primary.withOpacity(0.1),
                      child: Center(
                        child: Icon(
                          isVideo ? Icons.videocam : Icons.image,
                          color: AppColors.primary,
                          size: 50,
                        ),
                      ),
                    ),
          // Video play icon overlay
          if (isVideo || isVideoFile)
            Container(
              decoration: BoxDecoration(
                shape: BoxShape.circle,
                color: Colors.black.withOpacity(0.6),
              ),
              padding: const EdgeInsets.all(16),
              child: const Icon(
                Icons.play_arrow,
                color: Colors.white,
                size: 48,
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildPostActions() {
    return Row(
      mainAxisAlignment: MainAxisAlignment.spaceBetween,
      children: [
        Row(
          children: [
            GestureDetector(
              onTap: () async {
                if (widget.post == null) return;
                
                final mediaId = widget.post!['id'];
                final previousLikeState = _isLiked;
                final previousLikesCount = _likesCount;
                
                // Optimistic update
                setState(() {
                  _isLiked = !_isLiked;
                  if (_isLiked) {
                    _likesCount++;
                  } else {
                    _likesCount--;
                  }
                });
                
                try {
                  // Call API to like/unlike
                  final result = await _apiService.toggleLike(mediaId, previousLikeState);
                  
                  // ✅ API'den gelen güncel beğeni sayısını kullan
                  if (result['likes_count'] != null) {
                    setState(() {
                      _likesCount = result['likes_count'] as int;
                      _isLiked = result['is_liked'] as bool? ?? _isLiked;
                    });
                    
                    // ✅ Local post data'yı güncelle
                    if (widget.post != null) {
                      widget.post!['likes'] = _likesCount;
                      widget.post!['is_liked'] = _isLiked;
                    }
                  }
                  
                  // ✅ Force refresh media list to persist changes
                  if (widget.onMediaDeleted != null) {
                    widget.onMediaDeleted?.call();
                  }
                } catch (e) {
                  print('Like error: $e');
                  // Rollback on error
                  setState(() {
                    _isLiked = previousLikeState;
                    _likesCount = previousLikesCount;
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
              },
              child: Icon(
                _isLiked ? Icons.favorite : Icons.favorite_border,
                color: _isLiked ? Colors.red : Colors.black,
                size: 28,
              ),
            ),
            GestureDetector(
              onTap: _showCommentsModal,
              child: Row(
                mainAxisSize: MainAxisSize.min,
                children: [
                  const Icon(
                    CupertinoIcons.chat_bubble_2,
                    color: Colors.black,
                    size: 28,
                  ),
                  if (_commentsCount > 0) ...[
                    const SizedBox(width: 4),
                    Text(
                      '$_commentsCount',
                      style: const TextStyle(
                        color: Colors.black,
                        fontSize: 14,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ],
              ),
            ),
            IconButton(
              onPressed: () {},
              icon: const Icon(
                Icons.send,
                color: Colors.black,
                size: 28,
              ),
            ),
          ],
        ),
        IconButton(
          onPressed: () {},
          icon: const Icon(
            Icons.bookmark_border,
            color: Colors.black,
            size: 28,
          ),
        ),
      ],
    );
  }

  Widget _buildLikesCount() {
    // ✅ Local comment count kullan (real-time güncelleme için)
    final commentsCount = _commentsCount;
    
    // Debug log
    print('Post ID: ${widget.post?['id']}, Comments: $commentsCount, Local count: $_commentsCount');
    
    return Padding(
      padding: const EdgeInsets.only(left: 15, right: 15),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            '$_likesCount beğenme',
            style: const TextStyle(
              color: Colors.black,
              fontSize: 15,
              fontWeight: FontWeight.w600,
            ),
          ),
          if (commentsCount > 0) ...[
            const SizedBox(height: 4),
            GestureDetector(
              onTap: _showCommentsModal,
              child: Text(
                '$commentsCount yorum',
                style: TextStyle(
                  color: Colors.black.withOpacity(0.5),
                  fontSize: 14,
                  fontWeight: FontWeight.w500,
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _buildEventInfo() {
    return Padding(
      padding: const EdgeInsets.only(left: 15, right: 15),
      child: RichText(
        text: TextSpan(
          style: const TextStyle(color: Colors.black),
          children: [
            TextSpan(
              text: '${widget.event.title} ',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            if (widget.event.description != null)
              TextSpan(
                text: widget.event.description,
              ),
          ],
        ),
      ),
    );
  }

  Widget _buildComments() {
    // Comments are now shown in _buildLikesCount, so this is empty
    return const SizedBox.shrink();
  }

  Widget _buildAddComment() {
    return Padding(
      padding: const EdgeInsets.only(left: 15, right: 15),
      child: Row(
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Expanded(
            child: Row(
              children: [
                CircleAvatar(
                  radius: 15,
                  backgroundColor: AppColors.primary.withOpacity(0.1),
                  child: const Icon(
                    Icons.person,
                    color: AppColors.primary,
                    size: 15,
                  ),
                ),
                const SizedBox(width: 10),
                Expanded(
                  child: TextField(
                    controller: _commentController,
                    decoration: InputDecoration(
                      hintText: 'Yorum yap...',
                      hintStyle: TextStyle(
                        color: Colors.black.withOpacity(0.5),
                        fontSize: 15,
                        fontWeight: FontWeight.w500,
                      ),
                      border: InputBorder.none,
                      contentPadding: EdgeInsets.zero,
                    ),
                    style: const TextStyle(
                      color: Colors.black,
                      fontSize: 15,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ],
            ),
          ),
          Row(
            children: [
              GestureDetector(
                onTap: _addComment,
                child: Text(
                  'Gönder',
                  style: TextStyle(
                    color: _commentController.text.trim().isNotEmpty 
                        ? AppColors.primary 
                        : Colors.black.withOpacity(0.3),
                    fontSize: 15,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _buildPostInfo() {
    final description = widget.post?['description'] ?? widget.post?['content'];
    final userName = widget.post?['user_name'] ?? widget.event.title;
    
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 15),
      child: RichText(
        text: TextSpan(
          style: const TextStyle(color: Colors.black),
          children: [
            TextSpan(
              text: '$userName ',
              style: const TextStyle(fontWeight: FontWeight.bold),
            ),
            TextSpan(
              text: description ?? '',
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPostTime() {
    final createdAt = widget.post?['created_at'];
    String timeText = 'Şimdi';
    
    if (createdAt != null) {
      final createdDate = DateTime.tryParse(createdAt);
      if (createdDate != null) {
        final now = DateTime.now();
        final difference = now.difference(createdDate);
        
        if (difference.inDays > 0) {
          timeText = '${difference.inDays} gün önce';
        } else if (difference.inHours > 0) {
          timeText = '${difference.inHours} saat önce';
        } else if (difference.inMinutes > 0) {
          timeText = '${difference.inMinutes} dakika önce';
        }
      }
    }
    
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 15),
      child: Text(
        timeText,
        style: const TextStyle(
          color: Colors.grey,
          fontSize: 10,
        ),
      ),
    );
  }

  Widget _buildEventTime() {
    return Padding(
      padding: const EdgeInsets.only(left: 15, right: 15),
      child: Text(
        '${widget.event.participantCount} katılımcı • ${widget.event.mediaCount} medya',
        style: TextStyle(
          color: Colors.black.withOpacity(0.5),
          fontSize: 12,
          fontWeight: FontWeight.w500,
        ),
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
      
      // ✅ Direkt URL'den streaming yap (indirme yok, daha hızlı başlatma)
      _controller = VideoPlayerController.networkUrl(
        Uri.parse(cleanUrl),
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

  Future<http.Response> _downloadWithRetry(String url, {int maxRetries = 3}) async {
    int attempts = 0;
    Exception? lastException;
    
    while (attempts < maxRetries) {
      try {
        attempts++;
        print('Download attempt $attempts/$maxRetries for URL: $url');
        
        // Büyük dosyalar için chunked download
        final response = await _downloadLargeFile(url);
        return response;
      } catch (e) {
        lastException = e is Exception ? e : Exception(e.toString());
        print('Download attempt $attempts failed: $e');
        
        if (attempts < maxRetries) {
          // Exponential backoff: 1s, 2s, 4s
          final delay = Duration(seconds: attempts);
          print('Retrying in ${delay.inSeconds} seconds...');
          await Future.delayed(delay);
        }
      }
    }
    
    throw lastException ?? Exception('All download attempts failed');
  }

  Future<http.Response> _downloadLargeFile(String url) async {
    try {
      // İlk olarak dosya boyutunu kontrol et
      final headResponse = await http.head(Uri.parse(url)).timeout(
        const Duration(seconds: 10),
        onTimeout: () => throw Exception('Head request timeout'),
      );
      
      final contentLength = headResponse.headers['content-length'];
      final fileSize = contentLength != null ? int.tryParse(contentLength) : null;
      
      print('File size: ${fileSize != null ? (fileSize / 1024 / 1024).toStringAsFixed(2) + ' MB' : 'Unknown'}');
      
      // Büyük dosyalar için chunked download
      if (fileSize != null && fileSize > 2 * 1024 * 1024) { // 2MB'den büyükse
        print('Large file detected, using chunked download');
        return await _downloadInChunks(url, fileSize);
      } else {
        // Küçük dosyalar için normal download
        print('Small file, using normal download');
        return await http.get(
          Uri.parse(url),
          headers: {
            'Connection': 'keep-alive',
            'Cache-Control': 'no-cache',
          },
        ).timeout(
          const Duration(seconds: 60), // Büyük dosyalar için daha uzun timeout
          onTimeout: () => throw Exception('Download timeout after 60 seconds'),
        );
      }
    } catch (e) {
      print('Large file download error: $e');
      rethrow;
    }
  }

  Future<http.Response> _downloadInChunks(String url, int fileSize) async {
    const chunkSize = 1024 * 1024; // 1MB chunks
    final totalChunks = (fileSize / chunkSize).ceil();
    
    print('Downloading $totalChunks chunks of ${(chunkSize / 1024 / 1024).toStringAsFixed(2)} MB each');
    
    final List<int> allBytes = [];
    
    for (int chunk = 0; chunk < totalChunks; chunk++) {
      final start = chunk * chunkSize;
      final end = (start + chunkSize - 1).clamp(0, fileSize - 1);
      
      print('Downloading chunk ${chunk + 1}/$totalChunks (bytes $start-$end)');
      
      final response = await http.get(
        Uri.parse(url),
        headers: {
          'Range': 'bytes=$start-$end',
          'Connection': 'keep-alive',
        },
      ).timeout(
        const Duration(seconds: 30),
        onTimeout: () => throw Exception('Chunk download timeout'),
      );
      
      if (response.statusCode == 206 || response.statusCode == 200) {
        allBytes.addAll(response.bodyBytes);
        print('Chunk ${chunk + 1} downloaded successfully');
      } else {
        throw Exception('Failed to download chunk ${chunk + 1}: ${response.statusCode}');
      }
      
      // Chunk'lar arasında kısa bekleme
      if (chunk < totalChunks - 1) {
        await Future.delayed(const Duration(milliseconds: 100));
      }
    }
    
    print('All chunks downloaded, total size: ${(allBytes.length / 1024 / 1024).toStringAsFixed(2)} MB');
    
    // Mock response oluştur
    return http.Response.bytes(allBytes, 200);
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
    if (_hasError) {
      return Container(
        color: AppColors.primary.withOpacity(0.1),
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            const Icon(
              Icons.videocam_off,
              color: AppColors.primary,
              size: 50,
            ),
            const SizedBox(height: 8),
            Text(
              'Video Yüklenemedi',
              style: TextStyle(
                color: AppColors.primary,
                fontSize: 12,
              ),
            ),
          ],
        ),
      );
    }

    if (!_isInitialized || _controller == null) {
      return Container(
        color: AppColors.primary.withOpacity(0.1),
        child: const Center(
          child: CircularProgressIndicator(
            color: AppColors.primary,
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
                size: 50,
              ),
            ),
        ],
      ),
    );
  }
}
