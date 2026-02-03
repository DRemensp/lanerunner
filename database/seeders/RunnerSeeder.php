<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\RunnerProfileService;
use Illuminate\Database\Seeder;

class RunnerSeeder extends Seeder
{
    public function run(): void
    {
        $service = app(RunnerProfileService::class);
        $service->ensureDefaultSkins();

        User::query()->each(function (User $user) use ($service) {
            $service->ensureProfile($user);
        });
    }
}
