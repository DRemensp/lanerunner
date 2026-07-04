<template>
  <div class="runner-page">
    <div ref="canvasWrap" class="runner-canvas"></div>

    <div v-if="state !== 'running' && !showAuthGate" class="auth-bar">
      <template v-if="authUser">
        <div class="auth-user">Signed in as <strong>{{ authUser.name }}</strong></div>
      </template>
      <template v-else>
        <Link class="ghost-btn small" href="/login">Login</Link>
        <Link class="primary-btn small" href="/register">Register</Link>
      </template>
    </div>

    <div
      v-if="!showAuthGate"
      class="audio-pill"
      :class="{
        running: state === 'running',
        expanded: showTrackToast || showPlaylist,
        muted: isMuted,
      }"
      @click="handleAudioButton"
    >
      <svg class="audio-icon" viewBox="0 0 24 24" aria-hidden="true">
        <path
          d="M4 9h4l5-4v14l-5-4H4z"
          fill="currentColor"
        />
        <path
          class="audio-wave"
          d="M16 8c1.7 1.7 1.7 6.3 0 8"
          fill="none"
          stroke="currentColor"
          stroke-width="1.6"
          stroke-linecap="round"
        />
        <path
          class="audio-wave"
          d="M18.5 5.5c3 3.4 3 9.6 0 13"
          fill="none"
          stroke="currentColor"
          stroke-width="1.6"
          stroke-linecap="round"
        />
        <path
          class="audio-mute"
          d="M19 19L5 5"
          fill="none"
          stroke="currentColor"
          stroke-width="1.8"
          stroke-linecap="round"
        />
      </svg>
      <span class="audio-title">{{ currentTrackName }}</span>
    </div>

    <div
      v-if="showPlaylist && state !== 'running' && !showAuthGate"
      class="audio-backdrop"
      @click="showPlaylist = false"
    ></div>

    <div
      v-if="showPlaylist && state !== 'running' && !showAuthGate"
      class="audio-panel"
      @click.stop
    >
      <div class="audio-panel-head">
        <div class="audio-panel-title">Playlist</div>
        <button class="ghost-btn small" @click="showPlaylist = false" type="button">
          Close
        </button>
      </div>
      <div class="audio-controls">
        <button class="ghost-btn small" @click="playPrev" type="button">Prev</button>
        <button class="ghost-btn small" @click="togglePause" type="button">
          {{ isPaused ? 'Play' : 'Pause' }}
        </button>
        <button class="ghost-btn small" @click="playNext" type="button">Next</button>
      </div>
      <div class="audio-volume">
        <label for="audio-volume">Volume</label>
        <input
          id="audio-volume"
          type="range"
          min="0"
          max="1"
          step="0.01"
          v-model.number="audioVolume"
        />
      </div>
      <div class="audio-section">
        <div class="audio-section-title">Tracks</div>
        <div class="audio-list">
          <div v-for="track in playlistTracks" :key="track.id" class="audio-row">
            <button
              class="audio-toggle"
              :class="{ off: !track.enabled }"
              @click="toggleTrack(track)"
              type="button"
            >
              {{ track.enabled ? '-' : '+' }}
            </button>
            <button class="audio-track" @click="playSpecific(track)" type="button">
              <span>{{ track.title }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>

    <div v-if="state === 'running' || state === 'crashing' || state === 'paused'" class="hud">
      <div class="hud-block">
        <div class="hud-label">Score</div>
        <div class="hud-value">{{ Math.floor(score) }}</div>
      </div>
      <div class="hud-block coins">
        <div class="hud-label">Coins</div>
        <div class="hud-value">{{ runCoins }}</div>
      </div>
      <div class="hud-block">
        <div class="hud-label">Speed</div>
        <div class="hud-value">{{ speed.toFixed(1) }}</div>
      </div>
    </div>

    <div v-if="nearMissToast && state === 'running'" class="near-miss">
      Near Miss +{{ nearMissAmount }}
    </div>

    <div v-if="state === 'running' || state === 'paused'" class="power-row">
      <div v-if="shieldActive" class="power-chip shield">Shield</div>
      <div v-if="magnetTime > 0" class="power-chip magnet">Magnet {{ Math.ceil(magnetTime) }}s</div>
      <div v-if="multiTime > 0" class="power-chip multi">x2 Score {{ Math.ceil(multiTime) }}s</div>
    </div>

    <button
      v-if="state === 'running'"
      class="pause-btn"
      @click="pauseRun"
      type="button"
      aria-label="Pause"
    >
      II
    </button>

    <div v-if="state === 'paused'" class="death-overlay">
      <div class="death-card">
        <div class="death-title">Paused</div>
        <div class="death-actions">
          <button class="primary-btn" @click="resumeRun" type="button">Resume</button>
          <button class="ghost-btn" @click="startRun" type="button">Restart</button>
          <button class="ghost-btn" @click="quitRun" type="button">Quit Run</button>
        </div>
        <div class="pause-hint">Esc or Enter to resume</div>
      </div>
    </div>

    <div v-if="state === 'menu' && !showAuthGate" class="menu-overlay">
      <div v-if="menuScreen === 'main'" class="menu-layout">
        <div class="menu-left">
          <div class="menu-title">
            <div class="menu-eyebrow">Lane Runner</div>
            <h1 class="menu-title-text">Neon Rail Dash</h1>
            <p class="menu-sub">
              Sprint through three lanes, dodge the blocks, and build momentum.
              The menu is the hub for levels, skins, and inventory.
            </p>
          </div>

          <nav class="menu-nav">
            <button class="menu-link primary" @click="startRun" type="button">
              Start Game
            </button>
            <button class="menu-link" @click="openMenuScreen('level')" type="button">
              Change Lvl
            </button>
            <button class="menu-link" @click="openMenuScreen('inventory')" type="button">
              Inventory
            </button>
            <button class="menu-link" @click="openMenuScreen('leaderboard')" type="button">
              Leaderboard
            </button>
            <button class="menu-link" @click="openMenuScreen('settings')" type="button">
              Settings
            </button>
            <Link
              v-if="authUser"
              class="menu-link danger"
              href="/logout"
              method="post"
              as="button"
            >
              Leave Game
            </Link>
            <Link
              v-else
              class="menu-link"
              href="/login"
            >
              Login
            </Link>
          </nav>

          <div class="menu-controls">
            <div>Controls: A/D or Left/Right to switch lanes, Space or Up to jump, Esc to pause.</div>
            <div>Mobile: use the buttons on screen.</div>
          </div>
        </div>
      </div>

      <div v-else class="menu-screen">
          <div class="menu-screen-head">
            <button class="ghost-btn small" @click="backToMainMenu" type="button">
              Back
            </button>
            <div class="menu-screen-title">
              {{
                menuScreen === 'level'
                  ? 'Game Setup'
                  : menuScreen === 'inventory'
                    ? 'Inventory'
                    : menuScreen === 'leaderboard'
                      ? 'Leaderboard'
                      : 'Settings'
              }}
            </div>
          </div>
          <div class="menu-screen-card">
            <template v-if="menuScreen === 'level'">
            <div class="menu-field">
              <label for="level">Level</label>
              <select id="level" v-model="selectedLevel">
                <option v-for="level in levelOptions" :key="level.id" :value="level.id">
                  {{ level.label }}
                </option>
              </select>
            </div>
            <div class="menu-field">
              <label for="skin">Runner Skin</label>
              <div v-if="authUser" class="shop-balance">
                Balance: <strong>{{ totalCoins }}</strong> coins
              </div>
              <div class="skin-row">
                <button
                  v-for="skin in skinOptions"
                  :key="skin.id"
                  class="skin-chip"
                  :class="{ active: skin.id === selectedSkin, locked: !canUseSkin(skin) }"
                  :style="{ '--skin': skin.color }"
                  :title="canUseSkin(skin) ? 'Select skin' : `Unlock for ${skin.price} coins`"
                  @click="selectSkin(skin)"
                  type="button"
                >
                  {{ skin.label }}
                  <span v-if="!canUseSkin(skin)" class="skin-price">{{ skin.price }}c</span>
                </button>
              </div>
              <div v-if="shopMessage" class="shop-message">{{ shopMessage }}</div>
            </div>
          </template>

          <template v-else-if="menuScreen === 'inventory'">
            <div class="menu-inventory">
              <div v-if="inventoryItems.length === 0" class="menu-empty">
                No items yet. Earn or buy items to fill your inventory.
              </div>
              <div v-else class="menu-inventory-list" data-allow-scroll>
                <div v-for="item in inventoryItems" :key="item.item_id" class="menu-inventory-item">
                  <div>
                    <div class="menu-item-name">{{ item.name }}</div>
                    <div class="menu-item-meta">{{ item.type }}</div>
                  </div>
                  <div class="menu-item-qty">x{{ item.quantity }}</div>
                </div>
              </div>
            </div>
            </template>

            <template v-else-if="menuScreen === 'leaderboard'">
              <div class="leaderboard-panel">
                <div class="leaderboard-stat">
                  <div class="leaderboard-label">Best Score</div>
                  <div class="leaderboard-value">{{ Math.floor(bestScore) }}</div>
                </div>
                <div v-if="leaderboard.length" class="menu-leaderboard">
                  <div class="menu-leaderboard-title">Top Runners</div>
                  <ol>
                    <li v-for="(leader, index) in leaderboard" :key="index">
                      <span>{{ leader.name }}</span>
                      <span>{{ leader.best_distance }}</span>
                    </li>
                  </ol>
                </div>
                <div v-else class="menu-empty">No leaderboard data yet.</div>
              </div>
            </template>

            <template v-else>
              <div class="menu-settings-grid">
                <div class="menu-field">
                  <label for="settings-volume">Volume</label>
                <input
                  id="settings-volume"
                  type="range"
                  min="0"
                  max="1"
                  step="0.01"
                  v-model.number="audioVolume"
                />
                <div class="menu-slider-value">{{ Math.round(audioVolume * 100) }}%</div>
              </div>
              <div class="menu-field">
                <label for="settings-zoom">Zoom</label>
                <input
                  id="settings-zoom"
                  type="range"
                  min="0.8"
                  max="1.4"
                  step="0.01"
                  v-model.number="cameraZoom"
                />
                <div class="menu-slider-value">{{ Math.round(cameraZoom * 100) }}%</div>
              </div>
            </div>
          </template>
        </div>
      </div>
    </div>

    <div v-if="state === 'crashed'" class="death-overlay">
      <div class="death-card">
        <div class="death-title">Run Complete</div>
        <div class="death-score">
          <div>Last Score: <strong>{{ Math.floor(score) }}</strong></div>
          <div>Best Score: <strong>{{ Math.floor(bestScore) }}</strong></div>
          <div class="death-coins">Coins <strong>+{{ runCoins }}</strong></div>
        </div>
        <div class="death-actions">
          <button class="primary-btn" @click="startRun" type="button">Run Again</button>
          <button class="ghost-btn" @click="backToMenu" type="button">Return to Menu</button>
        </div>
      </div>
    </div>

    <div v-if="showAuthGate" class="gate-overlay" @click.self="acceptGuest">
      <div class="gate-card" @click.stop>
        <div class="gate-title">Choose your path</div>
        <p>
          Play as a guest or sign in to unlock inventory, skins, and leaderboards.
        </p>
        <div class="gate-actions">
          <button class="ghost-btn" @click="acceptGuest" type="button">Continue as Guest</button>
          <Link class="primary-btn" href="/login">Login</Link>
          <Link class="primary-btn alt" href="/register">Register</Link>
        </div>
      </div>
    </div>

    <div v-if="showLoginPrompt" class="modal-overlay" @click.self="closeLoginPrompt">
      <div class="modal-card" @click.stop>
        <div class="modal-title">Login Required</div>
        <p>Log in to access inventory, skins, and progress.</p>
        <div class="modal-actions">
          <button class="ghost-btn small" @click="closeLoginPrompt" type="button">Close</button>
          <Link class="ghost-btn small" href="/login">Login</Link>
          <Link class="primary-btn small" href="/register">Register</Link>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, computed, watch } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';

const canvasWrap = ref(null);

const page = usePage();
const authUser = computed(() => page.props.auth?.user || null);
const isGuest = computed(() => !authUser.value);

const state = ref('menu');
const score = ref(0);
const bestScore = ref(0);
const speed = ref(0);
const runCoins = ref(0);
const totalCoins = ref(0);
const shopMessage = ref('');
const leaderboard = ref([]);
const ownedSkinIds = ref([]);
const loadingProfile = ref(false);
const runToken = ref(null);
const inventoryItems = ref([]);
const menuScreen = ref('main');
const showLoginPrompt = ref(false);
const showAuthGate = ref(false);
const showPlaylist = ref(false);
const audioVolume = ref(0.7);
const isMuted = ref(false);
const isPaused = ref(false);
const currentTrackName = ref('');
const showTrackToast = ref(false);
const cameraZoom = ref(1);
const nearMissToast = ref(false);
const nearMissAmount = ref(25);
let nearMissTimer;

const shieldActive = ref(false);
const magnetTime = ref(0);
const multiTime = ref(0);
let invulnUntil = 0;
let shieldMesh;

const cameraBase = {
  y: 5.5,
  z: 8,
};

const levelOptions = [
  { id: 'casual', label: 'Leicht (Casual)', baseSpeed: 10, stepDistance: 500, speedStep: 1 },
  { id: 'rush', label: 'Mittel (City Rush)', baseSpeed: 12, stepDistance: 500, speedStep: 2 },
  { id: 'night', label: 'Schwer (Night Run)', baseSpeed: 14, stepDistance: 500, speedStep: 4 },
];
const selectedLevel = ref(levelOptions[1].id);

