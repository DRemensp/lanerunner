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
    private const LEVELS = [
        'casual' => [
            'base_speed' => 10,
        ],
        'rush' => [
            'base_speed' => 12,
        ],
        'night' => [
            'base_speed' => 14,
        ],
    ];

    private const SPEED_STEP = 2;
    private const STEP_DISTANCE = 500;
    private const SCORE_MULTIPLIER = 2.4;

    public function profile(Request $request, RunnerProfileService $service): Response
    {
        $service->ensureDefaultSkins();
        $skins = \App\Models\Skin::orderBy('id')->get([
            'id',
            'slug',
            'name',
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

        $flagReasons = [];
        $runId = $validated['run_id'] ?? null;

        if (!$profile->active_run_id || !$profile->run_started_at || !$runId) {
            $flagReasons[] = 'missing_run_start';
        } elseif ($runId !== $profile->active_run_id) {
            $flagReasons[] = 'run_id_mismatch';
        } else {
            $durationMs = max(0, now()->diffInMilliseconds($profile->run_started_at));
            $durationSeconds = max(1.0, $durationMs / 1000);
            $levelKey = $profile->run_level ?? 'rush';
            $baseSpeed = self::LEVELS[$levelKey]['base_speed'] ?? self::LEVELS['rush']['base_speed'];
            $maxDistance = $this->maxDistanceForDuration($durationSeconds, $baseSpeed);

            if ($distance > $maxDistance * 1.2) {
                $flagReasons[] = 'distance_over_cap';
            }

            $expectedSpeed = $baseSpeed + (int) floor($distance / self::STEP_DISTANCE) * self::SPEED_STEP;
            if ($maxSpeed > $expectedSpeed + 2.5) {
                $flagReasons[] = 'speed_over_cap';
            }
        }

        $profile->total_runs += 1;
        $profile->last_run_at = now();

        if ($distance > $profile->best_distance) {
            $profile->best_distance = $distance;
        }

        if ($maxSpeed > $profile->best_speed) {
            $profile->best_speed = $maxSpeed;
        }

        if (!empty($flagReasons)) {
            $profile->integrity_flags += 1;
            $profile->suspicious = true;
            $profile->suspicious_at = now();
            $profile->last_suspicious_reason = implode(';', $flagReasons);
        }

        $profile->active_run_id = null;
        $profile->run_started_at = null;
        $profile->run_level = null;
        $profile->save();

        return response([
            'guest' => false,
            'accepted' => true,
            'profile' => [
                'best_distance' => $profile->best_distance,
                'best_speed' => (float) $profile->best_speed,
                'total_runs' => $profile->total_runs,
            ],
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

    public function leaderboard(): Response
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

        return response([
            'leaders' => $leaders,
        ]);
    }

    private function maxDistanceForDuration(float $durationSeconds, int $baseSpeed): float
    {
        $growth = self::SCORE_MULTIPLIER / self::STEP_DISTANCE;
        $scale = $baseSpeed * self::STEP_DISTANCE;

        return $scale * (exp($growth * $durationSeconds) - 1);
    }
}
