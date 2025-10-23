import 'package:flutter/material.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:digimobil_new/utils/colors.dart';

class RobustImageWidget extends StatelessWidget {
  final String imageUrl;
  final double? width;
  final double? height;
  final BoxFit fit;
  final Widget? placeholder;
  final Widget? errorWidget;
  final VoidCallback? onImageLoaded;

  const RobustImageWidget({
    super.key,
    required this.imageUrl,
    this.width,
    this.height,
    this.fit = BoxFit.cover,
    this.placeholder,
    this.errorWidget,
    this.onImageLoaded,
  });

  @override
  Widget build(BuildContext context) {
    return CachedNetworkImage(
      imageUrl: imageUrl,
      width: width,
      height: height,
      fit: fit,
      // Disable problematic cache settings
      memCacheWidth: null,
      memCacheHeight: null,
      maxWidthDiskCache: null,
      maxHeightDiskCache: null,
      // Fast loading settings
      httpHeaders: {
        'Connection': 'keep-alive',
        'Cache-Control': 'max-age=3600', // 1 hour cache
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
