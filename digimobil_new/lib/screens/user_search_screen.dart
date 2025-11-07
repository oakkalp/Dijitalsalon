import 'package:flutter/material.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/theme_colors.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:digimobil_new/screens/profile_screen.dart';
import 'package:provider/provider.dart';
import 'package:digimobil_new/providers/auth_provider.dart';

class UserSearchScreen extends StatefulWidget {
  const UserSearchScreen({super.key});

  @override
  State<UserSearchScreen> createState() => _UserSearchScreenState();
}

class _UserSearchScreenState extends State<UserSearchScreen> {
  final ApiService _apiService = ApiService();
  final TextEditingController _searchController = TextEditingController();
  
  List<Map<String, dynamic>> _users = [];
  bool _isLoading = false;
  bool _hasSearched = false;
  String _currentQuery = '';
  int _currentPage = 1;
  int _totalPages = 1;
  int _totalUsers = 0;

  @override
  void initState() {
    super.initState();
    _searchController.addListener(_onSearchChanged);
  }

  void _onSearchChanged() {
    final query = _searchController.text.trim();
    if (query.length >= 2) {
      _searchUsers(query, refresh: true);
    } else if (query.isEmpty) {
      setState(() {
        _users = [];
        _hasSearched = false;
        _currentQuery = '';
      });
    }
  }

