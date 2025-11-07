import 'package:flutter/material.dart';

/// Theme-aware color system
/// Material 3 design system ile uyumlu
class ThemeColors {
  // ✅ Light Theme Colors
  static const lightPrimary = Color(0xFFE91E63); // Pink
  static const lightSecondary = Color(0xFFF48FB1); // Light Pink
  static const lightBackground = Color(0xFFFFFFFF); // White
  static const lightSurface = Color(0xFFF5F5F5); // Light Grey
  static const lightTextPrimary = Color(0xFF212121); // Dark Grey
  static const lightTextSecondary = Color(0xFF757575); // Medium Grey
  
  // ✅ Dark Theme Colors (Material 3 Dark Theme uyumlu)
  // ✅ Görseldeki tasarıma uygun renk paleti
  static const darkPrimary = Color(0xFFBB86FC); // Vibrant light purple/pink (accent color)
  static const darkSecondary = Color(0xFFCF9FFF); // Lighter variant
  static const darkBackground = Color(0xFF1A1A2E); // Dark purple/black background
  static const darkSurface = Color(0xFF2C2C4A); // Slightly lighter dark gray/purple for cards
  static const darkSurfaceVariant = Color(0xFF3A3A5A); // Lighter dark surface
  static const darkTextPrimary = Color(0xFFFFFFFF); // White
  static const darkTextSecondary = Color(0xFFB3B3B3); // Light Grey
  
  // ✅ Status Colors (her iki tema için aynı)
  static const error = Color(0xFFFF4444);
  static const success = Color(0xFF4CAF50);
  static const warning = Color(0xFFFFB74D);
  static const info = Color(0xFF42A5F5);
  
  // ✅ Border Colors
  static const lightBorder = Color(0xFFE0E0E0);
  static const darkBorder = Color(0xFF3A3A3A);
  
  /// BuildContext'ten theme'e göre renkleri al
  static Color primary(BuildContext context) {
    return Theme.of(context).brightness == Brightness.dark
        ? darkPrimary
        : lightPrimary;
  }
  
  static Color secondary(BuildContext context) {
    return Theme.of(context).brightness == Brightness.dark
        ? darkSecondary
        : lightSecondary;
  }
  
  static Color background(BuildContext context) {
    return Theme.of(context).brightness == Brightness.dark
        ? darkBackground
        : lightBackground;
  }
  
  static Color surface(BuildContext context) {
    return Theme.of(context).brightness == Brightness.dark
        ? darkSurface
        : lightSurface;
  }
  
  static Color surfaceVariant(BuildContext context) {
    return Theme.of(context).brightness == Brightness.dark
        ? darkSurfaceVariant
        : lightSurface;
  }
  
  static Color textPrimary(BuildContext context) {
    return Theme.of(context).brightness == Brightness.dark
        ? darkTextPrimary
        : lightTextPrimary;
  }
  
  static Color textSecondary(BuildContext context) {
    return Theme.of(context).brightness == Brightness.dark
        ? darkTextSecondary
        : lightTextSecondary;
  }
  
  static Color border(BuildContext context) {
    return Theme.of(context).brightness == Brightness.dark
        ? darkBorder
        : lightBorder;
  }
  
  /// Gradient'ler (theme-aware)
  static LinearGradient primaryGradient(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: isDark
          ? [darkPrimary, darkSecondary]
          : [lightPrimary, lightSecondary],
    );
  }
  
  static LinearGradient backgroundGradient(BuildContext context) {
    final isDark = Theme.of(context).brightness == Brightness.dark;
    return LinearGradient(
      begin: Alignment.topLeft,
      end: Alignment.bottomRight,
      colors: isDark
          ? [darkBackground, darkSurface]
          : [lightBackground, lightSurface],
    );
  }
}

/// Material 3 Theme Data Builder
class AppTheme {
  static ThemeData lightTheme() {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.light,
      colorScheme: ColorScheme.light(
        primary: ThemeColors.lightPrimary,
        secondary: ThemeColors.lightSecondary,
        background: ThemeColors.lightBackground,
        surface: ThemeColors.lightSurface,
        error: ThemeColors.error,
        onPrimary: Colors.white,
        onSecondary: Colors.white,
        onBackground: ThemeColors.lightTextPrimary,
        onSurface: ThemeColors.lightTextPrimary,
      ),
      scaffoldBackgroundColor: ThemeColors.lightBackground,
      appBarTheme: const AppBarTheme(
        backgroundColor: ThemeColors.lightBackground,
        foregroundColor: ThemeColors.lightTextPrimary,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
      ),
      cardTheme: CardThemeData(
        color: ThemeColors.lightBackground,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: BorderSide(color: ThemeColors.lightBorder, width: 1),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: ThemeColors.lightPrimary,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: ThemeColors.lightSurface,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide(color: ThemeColors.lightBorder, width: 1),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: ThemeColors.lightPrimary, width: 2),
        ),
        labelStyle: const TextStyle(color: ThemeColors.lightTextSecondary),
        hintStyle: const TextStyle(color: ThemeColors.lightTextSecondary),
      ),
      dividerTheme: const DividerThemeData(
        color: ThemeColors.lightBorder,
        thickness: 1,
      ),
    );
  }
  
  static ThemeData darkTheme() {
    return ThemeData(
      useMaterial3: true,
      brightness: Brightness.dark,
      colorScheme: ColorScheme.dark(
        primary: ThemeColors.darkPrimary,
        secondary: ThemeColors.darkSecondary,
        background: ThemeColors.darkBackground,
        surface: ThemeColors.darkSurface,
        error: ThemeColors.error,
        onPrimary: Colors.white,
        onSecondary: Colors.white,
        onBackground: ThemeColors.darkTextPrimary,
        onSurface: ThemeColors.darkTextPrimary,
      ),
      scaffoldBackgroundColor: ThemeColors.darkBackground,
      appBarTheme: const AppBarTheme(
        backgroundColor: ThemeColors.darkBackground,
        foregroundColor: ThemeColors.darkTextPrimary,
        elevation: 0,
        surfaceTintColor: Colors.transparent,
      ),
      cardTheme: CardThemeData(
        color: ThemeColors.darkSurface,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(12),
          side: const BorderSide(color: ThemeColors.darkBorder, width: 1),
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: ThemeColors.darkPrimary,
          foregroundColor: Colors.white,
          shape: RoundedRectangleBorder(
            borderRadius: BorderRadius.circular(12),
          ),
          padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: ThemeColors.darkSurface,
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: BorderSide.none,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: ThemeColors.darkBorder, width: 1),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: ThemeColors.darkPrimary, width: 2),
        ),
        labelStyle: const TextStyle(color: ThemeColors.darkTextSecondary),
        hintStyle: const TextStyle(color: ThemeColors.darkTextSecondary),
      ),
      dividerTheme: const DividerThemeData(
        color: ThemeColors.darkBorder,
        thickness: 1,
      ),
    );
  }
}