const skinOptions = ref([
  { id: 1, slug: 'neon', label: 'Neon', color: '#3bffb3', price: 0, is_default: true },
  { id: 2, slug: 'ember', label: 'Ember', color: '#ff6b3b', price: 300, is_default: false },
  { id: 3, slug: 'ion', label: 'Ion', color: '#49a8ff', price: 450, is_default: false },
]);
const selectedSkin = ref(skinOptions.value[0].id);

const buildTrack = (id, title, file) => ({
  id,
  title,
  src: encodeURI(`/audio/${file}`),
  enabled: true,
});

const tracks = ref([
  buildTrack('new-school', 'The New School', 'Nick Petrov - The New School.mp3'),
  buildTrack('golden', 'Golden', 'DEVMO - Golden.mp3'),
  buildTrack('bring-it-back', 'Bring It Back', 'Notize - Bring It Back.mp3'),
  buildTrack('soul-swingin', 'Soul Swingin', 'Richard Farrell - Soul Swingin.mp3'),
  buildTrack('sugarsweet', 'Sugarsweet', 'Zach Sorgen - Sugarsweet.mp3'),
]);

const playlistTracks = computed(() => tracks.value);

const lanes = [-2, 0, 2];
const groundY = 0.7;
const gravity = -28;
const jumpVelocity = 12;
const slideScale = 0.55;
const slideDuration = 0.6;
const dropBoost = 1.6;
const swipeThreshold = 40;
const swipeTimeLimit = 650;
const segmentLength = 20;
const playerSize = { w: 0.9, h: 1.4, d: 0.8 };
const coyoteTimeMs = 50;
const groundedEpsilon = 0.06;

let currentSurfaceY = 0;
let currentGroundCenter = groundY;
let lastGroundedAt = 0;

let scene;
let camera;
let renderer;
let animationId;
let lastTime = 0;

let player;
let playerMaterial;
let limbMaterial;
let runnerParts = null;
let runPhase = 0;
let playerVelocityY = 0;
let currentLane = 1;
let isSliding = false;
let slideTimer = 0;
let touchStart = null;
let pendingSlide = false;
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
let pointerUnlockHandler;

let floorSegments = [];
let obstacles = [];
let spawnTimer = 0;

let coins = [];
let coinPool = [];
let coinGeometry;
let coinMaterial;

let powerups = [];
const powerupPool = { shield: [], magnet: [], multi: [] };
let powerupAssets = null;

let sfxCtx = null;
let sfxGain = null;

let particles = [];
let particlePool = [];
let particleGeometry;
const particleMaterials = {};
let crashTimer = 0;

let buildingGeometry;
let buildingMaterials = [];
let glowTexture;
let starPoints;

const obstaclePools = {};
const obstacleResources = [];
let obstacleAssets = null;

const rowPatterns = [
  ['low', 'none', 'none'],
  ['none', 'low', 'none'],
  ['none', 'none', 'low'],
  ['over', 'none', 'none'],
  ['none', 'over', 'none'],
  ['none', 'none', 'over'],
  ['low', 'over', 'none'],
  ['over', 'low', 'none'],
  ['low', 'none', 'over'],
  ['over', 'none', 'low'],
  ['none', 'low', 'over'],
  ['none', 'over', 'low'],
  ['low', 'tall', 'tall'],
  ['tall', 'low', 'tall'],
  ['tall', 'tall', 'low'],
  ['over', 'tall', 'tall'],
  ['tall', 'over', 'tall'],
  ['tall', 'tall', 'over'],
  ['low', 'over', 'tall'],
  ['over', 'low', 'tall'],
  ['tall', 'low', 'over'],
  ['tall', 'over', 'low'],
  ['low', 'tall', 'over'],
  ['over', 'tall', 'low'],
];

const lookAtTarget = new THREE.Vector3();

const currentLevel = computed(() =>
  levelOptions.find((level) => level.id === selectedLevel.value) || levelOptions[0],
);
const currentSkin = computed(() =>
  skinOptions.value.find((skin) => skin.id === selectedSkin.value) || skinOptions.value[0],
);

const currentPlayerHeight = () => (isSliding ? playerSize.h * slideScale : playerSize.h);
const getGroundCenterForSurface = (surfaceY, playerHeight) => surfaceY + playerHeight / 2;
const currentGroundHeight = () => currentGroundCenter;

const startRun = async () => {
  if (!scene || state.value === 'crashing') return;
  unlockAudio();
  menuScreen.value = 'main';
  resetRun();
  runToken.value = null;
  if (authUser.value) {
    await startRunSession();
  }
  state.value = 'running';
};

const openMenuScreen = (screen) => {
  if (screen === 'inventory' && isGuest.value) {
    showLoginPrompt.value = true;
    return;
  }
  showLoginPrompt.value = false;
  shopMessage.value = '';
  menuScreen.value = screen;
};

const backToMainMenu = () => {
  menuScreen.value = 'main';
};

const closeLoginPrompt = () => {
  showLoginPrompt.value = false;
};

const backToMenu = () => {
  state.value = 'menu';
  menuScreen.value = 'main';
};

const pauseRun = () => {
  if (state.value !== 'running') return;
  state.value = 'paused';
};

const resumeRun = () => {
  if (state.value !== 'paused') return;
  state.value = 'running';
};

const quitRun = () => {
  resetRun();
  backToMenu();
};

const normalizeSkins = (skins) =>
  skins.map((skin) => ({
    id: skin.id,
    slug: skin.slug,
    label: skin.name,
    color: skin.color || '#3bffb3',
    price: skin.price_coins ?? 0,
    is_default: Boolean(skin.is_default),
  }));

const ensureSelectedSkin = () => {
  if (!skinOptions.value.length) return;
  const selected = skinOptions.value.find((skin) => skin.id === selectedSkin.value);
  if (!selected || !canUseSkin(selected)) {
    const fallback = skinOptions.value.find((skin) => canUseSkin(skin)) || skinOptions.value[0];
    selectedSkin.value = fallback.id;
  }
};

const loadGuestPrefs = () => {
  const storedBest = Number.parseInt(localStorage.getItem('runner_best_distance') || '0', 10);
  bestScore.value = Number.isFinite(storedBest) ? storedBest : 0;
  const storedSkin = Number.parseInt(localStorage.getItem('runner_skin_id') || '', 10);
  if (Number.isFinite(storedSkin)) {
    selectedSkin.value = storedSkin;
  }
  inventoryItems.value = [];
  ensureSelectedSkin();
};

