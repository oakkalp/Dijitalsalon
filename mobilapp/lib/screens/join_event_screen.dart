import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:provider/provider.dart';
import 'package:digimobil_new/providers/event_provider.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/utils/theme_colors.dart';
import 'package:digimobil_new/utils/constants.dart';
import 'package:digimobil_new/screens/qr_scanner_screen.dart';

class JoinEventScreen extends StatefulWidget {
  final VoidCallback? onEventJoined;
  
  const JoinEventScreen({super.key, this.onEventJoined});

  @override
  State<JoinEventScreen> createState() => _JoinEventScreenState();
}

class _JoinEventScreenState extends State<JoinEventScreen> {
  final _eventIdController = TextEditingController();
  bool _isLoading = false;

  @override
  void dispose() {
    _eventIdController.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    final textColor = isDark ? Colors.white : Colors.black;
    final secondaryTextColor = isDark ? Colors.grey[300]! : Colors.grey[600]!;
    
    return Scaffold(
      backgroundColor: Theme.of(context).scaffoldBackgroundColor,
      appBar: AppBar(
        title: Text(
          'Etkinliğe Katıl',
          style: TextStyle(
            color: textColor,
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: Theme.of(context).scaffoldBackgroundColor,
        elevation: 0,
        iconTheme: IconThemeData(color: textColor),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(AppConstants.defaultPadding),
        child: Column(
          children: [
            // Header
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(AppConstants.largePadding),
              decoration: BoxDecoration(
                gradient: AppColors.primaryGradient,
                borderRadius: BorderRadius.circular(AppConstants.defaultRadius),
                boxShadow: [
                  BoxShadow(
                    color: ThemeColors.primary(context).withOpacity(0.3),
                    blurRadius: 20,
                    offset: const Offset(0, 10),
                  ),
                ],
              ),
              child: Column(
                children: [
                  Icon(
                    Icons.qr_code_scanner,
                    size: 80,
                    color: textColor,
                  ),
                  const SizedBox(height: 16),
                  Text(
                    'Etkinliğe Katıl',
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                      color: textColor,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'QR kodu tarayın veya etkinlik kodunu girin',
                    style: TextStyle(
                      fontSize: 16,
                      color: secondaryTextColor,
                    ),
                    textAlign: TextAlign.center,
                  ),
                ],
              ),
            ),

            const SizedBox(height: 32),

            // QR Scanner Button
            Container(
              width: double.infinity,
              height: 120,
              decoration: BoxDecoration(
                color: Theme.of(context).cardTheme.color ?? ThemeColors.surface(context),
                borderRadius: BorderRadius.circular(AppConstants.defaultRadius),
                border: Border.all(color: ThemeColors.border(context)),
              ),
              child: ElevatedButton(
                onPressed: _scanQRCode,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.transparent,
                  shadowColor: Colors.transparent,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(AppConstants.defaultRadius),
                  ),
                ),
                child: Column(
                  mainAxisAlignment: MainAxisAlignment.center,
                  children: [
                    Icon(
                      Icons.qr_code_scanner,
                      size: 40,
                      color: ThemeColors.primary(context),
                    ),
                    const SizedBox(height: 8),
                    Text(
                      'QR Kodu Tara',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: textColor,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Etkinlik QR kodunu tarayın',
                      style: TextStyle(
                        fontSize: 12,
                        color: secondaryTextColor,
                      ),
                    ),
                  ],
                ),
              ),
            ),

            const SizedBox(height: 24),

            // Divider
            Row(
              children: [
                Expanded(
                  child: Divider(
                    color: ThemeColors.border(context),
                    thickness: 1,
                  ),
                ),
                Padding(
                  padding: const EdgeInsets.symmetric(horizontal: 16),
                  child: Text(
                    'VEYA',
                    style: TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                      color: secondaryTextColor,
                    ),
                  ),
                ),
                Expanded(
                  child: Divider(
                    color: ThemeColors.border(context),
                    thickness: 1,
                  ),
                ),
              ],
            ),

            const SizedBox(height: 24),

            // Manual Event ID Input
            Container(
              decoration: BoxDecoration(
                color: Theme.of(context).cardTheme.color ?? ThemeColors.surface(context),
                borderRadius: BorderRadius.circular(AppConstants.defaultRadius),
                border: Border.all(color: ThemeColors.border(context)),
              ),
              child: TextFormField(
                controller: _eventIdController,
                keyboardType: TextInputType.number,
                style: TextStyle(color: textColor),
                decoration: InputDecoration(
                  labelText: 'Etkinlik Kodu',
                  labelStyle: TextStyle(color: secondaryTextColor),
                  prefixIcon: Icon(Icons.event, color: secondaryTextColor),
                  border: InputBorder.none,
                  contentPadding: const EdgeInsets.all(AppConstants.defaultPadding),
                  hintText: 'Örnek: 20 (Etkinlikler sayfasındaki ID)',
                  hintStyle: TextStyle(color: secondaryTextColor?.withOpacity(0.7) ?? Colors.grey),
                ),
              ),
            ),

            const SizedBox(height: 24),

            // Join Button
            Container(
              width: double.infinity,
              height: 56,
              decoration: BoxDecoration(
                gradient: AppColors.primaryGradient,
                borderRadius: BorderRadius.circular(AppConstants.defaultRadius),
                boxShadow: [
                  BoxShadow(
                    color: AppColors.primary.withOpacity(0.3),
                    blurRadius: 15,
                    offset: const Offset(0, 5),
                  ),
                ],
              ),
              child: ElevatedButton(
                onPressed: _isLoading ? null : _joinEvent,
                style: ElevatedButton.styleFrom(
                  backgroundColor: Colors.transparent,
                  shadowColor: Colors.transparent,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(AppConstants.defaultRadius),
                  ),
                ),
                child: _isLoading
                    ? SizedBox(
                        width: 24,
                        height: 24,
                        child: CircularProgressIndicator(
                          color: textColor,
                          strokeWidth: 2,
                        ),
                      )
                    : Text(
                        'Etkinliğe Katıl',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                          color: textColor,
                        ),
                      ),
              ),
            ),

            const SizedBox(height: 32),

            // Info Card
            Container(
              padding: const EdgeInsets.all(AppConstants.defaultPadding),
              decoration: BoxDecoration(
                color: ThemeColors.info.withOpacity(0.1),
                borderRadius: BorderRadius.circular(AppConstants.defaultRadius),
                border: Border.all(color: ThemeColors.info.withOpacity(0.3)),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.info_outline,
                    color: ThemeColors.info,
                    size: 20,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child: Text(
                      'Etkinlik kodunu moderatörden alabilir veya etkinlik QR kodunu tarayabilirsiniz.\n\nMevcut etkinlik ID: 20',
                      style: const TextStyle(
                        color: ThemeColors.info,
                        fontSize: 14,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _scanQRCode() {
    Navigator.push(
      context,
      MaterialPageRoute(
        builder: (context) => QRScannerScreen(
          onQRCodeScanned: (qrCode) {
            // ✅ Refresh events after successful QR scan (cache bypass ile - yeni event hemen görünsün)
            Provider.of<EventProvider>(context, listen: false).loadEvents(bypassCache: true);
            // ✅ Ana sayfaya dön (event detay sayfası açılmayacak)
            widget.onEventJoined?.call();
          },
        ),
      ),
    );
  }

  void _joinEvent() async {
    if (_eventIdController.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text('Lütfen etkinlik kodunu girin'),
          backgroundColor: AppColors.error,
        ),
      );
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      final eventId = _eventIdController.text.trim();
      if (kDebugMode) {
        debugPrint('Joining event with ID: $eventId');
      }
      
      final eventProvider = Provider.of<EventProvider>(context, listen: false);
      await eventProvider.joinEvent(eventId);
      
      if (mounted) {
        // Show success message
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(eventProvider.successMessage ?? 'Etkinliğe başarıyla katıldınız!'),
            backgroundColor: AppColors.success,
            duration: const Duration(seconds: 2),
          ),
        );
        
        // Clear the input field
        _eventIdController.clear();
        
        // ✅ Ana sayfaya dön (event detay sayfası açılmayacak)
        // ✅ Event kartı real-time görünecek
        Future.delayed(const Duration(milliseconds: 1500), () {
          if (mounted) {
            // Call callback to switch to events tab (ana sayfaya dön)
            widget.onEventJoined?.call();
          }
        });
      }
    } catch (e) {
      if (mounted) {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text('Hata: $e'),
            backgroundColor: AppColors.error,
          ),
        );
      }
    } finally {
      if (mounted) {
        setState(() {
          _isLoading = false;
        });
      }
    }
  }
}
