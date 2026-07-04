<template>
  <div class="runner-page">
    <div ref="canvasWrap" class="runner-canvas"></div>

    <div v-if="state === 'menu' && !showAuthGate && !authUser" class="auth-bar">
      <Link class="ghost-btn small" href="/login">Login</Link>
      <Link class="primary-btn small" href="/register">Register</Link>
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
        <div
          class="hud-value"
          :class="{ low: finalePhase === 'drive' && speed < 15 }"
        >
          {{ speed.toFixed(1) }}
        </div>
      </div>
    </div>

    <div v-if="devRun && state !== 'menu'" class="dev-badge">
      Dev Run &mdash; not saved
    </div>

    <div v-if="finaleToast && state === 'running'" class="finale-toast">
      <div class="finale-toast-title">District Cleared</div>
      <div class="finale-toast-sub">You found the end of the line</div>
    </div>

    <div v-if="eventToast && state === 'running'" class="finale-toast event-toast">
      <div class="finale-toast-title">{{ eventToast.title }}</div>
      <div class="finale-toast-sub">{{ eventToast.sub }}</div>
    </div>

    <div
      v-if="state === 'running' && finalePhase === 'drive' && score >= RAMP_SCORE && !godModeActive"
      class="speed-goal"
      :class="{ ok: speed >= godTriggerSpeed }"
    >
      <div class="speed-goal-title">
        {{ speed >= godTriggerSpeed ? 'HOLD IT!' : 'SPEED UP!' }}
      </div>
      <div class="speed-goal-value">{{ Math.round(speed) }} / {{ godTriggerSpeed }}</div>
    </div>

    <div v-if="driveHint && state === 'running'" class="drive-hint">
      {{ driveHintText }}
    </div>

    <div
      v-if="finalePhase === 'plane' && (state === 'running' || state === 'paused')"
      class="hp-bar"
    >
      <div
        class="hp-fill"
        :class="{ hurt: playerHp < 35 }"
        :style="{ width: playerHp + '%' }"
      ></div>
    </div>

    <div v-if="damageFlash && state === 'running'" class="damage-flash"></div>

    <div
      v-if="state === 'running' && finalePhase === 'plane'"
      class="joystick"
      @pointerdown="joyStart"
      @pointermove="joyMove"
      @pointerup="joyEnd"
      @pointercancel="joyEnd"
    >
      <div
        class="joystick-knob"
        :style="{ transform: `translate(${joyKnob.x}px, ${joyKnob.y}px)` }"
      ></div>
    </div>

    <div
      v-if="
        state === 'running' &&
        (finalePhase === 'drive' || finalePhase === 'ramp') &&
        isTouchDevice
      "
      class="pedals"
      :class="handedness === 'left' ? 'pedals-right' : 'pedals-left'"
    >
      <button
        class="pedal gas"
        type="button"
        @pointerdown.prevent="pedalGasDown"
        @pointerup="pedalGasUp"
        @pointercancel="pedalGasUp"
        @pointerleave="pedalGasUp"
      >
        Gas
      </button>
      <button
        class="pedal brake"
        type="button"
        @pointerdown.prevent="pedalBrakeDown"
        @pointerup="pedalBrakeUp"
        @pointercancel="pedalBrakeUp"
        @pointerleave="pedalBrakeUp"
      >
        Brake
      </button>
    </div>

    <div v-if="showHandPrompt && !showAuthGate" class="modal-overlay">
      <div class="modal-card" @click.stop>
        <div class="modal-title">Which is your main hand?</div>
        <p>
          Your main hand steers by swiping — the gas and brake pedals go to
          the other side. You can change this later in Settings.
        </p>
        <div class="hand-choice">
          <button class="primary-btn" @click="setHandedness('left')" type="button">
            Left-handed
          </button>
          <button class="primary-btn alt" @click="setHandedness('right')" type="button">
            Right-handed
          </button>
        </div>
      </div>
    </div>

    <button
      v-if="state === 'running' && finalePhase === 'drive'"
      class="cam-btn"
      @click="toggleDriveCamera"
      type="button"
      aria-label="Toggle camera view"
    >
      {{ driveCamera === 'chase' ? 'Ego' : '3rd' }}
    </button>

    <div v-if="nearMissToast && state === 'running'" class="near-miss">
      Near Miss +{{ nearMissAmount }}
    </div>

    <div v-if="bumpToast && state === 'running'" class="bump-warning">
      &#9888; Careful!
    </div>

    <div v-if="state === 'running' || state === 'paused'" class="power-row">
      <div v-if="godModeActive" class="power-chip god">God Mode</div>
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
            <div class="difficulty-row">
              <span class="difficulty-label">Difficulty</span>
              <div class="difficulty-toggle">
                <button
                  v-for="level in levelOptions"
                  :key="level.id"
                  :class="{ active: selectedLevel === level.id }"
                  @click="setLevel(level.id)"
                  type="button"
                >
                  {{ level.label }}
                </button>
              </div>
            </div>
            <button class="menu-link" @click="openMenuScreen('level')" type="button">
              Skins
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
              class="menu-link"
              href="/profile"
            >
              Account
            </Link>
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
                  ? 'Skins'
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
              <div class="skin-preview-hint">
                Tap a skin to preview it live on the right.
              </div>
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
                <label>Pedal Side (Mobile)</label>
                <div class="difficulty-toggle">
                  <button
                    :class="{ active: handedness === 'right' }"
                    @click="setHandedness('right')"
                    type="button"
                  >
                    Right-handed
                  </button>
                  <button
                    :class="{ active: handedness === 'left' }"
                    @click="setHandedness('left')"
                    type="button"
                  >
                    Left-handed
                  </button>
                </div>
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
// Handedness decides which side the gas/brake pedals sit on: the dominant
// hand stays free for lane swipes, pedals go to the other thumb.
const handedness = ref('right');
const showHandPrompt = ref(false);
const isTouchDevice = ref(false);
const nearMissToast = ref(false);
const nearMissAmount = ref(25);
let nearMissTimer;

const shieldActive = ref(false);
const magnetTime = ref(0);
const multiTime = ref(0);
let invulnUntil = 0;
let shieldMesh;

const bumpToast = ref(false);
let bumpToastTimer;
let lastBumpAt = -Infinity;
let bumpProtectUntil = 0;
let bumpShakeTimer = 0;
let laneOrigin = 1;
const bumpWindowMs = 5000;

// Zone-1 event director: periodically breaks up the random rows with scripted
// moments (traffic-jam walls, wrong-way drivers, coin runs, lane drifters).
const eventToast = ref(null);
let eventToastTimer;
let eventTimer = 8;
let trafficWave = null;
let coinRushEndZ = null;
let nextMilestone = 2500;

const cameraBase = {
  y: 5.5,
  z: 8,
};

const levelOptions = [
  { id: 'rush', label: 'City Rush', baseSpeed: 12, stepDistance: 500, speedStep: 2 },
  { id: 'night', label: 'Night Run', baseSpeed: 14, stepDistance: 500, speedStep: 4 },
];
const selectedLevel = ref(levelOptions[0].id);

const loadHandednessPref = () => {
  isTouchDevice.value =
    window.matchMedia?.('(pointer: coarse)').matches || 'ontouchstart' in window;
  const stored = localStorage.getItem('runner_handedness');
  if (stored === 'left' || stored === 'right') {
    handedness.value = stored;
  } else if (isTouchDevice.value) {
    showHandPrompt.value = true;
  }
};

const setHandedness = (hand) => {
  handedness.value = hand;
  localStorage.setItem('runner_handedness', hand);
  showHandPrompt.value = false;
};

const loadLevelPref = () => {
  const stored = localStorage.getItem('runner_level');
  if (levelOptions.some((level) => level.id === stored)) {
    selectedLevel.value = stored;
  }
};

const setLevel = (levelId) => {
  if (!levelOptions.some((level) => level.id === levelId)) return;
  selectedLevel.value = levelId;
  localStorage.setItem('runner_level', levelId);
};

