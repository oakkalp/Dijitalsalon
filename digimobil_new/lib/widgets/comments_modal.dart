import 'package:flutter/material.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/models/user.dart';
import 'package:digimobil_new/models/event.dart';

class CommentsModal extends StatefulWidget {
  final int mediaId;
  final List<Map<String, dynamic>> comments;
  final bool isLoading;
  final VoidCallback onLoadComments;
  final Function(String) onAddComment;
  final User? currentUser;
  final Event? event; // ✅ Event parametresi eklendi

  const CommentsModal({
    super.key,
    required this.mediaId,
    required this.comments,
    required this.isLoading,
    required this.onLoadComments,
    required this.onAddComment,
    this.currentUser,
    this.event, // ✅ Event parametresi eklendi
  });

  @override
  State<CommentsModal> createState() => _CommentsModalState();
}

class _CommentsModalState extends State<CommentsModal> {
  final TextEditingController _commentController = TextEditingController();
  final TextEditingController _replyController = TextEditingController();
  bool _isAddingComment = false;
  bool _isAddingReply = false;
  final ApiService _apiService = ApiService();
  Map<int, List<Map<String, dynamic>>> _replies = {};
  Map<int, bool> _repliesLoading = {};
  Map<int, bool> _showReplyForm = {};
  
  // Local comments state
  List<Map<String, dynamic>> _localComments = [];
  bool _localLoading = false;

  @override
  void initState() {
    super.initState();
    _localComments = List.from(widget.comments);
    
    // Load comments if not provided
    WidgetsBinding.instance.addPostFrameCallback((_) {
      if (_localComments.isEmpty) {
        _loadComments();
      }
    });
  }

  @override
  void dispose() {
    _commentController.dispose();
    _replyController.dispose();
    super.dispose();
  }

  Future<void> _loadComments() async {
    if (_localLoading) return;
    
    setState(() {
      _localLoading = true;
    });

    try {
      final comments = await _apiService.getComments(widget.mediaId);
      setState(() {
        _localComments = comments;
        _localLoading = false;
      });
      
      // ✅ Tüm yorumların yanıtlarını otomatik yükle
      for (final comment in comments) {
        await _loadReplies(comment['id']);
      }
      
    } catch (e) {
      print('Error loading comments: $e');
      setState(() {
        _localLoading = false;
      });
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Yorumlar yüklenirken hata: $e'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    }
  }

  Future<void> _addComment() async {
    final content = _commentController.text.trim();
    if (content.isEmpty) return;

    print('Adding comment: $content for media: ${widget.mediaId}');

    setState(() {
      _isAddingComment = true;
    });

    try {
      final response = await _apiService.addComment(widget.mediaId, content);
      _commentController.clear();
      
      print('Comment added successfully, reloading comments...');
      
      // Reload comments locally
      await _loadComments();
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Yorum eklendi'),
            backgroundColor: AppColors.success,
          ),
        );
      }
      
