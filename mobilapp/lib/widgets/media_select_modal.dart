import 'dart:io';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:photo_manager/photo_manager.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/app_transitions.dart';
import 'package:digimobil_new/widgets/common_video_preview.dart';
import 'package:digimobil_new/widgets/error_modal.dart';
import 'package:permission_handler/permission_handler.dart';
import 'package:video_player/video_player.dart';

/// Instagram benzeri Medya Seçimi Ekranı
/// ✅ Üstte büyük önizleme alanı
/// ✅ Alt kısımda galeri grid görünümü
/// ✅ Sağ üstte "İleri" butonu
class MediaSelectModal extends StatefulWidget {
  final Function(File file) onMediaSelected;
  final String? shareType; // 'post', 'story', 'reels'

  const MediaSelectModal({
    super.key,
    required this.onMediaSelected,
    this.shareType,
  });

  static Future<void> show(
    BuildContext context, {
    required Function(File file) onMediaSelected,
    String? shareType,
  }) {
    return Navigator.of(context).push(
      AppPageRoute(
        page: MediaSelectModal(
          onMediaSelected: onMediaSelected,
          shareType: shareType,
        ),
        fullscreenDialog: true,
      ),
    );
  }

  @override
  State<MediaSelectModal> createState() => _MediaSelectModalState();
}

class _MediaSelectModalState extends State<MediaSelectModal> {
  List<AssetEntity> _recentPhotos = [];
  AssetEntity? _selectedAsset;
  File? _selectedFile;
  bool _isLoading = true;
  bool _isLoadingMore = false;
  int _currentPage = 0;
  static const int _pageSize = 30;
  bool _hasMore = true;
  VideoPlayerController? _videoController;
  bool _isVideo = false;

  @override
  void initState() {
    super.initState();
    _loadPhotos();
  }

  @override
  void dispose() {
    _videoController?.dispose();
    super.dispose();
  }

