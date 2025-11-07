import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/theme_colors.dart';
import 'package:digimobil_new/widgets/success_modal.dart';
import 'package:digimobil_new/widgets/error_modal.dart';

class QRCodeScannerScreen extends StatefulWidget {
  const QRCodeScannerScreen({super.key});

  @override
  State<QRCodeScannerScreen> createState() => _QRCodeScannerScreenState();
}

class _QRCodeScannerScreenState extends State<QRCodeScannerScreen> {
  MobileScannerController cameraController = MobileScannerController();
  bool isScanning = true;
  bool isTorchOn = false;
  bool isFrontCamera = false;
  final ApiService _apiService = ApiService();

  @override
  void dispose() {
    cameraController.dispose();
    super.dispose();
  }

  void _handleQRCode(BarcodeCapture capture) {
    if (!isScanning) return;
    
    final List<Barcode> barcodes = capture.barcodes;
    if (barcodes.isNotEmpty) {
      final String? qrCode = barcodes.first.rawValue;
      if (qrCode != null) {
        setState(() {
          isScanning = false;
        });
        _processQRCode(qrCode);
      }
    }
  }

  void _processQRCode(String qrCode) async {
    try {
      print('🔍 QR Kod işleniyor: $qrCode');
      // QR kodundan etkinlik ID'sini çıkar
      if (qrCode.startsWith('QR_')) {
        print('✅ Geçerli QR kod formatı: $qrCode');
        // ✅ Loading göster
        showDialog(
          context: context,
          barrierDismissible: false,
          builder: (context) => const Center(
            child: CircularProgressIndicator(color: AppColors.primary),
          ),
        );

        // Etkinliğe katıl
        Map<String, dynamic>? result;
        bool isAlreadyParticipant = false;
        
        try {
          print('📡 Join Event API çağrısı yapılıyor: $qrCode');
          result = await _apiService.joinEvent(qrCode);
          print('✅ Join Event API başarılı: ${result['event_id']} - ${result['event_title']}');
        } catch (e) {
          print('❌ Join Event API hatası: $e');
          // ✅ 409 hatası (zaten katılmış) durumunda bile event'i bulmalıyız
          if (e.toString().contains('already a participant') || e.toString().contains('409')) {
            isAlreadyParticipant = true;
            print('⚠️ Zaten katılımcısınız, event bulunuyor...');
          } else {
            // Loading'i kapat
            if (mounted) Navigator.pop(context);
            
            // ✅ Hata modalı göster
            if (mounted) {
              ErrorModal.show(
                context,
                title: 'Katılım Başarısız',
                message: e.toString(),
                icon: Icons.error_outline,
                iconColor: AppColors.error,
              );
            }
            
            // Tekrar taramaya izin ver
            setState(() {
              isScanning = true;
            });
            return;
          }
        }
        
        // Loading'i kapat
        if (mounted) Navigator.pop(context);
        
        // ✅ Join event response'undan event_id'yi al (ÖNCE - ana sayfada event'i bulmak için kullanacağız)
        int? eventIdFromResponse;
        if (result != null && result['event_id'] != null) {
          eventIdFromResponse = result['event_id'] as int;
          print('✅ Join Event Response\'dan event_id alındı: $eventIdFromResponse');
        }
        
        // ✅ ÖNEMLİ: EventProvider'daki event listesi QR scanner'da güncel olmayabilir
        // ✅ Ana sayfada event listesi yenilenecek ve event ID ile bulunacak
        // ✅ Bu yüzden QR scanner'da event aramaya çalışmak yerine direkt event_id'yi gönderiyoruz
        print('✅ Event ID ana sayfaya gönderilecek: $eventIdFromResponse (QR: $qrCode)');
        
        // ✅ Success modal göster ve event_id'yi ana sayfaya gönder
        if (eventIdFromResponse != null) {
          // ✅ Event bulunamadı ama event_id var, event_id'yi gönder (ana sayfada event listesi yenilenecek ve event açılacak)
          print('⚠️ Event objesi bulunamadı ama event_id var: $eventIdFromResponse');
          if (mounted) {
            SuccessModal.show(
              context,
              title: isAlreadyParticipant ? 'Etkinliğe Zaten Katılmışsınız' : 'Etkinliğe Katıldınız!',
              message: isAlreadyParticipant 
                  ? 'Etkinliğe zaten katılmışsınız.'
                  : '${result != null ? (result['event_title'] ?? 'Etkinlik') : 'Etkinlik'} etkinliğine başarıyla katıldınız.',
              icon: Icons.event_available,
              iconColor: AppColors.success,
              onClose: () {
                // ✅ Başarılı katılım flag'i gönder (ana sayfada event listesi yenilenecek)
                if (mounted) {
                  Navigator.pop(context, true);
                }
              },
            );
          }
        } else {
          // ✅ Event ID bulunamadı hatası
          if (mounted) {
            ErrorModal.show(
              context,
              title: 'Hata',
              message: 'Etkinlik ID\'si alınamadı. Lütfen tekrar deneyin.',
              icon: Icons.error_outline,
              iconColor: AppColors.error,
            );
          }
          
          // Tekrar taramaya izin ver
          setState(() {
            isScanning = true;
          });
        }
      } else {
        // ✅ Geçersiz QR kod hatası
        if (mounted) {
          ErrorModal.show(
            context,
            title: 'Geçersiz QR Kod',
            message: 'Lütfen geçerli bir etkinlik QR kodu tarayın',
            icon: Icons.qr_code_scanner,
            iconColor: Colors.orange,
          );
        }
        
        // Tekrar taramaya izin ver
        setState(() {
          isScanning = true;
        });
      }
    } catch (e) {
      // ✅ Hata modalı
      if (mounted) {
        ErrorModal.show(
          context,
          title: 'Bağlantı Hatası',
          message: 'Lütfen internet bağlantınızı kontrol edin ve tekrar deneyin',
          icon: Icons.wifi_off,
          iconColor: AppColors.error,
        );
      }
      
      // Tekrar taramaya izin ver
      setState(() {
        isScanning = true;
      });
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: const Text('QR Kod Tara'),
        backgroundColor: ThemeColors.primary(context),
        foregroundColor: Colors.white,
        actions: [
          IconButton(
            icon: Icon(
              isTorchOn ? Icons.flash_on : Icons.flash_off,
              color: isTorchOn ? Colors.yellow : Colors.grey,
            ),
            onPressed: () {
              setState(() {
                isTorchOn = !isTorchOn;
              });
              cameraController.toggleTorch();
            },
          ),
          IconButton(
            icon: Icon(
              isFrontCamera ? Icons.camera_front : Icons.camera_rear,
            ),
            onPressed: () {
              setState(() {
                isFrontCamera = !isFrontCamera;
              });
              cameraController.switchCamera();
            },
          ),
        ],
      ),
      body: Column(
        children: [
          Expanded(
            flex: 4,
            child: MobileScanner(
              controller: cameraController,
              onDetect: _handleQRCode,
            ),
          ),
          Expanded(
            flex: 1,
            child: Container(
              padding: const EdgeInsets.all(20),
              color: Theme.of(context).scaffoldBackgroundColor,
              child: Column(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  Text(
                    'Etkinlik QR kodunu kameraya doğrultun',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w500,
                      color: Theme.of(context).brightness == Brightness.dark ? Colors.white : Colors.black,
                    ),
                    textAlign: TextAlign.center,
                  ),
                  const SizedBox(height: 10),
                  Text(
                    isScanning ? 'QR kod aranıyor...' : 'QR kod işleniyor...',
                    style: TextStyle(
                      fontSize: 14,
                      color: isScanning ? AppColors.success : AppColors.info,
                    ),
                  ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }
}