const loadAudioPrefs = () => {
  const storedVolume = Number.parseFloat(localStorage.getItem('runner_audio_volume') || '0.7');
  audioVolume.value = Number.isFinite(storedVolume) ? storedVolume : 0.7;
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

const applyAudioVolume = () => {
  const base = isMuted.value ? 0 : audioVolume.value;
  if (activeAudio) {
    activeAudio.volume = base;
  }
  if (inactiveAudio) {
    inactiveAudio.volume = 0;
  }
};

const triggerTrackToast = (title) => {
  currentTrackName.value = title;
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
  const baseVolume = isMuted.value ? 0 : audioVolume.value;
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
    const t = Math.min(1, (now - fadeStart) / fadeDuration);
    if (prevAudio) {
      prevAudio.volume = baseVolume * (1 - t);
    }
    nextAudio.volume = baseVolume * t;
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
      triggerTrackToast(track.title);
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

const toggleTrack = (track) => {
  track.enabled = !track.enabled;
  persistAudioPrefs();
  shuffleQueue = [];
  if (!track.enabled && currentTrack?.id === track.id) {
    playNext(true);
    return;
  }
  if (track.enabled && (!currentTrack || !currentTrack.enabled)) {
    syncPlaylist(true);
  }
};

const playSpecific = (track) => {
  if (state.value === 'running') return;
  if (!track.enabled) return;
  shuffleQueue = [];
  setActiveTrack(track);
};

const acceptGuest = () => {
  localStorage.setItem('runner_guest_ok', '1');
  showAuthGate.value = false;
  unlockAudio();
};

const checkAuthGate = () => {
  if (authUser.value) {
    showAuthGate.value = false;
    return;
  }

  const alreadyAccepted = localStorage.getItem('runner_guest_ok') === '1';
  showAuthGate.value = !alreadyAccepted;
};

const loadProfile = async () => {
  if (loadingProfile.value) return;
  loadingProfile.value = true;

  try {
    const response = await axios.get('/api/runner/profile');
    const { data } = response;

    if (Array.isArray(data.skins) && data.skins.length > 0) {
      skinOptions.value = normalizeSkins(data.skins);
    }

    if (data.guest) {
      loadGuestPrefs();
    } else if (data.profile) {
      bestScore.value = data.profile.best_distance ?? 0;
      ownedSkinIds.value = Array.isArray(data.owned_skin_ids)
        ? data.owned_skin_ids.map((id) => Number(id))
        : [];
      if (data.profile.active_skin_id) {
        selectedSkin.value = Number(data.profile.active_skin_id);
      }
      totalCoins.value = data.profile.coins ?? 0;
      inventoryItems.value = Array.isArray(data.inventory) ? data.inventory : [];
      ensureSelectedSkin();
    }
  } catch (error) {
    loadGuestPrefs();
  } finally {
    loadingProfile.value = false;
  }
};

const loadLeaderboard = async () => {
  try {
    const response = await axios.get('/api/runner/leaderboard');
    leaderboard.value = response.data.leaders || [];
  } catch (error) {
    leaderboard.value = [];
  }
};

const canUseSkin = (skin) =>
  skin.is_default || (!isGuest.value && ownedSkinIds.value.includes(skin.id));

const selectSkin = async (skin) => {
  shopMessage.value = '';

  if (!canUseSkin(skin)) {
    if (isGuest.value) {
      showLoginPrompt.value = true;
      return;
    }
    if (totalCoins.value < (skin.price ?? 0)) {
      shopMessage.value = `Not enough coins — ${skin.label} costs ${skin.price}.`;
      return;
    }
    try {
      const response = await axios.post('/api/runner/skin/buy', { skin_id: skin.id });
      totalCoins.value = response.data.coins ?? totalCoins.value;
      ownedSkinIds.value = Array.isArray(response.data.owned_skin_ids)
        ? response.data.owned_skin_ids.map((id) => Number(id))
        : ownedSkinIds.value;
      shopMessage.value = `${skin.label} unlocked!`;
    } catch (error) {
      shopMessage.value = error.response?.data?.message || 'Purchase failed.';
      return;
    }
  }

  selectedSkin.value = skin.id;

  if (authUser.value) {
    try {
      await axios.post('/api/runner/skin', { skin_id: skin.id });
    } catch (error) {
      // Ignore update errors to keep UI responsive.
    }
  } else {
    localStorage.setItem('runner_skin_id', String(skin.id));
  }
};

const startRunSession = async () => {
  try {
    const response = await axios.post('/api/runner/run/start', {
      level: selectedLevel.value,
    });
    runToken.value = response.data.run_id || null;
  } catch (error) {
    runToken.value = null;
  }
};

const resetRun = () => {
  score.value = 0;
  runCoins.value = 0;
  speed.value = currentLevel.value.baseSpeed;
  spawnTimer = 0.7;
  currentLane = 1;
  playerVelocityY = 0;
  runToken.value = null;
  isSliding = false;
  slideTimer = 0;
  pendingSlide = false;
  runPhase = 0;
  currentSurfaceY = 0;
  currentGroundCenter = getGroundCenterForSurface(currentSurfaceY, currentPlayerHeight());
  lastGroundedAt = performance.now();
  if (player) {
    player.position.set(lanes[currentLane], currentGroundCenter, 2);
    player.scale.y = 1;
    player.rotation.z = 0;
    player.visible = true;
  }
  if (camera) {
    camera.fov = 60;
    camera.updateProjectionMatrix();
    camera.position.x = 0;
    applyCameraZoom();
  }
  shieldActive.value = false;
  magnetTime.value = 0;
  multiTime.value = 0;
  invulnUntil = 0;
  clearObstacles();
  clearCoins();
  clearPowerups();
  resetFloor();
};

const endRun = () => {
  state.value = 'crashed';
  persistRun();
  loadLeaderboard();
};

const persistRun = async () => {
  const distance = Math.max(0, Math.floor(score.value));
  const maxSpeed = Number(speed.value.toFixed(2));

  if (authUser.value) {
    try {
      const response = await axios.post('/api/runner/run/end', {
        distance,
        max_speed: maxSpeed,
        coins: runCoins.value,
        run_id: runToken.value,
      });
      const updated = response.data.profile;
      if (updated && typeof updated.best_distance === 'number') {
        bestScore.value = updated.best_distance;
      }
      if (updated && typeof updated.coins === 'number') {
        totalCoins.value = updated.coins;
      }
      loadProfile();
    } catch (error) {
      // Ignore network errors for now.
    }
    return;
  }

  const storedBest = Number.parseInt(localStorage.getItem('runner_best_distance') || '0', 10);
  const nextBest = Number.isFinite(storedBest)
    ? Math.max(storedBest, distance)
    : distance;
  localStorage.setItem('runner_best_distance', String(nextBest));
  bestScore.value = nextBest;
};

const clearObstacles = () => {
  obstacles.forEach((obstacle) => {
    scene.remove(obstacle);
    const key = obstacle.userData?.poolKey;
    if (key) {
      (obstaclePools[key] ||= []).push(obstacle);
    }
  });
  obstacles = [];
};

const resetFloor = () => {
  floorSegments.forEach((segment, index) => {
    segment.position.z = -(index - 1) * segmentLength;
  });
};

const moveLeft = () => {
  if (state.value !== 'running') return;
  currentLane = Math.max(0, currentLane - 1);
};

const moveRight = () => {
  if (state.value !== 'running') return;
  currentLane = Math.min(lanes.length - 1, currentLane + 1);
};

const attemptJump = () => {
  if (state.value !== 'running' || !player) return;
  if (isSliding) {
    stopSlide();
  }
  const groundHeight = currentGroundHeight();
  const now = performance.now();
  const grounded = player.position.y <= groundHeight + groundedEpsilon;
  const canCoyote = now - lastGroundedAt <= coyoteTimeMs;
  if (grounded || canCoyote) {
    playerVelocityY = jumpVelocity;
    lastGroundedAt = 0;
    sfx.jump();
  }
};

const requestSlide = () => {
  if (state.value !== 'running' || !player) return;
  if (isSliding) {
    slideTimer = slideDuration;
    return;
  }
  if (Math.abs(player.position.y - currentGroundCenter) > 0.01) {
    pendingSlide = true;
    playerVelocityY = Math.min(playerVelocityY, -jumpVelocity * dropBoost);
    return;
  }
  isSliding = true;
  slideTimer = slideDuration;
  playerVelocityY = 0;
  sfx.slide();
  player.scale.y = slideScale;
  player.position.y = getGroundCenterForSurface(currentSurfaceY, currentPlayerHeight());
  currentGroundCenter = player.position.y;
};

const stopSlide = () => {
  isSliding = false;
  slideTimer = 0;
  if (player) {
    player.scale.y = 1;
    player.position.y = getGroundCenterForSurface(currentSurfaceY, currentPlayerHeight());
    currentGroundCenter = player.position.y;
  }
};

const handleKeydown = (event) => {
  if (state.value === 'paused') {
    if (event.code === 'Escape' || event.code === 'Enter') {
      resumeRun();
    }
    return;
  }

  if (state.value !== 'running') {
    if (event.code === 'Enter') {
      startRun();
    }
    return;
  }

  if (event.code === 'Escape') {
    pauseRun();
    return;
  }

  switch (event.code) {
    case 'ArrowLeft':
    case 'KeyA':
      moveLeft();
      break;
    case 'ArrowRight':
    case 'KeyD':
      moveRight();
      break;
    case 'ArrowUp':
    case 'KeyW':
    case 'Space':
      attemptJump();
      break;
    case 'ArrowDown':
    case 'KeyS':
      requestSlide();
      break;
    default:
      break;
  }
};

const handleTouchStart = (event) => {
  if (state.value !== 'running') return;
  const touch = event.changedTouches[0];
  touchStart = {
    x: touch.clientX,
    y: touch.clientY,
    time: performance.now(),
  };
};

const triggerSwipe = (dx, dy) => {
  if (Math.abs(dx) < swipeThreshold && Math.abs(dy) < swipeThreshold) return false;

  if (Math.abs(dx) > Math.abs(dy)) {
    if (dx > 0) {
      moveRight();
    } else {
      moveLeft();
    }
    return true;
  }

  if (dy < 0) {
    attemptJump();
  } else {
    requestSlide();
  }
  return true;
};

const handleTouchEnd = (event) => {
  if (state.value !== 'running' || !touchStart) return;
  const touch = event.changedTouches[0];
  const dx = touch.clientX - touchStart.x;
  const dy = touch.clientY - touchStart.y;
  const dt = performance.now() - touchStart.time;
  touchStart = null;

  if (dt > swipeTimeLimit) return;
  triggerSwipe(dx, dy);
};

const handleTouchMove = (event) => {
  const target = event.target;
  if (
    target &&
    target.closest &&
    target.closest('input, select, textarea, [data-allow-scroll]')
  ) {
    return;
  }
  if (event.cancelable) {
    event.preventDefault();
  }

  if (state.value !== 'running' || !touchStart) return;
  const touch = event.changedTouches[0];
  const dx = touch.clientX - touchStart.x;
  const dy = touch.clientY - touchStart.y;
  const dt = performance.now() - touchStart.time;

  if (dt > swipeTimeLimit) return;
  if (triggerSwipe(dx, dy)) {
    touchStart = null;
  }
};

const getViewportSize = () => {
  const viewport = window.visualViewport;
  return {
    width: viewport?.width || window.innerWidth,
    height: viewport?.height || window.innerHeight,
  };
};

const applyCameraZoom = () => {
  if (!camera) return;
  const zoom = cameraZoom.value;
  camera.position.y = cameraBase.y * zoom;
  camera.position.z = cameraBase.z * zoom;
};

const handleResize = () => {
  if (!renderer || !camera || !canvasWrap.value) return;
  const viewport = getViewportSize();
  const width = canvasWrap.value.clientWidth || viewport.width;
  const height = canvasWrap.value.clientHeight || viewport.height;
  camera.aspect = width / height;
  camera.updateProjectionMatrix();
  renderer.setSize(width, height, false);
  applyCameraZoom();
};

let houseAssets = null;

const buildHouse = (side) => {
  const group = new THREE.Group();
  const w = 3.2 + Math.random() * 2.4;
  const h = 5 + Math.random() * 9;
  const d = 4 + Math.random() * 3;
  const base = new THREE.Mesh(
    buildingGeometry,
    buildingMaterials[Math.floor(Math.random() * buildingMaterials.length)],
  );
  base.scale.set(w, h, d);
  group.add(base);

  const faceX = -side * (w / 2 + 0.03);
  const faceRotation = side < 0 ? Math.PI / 2 : -Math.PI / 2;
  const rows = Math.max(1, Math.min(5, Math.floor((h - 2.6) / 2.1)));
  for (let row = 0; row < rows; row += 1) {
    for (let col = -1; col <= 1; col += 2) {
      const win = new THREE.Mesh(
        houseAssets.windowGeometry,
        Math.random() < 0.4 ? houseAssets.windowLit : houseAssets.windowDark,
      );
      win.position.set(faceX, 2.6 + row * 2.1, col * d * 0.22);
      win.rotation.y = faceRotation;
      group.add(win);
    }
  }

  const door = new THREE.Mesh(houseAssets.doorGeometry, houseAssets.doorMat);
  door.position.set(faceX, 0.85, 0);
  door.rotation.y = faceRotation;
  group.add(door);

  return group;
};

const buildRunner = () => {
  player = new THREE.Group();
  playerMaterial = new THREE.MeshLambertMaterial({ color: currentSkin.value.color });
  limbMaterial = new THREE.MeshLambertMaterial({
    color: new THREE.Color(currentSkin.value.color).multiplyScalar(0.55),
  });
  const darkMaterial = new THREE.MeshLambertMaterial({ color: 0x171c2a });
  const headMaterial = new THREE.MeshLambertMaterial({ color: 0x1a2132 });
  const visorMaterial = new THREE.MeshBasicMaterial({ color: 0x7df9ff });

  // Character spans roughly playerSize.h, centered on the group origin so the
  // existing collision math keeps working. The runner faces -z.
  const torso = new THREE.Group();
  torso.position.y = 0.2;
  const jacket = new THREE.Mesh(new THREE.BoxGeometry(0.58, 0.56, 0.32), playerMaterial);
  torso.add(jacket);
  const chest = new THREE.Mesh(new THREE.BoxGeometry(0.44, 0.34, 0.05), limbMaterial);
  chest.position.set(0, 0.04, -0.17);
  torso.add(chest);
  const belt = new THREE.Mesh(new THREE.BoxGeometry(0.6, 0.09, 0.34), darkMaterial);
  belt.position.y = -0.31;
  torso.add(belt);
  const pack = new THREE.Mesh(new THREE.BoxGeometry(0.42, 0.44, 0.16), darkMaterial);
  pack.position.set(0, 0.02, 0.26);
  torso.add(pack);
  player.add(torso);

  const head = new THREE.Group();
  head.position.y = 0.56;
  const skull = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.28, 0.3), headMaterial);
  skull.position.y = 0.12;
  head.add(skull);
  const visor = new THREE.Mesh(new THREE.BoxGeometry(0.24, 0.09, 0.03), visorMaterial);
  visor.position.set(0, 0.14, -0.16);
  head.add(visor);
  const cap = new THREE.Mesh(new THREE.BoxGeometry(0.34, 0.09, 0.34), playerMaterial);
  cap.position.y = 0.29;
  head.add(cap);
  const brim = new THREE.Mesh(new THREE.BoxGeometry(0.3, 0.04, 0.16), playerMaterial);
  brim.position.set(0, 0.27, -0.24);
  head.add(brim);
  player.add(head);

  const makeLeg = (x) => {
    const hip = new THREE.Group();
    hip.position.set(x, -0.05, 0);
    const thighGeo = new THREE.BoxGeometry(0.19, 0.34, 0.22);
    thighGeo.translate(0, -0.165, 0);
    hip.add(new THREE.Mesh(thighGeo, limbMaterial));
    const knee = new THREE.Group();
    knee.position.y = -0.33;
    const shinGeo = new THREE.BoxGeometry(0.16, 0.28, 0.19);
    shinGeo.translate(0, -0.14, 0);
    knee.add(new THREE.Mesh(shinGeo, limbMaterial));
    const shoe = new THREE.Mesh(new THREE.BoxGeometry(0.2, 0.1, 0.32), darkMaterial);
    shoe.position.set(0, -0.27, -0.05);
    knee.add(shoe);
    hip.add(knee);
    return { hip, knee };
  };

  const makeArm = (x) => {
    const shoulder = new THREE.Group();
    shoulder.position.set(x, 0.42, 0);
    const upperGeo = new THREE.BoxGeometry(0.15, 0.3, 0.17);
    upperGeo.translate(0, -0.15, 0);
    shoulder.add(new THREE.Mesh(upperGeo, playerMaterial));
    const elbow = new THREE.Group();
    elbow.position.y = -0.3;
    const foreGeo = new THREE.BoxGeometry(0.13, 0.26, 0.15);
    foreGeo.translate(0, -0.13, 0);
    elbow.add(new THREE.Mesh(foreGeo, limbMaterial));
    const hand = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.1, 0.12), headMaterial);
    hand.position.y = -0.3;
    elbow.add(hand);
    shoulder.add(elbow);
    return { shoulder, elbow };
  };

  const legL = makeLeg(-0.17);
  const legR = makeLeg(0.17);
  const armL = makeArm(-0.38);
  const armR = makeArm(0.38);
  player.add(legL.hip, legR.hip, armL.shoulder, armR.shoulder);

  shieldMesh = new THREE.Mesh(
    new THREE.SphereGeometry(0.95, 18, 14),
    new THREE.MeshBasicMaterial({
      color: 0x35e0ff,
      transparent: true,
      opacity: 0.18,
      blending: THREE.AdditiveBlending,
      depthWrite: false,
    }),
  );
  shieldMesh.visible = false;
  player.add(shieldMesh);

  runnerParts = { torso, head, legL, legR, armL, armR };
};

const animateRunner = (delta) => {
  if (!runnerParts) return;
  const { torso, head, legL, legR, armL, armR } = runnerParts;
  const grounded = player.position.y <= currentGroundCenter + groundedEpsilon;
  const d = (current, target, speedFactor = 14) =>
    THREE.MathUtils.damp(current, target, speedFactor, delta);

  player.rotation.z = d(player.rotation.z, (player.position.x - lanes[currentLane]) * 0.18, 10);

  if (isSliding) {
    // Feet-first slide, head tucked back.
    legL.hip.rotation.x = d(legL.hip.rotation.x, 1.25);
    legR.hip.rotation.x = d(legR.hip.rotation.x, 1.0);
    legL.knee.rotation.x = d(legL.knee.rotation.x, -0.45);
    legR.knee.rotation.x = d(legR.knee.rotation.x, -0.7);
    armL.shoulder.rotation.x = d(armL.shoulder.rotation.x, -0.7);
    armR.shoulder.rotation.x = d(armR.shoulder.rotation.x, -0.7);
    armL.elbow.rotation.x = d(armL.elbow.rotation.x, 0.5);
    armR.elbow.rotation.x = d(armR.elbow.rotation.x, 0.5);
    torso.rotation.x = d(torso.rotation.x, -0.3);
    head.rotation.x = d(head.rotation.x, 0.25);
    return;
  }

  if (!grounded) {
    // Hero jump: front leg reaching, back leg tucked, arms thrown up.
    legL.hip.rotation.x = d(legL.hip.rotation.x, 0.95, 12);
    legR.hip.rotation.x = d(legR.hip.rotation.x, -0.5, 12);
    legL.knee.rotation.x = d(legL.knee.rotation.x, -0.25, 12);
    legR.knee.rotation.x = d(legR.knee.rotation.x, -1.35, 12);
    armL.shoulder.rotation.x = d(armL.shoulder.rotation.x, -1.6, 12);
    armR.shoulder.rotation.x = d(armR.shoulder.rotation.x, -1.25, 12);
    armL.elbow.rotation.x = d(armL.elbow.rotation.x, 0.4, 12);
    armR.elbow.rotation.x = d(armR.elbow.rotation.x, 0.4, 12);
    torso.rotation.x = d(torso.rotation.x, 0.18, 10);
    head.rotation.x = d(head.rotation.x, -0.12, 10);
    return;
  }

  runPhase += delta * (6 + speed.value * 0.6);
  const swing = Math.sin(runPhase);
  legL.hip.rotation.x = swing;
  legR.hip.rotation.x = -swing;
  // Knee bends hardest while the leg swings through the back.
  legL.knee.rotation.x = -(0.25 + Math.max(0, -swing) * 1.15);
  legR.knee.rotation.x = -(0.25 + Math.max(0, swing) * 1.15);
  armL.shoulder.rotation.x = -swing * 0.85;
  armR.shoulder.rotation.x = swing * 0.85;
  armL.elbow.rotation.x = 0.9;
  armR.elbow.rotation.x = 0.9;
  torso.rotation.x = d(torso.rotation.x, 0.14, 8);
  torso.rotation.y = swing * 0.1;
  head.rotation.x = d(head.rotation.x, -0.08, 8);
  torso.position.y = 0.2 + Math.abs(Math.cos(runPhase)) * 0.04;
};

