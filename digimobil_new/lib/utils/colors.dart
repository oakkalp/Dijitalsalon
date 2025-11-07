import 'package:flutter/material.dart';

class AppColors {
  // 💖 ESKİ PEMBE TEMA - Orijinal Tasarım
  static const Color primary = Color(0xFFE91E63); // Pink
  static const Color secondary = Color(0xFFF48FB1); // Light Pink
  
  // Accent Colors - Vurgu Renkleri  
  static const Color accent = Color(0xFFFCE4EC); // Very Light Pink
  static const Color accentDark = Color(0xFFC2185B); // Deep Pink
  
  // Background Colors - Arka Plan
  static const Color background = Color(0xFFFFFFFF); // White
  static const Color surface = Color(0xFFF5F5F5); // Light Grey
  static const Color surfaceLight = Color(0xFFFAFAFA); // Very Light Grey
  static const Color cardBackground = Color(0xFFFFFFFF); // White Card
  
  // Text Colors - Metin Renkleri
  static const Color textPrimary = Color(0xFF212121); // Dark Grey
  static const Color textSecondary = Color(0xFF757575); // Medium Grey
  static const Color textTertiary = Color(0xFF9E9E9E); // Light Grey
  static const Color textGold = Color(0xFFE91E63); // Pink Text
  
  // Status Colors - Durum Renkleri
  static const Color error = Color(0xFFFF4444); // Red
  static const Color success = Color(0xFF4CAF50); // Green
  static const Color warning = Color(0xFFFFB74D); // Amber
  static const Color info = Color(0xFF42A5F5); // Blue
  
  // Border Colors - Kenarlık Renkleri
  static const Color border = Color(0xFFE0E0E0); // Light Border
  static const Color borderLight = Color(0xFFF5F5F5); // Very Light Border
  static const Color borderGold = Color(0xFFE91E63); // Pink Border
  
  // Gradient Colors - Degrade Renkleri
  static const LinearGradient backgroundGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [background, surface],
  );
  
  static const LinearGradient primaryGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [primary, accentDark],
  );
  
  static const LinearGradient weddingGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [primary, secondary],
  );
  
  static const LinearGradient orangeGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [primary, secondary],
  );
  
  static const LinearGradient shimmerGradient = LinearGradient(
    begin: Alignment.topLeft,
    end: Alignment.bottomRight,
    colors: [
      Color(0xFFE91E63), // Primary Pink
      Color(0xFFF48FB1), // Light Pink
      Color(0xFFE91E63), // Primary Pink
    ],
  );
}
