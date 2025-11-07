import 'package:flutter/material.dart';

/// ✅ Ortak Page Route Transitions
/// Sayfa geçişleri için tutarlı animasyonlar sağlar
class AppPageRoute<T> extends PageRouteBuilder<T> {
  final Widget page;
  final RouteSettings? _settings;
  final bool fullscreenDialog;

  AppPageRoute({
    required this.page,
    RouteSettings? settings,
    this.fullscreenDialog = false,
  }) : _settings = settings,
        super(
          pageBuilder: (context, animation, secondaryAnimation) => page,
          settings: settings ?? const RouteSettings(),
          fullscreenDialog: fullscreenDialog,
          transitionDuration: const Duration(milliseconds: 300),
          reverseTransitionDuration: const Duration(milliseconds: 250),
          transitionsBuilder: (context, animation, secondaryAnimation, child) {
            // ✅ Slide transition (aşağıdan yukarıya)
            const begin = Offset(0.0, 1.0);
            const end = Offset.zero;
            const curve = Curves.easeInOut;

            var tween = Tween(begin: begin, end: end).chain(
              CurveTween(curve: curve),
            );

            return SlideTransition(
              position: animation.drive(tween),
              child: child,
            );
          },
        );

  @override
  RouteSettings get settings => _settings ?? const RouteSettings();
}

/// ✅ Modal Bottom Sheet Transition
/// Modal açılışları için tutarlı animasyonlar sağlar
class AppModalBottomSheet {
  AppModalBottomSheet._(); // Private constructor - sadece static metodlar kullanılır

  static Future<T?> show<T>({
    required BuildContext context,
    required Widget child,
    Color? backgroundColor,
    double? borderRadius,
    bool isDismissible = true,
    bool enableDrag = true,
    bool isScrollControlled = false,
  }) {
    return showModalBottomSheet<T>(
      context: context,
      backgroundColor: backgroundColor ?? Colors.transparent,
      isDismissible: isDismissible,
      enableDrag: enableDrag,
      isScrollControlled: isScrollControlled,
      shape: RoundedRectangleBorder(
        borderRadius: BorderRadius.vertical(
          top: Radius.circular(borderRadius ?? 20),
        ),
      ),
      builder: (context) => child,
    );
  }
}

/// ✅ Fade Transition Widget
/// Widget'lar için fade animasyonu sağlar
class FadeTransitionWidget extends StatelessWidget {
  final Widget child;
  final Duration duration;
  final Duration delay;

  const FadeTransitionWidget({
    super.key,
    required this.child,
    this.duration = const Duration(milliseconds: 300),
    this.delay = Duration.zero,
  });

  @override
  Widget build(BuildContext context) {
    return TweenAnimationBuilder<double>(
      tween: Tween<double>(begin: 0.0, end: 1.0),
      duration: duration,
      curve: Curves.easeIn,
      builder: (context, value, child) {
        return Opacity(
          opacity: value,
          child: child,
        );
      },
      child: child,
    );
  }
}

