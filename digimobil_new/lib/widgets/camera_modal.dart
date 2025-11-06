import 'package:flutter/material.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/app_transitions.dart';
import 'package:digimobil_new/utils/error_handler.dart';
import 'package:camera/camera.dart';
import 'package:image_picker/image_picker.dart';
import 'package:digimobil_new/widgets/media_select_modal.dart';
import 'package:photo_manager/photo_manager.dart';
import 'dart:io';
import 'dart:async';
import 'dart:ui' as ui;
import 'package:flutter/services.dart';

/// Instagram benzeri Kamera Ekranı
/// ✅ Orta kısımda büyük kamera önizlemesi
/// ✅ Ortada büyük dairesel çekim butonu
/// ✅ Sol üstte "X" (kapat), sağ üstte "Galeri" butonu
class CameraModal extends StatefulWidget {
  final Function(File file) onMediaCaptured;
  final String? shareType; // 'post', 'story'
  final Function(String?)? onShareTypeChanged; // ✅ Paylaşım türü değiştiğinde callback

  const CameraModal({
    super.key,
    required this.onMediaCaptured,
    this.shareType,
    this.onShareTypeChanged, // ✅ Paylaşım türü callback
  });

  static Future<void> show(
    BuildContext context, {
    required Function(File file) onMediaCaptured,
    String? shareType,
    Function(String?)? onShareTypeChanged, // ✅ Paylaşım türü callback
  }) {
    return Navigator.of(context).push(
      AppPageRoute(
        page: CameraModal(
          onMediaCaptured: onMediaCaptured,
          shareType: shareType,
          onShareTypeChanged: onShareTypeChanged, // ✅ Paylaşım türü callback
        ),
        fullscreenDialog: true,
      ),
    );
  }

  @override
  State<CameraModal> createState() => _CameraModalState();
}

class _CameraModalState extends State<CameraModal> {
  CameraController? _controller;
  List<CameraDescription>? _cameras;
  bool _isInitialized = false;
  bool _isRecording = false;
  bool _isStopping = false; // ✅ Durdurma işlemi devam ediyor mu?
  bool _isPhotoMode = true;
  Timer? _longPressTimer; // ✅ Basılı tutma timer'ı
  bool _isLongPress = false; // ✅ Basılı tutma aktif mi?
  // ✅ Mod butonları kaldırıldı - sadece normal mod aktif
  FlashMode _flashMode = FlashMode.auto;
  bool _isFrontCamera = false;
  XFile? _capturedFile;
  final ImagePicker _picker = ImagePicker();
  String? _selectedShareType; // ✅ Seçilen paylaşım türü
  File? _lastMediaPreview; // ✅ Son medya önizlemesi
  Timer? _videoRecordingTimer; // ✅ Video kayıt timer'ı
  int _recordingSeconds = 0; // ✅ Kayıt süresi (saniye)
  static const int _maxRecordingDuration = 60; // ✅ Maksimum kayıt süresi (1 dakika)

  @override
  void initState() {
    super.initState();
    _selectedShareType = widget.shareType; // ✅ Başlangıç değeri
    _loadLastMediaPreview(); // ✅ Son medya önizlemesini yükle
    _initializeCamera();
  }

