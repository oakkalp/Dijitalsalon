import 'dart:io';
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:photo_manager/photo_manager.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/theme_colors.dart';
import 'package:digimobil_new/widgets/error_modal.dart';
import 'package:permission_handler/permission_handler.dart';

/// Instagram tarzı galeri grid ekranı
/// ✅ Tek seçim (çoklu seçim YOK)
/// ✅ Post/Story seçimi (Reels YOK)
class GalleryPickerScreen extends StatefulWidget {
  final Function(File file)? onMediaSelected;

  const GalleryPickerScreen({
    super.key,
    this.onMediaSelected,
  });

  static Future<File?> show(BuildContext context) async {
    final result = await Navigator.of(context).push<File>(
      MaterialPageRoute(
        builder: (context) => const GalleryPickerScreen(),
      ),
    );
    return result;
  }

  @override
  State<GalleryPickerScreen> createState() => _GalleryPickerScreenState();
}

class _GalleryPickerScreenState extends State<GalleryPickerScreen> {
  List<AssetEntity> _recentPhotos = [];
  List<AssetPathEntity> _albums = [];
  AssetPathEntity? _selectedAlbum;
  bool _isLoading = true;
  bool _isLoadingMore = false;
  int _currentPage = 0;
  static const int _pageSize = 30;
  bool _hasMore = true;

  @override
  void initState() {
    super.initState();
    _loadPhotos();
  }

  Future<void> _loadPhotos() async {
    setState(() {
      _isLoading = true;
    });

    try {
      // ✅ İzin kontrolü
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

      // ✅ Tüm albümleri al
      final albums = await PhotoManager.getAssetPathList(
        type: RequestType.image,
        hasAll: true,
      );

      // ✅ "Recents" (Yakınlardakiler) albümünü bul
      AssetPathEntity? recentsAlbum;
      for (final album in albums) {
        if (album.name.toLowerCase().contains('recent') ||
            album.name.toLowerCase().contains('yakın') ||
            album.name.toLowerCase().contains('camera') ||
            album.isAll) {
          recentsAlbum = album;
          break;
        }
      }

      // ✅ Eğer "Recents" bulunamazsa ilk albümü kullan
      _selectedAlbum = recentsAlbum ?? (albums.isNotEmpty ? albums.first : null);

      if (_selectedAlbum != null) {
        // ✅ İlk sayfa fotoğrafları
        final photos = await _selectedAlbum!.getAssetListPaged(
          page: 0,
          size: _pageSize,
        );
        setState(() {
          _albums = albums;
          _recentPhotos = photos;
          _hasMore = photos.length >= _pageSize;
          _isLoading = false;
        });
      } else {
        setState(() {
          _albums = albums;
          _isLoading = false;
        });
      }
    } catch (e) {
      print('Galeri yükleme hatası: $e');
      if (mounted) {
        ErrorModal.show(
          context,
          title: 'Galeri Hatası',
          message: 'Fotoğraflar yüklenirken bir sorun oluştu.',
          icon: Icons.error_outline,
        );
      }
      setState(() {
        _isLoading = false;
      });
    }
  }

  Future<void> _loadMorePhotos() async {
    if (_isLoadingMore || !_hasMore || _selectedAlbum == null) return;

    setState(() {
      _isLoadingMore = true;
    });

    try {
      final nextPage = _currentPage + 1;
      final photos = await _selectedAlbum!.getAssetListPaged(
        page: nextPage,
        size: _pageSize,
      );

      setState(() {
        _recentPhotos.addAll(photos);
        _currentPage = nextPage;
        _hasMore = photos.length >= _pageSize;
        _isLoadingMore = false;
      });
    } catch (e) {
      print('Daha fazla fotoğraf yükleme hatası: $e');
      setState(() {
        _isLoadingMore = false;
      });
    }
  }

  Future<void> _selectPhoto(AssetEntity asset) async {
    try {
      // ✅ Fotoğrafı dosyaya dönüştür
      final file = await asset.file;
      if (file != null && mounted) {
        // ✅ Seçilen fotoğrafı geri döndür
        Navigator.of(context).pop(file);
        if (widget.onMediaSelected != null) {
          widget.onMediaSelected!(file);
        }
      }
    } catch (e) {
      print('Fotoğraf seçme hatası: $e');
      if (mounted) {
        ErrorModal.show(
          context,
          title: 'Hata',
          message: 'Fotoğraf seçilirken bir sorun oluştu.',
          icon: Icons.error_outline,
        );
      }
    }
  }

