# 📷 CameraModal Icon Listesi

**Dosya:** `lib/widgets/camera_modal.dart`

## 🔝 Üst Kontroller (AppBar benzeri)

1. **Kapat Butonu**
   - **Icon:** `Icons.close`
   - **Konum:** Sol üst köşe
   - **Renk:** Beyaz (color: Colors.white)
   - **Fonksiyon:** `Navigator.of(context).pop()` - Modalı kapatır
   - **Arka Plan:** `Colors.black.withOpacity(0.3)`

2. **Flaş Butonu** (Dinamik - duruma göre değişir)
   - **Iconlar:**
     - `Icons.flash_on` - Flaş açık durumunda
     - `Icons.flash_off` - Flaş kapalı durumunda
     - `Icons.flash_auto` - Flaş otomatik durumunda
   - **Konum:** Üst orta
   - **Renk:** Beyaz (color: Colors.white)
   - **Fonksiyon:** `_toggleFlash()` - Flaş modunu değiştirir (auto → always → off → auto)
   - **Arka Plan:** `Colors.black.withOpacity(0.3)`

3. **Galeri Butonu**
   - **Icon:** `Icons.photo_library`
   - **Konum:** Sağ üst köşe
   - **Renk:** Beyaz (color: Colors.white)
   - **Fonksiyon:** `_openGallery()` - MediaSelectModal.show() açar
   - **Arka Plan:** `Colors.black.withOpacity(0.3)`

---

## ⬅️ Sol Tarafta Çekim Modları

4. **Normal Mod**
   - **Icon:** `Icons.camera_alt`
   - **Konum:** Sol tarafta, orta kısımda (ilk sıra)
   - **Label:** "Normal"
   - **Renk:** Beyaz (color: Colors.white)
   - **Fonksiyon:** `_captureMode = 'normal'` - Normal fotoğraf çekimi
   - **Seçili Durum:** `Colors.white.withOpacity(0.2)` arka plan

5. **Boomerang Mod**
   - **Icon:** `Icons.autorenew`
   - **Konum:** Sol tarafta, Normal'ın altında
   - **Label:** "Boomerang"
   - **Renk:** Beyaz (color: Colors.white)
   - **Fonksiyon:** `_captureMode = 'boomerang'` - Boomerang video çekimi
   - **Seçili Durum:** `Colors.white.withOpacity(0.2)` arka plan

6. **Yerleşim Mod**
   - **Icon:** `Icons.grid_view`
   - **Konum:** Sol tarafta, Boomerang'ın altında
   - **Label:** "Yerleşim"
   - **Renk:** Beyaz (color: Colors.white)
   - **Fonksiyon:** `_captureMode = 'layout'` - Grid yerleşim modu
   - **Seçili Durum:** `Colors.white.withOpacity(0.2)` arka plan

7. **Metin Mod**
   - **Icon:** `Icons.text_fields`
   - **Konum:** Sol tarafta, Yerleşim'in altında (en alt)
   - **Label:** "Metin"
   - **Renk:** Beyaz (color: Colors.white)
   - **Fonksiyon:** `_captureMode = 'text'` - Metin story modu
   - **Seçili Durum:** `Colors.white.withOpacity(0.2)` arka plan

---

## ⬇️ Alt Kontroller

8. **Galeri Önizleme (Sol)**
   - **Icon:** `Icons.photo`
   - **Konum:** Alt kısımda, çekim butonunun solunda
   - **Renk:** Beyaz (color: Colors.white)
   - **Boyut:** 24x24
   - **Fonksiyon:** Henüz aktif değil (placeholder)
   - **Tasarım:** 50x50 dairesel container, 2px beyaz border

9. **Çekim Butonu (Ortada)**
   - **Icon:** Yok (dairesel buton, içi boş/beyaz)
   - **Konum:** Alt kısımda, ortada
   - **Boyut:** 80x80 dairesel
   - **Renk:** Beyaz border (4px), içi beyaz
   - **Video Kaydı Sırasında:** İçi kırmızı (Colors.red) dairesel alan
   - **Fonksiyon:** 
     - Foto mod: `_capturePhoto()` - Tek tıklama
     - Video mod: `_startVideoRecording()` - Basılı tutma, `_stopVideoRecording()` - Tekrar tıklama

10. **Kamera Değiştir Butonu (Sağ)**
    - **Icon:** `Icons.cameraswitch`
    - **Konum:** Alt kısımda, çekim butonunun sağında
    - **Renk:** Beyaz (color: Colors.white)
    - **Boyut:** 28x28
    - **Fonksiyon:** `_switchCamera()` - Ön/arka kamera değiştirir
    - **Tasarım:** 50x50 dairesel container, `Colors.black.withOpacity(0.3)` arka plan

---

## 📊 Özet

| # | Icon | Konum | Fonksiyon |
|---|------|-------|-----------|
| 1 | `Icons.close` | Sol üst | Modalı kapat |
| 2 | `Icons.flash_on/off/auto` | Üst orta | Flaş kontrolü |
| 3 | `Icons.photo_library` | Sağ üst | Galeri aç |
| 4 | `Icons.camera_alt` | Sol orta | Normal mod |
| 5 | `Icons.autorenew` | Sol orta | Boomerang mod |
| 6 | `Icons.grid_view` | Sol orta | Yerleşim mod |
| 7 | `Icons.text_fields` | Sol orta | Metin mod |
| 8 | `Icons.photo` | Alt sol | Galeri önizleme |
| 9 | (Dairesel buton) | Alt orta | Foto/Video çek |
| 10 | `Icons.cameraswitch` | Alt sağ | Kamera değiştir |

---

## 🎨 Görsel Düzen

```
┌─────────────────────────────────┐
│ [X]  [⚡]  [📷]                  │ ← Üst kontroller
│                                 │
│ [📷]                            │
│ [🔄]    [Kamera Önizleme]       │ ← Sol modlar + Kamera
│ [⊞]                             │
│ [Aa]                            │
│                                 │
│ [📸]  [○]  [🔄]                 │ ← Alt kontroller
│                                 │
└─────────────────────────────────┘
```

**Açıklama:**
- **X** = Kapat (`Icons.close`)
- **⚡** = Flaş (`Icons.flash_*`)
- **📷** = Galeri (`Icons.photo_library`)
- **📷** = Normal (`Icons.camera_alt`)
- **🔄** = Boomerang (`Icons.autorenew`)
- **⊞** = Yerleşim (`Icons.grid_view`)
- **Aa** = Metin (`Icons.text_fields`)
- **📸** = Galeri önizleme (`Icons.photo`)
- **○** = Çekim butonu (dairesel)
- **🔄** = Kamera değiştir (`Icons.cameraswitch`)