  Future<void> _loadPhotos() async {
    setState(() {
      _isLoading = true;
    });

    try {
      final status = await Permission.photos.status;
      if (!status.isGranted) {
        final requested = await Permission.photos.request();
        if (!requested.isGranted) {
          if (mounted) {
            ErrorModal.show(
              context,
              title: 'İzin Gerekli',
              message: 'Fotoğraflara erişmek için galeri izni gereklidir.',
              icon: Icons.photo_library,
            );
            Navigator.of(context).pop();
          }
          return;
        }
      }

      final albums = await PhotoManager.getAssetPathList(
        type: RequestType.common,
        hasAll: true,
      );

      AssetPathEntity? recentsAlbum;
      for (final album in albums) {
        if (album.name.toLowerCase().contains('recent') ||
            album.name.toLowerCase().contains('yakın') ||
            album.isAll) {
          recentsAlbum = album;
          break;
        }
      }

      final selectedAlbum = recentsAlbum ?? (albums.isNotEmpty ? albums.first : null);

      if (selectedAlbum != null) {
        final photos = await selectedAlbum.getAssetListPaged(
          page: 0,
          size: _pageSize,
        );

        setState(() {
          _recentPhotos = photos;
          _isLoading = false;
          _hasMore = photos.length >= _pageSize;
        });
      } else {
        setState(() {
          _isLoading = false;
        });
      }
    } catch (e) {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
        ErrorModal.show(
          context,
          title: 'Hata',
          message: 'Fotoğraflar yüklenirken bir hata oluştu: $e',
          icon: Icons.error_outline,
        );
      }
    }
  }

  Future<void> _loadMorePhotos() async {
    if (_isLoadingMore || !_hasMore) return;

    setState(() {
      _isLoadingMore = true;
    });

    try {
      final albums = await PhotoManager.getAssetPathList(
        type: RequestType.common,
        hasAll: true,
      );

      AssetPathEntity? recentsAlbum;
      for (final album in albums) {
        if (album.name.toLowerCase().contains('recent') ||
            album.name.toLowerCase().contains('yakın') ||
            album.isAll) {
          recentsAlbum = album;
          break;
        }
      }

      final selectedAlbum = recentsAlbum ?? (albums.isNotEmpty ? albums.first : null);

      if (selectedAlbum != null) {
        final nextPage = _currentPage + 1;
        final photos = await selectedAlbum.getAssetListPaged(
          page: nextPage,
          size: _pageSize,
        );

        setState(() {
          _recentPhotos.addAll(photos);
          _currentPage = nextPage;
          _isLoadingMore = false;
          _hasMore = photos.length >= _pageSize;
        });
      }
    } catch (e) {
      setState(() {
        _isLoadingMore = false;
      });
    }
  }

  Future<void> _selectAsset(AssetEntity asset) async {
    try {
      setState(() {
        _selectedAsset = asset;
        _isLoading = true;
      });

      // ✅ Video kontrolü
      final isVideo = asset.type == AssetType.video;
      _isVideo = isVideo;

      // ✅ Dosyayı al
      final file = await asset.file;
      if (file == null) return;

      setState(() {
        _selectedFile = file;
        _isLoading = false;
      });

      // ✅ Video ise controller başlat
      if (isVideo) {
        await _videoController?.dispose();
        _videoController = VideoPlayerController.file(file);
        await _videoController!.initialize();
        setState(() {});
      } else {
        await _videoController?.dispose();
        _videoController = null;
      }
    } catch (e) {
      setState(() {
        _isLoading = false;
      });
      if (mounted) {
        ErrorModal.show(
          context,
          title: 'Hata',
          message: 'Medya seçilirken bir hata oluştu.',
          icon: Icons.error_outline,
        );
      }
    }
  }

  bool _isVideoFile(String path) {
    final lower = path.toLowerCase();
    return lower.endsWith('.mp4') ||
        lower.endsWith('.mov') ||
        lower.endsWith('.avi') ||
        lower.endsWith('.mkv');
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
            onPressed: _selectedFile != null
                ? () {
                    widget.onMediaSelected(_selectedFile!);
                    Navigator.of(context).pop();
                  }
                : null,
            child: Text(
              'İleri',
              style: TextStyle(
                color: _selectedFile != null ? Colors.blue : Colors.grey,
                fontSize: 16,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
        ],
      ),
      body: Column(
        children: [
          // ✅ Üstte büyük önizleme alanı
          Expanded(
            flex: 2,
            child: Container(
              width: double.infinity,
              color: Colors.black,
              child: _buildPreview(),
            ),
          ),

          // ✅ Alt kısımda galeri grid
          Expanded(
            flex: 3,
            child: Container(
              color: Colors.black,
              child: Column(
                children: [
                  // ✅ Galeri başlığı
                  Container(
                    padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
                    child: Row(
                      mainAxisAlignment: MainAxisAlignment.spaceBetween,
                      children: [
                        Row(
                          children: [
                            const Text(
                              'Yakınlardakiler',
                              style: TextStyle(
                                color: Colors.white,
                                fontSize: 16,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                            const SizedBox(width: 8),
                            Icon(
                              Icons.arrow_drop_down,
                              color: Colors.white.withOpacity(0.7),
                            ),
                          ],
                        ),
                        Row(
                          children: [
                            Icon(
                              Icons.grid_view,
                              color: Colors.white.withOpacity(0.7),
                              size: 20,
                            ),
                            const SizedBox(width: 8),
                            Text(
                              'BİRDEN FAZLA SEÇ',
                              style: TextStyle(
                                color: Colors.white.withOpacity(0.7),
                                fontSize: 12,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),

                  // ✅ Grid görünümü
                  Expanded(
                    child: _buildGalleryGrid(),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildPreview() {
    if (_selectedFile == null) {
      return Center(
        child: Column(
          mainAxisAlignment: MainAxisAlignment.center,
          children: [
            Icon(
              Icons.photo_library,
              color: Colors.white.withOpacity(0.3),
              size: 64,
            ),
            const SizedBox(height: 16),
            Text(
              'Bir medya seçin',
              style: TextStyle(
                color: Colors.white.withOpacity(0.5),
                fontSize: 16,
              ),
            ),
          ],
        ),
      );
    }

    if (_isVideo && _videoController != null && _videoController!.value.isInitialized) {
      return CommonVideoPreview(
        controller: _videoController!,
        showPlayButton: true,
        playButtonColor: Colors.white,
        playButtonSize: 48,
      );
    }

    return Image.file(
      _selectedFile!,
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

  Widget _buildGalleryGrid() {
    if (_isLoading && _recentPhotos.isEmpty) {
      return const Center(
        child: CircularProgressIndicator(color: Colors.white),
      );
    }

    if (_recentPhotos.isEmpty) {
      return Center(
        child: Text(
          'Fotoğraf bulunamadı',
          style: TextStyle(
            color: Colors.white.withOpacity(0.5),
            fontSize: 16,
          ),
        ),
      );
    }

    return GridView.builder(
      padding: const EdgeInsets.all(2),
      gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
        crossAxisCount: 3,
        crossAxisSpacing: 2,
        mainAxisSpacing: 2,
      ),
      itemCount: _recentPhotos.length + (_hasMore ? 1 : 0),
      itemBuilder: (context, index) {
        if (index >= _recentPhotos.length) {
          _loadMorePhotos();
          return const Center(
            child: CircularProgressIndicator(color: Colors.white),
          );
        }

        final asset = _recentPhotos[index];
        final isSelected = _selectedAsset?.id == asset.id;

        return GestureDetector(
          onTap: () => _selectAsset(asset),
          child: Stack(
            fit: StackFit.expand,
            children: [
              // ✅ Thumbnail
              FutureBuilder<Uint8List?>(
                future: asset.thumbnailDataWithSize(
                  const ThumbnailSize(200, 200),
                ),
                builder: (context, snapshot) {
                  if (snapshot.hasData && snapshot.data != null) {
                    return Image.memory(
                      snapshot.data!,
                      fit: BoxFit.cover,
                    );
                  }
                  return Container(
                    color: Colors.grey[900],
                    child: const Center(
                      child: CircularProgressIndicator(color: Colors.white),
                    ),
                  );
                },
              ),

              // ✅ Video ikonu
              if (asset.type == AssetType.video)
                Positioned(
                  bottom: 4,
                  right: 4,
                  child: Container(
                    padding: const EdgeInsets.all(4),
                    decoration: BoxDecoration(
                      color: Colors.black.withOpacity(0.6),
                      borderRadius: BorderRadius.circular(4),
                    ),
                    child: Row(
                      mainAxisSize: MainAxisSize.min,
                      children: [
                        const Icon(
                          Icons.play_arrow,
                          color: Colors.white,
                          size: 12,
                        ),
                        const SizedBox(width: 2),
                        Text(
                          _formatDuration(asset.duration),
                          style: const TextStyle(
                            color: Colors.white,
                            fontSize: 10,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),

              // ✅ Seçim göstergesi
              if (isSelected)
                Container(
                  decoration: BoxDecoration(
                    border: Border.all(
                      color: Colors.blue,
                      width: 3,
                    ),
                  ),
                  child: Container(
                    color: Colors.blue.withOpacity(0.2),
                  ),
                ),
            ],
          ),
        );
      },
    );
  }

  String _formatDuration(int seconds) {
    final duration = Duration(seconds: seconds);
    final minutes = duration.inMinutes;
    final secs = duration.inSeconds % 60;
    return '${minutes.toString()}:${secs.toString().padLeft(2, '0')}';
  }
}

