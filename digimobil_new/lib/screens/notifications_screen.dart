import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/theme_colors.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:digimobil_new/widgets/media_viewer_modal.dart';
import 'package:digimobil_new/models/event.dart';
import 'package:digimobil_new/widgets/shimmer_loading.dart';

class NotificationsScreen extends StatefulWidget {
  const NotificationsScreen({super.key});

  @override
  State<NotificationsScreen> createState() => _NotificationsScreenState();
}

class _NotificationsScreenState extends State<NotificationsScreen> {
  final ApiService _apiService = ApiService();
  List<Map<String, dynamic>> _notifications = [];
  List<Map<String, dynamic>> _filteredNotifications = [];
  bool _isLoading = false;
  bool _hasMore = true;
  int _page = 1; // ✅ Sayfa numarası
  final ScrollController _scrollController = ScrollController();
  int _unreadCount = 0;
  String _selectedFilter = 'all'; // 'all', 'like', 'comment', 'custom'
  bool _isSelectionMode = false;
  Set<dynamic> _selectedNotifications = {}; // ✅ int veya String olabilir
  bool _autoLoadTriggered = false; // ✅ Otomatik yükleme flag'i
  final TextEditingController _searchController = TextEditingController();
  String _searchQuery = ''; // ✅ Arama sorgusu

  @override
  void initState() {
    super.initState();
    _loadNotifications();
    _scrollController.addListener(_onScroll);
    _searchController.addListener(_onSearchChanged);
  }

  @override
  void dispose() {
    _scrollController.dispose();
    _searchController.dispose();
    super.dispose();
  }

  void _onSearchChanged() {
    setState(() {
      _searchQuery = _searchController.text.trim().toLowerCase();
      _applyFilter();
    });
  }

  void _onScroll() {
    if (!_scrollController.hasClients) return;
    
    final maxScroll = _scrollController.position.maxScrollExtent;
    final currentScroll = _scrollController.position.pixels;
    final threshold = maxScroll - 200; // ✅ 200px kala yükle
    
    // ✅ Scroll pozisyonu yaklaşık max'a yaklaştığında yükle
    if (currentScroll >= threshold && maxScroll > 0) {
      if (!_isLoading && _hasMore) {
        if (kDebugMode) {
          debugPrint('Scroll trigger - current: $currentScroll, max: $maxScroll, threshold: $threshold');
        }
        _loadMore();
      }
    }
  }

  // ✅ Bildirim filtreleme (tip + arama sorgusu)
  void _applyFilter() {
    List<Map<String, dynamic>> filtered = List.from(_notifications);

    // ✅ Tip filtresi
    if (_selectedFilter != 'all') {
      filtered = filtered.where((n) => n['type'] == _selectedFilter).toList();
    }

    // ✅ Arama sorgusu filtresi
    if (_searchQuery.isNotEmpty) {
      filtered = filtered.where((notification) {
        final query = _searchQuery;
        
        // Başlık araması
        final title = (notification['title'] ?? '').toString().toLowerCase();
        if (title.contains(query)) return true;
        
        // Mesaj araması
        final message = (notification['message'] ?? '').toString().toLowerCase();
        if (message.contains(query)) return true;
        
        // Event adı araması
        final eventTitle = (notification['event_title'] ?? '').toString().toLowerCase();
        if (eventTitle.contains(query)) return true;
        
        // Kullanıcı adı araması (like bildirimleri için)
        final likers = notification['likers'] as List<dynamic>?;
        if (likers != null) {
          for (var liker in likers) {
            if (liker is Map) {
              final name = (liker['name'] ?? liker['sender_name'] ?? '').toString().toLowerCase();
              if (name.contains(query)) return true;
            }
          }
        }
        
        // Yorum araması (comment bildirimleri için)
        final commenters = notification['commenters'] as List<dynamic>?;
        if (commenters != null) {
          for (var commenter in commenters) {
            if (commenter is Map) {
              final name = (commenter['name'] ?? '').toString().toLowerCase();
              if (name.contains(query)) return true;
              
              final commentText = (commenter['comment_text'] ?? commenter['comment'] ?? '').toString().toLowerCase();
              if (commentText.contains(query)) return true;
            }
          }
        }
        
        // Eski format yorum araması
        final comments = notification['comments'] as List<dynamic>?;
        if (comments != null) {
          for (var comment in comments) {
            if (comment is Map) {
              final commentText = (comment['comment_text'] ?? comment['comment'] ?? '').toString().toLowerCase();
              if (commentText.contains(query)) return true;
            }
          }
        }
        
        // Tek yorum araması
        final commentText = (notification['comment_text'] ?? '').toString().toLowerCase();
        if (commentText.contains(query)) return true;
        
        return false;
      }).toList();
    }

    _filteredNotifications = filtered;
  }

  void _setFilter(String filter) {
    setState(() {
      _selectedFilter = filter;
      _applyFilter();
    });
  }

