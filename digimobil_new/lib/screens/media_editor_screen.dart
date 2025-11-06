import 'dart:io';
import 'dart:ui' as ui;
import 'dart:typed_data';
import 'package:flutter/material.dart';
import 'package:flutter/rendering.dart';
import 'package:image_cropper/image_cropper.dart';
import 'package:image/image.dart' as img;
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/theme_colors.dart';
import 'package:digimobil_new/utils/error_handler.dart';
import 'package:digimobil_new/utils/app_transitions.dart';
import 'package:path_provider/path_provider.dart';

/// Instagram tarzı düzenleme ekranı
/// ✅ Filtre, Metin, Bindirme, Crop özellikleri
/// 
/// Bu ekran, CameraModal veya MediaSelectModal'dan gelen medya dosyalarını
/// düzenlemek için kullanılır. Kullanıcılar burada:
/// - Fotoğrafı kırpabilir (Crop)
/// - Filtre ekleyebilir
/// - Metin overlay ekleyebilir
/// - Emoji overlay ekleyebilir
/// 
/// Düzenlenmiş medya dosyası geri döndürülür ve ShareModal'a gönderilir.
class MediaEditorScreen extends StatefulWidget {
  /// Düzenlenecek medya dosyası
  final File mediaFile;
  
  /// Paylaşım türü: 'post' veya 'story'
  final String? shareType;
  
  /// CameraModal'dan gelen metin (opsiyonel)
  /// Eğer metin modu aktifse, bu metin otomatik olarak overlay olarak eklenir
  final String? initialText;

  const MediaEditorScreen({
    super.key,
    required this.mediaFile,
    this.shareType,
    this.initialText,
  });

  @override
  State<MediaEditorScreen> createState() => _MediaEditorScreenState();
}

class _MediaEditorScreenState extends State<MediaEditorScreen> {
  String _selectedFilter = 'normal';
  List<TextOverlay> _textOverlays = [];
  String? _selectedOverlay;
  File? _editedFile;
  final GlobalKey _previewKey = GlobalKey();
  
  final List<Map<String, String>> _filters = [
    {'name': 'Normal', 'value': 'normal'},
    {'name': 'Vintage', 'value': 'vintage'},
    {'name': 'Black & White', 'value': 'bw'},
    {'name': 'Warm', 'value': 'warm'},
    {'name': 'Cool', 'value': 'cool'},
    {'name': 'Dramatic', 'value': 'dramatic'},
  ];

  final List<Map<String, String>> _overlays = [
    {'name': 'Kalp', 'icon': '❤️', 'value': 'heart'},
    {'name': 'Yıldız', 'icon': '⭐', 'value': 'star'},
    {'name': 'Gülen Yüz', 'icon': '😊', 'value': 'smile'},
    {'name': 'Gökyüzü', 'icon': '☁️', 'value': 'cloud'},
    {'name': 'Ateş', 'icon': '🔥', 'value': 'fire'},
    {'name': 'Yıldırım', 'icon': '⚡', 'value': 'lightning'},
  ];

