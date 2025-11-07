<?php
require_once __DIR__ . '/image_config.php';

/**
 * Image Optimization and Thumbnail Generation Utility
 * Handles image compression, resizing, and thumbnail creation for better performance
 */

class ImageUtils {
    
    /**
     * Process uploaded image: compress, resize, and create thumbnails
     * 
     * @param string $source_path Original image path
     * @param string $base_filename Base filename (without extension)
     * @param string $upload_dir Upload directory
     * @param string $file_extension File extension
     * @param string $image_type Type of image (profile, event_cover, story, media)
     * @return array Array with processed file paths
     */
    public static function processImage($source_path, $base_filename, $upload_dir, $file_extension, $image_type = 'media') {
        $results = [
            'original' => null,
            'compressed' => null,
            'thumbnail' => null,
            'preview' => null,
            'error' => null
        ];
        
        try {
            // Get configuration for image type
            $config = ImageConfig::getConfigForType($image_type);
            
            // Get original image info
            $image_info = getimagesize($source_path);
            if (!$image_info) {
                throw new Exception('Invalid image file');
            }
            
            $original_width = $image_info[0];
            $original_height = $image_info[1];
            $mime_type = $image_info['mime'];
            
            // Log processing start
            ImageConfig::logProcessing('Processing image', [
                'file' => $source_path,
                'type' => $image_type,
                'original_size' => ImageConfig::formatFileSize(filesize($source_path)),
                'dimensions' => "{$original_width}x{$original_height}"
            ]);
            
            // Create image resource based on type
            $source_image = self::createImageResource($source_path, $mime_type);
            if (!$source_image) {
                throw new Exception('Could not create image resource');
            }
            
            // 1. Create compressed version (if original is too large)
            $compressed_path = null;
            if ($original_width > $config['max_width'] || $original_height > $config['max_height'] || filesize($source_path) > $config['max_file_size']) {
                $compressed_filename = $base_filename . '_compressed.' . $file_extension;
                $compressed_path = $upload_dir . $compressed_filename;
                
                self::resizeImage($source_image, $compressed_path, $config['max_width'], $config['max_height'], $config['quality'], $file_extension);
                $results['compressed'] = $compressed_path;
                
                ImageConfig::logProcessing('Created compressed version', [
                    'path' => $compressed_path,
                    'size' => ImageConfig::formatFileSize(filesize($compressed_path))
                ]);
            }
            
            // 2. Create thumbnail
            $thumbnail_filename = $base_filename . '_thumb.' . $file_extension;
            $thumbnail_path = $upload_dir . $thumbnail_filename;
            self::resizeImage($source_image, $thumbnail_path, $config['thumbnail_width'], $config['thumbnail_height'], ImageConfig::THUMBNAIL_QUALITY, $file_extension);
            $results['thumbnail'] = $thumbnail_path;
            
            // 3. Create preview (for grid views)
            $preview_filename = $base_filename . '_preview.' . $file_extension;
            $preview_path = $upload_dir . $preview_filename;
            self::resizeImage($source_image, $preview_path, ImageConfig::PREVIEW_WIDTH, ImageConfig::PREVIEW_HEIGHT, $config['quality'], $file_extension);
            $results['preview'] = $preview_path;
            
            // 4. Keep original if it's small enough, otherwise use compressed
            // ✅ CRITICAL: Orijinal dosyayı da EXIF orientation'a göre düzelt
            if ($compressed_path && file_exists($compressed_path)) {
                // Replace original with compressed version (EXIF orientation düzeltilmiş)
                unlink($source_path);
                rename($compressed_path, $source_path);
                $results['original'] = $source_path;
            } else {
                // ✅ Orijinal dosya küçükse bile EXIF orientation'ı düzelt ve kaydet
                // ✅ $source_image zaten EXIF orientation düzeltilmiş, bunu orijinal dosyaya kaydet
                if ($mime_type === 'image/jpeg') {
                    // ✅ EXIF orientation düzeltilmiş görüntüyü orijinal dosyaya kaydet
                    imagejpeg($source_image, $source_path, 95);
                    error_log("ImageUtils - Fixed EXIF orientation for original file: $source_path");
                } elseif ($mime_type === 'image/png') {
                    imagepng($source_image, $source_path, 9);
                } elseif ($mime_type === 'image/webp') {
                    imagewebp($source_image, $source_path, 95);
                }
                $results['original'] = $source_path;
            }
            
            // Clean up
            imagedestroy($source_image);
            
            ImageConfig::logProcessing('Image processing completed', [
                'original' => $results['original'],
                'thumbnail' => $results['thumbnail'],
                'preview' => $results['preview']
            ]);
            
        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
            ImageConfig::logProcessing('Image processing error', ['error' => $e->getMessage()]);
        }
        
        return $results;
    }
    