  Future<void> _loadNotifications({bool refresh = false}) async {
    if (_isLoading) return;

    // ✅ Refresh yapıldığında sayfayı sıfırla
    if (refresh) {
      setState(() {
        _page = 1;
        _notifications.clear();
        _hasMore = true;
      });
    }

    setState(() {
      _isLoading = true;
    });

    try {
      // ✅ Sayfa ilk açıldığında tüm bildirimleri okunmuş olarak işaretle
      if (_page == 1) {
        try {
          await _apiService.markAllNotificationsAsRead();
        } catch (e) {
          if (kDebugMode) {
            debugPrint('Error marking all notifications as read: $e');
          }
          // Hata olsa bile devam et
        }
        // ✅ İlk yüklemede tüm bildirimleri temizle
        if (!refresh) {
          _notifications.clear();
        }
      }
      
      // ✅ 3'er 3'er yükleme
      final data = await _apiService.getNotifications(page: _page, limit: 3);
      final notificationsList = data['notifications'] as List<dynamic>?;
      // ✅ has_more boolean veya int olabilir, bool'e çevir
      final hasMoreRaw = data['has_more'];
      final hasMore = hasMoreRaw == true || hasMoreRaw == 1 || (hasMoreRaw is bool && hasMoreRaw) || (hasMoreRaw is int && hasMoreRaw == 1);
      
      if (kDebugMode) {
        debugPrint('Notifications loaded - page: $_page, count: ${notificationsList?.length ?? 0}, hasMore: $hasMore (raw: $hasMoreRaw)');
      }
      
      if (mounted) {
        setState(() {
          // ✅ Yeni bildirimleri ekle (append)
          final newNotifications = notificationsList
              ?.map((e) => e as Map<String, dynamic>)
              .toList() ?? [];
          _notifications.addAll(newNotifications);
          
          // ✅ Tüm bildirimleri okunmuş olarak işaretle
          for (var notification in _notifications) {
            notification['is_read'] = true;
          }
          _unreadCount = 0; // ✅ Sayfa açıldığında okunmamış sayısını sıfırla
          _isLoading = false;
          _hasMore = hasMore;
          _autoLoadTriggered = false; // ✅ Yeni veri geldiğinde flag'i sıfırla
          _applyFilter(); // ✅ Filtreyi uygula
        });
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('Error loading notifications: $e');
      }
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }

  Future<void> _loadMore() async {
    if (_isLoading || !_hasMore) {
      if (kDebugMode) {
        debugPrint('LoadMore skipped - isLoading: $_isLoading, hasMore: $_hasMore');
      }
      return;
    }
    
    if (kDebugMode) {
      debugPrint('Loading more notifications - page: ${_page + 1}');
    }
    
    setState(() {
      _page++;
    });
    
    await _loadNotifications();
  }

  Future<void> _deleteNotification({
    int? notificationId,
    int? mediaId,
    int? eventId,
    String? type,
  }) async {
    try {
      // Backend'den sil
      await _apiService.deleteNotification(
        notificationId: notificationId,
        mediaId: mediaId,
        eventId: eventId,
        type: type,
      );
      // Local'den kaldır (zaten onDismissed'da kaldırıldı, burada sadece güvenlik için)
      if (mounted) {
        setState(() {
          if (notificationId != null) {
            final notification = _notifications.firstWhere(
              (n) => n['id'] == notificationId,
              orElse: () => {},
            );
            final wasUnread = !(notification['is_read'] ?? false);
            _notifications.removeWhere((n) => n['id'] == notificationId);
            _filteredNotifications.removeWhere((n) => n['id'] == notificationId);
            if (wasUnread && _unreadCount > 0) {
              _unreadCount--;
            }
          } else if (mediaId != null && eventId != null && type != null) {
            // Gruplu bildirim silme - media_id ve event_id ile eşleşenleri kaldır
            _notifications.removeWhere((n) {
              return n['type'] == type &&
                     n['media_id'] == mediaId &&
                     n['event_id'] == eventId;
            });
            _filteredNotifications.removeWhere((n) {
              return n['type'] == type &&
                     n['media_id'] == mediaId &&
                     n['event_id'] == eventId;
            });
          }
        });
      }
    } catch (e) {
      if (kDebugMode) {
        debugPrint('Error deleting notification: $e');
      }
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text('Bildirim silinirken hata: $e')),
        );
      }
    }
  }

  // ✅ Seçili bildirimleri sil
  Future<void> _deleteSelectedNotifications() async {
    if (_selectedNotifications.isEmpty) return;
    
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Bildirimleri Sil'),
        content: Text('${_selectedNotifications.length} bildirimi silmek istediğinizden emin misiniz?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('İptal'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Sil', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );
    
    if (confirmed == true) {
      try {
        final deletedCount = _selectedNotifications.length;
        
        // Backend'den seçili bildirimleri sil
        for (final selectedKey in _selectedNotifications) {
          try {
            // ✅ Seçili bildirimi bul
            final notification = _notifications.firstWhere(
              (n) {
                final nId = n['id'] ?? 
                           (n['media_id'] != null 
                            ? '${n['type']}_${n['media_id']}_${n['event_id']}'
                            : '${n['type']}_${n.hashCode}');
                return nId == selectedKey || n['id'] == selectedKey;
              },
              orElse: () => {},
            );
            
            if (notification.isEmpty) continue;
            
            // ✅ Eğer gerçek id varsa, onu kullan
            if (notification['id'] != null && notification['id'] is int) {
              await _apiService.deleteNotification(notificationId: notification['id'] as int);
            } 
            // ✅ Gruplu bildirim ise media_id + event_id + type ile sil
            else if (notification['media_id'] != null && notification['event_id'] != null && notification['type'] != null) {
              final mediaId = notification['media_id'] is int 
                  ? notification['media_id'] 
                  : int.tryParse(notification['media_id'].toString());
              final eventId = notification['event_id'] is int 
                  ? notification['event_id'] 
                  : int.tryParse(notification['event_id'].toString());
              final type = notification['type'] as String?;
              
              if (mediaId != null && eventId != null && type != null) {
                await _apiService.deleteNotification(
                  mediaId: mediaId,
                  eventId: eventId,
                  type: type,
                );
              }
            }
          } catch (e) {
            if (kDebugMode) {
              debugPrint('Error deleting notification: $e');
            }
          }
        }
        
        if (mounted) {
          setState(() {
            // Bildirimleri unique key ile kaldır
            _notifications.removeWhere((n) {
              final nId = n['id'] ?? 
                         (n['media_id'] != null 
                          ? '${n['type']}_${n['media_id']}_${n['event_id']}'
                          : '${n['type']}_${n.hashCode}');
              return _selectedNotifications.contains(nId) || _selectedNotifications.contains(n['id']);
            });
            _filteredNotifications.removeWhere((n) {
              final nId = n['id'] ?? 
                         (n['media_id'] != null 
                          ? '${n['type']}_${n['media_id']}_${n['event_id']}'
                          : '${n['type']}_${n.hashCode}');
              return _selectedNotifications.contains(nId) || _selectedNotifications.contains(n['id']);
            });
            _selectedNotifications.clear();
            _isSelectionMode = false;
          });
          
          // Unread count güncelleme için yeniden yükle (sayfayı sıfırla)
          _page = 1;
          await _loadNotifications(refresh: true);
          
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('$deletedCount bildirim silindi')),
            );
          }
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Hata: $e')),
          );
        }
      }
    }
  }

  Future<void> _clearAllNotifications() async {
    final confirmed = await showDialog<bool>(
      context: context,
      builder: (context) => AlertDialog(
        title: const Text('Tümünü Temizle'),
        content: const Text('Tüm bildirimleri silmek istediğinizden emin misiniz?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context, false),
            child: const Text('İptal'),
          ),
          TextButton(
            onPressed: () => Navigator.pop(context, true),
            child: const Text('Sil', style: TextStyle(color: Colors.red)),
          ),
        ],
      ),
    );

    if (confirmed == true) {
      try {
        // Backend'den tüm bildirimleri sil
        await _apiService.clearAllNotifications();
        
        if (mounted) {
          setState(() {
            _notifications.clear();
            _filteredNotifications.clear();
            _unreadCount = 0;
            _page = 1; // ✅ Sayfayı sıfırla
            _hasMore = true;
          });

          ScaffoldMessenger.of(context).showSnackBar(
            const SnackBar(content: Text('Tüm bildirimler temizlendi')),
          );
        }
      } catch (e) {
        if (mounted) {
          ScaffoldMessenger.of(context).showSnackBar(
            SnackBar(content: Text('Hata: $e')),
          );
        }
      }
    }
  }

  Future<void> _onNotificationTap(Map<String, dynamic> notification) async {
    // Okundu olarak işaretle
    if (notification['id'] != null && !(notification['is_read'] ?? false)) {
      try {
        await _apiService.markNotificationAsRead(notification['id']);
        setState(() {
          notification['is_read'] = true;
          _unreadCount = _unreadCount > 0 ? _unreadCount - 1 : 0;
        });
      } catch (e) {
        if (kDebugMode) {
          debugPrint('Error marking notification as read: $e');
        }
      }
    }

    // Medya açma
    if (notification['type'] == 'like' || notification['type'] == 'comment') {
      final mediaIdRaw = notification['media_id'];
      final eventIdRaw = notification['event_id'];
      
      // ✅ String olarak gelebilir, int'e çevir
      final mediaId = mediaIdRaw is String ? int.tryParse(mediaIdRaw) : (mediaIdRaw as int?);
      final eventId = eventIdRaw is String ? int.tryParse(eventIdRaw) : (eventIdRaw as int?);
      
      if (mediaId != null && eventId != null) {
        try {
          // ✅ Event'in medyalarını çek
          final mediaData = await _apiService.getMedia(eventId, page: 1, limit: 100);
          final mediaList = (mediaData['media'] as List<dynamic>?)
              ?.map((e) => e as Map<String, dynamic>)
              .toList() ?? [];
          
          // ✅ İlgili medyayı bul (int karşılaştırması)
          final mediaIndex = mediaList.indexWhere((m) {
            final mId = m['id'];
            final mIdInt = mId is String ? int.tryParse(mId) : (mId as int?);
            return mIdInt == mediaId;
          });
          
          if (mediaIndex != -1 && mounted) {
            // ✅ MediaViewerModal'ı aç
            Navigator.push(
              context,
              MaterialPageRoute(
                builder: (context) => MediaViewerModal(
                  mediaList: mediaList,
                  initialIndex: mediaIndex,
                  onMediaUpdated: () {
                    // ✅ Medya güncellendiğinde bildirimleri yenile
                    _loadNotifications();
                  },
                ),
              ),
            ).then((_) {
              // ✅ Modal kapandığında bildirimleri yenile
              _loadNotifications();
            });
          } else if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              const SnackBar(content: Text('Medya bulunamadı')),
            );
          }
        } catch (e) {
          if (kDebugMode) {
            debugPrint('Error opening media: $e');
          }
          if (mounted) {
            ScaffoldMessenger.of(context).showSnackBar(
              SnackBar(content: Text('Medya açılamadı: $e')),
            );
          }
        }
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? Colors.white : Colors.black;
    
    return Scaffold(
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        appBar: AppBar(
          title: Text(
            'Bildirimler',
            style: TextStyle(
              fontWeight: FontWeight.bold,
              color: textColor,
            ),
          ),
          backgroundColor: Theme.of(context).scaffoldBackgroundColor,
          elevation: 0,
          iconTheme: IconThemeData(color: textColor),
          actions: [
            if (_notifications.isNotEmpty && !_isSelectionMode)
              IconButton(
                icon: const Icon(Icons.checklist, color: Colors.blue),
                onPressed: () {
                  setState(() {
                    _isSelectionMode = true;
                    _selectedNotifications.clear();
                  });
                },
                tooltip: 'Seç',
              ),
            if (_isSelectionMode)
              IconButton(
                icon: Text(
                  '${_selectedNotifications.length}',
                  style: const TextStyle(
                    color: Colors.blue,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                onPressed: null,
                tooltip: 'Seçili: ${_selectedNotifications.length}',
              ),
            if (_isSelectionMode && _selectedNotifications.isNotEmpty)
              IconButton(
                icon: const Icon(Icons.delete, color: Colors.red),
                onPressed: _deleteSelectedNotifications,
                tooltip: 'Seçileni Sil',
              ),
            if (_isSelectionMode)
              IconButton(
                icon: Icon(Icons.close, color: textColor),
                onPressed: () {
                  setState(() {
                    _isSelectionMode = false;
                    _selectedNotifications.clear();
                  });
                },
                tooltip: 'İptal',
              ),
            if (_notifications.isNotEmpty && !_isSelectionMode)
              IconButton(
                icon: const Icon(Icons.delete_sweep, color: Colors.red),
                onPressed: _clearAllNotifications,
                tooltip: 'Tümünü Temizle',
              ),
          ],
          bottom: PreferredSize(
            preferredSize: Size.fromHeight(_notifications.isNotEmpty ? 110 : 60),
            child: Container(
              decoration: BoxDecoration(
                color: Theme.of(context).scaffoldBackgroundColor,
                border: Border(
                  bottom: BorderSide(
                    color: ThemeColors.border(context),
                    width: 1,
                  ),
                ),
              ),
              child: Column(
                children: [
                  // ✅ SearchBar
                  Padding(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                    child: TextField(
                      controller: _searchController,
                      decoration: InputDecoration(
                        hintText: 'Bildirimlerde ara...',
                        prefixIcon: Icon(
                          Icons.search,
                          color: isDark ? Colors.grey[400] : Colors.grey[600],
                        ),
                        suffixIcon: _searchQuery.isNotEmpty
                            ? IconButton(
                                icon: Icon(
                                  Icons.clear,
                                  color: isDark ? Colors.grey[400] : Colors.grey[600],
                                ),
                                onPressed: () {
                                  _searchController.clear();
                                },
                                tooltip: 'Temizle',
                              )
                            : null,
                        border: OutlineInputBorder(
                          borderRadius: BorderRadius.circular(12),
                          borderSide: BorderSide(
                            color: ThemeColors.border(context),
                          ),
                        ),
                        filled: true,
                        fillColor: ThemeColors.surface(context),
                        hintStyle: TextStyle(
                          color: isDark ? Colors.grey[400] : Colors.grey[600],
                        ),
                        contentPadding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                      ),
                      style: TextStyle(color: textColor),
                    ),
                  ),
                  // ✅ Filter Chips
                  if (_notifications.isNotEmpty)
                    Padding(
                      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 8),
                      child: SingleChildScrollView(
                        scrollDirection: Axis.horizontal,
                        child: Row(
                          children: [
                            _buildFilterChip('all', 'Tümü'),
                            const SizedBox(width: 8),
                            _buildFilterChip('like', 'Beğeniler'),
                            const SizedBox(width: 8),
                            _buildFilterChip('comment', 'Yorumlar'),
                            const SizedBox(width: 8),
                            _buildFilterChip('custom', 'Diğer'),
                          ],
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ),
        body: _isLoading && _notifications.isEmpty
            ? ListView.builder(
                itemCount: 6,
                itemBuilder: (context, index) => const NotificationCardShimmer(),
              )
            : _notifications.isEmpty
                ? Center(
                    child: Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(
                          _searchQuery.isNotEmpty || _selectedFilter != 'all'
                              ? Icons.search_off
                              : Icons.notifications_none,
                          size: 80,
                          color: isDark ? Colors.grey[600] : Colors.grey[400],
                        ),
                        const SizedBox(height: 16),
                        Text(
                          _searchQuery.isNotEmpty || _selectedFilter != 'all'
                              ? 'Sonuç bulunamadı'
                              : 'Henüz bildirim yok',
                          style: TextStyle(
                            fontSize: 18,
                            color: isDark ? Colors.grey[400] : Colors.grey[600],
                          ),
                        ),
                        if (_searchQuery.isNotEmpty || _selectedFilter != 'all')
                          Padding(
                            padding: const EdgeInsets.only(top: 8),
                            child: Text(
                              _searchQuery.isNotEmpty
                                  ? 'Arama kriterlerinizi değiştirmeyi deneyin'
                                  : 'Filtreleri temizlemeyi deneyin',
                              style: TextStyle(
                                fontSize: 14,
                                color: isDark ? Colors.grey[500] : Colors.grey[500],
                              ),
                            ),
                          ),
                      ],
                    ),
                  )
                : RefreshIndicator(
                    onRefresh: () => _loadNotifications(refresh: true),
                    child: ListView.separated(
                      controller: _scrollController,
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      itemCount: _filteredNotifications.length + (_hasMore && !_isLoading ? 1 : 0),
                      separatorBuilder: (context, index) => Divider(
                        height: 1,
                        color: Colors.grey[200],
                      ),
                      itemBuilder: (context, index) {
                        // ✅ Son öğe loading indicator - görünür görünmez otomatik yükle
                        if (index == _filteredNotifications.length && _hasMore) {
                          // ✅ Loading indicator render edildiğinde otomatik yükle (sadece bir kez)
                          if (!_autoLoadTriggered && !_isLoading) {
                            _autoLoadTriggered = true;
                            WidgetsBinding.instance.addPostFrameCallback((_) {
                              if (mounted && !_isLoading && _hasMore && _autoLoadTriggered) {
                                _autoLoadTriggered = false;
                                _loadMore();
                              }
                            });
                          }
                          return const Padding(
                            padding: EdgeInsets.all(16.0),
                            child: Center(child: CircularProgressIndicator()),
                          );
                        }

                        final notification = _filteredNotifications[index];
                        return _buildNotificationItem(notification);
                      },
                    ),
                  ),
    );
  }

  Widget _buildNotificationItem(Map<String, dynamic> notification) {
    final type = notification['type'];
    // ✅ Gruplu bildirimlerde id yok, unique key oluştur
    final notificationId = notification['id'] ?? 
                           (notification['media_id'] != null 
                            ? '${type}_${notification['media_id']}_${notification['event_id']}'
                            : '${type}_${notification.hashCode}');
    final isSelected = _selectedNotifications.contains(notificationId);
    
    Widget notificationWidget;
    if (type == 'like') {
      notificationWidget = _buildLikeNotification(notification);
    } else if (type == 'comment') {
      notificationWidget = _buildCommentNotification(notification);
    } else {
      notificationWidget = _buildCustomNotification(notification);
    }
    
    // ✅ Selection mode
    if (_isSelectionMode) {
      return InkWell(
        onTap: () {
          setState(() {
            if (isSelected) {
              _selectedNotifications.remove(notificationId);
            } else {
              _selectedNotifications.add(notificationId);
            }
          });
        },
        child: Container(
          color: isSelected 
              ? ThemeColors.primary(context).withOpacity(0.1)
              : Theme.of(context).cardTheme.color ?? ThemeColors.surface(context),
          child: Row(
            children: [
              Checkbox(
                value: isSelected,
                onChanged: (value) {
                  setState(() {
                    if (value == true) {
                      _selectedNotifications.add(notificationId);
                    } else {
                      _selectedNotifications.remove(notificationId);
                    }
                  });
                },
              ),
              Expanded(child: notificationWidget),
            ],
          ),
        ),
      );
    }
    
    // ✅ Normal mode (swipe to delete)
    return Dismissible(
      key: Key('notification_${notificationId}'),
      direction: DismissDirection.endToStart,
      background: Container(
        alignment: Alignment.centerRight,
        padding: const EdgeInsets.only(right: 20),
        color: Colors.red,
        child: const Icon(Icons.delete, color: Colors.white, size: 28),
      ),
      onDismissed: (direction) {
        // ✅ Önce lokal olarak listeden kaldır (widget hemen ağaçtan çıkarılsın)
        // Bildirimi bul (unique key ile)
        final notification = _notifications.firstWhere(
          (n) {
            final nId = n['id'] ?? 
                       (n['media_id'] != null 
                        ? '${n['type']}_${n['media_id']}_${n['event_id']}'
                        : '${n['type']}_${n.hashCode}');
            return nId == notificationId;
          },
          orElse: () => {},
        );
        
        final wasUnread = !(notification['is_read'] ?? false);
        final actualId = notification['id']; // Backend'den silmek için gerçek id
        
        setState(() {
          // Bildirimi listeden kaldır (unique key ile)
          _notifications.removeWhere(
            (n) {
              final nId = n['id'] ?? 
                         (n['media_id'] != null 
                          ? '${n['type']}_${n['media_id']}_${n['event_id']}'
                          : '${n['type']}_${n.hashCode}');
              return nId == notificationId;
            },
          );
          _filteredNotifications.removeWhere(
            (n) {
              final nId = n['id'] ?? 
                         (n['media_id'] != null 
                          ? '${n['type']}_${n['media_id']}_${n['event_id']}'
                          : '${n['type']}_${n.hashCode}');
              return nId == notificationId;
            },
          );
          
          // Unread count güncelle
          if (wasUnread && _unreadCount > 0) {
            _unreadCount--;
          }
        });
        
        // Sonra backend'den sil (async, arka planda)
        // ✅ Gruplu bildirim için media_id + event_id + type kullan
        if (actualId != null && actualId is int) {
          _deleteNotification(notificationId: actualId).catchError((e) {
            if (kDebugMode) {
              debugPrint('Error deleting notification after dismiss: $e');
            }
            // Hata durumunda bildirimi geri yükleme gerekmez, zaten silindi
          });
        } else if (notification['media_id'] != null && notification['event_id'] != null && notification['type'] != null) {
          // ✅ Gruplu bildirim - media_id, event_id ve type ile sil
          final mediaId = notification['media_id'] is int 
              ? notification['media_id'] 
              : int.tryParse(notification['media_id'].toString());
          final eventId = notification['event_id'] is int 
              ? notification['event_id'] 
              : int.tryParse(notification['event_id'].toString());
          final type = notification['type'] as String?;
          
          if (mediaId != null && eventId != null && type != null) {
            _deleteNotification(
              mediaId: mediaId,
              eventId: eventId,
              type: type,
            ).catchError((e) {
              if (kDebugMode) {
                debugPrint('Error deleting grouped notification after dismiss: $e');
              }
            });
          }
        }
      },
      child: notificationWidget,
    );
  }

  Widget _buildLikeNotification(Map<String, dynamic> notification) {
    // ✅ likers array'ini doğru şekilde parse et
    final likersRaw = notification['likers'];
    List<Map<String, dynamic>> likers = [];
    if (likersRaw != null) {
      if (likersRaw is List) {
        likers = likersRaw.map((e) {
          if (e is Map) {
            return Map<String, dynamic>.from(e);
          }
          return <String, dynamic>{};
        }).toList();
      }
    }
    
    if (kDebugMode) {
      debugPrint('🔍 Like Notification - likers: $likers, count: ${likers.length}');
      if (likers.isNotEmpty) {
        debugPrint('🔍 Like Notification - First liker: ${likers[0]}');
      }
    }
    
    final totalLikes = notification['total_likes'] ?? 0;
    final isRead = notification['is_read'] ?? false;
    final createdAt = notification['latest_created_at'] ?? notification['created_at'];
    final eventTitle = notification['event_title'] as String?;
    final mediaType = notification['media_type'] ?? 'foto'; // 'foto', 'video', 'hikaye'
    final mediaThumbnail = notification['media_thumbnail'] as String?;

    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = Theme.of(context).cardTheme.color ?? ThemeColors.surface(context);
    final unreadColor = ThemeColors.primary(context).withOpacity(0.1);

    return ListTile(
      onTap: () => _onNotificationTap(notification),
      tileColor: isRead ? cardColor : unreadColor,
      leading: _buildLikerAvatars(likers),
      title: _buildLikeText(likers, totalLikes, eventTitle, mediaType),
      subtitle: Text(
        _formatTimeAgo(createdAt),
        style: TextStyle(
          fontSize: 12,
          color: Colors.grey[600],
        ),
      ),
      trailing: _buildMediaThumbnail(mediaThumbnail),
    );
  }

  Widget _buildMediaThumbnail(String? thumbnailUrl) {
    if (thumbnailUrl == null || thumbnailUrl.isEmpty) {
      // ✅ Medya silinmiş veya bulunamadı
      final isDark = Theme.of(context).brightness == Brightness.dark;
      return Container(
        width: 50,
        height: 50,
        decoration: BoxDecoration(
          color: isDark ? Colors.grey[800] : Colors.grey[200],
          borderRadius: BorderRadius.circular(8),
          border: Border.all(color: ThemeColors.border(context)),
        ),
        child: Icon(
          Icons.image_not_supported,
          color: isDark ? Colors.grey[500] : Colors.grey[400],
          size: 24,
        ),
      );
    }

    // ✅ Video dosyası için thumbnail URL'sini düzelt (.mp4 → _thumb.jpg)
    String? imageUrl = thumbnailUrl;
    if (thumbnailUrl.toLowerCase().endsWith('.mp4')) {
      // Video dosyası için thumbnail URL'sini oluştur
      final urlWithoutExt = thumbnailUrl.substring(0, thumbnailUrl.length - 4);
      imageUrl = '${urlWithoutExt}_thumb.jpg';
    }

    return SizedBox(
      width: 50,
      height: 50,
      child: ClipRRect(
        borderRadius: BorderRadius.circular(8),
        child: CachedNetworkImage(
          imageUrl: imageUrl,
          fit: BoxFit.cover,
          placeholder: (context, url) {
            final isDark = Theme.of(context).brightness == Brightness.dark;
            return Container(
              color: isDark ? Colors.grey[800] : Colors.grey[200],
              child: const Center(
                child: CircularProgressIndicator(strokeWidth: 2),
              ),
            );
          },
          errorWidget: (context, url, error) {
            final isDark = Theme.of(context).brightness == Brightness.dark;
            // ✅ Thumbnail bulunamadıysa video icon göster
            if (thumbnailUrl.toLowerCase().endsWith('.mp4')) {
              return Container(
                color: isDark ? Colors.grey[800] : Colors.grey[200],
                child: Icon(
                  Icons.videocam,
                  color: isDark ? Colors.grey[500] : Colors.grey[400],
                  size: 24,
                ),
              );
            }
            return Container(
              color: isDark ? Colors.grey[800] : Colors.grey[200],
              child: Icon(
                Icons.image_not_supported,
                color: isDark ? Colors.grey[500] : Colors.grey[400],
                size: 24,
              ),
            );
          },
        ),
      ),
    );
  }

  Widget _buildLikerAvatars(List<Map<String, dynamic>> likers) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final avatarBgColor = isDark ? Colors.grey[700] : Colors.grey[300];
    final iconColor = isDark ? Colors.grey[400] : Colors.grey;
    
    if (likers.isEmpty) {
      return CircleAvatar(
        backgroundColor: avatarBgColor,
        child: Icon(Icons.person, color: iconColor),
      );
    }

    // ✅ Profil fotoğrafı URL'ini al (sender_profile_image veya profile_image)
    String? profileImageUrl;
    if (likers[0]['sender_profile_image'] != null && likers[0]['sender_profile_image'] != 'null') {
      profileImageUrl = likers[0]['sender_profile_image'];
    } else if (likers[0]['profile_image'] != null && likers[0]['profile_image'] != 'null') {
      profileImageUrl = likers[0]['profile_image'];
    }

    if (likers.length == 1) {
      return CircleAvatar(
        backgroundImage: profileImageUrl != null && profileImageUrl.isNotEmpty
            ? CachedNetworkImageProvider(profileImageUrl)
            : null,
        backgroundColor: avatarBgColor,
        child: profileImageUrl == null || profileImageUrl.isEmpty
            ? Icon(Icons.person, color: iconColor)
            : null,
      );
    }

    // Multiple likers - show stacked avatars
    return SizedBox(
      width: 50,
      height: 50,
      child: Stack(
        children: [
          Positioned(
            left: 0,
            child: CircleAvatar(
              radius: 18,
              backgroundImage: profileImageUrl != null && profileImageUrl.isNotEmpty
                  ? CachedNetworkImageProvider(profileImageUrl)
                  : null,
              backgroundColor: avatarBgColor,
              child: profileImageUrl == null || profileImageUrl.isEmpty
                  ? Icon(Icons.person, color: iconColor, size: 18)
                  : null,
            ),
          ),
          if (likers.length > 1)
            Positioned(
              left: 20,
              child: CircleAvatar(
                radius: 18,
                backgroundImage: (likers[1]['sender_profile_image'] ?? likers[1]['profile_image']) != null
                    ? CachedNetworkImageProvider(likers[1]['sender_profile_image'] ?? likers[1]['profile_image'])
                    : null,
                backgroundColor: isDark ? Colors.grey[600] : Colors.grey[400],
                child: (likers[1]['sender_profile_image'] ?? likers[1]['profile_image']) == null
                    ? Icon(Icons.person, color: iconColor, size: 18)
                    : null,
              ),
            ),
        ],
      ),
    );
  }

  Widget _buildLikeText(List<Map<String, dynamic>> likers, int totalLikes, String? eventTitle, String mediaType) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? Colors.white : Colors.black;
    final secondaryTextColor = isDark ? Colors.grey[300] : Colors.black87;
    final primaryColor = ThemeColors.primary(context);
    
    // ✅ Eğer total_likes > 0 ama likers boşsa, fallback göster
    if (totalLikes == 0) {
      return Text(
        eventTitle != null ? '${eventTitle} etkinliğinde beğeni' : 'Bilinmeyen kullanıcı beğendi',
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(color: textColor),
      );
    }
    
    // ✅ likers boş ama total_likes > 0 ise, sadece sayıyı göster
    if (likers.isEmpty && totalLikes > 0) {
      final contentType = (mediaType == 'hikaye' || mediaType == 'story') ? 'hikayeni' : 'medyanı';
      if (eventTitle != null && eventTitle.isNotEmpty) {
        return RichText(
          text: TextSpan(
            children: [
              TextSpan(
                text: '$totalLikes kişi ',
                style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
              ),
              TextSpan(
                text: eventTitle,
                style: TextStyle(fontWeight: FontWeight.bold, color: primaryColor),
              ),
              TextSpan(
                text: ' etkinliğindeki $contentType beğendi ',
                style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
              ),
              TextSpan(
                text: '[$totalLikes]',
                style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
              ),
            ],
          ),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        );
      } else {
        return RichText(
          text: TextSpan(
            children: [
              TextSpan(
                text: '$totalLikes kişi ',
                style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
              ),
              TextSpan(
                text: '$contentType beğendi ',
                style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
              ),
              TextSpan(
                text: '[$totalLikes]',
                style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
              ),
            ],
          ),
          maxLines: 2,
          overflow: TextOverflow.ellipsis,
        );
      }
    }
    
    if (likers.isEmpty) {
      return Text(
        eventTitle != null ? '${eventTitle} etkinliğinde beğeni' : 'Bilinmeyen kullanıcı beğendi',
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      );
    }

    List<TextSpan> spans = [];

    // ✅ Yeni format: "X kullanıcısı [ve Y kişi daha] [etkinlik] etkinliğindeki medyanı/hikayeni beğendi [toplam beğeni bold]"
    
    // İlk kullanıcı adı - name, sender_name veya ad+soyad'dan oluştur
    String? firstName;
    if (likers[0]['name'] != null && likers[0]['name'].toString().isNotEmpty) {
      firstName = likers[0]['name'].toString();
    } else if (likers[0]['sender_name'] != null && likers[0]['sender_name'].toString().isNotEmpty) {
      firstName = likers[0]['sender_name'].toString();
    } else if (likers[0]['sender_ad'] != null || likers[0]['sender_soyad'] != null) {
      final ad = likers[0]['sender_ad']?.toString() ?? '';
      final soyad = likers[0]['sender_soyad']?.toString() ?? '';
      firstName = (ad + ' ' + soyad).trim();
    }
    
    if (firstName == null || firstName.isEmpty) {
      firstName = 'Bilinmeyen';
    }
    
    spans.add(TextSpan(
      text: firstName,
      style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
    ));
    
    // ✅ Birden fazla kullanıcı varsa "ve X kişi daha" ekle
    if (totalLikes > 1) {
      spans.add(TextSpan(
        text: ' ve ${totalLikes - 1} kişi daha ',
        style: TextStyle(fontWeight: FontWeight.bold, color: secondaryTextColor),
      ));
    }
    
    // Medya/hikaye ayrımı
    final contentType = (mediaType == 'hikaye' || mediaType == 'story') ? 'hikayeni' : 'medyanı';
    
    // Etkinlik adı ve beğendi kısmı
    if (eventTitle != null && eventTitle.isNotEmpty) {
      spans.add(TextSpan(
        text: ' ',
        style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
      ));
      spans.add(TextSpan(
        text: eventTitle,
        style: TextStyle(fontWeight: FontWeight.bold, color: primaryColor),
      ));
      spans.add(TextSpan(
        text: ' etkinliğindeki $contentType beğendi ',
        style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
      ));
    } else {
      spans.add(TextSpan(
        text: ' $contentType beğendi ',
        style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
      ));
    }
    
    // ✅ Toplam beğeni sayısı (bold)
    spans.add(TextSpan(
      text: '[$totalLikes]',
      style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
    ));

    return RichText(
      text: TextSpan(children: spans),
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
    );
  }

  Widget _buildCommentNotification(Map<String, dynamic> notification) {
    // ✅ Gruplu comment bildirimi kontrolü
    // ✅ Backend'den 'commenters' array'i geliyor, 'comments' değil
    final commenters = notification['commenters'] as List<dynamic>?;
    final comments = notification['comments'] as List<dynamic>?;
    final totalComments = notification['total_comments'] ?? 0;
    final isRead = notification['is_read'] ?? false;
    final createdAt = notification['latest_created_at'] ?? notification['created_at'];
    final eventTitle = notification['event_title'] as String?;
    final mediaThumbnail = notification['media_thumbnail'] as String?;
    
    // ✅ Eğer commenters varsa onu kullan (yeni format)
    if (commenters != null && commenters.isNotEmpty) {
      final commenterList = commenters.map((c) => c as Map<String, dynamic>).toList();
      
      final isDark = Theme.of(context).brightness == Brightness.dark;
      final cardColor = Theme.of(context).cardTheme.color ?? ThemeColors.surface(context);
      final unreadColor = ThemeColors.primary(context).withOpacity(0.1);
      
      return ListTile(
        onTap: () => _onNotificationTap(notification),
        tileColor: isRead ? cardColor : unreadColor,
        leading: _buildCommentAvatars(commenterList),
        title: _buildCommentTextFromCommenters(commenterList, totalComments, eventTitle),
        subtitle: Text(
          _formatTimeAgo(createdAt),
          style: TextStyle(
            fontSize: 12,
            color: isDark ? Colors.grey[400] : Colors.grey[600],
          ),
        ),
        trailing: _buildMediaThumbnail(mediaThumbnail),
        isThreeLine: true,
      );
    }
    
    // ✅ Eski format desteği (tek yorum - comments array'i var)
    if (comments != null && comments.isNotEmpty) {
      final commentList = comments.map((c) => c as Map<String, dynamic>).toList();
      
      final isDark = Theme.of(context).brightness == Brightness.dark;
      final cardColor = Theme.of(context).cardTheme.color ?? ThemeColors.surface(context);
      final unreadColor = ThemeColors.primary(context).withOpacity(0.1);
      
      return ListTile(
        onTap: () => _onNotificationTap(notification),
        tileColor: isRead ? cardColor : unreadColor,
        leading: _buildCommentAvatars(commentList),
        title: _buildCommentText(commentList, totalComments, eventTitle),
        subtitle: Text(
          _formatTimeAgo(createdAt),
          style: TextStyle(
            fontSize: 12,
            color: isDark ? Colors.grey[400] : Colors.grey[600],
          ),
        ),
        trailing: _buildMediaThumbnail(mediaThumbnail),
        isThreeLine: true,
      );
    }
    
    // ✅ Çok eski format (tek commenter objesi)
    final commenter = notification['commenter'] ?? {};
    final commentText = notification['comment_text'] ?? '';
    
    String? profileImageUrl;
    if (commenter['sender_profile_image'] != null && commenter['sender_profile_image'] != 'null') {
      profileImageUrl = commenter['sender_profile_image'];
    } else if (commenter['profile_image'] != null && commenter['profile_image'] != 'null') {
      profileImageUrl = commenter['profile_image'];
    }

    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = Theme.of(context).cardTheme.color ?? ThemeColors.surface(context);
    final unreadColor = ThemeColors.primary(context).withOpacity(0.1);
    final textColor = isDark ? Colors.white : Colors.black;
    final secondaryTextColor = isDark ? Colors.grey[300] : Colors.black87;
    final commentTextColor = isDark ? Colors.grey[400] : Colors.grey[700];
    final avatarBgColor = isDark ? Colors.grey[700] : Colors.grey[300];
    final iconColor = isDark ? Colors.grey[400] : Colors.grey;
    
    return ListTile(
      onTap: () => _onNotificationTap(notification),
      tileColor: isRead ? cardColor : unreadColor,
      leading: CircleAvatar(
        backgroundImage: profileImageUrl != null && profileImageUrl.isNotEmpty
            ? CachedNetworkImageProvider(profileImageUrl)
            : null,
        backgroundColor: avatarBgColor,
        child: profileImageUrl == null || profileImageUrl.isEmpty
            ? Icon(Icons.person, color: iconColor)
            : null,
      ),
      title: RichText(
        text: TextSpan(
          children: [
            TextSpan(
              text: commenter['name'] ?? 'Bilinmeyen',
              style: TextStyle(
                fontWeight: FontWeight.bold,
                color: textColor,
              ),
            ),
            TextSpan(
              text: ' fotoğrafına yorum yaptı: ',
              style: TextStyle(
                fontWeight: FontWeight.normal,
                color: secondaryTextColor,
              ),
            ),
            TextSpan(
              text: commentText.length > 50
                  ? '${commentText.substring(0, 50)}...'
                  : commentText,
              style: TextStyle(
                fontWeight: FontWeight.normal,
                color: commentTextColor,
                fontStyle: FontStyle.italic,
              ),
            ),
          ],
        ),
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      ),
      subtitle: Text(
        _formatTimeAgo(createdAt),
        style: TextStyle(
          fontSize: 12,
          color: isDark ? Colors.grey[400] : Colors.grey[600],
        ),
      ),
      trailing: _buildMediaThumbnail(mediaThumbnail),
    );
  }
  
  // ✅ Comment text builder (commenters array'inden - yeni format)
  Widget _buildCommentTextFromCommenters(List<Map<String, dynamic>> commenters, int totalComments, String? eventTitle) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? Colors.white : Colors.black;
    final secondaryTextColor = isDark ? Colors.grey[300] : Colors.black87;
    final primaryColor = ThemeColors.primary(context);
    
    if (commenters.isEmpty) {
      return Text(
        eventTitle != null ? '$eventTitle etkinliğinde yorum' : 'Yorum bildirimi',
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
        style: TextStyle(color: textColor),
      );
    }
    
    List<TextSpan> spans = [];
    
    if (totalComments == 1) {
      spans.add(TextSpan(
        text: commenters[0]['name'] ?? 'Bilinmeyen Kullanıcı',
        style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
      ));
      spans.add(TextSpan(
        text: ' ',
        style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
      ));
      if (eventTitle != null && eventTitle.isNotEmpty) {
        spans.add(TextSpan(
          text: eventTitle,
          style: TextStyle(fontWeight: FontWeight.bold, color: primaryColor),
        ));
        spans.add(TextSpan(
          text: ' etkinliğindeki medyaya yorum yaptı',
          style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
        ));
      } else {
        spans.add(TextSpan(
          text: 'fotoğrafına yorum yaptı',
          style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
        ));
      }
    } else if (totalComments == 2) {
      spans.add(TextSpan(
        text: commenters[0]['name'] ?? 'Bilinmeyen Kullanıcı',
        style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
      ));
      spans.add(TextSpan(
        text: ' ve ',
        style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
      ));
      spans.add(TextSpan(
        text: commenters.length > 1 ? (commenters[1]['name'] ?? 'Bilinmeyen Kullanıcı') : 'Bilinmeyen Kullanıcı',
        style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
      ));
      spans.add(TextSpan(
        text: ' ',
        style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
      ));
      if (eventTitle != null && eventTitle.isNotEmpty) {
        spans.add(TextSpan(
          text: eventTitle,
          style: TextStyle(fontWeight: FontWeight.bold, color: primaryColor),
        ));
        spans.add(TextSpan(
          text: ' etkinliğindeki medyaya yorum yaptı',
          style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
        ));
      } else {
        spans.add(TextSpan(
          text: 'fotoğrafına yorum yaptı',
          style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
        ));
      }
    } else if (totalComments == 3) {
      spans.add(TextSpan(
        text: commenters[0]['name'] ?? 'Bilinmeyen Kullanıcı',
        style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
      ));
      spans.add(TextSpan(
        text: ', ',
        style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
      ));
      spans.add(TextSpan(
        text: commenters.length > 1 ? (commenters[1]['name'] ?? 'Bilinmeyen Kullanıcı') : 'Bilinmeyen Kullanıcı',
        style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
      ));
      spans.add(TextSpan(
        text: ' ve ',
        style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
      ));
      spans.add(TextSpan(
        text: commenters.length > 2 ? (commenters[2]['name'] ?? 'Bilinmeyen Kullanıcı') : 'Bilinmeyen Kullanıcı',
        style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
      ));
      spans.add(TextSpan(
        text: ' ',
        style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
      ));
      if (eventTitle != null && eventTitle.isNotEmpty) {
        spans.add(TextSpan(
          text: eventTitle,
          style: TextStyle(fontWeight: FontWeight.bold, color: primaryColor),
        ));
        spans.add(TextSpan(
          text: ' etkinliğindeki medyaya yorum yaptı',
          style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
        ));
      } else {
        spans.add(TextSpan(
          text: 'fotoğrafına yorum yaptı',
          style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
        ));
      }
    } else {
      spans.add(TextSpan(
        text: commenters[0]['name'] ?? 'Bilinmeyen Kullanıcı',
        style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
      ));
      if (commenters.length > 1) {
        spans.add(TextSpan(
          text: ', ',
          style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
        ));
        spans.add(TextSpan(
          text: commenters[1]['name'] ?? 'Bilinmeyen Kullanıcı',
          style: TextStyle(fontWeight: FontWeight.bold, color: textColor),
        ));
      }
      spans.add(TextSpan(
        text: ' ve ${totalComments - 2} kişi daha ',
        style: TextStyle(fontWeight: FontWeight.bold, color: secondaryTextColor),
      ));
      if (eventTitle != null && eventTitle.isNotEmpty) {
        spans.add(TextSpan(
          text: eventTitle,
          style: TextStyle(fontWeight: FontWeight.bold, color: primaryColor),
        ));
        spans.add(TextSpan(
          text: ' etkinliğindeki medyaya yorum yaptı',
          style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
        ));
      } else {
        spans.add(TextSpan(
          text: 'fotoğrafına yorum yaptı',
          style: TextStyle(fontWeight: FontWeight.normal, color: secondaryTextColor),
        ));
      }
    }
    
    return RichText(
      text: TextSpan(children: spans),
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
    );
  }
  
  // ✅ Comment avatar'ları (ilk 3 yorum)
  Widget _buildCommentAvatars(List<Map<String, dynamic>> comments) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final avatarBgColor = isDark ? Colors.grey[700] : Colors.grey[300];
    final iconColor = isDark ? Colors.grey[400] : Colors.grey;
    
    if (comments.isEmpty) {
      return CircleAvatar(
        backgroundColor: avatarBgColor,
        child: Icon(Icons.person, color: iconColor),
      );
    }
    
    if (comments.length == 1) {
      final comment = comments[0];
      final profileImage = comment['profile_image'];
      return CircleAvatar(
        backgroundImage: profileImage != null && profileImage != 'null' && profileImage.toString().isNotEmpty
            ? CachedNetworkImageProvider(profileImage.toString())
            : null,
        backgroundColor: avatarBgColor,
        child: profileImage == null || profileImage == 'null' || profileImage.toString().isEmpty
            ? Icon(Icons.person, color: iconColor)
            : null,
      );
    }
    
    // ✅ 2-3 yorum için stack avatar
    return SizedBox(
      width: 56, // 32 (avatar) + 18 (space) + 6 (border) = max width
      height: 40,
      child: Stack(
        clipBehavior: Clip.none,
        children: [
          for (int i = 0; i < comments.length.clamp(0, 3); i++)
            Positioned(
              left: i * 18.0,
              child: Container(
                width: 32,
                height: 32,
                decoration: BoxDecoration(
                  shape: BoxShape.circle,
                  border: Border.all(color: Colors.white, width: 2),
                ),
                child: CircleAvatar(
                  radius: 14,
                  backgroundImage: comments[i]['profile_image'] != null && 
                                   comments[i]['profile_image'] != 'null' &&
                                   comments[i]['profile_image'].toString().isNotEmpty
                      ? CachedNetworkImageProvider(comments[i]['profile_image'].toString())
                      : null,
                  backgroundColor: Colors.grey[300],
                  child: comments[i]['profile_image'] == null || 
                         comments[i]['profile_image'] == 'null' ||
                         comments[i]['profile_image'].toString().isEmpty
                      ? const Icon(Icons.person, color: Colors.grey, size: 16)
                      : null,
                ),
              ),
            ),
        ],
      ),
    );
  }
  
  // ✅ Comment text builder (gruplu)
  Widget _buildCommentText(List<Map<String, dynamic>> comments, int totalComments, String? eventTitle) {
    if (comments.isEmpty) {
      return Text(
        eventTitle != null ? '$eventTitle etkinliğinde yorum' : 'Yorum bildirimi',
        maxLines: 2,
        overflow: TextOverflow.ellipsis,
      );
    }
    
    List<TextSpan> spans = [];
    
    if (totalComments == 1) {
      spans.add(TextSpan(
        text: comments[0]['commenter_name'] ?? 'Bilinmeyen',
        style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.black),
      ));
      spans.add(const TextSpan(
        text: ' fotoğrafına yorum yaptı: ',
        style: TextStyle(fontWeight: FontWeight.normal, color: Colors.black87),
      ));
      final commentText = comments[0]['comment_text'] ?? '';
      spans.add(TextSpan(
        text: commentText.length > 50 ? '${commentText.substring(0, 50)}...' : commentText,
        style: TextStyle(
          fontWeight: FontWeight.normal,
          color: Colors.grey[700],
          fontStyle: FontStyle.italic,
        ),
      ));
    } else if (totalComments == 2) {
      spans.add(TextSpan(
        text: comments[0]['commenter_name'] ?? 'Bilinmeyen',
        style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.black),
      ));
      spans.add(const TextSpan(
        text: ' ve ',
        style: TextStyle(fontWeight: FontWeight.normal, color: Colors.black87),
      ));
      spans.add(TextSpan(
        text: comments[1]['commenter_name'] ?? 'Bilinmeyen',
        style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.black),
      ));
      spans.add(const TextSpan(
        text: ' fotoğrafına yorum yaptı',
        style: TextStyle(fontWeight: FontWeight.normal, color: Colors.black87),
      ));
    } else if (totalComments == 3) {
      spans.add(TextSpan(
        text: comments[0]['commenter_name'] ?? 'Bilinmeyen',
        style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.black),
      ));
      spans.add(const TextSpan(
        text: ', ',
        style: TextStyle(fontWeight: FontWeight.normal, color: Colors.black87),
      ));
      spans.add(TextSpan(
        text: comments[1]['commenter_name'] ?? 'Bilinmeyen',
        style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.black),
      ));
      spans.add(const TextSpan(
        text: ' ve ',
        style: TextStyle(fontWeight: FontWeight.normal, color: Colors.black87),
      ));
      spans.add(TextSpan(
        text: comments[2]['commenter_name'] ?? 'Bilinmeyen',
        style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.black),
      ));
      spans.add(const TextSpan(
        text: ' fotoğrafına yorum yaptı',
        style: TextStyle(fontWeight: FontWeight.normal, color: Colors.black87),
      ));
    } else {
      spans.add(TextSpan(
        text: comments[0]['commenter_name'] ?? 'Bilinmeyen',
        style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.black),
      ));
      spans.add(const TextSpan(
        text: ', ',
        style: TextStyle(fontWeight: FontWeight.normal, color: Colors.black87),
      ));
      spans.add(TextSpan(
        text: comments[1]['commenter_name'] ?? 'Bilinmeyen',
        style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.black),
      ));
      spans.add(TextSpan(
        text: ' ve ${totalComments - 2} kişi daha ',
        style: const TextStyle(fontWeight: FontWeight.bold, color: Colors.black87),
      ));
      spans.add(const TextSpan(
        text: 'fotoğrafına yorum yaptı',
        style: TextStyle(fontWeight: FontWeight.normal, color: Colors.black87),
      ));
    }
    
    return RichText(
      text: TextSpan(children: spans),
      maxLines: 2,
      overflow: TextOverflow.ellipsis,
    );
  }

  Widget _buildCustomNotification(Map<String, dynamic> notification) {
    final title = notification['title'] ?? 'Bildirim';
    final message = notification['message'] ?? '';
    final isRead = notification['is_read'] ?? false;
    final createdAt = notification['created_at'];
    final sender = notification['sender'];

    // ✅ Profil fotoğrafı URL'ini al
    String? profileImageUrl;
    if (sender != null) {
      if (sender['sender_profile_image'] != null && sender['sender_profile_image'] != 'null') {
        profileImageUrl = sender['sender_profile_image'];
      } else if (sender['profile_image'] != null && sender['profile_image'] != 'null') {
        profileImageUrl = sender['profile_image'];
      }
    }

    final isDark = Theme.of(context).brightness == Brightness.dark;
    final cardColor = Theme.of(context).cardTheme.color ?? ThemeColors.surface(context);
    final unreadColor = ThemeColors.primary(context).withOpacity(0.1);
    final textColor = isDark ? Colors.white : Colors.black;
    final secondaryTextColor = isDark ? Colors.grey[300] : Colors.black87;
    
    return ListTile(
      onTap: () => _onNotificationTap(notification),
      tileColor: isRead ? cardColor : unreadColor,
      leading: profileImageUrl != null && profileImageUrl.isNotEmpty
          ? CircleAvatar(
              backgroundImage: CachedNetworkImageProvider(profileImageUrl),
            )
          : CircleAvatar(
              backgroundColor: AppColors.primary,
              child: const Icon(Icons.notifications, color: Colors.white),
            ),
      title: Text(
        title,
        style: TextStyle(
          fontWeight: FontWeight.bold,
          color: textColor,
        ),
      ),
      subtitle: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const SizedBox(height: 4),
          Text(
            message,
            maxLines: 2,
            overflow: TextOverflow.ellipsis,
            style: TextStyle(color: secondaryTextColor),
          ),
          const SizedBox(height: 4),
          Text(
            _formatTimeAgo(createdAt),
            style: TextStyle(
              fontSize: 12,
              color: isDark ? Colors.grey[400] : Colors.grey[600],
            ),
          ),
        ],
      ),
      isThreeLine: true,
    );
  }

  String _formatTimeAgo(String? dateString) {
    if (dateString == null || dateString.isEmpty) return '';

    try {
      final date = DateTime.parse(dateString);
      final now = DateTime.now();
      final difference = now.difference(date);

      // Saniyeler
      if (difference.inSeconds < 60) {
        return 'Az önce';
      }
      
      // ✅ Gün kontrolü - Bugün/Dün (saat kontrolünden önce)
      final today = DateTime(now.year, now.month, now.day);
      final notificationDate = DateTime(date.year, date.month, date.day);
      
      // Bugün kontrolü (aynı gün)
      if (notificationDate == today) {
        // Bugün - dakika/saat göster
        final minutes = difference.inMinutes;
        if (minutes < 60) {
          if (minutes == 1) {
            return '1 dakika önce';
          }
          return '$minutes dakika önce';
        }
        final hours = difference.inHours;
        if (hours == 1) {
          return '1 saat önce';
        }
        return '$hours saat önce';
      }
      
      // Dün kontrolü
      final yesterday = today.subtract(const Duration(days: 1));
      if (notificationDate == yesterday) {
        return 'Dün';
      }
      
      // Dakikalar (bugün değilse)
      final minutes = difference.inMinutes;
      if (minutes < 60) {
        if (minutes == 1) {
          return '1 dakika önce';
        }
        return '$minutes dakika önce';
      }
      
      // Saatler (bugün değilse)
      final hours = difference.inHours;
      if (hours < 24) {
        if (hours == 1) {
          return '1 saat önce';
        }
        return '$hours saat önce';
      }
      
      // Günler
      final days = difference.inDays;
      if (days < 7) {
        if (days == 1) {
          return '1 gün önce';
        }
        return '$days gün önce';
      }
      
      // Haftalar
      final weeks = (days / 7).floor();
      if (weeks < 4) {
        if (weeks == 1) {
          return '1 hafta önce';
        }
        return '$weeks hafta önce';
      }
      
      // Aylar
      final months = (days / 30).floor();
      if (months < 12) {
        if (months == 1) {
          return '1 ay önce';
        }
        return '$months ay önce';
      }
      
      // Yıllar
      final years = (days / 365).floor();
      if (years == 1) {
        return '1 yıl önce';
      }
      return '$years yıl önce';
    } catch (e) {
      // Eğer parse edilemezse, orijinal string'i formatlamaya çalış
      try {
        // Tarih formatını kontrol et (yyyy-MM-dd HH:mm:ss veya başka formatlar)
        if (dateString.contains('T')) {
          // ISO format: 2024-01-15T14:30:00
          final date = DateTime.parse(dateString);
          final now = DateTime.now();
          final difference = now.difference(date);
          
          if (difference.inDays > 365) {
            return '${(difference.inDays / 365).floor()} yıl önce';
          } else if (difference.inDays > 30) {
            return '${(difference.inDays / 30).floor()} ay önce';
          } else if (difference.inDays > 7) {
            return '${(difference.inDays / 7).floor()} hafta önce';
          } else if (difference.inDays > 0) {
            return '${difference.inDays} gün önce';
          } else if (difference.inHours > 0) {
            return '${difference.inHours} saat önce';
          } else if (difference.inMinutes > 0) {
            return '${difference.inMinutes} dakika önce';
          } else {
            return 'Az önce';
          }
        }
      } catch (_) {
        // İkinci parse da başarısız olursa boş string döndür
      }
      return '';
    }
  }

  // ✅ Filtre chip widget'ı
  Widget _buildFilterChip(String filter, String label) {
    final isSelected = _selectedFilter == filter;
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final primaryColor = ThemeColors.primary(context);
    final textColor = isDark ? Colors.white : Colors.black87;
    final chipBgColor = isDark ? Colors.grey[800] : Colors.grey[200];
    final selectedBgColor = primaryColor.withOpacity(isDark ? 0.3 : 0.2);
    final borderColor = isSelected 
        ? primaryColor 
        : (isDark ? Colors.grey[600]! : Colors.grey[300]!);
    
    return FilterChip(
      label: Text(
        label,
        style: TextStyle(
          color: isSelected ? primaryColor : textColor,
          fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
        ),
      ),
      selected: isSelected,
      onSelected: (selected) => _setFilter(filter),
      backgroundColor: chipBgColor,
      selectedColor: selectedBgColor,
      side: BorderSide(
        color: borderColor,
        width: isSelected ? 2 : 1,
      ),
    );
  }
}
