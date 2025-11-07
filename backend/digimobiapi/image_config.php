<?php
/**
 * Image Optimization Configuration
 * Centralized settings for image processing and optimization
 */

class ImageConfig {
    
    // Image Quality Settings
    const THUMBNAIL_QUALITY = 80;
    const COMPRESSED_QUALITY = 85;
    const HIGH_QUALITY = 95;
    
    // File Size Limits
    const MAX_FILE_SIZE = 5 * 1024 * 1024; // 5MB
    const MAX_FILE_SIZE_MOBILE = 2 * 1024 * 1024; // 2MB for mobile uploads
    const MAX_FILE_SIZE_WEB = 10 * 1024 * 1024; // 10MB for web uploads
    
    // Dimension Limits
    const MAX_DIMENSION = 2048; // Max width/height for compressed images
    const MAX_DIMENSION_MOBILE = 1024; // Max dimension for mobile uploads
    const MAX_DIMENSION_WEB = 4096; // Max dimension for web uploads
    
    // Thumbnail Dimensions
    const THUMBNAIL_WIDTH = 300;
    const THUMBNAIL_HEIGHT = 300;
    const THUMBNAIL_WIDTH_SMALL = 150;
    const THUMBNAIL_HEIGHT_SMALL = 150;
    
    // Preview Dimensions (for grid views)
    const PREVIEW_WIDTH = 600;
    const PREVIEW_HEIGHT = 600;
    const PREVIEW_WIDTH_SMALL = 300;
    const PREVIEW_HEIGHT_SMALL = 300;
    
    // Profile Image Dimensions
    const PROFILE_WIDTH = 400;
    const PROFILE_HEIGHT = 400;
    const PROFILE_THUMBNAIL_WIDTH = 100;
    const PROFILE_THUMBNAIL_HEIGHT = 100;
    
    // Event Cover Photo Dimensions
    const EVENT_COVER_WIDTH = 800;
    const EVENT_COVER_HEIGHT = 600;
    const EVENT_COVER_THUMBNAIL_WIDTH = 200;
    const EVENT_COVER_THUMBNAIL_HEIGHT = 150;
    
    // Story Dimensions
    const STORY_WIDTH = 1080;
    const STORY_HEIGHT = 1920; // 9:16 aspect ratio
    const STORY_THUMBNAIL_WIDTH = 200;
    const STORY_THUMBNAIL_HEIGHT = 355;
    
    // Media Dimensions
    const MEDIA_WIDTH = 1200;
    const MEDIA_HEIGHT = 1200;
    const MEDIA_THUMBNAIL_WIDTH = 300;
    const MEDIA_THUMBNAIL_HEIGHT = 300;
    
    // Allowed File Types
    const ALLOWED_IMAGE_TYPES = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    const ALLOWED_VIDEO_TYPES = ['mp4', 'mov', 'avi', 'webm'];
    
    // Compression Settings by File Type
    const COMPRESSION_SETTINGS = [
        'jpg' => [
            'quality' => 85,
            'progressive' => true,
            'optimize' => true
        ],
        'jpeg' => [
            'quality' => 85,
            'progressive' => true,
            'optimize' => true
        ],
        'png' => [
            'compression_level' => 6,
            'optimize' => true
        ],
        'gif' => [
            'optimize' => true
        ],
        'webp' => [
            'quality' => 85,
            'lossless' => false
        ]
    ];
    
    // Cache Settings
    const CACHE_DURATION = 30 * 24 * 60 * 60; // 30 days in seconds
    const CACHE_CLEANUP_INTERVAL = 7 * 24 * 60 * 60; // 7 days in seconds
    
    // Performance Settings
    const ENABLE_LAZY_LOADING = true;
    const ENABLE_PROGRESSIVE_JPEG = true;
    const ENABLE_WEBP_CONVERSION = true;
    const ENABLE_AUTO_ROTATION = true;
    
