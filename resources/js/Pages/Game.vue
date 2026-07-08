<template>
  <div class="runner-page">
    <div
      ref="canvasWrap"
      class="runner-canvas"
      @pointerdown="galleryCamStart"
      @pointermove="galleryCamMove"
      @pointerup="galleryCamEnd"
      @pointercancel="galleryCamEnd"
    ></div>

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
      <div class="hud-side">
        <div class="hud-pill hud-coins">
          <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3.2" fill="currentColor"/></svg>
          <span>{{ runCoins }}</span>
        </div>
      </div>
      <div class="hud-score">
        <div class="hud-score-label">Score</div>
        <div class="hud-score-value">{{ Math.floor(score) }}</div>
      </div>
      <div class="hud-side hud-side-right">
        <div
          class="hud-pill hud-speed"
          :class="{ low: finalePhase === 'drive' && speed < 15 }"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4.5 18a9 9 0 1115 0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/><path d="M12 14l3.5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>
          <span>{{ speed.toFixed(0) }}</span>
        </div>
        <button
          v-if="state === 'running'"
          class="pause-btn"
          @click="pauseRun"
          type="button"
          aria-label="Pause"
        >
          <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="7" y="5.5" width="3.4" height="13" rx="1" fill="currentColor"/><rect x="13.6" y="5.5" width="3.4" height="13" rx="1" fill="currentColor"/></svg>
        </button>
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
      <div v-if="godHoldProgress > 0" class="speed-goal-track">
        <div
          class="speed-goal-fill"
          :style="{ width: Math.min(100, (godHoldProgress / godHoldSeconds) * 100) + '%' }"
        ></div>
      </div>
      <div v-if="godHoldProgress > 0" class="speed-goal-value">
        {{ godHoldProgress.toFixed(1) }}s / {{ godHoldSeconds }}s
      </div>
    </div>

    <div v-if="driveHint && state === 'running'" class="drive-hint">
      {{ driveHintText }}
    </div>

    <div
      v-if="showTutorial && state === 'running' && finalePhase === 'none'"
      class="drive-hint tutorial-hint"
    >
      <template v-if="isTouchDevice">
        Swipe &#9664;&#9654; to dodge &middot; &#9650; jump &middot; &#9660; slide
      </template>
      <template v-else>A / D &mdash; move &middot; W &mdash; jump &middot; S &mdash; slide</template>
    </div>

    <div
      v-if="
        (finalePhase === 'plane' || finalePhase === 'void') &&
        (state === 'running' || state === 'paused')
      "
      class="hp-bar"
    >
      <div
        class="hp-fill"
        :class="{ hurt: playerHp < 35 }"
        :style="{ width: playerHp + '%' }"
      ></div>
    </div>

    <div v-if="damageFlash && state === 'running'" class="damage-flash"></div>

    <div v-if="whiteFlash > 0" class="void-flash" :style="{ opacity: whiteFlash }"></div>

    <button
      v-if="
        state === 'running' &&
        (finalePhase === 'none' || finalePhase === 'drive')
      "
      class="cheat-skip"
      type="button"
      @pointerdown.prevent.stop="devSkip"
    >
      Skip &raquo;
    </button>

    <div
      v-if="state === 'running' && (finalePhase === 'plane' || finalePhase === 'void')"
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
      v-if="state === 'gallery'"
      class="joystick joystick--right"
      @pointerdown="galleryJoyStart"
      @pointermove="galleryJoyMove"
      @pointerup="galleryJoyEnd"
      @pointercancel="galleryJoyEnd"
    >
      <div
        class="joystick-knob"
        :style="{ transform: `translate(${galleryJoyKnob.x}px, ${galleryJoyKnob.y}px)` }"
      ></div>
    </div>

    <button
      v-if="state === 'gallery'"
      class="jump-btn"
      type="button"
      @pointerdown.prevent="galleryJump"
    >
      Jump
    </button>

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
      Near Miss<template v-if="nearMissCombo > 1"> x{{ nearMissCombo }}</template>
      +{{ nearMissAmount }}
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
        <div class="menu-hero">
          <div class="menu-eyebrow">Neon Night City</div>
          <h1 class="menu-logo">
            <span class="menu-logo-top">Lane</span>
            <span class="menu-logo-main">Runner</span>
          </h1>
          <div v-if="bestScore > 0" class="menu-best">Best {{ Math.floor(bestScore).toLocaleString() }}</div>
        </div>

        <div class="menu-center">
          <button class="play-btn" @click="startRun" type="button">Play</button>
          <div class="difficulty-row">
            <span class="difficulty-label">Mode</span>
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
        </div>

        <div class="menu-bottom">
          <nav class="menu-tiles">
            <button class="menu-tile" @click="openMenuScreen('level')" type="button">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M8.5 3.5L4 6.2l1.7 3.5L8 8.6V20h8V8.6l2.3 1.1L20 6.2l-4.5-2.7a3.5 3.5 0 01-7 0z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
              <span>Skins</span>
            </button>
            <button class="menu-tile" @click="openMenuScreen('inventory')" type="button">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 3l8 4.5v9L12 21l-8-4.5v-9L12 3zM12 12l8-4.5M12 12L4 7.5M12 12v9" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/></svg>
              <span>Items</span>
            </button>
            <button class="menu-tile" @click="openMenuScreen('missions')" type="button">
              <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="12" cy="12" r="1.3" fill="currentColor"/></svg>
              <span>Missions</span>
              <span v-if="completedMissionCount" class="tile-badge">{{ completedMissionCount }}</span>
            </button>
            <button class="menu-tile" @click="openMenuScreen('leaderboard')" type="button">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 4h10v5a5 5 0 01-10 0zM7 5H4.4a2.9 2.9 0 003 3.5M17 5h2.6a2.9 2.9 0 01-3 3.5M12 14v4M8 20.5h8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
              <span>Ranks</span>
            </button>
            <button class="menu-tile" @click="openMenuScreen('settings')" type="button">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h8M17.5 7H20M4 17h2.5M12 17h8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/><circle cx="14.5" cy="7" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/><circle cx="9" cy="17" r="2.2" fill="none" stroke="currentColor" stroke-width="1.8"/></svg>
              <span>Settings</span>
            </button>
            <button class="menu-tile" @click="startGallery" type="button">
              <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3.5" y="4.5" width="17" height="15" rx="1.6" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M3.5 15.5l4.6-4.6 3 3 4-4.6 5.4 6.2" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/><circle cx="8.2" cy="8.6" r="1.4" fill="currentColor"/></svg>
              <span>Gallery</span>
            </button>
          </nav>

          <div class="menu-foot">
            <div class="menu-foot-links">
              <template v-if="authUser">
                <div class="coin-chip">
                  <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="2"/><circle cx="12" cy="12" r="3.2" fill="currentColor"/></svg>
                  <span>{{ totalCoins }}</span>
                </div>
                <Link class="ghost-btn small" href="/profile">Account</Link>
                <Link class="ghost-btn small danger" href="/logout" method="post" as="button">Log out</Link>
              </template>
              <template v-else>
                <Link class="ghost-btn small" href="/login">Log in</Link>
                <Link class="primary-btn small" href="/register">Register</Link>
              </template>
            </div>
            <div class="menu-controls">
              <template v-if="isTouchDevice">Swipe to steer &middot; up to jump &middot; down to slide</template>
              <template v-else>A / D move &middot; W jump &middot; S slide &middot; Esc pause</template>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="menu-screen" :class="{ 'skin-screen': menuScreen === 'level' }">
          <div class="menu-screen-head">
            <button class="back-btn" @click="backToMainMenu" type="button" aria-label="Back">
              <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M14.5 5.5L8 12l6.5 6.5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>
            </button>
            <div class="menu-screen-title">
              {{
                menuScreen === 'level'
                  ? 'Skins'
                  : menuScreen === 'inventory'
                    ? 'Inventory'
                    : menuScreen === 'missions'
                      ? 'Daily Missions'
                      : menuScreen === 'leaderboard'
                        ? 'Leaderboard'
                        : 'Settings'
              }}
            </div>
          </div>
          <div v-if="menuScreen === 'level'" class="skin-dock">
            <div class="skin-tabs">
              <button
                class="skin-tab"
                :class="{ active: skinTab === 'runner' }"
                @click="setSkinTab('runner')"
                type="button"
              >
                Runner
              </button>
              <button
                class="skin-tab"
                :class="{ active: skinTab === 'car' }"
                @click="setSkinTab('car')"
                type="button"
              >
                {{ zone2Seen ? 'Car' : '???' }}
              </button>
              <button
                class="skin-tab"
                :class="{ active: skinTab === 'plane' }"
                @click="setSkinTab('plane')"
                type="button"
              >
                {{ zone3Seen ? 'Aircraft' : '???' }}
              </button>
              <div v-if="authUser" class="shop-balance">{{ totalCoins }}c</div>
            </div>
            <div v-if="skinTab === 'car' && !zone2Seen" class="zone-locked">
              ??? &mdash; Find the end of the line first.
            </div>
            <div v-else-if="skinTab === 'plane' && !zone3Seen" class="zone-locked">
              ??? &mdash; Only gods take to the sky.
            </div>
            <div v-else class="skin-strip" data-allow-scroll>
              <button
                v-for="skin in activeTabSkins"
                :key="skin.id"
                class="skin-chip"
                :class="{
                  active: isSkinActive(skin),
                  locked: !canUseSkin(skin),
                  previewing: previewSkin && previewSkin.id === skin.id,
                }"
                :style="{ '--skin': skin.color }"
                @click="tapSkin(skin)"
                type="button"
              >
                {{ skin.label }}
                <span v-if="!canUseSkin(skin)" class="skin-price">{{ skin.price }}c</span>
              </button>
            </div>
            <div class="skin-info">
              <template v-if="previewSkin">
                <span class="skin-info-name">{{ previewSkin.label }}</span>
                <button
                  v-if="!canUseSkin(previewSkin)"
                  class="buy-btn"
                  @click="buyPreviewedSkin"
                  type="button"
                >
                  Buy &mdash; {{ previewSkin.price }}c
                </button>
                <span v-else-if="isSkinActive(previewSkin)" class="skin-info-note">Selected</span>
              </template>
              <span v-if="shopMessage" class="shop-message">{{ shopMessage }}</span>
            </div>
          </div>

          <div v-else class="menu-screen-card">
          <template v-if="menuScreen === 'inventory'">
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

            <template v-else-if="menuScreen === 'missions'">
              <div class="mission-panel">
                <div class="mission-sub">
                  Three fresh missions every day. Rewards are paid in coins.
                </div>
                <div
                  v-for="(mission, index) in dailyMissions"
                  :key="mission.key"
                  class="mission-row"
                  :class="{ done: missionProgress(mission) >= mission.target }"
                >
                  <div class="mission-info">
                    <div class="mission-label">{{ mission.label }}</div>
                    <div class="mission-progress-track">
                      <div
                        class="mission-progress-fill"
                        :style="{
                          width:
                            Math.min(100, (missionProgress(mission) / mission.target) * 100) + '%',
                        }"
                      ></div>
                    </div>
                    <div class="mission-progress-text">
                      {{ Math.min(missionProgress(mission), mission.target) }} /
                      {{ mission.target }}
                    </div>
                  </div>
                  <button
                    v-if="claimedMissions.includes(index)"
                    class="mission-claim claimed"
                    disabled
                    type="button"
                  >
                    Claimed
                  </button>
                  <button
                    v-else
                    class="mission-claim"
                    :disabled="missionProgress(mission) < mission.target"
                    @click="claimMission(index)"
                    type="button"
                  >
                    +{{ missionRewards[index] }}c
                  </button>
                </div>
                <div v-if="isGuest" class="mission-guest-note">
                  Log in to claim mission rewards.
                </div>
                <div v-if="shopMessage" class="shop-message">{{ shopMessage }}</div>
              </div>
            </template>

            <template v-else-if="menuScreen === 'leaderboard'">
              <div class="leaderboard-panel">
                <div class="stats-grid">
                  <div class="leaderboard-stat">
                    <div class="leaderboard-label">Best Score</div>
                    <div class="leaderboard-value">{{ Math.floor(bestScore) }}</div>
                  </div>
                  <div class="leaderboard-stat">
                    <div class="leaderboard-label">Top Speed</div>
                    <div class="leaderboard-value">{{ Math.round(statBestSpeed) }}</div>
                  </div>
                  <div class="leaderboard-stat">
                    <div class="leaderboard-label">Runs</div>
                    <div class="leaderboard-value">{{ statTotalRuns }}</div>
                  </div>
                  <div v-if="authUser" class="leaderboard-stat">
                    <div class="leaderboard-label">Coins</div>
                    <div class="leaderboard-value">{{ totalCoins }}</div>
                  </div>
                </div>
                <div v-if="yourRank" class="your-rank">
                  Your global rank: <strong>#{{ yourRank }}</strong>
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
                <label>Vibration (Mobile)</label>
                <div class="difficulty-toggle">
                  <button
                    :class="{ active: vibrationEnabled }"
                    @click="setVibration(true)"
                    type="button"
                  >
                    On
                  </button>
                  <button
                    :class="{ active: !vibrationEnabled }"
                    @click="setVibration(false)"
                    type="button"
                  >
                    Off
                  </button>
                </div>
              </div>
              <div class="menu-field">
                <label>Render Quality</label>
                <div class="difficulty-toggle">
                  <button
                    :class="{ active: renderQuality === 'full' }"
                    @click="setRenderQuality('full')"
                    type="button"
                  >
                    Full
                  </button>
                  <button
                    :class="{ active: renderQuality === 'battery' }"
                    @click="setRenderQuality('battery')"
                    type="button"
                  >
                    Battery Saver
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
        <div class="death-hero">
          <div class="death-hero-label">Score</div>
          <div class="death-hero-value">{{ Math.floor(score).toLocaleString() }}</div>
        </div>
        <div class="death-stats">
          <div class="death-stat"><span>Best</span><strong>{{ Math.floor(bestScore).toLocaleString() }}</strong></div>
          <div class="death-stat gold"><span>Coins</span><strong>+{{ runCoins }}</strong></div>
          <div class="death-stat"><span>Top speed</span><strong>{{ Math.round(runTopSpeed) }}</strong></div>
          <div class="death-stat"><span>Near misses</span><strong>{{ runNearMisses }}</strong></div>
        </div>
        <div v-if="runSaveNoticeText" class="death-notice">{{ runSaveNoticeText }}</div>
        <Link v-if="!authUser" class="death-guest-hint" href="/login">
          Playing as guest — sign in to keep coins &amp; climb the leaderboard
        </Link>
        <div class="death-missions">
          <div
            v-for="mission in dailyMissions"
            :key="mission.key"
            class="death-mission"
            :class="{ done: missionProgress(mission) >= mission.target }"
          >
            <div class="death-mission-top">
              <span class="death-mission-label">{{ mission.label }}</span>
              <span class="death-mission-count">
                {{ Math.min(missionProgress(mission), mission.target) }}/{{ mission.target }}
              </span>
            </div>
            <div class="death-mission-bar">
              <div
                class="death-mission-fill"
                :style="{ width: `${Math.min(100, (missionProgress(mission) / mission.target) * 100)}%` }"
              ></div>
            </div>
          </div>
        </div>
        <div class="death-actions">
          <button class="primary-btn" @click="startRun" type="button">Run Again</button>
          <button class="ghost-btn" @click="shareScore" type="button">{{ shareLabel }}</button>
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
import '@fontsource/chakra-petch/500.css';
import '@fontsource/chakra-petch/600.css';
import '@fontsource/chakra-petch/700.css';
import * as THREE from 'three';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import {
  cameraBase,
  levelOptions,
  lanes,
  groundY,
  gravity,
  jumpVelocity,
  slideScale,
  slideDuration,
  dropBoost,
  swipeThreshold,
  swipeTimeLimit,
  segmentLength,
  playerSize,
  coyoteTimeMs,
  groundedEpsilon,
  bumpWindowMs,
  FINALE_SCORE,
  carLanes,
  carPlayerSize,
  driveScoreMinSpeed,
  driveMaxSpeed,
  godTriggerSpeed,
  godHoldSeconds,
  godFloorSpeed,
  RAMP_SCORE,
  envSettings,
  nightDistricts,
  rowPatterns,
} from '../game/constants';
import { createAudioSystem } from '../game/audio';
import { createMissions, missionRewards } from '../game/missions';

const canvasWrap = ref(null);

const page = usePage();
const authUser = computed(() => page.props.auth?.user || null);
const isGuest = computed(() => !authUser.value);

const state = ref('menu');
const score = ref(0);
const bestScore = ref(0);
const statBestSpeed = ref(0);
const statTotalRuns = ref(0);
const speed = ref(0);
const runCoins = ref(0);
const totalCoins = ref(0);
const shopMessage = ref('');
const leaderboard = ref([]);
const yourRank = ref(null);
const ownedSkinIds = ref([]);
const loadingProfile = ref(false);
const runToken = ref(null);
const inventoryItems = ref([]);
const menuScreen = ref('main');
const showLoginPrompt = ref(false);
const showAuthGate = ref(false);
const cameraZoom = ref(1);
// Handedness decides which side the gas/brake pedals sit on: the dominant
// hand stays free for lane swipes, pedals go to the other thumb.
const handedness = ref('right');
const showHandPrompt = ref(false);
const isTouchDevice = ref(false);
const nearMissToast = ref(false);
const nearMissAmount = ref(25);
const nearMissCombo = ref(0);
let nearMissComboAt = -Infinity;
let nearMissTimer;
const runNearMisses = ref(0);
const runTopSpeed = ref(0);
let runTopSpeedRaw = 0;
// Zone-3 per-run counters feeding the daily missions.
const runGrazes = ref(0);
const runDroneKills = ref(0);
const runMotherKills = ref(0);
const runBestCombo = ref(0);

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
// Sanfter Kamera-Hub für Trampolin-Bounces: bleibt 0 bei normalen Sprüngen,
// hebt die Chase-Cam nur an, wenn der Spieler höher als ein normaler Sprung
// steigt (sonst fliegt er beim Dach-Bounce oben aus dem Bild).
let chaseCamLift = 0;
let laneOrigin = 1;

// Zone-1 event director: periodically breaks up the random rows with scripted
// moments (traffic-jam walls, wrong-way drivers, coin runs, lane drifters).
const eventToast = ref(null);
let eventToastTimer;
let trafficWave = null;
let coinRushEndZ = null;
let nextMilestone = 2500;
// Speed only steps up once the checkpoint breather has cleared the road —
// never mid-traffic. speedTier is the applied tier, checkpointPending the
// breather state machine.
let speedTier = 0;
let checkpointPending = null;
let lastRowFull = false;
let smashStreak = 0;
let recordCelebrated = false;
let driveEventTimer = 10;

const selectedLevel = ref(levelOptions[0].id);

// Audio subsystem (music playlist, SFX synth, engine drone) — see game/audio.js.
const {
  audioVolume,
  isMuted,
  isPaused,
  currentTrackName,
  showTrackToast,
  showPlaylist,
  playlistTracks,
  sfx,
  updateEngineSound,
  silenceEngineSound,
  isEngineActive,
  initAudio,
  loadAudioPrefs,
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
} = createAudioSystem({ state, speed });

// Daily missions + local daily stats — see game/missions.js.
const {
  dailyMissions,
  claimedMissions,
  completedMissionCount,
  missionProgress,
  claimMission,
  loadDailyStats,
  recordDailyStats,
} = createMissions({
  isGuest,
  showLoginPrompt,
  shopMessage,
  totalCoins,
  runCoins,
  runNearMisses,
  runGrazes,
  runDroneKills,
  runMotherKills,
  runBestCombo,
  runTopSpeed,
  score,
  sfx,
});

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
  { id: 1, slug: 'neon', label: 'Neon', category: 'runner', color: '#3bffb3', price: 0, is_default: true },
  { id: 2, slug: 'ember', label: 'Ember', category: 'runner', color: '#ff6b3b', price: 300, is_default: false },
  { id: 3, slug: 'ion', label: 'Ion', category: 'runner', color: '#49a8ff', price: 450, is_default: false },
  { id: 4, slug: 'dusk', label: 'Dusk', category: 'runner', color: '#b18cff', price: 600, is_default: false },
  { id: 5, slug: 'volt', label: 'Volt', category: 'runner', color: '#ffe14d', price: 750, is_default: false },
  { id: 6, slug: 'nova', label: 'Nova', category: 'runner', color: '#ff4fd8', price: 900, is_default: false },
]);
const selectedSkin = ref(skinOptions.value[0].id);

const runnerSkinOptions = computed(() =>
  skinOptions.value.filter((skin) => (skin.category || 'runner') === 'runner'),
);
const carSkinOptions = computed(() =>
  skinOptions.value.filter((skin) => skin.category === 'car'),
);
const planeSkinOptions = computed(() =>
  skinOptions.value.filter((skin) => skin.category === 'plane'),
);

// Zone 2/3 skin sections stay hidden as "???" until the player has actually
// reached that section once — the finale stays a secret.
const zone2Seen = ref(localStorage.getItem('runner_zone2_seen') === '1');
const zone3Seen = ref(localStorage.getItem('runner_zone3_seen') === '1');
const markZoneSeen = (zone) => {
  if (zone === 2 && !zone2Seen.value) {
    zone2Seen.value = true;
    localStorage.setItem('runner_zone2_seen', '1');
  } else if (zone === 3 && !zone3Seen.value) {
    zone3Seen.value = true;
    localStorage.setItem('runner_zone3_seen', '1');
  }
};

const selectedCarSlug = ref(localStorage.getItem('runner_car_slug') || 'car-sedan-sports');
const selectedPlaneSlug = ref(localStorage.getItem('runner_plane_slug') || 'plane-cesium');

// Gameplay tuning constants (physics, zone thresholds, palettes) live in
// ../game/constants.js and are imported above.
const godHoldProgress = ref(0);
const godModeActive = ref(false);
let godHoldTimer = 0;
let flyingCars = [];

// Zone 3 runtime state (trigger score: RAMP_SCORE in game/constants.js).
let rampTriggered = false;
let ramp = null;
let oceanGroup = null;
let launchVy = 0;
let launchTimer = 0;
let dockingPlane = null;
let planeVisual = null;
const planeTemplates = {};
let planeMixer = null;
let planeVelX = 0;
let planeVelY = 0;
const planeKeys = { up: false, down: false, left: false, right: false };
const joyKnob = ref({ x: 0, y: 0 });
let joyVec = { x: 0, y: 0 };
let joyPointerId = null;
// Gallery showroom joystick: only visible while browsing the free-roam
// showroom. Steers the character the same way WASD/arrows do there — it's a
// separate stick from the runner/finale ones above since it needs its own
// pointer id and never overlaps with them (different `state`).
const galleryJoyKnob = ref({ x: 0, y: 0 });
let galleryJoyVec = { x: 0, y: 0 };
let galleryJoyPointerId = null;
// Free-look orbit camera for the showroom, driven by dragging on the scene.
let galleryCamYaw = 0;
let galleryCamPitch = 0.35;
let galleryCamPointerId = null;
let galleryCamLast = { x: 0, y: 0 };
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

