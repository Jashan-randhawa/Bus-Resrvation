# 📱 BusBooking Android Application Package

Official Android Trusted Web Activity (TWA) application package generated for the **Bus Reservation System** ([`https://bus-resrvation.onrender.com/`](https://bus-resrvation.onrender.com/)).

This package encapsulates the full Progressive Web App into a production-signed native Android package ready for direct sideloading or Google Play Store distribution.

---

## 📦 Package Artifacts

| File | Purpose | Size | Details |
|---|---|---|---|
| **[`BusBooking.apk`](./BusBooking.apk)** | Sideloadable Android Package | ~999 KB | Direct installation on any Android 5.0+ (API 21+) device |
| **[`BusBooking.aab`](./BusBooking.aab)** | Android App Bundle | ~1.08 MB | Production release bundle for Google Play Console |
| **[`assetlinks.json`](./assetlinks.json)** | Digital Asset Links | ~333 B | Domain verification file proving ownership |
| **[`signing.keystore`](./signing.keystore)** | Production Release Keystore | ~2.7 KB | Java Keystore (JKS) used to sign APK and AAB |
| **[`signing-key-info.txt`](./signing-key-info.txt)** | Keystore Credentials | ~440 B | Keystore passwords, alias, and signer metadata |

---

## 🚀 Installation & Testing

### Option 1: Direct Android Device Install
1. Transfer `BusBooking.apk` to your Android device via USB, Google Drive, or local download.
2. Enable **"Install from unknown sources"** in your Android Security Settings.
3. Tap `BusBooking.apk` to install.

### Option 2: ADB Command Line Install
```bash
adb install -r packages/app/BusBooking.apk
```

---

## 🔒 Digital Asset Links Verification

To eliminate the Chrome browser address bar on Android devices and run in full-screen standalone mode:

1. The file `assetlinks.json` is hosted on the live server at:
   ```
   https://bus-resrvation.onrender.com/.well-known/assetlinks.json
   ```
2. Android OS automatically fetches this file to verify that the app package `com.onrender.bus_resrvation.twa` is authorized to open `https://bus-resrvation.onrender.com/`.

---

## 🏪 Publishing to Google Play Store

1. Log into your [Google Play Console](https://play.google.com/console).
2. Create a new application named **BusBooking**.
3. Under **Production > Create new release**, upload `BusBooking.aab`.
4. The signing key credentials can be referenced in [`signing-key-info.txt`](./signing-key-info.txt).
