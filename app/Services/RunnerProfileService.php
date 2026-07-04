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
            'category' => 'runner',
            'color' => '#3bffb3',
            'price_coins' => 0,
            'is_default' => true,
        ],
        [
            'slug' => 'ember',
            'name' => 'Ember',
            'category' => 'runner',
            'color' => '#ff6b3b',
            'price_coins' => 300,
            'is_default' => false,
        ],
        [
            'slug' => 'ion',
            'name' => 'Ion',
            'category' => 'runner',
            'color' => '#49a8ff',
            'price_coins' => 450,
            'is_default' => false,
        ],
        [
            'slug' => 'dusk',
            'name' => 'Dusk',
            'category' => 'runner',
            'color' => '#b18cff',
            'price_coins' => 600,
            'is_default' => false,
        ],
        [
            'slug' => 'volt',
            'name' => 'Volt',
            'category' => 'runner',
            'color' => '#ffe14d',
            'price_coins' => 750,
            'is_default' => false,
        ],
        [
            'slug' => 'nova',
            'name' => 'Nova',
            'category' => 'runner',
            'color' => '#ff4fd8',
            'price_coins' => 900,
            'is_default' => false,
        ],
        // Zone 2 getaway cars — selectable once the player has reached the
        // drive section at least once. Slugs map to GLB keys (car-<key>).
        [
            'slug' => 'car-sedan-sports',
            'name' => 'Sports Sedan',
            'category' => 'car',
            'color' => '#ff4f6b',
            'price_coins' => 0,
            'is_default' => false,
        ],
        [
            'slug' => 'car-sedan',
            'name' => 'Sedan',
            'category' => 'car',
            'color' => '#9fb4c8',
            'price_coins' => 200,
            'is_default' => false,
        ],
        [
            'slug' => 'car-hatchback-sports',
            'name' => 'Hot Hatch',
            'category' => 'car',
            'color' => '#ffa14f',
            'price_coins' => 250,
            'is_default' => false,
        ],
        [
            'slug' => 'car-taxi',
            'name' => 'Taxi',
            'category' => 'car',
            'color' => '#ffd23b',
            'price_coins' => 350,
            'is_default' => false,
        ],
        [
            'slug' => 'car-suv',
            'name' => 'SUV',
            'category' => 'car',
            'color' => '#7dd07d',
            'price_coins' => 400,
            'is_default' => false,
        ],
        [
            'slug' => 'car-van',
            'name' => 'Van',
            'category' => 'car',
            'color' => '#b9c2ce',
            'price_coins' => 450,
            'is_default' => false,
        ],
        [
            'slug' => 'car-delivery',
            'name' => 'Delivery',
            'category' => 'car',
            'color' => '#caa16b',
            'price_coins' => 500,
            'is_default' => false,
        ],
        [
            'slug' => 'car-suv-luxury',
            'name' => 'Luxury SUV',
            'category' => 'car',
            'color' => '#d8b64f',
            'price_coins' => 600,
            'is_default' => false,
        ],
        [
            'slug' => 'car-garbage-truck',
            'name' => 'Garbage Truck',
            'category' => 'car',
            'color' => '#6fa26f',
            'price_coins' => 700,
            'is_default' => false,
        ],
        [
            'slug' => 'car-truck',
            'name' => 'Hauler',
            'category' => 'car',
            'color' => '#c46a4f',
            'price_coins' => 750,
            'is_default' => false,
        ],
        [
            'slug' => 'car-police',
            'name' => 'Police',
            'category' => 'car',
            'color' => '#4f7dff',
            'price_coins' => 800,
            'is_default' => false,
        ],
        [
            'slug' => 'car-ambulance',
            'name' => 'Ambulance',
            'category' => 'car',
            'color' => '#f2f5f8',
            'price_coins' => 900,
            'is_default' => false,
        ],
        [
            'slug' => 'car-race',
            'name' => 'Race Car',
            'category' => 'car',
            'color' => '#3bffb3',
            'price_coins' => 1000,
            'is_default' => false,
        ],
        [
            'slug' => 'car-firetruck',
            'name' => 'Fire Truck',
            'category' => 'car',
            'color' => '#ff3b3b',
            'price_coins' => 1200,
            'is_default' => false,
        ],
        // Zone 3 aircraft — more models land here once approved.
        [
            'slug' => 'plane-cesium',
            'name' => 'Cesium Air',
            'category' => 'plane',
            'color' => '#8fd0ff',
            'price_coins' => 0,
            'is_default' => false,
        ],
    ];

    public function ensureDefaultSkins(): Skin
    {
        // Re-seed whenever new skins were added to the catalog above.
        if (Skin::count() < count(self::DEFAULT_SKINS)) {
            foreach (self::DEFAULT_SKINS as $skinData) {
                Skin::updateOrCreate(
                    ['slug' => $skinData['slug']],
                    $skinData,
                );
            }
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
