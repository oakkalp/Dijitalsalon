@echo off
echo ========================================
echo   DigitalSalon - Release Build Script
echo ========================================
echo.

REM Flutter clean
echo [1/4] Cleaning Flutter project...
flutter clean
if %errorlevel% neq 0 (
    echo ERROR: Flutter clean failed!
    pause
    exit /b 1
)

REM Get dependencies
echo.
echo [2/4] Getting dependencies...
flutter pub get
if %errorlevel% neq 0 (
    echo ERROR: Flutter pub get failed!
    pause
    exit /b 1
)

REM Build APK
echo.
echo [3/4] Building APK (Release)...
flutter build apk --release
if %errorlevel% neq 0 (
    echo ERROR: APK build failed!
    pause
    exit /b 1
)

REM Build App Bundle
echo.
echo [4/4] Building App Bundle (Release)...
flutter build appbundle --release
if %errorlevel% neq 0 (
    echo ERROR: App Bundle build failed!
    pause
    exit /b 1
)

echo.
echo ========================================
echo   Build Completed Successfully!
echo ========================================
echo.
echo APK Location:
echo   build\app\outputs\flutter-apk\app-release.apk
echo.
echo App Bundle Location (Play Store):
echo   build\app\outputs\bundle\release\app-release.aab
echo.
echo Next Steps:
echo   1. Test the APK on a device
echo   2. Upload the AAB to Google Play Console
echo   3. Complete store listing information
echo   4. Publish to Play Store
echo.
pause