let idleTime = 0;

const animateIdle = (delta) => {
  if (!runnerParts || !player?.visible) return;
  const { torso, head, legL, legR, armL, armR } = runnerParts;
  idleTime += delta;
  const d = (current, target) => THREE.MathUtils.damp(current, target, 6, delta);

  torso.position.y = 0.2 + Math.sin(idleTime * 2) * 0.012;
  torso.rotation.x = d(torso.rotation.x, 0.02);
  torso.rotation.y = d(torso.rotation.y, 0);
  head.rotation.x = d(head.rotation.x, 0);
  head.rotation.y = Math.sin(idleTime * 0.6) * 0.25;
  [legL, legR].forEach((leg) => {
    leg.hip.rotation.x = d(leg.hip.rotation.x, 0);
    leg.knee.rotation.x = d(leg.knee.rotation.x, -0.06);
  });
  [armL, armR].forEach((arm) => {
    arm.shoulder.rotation.x = d(arm.shoulder.rotation.x, Math.sin(idleTime * 2) * 0.04);
    arm.elbow.rotation.x = d(arm.elbow.rotation.x, 0.25);
  });
  player.rotation.z = d(player.rotation.z, 0);
};

const getCoin = () => {
  if (coinPool.length) {
    return coinPool.pop();
  }
  return new THREE.Mesh(coinGeometry, coinMaterial);
};

const spawnCoinLine = (laneIndex, baseZ) => {
  for (let k = -2; k <= 2; k += 1) {
    const coin = getCoin();
    coin.position.set(lanes[laneIndex], 1.0, baseZ + k * 2.0);
    coin.rotation.y = Math.random() * Math.PI;
    coins.push(coin);
    scene.add(coin);
  }
};

const clearCoins = () => {
  coins.forEach((coin) => {
    scene.remove(coin);
    coinPool.push(coin);
  });
  coins = [];
};

const powerupTypes = ['shield', 'magnet', 'multi'];
const powerupDuration = 8;

const getPowerupAssets = () => {
  if (powerupAssets) return powerupAssets;
  const glowMat = (color) =>
    new THREE.MeshLambertMaterial({ color, emissive: color, emissiveIntensity: 0.6 });
  powerupAssets = {
    shieldGeo: new THREE.OctahedronGeometry(0.42),
    shieldMat: glowMat(0x35e0ff),
    magnetGeo: new THREE.TorusGeometry(0.3, 0.13, 8, 18, Math.PI),
    magnetMat: glowMat(0xff4fd8),
    multiGeo: new THREE.TetrahedronGeometry(0.42),
    multiMat: glowMat(0x67f05a),
    ringGeo: new THREE.TorusGeometry(0.66, 0.03, 6, 26),
    ringMat: new THREE.MeshBasicMaterial({ color: 0xffffff, transparent: true, opacity: 0.35 }),
  };
  return powerupAssets;
};

const getPowerup = (type) => {
  if (powerupPool[type].length) {
    return powerupPool[type].pop();
  }
  const assets = getPowerupAssets();
  const group = new THREE.Group();
  const core = new THREE.Mesh(assets[`${type}Geo`], assets[`${type}Mat`]);
  if (type === 'magnet') {
    core.rotation.z = Math.PI;
  }
  group.add(core);
  group.add(new THREE.Mesh(assets.ringGeo, assets.ringMat));
  group.userData.type = type;
  group.userData.core = core;
  group.userData.phase = Math.random() * Math.PI * 2;
  return group;
};

const spawnPowerup = (laneIndex, z) => {
  const type = powerupTypes[Math.floor(Math.random() * powerupTypes.length)];
  const powerup = getPowerup(type);
  powerup.position.set(lanes[laneIndex], 1.1, z);
  powerups.push(powerup);
  scene.add(powerup);
};

const clearPowerups = () => {
  powerups.forEach((powerup) => {
    scene.remove(powerup);
    powerupPool[powerup.userData.type].push(powerup);
  });
  powerups = [];
};

const activatePowerup = (type) => {
  if (type === 'shield') {
    shieldActive.value = true;
  } else if (type === 'magnet') {
    magnetTime.value = powerupDuration;
  } else {
    multiTime.value = powerupDuration;
  }
  sfx.powerup();
};

const spawnBurst = (position, materialKeys, count, force = 7) => {
  for (let i = 0; i < count; i += 1) {
    const key = materialKeys[i % materialKeys.length];
    let particle = particlePool.pop();
    if (!particle) {
      particle = new THREE.Mesh(particleGeometry, particleMaterials[key]);
    } else {
      particle.material = particleMaterials[key];
    }
    particle.position.copy(position);
    particle.scale.setScalar(0.6 + Math.random() * 0.8);
    particle.userData.velocity = new THREE.Vector3(
      (Math.random() - 0.5) * force,
      Math.random() * force * 0.9,
      (Math.random() - 0.4) * force,
    );
    particle.userData.life = 0.55 + Math.random() * 0.35;
    particles.push(particle);
    scene.add(particle);
  }
};

const updateParticles = (delta) => {
  for (let i = particles.length - 1; i >= 0; i -= 1) {
    const particle = particles[i];
    particle.userData.life -= delta;
    if (particle.userData.life <= 0) {
      scene.remove(particle);
      particlePool.push(particle);
      particles.splice(i, 1);
      continue;
    }
    particle.userData.velocity.y += gravity * 0.6 * delta;
    particle.position.addScaledVector(particle.userData.velocity, delta);
    particle.rotation.x += delta * 6;
    particle.rotation.y += delta * 5;
  }
};

const nearMissBase = 25;

const triggerNearMiss = () => {
  const bonus = nearMissBase * (multiTime.value > 0 ? 2 : 1);
  nearMissAmount.value = bonus;
  score.value += bonus;
  sfx.nearMiss();
  nearMissToast.value = false;
  if (nearMissTimer) {
    clearTimeout(nearMissTimer);
  }
  // Re-trigger the CSS animation even when two near misses overlap.
  requestAnimationFrame(() => {
    nearMissToast.value = true;
    nearMissTimer = setTimeout(() => {
      nearMissToast.value = false;
    }, 950);
  });
};

const initScene = () => {
  scene = new THREE.Scene();
  scene.background = new THREE.Color(0x05070f);
  scene.fog = new THREE.Fog(0x05070f, 35, 140);

  camera = new THREE.PerspectiveCamera(60, 1, 0.1, 200);
  camera.position.set(0, cameraBase.y, cameraBase.z);
  applyCameraZoom();

  renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
  renderer.setSize(1, 1);
  renderer.setClearColor(0x05070f, 1);
  renderer.domElement.style.display = 'block';
  renderer.domElement.style.width = '100%';
  renderer.domElement.style.height = '100%';

  if (canvasWrap.value) {
    canvasWrap.value.appendChild(renderer.domElement);
  }

  const hemiLight = new THREE.HemisphereLight(0x9fc1ff, 0x141824, 1.6);
  scene.add(hemiLight);

  const dirLight = new THREE.DirectionalLight(0xffffff, 1.7);
  dirLight.position.set(6, 10, 4);
  scene.add(dirLight);

  const floorMaterial = new THREE.MeshLambertMaterial({
    color: 0x0e1b2f,
  });
  const stripeMaterial = new THREE.MeshBasicMaterial({
    color: 0x2ee5ff,
  });
  const railMaterial = new THREE.MeshLambertMaterial({
    color: 0x2b3b56,
  });

  buildingGeometry = new THREE.BoxGeometry(1, 1, 1);
  buildingGeometry.translate(0, 0.5, 0);
  buildingMaterials = [
    new THREE.MeshLambertMaterial({ color: 0x1a2a46 }),
    new THREE.MeshLambertMaterial({ color: 0x14203a }),
    new THREE.MeshLambertMaterial({ color: 0x223354 }),
  ];
  const glowCanvas = document.createElement('canvas');
  glowCanvas.width = 128;
  glowCanvas.height = 128;
  const glowCtx = glowCanvas.getContext('2d');
  const gradient = glowCtx.createRadialGradient(64, 64, 4, 64, 64, 64);
  gradient.addColorStop(0, 'rgba(255, 214, 150, 0.5)');
  gradient.addColorStop(1, 'rgba(255, 214, 150, 0)');
  glowCtx.fillStyle = gradient;
  glowCtx.fillRect(0, 0, 128, 128);
  glowTexture = new THREE.CanvasTexture(glowCanvas);

  houseAssets = {
    windowGeometry: new THREE.PlaneGeometry(0.7, 0.9),
    doorGeometry: new THREE.PlaneGeometry(0.95, 1.7),
    windowLit: new THREE.MeshBasicMaterial({ color: 0xffd98c }),
    windowDark: new THREE.MeshBasicMaterial({ color: 0x0a1322 }),
    doorMat: new THREE.MeshBasicMaterial({ color: 0x0c1626 }),
  };

  const lampPoleGeometry = new THREE.BoxGeometry(0.11, 4.2, 0.11);
  const lampArmGeometry = new THREE.BoxGeometry(0.8, 0.08, 0.08);
  const lampHeadGeometry = new THREE.BoxGeometry(0.3, 0.15, 0.22);
  const lampConeGeometry = new THREE.ConeGeometry(1.4, 3.9, 20, 1, true);
  const lampPoolGeometry = new THREE.PlaneGeometry(4.4, 4.4);
  const lampPoleMaterial = new THREE.MeshLambertMaterial({ color: 0x24304a });
  const lampHeadMaterial = new THREE.MeshBasicMaterial({ color: 0xffdfa6 });
  const lampConeMaterial = new THREE.MeshBasicMaterial({
    color: 0xffca7a,
    transparent: true,
    opacity: 0.07,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    side: THREE.DoubleSide,
  });
  const lampPoolMaterial = new THREE.MeshBasicMaterial({
    map: glowTexture,
    transparent: true,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
  });

  for (let i = 0; i < 7; i += 1) {
    const segment = new THREE.Group();
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(8, segmentLength), floorMaterial);
    floor.rotation.x = -Math.PI / 2;
    segment.add(floor);

    const stripeGeometry = new THREE.BoxGeometry(0.08, 0.02, 6);
    const stripeLeft = new THREE.Mesh(stripeGeometry, stripeMaterial);
    stripeLeft.position.set(-1, 0.01, -3);
    segment.add(stripeLeft);

    const stripeRight = stripeLeft.clone();
    stripeRight.position.set(1, 0.01, 3);
    segment.add(stripeRight);

    const railGeometry = new THREE.BoxGeometry(0.2, 0.4, segmentLength);
    const railLeft = new THREE.Mesh(railGeometry, railMaterial);
    railLeft.position.set(-3.8, 0.2, 0);
    segment.add(railLeft);

    const railRight = railLeft.clone();
    railRight.position.set(3.8, 0.2, 0);
    segment.add(railRight);

    for (let side = -1; side <= 1; side += 2) {
      for (let b = 0; b < 2; b += 1) {
        const house = buildHouse(side);
        house.position.set(
          side * (7.5 + Math.random() * 3),
          0,
          -segmentLength / 2 + (b - 0.5) * 10 + (Math.random() - 0.5) * 4,
        );
        segment.add(house);
      }
    }

    for (let side = -1; side <= 1; side += 2) {
      const lamp = new THREE.Group();

      const pole = new THREE.Mesh(lampPoleGeometry, lampPoleMaterial);
      pole.position.y = 2.1;
      lamp.add(pole);

      const arm = new THREE.Mesh(lampArmGeometry, lampPoleMaterial);
      arm.position.set(-side * 0.4, 4.2, 0);
      lamp.add(arm);

      const head = new THREE.Mesh(lampHeadGeometry, lampHeadMaterial);
      head.position.set(-side * 0.8, 4.13, 0);
      lamp.add(head);

      const cone = new THREE.Mesh(lampConeGeometry, lampConeMaterial);
      cone.position.set(-side * 0.8, 2.08, 0);
      lamp.add(cone);

      const pool = new THREE.Mesh(lampPoolGeometry, lampPoolMaterial);
      pool.rotation.x = -Math.PI / 2;
      pool.position.set(-side * 0.8, 0.03, 0);
      lamp.add(pool);

      lamp.position.set(side * 4.15, 0, -segmentLength / 2);
      segment.add(lamp);
    }

    segment.position.z = -(i - 1) * segmentLength;
    scene.add(segment);
    floorSegments.push(segment);
  }

  coinGeometry = new THREE.CylinderGeometry(0.32, 0.32, 0.09, 18);
  coinGeometry.rotateX(Math.PI / 2);
  coinMaterial = new THREE.MeshBasicMaterial({ color: 0xffcf4d });

  particleGeometry = new THREE.BoxGeometry(0.13, 0.13, 0.13);
  particleMaterials.gold = new THREE.MeshBasicMaterial({ color: 0xffcf4d });
  particleMaterials.red = new THREE.MeshBasicMaterial({ color: 0xff3b57 });
  particleMaterials.skin = new THREE.MeshBasicMaterial({ color: currentSkin.value.color });
  particleMaterials.dust = new THREE.MeshBasicMaterial({
    color: 0x9fb2cc,
    transparent: true,
    opacity: 0.55,
  });

  const starCount = 260;
  const starPositions = new Float32Array(starCount * 3);
  for (let i = 0; i < starCount; i += 1) {
    starPositions[i * 3] = (Math.random() - 0.5) * 300;
    starPositions[i * 3 + 1] = 25 + Math.random() * 80;
    starPositions[i * 3 + 2] = -180 + Math.random() * 210;
  }
  const starGeometry = new THREE.BufferGeometry();
  starGeometry.setAttribute('position', new THREE.BufferAttribute(starPositions, 3));
  starPoints = new THREE.Points(
    starGeometry,
    new THREE.PointsMaterial({
      color: 0xbfd4ff,
      size: 0.8,
      transparent: true,
      opacity: 0.8,
      fog: false,
    }),
  );
  scene.add(starPoints);

  const moon = new THREE.Mesh(
    new THREE.CircleGeometry(5, 24),
    new THREE.MeshBasicMaterial({ color: 0xf5ecd4, fog: false }),
  );
  moon.position.set(-45, 55, -140);
  scene.add(moon);

  buildRunner();
  player.position.set(lanes[currentLane], currentGroundCenter, 2);
  scene.add(player);

  camera.lookAt(0, 1.2, -12);

  window.addEventListener('resize', handleResize);
  window.addEventListener('keydown', handleKeydown);
  window.addEventListener('touchstart', handleTouchStart, { passive: true });
  window.addEventListener('touchmove', handleTouchMove, { passive: false });
  window.addEventListener('touchend', handleTouchEnd, { passive: true });
};

