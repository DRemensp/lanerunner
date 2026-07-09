import type { CapacitorConfig } from '@capacitor/cli';

// The Android app loads the live game from the server (same as the old TWA),
// so there is no separate static bundle to keep in sync — but unlike a TWA it
// runs in a Capacitor WebView, which gives the page access to native plugins
// (AdMob). To ship a fully offline/bundled build instead, drop `server.url`
// and point `webDir` at a static export of the game.
const config: CapacitorConfig = {
  // Keep this equal to the existing Play listing's applicationId (and sign with
  // the same key) to UPDATE the current app in place. Change it only to publish
  // a brand-new listing.
  appId: 'com.on_forge.lanerunner.twa',
  appName: 'Neon Rail Dash',
  webDir: 'capacitor/www',
  server: {
    url: 'https://lanerunner.on-forge.com/game',
    // The game is served over HTTPS; no cleartext traffic is needed.
    cleartext: false,
  },
};

export default config;
