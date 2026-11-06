# QRONEX employee app (Flutter)

Scan the entrance QR display to punch IN/OUT. Talks to the QRONEX server at `{server}/api/v1`.

## Build on Windows (one-time setup)

1. Install the **Flutter SDK** (flutter.dev → Get started → Windows → Android) and **Android Studio** (with Android SDK + an emulator, or a phone with USB debugging on). Run `flutter doctor` until Android is ✓.
2. Generate the platform folders (keeps the files already here):
   ```
   cd C:\laragon\www\QRONEX\mobile
   flutter create --org com.ametecs --project-name qronex_app --platforms android,ios .
   flutter pub get
   flutter analyze
   ```
   If the Android build complains about minSdk, set `minSdk = 21` in `android/app/build.gradle.kts` (or `build.gradle`).

## Run against your PC

1. Start the server so phones on the same Wi-Fi can reach it:
   ```
   cd C:\laragon\www\QRONEX
   php artisan serve --host=0.0.0.0 --port=8000
   ```
   Allow port 8000 in Windows Firewall (Inbound rule → TCP 8000) the first time.
2. Find the PC's LAN IP (`ipconfig` → IPv4, e.g. 192.168.1.10). In the app, Server address = `http://192.168.1.10:8000`.
3. `flutter run` (phone connected) or `flutter build apk --release` → `build\app\outputs\flutter-apk\app-release.apk`.

Plain HTTP is allowed only for development (`usesCleartextTraffic` in AndroidManifest.xml). Remove it once the server runs on HTTPS.

## iOS

Needs a Mac with Xcode. Add to `ios/Runner/Info.plist`:
```
<key>NSCameraUsageDescription</key><string>Scan the QRONEX attendance QR code.</string>
<key>NSLocationWhenInUseUsageDescription</key><string>Confirm you are at the office when you punch attendance.</string>
```

## What the app does

- **Scan QR to punch IN/OUT** at the entrance display (11 server checks).
- **Remote check-in / checkout** when an administrator has approved work from home, field duty or client-site work for today.
- **Requests** (menu): ask for remote / field duty, ask for a missed-punch correction, see the status of your requests.
- **My attendance**: last 30 days; corrections show as *adjusted*.
- A new phone while the old one is still approved becomes a *replacement* request — approving it signs the old phone out.

## Release build (Play Store / company distribution)

1. **Server pinned, HTTPS only.** Build with the production address so employees never type it:
   `flutter build appbundle --release --dart-define=QRONEX_SERVER=https://qronex.app`
   (APK for direct install: `flutter build apk --release --dart-define=QRONEX_SERVER=https://qronex.app`)
2. **Remove cleartext HTTP** before release: delete `android:usesCleartextTraffic="true"` from `android/app/src/main/AndroidManifest.xml`.
3. **Signing key (once, keep it safe — losing it means you can never update the app):**
   `keytool -genkey -v -keystore %USERPROFILE%\qronex-upload.jks -keyalg RSA -keysize 2048 -validity 10000 -alias qronex`
   Create `android/key.properties` (never commit it):
   ```
   storePassword=…
   keyPassword=…
   keyAlias=qronex
   storeFile=C:\\Users\\<you>\\qronex-upload.jks
   ```
   Then in `android/app/build.gradle.kts` load it and set `signingConfigs.release` — follow
   https://docs.flutter.dev/deployment/android#configure-signing-in-gradle
4. **Version:** bump `version:` in `pubspec.yaml` (e.g. `0.2.0+2`) for every upload.
5. Play Console: app name QRONEX, package `com.ametecs.qronex_app`, privacy policy URL (needed because the app uses location + camera), data-safety form: location (precise, at punch time only), device ID (install reference).