const track = (resource) => {
  obstacleResources.push(resource);
  return resource;
};

const getObstacleAssets = () => {
  if (obstacleAssets) return obstacleAssets;
  const lambert = (color, glow = 0) =>
    track(new THREE.MeshLambertMaterial({
      color,
      emissive: glow ? color : 0x000000,
      emissiveIntensity: glow,
    }));
  obstacleAssets = {
    wood: lambert(0x9a6632),
    woodDark: lambert(0x6b4420),
    barrel: lambert(0xd23a4f, 0.3),
    ring: lambert(0x39404f),
    carBody: lambert(0xd7404f, 0.25),
    carGlass: lambert(0x18202e),
    wheel: lambert(0x11141a),
    lightMat: track(new THREE.MeshBasicMaterial({ color: 0xffe9b0 })),
    tailMat: track(new THREE.MeshBasicMaterial({ color: 0xff4d5e })),
    beamMat: track(new THREE.MeshBasicMaterial({
      color: 0xffe9b0,
      transparent: true,
      opacity: 0.14,
      depthWrite: false,
      blending: THREE.AdditiveBlending,
      side: THREE.DoubleSide,
    })),
    beamGeo: track(new THREE.PlaneGeometry(0.55, 3.4)),
    lightGeo: track(new THREE.BoxGeometry(0.18, 0.09, 0.05)),
    carPaints: [
      0xd7404f, 0x3f7fd6, 0xf0a63a, 0x3fbf7f, 0x9b59d0,
      0xdfe4ec, 0x5a6a80, 0x2ec5c5, 0xe66fb2, 0xf5d547,
    ].map((color) => lambert(color, 0.22)),
    busPaints: [0xc22b3d, 0x2f6fd0, 0x2f9e63, 0xd8892b].map((color) => lambert(color, 0.25)),
    tall: lambert(0xffb14a, 0.4),
    over: lambert(0x49a8ff, 0.45),
    busBody: lambert(0xc22b3d, 0.25),
    busRoof: lambert(0xe8e2d0),
    frame: lambert(0x2a3350),
  };
  return obstacleAssets;
};

// Every variant is centered on its origin so the existing collision math
// (position = center, userData.size = box extents) keeps working.
const obstacleBuilders = {
  'low-crate': () => {
    const a = getObstacleAssets();
    const g = new THREE.Group();
    g.add(new THREE.Mesh(track(new THREE.BoxGeometry(1.15, 1.15, 1.15)), a.wood));
    const plankGeo = track(new THREE.BoxGeometry(1.21, 0.16, 1.21));
    const plankH = new THREE.Mesh(plankGeo, a.woodDark);
    g.add(plankH);
    const plankV = new THREE.Mesh(plankGeo, a.woodDark);
    plankV.rotation.z = Math.PI / 2;
    g.add(plankV);
    return { mesh: g, size: { w: 1.15, h: 1.15, d: 1.15 } };
  },
  'low-barrel': () => {
    const a = getObstacleAssets();
    const g = new THREE.Group();
    g.add(new THREE.Mesh(track(new THREE.CylinderGeometry(0.55, 0.55, 1.35, 14)), a.barrel));
    const ringGeo = track(new THREE.CylinderGeometry(0.58, 0.58, 0.09, 14));
    [-0.42, 0.42].forEach((y) => {
      const ring = new THREE.Mesh(ringGeo, a.ring);
      ring.position.y = y;
      g.add(ring);
    });
    return { mesh: g, size: { w: 1.1, h: 1.35, d: 1.1 } };
  },
  'low-car': () => {
    const a = getObstacleAssets();
    const g = new THREE.Group();
    const body = new THREE.Mesh(track(new THREE.BoxGeometry(1.5, 0.5, 2.6)), a.carBody);
    body.position.y = -0.05;
    g.add(body);
    const cabin = new THREE.Mesh(track(new THREE.BoxGeometry(1.3, 0.42, 1.3)), a.carGlass);
    cabin.position.set(0, 0.34, 0.15);
    g.add(cabin);
    const wheelGeo = track(new THREE.CylinderGeometry(0.26, 0.26, 0.2, 10));
    wheelGeo.rotateZ(Math.PI / 2);
    const wheels = [];
    [[-0.72, 0.85], [0.72, 0.85], [-0.72, -0.85], [0.72, -0.85]].forEach(([x, z]) => {
      const wheel = new THREE.Mesh(wheelGeo, a.wheel);
      wheel.position.set(x, -0.29, z);
      wheels.push(wheel);
      g.add(wheel);
    });
    const lightGeo = track(new THREE.BoxGeometry(0.2, 0.1, 0.06));
    [-0.5, 0.5].forEach((x) => {
      const headlight = new THREE.Mesh(lightGeo, a.lightMat);
      headlight.position.set(x, -0.02, 1.31);
      g.add(headlight);
      const taillight = new THREE.Mesh(lightGeo, a.tailMat);
      taillight.position.set(x, -0.02, -1.31);
      g.add(taillight);
    });
    const beams = new THREE.Group();
    const beamGeo = track(new THREE.PlaneGeometry(0.55, 3.4));
    [-0.5, 0.5].forEach((x) => {
      const beam = new THREE.Mesh(beamGeo, a.beamMat);
      beam.rotation.x = -Math.PI / 2;
      beam.position.set(x, -0.52, 3.1);
      beams.add(beam);
    });
    beams.visible = false;
    g.add(beams);
    g.userData.paintMeshes = [body];
    g.userData.paintSet = 'car';
    g.userData.wheels = wheels;
    g.userData.beams = beams;
    return { mesh: g, size: { w: 1.5, h: 1.1, d: 2.6 } };
  },
  'tall-bus': () => {
    const a = getObstacleAssets();
    const g = new THREE.Group();
    const body = new THREE.Mesh(track(new THREE.BoxGeometry(1.5, 2.6, 3.4)), a.busBody);
    body.position.y = 0.15;
    g.add(body);
    const roof = new THREE.Mesh(track(new THREE.BoxGeometry(1.52, 0.12, 3.42)), a.busRoof);
    roof.position.y = 1.39;
    g.add(roof);
    const frontWinGeo = track(new THREE.BoxGeometry(1.3, 0.55, 0.06));
    const sideWinGeo = track(new THREE.BoxGeometry(0.06, 0.55, 3.0));
    [0.75, -0.35].forEach((y) => {
      const front = new THREE.Mesh(frontWinGeo, a.carGlass);
      front.position.set(0, y, 1.71);
      g.add(front);
      [-0.76, 0.76].forEach((x) => {
        const sideWin = new THREE.Mesh(sideWinGeo, a.carGlass);
        sideWin.position.set(x, y, 0);
        g.add(sideWin);
      });
    });
    const wheelGeo = track(new THREE.CylinderGeometry(0.3, 0.3, 0.22, 10));
    wheelGeo.rotateZ(Math.PI / 2);
    const wheels = [];
    [[-0.72, 1.1], [0.72, 1.1], [-0.72, -1.1], [0.72, -1.1]].forEach(([x, z]) => {
      const wheel = new THREE.Mesh(wheelGeo, a.wheel);
      wheel.position.set(x, -1.15, z);
      wheels.push(wheel);
      g.add(wheel);
    });
    const lightGeo = track(new THREE.BoxGeometry(0.2, 0.12, 0.06));
    [-0.5, 0.5].forEach((x) => {
      const headlight = new THREE.Mesh(lightGeo, a.lightMat);
      headlight.position.set(x, -0.95, 1.71);
      g.add(headlight);
      const taillight = new THREE.Mesh(lightGeo, a.tailMat);
      taillight.position.set(x, -0.95, -1.71);
      g.add(taillight);
    });
    const beams = new THREE.Group();
    const beamGeo = track(new THREE.PlaneGeometry(0.55, 3.4));
    [-0.5, 0.5].forEach((x) => {
      const beam = new THREE.Mesh(beamGeo, a.beamMat);
      beam.rotation.x = -Math.PI / 2;
      beam.position.set(x, -1.42, 3.5);
      beams.add(beam);
    });
    beams.visible = false;
    g.add(beams);
    g.userData.paintMeshes = [body];
    g.userData.paintSet = 'bus';
    g.userData.wheels = wheels;
    g.userData.beams = beams;
    return { mesh: g, size: { w: 1.5, h: 2.9, d: 3.4 } };
  },
  'tall-stack': () => {
    const a = getObstacleAssets();
    const g = new THREE.Group();
    const boxGeo = track(new THREE.BoxGeometry(1.2, 0.92, 1.2));
    [-0.93, 0, 0.93].forEach((y, i) => {
      const box = new THREE.Mesh(boxGeo, a.tall);
      box.position.y = y;
      box.rotation.y = (i - 1) * 0.18;
      g.add(box);
    });
    return { mesh: g, size: { w: 1.2, h: 2.8, d: 1.2 } };
  },
  'over-sign': () => {
    const a = getObstacleAssets();
    const g = new THREE.Group();
    const panel = new THREE.Mesh(track(new THREE.BoxGeometry(1.6, 0.55, 0.12)), a.over);
    g.add(panel);
    const frameGeo = track(new THREE.BoxGeometry(1.7, 0.09, 0.17));
    [0.3, -0.3].forEach((y) => {
      const rail = new THREE.Mesh(frameGeo, a.frame);
      rail.position.y = y;
      g.add(rail);
    });
    const rodGeo = track(new THREE.BoxGeometry(0.07, 1.5, 0.07));
    [-0.6, 0.6].forEach((x) => {
      const rod = new THREE.Mesh(rodGeo, a.frame);
      rod.position.set(x, 1.05, 0);
      g.add(rod);
    });
    return { mesh: g, size: { w: 1.6, h: 0.7, d: 1.2 } };
  },
};

const obstacleVariants = {
  low: ['low-crate', 'low-barrel', 'car-any'],
  tall: ['tall-stack', 'tall-any'],
  over: ['over-sign'],
};

// Low-poly vehicles from Kenney's CC0 Car Kit (kenney.nl), loaded at runtime.
// Until a model is ready the procedural fallbacks ('low-car'/'tall-bus') fill in.
const glbVehicleDefs = [
  { key: 'sedan', kind: 'car', fitLength: 2.6 },
  { key: 'sedan-sports', kind: 'car', fitLength: 2.6 },
  { key: 'hatchback-sports', kind: 'car', fitLength: 2.4 },
  { key: 'race', kind: 'car', fitLength: 2.5 },
  { key: 'taxi', kind: 'car', fitLength: 2.6 },
  { key: 'police', kind: 'car', fitLength: 2.7 },
  { key: 'suv', kind: 'car', fitLength: 2.6 },
  { key: 'suv-luxury', kind: 'car', fitLength: 2.7 },
  { key: 'van', kind: 'car', fitLength: 2.9 },
  { key: 'delivery', kind: 'car', fitLength: 2.9 },
  { key: 'ambulance', kind: 'tall', fitLength: 3.2 },
  { key: 'truck', kind: 'tall', fitLength: 3.4 },
  { key: 'firetruck', kind: 'tall', fitLength: 3.6 },
  { key: 'garbage-truck', kind: 'tall', fitLength: 3.5 },
  { key: 'cone', kind: 'prop', fitHeight: 1.05 },
  { key: 'box', kind: 'prop', fitHeight: 1.1 },
];

const glbTemplates = {};
const glbTraffic = { car: [], tall: [] };

