import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:provider/provider.dart';
import 'package:digimobil_new/providers/event_provider.dart';
import 'package:digimobil_new/utils/colors.dart';
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
    return Scaffold(
      backgroundColor: AppColors.background,
      appBar: AppBar(
        title: const Text(
          'Etkinliğe Katıl',
          style: TextStyle(
            color: AppColors.textPrimary,
            fontWeight: FontWeight.bold,
          ),
        ),
        backgroundColor: AppColors.surface,
        elevation: 0,
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
                    color: AppColors.primary.withOpacity(0.3),
                    blurRadius: 20,
                    offset: const Offset(0, 10),
                  ),
                ],
              ),
              child: Column(
                children: [
                  const Icon(
                    Icons.qr_code_scanner,
                    size: 80,
                    color: AppColors.textPrimary,
                  ),
                  const SizedBox(height: 16),
                  const Text(
                    'Etkinliğe Katıl',
                    style: TextStyle(
                      fontSize: 24,
                      fontWeight: FontWeight.bold,
                      color: AppColors.textPrimary,
                    ),
                  ),
                  const SizedBox(height: 8),
                  Text(
                    'QR kodu tarayın veya etkinlik kodunu girin',
                    style: TextStyle(
                      fontSize: 16,
                      color: AppColors.textSecondary,
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
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(AppConstants.defaultRadius),
                border: Border.all(color: AppColors.border),
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
                    const Icon(
                      Icons.qr_code_scanner,
                      size: 40,
                      color: AppColors.primary,
                    ),
                    const SizedBox(height: 8),
                    const Text(
                      'QR Kodu Tara',
                      style: TextStyle(
                        fontSize: 16,
                        fontWeight: FontWeight.w600,
                        color: AppColors.textPrimary,
                      ),
                    ),
                    const SizedBox(height: 4),
                    Text(
                      'Etkinlik QR kodunu tarayın',
                      style: TextStyle(
                        fontSize: 12,
                        color: AppColors.textSecondary,
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
                    color: AppColors.border,
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
                      color: AppColors.textTertiary,
                    ),
                  ),
                ),
                Expanded(
                  child: Divider(
                    color: AppColors.border,
                    thickness: 1,
                  ),
                ),
              ],
            ),

            const SizedBox(height: 24),

            // Manual Event ID Input
            Container(
              decoration: BoxDecoration(
                color: AppColors.surface,
                borderRadius: BorderRadius.circular(AppConstants.defaultRadius),
                border: Border.all(color: AppColors.border),
              ),
              child: TextFormField(
                controller: _eventIdController,
                keyboardType: TextInputType.number,
                style: const TextStyle(color: AppColors.textPrimary),
                decoration: const InputDecoration(
                  labelText: 'Etkinlik Kodu',
                  labelStyle: TextStyle(color: AppColors.textSecondary),
                  prefixIcon: Icon(Icons.event, color: AppColors.textSecondary),
                  border: InputBorder.none,
                  contentPadding: EdgeInsets.all(AppConstants.defaultPadding),
                  hintText: 'Örnek: 20 (Etkinlikler sayfasındaki ID)',
                  hintStyle: TextStyle(color: AppColors.textTertiary),
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
                    ? const SizedBox(
                        width: 24,
                        height: 24,
                        child: CircularProgressIndicator(
                          color: AppColors.textPrimary,
                          strokeWidth: 2,
                        ),
                      )
                    : const Text(
                        'Etkinliğe Katıl',
                        style: TextStyle(
                          fontSize: 16,
                          fontWeight: FontWeight.w600,
                          color: AppColors.textPrimary,
                        ),
                      ),
              ),
            ),

            const SizedBox(height: 32),

            // Info Card
            Container(
              padding: const EdgeInsets.all(AppConstants.defaultPadding),
              decoration: BoxDecoration(
                color: AppColors.info.withOpacity(0.1),
                borderRadius: BorderRadius.circular(AppConstants.defaultRadius),
                border: Border.all(color: AppColors.info.withOpacity(0.3)),
              ),
              child: Row(
                children: [
                  const Icon(
                    Icons.info_outline,
                    color: AppColors.info,
                    size: 20,
                  ),
                  const SizedBox(width: 12),
                  Expanded(
                    child:                     Text(
                      'Etkinlik kodunu moderatörden alabilir veya etkinlik QR kodunu tarayabilirsiniz.\n\nMevcut etkinlik ID: 20',
                      style: const TextStyle(
                        color: AppColors.info,
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
            // Refresh events after successful QR scan
            Provider.of<EventProvider>(context, listen: false).loadEvents();
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
        
        // Navigate back to events screen after a short delay
        Future.delayed(const Duration(milliseconds: 1500), () {
          if (mounted) {
            // Call callback to switch to events tab
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
