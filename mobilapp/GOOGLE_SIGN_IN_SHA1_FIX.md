# Google Sign In - SHA-1 Fingerprint Hatası Çözümü

## 🔴 Hata
```
ApiException: 10: DEVELOPER_ERROR
```

Bu hata, Google Sign In için SHA-1 fingerprint'in Firebase Console'a eklenmemiş olması anlamına gelir.

## ✅ Çözüm 1: Web Client ID Kullan (Geçici - Şu an uygulandı)

`lib/screens/login_screen.dart` dosyasında `serverClientId` parametresi eklendi. Bu, SHA-1 gerektirmez ve emulator'de çalışır.

```dart
final GoogleSignIn googleSignIn = GoogleSignIn(
  scopes: ['email', 'profile'],
  serverClientId: '839706849375-0vuj83hhjk5urmdl63odm58v7kk85jnp.apps.googleusercontent.com',
);
```

## ✅ Çözüm 2: SHA-1 Fingerprint Ekle (Kalıcı)

### Android Debug Keystore SHA-1'i Al

**Windows:**
```bash
keytool -list -v -keystore "%USERPROFILE%\.android\debug.keystore" -alias androiddebugkey -storepass android -keypass android
```

**macOS/Linux:**
```bash
keytool -list -v -keystore ~/.android/debug.keystore -alias androiddebugkey -storepass android -keypass android
```

### SHA-1'i Firebase Console'a Ekle

1. **Firebase Console** → **Project Settings** → **Your apps** → **Android app**
2. **SHA certificate fingerprints** bölümüne gidin
3. **Add fingerprint** butonuna tıklayın
4. SHA-1 değerini yapıştırın (örnek: `88:06:84:C3:25:81:A7:6C:17:64:B9:D2:DF:38:3F:7D:8D:D1:74:15`)
5. **Save** butonuna tıklayın

### google-services.json Güncelle

Firebase Console'dan yeni `google-services.json` dosyasını indirin ve `android/app/google-services.json` ile değiştirin.

### Release Build için SHA-1

Release build için de SHA-1 eklemeniz gerekir:

```bash
keytool -list -v -keystore your-release-keystore.jks -alias your-key-alias
```

## ✅ Çözüm 3: Android Studio ile SHA-1 Al

1. Android Studio'yu açın
2. **Gradle** panelini açın (sağ tarafta)
3. **app** → **Tasks** → **android** → **signingReport** çalıştırın
4. SHA-1 değerini log'lardan kopyalayın

## 📝 Notlar

- **Debug keystore**: `~/.android/debug.keystore` (otomatik oluşturulur)
- **Release keystore**: Kendi oluşturduğunuz keystore
- SHA-1 değeri `:` ile ayrılmış 20 hexadecimal byte'tır
- Her keystore için ayrı SHA-1 eklemelisiniz

## 🧪 Test

1. SHA-1 eklendikten sonra **google-services.json** dosyasını güncelleyin
2. Uygulamayı **tamamen kapatın** ve **yeniden başlatın**
3. Google Sign In'i test edin

## ✅ Şu Anki Durum

Web client ID (`serverClientId`) eklendi, bu sayede SHA-1 olmadan da çalışır. Ancak production için SHA-1 eklemek önerilir.