const addVehicleLights = (group, size) => {
  const assets = getObstacleAssets();
  const beams = new THREE.Group();
  [-0.28, 0.28].forEach((f) => {
    const beam = new THREE.Mesh(assets.beamGeo, assets.beamMat);
    beam.rotation.x = -Math.PI / 2;
    beam.position.set(size.w * f, -size.h / 2 + 0.06, size.d / 2 + 1.75);
    beams.add(beam);
  });
  beams.visible = false;
  group.add(beams);
  group.userData.beams = beams;
  [-0.3, 0.3].forEach((f) => {
    const tail = new THREE.Mesh(assets.lightGeo, assets.tailMat);
    tail.position.set(size.w * f, -size.h * 0.2, -size.d / 2 - 0.02);
    group.add(tail);
  });
};

const registerVehicleModel = (def, model) => {
  const rawSize = new THREE.Box3().setFromObject(model).getSize(new THREE.Vector3());
  const scale = def.fitLength ? def.fitLength / rawSize.z : def.fitHeight / rawSize.y;
  model.scale.setScalar(scale);
  const box = new THREE.Box3().setFromObject(model);
  model.position.sub(box.getCenter(new THREE.Vector3()));
  const dims = box.getSize(new THREE.Vector3());
  const size = { w: dims.x, h: dims.y, d: dims.z };
  glbTemplates[def.key] = { model, size };

  obstacleBuilders[def.key] = () => {
    const mesh = new THREE.Group();
    const body = model.clone(true);
    mesh.add(body);
    const wheels = [];
    body.traverse((node) => {
      if (node.name && node.name.startsWith('wheel')) {
        wheels.push(node);
      }
    });
    mesh.userData.wheels = wheels;
    if (def.kind !== 'prop') {
      addVehicleLights(mesh, size);
    }
    return { mesh, size: { ...size } };
  };

  if (def.kind === 'car') {
    glbTraffic.car.push(def.key);
  } else if (def.kind === 'tall') {
    glbTraffic.tall.push(def.key);
  } else {
    obstacleVariants.low.push(def.key);
  }
};

const loadVehicleModels = () => {
  const loader = new GLTFLoader();
  glbVehicleDefs.forEach((def) => {
    loader.load(
      `/models/carkit/${def.key}.glb`,
      (gltf) => registerVehicleModel(def, gltf.scene),
      undefined,
      () => {},
    );
  });
};

const pickFrom = (list) => list[Math.floor(Math.random() * list.length)];

const resolveVariantKey = (key) => {
  if (key === 'car-any') {
    return glbTraffic.car.length ? pickFrom(glbTraffic.car) : 'low-car';
  }
  if (key === 'tall-any') {
    return glbTraffic.tall.length ? pickFrom(glbTraffic.tall) : 'tall-bus';
  }
  return key;
};

const getObstacle = (type, forcedKey = null) => {
  const keys = obstacleVariants[type];
  const key = resolveVariantKey(forcedKey || keys[Math.floor(Math.random() * keys.length)]);

  let obstacle;
  if (obstaclePools[key]?.length) {
    obstacle = obstaclePools[key].pop();
  } else {
    const { mesh, size } = obstacleBuilders[key]();
    mesh.userData.type = type;
    mesh.userData.poolKey = key;
    mesh.userData.size = size;
    obstacle = mesh;
  }
  obstacle.userData.vz = 0;
  obstacle.userData.passed = false;
  if (obstacle.userData.beams) {
    obstacle.userData.beams.visible = false;
  }
  if (obstacle.userData.paintMeshes) {
    const assets = getObstacleAssets();
    const palette = obstacle.userData.paintSet === 'bus' ? assets.busPaints : assets.carPaints;
    const paint = palette[Math.floor(Math.random() * palette.length)];
    obstacle.userData.paintMeshes.forEach((paintMesh) => {
      paintMesh.material = paint;
    });
  }
  return obstacle;
};

// Oncoming traffic: one or two vehicles in distinct lanes, each with its own
// speed. Occasionally a slow bus rolls toward the player instead of a car.
const spawnOncoming = () => {
  const laneOrder = shuffleList([0, 1, 2]);
  const count = Math.random() < 0.3 ? 2 : 1;
  const levelFactor = currentLevel.value.baseSpeed / 12;

  for (let i = 0; i < count; i += 1) {
    const isTruck = Math.random() < 0.14;
    const vehicle = getObstacle(isTruck ? 'tall' : 'low', isTruck ? 'tall-any' : 'car-any');
    vehicle.userData.vz = (isTruck ? 3 + Math.random() * 3 : 4 + Math.random() * 10) * levelFactor;
    if (vehicle.userData.beams) {
      vehicle.userData.beams.visible = true;
    }
    vehicle.rotation.y = 0;
    vehicle.position.set(
      lanes[laneOrder[i]],
      vehicle.userData.size.h / 2 + 0.02,
      -90 - i * 14 - Math.random() * 8,
    );
    obstacles.push(vehicle);
    scene.add(vehicle);
  }
};

const spawnRow = () => {
  // Sometimes oncoming traffic instead of a full row.
  if (Math.random() < 0.28) {
    spawnOncoming();
    return;
  }

  const pattern = rowPatterns[Math.floor(Math.random() * rowPatterns.length)];
  const baseZ = -60;

  pattern.forEach((type, laneIndex) => {
    if (type === 'none') return;
    const obstacle = getObstacle(type);
    const size = obstacle.userData.size;
    const y = type === 'over' ? 1.55 : size.h / 2 + 0.02;
    obstacle.rotation.y = 0;
    obstacle.position.set(lanes[laneIndex], y, baseZ);
    obstacles.push(obstacle);
    scene.add(obstacle);
  });

  const freeLanes = pattern
    .map((type, laneIndex) => (type === 'none' ? laneIndex : -1))
    .filter((laneIndex) => laneIndex >= 0);
  let coinLane = -1;
  if (freeLanes.length && Math.random() < 0.55) {
    coinLane = freeLanes[Math.floor(Math.random() * freeLanes.length)];
    spawnCoinLine(coinLane, baseZ);
  }
  const powerupLanes = freeLanes.filter((laneIndex) => laneIndex !== coinLane);
  if (powerupLanes.length && Math.random() < 0.08) {
    spawnPowerup(powerupLanes[Math.floor(Math.random() * powerupLanes.length)], baseZ);
  }
};

const checkCollision = (obstacle, playerHeight) => {
  const oSize = obstacle.userData.size;
  const dx = Math.abs(player.position.x - obstacle.position.x);
  const dy = Math.abs(player.position.y - obstacle.position.y);
  const dz = Math.abs(player.position.z - obstacle.position.z);

  if (
    dx >= (playerSize.w + oSize.w) / 2 ||
    dy >= (playerHeight + oSize.h) / 2 ||
    dz >= (playerSize.d + oSize.d) / 2
  ) {
    return false;
  }

  const obstacleTop = obstacle.position.y + oSize.h / 2;
  const playerBottom = player.position.y - playerHeight / 2;

  return playerBottom < obstacleTop - 0.05;
};

const updateRunner = (delta) => {
  const level = currentLevel.value;
  if (magnetTime.value > 0) {
    magnetTime.value = Math.max(0, magnetTime.value - delta);
  }
  if (multiTime.value > 0) {
    multiTime.value = Math.max(0, multiTime.value - delta);
  }
  const scoreMult = multiTime.value > 0 ? 2 : 1;
  score.value += speed.value * delta * 2.4 * scoreMult;
  const stepIndex = Math.floor(score.value / level.stepDistance);
  const targetSpeed = level.baseSpeed + stepIndex * level.speedStep;
  speed.value = THREE.MathUtils.damp(speed.value, targetSpeed, 4, delta);

  const targetX = lanes[currentLane];
  player.position.x = THREE.MathUtils.damp(player.position.x, targetX, 12, delta);

  const playerHeight = currentPlayerHeight();
  const prevY = player.position.y;
  playerVelocityY += gravity * delta;
  player.position.y += playerVelocityY * delta;

  let surfaceY = 0;
  const prevBottom = prevY - playerHeight / 2;
  const nextBottom = player.position.y - playerHeight / 2;
  let bestTop = -Infinity;
  for (const obstacle of obstacles) {
    const oSize = obstacle.userData.size;
    const dx = Math.abs(player.position.x - obstacle.position.x);
    const dz = Math.abs(player.position.z - obstacle.position.z);
    if (dx >= (playerSize.w + oSize.w) / 2 || dz >= (playerSize.d + oSize.d) / 2) {
      continue;
    }
    const top = obstacle.position.y + oSize.h / 2;
    const landing =
      playerVelocityY <= 0 &&
      prevBottom >= top - 0.05 &&
      nextBottom <= top + 0.12;
    if (landing && top > bestTop) {
      bestTop = top;
    }
  }
  if (bestTop > -Infinity) {
    surfaceY = bestTop;
  }

  const targetGround = getGroundCenterForSurface(surfaceY, playerHeight);
  currentSurfaceY = surfaceY;
  currentGroundCenter = targetGround;
  if (player.position.y <= targetGround) {
    if (playerVelocityY < -6) {
      sfx.land();
      spawnBurst(
        new THREE.Vector3(player.position.x, targetGround - playerHeight / 2 + 0.06, player.position.z),
        ['dust'],
        5,
        2.5,
      );
    }
    player.position.y = targetGround;
    playerVelocityY = 0;
    lastGroundedAt = performance.now();
    if (pendingSlide && !isSliding) {
      pendingSlide = false;
      requestSlide();
    }
  }

  if (isSliding) {
    slideTimer -= delta;
    if (slideTimer <= 0) {
      stopSlide();
    }
  }

  const collisionPlayerHeight = currentPlayerHeight();

  animateRunner(delta);

  camera.fov = THREE.MathUtils.damp(
    camera.fov,
    60 + Math.min(14, Math.max(0, speed.value - 10) * 0.55),
    3,
    delta,
  );
  camera.updateProjectionMatrix();

  floorSegments.forEach((segment) => {
    segment.position.z += speed.value * delta;
    if (segment.position.z > 30) {
      segment.position.z -= segmentLength * floorSegments.length;
    }
  });

  for (let i = coins.length - 1; i >= 0; i -= 1) {
    const coin = coins[i];
    coin.position.z += speed.value * delta;
    coin.rotation.y += delta * 5;
    if (magnetTime.value > 0) {
      const dx = player.position.x - coin.position.x;
      const dy = player.position.y - coin.position.y;
      const dz = player.position.z - coin.position.z;
      if (dx * dx + dy * dy + dz * dz < 42) {
        const pull = Math.min(1, delta * 9);
        coin.position.x += dx * pull;
        coin.position.y += dy * pull;
        coin.position.z += dz * pull;
      }
    }
    if (coin.position.z > 18) {
      scene.remove(coin);
      coinPool.push(coin);
      coins.splice(i, 1);
      continue;
    }
    if (
      Math.abs(coin.position.z - player.position.z) < 0.8 &&
      Math.abs(coin.position.x - player.position.x) < 0.75 &&
      Math.abs(coin.position.y - player.position.y) < 1.15
    ) {
      runCoins.value += 1;
      sfx.coin();
      spawnBurst(coin.position, ['gold'], 4, 3.5);
      scene.remove(coin);
      coinPool.push(coin);
      coins.splice(i, 1);
    }
  }

  for (let i = powerups.length - 1; i >= 0; i -= 1) {
    const powerup = powerups[i];
    powerup.position.z += speed.value * delta;
    powerup.rotation.y += delta * 2.2;
    powerup.userData.phase += delta * 3;
    powerup.userData.core.position.y = Math.sin(powerup.userData.phase) * 0.09;
    if (powerup.position.z > 18) {
      scene.remove(powerup);
      powerupPool[powerup.userData.type].push(powerup);
      powerups.splice(i, 1);
      continue;
    }
    if (
      Math.abs(powerup.position.z - player.position.z) < 0.9 &&
      Math.abs(powerup.position.x - player.position.x) < 0.8 &&
      Math.abs(powerup.position.y - player.position.y) < 1.3
    ) {
      activatePowerup(powerup.userData.type);
      spawnBurst(powerup.position, ['gold', 'skin'], 8, 5);
      scene.remove(powerup);
      powerupPool[powerup.userData.type].push(powerup);
      powerups.splice(i, 1);
    }
  }

  spawnTimer -= delta;
  if (spawnTimer <= 0) {
    spawnRow();
    const spacingFactor = 1 + Math.min(1.2, speed.value / 24);
    spawnTimer = THREE.MathUtils.randFloat(0.5, 1.5) * (14 / speed.value) * spacingFactor;
  }

  for (let i = obstacles.length - 1; i >= 0; i -= 1) {
    const obstacle = obstacles[i];
    const vz = obstacle.userData.vz || 0;
    obstacle.position.z += (speed.value + vz) * delta;
    if (vz > 0) {
      if (obstacle.userData.wheels) {
        const spin = (vz * delta) / 0.27;
        obstacle.userData.wheels.forEach((wheel) => {
          wheel.rotation.x += spin;
        });
      }
      if (!obstacle.userData.passed && obstacle.position.z > player.position.z + 1.2) {
        obstacle.userData.passed = true;
        if (state.value === 'running') {
          const lateral = Math.abs(obstacle.position.x - player.position.x);
          // A real near miss needs the player to be mid-dodge right next to
          // the car — standing in the neighbouring lane (2.0 apart) is safe.
          if (lateral < 1.6) {
            triggerNearMiss();
            spawnBurst(
              new THREE.Vector3(obstacle.position.x, 1, obstacle.position.z),
              ['gold'],
              6,
              4,
            );
          } else if (lateral < 3.6) {
            sfx.carPass(lateral);
          }
        }
      }
    }
    if (obstacle.position.z > 18) {
      scene.remove(obstacle);
      const key = obstacle.userData?.poolKey;
      if (key) {
        (obstaclePools[key] ||= []).push(obstacle);
      }
      obstacles.splice(i, 1);
      continue;
    }
    if (checkCollision(obstacle, collisionPlayerHeight)) {
      if (performance.now() < invulnUntil) {
        continue;
      }
      if (shieldActive.value) {
        shieldActive.value = false;
        invulnUntil = performance.now() + 900;
        sfx.shieldBreak();
        spawnBurst(obstacle.position, ['red', 'dust'], 14, 6);
        scene.remove(obstacle);
        const poolKey = obstacle.userData?.poolKey;
        if (poolKey) {
          (obstaclePools[poolKey] ||= []).push(obstacle);
        }
        obstacles.splice(i, 1);
        continue;
      }
      startCrash();
      break;
    }
  }

  if (shieldMesh?.visible) {
    shieldMesh.rotation.y += delta * 1.4;
  }

  camera.position.x = THREE.MathUtils.damp(camera.position.x, player.position.x * 0.35, 4, delta);
  lookAtTarget.set(player.position.x * 0.4, 1.2, -15);
  camera.lookAt(lookAtTarget);
};