    // Error Handling
    const MAX_RETRY_ATTEMPTS = 3;
    const RETRY_DELAY = 1000; // milliseconds
    
    // Debug Settings
    const DEBUG_MODE = false;
    const LOG_PROCESSING_TIME = true;
    const LOG_FILE_SIZES = true;
    
    /**
     * Get configuration for specific image type
     */
    public static function getConfigForType($type) {
        $configs = [
            'profile' => [
                'max_width' => self::PROFILE_WIDTH,
                'max_height' => self::PROFILE_HEIGHT,
                'thumbnail_width' => self::PROFILE_THUMBNAIL_WIDTH,
                'thumbnail_height' => self::PROFILE_THUMBNAIL_HEIGHT,
                'quality' => self::COMPRESSED_QUALITY,
                'max_file_size' => self::MAX_FILE_SIZE_MOBILE
            ],
            'event_cover' => [
                'max_width' => self::EVENT_COVER_WIDTH,
                'max_height' => self::EVENT_COVER_HEIGHT,
                'thumbnail_width' => self::EVENT_COVER_THUMBNAIL_WIDTH,
                'thumbnail_height' => self::EVENT_COVER_THUMBNAIL_HEIGHT,
                'quality' => self::COMPRESSED_QUALITY,
                'max_file_size' => self::MAX_FILE_SIZE
            ],
            'story' => [
                'max_width' => self::STORY_WIDTH,
                'max_height' => self::STORY_HEIGHT,
                'thumbnail_width' => self::STORY_THUMBNAIL_WIDTH,
                'thumbnail_height' => self::STORY_THUMBNAIL_HEIGHT,
                'quality' => self::COMPRESSED_QUALITY,
                'max_file_size' => self::MAX_FILE_SIZE_MOBILE
            ],
            'media' => [
                'max_width' => self::MEDIA_WIDTH,
                'max_height' => self::MEDIA_HEIGHT,
                'thumbnail_width' => self::MEDIA_THUMBNAIL_WIDTH,
                'thumbnail_height' => self::MEDIA_THUMBNAIL_HEIGHT,
                'quality' => self::COMPRESSED_QUALITY,
                'max_file_size' => self::MAX_FILE_SIZE
            ]
        ];
        
        return $configs[$type] ?? $configs['media'];
    }
    
    /**
     * Get compression settings for file type
     */
    public static function getCompressionSettings($file_extension) {
        return self::COMPRESSION_SETTINGS[strtolower($file_extension)] ?? self::COMPRESSION_SETTINGS['jpg'];
    }
    
    /**
     * Check if file type is allowed
     */
    public static function isAllowedImageType($file_extension) {
        return in_array(strtolower($file_extension), self::ALLOWED_IMAGE_TYPES);
    }
    
    /**
     * Check if file type is allowed video
     */
    public static function isAllowedVideoType($file_extension) {
        return in_array(strtolower($file_extension), self::ALLOWED_VIDEO_TYPES);
    }
    
    /**
     * Get optimal dimensions maintaining aspect ratio
     */
    public static function getOptimalDimensions($original_width, $original_height, $max_width, $max_height) {
        $ratio = min($max_width / $original_width, $max_height / $original_height);
        
        return [
            'width' => intval($original_width * $ratio),
            'height' => intval($original_height * $ratio)
        ];
    }
    
    /**
     * Format file size for display
     */
    public static function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
    
    /**
     * Get cache key for image
     */
    public static function getCacheKey($image_path, $type = 'original') {
        return md5($image_path . '_' . $type);
    }
    
    /**
     * Check if debug mode is enabled
     */
    public static function isDebugMode() {
        return self::DEBUG_MODE;
    }
    
    /**
     * Log processing information
     */
    public static function logProcessing($message, $data = []) {
        if (self::isDebugMode()) {
            error_log('[Image Processing] ' . $message . ' ' . json_encode($data));
        }
    }
}
?>
