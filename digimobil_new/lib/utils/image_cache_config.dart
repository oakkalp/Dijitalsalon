import 'package:flutter_cache_manager/flutter_cache_manager.dart';
import 'package:cached_network_image/cached_network_image.dart';
import 'package:flutter/painting.dart';

/// ✅ Image Cache Configuration
/// Optimized cache settings for better performance
class ImageCacheConfig {
  // ✅ Memory cache configuration
  static const int maxMemoryCacheSize = 100 * 1024 * 1024; // 100 MB
  
  // ✅ Disk cache configuration
  static const int maxDiskCacheSize = 500 * 1024 * 1024; // 500 MB
  static const int maxDiskCacheAge = 30; // 30 days
  
  // ✅ Custom cache manager with optimized settings
  static DefaultCacheManager defaultCacheManager = DefaultCacheManager();
  
  // ✅ Image cache manager (for images only)
  static CacheManager imageCacheManager = CacheManager(
    Config(
      'image_cache',
      repo: JsonCacheInfoRepository(databaseName: 'image_cache'),
      fileService: HttpFileService(),
    ),
  );
  
  // ✅ Thumbnail cache manager (for thumbnails)
  static CacheManager thumbnailCacheManager = CacheManager(
    Config(
      'thumbnail_cache',
      repo: JsonCacheInfoRepository(databaseName: 'thumbnail_cache'),
      fileService: HttpFileService(),
    ),
  );
  
  /// ✅ Check if URL is a thumbnail
  static bool isThumbnail(String url) {
    return url.contains('thumbnail') || 
           url.contains('thumb') || 
           url.contains('preview') ||
           url.contains('kucuk_resim');
  }
  
  /// ✅ Get cache manager based on URL type
  static CacheManager? getCacheManager(String url) {
    if (isThumbnail(url)) {
      return thumbnailCacheManager;
    }
    return imageCacheManager;
  }
  
  /// ✅ Get memory cache width
  static int? getMemCacheWidth(String url, {int? maxWidth}) {
    if (isThumbnail(url)) {
      return maxWidth ?? 300; // Thumbnails için küçük boyut
    }
    return maxWidth; // Full images için belirtilen maxWidth
  }
  
  /// ✅ Get memory cache height
  static int? getMemCacheHeight(String url, {int? maxHeight}) {
    if (isThumbnail(url)) {
      return maxHeight ?? 300; // Thumbnails için küçük boyut
    }
    return maxHeight; // Full images için belirtilen maxHeight
  }
  
  /// ✅ Get disk cache width
  static int? getDiskCacheWidth(String url, {int? maxWidth}) {
    if (isThumbnail(url)) {
      return maxWidth ?? 800; // Thumbnails için orta boyut
    }
    return maxWidth ?? 1920; // Full images için HD
  }
  
  /// ✅ Get disk cache height
  static int? getDiskCacheHeight(String url, {int? maxHeight}) {
    if (isThumbnail(url)) {
      return maxHeight ?? 800; // Thumbnails için orta boyut
    }
    return maxHeight ?? 1920; // Full images için HD
  }
  
  /// ✅ Get optimized ImageProvider
  static ImageProvider getImageProvider(String url) {
    return CachedNetworkImageProvider(
      url,
      cacheManager: getCacheManager(url),
    );
  }
  
  /// ✅ Clear all image cache
  static Future<void> clearCache() async {
    await imageCacheManager.emptyCache();
    await thumbnailCacheManager.emptyCache();
    await defaultCacheManager.emptyCache();
  }
  
  /// ✅ Clear old cache files
  static Future<void> clearOldCache() async {
    await imageCacheManager.emptyCache();
    await thumbnailCacheManager.emptyCache();
  }
  
  /// ✅ Get cache size
  static Future<int> getCacheSize() async {
    // Cache size calculation would require additional package
    // For now, return 0
    return 0;
  }
}
