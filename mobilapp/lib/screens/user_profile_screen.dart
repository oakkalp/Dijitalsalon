import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:digimobil_new/providers/auth_provider.dart';
import 'package:digimobil_new/providers/event_provider.dart';
import 'package:digimobil_new/models/user.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';

class UserProfileScreen extends StatefulWidget {
  final int? targetUserId;
  final String? targetUserName;
  
  const UserProfileScreen({
    super.key,
    this.targetUserId,
    this.targetUserName,
  });

  @override
  State<UserProfileScreen> createState() => _UserProfileScreenState();
}

class _UserProfileScreenState extends State<UserProfileScreen> {
  final ApiService _apiService = ApiService();
  final ImagePicker _picker = ImagePicker();
  bool _isLoading = false;
  bool _isEditing = false;
  
  // Form controllers
  final TextEditingController _nameController = TextEditingController();
  final TextEditingController _surnameController = TextEditingController();
  final TextEditingController _emailController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _usernameController = TextEditingController();
  
  // Profile image
  File? _selectedImage;
  String? _currentProfileImage;
  
  // Target user data (for viewing other user's profile)
  User? _targetUser;
  bool _isLoadingTargetUser = false;

  @override
  void initState() {
    super.initState();
    _loadUserData();
    // ✅ Eğer başka kullanıcının profilini görüntülüyorsak, o kullanıcının bilgilerini al
    if (widget.targetUserId != null) {
      _loadTargetUserData();
    }
  }

  @override
  void dispose() {
    _nameController.dispose();
    _surnameController.dispose();
    _emailController.dispose();
    _phoneController.dispose();
    _usernameController.dispose();
    super.dispose();
  }

  void _loadUserData() {
    final user = Provider.of<AuthProvider>(context, listen: false).user;
    if (user != null) {
      _nameController.text = user.name.split(' ').first;
      _surnameController.text = user.name.split(' ').length > 1 
          ? user.name.split(' ').sublist(1).join(' ') 
          : '';
      _emailController.text = user.email;
      _phoneController.text = user.phone ?? '';
      _usernameController.text = user.username ?? '';
      _currentProfileImage = user.profileImage;
    }
  }
  
  // ✅ Hedef kullanıcının bilgilerini yükle
  Future<void> _loadTargetUserData() async {
    if (widget.targetUserId == null) return;
    
    setState(() {
      _isLoadingTargetUser = true;
    });

    try {
      final userData = await _apiService.getUserById(widget.targetUserId!);
      
      setState(() {
        _targetUser = userData;
        _nameController.text = userData.name.split(' ').first;
        _surnameController.text = userData.name.split(' ').length > 1 
            ? userData.name.split(' ').sublist(1).join(' ') 
            : '';
        _emailController.text = userData.email;
        _phoneController.text = userData.phone ?? '';
        _usernameController.text = userData.username ?? '';
        _currentProfileImage = userData.profileImage;
        _isLoadingTargetUser = false;
      });
    } catch (e) {
      print('Hedef kullanıcı bilgileri alınamadı: $e');
      setState(() {
        _isLoadingTargetUser = false;
      });
      if (mounted) {
        _showSnackBar('Kullanıcı bilgileri yüklenemedi: $e', isError: true);
      }
    }
  }

