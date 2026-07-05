// Daily missions: three per day, deterministically seeded from the date.
// Progress is tracked locally; claiming rewards goes through the backend
// (one claim per slot per day, fixed rewards).
import { ref, computed } from 'vue';
import axios from 'axios';

export const missionRewards = [60, 80, 100];

const missionPool = [
  { id: 'coins', text: (n) => `Collect ${n} coins today`, targets: [80, 130, 200], stat: 'coins' },
  { id: 'near', text: (n) => `${n} near misses today`, targets: [10, 18, 30], stat: 'nearMisses' },
  { id: 'score', text: (n) => `Reach ${n} points in one run`, targets: [2000, 3500, 5500], stat: 'bestScore' },
  { id: 'runs', text: (n) => `Finish ${n} runs today`, targets: [3, 5, 8], stat: 'runs' },
];

const todayKey = () => new Date().toISOString().slice(0, 10);

const mulberry32 = (seed) => {
  let a = seed | 0;
  return () => {
    a = (a + 0x6d2b79f5) | 0;
    let t = Math.imul(a ^ (a >>> 15), 1 | a);
    t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
    return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
  };
};

const hashString = (input) => {
  let h = 0;
  for (const ch of input) h = (h * 31 + ch.charCodeAt(0)) | 0;
  return h;
};

export function createMissions({
  isGuest,
  showLoginPrompt,
  shopMessage,
  totalCoins,
  runCoins,
  runNearMisses,
  score,
  sfx,
}) {
  const dailyMissions = computed(() => {
    const rng = mulberry32(hashString(todayKey()));
    const pool = [...missionPool];
    // Fisher-Yates with the seeded rng keeps the pick deterministic per day.
    for (let i = pool.length - 1; i > 0; i -= 1) {
      const j = Math.floor(rng() * (i + 1));
      [pool[i], pool[j]] = [pool[j], pool[i]];
    }
    return pool.slice(0, 3).map((mission, slot) => {
      const target = mission.targets[Math.floor(rng() * mission.targets.length)];
      return {
        key: `${todayKey()}-${mission.id}`,
        label: mission.text(target),
        stat: mission.stat,
        target,
        slot,
      };
    });
  });

  const emptyDailyStats = () => ({
    date: todayKey(),
    coins: 0,
    nearMisses: 0,
    bestScore: 0,
    runs: 0,
  });
  const dailyStats = ref(emptyDailyStats());

  const loadDailyStats = () => {
    try {
      const raw = JSON.parse(localStorage.getItem('runner_daily_stats') || 'null');
      if (raw && raw.date === todayKey()) {
        dailyStats.value = { ...emptyDailyStats(), ...raw };
      }
    } catch (error) {
      // Corrupt storage — start fresh.
    }
  };

  const recordDailyStats = () => {
    if (dailyStats.value.date !== todayKey()) {
      dailyStats.value = emptyDailyStats();
    }
    const stats = dailyStats.value;
    stats.coins += runCoins.value;
    stats.nearMisses += runNearMisses.value;
    stats.bestScore = Math.max(stats.bestScore, Math.floor(score.value));
    stats.runs += 1;
    localStorage.setItem('runner_daily_stats', JSON.stringify(stats));
  };

  const missionProgress = (mission) => Math.floor(dailyStats.value[mission.stat] || 0);
  const claimedMissions = ref([]);
  const completedMissionCount = computed(
    () =>
      dailyMissions.value.filter(
        (mission, index) =>
          missionProgress(mission) >= mission.target && !claimedMissions.value.includes(index),
      ).length,
  );

  const claimMission = async (index) => {
    shopMessage.value = '';
    if (isGuest.value) {
      showLoginPrompt.value = true;
      return;
    }
    try {
      const response = await axios.post('/api/runner/mission/claim', { mission_index: index });
      totalCoins.value = response.data.coins ?? totalCoins.value;
      claimedMissions.value = Array.isArray(response.data.claimed)
        ? response.data.claimed.map((i) => Number(i))
        : claimedMissions.value;
      shopMessage.value = `Reward claimed — +${missionRewards[index]} coins!`;
      sfx.powerup();
    } catch (error) {
      shopMessage.value = error.response?.data?.message || 'Claim failed.';
    }
  };

  return {
    dailyMissions,
    dailyStats,
    claimedMissions,
    completedMissionCount,
    missionProgress,
    claimMission,
    loadDailyStats,
    recordDailyStats,
  };
}