  Future<void> _searchUsers(String query, {bool refresh = false}) async {
    if (refresh) {
      _currentPage = 1;
    }

    setState(() {
      _isLoading = true;
      _currentQuery = query;
    });

    try {
      print('🔍 UserSearch - Searching for: "$query"');
      final result = await _apiService.searchUsers(
        query: query,
        page: _currentPage,
        limit: 20,
      );

      print('🔍 UserSearch - API Response: $result');

      if (result['success'] == true) {
        final users = result['users'] as List<dynamic>? ?? [];
        final pagination = result['pagination'] as Map<String, dynamic>? ?? {};
        
        setState(() {
          if (refresh) {
            _users = users.cast<Map<String, dynamic>>();
          } else {
            _users.addAll(users.cast<Map<String, dynamic>>());
          }
          
          _totalUsers = pagination['total'] ?? 0;
          _totalPages = pagination['total_pages'] ?? 1;
          _hasSearched = true;
        });
        
        print('🔍 UserSearch - Found ${_users.length} users');
      } else {
        print('❌ UserSearch - API Error: ${result['message']}');
        _showSnackBar('Arama sırasında hata: ${result['message']}', isError: true);
      }
    } catch (e) {
      print('❌ UserSearch - Exception: $e');
      _showSnackBar('Arama sırasında hata: $e', isError: true);
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

  String _getRoleText(String role) {
    switch (role) {
      case 'super_admin':
        return 'Süper Admin';
      case 'moderator':
        return 'Moderatör';
      case 'kullanici':
        return 'Kullanıcı';
      default:
        return role;
    }
  }

  Color _getRoleColor(String role) {
    switch (role) {
      case 'super_admin':
        return Colors.red;
      case 'moderator':
        return Colors.blue;
      case 'kullanici':
        return Colors.green;
      default:
        return Colors.grey;
    }
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('Kullanıcı Ara'),
        backgroundColor: ThemeColors.primary(context),
        foregroundColor: Colors.white,
        elevation: 0,
        bottom: PreferredSize(
          preferredSize: const Size.fromHeight(60),
          child: Padding(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _searchController,
              decoration: InputDecoration(
                hintText: 'Kullanıcı adı, ad soyad veya email ara...',
                prefixIcon: Icon(
                  Icons.search,
                  color: isDark ? Colors.grey[400] : Colors.grey[600],
                ),
                suffixIcon: _searchController.text.isNotEmpty
                    ? IconButton(
                        icon: Icon(
                          Icons.clear,
                          color: isDark ? Colors.grey[400] : Colors.grey[600],
                        ),
                        onPressed: () {
                          _searchController.clear();
                        },
                      )
                    : null,
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(12),
                  borderSide: BorderSide(color: ThemeColors.border(context)),
                ),
                filled: true,
                fillColor: ThemeColors.surface(context),
                hintStyle: TextStyle(
                  color: isDark ? Colors.grey[400] : Colors.grey[600],
                ),
              ),
            ),
          ),
        ),
      ),
      body: Column(
        children: [
          // Stats
          if (_hasSearched)
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Theme.of(context).cardTheme.color ?? ThemeColors.surface(context),
                border: Border(
                  bottom: BorderSide(
                    color: ThemeColors.border(context),
                    width: 1,
                  ),
                ),
              ),
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceAround,
                children: [
                  _buildStatCard('Bulunan', _totalUsers.toString(), Icons.people),
                  _buildStatCard('Sayfa', '$_currentPage / $_totalPages', Icons.pages),
                  _buildStatCard('Gösterilen', _users.length.toString(), Icons.visibility),
                ],
              ),
            ),
          
          // Results
          Expanded(
            child: _isLoading && _users.isEmpty
                ? const Center(child: CircularProgressIndicator())
                : !_hasSearched
                    ? Center(
                        child: Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(
                              Icons.search,
                              size: 80,
                              color: isDark ? Colors.grey[600] : Colors.grey,
                            ),
                            const SizedBox(height: 16),
                            Text(
                              'Kullanıcı aramak için yazmaya başlayın',
                              style: TextStyle(
                                fontSize: 16,
                                color: isDark ? Colors.grey[400] : Colors.grey,
                              ),
                            ),
                            const SizedBox(height: 8),
                            Text(
                              'En az 2 karakter girin',
                              style: TextStyle(
                                fontSize: 14,
                                color: isDark ? Colors.grey[500] : Colors.grey,
                              ),
                            ),
                          ],
                        ),
                      )
                    : _users.isEmpty
                        ? Center(
                            child: Column(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(
                                  Icons.person_search,
                                  size: 80,
                                  color: isDark ? Colors.grey[600] : Colors.grey,
                                ),
                                const SizedBox(height: 16),
                                Text(
                                  'Kullanıcı bulunamadı',
                                  style: TextStyle(
                                    fontSize: 16,
                                    color: isDark ? Colors.grey[400] : Colors.grey,
                                  ),
                                ),
                                const SizedBox(height: 8),
                                Text(
                                  'Farklı bir arama terimi deneyin',
                                  style: TextStyle(
                                    fontSize: 14,
                                    color: isDark ? Colors.grey[500] : Colors.grey,
                                  ),
                                ),
                              ],
                            ),
                          )
                        : RefreshIndicator(
                            onRefresh: () => _searchUsers(_currentQuery, refresh: true),
                            child: ListView.builder(
                              itemCount: _users.length + (_isLoading ? 1 : 0),
                              itemBuilder: (context, index) {
                                if (index == _users.length) {
                                  return const Center(
                                    child: Padding(
                                      padding: EdgeInsets.all(16),
                                      child: CircularProgressIndicator(),
                                    ),
                                  );
                                }
                                
                                final user = _users[index];
                                return _buildUserCard(user);
                              },
                            ),
                          ),
          ),
        ],
      ),
    );
  }

  Widget _buildStatCard(String title, String value, IconData icon) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final primaryColor = ThemeColors.primary(context);
    
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: primaryColor.withOpacity(0.1),
        borderRadius: BorderRadius.circular(8),
      ),
      child: Column(
        children: [
          Icon(icon, color: primaryColor, size: 20),
          const SizedBox(height: 4),
          Text(
            value,
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.bold,
              color: primaryColor,
            ),
          ),
          Text(
            title,
            style: TextStyle(
              fontSize: 12,
              color: isDark ? Colors.grey[400] : Colors.grey,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildUserCard(Map<String, dynamic> user) {
    final currentUser = Provider.of<AuthProvider>(context, listen: false).user;
    final isSuperAdmin = currentUser?.role == 'super_admin';
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final primaryColor = ThemeColors.primary(context);
    final textColor = isDark ? Colors.white : Colors.black;
    final secondaryTextColor = isDark ? Colors.grey[400] : Colors.grey[600];
    
    return Card(
      margin: const EdgeInsets.symmetric(horizontal: 16, vertical: 4),
      color: Theme.of(context).cardTheme.color ?? ThemeColors.surface(context),
      child: ListTile(
        leading: CircleAvatar(
          radius: 25,
          backgroundColor: primaryColor.withOpacity(0.1),
          backgroundImage: (user['profile_image'] != null && user['profile_image'].toString().isNotEmpty)
              ? CachedNetworkImageProvider(user['profile_image'])
              : null,
          child: (user['profile_image'] == null || user['profile_image'].toString().isEmpty)
              ? Icon(
                  Icons.person,
                  color: primaryColor,
                  size: 25,
                )
              : null,
        ),
        title: Text(
          user['name'] ?? 'Bilinmeyen Kullanıcı',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.bold,
            color: textColor,
          ),
        ),
        subtitle: user['username'] != null && user['username'].isNotEmpty
            ? Text(
                '@${user['username']}',
                style: TextStyle(
                  fontSize: 14,
                  color: secondaryTextColor,
                ),
              )
            : null,
        trailing: isSuperAdmin
            ? Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
                decoration: BoxDecoration(
                  color: _getRoleColor(user['role']).withOpacity(0.1),
                  borderRadius: BorderRadius.circular(12),
                ),
                child: Text(
                  _getRoleText(user['role']),
                  style: TextStyle(
                    fontSize: 12,
                    color: _getRoleColor(user['role']),
                    fontWeight: FontWeight.w500,
                  ),
                ),
              )
            : null,
               onTap: () {
                 // Kullanıcı profilini göster
                 Navigator.push(
                   context,
                   MaterialPageRoute(
                     builder: (context) => ProfileScreen(
                       targetUserId: user['id'],
                       targetUserName: user['name'],
                     ),
                   ),
                 );
               },
      ),
    );
  }

  @override
  void dispose() {
    _searchController.dispose();
    super.dispose();
  }
}