  Future<void> _pickImage() async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.gallery,
        maxWidth: 800,
        maxHeight: 800,
        imageQuality: 80,
      );
      
      if (image != null) {
        setState(() {
          _selectedImage = File(image.path);
        });
      }
    } catch (e) {
      _showSnackBar('Resim seçilirken hata: $e', isError: true);
    }
  }

  Future<void> _takePhoto() async {
    try {
      final XFile? image = await _picker.pickImage(
        source: ImageSource.camera,
        maxWidth: 800,
        maxHeight: 800,
        imageQuality: 80,
      );
      
      if (image != null) {
        setState(() {
          _selectedImage = File(image.path);
        });
      }
    } catch (e) {
      _showSnackBar('Fotoğraf çekilirken hata: $e', isError: true);
    }
  }

  Future<void> _updateProfile() async {
    if (!_isEditing) return;

    setState(() {
      _isLoading = true;
    });

    try {
      final authProvider = Provider.of<AuthProvider>(context, listen: false);
      final currentUser = authProvider.user;
      
      if (currentUser == null) {
        throw Exception('Kullanıcı bilgisi bulunamadı');
      }

      final newUsername = _usernameController.text.trim();
      
      // Kullanıcı adı değiştiyse kontrol et
      if (newUsername != currentUser.username) {
        final isAvailable = await _apiService.checkUsernameAvailability(
          newUsername,
          currentUserId: currentUser.id,
        );
        
        if (!isAvailable) {
          _showSnackBar('Bu kullanıcı adı zaten kullanılıyor!', isError: true);
          return;
        }
      }

      // Update user data - tüm alanları güncelle
      final updatedUser = await _apiService.updateUserProfile(
        userId: currentUser.id,
        name: _nameController.text.trim(),
        surname: _surnameController.text.trim(),
        email: _emailController.text.trim(),
        phone: _phoneController.text.trim(),
        username: newUsername,
        profileImage: _selectedImage,
      );

      // Update auth provider
      await authProvider.updateUser(updatedUser);
      
      // EventProvider'ı yeniden yükle (medya verilerini güncellemek için)
      final eventProvider = Provider.of<EventProvider>(context, listen: false);
      await eventProvider.loadEvents();
      
      setState(() {
        _isEditing = false;
        _selectedImage = null;
        _currentProfileImage = updatedUser.profileImage;
      });

      _showSnackBar('Profil başarıyla güncellendi!');
      
    } catch (e) {
      _showSnackBar('Profil güncellenirken hata: $e', isError: true);
    } finally {
      setState(() {
        _isLoading = false;
      });
    }
  }

  Future<void> _logout() async {
    final authProvider = Provider.of<AuthProvider>(context, listen: false);
    await authProvider.logout();
    
    if (mounted) {
      Navigator.of(context).pushNamedAndRemoveUntil(
        '/login',
        (route) => false,
      );
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

  void _showImagePicker() {
    showModalBottomSheet(
      context: context,
      builder: (context) => SafeArea(
        child: Wrap(
          children: [
            ListTile(
              leading: const Icon(Icons.photo_library),
              title: const Text('Galeriden Seç'),
              onTap: () {
                Navigator.pop(context);
                _pickImage();
              },
            ),
            ListTile(
              leading: const Icon(Icons.camera_alt),
              title: const Text('Fotoğraf Çek'),
              onTap: () {
                Navigator.pop(context);
                _takePhoto();
              },
            ),
            if (_currentProfileImage != null || _selectedImage != null)
              ListTile(
                leading: const Icon(Icons.delete, color: Colors.red),
                title: const Text('Profil Resmini Sil', style: TextStyle(color: Colors.red)),
                onTap: () {
                  Navigator.pop(context);
                  setState(() {
                    _selectedImage = null;
                    _currentProfileImage = null;
                  });
                },
              ),
          ],
        ),
      ),
    );
  }

  @override
  Widget build(BuildContext context) {
    print('🔍 SCREEN DEBUG - UserProfileScreen build() called');
    
    // Eğer hedef kullanıcı varsa onun bilgilerini göster, yoksa mevcut kullanıcının
    final authProvider = Provider.of<AuthProvider>(context);
    final currentUser = authProvider.user;
    final isViewingOtherProfile = widget.targetUserId != null && widget.targetUserId != currentUser?.id;
    
    // ✅ Hedef kullanıcı yükleniyorsa loading göster
    if (isViewingOtherProfile && _isLoadingTargetUser) {
      return const Scaffold(
        body: Center(
          child: CircularProgressIndicator(),
        ),
      );
    }
    
    // ✅ Hedef kullanıcı yüklenememişse hata göster
    if (isViewingOtherProfile && _targetUser == null && !_isLoadingTargetUser) {
      return Scaffold(
        appBar: AppBar(
          title: const Text('Kullanıcı Profili'),
        ),
        body: const Center(
          child: Text('Kullanıcı bilgileri yüklenemedi'),
        ),
      );
    }
    
    // ✅ Görüntülenecek kullanıcıyı belirle
    final displayUser = isViewingOtherProfile ? _targetUser : currentUser;
    
    if (displayUser == null) {
      print('🔍 SCREEN DEBUG - UserProfileScreen: User is null');
      return const Scaffold(
        body: Center(
          child: Text('Kullanıcı bilgisi bulunamadı'),
        ),
      );
    }
    
    print('🔍 SCREEN DEBUG - UserProfileScreen: User found - ${displayUser.name} (ID: ${displayUser.id})');
    print('🔍 SCREEN DEBUG - Viewing other profile: $isViewingOtherProfile, Target ID: ${widget.targetUserId}');

    return Scaffold(
      backgroundColor: Colors.grey[50],
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        title: Text(
          isViewingOtherProfile 
              ? widget.targetUserName ?? 'Kullanıcı Profili'
              : 'Profil',
          style: const TextStyle(
            color: Colors.black,
            fontWeight: FontWeight.bold,
          ),
        ),
        centerTitle: true,
        actions: [
          if (_isEditing)
            TextButton(
              onPressed: _isLoading ? null : _updateProfile,
              child: _isLoading
                  ? const SizedBox(
                      width: 20,
                      height: 20,
                      child: CircularProgressIndicator(strokeWidth: 2),
                    )
                  : const Text(
                      'Kaydet',
                      style: TextStyle(
                        color: AppColors.primary,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
            )
          else if (!isViewingOtherProfile) // Sadece kendi profilini düzenleyebilir
            TextButton(
              onPressed: () {
                setState(() {
                  _isEditing = true;
                });
              },
              child: const Text(
                'Düzenle',
                style: TextStyle(
                  color: AppColors.primary,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          children: [
            // Profile Image Section
            Center(
              child: Stack(
                children: [
                  GestureDetector(
                    onTap: (_isEditing && !isViewingOtherProfile) ? _showImagePicker : null,
                    child: Container(
                      width: 120,
                      height: 120,
                      decoration: BoxDecoration(
                        shape: BoxShape.circle,
                        border: Border.all(
                          color: _isEditing ? AppColors.primary : Colors.grey[300]!,
                          width: 3,
                        ),
                      ),
                      child: ClipOval(
                        child: _selectedImage != null
                            ? Image.file(
                                _selectedImage!,
                                fit: BoxFit.cover,
                                width: 120,
                                height: 120,
                              )
                            : (_currentProfileImage != null && _currentProfileImage!.isNotEmpty && _currentProfileImage!.startsWith('http'))
                                ? CachedNetworkImage(
                                    imageUrl: _currentProfileImage!,
                                    fit: BoxFit.cover,
                                    width: 120,
                                    height: 120,
                                    placeholder: (context, url) => Container(
                                      color: Colors.grey[300],
                                      child: const Icon(
                                        Icons.person,
                                        size: 60,
                                        color: Colors.grey,
                                      ),
                                    ),
                                    errorWidget: (context, url, error) => Container(
                                      color: Colors.grey[300],
                                      child: const Icon(
                                        Icons.person,
                                        size: 60,
                                        color: Colors.grey,
                                      ),
                                    ),
                                  )
                                : Container(
                                    color: Colors.grey[300],
                                    child: const Icon(
                                      Icons.person,
                                      size: 60,
                                      color: Colors.grey,
                                    ),
                                  ),
                      ),
                    ),
                  ),
                  if (_isEditing && !isViewingOtherProfile)
                    Positioned(
                      bottom: 0,
                      right: 0,
                      child: Container(
                        width: 36,
                        height: 36,
                        decoration: const BoxDecoration(
                          color: AppColors.primary,
                          shape: BoxShape.circle,
                        ),
                        child: const Icon(
                          Icons.camera_alt,
                          color: Colors.white,
                          size: 20,
                        ),
                      ),
                    ),
                ],
              ),
            ),
            
            const SizedBox(height: 30),
            
            // Profile Form
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.white,
                borderRadius: BorderRadius.circular(15),
                boxShadow: [
                  BoxShadow(
                    color: Colors.grey.withOpacity(0.1),
                    spreadRadius: 1,
                    blurRadius: 10,
                    offset: const Offset(0, 2),
                  ),
                ],
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'Kişisel Bilgiler',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.bold,
                      color: Colors.black87,
                    ),
                  ),
                  const SizedBox(height: 20),
                  
                  // Name Field - Düzenlenebilir
                  _buildTextField(
                    controller: _nameController,
                    label: 'Ad',
                    icon: Icons.person,
                    enabled: _isEditing && !isViewingOtherProfile,
                  ),
                  
                  const SizedBox(height: 15),
                  
                  // Surname Field - Düzenlenebilir
                  _buildTextField(
                    controller: _surnameController,
                    label: 'Soyad',
                    icon: Icons.person_outline,
                    enabled: _isEditing && !isViewingOtherProfile,
                  ),
                  
                  const SizedBox(height: 15),
                  
                  // Username Field - Düzenlenebilir
                  _buildTextField(
                    controller: _usernameController,
                    label: 'Kullanıcı Adı',
                    icon: Icons.alternate_email,
                    enabled: _isEditing && !isViewingOtherProfile,
                  ),
                  
                  const SizedBox(height: 15),
                  
                  // Email Field - Düzenlenebilir
                  _buildTextField(
                    controller: _emailController,
                    label: 'E-posta',
                    icon: Icons.email,
                    enabled: _isEditing && !isViewingOtherProfile,
                    keyboardType: TextInputType.emailAddress,
                  ),
                  
                  const SizedBox(height: 15),
                  
                  // Phone Field - Düzenlenebilir
                  _buildTextField(
                    controller: _phoneController,
                    label: 'Telefon',
                    icon: Icons.phone,
                    enabled: _isEditing && !isViewingOtherProfile,
                    keyboardType: TextInputType.phone,
                  ),
                  
                  const SizedBox(height: 20),
                  
                  // Role Info
                  Container(
                    padding: const EdgeInsets.all(15),
                    decoration: BoxDecoration(
                      color: Colors.grey[50],
                      borderRadius: BorderRadius.circular(10),
                      border: Border.all(color: Colors.grey[200]!),
                    ),
                    child: Row(
                      children: [
                        Icon(
                          Icons.admin_panel_settings,
                          color: Colors.grey[600],
                          size: 20,
                        ),
                        const SizedBox(width: 10),
                        Text(
                          'Rol: ${displayUser.role}',
                          style: TextStyle(
                            color: Colors.grey[600],
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ),
                ],
              ),
            ),
            
            const SizedBox(height: 20),
            
            // Action Buttons
            if (_isEditing) ...[
              Row(
                children: [
                  Expanded(
                    child: OutlinedButton(
                      onPressed: () {
                        setState(() {
                          _isEditing = false;
                          _selectedImage = null;
                          _loadUserData(); // Reset form
                        });
                      },
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 15),
                        side: const BorderSide(color: Colors.grey),
                      ),
                      child: const Text(
                        'İptal',
                        style: TextStyle(
                          color: Colors.grey,
                          fontWeight: FontWeight.bold,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 15),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: _isLoading ? null : _updateProfile,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                        padding: const EdgeInsets.symmetric(vertical: 15),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(10),
                        ),
                      ),
                      child: _isLoading
                          ? const SizedBox(
                              width: 20,
                              height: 20,
                              child: CircularProgressIndicator(
                                strokeWidth: 2,
                                valueColor: AlwaysStoppedAnimation<Color>(Colors.white),
                              ),
                            )
                          : const Text(
                              'Kaydet',
                              style: TextStyle(
                                color: Colors.white,
                                fontWeight: FontWeight.bold,
                              ),
                            ),
                    ),
                  ),
                ],
              ),
            ] else if (!isViewingOtherProfile) ...[
              // Logout Button - Sadece kendi profili için
              SizedBox(
                width: double.infinity,
                child: OutlinedButton(
                  onPressed: () {
                    showDialog(
                      context: context,
                      builder: (context) => AlertDialog(
                        title: const Text('Çıkış Yap'),
                        content: const Text('Hesabınızdan çıkmak istediğinizden emin misiniz?'),
                        actions: [
                          TextButton(
                            onPressed: () => Navigator.pop(context),
                            child: const Text('İptal'),
                          ),
                          TextButton(
                            onPressed: () {
                              Navigator.pop(context);
                              _logout();
                            },
                            child: const Text(
                              'Çıkış Yap',
                              style: TextStyle(color: Colors.red),
                            ),
                          ),
                        ],
                      ),
                    );
                  },
                  style: OutlinedButton.styleFrom(
                    padding: const EdgeInsets.symmetric(vertical: 15),
                    side: const BorderSide(color: Colors.red),
                  ),
                  child: const Text(
                    'Çıkış Yap',
                    style: TextStyle(
                      color: Colors.red,
                      fontWeight: FontWeight.bold,
                    ),
                  ),
                ),
              ),
            ],
          ],
        ),
      ),
    );
  }

  Widget _buildTextField({
    required TextEditingController controller,
    required String label,
    required IconData icon,
    bool enabled = true,
    TextInputType? keyboardType,
  }) {
    return TextFormField(
      controller: controller,
      enabled: enabled,
      keyboardType: keyboardType,
      decoration: InputDecoration(
        labelText: label,
        prefixIcon: Icon(icon, color: enabled ? AppColors.primary : Colors.grey),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: Colors.grey[300]!),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: Colors.grey[300]!),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: const BorderSide(color: AppColors.primary, width: 2),
        ),
        disabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(10),
          borderSide: BorderSide(color: Colors.grey[200]!),
        ),
        filled: !enabled,
        fillColor: enabled ? Colors.white : Colors.grey[50],
      ),
    );
  }

  Widget _buildReadOnlyField({
    required String label,
    required String value,
    required IconData icon,
  }) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 16),
      decoration: BoxDecoration(
        color: Colors.grey[50],
        borderRadius: BorderRadius.circular(10),
        border: Border.all(color: Colors.grey[300]!),
      ),
      child: Row(
        children: [
          Icon(icon, color: Colors.grey[600], size: 20),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  label,
                  style: TextStyle(
                    fontSize: 12,
                    color: Colors.grey[600],
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  value,
                  style: const TextStyle(
                    fontSize: 16,
                    color: Colors.black87,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
          Icon(
            Icons.lock_outline,
            color: Colors.grey[400],
            size: 16,
          ),
        ],
      ),
    );
  }
}
