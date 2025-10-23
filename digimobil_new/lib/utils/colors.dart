import 'package:flutter/material.dart';

class AppColors {
  // Primary Colors
  static const Color primary = Color(0xFFDB61A2);
  static const Color secondary = Color(0xFFBF9EEE);
  
  // Background Colors
  static const Color background = Color(0xFF0A0A0A);
  static const Color surface = Color(0xFF1A1A1A);
  static const Color surfaceLight = Color(0xFF2A2A2A);
  static const Color cardBackground = Color(0xFF1E1E1E);
  
  // Text Colors
  static const Color textPrimary = Color(0xFFFFFFFF);
  static const Color textSecondary = Color(0xFFB0B0B0);
  static const Color textTertiary = Color(0xFF808080);
  
  // Status Colors
  static const Color error = Color(0xFFFF4444);
  static const Color success = Color(0xFF00C851);
  static const Color warning = Color(0xFFFF8800);
  static const Color info = Color(0xFF33B5E5);
  
  // Border Colors
  static const Color border = Color(0xFF333333);
  static const Color borderLight = Color(0xFF444444);
  
  // Gradient Colors
  static const LinearGradient backgroundGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [background, surface],
  );
  
  static const LinearGradient primaryGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [primary, secondary],
  );
}

