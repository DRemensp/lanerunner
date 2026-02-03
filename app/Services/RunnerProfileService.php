<?php

namespace App\Services;

use App\Models\RunnerProfile;
use App\Models\Skin;
use App\Models\User;

class RunnerProfileService
{
    private const DEFAULT_SKINS = [
        [
            'slug' => 'neon',
            'name' => 'Neon',
            'color' => '#3bffb3',
            'price_coins' => 0,
            'is_default' => true,
        ],
        [
            'slug' => 'ember',
            'name' => 'Ember',
            'color' => '#ff6b3b',
            'price_coins' => 300,
            'is_default' => false,
        ],
        [
            'slug' => 'ion',
            'name' => 'Ion',
            'color' => '#49a8ff',
            'price_coins' => 450,
            'is_default' => false,
        ],
    ];

    public function ensureDefaultSkins(): Skin
    {
        foreach (self::DEFAULT_SKINS as $skinData) {
            Skin::updateOrCreate(
                ['slug' => $skinData['slug']],
                $skinData,
            );
        }

        return Skin::where('is_default', true)->orderBy('id')->firstOrFail();
    }

    public function ensureProfile(User $user): RunnerProfile
    {
        $defaultSkin = $this->ensureDefaultSkins();

        $profile = RunnerProfile::firstOrCreate(
            ['user_id' => $user->id],
            [
                'best_distance' => 0,
                'best_speed' => 0,
                'total_runs' => 0,
                'coins' => 0,
                'active_skin_id' => $defaultSkin->id,
            ],
        );

        $this->ensureDefaultSkinOwnership($user, $profile, $defaultSkin);

        return $profile;
    }

    private function ensureDefaultSkinOwnership(
        User $user,
        RunnerProfile $profile,
        Skin $defaultSkin,
    ): void {
        $hasDefaultSkin = $user->skins()
            ->where('skins.id', $defaultSkin->id)
            ->exists();

        if (!$hasDefaultSkin) {
            $user->skins()->attach($defaultSkin->id, ['unlocked_at' => now()]);
        }

        if (!$profile->active_skin_id) {
            $profile->active_skin_id = $defaultSkin->id;
            $profile->save();
        }
    }
}
