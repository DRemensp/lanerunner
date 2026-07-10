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

// TODO before going live: paste your real AdMob ad unit IDs here.
const PROD_IDS = {
  interstitial: '',
  rewarded: '',
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

// Shows an interstitial if one is ready, then preloads the next. Never throws.
export async function showInterstitial() {
  if (!ready || !interstitialLoaded) return;
  try {
    await AdMob.showInterstitial();
  } catch {
    // ignore — a failed show should never interrupt the run flow
  }
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

// Shows the rewarded ad. Resolves TRUE only if the player watched to completion
// and earned the reward; FALSE if they skipped, it failed, or none was ready.
export async function showRewarded() {
  if (!rewardedReady()) return false;

  const earned = await new Promise((resolve) => {
    let got = false;
    const handles = [];
    let settled = false;
    const finish = (value) => {
      if (settled) return;
      settled = true;
      handles.forEach((h) => h?.remove?.());
      resolve(value);
    };
    const listen = async (event, cb) => {
      if (!event) return;
      try {
        handles.push(await AdMob.addListener(event, cb));
      } catch {
        // listener registration failed — finish() fallbacks still apply
      }
    };

    listen(events.Rewarded, () => {
      got = true;
    });
    listen(events.Dismissed, () => finish(got));
    listen(events.FailedToShow, () => finish(false));

    AdMob.showRewardVideoAd()
      .then((result) => {
        // Some plugin versions resolve with the reward object on completion and
        // never fire Dismissed — treat a truthy result as earned.
        if (result) got = true;
        finish(got);
      })
      .catch(() => finish(false));
  });

  rewardedLoaded = false;
  preloadRewarded();
  return earned;
}
