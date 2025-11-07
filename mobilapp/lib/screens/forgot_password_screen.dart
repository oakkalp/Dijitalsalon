import 'package:flutter/material.dart';
import 'package:flutter/foundation.dart';
import 'package:digimobil_new/services/api_service.dart';
import 'package:digimobil_new/utils/colors.dart';
import 'package:digimobil_new/widgets/error_modal.dart';
import 'package:digimobil_new/widgets/success_modal.dart';
import 'package:digimobil_new/screens/reset_password_screen.dart';
import 'package:digimobil_new/screens/verify_code_screen.dart';

class ForgotPasswordScreen extends StatefulWidget {
  const ForgotPasswordScreen({super.key});

  @override
  State<ForgotPasswordScreen> createState() => _ForgotPasswordScreenState();
}

class _ForgotPasswordScreenState extends State<ForgotPasswordScreen> {
  final _formKey = GlobalKey<FormState>();
  final _emailController = TextEditingController();
  final ApiService _apiService = ApiService();
  bool _isLoading = false;

  @override
  void dispose() {
    _emailController.dispose();
    super.dispose();
  }

  Future<void> _sendResetEmail() async {
    if (!_formKey.currentState!.validate()) {
      return;
    }

    setState(() {
      _isLoading = true;
    });

    try {
      final email = _emailController.text.trim();
      final result = await _apiService.forgotPassword(email);

      if (result['success'] == true) {
        if (mounted) {
          // ✅ Her zaman doğrulama kodu ekranına yönlendir
          final verificationCode = result['verification_code'] as String?;
          final emailSent = result['email_sent'] == true;
          
          if (verificationCode != null && verificationCode.isNotEmpty) {
            // ✅ Email gönderildi veya gönderilmedi, her durumda doğrulama ekranına git
            Navigator.pushReplacement(
              context,
              MaterialPageRoute(
                builder: (context) => VerifyCodeScreen(
                  email: _emailController.text.trim(),
                  verificationCode: verificationCode,
                ),
              ),
            );
            
            // ✅ Email gönderildiyse kullanıcıya bilgi ver
            if (emailSent) {
              Future.delayed(const Duration(milliseconds: 500), () {
                if (mounted) {
                  SuccessModal.show(
                    context,
                    title: 'E-posta Gönderildi',
                    message: 'Doğrulama kodunuz e-posta adresinize gönderilmiştir. Lütfen e-postanızı kontrol edin.',
                    icon: Icons.email_outlined,
                  );
                }
              });
            }
          } else {
            // ✅ Kod yoksa hata göster
            ErrorModal.show(
              context,
              title: 'Hata',
              message: 'Doğrulama kodu oluşturulamadı. Lütfen tekrar deneyin.',
            );
          }
        }
      } else {
        if (mounted) {
          ErrorModal.show(
            context,
            title: 'Hata',
            message: result['message'] ?? 'Bir hata oluştu. Lütfen tekrar deneyin.',
          );
        }
      }
    } catch (e) {
      if (mounted) {
        ErrorModal.show(
          context,
          title: 'Hata',
          message: 'Bir hata oluştu: $e',
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

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: Colors.white,
        elevation: 0,
        leading: IconButton(
          icon: Icon(Icons.arrow_back, color: Colors.grey[800]),
          onPressed: () => Navigator.pop(context),
        ),
        title: Text(
          'Şifremi Unuttum',
          style: TextStyle(
            color: Colors.grey[800],
            fontSize: 20,
            fontWeight: FontWeight.w600,
          ),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.all(24),
          child: Form(
            key: _formKey,
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.stretch,
              children: [
                const SizedBox(height: 40),
                
                // Icon
                Container(
                  width: 100,
                  height: 100,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE1306C).withOpacity(0.1),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.lock_reset,
                    color: Color(0xFFE1306C),
                    size: 50,
                  ),
                ),
                
                const SizedBox(height: 32),
                
                const Text(
                  'Şifre Sıfırlama',
                  style: TextStyle(
                    fontSize: 28,
                    fontWeight: FontWeight.bold,
                    color: Colors.black,
                  ),
                  textAlign: TextAlign.center,
                ),
                
                const SizedBox(height: 12),
                
                Text(
                  'E-posta adresinize şifre sıfırlama bağlantısı göndereceğiz.',
                  style: TextStyle(
                    fontSize: 16,
                    color: Colors.grey[600],
                  ),
                  textAlign: TextAlign.center,
                ),
                
                const SizedBox(height: 40),
                
                // Email Field
                Container(
                  decoration: BoxDecoration(
                    color: Colors.grey[50],
                    borderRadius: BorderRadius.circular(16),
                    border: Border.all(color: Colors.grey[200]!),
                  ),
                  child: TextFormField(
                    controller: _emailController,
                    keyboardType: TextInputType.emailAddress,
                    decoration: InputDecoration(
                      labelText: 'E-posta adresi',
                      labelStyle: TextStyle(color: Colors.grey[600]),
                      prefixIcon: Icon(Icons.email_outlined, color: Colors.grey[600]),
                      border: InputBorder.none,
                      contentPadding: const EdgeInsets.all(20),
                    ),
                    validator: (value) {
                      if (value == null || value.isEmpty) {
                        return 'E-posta adresi gerekli';
                      }
                      if (!value.contains('@') || !value.contains('.')) {
                        return 'Geçerli bir e-posta adresi girin';
                      }
                      return null;
                    },
                  ),
                ),
                
                const SizedBox(height: 32),
                
                // Send Button
                SizedBox(
                  width: double.infinity,
                  height: 56,
                  child: ElevatedButton(
                    onPressed: _isLoading ? null : _sendResetEmail,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: const Color(0xFFE1306C),
                      foregroundColor: Colors.white,
                      shape: RoundedRectangleBorder(
                        borderRadius: BorderRadius.circular(16),
                      ),
                      elevation: 0,
                    ),
                    child: _isLoading
                        ? const SizedBox(
                            width: 24,
                            height: 24,
                            child: CircularProgressIndicator(
                              color: Colors.white,
                              strokeWidth: 2,
                            ),
                          )
                        : const Text(
                            'Gönder',
                            style: TextStyle(
                              fontSize: 18,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                  ),
                ),
                
                const SizedBox(height: 24),
                
                // Back to Login
                TextButton(
                  onPressed: () => Navigator.pop(context),
                  child: Text(
                    'Giriş sayfasına dön',
                    style: TextStyle(
                      color: Colors.grey[600],
                      fontSize: 14,
                    ),
                  ),
                ),
              ],
            ),
          ),
        ),
      ),
    );
  }
}

