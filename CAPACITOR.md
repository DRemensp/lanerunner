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

## Native project status (already done, committed in `android/`)

- `npx cap add android` has been run — the `android/` Gradle project is in the
  repo (Capacitor treats it as source, not a build artifact).
- The real AdMob **App ID** (`ca-app-pub-6027355044047549~5722748890`) is
  already in `android/app/src/main/AndroidManifest.xml`.
- Both real **ad-unit IDs** are in `PROD_IDS` in `resources/js/game/ads.js`.
- Package id: `com.onforge.lanerunner` (fresh Play listing; the old
  `…lanerunner.twa` id was never published, so it was free to replace).

After changing web code, plugins, or `capacitor.config.ts`, run:

```bash
npx cap sync android
```

## Build & run (needs Android Studio)

```bash
npx cap open android        # opens Android Studio → Run on device/emulator
```

With `USE_TEST = true` (current state) the app shows Google's official test
ads — safe for your account, full flow testable.

## Going live (flip from test ads to real money)

1. In `resources/js/game/ads.js`: set `USE_TEST = false` (the real ad-unit IDs
   are already in `PROD_IDS`).
2. Deploy the website — the app loads the game remotely, so the flip ships via
   Forge, no app rebuild needed.

> Keep `USE_TEST = true` while developing. Showing **live** ads on your own
> device can get the AdMob account flagged.

## Play listing / signing

First upload creates a new listing under `com.onforge.lanerunner` — this id can
NEVER change afterwards. Build a signed bundle in Android Studio (Build →
Generate Signed App Bundle; create a new keystore and BACK IT UP) or use Play
App Signing. Remember: mark "Contains ads" + fill the Data safety form in the
Play Console, and link the app to the AdMob entry once it's live.

## Notes

- Android 13+ needs the `com.google.android.gms.permission.AD_ID` permission —
  the AdMob plugin adds it automatically via `cap sync`.
- The app updates gameplay by loading the live site, so most changes ship by
  deploying the web build; rebuild the Android app only when the Capacitor
  config, plugins, or ad IDs change.
- Rewarded revive is offered only in the run and fly zones; zone 2 keeps its
  existing free "eject into a car" second chance.