  Future<void> _loadLastMediaPreview() async {
    try {
      // ✅ Foto izni kontrol et
      final status = await PhotoManager.requestPermissionExtend();
      if (!status.isAuth) {
        debugPrint('⚠️ Photo permission not granted');
        return;
      }

      // ✅ Son medyayı al (fotoğraf veya video)
      final List<AssetPathEntity> albums = await PhotoManager.getAssetPathList(
        type: RequestType.common,
        hasAll: true,
      );

      if (albums.isEmpty) {
        debugPrint('⚠️ Foto albümü bulunamadı');
        return;
      }

      // ✅ Null safety kontrolü eklendi
      final firstAlbum = albums.first;
      if (firstAlbum == null) {
        debugPrint('⚠️ İlk albüm null');
        return;
      }

      final recentAssets = await firstAlbum.getAssetListRange(
        start: 0,
        end: 1,
      );

      if (recentAssets.isEmpty) {
        debugPrint('⚠️ Son medya bulunamadı');
        return;
      }

      // ✅ Null safety kontrolü eklendi
      final asset = recentAssets.first;
      if (asset == null) {
        debugPrint('⚠️ İlk asset null');
        return;
      }

      // ✅ Sadece fotoğraf olanları al
      if (asset.type == AssetType.image) {
        final file = await asset.file;
        if (file != null && mounted) {
          setState(() {
            _lastMediaPreview = file;
          });
        }
      }
    } catch (e) {
      debugPrint('❌ Son medya önizlemesi yüklenemedi: $e');
      // ✅ Kullanıcıya bilgi verilmeye gerek yok, bu opsiyonel bir özellik
      // ✅ Sadece debug log'da göster
      if (mounted) {
        // Sessizce devam et, önizleme göstermez
      }
    }
  }

  Future<void> _initializeCamera() async {
    try {
      _cameras = await availableCameras();
      if (_cameras == null || _cameras!.isEmpty) {
        if (mounted) {
          ErrorHandler.showError(
            context,
            'Kamera bulunamadı',
          );
        }
        return;
      }

      _controller = CameraController(
        _cameras![_isFrontCamera ? 1 : 0],
        ResolutionPreset.high,
        enableAudio: true,
      );

      await _controller!.initialize();
      await _controller!.setFlashMode(_flashMode);

      if (mounted) {
        setState(() {
          _isInitialized = true;
        });
      }
    } catch (e) {
      if (mounted) {
        ErrorHandler.showError(
          context,
          ErrorHandler.formatError(e),
        );
      }
    }
  }

  @override
  void dispose() {
    _controller?.dispose();
    _videoRecordingTimer?.cancel();
    _longPressTimer?.cancel();
    super.dispose();
  }

  Future<void> _capturePhoto() async {
    if (_controller == null || !_controller!.value.isInitialized) return;

    try {
      // ✅ Haptic feedback ekle
      HapticFeedback.mediumImpact();
      
      // ✅ Loading state göster (kısa süre)
      setState(() {
        // Fotoğraf çekilirken minimal feedback
      });
      
      final XFile photo = await _controller!.takePicture();
      final file = File(photo.path);
      
      // ✅ Paylaşım türünü kontrol et
      final shareType = _selectedShareType ?? widget.shareType ?? 'post';
      
      // ✅ Başarılı haptic feedback
      HapticFeedback.selectionClick();
      
      // ✅ Normal mod - metin ve boomerang bilgisi yok
      widget.onMediaCaptured(file);
      if (mounted) {
        Navigator.of(context).pop();
      }
    } catch (e) {
      // ✅ Hata haptic feedback
      HapticFeedback.heavyImpact();
      if (mounted) {
        ErrorHandler.showError(
          context,
          ErrorHandler.formatError(e),
        );
      }
    }
  }

  Future<void> _startVideoRecording() async {
    if (_controller == null || !_controller!.value.isInitialized) return;

    try {
      // ✅ Haptic feedback ekle - video kaydı başladığında
      HapticFeedback.mediumImpact();
      
      // ✅ Gecikmeyi önlemek için await kullan
      await _controller!.startVideoRecording();
      
      // ✅ Timer başlat
      _recordingSeconds = 0;
      _videoRecordingTimer = Timer.periodic(const Duration(seconds: 1), (timer) {
        if (mounted) {
          setState(() {
            _recordingSeconds = timer.tick;
          });
          
          // ✅ 1 dakika dolduğunda otomatik durdur
          if (_recordingSeconds >= _maxRecordingDuration) {
            timer.cancel();
            _stopVideoRecording();
          }
        } else {
          timer.cancel();
        }
      });
      
      setState(() {
        _isRecording = true;
        _isStopping = false; // ✅ Kayıt başladığında durdurma flag'ini sıfırla
      });
      
      debugPrint('✅ Video kaydı başlatıldı');
    } catch (e) {
      // ✅ Hata haptic feedback
      HapticFeedback.heavyImpact();
      debugPrint('❌ Video kaydı başlatılamadı: $e');
      if (mounted) {
        ErrorHandler.showError(
          context,
          ErrorHandler.formatError(e),
        );
      }
    }
  }

