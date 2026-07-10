// AdMob integration for the Capacitor (Google Play) build.
//
// Everything here is a NO-OP on the web/PWA build: `Capacitor.isNativePlatform()`
// is false in a browser, so `initAds()` returns immediately and every show/ready
// helper reports "no ad". The game runs identically on the web; ads only ever
// appear inside the native Android app.
//
// Two ad types are wired up:
//   • interstitial — a full-screen ad shown between runs (every 3rd run).
//   • rewarded     — the opt-in "revive" ad (one per run).
//
// The @capacitor-community/admob plugin is loaded with a dynamic import inside
// initAds so it is only pulled in on the native platform.
//
// IMPORTANT: no static `@capacitor/core` import here. Game.vue imports this
// module statically, so a static capacitor import would weld Capacitor core
// into Game.vue's chunk — the dynamic AdMob chunk then imports the Game chunk
// for it, which strips the chunk's facade and its
// "resources/js/Pages/Game.vue" key from the Vite manifest → Laravel 500s with
// "Unable to locate file in Vite manifest". Native detection instead uses the
// window.Capacitor bridge that the Capacitor shell injects into the page —
// which is also the correct signal for a server.url-loaded app.

// Google's official TEST ad unit IDs. They serve real (test) ads on device and
// are SAFE to ship during development — using your live units on your own
// device can get the AdMob account flagged. Replace PROD ids and flip USE_TEST
// to false for release (see CAPACITOR.md).
const TEST_IDS = {
  interstitial: 'ca-app-pub-3940256099942544/1033173712',
  rewarded: 'ca-app-pub-3940256099942544/5224354917',
};

// Real AdMob ad unit IDs (app: Neon Rail Dash, ca-app-pub-6027355044047549).
// The matching App ID for AndroidManifest.xml: ca-app-pub-6027355044047549~5722748890
const PROD_IDS = {
  interstitial: 'ca-app-pub-6027355044047549/4350947157',
  rewarded: 'ca-app-pub-6027355044047549/3410647416',
};

// Flip to false once PROD_IDS are filled in and you are ready to earn.
const USE_TEST = true;

const adUnit = (kind) =>
  USE_TEST || !PROD_IDS[kind] ? TEST_IDS[kind] : PROD_IDS[kind];

let AdMob = null;
let events = null;
let ready = false;
let interstitialLoaded = false;
let rewardedLoaded = false;

export const adsSupported = () =>
  typeof window !== 'undefined' && !!window.Capacitor?.isNativePlatform?.();

export async function initAds() {
  if (!adsSupported()) return;
  try {
    const mod = await import('@capacitor-community/admob');
    AdMob = mod.AdMob;
    events = {
      Rewarded: mod.RewardAdPluginEvents?.Rewarded,
      Dismissed: mod.RewardAdPluginEvents?.Dismissed,
      FailedToShow: mod.RewardAdPluginEvents?.FailedToShow,
      InterstitialDismissed: mod.InterstitialAdPluginEvents?.Dismissed,
      InterstitialFailedToShow: mod.InterstitialAdPluginEvents?.FailedToShow,
    };
    await AdMob.initialize({ initializeForTesting: USE_TEST });

    // GDPR / UMP consent — required for EU/UK users before serving ads.
    try {
      const info = await AdMob.requestConsentInfo();
      if (info?.isConsentFormAvailable && info?.status === 'REQUIRED') {
        await AdMob.showConsentForm();
      }
    } catch {
      // Consent flow is best-effort; a failure here must not block the game.
    }

    ready = true;
    preloadInterstitial();
    preloadRewarded();
  } catch {
    ready = false;
  }
}

async function preloadInterstitial() {
  if (!ready) return;
  try {
    await AdMob.prepareInterstitial({ adId: adUnit('interstitial') });
    interstitialLoaded = true;
  } catch {
    interstitialLoaded = false;
  }
}

// Shows a full-screen ad and resolves only once the overlay is really GONE.
// The plugin's show() promise settles when the video finishes — at that point
// the end card (with its X) still covers the screen, and resuming the game
// there means playing blind behind the ad. So the Dismissed event is the only
// success signal, with a generous watchdog as the last resort so the game can
// never hang forever.
//
// Deliberately NO visibilitychange backup: app-switching DURING an ad (video
// pauses, player returns later) produces hidden→visible flips while the ad is
// still open — a backup keyed on visibility settles the promise too early and
// eats the earned reward (real bug, found on device).
function runFullScreenAd({ show, dismissed, failed, rewarded = null }) {
  return new Promise((resolve) => {
    // Interstitials have nothing to earn — they start "successful".
    let got = rewarded === null;
    let settled = false;
    let watchdog = null;
    const handles = [];
    const finish = (value) => {
      if (settled) return;
      settled = true;
      if (watchdog) clearTimeout(watchdog);
      handles.forEach((h) => h?.remove?.());
      resolve(value);
    };
    const listen = async (event, cb) => {
      if (!event) return;
      try {
        handles.push(await AdMob.addListener(event, cb));
      } catch {
        // listener registration failed — the fallbacks below still finish()
      }
    };

    if (rewarded) {
      listen(rewarded, () => {
        got = true;
      });
    }
    listen(dismissed, () => finish(got));
    listen(failed, () => finish(false));

    // Armed from the start, so even an ad that dies without any event (killed
    // activity, lost callbacks) can never strand the player on a dead button.
    // No ad runs anywhere near this long.
    watchdog = setTimeout(() => finish(got), 180000);

    show()
      .then((result) => {
        if (result) got = true;
        // Do NOT finish here — wait for Dismissed: the end card is still up.
      })
      .catch(() => finish(false));
  });
}

// Shows an interstitial if one is ready (waiting until it is closed), then
// preloads the next. Never throws.
export async function showInterstitial() {
  if (!ready || !interstitialLoaded) return;
  await runFullScreenAd({
    show: () => AdMob.showInterstitial(),
    dismissed: events.InterstitialDismissed,
    failed: events.InterstitialFailedToShow,
  });
  interstitialLoaded = false;
  preloadInterstitial();
}

async function preloadRewarded() {
  if (!ready) return;
  try {
    await AdMob.prepareRewardVideoAd({ adId: adUnit('rewarded') });
    rewardedLoaded = true;
  } catch {
    rewardedLoaded = false;
  }
}

// True only when a rewarded ad is loaded and can be shown right now — used to
// decide whether to offer the revive button.
export const rewardedReady = () => ready && rewardedLoaded;

// Shows the rewarded ad. Resolves TRUE only if the player earned the reward
// AND the ad overlay is fully closed — never resumes the game behind an ad.
export async function showRewarded() {
  if (!rewardedReady()) return false;

  const earned = await runFullScreenAd({
    show: () => AdMob.showRewardVideoAd(),
    dismissed: events.Dismissed,
    failed: events.FailedToShow,
    rewarded: events.Rewarded,
  });

  rewardedLoaded = false;
  preloadRewarded();
  return earned;
}