  void _showAlbumSelector() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        decoration: BoxDecoration(
          color: ThemeColors.surface(context),
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            // Handle bar
            Container(
              margin: const EdgeInsets.only(top: 12, bottom: 8),
              width: 40,
              height: 4,
              decoration: BoxDecoration(
                color: ThemeColors.textSecondary(context).withOpacity(0.3),
                borderRadius: BorderRadius.circular(2),
              ),
            ),
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Albüm Seç',
                style: TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            Flexible(
              child: ListView.builder(
                shrinkWrap: true,
                itemCount: _albums.length,
                itemBuilder: (context, index) {
                  final album = _albums[index];
                  final isSelected = _selectedAlbum?.id == album.id;
                  return ListTile(
                    leading: FutureBuilder<AssetEntity?>(
                      future: album.getAssetListRange(start: 0, end: 1).then(
                            (list) => list.isNotEmpty ? list.first : null,
                          ),
                      builder: (context, snapshot) {
                        if (snapshot.hasData && snapshot.data != null) {
                          return FutureBuilder<Widget?>(
                            future: _buildThumbnail(snapshot.data!),
                            builder: (context, thumbSnapshot) {
                              return ClipRRect(
                                borderRadius: BorderRadius.circular(4),
                                child: thumbSnapshot.data ??
                                    Container(
                                      width: 40,
                                      height: 40,
                                      color: ThemeColors.border(context),
                                    ),
                              );
                            },
                          );
                        }
                        return Container(
                          width: 40,
                          height: 40,
                          decoration: BoxDecoration(
                            color: ThemeColors.border(context),
                            borderRadius: BorderRadius.circular(4),
                          ),
                          child: Icon(
                            Icons.folder,
                            color: ThemeColors.textSecondary(context),
                            size: 20,
                          ),
                        );
                      },
                    ),
                    title: Text(album.name),
                    subtitle: FutureBuilder<int>(
                      future: album.assetCountAsync,
                      builder: (context, snapshot) {
                        if (snapshot.hasData) {
                          return Text('${snapshot.data} fotoğraf');
                        }
                        return const Text('Yükleniyor...');
                      },
                    ),
                    trailing: isSelected
                        ? Icon(
                            Icons.check_circle,
                            color: ThemeColors.primary(context),
                          )
                        : null,
                    onTap: () async {
                      Navigator.of(context).pop();
                      setState(() {
                        _selectedAlbum = album;
                        _recentPhotos = [];
                        _currentPage = 0;
                        _hasMore = true;
                        _isLoading = true;
                      });
                      final photos = await album.getAssetListPaged(
                        page: 0,
                        size: _pageSize,
                      );
                      setState(() {
                        _recentPhotos = photos;
                        _hasMore = photos.length >= _pageSize;
                        _isLoading = false;
                      });
                    },
                  );
                },
              ),
            ),
            SizedBox(height: MediaQuery.of(context).padding.bottom),
          ],
        ),
      ),
    );
  }

  Future<Widget?> _buildThumbnail(AssetEntity asset) async {
    try {
      final thumbnail = await asset.thumbnailDataWithSize(
        const ThumbnailSize(200, 200),
      );
      if (thumbnail != null) {
        return Image.memory(
          thumbnail,
          width: 40,
          height: 40,
          fit: BoxFit.cover,
        );
      }
    } catch (e) {
      print('Thumbnail oluşturma hatası: $e');
    }
    return null;
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;

    return Scaffold(
      backgroundColor: ThemeColors.background(context),
      appBar: AppBar(
        backgroundColor: ThemeColors.background(context),
        elevation: 0,
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.of(context).pop(),
        ),
        title: GestureDetector(
          onTap: _showAlbumSelector,
          child: Row(
            mainAxisSize: MainAxisSize.min,
            children: [
              Text(
                _selectedAlbum?.name ?? 'Yakınlardakiler',
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
              const SizedBox(width: 4),
              const Icon(Icons.arrow_drop_down, size: 20),
            ],
          ),
        ),
        centerTitle: true,
      ),
      body: _isLoading
          ? const Center(child: CircularProgressIndicator())
          : _recentPhotos.isEmpty
              ? Center(
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        Icons.photo_library_outlined,
                        size: 64,
                        color: ThemeColors.textSecondary(context),
                      ),
                      const SizedBox(height: 16),
                      Text(
                        'Fotoğraf bulunamadı',
                        style: TextStyle(
                          fontSize: 16,
                          color: ThemeColors.textSecondary(context),
                        ),
                      ),
                    ],
                  ),
                )
              : GridView.builder(
                  padding: const EdgeInsets.all(2),
                  gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
                    crossAxisCount: 3,
                    crossAxisSpacing: 2,
                    mainAxisSpacing: 2,
                  ),
                  itemCount: _recentPhotos.length + (_hasMore ? 1 : 0),
                  itemBuilder: (context, index) {
                    // ✅ Son item: "Daha fazla yükle" göstergesi
                    if (index == _recentPhotos.length) {
                      if (_isLoadingMore) {
                        return const Center(child: CircularProgressIndicator());
                      }
                      _loadMorePhotos();
                      return const SizedBox.shrink();
                    }

                    final asset = _recentPhotos[index];
                    return _PhotoGridItem(
                      asset: asset,
                      onTap: () => _selectPhoto(asset),
                    );
                  },
                ),
    );
  }
}

class _PhotoGridItem extends StatelessWidget {
  final AssetEntity asset;
  final VoidCallback onTap;

  const _PhotoGridItem({
    required this.asset,
    required this.onTap,
  });

  @override
  Widget build(BuildContext context) {
    return GestureDetector(
      onTap: onTap,
      child: FutureBuilder<Uint8List?>(
        future: asset.thumbnailDataWithSize(const ThumbnailSize(300, 300)),
        builder: (context, snapshot) {
          if (snapshot.hasData && snapshot.data != null) {
            return Image.memory(
              snapshot.data!,
              fit: BoxFit.cover,
            );
          }
          return Container(
            color: ThemeColors.surface(context),
            child: const Center(
              child: CircularProgressIndicator(strokeWidth: 2),
            ),
          );
        },
      ),
    );
  }
}