const startCrash = () => {
  state.value = 'crashing';
  crashTimer = 0.75;
  player.visible = false;
  sfx.crash();
  spawnBurst(player.position, ['skin', 'red', 'skin'], 26, 8);
};

const animate = (time) => {
  animationId = requestAnimationFrame(animate);
  const delta = Math.min(0.05, (time - lastTime) / 1000 || 0);
  lastTime = time;

  if (state.value === 'running') {
    updateRunner(delta);
  } else if (state.value !== 'crashing' && state.value !== 'paused') {
    animateIdle(delta);
  }
  if (state.value === 'crashing') {
    crashTimer -= delta;
    camera.position.x += (Math.random() - 0.5) * 0.12 * Math.max(0, crashTimer);
    camera.position.y += (Math.random() - 0.5) * 0.08 * Math.max(0, crashTimer);
    if (crashTimer <= 0) {
      applyCameraZoom();
      endRun();
    }
  }

  if (state.value !== 'paused') {
    updateParticles(delta);
  }

  renderer.render(scene, camera);
};

watch(selectedSkin, () => {
  if (!currentSkin.value) return;
  if (playerMaterial) {
    playerMaterial.color.set(currentSkin.value.color);
  }
  if (limbMaterial) {
    limbMaterial.color.set(currentSkin.value.color).multiplyScalar(0.55);
  }
  if (particleMaterials.skin) {
    particleMaterials.skin.color.set(currentSkin.value.color);
  }
});

watch(authUser, () => {
  showLoginPrompt.value = false;
  if (!authUser.value && menuScreen.value === 'inventory') {
    menuScreen.value = 'main';
  }
  loadProfile();
  loadLeaderboard();
  checkAuthGate();
});

watch(state, () => {
  if (state.value === 'running') {
    showPlaylist.value = false;
  }
  if (state.value !== 'menu') {
    menuScreen.value = 'main';
  }
  syncPlaylist();
});

watch(audioVolume, () => {
  applyAudioVolume();
  persistAudioPrefs();
});

watch(isMuted, () => {
  applyAudioVolume();
  persistAudioPrefs();
});

watch(cameraZoom, () => {
  applyCameraZoom();
});

watch(shieldActive, () => {
  if (shieldMesh) {
    shieldMesh.visible = shieldActive.value;
  }
});

const handleVisibility = () => {
  if (document.hidden) {
    pauseRun();
  }
};

onMounted(() => {
  loadAudioPrefs();
  initAudio();
  pointerUnlockHandler = () => unlockAudio();
  window.addEventListener('pointerdown', pointerUnlockHandler, { once: true });
  document.addEventListener('visibilitychange', handleVisibility);
  initScene();
  loadVehicleModels();
  handleResize();
  checkAuthGate();
  loadProfile();
  loadLeaderboard();
  resetRun();
  animate(0);
});

onBeforeUnmount(() => {
  if (animationId) {
    cancelAnimationFrame(animationId);
  }
  if (nearMissTimer) {
    clearTimeout(nearMissTimer);
  }
  if (pointerUnlockHandler) {
    window.removeEventListener('pointerdown', pointerUnlockHandler);
  }
  fadeToken += 1;
  [audioPrimary, audioSecondary].forEach((audio) => {
    if (!audio) return;
    audio.onended = null;
    audio.pause();
    audio.removeAttribute('src');
  });
  document.removeEventListener('visibilitychange', handleVisibility);
  if (sfxCtx) {
    sfxCtx.close().catch(() => {});
  }
  window.removeEventListener('resize', handleResize);
  window.removeEventListener('keydown', handleKeydown);
  window.removeEventListener('touchstart', handleTouchStart);
  window.removeEventListener('touchmove', handleTouchMove);
  window.removeEventListener('touchend', handleTouchEnd);
  if (renderer && renderer.domElement && canvasWrap.value) {
    canvasWrap.value.removeChild(renderer.domElement);
  }
  scene?.traverse((object) => {
    if (object.isMesh) {
      object.geometry?.dispose();
      const materials = Array.isArray(object.material) ? object.material : [object.material];
      materials.forEach((material) => material?.dispose());
    }
  });
  obstacleResources.forEach((resource) => resource?.dispose());
  Object.values(glbTemplates).forEach(({ model }) => {
    model.traverse((node) => {
      if (node.isMesh) {
        node.geometry?.dispose();
        const materials = Array.isArray(node.material) ? node.material : [node.material];
        materials.forEach((material) => {
          material?.map?.dispose();
          material?.dispose();
        });
      }
    });
  });
  if (powerupAssets) {
    Object.values(powerupAssets).forEach((resource) => resource?.dispose?.());
  }
  coinGeometry?.dispose();
  coinMaterial?.dispose();
  glowTexture?.dispose();
  starPoints?.geometry?.dispose();
  starPoints?.material?.dispose();
  particleGeometry?.dispose();
  Object.values(particleMaterials).forEach((material) => material?.dispose());
  buildingGeometry?.dispose();
  renderer?.dispose();
});
</script>

<style scoped>
@import url('https://fonts.googleapis.com/css2?family=Bebas+Neue&display=swap');
:global(html),
:global(body),
:global(#app) {
  width: 100%;
  height: 100%;
  overflow: hidden;
  overscroll-behavior: none;
}

:global(body) {
  margin: 0;
  background: #05070f;
}

