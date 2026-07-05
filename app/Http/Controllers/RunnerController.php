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
    // Must match levelOptions in resources/js/Pages/Game.vue.
    private const LEVELS = [
        'casual' => [
            'base_speed' => 10,
            'speed_step' => 1,
        ],
        'rush' => [
            'base_speed' => 12,
            'speed_step' => 2,
        ],
        'night' => [
            'base_speed' => 14,
            'speed_step' => 4,
        ],
    ];

    private const STEP_DISTANCE = 500;
    private const SCORE_MULTIPLIER = 2.4;

    // Past this distance the run switches to zone 2 (driving), which allows
    // speeds up to 160 and its own score pacing.
    private const FINALE_DISTANCE = 10000;
    private const DRIVE_MAX_SPEED = 160.0;

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
        if (!$user) {
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
        ]);
    }

    public function startRun(Request $request, RunnerProfileService $service): Response
    {
        $validated = $request->validate([
            'level' => ['required', 'string', Rule::in(array_keys(self::LEVELS))],
        ]);

        $user = $request->user();
        if (!$user) {
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
        if (!$user) {
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

        if (!$profile->active_run_id || !$profile->run_started_at || !$runId) {
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
            $maxDistance = $this->maxDistanceForDuration(
                $durationSeconds,
                $level['base_speed'],
                $level['speed_step'],
            );

            // Zone 2 lets skilled players out-earn the runner speed curve for
            // a while, so grant flat headroom once the finale is reached.
            // The 1.35 factor also absorbs skill bonuses on top of raw
            // distance: near-miss combo chains, traffic-wave clears and
            // god-mode smash streaks.
            $distanceCap = $maxDistance * 1.35
                + ($distance >= self::FINALE_DISTANCE ? 8000 : 0);
            if ($distance > $distanceCap) {
                $cheatReasons[] = 'distance_over_cap';
                $verified = false;
            }

            $expectedSpeed = $level['base_speed']
                + (int) floor($distance / self::STEP_DISTANCE) * $level['speed_step'];
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

            // Coins spawn in lines of 5 roughly every other obstacle row, which
            // works out to well under distance/6; anything above that is fake.
            $coinsCap = (int) floor($distance / 6) + 10;
            $coinsEarned = min((int) ($validated['coins'] ?? 0), $coinsCap);
            $profile->coins += $coinsEarned;
        }

        if (!empty($cheatReasons)) {
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
        if (!$user) {
            return response([
                'guest' => true,
                'message' => 'Login required.',
            ], 401);
        }

        $profile = $service->ensureProfile($user);
        $skin = \App\Models\Skin::findOrFail((int) $validated['skin_id']);

        $alreadyOwned = $user->skins()->where('skins.id', $skin->id)->exists();
        if (!$alreadyOwned) {
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
        if (!$user) {
            return response([
                'guest' => true,
                'message' => 'Login required.',
            ], 401);
        }

        $profile = $service->ensureProfile($user);
        $skinId = (int) $validated['skin_id'];

        $ownsSkin = $user->skins()->where('skins.id', $skinId)->exists();
        if (!$ownsSkin) {
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

    private function maxDistanceForDuration(float $durationSeconds, int $baseSpeed, int $speedStep): float
    {
        // Solves d'(t) = SCORE_MULTIPLIER * (baseSpeed + d/STEP_DISTANCE * speedStep).
        $growth = self::SCORE_MULTIPLIER * $speedStep / self::STEP_DISTANCE;
        $scale = $baseSpeed * self::STEP_DISTANCE / $speedStep;

        return $scale * (exp($growth * $durationSeconds) - 1);
    }
}
