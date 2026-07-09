<?php

namespace App\Http\Controllers;

use App\Models\RunnerProfile;
use App\Services\RunnerProfileService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class RunnerController extends Controller
{
    // Must match levelOptions in resources/js/Pages/Game.vue: one big speed
    // jump per 2,500-point checkpoint. The growth ratio speed_step/STEP
    // stays identical to the old per-500 curve, so the duration model holds.
    private const LEVELS = [
        'casual' => [
            'base_speed' => 10,
            'speed_step' => 5,
        ],
        'rush' => [
            'base_speed' => 12,
            'speed_step' => 10,
        ],
        'night' => [
            'base_speed' => 14,
            // 14 instead of 20: Night top speed lowered from 74 to 56 (2026-07-08),
            // in sync with levelOptions in resources/js/game/constants.js.
            'speed_step' => 14,
        ],
    ];

    private const STEP_DISTANCE = 2500;

    private const SCORE_MULTIPLIER = 2.4;

    // Past this distance the run switches to zone 2 (driving), which allows
    // speeds up to 160 and its own score pacing.
    private const FINALE_DISTANCE = 10000;

    private const DRIVE_MAX_SPEED = 160.0;

    // Past FINALE_DISTANCE the run leaves the runner speed curve entirely:
    // zone 2 driving pays 2.4 * speed (client-capped at 80), zones 3+ stack
    // flat kill bonuses on top (drones +600, motherships +2500) plus
    // near-miss/graze chains and god-mode smash streaks. MAX_ZONE_RATE is a
    // generous ceiling on sustained points per second past the finale;
    // score itself stays uncapped server-side, only pacing is checked.
    private const MAX_ZONE_RATE = 800.0;

    public function profile(Request $request, RunnerProfileService $service): Response
    {
        $service->ensureDefaultSkins();
        $skins = \App\Models\Skin::orderBy('id')->get([
            'id',
            'slug',
            'name',
            'category',
            'color',
            'price_coins',
            'is_default',
        ]);

        $user = $request->user();
        if (! $user) {
            return response([
                'guest' => true,
                'skins' => $skins,
            ]);
        }

        $profile = $service->ensureProfile($user);
        $inventory = $user->userItems()
            ->with('item')
            ->where('quantity', '>', 0)
            ->get()
            ->map(function ($entry) {
                return [
                    'item_id' => $entry->item_id,
                    'slug' => $entry->item?->slug,
                    'name' => $entry->item?->name,
                    'type' => $entry->item?->type,
                    'quantity' => $entry->quantity,
                ];
            });

        return response([
            'guest' => false,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
            ],
            'profile' => [
                'best_distance' => $profile->best_distance,
                'best_speed' => (float) $profile->best_speed,
                'total_runs' => $profile->total_runs,
                'coins' => $profile->coins,
                'active_skin_id' => $profile->active_skin_id,
            ],
            'skins' => $skins,
            'owned_skin_ids' => $user->skins()->pluck('skins.id')->values(),
            'inventory' => $inventory,
            'mission_claims' => \App\Models\MissionClaim::where('user_id', $user->id)
                ->whereDate('claim_date', now()->toDateString())
                ->pluck('mission_index')
                ->values(),
        ]);
    }

    public function startRun(Request $request, RunnerProfileService $service): Response
    {
        $validated = $request->validate([
            'level' => ['required', 'string', Rule::in(array_keys(self::LEVELS))],
        ]);

        $user = $request->user();
        if (! $user) {
            return response([
                'guest' => true,
            ]);
        }

        $profile = $service->ensureProfile($user);
        $profile->active_run_id = (string) Str::uuid();
        $profile->run_started_at = now();
        $profile->run_level = $validated['level'];
        $profile->save();

        return response([
            'run_id' => $profile->active_run_id,
            'started_at' => $profile->run_started_at?->toIso8601String(),
        ]);
    }

    public function endRun(Request $request, RunnerProfileService $service): Response
    {
        $validated = $request->validate([
            'distance' => ['required', 'integer', 'min:0'],
            'max_speed' => ['nullable', 'numeric', 'min:0'],
            'coins' => ['nullable', 'integer', 'min:0'],
            'run_id' => ['nullable', 'uuid'],
        ]);

        $user = $request->user();
        if (! $user) {
            return response([
                'guest' => true,
                'accepted' => false,
            ]);
        }

        $profile = $service->ensureProfile($user);
        $distance = (int) $validated['distance'];
        $maxSpeed = isset($validated['max_speed']) ? (float) $validated['max_speed'] : 0.0;

        $profile->integrity_runs += 1;

        $verified = false;
        $cheatReasons = [];
        $runId = $validated['run_id'] ?? null;

        if (! $profile->active_run_id || ! $profile->run_started_at || ! $runId) {
            // No run session (e.g. network hiccup on start): the run does not
            // count for records, but the player is not marked suspicious.
        } elseif ($runId !== $profile->active_run_id) {
            $cheatReasons[] = 'run_id_mismatch';
        } else {
            $verified = true;
            $durationMs = max(0, $profile->run_started_at->diffInMilliseconds(now()));
            $durationSeconds = max(1.0, $durationMs / 1000);
            $levelKey = $profile->run_level ?? 'rush';
            $level = self::LEVELS[$levelKey] ?? self::LEVELS['rush'];
            // Pacing sanity: instead of projecting a max score from the
            // zone-1 runner curve (which flags legit bonus-heavy zone 3+
            // runs), require the run to have lasted at least as long as a
            // perfect player would need for this score.
            $minDuration = $this->minPlausibleDuration(
                (float) $distance,
                $level['base_speed'],
                $level['speed_step'],
            );
            if ($durationSeconds < $minDuration) {
                $cheatReasons[] = 'distance_over_cap';
                $verified = false;
            }

            // Mirrors the client's continuous ramp: base + min(3, d/2500) * step
            // (Game.vue targetSpeed). Tier cap 3 == the old top tier.
            $expectedSpeed = $level['base_speed']
                + min(3.0, $distance / self::STEP_DISTANCE) * $level['speed_step'];
            if ($distance >= self::FINALE_DISTANCE) {
                $expectedSpeed = max($expectedSpeed, self::DRIVE_MAX_SPEED);
            }
            if ($maxSpeed > $expectedSpeed + 2.5) {
                $cheatReasons[] = 'speed_over_cap';
                $verified = false;
            }
        }

        $profile->total_runs += 1;
        $profile->last_run_at = now();

        $coinsEarned = 0;

        if ($verified) {
            if ($distance > $profile->best_distance) {
                $profile->best_distance = $distance;
            }

            if ($maxSpeed > $profile->best_speed) {
                $profile->best_speed = $maxSpeed;
            }

            // Coins spawn as trails of up to ~8 per row plus jump arcs and
            // jam-roof lines; a perfect line hugger stays under distance/2.
            $coinsCap = (int) floor($distance / 2) + 25;
            $coinsEarned = min((int) ($validated['coins'] ?? 0), $coinsCap);
            $profile->coins += $coinsEarned;
        }

        if (! empty($cheatReasons)) {
            $profile->integrity_flags += 1;
            $profile->suspicious = true;
            $profile->suspicious_at = now();
            $profile->last_suspicious_reason = implode(';', $cheatReasons);
        }

        $profile->active_run_id = null;
        $profile->run_started_at = null;
        $profile->run_level = null;
        $profile->save();

        return response([
            'guest' => false,
            'accepted' => $verified,
            'coins_earned' => $coinsEarned,
            'profile' => [
                'best_distance' => $profile->best_distance,
                'best_speed' => (float) $profile->best_speed,
                'total_runs' => $profile->total_runs,
                'coins' => $profile->coins,
            ],
        ]);
    }

    public function buySkin(Request $request, RunnerProfileService $service): Response
    {
        $validated = $request->validate([
            'skin_id' => ['required', 'integer', 'exists:skins,id'],
        ]);

        $user = $request->user();
        if (! $user) {
            return response([
                'guest' => true,
                'message' => 'Login required.',
            ], 401);
        }

        $profile = $service->ensureProfile($user);
        $skin = \App\Models\Skin::findOrFail((int) $validated['skin_id']);

        $alreadyOwned = $user->skins()->where('skins.id', $skin->id)->exists();
        if (! $alreadyOwned) {
            if ($profile->coins < $skin->price_coins) {
                return response([
                    'message' => 'Not enough coins.',
                    'coins' => $profile->coins,
                ], 422);
            }

            $profile->coins -= $skin->price_coins;
            $profile->save();
            $user->skins()->attach($skin->id, ['unlocked_at' => now()]);
        }

        return response([
            'coins' => $profile->coins,
            'owned_skin_ids' => $user->skins()->pluck('skins.id')->values(),
        ]);
    }

    public function setSkin(Request $request, RunnerProfileService $service): Response
    {
        $validated = $request->validate([
            'skin_id' => ['required', 'integer', 'exists:skins,id'],
        ]);

        $user = $request->user();
        if (! $user) {
            return response([
                'guest' => true,
                'message' => 'Login required.',
            ], 401);
        }

        $profile = $service->ensureProfile($user);
        $skinId = (int) $validated['skin_id'];

        $ownsSkin = $user->skins()->where('skins.id', $skinId)->exists();
        if (! $ownsSkin) {
            return response([
                'message' => 'Skin not unlocked.',
            ], 403);
        }

        $profile->active_skin_id = $skinId;
        $profile->save();

        return response([
            'active_skin_id' => $profile->active_skin_id,
        ]);
    }

    // Fixed rewards per daily-mission slot. Mission definitions live in the
    // client (seeded from the date); the server only rate-limits claims to
    // one per slot per day, so the worst-case abuse is 240 coins daily.
    private const MISSION_REWARDS = [60, 80, 100];

    public function claimMission(Request $request, RunnerProfileService $service): Response
    {
        $validated = $request->validate([
            'mission_index' => ['required', 'integer', 'min:0', 'max:2'],
        ]);

        $user = $request->user();
        if (! $user) {
            return response(['message' => 'Login required.'], 401);
        }

        $profile = $service->ensureProfile($user);
        $index = (int) $validated['mission_index'];
        $today = now()->toDateString();

        $claim = \App\Models\MissionClaim::firstOrCreate([
            'user_id' => $user->id,
            'claim_date' => $today,
            'mission_index' => $index,
        ]);

        if ($claim->wasRecentlyCreated) {
            $profile->coins += self::MISSION_REWARDS[$index];
            $profile->save();
        }

        $claimed = \App\Models\MissionClaim::where('user_id', $user->id)
            ->whereDate('claim_date', $today)
            ->pluck('mission_index')
            ->values();

        return response([
            'coins' => $profile->coins,
            'claimed' => $claimed,
        ]);
    }

    public function leaderboard(Request $request): Response
    {
        $leaders = RunnerProfile::with('user')
            ->where('best_distance', '>', 0)
            ->orderByDesc('best_distance')
            ->limit(10)
            ->get()
            ->map(function (RunnerProfile $profile) {
                return [
                    'name' => $profile->user?->name ?? 'Runner',
                    'best_distance' => $profile->best_distance,
                    'best_speed' => (float) $profile->best_speed,
                ];
            });

        $yourRank = null;
        $user = $request->user();
        if ($user) {
            $profile = RunnerProfile::where('user_id', $user->id)->first();
            if ($profile && $profile->best_distance > 0) {
                $yourRank = RunnerProfile::where('best_distance', '>', $profile->best_distance)
                    ->count() + 1;
            }
        }

        return response([
            'leaders' => $leaders,
            'your_rank' => $yourRank,
        ]);
    }

    private function minPlausibleDuration(float $distance, int $baseSpeed, int $speedStep): float
    {
        // Zone 1 pacing solves d'(t) = SCORE_MULTIPLIER * (baseSpeed +
        // d/STEP_DISTANCE * speedStep); the 2x score power-up can at most
        // double that rate, so invert the doubled curve for the fastest
        // legit time through the runner stretch.
        $growth = 2 * self::SCORE_MULTIPLIER * $speedStep / self::STEP_DISTANCE;
        $scale = $baseSpeed * self::STEP_DISTANCE / $speedStep;
        $seconds = log(1 + min($distance, self::FINALE_DISTANCE) / $scale) / $growth;

        // Everything past the finale is paced linearly by MAX_ZONE_RATE.
        if ($distance > self::FINALE_DISTANCE) {
            $seconds += ($distance - self::FINALE_DISTANCE) / self::MAX_ZONE_RATE;
        }

        // 25% grace plus a flat 5s so borderline legit runs never flag.
        return max(1.0, $seconds * 0.75 - 5.0);
    }
}
