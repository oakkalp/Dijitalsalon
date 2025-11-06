import 'dart:io';
import 'package:flutter/material.dart';
import 'package:image_picker/image_picker.dart';
import 'package:video_player/video_player.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/widgets/error_modal.dart';
import 'package:digimobil_new/screens/gallery_picker_screen.dart';
import 'package:digimobil_new/screens/media_editor_screen.dart';

class ModernShareModal extends StatefulWidget {
  final Function(XFile file, String contentType, String description) onShare;
  
  const ModernShareModal({
    super.key,
    required this.onShare,
  });

  static Future<void> show(
    BuildContext context, {
    required Function(XFile file, String contentType, String description) onShare,
  }) {
    return showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.transparent,
      builder: (context) => ModernShareModal(onShare: onShare),
    );
  }

  @override
  State<ModernShareModal> createState() => _ModernShareModalState();
}

class _ModernShareModalState extends State<ModernShareModal>
    with SingleTickerProviderStateMixin {
  final ImagePicker _picker = ImagePicker();
  XFile? _selectedFile;
  String _shareType = 'post'; // 'post' veya 'story'
  final TextEditingController _descriptionController = TextEditingController();
  late AnimationController _animationController;
  late Animation<double> _fadeAnimation;
  late Animation<Offset> _slideAnimation;
  VideoPlayerController? _videoController;

  @override
  void initState() {
    super.initState();
    _animationController = AnimationController(
      duration: const Duration(milliseconds: 300),
      vsync: this,
    );
    _fadeAnimation = CurvedAnimation(
      parent: _animationController,
      curve: Curves.easeOut,
    );
    _slideAnimation = Tween<Offset>(
      begin: const Offset(0, 1),
      end: Offset.zero,
    ).animate(CurvedAnimation(
      parent: _animationController,
      curve: Curves.easeOutCubic,
    ));
    _animationController.forward();
  }

  @override
  void dispose() {
    _animationController.dispose();
    _descriptionController.dispose();
    _videoController?.dispose();
    super.dispose();
  }
  
  // ✅ Dosya tipini kontrol et (video mu resim mi)
  bool _isVideoFile(String filePath) {
    final lowerPath = filePath.toLowerCase();
    return lowerPath.endsWith('.mp4') ||
           lowerPath.endsWith('.mov') ||
           lowerPath.endsWith('.avi') ||
           lowerPath.endsWith('.mkv') ||
           lowerPath.endsWith('.webm') ||
           lowerPath.endsWith('.3gp');
  }

  Future<void> _pickFromCamera() async {
    try {
      final XFile? photo = await _picker.pickImage(
        source: ImageSource.camera,
        imageQuality: 85,
      );
      if (photo != null && mounted) {
        // ✅ Düzenleme ekranına yönlendir
        final editedData = await Navigator.of(context).push<Map<String, dynamic>>(
          MaterialPageRoute(
            builder: (context) => MediaEditorScreen(
              mediaFile: File(photo.path),
              shareType: _shareType,
            ),
          ),
        );

        if (editedData != null && mounted) {
          final editedFile = editedData['file'] as File?;
          if (editedFile != null) {
            setState(() {
              _selectedFile = XFile(editedFile.path);
            });
          }
        }
      }
    } catch (e) {
      if (mounted) {
        ErrorModal.show(
          context,
          title: 'Kamera Hatası',
          message: 'Kamera erişiminde bir sorun oluştu.',
          icon: Icons.camera_alt,
        );
      }
    }
  }

  Future<void> _pickVideoFromCamera() async {
    try {
      final XFile? video = await _picker.pickVideo(
        source: ImageSource.camera,
        maxDuration: const Duration(seconds: 60),
      );
      if (video != null && mounted) {
        // ✅ Video controller'ı başlat
        await _initializeVideoController(video.path);
        setState(() {
          _selectedFile = video;
        });
      }
    } catch (e) {
      if (mounted) {
        ErrorModal.show(
          context,
          title: 'Kamera Hatası',
          message: 'Video kamera erişiminde bir sorun oluştu.',
          icon: Icons.videocam,
        );
      }
    }
  }
  
  Future<void> _initializeVideoController(String videoPath) async {
    // ✅ Önceki controller'ı temizle
    await _videoController?.dispose();
    
    // ✅ Yeni controller oluştur
    _videoController = VideoPlayerController.file(File(videoPath));
    await _videoController!.initialize();
    
    if (mounted) {
      setState(() {});
    }
  }

  Future<void> _pickFromGallery() async {
    try {
      // ✅ Instagram tarzı galeri ekranını aç
      final File? selectedFile = await GalleryPickerScreen.show(context);
      if (selectedFile != null && mounted) {
        // ✅ Video dosyası ise direkt preview'a geç
        if (_isVideoFile(selectedFile.path)) {
          await _initializeVideoController(selectedFile.path);
          setState(() {
            _selectedFile = XFile(selectedFile.path);
          });
        } else {
          // ✅ Resim dosyası ise düzenleme ekranına yönlendir
          final editedData = await Navigator.of(context).push<Map<String, dynamic>>(
            MaterialPageRoute(
              builder: (context) => MediaEditorScreen(
                mediaFile: selectedFile,
                shareType: _shareType,
              ),
            ),
          );

          if (editedData != null && mounted) {
            setState(() {
              _selectedFile = XFile(editedData['file'].path);
            });
          }
        }
      }
    } catch (e) {
      if (mounted) {
        ErrorModal.show(
          context,
          title: 'Galeri Hatası',
          message: 'Galeriye erişimde bir sorun oluştu.',
          icon: Icons.photo_library,
        );
      }
    }
  }

  Future<void> _pickVideoFromGallery() async {
    try {
      final XFile? video = await _picker.pickVideo(
        source: ImageSource.gallery,
      );
      if (video != null && mounted) {
        // ✅ Video controller'ı başlat
        await _initializeVideoController(video.path);
        setState(() {
          _selectedFile = video;
        });
      }
    } catch (e) {
      if (mounted) {
        ErrorModal.show(
          context,
          title: 'Galeri Hatası',
          message: 'Video seçiminde bir sorun oluştu.',
          icon: Icons.video_library,
        );
      }
    }
  }

  void _handleShare() {
    if (_selectedFile == null) {
      ErrorModal.show(
        context,
        title: 'Medya Seçilmedi',
        message: 'Lütfen bir fotoğraf veya video seçin.',
        icon: Icons.image_not_supported,
      );
      return;
    }

    final description = _descriptionController.text.trim();
    widget.onShare(_selectedFile!, _shareType == 'story' ? 'story' : 'media', description);
    Navigator.of(context).pop();
  }

  @override
  Widget build(BuildContext context) {
    return SlideTransition(
      position: _slideAnimation,
      child: FadeTransition(
        opacity: _fadeAnimation,
        child: Container(
          height: MediaQuery.of(context).size.height * 0.85,
          decoration: BoxDecoration(
            gradient: LinearGradient(
              begin: Alignment.topCenter,
              end: Alignment.bottomCenter,
              colors: [
                AppColors.surface,
                AppColors.background,
              ],
            ),
            borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
          ),
          child: Column(
            children: [
              // Handle bar
              Container(
                margin: const EdgeInsets.only(top: 12),
                width: 40,
                height: 4,
                decoration: BoxDecoration(
                  color: AppColors.borderLight,
                  borderRadius: BorderRadius.circular(2),
                ),
              ),
              
              // Header
              Padding(
                padding: const EdgeInsets.all(20),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    IconButton(
                      onPressed: () => Navigator.of(context).pop(),
                      icon: const Icon(Icons.close, color: AppColors.textPrimary),
                    ),
                    ShaderMask(
                      shaderCallback: (bounds) => AppColors.shimmerGradient.createShader(bounds),
                      child: const Text(
                        'Paylaş',
                        style: TextStyle(
                          fontSize: 20,
                          fontWeight: FontWeight.bold,
                          color: Colors.white,
                        ),
                      ),
                    ),
                    IconButton(
                      onPressed: _selectedFile != null ? _handleShare : null,
                      icon: Icon(
                        Icons.check_circle,
                        color: _selectedFile != null ? AppColors.primary : AppColors.border,
                      ),
                    ),
                  ],
                ),
              ),
              
              // Share type selector (Post / Story)
              _buildShareTypeSelector(),
              
              const SizedBox(height: 16),
              
              // Preview or camera/gallery selector
              Expanded(
                child: _selectedFile == null
                    ? _buildMediaSelector()
                    : _buildPreview(),
              ),
              
              // Description input (only for posts)
              if (_shareType == 'post' && _selectedFile != null)
                _buildDescriptionInput(),
              
              // Bottom padding
              SizedBox(height: MediaQuery.of(context).padding.bottom + 16),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildShareTypeSelector() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20),
      padding: const EdgeInsets.all(4),
      decoration: BoxDecoration(
        color: AppColors.surfaceLight,
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: AppColors.borderGold, width: 1),
      ),
      child: Row(
        children: [
          Expanded(
            child: _buildTypeButton(
              label: 'Gönderi',
              icon: Icons.grid_on,
              isSelected: _shareType == 'post',
              onTap: () => setState(() => _shareType = 'post'),
            ),
          ),
          Expanded(
            child: _buildTypeButton(
              label: 'Hikaye',
              icon: Icons.auto_stories,
              isSelected: _shareType == 'story',
              onTap: () => setState(() => _shareType = 'story'),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTypeButton({
    required String label,
    required IconData icon,
    required bool isSelected,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 200),
        padding: const EdgeInsets.symmetric(vertical: 12),
        decoration: BoxDecoration(
          gradient: isSelected ? AppColors.weddingGradient : null,
          color: isSelected ? null : Colors.transparent,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Row(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              icon,
              color: isSelected ? Colors.white : AppColors.textSecondary,
              size: 20,
            ),
            const SizedBox(width: 8),
            Text(
              label,
              style: TextStyle(
                color: isSelected ? Colors.white : AppColors.textSecondary,
                fontWeight: isSelected ? FontWeight.bold : FontWeight.normal,
                fontSize: 16,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildMediaSelector() {
    return Column(
      mainAxisAlignment: MainAxisAlignment.center,
      children: [
        // Camera icon with shimmer effect
        Container(
          width: 120,
          height: 120,
          decoration: BoxDecoration(
            gradient: AppColors.weddingGradient,
            shape: BoxShape.circle,
            boxShadow: [
              BoxShadow(
                color: AppColors.primary.withOpacity(0.3),
                blurRadius: 20,
                spreadRadius: 5,
              ),
            ],
          ),
          child: const Icon(
            Icons.camera_alt,
            size: 48,
            color: Colors.white,
          ),
        ),
        
        const SizedBox(height: 32),
        
        Text(
          'Fotoğraf veya Video Seç',
          style: TextStyle(
            fontSize: 20,
            fontWeight: FontWeight.bold,
            color: AppColors.textPrimary,
          ),
        ),
        
        const SizedBox(height: 32),
        
        // Action buttons
        Wrap(
          alignment: WrapAlignment.center,
          spacing: 16,
          runSpacing: 16,
          children: [
            _buildActionButton(
              icon: Icons.camera_alt,
              label: 'Fotoğraf Çek',
              gradient: LinearGradient(
                colors: [AppColors.primary, AppColors.accentDark],
              ),
              onTap: _pickFromCamera,
            ),
            _buildActionButton(
              icon: Icons.videocam,
              label: 'Video Çek',
              gradient: LinearGradient(
                colors: [AppColors.secondary, AppColors.primary],
              ),
              onTap: _pickVideoFromCamera,
            ),
            _buildActionButton(
              icon: Icons.photo_library,
              label: 'Galeriden Seç',
              gradient: LinearGradient(
                colors: [AppColors.accentDark, AppColors.secondary],
              ),
              onTap: _pickFromGallery,
            ),
            _buildActionButton(
              icon: Icons.video_library,
              label: 'Video Seç',
              gradient: LinearGradient(
                colors: [AppColors.primary, AppColors.secondary],
              ),
              onTap: _pickVideoFromGallery,
            ),
          ],
        ),
      ],
    );
  }

  Widget _buildActionButton({
    required IconData icon,
    required String label,
    required Gradient gradient,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 160,
        padding: const EdgeInsets.symmetric(vertical: 16, horizontal: 20),
        decoration: BoxDecoration(
          gradient: gradient,
          borderRadius: BorderRadius.circular(16),
          boxShadow: [
            BoxShadow(
              color: AppColors.primary.withOpacity(0.2),
              blurRadius: 10,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Column(
          children: [
            Icon(icon, color: Colors.white, size: 32),
            const SizedBox(height: 8),
            Text(
              label,
              textAlign: TextAlign.center,
              style: const TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w600,
                fontSize: 14,
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPreview() {
    final isVideo = _isVideoFile(_selectedFile!.path);
    
    return Column(
      children: [
        Expanded(
          child: Container(
            margin: const EdgeInsets.all(20),
            decoration: BoxDecoration(
              borderRadius: BorderRadius.circular(16),
              border: Border.all(color: AppColors.borderGold, width: 2),
            ),
            child: ClipRRect(
              borderRadius: BorderRadius.circular(14),
              child: isVideo
                  ? _buildVideoPreview()
                  : Image.file(
                      File(_selectedFile!.path),
                      fit: BoxFit.cover,
                    ),
            ),
          ),
        ),
        TextButton.icon(
          onPressed: () {
            _videoController?.dispose();
            _videoController = null;
            setState(() => _selectedFile = null);
          },
          icon: const Icon(Icons.refresh, color: AppColors.primary),
          label: const Text(
            'Farklı Medya Seç',
            style: TextStyle(color: AppColors.primary),
          ),
        ),
      ],
    );
  }
  
  Widget _buildVideoPreview() {
    if (_videoController == null || !_videoController!.value.isInitialized) {
      return const Center(
        child: CircularProgressIndicator(
          color: AppColors.primary,
        ),
      );
    }
    
    return Stack(
      fit: StackFit.expand,
      children: [
        AspectRatio(
          aspectRatio: _videoController!.value.aspectRatio,
          child: VideoPlayer(_videoController!),
        ),
        Center(
          child: IconButton(
            icon: Icon(
              _videoController!.value.isPlaying
                  ? Icons.pause
                  : Icons.play_arrow,
              color: Colors.white,
              size: 64,
            ),
            onPressed: () {
              setState(() {
                if (_videoController!.value.isPlaying) {
                  _videoController!.pause();
                } else {
                  _videoController!.play();
                }
              });
            },
          ),
        ),
      ],
    );
  }

  Widget _buildDescriptionInput() {
    return Container(
      margin: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      decoration: BoxDecoration(
        color: AppColors.surfaceLight,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: AppColors.borderGold, width: 1),
      ),
      child: TextField(
        controller: _descriptionController,
        maxLines: 3,
        maxLength: 500,
        style: const TextStyle(color: AppColors.textPrimary),
        decoration: const InputDecoration(
          hintText: 'Bir açıklama yazın...',
          hintStyle: TextStyle(color: AppColors.textSecondary),
          border: InputBorder.none,
          counterStyle: TextStyle(color: AppColors.textTertiary),
        ),
      ),
    );
  }
}

