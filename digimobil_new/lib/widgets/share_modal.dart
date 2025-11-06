import 'dart:io';
import 'package:flutter/material.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/app_transitions.dart';
import 'package:digimobil_new/widgets/common_video_preview.dart';
import 'package:digimobil_new/utils/error_handler.dart';
import 'package:video_player/video_player.dart';

/// Instagram benzeri Paylaşım Ekranı
/// ✅ Üstte seçilen fotoğraf/video önizlemesi
/// ✅ Altında açıklama yazma alanı
/// ✅ Kişileri Etiketle, Konum Ekle, Müzik Ekle seçenekleri
/// ✅ En altta sabit "Paylaş" butonu
class ShareModal extends StatefulWidget {
  final File mediaFile;
  final Function(String description, Map<String, dynamic>? tags) onShare;
  final String? shareType; // 'post', 'story', 'reels'

  const ShareModal({
    super.key,
    required this.mediaFile,
    required this.onShare,
    this.shareType,
  });

  static Future<void> show(
    BuildContext context, {
    required File mediaFile,
    required Function(String description, Map<String, dynamic>? tags) onShare,
    String? shareType,
  }) {
    return Navigator.of(context).push(
      AppPageRoute(
        page: ShareModal(
          mediaFile: mediaFile,
          onShare: onShare,
          shareType: shareType,
        ),
        fullscreenDialog: true,
      ),
    );
  }

  @override
  State<ShareModal> createState() => _ShareModalState();
}

class _ShareModalState extends State<ShareModal> {
  final TextEditingController _descriptionController = TextEditingController();
  VideoPlayerController? _videoController;
  bool _isVideo = false;
  bool _isVideoInitialized = false;
  Map<String, dynamic>? _tags;

  @override
  void initState() {
    super.initState();
    _checkVideo();
  }

  @override
  void dispose() {
    _descriptionController.dispose();
    _videoController?.dispose();
    super.dispose();
  }

  void _checkVideo() {
    final path = widget.mediaFile.path.toLowerCase();
    _isVideo = path.endsWith('.mp4') ||
        path.endsWith('.mov') ||
        path.endsWith('.avi') ||
        path.endsWith('.mkv');

    if (_isVideo) {
      _initializeVideo();
    }
  }

