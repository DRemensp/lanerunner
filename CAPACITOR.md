# Capacitor + AdMob (Google Play build)

The Play Store app is a **Capacitor** wrapper that loads the live game
(`https://lanerunner.on-forge.com/game`) in a native WebView — same content as
the old Bubblewrap TWA, but with native plugin access so **AdMob** works. The
web/PWA build is untouched: all ad code is a no-op in a browser
(`Capacitor.isNativePlatform()` is false).

## What's already wired up

- `capacitor.config.ts` — app id, name, and `server.url` (the live game).
- `resources/js/game/ads.js` — AdMob init, GDPR/UMP consent, interstitial, rewarded.
- `Game.vue`:
  - **Interstitial** every 3rd completed run, shown on "Run Again" (never over the death stats).
  - **Rewarded revive**, one per run: green button on the death screen, resumes the run with a 3s grace. The run is persisted **once**, at the true end — a revive defers it so the longer score still ranks.

## One-time native setup (on your machine, needs Android Studio)

```bash
npm install                 # pulls the Capacitor + AdMob packages
npx cap add android         # scaffolds the android/ Gradle project
npx cap sync                # copies config + installs the AdMob native plugin
```

### 1. Add your AdMob **App ID** (required — the app crashes on launch without it)

The App ID is NOT the same as an ad-unit ID. In
`android/app/src/main/AndroidManifest.xml`, inside `<application>`:

```xml
<meta-data
    android:name="com.google.android.gms.ads.APPLICATION_ID"
    android:value="ca-app-pub-XXXXXXXXXXXXXXXX~YYYYYYYYYY"/>
```

For development you can use Google's **test App ID**:
`ca-app-pub-3940256099942544~3347511713`.

### 2. Build & run

```bash
npx cap open android        # opens Android Studio → Run on device/emulator
```

## Going live (flip from test ads to real money)

In `resources/js/game/ads.js`:

1. Paste your real ad-unit IDs into `PROD_IDS` (`interstitial`, `rewarded`).
2. Set `USE_TEST = false`.
3. Put your real AdMob **App ID** in the manifest (step 1 above).
4. Redeploy the web build (the app loads it remotely) and rebuild the Android app.

> Keep `USE_TEST = true` while developing. Showing **live** ads on your own
> device can get the AdMob account flagged. The defaults are Google's official
> **test** ad units, which serve safe placeholder ads.

## Play listing / signing

`appId` in `capacitor.config.ts` is set to the existing TWA package
(`com.on_forge.lanerunner.twa`). To **replace the current listing in place**,
keep this id, sign with the **same** keystore as the TWA, and bump the
`versionCode`. To publish a fresh listing instead, change the `appId`.

## Notes

- Android 13+ needs the `com.google.android.gms.permission.AD_ID` permission —
  the AdMob plugin adds it automatically via `cap sync`.
- The app updates gameplay by loading the live site, so most changes ship by
  deploying the web build; rebuild the Android app only when the Capacitor
  config, plugins, or ad IDs change.
- Rewarded revive is offered only in the run and fly zones; zone 2 keeps its
  existing free "eject into a car" second chance.
