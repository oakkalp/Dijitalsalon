import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'dart:convert';

class TermsAgreementModal extends StatefulWidget {
  final Function(bool) onAgree;
  final bool initialValue;

  const TermsAgreementModal({
    super.key,
    required this.onAgree,
    this.initialValue = false,
  });

  @override
  State<TermsAgreementModal> createState() => _TermsAgreementModalState();
}

class _TermsAgreementModalState extends State<TermsAgreementModal> {
  late bool _isAgreed;
  String? _termsContent;

  @override
  void initState() {
    super.initState();
    _isAgreed = widget.initialValue;
    _loadTerms();
  }

  Future<void> _loadTerms() async {
    try {
      // Load terms from assets
      final String terms = await rootBundle.loadString('assets/terms.txt');
      if (mounted) {
        setState(() {
          _termsContent = terms;
        });
      }
    } catch (e) {
      // Fallback: return hardcoded terms from sozlesme.md
      if (mounted) {
        setState(() {
          _termsContent = _getFallbackTerms();
        });
      }
    }
  }

  String _getFallbackTerms() {
    // Eğer assets/terms.txt yüklenemezse, sozlesme.md'den tam içeriği göster
    // Bu durumda kullanıcıya tam sözleşmeyi gösteremezsek hata mesajı göster
    return 'Sözleşme dosyası yüklenemedi. Lütfen uygulamayı yeniden başlatın veya daha sonra tekrar deneyin.\n\nTam sözleşme metni için: https://dijitalsalon.cagapps.app/sozlesme.md';
  }

  @override
  Widget build(BuildContext context) {
    return Dialog(
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.circular(20),
      ),
      child: Container(
        constraints: const BoxConstraints(maxHeight: 600),
        child: Column(
          children: [
            // Header
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: const Color(0xFFE1306C),
                borderRadius: const BorderRadius.only(
                  topLeft: Radius.circular(20),
                  topRight: Radius.circular(20),
                ),
              ),
              child: Row(
                children: [
                  const Icon(Icons.description, color: Colors.white, size: 28),
                  const SizedBox(width: 12),
                  const Expanded(
                    child: Text(
                      'Kullanım Koşulları ve Gizlilik Politikası',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 18,
                        fontWeight: FontWeight.bold,
                      ),
                    ),
                  ),
                  IconButton(
                    icon: const Icon(Icons.close, color: Colors.white),
                    onPressed: () {
                      Navigator.of(context).pop(false);
                      widget.onAgree(false);
                    },
                  ),
                ],
              ),
            ),
            
            // Content
            Expanded(
              child: _termsContent == null
                  ? const Center(child: CircularProgressIndicator())
                  : SingleChildScrollView(
                      padding: const EdgeInsets.all(20),
                      child: Text(
                        _termsContent!,
                        style: const TextStyle(
                          fontSize: 14,
                          height: 1.6,
                          color: Colors.black87,
                        ),
                      ),
                    ),
            ),
            
            // Footer with checkbox and buttons
            Container(
              padding: const EdgeInsets.all(20),
              decoration: BoxDecoration(
                color: Colors.grey[50],
                border: Border(
                  top: BorderSide(color: Colors.grey[300]!),
                ),
              ),
              child: Column(
                mainAxisSize: MainAxisSize.min,
                children: [
                  Row(
                    children: [
                      Checkbox(
                        value: _isAgreed,
                        onChanged: (value) {
                          setState(() {
                            _isAgreed = value ?? false;
                          });
                        },
                        activeColor: const Color(0xFFE1306C),
                      ),
                      Expanded(
                        child: GestureDetector(
                          onTap: () {
                            setState(() {
                              _isAgreed = !_isAgreed;
                            });
                          },
                          child: RichText(
                            text: const TextSpan(
                              style: TextStyle(
                                fontSize: 13,
                                color: Colors.black87,
                              ),
                              children: [
                                TextSpan(text: 'Kullanım Koşullarını okudum, anladım ve '),
                                TextSpan(
                                  text: 'kabul ediyorum',
                                  style: TextStyle(
                                    fontWeight: FontWeight.bold,
                                    color: Color(0xFFE1306C),
                                    decoration: TextDecoration.underline,
                                  ),
                                ),
                                TextSpan(text: '.'),
                              ],
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                  const SizedBox(height: 12),
                  Row(
                    children: [
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {
                            Navigator.of(context).pop(false);
                            widget.onAgree(false);
                          },
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            side: BorderSide(color: Colors.grey[400]!),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                          ),
                          child: const Text(
                            'Reddet',
                            style: TextStyle(
                              color: Colors.black87,
                              fontWeight: FontWeight.w600,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        flex: 2,
                        child: ElevatedButton(
                          onPressed: _isAgreed
                              ? () {
                                  Navigator.of(context).pop(true);
                                  widget.onAgree(true);
                                }
                              : null,
                          style: ElevatedButton.styleFrom(
                            backgroundColor: const Color(0xFFE1306C),
                            foregroundColor: Colors.white,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: RoundedRectangleBorder(
                              borderRadius: BorderRadius.circular(10),
                            ),
                            elevation: 0,
                          ),
                          child: const Text(
                            'Kabul Et ve Devam Et',
                            style: TextStyle(
                              fontWeight: FontWeight.bold,
                              fontSize: 15,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),
          ],
        ),
      ),
    );
  }
}

// ✅ Checkbox widget for inline agreement
class TermsCheckbox extends StatefulWidget {
  final Function(bool) onChanged;
  final bool initialValue;

  const TermsCheckbox({
    super.key,
    required this.onChanged,
    this.initialValue = false,
  });

  @override
  State<TermsCheckbox> createState() => _TermsCheckboxState();
}

class _TermsCheckboxState extends State<TermsCheckbox> {
  late bool _isAgreed;

  @override
  void initState() {
    super.initState();
    _isAgreed = widget.initialValue;
  }

  @override
  Widget build(BuildContext context) {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Checkbox(
          value: _isAgreed,
          onChanged: (value) {
            setState(() {
              _isAgreed = value ?? false;
            });
            widget.onChanged(_isAgreed);
          },
          activeColor: const Color(0xFFE1306C),
        ),
        Expanded(
          child: GestureDetector(
            onTap: () {
              showDialog(
                context: context,
                builder: (context) => TermsAgreementModal(
                  onAgree: (agreed) {
                    setState(() {
                      _isAgreed = agreed;
                    });
                    widget.onChanged(agreed);
                  },
                  initialValue: _isAgreed,
                ),
              );
            },
            child: RichText(
              text: TextSpan(
                style: TextStyle(
                  fontSize: 13,
                  color: Colors.grey[700],
                  height: 1.5,
                ),
                children: [
                  const TextSpan(text: 'Kullanım Koşullarını ve Gizlilik Politikasını okudum, anladım ve '),
                  TextSpan(
                    text: 'kabul ediyorum',
                    style: TextStyle(
                      fontWeight: FontWeight.bold,
                      color: const Color(0xFFE1306C),
                      decoration: TextDecoration.underline,
                    ),
                  ),
                  const TextSpan(text: '.'),
                ],
              ),
            ),
          ),
        ),
      ],
    );
  }
}