  Future<void> _handleLongPressStart() async {
    // ✅ Basılı tutma başladı: Video kaydı başlat
    debugPrint('🎬 Basılı tutma başladı, video kaydı başlatılıyor...');
    
    if (_isPhotoMode) {
      // Foto modunda basılı tutulursa video moduna geç
      setState(() {
        _isPhotoMode = false;
      });
    }
    // ✅ Hemen video kaydı başlat
    await _startVideoRecording();
  }

  Future<void> _handleLongPressEnd() async {
    // ✅ Basılı tutma bitti: Video kaydı durdur
    debugPrint('🛑 Basılı tutma bitti, video kaydı durduruluyor...');
    debugPrint('🛑 _isRecording: $_isRecording, _isStopping: $_isStopping');
    
    if (_isRecording && !_isStopping) {
      await _stopVideoRecording();
    } else {
      debugPrint('⚠️ Video kaydı durdurulamadı: _isRecording=$_isRecording, _isStopping=$_isStopping');
    }
  }

  Future<void> _stopVideoRecording() async {
    // ✅ Zaten durduruluyorsa veya kayıt yoksa tekrar çağırma
    if (_isStopping || !_isRecording || _controller == null || !_controller!.value.isInitialized) {
      debugPrint('⚠️ _stopVideoRecording atlandı: _isStopping=$_isStopping, _isRecording=$_isRecording');
      return;
    }

    try {
      // ✅ Durdurma işlemi başladı
      _isStopping = true;
      debugPrint('🛑 Video kaydı durduruluyor...');
      
      // ✅ Timer'ı durdur
      _videoRecordingTimer?.cancel();
      _videoRecordingTimer = null;
      
      // ✅ State'i güncelle (kayıt durduruluyor)
      if (mounted) {
        setState(() {
          _isRecording = false;
        });
      }
      
      final XFile video = await _controller!.stopVideoRecording();
      final file = File(video.path);
      
      debugPrint('✅ Video kaydı durduruldu: ${file.path}');
      
      // ✅ Paylaşım türünü kontrol et
      final shareType = _selectedShareType ?? widget.shareType ?? 'post';
      
      // ✅ Normal mod - metin ve boomerang bilgisi yok
      widget.onMediaCaptured(file);
      
      if (mounted) {
        setState(() {
          _recordingSeconds = 0;
          _isStopping = false;
        });
        Navigator.of(context).pop();
      }
    } catch (e) {
      debugPrint('❌ Video kaydı durdurulamadı: $e');
      _videoRecordingTimer?.cancel();
      _videoRecordingTimer = null;
      if (mounted) {
        setState(() {
          _isRecording = false;
          _recordingSeconds = 0;
          _isStopping = false;
        });
        ErrorHandler.showError(
          context,
          ErrorHandler.formatError(e),
        );
      }
    }
  }

  Future<void> _switchCamera() async {
    if (_cameras == null || _cameras!.length < 2) return;

    setState(() {
      _isFrontCamera = !_isFrontCamera;
      _isInitialized = false;
    });

    await _controller?.dispose();
    await _initializeCamera();
  }

  Future<void> _toggleFlash() async {
    setState(() {
      if (_flashMode == FlashMode.auto) {
        _flashMode = FlashMode.always;
      } else if (_flashMode == FlashMode.always) {
        _flashMode = FlashMode.off;
      } else {
        _flashMode = FlashMode.auto;
      }
    });

    await _controller?.setFlashMode(_flashMode);
  }

