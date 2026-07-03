<?php

use App\Models\RunnerProfile;
use App\Models\Skin;
use App\Models\User;
use Illuminate\Support\Carbon;

test('guests get skins but no profile', function () {
    $response = $this->getJson('/api/runner/profile');

    $response->assertOk()
        ->assertJsonPath('guest', true)
        ->assertJsonCount(3, 'skins');
});

test('a verified run updates best distance and awards coins', function () {
    $user = User::factory()->create();

    Carbon::setTestNow(now());

    $start = $this->actingAs($user)->postJson('/api/runner/run/start', [
        'level' => 'rush',
    ]);
    $start->assertOk();
    $runId = $start->json('run_id');

    Carbon::setTestNow(now()->addSeconds(60));

    $response = $this->actingAs($user)->postJson('/api/runner/run/end', [
        'distance' => 1500,
        'max_speed' => 18,
        'coins' => 120,
        'run_id' => $runId,
    ]);

    $response->assertOk()
        ->assertJsonPath('accepted', true)
        ->assertJsonPath('coins_earned', 120)
        ->assertJsonPath('profile.best_distance', 1500)
        ->assertJsonPath('profile.coins', 120);

    $profile = RunnerProfile::where('user_id', $user->id)->firstOrFail();
    expect($profile->suspicious)->toBeFalse();

    Carbon::setTestNow();
});

test('coins above the plausibility cap are clamped', function () {
    $user = User::factory()->create();

    Carbon::setTestNow(now());
    $runId = $this->actingAs($user)
        ->postJson('/api/runner/run/start', ['level' => 'rush'])
        ->json('run_id');
    Carbon::setTestNow(now()->addSeconds(60));

    $response = $this->actingAs($user)->postJson('/api/runner/run/end', [
        'distance' => 600,
        'max_speed' => 14,
        'coins' => 5000,
        'run_id' => $runId,
    ]);

    // Cap = floor(600 / 6) + 10 = 110.
    $response->assertOk()->assertJsonPath('coins_earned', 110);

    Carbon::setTestNow();
});

test('an impossible distance is flagged and does not count', function () {
    $user = User::factory()->create();

    Carbon::setTestNow(now());
    $runId = $this->actingAs($user)
        ->postJson('/api/runner/run/start', ['level' => 'rush'])
        ->json('run_id');
    Carbon::setTestNow(now()->addSeconds(5));

    $response = $this->actingAs($user)->postJson('/api/runner/run/end', [
        'distance' => 999999,
        'max_speed' => 12,
        'coins' => 50,
        'run_id' => $runId,
    ]);

    $response->assertOk()
        ->assertJsonPath('accepted', false)
        ->assertJsonPath('coins_earned', 0)
        ->assertJsonPath('profile.best_distance', 0);

    $profile = RunnerProfile::where('user_id', $user->id)->firstOrFail();
    expect($profile->suspicious)->toBeTrue()
        ->and($profile->last_suspicious_reason)->toContain('distance_over_cap');

    Carbon::setTestNow();
});

test('a missing run session is rejected but not flagged', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/runner/run/end', [
        'distance' => 500,
        'max_speed' => 14,
        'coins' => 40,
    ]);

    $response->assertOk()
        ->assertJsonPath('accepted', false)
        ->assertJsonPath('profile.best_distance', 0);

    $profile = RunnerProfile::where('user_id', $user->id)->firstOrFail();
    expect($profile->suspicious)->toBeFalse();
});

test('buying a skin deducts coins and unlocks it', function () {
    $user = User::factory()->create();

    // Seed skins + profile, then give the user enough coins.
    $this->actingAs($user)->getJson('/api/runner/profile')->assertOk();
    RunnerProfile::where('user_id', $user->id)->update(['coins' => 300]);
    $ember = Skin::where('slug', 'ember')->firstOrFail();

    $response = $this->actingAs($user)->postJson('/api/runner/skin/buy', [
        'skin_id' => $ember->id,
    ]);

    $response->assertOk()->assertJsonPath('coins', 0);
    expect($user->skins()->where('skins.id', $ember->id)->exists())->toBeTrue();

    // Buying again is idempotent and free.
    $this->actingAs($user)
        ->postJson('/api/runner/skin/buy', ['skin_id' => $ember->id])
        ->assertOk()
        ->assertJsonPath('coins', 0);
});

test('buying a skin without enough coins fails', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/runner/profile')->assertOk();
    $ion = Skin::where('slug', 'ion')->firstOrFail();

    $this->actingAs($user)
        ->postJson('/api/runner/skin/buy', ['skin_id' => $ion->id])
        ->assertStatus(422);

    expect($user->skins()->where('skins.id', $ion->id)->exists())->toBeFalse();
});

test('an unowned skin cannot be equipped', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->getJson('/api/runner/profile')->assertOk();
    $ion = Skin::where('slug', 'ion')->firstOrFail();

    $this->actingAs($user)
        ->postJson('/api/runner/skin', ['skin_id' => $ion->id])
        ->assertStatus(403);
});