// Zone 4 (the void): after a mothership kill the wreck detonates, collapses
// into a black hole and drags the plane through a wormhole into a deep-space
// asteroid field. finalePhase: 'collapse' (cinematic, controls locked) then
// 'void' (asteroid gameplay — shoot the rocks or weave through).
let collapseTimer = 0;
let blackHole = null;
let shockRings = [];
let wreckDebris = [];
let debrisAssets = null;
const whiteFlash = ref(0);
let asteroids = [];
let asteroidPool = [];
let asteroidSpawnTimer = 0;
let asteroidGeometry = null;

let envMode = 'night'; // night | sunrise | day
let districtIndex = 0;
const applyDistrict = (index) => {
  districtIndex = index % nightDistricts.length;
  const district = nightDistricts[districtIndex];
  envSettings.night.bg = district.bg;
  envSettings.night.fog = district.fog;
  envSettings.night.hemiSky = district.hemiSky;
  envSettings.night.edge = district.edge;
};
const tmpEnvColor = new THREE.Color();
const finalePhase = ref('none'); // none | approach | walk | enter | drive
// Dev cheat (F9 during a run): jump straight to the finale trigger. The run
// is then never persisted, so it cannot flag the account or touch records.
const devRun = ref(false);
// Shared by the F9 key and the mobile cheat button.
const devSkip = () => {
  if (state.value !== 'running') return;
  if (!finaleTriggered && finalePhase.value === 'none') {
    devRun.value = true;
    score.value = FINALE_SCORE;
  } else if (finalePhase.value === 'drive' && !rampTriggered) {
    // Second skip in zone 2: jump straight to the ramp requirements.
    devRun.value = true;
    score.value = Math.max(score.value, RAMP_SCORE);
    driveTargetSpeed = driveMaxSpeed;
    speed.value = Math.max(speed.value, godTriggerSpeed);
    if (!godModeActive.value) {
      activateGodMode();
    }
  }
};
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
// Second chance: the first Zone-2 crash throws the character out of the car;
// they steal a parked car at the left curb and drive on. Crash #2 is fatal.
let driveLifeUsed = false;
let carjackPhase = 'eject'; // 'eject' (tumble arc) -> 'run' (sprint to the car)
let carjackTimer = 0;
let carjackCar = null;
let carjackFrom = new THREE.Vector3();
let carjackLand = new THREE.Vector3();
const carjackProps = [];

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
let pointerUnlockHandler;

let floorSegments = [];
let obstacles = [];
let spawnTimer = 0;

// Gallery mode: a free-roam showroom listing every obstacle/vehicle variant
// side by side so art changes are easy to eyeball without playing a run.
let galleryProps = [];
let galleryFloor = null;
let galleryYaw = Math.PI;
const galleryKeys = { forward: false, back: false, left: false, right: false };

let coins = [];
let coinPool = [];
let coinGeometry;
let coinMaterial;

let powerups = [];
const powerupPool = { shield: [], magnet: [], multi: [] };
let powerupAssets = null;

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

const lookAtTarget = new THREE.Vector3();

const currentLevel = computed(() =>
  levelOptions.find((level) => level.id === selectedLevel.value) || levelOptions[0],
);
const currentSkin = computed(() =>
  skinOptions.value.find((skin) => skin.id === selectedSkin.value) || skinOptions.value[0],
);

// One-time control hint for brand-new players; disappears on its own and is
// never shown again once seen.
const showTutorial = ref(false);
let tutorialTimer;
const maybeShowTutorial = () => {
  if (localStorage.getItem('runner_tutorial_done') === '1') return;
  showTutorial.value = true;
  if (tutorialTimer) {
    clearTimeout(tutorialTimer);
  }
  tutorialTimer = setTimeout(() => {
    showTutorial.value = false;
    localStorage.setItem('runner_tutorial_done', '1');
  }, 8000);
};

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
  maybeShowTutorial();
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
  if (screen === 'level') {
    setSkinTab('runner');
  }
};

const backToMainMenu = () => {
  clearCarPreview();
  previewSkin.value = null;
  menuScreen.value = 'main';
};

const closeLoginPrompt = () => {
  showLoginPrompt.value = false;
};

const backToMenu = () => {
  state.value = 'menu';
  menuScreen.value = 'main';
};

const shareLabel = ref('Share');
const shareScore = async () => {
  const text = `I scored ${Math.floor(score.value)} points in Lane Runner — can you beat it?`;
  const url = 'https://lanerunner.on-forge.com/game';
  try {
    if (navigator.share) {
      await navigator.share({ title: 'Lane Runner', text, url });
      return;
    }
    await navigator.clipboard.writeText(`${text} ${url}`);
    shareLabel.value = 'Copied!';
    setTimeout(() => {
      shareLabel.value = 'Share';
    }, 1600);
  } catch (error) {
    // Share sheet dismissed — nothing to do.
  }
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

const startGallery = () => {
  if (!scene) return;
  unlockAudio();
  showLoginPrompt.value = false;
  menuScreen.value = 'main';
  clearCarPreview();
  applyDistrict(0);
  exitFinale();
  clearObstacles();
  clearCoins();
  clearPowerups();
  buildGallery();
  floorSegments.forEach((segment) => {
    segment.visible = false;
  });
  playerVelocityY = 0;
  isSliding = false;
  galleryYaw = 0;
  galleryKeys.forward = false;
  galleryKeys.back = false;
  galleryKeys.left = false;
  galleryKeys.right = false;
  galleryJoyVec = { x: 0, y: 0 };
  galleryJoyKnob.value = { x: 0, y: 0 };
  galleryJoyPointerId = null;
  galleryCamYaw = 0;
  galleryCamPitch = 0.35;
  galleryCamPointerId = null;
  if (player) {
    player.scale.y = 1;
    player.rotation.set(0, galleryYaw, 0);
    player.position.set(0, playerSize.h / 2, 4);
    player.visible = true;
  }
  if (camera) {
    camera.fov = 60;
    camera.updateProjectionMatrix();
  }
  state.value = 'gallery';
};

const exitGallery = () => {
  if (state.value !== 'gallery') return;
  clearGallery();
  floorSegments.forEach((segment) => {
    segment.visible = true;
  });
  state.value = 'menu';
  menuScreen.value = 'main';
  if (camera) {
    camera.position.x = 0;
    applyCameraZoom();
  }
};

const normalizeSkins = (skins) =>
  skins.map((skin) => ({
    id: skin.id,
    slug: skin.slug,
    label: skin.name,
    category: skin.category || 'runner',
    color: skin.color || '#3bffb3',
    price: skin.price_coins ?? 0,
    is_default: Boolean(skin.is_default),
  }));

const ensureSelectedSkin = () => {
  const runners = runnerSkinOptions.value;
  if (!runners.length) return;
  const selected = runners.find((skin) => skin.id === selectedSkin.value);
  if (!selected || !canUseSkin(selected)) {
    const fallback = runners.find((skin) => canUseSkin(skin)) || runners[0];
    selectedSkin.value = fallback.id;
  }
  // A stored car/plane pick the player no longer owns falls back to default.
  const car = carSkinOptions.value.find((skin) => skin.slug === selectedCarSlug.value);
  if (car && !canUseSkin(car)) {
    selectedCarSlug.value = 'car-sedan-sports';
    localStorage.setItem('runner_car_slug', selectedCarSlug.value);
  }
};

const loadGuestPrefs = () => {
  const storedBest = Number.parseInt(localStorage.getItem('runner_best_distance') || '0', 10);
  bestScore.value = Number.isFinite(storedBest) ? storedBest : 0;
  statBestSpeed.value = Number.parseFloat(localStorage.getItem('runner_best_speed') || '0') || 0;
  statTotalRuns.value =
    Number.parseInt(localStorage.getItem('runner_total_runs') || '0', 10) || 0;
  const storedSkin = Number.parseInt(localStorage.getItem('runner_skin_id') || '', 10);
  if (Number.isFinite(storedSkin)) {
    selectedSkin.value = storedSkin;
  }
  inventoryItems.value = [];
  ensureSelectedSkin();
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
      statBestSpeed.value = Number(data.profile.best_speed) || 0;
      statTotalRuns.value = data.profile.total_runs ?? 0;
      ownedSkinIds.value = Array.isArray(data.owned_skin_ids)
        ? data.owned_skin_ids.map((id) => Number(id))
        : [];
      if (data.profile.active_skin_id) {
        selectedSkin.value = Number(data.profile.active_skin_id);
      }
      totalCoins.value = data.profile.coins ?? 0;
      inventoryItems.value = Array.isArray(data.inventory) ? data.inventory : [];
      claimedMissions.value = Array.isArray(data.mission_claims)
        ? data.mission_claims.map((i) => Number(i))
        : [];
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
    yourRank.value = response.data.your_rank ?? null;
  } catch (error) {
    leaderboard.value = [];
    yourRank.value = null;
  }
};

const canUseSkin = (skin) =>
  skin.is_default ||
  (skin.price ?? 0) === 0 ||
  (!isGuest.value && ownedSkinIds.value.includes(skin.id));

// Vehicle preview in the skins menu: the car/plane takes the runner's spot
// on stage; previewKind drives the camera framing.
let previewCar = null;
let previewKind = null;
const clearCarPreview = () => {
  if (previewCar) {
    if (scene) scene.remove(previewCar);
    previewCar = null;
  }
  previewKind = null;
  if (player && state.value === 'menu') {
    player.visible = true;
  }
};

const showCarPreview = (skin) => {
  if (!scene) return;
  clearCarPreview();
  const template = glbTemplates[skin.slug.replace(/^car-/, '')];
  if (!template) return;
  previewCar = template.model.clone(true);
  previewCar.position.set(
    player ? player.position.x : 0,
    template.size.h / 2 + 0.02,
    player ? player.position.z : 2,
  );
  previewCar.rotation.set(0, Math.PI * 0.85, 0);
  previewKind = 'car';
  scene.add(previewCar);
  if (player) {
    player.visible = false;
  }
};

const showPlanePreview = (skin) => {
  if (!scene) return;
  clearCarPreview();
  const key = skin.slug.replace(/^plane-/, '');
  const template = planeTemplates[key] || planeTemplates.cesium;
  if (!template) return;
  previewCar = template.model.clone(true);
  previewCar.position.set(
    player ? player.position.x : 0,
    1.5,
    player ? player.position.z : 2,
  );
  previewCar.rotation.set(0, Math.PI * 0.85, 0);
  previewKind = 'plane';
  scene.add(previewCar);
  if (player) {
    player.visible = false;
  }
};

// Buying is shared across categories; only what happens on success differs.
const purchaseSkin = async (skin) => {
  if (canUseSkin(skin)) return true;
  if (isGuest.value) {
    showLoginPrompt.value = true;
    return false;
  }
  if (totalCoins.value < (skin.price ?? 0)) {
    shopMessage.value = `Not enough coins — ${skin.label} costs ${skin.price}.`;
    return false;
  }
  try {
    const response = await axios.post('/api/runner/skin/buy', { skin_id: skin.id });
    totalCoins.value = response.data.coins ?? totalCoins.value;
    ownedSkinIds.value = Array.isArray(response.data.owned_skin_ids)
      ? response.data.owned_skin_ids.map((id) => Number(id))
      : ownedSkinIds.value;
    shopMessage.value = `${skin.label} unlocked!`;
    return true;
  } catch (error) {
    shopMessage.value = error.response?.data?.message || 'Purchase failed.';
    return false;
  }
};

// Tapping a chip only previews (and selects when already owned/free) — buying
// happens exclusively via the explicit Buy button.
const skinTab = ref('runner');
const previewSkin = ref(null);

const activeTabSkins = computed(() =>
  skinTab.value === 'car'
    ? carSkinOptions.value
    : skinTab.value === 'plane'
      ? planeSkinOptions.value
      : runnerSkinOptions.value,
);

const isSkinActive = (skin) => {
  if (skin.category === 'car') return skin.slug === selectedCarSlug.value;
  if (skin.category === 'plane') return skin.slug === selectedPlaneSlug.value;
  return skin.id === selectedSkin.value;
};

const applyRunnerSelection = (skin) => {
  selectedSkin.value = skin.id;
  if (authUser.value) {
    axios.post('/api/runner/skin', { skin_id: skin.id }).catch(() => {});
  } else {
    localStorage.setItem('runner_skin_id', String(skin.id));
  }
};

const applySkinSelection = (skin) => {
  if (skin.category === 'car') {
    selectedCarSlug.value = skin.slug;
    localStorage.setItem('runner_car_slug', skin.slug);
  } else if (skin.category === 'plane') {
    selectedPlaneSlug.value = skin.slug;
    localStorage.setItem('runner_plane_slug', skin.slug);
  } else {
    applyRunnerSelection(skin);
  }
};

const tapSkin = (skin) => {
  shopMessage.value = '';
  previewSkin.value = skin;
  if (skin.category === 'car') {
    showCarPreview(skin);
  } else if (skin.category === 'plane') {
    showPlanePreview(skin);
  } else {
    clearCarPreview();
    applyCharacter(skin);
  }
  if (canUseSkin(skin)) {
    applySkinSelection(skin);
  }
};

const buyPreviewedSkin = async () => {
  const skin = previewSkin.value;
  if (!skin) return;
  if (!(await purchaseSkin(skin))) return;
  applySkinSelection(skin);
};

