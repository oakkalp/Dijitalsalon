import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/image_cache_config.dart';

class RobustImageWidget extends StatelessWidget {
  final String imageUrl;
  final double? width;
  final double? height;
  final BoxFit fit;
  final Widget? placeholder;
  final Widget? errorWidget;
  final VoidCallback? onImageLoaded;
  final bool useOptimizedCache;

  const RobustImageWidget({
    super.key,
    required this.imageUrl,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.placeholder,
    this.errorWidget,
    this.onImageLoaded,
    this.useOptimizedCache = true,
  });

  @override
  Widget build(BuildContext context) {
    // Determine if this is a thumbnail
    final isThumb = ImageCacheConfig.isThumbnail(imageUrl);
    
    // Get cache manager
    final cacheManager = useOptimizedCache 
        ? ImageCacheConfig.getCacheManager(imageUrl)
        : null;
    
    // Get optimized cache dimensions
    final memWidth = useOptimizedCache
        ? ImageCacheConfig.getMemCacheWidth(imageUrl, maxWidth: width?.toInt())
        : null;
    final memHeight = useOptimizedCache
        ? ImageCacheConfig.getMemCacheHeight(imageUrl, maxHeight: height?.toInt())
        : null;
    final diskWidth = useOptimizedCache
        ? ImageCacheConfig.getDiskCacheWidth(imageUrl, maxWidth: width?.toInt())
        : null;
    final diskHeight = useOptimizedCache
        ? ImageCacheConfig.getDiskCacheHeight(imageUrl, maxHeight: height?.toInt())
        : null;
    
    return CachedNetworkImage(
      imageUrl: imageUrl,
      width: width,
      height: height,
      fit: fit,
      // Optimized cache manager
      cacheManager: cacheManager,
      // Optimized memory cache (smaller for thumbnails, full for images)
      memCacheWidth: memWidth,
      memCacheHeight: memHeight,
      // ❌ maxWidthDiskCache ve maxHeightDiskCache kaldırıldı (ImageCacheManager gerektirir)
      // Normal CacheManager ile bu parametreler kullanılamaz
      // Fast loading settings
      httpHeaders: {
        'Connection': 'keep-alive',
        'Cache-Control': isThumb 
            ? 'max-age=2592000' // 30 days for thumbnails
            : 'max-age=604800', // 7 days for full images
      },
      placeholder: (context, url) => placeholder ?? Container(
        width: width,
        height: height,
        color: AppColors.primary.withOpacity(0.1),
        child: const Center(
          child: CircularProgressIndicator(
            color: AppColors.primary,
            strokeWidth: 2,
          ),
        ),
      ),
      errorWidget: (context, url, error) {
        print('Image load error: $url - $error');
        return errorWidget ?? Container(
          width: width,
          height: height,
          color: AppColors.primary.withOpacity(0.1),
          child: const Icon(
            Icons.error_outline,
            color: AppColors.primary,
            size: 30,
          ),
        );
      },
      imageBuilder: (context, imageProvider) {
        // Call onImageLoaded when image is ready
        WidgetsBinding.instance.addPostFrameCallback((_) {
          onImageLoaded?.call();
        });
        return Image(
          image: imageProvider,
          width: width,
          height: height,
          fit: fit,
        );
      },
    );
  }
}
