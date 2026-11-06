# QRONEX employee app 1.0 (Android, Kotlin)

The phone app employees use to punch IN/OUT by scanning the entrance QR. It talks to the QRONEX server at `{server}/api/v1`, using the same API as the old `android-app` (0.3.0). This app replaces the old one.

## Screens

- **Activate**: company code, employee code and the one-time activation code from the portal's **Employees → Activation code** button. The phone makes its own Keystore key during activation.
- **Home**: greeting, live clock, today's check-in / check-out / time worked, and the big **Punch** button (scan → location → verify → result). An approved remote permission adds a **Check IN remotely** card. A phone waiting for approval shows its approval code instead.
- **Result**: an animated tick or cross, the punch time, office, gate, distance and reference, then the server's security checks shown one by one. A failed punch shows a hint for the reason and a **Try again** button.
- **Attendance**: the last 30 days grouped by day, with hours worked per day. Corrected and remote punches are marked.
- **Requests**: ask to work remotely (WFH, field duty, client site, temporary remote), or fix a missed punch. The list shows each request's status.
- **Profile**: employee, company, phone status and security key, permissions, and sign out.

## Security

- Every request is signed with an ECDSA P-256 key held in the Android Keystore. The key can't be copied off the phone. This is the same scheme as ROVEXY; see `app/Support/DeviceSignature.php` on the server.
- Release builds are HTTPS only. Plain HTTP is allowed in debug builds so you can test against your PC.
- `allowBackup=false`, so the sign-in is never restored onto another phone.

## Build and run (Android Studio)

1. **File → Open** → `C:\laragon\www\QRONEX\android`, then wait for Gradle sync. The first sync downloads the Kotlin plugin.
2. Pick the emulator or a USB phone, then click **Run ▶**.
3. Activate:
   - **Live server** (default `https://qronex.app`): send yourself an activation code from the portal.
   - **Your PC**: in debug builds, tap **Advanced: server address** and enter `http://10.0.2.2:8000` (emulator) or `http://<PC IP>:8000` (phone on the same Wi-Fi). Run `php artisan serve --host=0.0.0.0 --port=8000` first.

APK for direct install: **Build → Build App Bundle(s) / APK(s) → Build APK(s)**. The file is `app\build\outputs\apk\debug\app-debug.apk`.

## Before the Play Store

1. Make an upload key once and keep it safe. If you lose it, you can never update the app:
   `keytool -genkey -v -keystore %USERPROFILE%\qronex-upload.jks -keyalg RSA -keysize 2048 -validity 10000 -alias qronex`
2. In `app/build.gradle`, replace `signingConfig signingConfigs.debug` with a release signing config that uses that key.
3. Increase `versionCode` and `versionName` for every upload.
4. Play Console needs a privacy policy URL (the app uses location and camera) and the data-safety form: precise location at punch time only, and an app-generated install ID.

## Notes

- The source lives in `app/src/main/kotlin/`, with no package folders, because the Claude desktop bridge only reaches 7 folders deep. Kotlin doesn't need the folders to match the package.
- The package is `com.ametecs.qronex` with versionCode 10, so it installs over the old 0.3.0 app. Employees activate once more because the new app keeps its own sign-in and key.
