// Audio subsystem: music playlist with cross-fades, a tiny WebAudio SFX synth
// (no audio assets needed), and the looping zone-2 engine drone. Owns all
// audio refs/state; Game.vue wires it up once via createAudioSystem().
import { ref, computed, watch } from 'vue';
import { MathUtils } from 'three';
import { driveMaxSpeed } from './constants';

// Cover art lives in /audio/covers/<id>.<ext>, extracted once from the
// MP3s' ID3 APIC frames (a few are PNG, the rest JPEG).
const buildTrack = (id, title, file, coverExt = 'jpg') => ({
  id,
  title,
  src: encodeURI(`/audio/${file}`),
  cover: `/audio/covers/${id}.${coverExt}`,
  enabled: true,
});

export function createAudioSystem({ state, speed }) {
  const audioVolume = ref(0.3);
  const isMuted = ref(false);
  const isPaused = ref(false);
  const currentTrackName = ref('');
  const currentTrackCover = ref('');
  const showTrackToast = ref(false);
  const showPlaylist = ref(false);

  // All tracks from Uppbeat (credits + license codes in Settings → Music).
  const tracks = ref([
    buildTrack('stuzzy-fonk', 'Stuzzy Fonk', 'Hey Pluto - Stuzzy Fonk.mp3'),
    buildTrack('best-friend', 'Best Friend', 'Kasseo - Best Friend.mp3'),
    buildTrack('never-wanna-stop', 'Never Wanna Stop Loving You', 'All Good Folks - Never Wanna Stop Loving You.mp3'),
    buildTrack('magic', 'Magic', 'Mood Maze - Magic.mp3'),
    buildTrack('do-it', 'Do It', 'Aylex - Do It.mp3'),
    buildTrack('superfly', 'Superfly Get Happy', 'Barry Dallas - Superfly Get Happy.mp3'),
    buildTrack('always', 'Always', 'Distrxct - Always.mp3'),
    buildTrack('come-down', 'Come Down', 'Kula - Come Down.mp3'),
    buildTrack('come-on-lets-go', "Come On Let's Go", 'Ra - Come On Lets Go.mp3'),
    buildTrack('sunshine', 'Sunshine', 'All Good Folks - Sunshine.mp3'),
    buildTrack('dont-stop', "Don't Stop Regardless", 'Mazbou Q - Dont Stop Regardless.mp3'),
  ]);

  const playlistTracks = computed(() => tracks.value);

  let audioPrimary;
  let audioSecondary;
  let activeAudio;
  let inactiveAudio;
  let currentTrack = null;
  let shuffleQueue = [];
  let playHistory = [];
  let audioUnlocked = false;
  let fadeToken = 0;
  let toastTimer;

  let sfxCtx = null;
  let sfxGain = null;
  let engineOsc = null;
  let engineGain = null;

  // Music ducks under loud game moments (finale, launch) and recovers slowly.
  let musicDuckTarget = 1;
  let musicDuckCurrent = 1;

  const loadAudioPrefs = () => {
    const storedVolume = Number.parseFloat(localStorage.getItem('runner_audio_volume') || '0.3');
    audioVolume.value = Number.isFinite(storedVolume) ? storedVolume : 0.3;
    isMuted.value = localStorage.getItem('runner_audio_muted') === '1';

    const disabled = localStorage.getItem('runner_audio_disabled');
    if (disabled) {
      const disabledIds = JSON.parse(disabled);
      if (Array.isArray(disabledIds)) {
        tracks.value = tracks.value.map((track) => ({
          ...track,
          enabled: !disabledIds.includes(track.id),
        }));
      }
    }
  };

  const persistAudioPrefs = () => {
    localStorage.setItem('runner_audio_volume', String(audioVolume.value));
    localStorage.setItem('runner_audio_muted', isMuted.value ? '1' : '0');
    const disabledIds = tracks.value.filter((track) => !track.enabled).map((track) => track.id);
    localStorage.setItem('runner_audio_disabled', JSON.stringify(disabledIds));
  };

  // Tiny WebAudio synth for game SFX — no audio assets needed.
  const getSfxContext = () => {
    const Ctx = window.AudioContext || window.webkitAudioContext;
    if (!Ctx) return null;
    if (!sfxCtx) {
      sfxCtx = new Ctx();
      sfxGain = sfxCtx.createGain();
      sfxGain.connect(sfxCtx.destination);
    }
    if (sfxCtx.state === 'suspended') {
      sfxCtx.resume().catch(() => {});
    }
    sfxGain.gain.value = isMuted.value ? 0 : audioVolume.value * 0.5;
    return sfxCtx;
  };

  const playTone = ({ freq = 440, to = null, type = 'sine', duration = 0.12, delay = 0, gain = 0.4 }) => {
    const ctx = getSfxContext();
    if (!ctx) return;
    const t0 = ctx.currentTime + delay;
    const osc = ctx.createOscillator();
    const env = ctx.createGain();
    osc.type = type;
    osc.frequency.setValueAtTime(freq, t0);
    if (to) {
      osc.frequency.exponentialRampToValueAtTime(to, t0 + duration);
    }
    env.gain.setValueAtTime(0.0001, t0);
    env.gain.exponentialRampToValueAtTime(gain, t0 + 0.012);
    env.gain.exponentialRampToValueAtTime(0.0001, t0 + duration);
    osc.connect(env).connect(sfxGain);
    osc.start(t0);
    osc.stop(t0 + duration + 0.05);
  };

  const playNoise = ({ duration = 0.3, from = 1200, to = 200, gain = 0.35, delay = 0 }) => {
    const ctx = getSfxContext();
    if (!ctx) return;
    const t0 = ctx.currentTime + delay;
    const length = Math.max(1, Math.floor(ctx.sampleRate * duration));
    const buffer = ctx.createBuffer(1, length, ctx.sampleRate);
    const data = buffer.getChannelData(0);
    for (let i = 0; i < length; i += 1) {
      data[i] = (Math.random() * 2 - 1) * (1 - i / length);
    }
    const source = ctx.createBufferSource();
    source.buffer = buffer;
    const filter = ctx.createBiquadFilter();
    filter.type = 'lowpass';
    filter.frequency.setValueAtTime(from, t0);
    filter.frequency.exponentialRampToValueAtTime(Math.max(40, to), t0 + duration);
    const env = ctx.createGain();
    env.gain.setValueAtTime(gain, t0);
    env.gain.exponentialRampToValueAtTime(0.0001, t0 + duration);
    source.connect(filter).connect(env).connect(sfxGain);
    source.start(t0);
  };

  const sfx = {
    jump: () => playTone({ freq: 320, to: 620, type: 'triangle', duration: 0.16, gain: 0.3 }),
    coin: () => {
      playTone({ freq: 880, duration: 0.07, gain: 0.25 });
      playTone({ freq: 1318, duration: 0.1, delay: 0.06, gain: 0.25 });
    },
    nearMiss: () => playNoise({ duration: 0.25, from: 2400, to: 400, gain: 0.28 }),
    slide: () => playNoise({ duration: 0.28, from: 1600, to: 250, gain: 0.22 }),
    bump: () => {
      playNoise({ duration: 0.16, from: 420, to: 120, gain: 0.32 });
      playTone({ freq: 150, to: 85, type: 'square', duration: 0.14, gain: 0.2 });
    },
    door: () => {
      playNoise({ duration: 0.09, from: 900, to: 300, gain: 0.22 });
      playTone({ freq: 110, to: 70, type: 'sine', duration: 0.12, gain: 0.28 });
    },
    engineStart: () => {
      playTone({ freq: 55, to: 140, type: 'sawtooth', duration: 0.7, gain: 0.22 });
      playNoise({ duration: 0.5, from: 300, to: 900, gain: 0.12, delay: 0.15 });
    },
    rev: () => playTone({ freq: 70, to: 150, type: 'sawtooth', duration: 0.35, gain: 0.16 }),
    brakeScreech: () => playNoise({ duration: 0.45, from: 2600, to: 900, gain: 0.22 }),
    horn: () => {
      const base = 400 + Math.random() * 80;
      playTone({ freq: base, type: 'square', duration: 0.3, gain: 0.15 });
      playTone({ freq: base * 1.26, type: 'square', duration: 0.3, gain: 0.15 });
    },
    siren: () => {
      // German Martinshorn: two alternating fourths (a'/d''), three cycles.
      for (let i = 0; i < 6; i += 1) {
        playTone({ freq: i % 2 ? 587 : 440, type: 'square', duration: 0.26, delay: i * 0.28, gain: 0.1 });
      }
    },
    godMode: () => {
      [220, 330, 440, 660, 880].forEach((freq, i) =>
        playTone({ freq, type: 'sawtooth', duration: 0.22, delay: i * 0.07, gain: 0.22 }),
      );
      playNoise({ duration: 0.8, from: 500, to: 3200, gain: 0.2 });
    },
    smash: () => {
      playNoise({ duration: 0.3, from: 1800, to: 200, gain: 0.4 });
      playTone({ freq: 90, to: 45, type: 'square', duration: 0.25, gain: 0.3 });
    },
    launch: () => {
      playNoise({ duration: 0.7, from: 700, to: 2600, gain: 0.3 });
      playTone({ freq: 120, to: 320, type: 'sawtooth', duration: 0.6, gain: 0.2 });
    },
    dock: () => {
      playNoise({ duration: 0.22, from: 1200, to: 250, gain: 0.34 });
      playTone({ freq: 75, to: 45, type: 'square', duration: 0.2, gain: 0.3 });
    },
    shoot: () => playTone({ freq: 880 + Math.random() * 160, to: 320, type: 'square', duration: 0.09, gain: 0.1 }),
    enemyShoot: () => playTone({ freq: 260, to: 110, type: 'sawtooth', duration: 0.18, gain: 0.16 }),
    enemyDash: () => {
      // Quick thruster whoosh as an alien jukes sideways.
      playNoise({ duration: 0.16, from: 320, to: 1600, gain: 0.14 });
      playTone({ freq: 180, to: 420, type: 'triangle', duration: 0.14, gain: 0.1 });
    },
    shotgun: () => {
      playNoise({ duration: 0.3, from: 1400, to: 300, gain: 0.3 });
      playTone({ freq: 180, to: 90, type: 'sawtooth', duration: 0.25, gain: 0.2 });
    },
    motherSpawn: () => {
      [70, 55, 85, 65].forEach((freq, i) =>
        playTone({ freq, type: 'sawtooth', duration: 0.5, delay: i * 0.3, gain: 0.24 }),
      );
      playNoise({ duration: 1.2, from: 200, to: 900, gain: 0.16 });
    },
    shieldUp: () => playTone({ freq: 420, to: 980, type: 'sine', duration: 0.5, gain: 0.2 }),
    absorb: () => playTone({ freq: 700, to: 220, type: 'sine', duration: 0.35, gain: 0.18 }),
    playerHit: () => {
      playNoise({ duration: 0.25, from: 900, to: 150, gain: 0.4 });
      playTone({ freq: 140, to: 70, type: 'square', duration: 0.22, gain: 0.26 });
    },
    enemyDown: () => {
      playNoise({ duration: 0.35, from: 1500, to: 150, gain: 0.35 });
      playTone({ freq: 200, to: 60, type: 'sawtooth', duration: 0.3, gain: 0.22 });
    },
    land: () => {
      playNoise({ duration: 0.12, from: 500, to: 140, gain: 0.22 });
      playTone({ freq: 95, to: 55, type: 'sine', duration: 0.12, gain: 0.25 });
    },
    carPass: (lateral = 2) => {
      const closeness = Math.max(0.15, 1 - lateral / 4);
      playNoise({ duration: 0.4, from: 1000, to: 160, gain: 0.3 * closeness });
      playTone({
        freq: 130,
        to: 70,
        type: 'sawtooth',
        duration: 0.38,
        gain: 0.12 * closeness,
      });
    },
    powerup: () => {
      [523, 659, 784, 1046].forEach((freq, i) =>
        playTone({ freq, type: 'triangle', duration: 0.09, delay: i * 0.07, gain: 0.26 }),
      );
    },
    shieldBreak: () => {
      playNoise({ duration: 0.2, from: 3000, to: 800, gain: 0.32 });
      playTone({ freq: 220, to: 120, type: 'sawtooth', duration: 0.25, gain: 0.22 });
    },
    crash: () => {
      playNoise({ duration: 0.5, from: 900, to: 90, gain: 0.55 });
      playTone({ freq: 160, to: 50, type: 'sawtooth', duration: 0.5, gain: 0.35 });
    },
  };

  // Looping engine drone for zone 2 — pitch and volume follow the car speed.
  const updateEngineSound = () => {
    const ctx = getSfxContext();
    if (!ctx) return;
    if (!engineOsc) {
      engineOsc = ctx.createOscillator();
      engineOsc.type = 'sawtooth';
      engineGain = ctx.createGain();
      engineGain.gain.value = 0;
      engineOsc.connect(engineGain).connect(sfxGain);
      engineOsc.start();
    }
    engineGain.gain.setTargetAtTime(
      0.045 + (speed.value / driveMaxSpeed) * 0.06,
      ctx.currentTime,
      0.1,
    );
    engineOsc.frequency.setTargetAtTime(38 + speed.value * 1.9, ctx.currentTime, 0.08);
  };

  const silenceEngineSound = () => {
    if (engineGain && sfxCtx) {
      engineGain.gain.setTargetAtTime(0, sfxCtx.currentTime, 0.15);
    }
  };

  const isEngineActive = () => Boolean(engineOsc);

  const initAudio = () => {
    if (audioPrimary) return;
    audioPrimary = new Audio();
    audioSecondary = new Audio();
    [audioPrimary, audioSecondary].forEach((audio) => {
      audio.preload = 'auto';
      audio.loop = false;
    });
    activeAudio = audioPrimary;
    inactiveAudio = audioSecondary;
  };

  // HTMLMediaElement throws on volume outside [0,1] — always clamp.
  const clampVolume = (value) => Math.min(1, Math.max(0, value));

  const applyAudioVolume = () => {
    const base = clampVolume((isMuted.value ? 0 : audioVolume.value) * musicDuckCurrent);
    if (activeAudio) {
      activeAudio.volume = base;
    }
    if (inactiveAudio) {
      inactiveAudio.volume = 0;
    }
  };

  const setMusicDuck = (target) => {
    musicDuckTarget = target;
  };

  // Called once per frame: eases the duck level toward its target.
  const updateMusicDuck = (delta) => {
    if (Math.abs(musicDuckCurrent - musicDuckTarget) > 0.005) {
      musicDuckCurrent = MathUtils.clamp(
        MathUtils.damp(musicDuckCurrent, musicDuckTarget, 1.2, delta),
        0,
        1,
      );
      applyAudioVolume();
    }
  };

  const triggerTrackToast = (track) => {
    currentTrackName.value = track.title;
    currentTrackCover.value = track.cover;
    showTrackToast.value = true;
    if (toastTimer) {
      clearTimeout(toastTimer);
    }
    toastTimer = setTimeout(() => {
      showTrackToast.value = false;
    }, 2600);
  };

  const getEnabledTracks = () => tracks.value.filter((track) => track.enabled);

  const shuffleList = (list) => {
    const array = [...list];
    for (let i = array.length - 1; i > 0; i -= 1) {
      const j = Math.floor(Math.random() * (i + 1));
      [array[i], array[j]] = [array[j], array[i]];
    }
    return array;
  };

  const rebuildShuffleQueue = () => {
    const list = getEnabledTracks();
    if (!list.length) {
      shuffleQueue = [];
      return;
    }

    const pool = [...list];
    if (pool.length > 1 && currentTrack) {
      const lastIndex = pool.findIndex((track) => track.id === currentTrack.id);
      if (lastIndex !== -1) {
        const [last] = pool.splice(lastIndex, 1);
        shuffleQueue = shuffleList(pool);
        shuffleQueue.push(last);
        return;
      }
    }

    shuffleQueue = shuffleList(pool);
  };

  const pickNextTrack = () => {
    const list = getEnabledTracks();
    if (!list.length) return null;
    // Very first play ever (fresh device): open with the signature track
    // instead of a random one. Shuffle takes over from track two on.
    if (!localStorage.getItem('runner_music_opened')) {
      localStorage.setItem('runner_music_opened', '1');
      const opener = list.find((track) => track.id === 'best-friend');
      if (opener) {
        if (!shuffleQueue.length) {
          rebuildShuffleQueue();
        }
        shuffleQueue = shuffleQueue.filter((track) => track.id !== opener.id);
        return opener;
      }
    }
    if (!shuffleQueue.length) {
      rebuildShuffleQueue();
    }
    if (!shuffleQueue.length) {
      return list[0];
    }
    return shuffleQueue.shift();
  };

  const rememberTrack = (track) => {
    if (!track) return;
    if (!playHistory.length || playHistory[playHistory.length - 1].id !== track.id) {
      playHistory.push(track);
      if (playHistory.length > 40) {
        playHistory.shift();
      }
    }
  };

  const setActiveTrack = async (track, { recordHistory = true } = {}) => {
    if (!track) return;
    initAudio();
    const token = ++fadeToken;
    const baseVolume = (isMuted.value ? 0 : audioVolume.value) * musicDuckCurrent;
    const nextAudio = inactiveAudio;
    nextAudio.src = track.src;
    nextAudio.currentTime = 0;
    nextAudio.volume = 0;

    try {
      await nextAudio.play();
    } catch (error) {
      return;
    }

    if (token !== fadeToken) {
      nextAudio.pause();
      return;
    }

    const prevAudio = activeAudio;
    const fadeStart = performance.now();
    const fadeDuration = 700;

    const step = (now) => {
      if (token !== fadeToken) return;
      const t = Math.min(1, Math.max(0, (now - fadeStart) / fadeDuration));
      if (prevAudio) {
        prevAudio.volume = clampVolume(baseVolume * (1 - t));
      }
      nextAudio.volume = clampVolume(baseVolume * t);
      if (t < 1) {
        requestAnimationFrame(step);
      } else {
        if (prevAudio) {
          prevAudio.pause();
          prevAudio.currentTime = 0;
        }
        activeAudio = nextAudio;
        inactiveAudio = prevAudio;
        currentTrack = track;
        if (recordHistory) {
          rememberTrack(track);
        }
        activeAudio.onended = () => {
          if (!isPaused.value) {
            playNext(true);
          }
        };
        if (isPaused.value) {
          activeAudio.pause();
        }
        triggerTrackToast(track);
      }
    };

    requestAnimationFrame(step);
  };

  const syncPlaylist = (forceStart = false) => {
    if (!audioUnlocked && !forceStart) return;
    const list = getEnabledTracks();
    if (!list.length) {
      if (activeAudio) {
        activeAudio.pause();
      }
      return;
    }

    if (!currentTrack || !currentTrack.enabled) {
      shuffleQueue = [];
      const next = pickNextTrack();
      if (next) {
        setActiveTrack(next);
      }
    }
  };

  const unlockAudio = () => {
    if (audioUnlocked) return;
    audioUnlocked = true;
    syncPlaylist(true);
  };

  // Background behavior: switching tabs, minimizing the window or leaving
  // the app (TWA) pauses the music AND the WebAudio context (engine drone).
  // On return the music only resumes if it was playing before and the user
  // didn't pause it themselves. blur/focus in addition to visibilitychange:
  // an alt-tabbed window stays "visible" but should still go silent.
  let pausedByBackground = false;
  const handleBackgroundChange = () => {
    const inBackground = document.hidden || !document.hasFocus();
    if (inBackground) {
      if (activeAudio && !activeAudio.paused) {
        pausedByBackground = true;
      }
      // Abort a running crossfade — rAF freezes in the background anyway.
      fadeToken += 1;
      if (activeAudio) activeAudio.pause();
      if (inactiveAudio) inactiveAudio.pause();
      if (sfxCtx && sfxCtx.state === 'running') {
        sfxCtx.suspend().catch(() => {});
      }
    } else {
      if (sfxCtx && sfxCtx.state === 'suspended') {
        sfxCtx.resume().catch(() => {});
      }
      if (pausedByBackground && activeAudio && !isPaused.value) {
        applyAudioVolume();
        activeAudio.play().catch(() => {});
      }
      pausedByBackground = false;
    }
  };
  document.addEventListener('visibilitychange', handleBackgroundChange);
  window.addEventListener('blur', handleBackgroundChange);
  window.addEventListener('focus', handleBackgroundChange);

  const handleAudioButton = () => {
    unlockAudio();
    if (state.value === 'running') {
      isMuted.value = !isMuted.value;
      applyAudioVolume();
      persistAudioPrefs();
      return;
    }
    showPlaylist.value = !showPlaylist.value;
  };

  const playNext = (auto = false) => {
    if (!auto && state.value === 'running') return;
    const next = pickNextTrack();
    if (!next) return;
    setActiveTrack(next);
  };

  const playPrev = () => {
    if (state.value === 'running') return;
    if (playHistory.length <= 1) return;
    playHistory.pop();
    while (playHistory.length && !playHistory[playHistory.length - 1].enabled) {
      playHistory.pop();
    }
    const prevTrack = playHistory[playHistory.length - 1];
    if (!prevTrack) {
      playNext(true);
      return;
    }
    setActiveTrack(prevTrack, { recordHistory: false });
  };

  const togglePause = () => {
    if (state.value === 'running') return;
    isPaused.value = !isPaused.value;
    if (!activeAudio) {
      syncPlaylist(true);
      return;
    }
    if (isPaused.value) {
      activeAudio.pause();
    } else {
      activeAudio.play().catch(() => {});
    }
  };

  // The shuffle queue is one full CYCLE: every enabled track exactly once.
  // Manual picks and add/remove must edit the current cycle in place — a
  // full reset would reshuffle already-played tracks back into the pot
  // (tracks repeated 2-3x within a few songs).
  const removeFromQueue = (id) => {
    shuffleQueue = shuffleQueue.filter((queued) => queued.id !== id);
  };

  const toggleTrack = (track) => {
    track.enabled = !track.enabled;
    persistAudioPrefs();
    if (!track.enabled) {
      removeFromQueue(track.id);
      if (currentTrack?.id === track.id) {
        playNext(true);
      }
      return;
    }
    // Re-enabled: slot it into the running cycle at a random spot.
    const pos = Math.floor(Math.random() * (shuffleQueue.length + 1));
    shuffleQueue.splice(pos, 0, track);
    if (!currentTrack || !currentTrack.enabled) {
      syncPlaylist(true);
    }
  };

  const playSpecific = (track) => {
    if (state.value === 'running') return;
    if (!track.enabled) return;
    // Counts as this cycle's play of that track; if it already played this
    // cycle it's simply a bonus listen without touching the cycle.
    removeFromQueue(track.id);
    setActiveTrack(track);
  };

  // Tear-down for onBeforeUnmount: stops fades, players, and the synth.
  const disposeAudio = () => {
    fadeToken += 1;
    document.removeEventListener('visibilitychange', handleBackgroundChange);
    window.removeEventListener('blur', handleBackgroundChange);
    window.removeEventListener('focus', handleBackgroundChange);
    [audioPrimary, audioSecondary].forEach((audio) => {
      if (!audio) return;
      audio.onended = null;
      audio.pause();
      audio.removeAttribute('src');
    });
    if (sfxCtx) {
      sfxCtx.close().catch(() => {});
    }
  };

  watch(audioVolume, () => {
    applyAudioVolume();
    persistAudioPrefs();
  });

  watch(isMuted, () => {
    applyAudioVolume();
    persistAudioPrefs();
  });

  return {
    // refs
    audioVolume,
    isMuted,
    isPaused,
    currentTrackName,
    currentTrackCover,
    showTrackToast,
    showPlaylist,
    tracks,
    playlistTracks,
    // sfx + engine
    sfx,
    updateEngineSound,
    silenceEngineSound,
    isEngineActive,
    // music
    initAudio,
    loadAudioPrefs,
    applyAudioVolume,
    setMusicDuck,
    updateMusicDuck,
    unlockAudio,
    syncPlaylist,
    handleAudioButton,
    playNext,
    playPrev,
    togglePause,
    toggleTrack,
    playSpecific,
    disposeAudio,
  };
}