  Future<void> _openGallery() async {
    File? selectedFile;
    await MediaSelectModal.show(
      context,
      onMediaSelected: (file) {
        selectedFile = file;
      },
      shareType: _selectedShareType ?? widget.shareType, // ✅ Seçilen paylaşım türünü kullan
    );
    if (selectedFile != null && mounted) {
      // ✅ Seçilen medyayı önizleme olarak güncelle
      setState(() {
        _lastMediaPreview = selectedFile;
      });
      widget.onMediaCaptured(selectedFile!);
      Navigator.of(context).pop();
    }
  }


  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      body: SafeArea(
        child: Stack(
          children: [
            // ✅ Kamera önizlemesi
            if (_isInitialized && _controller != null)
              Positioned.fill(
                child: Stack(
                  children: [
                    CameraPreview(_controller!),
                  ],
                ),
              )
            else
              const Center(
                child: CircularProgressIndicator(color: Colors.white),
              ),

            // ✅ Üst kontroller
            Positioned(
              top: 16,
              left: 16,
              right: 16,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  // ✅ Kapat butonu
                  IconButton(
                    icon: const Icon(Icons.close, color: Colors.white),
                    onPressed: () => Navigator.of(context).pop(),
                    style: IconButton.styleFrom(
                      backgroundColor: Colors.black.withOpacity(0.3),
                    ),
                  ),

                  // ✅ Flaş butonu
                  IconButton(
                    icon: Icon(
                      _flashMode == FlashMode.always
                          ? Icons.flash_on
                          : _flashMode == FlashMode.off
                              ? Icons.flash_off
                              : Icons.flash_auto,
                      color: Colors.white,
                    ),
                    onPressed: _toggleFlash,
                    style: IconButton.styleFrom(
                      backgroundColor: Colors.black.withOpacity(0.3),
                    ),
                  ),

                  // ✅ Galeri butonu
                  IconButton(
                    icon: const Icon(Icons.photo_library, color: Colors.white),
                    onPressed: _openGallery,
                    style: IconButton.styleFrom(
                      backgroundColor: Colors.black.withOpacity(0.3),
                    ),
                  ),
                ],
              ),
            ),

            // ✅ Mod butonları kaldırıldı - sadece normal mod aktif

            // ✅ Alt kontroller
            Positioned(
              bottom: 0,
              left: 0,
              right: 0,
              child: Container(
                padding: const EdgeInsets.symmetric(vertical: 24),
                decoration: BoxDecoration(
                  gradient: LinearGradient(
                    begin: Alignment.topCenter,
                    end: Alignment.bottomCenter,
                    colors: [
                      Colors.transparent,
                      Colors.black.withOpacity(0.8),
                    ],
                  ),
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    // ✅ Paylaşım türü seçimi (tıklanabilir) - REELS kaldırıldı
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        _buildShareTypeButton('GÖNDERİ', 'post'),
                        const SizedBox(width: 24),
                        _buildShareTypeButton('HİKAYE', 'story'),
                      ],
                    ),
                    const SizedBox(height: 24),
                    // ✅ Çekim butonu ve galeri önizlemeleri
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        // ✅ Galeri önizleme (sol) - Tıklanabilir, son medya önizlemesi
                        GestureDetector(
                          onTap: _openGallery,
                          child: Container(
                            width: 50,
                            height: 50,
                            decoration: BoxDecoration(
                              borderRadius: BorderRadius.circular(25),
                              border: Border.all(color: Colors.white, width: 2),
                            ),
                            child: ClipRRect(
                              borderRadius: BorderRadius.circular(23),
                              child: _lastMediaPreview != null
                                  ? Image.file(
                                      _lastMediaPreview!,
                                      fit: BoxFit.cover,
                                      width: 50,
                                      height: 50,
                                      errorBuilder: (context, error, stackTrace) {
                                        return Container(
                                          color: Colors.grey[800],
                                          child: const Icon(
                                            Icons.photo,
                                            color: Colors.white,
                                            size: 24,
                                          ),
                                        );
                                      },
                                    )
                                  : Container(
                                      color: Colors.grey[800],
                                      child: const Icon(
                                        Icons.photo,
                                        color: Colors.white,
                                        size: 24,
                                      ),
                                    ),
                            ),
                          ),
                        ),

                        const SizedBox(width: 24),

                        // ✅ Büyük çekim butonu
                        // Tek tıklama: Fotoğraf çek | Basılı tutma: Video kaydet
                        Listener(
                          onPointerDown: (_) {
                            // ✅ Basılı tutma başladı: Kısa delay ile video kaydı başlat
                            _longPressTimer?.cancel();
                            _isLongPress = false;
                            
                            // ✅ 200ms sonra video kaydı başlat (eğer hala basılıysa)
                            _longPressTimer = Timer(const Duration(milliseconds: 200), () {
                              if (!_isRecording && !_isStopping && mounted) {
                                _isLongPress = true;
                                _handleLongPressStart();
                              }
                            });
                          },
                          onPointerUp: (_) {
                            // ✅ Basılı tutma bitti
                            _longPressTimer?.cancel();
                            
                            if (_isLongPress && _isRecording && !_isStopping) {
                              // ✅ Video kaydı durdur
                              _handleLongPressEnd();
                            } else if (!_isLongPress && !_isRecording && !_isStopping) {
                              // ✅ Tek tıklama: Fotoğraf çek
                              _capturePhoto();
                            }
                            
                            _isLongPress = false;
                          },
                          onPointerCancel: (_) {
                            // ✅ Basılı tutma iptal edildi: Video kaydı durdur
                            _longPressTimer?.cancel();
                            if (_isRecording && !_isStopping) {
                              _handleLongPressEnd();
                            }
                            _isLongPress = false;
                          },
                          child: GestureDetector(
                            onTap: () {
                              // ✅ GestureDetector onTap sadece fotoğraf için yedek olarak
                              // Asıl kontrol Listener'da yapılıyor
                            },
                            child: Stack(
                              alignment: Alignment.center,
                              children: [
                                // ✅ Kırmızı dairesel çizgi (video kaydı sırasında)
                                if (_isRecording)
                                  SizedBox(
                                    width: 88,
                                    height: 88,
                                    child: CircularProgressIndicator(
                                      value: _recordingSeconds / _maxRecordingDuration,
                                      strokeWidth: 4,
                                      backgroundColor: Colors.white.withOpacity(0.3),
                                      valueColor: const AlwaysStoppedAnimation<Color>(Colors.red),
                                    ),
                                  ),
                                // ✅ Çekim butonu
                                Container(
                                  width: 80,
                                  height: 80,
                                  decoration: BoxDecoration(
                                    shape: BoxShape.circle,
                                    border: Border.all(
                                      color: _isRecording ? Colors.red : Colors.white,
                                      width: 4,
                                    ),
                                    color: Colors.transparent,
                                  ),
                                  child: Container(
                                    margin: const EdgeInsets.all(4),
                                    decoration: BoxDecoration(
                                      shape: BoxShape.circle,
                                      color: _isRecording ? Colors.red.withOpacity(0.5) : Colors.white,
                                    ),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),

                        const SizedBox(width: 24),

                        // Kamera değiştir butonu
                        GestureDetector(
                          onTap: _switchCamera,
                          child: Container(
                            width: 50,
                            height: 50,
                            decoration: BoxDecoration(
                              shape: BoxShape.circle,
                              color: Colors.black.withOpacity(0.3),
                            ),
                            child: const Icon(
                              Icons.cameraswitch,
                              color: Colors.white,
                              size: 28,
                            ),
                          ),
                        ),
                      ],
                    ),
                  ],
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }


  Widget _buildShareTypeButton(String label, String shareType) {
    final isSelected = _selectedShareType == shareType;
    return GestureDetector(
      onTap: () {
        setState(() {
          _selectedShareType = shareType;
        });
        // ✅ Paylaşım türü değiştiğinde callback çağır
        widget.onShareTypeChanged?.call(shareType);
      },
      child: Text(
        label,
        style: TextStyle(
          color: isSelected ? Colors.white : Colors.white.withOpacity(0.5),
          fontSize: 14,
          fontWeight: isSelected ? FontWeight.w600 : FontWeight.normal,
          decoration: isSelected ? TextDecoration.underline : null,
        ),
      ),
    );
  }
}