    /**
     * Create image resource from file
     * ✅ EXIF orientation desteği eklendi
     */
    private static function createImageResource($file_path, $mime_type) {
        $image_resource = false;
        
        switch ($mime_type) {
            case 'image/jpeg':
                $image_resource = imagecreatefromjpeg($file_path);
                // ✅ EXIF orientation kontrolü (sadece JPEG için)
                if ($image_resource && function_exists('exif_read_data')) {
                    try {
                        $exif = @exif_read_data($file_path);
                        if ($exif && isset($exif['Orientation'])) {
                            $orientation = $exif['Orientation'];
                            // ✅ Orientation'a göre görüntüyü döndür
                            $image_resource = self::fixImageOrientation($image_resource, $orientation);
                        }
                    } catch (Exception $e) {
                        // EXIF okuma hatası - görüntüyü olduğu gibi kullan
                        error_log("EXIF read error: " . $e->getMessage());
                    }
                }
                return $image_resource;
            case 'image/png':
                return imagecreatefrompng($file_path);
            case 'image/gif':
                return imagecreatefromgif($file_path);
            case 'image/webp':
                return imagecreatefromwebp($file_path);
            default:
                return false;
        }
    }
    
    /**
     * Fix image orientation based on EXIF data
     * ✅ EXIF orientation değerlerine göre görüntüyü döndürür
     */
    private static function fixImageOrientation($image_resource, $orientation) {
        switch ($orientation) {
            case 2: // Horizontal flip
                return self::flipImage($image_resource, 'horizontal');
            case 3: // 180 rotate
                return imagerotate($image_resource, 180, 0);
            case 4: // Vertical flip
                return self::flipImage($image_resource, 'vertical');
            case 5: // Rotate 90 CW and flip horizontal
                $rotated = imagerotate($image_resource, -90, 0);
                imagedestroy($image_resource);
                return self::flipImage($rotated, 'horizontal');
            case 6: // Rotate 90 CW
                $rotated = imagerotate($image_resource, -90, 0);
                imagedestroy($image_resource);
                return $rotated;
            case 7: // Rotate 90 CCW and flip horizontal
                $rotated = imagerotate($image_resource, 90, 0);
                imagedestroy($image_resource);
                return self::flipImage($rotated, 'horizontal');
            case 8: // Rotate 90 CCW
                $rotated = imagerotate($image_resource, 90, 0);
                imagedestroy($image_resource);
                return $rotated;
            default:
                return $image_resource; // No rotation needed
        }
    }
    
    /**
     * Flip image horizontally or vertically
     */
    private static function flipImage($image_resource, $mode = 'horizontal') {
        $width = imagesx($image_resource);
        $height = imagesy($image_resource);
        $flipped = imagecreatetruecolor($width, $height);
        
        if ($mode === 'horizontal') {
            for ($x = 0; $x < $width; $x++) {
                imagecopy($flipped, $image_resource, $width - $x - 1, 0, $x, 0, 1, $height);
            }
        } else {
            for ($y = 0; $y < $height; $y++) {
                imagecopy($flipped, $image_resource, 0, $height - $y - 1, 0, $y, $width, 1);
            }
        }
        
        imagedestroy($image_resource);
        return $flipped;
    }
    