const setSkinTab = (tab) => {
  skinTab.value = tab;
  shopMessage.value = '';
  if (tab === 'car' && zone2Seen.value) {
    const skin =
      carSkinOptions.value.find((s) => s.slug === selectedCarSlug.value) ||
      carSkinOptions.value[0];
    if (skin) {
      previewSkin.value = skin;
      showCarPreview(skin);
    }
  } else if (tab === 'plane' && zone3Seen.value) {
    const skin =
      planeSkinOptions.value.find((s) => s.slug === selectedPlaneSlug.value) ||
      planeSkinOptions.value[0];
    if (skin) {
      previewSkin.value = skin;
      showPlanePreview(skin);
    }
  } else {
    clearCarPreview();
    previewSkin.value =
      runnerSkinOptions.value.find((s) => s.id === selectedSkin.value) || null;
    applyCharacter();
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
  clearCarPreview();
  applyDistrict(0);
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
  chaseCamLift = 0;
  laneOrigin = 1;
  bumpToast.value = false;
  nearMissCombo.value = 0;
  nearMissComboAt = -Infinity;
  smashStreak = 0;
  recordCelebrated = false;
  runNearMisses.value = 0;
  runGrazes.value = 0;
  runDroneKills.value = 0;
  runMotherKills.value = 0;
  runBestCombo.value = 0;
  runTopSpeed.value = 0;
  runTopSpeedRaw = 0;
  trafficWave = null;
  coinRushEndZ = null;
  speedTier = 0;
  checkpointPending = null;
  lastRowFull = false;

  nextMilestone = 2500;
  runSaveNotice.value = null;
  eventToast.value = null;
  clearObstacles();
  clearCoins();
  clearPowerups();
  resetFloor();
};

const endRun = () => {
  state.value = 'crashed';
  runTopSpeed.value = runTopSpeedRaw;
  if (!devRun.value) {
    recordDailyStats();
  }
  persistRun();
  loadLeaderboard();
};

const runSaveNotice = ref(null);

const runSaveNoticeText = computed(() => {
  if (runSaveNotice.value === 'unranked') {
    return 'Run not ranked — records unchanged.';
  }
  if (runSaveNotice.value === 'offline') {
    return 'Score could not be saved — check your connection.';
  }
  return '';
});

const persistRun = async () => {
  runSaveNotice.value = null;
  if (devRun.value) {
    // Dev runs never touch records, coins, leaderboard, or anti-cheat.
    return;
  }
  const distance = Math.max(0, Math.floor(score.value));
  const maxSpeed = Number(speed.value.toFixed(2));

  if (authUser.value) {
    // No run token means /run/start already failed (network/session), so
    // the server will refuse to rank this run either way.
    const hadToken = !!runToken.value;
    try {
      const response = await axios.post('/api/runner/run/end', {
        distance,
        max_speed: maxSpeed,
        coins: runCoins.value,
        run_id: runToken.value,
      });
      if (response.data && response.data.accepted === false) {
        runSaveNotice.value = hadToken && !response.data.guest ? 'unranked' : 'offline';
      }
      const updated = response.data.profile;
      if (updated && typeof updated.best_distance === 'number') {
        bestScore.value = updated.best_distance;
      }
      if (updated && typeof updated.coins === 'number') {
        totalCoins.value = updated.coins;
      }
      loadProfile();
    } catch (error) {
      runSaveNotice.value = 'offline';
    }
    return;
  }

  const storedBest = Number.parseInt(localStorage.getItem('runner_best_distance') || '0', 10);
  const nextBest = Number.isFinite(storedBest)
    ? Math.max(storedBest, distance)
    : distance;
  localStorage.setItem('runner_best_distance', String(nextBest));
  bestScore.value = nextBest;
  statTotalRuns.value += 1;
  localStorage.setItem('runner_total_runs', String(statTotalRuns.value));
  statBestSpeed.value = Math.max(statBestSpeed.value, maxSpeed);
  localStorage.setItem('runner_best_speed', String(statBestSpeed.value));
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

const clearGallery = () => {
  galleryProps.forEach((prop) => scene.remove(prop));
  galleryProps = [];
  if (galleryFloor) {
    scene.remove(galleryFloor);
    galleryFloor = null;
  }
};

// Lays out one instance of every registered obstacle/vehicle builder in a
// Floating name tag above each showroom exhibit so it's obvious which
// obstacle key builds which mesh. Canvas-based sprite: always faces the
// camera, sized from the measured text so long keys don't get squished.
const makeGalleryLabel = (text) => {
  const canvas = document.createElement('canvas');
  const font = '600 44px "Segoe UI", system-ui, sans-serif';
  const ctx = canvas.getContext('2d');
  ctx.font = font;
  const padX = 26;
  canvas.width = Math.ceil(ctx.measureText(text).width) + padX * 2;
  canvas.height = 88;
  // Resizing the canvas resets the 2D context state, so restyle after.
  ctx.font = font;
  ctx.fillStyle = 'rgba(8, 12, 22, 0.72)';
  ctx.fillRect(0, 0, canvas.width, canvas.height);
  ctx.strokeStyle = 'rgba(120, 180, 255, 0.65)';
  ctx.lineWidth = 3;
  ctx.strokeRect(1.5, 1.5, canvas.width - 3, canvas.height - 3);
  ctx.textAlign = 'center';
  ctx.textBaseline = 'middle';
  ctx.fillStyle = '#cfe0ff';
  ctx.fillText(text, canvas.width / 2, canvas.height / 2 + 2);
  const texture = track(new THREE.CanvasTexture(canvas));
  texture.colorSpace = THREE.SRGBColorSpace;
  const sprite = new THREE.Sprite(
    track(new THREE.SpriteMaterial({ map: texture, transparent: true, depthWrite: false })),
  );
  const worldH = 0.5;
  sprite.scale.set((canvas.width / canvas.height) * worldH, worldH, 1);
  return sprite;
};

// grid on a dedicated showroom floor. Rebuilt each time the gallery opens so
// GLB variants that finish loading late (see glbVehicleDefs) still show up.
const buildGallery = () => {
  if (!scene) return;
  clearGallery();

  const floorGeo = track(new THREE.PlaneGeometry(80, 80));
  const floorMat = track(new THREE.MeshLambertMaterial({ color: 0x1b2233 }));
  galleryFloor = new THREE.Mesh(floorGeo, floorMat);
  galleryFloor.rotation.x = -Math.PI / 2;
  galleryFloor.position.set(0, 0, -14);
  scene.add(galleryFloor);
  galleryProps.push(galleryFloor);

  const assets = getObstacleAssets();
  const keys = Object.keys(obstacleBuilders).sort();
  const cols = Math.max(1, Math.ceil(Math.sqrt(keys.length)));
  const spacing = 4.4;
  const startX = -((cols - 1) * spacing) / 2;
  keys.forEach((key, index) => {
    const { mesh, size } = obstacleBuilders[key]();
    const col = index % cols;
    const row = Math.floor(index / cols);
    mesh.position.set(startX + col * spacing, size.h / 2, -14 - row * spacing);
    mesh.rotation.y = Math.PI;
    if (mesh.userData.beams) {
      mesh.userData.beams.visible = false;
    }
    if (mesh.userData.paintMeshes) {
      const palette = mesh.userData.paintSet === 'bus' ? assets.busPaints : assets.carPaints;
      const paint = palette[Math.floor(Math.random() * palette.length)];
      mesh.userData.paintMeshes.forEach((paintMesh) => {
        paintMesh.material = paint;
      });
    }
    // Child of the exhibit mesh so clearGallery removes it along with it.
    const label = makeGalleryLabel(key);
    label.position.set(0, size.h / 2 + 0.55, 0);
    mesh.add(label);
    scene.add(mesh);
    galleryProps.push(mesh);
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
  if (state.value === 'gallery') {
    switch (event.code) {
      case 'ArrowUp':
      case 'KeyW':
        galleryKeys.forward = true;
        break;
      case 'ArrowDown':
      case 'KeyS':
        galleryKeys.back = true;
        break;
      case 'ArrowLeft':
      case 'KeyA':
        galleryKeys.left = true;
        break;
      case 'ArrowRight':
      case 'KeyD':
        galleryKeys.right = true;
        break;
      case 'Space':
        galleryJump();
        break;
      case 'Escape':
        exitGallery();
        break;
      default:
        break;
    }
    return;
  }

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
    devSkip();
    return;
  }

  if (finalePhase.value === 'collapse') {
    // Black-hole cinematic: the player has no control until zone 4 opens.
    return;
  }

  if (finalePhase.value === 'plane' || finalePhase.value === 'void') {
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

const pedalGasDown = (event) => {
  // Capture keeps the pointer bound to the pedal even when the thumb
  // drifts off the button — pointerup always reaches us.
  event?.currentTarget?.setPointerCapture?.(event.pointerId);
  if (!accelHeld) {
    sfx.rev();
  }
  accelHeld = true;
};

const pedalGasUp = () => {
  accelHeld = false;
};

const pedalBrakeDown = (event) => {
  event?.currentTarget?.setPointerCapture?.(event.pointerId);
  if (!brakeHeld && speed.value > 12) {
    sfx.brakeScreech();
  }
  brakeHeld = true;
};

const pedalBrakeUp = () => {
  brakeHeld = false;
};

const handleKeyup = (event) => {
  if (state.value === 'gallery') {
    switch (event.code) {
      case 'ArrowUp':
      case 'KeyW':
        galleryKeys.forward = false;
        break;
      case 'ArrowDown':
      case 'KeyS':
        galleryKeys.back = false;
        break;
      case 'ArrowLeft':
      case 'KeyA':
        galleryKeys.left = false;
        break;
      case 'ArrowRight':
      case 'KeyD':
        galleryKeys.right = false;
        break;
      default:
        break;
    }
    return;
  }
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
  if (event.target?.closest?.('.joystick, .pedals, .cheat-skip')) return;
  // Track exactly one swipe finger by identifier so a thumb resting on the
  // pedals (or joystick) never hijacks or cancels the swipe gesture.
  if (touchStart) return;
  const touch = event.changedTouches[0];
  touchStart = {
    id: touch.identifier,
    x: touch.clientX,
    y: touch.clientY,
    time: performance.now(),
  };
};

const findTrackedTouch = (event) => {
  if (!touchStart) return null;
  for (const touch of event.changedTouches) {
    if (touch.identifier === touchStart.id) return touch;
  }
  return null;
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

  if (
    finalePhase.value === 'plane' ||
    finalePhase.value === 'launch' ||
    finalePhase.value === 'void' ||
    finalePhase.value === 'collapse'
  ) {
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
  const touch = findTrackedTouch(event);
  if (!touch) return;
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
  if (target?.closest?.('.joystick, .pedals, .cheat-skip')) return;

  if (state.value !== 'running' || !touchStart) return;
  const touch = findTrackedTouch(event);
  if (!touch) return;
  const dx = touch.clientX - touchStart.x;
  const dy = touch.clientY - touchStart.y;
  const dt = performance.now() - touchStart.time;

  if (dt > swipeTimeLimit) return;
  if (triggerSwipe(dx, dy)) {
    touchStart = null;
  }
};

const handleTouchCancel = (event) => {
  if (findTrackedTouch(event)) {
    touchStart = null;
  }
};

// Haptics: short buzzes on impactful moments (mobile only, toggleable).
const vibrationEnabled = ref(localStorage.getItem('runner_vibration') !== '0');
const setVibration = (on) => {
  vibrationEnabled.value = on;
  localStorage.setItem('runner_vibration', on ? '1' : '0');
};
const vibrate = (ms) => {
  if (!vibrationEnabled.value) return;
  navigator.vibrate?.(ms);
};

// Battery Saver renders at a lower pixel ratio — a big win on hot phones.
const renderQuality = ref(localStorage.getItem('runner_quality') || 'full');
const currentPixelRatio = () =>
  Math.min(window.devicePixelRatio || 1, renderQuality.value === 'battery' ? 1.3 : 2);
const setRenderQuality = (quality) => {
  renderQuality.value = quality;
  localStorage.setItem('runner_quality', quality);
  if (renderer) {
    renderer.setPixelRatio(currentPixelRatio());
    handleResize();
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

// Wind-Streaks: dünne additive Lichtstriche neben der Strecke, die schneller
// als die Welt scrollen — machen das Tempo sichtbar. Deckkraft wächst mit dem
// Speed (unter ~15 unsichtbar), ein geteiltes Material für alle Striche.
let windStreaks = null;
let windStreakMat = null;
// Geteiltes Material der Neon-Straßenkanten — Farbe wandert pro District mit.
let edgeLineMaterial = null;

const resetWindStreak = (streak, anywhere = false) => {
  const side = Math.random() < 0.5 ? -1 : 1;
  streak.position.set(
    side * (2.8 + Math.random() * 4.2),
    0.5 + Math.random() * 3.6,
    anywhere ? -Math.random() * 70 : -(55 + Math.random() * 25),
  );
};

const ensureWindStreaks = () => {
  if (windStreaks || !scene) return;
  windStreakMat = new THREE.MeshBasicMaterial({
    color: 0xbfe6ff,
    transparent: true,
    opacity: 0,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
    side: THREE.DoubleSide,
  });
  const streakGeo = new THREE.PlaneGeometry(0.055, 3.4);
  windStreaks = new THREE.Group();
  for (let i = 0; i < 16; i += 1) {
    const streak = new THREE.Mesh(streakGeo, windStreakMat);
    streak.rotation.x = Math.PI / 2;
    resetWindStreak(streak, true);
    windStreaks.add(streak);
  }
  scene.add(windStreaks);
};

const updateWindStreaks = (delta) => {
  ensureWindStreaks();
  if (!windStreaks) return;
  const strength = THREE.MathUtils.clamp((speed.value - 15) / 30, 0, 1);
  windStreakMat.opacity = strength * 0.45;
  if (strength <= 0) return;
  windStreaks.children.forEach((streak) => {
    streak.position.z += speed.value * 2.1 * delta;
    if (streak.position.z > 10) {
      resetWindStreak(streak);
    }
  });
};

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

  // Silhouetten-Vielfalt: gestufte Türme oder leuchtende Dach-Billboards,
  // damit die Skyline nicht wie eine Reihe gleicher Klötze wirkt.
  if (h >= 8 && Math.random() < 0.25) {
    const upper = new THREE.Mesh(
      buildingGeometry,
      buildingMaterials[Math.floor(Math.random() * buildingMaterials.length)],
    );
    upper.scale.set(w * 0.55, h * 0.35, d * 0.6);
    upper.position.y = h;
    group.add(upper);
    const upperParapet = new THREE.Mesh(buildingGeometry, houseAssets.trimMat);
    upperParapet.scale.set(w * 0.55 + 0.14, 0.22, d * 0.6 + 0.14);
    upperParapet.position.y = h + h * 0.35 - 0.04;
    group.add(upperParapet);
  } else if (h >= 9 && Math.random() < 0.3) {
    const billboard = new THREE.Group();
    const face = new THREE.Mesh(
      houseAssets.billboardGeo,
      houseAssets.neonMats[Math.floor(Math.random() * houseAssets.neonMats.length)],
    );
    face.position.y = 1.05;
    billboard.add(face);
    const back = new THREE.Mesh(houseAssets.billboardGeo, houseAssets.grayMat);
    back.position.set(0, 1.05, -0.05);
    back.rotation.y = Math.PI;
    billboard.add(back);
    [-0.9, 0.9].forEach((x) => {
      const pole = new THREE.Mesh(houseAssets.billboardPoleGeo, houseAssets.grayMat);
      pole.position.set(x, 0.28, -0.03);
      billboard.add(pole);
    });
    billboard.position.y = h;
    billboard.rotation.y = faceRotation;
    group.add(billboard);
  }
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

  if (Math.random() < 0.45) {
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
  astro: 'character-astro',
  ninja: 'character-ninja',
  robo: 'character-robot',
  alien: 'character-alien',
};
const characterKeys = [
  'character-a',
  'character-b',
  'character-c',
  'character-d',
  'character-e',
  'character-f',
  'character-astro',
  'character-ninja',
  'character-robot',
  'character-alien',
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
  // Kenney names clips plainly ('sprint'); Quaternius prefixes them with the
  // armature ('CharacterArmature|Run', 'AlienArmature|Alien_Run').
  const stripClipName = (name) => name.split('|').pop().toLowerCase();
  const findClip = (...names) => {
    for (const name of names) {
      const clip = template.clips.find((c) => stripClipName(c.name) === name);
      if (clip) return clip;
    }
    return null;
  };
  const runClip =
    findClip('sprint', 'run', 'walk') ||
    template.clips.find((c) => stripClipName(c.name).includes('run')) ||
    null;
  const idleClip =
    findClip('idle', 'idle_neutral', 'static') ||
    template.clips.find((c) => stripClipName(c.name).includes('idle')) ||
    null;
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
        // Skinned meshes can vanish to frustum culling once bones move the
        // mesh away from its bind-pose bounds.
        gltf.scene.traverse((node) => {
          if (node.isSkinnedMesh) {
            node.frustumCulled = false;
          }
        });
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

const galleryMoveSpeed = 3.6;
const galleryCamDist = 4.6;

const updateGallery = (delta) => {
  if (!player) return;
  // Stick + WASD are camera-relative, like any mobile open-world game:
  // push the stick right and the character runs to the right of the view.
  const inX =
    galleryJoyVec.x + (galleryKeys.right ? 1 : 0) - (galleryKeys.left ? 1 : 0);
  const inY =
    -galleryJoyVec.y +
    (galleryKeys.forward ? 1 : 0) -
    (galleryKeys.back ? 1 : 0);
  const inLen = Math.min(1, Math.hypot(inX, inY));
  const moving = inLen > 0.001;
  // Camera basis vectors projected onto the ground plane.
  const camFwdX = Math.sin(galleryCamYaw);
  const camFwdZ = -Math.cos(galleryCamYaw);
  const camRightX = Math.cos(galleryCamYaw);
  const camRightZ = Math.sin(galleryCamYaw);
  if (moving) {
    const moveX = camFwdX * inY + camRightX * inX;
    const moveZ = camFwdZ * inY + camRightZ * inX;
    const moveLen = Math.hypot(moveX, moveZ) || 1;
    player.position.x += (moveX / moveLen) * inLen * galleryMoveSpeed * delta;
    player.position.z += (moveZ / moveLen) * inLen * galleryMoveSpeed * delta;
    // Turn the character toward the direction they are running, taking the
    // shortest way around the circle. three.js convention: models rest
    // facing -Z and positive rotation.y turns counter-clockwise seen from
    // above (right-hand rule), so forward(yaw) = (-sin(yaw), -cos(yaw))
    // and the yaw facing (moveX, moveZ) is atan2(-moveX, -moveZ).
    const targetYaw = Math.atan2(-moveX, -moveZ);
    let diff = targetYaw - galleryYaw;
    diff =
      ((((diff + Math.PI) % (Math.PI * 2)) + Math.PI * 2) % (Math.PI * 2)) -
      Math.PI;
    galleryYaw += diff * Math.min(1, delta * 12);
    // The chase cam eases around behind the run direction (same turn as
    // the character) while the player is NOT actively dragging the camera.
    // Camera forward is (sin(yaw), -cos(yaw)), so the yaw that looks along
    // (moveX, moveZ) is atan2(moveX, -moveZ). Skip when running toward the
    // camera — otherwise it would flip 180° and you could never run into
    // the view.
    if (galleryCamPointerId === null) {
      const camTargetYaw = Math.atan2(moveX, -moveZ);
      let camDiff = camTargetYaw - galleryCamYaw;
      camDiff =
        ((((camDiff + Math.PI) % (Math.PI * 2)) + Math.PI * 2) %
          (Math.PI * 2)) -
        Math.PI;
      if (Math.abs(camDiff) < Math.PI * 0.75) {
        galleryCamYaw += camDiff * Math.min(1, delta * 2.5) * inLen;
      }
    }
  }
  player.position.x = THREE.MathUtils.clamp(player.position.x, -20, 20);
  player.position.z = THREE.MathUtils.clamp(player.position.z, -46, 8);
  player.rotation.y = galleryYaw;

  // Simple jump arc for the showroom: same gravity/jump tuning as the run,
  // clamped to the flat gallery floor.
  playerVelocityY += gravity * delta;
  player.position.y += playerVelocityY * delta;
  const galleryGroundY = playerSize.h / 2;
  if (player.position.y <= galleryGroundY) {
    player.position.y = galleryGroundY;
    playerVelocityY = 0;
  }

  if (activeCharacter) {
    setCharacterAction(moving ? 'run' : 'idle');
    if (activeCharacter.actions.run) {
      activeCharacter.actions.run.paused = !moving;
    }
    // The character always turns into the run direction now, so the
    // animation never needs to play backwards.
    activeCharacter.mixer.timeScale = 1;
    activeCharacter.root.rotation.x = THREE.MathUtils.damp(
      activeCharacter.root.rotation.x,
      0,
      8,
      delta,
    );
    activeCharacter.mixer.update(delta);
  }
  player.rotation.z = THREE.MathUtils.damp(player.rotation.z, 0, 6, delta);

  // Orbit chase cam: the drag gesture controls where the camera sits on a
  // sphere around the character, independent of the run direction.
  const cosPitch = Math.cos(galleryCamPitch);
  const targetX = player.position.x - camFwdX * cosPitch * galleryCamDist;
  const targetY =
    player.position.y + 0.9 + Math.sin(galleryCamPitch) * galleryCamDist;
  const targetZ = player.position.z - camFwdZ * cosPitch * galleryCamDist;
  camera.position.x = THREE.MathUtils.damp(camera.position.x, targetX, 8, delta);
  camera.position.y = THREE.MathUtils.damp(camera.position.y, targetY, 8, delta);
  camera.position.z = THREE.MathUtils.damp(camera.position.z, targetZ, 8, delta);
  lookAtTarget.set(
    player.position.x,
    player.position.y + 1.1,
    player.position.z,
  );
  camera.lookAt(lookAtTarget);
};

const getCoin = () => {
  if (coinPool.length) {
    return coinPool.pop();
  }
  return new THREE.Mesh(coinGeometry, coinMaterial);
};

const placeCoin = (x, y, z) => {
  const coin = getCoin();
  coin.position.set(x, y, z);
  coin.rotation.y = Math.random() * Math.PI;
  coins.push(coin);
  scene.add(coin);
};

// Coin-Trails, die den Weg WEISEN statt nur herumzuliegen: meist ein Schwenk
// aus einer Nachbar-Lane in die freie Lane der kommenden Reihe (der Blick
// folgt den Coins zum sicheren Slot), sonst eine kurze gerade Linie.
const spawnCoinTrail = (laneIndex, baseZ) => {
  // Nur NACHBAR-Lanes als Startpunkt — der Wechsel geht nie quer über die
  // ganze Straße.
  const neighbors = [laneIndex - 1, laneIndex + 1].filter((lane) => lane >= 0 && lane <= 2);
  const fromLane = neighbors[Math.floor(Math.random() * neighbors.length)];
  if (Math.random() < 0.6) {
    // Lane-Wechsel mit genau 3 Münzen: eine in der alten Lane, eine schon in
    // der Ziel-Lane, die dritte auf dem normalen Weg — locker verteilt.
    placeCoin(lanes[fromLane], 1.0, baseZ + 8.0);
    placeCoin(lanes[laneIndex], 1.0, baseZ + 5.0);
    placeCoin(lanes[laneIndex], 1.0, baseZ + 2.0);
  } else {
    // Gerade Linie, mit ein paar Metern Luft zwischen den Münzen.
    for (let k = 0; k < 5; k += 1) {
      placeCoin(lanes[laneIndex], 1.0, baseZ + 5.0 - k * 3.0);
    }
  }
};

// Sprung-Köder: Bogen über ein niedriges Hindernis — wer springt, kassiert.
// Der Bogen folgt der ECHTEN Sprungparabel: die Flugzeit (~0.86s) ist fix,
// die Sprungweite wächst also linear mit dem Tempo — feste Coin-Abstände
// liegen ab ~Speed 15 sichtbar neben der tatsächlichen Flugbahn.
const jumpAirtime = 0.86;
const jumpApexLift = 1.85;
const spawnCoinArc = (laneIndex, z) => {
  const arcSpan = speed.value * jumpAirtime;
  for (let i = 0; i < 5; i += 1) {
    const f = i / 4;
    placeCoin(lanes[laneIndex], 1.0 + jumpApexLift * 4 * f * (1 - f), z + (0.5 - f) * arcSpan);
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
// Chained near misses within the combo window multiply the bonus (x2, x3…).
const nearMissComboWindowMs = 4000;

const triggerNearMiss = () => {
  const now = performance.now();
  nearMissCombo.value =
    now - nearMissComboAt < nearMissComboWindowMs ? nearMissCombo.value + 1 : 1;
  nearMissComboAt = now;
  runBestCombo.value = Math.max(runBestCombo.value, nearMissCombo.value);
  const bonus = nearMissBase * nearMissCombo.value * (multiTime.value > 0 ? 2 : 1);
  nearMissAmount.value = bonus;
  runNearMisses.value += 1;
  vibrate(12);
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

// Zone-3 graze: an enemy bolt slipping past close by pays out like a road
// near miss — shares the toast/combo machinery, tracks its own mission stat.
const triggerGraze = () => {
  const now = performance.now();
  nearMissCombo.value =
    now - nearMissComboAt < nearMissComboWindowMs ? nearMissCombo.value + 1 : 1;
  nearMissComboAt = now;
  runBestCombo.value = Math.max(runBestCombo.value, nearMissCombo.value);
  const bonus = 30 * nearMissCombo.value;
  nearMissAmount.value = bonus;
  runGrazes.value += 1;
  vibrate(12);
  score.value += bonus;
  sfx.nearMiss();
  nearMissToast.value = false;
  if (nearMissTimer) {
    clearTimeout(nearMissTimer);
  }
  requestAnimationFrame(() => {
    nearMissToast.value = true;
    nearMissTimer = setTimeout(() => {
      nearMissToast.value = false;
    }, 950);
  });
};

// Ambient shooting stars streaking across the night sky.
let shootingStars = [];
let shootingStarTimer = 6;
let shootingStarGeo = null;
let shootingStarMat = null;

const spawnShootingStar = () => {
  if (!shootingStarGeo) {
    shootingStarGeo = new THREE.BoxGeometry(0.14, 0.14, 7);
    shootingStarMat = new THREE.MeshBasicMaterial({
      color: 0xdfeaff,
      transparent: true,
      opacity: 0.9,
      fog: false,
    });
  }
  const mesh = new THREE.Mesh(shootingStarGeo, shootingStarMat.clone());
  const x = THREE.MathUtils.randFloat(-90, 90);
  mesh.position.set(x, THREE.MathUtils.randFloat(45, 80), -180);
  const vel = new THREE.Vector3(
    THREE.MathUtils.randFloat(-42, -22) * (x > 0 ? 1 : -1),
    THREE.MathUtils.randFloat(-14, -6),
    0,
  );
  mesh.userData.vel = vel;
  mesh.userData.life = 1.2;
  mesh.lookAt(mesh.position.clone().add(vel));
  shootingStars.push(mesh);
  scene.add(mesh);
};

const updateShootingStars = (delta) => {
  if (!scene) return;
  shootingStarTimer -= delta;
  if (shootingStarTimer <= 0 && envMode === 'night') {
    spawnShootingStar();
    shootingStarTimer = THREE.MathUtils.randFloat(7, 18);
  }
  for (let i = shootingStars.length - 1; i >= 0; i -= 1) {
    const star = shootingStars[i];
    star.position.addScaledVector(star.userData.vel, delta);
    star.userData.life -= delta;
    star.material.opacity = Math.max(0, Math.min(0.9, star.userData.life));
    if (star.userData.life <= 0) {
      scene.remove(star);
      star.material.dispose();
      shootingStars.splice(i, 1);
    }
  }
};

const initScene = () => {
  scene = new THREE.Scene();
  scene.background = new THREE.Color(0x05070f);
  scene.fog = new THREE.Fog(0x05070f, 40, 165);

  camera = new THREE.PerspectiveCamera(60, 1, 0.1, 700);
  camera.position.set(0, cameraBase.y, cameraBase.z);
  applyCameraZoom();

  renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false });
  renderer.setPixelRatio(currentPixelRatio());
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
  edgeLineMaterial = new THREE.MeshBasicMaterial({ color: 0x2ee5ff });
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
    billboardGeo: new THREE.PlaneGeometry(2.4, 1.3),
    billboardPoleGeo: new THREE.BoxGeometry(0.08, 0.75, 0.08),
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
  window.addEventListener('touchcancel', handleTouchCancel, { passive: true });
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
// Until a model is ready the procedural fallbacks ('low-car'/'tall-stack') fill in.
const glbVehicleDefs = [
  { key: 'sedan', kind: 'car', fitLength: 2.7 },
  { key: 'sedan-sports', kind: 'car', fitLength: 2.9 },
  { key: 'hatchback-sports', kind: 'car', fitLength: 2.65 },
  { key: 'race', kind: 'car', fitLength: 2.75 },
  { key: 'taxi', kind: 'car', fitLength: 2.7 },
  { key: 'police', kind: 'car', fitLength: 3.2 },
  { key: 'suv', kind: 'car', fitLength: 2.7 },
  { key: 'suv-luxury', kind: 'car', fitLength: 3.0 },
  { key: 'van', kind: 'car', fitLength: 3.0 },
  { key: 'delivery', kind: 'car', fitLength: 3.2 },
  { key: 'ambulance', kind: 'tall', fitLength: 3.5 },
  { key: 'truck', kind: 'tall', fitLength: 3.4 },
  { key: 'firetruck', kind: 'tall', fitLength: 4.3 },
  { key: 'garbage-truck', kind: 'tall', fitLength: 3.9 },
  { key: 'cone', kind: 'prop', fitHeight: 1.05 },
  { key: 'box', kind: 'prop', fitHeight: 1.1 },
  // Baustellenfahrzeuge (Kenney Car Kit, CC0)
  { key: 'tractor', kind: 'tall', fitLength: 3.1 },
  { key: 'tractor-shovel', kind: 'tall', fitLength: 3.5 },
  { key: 'truck-flat', kind: 'tall', fitLength: 3.6 },
  // Baustelle & Schrott (Kenney City Kit Roads + Survival Kit, CC0).
  // 'prop' = niedriges statisches Hindernis (überspringbar),
  // 'set-piece' = spawnt nie einzeln, nur im Baustellen-Set (spawnConstructionSet),
  // 'decor' = reine Deko ohne Kollision (Schilderbrücke über der Straße).
  { key: 'construction-barrier', kind: 'prop', fitHeight: 1.1, dir: 'obstacles/roads' },
  { key: 'resource-planks', kind: 'set-piece', fitHeight: 0.6, dir: 'obstacles/survival' },
  { key: 'box-large', kind: 'prop', fitHeight: 1.2, dir: 'obstacles/survival' },
  { key: 'construction-light', kind: 'set-piece', fitHeight: 2.7, rotateY: Math.PI / 2, dir: 'obstacles/roads' },
  { key: 'structure-metal', kind: 'set-piece', fitHeight: 2.8, scaleX: 0.6, dir: 'obstacles/survival' },
  { key: 'sign-highway', kind: 'decor', fitWidth: 9, rotateY: Math.PI / 2, dir: 'obstacles/roads' },
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

// Baustellen-Zone-Optik fürs Gerüst: kleine rot-weiße Absperrbaken links und
// rechts plus ein Stop-Schild vorne. Rein dekorativ — die Hitbox bleibt die
// Bounding-Box des Gerüsts selbst.
const addConstructionDressing = (group, size) => {
  const a = getObstacleAssets();
  const groundY = -size.h / 2;
  const beamGeo = track(new THREE.BoxGeometry(0.85, 0.2, 0.1));
  const stripeGeo = track(new THREE.BoxGeometry(0.2, 0.22, 0.12));
  const legGeo = track(new THREE.BoxGeometry(0.07, 0.6, 0.07));
  [-1, 1].forEach((side) => {
    const barrier = new THREE.Group();
    const beam = new THREE.Mesh(beamGeo, a.barrel);
    beam.position.y = 0.62;
    barrier.add(beam);
    [-0.28, 0.04, 0.36].forEach((x) => {
      const stripe = new THREE.Mesh(stripeGeo, a.busRoof);
      stripe.position.set(x, 0.62, 0);
      barrier.add(stripe);
    });
    [-0.36, 0.36].forEach((x) => {
      const leg = new THREE.Mesh(legGeo, a.frame);
      leg.position.set(x, 0.3, 0);
      barrier.add(leg);
    });
    barrier.position.set(side * (size.w / 2 + 0.35), groundY, size.d / 2 + 0.2);
    barrier.rotation.y = side * 0.45;
    group.add(barrier);
  });
  const sign = new THREE.Group();
  const pole = new THREE.Mesh(track(new THREE.CylinderGeometry(0.045, 0.045, 1.45, 8)), a.frame);
  pole.position.y = 0.72;
  sign.add(pole);
  const faceGeo = track(new THREE.CylinderGeometry(0.3, 0.3, 0.05, 8));
  faceGeo.rotateX(Math.PI / 2);
  const face = new THREE.Mesh(faceGeo, a.barrel);
  face.position.y = 1.55;
  sign.add(face);
  const text = new THREE.Mesh(track(new THREE.BoxGeometry(0.36, 0.09, 0.02)), a.busRoof);
  text.position.set(0, 1.55, 0.04);
  sign.add(text);
  sign.position.set(0, groundY, size.d / 2 + 0.4);
  group.add(sign);
  // Blinklampen auf den Baken (geteiltes hazard-Material pulsiert im Loop).
  const lampGeo = track(new THREE.BoxGeometry(0.11, 0.09, 0.11));
  [-1, 1].forEach((side) => {
    const lamp = new THREE.Mesh(lampGeo, a.hazard);
    lamp.position.set(side * (size.w / 2 + 0.35), groundY + 0.78, size.d / 2 + 0.2);
    group.add(lamp);
  });
};

// Neon-Kanten für die Schilderbrücke: Cyan-Streifen unter den Panels, Pink
// an den Pfosten — sonst wirkt das braune Kenney-Schild nachts wie Pappe.
const addGantryNeon = (group, size) => {
  const cyan = track(new THREE.MeshBasicMaterial({ color: 0x35e0ff }));
  const pink = track(new THREE.MeshBasicMaterial({ color: 0xff4fd8 }));
  const strip = new THREE.Mesh(track(new THREE.BoxGeometry(size.w * 0.86, 0.09, 0.07)), cyan);
  strip.position.set(0, size.h * 0.05, size.d / 2 + 0.04);
  group.add(strip);
  const postGeo = track(new THREE.BoxGeometry(0.07, size.h * 0.48, 0.07));
  [-1, 1].forEach((side) => {
    const post = new THREE.Mesh(postGeo, pink);
    post.position.set(side * (size.w / 2 - 0.12), -size.h * 0.24, size.d / 2 + 0.04);
    group.add(post);
  });
};

const registerVehicleModel = (def, model) => {
  // Neon-Pass: Kenney-Texturen leicht selbstleuchten lassen — in der dunklen
  // Nachtszene saufen die Modelle sonst im Blauschwarz ab.
  model.traverse((node) => {
    if (node.isMesh && node.material && 'emissive' in node.material) {
      node.material.emissive = new THREE.Color(0xffffff);
      node.material.emissiveMap = node.material.map || null;
      node.material.emissiveIntensity = 0.25;
    }
  });
  if (def.rotateY) {
    model.rotation.y = def.rotateY;
  }
  const rawSize = new THREE.Box3().setFromObject(model).getSize(new THREE.Vector3());
  const scale =
    (def.fitLength
      ? def.fitLength / rawSize.z
      : def.fitWidth
        ? def.fitWidth / rawSize.x
        : def.fitHeight / rawSize.y) * (def.scaleMultiplier || 1);
  model.scale.setScalar(scale);
  if (def.scaleX) {
    // Nur schmaler machen (X), Höhe und Tiefe unverändert lassen. scaleX wirkt
    // auf die lokale Achse, deshalb vor einer etwaigen rotateY-Drehung denken.
    model.scale.x *= def.scaleX;
  }
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
    if (def.kind === 'car' || def.kind === 'tall') {
      addVehicleLights(mesh, size);
    }
    if (def.key === 'structure-metal') {
      addConstructionDressing(mesh, size);
    }
    if (def.key === 'sign-highway') {
      addGantryNeon(mesh, size);
    }
    return { mesh, size: { ...size } };
  };

  if (def.kind === 'car') {
    glbTraffic.car.push(def.key);
  } else if (def.kind === 'tall') {
    glbTraffic.tall.push(def.key);
  } else if (def.kind === 'set-piece' || def.kind === 'decor') {
    // Nicht in die normalen Spawn-Pools: Set-Teile kommen nur über
    // spawnConstructionSet, Deko nur über spawnSignGantry.
  } else {
    obstacleVariants.low.push(def.key);
  }

  // Showroom offen während ein Modell fertig lädt? Direkt neu aufbauen,
  // damit wirklich ALLE Hindernisse ausgestellt sind.
  if (state.value === 'gallery') {
    buildGallery();
  }
};

const loadVehicleModels = () => {
  const loader = new GLTFLoader();
  glbVehicleDefs.forEach((def) => {
    loader.load(
      `/models/${def.dir || 'carkit'}/${def.key}.glb`,
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
    return glbTraffic.tall.length ? pickFrom(glbTraffic.tall) : 'tall-stack';
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
  obstacle.userData.jam = false;
  obstacle.userData.decor = false;
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

// Handgebaute Hindernis-Sets: kleine Mini-Szenen in EINER Lane, Reihenfolge
// aus Spielersicht (z 0 = am nächsten). rotY kippt ein Teil dekorativ an —
// die Hitbox bleibt die achsenparallele Box des Templates.
const obstacleSetDefs = [
  {
    id: 'construction',
    pieces: [
      { key: 'construction-light', z: 0 },
      { key: 'resource-planks', z: -2.4 },
      { key: 'structure-metal', z: -5.0 },
    ],
  },
  {
    id: 'accident',
    // Unfallstelle: Pylone, quer geschleudertes Auto, Polizei dahinter.
    pieces: [
      { key: 'cone', z: 0 },
      { key: 'car-any', z: -2.8, rotY: 0.55 },
      { key: 'police', z: -5.8, rotY: -0.1 },
    ],
  },
];

const setPieceReady = (key) =>
  key === 'car-any' ? glbTraffic.car.length > 0 : !!obstacleBuilders[key];

const spawnObstacleSet = (laneIndex, baseZ) => {
  const ready = obstacleSetDefs.filter((set) => set.pieces.every((p) => setPieceReady(p.key)));
  if (!ready.length) return false;
  const set = pickFrom(ready);
  set.pieces.forEach((piece) => {
    const obstacle = getObstacle('tall', piece.key);
    const size = obstacle.userData.size;
    obstacle.rotation.y = piece.rotY || 0;
    obstacle.position.set(lanes[laneIndex], size.h / 2 + 0.02, baseZ + piece.z);
    obstacles.push(obstacle);
    scene.add(obstacle);
  });
  return true;
};

// Truck-Konvoi: ein Auto als "Treppe", dahinter zwei Box-Trucks — die hohe
// Dachlauf-Route. Vom Boden ist kein Truckdach erreichbar (Sprunghöhe ~1.9,
// Dach ~2.4+), deshalb führt der Einstieg IMMER über das Autodach.
const spawnTruckConvoy = (laneIndex, baseZ) => {
  const pieces = [
    { key: 'car-any', z: 0 },
    { key: 'truck', z: -2.9 },
    { key: 'truck', z: -6.2 },
  ];
  let roofY = 0;
  pieces.forEach((piece) => {
    const vehicle = getObstacle(piece.key === 'truck' ? 'tall' : 'low', piece.key);
    const size = vehicle.userData.size;
    vehicle.userData.jam = true;
    vehicle.rotation.y = Math.PI;
    vehicle.position.set(lanes[laneIndex], size.h / 2 + 0.02, baseZ + piece.z);
    roofY = Math.max(roofY, size.h + 0.02);
    obstacles.push(vehicle);
    scene.add(vehicle);
  });
  // Coins: Bogen aufs Autodach, dann entlang der Truck-Dachlinie.
  [
    { y: 1.2, z: 3.6 },
    { y: 2.3, z: 1.4 },
    { y: roofY + 1.3, z: -1.4 },
    { y: roofY + 1.4, z: -3.8 },
    { y: roofY + 1.3, z: -6.2 },
  ].forEach((spot) => {
    placeCoin(lanes[laneIndex], spot.y, baseZ + spot.z);
  });
};

// Mini-Stau: drei Autos Stoßstange an Stoßstange mit Trampolin-Dächern (wie
// Rush Hour) plus Coin-Spur, die aufs erste Dach und über die Dachlinie führt
// — macht Dach-Hüpfen zur normalen Route statt zum Sonderevent.
const spawnJamSet = (laneIndex, baseZ) => {
  let roofY = 0;
  for (let i = 0; i < 3; i += 1) {
    const vehicle = getObstacle('low', 'car-any');
    const size = vehicle.userData.size;
    vehicle.userData.jam = true;
    vehicle.rotation.y = Math.PI;
    vehicle.position.set(lanes[laneIndex], size.h / 2 + 0.02, baseZ - i * 2.8);
    roofY = Math.max(roofY, size.h + 0.02);
    obstacles.push(vehicle);
    scene.add(vehicle);
  }
  // Anlauf-Bogen aufs erste Dach, dann Coins über der Dachlinie.
  const trailY = [1.1, 2.0, roofY + 1.5, roofY + 1.6, roofY + 1.5];
  trailY.forEach((y, i) => {
    placeCoin(lanes[laneIndex], y, baseZ + 3.6 - i * 2.4);
  });
};

// Schilderbrücke: spannt mittig über die ganze Straße, keine Kollision —
// der Scroll-Loop bewegt und recycelt sie wie jedes andere Hindernis.
const spawnSignGantry = (z) => {
  const gantry = getObstacle('tall', 'sign-highway');
  gantry.userData.decor = true;
  gantry.position.set(0, gantry.userData.size.h / 2, z);
  obstacles.push(gantry);
  scene.add(gantry);
};

// Lane reservation (standard endless-runner technique): every moving
// vehicle owns its lane exclusively for its whole approach. Static rows
// never spawn into a reserved lane, and moving vehicles never spawn into a
// lane that has static obstacles ahead — nothing can clip through anything.
const movingBlockedLanes = () => {
  const blocked = new Set();
  obstacles.forEach((obstacle) => {
    const moving =
      (obstacle.userData.vz || 0) > 0 || obstacle.userData.driftTo !== undefined;
    if (!moving) return;
    lanes.forEach((laneX, index) => {
      if (Math.abs(obstacle.position.x - laneX) < 0.9) blocked.add(index);
    });
    if (obstacle.userData.driftTo !== undefined) {
      lanes.forEach((laneX, index) => {
        if (Math.abs(obstacle.userData.driftTo - laneX) < 0.9) blocked.add(index);
      });
    }
  });
  return blocked;
};

// Oncoming traffic: a single vehicle, only into a lane that is completely
// clear of static obstacles. Returns false when no lane qualifies.
const spawnOncoming = () => {
  const blocked = movingBlockedLanes();
  const laneIsClear = (index) =>
    !blocked.has(index) &&
    !obstacles.some(
      (obstacle) =>
        !obstacle.userData.decor &&
        (obstacle.userData.vz || 0) === 0 &&
        Math.abs(obstacle.position.x - lanes[index]) < 0.9 &&
        obstacle.position.z < 0,
    );
  const candidates = [0, 1, 2].filter(laneIsClear);
  if (!candidates.length) return false;

  const laneIndex = candidates[Math.floor(Math.random() * candidates.length)];
  const levelFactor = currentLevel.value.baseSpeed / 12;
  const isTruck = Math.random() < 0.14;
  const vehicle = getObstacle(isTruck ? 'tall' : 'low', isTruck ? 'tall-any' : 'car-any');
  vehicle.userData.vz = (isTruck ? 3 + Math.random() * 3 : 4 + Math.random() * 10) * levelFactor;
  if (vehicle.userData.beams) {
    vehicle.userData.beams.visible = true;
  }
  vehicle.rotation.y = 0;
  // Arrival-normalized depth: travel time to the player equals a normal
  // row's, so spawn cadence and arrival cadence stay identical.
  const rowDepth = 72 + Math.min(45, speed.value);
  const travelDepth = rowDepth * ((speed.value + vehicle.userData.vz) / speed.value);
  vehicle.position.set(
    lanes[laneIndex],
    vehicle.userData.size.h / 2 + 0.02,
    -travelDepth - Math.random() * 4,
  );
  obstacles.push(vehicle);
  scene.add(vehicle);
  return true;
};

const spawnRow = () => {
  // Occasionally oncoming traffic instead of a row — far less often than
  // before, and only when a fully clear lane exists for it.
  if (Math.random() < 0.12 && spawnOncoming()) {
    return;
  }

  // Never two fully blocked rows back to back: after a 3-lane wall the next
  // row always keeps a free lane, so steering is always an escape option.
  let pattern = rowPatterns[Math.floor(Math.random() * rowPatterns.length)];
  if (lastRowFull && !pattern.includes('none')) {
    const openPatterns = rowPatterns.filter((candidate) => candidate.includes('none'));
    pattern = openPatterns[Math.floor(Math.random() * openPatterns.length)];
  }
  // Lanes owned by moving vehicles stay empty in static rows.
  const blocked = movingBlockedLanes();
  if (blocked.size) {
    pattern = pattern.map((type, laneIndex) => (blocked.has(laneIndex) ? 'none' : type));
  }
  lastRowFull = !pattern.includes('none');
  // Spawn farther out the faster we go, so there is always time to react.
  const baseZ = -(70 + Math.min(45, speed.value));

  // Gelegentlich eine große Schilderbrücke als reine Deko über der Straße —
  // spannt alle Lanes, blockiert nichts.
  if (obstacleBuilders['sign-highway'] && Math.random() < 0.06) {
    spawnSignGantry(baseZ - 16);
  }

  let setSpawned = false;
  let jamSpawned = false;
  const arcLanes = [];
  pattern.forEach((type, laneIndex) => {
    if (type === 'none') return;
    // Themen-Sets statt einzelner Hindernisse: hohe Slots werden gelegentlich
    // zu Baustelle/Unfallstelle, niedrige zu einem Mini-Stau mit Trampolin-
    // Dächern. Max. ein Set pro Reihe.
    if (type === 'tall' && !setSpawned && Math.random() < 0.3) {
      // Ein Drittel der Sets ist der Truck-Konvoi (Dachlauf-Route mit
      // eigenen Coins), der Rest die statischen Themen-Sets.
      if (glbTemplates.truck && glbTraffic.car.length && Math.random() < 0.35) {
        spawnTruckConvoy(laneIndex, baseZ);
        setSpawned = true;
        jamSpawned = true;
        return;
      }
      if (spawnObstacleSet(laneIndex, baseZ)) {
        setSpawned = true;
        return;
      }
    }
    if (type === 'low' && !setSpawned && glbTraffic.car.length && Math.random() < 0.1) {
      spawnJamSet(laneIndex, baseZ);
      setSpawned = true;
      jamSpawned = true;
      return;
    }
    const obstacle = getObstacle(type);
    const size = obstacle.userData.size;
    const y = type === 'over' ? 1.55 : size.h / 2 + 0.02;
    obstacle.rotation.y = 0;
    obstacle.position.set(lanes[laneIndex], y, baseZ);
    obstacles.push(obstacle);
    scene.add(obstacle);
    if (type === 'low') {
      arcLanes.push(laneIndex);
    }
  });

  const freeLanes = pattern
    .map((type, laneIndex) => (type === 'none' ? laneIndex : -1))
    .filter((laneIndex) => laneIndex >= 0);
  // Höchstens EIN Coin-Feature pro Reihe: Jam-Set-Coins ODER Trail ODER
  // Sprungbogen — nie gestapelt, sonst wird es eine Münzflut.
  let coinLane = -1;
  if (!jamSpawned) {
    if (freeLanes.length && Math.random() < 0.7) {
      coinLane = freeLanes[Math.floor(Math.random() * freeLanes.length)];
      spawnCoinTrail(coinLane, baseZ);
    } else if (arcLanes.length && Math.random() < 0.5) {
      spawnCoinArc(pickFrom(arcLanes), baseZ);
    }
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

// Rush hour: a solid, tightly packed jam of mixed vehicles (cars, police,
// trucks, fire engines) filling ALL three lanes. There is no gap — the only
// way through is jumping onto the roofs and bouncing car to car. The first
// row is low cars so the entry jump always works from the ground; taller
// trucks appear deeper in, reachable from a car roof.
const spawnWaveRow = () => {
  const baseZ = -(95 + Math.min(45, speed.value));
  for (let laneIndex = 0; laneIndex < 3; laneIndex += 1) {
    const useTall = !trafficWave.first && Math.random() < 0.35;
    const vehicle = getObstacle(useTall ? 'tall' : 'low', useTall ? 'tall-any' : 'car-any');
    vehicle.userData.jam = true;
    vehicle.rotation.y = Math.PI;
    vehicle.position.set(
      lanes[laneIndex],
      vehicle.userData.size.h / 2 + 0.02,
      baseZ - Math.random() * 0.15,
    );
    obstacles.push(vehicle);
    scene.add(vehicle);
  }
  // Guide coins above the roofline sell the "go up" route.
  if (trafficWave.rowsLeft % 2 === 1) {
    const coin = getCoin();
    coin.position.set(lanes[Math.floor(Math.random() * 3)], 3.1, baseZ);
    coin.rotation.y = Math.random() * Math.PI;
    coins.push(coin);
    scene.add(coin);
  }
  trafficWave.first = false;
  trafficWave.rowsLeft -= 1;
  if (trafficWave.rowsLeft <= 0) {
    trafficWave.endZ = baseZ;
  } else {
    // Bumper to bumper: 2.8 units apart — even two kurze Sedans (2.7 tief)
    // überlappen dann entlang z, die Dachfläche ist lückenlos.
    trafficWave.rowTimer = 2.8 / speed.value;
  }
};

const startTrafficWave = () => {
  trafficWave = {
    rowsLeft: 9 + Math.min(4, Math.floor(score.value / 3000)),
    rowTimer: 0,
    endZ: null,
    first: true,
  };
  sfx.horn();
  showEventToast('Rush Hour', 'Jump the jam — roof to roof!');
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
const coinRushStep = 2.2;
const startCoinRush = () => {
  // Just behind the deepest possible normal row — safe, but no dead air.
  const baseZ = -(78 + Math.min(45, speed.value));
  const count = 14;
  // A single jump arc: two arcs on the shorter trail would sit too close
  // together to land and jump again at low speeds.
  const arcCenters = [7];
  const snapToLane = (x) =>
    lanes.reduce((best, lane) => (Math.abs(lane - x) < Math.abs(best - x) ? lane : best), lanes[0]);
  const arcX = arcCenters.map((c) => snapToLane(Math.sin(c * 0.45) * 2));
  // Bogen wie in spawnCoinArc entlang der echten Sprungparabel: Weite und
  // betroffene Coins skalieren mit dem Tempo statt fester ±2-Fenster.
  const arcSpan = speed.value * jumpAirtime;
  const arcCoinRadius = Math.ceil(arcSpan / 2 / coinRushStep);

  for (let k = 0; k < count; k += 1) {
    const arcIndex = arcCenters.findIndex((c) => Math.abs(k - c) <= arcCoinRadius);
    const f = arcIndex >= 0 ? 0.5 - ((k - arcCenters[arcIndex]) * coinRushStep) / arcSpan : 0;
    const x = arcIndex >= 0 ? arcX[arcIndex] : Math.sin(k * 0.45) * 2;
    const lift = f > 0 && f < 1 ? jumpApexLift * 4 * f * (1 - f) : 0;
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
  if (roll < 0.42) {
    startTrafficWave();
  } else if (roll < 0.62) {
    startWrongWayDriver();
  } else if (roll < 0.84) {
    startCoinRush();
  } else {
    startDriftCar();
  }
};

// Spawns only hold while an event's tail is still deeper than where new
// rows appear; once the tail is closer, fresh rows spawn safely behind it.
const eventSpawnHoldActive = () => {
  // Checkpoint breather: nothing spawns until the road has drained and the
  // speed step has been applied.
  if (checkpointPending) return true;
  // Speed-scaled margin: the landing arc after the final roof bounce must
  // never come down on a freshly spawned row. (0.6: Bounce ist jetzt 1.08x
  // jumpVelocity statt 0.95x, der Bogen fliegt entsprechend weiter.)
  const spawnDepth = -(70 + Math.min(45, speed.value)) + 6 + speed.value * 0.6;
  if (
    trafficWave &&
    (trafficWave.rowsLeft > 0 ||
      (trafficWave.endZ !== null && trafficWave.endZ < spawnDepth))
  ) {
    return true;
  }
  return coinRushEndZ !== null && coinRushEndZ < spawnDepth;
};

const updateZoneEvents = (delta) => {
  if (nextMilestone < FINALE_SCORE && score.value >= nextMilestone) {
    // Checkpoint reached: open the breather first. Spawns stop, remaining
    // traffic drains out; the speed step and the event come only once the
    // road ahead is clear.
    showEventToast(`Checkpoint ${nextMilestone.toLocaleString('en-US')}`, 'Safe zone ahead', 1800);
    sfx.powerup();
    spawnBurst(player.position.clone(), ['gold'], 10, 5);
    checkpointPending = {
      failsafe: 8,
      fireEvent: nextMilestone <= FINALE_SCORE - 2500,
    };
    nextMilestone += 2500;
  }
  if (checkpointPending) {
    checkpointPending.failsafe -= delta;
    const clearDepth = -(78 + Math.min(45, speed.value));
    const roadClear = !obstacles.some(
      (obstacle) =>
        obstacle.position.z < player.position.z + 2 && obstacle.position.z > clearDepth,
    );
    if (roadClear || checkpointPending.failsafe <= 0) {
      speedTier = Math.min(3, speedTier + 1);
      applyDistrict(districtIndex + 1);
      showEventToast('Speed up!', 'New tempo — go!', 1600);
      sfx.godMode();
      if (checkpointPending.fireEvent && !trafficWave && !finaleTriggered) {
        startRandomEvent();
      }
      checkpointPending = null;
    }
    return;
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
    }
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
  vibrate(35);
  nearMissCombo.value = 0;
  nearMissComboAt = -Infinity;
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

  // The getaway car, waiting with its lights on — the player's chosen ride.
  const chosenKey = (selectedCarSlug.value || '').replace(/^car-/, '');
  const template =
    glbTemplates[chosenKey] ||
    glbTemplates['sedan-sports'] ||
    glbTemplates.sedan ||
    glbTemplates.race;
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
  setMusicDuck(0.3);
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
  markZoneSeen(2);
  driveEventTimer = 10;
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
  setMusicDuck(0.6);
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

const buildParkedCar = () => {
  // Any ride will do when you're stealing it — pick a random template.
  const keys = Object.keys(glbTemplates);
  const template = keys.length
    ? glbTemplates[keys[Math.floor(Math.random() * keys.length)]]
    : null;
  if (template) {
    const car = template.model.clone(true);
    car.userData.restY = template.size.h / 2 + 0.12;
    return car;
  }
  const { mesh } = obstacleBuilders['low-car']();
  mesh.userData.restY = 0.57 + 0.12;
  return mesh;
};

// First Zone-2 crash: throw the character out of the wreck instead of ending
// the run. The wreck stays behind, a parked car waits at the left curb, and
// the god-mode hold progress is deliberately NOT reset.
const ejectFromCar = () => {
  driveLifeUsed = true;
  finalePhase.value = 'carjack';
  carjackPhase = 'eject';
  carjackTimer = 0.85;
  nearMissCombo.value = 0;
  nearMissComboAt = -Infinity;
  vibrate(90);
  sfx.crash();
  spawnBurst(player.position.clone(), ['red', 'dust', 'skin'], 26, 8);
  clearObstacles();

  // Leave the dead car behind as a tipped-over wreck.
  if (carVisual) {
    const wreckPosition = new THREE.Vector3();
    carVisual.getWorldPosition(wreckPosition);
    player.remove(carVisual);
    carVisual.position.copy(wreckPosition);
    carVisual.position.y = Math.max(0.5, wreckPosition.y);
    carVisual.rotation.set(0, Math.PI + 0.7, 0.55);
    scene.add(carVisual);
    carjackProps.push(carVisual);
    carVisual = null;
    carWheels = [];
  }

  // The character reappears and tumbles toward the left curb.
  player.visible = true;
  player.scale.y = 1;
  if (activeCharacter) {
    activeCharacter.root.visible = true;
  } else {
    proceduralParts.forEach((part) => {
      part.visible = true;
    });
  }
  currentSurfaceY = 0;
  currentGroundCenter = getGroundCenterForSurface(0, currentPlayerHeight());
  playerVelocityY = 0;
  carjackFrom.copy(player.position);
  carjackLand.set(carLanes[0] - 1.6, currentGroundCenter, player.position.z + 1.2);

  // The getaway ride, parked at the left curb a few meters ahead.
  carjackCar = buildParkedCar();
  carjackCar.position.set(-7.2, carjackCar.userData.restY, player.position.z - 9);
  carjackCar.rotation.y = Math.PI;
  scene.add(carjackCar);

  driveHintText.value = 'Wrecked! Steal the parked car on the left — the next crash is final.';
  driveHint.value = true;
  if (driveHintTimer) {
    clearTimeout(driveHintTimer);
  }
  driveHintTimer = setTimeout(() => {
    driveHint.value = false;
  }, 4200);
};

const enterStolenCar = () => {
  sfx.door();
  carVisual = carjackCar;
  carjackCar = null;
  scene.remove(carVisual);
  carVisual.position.set(0, 0, 0);
  carVisual.rotation.set(0, Math.PI, 0);
  player.add(carVisual);
  carWheels = [];
  carVisual.traverse((node) => {
    if (node.name && node.name.startsWith('wheel')) {
      carWheels.push(node);
    }
  });
  if (activeCharacter) {
    activeCharacter.root.visible = false;
  }
  proceduralParts.forEach((part) => {
    part.visible = false;
  });
  player.rotation.z = 0;
  player.position.y = carPlayerSize.h / 2 + 0.02;
  currentGroundCenter = player.position.y;
  playerVelocityY = 0;
  currentLane = 2;
  laneOrigin = 2;
  finalePhase.value = 'drive';
  speed.value = 2;
  driveTargetSpeed = 24;
  driveSpawnTimer = 1.8;
  const now = performance.now();
  bumpProtectUntil = now + 2500;
  invulnUntil = now + 2500;
  sfx.engineStart();
  showEventToast('Ride stolen!', 'Last chance — keep it on the road.', 2600);
};

const updateCarjack = (delta) => {
  // The road grinds to a halt while the character is on foot.
  speed.value = THREE.MathUtils.damp(speed.value, 0, 6, delta);
  driveTargetSpeed = 0;

  if (carjackPhase === 'eject') {
    carjackTimer -= delta;
    const p = 1 - Math.max(0, carjackTimer) / 0.85;
    player.position.x = THREE.MathUtils.lerp(carjackFrom.x, carjackLand.x, p);
    player.position.z = THREE.MathUtils.lerp(carjackFrom.z, carjackLand.z, p);
    player.position.y = currentGroundCenter + Math.sin(p * Math.PI) * 1.5;
    player.rotation.z = Math.sin(p * Math.PI) * 0.9;
    if (carjackTimer <= 0) {
      carjackPhase = 'run';
      player.rotation.z = 0;
      player.position.y = currentGroundCenter;
      sfx.land();
      spawnBurst(player.position.clone(), ['dust'], 8, 4);
    }
  } else if (carjackCar) {
    // Sprint to the driver's door, same beat as the plaza walk-up.
    const targetX = carjackCar.position.x + 1.4;
    const targetZ = carjackCar.position.z + 1.6;
    player.position.x = THREE.MathUtils.damp(player.position.x, targetX, 5, delta);
    player.position.z = Math.max(targetZ, player.position.z - 4.2 * delta);
    if (activeCharacter) {
      setCharacterAction('run');
      if (activeCharacter.actions.run) {
        activeCharacter.actions.run.paused = false;
      }
      activeCharacter.mixer.timeScale = 1.0;
      activeCharacter.mixer.update(delta);
    }
    if (player.position.z <= targetZ + 0.05 && Math.abs(player.position.x - targetX) < 0.5) {
      enterStolenCar();
    }
  }

  camera.position.x = THREE.MathUtils.damp(camera.position.x, player.position.x * 0.3, 4, delta);
  camera.position.y = THREE.MathUtils.damp(camera.position.y, 3.2, 2.5, delta);
  camera.position.z = THREE.MathUtils.damp(camera.position.z, player.position.z + 7.5, 2.5, delta);
  lookAtTarget.set(player.position.x * 0.6, 1.0, player.position.z - 8);
  camera.lookAt(lookAtTarget);
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

// Zone-2 events: a hot-shot overtaker blasting past from behind, or a slow
// truck convoy blocking a forward lane.
const startDriveOvertaker = () => {
  const laneChoices = [0, 1, 2, 3].filter((index) => index !== currentLane);
  const laneIndex = laneChoices[Math.floor(Math.random() * laneChoices.length)];
  const vehicle = getObstacle('low', 'car-any');
  // Own speed beats the world scroll, so it pulls away toward the horizon.
  vehicle.userData.vz = -(speed.value + 16 + Math.random() * 12);
  vehicle.rotation.y = Math.PI;
  if (vehicle.userData.beams) {
    vehicle.userData.beams.visible = false;
  }
  vehicle.position.set(
    carLanes[laneIndex],
    vehicle.userData.size.h / 2 + 0.02,
    player.position.z + 12,
  );
  obstacles.push(vehicle);
  scene.add(vehicle);
  sfx.horn();
};

const startDriveConvoy = () => {
  const laneIndex = 2 + Math.floor(Math.random() * 2);
  const ownSpeed = 4 + Math.random() * 3;
  for (let k = 0; k < 3; k += 1) {
    const vehicle = getObstacle('tall', 'tall-any');
    vehicle.userData.vz = -ownSpeed;
    vehicle.rotation.y = Math.PI;
    if (vehicle.userData.beams) {
      vehicle.userData.beams.visible = false;
    }
    vehicle.position.set(
      carLanes[laneIndex],
      vehicle.userData.size.h / 2 + 0.02,
      -(150 + Math.min(150, speed.value * 0.9)) - k * 14,
    );
    obstacles.push(vehicle);
    scene.add(vehicle);
  }
  showEventToast('Convoy', 'Heavy loads ahead — swing wide!', 1600);
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
  smashStreak += 1;
  if (smashStreak % 5 === 0) {
    score.value += 150;
    showEventToast(`Smash x${smashStreak}`, '+150 bonus', 1400);
  }
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
  smashStreak = 0;
  vibrate([40, 40, 80]);
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
  if (edgeLineMaterial && target.edge !== undefined) {
    edgeLineMaterial.color.lerp(tmpEnvColor.set(target.edge), k);
  }
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
  if (edgeLineMaterial && target.edge !== undefined) {
    edgeLineMaterial.color.set(target.edge);
  }
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

// Solar eclipse for the mothership's final phase: the day palette collapses
// into violet twilight and the ocean sun becomes a black disc with a corona.
// applyEnvironment lerps toward envSettings every frame, so mutating the day
// palette fades the whole scene smoothly (same pattern as applyDistrict).
let sunMesh = null;
let sunGlowMesh = null;
let eclipseActive = false;
let dayEnvBackup = null;

const setEclipse = (active) => {
  if (eclipseActive === active) return;
  eclipseActive = active;
  if (active) {
    dayEnvBackup = { ...envSettings.day };
    Object.assign(envSettings.day, {
      bg: 0x120e22,
      fog: 0x1a1230,
      hemi: 1.0,
      dir: 0.9,
      hemiSky: 0x8f7ad0,
      dirColor: 0xcaa8ff,
    });
  } else if (dayEnvBackup) {
    Object.assign(envSettings.day, dayEnvBackup);
    dayEnvBackup = null;
  }
  if (sunMesh) {
    sunMesh.material.color.set(active ? 0x07070e : 0xffc46a);
    sunGlowMesh.material.color.set(active ? 0xd8e2ff : 0xff9a50);
    sunGlowMesh.material.opacity = active ? 0.7 : 0.3;
  }
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
  sunMesh = sun;
  sunGlowMesh = sunGlow;

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
      hugeBoltGeo: new THREE.SphereGeometry(1.5, 12, 10),
      hugeBoltMat: new THREE.MeshBasicMaterial({ color: 0xff2e4d }),
      missileGeo: new THREE.BoxGeometry(0.34, 0.34, 1.2),
      missileMat: new THREE.MeshBasicMaterial({ color: 0xffa22e }),
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
  // Kept for phase tinting: the hull recolors as the fight escalates.
  ship.userData.phaseMats = {
    hull: hull.material,
    belly: belly.material,
    rim: rim.material,
    dome: dome.material,
    emitter: emitter.material,
  };
  return ship;
};

// Mothership fight phases. Stage 2 (<50% HP) turns the hull ember-orange and
// speeds up attacks; stage 3 (<10% HP) goes near-black with red glow, grants
// 10s of invulnerability, then unleashes the eclipse barrage of huge orbs.
const motherStageColors = {
  1: { hull: 0x565f7d, belly: 0x3f4763, rim: 0x2c3350, dome: 0x7df9d0, domeGlow: 0x2c8a6e, emitter: 0x9b59d0, shield: 0x7df9d0 },
  2: { hull: 0x94502e, belly: 0x6e3a20, rim: 0x4a2312, dome: 0xffb066, domeGlow: 0xa04a1a, emitter: 0xff7a2e, shield: 0xffa22e },
  3: { hull: 0x36080f, belly: 0x24060a, rim: 0x160306, dome: 0xff3b57, domeGlow: 0xaa1020, emitter: 0xff2244, shield: 0xff3b57 },
};

const setMotherStage = (ship, stage) => {
  const colors = motherStageColors[stage];
  const mats = ship.userData.phaseMats;
  if (!colors || !mats) return;
  mats.hull.color.set(colors.hull);
  mats.belly.color.set(colors.belly);
  mats.rim.color.set(colors.rim);
  mats.dome.color.set(colors.dome);
  mats.dome.emissive.set(colors.domeGlow);
  mats.emitter.color.set(colors.emitter);
  mats.emitter.emissive.set(colors.emitter);
  ship.userData.shieldMesh.material.color.set(colors.shield);
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
  ship.userData.stage = 1;
  ship.userData.invulnTimer = 0;
  ship.userData.hugeTimer = 0;
  setMotherStage(ship, 1);
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

const fireEnemyBolt = (enemy, targetPoint = null, opts = {}) => {
  const assets = getEnemyAssets();
  const isMother = enemy.userData.kind === 'mother';
  const huge = !!opts.huge;
  let bolt = enemyBoltPool.pop();
  if (!bolt) {
    bolt = new THREE.Mesh(assets.enemyBoltGeo, assets.eyeMat);
  }
  bolt.geometry = huge ? assets.hugeBoltGeo : isMother ? assets.motherBoltGeo : assets.enemyBoltGeo;
  bolt.material = huge ? assets.hugeBoltMat : isMother ? assets.motherBoltMat : assets.eyeMat;
  bolt.position.copy(enemy.position);
  bolt.position.z += isMother ? 4 : 2.5;
  const target = targetPoint || player.position;
  const dir = new THREE.Vector3()
    .subVectors(target, bolt.position)
    .normalize()
    .multiplyScalar(huge ? 38 : isMother ? 46 : 42);
  bolt.userData.vel = dir;
  bolt.userData.life = 6;
  bolt.userData.mother = isMother;
  bolt.userData.huge = huge;
  bolt.userData.grazed = false;
  bolt.userData.dmg = huge ? 26 : isMother ? 20 : 18;
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
  // The pool is shared with homing missiles, so geometry/material/userData
  // must be reassigned on every spawn.
  const spawnBolt = (offsetX, vx) => {
    let bolt = projectilePool.pop();
    if (!bolt) {
      bolt = new THREE.Mesh(assets.boltGeo, assets.boltMat);
    }
    bolt.geometry = assets.boltGeo;
    bolt.material = assets.boltMat;
    bolt.rotation.set(0, 0, 0);
    bolt.userData.homing = false;
    bolt.userData.dmg = 1;
    bolt.userData.vx = vx;
    bolt.position.set(player.position.x + offsetX, player.position.y - 0.2, player.position.z - 2.2);
    projectiles.push(bolt);
    scene.add(bolt);
  };
  spawnBolt(-1.1, 0);
  spawnBolt(1.1, 0);
  if (spreadFireTime > 0) {
    spawnBolt(-1.6, -34);
    spawnBolt(1.6, 34);
  }
  sfx.shoot();
};

// Homing missile: launches loose, then steers toward the current enemy.
const fireHomingMissile = () => {
  const assets = getEnemyAssets();
  let missile = projectilePool.pop();
  if (!missile) {
    missile = new THREE.Mesh(assets.missileGeo, assets.missileMat);
  }
  missile.geometry = assets.missileGeo;
  missile.material = assets.missileMat;
  missile.userData.homing = true;
  missile.userData.dmg = 8;
  missile.userData.vx = 0;
  missile.userData.vel = new THREE.Vector3((Math.random() - 0.5) * 30, 12, -30);
  missile.position.set(player.position.x, player.position.y - 0.8, player.position.z - 2);
  projectiles.push(missile);
  scene.add(missile);
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

const planeDefs = [
  { key: 'cesium', file: 'cesium-air.glb' },
  { key: 'jet', file: 'sky-jet.glb' },
  { key: 'prop', file: 'prop-plane.glb' },
];

const loadPlaneModel = () => {
  const loader = new GLTFLoader();
  planeDefs.forEach((def) => {
    loader.load(
      `/models/plane/${def.file}`,
      (gltf) => {
        const model = gltf.scene;
        const rawSize = new THREE.Box3().setFromObject(model).getSize(new THREE.Vector3());
        const scale = 5.5 / Math.max(rawSize.z, rawSize.x);
        model.scale.setScalar(scale);
        const box = new THREE.Box3().setFromObject(model);
        model.position.sub(box.getCenter(new THREE.Vector3()));
        planeTemplates[def.key] = { model, clips: gltf.animations };
      },
      undefined,
      () => {
        if (def.key === 'cesium') {
          planeTemplates.cesium = { model: buildFallbackPlaneModel(), clips: [] };
        }
      },
    );
  });
};

const activePlaneTemplate = () => {
  const key = (selectedPlaneSlug.value || '').replace(/^plane-/, '');
  return planeTemplates[key] || planeTemplates.cesium || null;
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
  setMusicDuck(0.45);
};

const buildDockingPlane = () => {
  dockingPlane = new THREE.Group();
  const template = activePlaneTemplate();
  if (template) {
    template.model.rotation.set(0, Math.PI, 0);
    dockingPlane.add(template.model);
    if (template.clips.length) {
      planeMixer = new THREE.AnimationMixer(template.model);
      template.clips.forEach((clip) => planeMixer.clipAction(clip).play());
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
  markZoneSeen(3);
  sfx.dock();
  if (carVisual) {
    player.remove(carVisual);
    carVisual = null;
  }
  carWheels = [];
  godModeActive.value = false;
  godHoldTimer = 0;
  godHoldProgress.value = 0;
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
  setMusicDuck(0.85);
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

// Zone-3 pickups: repair kits and rapid-fire stars floating in the flight path.
let skyPickups = [];
let skyPickupTimer = 7;
let rapidFireTime = 0;
let spreadFireTime = 0;
let homingAmmo = 0;
let homingTimer = 0;
const tmpHomingDir = new THREE.Vector3();
let skyPickupAssets = null;

const getSkyPickupAssets = () => {
  if (skyPickupAssets) return skyPickupAssets;
  skyPickupAssets = {
    geo: new THREE.OctahedronGeometry(0.75),
    heal: new THREE.MeshStandardMaterial({
      color: 0x35ff88,
      emissive: 0x1fbb5e,
      emissiveIntensity: 1.4,
    }),
    rapid: new THREE.MeshStandardMaterial({
      color: 0xffd24d,
      emissive: 0xcc9a1f,
      emissiveIntensity: 1.6,
    }),
    spread: new THREE.MeshStandardMaterial({
      color: 0xc06bff,
      emissive: 0x8a2fd0,
      emissiveIntensity: 1.6,
    }),
    homing: new THREE.MeshStandardMaterial({
      color: 0x4dd8ff,
      emissive: 0x1f8acc,
      emissiveIntensity: 1.6,
    }),
  };
  return skyPickupAssets;
};

const spawnSkyPickup = () => {
  const assets = getSkyPickupAssets();
  const roll = Math.random();
  const type = roll < 0.4 ? 'heal' : roll < 0.65 ? 'rapid' : roll < 0.85 ? 'spread' : 'homing';
  const mesh = new THREE.Mesh(assets.geo, assets[type]);
  mesh.userData.type = type;
  mesh.position.set(
    THREE.MathUtils.randFloat(-11, 11),
    THREE.MathUtils.randFloat(5, 18),
    -220,
  );
  skyPickups.push(mesh);
  scene.add(mesh);
};

const clearSkyPickups = () => {
  skyPickups.forEach((pickup) => scene.remove(pickup));
  skyPickups = [];
};

const updateSkyPickups = (delta) => {
  skyPickupTimer -= delta;
  if (skyPickupTimer <= 0) {
    spawnSkyPickup();
    skyPickupTimer = THREE.MathUtils.randFloat(6, 10);
  }
  if (rapidFireTime > 0) {
    rapidFireTime -= delta;
  }
  if (spreadFireTime > 0) {
    spreadFireTime -= delta;
  }
  for (let i = skyPickups.length - 1; i >= 0; i -= 1) {
    const pickup = skyPickups[i];
    pickup.position.z += (speed.value + 18) * delta;
    pickup.rotation.y += delta * 3;
    pickup.rotation.x += delta * 1.5;
    if (pickup.position.z > 15) {
      scene.remove(pickup);
      skyPickups.splice(i, 1);
      continue;
    }
    const dx = pickup.position.x - player.position.x;
    const dy = pickup.position.y - player.position.y;
    const dz = pickup.position.z - player.position.z;
    if (dx * dx + dy * dy + dz * dz < 7.5) {
      if (pickup.userData.type === 'heal') {
        playerHp.value = Math.min(100, playerHp.value + 25);
        showEventToast('Repair Kit', '+25 HP', 1200);
      } else if (pickup.userData.type === 'rapid') {
        rapidFireTime = 6;
        showEventToast('Rapid Fire', 'Six seconds of fury!', 1200);
      } else if (pickup.userData.type === 'spread') {
        spreadFireTime = 8;
        showEventToast('Spread Shot', 'Wide fire for eight seconds!', 1200);
      } else {
        homingAmmo = Math.min(homingAmmo + 6, 12);
        showEventToast('Homing Missiles', '+6 seeker missiles', 1200);
      }
      sfx.powerup();
      spawnBurst(pickup.position, ['gold', 'skin'], 10, 6);
      scene.remove(pickup);
      skyPickups.splice(i, 1);
    }
  }
};

// ---------------------------------------------------------------------------
// Zone 4 — the void. The dying mothership detonates in slow motion, its wreck
// collapses into a black hole and the plane is dragged through the wormhole
// into a deep-space asteroid field: indestructible rocks — dodge or die.
// ---------------------------------------------------------------------------

const spawnShockRing = (position, color, grow) => {
  const ring = new THREE.Mesh(
    new THREE.RingGeometry(0.85, 1.1, 48),
    new THREE.MeshBasicMaterial({
      color,
      transparent: true,
      opacity: 0.95,
      fog: false,
      side: THREE.DoubleSide,
      depthWrite: false,
      blending: THREE.AdditiveBlending,
    }),
  );
  ring.position.copy(position);
  ring.userData.grow = grow;
  shockRings.push(ring);
  scene.add(ring);
};

const buildBlackHole = (position) => {
  blackHole = new THREE.Group();
  const core = new THREE.Mesh(
    new THREE.SphereGeometry(3, 24, 18),
    new THREE.MeshBasicMaterial({ color: 0x000000, fog: false }),
  );
  blackHole.add(core);
  const diskMat = {
    transparent: true,
    fog: false,
    side: THREE.DoubleSide,
    depthWrite: false,
    blending: THREE.AdditiveBlending,
  };
  const disk = new THREE.Mesh(
    new THREE.RingGeometry(3.3, 5.4, 56),
    new THREE.MeshBasicMaterial({ ...diskMat, color: 0xcaa8ff, opacity: 0.85 }),
  );
  blackHole.add(disk);
  const rim = new THREE.Mesh(
    new THREE.RingGeometry(5.6, 6.2, 56),
    new THREE.MeshBasicMaterial({ ...diskMat, color: 0xffd9a8, opacity: 0.5 }),
  );
  blackHole.add(rim);
  const glow = new THREE.Mesh(
    new THREE.CircleGeometry(8, 40),
    new THREE.MeshBasicMaterial({
      color: 0x8f7ad0,
      transparent: true,
      opacity: 0.22,
      fog: false,
      depthWrite: false,
      blending: THREE.AdditiveBlending,
    }),
  );
  glow.position.z = -0.5;
  blackHole.add(glow);
  blackHole.position.copy(position);
  blackHole.scale.setScalar(0.001);
  scene.add(blackHole);
};

const clearVoidObjects = () => {
  shockRings.forEach((ring) => {
    scene.remove(ring);
    ring.geometry.dispose();
    ring.material.dispose();
  });
  shockRings = [];
  wreckDebris.forEach((chunk) => scene.remove(chunk));
  wreckDebris = [];
  if (blackHole) {
    scene.remove(blackHole);
    blackHole.traverse((node) => {
      if (node.isMesh) {
        node.geometry?.dispose();
        node.material?.dispose();
      }
    });
    blackHole = null;
  }
  asteroids.forEach((rock) => {
    scene.remove(rock);
    asteroidPool.push(rock);
  });
  asteroids = [];
  collapseTimer = 0;
  whiteFlash.value = 0;
};

const getDebrisAssets = () => {
  if (!debrisAssets) {
    debrisAssets = {
      geo: new THREE.BoxGeometry(1, 1, 1),
      mat: new THREE.MeshLambertMaterial({ color: 0x2c3242 }),
    };
  }
  return debrisAssets;
};

const triggerMotherCollapse = (position) => {
  finalePhase.value = 'collapse';
  collapseTimer = 0;
  clearEnemies();
  clearEnemyBolts();
  clearSkyPickups();
  planeKeys.up = false;
  planeKeys.down = false;
  planeKeys.left = false;
  planeKeys.right = false;
  joyVec = { x: 0, y: 0 };
  joyKnob.value = { x: 0, y: 0 };
  driveHint.value = false;
  // The blast itself: white-out, shockwaves, wreck debris. updateCollapse
  // plays the first beat in slow motion.
  whiteFlash.value = 1;
  bumpShakeTimer = 1.1;
  sfx.smash();
  sfx.enemyDown();
  spawnBurst(position, ['red', 'gold', 'flame'], 50, 18);
  spawnBurst(position, ['dust', 'gold'], 36, 12);
  spawnShockRing(position, 0xffd9a8, 34);
  spawnShockRing(position, 0xff6a3a, 26);
  spawnShockRing(position, 0xcaa8ff, 44);
  const assets = getDebrisAssets();
  for (let i = 0; i < 16; i += 1) {
    const chunk = new THREE.Mesh(assets.geo, assets.mat);
    chunk.position.copy(position);
    chunk.scale.set(
      0.5 + Math.random() * 1.6,
      0.4 + Math.random() * 1.2,
      0.5 + Math.random() * 1.8,
    );
    chunk.userData.velocity = new THREE.Vector3(
      (Math.random() - 0.5) * 26,
      (Math.random() - 0.35) * 20,
      (Math.random() - 0.5) * 22,
    );
    chunk.userData.spin = new THREE.Vector3(
      (Math.random() - 0.5) * 8,
      (Math.random() - 0.5) * 8,
      (Math.random() - 0.5) * 8,
    );
    wreckDebris.push(chunk);
    scene.add(chunk);
  }
  buildBlackHole(position);
  setMusicDuck(0.3);
  showEventToast('MOTHERSHIP DESTROYED', 'Its core is collapsing…', 2600);
};

const updateCollapse = (delta) => {
  collapseTimer += delta;
  const t = collapseTimer;
  // The first beat of the detonation plays in slow motion.
  const world = t < 0.9 ? delta * 0.3 : delta;

  speed.value = THREE.MathUtils.damp(speed.value, 8, 1.4, delta);
  updateEngineSound();
  updateOceanMotion(world);
  planeMixer?.update(world);

  if (t < 3.2) {
    // The blast white-out fades…
    whiteFlash.value = Math.max(0, whiteFlash.value - delta * 1.5);
  } else {
    // …and the wormhole swallows the screen right before zone 4.
    whiteFlash.value = Math.min(1, whiteFlash.value + delta * 2.6);
  }

  // Secondary detonations while the wreck breaks apart.
  if (t < 1.1 && blackHole && Math.random() < 0.4) {
    spawnBurst(
      new THREE.Vector3(
        blackHole.position.x + (Math.random() - 0.5) * 10,
        blackHole.position.y + (Math.random() - 0.5) * 6,
        blackHole.position.z + (Math.random() - 0.5) * 6,
      ),
      ['flame', 'gold', 'red'],
      6,
      14,
    );
  }

  for (let i = shockRings.length - 1; i >= 0; i -= 1) {
    const ring = shockRings[i];
    const s = ring.scale.x + ring.userData.grow * world;
    ring.scale.set(s, s, s);
    ring.material.opacity -= world * 0.5;
    if (ring.material.opacity <= 0) {
      scene.remove(ring);
      ring.geometry.dispose();
      ring.material.dispose();
      shockRings.splice(i, 1);
    }
  }

  // Debris flies free, then spirals into the growing hole.
  const sucking = t > 1.5 && blackHole;
  for (let i = wreckDebris.length - 1; i >= 0; i -= 1) {
    const chunk = wreckDebris[i];
    if (sucking) {
      tmpHomingDir.subVectors(blackHole.position, chunk.position);
      const dist = tmpHomingDir.length();
      tmpHomingDir.normalize().multiplyScalar(60);
      chunk.userData.velocity.x = THREE.MathUtils.damp(chunk.userData.velocity.x, tmpHomingDir.x, 2.5, world);
      chunk.userData.velocity.y = THREE.MathUtils.damp(chunk.userData.velocity.y, tmpHomingDir.y, 2.5, world);
      chunk.userData.velocity.z = THREE.MathUtils.damp(chunk.userData.velocity.z, tmpHomingDir.z, 2.5, world);
      if (dist < 3.5 * blackHole.scale.x) {
        scene.remove(chunk);
        wreckDebris.splice(i, 1);
        continue;
      }
    }
    chunk.position.addScaledVector(chunk.userData.velocity, world);
    chunk.rotation.x += chunk.userData.spin.x * world;
    chunk.rotation.y += chunk.userData.spin.y * world;
    chunk.rotation.z += chunk.userData.spin.z * world;
  }

  if (blackHole) {
    if (t > 0.8) {
      blackHole.scale.setScalar(Math.max(0.001, Math.min(1.25, ((t - 0.8) / 1.4) * 1.25)));
    }
    blackHole.rotation.z += world * 2.4;
  }

  if (t > 1.2 && envMode !== 'void') {
    // The eclipse sky bleeds into deep space while the hole grows.
    envMode = 'void';
  }

  if (t > 1.6 && blackHole) {
    // The hole reels the plane in; the airframe starts to spiral.
    player.position.x = THREE.MathUtils.damp(player.position.x, blackHole.position.x, 1.4, delta);
    player.position.y = THREE.MathUtils.damp(player.position.y, Math.max(5, blackHole.position.y), 1.4, delta);
    blackHole.position.z = THREE.MathUtils.damp(blackHole.position.z, player.position.z - 10, 0.55, delta);
    bumpShakeTimer = Math.max(bumpShakeTimer, 0.25);
    if (planeVisual) {
      planeVisual.rotation.z += delta * Math.min(6, (t - 1.6) * 2.2);
    }
  }

  camera.position.x = THREE.MathUtils.damp(camera.position.x, player.position.x * 0.5, 4, delta);
  camera.position.y = THREE.MathUtils.damp(camera.position.y, player.position.y + 3.2, 4, delta);
  camera.position.z = THREE.MathUtils.damp(camera.position.z, player.position.z + 10, 4, delta);
  camera.fov = THREE.MathUtils.damp(camera.fov, t > 1.6 ? 74 : 58, 2.5, delta);
  camera.updateProjectionMatrix();
  if (blackHole) {
    lookAtTarget.set(
      (player.position.x + blackHole.position.x) / 2,
      (player.position.y + blackHole.position.y) / 2,
      blackHole.position.z,
    );
  } else {
    lookAtTarget.set(player.position.x * 0.85, player.position.y + 0.3, player.position.z - 12);
  }
  camera.lookAt(lookAtTarget);

  if (t >= 4.1) {
    enterVoid();
  }
};

const enterVoid = () => {
  clearVoidObjects();
  finalePhase.value = 'void';
  // The sunrise world stays behind the wormhole.
  if (oceanGroup) oceanGroup.visible = false;
  terrainChunks.forEach((chunk) => {
    chunk.visible = false;
  });
  clouds.forEach((cloud) => {
    cloud.visible = false;
  });
  if (starPoints) starPoints.visible = true;
  envMode = 'void';
  applyEnvironmentNow();
  // Drop out of the wormhole flash into the starfield.
  whiteFlash.value = 1;
  playerHp.value = Math.min(100, playerHp.value + 30);
  player.position.set(0, 10, 2);
  if (planeVisual) {
    planeVisual.rotation.set(0, 0, 0);
  }
  planeVelX = 0;
  planeVelY = 0;
  asteroidSpawnTimer = 1.2;
  // Pre-seed the belt so the zone doesn't open with empty space — push the
  // seeded rocks deeper out so the player still gets a beat to orient.
  for (let i = 0; i < 14; i += 1) {
    spawnAsteroid();
    const seeded = asteroids[asteroids.length - 1];
    if (seeded) {
      seeded.position.z -= 60 + Math.random() * 220;
    }
  }
  fireTimer = 0.4;
  setMusicDuck(0.7);
  sfx.dock();
  showEventToast('ZONE 4 — THE VOID', 'Deep space. The rocks are indestructible — weave through!', 3200);
};

// A real belt: lots of small debris, mid rocks and rare colossal boulders.
// Every rock is pure terrain — immune to weapons fire. Bolts spark off the
// surface without effect; weaving through is the only way past.
const asteroidTiers = [
  { radius: 0.9, dmg: 12 },
  { radius: 1.4, dmg: 18 },
  { radius: 2.3, dmg: 32 },
  { radius: 3.4, dmg: 50 },
  { radius: 6.2, dmg: 75 },
];

const spawnAsteroid = () => {
  const roll = Math.random();
  // Mostly small debris, a scattering of mid rocks, and the occasional wall.
  const tier = roll < 0.38 ? asteroidTiers[0]
    : roll < 0.66 ? asteroidTiers[1]
      : roll < 0.83 ? asteroidTiers[2]
        : roll < 0.93 ? asteroidTiers[3]
          : asteroidTiers[4];
  let rock = asteroidPool.pop();
  if (!rock) {
    if (!asteroidGeometry) {
      asteroidGeometry = new THREE.DodecahedronGeometry(1, 0);
    }
    // Each rock owns its material so the hit flash never bleeds to others.
    rock = new THREE.Mesh(
      asteroidGeometry,
      new THREE.MeshLambertMaterial({ color: 0x8a8296, emissive: 0x1a1424 }),
    );
  }
  const ud = rock.userData;
  const size = tier.radius * (0.85 + Math.random() * 0.3);
  ud.tier = tier;
  ud.radius = size;
  ud.grazed = false;
  ud.hitFlash = 0;
  ud.spin = new THREE.Vector3(
    (Math.random() - 0.5) * 1.6,
    (Math.random() - 0.5) * 1.6,
    (Math.random() - 0.5) * 1.6,
  );
  // Big rocks fly straight and predictable — dodging them stays fair; the
  // small debris is what tumbles and drifts around.
  const driftScale = Math.min(1, 2.2 / size);
  ud.driftX = (Math.random() - 0.5) * 3 * driftScale;
  ud.driftY = (Math.random() - 0.5) * 2 * driftScale;
  rock.scale.setScalar(size);
  rock.material.emissive.setHex(0x1a1424);
  rock.position.set(
    (Math.random() - 0.5) * 32,
    4 + Math.random() * 16,
    -250 - Math.random() * 60,
  );
  rock.rotation.set(Math.random() * Math.PI, Math.random() * Math.PI, 0);
  asteroids.push(rock);
  scene.add(rock);
};

const recycleAsteroid = (index) => {
  const rock = asteroids[index];
  scene.remove(rock);
  asteroidPool.push(rock);
  asteroids.splice(index, 1);
};

const updateVoid = (delta) => {
  // Movement mirrors zone 3, slightly faster and with more vertical room.
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

  speed.value = THREE.MathUtils.damp(speed.value, 48 - inputY * 6, 2, delta);
  updateEngineSound();
  score.value += speed.value * delta * 2.4;

  whiteFlash.value = Math.max(0, whiteFlash.value - delta * 1.1);

  planeVelX = THREE.MathUtils.damp(planeVelX, inputX * 22, 6, delta);
  planeVelY = THREE.MathUtils.damp(planeVelY, inputY * 14, 6, delta);
  player.position.x = THREE.MathUtils.clamp(player.position.x + planeVelX * delta, -13, 13);
  player.position.y = THREE.MathUtils.clamp(player.position.y + planeVelY * delta, 3, 21);
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
    planeVisual.position.y = Math.sin(performance.now() * 0.0016) * 0.15;
  }
  player.rotation.x = THREE.MathUtils.damp(player.rotation.x, 0, 4, delta);
  planeMixer?.update(delta);

  if (starPoints) {
    starPoints.rotation.z += delta * 0.008;
  }

  updateSkyPickups(delta);

  fireTimer -= delta;
  if (fireTimer <= 0) {
    fireProjectiles();
    fireTimer = rapidFireTime > 0 ? 0.09 : 0.18;
  }

  // Straight shots only — there are no lock-on targets out here.
  for (let i = projectiles.length - 1; i >= 0; i -= 1) {
    const bolt = projectiles[i];
    bolt.position.z -= 150 * delta;
    bolt.position.x += (bolt.userData.vx || 0) * delta;
    if (bolt.position.z < -280 || bolt.position.y > 60 || Math.abs(bolt.position.x) > 70) {
      scene.remove(bolt);
      projectilePool.push(bolt);
      projectiles.splice(i, 1);
    }
  }

  asteroidSpawnTimer -= delta;
  if (asteroidSpawnTimer <= 0 && asteroids.length < 30) {
    spawnAsteroid();
    asteroidSpawnTimer = 0.14 + Math.random() * 0.28;
  }

  for (let i = asteroids.length - 1; i >= 0; i -= 1) {
    const rock = asteroids[i];
    const ud = rock.userData;
    rock.position.z += (speed.value * 1.2 + 10) * delta;
    rock.position.x += ud.driftX * delta;
    rock.position.y += ud.driftY * delta;
    rock.rotation.x += ud.spin.x * delta;
    rock.rotation.y += ud.spin.y * delta;
    if (ud.hitFlash > 0) {
      ud.hitFlash -= delta;
      if (ud.hitFlash <= 0) {
        rock.material.emissive.setHex(0x1a1424);
      }
    }
    if (rock.position.z > 26) {
      recycleAsteroid(i);
      continue;
    }

    // Rocks are immune to weapons fire: bolts get swallowed with a dull
    // spark, the asteroid doesn't care. Dodging is the only option.
    const hitRange = (ud.radius + 1) * (ud.radius + 1);
    for (let j = projectiles.length - 1; j >= 0; j -= 1) {
      const bolt = projectiles[j];
      const dx = bolt.position.x - rock.position.x;
      const dy = bolt.position.y - rock.position.y;
      const dz = bolt.position.z - rock.position.z;
      if (dx * dx + dy * dy + dz * dz < hitRange) {
        scene.remove(bolt);
        projectilePool.push(bolt);
        projectiles.splice(j, 1);
        ud.hitFlash = 0.12;
        rock.material.emissive.setHex(0x3a3446);
        spawnBurst(bolt.position, ['dust'], 3, 4);
      }
    }

    // Collision hurts — big rocks hit like a truck.
    const px = rock.position.x - player.position.x;
    const py = rock.position.y - player.position.y;
    const pz = rock.position.z - player.position.z;
    const playerDist = px * px + py * py + pz * pz;
    const crashRange = (ud.radius + 1.6) * (ud.radius + 1.6);
    if (playerDist < crashRange) {
      damagePlayer(ud.tier.dmg);
      spawnBurst(rock.position, ['red', 'dust'], 16, 9);
      recycleAsteroid(i);
      if (state.value !== 'running') {
        return;
      }
      continue;
    }

    // A near pass pays out like a graze.
    const grazeRange = (ud.radius + 5) * (ud.radius + 5);
    if (!ud.grazed && Math.abs(pz) < 2.5 && playerDist < grazeRange) {
      ud.grazed = true;
      triggerGraze();
    }
  }

  camera.position.x = THREE.MathUtils.damp(camera.position.x, player.position.x * 0.5, 4, delta);
  camera.position.y = THREE.MathUtils.damp(camera.position.y, player.position.y + 3.2, 4, delta);
  camera.position.z = THREE.MathUtils.damp(camera.position.z, player.position.z + 10, 4, delta);
  camera.fov = THREE.MathUtils.damp(camera.fov, 64 + Math.abs(planeVelX) * 0.2, 3, delta);
  camera.updateProjectionMatrix();
  lookAtTarget.set(player.position.x * 0.85, player.position.y + 0.3, player.position.z - 12);
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

  updateSkyPickups(delta);

  fireTimer -= delta;
  if (fireTimer <= 0) {
    fireProjectiles();
    fireTimer = rapidFireTime > 0 ? 0.09 : 0.18;
  }

  if (homingAmmo > 0) {
    homingTimer -= delta;
    if (homingTimer <= 0 && enemies.length) {
      fireHomingMissile();
      homingAmmo -= 1;
      homingTimer = 0.55;
    }
  }

  for (let i = projectiles.length - 1; i >= 0; i -= 1) {
    const bolt = projectiles[i];
    if (bolt.userData.homing) {
      const target = enemies[0];
      if (target) {
        tmpHomingDir.subVectors(target.position, bolt.position).normalize().multiplyScalar(95);
        bolt.userData.vel.x = THREE.MathUtils.damp(bolt.userData.vel.x, tmpHomingDir.x, 5, delta);
        bolt.userData.vel.y = THREE.MathUtils.damp(bolt.userData.vel.y, tmpHomingDir.y, 5, delta);
        bolt.userData.vel.z = THREE.MathUtils.damp(bolt.userData.vel.z, tmpHomingDir.z, 5, delta);
      }
      bolt.position.addScaledVector(bolt.userData.vel, delta);
      bolt.lookAt(
        bolt.position.x + bolt.userData.vel.x,
        bolt.position.y + bolt.userData.vel.y,
        bolt.position.z + bolt.userData.vel.z,
      );
    } else {
      bolt.position.z -= 150 * delta;
      bolt.position.x += (bolt.userData.vx || 0) * delta;
    }
    if (
      bolt.position.z < -280 ||
      bolt.position.z > 30 ||
      bolt.position.y > 60 ||
      Math.abs(bolt.position.x) > 70
    ) {
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

      if (ud.stage === 3 && ud.invulnTimer > 0) {
        // Final-phase telegraph: 10s untouchable while it charges, the shield
        // bubble pulses blood-red under the darkening eclipse. It already
        // lobs the huge orbs through the shield — slower cadence than the
        // full barrage, but the dodging starts immediately.
        ud.invulnTimer -= delta;
        ud.shieldMesh.visible = true;
        ud.shieldMesh.material.opacity = 0.18 + Math.sin(performance.now() * 0.02) * 0.1;
        ud.hugeTimer -= delta;
        if (ud.hugeTimer <= 0) {
          fireEnemyBolt(enemy, new THREE.Vector3(
            player.position.x + (Math.random() - 0.5) * 7,
            player.position.y + (Math.random() - 0.5) * 4.5,
            player.position.z,
          ), { huge: true });
          sfx.enemyShoot();
          ud.hugeTimer = 0.5;
        }
        if (ud.invulnTimer <= 0) {
          ud.shieldMesh.visible = false;
          ud.hugeTimer = 0.24;
          showEventToast('ECLIPSE BARRAGE', 'Dodge the orbs!', 2200);
          sfx.motherSpawn();
        }
      } else if (ud.stage === 3) {
        // Eclipse barrage: huge slow orbs at a relentless rate, nothing else.
        ud.hugeTimer -= delta;
        if (ud.hugeTimer <= 0) {
          fireEnemyBolt(enemy, new THREE.Vector3(
            player.position.x + (Math.random() - 0.5) * 7,
            player.position.y + (Math.random() - 0.5) * 4.5,
            player.position.z,
          ), { huge: true });
          sfx.enemyShoot();
          ud.hugeTimer = 0.24;
        }
      } else {
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
              // Enraged (stage 2): longer bursts, shorter breathers.
              ud.burstLeft = ud.stage === 2 ? 9 : 6;
              ud.burstTick = 0;
            } else if (roll < 0.75) {
              fireShotgun(enemy);
            } else {
              ud.shieldTimer = 3;
              sfx.shieldUp();
            }
            ud.attackTimer = ud.stage === 2
              ? 1.4 + Math.random() * 1.1
              : 2.3 + Math.random() * 1.6;
          }
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
        if (isMother && (ud.shieldTimer > 0 || ud.invulnTimer > 0)) {
          // Shield or final-phase invulnerability absorbs the shot.
          spawnBurst(bolt.position, ['skin'], 2, 2);
          continue;
        }
        ud.hp -= bolt.userData.dmg || 1;
        if (bolt.userData.homing) {
          spawnBurst(bolt.position, ['flame', 'gold'], 8, 6);
        }
        hit = true;
        if (isMother) {
          if (ud.stage === 1 && ud.hp <= ud.maxHp * 0.5) {
            ud.stage = 2;
            setMotherStage(enemy, 2);
            spawnBurst(enemy.position, ['red', 'flame'], 24, 10);
            sfx.shieldUp();
            showEventToast('MOTHERSHIP ENRAGED', 'Its hull burns — attacks speed up!', 2200);
          } else if (ud.stage === 2 && ud.hp <= ud.maxHp * 0.1) {
            // Final phase: clamp HP so the killing blow can't skip it.
            ud.stage = 3;
            ud.hp = Math.max(ud.hp, 1);
            ud.invulnTimer = 10;
            // First shielded orb after a short telegraph beat.
            ud.hugeTimer = 0.8;
            setMotherStage(enemy, 3);
            setEclipse(true);
            spawnBurst(enemy.position, ['red', 'dust'], 30, 12);
            sfx.shieldUp();
            showEventToast('SOLAR ECLIPSE', 'The mothership charges — orbs incoming!', 2600);
          }
        }
      }
    }
    if (hit) {
      spawnBurst(enemy.position, ['gold'], 2, 3);
      if (ud.hp <= 0) {
        if (isMother) {
          score.value += 2500;
          playerHp.value = Math.min(100, playerHp.value + 40);
          mothershipCount += 1;
          runMotherKills.value += 1;
          killsSinceMother = 0;
          setEclipse(false);
          // The wreck detonates and collapses into a black hole — zone 4.
          const wreckAt = enemy.position.clone();
          removeEnemy(i);
          triggerMotherCollapse(wreckAt);
          return;
        } else {
          score.value += 600;
          playerHp.value = Math.min(100, playerHp.value + 15);
          bossCount += 1;
          runDroneKills.value += 1;
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
      continue;
    }
    // Graze: the bolt slips past the cockpit without hitting — pays out once
    // per bolt, right as it crosses the player's z-plane.
    if (
      !bolt.userData.grazed &&
      Math.abs(bolt.position.z - player.position.z) < 2.2 &&
      Math.abs(bolt.position.x - player.position.x) < 6 &&
      Math.abs(bolt.position.y - player.position.y) < 4.5
    ) {
      bolt.userData.grazed = true;
      triggerGraze();
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

const galleryJoyStart = (event) => {
  galleryJoyPointerId = event.pointerId;
  event.currentTarget.setPointerCapture(event.pointerId);
  galleryJoyMove(event);
};

const galleryJoyMove = (event) => {
  if (galleryJoyPointerId !== event.pointerId) return;
  const rect = event.currentTarget.getBoundingClientRect();
  let dx = event.clientX - (rect.left + rect.width / 2);
  let dy = event.clientY - (rect.top + rect.height / 2);
  const max = rect.width / 2 - 18;
  const len = Math.hypot(dx, dy);
  if (len > max) {
    dx *= max / len;
    dy *= max / len;
  }
  galleryJoyKnob.value = { x: dx, y: dy };
  galleryJoyVec = { x: dx / max, y: dy / max };
};

const galleryJoyEnd = (event) => {
  if (galleryJoyPointerId !== event.pointerId) return;
  galleryJoyPointerId = null;
  galleryJoyKnob.value = { x: 0, y: 0 };
  galleryJoyVec = { x: 0, y: 0 };
};

// Open-world style free look: dragging anywhere on the scene (the controls
// are overlays and swallow their own pointer events) orbits the camera
// around the character. Works with touch and mouse alike.
const galleryCamStart = (event) => {
  if (state.value !== 'gallery') return;
  galleryCamPointerId = event.pointerId;
  galleryCamLast = { x: event.clientX, y: event.clientY };
  event.currentTarget.setPointerCapture(event.pointerId);
};

const galleryCamMove = (event) => {
  if (galleryCamPointerId !== event.pointerId) return;
  const dx = event.clientX - galleryCamLast.x;
  const dy = event.clientY - galleryCamLast.y;
  galleryCamLast = { x: event.clientX, y: event.clientY };
  // Drag moves the camera itself around the character. Yaw already orbits
  // the cam in the finger direction (drag right -> camera swings right),
  // so pitch must match: drag up (dy < 0) lifts the camera (pitch up).
  galleryCamYaw -= dx * 0.006;
  galleryCamPitch = THREE.MathUtils.clamp(
    galleryCamPitch - dy * 0.004,
    -0.1,
    1.1,
  );
};

const galleryCamEnd = (event) => {
  if (galleryCamPointerId !== event.pointerId) return;
  galleryCamPointerId = null;
};

// Left-side jump button in the showroom: only fires when the character is
// actually standing on the floor so mashing it mid-air does nothing.
const galleryJump = () => {
  if (state.value !== 'gallery' || !player) return;
  const groundY = playerSize.h / 2;
  if (player.position.y > groundY + 0.01) return;
  playerVelocityY = jumpVelocity;
  sfx.jump();
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
  driveLifeUsed = false;
  carjackPhase = 'eject';
  carjackTimer = 0;
  if (carjackCar) {
    scene.remove(carjackCar);
    carjackCar = null;
  }
  carjackProps.forEach((prop) => {
    scene.remove(prop);
  });
  carjackProps.length = 0;
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
  setMusicDuck(1);
  accelHeld = false;
  brakeHeld = false;
  driveTargetSpeed = 24;
  driveSpawnTimer = 0;
  godModeActive.value = false;
  godHoldTimer = 0;
  godHoldProgress.value = 0;
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
  clearSkyPickups();
  // Zone 4 cleanup: black hole, shock rings, wreck debris, asteroids, flash.
  clearVoidObjects();
  asteroidSpawnTimer = 0;
  rapidFireTime = 0;
  spreadFireTime = 0;
  homingAmmo = 0;
  homingTimer = 0;
  setEclipse(false);
  skyPickupTimer = 7;
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
  if (finalePhase.value === 'carjack') {
    updateCarjack(delta);
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
  if (finalePhase.value === 'collapse') {
    updateCollapse(delta);
    return;
  }
  if (finalePhase.value === 'void') {
    updateVoid(delta);
    return;
  }

  // Carjack leftovers (the tipped wreck) scroll away with the road.
  if (carjackProps.length) {
    for (let i = carjackProps.length - 1; i >= 0; i -= 1) {
      const prop = carjackProps[i];
      prop.position.z += speed.value * delta;
      if (prop.position.z > 30) {
        // Clones share GLB template geometry — remove only, never dispose.
        scene.remove(prop);
        carjackProps.splice(i, 1);
      }
    }
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
      // Zone 2 is capped at 80 km/h — permanently. No speed trap, no
      // temporary window; driveMaxSpeed IS the limit.
      const driveCap = driveMaxSpeed;
      if (accelHeld) {
        driveTargetSpeed = Math.min(driveCap, driveTargetSpeed + 26 * delta);
      }
      if (driveTargetSpeed > driveCap) {
        // Forced deceleration while the limit is active — pedals or not.
        driveTargetSpeed = Math.max(driveCap, driveTargetSpeed - 60 * delta);
      }
      if (brakeHeld) {
        driveTargetSpeed = Math.max(0, driveTargetSpeed - 40 * delta);
      }
      speed.value = THREE.MathUtils.damp(speed.value, driveTargetSpeed, 2.2, delta);
    }
    updateEngineSound();

    if (!godModeActive.value) {
      // Round like the HUD does: damp() approaches the 80 cap asymptotically
      // and would otherwise never hit godTriggerSpeed exactly.
      if (Math.round(speed.value) >= godTriggerSpeed) {
        godHoldTimer += delta;
        if (godHoldTimer >= godHoldSeconds) {
          activateGodMode();
        }
      } else {
        // Drain progress instead of a hard reset — dips cost time, not all.
        godHoldTimer = Math.max(0, godHoldTimer - delta * 1.5);
      }
      godHoldProgress.value = godHoldTimer;
    } else {
      emitFireTrail();
      if (finalePhase.value !== 'ramp' && speed.value < godFloorSpeed) {
        godModeActive.value = false;
        godHoldTimer = 0;
        godHoldProgress.value = 0;
      }
    }

    if (
      finalePhase.value === 'drive'
      && !rampTriggered
      && score.value >= RAMP_SCORE
    ) {
      if (godModeActive.value) {
        triggerRamp();
      } else if (!rampHintShown) {
        // Make the requirement discoverable instead of silently failing.
        rampHintShown = true;
        driveHintText.value = `The road only breaks for gods — hold ${godTriggerSpeed}+ and keep it there!`;
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
    // Stufenlose Beschleunigung statt Treppenstufen alle 2500: exakt die
    // Kurve, die das Anti-Cheat serverseitig modelliert (minPlausibleDuration
    // löst d'(t) = 2.4·(base + d/2500·step); expectedSpeed spiegelt min(3, …)).
    // Checkpoints/Districts alle 2500 bleiben als Events bestehen.
    const targetSpeed =
      level.baseSpeed + Math.min(3, score.value / level.stepDistance) * level.speedStep;
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

  updateWindStreaks(delta);

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

  // Warnlampen-Blinken: ein geteiltes Material, alle Baustellen-Lampen
  // pulsieren synchron — ein Farbwert pro Frame, praktisch gratis.
  if (obstacleAssets) {
    const blink = 0.55 + 0.45 * Math.sin(performance.now() * 0.008);
    obstacleAssets.hazard.color.setRGB(blink, 0.63 * blink, 0.18 * blink);
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
      // Widen the pickup window with speed so fast zone-2 runs cannot step
      // over a coin between two frames.
      Math.abs(coin.position.z - player.position.z) <
        Math.max(0.8, speed.value * delta * 1.2) &&
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
      // 22 statt 26: bei niedrigem Drive-Tempo war die vierspurige Straße
      // fast leer (1 Fahrzeug auf 160 m).
      driveSpawnTimer = Math.max(
        0.28,
        THREE.MathUtils.randFloat(0.7, 1.5) * (22 / Math.max(14, speed.value)),
      );
    }
    driveEventTimer -= delta;
    if (driveEventTimer <= 0) {
      if (Math.random() < 0.55) {
        startDriveOvertaker();
      } else {
        startDriveConvoy();
      }
      driveEventTimer = THREE.MathUtils.randFloat(9, 15);
    }
  } else if (finalePhase.value === 'none') {
    updateZoneEvents(delta);
    // Normal rows pause only while an event's tail still sits deeper than
    // the spawn point — new rows land safely behind it, so the pause stays
    // as short as possible.
    if (!eventSpawnHoldActive()) {
      spawnTimer -= delta;
      if (spawnTimer <= 0) {
        spawnRow();
        // Arrival gap between hazards, tiered per 2.5k checkpoint block.
        // The floor always stays above the 0.86s jump airtime in tier 0, and
        // oncoming cars are arrival-normalized (see spawnOncoming), so this
        // IS the real time between obstacles reaching the player.
        // Cadence follows the applied tier, in lockstep with the speed.
        // Floor stays above a full jump chain (0.86s airtime + reaction):
        // tier 0 never dips below 1.05s between arrivals. (Vorher 1.1-1.45 —
        // leicht dichter für weniger leeren Asphalt, Floor bleibt drüber.)
        const baseGap = [1.0, 0.85, 0.75, 0.65][speedTier];
        spawnTimer = THREE.MathUtils.randFloat(1.05, 1.35) * baseGap;
      }
    } else {
      spawnTimer = Math.max(spawnTimer, 0.7);
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
    if (obstacle.userData.decor) continue;
    if (checkCollision(obstacle, collisionPlayerHeight)) {
      // Jam vehicles are trampolines: landing on a roof bounces the player
      // onward instead of crashing — the intended way through rush hour.
      // Fenster 1.25 statt 0.65: Dachhöhen sind nicht genormt (Sedan 1.1 vs
      // SUV 1.4, Trucks ~2.6+); wer in eine Fuge oder gegen eine etwas
      // höhere Front fällt, wird aufs Dach gezogen statt zu sterben.
      if (
        obstacle.userData.jam &&
        playerVelocityY < 0 &&
        player.position.y - collisionPlayerHeight / 2 >
          obstacle.position.y + obstacle.userData.size.h / 2 - 1.25
      ) {
        const roofY = obstacle.position.y + obstacle.userData.size.h / 2;
        player.position.y = roofY + collisionPlayerHeight / 2 + 0.02;
        // 1.08: der Bounce vom Autodach muss auch die höchste Truck-Front
        // (~2.9) sicher übersteigen, sonst stirbt man am Car→Truck-Übergang.
        playerVelocityY = jumpVelocity * 1.08;
        score.value += 20;
        sfx.land();
        vibrate(12);
        spawnBurst(
          new THREE.Vector3(player.position.x, roofY, player.position.z),
          ['dust'],
          5,
          3,
        );
        continue;
      }
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
    // Zone 1+2 chase cam, Subway-Surfers style (matched against the
    // reference capture frame by frame): anchored to the RESTING height —
    // jumping/sliding never moves the camera. A lane change is nothing but
    // a smooth lateral glide FULLY into the new lane; the camera keeps
    // looking straight down the track (lookAt shares the camera x), so
    // there is zero yaw swing, zero roll, no bob, no shake. The character
    // drifts briefly off-center during the move and recenters softly.
    const chaseDist = galleryCamDist * cameraZoom.value;
    const restY = playerSize.h / 2;
    const chaseY = restY + 0.9 + Math.sin(0.35) * chaseDist;
    const chaseZ = player.position.z + Math.cos(0.35) * chaseDist;
    // Normale Sprünge (Apex ~2.6 m bei v=12/g=-28) lassen die Kamera in Ruhe;
    // nur was darüber hinausgeht (Trampolin-Bounce vom Autodach) zieht sie
    // langsam mit hoch. Cam UND lookAt heben gleich viel — die Ansicht gleitet
    // nach oben, ohne zu kippen, und sinkt nach der Landung wieder zurück.
    const excessY = Math.max(0, player.position.y - restY - 2.6);
    chaseCamLift = THREE.MathUtils.damp(chaseCamLift, excessY, 4, delta);
    camera.position.x = THREE.MathUtils.damp(
      camera.position.x,
      player.position.x,
      5,
      delta,
    );
    camera.position.y = THREE.MathUtils.damp(
      camera.position.y,
      chaseY + chaseCamLift,
      8,
      delta,
    );
    camera.position.z = THREE.MathUtils.damp(camera.position.z, chaseZ, 8, delta);
    lookAtTarget.set(
      camera.position.x,
      restY + 1.1 + chaseCamLift,
      player.position.z,
    );
    camera.lookAt(lookAtTarget);
  }
};

const startCrash = () => {
  // Zone 2 second chance: the first wreck ejects the character instead of
  // ending the run — they steal a parked car at the left curb and drive on.
  if (finalePhase.value === 'drive' && !rampTriggered && !driveLifeUsed) {
    ejectFromCar();
    return;
  }
  state.value = 'crashing';
  crashTimer = 1.0;
  player.visible = false;
  nearMissCombo.value = 0;
  nearMissComboAt = -Infinity;
  vibrate(90);
  sfx.crash();
  spawnBurst(player.position, ['skin', 'red', 'skin'], 26, 8);
};

const animate = (time) => {
  animationId = requestAnimationFrame(animate);
  // Clamp in BEIDE Richtungen: der erste rAF-Timestamp kann hinter lastTime
  // liegen (anderer Zeitursprung) — negatives delta lässt damp() rückwärts
  // extrapolieren (u.a. Musik-Duck unter 0 → volume-Exception).
  const delta = Math.min(0.05, Math.max(0, (time - lastTime) / 1000 || 0));
  lastTime = time;

  if (state.value === 'running') {
    updateRunner(delta);
    if (speed.value > runTopSpeedRaw) {
      runTopSpeedRaw = speed.value;
    }
    if (
      !recordCelebrated &&
      !devRun.value &&
      bestScore.value > 400 &&
      score.value > bestScore.value
    ) {
      recordCelebrated = true;
      showEventToast('New Record!', `${Math.floor(bestScore.value)} beaten — keep going!`, 2400);
      sfx.powerup();
      spawnBurst(player.position.clone(), ['gold', 'skin'], 14, 6);
    }
    if (bumpShakeTimer > 0) {
      // Reference cam has zero shake — keep the timer for gameplay logic but
      // never wobble the camera.
      bumpShakeTimer -= delta;
    }
  } else if (state.value === 'gallery') {
    updateGallery(delta);
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
      // The shop dock sits at the bottom, so aim low: the subject floats in
      // the free upper half. Bigger subjects need more camera distance.
      const kind = previewCar ? previewKind : 'runner';
      const frames = {
        runner: portrait
          ? { cam: [0, 1.6, -2.8], look: [0, 0.75, 2] }
          : { cam: [-1.4, 1.5, -1.6], look: [0.7, 0.85, 2] },
        car: portrait
          ? { cam: [0, 2.2, -5.4], look: [0, 0.3, 2] }
          : { cam: [-1.2, 2.0, -3.8], look: [0.5, 0.5, 2] },
        plane: portrait
          ? { cam: [0, 2.8, -8.8], look: [0, 1.0, 2] }
          : { cam: [-1.5, 2.6, -6.6], look: [0.5, 1.2, 2] },
      };
      const frame = frames[kind] || frames.runner;
      camera.position.x = damp(camera.position.x, frame.cam[0]);
      camera.position.y = damp(camera.position.y, frame.cam[1]);
      camera.position.z = damp(camera.position.z, frame.cam[2]);
      lookAtTarget.set(frame.look[0], frame.look[1], frame.look[2]);
      camera.lookAt(lookAtTarget);
      if (previewCar) {
        previewCar.rotation.y += delta * 0.7;
      } else if (player) {
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
    // Slow dolly toward the wreck while the debris hangs in slow motion.
    camera.position.y = THREE.MathUtils.damp(camera.position.y, 3.1, 2.0, delta);
    camera.position.z = THREE.MathUtils.damp(camera.position.z, 6.4, 2.0, delta);
    if (player) {
      lookAtTarget.set(player.position.x, Math.max(0.8, player.position.y), player.position.z);
      camera.lookAt(lookAtTarget);
    }
    camera.position.x += (Math.random() - 0.5) * 0.12 * Math.max(0, crashTimer);
    camera.position.y += (Math.random() - 0.5) * 0.08 * Math.max(0, crashTimer);
    if (crashTimer <= 0) {
      applyCameraZoom();
      endRun();
    }
  }

  if (state.value !== 'paused') {
    // Crash debris floats in slow motion for the drama.
    updateParticles(state.value === 'crashing' ? delta * 0.35 : delta);
    updateShootingStars(delta);
  }

  // Wind-Streaks leben nur im Lauf: nach Tod/Quit bei hohem Tempo würden sie
  // sonst eingefroren über Death-Screen oder Menü hängen bleiben.
  if (windStreakMat && state.value !== 'running' && windStreakMat.opacity > 0) {
    windStreakMat.opacity = Math.max(0, windStreakMat.opacity - delta * 2);
  }

  updateMusicDuck(delta);

  lerpEnvironment(delta);

  if (
    isEngineActive() &&
    (state.value !== 'running' ||
      !['drive', 'ramp', 'launch', 'plane', 'collapse', 'void'].includes(finalePhase.value))
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

// Alt-Tab pausiert auch: ein unfokussiertes Fenster bleibt "visible", aber
// rAF läuft weiter — ohne Auto-Pause rennt der Läufer in den Tod.
const handleWindowBlur = () => {
  pauseRun();
};

onMounted(() => {
  loadAudioPrefs();
  loadLevelPref();
  loadHandednessPref();
  loadDailyStats();
  initAudio();
  pointerUnlockHandler = () => unlockAudio();
  window.addEventListener('pointerdown', pointerUnlockHandler, { once: true });
  document.addEventListener('visibilitychange', handleVisibility);
  window.addEventListener('blur', handleWindowBlur);
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
  disposeAudio();
  document.removeEventListener('visibilitychange', handleVisibility);
  window.removeEventListener('blur', handleWindowBlur);
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
  Object.values(planeTemplates).forEach((template) => {
    template.model.traverse((node) => {
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
  window.removeEventListener('touchstart', handleTouchStart);
  window.removeEventListener('touchmove', handleTouchMove);
  window.removeEventListener('touchend', handleTouchEnd);
  window.removeEventListener('touchcancel', handleTouchCancel);
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
  --display: 'Chakra Petch', 'Bahnschrift', 'Segoe UI', sans-serif;
  --cyan: #4be8ff;
  --gold: #ffc94a;
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
  font-family: 'Chakra Petch', 'Bahnschrift', 'Segoe UI', sans-serif;
}

.runner-canvas {
  position: absolute;
  inset: 0;
  z-index: 1;
  /* The swipe surface must own its touches completely: without this the
     browser treats two active fingers (pedal + swipe) as a pinch-zoom
     candidate and pointercancels BOTH inputs. */
  touch-action: none;
}

.runner-canvas canvas {
  width: 100%;
  height: 100%;
  display: block;
}

.hud {
  position: absolute;
  top: calc(10px + env(safe-area-inset-top));
  left: calc(14px + env(safe-area-inset-left));
  right: calc(14px + env(safe-area-inset-right));
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: start;
  gap: 10px;
  z-index: 3;
  pointer-events: none;
}

.hud-side {
  display: flex;
  align-items: center;
  gap: 8px;
}

.hud-side-right {
  justify-content: flex-end;
}

.hud-pill {
  display: inline-flex;
  align-items: center;
  gap: 7px;
  height: 38px;
  padding: 0 14px;
  background: linear-gradient(180deg, rgba(16, 24, 44, 0.85), rgba(8, 12, 24, 0.85));
  box-shadow: inset 0 1px 0 rgba(160, 210, 255, 0.14);
  clip-path: polygon(9px 0, 100% 0, 100% calc(100% - 9px), calc(100% - 9px) 100%, 0 100%, 0 9px);
  font-family: var(--display);
  font-weight: 600;
  font-size: 1rem;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.06em;
  color: #eef6ff;
}

.hud-pill svg {
  width: 17px;
  height: 17px;
  flex: none;
}

.hud-coins {
  color: #ffd97a;
}

.hud-speed svg {
  color: var(--cyan);
}

.hud-speed.low {
  color: #ff8087;
}

.hud-speed.low svg {
  color: #ff5d68;
}

.hud-score {
  text-align: center;
  font-family: var(--display);
}

.hud-score-label {
  font-size: 0.56rem;
  font-weight: 600;
  letter-spacing: 0.5em;
  margin-left: 0.5em;
  text-transform: uppercase;
  color: rgba(170, 200, 240, 0.6);
}

.hud-score-value {
  font-size: clamp(1.9rem, 5.5vw, 2.6rem);
  font-weight: 700;
  line-height: 1.02;
  font-variant-numeric: tabular-nums;
  letter-spacing: 0.03em;
  color: #f4faff;
  text-shadow: 0 0 22px rgba(75, 232, 255, 0.35), 0 2px 14px rgba(0, 0, 0, 0.65);
}

.near-miss {
  position: absolute;
  top: 30%;
  left: 50%;
  z-index: 3;
  pointer-events: none;
  color: #ffd766;
  font-family: var(--display);
  font-size: 1.6rem;
  letter-spacing: 0.2em;
  text-transform: uppercase;
  text-shadow: 0 0 18px rgba(255, 190, 70, 0.65);
  animation: nearMissPop 0.95s ease-out forwards;
}

.death-guest-hint {
  display: block;
  padding: 8px 14px;
  border-radius: 10px;
  border: 1px solid rgba(53, 224, 255, 0.35);
  background: rgba(53, 224, 255, 0.08);
  color: #7fd8f2;
  font-size: 0.72rem;
  letter-spacing: 0.1em;
  text-transform: uppercase;
  text-align: center;
  text-decoration: none;
}

.death-missions {
  display: flex;
  flex-direction: column;
  gap: 8px;
  width: 100%;
  margin-top: 4px;
  padding-top: 12px;
  border-top: 1px solid rgba(120, 150, 190, 0.16);
}

.death-mission-top {
  display: flex;
  justify-content: space-between;
  gap: 12px;
  margin-bottom: 4px;
}

.death-mission-label {
  color: #9fb3d0;
  font-size: 0.72rem;
  letter-spacing: 0.08em;
  text-transform: uppercase;
}

.death-mission-count {
  color: #cfe2ff;
  font-size: 0.72rem;
  font-variant-numeric: tabular-nums;
}

.death-mission-bar {
  height: 5px;
  border-radius: 999px;
  background: rgba(120, 150, 190, 0.18);
  overflow: hidden;
}

.death-mission-fill {
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #2fe6b2, #35b4ff);
  transition: width 0.5s ease;
}

.death-mission.done .death-mission-label {
  color: #2fe6b2;
}

.death-mission.done .death-mission-fill {
  background: #2fe6b2;
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
  font-family: var(--display);
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

.mission-badge {
  display: inline-block;
  min-width: 20px;
  margin-left: 8px;
  padding: 1px 6px;
  border-radius: 999px;
  background: #ffd76b;
  color: #241a04;
  font-size: 0.72rem;
  font-weight: 700;
  text-align: center;
}

.mission-panel {
  display: grid;
  gap: 12px;
}

.mission-sub {
  font-size: 0.78rem;
  letter-spacing: 0.1em;
  color: rgba(180, 205, 235, 0.7);
}

.mission-row {
  display: flex;
  align-items: center;
  gap: 14px;
  padding: 12px 14px;
  border-radius: 14px;
  border: 1px solid rgba(120, 180, 255, 0.25);
  background: rgba(14, 20, 34, 0.6);
}

.mission-row.done {
  border-color: rgba(61, 255, 179, 0.55);
}

.mission-info {
  flex: 1;
  min-width: 0;
}

.mission-label {
  font-size: 0.88rem;
  color: rgba(225, 238, 255, 0.92);
  margin-bottom: 6px;
}

.mission-progress-track {
  height: 6px;
  border-radius: 999px;
  background: rgba(70, 100, 150, 0.3);
  overflow: hidden;
}

.mission-progress-fill {
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #2ee5ff, #3bffb3);
}

.mission-progress-text {
  margin-top: 4px;
  font-size: 0.7rem;
  letter-spacing: 0.12em;
  color: rgba(170, 195, 230, 0.65);
}

.mission-claim {
  flex: 0 0 auto;
  padding: 9px 16px;
  border-radius: 999px;
  border: 1px solid rgba(255, 210, 100, 0.7);
  background: rgba(60, 44, 12, 0.7);
  color: #ffd76b;
  font-size: 0.8rem;
  letter-spacing: 0.1em;
  cursor: pointer;
}

.mission-claim:disabled {
  opacity: 0.45;
  cursor: default;
}

.mission-claim.claimed {
  border-color: rgba(61, 255, 179, 0.55);
  color: #7dfce0;
  background: rgba(10, 40, 28, 0.6);
}

.mission-guest-note {
  font-size: 0.75rem;
  letter-spacing: 0.1em;
  color: rgba(255, 215, 107, 0.75);
}

.death-substats {
  margin-top: 6px;
  font-size: 0.75rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: rgba(180, 205, 235, 0.65);
}

.your-rank {
  padding: 8px 14px;
  border-radius: 12px;
  border: 1px solid rgba(255, 210, 100, 0.4);
  background: rgba(50, 38, 12, 0.4);
  font-size: 0.85rem;
  letter-spacing: 0.1em;
  color: #ffd76b;
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
  gap: 10px;
}

.tutorial-hint {
  bottom: calc(110px + env(safe-area-inset-bottom));
  border-color: rgba(61, 255, 179, 0.45);
  color: #a9ffe2;
}

.zone-locked {
  padding: 10px 14px;
  border-radius: 12px;
  border: 1px dashed rgba(120, 160, 220, 0.35);
  background: rgba(12, 18, 32, 0.45);
  font-size: 0.78rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  color: rgba(170, 195, 230, 0.55);
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
  font-family: var(--display);
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

.speed-goal-track {
  margin-top: 6px;
  height: 5px;
  border-radius: 999px;
  background: rgba(80, 60, 30, 0.6);
  overflow: hidden;
}

.speed-goal-fill {
  height: 100%;
  border-radius: 999px;
  background: linear-gradient(90deg, #ffb056, #5cffa8);
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
  font-family: var(--display);
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
  pointer-events: auto;
  width: 38px;
  height: 38px;
  display: grid;
  place-items: center;
  border: none;
  background: linear-gradient(180deg, rgba(16, 24, 44, 0.85), rgba(8, 12, 24, 0.85));
  box-shadow: inset 0 1px 0 rgba(160, 210, 255, 0.14);
  clip-path: polygon(9px 0, 100% 0, 100% calc(100% - 9px), calc(100% - 9px) 100%, 0 100%, 0 9px);
  color: #cfe4ff;
  cursor: pointer;
  transition: transform 0.15s ease;
}

.pause-btn svg {
  width: 15px;
  height: 15px;
}

.pause-btn:active {
  transform: scale(0.92);
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

/* Mothership blast / wormhole white-out — opacity is driven from script. */
.void-flash {
  position: absolute;
  inset: 0;
  z-index: 4;
  pointer-events: none;
  background: #fff;
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

.cheat-skip {
  position: absolute;
  top: calc(14px + env(safe-area-inset-top));
  left: 50%;
  transform: translateX(-50%);
  z-index: 6;
  padding: 8px 18px;
  border-radius: 999px;
  border: 1px dashed rgba(255, 200, 80, 0.7);
  background: rgba(40, 30, 8, 0.65);
  color: #ffc850;
  font-size: 0.7rem;
  font-weight: 700;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  cursor: pointer;
  touch-action: none;
  user-select: none;
  -webkit-user-select: none;
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

.joystick--right {
  left: auto;
  right: calc(24px + env(safe-area-inset-right));
  transform: none;
}

.jump-btn {
  position: absolute;
  bottom: calc(50px + env(safe-area-inset-bottom));
  left: calc(24px + env(safe-area-inset-left));
  z-index: 5;
  width: 96px;
  height: 96px;
  border-radius: 50%;
  border: 1px solid rgba(120, 180, 255, 0.45);
  background: rgba(8, 12, 22, 0.45);
  color: #cfe0ff;
  font-size: 0.72rem;
  font-weight: 700;
  letter-spacing: 0.08em;
  text-transform: uppercase;
  touch-action: none;
  -webkit-user-select: none;
  user-select: none;
}

.jump-btn:active {
  background: rgba(120, 180, 255, 0.35);
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
  justify-content: center;
  background:
    radial-gradient(ellipse at 50% -12%, rgba(60, 130, 220, 0.18), transparent 55%),
    linear-gradient(180deg, rgba(5, 7, 15, 0.92) 0%, rgba(5, 7, 15, 0.55) 45%, rgba(5, 7, 15, 0.88) 100%);
  z-index: 3;
  animation: fadeIn 0.5s ease;
}

.menu-layout {
  width: min(620px, 100%);
  height: 100%;
  padding: calc(24px + env(safe-area-inset-top)) 22px calc(18px + env(safe-area-inset-bottom));
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: space-between;
  gap: 16px;
}

.menu-hero {
  text-align: center;
  margin-top: 5vh;
}

.menu-eyebrow {
  font-family: var(--display);
  font-size: 0.62rem;
  font-weight: 600;
  letter-spacing: 0.52em;
  margin-left: 0.52em;
  text-transform: uppercase;
  color: rgba(150, 190, 255, 0.65);
}

.menu-logo {
  margin: 12px 0 0;
  font-family: var(--display);
  text-transform: uppercase;
  line-height: 0.94;
}

.menu-logo-top {
  display: block;
  font-size: clamp(1.2rem, 4.5vw, 1.8rem);
  font-weight: 600;
  letter-spacing: 0.64em;
  margin-left: 0.64em;
  color: rgba(175, 205, 245, 0.85);
}

.menu-logo-main {
  display: block;
  font-size: clamp(3rem, 11vw, 4.6rem);
  font-weight: 700;
  letter-spacing: 0.1em;
  margin-left: 0.1em;
  color: #f6fbff;
  text-shadow: 0 0 34px rgba(75, 232, 255, 0.4), 0 4px 24px rgba(0, 0, 0, 0.6);
}

.menu-best {
  display: inline-flex;
  margin-top: 14px;
  padding: 7px 14px;
  font-family: var(--display);
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  font-variant-numeric: tabular-nums;
  color: var(--gold);
  background: rgba(50, 36, 8, 0.55);
  box-shadow: inset 0 0 0 1px rgba(255, 201, 74, 0.35);
  clip-path: polygon(7px 0, 100% 0, 100% calc(100% - 7px), calc(100% - 7px) 100%, 0 100%, 0 7px);
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

.menu-center {
  width: 100%;
  display: grid;
  justify-items: center;
  gap: 16px;
}

.play-btn {
  width: min(320px, 100%);
  padding: 17px 20px 16px;
  border: none;
  position: relative;
  overflow: hidden;
  font-family: var(--display);
  font-size: 1.25rem;
  font-weight: 700;
  letter-spacing: 0.42em;
  text-indent: 0.42em;
  text-transform: uppercase;
  color: #041018;
  background: linear-gradient(115deg, #39f9c0, #25a6ff);
  clip-path: polygon(16px 0, 100% 0, 100% calc(100% - 16px), calc(100% - 16px) 100%, 0 100%, 0 16px);
  cursor: pointer;
  filter: drop-shadow(0 12px 30px rgba(43, 205, 213, 0.35));
  transition: transform 0.15s ease, filter 0.2s ease;
}

.play-btn::after {
  content: '';
  position: absolute;
  top: 0;
  bottom: 0;
  left: -60%;
  width: 40%;
  background: linear-gradient(100deg, transparent, rgba(255, 255, 255, 0.45), transparent);
  transform: skewX(-18deg);
  animation: playSheen 3.6s ease-in-out infinite;
}

@keyframes playSheen {
  0%,
  60% {
    left: -60%;
  }
  100% {
    left: 130%;
  }
}

.play-btn:hover {
  transform: translateY(-2px);
  filter: drop-shadow(0 16px 34px rgba(43, 205, 213, 0.5));
}

.play-btn:active {
  transform: translateY(0) scale(0.97);
}

.difficulty-row {
  display: grid;
  justify-items: center;
  gap: 7px;
}

.difficulty-label {
  font-family: var(--display);
  font-size: 0.56rem;
  font-weight: 600;
  letter-spacing: 0.34em;
  margin-left: 0.34em;
  text-transform: uppercase;
  color: rgba(170, 200, 255, 0.55);
}

.difficulty-toggle {
  display: inline-flex;
  padding: 3px;
  gap: 3px;
  background: rgba(9, 14, 28, 0.78);
  box-shadow: inset 0 1px 0 rgba(160, 210, 255, 0.12);
  clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
}

.difficulty-toggle button {
  border: none;
  padding: 8px 18px;
  font-family: var(--display);
  font-size: 0.68rem;
  font-weight: 600;
  letter-spacing: 0.16em;
  text-transform: uppercase;
  background: transparent;
  color: rgba(200, 220, 250, 0.68);
  cursor: pointer;
  clip-path: polygon(8px 0, 100% 0, 100% calc(100% - 8px), calc(100% - 8px) 100%, 0 100%, 0 8px);
  transition: background 0.2s ease, color 0.2s ease;
}

.difficulty-toggle button.active {
  background: linear-gradient(115deg, #39f9c0, #25a6ff);
  color: #041018;
  font-weight: 700;
}

.menu-bottom {
  width: 100%;
  display: grid;
  gap: 14px;
}

.menu-tiles {
  display: grid;
  grid-template-columns: repeat(5, 1fr);
  gap: 8px;
}

.menu-tile {
  position: relative;
  display: grid;
  justify-items: center;
  gap: 7px;
  padding: 13px 4px 11px;
  border: none;
  background: linear-gradient(180deg, rgba(16, 24, 44, 0.8), rgba(8, 12, 24, 0.8));
  box-shadow: inset 0 1px 0 rgba(160, 210, 255, 0.12);
  clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
  color: rgba(210, 228, 255, 0.85);
  font-family: var(--display);
  font-size: 0.58rem;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  cursor: pointer;
  transition: transform 0.15s ease, color 0.15s ease;
}

.menu-tile svg {
  width: 21px;
  height: 21px;
  color: var(--cyan);
}

@media (hover: hover) {
  .menu-tile:hover {
    transform: translateY(-2px);
    color: #ffffff;
  }
}

.menu-tile:active {
  transform: scale(0.95);
}

.tile-badge {
  position: absolute;
  top: 5px;
  right: 7px;
  min-width: 17px;
  height: 17px;
  padding: 0 4px;
  display: grid;
  place-items: center;
  border-radius: 9px;
  background: var(--gold);
  color: #241300;
  font-size: 0.6rem;
  font-weight: 700;
  letter-spacing: 0;
}

.menu-foot {
  display: grid;
  justify-items: center;
  gap: 10px;
}

.menu-foot-links {
  display: flex;
  align-items: center;
  justify-content: center;
  flex-wrap: wrap;
  gap: 8px;
}

.coin-chip {
  display: inline-flex;
  align-items: center;
  gap: 6px;
  padding: 8px 12px;
  font-family: var(--display);
  font-weight: 600;
  font-size: 0.75rem;
  font-variant-numeric: tabular-nums;
  color: var(--gold);
  background: rgba(50, 36, 8, 0.5);
  box-shadow: inset 0 0 0 1px rgba(255, 201, 74, 0.3);
  clip-path: polygon(8px 0, 100% 0, 100% calc(100% - 8px), calc(100% - 8px) 100%, 0 100%, 0 8px);
}

.coin-chip svg {
  width: 14px;
  height: 14px;
}

.menu-screen {
  width: min(640px, 100%);
  height: 100%;
  padding: calc(16px + env(safe-area-inset-top)) 18px calc(16px + env(safe-area-inset-bottom));
  display: flex;
  flex-direction: column;
  gap: 14px;
  min-height: 0;
}

.menu-screen-head {
  display: flex;
  align-items: center;
  gap: 12px;
}

.back-btn {
  flex: none;
  width: 40px;
  height: 40px;
  display: grid;
  place-items: center;
  border: none;
  background: linear-gradient(180deg, rgba(16, 24, 44, 0.85), rgba(8, 12, 24, 0.85));
  box-shadow: inset 0 1px 0 rgba(160, 210, 255, 0.14);
  clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
  color: #d5e6ff;
  cursor: pointer;
  transition: transform 0.15s ease;
}

.back-btn svg {
  width: 18px;
  height: 18px;
}

.back-btn:active {
  transform: scale(0.92);
}

.menu-screen-title {
  flex: 1;
  font-family: var(--display);
  font-size: clamp(1.1rem, 4vw, 1.4rem);
  font-weight: 700;
  letter-spacing: 0.24em;
  text-transform: uppercase;
  color: #eef6ff;
}

.menu-screen-card {
  flex: 0 1 auto;
  min-height: 0;
  background: linear-gradient(180deg, rgba(13, 19, 36, 0.92), rgba(8, 12, 24, 0.92));
  box-shadow: inset 0 1px 0 rgba(160, 210, 255, 0.12);
  clip-path: polygon(14px 0, 100% 0, 100% calc(100% - 14px), calc(100% - 14px) 100%, 0 100%, 0 14px);
  padding: 18px;
  display: grid;
  align-content: start;
  gap: 16px;
  overflow-y: auto;
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
  padding: 14px 16px;
  background: rgba(9, 14, 28, 0.7);
  box-shadow: inset 0 1px 0 rgba(160, 210, 255, 0.1);
  clip-path: polygon(10px 0, 100% 0, 100% calc(100% - 10px), calc(100% - 10px) 100%, 0 100%, 0 10px);
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
  font-family: var(--display);
  font-size: 1.6rem;
  font-weight: 700;
  font-variant-numeric: tabular-nums;
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

.menu-screen.skin-screen {
  justify-content: space-between;
  width: min(720px, 100%);
}

.skin-dock {
  display: grid;
  gap: 10px;
  padding: 12px 14px;
  background: linear-gradient(180deg, rgba(13, 19, 36, 0.92), rgba(8, 12, 24, 0.92));
  box-shadow: inset 0 1px 0 rgba(160, 210, 255, 0.12);
  clip-path: polygon(12px 0, 100% 0, 100% calc(100% - 12px), calc(100% - 12px) 100%, 0 100%, 0 12px);
}

.skin-tabs {
  display: flex;
  align-items: center;
  gap: 8px;
}

.skin-tab {
  font-family: var(--display);
  font-weight: 600;
  padding: 7px 16px;
  border-radius: 999px;
  border: 1px solid rgba(120, 180, 255, 0.3);
  background: rgba(16, 22, 38, 0.7);
  color: rgba(210, 228, 255, 0.75);
  font-size: 0.78rem;
  letter-spacing: 0.12em;
  text-transform: uppercase;
  cursor: pointer;
}

.skin-tab.active {
  border-color: rgba(61, 255, 179, 0.7);
  color: #7dfce0;
  box-shadow: 0 0 14px rgba(60, 255, 200, 0.25);
}

.skin-tabs .shop-balance {
  margin-left: auto;
  font-size: 0.82rem;
  color: #ffd76b;
  white-space: nowrap;
}

.skin-strip {
  display: flex;
  flex-wrap: nowrap;
  gap: 8px;
  overflow-x: auto;
  padding: 2px 2px 8px;
  -webkit-overflow-scrolling: touch;
  scrollbar-width: thin;
}

.skin-strip .skin-chip {
  flex: 0 0 auto;
}

.skin-chip.previewing {
  border-color: rgba(255, 210, 100, 0.75);
}

.skin-info {
  display: flex;
  align-items: center;
  gap: 12px;
  min-height: 38px;
  flex-wrap: wrap;
}

.skin-info-name {
  font-size: 0.9rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: rgba(225, 238, 255, 0.9);
}

.skin-info-note {
  font-size: 0.75rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #7dfce0;
}

.buy-btn {
  padding: 8px 18px;
  border-radius: 999px;
  border: 1px solid rgba(255, 210, 100, 0.7);
  background: rgba(60, 44, 12, 0.7);
  color: #ffd76b;
  font-size: 0.8rem;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  cursor: pointer;
}

.buy-btn:hover {
  background: rgba(90, 66, 18, 0.85);
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

.skin-chip.locked::before {
  box-shadow: none;
}

.menu-actions {
  display: flex;
  gap: 12px;
}

.primary-btn,
.ghost-btn {
  padding: 13px 18px;
  border: none;
  font-family: var(--display);
  text-transform: uppercase;
  letter-spacing: 0.22em;
  font-size: 0.78rem;
  font-weight: 700;
  text-align: center;
  cursor: pointer;
  clip-path: polygon(11px 0, 100% 0, 100% calc(100% - 11px), calc(100% - 11px) 100%, 0 100%, 0 11px);
  transition: transform 0.15s ease, filter 0.15s ease;
}

.primary-btn.small,
.ghost-btn.small {
  padding: 8px 12px;
  font-size: 0.65rem;
  letter-spacing: 0.15em;
}

.primary-btn {
  background: linear-gradient(115deg, #39f9c0, #25a6ff);
  color: #041018;
  filter: drop-shadow(0 8px 22px rgba(40, 210, 190, 0.3));
}

.ghost-btn {
  background: rgba(14, 20, 38, 0.75);
  box-shadow: inset 0 0 0 1px rgba(120, 175, 255, 0.35), inset 0 1px 0 rgba(160, 210, 255, 0.1);
  color: #dbe9ff;
}

.ghost-btn.danger {
  color: rgba(255, 176, 182, 0.9);
}

.primary-btn:active,
.ghost-btn:active {
  transform: scale(0.96);
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
  font-family: var(--display);
  font-size: 0.62rem;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  text-align: center;
  color: rgba(165, 185, 220, 0.6);
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

.death-card,
.modal-card,
.gate-card {
  width: min(410px, calc(100vw - 32px));
  padding: 24px 22px;
  background: linear-gradient(180deg, rgba(13, 19, 36, 0.97), rgba(7, 10, 20, 0.97));
  box-shadow: inset 0 1px 0 rgba(160, 210, 255, 0.14);
  clip-path: polygon(16px 0, 100% 0, 100% calc(100% - 16px), calc(100% - 16px) 100%, 0 100%, 0 16px);
  display: grid;
  gap: 16px;
  text-align: center;
  filter: drop-shadow(0 24px 60px rgba(0, 0, 0, 0.55));
}

.modal-title {
  font-family: var(--display);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3em;
  font-size: 1rem;
  color: #eaf4ff;
}

.modal-actions {
  display: flex;
  flex-wrap: wrap;
  justify-content: center;
  gap: 10px;
}

.death-title {
  font-family: var(--display);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3em;
  font-size: 1rem;
  color: #eaf4ff;
}

.death-hero {
  display: grid;
  gap: 2px;
}

.death-hero-label {
  font-family: var(--display);
  font-size: 0.56rem;
  font-weight: 600;
  letter-spacing: 0.46em;
  margin-left: 0.46em;
  text-transform: uppercase;
  color: rgba(160, 195, 240, 0.6);
}

.death-hero-value {
  font-family: var(--display);
  font-size: 2.8rem;
  font-weight: 700;
  line-height: 1;
  font-variant-numeric: tabular-nums;
  color: #f6fbff;
  text-shadow: 0 0 26px rgba(75, 232, 255, 0.35);
}

.death-stats {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 8px;
}

.death-stat {
  display: grid;
  gap: 3px;
  padding: 10px 8px;
  background: rgba(9, 14, 28, 0.7);
  box-shadow: inset 0 1px 0 rgba(160, 210, 255, 0.1);
  clip-path: polygon(9px 0, 100% 0, 100% calc(100% - 9px), calc(100% - 9px) 100%, 0 100%, 0 9px);
}

.death-stat span {
  font-family: var(--display);
  font-size: 0.54rem;
  font-weight: 600;
  letter-spacing: 0.22em;
  text-transform: uppercase;
  color: rgba(165, 195, 240, 0.65);
}

.death-stat strong {
  font-family: var(--display);
  font-size: 1.05rem;
  font-variant-numeric: tabular-nums;
  color: #eef6ff;
}

.death-stat.gold strong {
  color: var(--gold);
}

.death-notice {
  padding: 8px 10px;
  font-family: var(--display);
  font-size: 0.6rem;
  font-weight: 600;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: #ffb46e;
  background: rgba(60, 30, 8, 0.55);
  box-shadow: inset 0 0 0 1px rgba(255, 180, 110, 0.35);
  clip-path: polygon(9px 0, 100% 0, 100% calc(100% - 9px), calc(100% - 9px) 100%, 0 100%, 0 9px);
}

.death-actions {
  display: grid;
  gap: 10px;
}

.gate-title {
  font-family: var(--display);
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 0.3em;
  font-size: 1rem;
  color: #eaf4ff;
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
    top: calc(8px + env(safe-area-inset-top));
    left: 10px;
    right: 10px;
  }

  .hud-pill {
    height: 34px;
    padding: 0 10px;
    font-size: 0.9rem;
  }

  .pause-btn {
    width: 34px;
    height: 34px;
  }

  .audio-pill {
    top: calc(16px + env(safe-area-inset-top));
    left: 16px;
  }

  .audio-pill.running {
    top: calc(56px + env(safe-area-inset-top));
  }

  .audio-panel {
    top: calc(64px + env(safe-area-inset-top));
    left: 16px;
  }

  .menu-layout,
  .menu-screen {
    width: 100%;
  }

  .menu-hero {
    margin-top: 2vh;
  }

  .menu-tiles {
    gap: 6px;
  }

  .menu-tile {
    padding: 11px 2px 9px;
    font-size: 0.52rem;
    letter-spacing: 0.08em;
  }

  .menu-tile svg {
    width: 19px;
    height: 19px;
  }

  .play-btn {
    width: 100%;
  }

  .menu-screen-card {
    padding: 14px;
  }

  .menu-inventory-list {
    max-height: 34vh;
  }

  .menu-controls {
    font-size: 0.56rem;
  }
}

.runner-page :is(button, a):focus-visible {
  outline: 2px solid var(--cyan);
  outline-offset: 2px;
}

@media (prefers-reduced-motion: reduce) {
  .play-btn::after {
    animation: none;
  }

  .menu-overlay {
    animation: none;
  }
}
</style>
