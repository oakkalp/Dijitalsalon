import 'package:flutter/material.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:camera/camera.dart';
import 'package:image_picker/image_picker.dart';
import 'dart:io';

class CameraScreenModal extends StatefulWidget {
  final bool isStoryMode;
  final Function(XFile)? onMediaCaptured;
  const CameraScreenModal({Key? key, this.isStoryMode = false, this.onMediaCaptured}) : super(key: key);
  @override
  State<CameraScreenModal> createState() => _CameraScreenModalState();
}
// State içindeki kullanımlar: _controller ve _capturedFile State alanında olmalı. capture/galeri seçildiğinde
// widget.onMediaCaptured?.call(file); çağrılacak ve Navigator.pop(context) ile ekrana geri dönülecek.

class _CameraScreenModalState extends State<CameraScreenModal> {
  // Kamera mantığı: CameraController seçimi, modlar
  bool isPhotoMode = true;
  bool isRecording = false;

  XFile? _capturedFile;
  CameraController? _controller;

  Future<void> _initCamera() async {
    final cameras = await availableCameras();
    if (cameras.isEmpty) return;
    _controller = CameraController(cameras.first, ResolutionPreset.high, enableAudio: true);
    await _controller!.initialize();
    if (!mounted) return;
    setState(() {});
  }