    /**
     * Resize image maintaining aspect ratio
     */
    private static function resizeImage($source_image, $output_path, $max_width, $max_height, $quality, $file_extension) {
        $source_width = imagesx($source_image);
        $source_height = imagesy($source_image);
        
        // Calculate new dimensions maintaining aspect ratio
        $ratio = min($max_width / $source_width, $max_height / $source_height);
        $new_width = intval($source_width * $ratio);
        $new_height = intval($source_height * $ratio);
        
        // Create new image
        $new_image = imagecreatetruecolor($new_width, $new_height);
        
        // Preserve transparency for PNG
        if ($file_extension === 'png') {
            imagealphablending($new_image, false);
            imagesavealpha($new_image, true);
            $transparent = imagecolorallocatealpha($new_image, 255, 255, 255, 127);
            imagefilledrectangle($new_image, 0, 0, $new_width, $new_height, $transparent);
        }
        
        // Resize
        imagecopyresampled($new_image, $source_image, 0, 0, 0, 0, $new_width, $new_height, $source_width, $source_height);
        
        // Save based on file extension
        switch ($file_extension) {
            case 'jpg':
            case 'jpeg':
                imagejpeg($new_image, $output_path, $quality);
                break;
            case 'png':
                imagepng($new_image, $output_path, intval(9 - ($quality / 10)));
                break;
            case 'gif':
                imagegif($new_image, $output_path);
                break;
            case 'webp':
                imagewebp($new_image, $output_path, $quality);
                break;
        }
        
        imagedestroy($new_image);
    }
    
