@echo off
echo ========================================
echo   DigitalSalon - Play Store Build
echo ========================================
echo.

REM Flutter clean
echo [1/3] Cleaning Flutter project...
flutter clean
if %errorlevel% neq 0 (
    echo ERROR: Flutter clean failed!
    pause
    exit /b 1
)

REM Get dependencies
echo.
echo [2/3] Getting dependencies...
flutter pub get
if %errorlevel% neq 0 (
    echo ERROR: Flutter pub get failed!
    pause
    exit /b 1
)

REM Build App Bundle (Play Store için)
echo.
echo [3/3] Building App Bundle (Release)...
echo This may take a few minutes...
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
echo App Bundle Location:
echo   build\app\outputs\bundle\release\app-release.aab
echo.
echo Next Steps:
echo   1. Go to Google Play Console
echo   2. Create new app (if first time)
echo   3. Complete store listing
echo   4. Upload app-release.aab
echo   5. Submit for review
echo.
echo Opening build folder...
start build\app\outputs\bundle\release
pause

