import 'package:flutter/material.dart';
import 'package:digimobil_new/utils/colors.dart';

class GradientBackground extends StatelessWidget {
  final Widget child;
  final bool isDarkMode;

  const GradientBackground({
    super.key,
    required this.child,
    this.isDarkMode = true,
  });

  @override
  Widget build(BuildContext context) {
    return Stack(
      children: [
        Container(
          color: isDarkMode ? AppColors.background : Colors.white,
        ),
        Positioned(
          child: Container(
            decoration: BoxDecoration(
              gradient: LinearGradient(
                begin: Alignment.topCenter,
                end: Alignment.bottomCenter,
                colors: [
                  isDarkMode ? AppColors.background : Colors.white,
                  isDarkMode ? AppColors.background : Colors.white,
                  if (isDarkMode) ...[
                    AppColors.primary.withOpacity(.1),
                    AppColors.secondary.withOpacity(.2),
                  ] else ...[
                    AppColors.primary.withOpacity(.05),
                    AppColors.secondary.withOpacity(.1),
                  ]
                ],
              ),
            ),
          ),
        ),
        Positioned(
          top: 0,
          bottom: 80,
          left: 0,
          right: 0,
          child: child,
        ),
      ],
    );
  }
}