const skinOptions = ref([
  { id: 1, slug: 'neon', label: 'Neon', color: '#3bffb3', price: 0, is_default: true },
  { id: 2, slug: 'ember', label: 'Ember', color: '#ff6b3b', price: 300, is_default: false },
  { id: 3, slug: 'ion', label: 'Ion', color: '#49a8ff', price: 450, is_default: false },
  { id: 4, slug: 'dusk', label: 'Dusk', color: '#b18cff', price: 600, is_default: false },
  { id: 5, slug: 'volt', label: 'Volt', color: '#ffe14d', price: 750, is_default: false },
  { id: 6, slug: 'nova', label: 'Nova', color: '#ff4fd8', price: 900, is_default: false },
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

// Secret finale: at FINALE_SCORE the district ends in an open plaza, the
// runner boards a car and zone 2 begins — a four-lane road (two lanes each
// direction) where the only controls are steering, gas, and brake.
const FINALE_SCORE = 10000;
const carLanes = [-3, -1, 1, 3];
const carPlayerSize = { w: 1.5, h: 1.1, d: 2.6 };
const driveScoreMinSpeed = 15;
const driveMaxSpeed = 160;
// Hold near top speed for a moment and the car goes god mode: it smashes
// straight through traffic (cars go flying) and drags a fire trail.
const godTriggerSpeed = 132;
const godHoldSeconds = 2.5;
const godFloorSpeed = 120;
const godModeActive = ref(false);
let godHoldTimer = 0;
let flyingCars = [];

// Zone 3: at RAMP_SCORE while in god mode, the road ends in a jump ramp into
// the sunrise over water; mid-air the car slams into a plane's cargo hold and
// the player flies on — free movement, no lanes, bright daylight.
const RAMP_SCORE = 20000;
let rampTriggered = false;
let ramp = null;
let oceanGroup = null;
let launchVy = 0;
let launchTimer = 0;
let dockingPlane = null;
let planeVisual = null;
let planeTemplate = null;
let planeMixer = null;
let planeVelX = 0;
let planeVelY = 0;
const planeKeys = { up: false, down: false, left: false, right: false };
const joyKnob = ref({ x: 0, y: 0 });
let joyVec = { x: 0, y: 0 };
let joyPointerId = null;
let rampHintShown = false;
let clouds = [];
let cloudAssets = null;
let terrainChunks = [];
let terrainAssets = null;

// Zone 3 combat: single big drones that shoot back; kills restore some HP.
const playerHp = ref(100);
const damageFlash = ref(false);
let damageFlashTimer;
let enemies = [];
let enemyPool = [];
let enemySpawnTimer = 0;
let bossCount = 0;
let killsSinceMother = 0;
let mothershipCount = 0;
let motherTemplate = null;
let projectiles = [];
let projectilePool = [];
let enemyBolts = [];
let enemyBoltPool = [];
let fireTimer = 0;
let enemyAssets = null;

let envMode = 'night'; // night | sunrise | day
const envSettings = {
  night: {
    bg: 0x05070f, fog: 0x05070f, fogNear: 40, fogFar: 165,
    hemi: 1.6, dir: 1.7, hemiSky: 0x9fc1ff, dirColor: 0xffffff,
  },
  sunrise: {
    bg: 0x2e1f45, fog: 0xb06a4a, fogNear: 60, fogFar: 320,
    hemi: 1.9, dir: 2.3, hemiSky: 0xffc49a, dirColor: 0xffd9a8,
  },
  day: {
    bg: 0x8ecdf0, fog: 0xaee0f8, fogNear: 90, fogFar: 460,
    hemi: 2.6, dir: 3.0, hemiSky: 0xeaf6ff, dirColor: 0xfff4e0,
  },
};
const tmpEnvColor = new THREE.Color();
const finalePhase = ref('none'); // none | approach | walk | enter | drive
// Dev cheat (F9 during a run): jump straight to the finale trigger. The run
// is then never persisted, so it cannot flag the account or touch records.
const devRun = ref(false);
const driveCamera = ref('chase'); // chase | ego (hood cam, car hidden)
const finaleToast = ref(false);
const driveHint = ref(false);
const driveHintText = ref('');
let finaleTriggered = false;
let finaleTimer = 0;
let finaleToastTimer;
let driveHintTimer;
let plaza = null;
let plazaCar = null;
let carVisual = null;
let carWheels = [];
let driveTargetSpeed = 24;
let accelHeld = false;
let brakeHeld = false;
let driveSpawnTimer = 0;
let musicDuckTarget = 1;
let musicDuckCurrent = 1;

const activeLanes = () => (finalePhase.value === 'drive' ? carLanes : lanes);

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
let proceduralParts = [];
const characterTemplates = {};
let activeCharacter = null;
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
let engineOsc = null;
let engineGain = null;

let particles = [];
let particlePool = [];
let particleGeometry;
const particleMaterials = {};
let crashTimer = 0;

let buildingGeometry;
let buildingMaterials = [];
let glowTexture;
let skylineTexture;
let starPoints;
let hemiLight;
let dirLight;
let moonMesh;
let skylineMesh;

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

const currentPlayerHeight = () =>
  finalePhase.value === 'drive'
    ? carPlayerSize.h
    : isSliding
      ? playerSize.h * slideScale
      : playerSize.h;
const getGroundCenterForSurface = (surfaceY, playerHeight) => surfaceY + playerHeight / 2;
const currentGroundHeight = () => currentGroundCenter;

const startRun = () => {
  if (!scene || state.value === 'crashing') return;
  unlockAudio();
  menuScreen.value = 'main';
  resetRun();
  state.value = 'running';
  if (authUser.value) {
    // Fire and forget: the run token arrives while the player is already
    // running instead of blocking the start on a slow network round-trip.
    startRunSession();
  }
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

const toggleDriveCamera = () => {
  if (finalePhase.value !== 'drive') return;
  driveCamera.value = driveCamera.value === 'chase' ? 'ego' : 'chase';
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
  const base = (isMuted.value ? 0 : audioVolume.value) * musicDuckCurrent;
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
  // Always preview the clicked character in the scene, even if locked.
  applyCharacter(skin);

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

let runSessionSeq = 0;

const startRunSession = async () => {
  const seq = ++runSessionSeq;
  try {
    const response = await axios.post('/api/runner/run/start', {
      level: selectedLevel.value,
    });
    // Ignore responses that arrive after another run has started since.
    if (seq === runSessionSeq) {
      runToken.value = response.data.run_id || null;
    }
  } catch (error) {
    if (seq === runSessionSeq) {
      runToken.value = null;
    }
  }
};

const resetRun = () => {
  exitFinale();
  devRun.value = false;
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
    player.rotation.y = 0;
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
  lastBumpAt = -Infinity;
  bumpProtectUntil = 0;
  bumpShakeTimer = 0;
  laneOrigin = 1;
  bumpToast.value = false;
  trafficWave = null;
  coinRushEndZ = null;
  eventTimer = 8;
  nextMilestone = 2500;
  eventToast.value = null;
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
  if (devRun.value) {
    // Dev runs never touch records, coins, leaderboard, or anti-cheat.
    return;
  }
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
  if (['walk', 'enter', 'launch', 'plane'].includes(finalePhase.value)) return;
  const next = Math.max(0, currentLane - 1);
  if (next !== currentLane) {
    laneOrigin = currentLane;
    currentLane = next;
  }
};

const moveRight = () => {
  if (state.value !== 'running') return;
  if (['walk', 'enter', 'launch', 'plane'].includes(finalePhase.value)) return;
  const next = Math.min(activeLanes().length - 1, currentLane + 1);
  if (next !== currentLane) {
    laneOrigin = currentLane;
    currentLane = next;
  }
};

const attemptJump = () => {
  if (state.value !== 'running' || !player) return;
  if (finalePhase.value !== 'none' && finalePhase.value !== 'approach') return;
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
  if (finalePhase.value !== 'none' && finalePhase.value !== 'approach') return;
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

  if (event.code === 'F9') {
    if (!finaleTriggered && finalePhase.value === 'none') {
      devRun.value = true;
      score.value = FINALE_SCORE;
    } else if (finalePhase.value === 'drive' && !rampTriggered) {
      // Second F9 press in zone 2: jump straight to the ramp requirements.
      devRun.value = true;
      score.value = Math.max(score.value, RAMP_SCORE);
      driveTargetSpeed = driveMaxSpeed;
      speed.value = Math.max(speed.value, godTriggerSpeed);
      if (!godModeActive.value) {
        activateGodMode();
      }
    }
    return;
  }

  if (finalePhase.value === 'plane') {
    switch (event.code) {
      case 'ArrowUp':
      case 'KeyW':
      case 'Space':
        planeKeys.up = true;
        break;
      case 'ArrowDown':
      case 'KeyS':
        planeKeys.down = true;
        break;
      case 'ArrowLeft':
      case 'KeyA':
        planeKeys.left = true;
        break;
      case 'ArrowRight':
      case 'KeyD':
        planeKeys.right = true;
        break;
      default:
        break;
    }
    return;
  }

  if (finalePhase.value === 'launch') {
    return;
  }

  if (finalePhase.value === 'drive' || finalePhase.value === 'ramp') {
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
        if (!accelHeld) {
          sfx.rev();
        }
        accelHeld = true;
        break;
      case 'ArrowDown':
      case 'KeyS':
        if (!brakeHeld && speed.value > 12) {
          sfx.brakeScreech();
        }
        brakeHeld = true;
        break;
      case 'KeyC':
        toggleDriveCamera();
        break;
      default:
        break;
    }
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

const pedalGasDown = () => {
  if (!accelHeld) {
    sfx.rev();
  }
  accelHeld = true;
};

const pedalGasUp = () => {
  accelHeld = false;
};

const pedalBrakeDown = () => {
  if (!brakeHeld && speed.value > 12) {
    sfx.brakeScreech();
  }
  brakeHeld = true;
};

const pedalBrakeUp = () => {
  brakeHeld = false;
};

const handleKeyup = (event) => {
  switch (event.code) {
    case 'ArrowUp':
    case 'KeyW':
    case 'Space':
      accelHeld = false;
      planeKeys.up = false;
      break;
    case 'ArrowDown':
    case 'KeyS':
      brakeHeld = false;
      planeKeys.down = false;
      break;
    case 'ArrowLeft':
    case 'KeyA':
      planeKeys.left = false;
      break;
    case 'ArrowRight':
    case 'KeyD':
      planeKeys.right = false;
      break;
    default:
      break;
  }
};

const handleTouchStart = (event) => {
  if (state.value !== 'running') return;
  if (event.target?.closest?.('.joystick, .pedals')) return;
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

  if (finalePhase.value === 'plane' || finalePhase.value === 'launch') {
    // The plane uses the on-screen joystick instead of swipes.
    return true;
  }

  if (finalePhase.value === 'drive' || finalePhase.value === 'ramp') {
    // Vertical swipes do nothing in the car — speed is on the pedals.
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
  if (target?.closest?.('.joystick, .pedals')) return;

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
  const w = 3.2 + Math.random() * 2.6;
  const h = 5 + Math.random() * 11;
  const d = 4 + Math.random() * 3;
  const base = new THREE.Mesh(
    buildingGeometry,
    buildingMaterials[Math.floor(Math.random() * buildingMaterials.length)],
  );
  base.scale.set(w, h, d);
  group.add(base);

  const parapet = new THREE.Mesh(buildingGeometry, houseAssets.trimMat);
  parapet.scale.set(w + 0.18, 0.3, d + 0.18);
  parapet.position.y = h - 0.05;
  group.add(parapet);

  if (Math.random() < 0.55) {
    const ac = new THREE.Mesh(houseAssets.acGeo, houseAssets.grayMat);
    ac.position.set((Math.random() - 0.5) * w * 0.5, h + 0.45, (Math.random() - 0.5) * d * 0.4);
    group.add(ac);
  }
  if (Math.random() < 0.4) {
    const antenna = new THREE.Mesh(houseAssets.antennaGeo, houseAssets.grayMat);
    antenna.position.set(
      (Math.random() - 0.5) * w * 0.6,
      h + 1.2,
      (Math.random() - 0.5) * d * 0.4,
    );
    group.add(antenna);
  }
  if (Math.random() < 0.3) {
    const tank = new THREE.Mesh(houseAssets.tankGeo, houseAssets.trimMat);
    tank.position.set((Math.random() - 0.5) * w * 0.4, h + 0.45, (Math.random() - 0.5) * d * 0.3);
    group.add(tank);
  }

  const faceX = -side * (w / 2 + 0.03);
  const faceRotation = side < 0 ? Math.PI / 2 : -Math.PI / 2;
  const rows = Math.max(1, Math.min(6, Math.floor((h - 2.4) / 1.9)));
  for (let row = 0; row < rows; row += 1) {
    for (let col = -1; col <= 1; col += 1) {
      if (Math.random() < 0.12) continue;
      const litRoll = Math.random();
      const material =
        litRoll < 0.3
          ? houseAssets.windowLit
          : litRoll < 0.42
            ? houseAssets.windowCool
            : houseAssets.windowDark;
      const win = new THREE.Mesh(houseAssets.windowGeometry, material);
      win.position.set(faceX, 2.5 + row * 1.9, col * d * 0.26);
      win.rotation.y = faceRotation;
      group.add(win);
    }
  }

  if (Math.random() < 0.35) {
    const sign = new THREE.Mesh(
      houseAssets.signGeo,
      houseAssets.neonMats[Math.floor(Math.random() * houseAssets.neonMats.length)],
    );
    sign.scale.y = Math.min(1, (h * 0.45) / 2.4);
    sign.position.set(-side * (w / 2 + 0.09), h * 0.55, (Math.random() - 0.5) * d * 0.5);
    sign.rotation.y = faceRotation;
    group.add(sign);
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
  proceduralParts = [torso, head, legL.hip, legR.hip, armL.shoulder, armR.shoulder];
};

// Animated low-poly runners from Kenney's CC0 Blocky Characters pack. Each
// GLB ships node-based clips (idle/sprint/...) — no skinning, so plain
// AnimationMixer on the scene works. The procedural rig stays as fallback.
const characterSkinMap = {
  neon: 'character-a',
  ember: 'character-b',
  ion: 'character-c',
  dusk: 'character-d',
  volt: 'character-e',
  nova: 'character-f',
};
const characterKeys = [
  'character-a',
  'character-b',
  'character-c',
  'character-d',
  'character-e',
  'character-f',
];

const characterKeyForSkin = (skin) => {
  if (skin?.slug && characterSkinMap[skin.slug]) {
    return characterSkinMap[skin.slug];
  }
  const index = Math.abs(Number(skin?.id) || 0) % characterKeys.length;
  return characterKeys[index];
};

const removeActiveCharacter = () => {
  if (!activeCharacter) return;
  activeCharacter.mixer.stopAllAction();
  player.remove(activeCharacter.root);
  activeCharacter = null;
  proceduralParts.forEach((part) => {
    part.visible = true;
  });
};

const applyCharacter = (skin = null) => {
  if (!player) return;
  const key = characterKeyForSkin(skin || currentSkin.value);
  const template = characterTemplates[key];
  if (!template || activeCharacter?.key === key) return;
  removeActiveCharacter();

  const root = template.scene;
  const scale = playerSize.h / template.rawHeight;
  root.scale.setScalar(scale);
  root.rotation.set(0, Math.PI, 0);
  root.position.set(0, -playerSize.h / 2 - template.rawMinY * scale, 0);

  const mixer = new THREE.AnimationMixer(root);
  const findClip = (...names) => {
    for (const name of names) {
      const clip = THREE.AnimationClip.findByName(template.clips, name);
      if (clip) return clip;
    }
    return null;
  };
  const runClip = findClip('sprint', 'walk');
  const idleClip = findClip('idle', 'static');
  const actions = {
    run: runClip ? mixer.clipAction(runClip) : null,
    idle: idleClip ? mixer.clipAction(idleClip) : null,
  };

  player.add(root);
  proceduralParts.forEach((part) => {
    part.visible = false;
  });
  activeCharacter = { key, root, mixer, actions, current: null };
};

const setCharacterAction = (name) => {
  const character = activeCharacter;
  if (!character || character.current === name) return;
  const next = character.actions[name];
  if (!next) return;
  if (character.current && character.actions[character.current]) {
    character.actions[character.current].fadeOut(0.18);
  }
  next.reset().fadeIn(0.18).play();
  character.current = name;
};

const driveCharacter = (delta, running) => {
  const character = activeCharacter;
  const d = (current, target, speedFactor = 10) =>
    THREE.MathUtils.damp(current, target, speedFactor, delta);

  if (!running) {
    setCharacterAction('idle');
    character.mixer.timeScale = 1;
    character.root.rotation.x = d(character.root.rotation.x, 0);
    player.rotation.z = d(player.rotation.z, 0, 6);
    character.mixer.update(delta);
    return;
  }

  player.rotation.z = d(player.rotation.z, (player.position.x - lanes[currentLane]) * 0.18);
  const grounded = player.position.y <= currentGroundCenter + groundedEpsilon;
  setCharacterAction('run');
  if (character.actions.run) {
    // Freeze the stride mid-air and in the slide; lean sells the pose.
    character.actions.run.paused = isSliding || !grounded;
  }
  character.mixer.timeScale = 0.7 + speed.value / 16;
  let lean = 0.12;
  if (isSliding) {
    lean = 0.45;
  } else if (!grounded) {
    lean = -0.18;
  }
  character.root.rotation.x = d(character.root.rotation.x, lean, 8);
  character.mixer.update(delta);
};

const loadCharacterModels = () => {
  const loader = new GLTFLoader();
  characterKeys.forEach((key) => {
    loader.load(
      `/models/characters/${key}.glb`,
      (gltf) => {
        const box = new THREE.Box3().setFromObject(gltf.scene);
        characterTemplates[key] = {
          scene: gltf.scene,
          clips: gltf.animations,
          rawHeight: box.max.y - box.min.y,
          rawMinY: box.min.y,
        };
        applyCharacter();
      },
      undefined,
      () => {},
    );
  });
};

const animateRunner = (delta) => {
  if (activeCharacter) {
    driveCharacter(delta, true);
    return;
  }
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
  if (!player?.visible) return;
  if (activeCharacter) {
    driveCharacter(delta, false);
    return;
  }
  if (!runnerParts) return;
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
    particle.userData.buoyant = false;
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
    if (particle.userData.buoyant) {
      particle.userData.velocity.y += 9 * delta;
    } else {
      particle.userData.velocity.y += gravity * 0.6 * delta;
    }
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
  scene.fog = new THREE.Fog(0x05070f, 40, 165);

  camera = new THREE.PerspectiveCamera(60, 1, 0.1, 700);
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

  hemiLight = new THREE.HemisphereLight(0x9fc1ff, 0x141824, 1.6);
  scene.add(hemiLight);

  dirLight = new THREE.DirectionalLight(0xffffff, 1.7);
  dirLight.position.set(6, 10, 4);
  scene.add(dirLight);

  const floorMaterial = new THREE.MeshLambertMaterial({
    color: 0x11151f,
  });
  const laneDashGeometry = new THREE.BoxGeometry(0.09, 0.02, 1.3);
  const laneDashMaterial = new THREE.MeshBasicMaterial({ color: 0xcfd8ea });
  const edgeLineGeometry = new THREE.BoxGeometry(0.07, 0.02, segmentLength);
  const edgeLineMaterial = new THREE.MeshBasicMaterial({ color: 0x2ee5ff });
  const centerLineGeometry = new THREE.BoxGeometry(0.06, 0.02, segmentLength);
  const centerLineMaterial = new THREE.MeshBasicMaterial({ color: 0xffc94d });
  const curbGeometry = new THREE.BoxGeometry(0.34, 0.26, segmentLength);
  const curbMaterial = new THREE.MeshLambertMaterial({ color: 0x39465e });
  const sidewalkGeometry = new THREE.BoxGeometry(2.6, 0.26, segmentLength);
  const sidewalkMaterial = new THREE.MeshLambertMaterial({ color: 0x1a2233 });

  buildingGeometry = new THREE.BoxGeometry(1, 1, 1);
  buildingGeometry.translate(0, 0.5, 0);
  buildingMaterials = [
    new THREE.MeshLambertMaterial({ color: 0x1a2a46 }),
    new THREE.MeshLambertMaterial({ color: 0x14203a }),
    new THREE.MeshLambertMaterial({ color: 0x223354 }),
    new THREE.MeshLambertMaterial({ color: 0x2c2340 }),
    new THREE.MeshLambertMaterial({ color: 0x1c3040 }),
    new THREE.MeshLambertMaterial({ color: 0x33273a }),
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
    windowGeometry: new THREE.PlaneGeometry(0.62, 0.85),
    doorGeometry: new THREE.PlaneGeometry(0.95, 1.7),
    windowLit: new THREE.MeshBasicMaterial({ color: 0xffd98c }),
    windowCool: new THREE.MeshBasicMaterial({ color: 0x9fd8ff }),
    windowDark: new THREE.MeshBasicMaterial({ color: 0x0a1322 }),
    doorMat: new THREE.MeshBasicMaterial({ color: 0x0c1626 }),
    trimMat: new THREE.MeshLambertMaterial({ color: 0x33415e }),
    grayMat: new THREE.MeshLambertMaterial({ color: 0x222c42 }),
    acGeo: new THREE.BoxGeometry(0.7, 0.45, 0.6),
    antennaGeo: new THREE.BoxGeometry(0.07, 2.4, 0.07),
    tankGeo: new THREE.CylinderGeometry(0.5, 0.5, 0.9, 10),
    signGeo: new THREE.PlaneGeometry(0.42, 2.4),
    neonMats: [0xff4fd8, 0x2ee5ff, 0xffa22e, 0x67f05a, 0xff5a6e].map(
      (color) => new THREE.MeshBasicMaterial({ color, side: THREE.DoubleSide }),
    ),
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

  for (let i = 0; i < 9; i += 1) {
    const segment = new THREE.Group();
    const floor = new THREE.Mesh(new THREE.PlaneGeometry(8, segmentLength), floorMaterial);
    floor.rotation.x = -Math.PI / 2;
    segment.add(floor);

    const zone1Marks = new THREE.Group();
    const zone2Marks = new THREE.Group();
    for (let side = -1; side <= 1; side += 2) {
      for (let dash = 0; dash < 5; dash += 1) {
        const laneDash = new THREE.Mesh(laneDashGeometry, laneDashMaterial);
        laneDash.position.set(side, 0.01, -segmentLength / 2 + 2 + dash * 4);
        zone1Marks.add(laneDash);

        const zone2Dash = new THREE.Mesh(laneDashGeometry, laneDashMaterial);
        zone2Dash.position.set(side * 2, 0.01, -segmentLength / 2 + 2 + dash * 4);
        zone2Marks.add(zone2Dash);
      }

      const centerLine = new THREE.Mesh(centerLineGeometry, centerLineMaterial);
      centerLine.position.set(side * 0.09, 0.011, 0);
      zone2Marks.add(centerLine);

      const edgeLine = new THREE.Mesh(edgeLineGeometry, edgeLineMaterial);
      edgeLine.position.set(side * 3.15, 0.012, 0);
      segment.add(edgeLine);

      const curb = new THREE.Mesh(curbGeometry, curbMaterial);
      curb.position.set(side * 3.45, 0.13, 0);
      segment.add(curb);

      const sidewalk = new THREE.Mesh(sidewalkGeometry, sidewalkMaterial);
      sidewalk.position.set(side * 4.92, 0.13, 0);
      segment.add(sidewalk);
    }
    zone2Marks.visible = false;
    segment.add(zone1Marks, zone2Marks);
    segment.userData.zone1Marks = zone1Marks;
    segment.userData.zone2Marks = zone2Marks;

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

      lamp.position.set(side * 4.6, 0.26, -segmentLength / 2);
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
  particleMaterials.flame = new THREE.MeshBasicMaterial({ color: 0xff7a2e });
  particleMaterials.heal = new THREE.MeshBasicMaterial({ color: 0x67f05a });
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

  moonMesh = new THREE.Mesh(
    new THREE.CircleGeometry(5, 24),
    new THREE.MeshBasicMaterial({ color: 0xf5ecd4, fog: false }),
  );
  moonMesh.position.set(-45, 55, -140);
  scene.add(moonMesh);

  // Distant city silhouette painted onto a canvas, parked behind the fog.
  const skylineCanvas = document.createElement('canvas');
  skylineCanvas.width = 1024;
  skylineCanvas.height = 256;
  const skyCtx = skylineCanvas.getContext('2d');
  let cursorX = 0;
  while (cursorX < skylineCanvas.width) {
    const towerW = 28 + Math.random() * 62;
    const towerH = 60 + Math.random() * 175;
    skyCtx.fillStyle = '#0a1124';
    skyCtx.fillRect(cursorX, skylineCanvas.height - towerH, towerW, towerH);
    skyCtx.fillStyle = 'rgba(255, 214, 140, 0.5)';
    for (let wy = skylineCanvas.height - towerH + 8; wy < skylineCanvas.height - 8; wy += 9) {
      for (let wx = cursorX + 5; wx < cursorX + towerW - 5; wx += 8) {
        if (Math.random() < 0.16) {
          skyCtx.fillRect(wx, wy, 3, 4);
        }
      }
    }
    cursorX += towerW + 4 + Math.random() * 12;
  }
  skylineTexture = new THREE.CanvasTexture(skylineCanvas);
  skylineMesh = new THREE.Mesh(
    new THREE.PlaneGeometry(340, 82),
    new THREE.MeshBasicMaterial({
      map: skylineTexture,
      transparent: true,
      opacity: 0.9,
      fog: false,
      depthWrite: false,
    }),
  );
  skylineMesh.position.set(0, 34, -155);
  scene.add(skylineMesh);

  buildRunner();
  player.position.set(lanes[currentLane], currentGroundCenter, 2);
  scene.add(player);

  camera.lookAt(0, 1.2, -12);

  window.addEventListener('resize', handleResize);
  window.addEventListener('keydown', handleKeydown);
  window.addEventListener('keyup', handleKeyup);
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
    hazard: track(new THREE.MeshBasicMaterial({ color: 0xffa22e })),
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
  // Roadwork barrier: striped beam on two legs, open underneath — slide
  // under it or jump over it. Collision box covers only the beam.
  'over-barrier': () => {
    const a = getObstacleAssets();
    const g = new THREE.Group();
    const beam = new THREE.Mesh(track(new THREE.BoxGeometry(1.7, 0.26, 0.14)), a.barrel);
    g.add(beam);
    const stripeGeo = track(new THREE.BoxGeometry(0.3, 0.28, 0.16));
    [-0.55, 0.05, 0.62].forEach((x) => {
      const stripe = new THREE.Mesh(stripeGeo, a.busRoof);
      stripe.position.x = x;
      g.add(stripe);
    });
    const legGeo = track(new THREE.BoxGeometry(0.1, 1.72, 0.1));
    const footGeo = track(new THREE.BoxGeometry(0.36, 0.08, 0.36));
    [-0.82, 0.82].forEach((x) => {
      const leg = new THREE.Mesh(legGeo, a.frame);
      leg.position.set(x, -0.73, 0);
      g.add(leg);
      const foot = new THREE.Mesh(footGeo, a.frame);
      foot.position.set(x, -1.55, 0);
      g.add(foot);
    });
    const lampGeo = track(new THREE.BoxGeometry(0.12, 0.1, 0.12));
    [-0.82, 0, 0.82].forEach((x) => {
      const hazardLamp = new THREE.Mesh(lampGeo, a.hazard);
      hazardLamp.position.set(x, 0.2, 0);
      g.add(hazardLamp);
    });
    return { mesh: g, size: { w: 1.6, h: 0.7, d: 1.2 } };
  },
};

const obstacleVariants = {
  low: ['low-crate', 'low-barrel', 'car-any'],
  tall: ['tall-stack', 'tall-any'],
  over: ['over-barrier'],
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
  delete obstacle.userData.driftTo;
  obstacle.userData.driftHonked = false;
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
      -(112 + Math.min(20, speed.value * 0.3)) - i * 10 - Math.random() * 5,
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
  // Spawn farther out the faster we go, so there is always time to react.
  const baseZ = -(70 + Math.min(45, speed.value));

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

const showEventToast = (title, sub, duration = 1900) => {
  eventToast.value = { title, sub };
  if (eventToastTimer) {
    clearTimeout(eventToastTimer);
  }
  eventToastTimer = setTimeout(() => {
    eventToast.value = null;
  }, duration);
};

// Traffic jam: a few walls of parked cars with exactly one gap that wanders
// between rows. Coins mark the safe lane so the path reads from a distance.
const spawnWaveRow = () => {
  const baseZ = -(95 + Math.min(45, speed.value));
  for (let laneIndex = 0; laneIndex < 3; laneIndex += 1) {
    if (laneIndex === trafficWave.gapLane) {
      for (let k = 0; k < 2; k += 1) {
        const coin = getCoin();
        coin.position.set(lanes[laneIndex], 1.0, baseZ - k * 1.6);
        coin.rotation.y = Math.random() * Math.PI;
        coins.push(coin);
        scene.add(coin);
      }
      continue;
    }
    const vehicle = getObstacle('low', 'car-any');
    vehicle.rotation.y = Math.PI;
    vehicle.position.set(lanes[laneIndex], vehicle.userData.size.h / 2 + 0.02, baseZ);
    obstacles.push(vehicle);
    scene.add(vehicle);
  }
  trafficWave.rowsLeft -= 1;
  if (trafficWave.rowsLeft <= 0) {
    trafficWave.endZ = baseZ;
  } else {
    trafficWave.rowTimer = 22 / speed.value;
    if (Math.random() < 0.7) {
      trafficWave.gapLane = THREE.MathUtils.clamp(
        trafficWave.gapLane + (Math.random() < 0.5 ? -1 : 1),
        0,
        2,
      );
    }
  }
};

const startTrafficWave = () => {
  trafficWave = {
    rowsLeft: 3 + Math.min(2, Math.floor(score.value / 4000)),
    gapLane: Math.floor(Math.random() * 3),
    rowTimer: 0,
    endZ: null,
  };
  sfx.horn();
  showEventToast('Rush Hour', 'Follow the coins through the jam!');
};

const startWrongWayDriver = () => {
  const levelFactor = currentLevel.value.baseSpeed / 12;
  const vehicle = getObstacle('low', 'car-any');
  vehicle.userData.vz = (16 + Math.random() * 8) * levelFactor;
  if (vehicle.userData.beams) {
    vehicle.userData.beams.visible = true;
  }
  vehicle.rotation.y = 0;
  vehicle.position.set(
    lanes[currentLane],
    vehicle.userData.size.h / 2 + 0.02,
    -150,
  );
  obstacles.push(vehicle);
  scene.add(vehicle);
  sfx.horn();
  showEventToast('Wrong-Way Driver', 'Get out of your lane!');
};

// A snaking trail of coins weaving across all three lanes. The trail spawns
// deeper than any in-flight row and normal spawns pause until it has passed,
// so following it is always safe. Two jump arcs curve over their own crate.
const coinRushStep = 2.3;
const startCoinRush = () => {
  const baseZ = -(120 + Math.min(45, speed.value));
  const count = 20;
  const arcCenters = [8, 15];
  const arcLift = [0, 1.2, 1.9, 1.2, 0];
  const snapToLane = (x) =>
    lanes.reduce((best, lane) => (Math.abs(lane - x) < Math.abs(best - x) ? lane : best), lanes[0]);
  const arcX = arcCenters.map((c) => snapToLane(Math.sin(c * 0.45) * 2));

  for (let k = 0; k < count; k += 1) {
    const arcIndex = arcCenters.findIndex((c) => Math.abs(k - c) <= 2);
    const x = arcIndex >= 0 ? arcX[arcIndex] : Math.sin(k * 0.45) * 2;
    const lift = arcIndex >= 0 ? arcLift[k - arcCenters[arcIndex] + 2] : 0;
    const coin = getCoin();
    coin.position.set(x, 1.0 + lift, baseZ - k * coinRushStep);
    coin.rotation.y = Math.random() * Math.PI;
    coins.push(coin);
    scene.add(coin);
  }
  arcCenters.forEach((c, i) => {
    const obstacle = getObstacle('low', Math.random() < 0.5 ? 'low-crate' : 'low-barrel');
    obstacle.rotation.y = 0;
    obstacle.position.set(arcX[i], obstacle.userData.size.h / 2 + 0.02, baseZ - c * coinRushStep);
    obstacles.push(obstacle);
    scene.add(obstacle);
  });
  coinRushEndZ = baseZ - (count - 1) * coinRushStep;
  showEventToast('Coin Rush', 'Follow the trail — jump the arcs!');
};

// Oncoming car that swerves into a neighbouring lane mid-approach.
const startDriftCar = () => {
  const levelFactor = currentLevel.value.baseSpeed / 12;
  const fromLane = Math.floor(Math.random() * 3);
  const shift = fromLane === 0 ? 1 : fromLane === 2 ? -1 : Math.random() < 0.5 ? -1 : 1;
  const vehicle = getObstacle('low', 'car-any');
  vehicle.userData.vz = (8 + Math.random() * 6) * levelFactor;
  vehicle.userData.driftTo = lanes[fromLane + shift];
  vehicle.userData.driftAt = -(42 + Math.random() * 24);
  vehicle.userData.driftHonked = false;
  if (vehicle.userData.beams) {
    vehicle.userData.beams.visible = true;
  }
  vehicle.rotation.y = 0;
  vehicle.position.set(
    lanes[fromLane],
    vehicle.userData.size.h / 2 + 0.02,
    -125,
  );
  obstacles.push(vehicle);
  scene.add(vehicle);
};

const startRandomEvent = () => {
  const roll = Math.random();
  if (roll < 0.34) {
    startTrafficWave();
  } else if (roll < 0.6) {
    startWrongWayDriver();
  } else if (roll < 0.82) {
    startCoinRush();
  } else {
    startDriftCar();
  }
};

const updateZoneEvents = (delta) => {
  if (nextMilestone < FINALE_SCORE && score.value >= nextMilestone) {
    showEventToast(`${nextMilestone.toLocaleString('en-US')} points`, 'Keep it rolling!', 1500);
    sfx.powerup();
    spawnBurst(player.position.clone(), ['gold'], 10, 5);
    nextMilestone += 2500;
  }
  if (trafficWave) {
    if (trafficWave.rowsLeft > 0) {
      trafficWave.rowTimer -= delta;
      if (trafficWave.rowTimer <= 0) {
        spawnWaveRow();
      }
    }
    if (trafficWave.endZ !== null) {
      trafficWave.endZ += speed.value * delta;
      if (trafficWave.endZ > player.position.z + 2) {
        const bonus = 150 * (multiTime.value > 0 ? 2 : 1);
        score.value += bonus;
        sfx.nearMiss();
        showEventToast('Traffic Cleared', `+${bonus} bonus`);
        trafficWave = null;
      }
    }
    return;
  }
  if (coinRushEndZ !== null) {
    coinRushEndZ += speed.value * delta;
    if (coinRushEndZ > player.position.z + 2) {
      coinRushEndZ = null;
    } else {
      return;
    }
  }
  if (finaleTriggered) return;
  eventTimer -= delta;
  if (eventTimer <= 0 && score.value > 350 && score.value < FINALE_SCORE - 800) {
    startRandomEvent();
    eventTimer = THREE.MathUtils.randFloat(11, 18);
  }
};

const checkCollision = (obstacle, playerHeight) => {
  const oSize = obstacle.userData.size;
  const box = finalePhase.value === 'drive' ? carPlayerSize : playerSize;
  const dx = Math.abs(player.position.x - obstacle.position.x);
  const dy = Math.abs(player.position.y - obstacle.position.y);
  const dz = Math.abs(player.position.z - obstacle.position.z);

  if (
    dx >= (box.w + oSize.w) / 2 ||
    dy >= (playerHeight + oSize.h) / 2 ||
    dz >= (box.d + oSize.d) / 2
  ) {
    return false;
  }

  const obstacleTop = obstacle.position.y + oSize.h / 2;
  const playerBottom = player.position.y - playerHeight / 2;

  return playerBottom < obstacleTop - 0.05;
};

const triggerBumpToast = () => {
  bumpToast.value = false;
  if (bumpToastTimer) {
    clearTimeout(bumpToastTimer);
  }
  requestAnimationFrame(() => {
    bumpToast.value = true;
    bumpToastTimer = setTimeout(() => {
      bumpToast.value = false;
    }, 1300);
  });
};

// Swiping into a lane that is already blocked bounces the player back instead
// of killing them — but a second bump within the window is fatal.
const handleSideBump = (obstacle) => {
  const now = performance.now();
  currentLane = laneOrigin;
  bumpProtectUntil = now + 800;
  bumpShakeTimer = 0.3;
  spawnBurst(
    new THREE.Vector3(
      (player.position.x + obstacle.position.x) / 2,
      player.position.y,
      player.position.z,
    ),
    ['dust'],
    8,
    4,
  );
  if (now - lastBumpAt < bumpWindowMs) {
    if (shieldActive.value) {
      shieldActive.value = false;
      lastBumpAt = now;
      sfx.shieldBreak();
      triggerBumpToast();
      return;
    }
    startCrash();
    return;
  }
  lastBumpAt = now;
  sfx.bump();
  triggerBumpToast();
};

const setRoadZone = (zone) => {
  floorSegments.forEach((segment) => {
    if (segment.userData.zone1Marks) {
      segment.userData.zone1Marks.visible = zone === 1;
    }
    if (segment.userData.zone2Marks) {
      segment.userData.zone2Marks.visible = zone === 2;
    }
  });
};

const buildPlaza = () => {
  plaza = new THREE.Group();

  const ground = new THREE.Mesh(
    new THREE.BoxGeometry(46, 0.12, 70),
    new THREE.MeshLambertMaterial({ color: 0x1a2438 }),
  );
  ground.position.y = 0.06;
  plaza.add(ground);

  const ring = new THREE.Mesh(
    new THREE.RingGeometry(5.6, 6, 40),
    new THREE.MeshBasicMaterial({ color: 0x2ee5ff, side: THREE.DoubleSide }),
  );
  ring.rotation.x = -Math.PI / 2;
  ring.position.set(0, 0.135, -4);
  plaza.add(ring);

  const trunkGeo = new THREE.CylinderGeometry(0.16, 0.22, 1.2, 7);
  const trunkMat = new THREE.MeshLambertMaterial({ color: 0x4a3526 });
  const crownGeo = new THREE.IcosahedronGeometry(1.05, 0);
  const crownMat = new THREE.MeshLambertMaterial({ color: 0x1d4d3b });
  for (let i = 0; i < 10; i += 1) {
    const angle = (i / 10) * Math.PI * 2;
    const radius = 13 + Math.random() * 6;
    const tree = new THREE.Group();
    const trunk = new THREE.Mesh(trunkGeo, trunkMat);
    trunk.position.y = 0.72;
    tree.add(trunk);
    const crown = new THREE.Mesh(crownGeo, crownMat);
    crown.position.y = 1.9;
    crown.rotation.y = Math.random() * Math.PI;
    tree.add(crown);
    tree.position.set(Math.cos(angle) * radius, 0, -4 + Math.sin(angle) * radius * 0.8);
    plaza.add(tree);
  }

  const postGeo = new THREE.BoxGeometry(0.1, 2.6, 0.1);
  const postMat = new THREE.MeshLambertMaterial({ color: 0x24304a });
  const orbGeo = new THREE.SphereGeometry(0.18, 10, 8);
  const orbMat = new THREE.MeshBasicMaterial({ color: 0xffdfa6 });
  for (let i = 0; i < 6; i += 1) {
    const angle = (i / 6) * Math.PI * 2 + 0.3;
    const post = new THREE.Group();
    const pole = new THREE.Mesh(postGeo, postMat);
    pole.position.y = 1.3;
    post.add(pole);
    const orb = new THREE.Mesh(orbGeo, orbMat);
    orb.position.y = 2.7;
    post.add(orb);
    post.position.set(Math.cos(angle) * 8, 0.1, -4 + Math.sin(angle) * 8);
    plaza.add(post);
  }

  // The getaway car, waiting with its lights on.
  const template = glbTemplates['sedan-sports'] || glbTemplates.sedan || glbTemplates.race;
  if (template) {
    plazaCar = template.model.clone(true);
    plazaCar.rotation.y = Math.PI;
    plazaCar.position.set(carLanes[2], template.size.h / 2 + 0.12, -4);
  } else {
    const { mesh } = obstacleBuilders['low-car']();
    plazaCar = mesh;
    plazaCar.rotation.y = Math.PI;
    plazaCar.position.set(carLanes[2], 0.57 + 0.12, -4);
  }
  plaza.add(plazaCar);

  plaza.position.z = -170;
  scene.add(plaza);
};

const disposePlaza = () => {
  if (!plaza) return;
  if (plazaCar && plazaCar.parent === plaza) {
    // The car clone shares geometry/materials with the GLB templates —
    // detach it so the traversal below never disposes shared resources.
    plaza.remove(plazaCar);
  }
  scene.remove(plaza);
  plaza.traverse((node) => {
    if (node.isMesh) {
      node.geometry?.dispose();
      const materials = Array.isArray(node.material) ? node.material : [node.material];
      materials.forEach((material) => material?.dispose());
    }
  });
  plaza = null;
  plazaCar = null;
};

const triggerFinale = () => {
  finaleTriggered = true;
  finalePhase.value = 'approach';
  buildPlaza();
  musicDuckTarget = 0.3;
  finaleToast.value = true;
  if (finaleToastTimer) {
    clearTimeout(finaleToastTimer);
  }
  finaleToastTimer = setTimeout(() => {
    finaleToast.value = false;
  }, 4200);
};

const startDriving = () => {
  finalePhase.value = 'drive';
  carVisual = plazaCar;
  carWheels = [];
  if (carVisual) {
    plaza.remove(carVisual);
    carVisual.position.set(0, 0, 0);
    carVisual.rotation.set(0, Math.PI, 0);
    player.add(carVisual);
    carVisual.traverse((node) => {
      if (node.name && node.name.startsWith('wheel')) {
        carWheels.push(node);
      }
    });
  }
  player.visible = true;
  if (activeCharacter) {
    activeCharacter.root.visible = false;
  }
  proceduralParts.forEach((part) => {
    part.visible = false;
  });
  player.position.y = carPlayerSize.h / 2 + 0.02;
  currentGroundCenter = player.position.y;
  playerVelocityY = 0;
  isSliding = false;
  player.scale.y = 1;
  currentLane = 2;
  laneOrigin = 2;
  driveTargetSpeed = 24;
  speed.value = 2;
  driveSpawnTimer = 1.6;
  driveCamera.value = 'chase';
  clearObstacles();
  clearCoins();
  clearPowerups();
  setRoadZone(2);
  musicDuckTarget = 0.6;
  sfx.engineStart();
  driveHintText.value = isTouchDevice.value
    ? 'Zone 2 — hold the pedals, swipe to steer. No points below speed 15.'
    : 'Zone 2 — W/↑ gas, S/↓ brake, C camera. No points below speed 15.';
  driveHint.value = true;
  if (driveHintTimer) {
    clearTimeout(driveHintTimer);
  }
  driveHintTimer = setTimeout(() => {
    driveHint.value = false;
  }, 5200);
};

const updateFinaleWalk = (delta) => {
  speed.value = 0;

  if (finalePhase.value === 'enter') {
    finaleTimer -= delta;
    if (finaleTimer <= 0) {
      startDriving();
    }
    return;
  }

  const carWorldZ = plaza.position.z + plazaCar.position.z;
  const targetZ = carWorldZ + 2.1;
  player.position.x = THREE.MathUtils.damp(player.position.x, carLanes[2], 4, delta);
  player.position.z = Math.max(targetZ, player.position.z - 3.4 * delta);

  if (activeCharacter) {
    setCharacterAction('run');
    if (activeCharacter.actions.run) {
      activeCharacter.actions.run.paused = false;
    }
    activeCharacter.mixer.timeScale = 0.9;
    activeCharacter.mixer.update(delta);
  }

  camera.position.x = THREE.MathUtils.damp(camera.position.x, player.position.x * 0.3, 4, delta);
  lookAtTarget.set(player.position.x * 0.5, 1.0, player.position.z - 8);
  camera.lookAt(lookAtTarget);

  if (player.position.z <= targetZ + 0.05) {
    finalePhase.value = 'enter';
    finaleTimer = 0.7;
    player.visible = false;
    sfx.door();
  }
};

const spawnDriveTraffic = () => {
  const oncoming = Math.random() < 0.45;
  const lanePair = oncoming ? [0, 1] : [2, 3];
  const laneIndex = lanePair[Math.floor(Math.random() * lanePair.length)];
  const isTruck = Math.random() < 0.12;
  const vehicle = getObstacle(isTruck ? 'tall' : 'low', isTruck ? 'tall-any' : 'car-any');
  const ownSpeed = oncoming ? 10 + Math.random() * 12 : 8 + Math.random() * 10;
  vehicle.userData.vz = oncoming ? ownSpeed : -ownSpeed;
  vehicle.rotation.y = oncoming ? 0 : Math.PI;
  if (vehicle.userData.beams) {
    vehicle.userData.beams.visible = oncoming;
  }
  vehicle.position.set(
    carLanes[laneIndex],
    vehicle.userData.size.h / 2 + 0.02,
    -(140 + Math.min(150, speed.value * 0.9)) - Math.random() * 20,
  );
  obstacles.push(vehicle);
  scene.add(vehicle);
};

const emitFireTrail = () => {
  const keys = ['flame', 'gold', 'red'];
  for (let i = 0; i < 3; i += 1) {
    let particle = particlePool.pop();
    const key = keys[i % keys.length];
    if (!particle) {
      particle = new THREE.Mesh(particleGeometry, particleMaterials[key]);
    } else {
      particle.material = particleMaterials[key];
    }
    particle.position.set(
      player.position.x + (Math.random() - 0.5) * 0.9,
      0.3 + Math.random() * 0.35,
      player.position.z + 1.3 + Math.random() * 0.5,
    );
    particle.scale.setScalar(0.5 + Math.random() * 0.7);
    particle.userData.velocity = new THREE.Vector3(
      (Math.random() - 0.5) * 2,
      1.5 + Math.random() * 2.5,
      speed.value * (0.85 + Math.random() * 0.2),
    );
    particle.userData.buoyant = true;
    particle.userData.life = 0.3 + Math.random() * 0.3;
    particles.push(particle);
    scene.add(particle);
  }
};

const smashObstacle = (obstacle, index) => {
  obstacles.splice(index, 1);
  const dir = Math.sign(obstacle.position.x - player.position.x) || (Math.random() < 0.5 ? -1 : 1);
  obstacle.userData.flyVel = new THREE.Vector3(
    dir * (6 + Math.random() * 7),
    9 + Math.random() * 6,
    -(4 + Math.random() * 10),
  );
  obstacle.userData.spin = new THREE.Vector3(
    (Math.random() - 0.5) * 10,
    (Math.random() - 0.5) * 8,
    (Math.random() - 0.5) * 10,
  );
  obstacle.userData.flyLife = 1.7;
  flyingCars.push(obstacle);
  score.value += 40;
  sfx.smash();
  spawnBurst(obstacle.position, ['red', 'gold', 'dust'], 12, 9);
  bumpShakeTimer = 0.22;
};

const updateFlyingCars = (delta) => {
  for (let i = flyingCars.length - 1; i >= 0; i -= 1) {
    const car = flyingCars[i];
    car.userData.flyVel.y -= 22 * delta;
    car.position.addScaledVector(car.userData.flyVel, delta);
    car.position.z += speed.value * delta;
    car.rotation.x += car.userData.spin.x * delta;
    car.rotation.y += car.userData.spin.y * delta;
    car.rotation.z += car.userData.spin.z * delta;
    car.userData.flyLife -= delta;
    if (car.userData.flyLife <= 0 || car.position.y < -6) {
      scene.remove(car);
      car.rotation.set(0, 0, 0);
      const key = car.userData?.poolKey;
      if (key) {
        (obstaclePools[key] ||= []).push(car);
      }
      flyingCars.splice(i, 1);
    }
  }
};

const clearFlyingCars = () => {
  flyingCars.forEach((car) => {
    scene.remove(car);
    car.rotation.set(0, 0, 0);
    const key = car.userData?.poolKey;
    if (key) {
      (obstaclePools[key] ||= []).push(car);
    }
  });
  flyingCars = [];
};

const activateGodMode = () => {
  godModeActive.value = true;
  sfx.godMode();
  bumpShakeTimer = 0.3;
};

const lerpEnvironment = (delta) => {
  if (!scene || !scene.fog || !hemiLight) return;
  const target = envSettings[envMode];
  const k = Math.min(1, delta * 0.9);
  scene.background.lerp(tmpEnvColor.set(target.bg), k);
  scene.fog.color.lerp(tmpEnvColor.set(target.fog), k);
  scene.fog.near = THREE.MathUtils.damp(scene.fog.near, target.fogNear, 0.9, delta);
  scene.fog.far = THREE.MathUtils.damp(scene.fog.far, target.fogFar, 0.9, delta);
  hemiLight.intensity = THREE.MathUtils.damp(hemiLight.intensity, target.hemi, 0.9, delta);
  dirLight.intensity = THREE.MathUtils.damp(dirLight.intensity, target.dir, 0.9, delta);
  hemiLight.color.lerp(tmpEnvColor.set(target.hemiSky), k);
  dirLight.color.lerp(tmpEnvColor.set(target.dirColor), k);
};

const applyEnvironmentNow = () => {
  if (!scene || !scene.fog || !hemiLight) return;
  const target = envSettings[envMode];
  scene.background.set(target.bg);
  scene.fog.color.set(target.fog);
  scene.fog.near = target.fogNear;
  scene.fog.far = target.fogFar;
  hemiLight.intensity = target.hemi;
  dirLight.intensity = target.dir;
  hemiLight.color.set(target.hemiSky);
  dirLight.color.set(target.dirColor);
};

const getTerrainAssets = () => {
  if (terrainAssets) return terrainAssets;
  const lambert = (color) => new THREE.MeshLambertMaterial({ color });
  terrainAssets = {
    groundGeo: new THREE.PlaneGeometry(280, 62),
    groundMats: [lambert(0x2e6b40), lambert(0x2a6339), lambert(0x337044)],
    fieldGeo: new THREE.PlaneGeometry(1, 1),
    fieldMats: [lambert(0x8fa24c), lambert(0x3c7a3a), lambert(0x5d8a3c), lambert(0x27583a)],
    lakeGeo: new THREE.CircleGeometry(1, 18),
    lakeMat: lambert(0x3f7fc2),
    trunkGeo: new THREE.CylinderGeometry(0.12, 0.17, 1, 5),
    trunkMat: lambert(0x4a3526),
    pineGeo: new THREE.ConeGeometry(0.9, 2.3, 6),
    pineMat: lambert(0x1d5c38),
    crownGeo: new THREE.IcosahedronGeometry(0.9, 0),
    crownMat: lambert(0x2f7a46),
    houseGeo: new THREE.BoxGeometry(1.7, 1.1, 1.4),
    houseMats: [lambert(0xe8e2d0), lambert(0xd8c8a8), lambert(0xc9d4dd)],
    roofGeo: new THREE.ConeGeometry(1.4, 0.95, 4),
    roofMats: [lambert(0xa8452e), lambert(0x7a4a32)],
    hillGeo: new THREE.IcosahedronGeometry(1, 0),
    hillMat: lambert(0x2c6a3e),
    mtnGeo: new THREE.ConeGeometry(1, 1, 5),
    mtnMats: [lambert(0x5a6a80), lambert(0x50607a)],
    snowMat: lambert(0xe8f2fa),
  };
  return terrainAssets;
};

const pickOne = (list) => list[Math.floor(Math.random() * list.length)];

// A 62-deep strip of countryside: fields, woods, villages, lakes, and
// snow-capped mountains at the edges. Chunks recycle like road segments.
const buildTerrainChunk = () => {
  const a = getTerrainAssets();
  const chunk = new THREE.Group();

  const ground = new THREE.Mesh(a.groundGeo, pickOne(a.groundMats));
  ground.rotation.x = -Math.PI / 2;
  chunk.add(ground);

  for (let i = 0; i < 6; i += 1) {
    const field = new THREE.Mesh(a.fieldGeo, pickOne(a.fieldMats));
    field.rotation.set(-Math.PI / 2, 0, Math.random() * Math.PI);
    field.scale.set(10 + Math.random() * 22, 8 + Math.random() * 16, 1);
    field.position.set((Math.random() - 0.5) * 230, 0.02, (Math.random() - 0.5) * 52);
    chunk.add(field);
  }

  if (Math.random() < 0.35) {
    const lake = new THREE.Mesh(a.lakeGeo, a.lakeMat);
    lake.rotation.x = -Math.PI / 2;
    lake.scale.setScalar(7 + Math.random() * 9);
    lake.position.set((Math.random() - 0.5) * 180, 0.04, (Math.random() - 0.5) * 40);
    chunk.add(lake);
  }

  for (let i = 0; i < 18; i += 1) {
    const tree = new THREE.Group();
    const trunk = new THREE.Mesh(a.trunkGeo, a.trunkMat);
    trunk.position.y = 0.5;
    tree.add(trunk);
    if (Math.random() < 0.5) {
      const pine = new THREE.Mesh(a.pineGeo, a.pineMat);
      pine.position.y = 1.9;
      tree.add(pine);
    } else {
      const crown = new THREE.Mesh(a.crownGeo, a.crownMat);
      crown.position.y = 1.6;
      crown.rotation.y = Math.random() * Math.PI;
      tree.add(crown);
    }
    const treeScale = 1 + Math.random() * 1.3;
    tree.scale.setScalar(treeScale);
    const side = Math.random() < 0.5 ? -1 : 1;
    tree.position.set(side * (12 + Math.random() * 115), 0, (Math.random() - 0.5) * 56);
    tree.rotation.y = Math.random() * Math.PI;
    chunk.add(tree);
  }

  for (let i = 0; i < 4; i += 1) {
    const house = new THREE.Group();
    const walls = new THREE.Mesh(a.houseGeo, pickOne(a.houseMats));
    walls.position.y = 0.55;
    house.add(walls);
    const roof = new THREE.Mesh(a.roofGeo, pickOne(a.roofMats));
    roof.position.y = 1.55;
    roof.rotation.y = Math.PI / 4;
    house.add(roof);
    const side = Math.random() < 0.5 ? -1 : 1;
    house.position.set(side * (14 + Math.random() * 85), 0, (Math.random() - 0.5) * 50);
    house.rotation.y = Math.random() * Math.PI;
    house.scale.setScalar(1 + Math.random() * 0.8);
    chunk.add(house);
  }

  if (Math.random() < 0.45) {
    const hill = new THREE.Mesh(a.hillGeo, a.hillMat);
    hill.scale.set(9 + Math.random() * 8, 2.5 + Math.random() * 2, 7 + Math.random() * 6);
    const side = Math.random() < 0.5 ? -1 : 1;
    hill.position.set(side * (30 + Math.random() * 80), 0, (Math.random() - 0.5) * 40);
    chunk.add(hill);
  }

  for (let i = 0; i < 3; i += 1) {
    const width = 11 + Math.random() * 11;
    const height = 8 + Math.random() * 9;
    const mtnMat = pickOne(a.mtnMats);
    const mountain = new THREE.Mesh(a.mtnGeo, mtnMat);
    mountain.scale.set(width, height, width);
    const side = Math.random() < 0.5 ? -1 : 1;
    mountain.position.set(side * (58 + Math.random() * 65), height / 2, (Math.random() - 0.5) * 50);
    mountain.rotation.y = Math.random() * Math.PI;
    chunk.add(mountain);
    const snow = new THREE.Mesh(a.mtnGeo, a.snowMat);
    const snowH = height * 0.26;
    snow.scale.set(width * 0.28, snowH, width * 0.28);
    snow.position.set(mountain.position.x, height - snowH / 2, mountain.position.z);
    snow.rotation.y = mountain.rotation.y;
    chunk.add(snow);
  }

  return chunk;
};

const buildOcean = () => {
  oceanGroup = new THREE.Group();

  const sun = new THREE.Mesh(
    new THREE.CircleGeometry(48, 30),
    new THREE.MeshBasicMaterial({ color: 0xffc46a, fog: false }),
  );
  sun.position.set(0, 30, -560);
  oceanGroup.add(sun);
  const sunGlow = new THREE.Mesh(
    new THREE.CircleGeometry(95, 30),
    new THREE.MeshBasicMaterial({
      color: 0xff9a50,
      transparent: true,
      opacity: 0.3,
      fog: false,
      depthWrite: false,
      blending: THREE.AdditiveBlending,
    }),
  );
  sunGlow.position.set(0, 28, -561);
  oceanGroup.add(sunGlow);

  scene.add(oceanGroup);

  for (let i = 0; i < 7; i += 1) {
    const chunk = buildTerrainChunk();
    chunk.position.set(0, -1.4, -340 + i * 62);
    terrainChunks.push(chunk);
    scene.add(chunk);
  }
};

const getCloudAssets = () => {
  if (!cloudAssets) {
    cloudAssets = {
      geo: new THREE.SphereGeometry(1, 7, 5),
      mat: new THREE.MeshBasicMaterial({ color: 0xf2f7ff, transparent: true, opacity: 0.82 }),
    };
  }
  return cloudAssets;
};

const spawnCloud = (zRange = [-380, -120]) => {
  const assets = getCloudAssets();
  const cloud = new THREE.Group();
  const puffs = 2 + Math.floor(Math.random() * 3);
  for (let i = 0; i < puffs; i += 1) {
    const puff = new THREE.Mesh(assets.geo, assets.mat);
    puff.position.set(i * 1.4 - puffs * 0.7, (Math.random() - 0.5) * 0.6, (Math.random() - 0.5) * 1.2);
    puff.scale.setScalar(0.8 + Math.random() * 0.9);
    cloud.add(puff);
  }
  const size = 2 + Math.random() * 4;
  cloud.scale.set(size, size * 0.42, size);
  const side = Math.random() < 0.5 ? -1 : 1;
  cloud.position.set(
    side * (14 + Math.random() * 55),
    3 + Math.random() * 34,
    zRange[0] + Math.random() * (zRange[1] - zRange[0]),
  );
  clouds.push(cloud);
  scene.add(cloud);
};

const updateOceanMotion = (delta) => {
  if (!oceanGroup) return;
  terrainChunks.forEach((chunk) => {
    chunk.position.z += speed.value * delta;
    if (chunk.position.z > 95) {
      chunk.position.z -= terrainChunks.length * 62;
    }
  });
  while (clouds.length < 12) {
    spawnCloud();
  }
  for (let i = clouds.length - 1; i >= 0; i -= 1) {
    const cloud = clouds[i];
    cloud.position.z += speed.value * delta;
    if (cloud.position.z > 40) {
      scene.remove(cloud);
      clouds.splice(i, 1);
      spawnCloud([-380, -260]);
    }
  }
};

const clearClouds = () => {
  clouds.forEach((cloud) => scene.remove(cloud));
  clouds = [];
};

const getEnemyAssets = () => {
  if (!enemyAssets) {
    enemyAssets = {
      coreGeo: new THREE.OctahedronGeometry(1.05),
      coreMat: new THREE.MeshLambertMaterial({
        color: 0x2a3350,
        emissive: 0x131c30,
        emissiveIntensity: 0.5,
      }),
      ringGeo: new THREE.TorusGeometry(1.5, 0.15, 8, 20),
      ringMat: new THREE.MeshLambertMaterial({ color: 0x50628a }),
      eyeGeo: new THREE.SphereGeometry(0.34, 10, 8),
      eyeMat: new THREE.MeshBasicMaterial({ color: 0xff3b57 }),
      boltGeo: new THREE.BoxGeometry(0.16, 0.16, 1.5),
      boltMat: new THREE.MeshBasicMaterial({ color: 0x7dffb0 }),
      enemyBoltGeo: new THREE.BoxGeometry(0.3, 0.3, 1.6),
      motherBoltGeo: new THREE.BoxGeometry(0.5, 0.5, 2.2),
      motherBoltMat: new THREE.MeshBasicMaterial({ color: 0x67f05a }),
      podGeo: new THREE.BoxGeometry(0.5, 0.4, 1.1),
      hpBarGeo: new THREE.PlaneGeometry(1, 1),
      hpBgMat: new THREE.MeshBasicMaterial({
        color: 0x0a0f1a,
        transparent: true,
        opacity: 0.7,
        depthWrite: false,
      }),
      hpFillMat: new THREE.MeshBasicMaterial({ color: 0xff4d5e, depthWrite: false }),
    };
  }
  return enemyAssets;
};

const attachHpBar = (enemy, width, yOffset) => {
  const assets = getEnemyAssets();
  const compensate = 1 / enemy.scale.x;
  const barGroup = new THREE.Group();
  barGroup.scale.setScalar(compensate);
  barGroup.position.y = yOffset * compensate;
  const bg = new THREE.Mesh(assets.hpBarGeo, assets.hpBgMat);
  bg.scale.set(width + 0.3, 0.55, 1);
  barGroup.add(bg);
  const fill = new THREE.Mesh(assets.hpBarGeo, assets.hpFillMat);
  fill.scale.set(width, 0.34, 1);
  fill.position.z = 0.02;
  barGroup.add(fill);
  enemy.add(barGroup);
  enemy.userData.hpBar = { fill, width };
};

const updateHpBar = (enemy) => {
  const bar = enemy.userData.hpBar;
  if (!bar) return;
  const t = THREE.MathUtils.clamp(enemy.userData.hp / enemy.userData.maxHp, 0, 1);
  bar.fill.scale.x = Math.max(0.02, bar.width * t);
  bar.fill.position.x = (-bar.width * (1 - t)) / 2;
};

const spawnEnemy = () => {
  const assets = getEnemyAssets();
  let enemy = enemyPool.pop();
  if (!enemy) {
    enemy = new THREE.Group();
    const core = new THREE.Mesh(assets.coreGeo, assets.coreMat);
    enemy.add(core);
    const ring = new THREE.Mesh(assets.ringGeo, assets.ringMat);
    enemy.add(ring);
    const eye = new THREE.Mesh(assets.eyeGeo, assets.eyeMat);
    eye.position.z = 1.0;
    enemy.add(eye);
    // Twin gun pods under the ring.
    [-1.3, 1.3].forEach((x) => {
      const pod = new THREE.Mesh(assets.podGeo, assets.ringMat);
      pod.position.set(x, -0.7, 0.5);
      enemy.add(pod);
    });
    enemy.userData.ring = ring;
    enemy.userData.eye = eye;
    enemy.scale.setScalar(3.1);
  }
  if (!enemy.userData.hpBar) {
    attachHpBar(enemy, 4.6, 4.4);
  }
  enemy.userData.kind = 'drone';
  enemy.userData.hp = Math.min(90, 50 + bossCount * 10);
  enemy.userData.maxHp = enemy.userData.hp;
  enemy.userData.baseX = (Math.random() - 0.5) * 16;
  enemy.userData.baseY = 8 + Math.random() * 8;
  enemy.userData.ampX = 3 + Math.random() * 4;
  enemy.userData.ampY = 1 + Math.random() * 2.5;
  enemy.userData.freq = 0.5 + Math.random() * 0.8;
  enemy.userData.phase = Math.random() * Math.PI * 2;
  enemy.userData.shootTimer = 2.2;
  enemy.userData.burstLeft = 0;
  enemy.userData.burstTick = 0;
  enemy.userData.dodgeTimer = 1.6;
  enemy.userData.agile = 0;
  enemy.position.set(enemy.userData.baseX, enemy.userData.baseY, -220);
  enemies.push(enemy);
  scene.add(enemy);
};

// The mothership: a proper alien saucer — hull discs, glass dome, rotating
// rim lights, gun pods, tractor emitter, antennae, and a shield bubble.
const buildMothershipModel = () => {
  const lambert = (color, glow = 0) =>
    new THREE.MeshLambertMaterial({
      color,
      emissive: glow ? color : 0x000000,
      emissiveIntensity: glow,
    });
  const ship = new THREE.Group();

  const hull = new THREE.Mesh(new THREE.CylinderGeometry(2.2, 5.6, 1.5, 12), lambert(0x565f7d));
  ship.add(hull);
  const belly = new THREE.Mesh(new THREE.CylinderGeometry(5.6, 3.0, 1.1, 12), lambert(0x3f4763));
  belly.position.y = -1.3;
  ship.add(belly);
  const rim = new THREE.Mesh(new THREE.TorusGeometry(5.6, 0.35, 8, 24), lambert(0x2c3350));
  rim.rotation.x = Math.PI / 2;
  rim.position.y = -0.75;
  ship.add(rim);

  const dome = new THREE.Mesh(
    new THREE.SphereGeometry(2.1, 14, 10, 0, Math.PI * 2, 0, Math.PI / 2),
    new THREE.MeshLambertMaterial({
      color: 0x7df9d0,
      emissive: 0x2c8a6e,
      emissiveIntensity: 0.55,
      transparent: true,
      opacity: 0.85,
    }),
  );
  dome.position.y = 0.75;
  ship.add(dome);

  const lightRing = new THREE.Group();
  const lightGeoSmall = new THREE.SphereGeometry(0.3, 8, 6);
  for (let i = 0; i < 10; i += 1) {
    const angle = (i / 10) * Math.PI * 2;
    const light = new THREE.Mesh(
      lightGeoSmall,
      new THREE.MeshBasicMaterial({ color: i % 2 ? 0xffa22e : 0x67f05a }),
    );
    light.position.set(Math.cos(angle) * 5.1, -0.72, Math.sin(angle) * 5.1);
    lightRing.add(light);
  }
  ship.add(lightRing);

  const podGeoBig = new THREE.BoxGeometry(1.0, 0.8, 2.0);
  const podMat = lambert(0x2c3350);
  for (let i = 0; i < 4; i += 1) {
    const angle = (i / 4) * Math.PI * 2 + Math.PI / 4;
    const pod = new THREE.Mesh(podGeoBig, podMat);
    pod.position.set(Math.cos(angle) * 4.1, -1.15, Math.sin(angle) * 4.1);
    pod.lookAt(pod.position.x * 2, -1.15, pod.position.z * 2);
    ship.add(pod);
  }

  const emitter = new THREE.Mesh(
    new THREE.ConeGeometry(1.5, 1.5, 8),
    lambert(0x9b59d0, 0.6),
  );
  emitter.rotation.x = Math.PI;
  emitter.position.y = -2.3;
  ship.add(emitter);

  const antennaGeoThin = new THREE.CylinderGeometry(0.05, 0.05, 1.6, 5);
  const antennaMat = lambert(0x2c3350);
  const tipGeo = new THREE.SphereGeometry(0.16, 6, 5);
  [[-1.1, 0.4], [1.2, -0.3]].forEach(([x, z]) => {
    const antenna = new THREE.Mesh(antennaGeoThin, antennaMat);
    antenna.position.set(x, 2.2, z);
    ship.add(antenna);
    const tip = new THREE.Mesh(tipGeo, new THREE.MeshBasicMaterial({ color: 0xff3b57 }));
    tip.position.set(x, 3.05, z);
    ship.add(tip);
  });

  const shieldMeshBig = new THREE.Mesh(
    new THREE.SphereGeometry(8, 18, 14),
    new THREE.MeshBasicMaterial({
      color: 0x7df9d0,
      transparent: true,
      opacity: 0.15,
      depthWrite: false,
      blending: THREE.AdditiveBlending,
    }),
  );
  shieldMeshBig.visible = false;
  ship.add(shieldMeshBig);

  ship.userData.lightRing = lightRing;
  ship.userData.shieldMesh = shieldMeshBig;
  return ship;
};

const spawnMothership = () => {
  if (!motherTemplate) {
    motherTemplate = buildMothershipModel();
    attachHpBar(motherTemplate, 11, 4.6);
  }
  const ship = motherTemplate;
  ship.userData.kind = 'mother';
  ship.userData.hp = 260 + mothershipCount * 60;
  ship.userData.maxHp = ship.userData.hp;
  ship.userData.baseX = 0;
  ship.userData.baseY = 13;
  ship.userData.freq = 0.4;
  ship.userData.phase = 0;
  ship.userData.attackTimer = 2.6;
  ship.userData.burstLeft = 0;
  ship.userData.burstTick = 0;
  ship.userData.shieldTimer = 0;
  ship.userData.ramCooldown = 0;
  ship.userData.shieldMesh.visible = false;
  ship.position.set(0, 13, -240);
  enemies.push(ship);
  scene.add(ship);
  sfx.motherSpawn();
  driveHintText.value = 'MOTHERSHIP INBOUND — it heals whenever it hits you!';
  driveHint.value = true;
  if (driveHintTimer) {
    clearTimeout(driveHintTimer);
  }
  driveHintTimer = setTimeout(() => {
    driveHint.value = false;
  }, 4200);
};

const fireEnemyBolt = (enemy, targetPoint = null) => {
  const assets = getEnemyAssets();
  const isMother = enemy.userData.kind === 'mother';
  let bolt = enemyBoltPool.pop();
  if (!bolt) {
    bolt = new THREE.Mesh(assets.enemyBoltGeo, assets.eyeMat);
  }
  bolt.geometry = isMother ? assets.motherBoltGeo : assets.enemyBoltGeo;
  bolt.material = isMother ? assets.motherBoltMat : assets.eyeMat;
  bolt.position.copy(enemy.position);
  bolt.position.z += isMother ? 4 : 2.5;
  const target = targetPoint || player.position;
  const dir = new THREE.Vector3()
    .subVectors(target, bolt.position)
    .normalize()
    .multiplyScalar(isMother ? 46 : 42);
  bolt.userData.vel = dir;
  bolt.userData.life = 6;
  bolt.userData.mother = isMother;
  bolt.userData.dmg = isMother ? 20 : 18;
  bolt.lookAt(target);
  enemyBolts.push(bolt);
  scene.add(bolt);
};

// Cross-pattern spread: a horizontal and a vertical fan of bolts.
const fireShotgun = (enemy) => {
  [-8, -4, 0, 4, 8].forEach((offset) => {
    fireEnemyBolt(
      enemy,
      new THREE.Vector3(player.position.x + offset, player.position.y, player.position.z),
    );
    if (offset !== 0) {
      fireEnemyBolt(
        enemy,
        new THREE.Vector3(
          player.position.x,
          THREE.MathUtils.clamp(player.position.y + offset * 0.6, 3, 21),
          player.position.z,
        ),
      );
    }
  });
  sfx.shotgun();
};

const clearEnemyBolts = () => {
  enemyBolts.forEach((bolt) => {
    scene.remove(bolt);
    enemyBoltPool.push(bolt);
  });
  enemyBolts = [];
};

const damagePlayer = (amount) => {
  playerHp.value = Math.max(0, playerHp.value - amount);
  sfx.playerHit();
  bumpShakeTimer = 0.3;
  damageFlash.value = false;
  if (damageFlashTimer) {
    clearTimeout(damageFlashTimer);
  }
  requestAnimationFrame(() => {
    damageFlash.value = true;
    damageFlashTimer = setTimeout(() => {
      damageFlash.value = false;
    }, 450);
  });
  if (playerHp.value <= 0) {
    startCrash();
  }
};

const removeEnemy = (index) => {
  const enemy = enemies[index];
  scene.remove(enemy);
  if (enemy.userData.kind !== 'mother') {
    // The mothership is a singleton template — never pool it as a drone.
    enemyPool.push(enemy);
  }
  enemies.splice(index, 1);
};

const clearEnemies = () => {
  enemies.forEach((enemy) => {
    scene.remove(enemy);
    if (enemy.userData.kind !== 'mother') {
      enemyPool.push(enemy);
    }
  });
  enemies = [];
};

const fireProjectiles = () => {
  const assets = getEnemyAssets();
  [-1.1, 1.1].forEach((x) => {
    let bolt = projectilePool.pop();
    if (!bolt) {
      bolt = new THREE.Mesh(assets.boltGeo, assets.boltMat);
    }
    bolt.position.set(player.position.x + x, player.position.y - 0.2, player.position.z - 2.2);
    projectiles.push(bolt);
    scene.add(bolt);
  });
  sfx.shoot();
};

const clearProjectiles = () => {
  projectiles.forEach((bolt) => {
    scene.remove(bolt);
    projectilePool.push(bolt);
  });
  projectiles = [];
};

const buildRamp = () => {
  ramp = new THREE.Group();
  const deck = new THREE.Group();
  deck.rotation.x = 0.27;
  deck.position.y = 2.9;

  const surface = new THREE.Mesh(
    new THREE.BoxGeometry(9, 0.5, 22),
    new THREE.MeshLambertMaterial({ color: 0x27344e }),
  );
  deck.add(surface);
  const edgeGeo = new THREE.BoxGeometry(0.22, 0.14, 22);
  const edgeMat = new THREE.MeshBasicMaterial({ color: 0x2ee5ff });
  [-4.4, 4.4].forEach((x) => {
    const edge = new THREE.Mesh(edgeGeo, edgeMat);
    edge.position.set(x, 0.3, 0);
    deck.add(edge);
  });
  ramp.add(deck);

  const strutMat = new THREE.MeshLambertMaterial({ color: 0x1c2740 });
  [[-3, -4, 2.2], [3, -4, 2.2], [-3, -9.5, 4.6], [3, -9.5, 4.6]].forEach(([x, z, h]) => {
    const strut = new THREE.Mesh(new THREE.BoxGeometry(0.5, h, 0.5), strutMat);
    strut.position.set(x, h / 2, z);
    ramp.add(strut);
  });

  ramp.position.z = -290;
  scene.add(ramp);
};

const disposeZone3 = () => {
  [ramp, oceanGroup].forEach((group) => {
    if (!group) return;
    scene.remove(group);
    group.traverse((node) => {
      if (node.isMesh) {
        node.geometry?.dispose();
        const materials = Array.isArray(node.material) ? node.material : [node.material];
        materials.forEach((material) => material?.dispose());
      }
    });
  });
  ramp = null;
  oceanGroup = null;
  // Terrain chunks only use shared assets — removing them is enough.
  terrainChunks.forEach((chunk) => scene.remove(chunk));
  terrainChunks = [];
  clearClouds();
};

const buildFallbackPlaneModel = () => {
  const group = new THREE.Group();
  const bodyMat = new THREE.MeshLambertMaterial({ color: 0xdfe4ec });
  const accentMat = new THREE.MeshLambertMaterial({ color: 0xd7404f });
  const fuselage = new THREE.Mesh(new THREE.BoxGeometry(1.3, 1.3, 7), bodyMat);
  group.add(fuselage);
  const wings = new THREE.Mesh(new THREE.BoxGeometry(10, 0.18, 1.8), accentMat);
  wings.position.set(0, 0.3, -0.6);
  group.add(wings);
  const tailWing = new THREE.Mesh(new THREE.BoxGeometry(3.6, 0.14, 1.1), accentMat);
  tailWing.position.set(0, 0.4, 3);
  group.add(tailWing);
  const fin = new THREE.Mesh(new THREE.BoxGeometry(0.16, 1.6, 1.3), accentMat);
  fin.position.set(0, 1, 3.1);
  group.add(fin);
  return group;
};

const loadPlaneModel = () => {
  const loader = new GLTFLoader();
  loader.load(
    '/models/plane/cesium-air.glb',
    (gltf) => {
      const model = gltf.scene;
      const rawSize = new THREE.Box3().setFromObject(model).getSize(new THREE.Vector3());
      const scale = 5.5 / rawSize.z;
      model.scale.setScalar(scale);
      const box = new THREE.Box3().setFromObject(model);
      model.position.sub(box.getCenter(new THREE.Vector3()));
      planeTemplate = { model, clips: gltf.animations };
    },
    undefined,
    () => {
      planeTemplate = { model: buildFallbackPlaneModel(), clips: [] };
    },
  );
};

const triggerRamp = () => {
  rampTriggered = true;
  finalePhase.value = 'ramp';
  buildRamp();
  buildOcean();
  envMode = 'sunrise';
  if (starPoints) starPoints.visible = false;
  if (moonMesh) moonMesh.visible = false;
  if (skylineMesh) skylineMesh.visible = false;
  musicDuckTarget = 0.45;
};

const buildDockingPlane = () => {
  dockingPlane = new THREE.Group();
  if (planeTemplate) {
    planeTemplate.model.rotation.set(0, Math.PI, 0);
    dockingPlane.add(planeTemplate.model);
    if (planeTemplate.clips.length) {
      planeMixer = new THREE.AnimationMixer(planeTemplate.model);
      planeTemplate.clips.forEach((clip) => planeMixer.clipAction(clip).play());
    }
  } else {
    dockingPlane.add(buildFallbackPlaneModel());
  }
  dockingPlane.position.set(0, 15, -110);
  scene.add(dockingPlane);
};

const doLaunch = () => {
  finalePhase.value = 'launch';
  // Leave the deck with the velocity the slope actually gives us.
  launchVy = Math.max(12, speed.value * 0.28);
  launchTimer = 0;
  sfx.launch();
  clearObstacles();
  clearCoins();
  clearPowerups();
  clearFlyingCars();
  buildDockingPlane();
  if (dockingPlane) {
    dockingPlane.position.z = player.position.z - 95;
  }
};

const enterPlane = () => {
  finalePhase.value = 'plane';
  sfx.dock();
  if (carVisual) {
    player.remove(carVisual);
    carVisual = null;
  }
  carWheels = [];
  godModeActive.value = false;
  godHoldTimer = 0;
  planeVisual = dockingPlane;
  scene.remove(dockingPlane);
  player.position.copy(dockingPlane.position);
  planeVisual.position.set(0, 0, 0);
  planeVisual.rotation.set(0, 0, 0);
  player.add(planeVisual);
  dockingPlane = null;
  planeVelX = 0;
  planeVelY = 0;
  playerHp.value = 100;
  bossCount = 0;
  killsSinceMother = 0;
  enemySpawnTimer = 2.6;
  fireTimer = 0.5;
  envMode = 'day';
  musicDuckTarget = 0.85;
  driveHintText.value = 'Zone 3 — joystick or WASD/arrows to fly. Guns fire automatically!';
  driveHint.value = true;
  if (driveHintTimer) {
    clearTimeout(driveHintTimer);
  }
  driveHintTimer = setTimeout(() => {
    driveHint.value = false;
  }, 5200);
};

const updateLaunch = (delta) => {
  speed.value = THREE.MathUtils.damp(speed.value, 60, 1.5, delta);
  updateEngineSound();
  launchTimer += delta;
  score.value += speed.value * delta * 2.4;

  // The road keeps sliding past below but never wraps back in — it ends.
  floorSegments.forEach((segment) => {
    if (!segment.visible) return;
    segment.position.z += speed.value * delta;
    if (segment.position.z > 45) {
      segment.visible = false;
    }
  });
  if (ramp) {
    ramp.position.z += speed.value * delta;
    if (ramp.position.z > 140) {
      scene.remove(ramp);
    }
  }
  updateOceanMotion(delta);

  launchVy -= 7.5 * delta;
  player.position.y = Math.max(3, player.position.y + launchVy * delta);
  player.position.x = THREE.MathUtils.damp(player.position.x, 0, 3, delta);
  // Glide forward over the cliff edge instead of hanging in place.
  player.position.z = THREE.MathUtils.damp(player.position.z, -4.5, 0.7, delta);
  player.rotation.x = THREE.MathUtils.damp(
    player.rotation.x,
    THREE.MathUtils.clamp(launchVy * 0.03, -0.45, 0.5),
    3,
    delta,
  );
  if (godModeActive.value) {
    emitFireTrail();
  }

  if (dockingPlane) {
    // The plane flies to a hold point just ahead of the car and waits there,
    // so it can never overshoot and drag the car backwards.
    const holdZ = player.position.z - 4.8;
    dockingPlane.position.z += Math.max(0, speed.value - 26) * delta;
    if (dockingPlane.position.z > holdZ) {
      dockingPlane.position.z = holdZ;
    }
    planeMixer?.update(delta);
    const dockZ = dockingPlane.position.z + 3.6;
    const planeHolding = dockingPlane.position.z >= holdZ - 0.5;
    if ((launchVy < 3 && planeHolding) || launchTimer > 7) {
      player.position.x = THREE.MathUtils.damp(player.position.x, dockingPlane.position.x, 8, delta);
      player.position.y = THREE.MathUtils.damp(player.position.y, dockingPlane.position.y, 6, delta);
      player.position.z = THREE.MathUtils.damp(player.position.z, dockZ, 5, delta);
      const docked =
        Math.abs(player.position.z - dockZ) < 1.3 &&
        Math.abs(player.position.y - dockingPlane.position.y) < 1.1;
      if (docked || launchTimer > 8.5) {
        enterPlane();
        return;
      }
    }
  } else if (launchTimer > 7) {
    // Should never happen, but never strand the player mid-air.
    buildDockingPlane();
    dockingPlane.position.set(0, player.position.y, player.position.z - 6);
  }

  camera.position.x = THREE.MathUtils.damp(camera.position.x, 0, 4, delta);
  camera.position.y = THREE.MathUtils.damp(camera.position.y, player.position.y + 2.8, 4, delta);
  camera.position.z = THREE.MathUtils.damp(camera.position.z, player.position.z + 10.5, 4, delta);
  camera.fov = THREE.MathUtils.damp(camera.fov, 66, 3, delta);
  camera.updateProjectionMatrix();
  lookAtTarget.set(player.position.x, player.position.y * 0.92, player.position.z - 14);
  camera.lookAt(lookAtTarget);
};

const updatePlane = (delta) => {
  const inputX = THREE.MathUtils.clamp(
    (planeKeys.right ? 1 : 0) - (planeKeys.left ? 1 : 0) + joyVec.x,
    -1,
    1,
  );
  const inputY = THREE.MathUtils.clamp(
    (planeKeys.up ? 1 : 0) - (planeKeys.down ? 1 : 0) - joyVec.y,
    -1,
    1,
  );

  // Diving picks up a little speed, climbing bleeds some.
  speed.value = THREE.MathUtils.damp(speed.value, 42 - inputY * 6, 2, delta);
  updateEngineSound();
  score.value += speed.value * delta * 2.4;

  planeVelX = THREE.MathUtils.damp(planeVelX, inputX * 20, 6, delta);
  planeVelY = THREE.MathUtils.damp(planeVelY, inputY * 13, 6, delta);
  player.position.x = THREE.MathUtils.clamp(player.position.x + planeVelX * delta, -13, 13);
  player.position.y = THREE.MathUtils.clamp(player.position.y + planeVelY * delta, 4, 20);
  player.position.z = THREE.MathUtils.damp(player.position.z, 2, 0.8, delta);

  if (planeVisual) {
    planeVisual.rotation.z = THREE.MathUtils.damp(
      planeVisual.rotation.z,
      THREE.MathUtils.clamp(-planeVelX * 0.055, -0.85, 0.85),
      7,
      delta,
    );
    planeVisual.rotation.x = THREE.MathUtils.damp(
      planeVisual.rotation.x,
      THREE.MathUtils.clamp(planeVelY * 0.045, -0.6, 0.6),
      7,
      delta,
    );
    planeVisual.rotation.y = THREE.MathUtils.damp(planeVisual.rotation.y, -planeVelX * 0.014, 6, delta);
    // Gentle idle bob so the plane never feels frozen.
    planeVisual.position.y = Math.sin(performance.now() * 0.0016) * 0.15;
  }
  player.rotation.x = THREE.MathUtils.damp(player.rotation.x, 0, 4, delta);
  planeMixer?.update(delta);

  updateOceanMotion(delta);

  // --- Combat: one big boss drone at a time; every 5 kills the mothership. ---
  if (!enemies.length) {
    enemySpawnTimer -= delta;
    if (enemySpawnTimer <= 0) {
      if (killsSinceMother >= 5) {
        spawnMothership();
      } else {
        spawnEnemy();
      }
    }
  }

  fireTimer -= delta;
  if (fireTimer <= 0) {
    fireProjectiles();
    fireTimer = 0.18;
  }

  for (let i = projectiles.length - 1; i >= 0; i -= 1) {
    const bolt = projectiles[i];
    bolt.position.z -= 150 * delta;
    if (bolt.position.z < -280) {
      scene.remove(bolt);
      projectilePool.push(bolt);
      projectiles.splice(i, 1);
    }
  }

  for (let i = enemies.length - 1; i >= 0; i -= 1) {
    const enemy = enemies[i];
    const ud = enemy.userData;
    const isMother = ud.kind === 'mother';
    ud.phase += delta * ud.freq;

    if (isMother) {
      enemy.position.z = THREE.MathUtils.damp(enemy.position.z, -68, 0.7, delta);
      ud.baseX = THREE.MathUtils.damp(ud.baseX, player.position.x * 0.7, 0.25, delta);
      enemy.position.x = ud.baseX + Math.sin(ud.phase) * 4;
      enemy.position.y = THREE.MathUtils.clamp(13 + Math.cos(ud.phase * 0.8) * 2.5, 9, 18);
      ud.lightRing.rotation.y += delta * 1.4;
      enemy.rotation.y += delta * 0.12;

      if (ud.shieldTimer > 0) {
        ud.shieldTimer -= delta;
        ud.shieldMesh.visible = true;
        ud.shieldMesh.material.opacity = 0.12 + Math.sin(performance.now() * 0.012) * 0.05;
        if (ud.shieldTimer <= 0) {
          ud.shieldMesh.visible = false;
        }
      }

      if (ud.burstLeft > 0) {
        ud.burstTick -= delta;
        if (ud.burstTick <= 0) {
          fireEnemyBolt(enemy, new THREE.Vector3(
            player.position.x + (Math.random() - 0.5) * 3,
            player.position.y + (Math.random() - 0.5) * 2,
            player.position.z,
          ));
          sfx.enemyShoot();
          ud.burstLeft -= 1;
          ud.burstTick = 0.13;
        }
      } else if (enemy.position.z > -150) {
        ud.attackTimer -= delta;
        if (ud.attackTimer <= 0) {
          const roll = Math.random();
          if (roll < 0.4) {
            ud.burstLeft = 6;
            ud.burstTick = 0;
          } else if (roll < 0.75) {
            fireShotgun(enemy);
          } else {
            ud.shieldTimer = 3;
            sfx.shieldUp();
          }
          ud.attackTimer = 2.3 + Math.random() * 1.6;
        }
      }
      if (ud.ramCooldown > 0) {
        ud.ramCooldown -= delta;
      }
    } else {
      enemy.position.z = THREE.MathUtils.damp(enemy.position.z, -52, 0.9, delta);
      // Occasional quick dodge to a new position near the player.
      ud.dodgeTimer -= delta;
      if (ud.dodgeTimer <= 0) {
        ud.baseX = THREE.MathUtils.clamp(player.position.x + (Math.random() - 0.5) * 16, -12, 12);
        ud.baseY = THREE.MathUtils.clamp(player.position.y + (Math.random() - 0.5) * 10, 5, 18);
        ud.agile = 0.8;
        ud.dodgeTimer = 1.4 + Math.random() * 1.6;
      }
      ud.agile -= delta;
      const chaseRate = ud.agile > 0 ? 2.4 : 0.35;
      ud.baseX = THREE.MathUtils.damp(ud.baseX, player.position.x, chaseRate * 0.3, delta);
      enemy.position.x = ud.baseX + Math.sin(ud.phase) * ud.ampX;
      enemy.position.y = THREE.MathUtils.clamp(
        ud.baseY + Math.cos(ud.phase * 1.3) * ud.ampY,
        5,
        19,
      );
      ud.ring.rotation.z += delta * 2.2;

      // Telegraphed triple burst: the eye swells, then three quick shots.
      if (enemy.position.z > -140) {
        if (ud.burstLeft > 0) {
          ud.burstTick -= delta;
          if (ud.burstTick <= 0) {
            fireEnemyBolt(enemy, new THREE.Vector3(
              player.position.x + (Math.random() - 0.5) * 2.5,
              player.position.y + (Math.random() - 0.5) * 1.6,
              player.position.z,
            ));
            sfx.enemyShoot();
            ud.burstLeft -= 1;
            ud.burstTick = 0.15;
          }
        } else {
          ud.shootTimer -= delta;
          if (ud.shootTimer <= 0.45) {
            ud.eye.scale.setScalar(1 + (0.45 - Math.max(0, ud.shootTimer)) * 1.6);
          }
          if (ud.shootTimer <= 0) {
            ud.burstLeft = 3;
            ud.burstTick = 0;
            ud.eye.scale.setScalar(1);
            ud.shootTimer = 1.6 + Math.random() * 1.1;
          }
        }
      }
    }

    updateHpBar(enemy);

    const hbX = isMother ? 7.4 : 4.2;
    const hbY = isMother ? 4.6 : 4.0;
    const hbZ = isMother ? 6.2 : 4.4;
    let hit = false;
    for (let j = projectiles.length - 1; j >= 0; j -= 1) {
      const bolt = projectiles[j];
      if (
        Math.abs(bolt.position.z - enemy.position.z) < hbZ &&
        Math.abs(bolt.position.x - enemy.position.x) < hbX &&
        Math.abs(bolt.position.y - enemy.position.y) < hbY
      ) {
        scene.remove(bolt);
        projectilePool.push(bolt);
        projectiles.splice(j, 1);
        if (isMother && ud.shieldTimer > 0) {
          // Shield absorbs the shot.
          spawnBurst(bolt.position, ['skin'], 2, 2);
          continue;
        }
        ud.hp -= 1;
        hit = true;
      }
    }
    if (hit) {
      spawnBurst(enemy.position, ['gold'], 2, 3);
      if (ud.hp <= 0) {
        if (isMother) {
          score.value += 2500;
          playerHp.value = Math.min(100, playerHp.value + 40);
          mothershipCount += 1;
          killsSinceMother = 0;
          sfx.enemyDown();
          sfx.smash();
          spawnBurst(enemy.position, ['red', 'gold', 'flame'], 40, 14);
          spawnBurst(enemy.position, ['dust', 'gold'], 30, 9);
          bumpShakeTimer = 0.5;
          enemySpawnTimer = 3.5;
        } else {
          score.value += 600;
          playerHp.value = Math.min(100, playerHp.value + 15);
          bossCount += 1;
          killsSinceMother += 1;
          sfx.enemyDown();
          spawnBurst(enemy.position, ['red', 'gold', 'dust'], 30, 11);
          enemySpawnTimer = 2.4;
        }
        removeEnemy(i);
        continue;
      }
    }

    const ramX = isMother ? 7.8 : 4.4;
    const ramY = isMother ? 4.4 : 3.4;
    const ramZ = isMother ? 6.4 : 4.2;
    if (
      Math.abs(enemy.position.z - player.position.z) < ramZ &&
      Math.abs(enemy.position.x - player.position.x) < ramX &&
      Math.abs(enemy.position.y - player.position.y) < ramY
    ) {
      if (isMother) {
        if (ud.ramCooldown <= 0) {
          ud.ramCooldown = 1.2;
          damagePlayer(50);
          planeVelY = -14;
          if (state.value !== 'running') {
            return;
          }
        }
      } else {
        // Ramming hurts a lot and destroys the drone.
        damagePlayer(40);
        spawnBurst(enemy.position, ['red', 'dust'], 20, 9);
        removeEnemy(i);
        enemySpawnTimer = 2.4;
        if (state.value !== 'running') {
          return;
        }
        continue;
      }
    }
  }

  for (let i = enemyBolts.length - 1; i >= 0; i -= 1) {
    const bolt = enemyBolts[i];
    bolt.position.addScaledVector(bolt.userData.vel, delta);
    bolt.userData.life -= delta;
    if (bolt.userData.life <= 0 || bolt.position.z > 24) {
      scene.remove(bolt);
      enemyBoltPool.push(bolt);
      enemyBolts.splice(i, 1);
      continue;
    }
    if (
      Math.abs(bolt.position.z - player.position.z) < 2.2 &&
      Math.abs(bolt.position.x - player.position.x) < 2.6 &&
      Math.abs(bolt.position.y - player.position.y) < 1.7
    ) {
      const fromMother = bolt.userData.mother;
      const damage = bolt.userData.dmg || 20;
      scene.remove(bolt);
      enemyBoltPool.push(bolt);
      enemyBolts.splice(i, 1);
      damagePlayer(damage);
      if (state.value !== 'running') {
        return;
      }
      if (fromMother) {
        // Health absorption: the mothership feeds on every hit it lands.
        const mother = enemies.find((entity) => entity.userData.kind === 'mother');
        if (mother) {
          mother.userData.hp = Math.min(mother.userData.maxHp, mother.userData.hp + 10);
          sfx.absorb();
          spawnBurst(mother.position, ['heal'], 8, 5);
        }
      }
    }
  }

  camera.position.x = THREE.MathUtils.damp(camera.position.x, player.position.x * 0.5, 4, delta);
  camera.position.y = THREE.MathUtils.damp(camera.position.y, player.position.y + 3.2, 4, delta);
  camera.position.z = THREE.MathUtils.damp(camera.position.z, player.position.z + 10, 4, delta);
  camera.fov = THREE.MathUtils.damp(camera.fov, 62 + Math.abs(planeVelX) * 0.2, 3, delta);
  camera.updateProjectionMatrix();
  lookAtTarget.set(player.position.x * 0.85, player.position.y + 0.3, player.position.z - 12);
  camera.lookAt(lookAtTarget);
};

const joyStart = (event) => {
  joyPointerId = event.pointerId;
  event.currentTarget.setPointerCapture(event.pointerId);
  joyMove(event);
};

const joyMove = (event) => {
  if (joyPointerId !== event.pointerId) return;
  const rect = event.currentTarget.getBoundingClientRect();
  let dx = event.clientX - (rect.left + rect.width / 2);
  let dy = event.clientY - (rect.top + rect.height / 2);
  const max = rect.width / 2 - 18;
  const len = Math.hypot(dx, dy);
  if (len > max) {
    dx *= max / len;
    dy *= max / len;
  }
  joyKnob.value = { x: dx, y: dy };
  joyVec = { x: dx / max, y: dy / max };
};

const joyEnd = (event) => {
  if (joyPointerId !== event.pointerId) return;
  joyPointerId = null;
  joyKnob.value = { x: 0, y: 0 };
  joyVec = { x: 0, y: 0 };
};

const exitFinale = () => {
  finaleTriggered = false;
  finalePhase.value = 'none';
  finaleTimer = 0;
  finaleToast.value = false;
  driveHint.value = false;
  if (carVisual) {
    player.remove(carVisual);
    carVisual = null;
  }
  carWheels = [];
  driveCamera.value = 'chase';
  applyCameraZoom();
  if (activeCharacter) {
    activeCharacter.root.visible = true;
  } else {
    proceduralParts.forEach((part) => {
      part.visible = true;
    });
  }
  disposePlaza();
  setRoadZone(1);
  musicDuckTarget = 1;
  accelHeld = false;
  brakeHeld = false;
  driveTargetSpeed = 24;
  driveSpawnTimer = 0;
  godModeActive.value = false;
  godHoldTimer = 0;
  clearFlyingCars();

  // Zone 3 cleanup: back to the night city.
  if (planeVisual) {
    player.remove(planeVisual);
    planeVisual = null;
  }
  if (dockingPlane) {
    scene.remove(dockingPlane);
    dockingPlane = null;
  }
  if (planeMixer) {
    planeMixer.stopAllAction();
    planeMixer = null;
  }
  disposeZone3();
  clearEnemies();
  clearProjectiles();
  clearEnemyBolts();
  playerHp.value = 100;
  damageFlash.value = false;
  bossCount = 0;
  killsSinceMother = 0;
  mothershipCount = 0;
  rampTriggered = false;
  rampHintShown = false;
  enemySpawnTimer = 0;
  fireTimer = 0;
  launchVy = 0;
  launchTimer = 0;
  planeVelX = 0;
  planeVelY = 0;
  planeKeys.up = false;
  planeKeys.down = false;
  planeKeys.left = false;
  planeKeys.right = false;
  joyVec = { x: 0, y: 0 };
  joyKnob.value = { x: 0, y: 0 };
  joyPointerId = null;
  floorSegments.forEach((segment) => {
    segment.visible = true;
  });
  if (starPoints) starPoints.visible = true;
  if (moonMesh) moonMesh.visible = true;
  if (skylineMesh) skylineMesh.visible = true;
  if (player) {
    player.rotation.x = 0;
  }
  envMode = 'night';
  applyEnvironmentNow();
};

const updateRunner = (delta) => {
  if (finalePhase.value === 'walk' || finalePhase.value === 'enter') {
    updateFinaleWalk(delta);
    return;
  }
  if (finalePhase.value === 'launch') {
    updateLaunch(delta);
    return;
  }
  if (finalePhase.value === 'plane') {
    updatePlane(delta);
    return;
  }

  const level = currentLevel.value;
  const driving = finalePhase.value === 'drive' || finalePhase.value === 'ramp';
  if (magnetTime.value > 0) {
    magnetTime.value = Math.max(0, magnetTime.value - delta);
  }
  if (multiTime.value > 0) {
    multiTime.value = Math.max(0, multiTime.value - delta);
  }
  const scoreMult = multiTime.value > 0 ? 2 : 1;

  if (driving) {
    if (finalePhase.value === 'ramp') {
      // Scripted approach: ease down to launch speed, ignore pedals.
      speed.value = THREE.MathUtils.damp(speed.value, 48, 1.1, delta);
      driveTargetSpeed = speed.value;
    } else {
      if (accelHeld) {
        driveTargetSpeed = Math.min(driveMaxSpeed, driveTargetSpeed + 26 * delta);
      }
      if (brakeHeld) {
        driveTargetSpeed = Math.max(0, driveTargetSpeed - 40 * delta);
      }
      speed.value = THREE.MathUtils.damp(speed.value, driveTargetSpeed, 2.2, delta);
    }
    updateEngineSound();

    if (!godModeActive.value) {
      if (speed.value >= godTriggerSpeed) {
        godHoldTimer += delta;
        if (godHoldTimer >= godHoldSeconds) {
          activateGodMode();
        }
      } else {
        godHoldTimer = 0;
      }
    } else {
      emitFireTrail();
      if (finalePhase.value !== 'ramp' && speed.value < godFloorSpeed) {
        godModeActive.value = false;
        godHoldTimer = 0;
      }
    }

    if (finalePhase.value === 'drive' && !rampTriggered && score.value >= RAMP_SCORE) {
      if (godModeActive.value) {
        triggerRamp();
      } else if (!rampHintShown) {
        // Make the requirement discoverable instead of silently failing.
        rampHintShown = true;
        driveHintText.value = 'The road ahead only breaks for gods — hold top speed!';
        driveHint.value = true;
        if (driveHintTimer) {
          clearTimeout(driveHintTimer);
        }
        driveHintTimer = setTimeout(() => {
          driveHint.value = false;
        }, 5200);
      }
    }
    // No points below cruising speed — crawling along earns nothing.
    if (speed.value >= driveScoreMinSpeed) {
      score.value += speed.value * delta * 2.4 * scoreMult;
    }
  } else if (finalePhase.value === 'approach') {
    if (plaza) {
      // Brake proportionally to the remaining distance so the plaza always
      // arrives instead of the world stalling short of it.
      const stopDistance = Math.max(0, -2 - plaza.position.z);
      const targetSpeed = Math.min(18, stopDistance * 0.9);
      speed.value = THREE.MathUtils.damp(speed.value, targetSpeed, 2.5, delta);
      if (plaza.position.z > -6 && speed.value < 2.5) {
        speed.value = 0;
        finalePhase.value = 'walk';
        clearObstacles();
        clearCoins();
        clearPowerups();
      }
    }
  } else {
    score.value += speed.value * delta * 2.4 * scoreMult;
    const stepIndex = Math.floor(score.value / level.stepDistance);
    const targetSpeed = level.baseSpeed + stepIndex * level.speedStep;
    speed.value = THREE.MathUtils.damp(speed.value, targetSpeed, 4, delta);
    if (!finaleTriggered && score.value >= FINALE_SCORE) {
      triggerFinale();
    }
  }

  const targetX = finalePhase.value === 'ramp' ? 0 : activeLanes()[currentLane];
  player.position.x = THREE.MathUtils.damp(player.position.x, targetX, driving ? 7 : 12, delta);

  const playerHeight = currentPlayerHeight();
  if (driving) {
    let carY = carPlayerSize.h / 2 + 0.02;
    let pitch = 0;
    if (finalePhase.value === 'ramp' && ramp) {
      // Ride up the ramp deck instead of popping into the air.
      const t = (9 + ramp.position.z) / 22;
      if (t > 0) {
        carY += Math.min(1, t) * 5.9;
        pitch = 0.27;
      }
    }
    player.position.y = THREE.MathUtils.damp(player.position.y, carY, 16, delta);
    player.rotation.x = THREE.MathUtils.damp(player.rotation.x, pitch, 8, delta);
    currentGroundCenter = player.position.y;
    playerVelocityY = 0;
    // Ease the car from the plaza back onto the running line.
    player.position.z = THREE.MathUtils.damp(player.position.z, 2, 0.9, delta);
    player.rotation.z = THREE.MathUtils.damp(
      player.rotation.z,
      (player.position.x - targetX) * 0.08,
      10,
      delta,
    );
  }

  const prevY = player.position.y;
  if (!driving) {
    playerVelocityY += gravity * delta;
    player.position.y += playerVelocityY * delta;
  }

  if (!driving) {
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

    animateRunner(delta);
  }

  const collisionPlayerHeight = currentPlayerHeight();

  const egoView = driving && driveCamera.value === 'ego';
  camera.fov = THREE.MathUtils.damp(
    camera.fov,
    (egoView ? 70 : 60) + Math.min(driving ? 20 : 14, Math.max(0, speed.value - 10) * 0.55),
    3,
    delta,
  );
  camera.updateProjectionMatrix();

  floorSegments.forEach((segment) => {
    segment.position.z += speed.value * delta;
    if (segment.position.z > 30) {
      if (finalePhase.value === 'ramp' && ramp && ramp.position.z > -160) {
        // The road ends at the ramp — stop recycling segments forward.
        segment.visible = false;
      } else {
        segment.position.z -= segmentLength * floorSegments.length;
      }
    }
  });

  if (plaza) {
    plaza.position.z += speed.value * delta;
    if (driving && plaza.position.z > 110) {
      disposePlaza();
    }
  }

  if (ramp && finalePhase.value === 'ramp') {
    ramp.position.z += speed.value * delta;
    updateOceanMotion(delta);
    // Launch right as the car leaves the top edge of the deck.
    if (ramp.position.z >= 12.5) {
      doLaunch();
    }
  }

  if (driving && carVisual) {
    const wheelSpin = (speed.value * delta) / 0.27;
    carWheels.forEach((wheel) => {
      wheel.rotation.x -= wheelSpin;
    });
  }

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

  if (finalePhase.value === 'drive') {
    driveSpawnTimer -= delta;
    if (driveSpawnTimer <= 0) {
      spawnDriveTraffic();
      driveSpawnTimer = Math.max(
        0.28,
        THREE.MathUtils.randFloat(0.7, 1.5) * (26 / Math.max(14, speed.value)),
      );
    }
  } else if (finalePhase.value === 'none') {
    updateZoneEvents(delta);
    // Normal rows pause while a traffic wave or coin-rush trail rolls
    // through, so their safe route is never blocked by a random spawn.
    if (!trafficWave && coinRushEndZ === null) {
      spawnTimer -= delta;
      if (spawnTimer <= 0) {
        spawnRow();
        const spacingFactor = 1 + Math.min(1.2, speed.value / 24);
        spawnTimer = THREE.MathUtils.randFloat(0.5, 1.5) * (14 / speed.value) * spacingFactor;
      }
    } else {
      spawnTimer = Math.max(spawnTimer, 1.1);
    }
  }

  for (let i = obstacles.length - 1; i >= 0; i -= 1) {
    const obstacle = obstacles[i];
    const vz = obstacle.userData.vz || 0;
    obstacle.position.z += (speed.value + vz) * delta;
    if (vz !== 0 && obstacle.userData.wheels) {
      const spin = (Math.abs(vz) * delta) / 0.27;
      obstacle.userData.wheels.forEach((wheel) => {
        wheel.rotation.x += spin;
      });
    }
    if (obstacle.userData.driftTo !== undefined && obstacle.position.z > obstacle.userData.driftAt) {
      if (!obstacle.userData.driftHonked) {
        obstacle.userData.driftHonked = true;
        sfx.horn();
      }
      obstacle.position.x = THREE.MathUtils.damp(
        obstacle.position.x,
        obstacle.userData.driftTo,
        3.5,
        delta,
      );
      if (Math.abs(obstacle.position.x - obstacle.userData.driftTo) < 0.05) {
        delete obstacle.userData.driftTo;
      }
    }
    if (vz > 0) {
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
    if (obstacle.position.z > 18 || obstacle.position.z < -330) {
      scene.remove(obstacle);
      const key = obstacle.userData?.poolKey;
      if (key) {
        (obstaclePools[key] ||= []).push(obstacle);
      }
      obstacles.splice(i, 1);
      continue;
    }
    if (checkCollision(obstacle, collisionPlayerHeight)) {
      if (godModeActive.value) {
        smashObstacle(obstacle, i);
        continue;
      }
      const now = performance.now();
      if (now < invulnUntil || now < bumpProtectUntil) {
        continue;
      }
      // Side bump: the player is still travelling sideways into the target
      // lane and the obstacle sits in that lane — a frontal hit stays fatal.
      const laneX = activeLanes()[currentLane];
      const sideBump =
        Math.abs(player.position.x - laneX) > 0.35 &&
        Math.abs(obstacle.position.x - laneX) < 1.0 &&
        currentLane !== laneOrigin;
      if (sideBump) {
        handleSideBump(obstacle);
        if (state.value !== 'running') {
          break;
        }
        continue;
      }
      // Zone 2: rear-ending a low car ahead is survivable — it honks and
      // brakes, and we shed speed instead of crashing. Trucks and oncoming
      // traffic stay fatal.
      if (
        driving &&
        obstacle.userData.vz < 0 &&
        obstacle.userData.type === 'low' &&
        obstacle.position.z < player.position.z
      ) {
        const carSpeed = -obstacle.userData.vz;
        const brakedSpeed = carSpeed * 0.75;
        obstacle.userData.vz = -brakedSpeed;
        speed.value = Math.min(speed.value, brakedSpeed * 0.9);
        driveTargetSpeed = Math.min(driveTargetSpeed, brakedSpeed * 0.9);
        bumpProtectUntil = now + 900;
        bumpShakeTimer = 0.25;
        sfx.horn();
        spawnBurst(
          new THREE.Vector3(
            obstacle.position.x,
            0.8,
            obstacle.position.z + obstacle.userData.size.d / 2,
          ),
          ['dust'],
          6,
          3,
        );
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

  updateFlyingCars(delta);

  if (egoView) {
    // Hood cam: sit at the top of the windshield so the bonnet stays in
    // frame at the bottom while the car itself remains visible.
    camera.position.x = THREE.MathUtils.damp(camera.position.x, player.position.x, 12, delta);
    camera.position.y = THREE.MathUtils.damp(camera.position.y, 1.24, 10, delta);
    camera.position.z = THREE.MathUtils.damp(
      camera.position.z,
      player.position.z + 0.25,
      12,
      delta,
    );
    lookAtTarget.set(camera.position.x + player.rotation.z * 5, 0.68, player.position.z - 26);
    camera.lookAt(lookAtTarget);
    camera.rotation.z += player.rotation.z * 0.5;
  } else {
    if (driving) {
      // Recover smoothly after switching back from the hood cam.
      camera.position.y = THREE.MathUtils.damp(
        camera.position.y,
        cameraBase.y * cameraZoom.value,
        6,
        delta,
      );
      camera.position.z = THREE.MathUtils.damp(
        camera.position.z,
        cameraBase.z * cameraZoom.value,
        6,
        delta,
      );
    }
    camera.position.x = THREE.MathUtils.damp(camera.position.x, player.position.x * 0.35, 4, delta);
    lookAtTarget.set(player.position.x * 0.4, 1.2, -15);
    camera.lookAt(lookAtTarget);
  }
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
    if (bumpShakeTimer > 0) {
      bumpShakeTimer -= delta;
      camera.position.x += (Math.random() - 0.5) * 0.14 * Math.max(0, bumpShakeTimer);
    }
  } else if (state.value !== 'crashing' && state.value !== 'paused') {
    animateIdle(delta);
    const damp = (current, target) => THREE.MathUtils.damp(current, target, 4, delta);
    if (state.value === 'menu' && menuScreen.value === 'level') {
      // Skin preview: swing the camera in front of the runner and spin them.
      // The camera faces +z here, so screen-right is world -x: aiming left of
      // the player pushes them to the right half of the screen. On portrait
      // phones the menu card fills the width, so frame the runner below it
      // instead by aiming above their head.
      const portrait = camera.aspect < 1;
      camera.position.x = damp(camera.position.x, portrait ? 0 : -1.4);
      camera.position.y = damp(camera.position.y, portrait ? 1.7 : 1.5);
      camera.position.z = damp(camera.position.z, portrait ? -2.4 : -1.4);
      lookAtTarget.set(portrait ? 0 : 1.05, portrait ? 1.95 : 0.95, 2);
      camera.lookAt(lookAtTarget);
      if (player) {
        player.rotation.y += delta * 0.7;
      }
    } else {
      camera.position.x = damp(camera.position.x, 0);
      camera.position.y = damp(camera.position.y, cameraBase.y * cameraZoom.value);
      camera.position.z = damp(camera.position.z, cameraBase.z * cameraZoom.value);
      lookAtTarget.set(0, 1.2, -12);
      camera.lookAt(lookAtTarget);
      if (player) {
        player.rotation.y = THREE.MathUtils.damp(
          player.rotation.y % (Math.PI * 2),
          0,
          6,
          delta,
        );
      }
    }
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

  if (Math.abs(musicDuckCurrent - musicDuckTarget) > 0.005) {
    musicDuckCurrent = THREE.MathUtils.damp(musicDuckCurrent, musicDuckTarget, 1.2, delta);
    applyAudioVolume();
  }

  lerpEnvironment(delta);

  if (
    engineOsc &&
    (state.value !== 'running' ||
      !['drive', 'ramp', 'launch', 'plane'].includes(finalePhase.value))
  ) {
    silenceEngineSound();
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
  applyCharacter();
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

watch(menuScreen, (screen) => {
  if (screen !== 'level') {
    // Leaving the skins screen: drop any preview back to the owned skin.
    applyCharacter();
  }
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
  loadLevelPref();
  loadHandednessPref();
  initAudio();
  pointerUnlockHandler = () => unlockAudio();
  window.addEventListener('pointerdown', pointerUnlockHandler, { once: true });
  document.addEventListener('visibilitychange', handleVisibility);
  initScene();
  loadVehicleModels();
  loadCharacterModels();
  loadPlaneModel();
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
  [nearMissTimer, bumpToastTimer, finaleToastTimer, driveHintTimer].forEach((timer) => {
    if (timer) {
      clearTimeout(timer);
    }
  });
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
  window.removeEventListener('keyup', handleKeyup);
  window.removeEventListener('touchstart', handleTouchStart);
  disposePlaza();
  disposeZone3();
  if (cloudAssets) {
    cloudAssets.geo.dispose();
    cloudAssets.mat.dispose();
  }
  if (enemyAssets) {
    Object.values(enemyAssets).forEach((resource) => resource?.dispose?.());
  }
  if (terrainAssets) {
    Object.values(terrainAssets).forEach((resource) => {
      if (Array.isArray(resource)) {
        resource.forEach((item) => item?.dispose?.());
      } else {
        resource?.dispose?.();
      }
    });
  }
  if (motherTemplate) {
    motherTemplate.traverse((node) => {
      if (node.isMesh) {
        node.geometry?.dispose();
        const materials = Array.isArray(node.material) ? node.material : [node.material];
        materials.forEach((material) => material?.dispose());
      }
    });
  }
  if (planeTemplate) {
    planeTemplate.model.traverse((node) => {
      if (node.isMesh) {
        node.geometry?.dispose();
        const materials = Array.isArray(node.material) ? node.material : [node.material];
        materials.forEach((material) => {
          material?.map?.dispose();
          material?.dispose();
        });
      }
    });
  }
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
  Object.values(characterTemplates).forEach(({ scene: characterScene }) => {
    characterScene.traverse((node) => {
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
  skylineTexture?.dispose();
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

.hud-value.low {
  color: #ff6b6e;
}

.dev-badge {
  position: absolute;
  bottom: calc(16px + env(safe-area-inset-bottom));
  left: calc(20px + env(safe-area-inset-left));
  z-index: 3;
  pointer-events: none;
  padding: 5px 12px;
  border-radius: 999px;
  border: 1px solid rgba(255, 170, 60, 0.55);
  background: rgba(30, 18, 6, 0.75);
  color: #ffbe5c;
  font-size: 0.68rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
}

.finale-toast {
  position: absolute;
  top: 26%;
  left: 50%;
  transform: translateX(-50%);
  z-index: 3;
  pointer-events: none;
  text-align: center;
  animation: fadeIn 1.2s ease;
}

.finale-toast-title {
  font-family: 'Bebas Neue', 'Oswald', 'Segoe UI', sans-serif;
  font-size: 2.6rem;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  color: #7dfce0;
  text-shadow: 0 0 26px rgba(60, 255, 200, 0.6);
}

.finale-toast-sub {
  margin-top: 6px;
  font-size: 0.8rem;
  letter-spacing: 0.3em;
  text-transform: uppercase;
  color: rgba(210, 240, 255, 0.75);
}

.event-toast {
  top: 19%;
  animation: fadeIn 0.35s ease;
}

.event-toast .finale-toast-title {
  font-size: 1.7rem;
  color: #ffcf6b;
  text-shadow: 0 0 22px rgba(255, 190, 80, 0.55);
}

.event-toast .finale-toast-sub {
  font-size: 0.7rem;
  color: rgba(255, 230, 190, 0.8);
}

.speed-goal {
  position: absolute;
  top: calc(76px + env(safe-area-inset-top));
  left: 50%;
  transform: translateX(-50%);
  z-index: 3;
  pointer-events: none;
  text-align: center;
  padding: 7px 20px 9px;
  border-radius: 14px;
  background: rgba(22, 10, 6, 0.55);
  border: 1px solid rgba(255, 160, 70, 0.55);
  animation: speedGoalPulse 0.85s ease-in-out infinite;
}

.speed-goal-title {
  font-family: 'Bebas Neue', 'Oswald', 'Segoe UI', sans-serif;
  font-size: 1.35rem;
  letter-spacing: 0.26em;
  text-transform: uppercase;
  color: #ffb056;
  text-shadow: 0 0 18px rgba(255, 165, 70, 0.6);
}

.speed-goal-value {
  margin-top: 2px;
  font-size: 0.85rem;
  letter-spacing: 0.2em;
  font-variant-numeric: tabular-nums;
  color: rgba(255, 228, 195, 0.9);
}

.speed-goal.ok {
  border-color: rgba(90, 255, 170, 0.6);
  background: rgba(6, 22, 14, 0.55);
  animation: none;
}

.speed-goal.ok .speed-goal-title {
  color: #5cffa8;
  text-shadow: 0 0 18px rgba(80, 255, 170, 0.6);
}

@keyframes speedGoalPulse {
  0%,
  100% {
    transform: translateX(-50%) scale(1);
  }
  50% {
    transform: translateX(-50%) scale(1.07);
  }
}

.drive-hint {
  position: absolute;
  bottom: calc(48px + env(safe-area-inset-bottom));
  left: 50%;
  transform: translateX(-50%);
  z-index: 3;
  pointer-events: none;
  padding: 10px 18px;
  border-radius: 999px;
  background: rgba(8, 12, 22, 0.78);
  border: 1px solid rgba(90, 140, 255, 0.4);
  font-size: 0.78rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgba(220, 235, 255, 0.9);
  white-space: nowrap;
  animation: fadeIn 0.5s ease;
}

.bump-warning {
  position: absolute;
  top: 40%;
  left: 50%;
  z-index: 3;
  pointer-events: none;
  color: #ffcf3d;
  font-family: 'Bebas Neue', 'Oswald', 'Segoe UI', sans-serif;
  font-size: 1.8rem;
  letter-spacing: 0.18em;
  text-transform: uppercase;
  text-shadow: 0 0 20px rgba(255, 200, 40, 0.7);
  animation: bumpShake 1.3s ease-out forwards;
}

@keyframes bumpShake {
  0% {
    transform: translate(-50%, 0) rotate(0deg) scale(0.8);
    opacity: 0;
  }
  10% {
    transform: translate(calc(-50% - 8px), 0) rotate(-3deg) scale(1.1);
    opacity: 1;
  }
  20% {
    transform: translate(calc(-50% + 8px), 0) rotate(3deg) scale(1.05);
  }
  30% {
    transform: translate(calc(-50% - 5px), 0) rotate(-2deg) scale(1);
  }
  40% {
    transform: translate(calc(-50% + 5px), 0) rotate(1deg) scale(1);
  }
  75% {
    transform: translate(-50%, 0) rotate(0deg) scale(1);
    opacity: 1;
  }
  100% {
    transform: translate(-50%, -18px) scale(0.95);
    opacity: 0;
  }
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

.power-chip.god {
  color: #ffb347;
  border-color: rgba(255, 140, 40, 0.8);
  font-weight: 700;
  animation: godPulse 0.7s ease-in-out infinite alternate;
}

@keyframes godPulse {
  from {
    box-shadow: 0 0 6px rgba(255, 130, 30, 0.4);
    transform: scale(1);
  }
  to {
    box-shadow: 0 0 22px rgba(255, 150, 50, 0.85);
    transform: scale(1.06);
  }
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

.skin-preview-hint {
  margin-top: 10px;
  font-size: 0.7rem;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  color: rgba(190, 210, 255, 0.55);
}

.hp-bar {
  position: absolute;
  top: calc(96px + env(safe-area-inset-top));
  left: 50%;
  transform: translateX(-50%);
  width: min(300px, 60vw);
  height: 12px;
  border-radius: 999px;
  border: 1px solid rgba(120, 180, 255, 0.5);
  background: rgba(8, 12, 22, 0.7);
  overflow: hidden;
  z-index: 3;
  pointer-events: none;
}

.hp-fill {
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #39f9c0, #6cd85a);
  transition: width 0.25s ease;
}

.hp-fill.hurt {
  background: linear-gradient(90deg, #ff8a5a, #ff4d5e);
}

.damage-flash {
  position: absolute;
  inset: 0;
  z-index: 3;
  pointer-events: none;
  background: radial-gradient(ellipse at center, transparent 42%, rgba(255, 45, 65, 0.4) 100%);
  animation: damageFlash 0.45s ease-out forwards;
}

@keyframes damageFlash {
  0% {
    opacity: 0;
  }
  20% {
    opacity: 1;
  }
  100% {
    opacity: 0;
  }
}

.pedals {
  position: absolute;
  bottom: calc(30px + env(safe-area-inset-bottom));
  display: grid;
  gap: 14px;
  z-index: 5;
}

.pedals-left {
  left: calc(22px + env(safe-area-inset-left));
}

.pedals-right {
  right: calc(22px + env(safe-area-inset-right));
}

.pedal {
  width: 84px;
  height: 84px;
  border-radius: 50%;
  border: 2px solid;
  font-size: 0.78rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  cursor: pointer;
  touch-action: none;
  user-select: none;
  -webkit-user-select: none;
}

.pedal.gas {
  background: rgba(30, 90, 55, 0.75);
  border-color: rgba(80, 240, 160, 0.7);
  color: #7dffb0;
}

.pedal.brake {
  background: rgba(90, 25, 35, 0.75);
  border-color: rgba(255, 90, 110, 0.7);
  color: #ff9dab;
}

.pedal:active {
  transform: scale(0.93);
  filter: brightness(1.4);
}

.hand-choice {
  display: flex;
  gap: 12px;
  justify-content: center;
  margin-top: 16px;
  flex-wrap: wrap;
}

.joystick {
  position: absolute;
  bottom: calc(34px + env(safe-area-inset-bottom));
  left: 50%;
  transform: translateX(-50%);
  width: 128px;
  height: 128px;
  border-radius: 50%;
  border: 1px solid rgba(120, 180, 255, 0.45);
  background: rgba(8, 12, 22, 0.45);
  z-index: 5;
  touch-action: none;
  display: flex;
  align-items: center;
  justify-content: center;
}

.joystick-knob {
  width: 46px;
  height: 46px;
  border-radius: 50%;
  background: rgba(120, 180, 255, 0.55);
  border: 1px solid rgba(190, 225, 255, 0.7);
  pointer-events: none;
}

.cam-btn {
  position: absolute;
  top: calc(148px + env(safe-area-inset-top));
  right: calc(24px + env(safe-area-inset-right));
  z-index: 4;
  width: 42px;
  height: 42px;
  border-radius: 12px;
  border: 1px solid rgba(90, 140, 255, 0.4);
  background: rgba(8, 12, 22, 0.7);
  color: #cfe0ff;
  font-size: 0.62rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
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

.difficulty-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 2px 0 6px;
}

.difficulty-label {
  font-size: 0.68rem;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: rgba(170, 200, 255, 0.7);
}

.difficulty-toggle {
  display: inline-flex;
  border: 1px solid rgba(110, 165, 255, 0.4);
  border-radius: 999px;
  padding: 3px;
  gap: 3px;
  background: rgba(8, 12, 22, 0.6);
}

.difficulty-toggle button {
  border: none;
  border-radius: 999px;
  padding: 7px 16px;
  font-size: 0.72rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  background: transparent;
  color: rgba(210, 225, 255, 0.75);
  cursor: pointer;
  transition: background 0.2s ease, color 0.2s ease;
}

.difficulty-toggle button.active {
  background: linear-gradient(120deg, #39f9c0, #25a6ff);
  color: #05070f;
  font-weight: 700;
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
