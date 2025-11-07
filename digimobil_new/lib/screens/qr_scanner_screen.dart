import 'package:flutter/material.dart';
import 'package:mobile_scanner/mobile_scanner.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/utils/colors.dart';

class QRScannerScreen extends StatefulWidget {
  final Function(String) onQRCodeScanned;

  const QRScannerScreen({
    super.key,
    required this.onQRCodeScanned,
  });

  @override
  State<QRScannerScreen> createState() => _QRScannerScreenState();
}

class _QRScannerScreenState extends State<QRScannerScreen> {
  MobileScannerController controller = MobileScannerController();
  bool isScanning = true;
  final ApiService _apiService = ApiService();

  @override
  void dispose() {
    controller.dispose();
    super.dispose();
  }

  void _processQRCode(String qrCode) async {
    if (!isScanning) return;
    
    setState(() {
      isScanning = false;
    });

    try {
      // Show loading
      showDialog(
        context: context,
        barrierDismissible: false,
        builder: (context) => const Center(
          child: CircularProgressIndicator(color: AppColors.primary),
        ),
      );

      // Join event using QR code
      final success = await _apiService.joinEventByQR(qrCode);
      
      // Close loading dialog
      Navigator.pop(context);
      
      if (success) {
        // Close QR scanner and return to previous screen
        Navigator.pop(context);
        widget.onQRCodeScanned(qrCode);
        
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Etkinliğe başarıyla katıldınız!'),
            backgroundColor: AppColors.success,
          ),
        );
      } else {
        setState(() {
          isScanning = true;
        });
        
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('QR kod geçersiz veya etkinlik bulunamadı'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    } catch (e) {
      // Close loading dialog
      Navigator.pop(context);
      
      setState(() {
        isScanning = true;
      });
      
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Hata: $e'),
          backgroundColor: AppColors.error,
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.black,
      appBar: AppBar(
        backgroundColor: Colors.black,
        foregroundColor: Colors.white,
        title: const Text('QR Kod Tara'),
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.pop(context),
        ),
      ),
      body: Column(
        children: [
          Expanded(
            flex: 4,
            child: MobileScanner(
              controller: controller,
              onDetect: (capture) {
                final List<Barcode> barcodes = capture.barcodes;
                for (final barcode in barcodes) {
                  if (barcode.rawValue != null && isScanning) {
                    _processQRCode(barcode.rawValue!);
                    break;
                  }
                }
              },
            ),
          ),
          Expanded(
            flex: 1,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 16),
              child: SingleChildScrollView(
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  mainAxisSize: MainAxisSize.min,
                  children: [
                    const Icon(
                      Icons.qr_code_scanner,
                      color: Colors.white,
                      size: 48,
                    ),
                    const SizedBox(height: 12),
                    const Text(
                      'QR kodu kameraya doğru tutun',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 16,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      'Etkinlik QR kodunu tarayarak otomatik olarak katılabilirsiniz',
                      textAlign: TextAlign.center,
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.7),
                        fontSize: 13,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
