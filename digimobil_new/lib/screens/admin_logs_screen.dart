import 'package:flutter/material.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/utils/colors.dart';

class AdminLogsScreen extends StatefulWidget {
  const AdminLogsScreen({super.key});

  @override
  State<AdminLogsScreen> createState() => _AdminLogsScreenState();
}

class _AdminLogsScreenState extends State<AdminLogsScreen> {
  final ApiService _apiService = ApiService();
  List<Map<String, dynamic>> _logs = [];
  bool _isLoading = false;
  String _selectedAction = 'all';
  String? _selectedUserId;
  int _currentPage = 1;
  int _totalPages = 1;
  int _totalLogs = 0;

  final List<String> _actions = [
    'all',
    'register',
    'login',
    'logout',
    'profile_update',
    'password_change',
    'media_upload',
    'media_delete',
    'story_upload',
    'story_delete',
    'comment_add',
    'comment_delete',
    'like',
    'unlike',
    'story_like',
    'story_unlike',
    'event_join',
    'event_leave',
    'profile_visit'
  ];

  @override
  void initState() {
    super.initState();
    _loadLogs();
  }

  Future<void> _loadLogs({bool refresh = false}) async {
    if (refresh) {
      _currentPage = 1;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      final result = await _apiService.getLogs(
        action: _selectedAction,
        userId: _selectedUserId,
        page: _currentPage,
        limit: 50,
      );

      if (result['success'] == true) {
        setState(() {
          if (refresh) {
            _logs = List<Map<String, dynamic>>.from(result['logs']);
          } else {
            _logs.addAll(List<Map<String, dynamic>>.from(result['logs']));
          }
          
          _totalLogs = result['pagination']['total'];
          _totalPages = result['pagination']['total_pages'];
        });
      } else {
        _showSnackBar('Loglar yüklenirken hata: ${result['message']}', isError: true);
      }
    } catch (e) {
      _showSnackBar('Loglar yüklenirken hata: $e', isError: true);
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

  String _getActionIcon(String action) {
    switch (action) {
      case 'register':
        return '👤';
      case 'login':
        return '🔑';
      case 'logout':
        return '🚪';
      case 'profile_update':
        return '✏️';
      case 'password_change':
        return '🔒';
      case 'media_upload':
        return '📷';
      case 'media_delete':
        return '🗑️';
      case 'story_upload':
        return '📖';
      case 'story_delete':
        return '🗑️';
      case 'comment_add':
        return '💬';
      case 'comment_delete':
        return '🗑️';
      case 'like':
        return '❤️';
      case 'unlike':
        return '💔';
      case 'story_like':
        return '⭐';
      case 'story_unlike':
        return '✨';
      case 'event_join':
        return '➕';
      case 'event_leave':
        return '➖';
      case 'profile_visit':
        return '👁️';
      default:
        return '📝';
    }
  }

  Color _getActionColor(String action) {
    switch (action) {
      case 'register':
        return Colors.green;
      case 'login':
        return Colors.blue;
      case 'logout':
        return Colors.orange;
      case 'profile_update':
        return Colors.purple;
      case 'password_change':
        return Colors.red;
      case 'media_upload':
        return Colors.teal;
      case 'media_delete':
        return Colors.red;
      case 'story_upload':
        return Colors.indigo;
      case 'story_delete':
        return Colors.red;
      case 'comment_add':
        return Colors.cyan;
      case 'comment_delete':
        return Colors.red;
      case 'like':
        return Colors.pink;
      case 'unlike':
        return Colors.grey;
      case 'story_like':
        return Colors.orange;
      case 'story_unlike':
        return Colors.grey;
      case 'event_join':
        return Colors.green;
      case 'event_leave':
        return Colors.orange;
      case 'profile_visit':
        return Colors.deepPurple;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        title: const Text('Kullanıcı Logları'),
        backgroundColor: AppColors.primary,
        foregroundColor: Colors.white,
        elevation: 0,
        actions: [
          IconButton(
            icon: const Icon(Icons.refresh),
            onPressed: () => _loadLogs(refresh: true),
          ),
        ],
      ),
      body: Column(
        children: [
          // Filters
          Container(
            padding: const EdgeInsets.all(16),
            color: Colors.white,
            child: Row(
              children: [
                Expanded(
                  child: DropdownButtonFormField<String>(
                    value: _selectedAction,
                    decoration: const InputDecoration(
                      labelText: 'Aksiyon',
                      border: OutlineInputBorder(),
                      contentPadding: EdgeInsets.symmetric(horizontal: 12, vertical: 8),
                    ),
                    items: _actions.map((action) {
                      return DropdownMenuItem(
                        value: action,
                        child: Text(action == 'all' ? 'Tümü' : action),
                      );
                    }).toList(),
                    onChanged: (value) {
                      setState(() {
                        _selectedAction = value!;
                      });
                      _loadLogs(refresh: true);
                    },
                  ),
                ),
                const SizedBox(width: 16),
                IconButton(
                  icon: const Icon(Icons.filter_list),
                  onPressed: () {
                    // TODO: Advanced filters
                    _showSnackBar('Gelişmiş filtreler yakında eklenecek');
                  },
                ),
              ],
            ),
          ),
          
          // Stats
          Container(
            padding: const EdgeInsets.all(16),
            color: Colors.white,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _buildStatCard('Toplam Log', _totalLogs.toString(), Icons.list),
                _buildStatCard('Sayfa', '$_currentPage / $_totalPages', Icons.pages),
                _buildStatCard('Gösterilen', _logs.length.toString(), Icons.visibility),
              ],
            ),
          ),
          
          // Logs List
          Expanded(
            child: _isLoading && _logs.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : _logs.isEmpty
                    ? const Center(
                        child: Text(
                          'Log bulunamadı',
                          style: TextStyle(fontSize: 16, color: Colors.grey),
                        ),
                      )
                    : RefreshIndicator(
                        onRefresh: () => _loadLogs(refresh: true),
                        child: ListView.builder(
                          itemCount: _logs.length + (_isLoading ? 1 : 0),
                          itemBuilder: (context, index) {
                            if (index == _logs.length) {
                              return const Center(
                                child: Padding(
                                  padding: EdgeInsets.all(16),
                                  child: CircularProgressIndicator(),
                                ),
                              );
                            }
                            
                            final log = _logs[index];
                            return _buildLogCard(log);
                          },
                        ),
                      ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatCard(String title, String value, IconData icon) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: AppColors.primary.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        children: [
          Icon(icon, color: AppColors.primary, size: 20),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: AppColors.primary,
            ),
          ),
          Text(
            title,
            style: const TextStyle(
              fontSize: 12,
              color: Colors.grey,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildLogCard(Map<String, dynamic> log) {
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      child: Padding(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Header
            Row(
              children: [
                Text(
                  _getActionIcon(log['action']),
                  style: const TextStyle(fontSize: 24),
                ),
                const SizedBox(width: 12),
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        log['action_text'] ?? log['action'],
                        style: const TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                      Text(
                        log['user_name'] ?? 'Bilinmeyen Kullanıcı',
                        style: TextStyle(
                          fontSize: 14,
                          color: Colors.grey[600],
                        ),
                      ),
                    ],
                  ),
                ),
                Container(
                  padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                  decoration: BoxDecoration(
                    color: _getActionColor(log['action']).withOpacity(0.1),
                    borderRadius: BorderRadius.circular(12),
                  ),
                  child: Text(
                    log['formatted_time'] ?? '',
                    style: TextStyle(
                      fontSize: 12,
                      color: _getActionColor(log['action']),
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ),
              ],
            ),
            
            const SizedBox(height: 12),
            
            // User Info
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.grey[100],
                borderRadius: BorderRadius.circular(8),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildInfoRow('👤', 'Kullanıcı', log['user_name'] ?? ''),
                  _buildInfoRow('📧', 'Email', log['user_email'] ?? ''),
                  _buildInfoRow('📱', 'Telefon', log['user_phone'] ?? ''),
                  _buildInfoRow('🏷️', 'Kullanıcı Adı', log['user_username'] ?? ''),
                ],
              ),
            ),
            
            const SizedBox(height: 12),
            
            // Technical Info
            Container(
              padding: const EdgeInsets.all(12),
              decoration: BoxDecoration(
                color: Colors.blue[50],
                borderRadius: BorderRadius.circular(8),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildInfoRow('🌐', 'IP Adresi', log['ip_address'] ?? ''),
                  _buildInfoRow('📱', 'Cihaz', log['device_info'] ?? ''),
                  if (log['user_agent'] != null && log['user_agent'].isNotEmpty)
                    _buildInfoRow('🔍', 'User Agent', log['user_agent']),
                ],
              ),
            ),
            
            // Details - Detaylı log bilgileri
            if (log['details'] != null && log['details'] is Map) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.green[50],
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Detaylar:',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 8),
                    ..._buildDetailedLogs(log['details'] as Map<String, dynamic>, log['action'] as String),
                  ],
                ),
              ),
            ] else if (log['details'] != null && log['details'].toString().isNotEmpty) ...[
              const SizedBox(height: 12),
              Container(
                padding: const EdgeInsets.all(12),
                decoration: BoxDecoration(
                  color: Colors.green[50],
                  borderRadius: BorderRadius.circular(8),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    const Text(
                      'Detaylar:',
                      style: TextStyle(
                        fontWeight: FontWeight.bold,
                        fontSize: 14,
                      ),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      log['details'].toString(),
                      style: const TextStyle(fontSize: 12),
                    ),
                  ],
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildInfoRow(String icon, String label, String value) {
    if (value.isEmpty) return const SizedBox.shrink();
    
    return Padding(
      padding: const EdgeInsets.only(bottom: 4),
      child: Row(
        children: [
          Text(icon, style: const TextStyle(fontSize: 16)),
          const SizedBox(width: 8),
          Text(
            '$label: ',
            style: const TextStyle(
              fontWeight: FontWeight.w500,
              fontSize: 12,
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(fontSize: 12),
            ),
          ),
        ],
      ),
    );
  }

  // ✅ Detaylı log bilgilerini göster
  List<Widget> _buildDetailedLogs(Map<String, dynamic> details, String action) {
    List<Widget> widgets = [];
    
    // Profile Visit
    if (action == 'profile_visit') {
      if (details['visited_user_id'] != null) {
        widgets.add(_buildInfoRow('👤', 'Ziyaret Edilen Kullanıcı', 
            'ID: ${details['visited_user_id']}'));
      }
      if (details['visited_user_name'] != null) {
        widgets.add(_buildInfoRow('📝', 'Ziyaret Edilen İsim', 
            details['visited_user_name'].toString()));
      }
    }
    
    // Like/Unlike
    if (action == 'like' || action == 'unlike') {
      if (details['event_id'] != null) {
        widgets.add(_buildInfoRow('🎉', 'Etkinlik', 
            'ID: ${details['event_id']}'));
      }
      if (details['event_title'] != null) {
        widgets.add(_buildInfoRow('📌', 'Etkinlik Adı', 
            details['event_title'].toString()));
      }
      if (details['media_id'] != null) {
        widgets.add(_buildInfoRow('🖼️', 'Medya ID', 
            details['media_id'].toString()));
      }
      if (details['media_type'] != null) {
        widgets.add(_buildInfoRow('📸', 'Medya Türü', 
            details['media_type'].toString()));
      }
    }
    
    // Comment Add
    if (action == 'comment_add') {
      if (details['event_id'] != null) {
        widgets.add(_buildInfoRow('🎉', 'Etkinlik', 
            'ID: ${details['event_id']}'));
      }
      if (details['event_title'] != null) {
        widgets.add(_buildInfoRow('📌', 'Etkinlik Adı', 
            details['event_title'].toString()));
      }
      if (details['media_id'] != null) {
        widgets.add(_buildInfoRow('🖼️', 'Medya ID', 
            details['media_id'].toString()));
      }
      if (details['comment_id'] != null) {
        widgets.add(_buildInfoRow('💬', 'Yorum ID', 
            details['comment_id'].toString()));
      }
      if (details['comment_text'] != null) {
        widgets.add(_buildInfoRow('📝', 'Yorum Metni', 
            details['comment_text'].toString()));
      }
    }
    
    // Event Join
    if (action == 'event_join') {
      if (details['event_id'] != null) {
        widgets.add(_buildInfoRow('🎉', 'Etkinlik ID', 
            details['event_id'].toString()));
      }
      if (details['event_title'] != null) {
        widgets.add(_buildInfoRow('📌', 'Etkinlik Adı', 
            details['event_title'].toString()));
      }
      if (details['qr_code'] != null) {
        widgets.add(_buildInfoRow('📱', 'QR Kod', 
            details['qr_code'].toString()));
      }
      if (details['join_method'] != null) {
        widgets.add(_buildInfoRow('🔗', 'Katılma Yöntemi', 
            details['join_method'].toString()));
      }
    }
    
    // Story Like
    if (action == 'story_like' || action == 'story_unlike') {
      if (details['story_id'] != null) {
        widgets.add(_buildInfoRow('📖', 'Hikaye ID', 
            details['story_id'].toString()));
      }
      if (details['event_id'] != null) {
        widgets.add(_buildInfoRow('🎉', 'Etkinlik ID', 
            details['event_id'].toString()));
      }
      if (details['event_title'] != null) {
        widgets.add(_buildInfoRow('📌', 'Etkinlik Adı', 
            details['event_title'].toString()));
      }
    }
    
    // Tarih bilgisi (varsa)
    if (details['created_at'] != null) {
      widgets.add(_buildInfoRow('📅', 'İşlem Tarihi', 
          details['created_at'].toString()));
    }
    
    return widgets.isEmpty 
        ? [const Text('Detay bilgisi bulunamadı', style: TextStyle(fontSize: 12))]
        : widgets;
  }
}