      // ✅ Parent widget'a yorum eklendiğini bildir
      widget.onAddComment(content);
    } catch (e) {
      print('Error adding comment: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Yorum eklenirken hata: $e'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isAddingComment = false;
        });
      }
    }
  }

  Future<void> _addReply(int commentId) async {
    final content = _replyController.text.trim();
    if (content.isEmpty) return;

    print('Adding reply: $content for comment: $commentId');

    setState(() {
      _isAddingReply = true;
    });

    try {
      await _apiService.addReply(commentId, content);
      _replyController.clear();
      
      print('Reply added successfully, reloading replies...');
      
      // Hide reply form
      setState(() {
        _showReplyForm[commentId] = false;
      });
      
      // Reload replies for the comment
      await _loadReplies(commentId);
      
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Yanıt eklendi'),
            backgroundColor: AppColors.success,
          ),
        );
      }
    } catch (e) {
      print('Error adding reply: $e');
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Yanıt eklenirken hata: $e'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isAddingReply = false;
        });
      }
    }
  }

  void _toggleReplyForm(int commentId) {
    setState(() {
      _showReplyForm[commentId] = !(_showReplyForm[commentId] ?? false);
    });
    
    // ✅ Yanıtları sadece daha önce yüklenmemişse yükle
    if (_showReplyForm[commentId] == true && _replies[commentId] == null) {
      _loadReplies(commentId);
    }
  }

  Future<void> _loadReplies(int commentId) async {
    if (_repliesLoading[commentId] == true) return;
    
    setState(() {
      _repliesLoading[commentId] = true;
    });

    try {
      final replies = await _apiService.getReplies(commentId);
      setState(() {
        _replies[commentId] = replies;
        _repliesLoading[commentId] = false;
      });
    } catch (e) {
      print('Error loading replies: $e');
      setState(() {
        _repliesLoading[commentId] = false;
      });
    }
  }

  Future<void> _deleteComment(Map<String, dynamic> comment) async {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Yorumu Sil'),
        content: const Text('Bu yorumu silmek istediğinizden emin misiniz?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('İptal'),
          ),
          TextButton(
            onPressed: () async {
              Navigator.pop(context);
              
              try {
                await _apiService.deleteComment(comment['id']);
                
                // Remove comment from local list
                setState(() {
                  _localComments.removeWhere((c) => c['id'] == comment['id']);
                });
                
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    const SnackBar(
                      content: Text('Yorum başarıyla silindi'),
                      backgroundColor: AppColors.success,
                    ),
                  );
                }
              } catch (e) {
                if (mounted) {
                  ScaffoldMessenger.of(context).showSnackBar(
                    SnackBar(
                      content: Text('Yorum silinirken hata: $e'),
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

  // ✅ Yorum silme yetkisi kontrolü
  bool _canDeleteComment(Map<String, dynamic> comment) {
    final currentUser = widget.currentUser;
    final event = widget.event;
    if (currentUser == null) return false;
    
    // Kendi yorumu mu kontrol et
    final isOwnComment = comment['user_id'] == currentUser.id;
    
    // Kendi yorumu ise silebilir
    if (isOwnComment) {
      return true;
    }
    
    // Event yetkileri kontrolü
    if (event != null) {
      final userRole = event.userRole;
      final userPermissions = event.userPermissions;
      
      // Admin ve Moderator her şeyi silebilir
      if (userRole == 'admin' || userRole == 'moderator') {
        return true;
      }
      
      // Yetkili kullanıcı ve yorum silme yetkisi varsa
      if (userRole == 'yetkili_kullanici' && userPermissions != null) {
        if (userPermissions['yorum_silebilir'] == true) {
          return true;
        }
      }
    }
    
    return false;
  }

  Future<void> _toggleCommentLike(Map<String, dynamic> comment) async {
    try {
      final action = comment['is_liked'] == true ? 'unlike' : 'like';
      await _apiService.toggleCommentLike(comment['id'], action);
      
      // Update local state
      setState(() {
        comment['is_liked'] = !(comment['is_liked'] == true);
        comment['likes'] = (comment['likes'] ?? 0) + (action == 'like' ? 1 : -1);
      });
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(action == 'like' ? 'Yorum beğenildi' : 'Beğeni kaldırıldı'),
          backgroundColor: AppColors.success,
        ),
      );
    } catch (e) {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Beğeni işlemi başarısız: $e'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  Widget _buildReplyItem(Map<String, dynamic> reply) {
    return Container(
      margin: const EdgeInsets.only(left: 20, bottom: 8),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          CircleAvatar(
            radius: 12,
            backgroundImage: reply['user_avatar'] != null
                ? NetworkImage(reply['user_avatar'])
                : null,
            child: reply['user_avatar'] == null
                ? const Icon(Icons.person, size: 12, color: AppColors.textPrimary)
                : null,
          ),
          const SizedBox(width: 8),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  reply['user_name'] ?? 'Bilinmeyen Kullanıcı',
                  style: const TextStyle(
                    fontWeight: FontWeight.bold,
                    color: AppColors.textPrimary,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 2),
                Text(
                  reply['content'] ?? '',
                  style: const TextStyle(
                    color: AppColors.textPrimary,
                    fontSize: 12,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  _formatTime(reply['created_at']),
                  style: const TextStyle(
                    color: AppColors.textSecondary,
                    fontSize: 10,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  String _formatTime(String? dateString) {
    if (dateString == null) return '';
    
    try {
      final date = DateTime.parse(dateString);
      final now = DateTime.now();
      final difference = now.difference(date);
      
      if (difference.inMinutes < 1) {
        return 'Şimdi';
      } else if (difference.inHours < 1) {
        return '${difference.inMinutes}dk';
      } else if (difference.inDays < 1) {
        return '${difference.inHours}sa';
      } else if (difference.inDays < 7) {
        return '${difference.inDays}g';
      } else {
        return '${date.day}/${date.month}/${date.year}';
      }
    } catch (e) {
      return dateString;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Container(
      height: MediaQuery.of(context).size.height * 0.7,
      decoration: const BoxDecoration(
        color: AppColors.surface,
        borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
      ),
      child: Column(
        children: [
          // Header
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              border: Border(
                bottom: BorderSide(color: AppColors.border.withOpacity(0.3)),
              ),
            ),
            child: Row(
              children: [
                const Text(
                  'Yorumlar',
                  style: TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                    color: AppColors.textPrimary,
                  ),
                ),
                const Spacer(),
                IconButton(
                  onPressed: () => Navigator.pop(context),
                  icon: const Icon(Icons.close, color: AppColors.textSecondary),
                ),
              ],
            ),
          ),

          // Comments List
          Expanded(
            child: _localLoading
                ? const Center(
                    child: CircularProgressIndicator(color: AppColors.primary),
                  )
                : _localComments.isEmpty
                    ? const Center(
                        child: Text(
                          'Henüz yorum yok',
                          style: TextStyle(
                            color: AppColors.textSecondary,
                            fontSize: 16,
                          ),
                        ),
                      )
                    : ListView.builder(
                        padding: const EdgeInsets.all(16),
                        itemCount: _localComments.length,
                        itemBuilder: (context, index) {
                          final comment = _localComments[index];
                          return Padding(
                            padding: const EdgeInsets.only(bottom: 16),
                            child: Row(
                              crossAxisAlignment: CrossAxisAlignment.start,
                              children: [
                                CircleAvatar(
                                  radius: 16,
                                  backgroundImage: comment['user_avatar'] != null
                                      ? NetworkImage(comment['user_avatar'])
                                      : null,
                                  child: comment['user_avatar'] == null
                                      ? const Icon(Icons.person, size: 16, color: AppColors.textPrimary)
                                      : null,
                                ),
                                const SizedBox(width: 12),
                                Expanded(
                                  child: Column(
                                    crossAxisAlignment: CrossAxisAlignment.start,
                                    children: [
                                      Text(
                                        comment['user_name'] ?? 'Bilinmeyen Kullanıcı',
                                        style: const TextStyle(
                                          fontWeight: FontWeight.bold,
                                          color: AppColors.textPrimary,
                                          fontSize: 14,
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        comment['content'] ?? '',
                                        style: const TextStyle(
                                          color: AppColors.textSecondary,
                                          fontSize: 14,
                                        ),
                                      ),
                                      const SizedBox(height: 4),
                                      Text(
                                        _formatTime(comment['created_at']),
                                        style: const TextStyle(
                                          color: AppColors.textTertiary,
                                          fontSize: 12,
                                        ),
                                      ),
                                      const SizedBox(height: 8),
                                      
                                      // Like Button and Count
                                      Row(
                                        children: [
                                          GestureDetector(
                                            onTap: () => _toggleCommentLike(comment),
                                            child: Row(
                                              children: [
                                                Icon(
                                                  comment['is_liked'] == true ? Icons.favorite : Icons.favorite_border,
                                                  size: 16,
                                                  color: comment['is_liked'] == true ? Colors.red : AppColors.textSecondary,
                                                ),
                                                const SizedBox(width: 4),
                                                Text(
                                                  '${comment['likes'] ?? 0}',
                                                  style: const TextStyle(
                                                    color: AppColors.textSecondary,
                                                    fontSize: 12,
                                                  ),
                                                ),
                                              ],
                                            ),
                                          ),
                                          const SizedBox(width: 16),
                                          
                                          // Reply Button
                                          GestureDetector(
                                            onTap: () => _toggleReplyForm(comment['id']),
                                            child: Text(
                                              _showReplyForm[comment['id']] == true ? 'Yanıtı Gizle' : 'Yanıtla',
                                              style: const TextStyle(
                                                color: AppColors.textSecondary,
                                                fontSize: 12,
                                              ),
                                            ),
                                          ),
                                          
                                          // ✅ Yorum silme butonu (yetki kontrolü ile)
                                          if (_canDeleteComment(comment)) ...[
                                            const SizedBox(width: 16),
                                            GestureDetector(
                                              onTap: () => _deleteComment(comment),
                                              child: const Text(
                                                'Sil',
                                                style: TextStyle(
                                                  color: Colors.red,
                                                  fontSize: 12,
                                                ),
                                              ),
                                            ),
                                          ],
                                        ],
                                      ),
                                      
                                      // Reply Form
                                      if (_showReplyForm[comment['id']] == true) ...[
                                        const SizedBox(height: 12),
                                        Row(
                                          children: [
                                            Expanded(
                                              child: TextField(
                                                controller: _replyController,
                                                decoration: const InputDecoration(
                                                  hintText: 'Yanıt yazın...',
                                                  hintStyle: TextStyle(color: AppColors.textSecondary),
                                                  border: OutlineInputBorder(
                                                    borderRadius: BorderRadius.all(Radius.circular(15)),
                                                    borderSide: BorderSide(color: AppColors.border),
                                                  ),
                                                  enabledBorder: OutlineInputBorder(
                                                    borderRadius: BorderRadius.all(Radius.circular(15)),
                                                    borderSide: BorderSide(color: AppColors.border),
                                                  ),
                                                  focusedBorder: OutlineInputBorder(
                                                    borderRadius: BorderRadius.all(Radius.circular(15)),
                                                    borderSide: BorderSide(color: AppColors.primary),
                                                  ),
                                                  contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                                                ),
                                                style: const TextStyle(color: Colors.black, fontSize: 12),
                                                maxLines: null,
                                                textInputAction: TextInputAction.send,
                                                onSubmitted: (_) => _addReply(comment['id']),
                                              ),
                                            ),
                                            const SizedBox(width: 8),
                                            IconButton(
                                              onPressed: _isAddingReply ? null : () => _addReply(comment['id']),
                                              icon: _isAddingReply
                                                  ? const SizedBox(
                                                      width: 16,
                                                      height: 16,
                                                      child: CircularProgressIndicator(
                                                        strokeWidth: 2,
                                                        color: AppColors.primary,
                                                      ),
                                                    )
                                                  : const Icon(Icons.send, color: AppColors.primary, size: 16),
                                            ),
                                          ],
                                        ),
                                      ],
                                      
                                      // ✅ Replies List - Her zaman göster (yanıt varsa)
                                      if (_replies[comment['id']] != null && _replies[comment['id']]!.isNotEmpty) ...[
                                        const SizedBox(height: 8),
                                        ...(_replies[comment['id']]!.map((reply) => _buildReplyItem(reply))),
                                      ],
                                      
                                      // ✅ Yanıt yükleme durumu
                                      if (_repliesLoading[comment['id']] == true) ...[
                                        const SizedBox(height: 8),
                                        const Center(
                                          child: Padding(
                                            padding: EdgeInsets.all(8.0),
                                            child: SizedBox(
                                              width: 16,
                                              height: 16,
                                              child: CircularProgressIndicator(
                                                strokeWidth: 2,
                                                color: AppColors.primary,
                                              ),
                                            ),
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                ),
                              ],
                            ),
                          );
                        },
                      ),
          ),

          // Comment Input
          Container(
            padding: const EdgeInsets.all(16),
            decoration: BoxDecoration(
              border: Border(
                top: BorderSide(color: AppColors.border.withOpacity(0.3)),
              ),
            ),
            child: Row(
              children: [
                Expanded(
                  child: TextField(
                    controller: _commentController,
                    decoration: const InputDecoration(
                      hintText: 'Yorum yazın...',
                      hintStyle: TextStyle(color: AppColors.textSecondary),
                      border: OutlineInputBorder(
                        borderRadius: BorderRadius.all(Radius.circular(20)),
                        borderSide: BorderSide(color: AppColors.border),
                      ),
                      enabledBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.all(Radius.circular(20)),
                        borderSide: BorderSide(color: AppColors.border),
                      ),
                      focusedBorder: OutlineInputBorder(
                        borderRadius: BorderRadius.all(Radius.circular(20)),
                        borderSide: BorderSide(color: AppColors.primary),
                      ),
                      contentPadding: EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    ),
                    style: const TextStyle(color: Colors.black),
                    maxLines: null,
                    textInputAction: TextInputAction.send,
                    onSubmitted: (_) => _addComment(),
                  ),
                ),
                const SizedBox(width: 8),
                IconButton(
                  onPressed: _isAddingComment ? null : _addComment,
                  icon: _isAddingComment
                      ? const SizedBox(
                          width: 20,
                          height: 20,
                          child: CircularProgressIndicator(
                            strokeWidth: 2,
                            color: AppColors.primary,
                          ),
                        )
                      : const Icon(Icons.send, color: AppColors.primary),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}