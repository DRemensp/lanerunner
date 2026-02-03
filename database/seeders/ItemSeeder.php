<?php

namespace Database\Seeders;

use App\Models\Item;
use Illuminate\Database\Seeder;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'slug' => 'coin_boost',
                'name' => 'Coin Boost',
                'type' => 'boost',
                'price_coins' => 250,
                'max_stack' => 10,
                'is_active' => true,
                'meta' => [
                    'duration' => 15,
                    'multiplier' => 2,
                ],
            ],
            [
                'slug' => 'shield',
                'name' => 'Shield',
                'type' => 'defense',
                'price_coins' => 300,
                'max_stack' => 5,
                'is_active' => true,
                'meta' => [
                    'hits' => 1,
                ],
            ],
            [
                'slug' => 'magnet',
                'name' => 'Magnet',
                'type' => 'utility',
                'price_coins' => 350,
                'max_stack' => 5,
                'is_active' => true,
                'meta' => [
                    'radius' => 3,
                ],
            ],
        ];

        foreach ($items as $item) {
            Item::updateOrCreate(
                ['slug' => $item['slug']],
                $item,
            );
        }
    }
}
