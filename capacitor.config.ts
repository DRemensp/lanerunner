import type { CapacitorConfig } from '@capacitor/cli';

// The Android app loads the live game from the server (same as the old TWA),
// so there is no separate static bundle to keep in sync — but unlike a TWA it
// runs in a Capacitor WebView, which gives the page access to native plugins
// (AdMob). To ship a fully offline/bundled build instead, drop `server.url`
// and point `webDir` at a static export of the game.
const config: CapacitorConfig = {
  // Package id of the (future) Play listing. Nothing is published yet, so this
  // was free to choose — after the FIRST Play upload it can never change.
  appId: 'com.onforge.lanerunner',
  appName: 'Neon Rail Dash',
  webDir: 'capacitor/www',
  server: {
    url: 'https://lanerunner.on-forge.com/game',
    // The game is served over HTTPS; no cleartext traffic is needed.
    cleartext: false,
  },
};

export default config;
