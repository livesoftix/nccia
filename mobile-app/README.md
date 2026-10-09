# 📱 NCCIA CMS - Native Android Mobile Application

This directory contains the complete, dedicated **Android Native Mobile Application** for the **National Cyber Crime Investigation Agency (NCCIA) Case Management System**.

- **Package Name / App ID:** `pk.gov.nccia.cms`
- **Application Name:** `NCCIA CMS`
- **Version:** `v2.4.1 (Enterprise Production)`
- **Framework:** Capacitor Native Android + Android Gradle Toolchain
- **Developer / Architect:** Engr. Humayun (LiveSoftix Software Engineering)

---

## 📂 Directory Structure

```text
mobile-app/
├── android/                         # Complete Native Android Studio Project
│   ├── app/                         # App module, Java sources, AndroidManifest.xml
│   │   └── src/main/
│   │       ├── AndroidManifest.xml  # Hardware permissions (Camera, Voice, Biometrics)
│   │       ├── java/pk/gov/nccia/cms/MainActivity.java
│   │       └── res/                 # App icons (mipmap) and Splash screens
│   ├── build.gradle                 # Project Gradle script
│   ├── gradlew / gradlew.bat        # Gradle wrapper binaries
│   └── settings.gradle              # Gradle modules configuration
├── capacitor.config.json            # Capacitor bridge configuration
├── package.json                     # Mobile app dependencies & sync scripts
└── README.md                        # Documentation & build instructions
```

---

## 🚀 How to Build & Install the APK

### Method 1: Automated Cloud Build via GitHub Actions (Zero Local Setup)
1. Go to the GitHub repository: `https://github.com/livesoftix/NCCIA/actions`
2. Select **"Build NCCIA Android Mobile App (APK)"** workflow.
3. Click **"Run workflow"** (or it builds automatically on every push to `main`).
4. Once completed (approx. 2-3 minutes), download the generated **`NCCIA-CMS-Mobile-App-v2.4.1.zip`** from the **Artifacts** section.
5. Extract and install `NCCIA-CMS-v2.4.1.apk` on any Android device!

### Method 2: Open in Android Studio (Local Development)
1. Open **Android Studio**.
2. Click **Open** and select the folder: `mobile-app/android`.
3. Wait for Gradle sync to complete.
4. Click **Run** (or press `Shift + F10`) to launch on an emulator or physical Android phone connected via USB.
5. To generate a signed APK or release APK in Android Studio:
   `Build` -> `Build Bundle(s) / APK(s)` -> `Build APK(s)`.

### Method 3: Command Line APK Build
From the `mobile-app/android` directory:
```bash
# Windows
gradlew.bat assembleDebug

# Linux / macOS
chmod +x gradlew
./gradlew assembleDebug
```
The compiled APK will be at:
`mobile-app/android/app/build/outputs/apk/debug/app-debug.apk`

---

## 🔐 Configured Hardware Permissions
- 📷 **Camera**: Instant capture of spot verifications, physical evidence, and seized digital devices.
- 🎙️ **Microphone / Voice**: Recording audio statements of witnesses and accused persons.
- 🔒 **Biometric Security**: Fingerprint / Face ID login.
- 🌐 **Network & Offline**: Real-time sync with API server and offline connection diagnostics.
- 💾 **Storage**: Saving and exporting official PDF verification reports, FIRs, and DSRs directly to mobile storage.