.runner-page {
  position: relative;
  width: 100vw;
  height: 100vh;
  height: 100svh;
  height: 100dvh;
  overflow: hidden;
  color: #e9f0ff;
  background:
    radial-gradient(circle at 20% 20%, rgba(70, 120, 200, 0.2), transparent 45%),
    radial-gradient(circle at 80% 10%, rgba(255, 120, 80, 0.18), transparent 42%),
    linear-gradient(180deg, #05070f 0%, #0a1020 60%, #05070f 100%);
  font-family: 'Agency FB', 'Bahnschrift', 'Segoe UI', sans-serif;
}

.runner-canvas {
  position: absolute;
  inset: 0;
  z-index: 1;
}

.runner-canvas canvas {
  width: 100%;
  height: 100%;
  display: block;
}

.hud {
  position: absolute;
  top: 24px;
  left: 24px;
  right: 24px;
  display: flex;
  justify-content: space-between;
  z-index: 3;
  pointer-events: none;
}

.hud-block {
  background: rgba(8, 12, 22, 0.7);
  border: 1px solid rgba(88, 140, 255, 0.4);
  padding: 12px 18px;
  border-radius: 14px;
  box-shadow: 0 12px 30px rgba(0, 0, 0, 0.35);
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.hud-label {
  font-size: 0.65rem;
  opacity: 0.7;
}

.hud-value {
  font-size: 1.4rem;
  font-weight: 700;
}

.near-miss {
  position: absolute;
  top: 30%;
  left: 50%;
  z-index: 3;
  pointer-events: none;
  color: #ffd766;
  font-family: 'Bebas Neue', 'Oswald', 'Segoe UI', sans-serif;
  font-size: 1.6rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  text-shadow: 0 0 18px rgba(255, 190, 70, 0.65);
  animation: nearMissPop 0.95s ease-out forwards;
}

.power-row {
  position: absolute;
  top: calc(96px + env(safe-area-inset-top));
  left: 50%;
  transform: translateX(-50%);
  display: flex;
  gap: 8px;
  z-index: 3;
  pointer-events: none;
}

.power-chip {
  padding: 6px 12px;
  border-radius: 999px;
  font-size: 0.72rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  background: rgba(8, 12, 22, 0.72);
  border: 1px solid;
}

.power-chip.shield {
  color: #7deeff;
  border-color: rgba(53, 224, 255, 0.6);
}

.power-chip.magnet {
  color: #ff9dea;
  border-color: rgba(255, 79, 216, 0.6);
}

.power-chip.multi {
  color: #9dff8a;
  border-color: rgba(103, 240, 90, 0.6);
}

.pause-btn {
  position: absolute;
  top: calc(96px + env(safe-area-inset-top));
  right: calc(24px + env(safe-area-inset-right));
  z-index: 4;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  border: 1px solid rgba(90, 140, 255, 0.4);
  background: rgba(8, 12, 22, 0.7);
  color: #cfe0ff;
  font-weight: 700;
  letter-spacing: 0.1em;
  cursor: pointer;
}

.pause-hint {
  margin-top: 14px;
  font-size: 0.7rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: rgba(190, 210, 255, 0.55);
}

@keyframes nearMissPop {
  0% {
    transform: translate(-50%, 14px) scale(0.7);
    opacity: 0;
  }
  18% {
    transform: translate(-50%, 0) scale(1.08);
    opacity: 1;
  }
  70% {
    transform: translate(-50%, -14px) scale(1);
    opacity: 1;
  }
  100% {
    transform: translate(-50%, -30px) scale(0.96);
    opacity: 0;
  }
}

.menu-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  align-items: stretch;
  justify-content: flex-start;
  background: linear-gradient(
    90deg,
    rgba(6, 8, 18, 0.92) 0%,
    rgba(6, 8, 18, 0.7) 38%,
    rgba(6, 8, 18, 0.3) 60%,
    rgba(6, 8, 18, 0) 100%
  );
  z-index: 3;
  animation: fadeIn 0.6s ease;
}

.menu-layout {
  width: min(620px, 92vw);
  height: 100%;
  padding: calc(156px + env(safe-area-inset-top)) 0 calc(40px + env(safe-area-inset-bottom)) 64px;
  display: flex;
  align-items: flex-start;
}

.menu-left {
  display: grid;
  gap: 24px;
  max-width: 520px;
  min-height: 0;
}

.menu-eyebrow {
  font-size: 0.75rem;
  letter-spacing: 0.4em;
  text-transform: uppercase;
  color: rgba(150, 190, 255, 0.7);
}

.menu-title-text {
  font-family: 'Bebas Neue', 'Oswald', 'Segoe UI', sans-serif;
  font-size: 3.2rem;
  margin: 0;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.menu-sub {
  margin: 0;
  color: rgba(220, 230, 255, 0.7);
  line-height: 1.5;
}

.menu-title {
  display: grid;
  gap: 12px;
}

.auth-bar {
  position: absolute;
  top: calc(20px + env(safe-area-inset-top));
  right: calc(24px + env(safe-area-inset-right));
  display: flex;
  align-items: center;
  gap: 10px;
  z-index: 6;
}

.auth-user {
  font-size: 0.8rem;
  color: rgba(210, 225, 255, 0.85);
  background: rgba(8, 12, 22, 0.6);
  border: 1px solid rgba(90, 140, 255, 0.35);
  padding: 6px 10px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.12em;
}

.audio-pill {
  position: absolute;
  top: calc(24px + env(safe-area-inset-top));
  left: calc(24px + env(safe-area-inset-left));
  display: inline-flex;
  align-items: center;
  gap: 10px;
  padding: 8px 12px;
  border-radius: 999px;
  background: rgba(8, 12, 22, 0.7);
  border: 1px solid rgba(90, 140, 255, 0.35);
  color: rgba(225, 235, 255, 0.9);
  cursor: pointer;
  z-index: 6;
  transition: box-shadow 0.3s ease;
}

.audio-pill.running {
  top: calc(92px + env(safe-area-inset-top));
}

.audio-pill.expanded {
  box-shadow: 0 10px 24px rgba(6, 10, 20, 0.5);
}

.audio-icon {
  width: 18px;
  height: 18px;
  display: block;
}

.audio-pill .audio-mute {
  opacity: 0;
}

.audio-pill.muted .audio-wave {
  opacity: 0;
}

.audio-pill.muted .audio-mute {
  opacity: 1;
}

.audio-title {
  max-width: 0;
  overflow: hidden;
  white-space: nowrap;
  font-size: 0.75rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  transition: max-width 0.4s ease, opacity 0.4s ease;
  opacity: 0;
}

.audio-pill.expanded .audio-title {
  max-width: 220px;
  opacity: 1;
}

.audio-panel {
  position: absolute;
  top: calc(76px + env(safe-area-inset-top));
  left: calc(24px + env(safe-area-inset-left));
  width: min(320px, 92vw);
  padding: 16px;
  border-radius: 16px;
  border: 1px solid rgba(120, 180, 255, 0.35);
  background: rgba(10, 14, 24, 0.95);
  display: grid;
  gap: 14px;
  z-index: 5;
  box-shadow: 0 24px 50px rgba(0, 0, 0, 0.45);
}

.audio-backdrop {
  position: absolute;
  inset: 0;
  background: rgba(4, 6, 12, 0.35);
  z-index: 4;
}

.audio-panel-head {
  display: flex;
  justify-content: space-between;
  align-items: center;
}

.audio-panel-title {
  text-transform: uppercase;
  letter-spacing: 0.25em;
  font-size: 0.7rem;
  color: rgba(190, 210, 255, 0.85);
}

.audio-controls {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.audio-volume {
  display: grid;
  gap: 6px;
  font-size: 0.75rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: rgba(190, 210, 255, 0.7);
}

.audio-volume input[type='range'] {
  width: 100%;
}

.audio-section {
  display: grid;
  gap: 8px;
}

.audio-section-title {
  text-transform: uppercase;
  letter-spacing: 0.2em;
  font-size: 0.65rem;
  color: rgba(170, 200, 255, 0.7);
}

.audio-list {
  display: grid;
  gap: 6px;
}

.audio-row {
  display: grid;
  grid-template-columns: 28px 1fr;
  gap: 8px;
  align-items: center;
}

.audio-toggle {
  width: 24px;
  height: 24px;
  border-radius: 6px;
  border: 1px solid rgba(120, 180, 255, 0.35);
  background: rgba(10, 14, 24, 0.7);
  color: rgba(225, 235, 255, 0.85);
  cursor: pointer;
  text-transform: uppercase;
}

.audio-toggle.off {
  opacity: 0.5;
}

.audio-track {
  border: none;
  background: transparent;
  color: rgba(225, 235, 255, 0.85);
  text-align: left;
  padding: 0;
  cursor: pointer;
  font-size: 0.75rem;
  letter-spacing: 0.08em;
}

.menu-nav {
  display: grid;
  gap: 10px;
  margin-top: 16px;
}

.menu-link {
  display: block;
  width: 100%;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  font-size: 0.85rem;
  padding: 12px 0;
  border: none;
  color: rgba(224, 235, 255, 0.85);
  background: transparent;
  cursor: pointer;
  text-align: left;
  position: relative;
}

.menu-link.primary {
  color: #05070f;
  background: linear-gradient(120deg, #39f9c0, #25a6ff);
  padding: 12px 16px;
  border-radius: 12px;
  box-shadow: 0 12px 30px rgba(40, 210, 190, 0.25);
  letter-spacing: 0.18em;
}

.menu-link.active {
  color: #f6fffd;
}

.menu-link.active::after {
  content: '';
  position: absolute;
  left: 0;
  bottom: 2px;
  width: 120px;
  height: 2px;
  background: linear-gradient(90deg, rgba(80, 220, 190, 0.95), transparent);
}

.menu-link.danger {
  color: rgba(255, 190, 190, 0.9);
}

.menu-screen {
  width: min(720px, 92vw);
  height: 100%;
  padding: calc(152px + env(safe-area-inset-top)) 0 calc(32px + env(safe-area-inset-bottom)) 64px;
  display: grid;
  align-content: start;
  gap: 18px;
}

.menu-screen-head {
  display: flex;
  align-items: center;
  gap: 12px;
}

.menu-screen-title {
  flex: 1;
  text-transform: uppercase;
  letter-spacing: 0.3em;
  font-size: 0.75rem;
  color: rgba(190, 210, 255, 0.85);
}

.menu-screen-card {
  border-radius: 18px;
  border: 1px solid rgba(120, 180, 255, 0.35);
  background: rgba(10, 14, 24, 0.85);
  padding: 18px;
  display: grid;
  gap: 16px;
  max-height: calc(100dvh - 180px);
  overflow: hidden;
}

.menu-settings-grid {
  display: grid;
  gap: 16px;
}

.leaderboard-panel {
  display: grid;
  gap: 16px;
}

.leaderboard-stat {
  border-radius: 14px;
  border: 1px solid rgba(120, 180, 255, 0.3);
  background: rgba(8, 12, 22, 0.6);
  padding: 14px 16px;
  display: grid;
  gap: 6px;
}

.leaderboard-label {
  font-size: 0.7rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: rgba(170, 200, 255, 0.7);
}

.leaderboard-value {
  font-size: 1.6rem;
  font-weight: 700;
  color: rgba(230, 240, 255, 0.95);
}

.menu-panel {
  display: grid;
  gap: 18px;
}

.menu-grid {
  display: grid;
  gap: 18px;
}

.menu-field {
  display: grid;
  gap: 10px;
}

.menu-field label {
  font-size: 0.85rem;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  color: rgba(180, 200, 255, 0.7);
}

.menu-field select {
  background: rgba(12, 16, 30, 0.9);
  border: 1px solid rgba(90, 140, 255, 0.4);
  border-radius: 12px;
  padding: 10px 14px;
  color: #f0f5ff;
  font-size: 1rem;
}

.menu-field input[type='range'] {
  width: 100%;
}

.menu-slider-value {
  font-size: 0.7rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  color: rgba(170, 200, 255, 0.7);
}

.skin-row {
  display: flex;
  flex-wrap: wrap;
  gap: 10px;
}

.skin-chip {
  border: 1px solid rgba(120, 180, 255, 0.35);
  color: #e6f0ff;
  background: rgba(10, 14, 24, 0.8);
  padding: 8px 14px;
  border-radius: 999px;
  text-transform: uppercase;
  letter-spacing: 0.15em;
  font-size: 0.7rem;
  position: relative;
}

.skin-chip::before {
  content: '';
  width: 10px;
  height: 10px;
  border-radius: 50%;
  background: var(--skin);
  display: inline-block;
  margin-right: 8px;
  box-shadow: 0 0 8px var(--skin);
}

.skin-chip.active {
  border-color: rgba(120, 240, 200, 0.6);
  box-shadow: 0 0 12px rgba(70, 220, 170, 0.4);
}

.skin-chip.locked {
  opacity: 0.6;
  box-shadow: none;
}

.skin-price {
  margin-left: 8px;
  padding: 2px 7px;
  border-radius: 999px;
  background: rgba(255, 207, 77, 0.15);
  border: 1px solid rgba(255, 207, 77, 0.4);
  color: #ffcf4d;
  font-size: 0.62rem;
  letter-spacing: 0.08em;
}

.shop-balance {
  font-size: 0.75rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: #ffcf4d;
}

.shop-message {
  font-size: 0.8rem;
  color: rgba(255, 220, 150, 0.9);
}

.hud-block.coins .hud-value {
  color: #ffcf4d;
}

.death-coins {
  color: #ffcf4d;
}

.skin-chip.locked::before {
  box-shadow: none;
}

.menu-actions {
  display: flex;
  gap: 12px;
}

.primary-btn,
.ghost-btn {
  padding: 12px 18px;
  border-radius: 12px;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  font-size: 0.75rem;
  font-weight: 700;
  cursor: pointer;
}

.primary-btn.small,
.ghost-btn.small {
  padding: 8px 12px;
  font-size: 0.65rem;
  letter-spacing: 0.15em;
}

.primary-btn {
  background: linear-gradient(120deg, #39f9c0, #25a6ff);
  border: none;
  color: #060812;
  box-shadow: 0 12px 30px rgba(40, 210, 190, 0.35);
}

.ghost-btn {
  background: transparent;
  border: 1px solid rgba(120, 180, 255, 0.4);
  color: #e5f0ff;
}

.menu-result {
  display: flex;
  justify-content: space-between;
  font-size: 0.95rem;
  color: rgba(210, 220, 255, 0.8);
}

.menu-stats {
  display: grid;
  gap: 10px;
  color: rgba(210, 220, 255, 0.85);
  font-size: 0.95rem;
}

.menu-locked {
  display: grid;
  gap: 12px;
  padding: 16px;
  border-radius: 16px;
  border: 1px dashed rgba(120, 180, 255, 0.35);
  background: rgba(10, 14, 24, 0.5);
}

.menu-locked-title {
  text-transform: uppercase;
  letter-spacing: 0.2em;
  font-size: 0.8rem;
  color: rgba(200, 210, 255, 0.85);
}

.menu-locked-actions {
  display: flex;
  gap: 10px;
}

.menu-inventory {
  display: grid;
  gap: 12px;
}

.menu-inventory-list {
  display: grid;
  gap: 10px;
  max-height: 40vh;
  overflow: auto;
  padding-right: 4px;
}

.menu-inventory-item {
  display: flex;
  justify-content: space-between;
  align-items: center;
  background: rgba(10, 14, 24, 0.65);
  border: 1px solid rgba(120, 180, 255, 0.25);
  border-radius: 12px;
  padding: 10px 14px;
}

.menu-item-name {
  font-size: 0.95rem;
  color: rgba(235, 240, 255, 0.9);
}

.menu-item-meta {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.2em;
  color: rgba(140, 180, 255, 0.7);
}

.menu-item-qty {
  font-size: 1rem;
  font-weight: 700;
}

.menu-empty {
  color: rgba(200, 210, 255, 0.7);
  font-style: italic;
}

.menu-settings {
  display: grid;
  gap: 10px;
  color: rgba(210, 220, 255, 0.8);
}

.menu-settings-title {
  text-transform: uppercase;
  letter-spacing: 0.2em;
  font-size: 0.8rem;
}

.menu-leaderboard {
  background: rgba(10, 14, 24, 0.6);
  border: 1px solid rgba(120, 180, 255, 0.3);
  border-radius: 16px;
  padding: 12px 14px;
  display: grid;
  gap: 8px;
}

.menu-leaderboard-title {
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.3em;
  color: rgba(160, 200, 255, 0.7);
}

.menu-leaderboard ol {
  margin: 0;
  padding-left: 18px;
  display: grid;
  gap: 6px;
}

.menu-leaderboard li {
  display: flex;
  justify-content: space-between;
  gap: 12px;
}

.menu-controls {
  font-size: 0.8rem;
  color: rgba(190, 200, 230, 0.7);
  display: grid;
  gap: 6px;
}

.gate-overlay {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(4, 6, 12, 0.8);
  z-index: 8;
}

.death-overlay {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(4, 6, 12, 0.45);
  z-index: 7;
}

.modal-overlay {
  position: absolute;
  inset: 0;
  display: grid;
  place-items: center;
  background: rgba(4, 6, 12, 0.75);
  z-index: 9;
}

.death-card {
  width: min(420px, 90vw);
  padding: 26px;
  border-radius: 18px;
  border: 1px solid rgba(120, 180, 255, 0.35);
  background: rgba(10, 14, 24, 0.95);
  display: grid;
  gap: 16px;
  text-align: center;
}

.modal-card {
  width: min(420px, 90vw);
  padding: 24px;
  border-radius: 18px;
  border: 1px solid rgba(120, 180, 255, 0.35);
  background: rgba(10, 14, 24, 0.95);
  display: grid;
  gap: 16px;
  text-align: center;
}

.modal-title {
  text-transform: uppercase;
  letter-spacing: 0.3em;
  font-size: 0.85rem;
  color: rgba(180, 210, 255, 0.9);
}

.modal-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
}

.death-title {
  text-transform: uppercase;
  letter-spacing: 0.3em;
  font-size: 0.85rem;
  color: rgba(180, 210, 255, 0.9);
}

.death-score {
  display: grid;
  gap: 6px;
  color: rgba(220, 230, 255, 0.85);
}

.death-actions {
  display: grid;
  gap: 10px;
}

.gate-card {
  width: min(420px, 90vw);
  padding: 24px;
  border-radius: 18px;
  border: 1px solid rgba(120, 180, 255, 0.4);
  background: rgba(10, 14, 24, 0.95);
  display: grid;
  gap: 16px;
  text-align: center;
}

.gate-title {
  text-transform: uppercase;
  letter-spacing: 0.3em;
  font-size: 0.9rem;
  color: rgba(180, 210, 255, 0.9);
}

.gate-actions {
  display: grid;
  gap: 10px;
}

.primary-btn.alt {
  background: linear-gradient(120deg, #ffcf6b, #ff7a59);
  color: #1a0d00;
}

@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@media (max-width: 768px) {
  .hud {
    top: calc(12px + env(safe-area-inset-top));
    left: 16px;
    right: 16px;
  }

  .auth-bar {
    top: calc(12px + env(safe-area-inset-top));
    right: 16px;
  }

  .audio-pill {
    top: calc(16px + env(safe-area-inset-top));
    left: 16px;
  }

  .audio-pill.running {
    top: calc(72px + env(safe-area-inset-top));
  }

  .audio-panel {
    top: calc(64px + env(safe-area-inset-top));
    left: 16px;
  }

  .menu-layout,
  .menu-screen {
    width: 100%;
    padding: calc(124px + env(safe-area-inset-top)) 20px calc(24px + env(safe-area-inset-bottom));
  }

  .menu-left {
    gap: 16px;
  }

  .menu-title-text {
    font-size: 2.2rem;
  }

  .menu-sub {
    display: none;
  }

  .menu-nav {
    grid-template-columns: minmax(0, 1fr);
    gap: 6px;
    margin-top: 10px;
  }

  .menu-link {
    text-align: left;
    padding: 10px 12px;
    font-size: 0.72rem;
    letter-spacing: 0.16em;
    width: min(240px, 60%);
  }

  .menu-screen-card {
    max-height: calc(100dvh - 140px);
  }

  .menu-inventory-list {
    max-height: 34vh;
  }

  .menu-controls {
    font-size: 0.7rem;
  }
}
</style>
