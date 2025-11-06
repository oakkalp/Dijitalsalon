import 'dart:async';
import 'package:flutter/material.dart';
import 'package:flutter/painting.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:digimobil_new/utils/image_cache_config.dart';
import 'package:digimobil_new/utils/colors.dart';

/// ✅ Aspect Ratio Aware Image Widget
/// Portrait fotoğrafların kesilmesini önler
class AspectRatioImage extends StatefulWidget {
  final String imageUrl;
  final BoxFit fit;
  final Widget? placeholder;
  final Widget? errorWidget;
  final double? maxHeight;
  final double? maxWidth;
  final Color? backgroundColor;

  const AspectRatioImage({
    super.key,
    required this.imageUrl,
    this.fit = BoxFit.contain,
    this.placeholder,
    this.errorWidget,
    this.maxHeight,
    this.maxWidth,
    this.backgroundColor,
  });

  @override
  State<AspectRatioImage> createState() => _AspectRatioImageState();
}

class _AspectRatioImageState extends State<AspectRatioImage> {
  double? _aspectRatio;
  bool _isLoading = true;
  bool _hasError = false;

  @override
  void initState() {
    super.initState();
    _loadImageAspectRatio();
  }

  Future<void> _loadImageAspectRatio() async {
    try {
      // ✅ Görüntü metadata'sını al (aspect ratio için)
      final imageProvider = CachedNetworkImageProvider(
        widget.imageUrl,
        cacheManager: ImageCacheConfig.getCacheManager(widget.imageUrl),
      );
      
      // ✅ Görüntüyü resolve et ve aspect ratio'yu öğren
      final completer = Completer<ImageInfo>();
      final imageStream = imageProvider.resolve(ImageConfiguration.empty);
      
      late ImageStreamListener listener;
      listener = ImageStreamListener((ImageInfo imageInfo, bool synchronousCall) {
        completer.complete(imageInfo);
        imageStream.removeListener(listener);
      });
      
      imageStream.addListener(listener);
      
      final imageInfo = await completer.future;
      final image = imageInfo.image;
      
      if (mounted && image != null) {
        final width = image.width.toDouble();
        final height = image.height.toDouble();
        
        if (width > 0 && height > 0) {
          setState(() {
            _aspectRatio = width / height;
            _isLoading = false;
          });
        } else {
          // ✅ Geçersiz boyutlar, varsayılan kullan
          setState(() {
            _aspectRatio = 1.0;
            _isLoading = false;
          });
        }
      } else {
        // ✅ Görüntü yüklenemedi, varsayılan kullan
        if (mounted) {
          setState(() {
            _aspectRatio = 1.0;
            _isLoading = false;
          });
        }
      }
    } catch (e) {
      print('Aspect ratio load error: $e');
      // ✅ Hata durumunda varsayılan aspect ratio (1:1 - kare)
      if (mounted) {
        setState(() {
          _aspectRatio = 1.0;
          _isLoading = false;
          _hasError = true;
        });
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    // ✅ Loading durumu
    if (_isLoading) {
      return widget.placeholder ??
          Container(
            width: double.infinity,
            height: widget.maxHeight ?? MediaQuery.of(context).size.height * 0.35,
            color: widget.backgroundColor ?? AppColors.primary.withOpacity(0.1),
            child: const Center(
              child: CircularProgressIndicator(
                color: AppColors.primary,
                strokeWidth: 2,
              ),
            ),
          );
    }

    // ✅ Aspect ratio hesaplandı, görüntüyü göster
    final aspectRatio = _aspectRatio ?? 1.0;
    final screenWidth = MediaQuery.of(context).size.width;
    final maxWidth = widget.maxWidth ?? screenWidth;
    final maxHeight = widget.maxHeight ?? MediaQuery.of(context).size.height * 0.6;
    
    // ✅ Aspect ratio'ya göre gerçek boyutları hesapla
    double imageWidth = maxWidth;
    double imageHeight = maxWidth / aspectRatio;
    
    // ✅ Eğer yükseklik maksimum değeri aşıyorsa, yüksekliği sınırla
    if (imageHeight > maxHeight) {
      imageHeight = maxHeight;
      imageWidth = imageHeight * aspectRatio;
    }
    
    // ✅ Minimum yükseklik (çok küçük görüntüler için)
    if (imageHeight < 100) {
      imageHeight = 100;
      imageWidth = imageHeight * aspectRatio;
    }

    return Container(
      width: double.infinity,
      color: widget.backgroundColor ?? Colors.transparent,
      child: Center(
        child: SizedBox(
          width: imageWidth,
          height: imageHeight,
          child: _hasError
              ? (widget.errorWidget ??
                  Container(
                    color: AppColors.primary.withOpacity(0.1),
                    child: const Icon(
                      Icons.error_outline,
                      color: AppColors.primary,
                      size: 50,
                    ),
                  ))
              : CachedNetworkImage(
                  imageUrl: widget.imageUrl,
                  fit: widget.fit,
                  // ✅ CacheManager kullanırken maxWidthDiskCache/maxHeightDiskCache kullanılamaz
                  // Normal CacheManager ile sadece memCacheWidth/memCacheHeight kullanılabilir
                  cacheManager: ImageCacheConfig.getCacheManager(widget.imageUrl),
                  memCacheWidth: ImageCacheConfig.getMemCacheWidth(widget.imageUrl),
                  memCacheHeight: ImageCacheConfig.getMemCacheHeight(widget.imageUrl),
                  // ❌ maxWidthDiskCache ve maxHeightDiskCache kaldırıldı (ImageCacheManager gerektirir)
                  httpHeaders: {
                    'Connection': 'keep-alive',
                    'Cache-Control': ImageCacheConfig.isThumbnail(widget.imageUrl)
                        ? 'max-age=2592000'
                        : 'max-age=604800',
                  },
                  placeholder: (context, url) => widget.placeholder ??
                      Container(
                        width: imageWidth,
                        height: imageHeight,
                        color: AppColors.primary.withOpacity(0.1),
                        child: const Center(
                          child: CircularProgressIndicator(
                            color: AppColors.primary,
                            strokeWidth: 2,
                          ),
                        ),
                      ),
                  errorWidget: (context, url, error) {
                    print('AspectRatioImage error: $url - $error');
                    return widget.errorWidget ??
                        Container(
                          width: imageWidth,
                          height: imageHeight,
                          color: AppColors.primary.withOpacity(0.1),
                          child: const Icon(
                            Icons.error_outline,
                            color: AppColors.primary,
                            size: 50,
                          ),
                        );
                  },
                ),
        ),
      ),
    );
  }
}