  @override
  void initState() {
    super.initState();
    _editedFile = widget.mediaFile;
    
    // ✅ Eğer initialText varsa otomatik ekle
    if (widget.initialText != null && widget.initialText!.isNotEmpty) {
      _textOverlays.add(TextOverlay(
        text: widget.initialText!,
        color: Colors.white,
        fontSize: 32,
        position: const Offset(50, 50),
      ));
    }
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
          'Düzenle',
          style: TextStyle(color: Colors.white),
        ),
        centerTitle: true,
        actions: [
          IconButton(
            icon: const Icon(Icons.check, color: Colors.white),
            onPressed: () async {
              // ✅ Düzenlenmiş medyayı kaydet ve geri döndür
              final savedFile = await _saveEditedImage();
              if (mounted && savedFile != null) {
                Navigator.of(context).pop(savedFile); // ✅ Sadece File döndür
              }
            },
          ),
        ],
      ),
      body: Column(
        children: [
          // ✅ Medya önizleme alanı (metin ve overlay'ler ile)
          Expanded(
            child: Container(
              width: double.infinity,
              color: Colors.black,
              child: RepaintBoundary(
                key: _previewKey,
                child: Stack(
                  fit: StackFit.expand,
                  children: [
                    // ✅ Filtreli resim
                    ColorFiltered(
                      colorFilter: _getColorFilter(_selectedFilter),
                      child: Image.file(
                        _editedFile!,
                        fit: BoxFit.contain,
                      ),
                    ),
                    // ✅ Metin overlay'leri
                    ..._textOverlays.map((textOverlay) => _buildTextOverlay(textOverlay)),
                    // ✅ Emoji overlay
                    if (_selectedOverlay != null)
                      _buildEmojiOverlay(_selectedOverlay!),
                  ],
                ),
              ),
            ),
          ),

          // ✅ Alt araç çubuğu
          Container(
            height: 100,
            color: Colors.black,
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceAround,
              children: [
                _buildToolButton(
                  icon: Icons.crop,
                  label: 'Kes',
                  onTap: () => _cropImage(),
                ),
                _buildToolButton(
                  icon: Icons.tune,
                  label: 'Filtre',
                  onTap: () => _showFilterSelector(),
                ),
                _buildToolButton(
                  icon: Icons.text_fields,
                  label: 'Metin',
                  onTap: () => _showTextEditor(),
                ),
                _buildToolButton(
                  icon: Icons.layers,
                  label: 'Bindirme',
                  onTap: () => _showOverlaySelector(),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildToolButton({
    required IconData icon,
    required String label,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Column(
        mainAxisAlignment: MainAxisAlignment.center,
        children: [
          Container(
            width: 50,
            height: 50,
            decoration: BoxDecoration(
              color: Colors.white.withOpacity(0.1),
              borderRadius: BorderRadius.circular(25),
            ),
            child: Icon(icon, color: Colors.white, size: 24),
          ),
          const SizedBox(height: 4),
          Text(
            label,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 12,
            ),
          ),
        ],
      ),
    );
  }

  /// Renk filtresi döndürür
  /// 
  /// [filter] Filtre tipi (normal, vintage, bw, warm, cool, dramatic)
  /// Her filtre için farklı ColorFilter matrix'i döndürür
  ColorFilter _getColorFilter(String filter) {
    switch (filter) {
      case 'vintage':
        return const ColorFilter.matrix([
          0.9, 0.5, 0.1, 0, 0,
          0.3, 0.8, 0.1, 0, 0,
          0.2, 0.3, 0.5, 0, 0,
          0, 0, 0, 1, 0,
        ]);
      case 'bw':
        return const ColorFilter.matrix([
          0.2126, 0.7152, 0.0722, 0, 0,
          0.2126, 0.7152, 0.0722, 0, 0,
          0.2126, 0.7152, 0.0722, 0, 0,
          0, 0, 0, 1, 0,
        ]);
      case 'warm':
        return const ColorFilter.matrix([
          1.2, 0, 0, 0, 0,
          0.1, 1.1, 0, 0, 0,
          0, 0, 0.9, 0, 0,
          0, 0, 0, 1, 0,
        ]);
      case 'cool':
        return const ColorFilter.matrix([
          0.9, 0, 0, 0, 0,
          0, 1.1, 0, 0, 0,
          0.1, 0.1, 1.2, 0, 0,
          0, 0, 0, 1, 0,
        ]);
      case 'dramatic':
        return const ColorFilter.matrix([
          1.2, 0.1, -0.1, 0, 0,
          -0.1, 1.1, 0.1, 0, 0,
          0.1, -0.1, 1.2, 0, 0,
          0, 0, 0, 1, 0,
        ]);
      default:
        return const ColorFilter.matrix([
          1, 0, 0, 0, 0,
          0, 1, 0, 0, 0,
          0, 0, 1, 0, 0,
          0, 0, 0, 1, 0,
        ]);
    }
  }

  Widget _buildTextOverlay(TextOverlay textOverlay) {
    return Positioned(
      left: textOverlay.position.dx,
      top: textOverlay.position.dy,
      child: GestureDetector(
        onPanUpdate: (details) {
          setState(() {
            textOverlay.position += details.delta;
          });
        },
        child: Text(
          textOverlay.text,
          style: TextStyle(
            fontSize: textOverlay.fontSize,
            color: textOverlay.color,
            fontWeight: FontWeight.bold,
            shadows: [
              Shadow(
                offset: const Offset(1, 1),
                blurRadius: 3,
                color: Colors.black.withOpacity(0.8),
              ),
            ],
          ),
        ),
      ),
    );
  }

  /// Emoji overlay widget'ı oluşturur
  /// 
  /// [overlayType] Overlay tipi (heart, star, smile, vb.)
  Widget _buildEmojiOverlay(String overlayType) {
    final overlay = _overlays.firstWhere((o) => o['value'] == overlayType);
    return Center(
      child: Text(
        overlay['icon']!,
        style: const TextStyle(fontSize: 80),
      ),
    );
  }

  /// Fotoğrafı kırpar
  /// 
  /// ImageCropper kullanarak fotoğrafı kırpar ve düzenlenmiş dosyayı kaydeder
  Future<void> _cropImage() async {
    try {
      final croppedFile = await ImageCropper().cropImage(
        sourcePath: _editedFile!.path,
        aspectRatio: const CropAspectRatio(ratioX: 1, ratioY: 1),
        uiSettings: [
          AndroidUiSettings(
            toolbarTitle: 'Fotoğrafı Kes',
            toolbarColor: AppColors.primary,
            toolbarWidgetColor: Colors.white,
            initAspectRatio: CropAspectRatioPreset.square,
            lockAspectRatio: false,
          ),
          IOSUiSettings(
            title: 'Fotoğrafı Kes',
            cancelButtonTitle: 'İptal',
            doneButtonTitle: 'Tamam',
          ),
        ],
      );

      if (croppedFile != null) {
        setState(() {
          _editedFile = File(croppedFile.path);
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

  /// Filtre seçici modal'ı gösterir
  void _showFilterSelector() {
    AppModalBottomSheet.show(
      context: context,
      backgroundColor: Colors.transparent,
      borderRadius: 20,
      child: Container(
        height: 200,
        decoration: BoxDecoration(
          color: Colors.black,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Filtre Seç',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            Expanded(
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: _filters.length,
                itemBuilder: (context, index) {
                  final filter = _filters[index];
                  final isSelected = _selectedFilter == filter['value'];
                  return GestureDetector(
                    onTap: () {
                      setState(() {
                        _selectedFilter = filter['value']!;
                      });
                      Navigator.of(context).pop();
                    },
                    child: Container(
                      margin: const EdgeInsets.only(right: 12),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 20,
                        vertical: 12,
                      ),
                      decoration: BoxDecoration(
                        color: isSelected
                            ? AppColors.primary
                            : Colors.white.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(20),
                      ),
                      child: Text(
                        filter['name']!,
                        style: TextStyle(
                          color: isSelected ? Colors.white : Colors.white70,
                          fontWeight:
                              isSelected ? FontWeight.bold : FontWeight.normal,
                        ),
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  /// Metin editor modal'ı gösterir
  void _showTextEditor() {
    final textController = TextEditingController();
    Color selectedColor = Colors.white;
    double fontSize = 24.0;

    AppModalBottomSheet.show(
      context: context,
      backgroundColor: Colors.transparent,
      borderRadius: 20,
      isScrollControlled: true,
      child: StatefulBuilder(
        builder: (context, setModalState) => Container(
          padding: EdgeInsets.only(
            bottom: MediaQuery.of(context).viewInsets.bottom,
          ),
          decoration: const BoxDecoration(
            color: Colors.black,
            borderRadius: BorderRadius.vertical(top: Radius.circular(20)),
          ),
          child: Padding(
            padding: const EdgeInsets.all(20),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                const Text(
                  'Metin Ekle',
                  style: TextStyle(
                    color: Colors.white,
                    fontSize: 18,
                    fontWeight: FontWeight.bold,
                  ),
                ),
                const SizedBox(height: 16),
                TextField(
                  controller: textController,
                  style: const TextStyle(color: Colors.white),
                  decoration: InputDecoration(
                    hintText: 'Metin girin...',
                    hintStyle: TextStyle(color: Colors.white.withOpacity(0.5)),
                    border: OutlineInputBorder(
                      borderRadius: BorderRadius.circular(12),
                    ),
                    filled: true,
                    fillColor: Colors.white.withOpacity(0.1),
                  ),
                  maxLength: 100,
                ),
                const SizedBox(height: 16),
                // ✅ Renk seçici
                Row(
                  children: [
                    const Text(
                      'Renk:',
                      style: TextStyle(color: Colors.white70),
                    ),
                    const SizedBox(width: 8),
                    ...[
                      Colors.white,
                      Colors.black,
                      Colors.red,
                      Colors.blue,
                      Colors.green,
                      Colors.yellow,
                      Colors.purple,
                    ].map((color) => GestureDetector(
                      onTap: () {
                        setModalState(() {
                          selectedColor = color;
                        });
                      },
                      child: Container(
                        margin: const EdgeInsets.only(right: 8),
                        width: 30,
                        height: 30,
                        decoration: BoxDecoration(
                          color: color,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: selectedColor == color
                                ? Colors.white
                                : Colors.transparent,
                            width: 2,
                          ),
                        ),
                      ),
                    )),
                  ],
                ),
                const SizedBox(height: 16),
                // ✅ Font boyutu
                Row(
                  children: [
                    const Text(
                      'Boyut:',
                      style: TextStyle(color: Colors.white70),
                    ),
                    Expanded(
                      child: Slider(
                        value: fontSize,
                        min: 16,
                        max: 48,
                        divisions: 8,
                        label: fontSize.toInt().toString(),
                        onChanged: (value) {
                          setModalState(() {
                            fontSize = value;
                          });
                        },
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                Row(
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    TextButton(
                      onPressed: () => Navigator.of(context).pop(),
                      child: const Text(
                        'İptal',
                        style: TextStyle(color: Colors.white70),
                      ),
                    ),
                    const SizedBox(width: 8),
                    ElevatedButton(
                      onPressed: () {
                        if (textController.text.trim().isNotEmpty) {
                          setState(() {
                            _textOverlays.add(TextOverlay(
                              text: textController.text.trim(),
                              color: selectedColor,
                              fontSize: fontSize,
                              position: const Offset(50, 50),
                            ));
                          });
                          Navigator.of(context).pop();
                        }
                      },
                      style: ElevatedButton.styleFrom(
                        backgroundColor: AppColors.primary,
                      ),
                      child: const Text('Ekle'),
                    ),
                  ],
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }

  void _showOverlaySelector() {
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.transparent,
      builder: (context) => Container(
        height: 200,
        decoration: BoxDecoration(
          color: Colors.black,
          borderRadius: const BorderRadius.vertical(top: Radius.circular(20)),
        ),
        child: Column(
          children: [
            const Padding(
              padding: EdgeInsets.all(16),
              child: Text(
                'Bindirme Seç',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 18,
                  fontWeight: FontWeight.bold,
                ),
              ),
            ),
            Expanded(
              child: ListView.builder(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.symmetric(horizontal: 16),
                itemCount: _overlays.length + 1, // +1 for remove option
                itemBuilder: (context, index) {
                  if (index == 0) {
                    return GestureDetector(
                      onTap: () {
                        setState(() {
                          _selectedOverlay = null;
                        });
                        Navigator.of(context).pop();
                      },
                      child: Container(
                        margin: const EdgeInsets.only(right: 12),
                        width: 80,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.1),
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: const Column(
                          mainAxisAlignment: MainAxisAlignment.center,
                          children: [
                            Icon(Icons.close, color: Colors.white, size: 32),
                            SizedBox(height: 8),
                            Text(
                              'Kaldır',
                              style: TextStyle(color: Colors.white70, fontSize: 12),
                            ),
                          ],
                        ),
                      ),
                    );
                  }

                  final overlay = _overlays[index - 1];
                  final isSelected = _selectedOverlay == overlay['value'];
                  return GestureDetector(
                    onTap: () {
                      setState(() {
                        _selectedOverlay = overlay['value'];
                      });
                      Navigator.of(context).pop();
                    },
                    child: Container(
                      margin: const EdgeInsets.only(right: 12),
                      width: 80,
                      decoration: BoxDecoration(
                        color: isSelected
                            ? AppColors.primary
                            : Colors.white.withOpacity(0.1),
                        borderRadius: BorderRadius.circular(12),
                      ),
                      child: Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Text(
                            overlay['icon']!,
                            style: const TextStyle(fontSize: 32),
                          ),
                          const SizedBox(height: 8),
                          Text(
                            overlay['name']!,
                            style: TextStyle(
                              color: isSelected ? Colors.white : Colors.white70,
                              fontSize: 12,
                              fontWeight:
                                  isSelected ? FontWeight.bold : FontWeight.normal,
                            ),
                          ),
                        ],
                      ),
                    ),
                  );
                },
              ),
            ),
          ],
        ),
      ),
    );
  }

  Future<File?> _saveEditedImage() async {
    try {
      // ✅ Preview'ı widget olarak render et ve resme çevir
      final RenderRepaintBoundary boundary =
          _previewKey.currentContext!.findRenderObject() as RenderRepaintBoundary;
      final ui.Image image = await boundary.toImage(pixelRatio: 2.0);
      final ByteData? byteData =
          await image.toByteData(format: ui.ImageByteFormat.png);
      final Uint8List pngBytes = byteData!.buffer.asUint8List();

      // ✅ Geçici dosyaya kaydet
      final tempDir = await getTemporaryDirectory();
      final file = File('${tempDir.path}/edited_${DateTime.now().millisecondsSinceEpoch}.png');
      await file.writeAsBytes(pngBytes);

      return file;
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Kaydetme hatası: $e'),
            backgroundColor: Colors.red,
          ),
        );
      }
      return null;
    }
  }
}

class TextOverlay {
  String text;
  Color color;
  double fontSize;
  Offset position;

  TextOverlay({
    required this.text,
    required this.color,
    required this.fontSize,
    required this.position,
  });

  Map<String, dynamic> toMap() {
    return {
      'text': text,
      'color': color.value,
      'fontSize': fontSize,
      'position': {'x': position.dx, 'y': position.dy},
    };
  }
}