  @override
  void initState() {
    super.initState();
    _initCamera();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: AppColors.background,
      body: SafeArea(
        bottom: false,
        child: Stack(
          children: [
            // Kamera önizlemesi alanı:
            Positioned(
              left: 0, right: 0,
              top: 0, 
              height: MediaQuery.of(context).size.height * 0.73,
              child: Container(
                decoration: BoxDecoration(
                  color: AppColors.surface,
                ),
                child: _capturedFile == null 
                  ? (_controller?.value.isInitialized==true 
                      ? CameraPreview(_controller!) 
                      : Container(color: AppColors.surface,child: const Center(child:CircularProgressIndicator()))) 
                  : Image.file(File(_capturedFile!.path), fit:BoxFit.cover)
              ),
            ),
            // ÜSTTE İKONLAR - Flaş & kamera değiş
            Positioned(
              top: 18,
              left: 18,
              child: _buildCircleIcon(
                  icon: Icons.flash_on_outlined,
                  onTap: (){},
                  color: AppColors.textPrimary),
            ),
            Positioned(
              top: 18,
              right: 18,
              child: _buildCircleIcon(
                  icon: Icons.cameraswitch_outlined,
                  onTap: (){},
                  color: AppColors.textPrimary),
            ),
            // ALT ANA KONTROLLER
            Positioned(
              left: 0, right: 0,
              bottom: 0,
              child: Container(
                padding: const EdgeInsets.only(top: 22, bottom: 36),
                decoration: BoxDecoration(
                  color: AppColors.surface,
                  borderRadius: const BorderRadius.vertical(top: Radius.circular(24)),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.13),
                      blurRadius: 16,
                      offset: const Offset(0, -4),
                    ),
                  ],
                ),
                child: Column(
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    Row(
                      mainAxisAlignment: MainAxisAlignment.center,
                      crossAxisAlignment: CrossAxisAlignment.center,
                      children: [
                        // Sol: Galeri
                        _buildCircleIcon(
                          icon: Icons.photo_library_outlined,
                          onTap: () async { 
                            final picked = await ImagePicker().pickImage(source: ImageSource.gallery); 
                            if(picked!=null) setState(()=>_capturedFile=picked); 
                          },
                          color: AppColors.textPrimary,
                          size: 34,
                        ),
                        const SizedBox(width: 42),
                        // Çekim Butonu
                        _buildCaptureButton(context),
                        const SizedBox(width: 42),
                        // Sağ: Ayarlar
                        _buildCircleIcon(
                          icon: Icons.more_horiz,
                          onTap: (){},
                          color: AppColors.textSecondary,
                          size: 34,
                        ),
                      ],
                    ),
                    const SizedBox(height: 20),
                    // Mod seçici
                    Container(
                      margin: const EdgeInsets.symmetric(horizontal: 32),
                      padding: const EdgeInsets.symmetric(vertical: 7, horizontal: 7),
                      decoration: BoxDecoration(
                        color: AppColors.surfaceLight,
                        borderRadius: BorderRadius.circular(16),
                      ),
                      child: Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          _buildModeButton('Fotoğraf', isPhotoMode, onTap: ()=>setState(()=>isPhotoMode=true)),
                          _buildModeButton('Video', !isPhotoMode, onTap: ()=>setState(()=>isPhotoMode=false)),
                        ],
                      ),
                    ),
                  ],
                ),
              ),
            ),
            // -- ÇEKİM SONRASI MODAL veya PAYLAŞIM butonları --
            if (_capturedFile != null) Positioned(
              left: 0, right: 0, bottom: 8,
              child: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 20),
                child: Row(
                  children: [
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () {
                          setState(()=>_capturedFile=null);
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.surfaceLight,
                          foregroundColor: AppColors.textPrimary,
                        ),
                        child: const Text('Retake'),
                      ),
                    ),
                    const SizedBox(width: 12),
                    Expanded(
                      child: ElevatedButton(
                        onPressed: () {
                          final file = _capturedFile!;
                          widget.onMediaCaptured?.call(file);
                          Navigator.pop(context, file);
                        },
                        style: ElevatedButton.styleFrom(
                          backgroundColor: AppColors.primary,
                          foregroundColor: Colors.white,
                        ),
                        child: Text(widget.isStoryMode ? 'Share as Story' : 'Share as Media'),
                      ),
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

  Widget _buildCircleIcon({
    required IconData icon,
    required VoidCallback onTap,
    required Color color,
    double size = 30,
  }) {
    return Material(
      color: Colors.transparent,
      shape: const CircleBorder(),
      child: InkWell(
        customBorder: const CircleBorder(),
        onTap: onTap,
        child: Container(
          width: size + 15,
          height: size + 15,
          decoration: BoxDecoration(
            color: AppColors.surfaceLight.withOpacity(0.74),
            shape: BoxShape.circle,
            border: Border.all(color: AppColors.border, width: 1),
          ),
          child: Center(child: Icon(icon, size: size, color: color)),
        ),
      ),
    );
  }

  Widget _buildCaptureButton(BuildContext context) {
    return GestureDetector(
      onTapDown: (_) {
        // Animasyon başlat, video ise kayda başla
        setState(() { isRecording = !isPhotoMode; });
      },
      onTapUp: (_) {
        setState(() { isRecording = false; });
      },
      onTap: () async { 
        if(_controller!=null){ 
          if (isPhotoMode) {
            final file = await _controller!.takePicture(); 
            setState(()=>_capturedFile=file); 
          } else {
            // Basit video toggling (demo)
            if (!_controller!.value.isRecordingVideo) {
              await _controller!.startVideoRecording();
              setState(()=>isRecording=true);
            } else {
              final file = await _controller!.stopVideoRecording();
              setState((){ isRecording=false; _capturedFile=file; });
            }
          }
        } 
      },
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 230),
        width: isRecording ? 86 : 80,
        height: isRecording ? 86 : 80,
        decoration: BoxDecoration(
          shape: BoxShape.circle,
          gradient: AppColors.primaryGradient,
          boxShadow: [
            BoxShadow(
              color: AppColors.primary.withOpacity(0.19),
              blurRadius: 18,
              offset: const Offset(0, 3),
              spreadRadius: 2,
            ),
          ],
          border: Border.all(
            color: Colors.white.withOpacity(0.48),
            width: 3,
          ),
        ),
        child: Center(
          child: Container(
            width: isRecording ? 48 : 54,
            height: isRecording ? 48 : 54,
            decoration: const BoxDecoration(
              shape: BoxShape.circle,
              color: Colors.white,
            ),
            child: isRecording ?
              Padding(
                padding: const EdgeInsets.all(9.0),
                child: Container(
                  width: 24,
                  height: 24,
                  decoration: BoxDecoration(
                    shape: BoxShape.rectangle,
                    color: AppColors.primary,
                    borderRadius: BorderRadius.circular(6),
                  ),
                ),
              )
              : null,
          ),
        ),
      ),
    );
  }

  Widget _buildModeButton(String label, bool selected, {required VoidCallback onTap}) {
    return Expanded(
      child: GestureDetector(
        onTap: onTap,
        child: AnimatedContainer(
          duration: const Duration(milliseconds: 180),
          padding: const EdgeInsets.symmetric(vertical: 8),
          decoration: BoxDecoration(
            borderRadius: BorderRadius.circular(11),
            color: selected ? AppColors.primary.withOpacity(0.14) : Colors.transparent,
          ),
          alignment: Alignment.center,
          child: Text(
            label,
            style: TextStyle(
              color: selected
                  ? AppColors.primary
                  : AppColors.textTertiary,
              fontWeight: FontWeight.bold,
              fontSize: 16,
              letterSpacing: 0.2,
            ),
          ),
        ),
      ),
    );
  }
}
