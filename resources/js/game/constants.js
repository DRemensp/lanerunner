// Gameplay tuning constants shared across the game modules. Values here must
// stay in sync with the server-side anti-cheat bounds in RunnerController.

export const cameraBase = {
  y: 5.5,
  z: 8,
};

// Speed jumps once per 2,500-point checkpoint (5x the old per-500 step), so
// every checkpoint is a distinct tempo change.
export const levelOptions = [
  // Top speed = base + 3·step (tier cap 3): Rush 42, Night 56. Night had
  // always been 74 (step 20) — lowered to ~55 at Daniel's request.
  // MUST stay in sync with LEVELS in RunnerController (anti-cheat)!
  { id: 'rush', label: 'City Rush', baseSpeed: 12, stepDistance: 2500, speedStep: 10 },
  { id: 'night', label: 'Night Run', baseSpeed: 14, stepDistance: 2500, speedStep: 14 },
];

export const lanes = [-2, 0, 2];
export const groundY = 0.7;
export const gravity = -28;
// 11.4 instead of 12: apex ~2.32 m (feet above ground) — comfortably over
// barriers and onto every vehicle roof (cars 1.17–1.47, box truck ~1.5,
// ambulance ~1.94), but together with the step-up limit (1.1, collision
// loop) never enough to climb the tall static obstacles (2.75+) from the
// ground.
export const jumpVelocity = 11.4;
export const slideScale = 0.55;
export const slideDuration = 0.6;
export const dropBoost = 1.6;
export const swipeThreshold = 40;
// Sideways swiping (lane change) is the most common action and fires earlier
// than vertical — otherwise it feels more sluggish than jump/slide.
export const swipeThresholdX = 26;
export const segmentLength = 20;
export const playerSize = { w: 0.9, h: 1.4, d: 0.8 };
export const coyoteTimeMs = 50;
export const groundedEpsilon = 0.06;

// How long a side bump keeps protecting the player from another bump.
export const bumpWindowMs = 5000;

// Secret finale: at FINALE_SCORE the district ends in an open plaza, the
// runner boards a car and zone 2 begins — a four-lane road (two lanes each
// direction) where the only controls are steering, gas, and brake.
export const FINALE_SCORE = 10000;
export const carLanes = [-3, -1, 1, 3];
export const carPlayerSize = { w: 1.5, h: 1.1, d: 2.6 };
export const driveScoreMinSpeed = 15;
// Zone 2 hard limit: 80 km/h, always. Nothing in stage 2 goes faster.
export const driveMaxSpeed = 80;
// Hold near top speed for a moment and the car goes god mode: it smashes
// straight through traffic (cars go flying) and drags a fire trail.
// God mode: hold a medium-high speed for a sustained stretch (not a short
// top-speed burst). Dropping below the trigger drains progress at 1.5x
// instead of resetting — challenging, but never cheap.
export const godTriggerSpeed = 80;
export const godHoldSeconds = 10;
export const godFloorSpeed = 72;

// Zone 3: at RAMP_SCORE while in god mode, the road ends in a jump ramp into
// the sunrise over water; mid-air the car slams into a plane's cargo hold and
// the player flies on — free movement, no lanes, bright daylight.
export const RAMP_SCORE = 20000;

// Environment palettes per phase. envSettings.night is mutated at runtime by
// applyDistrict() as the run travels through city districts.
// edge = color of the glowing road edges (neon lines left/right).
export const envSettings = {
  night: {
    bg: 0x05070f, fog: 0x05070f, fogNear: 48, fogFar: 190,
    hemi: 1.6, dir: 1.7, hemiSky: 0x9fc1ff, dirColor: 0xffffff, edge: 0x2ee5ff,
  },
  sunrise: {
    bg: 0x2e1f45, fog: 0xb06a4a, fogNear: 60, fogFar: 320,
    hemi: 1.9, dir: 2.3, hemiSky: 0xffc49a, dirColor: 0xffd9a8, edge: 0xffb07a,
  },
  day: {
    bg: 0x8ecdf0, fog: 0xaee0f8, fogNear: 90, fogFar: 460,
    hemi: 2.6, dir: 3.0, hemiSky: 0xeaf6ff, dirColor: 0xfff4e0, edge: 0xeaf6ff,
  },
  // Zone 4: deep space beyond the wormhole — near-black sky, thin violet fog
  // so the asteroid field reads against the starfield.
  void: {
    bg: 0x02010a, fog: 0x060218, fogNear: 70, fogFar: 380,
    hemi: 1.2, dir: 1.3, hemiSky: 0xa88fff, dirColor: 0xd8c8ff, edge: 0xa88fff,
  },
};

// Zone-1 districts: each milestone nudges the night palette, so a long run
// visibly travels through different parts of the city. lerpEnvironment
// blends the change in smoothly.
export const nightDistricts = [
  { bg: 0x05070f, fog: 0x05070f, hemiSky: 0x9fc1ff, edge: 0x2ee5ff },
  { bg: 0x0a0618, fog: 0x120a26, hemiSky: 0xbfa8ff, edge: 0xbf7bff },
  { bg: 0x04120f, fog: 0x07211c, hemiSky: 0x8fffd9, edge: 0x4affc2 },
  { bg: 0x120609, fog: 0x220b12, hemiSky: 0xff9fb0, edge: 0xff6b8a },
];

// No single-obstacle patterns: a lone barrel on a three-lane road is dead
// air. Every row carries at least two obstacles; the only thing allowed to
// stand alone is a themed set row (see spawnRow), which fills 6–9 m of road
// by itself.
export const rowPatterns = [
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