    /**
     * Generate video thumbnail using FFmpeg (if available)
     */
    public static function generateVideoThumbnail($video_path, $thumbnail_path, $time_offset = 5) {
        try {
            error_log("Video Thumbnail - START (video: $video_path, output: $thumbnail_path)");
            
            // Check if FFmpeg is available
            error_log("Video Thumbnail - Searching for FFmpeg...");
            $ffmpeg_path = self::findFFmpeg();
            
            if (!$ffmpeg_path) {
                error_log("Video Thumbnail - FFmpeg NOT FOUND - returning false");
                return false;
            }
            
            error_log("Video Thumbnail - FFmpeg found: $ffmpeg_path");
            
            // Validate video path (skip file_exists for C: drive)
            error_log("Video Thumbnail - Video path: $video_path");
            error_log("Video Thumbnail - Output path: $thumbnail_path");
            
            $command = sprintf(
                '"%s" -i "%s" -ss %d -vframes 1 -q:v 2 "%s" 2>&1',
                $ffmpeg_path,
                $video_path,
                $time_offset,
                $thumbnail_path
            );
            
            error_log("Video Thumbnail - Command: $command");
            
            $output = [];
            $return_code = -1;
            
            $start_time = microtime(true);
            set_time_limit(30); // 30 saniye timeout
            @exec($command, $output, $return_code);
            set_time_limit(300); // Reset
            $duration = round(microtime(true) - $start_time, 2);
            
            error_log("Video Thumbnail - Exec completed. Duration: {$duration}s, Return code: $return_code");
            error_log("Video Thumbnail - Output lines: " . count($output));
            
            if (!empty($output)) {
                $first_lines = array_slice($output, 0, 3);
                $last_lines = array_slice($output, -3);
                error_log("Video Thumbnail - FFmpeg output (first 3): " . implode(' | ', $first_lines));
                error_log("Video Thumbnail - FFmpeg output (last 3): " . implode(' | ', $last_lines));
            }
            
            if ($return_code !== 0) {
                error_log("Video Thumbnail - FFmpeg failed with code $return_code");
                return false;
            }
            
            // Dosya kontrolü
            if (file_exists($thumbnail_path)) {
                $filesize = filesize($thumbnail_path);
                error_log("Video Thumbnail - File exists! Size: {$filesize} bytes");
                
                if ($filesize > 0) {
                    error_log("Video Thumbnail - Result: SUCCESS");
                    return $thumbnail_path;
                } else {
                    error_log("Video Thumbnail - Result: FAILED (file is empty)");
                    return false;
                }
            } else {
                error_log("Video Thumbnail - Result: FAILED (file not created)");
                error_log("Video Thumbnail - Expected path: $thumbnail_path");
                return false;
            }
        } catch (Exception $e) {
            error_log("Video Thumbnail - Exception: " . $e->getMessage());
            return false;
        } catch (Error $e) {
            error_log("Video Thumbnail - Fatal Error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Find FFmpeg executable
     */
    private static function findFFmpeg() {
        error_log("findFFmpeg - START searching...");
        
        // ✅ Önce PATH'ten kontrol et
        $paths_to_check = [
            'C:\\ffmpeg\\ffmpeg-8.0-essentials_build\\bin\\ffmpeg.exe',  // En çalışan
            'Z:\\ffmpeg\\bin\\ffmpeg.exe',  // Z: drive (Plesk erişilebilir)
            'ffmpeg',  // System PATH
            'C:\\ffmpeg\\ffmpeg-7.1-essentials_build\\bin\\ffmpeg.exe',
            'C:\\ffmpeg\\bin\\ffmpeg.exe',
            'C:\\Program Files\\ffmpeg\\bin\\ffmpeg.exe',
        ];
        
        error_log("findFFmpeg - Testing " . count($paths_to_check) . " paths");
        
        foreach ($paths_to_check as $index => $path) {
            error_log("findFFmpeg - [" . ($index + 1) . "/" . count($paths_to_check) . "] Testing: $path");
            
            // SKIP file_exists() - direkt exec() dene (Windows C: sürücüsü sorunu)
            error_log("findFFmpeg - Skipping file_exists, testing exec directly...");
            
            $output = [];
            $return = -1;
            
            // Timeout için 10 saniye limit koy
            set_time_limit(10);
            
            @exec("\"$path\" -version 2>&1", $output, $return);
            
            // Timeout'u geri al
            set_time_limit(300);
            
            error_log("findFFmpeg - exec return code: $return, output lines: " . count($output));
            
            if ($return === 0 && !empty($output)) {
                error_log("findFFmpeg - SUCCESS! FFmpeg works at: $path");
                return $path;
            } else {
                error_log("findFFmpeg - FAILED at $path (return: $return)");
                if (!empty($output)) {
                    error_log("findFFmpeg - Output: " . implode(' | ', array_slice($output, 0, 2)));
                }
            }
        }
        
        error_log("findFFmpeg - FAILED - FFmpeg not found in any location");
        return false;
    }
    
    /**
     * Find FFmpeg executable (OLD - fallback)
     */
    private static function findFFmpeg_OLD() {
        $possible_paths = [
            '/usr/bin/ffmpeg',
            '/usr/local/bin/ffmpeg',
            'ffmpeg', // In PATH
            'C:\\ffmpeg\\bin\\ffmpeg.exe', // Windows
        ];
        
        foreach ($possible_paths as $path) {
            if (is_executable($path) || (strpos($path, 'ffmpeg') !== false && self::commandExists($path))) {
                return $path;
            }
        }
        
        return false;
    }
    
    /**
     * Check if command exists
     */
    private static function commandExists($command) {
        $return = shell_exec(sprintf("which %s", escapeshellarg($command)));
        return !empty($return);
    }
    
    /**
     * Get optimized image URLs for API response
     */
    public static function getImageUrls($base_path, $base_filename, $file_extension) {
        $base_url = 'https://dijitalsalon.cagapps.app/';
        
        return [
            'original' => $base_url . $base_path,
            'thumbnail' => $base_url . dirname($base_path) . '/' . $base_filename . '_thumb.' . $file_extension,
            'preview' => $base_url . dirname($base_path) . '/' . $base_filename . '_preview.' . $file_extension,
        ];
    }
    
    /**
     * Clean up old files (for maintenance)
     */
    public static function cleanupOldFiles($directory, $days_old = 30) {
        if (!is_dir($directory)) {
            return false;
        }
        
        $files = glob($directory . '*');
        $cutoff_time = time() - ($days_old * 24 * 60 * 60);
        
        foreach ($files as $file) {
            if (is_file($file) && filemtime($file) < $cutoff_time) {
                unlink($file);
            }
        }
        
        return true;
    }
    
    /**
     * Validate image file
     */
    public static function validateImage($file_path) {
        $image_info = getimagesize($file_path);
        if (!$image_info) {
            return false;
        }
        
        $allowed_types = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        return in_array($image_info[2], $allowed_types);
    }
    
    /**
     * Validate image file with type checking
     */
    public static function validateImageType($file_path, $file_extension) {
        if (!ImageConfig::isAllowedImageType($file_extension)) {
            return false;
        }
        
        return self::validateImage($file_path);
    }
    
    /**
     * Get file size in human readable format
     */
    public static function formatFileSize($bytes) {
        return ImageConfig::formatFileSize($bytes);
    }
}
?>