  Future<void> _initializeVideo() async {
    try {
      _videoController = VideoPlayerController.file(widget.mediaFile);
      await _videoController!.initialize();
      if (mounted) {
        setState(() {
          _isVideoInitialized = true;
        });
      }
    } catch (e) {
      print('❌ Video initialize hatası: $e');
      if (mounted) {
        setState(() {
          _isVideoInitialized = true; // Hata olsa bile true yap ki error widget gösterilsin
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.close, color: Colors.white),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: const Text(
          'Yeni gönderi',
          style: TextStyle(
            color: Colors.white,
            fontSize: 18,
            fontWeight: FontWeight.w600,
          ),
        ),
        centerTitle: true,
        actions: [
          TextButton(
            onPressed: () {
              // ✅ Input validation
              final description = _descriptionController.text.trim();
              
              // ✅ Açıklama karakter limiti kontrolü
              if (description.length > 500) {
                ErrorHandler.showError(
                  context,
                  'Açıklama en fazla 500 karakter olabilir.',
                );
                return;
              }
              
              widget.onShare(
                description,
                _tags,
              );
              Navigator.of(context).pop();
            },
            child: const Text(
              'Paylaş',
              style: TextStyle(
                color: Colors.blue,
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          // ✅ Üstte medya önizlemesi
          Container(
            height: 300,
            width: double.infinity,
            color: Colors.black,
            child: _buildPreview(),
          ),

          // ✅ Açıklama alanı
          Container(
            padding: const EdgeInsets.all(16),
            child: TextField(
              controller: _descriptionController,
              style: const TextStyle(color: Colors.white),
              maxLines: 5,
              maxLength: 500, // ✅ Maksimum karakter sınırı
              buildCounter: (context, {required currentLength, required isFocused, maxLength}) {
                return Text(
                  '$currentLength / $maxLength',
                  style: TextStyle(
                    color: Colors.white.withOpacity(0.5),
                    fontSize: 12,
                  ),
                );
              },
              decoration: InputDecoration(
                hintText: 'Açıklama yaz...',
                hintStyle: TextStyle(
                  color: Colors.white.withOpacity(0.5),
                ),
                border: InputBorder.none,
                filled: false,
              ),
            ),
          ),

          const Divider(color: Colors.grey, height: 1),

          // ✅ Ek seçenekler
          Expanded(
            child: ListView(
              children: [
                _buildOptionTile(
                  icon: Icons.person_add,
                  title: 'Kişileri Etiketle',
                  onTap: () => _showTagPeopleDialog(),
                ),
                _buildOptionTile(
                  icon: Icons.location_on,
                  title: 'Konum Ekle',
                  onTap: () => _showLocationDialog(),
                ),
                if (widget.shareType == 'story')
                  _buildOptionTile(
                    icon: Icons.music_note,
                    title: 'Müzik Ekle',
                    onTap: () => _showMusicDialog(),
                  ),
                _buildOptionTile(
                  icon: Icons.people,
                  title: 'Kimler görebilir?',
                  subtitle: 'Herkes',
                  onTap: () => _showAudienceDialog(),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPreview() {
    // ✅ Video kontrolü - henüz initialize olmadıysa loading göster
    if (_isVideo) {
      if (!_isVideoInitialized || _videoController == null) {
        return const VideoLoadingWidget(
          message: 'Video yükleniyor...',
          color: Colors.white,
        );
      }
      
      if (_videoController!.value.hasError) {
        return const VideoErrorWidget(
          message: 'Video yüklenemedi',
          color: Colors.white,
        );
      }
      
      return CommonVideoPreview(
        controller: _videoController!,
        showPlayButton: true,
        playButtonColor: Colors.white,
        playButtonSize: 48,
      );
    }

    // ✅ Fotoğraf için error handling ekle
    return Image.file(
      widget.mediaFile,
      fit: BoxFit.contain,
      errorBuilder: (context, error, stackTrace) {
        return Center(
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              const Icon(
                Icons.error_outline,
                color: Colors.white,
                size: 48,
              ),
              const SizedBox(height: 16),
              Text(
                'Görüntü yüklenemedi',
                style: TextStyle(
                  color: Colors.white.withOpacity(0.7),
                  fontSize: 16,
                ),
              ),
            ],
          ),
        );
      },
    );
  }

  Widget _buildOptionTile({
    required IconData icon,
    required String title,
    String? subtitle,
    required VoidCallback onTap,
  }) {
    return ListTile(
      leading: Icon(icon, color: Colors.white),
      title: Text(
        title,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 16,
        ),
      ),
      subtitle: subtitle != null
          ? Text(
              subtitle,
              style: TextStyle(
                color: Colors.white.withOpacity(0.6),
                fontSize: 14,
              ),
            )
          : null,
      trailing: Icon(
        Icons.chevron_right,
        color: Colors.white.withOpacity(0.5),
      ),
      onTap: onTap,
    );
  }

  void _showTagPeopleDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: Colors.grey[900],
        title: const Text(
          'Kişileri Etiketle',
          style: TextStyle(color: Colors.white),
        ),
        content: const Text(
          'Etiketleme özelliği yakında eklenecek.',
          style: TextStyle(color: Colors.white70),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Tamam'),
          ),
        ],
      ),
    );
  }

  void _showLocationDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: Colors.grey[900],
        title: const Text(
          'Konum Ekle',
          style: TextStyle(color: Colors.white),
        ),
        content: const Text(
          'Konum ekleme özelliği yakında eklenecek.',
          style: TextStyle(color: Colors.white70),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Tamam'),
          ),
        ],
      ),
    );
  }

  void _showMusicDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: Colors.grey[900],
        title: const Text(
          'Müzik Ekle',
          style: TextStyle(color: Colors.white),
        ),
        content: const Text(
          'Müzik ekleme özelliği yakında eklenecek.',
          style: TextStyle(color: Colors.white70),
        ),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(context),
            child: const Text('Tamam'),
          ),
        ],
      ),
    );
  }

  void _showAudienceDialog() {
    showDialog(
      context: context,
      builder: (context) => AlertDialog(
        backgroundColor: Colors.grey[900],
        title: const Text(
          'Kimler görebilir?',
          style: TextStyle(color: Colors.white),
        ),
        content: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            ListTile(
              title: const Text('Herkes', style: TextStyle(color: Colors.white)),
              leading: const Icon(Icons.public, color: Colors.white),
              onTap: () => Navigator.pop(context),
            ),
            ListTile(
              title: const Text('Takipçiler', style: TextStyle(color: Colors.white)),
              leading: const Icon(Icons.people, color: Colors.white),
              onTap: () => Navigator.pop(context),
            ),
          ],
        ),
      ),
    );
  }
}


