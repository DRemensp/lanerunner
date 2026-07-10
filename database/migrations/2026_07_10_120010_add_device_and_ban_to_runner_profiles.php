<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runner_profiles', function (Blueprint $table) {
            // Last device the account played on — correlates accounts with
            // guest telemetry/bug reports for cheater hunting.
            $table->string('last_device_id', 64)->nullable()->index();
            // Manual ban switch (set directly in the DB): banned accounts keep
            // playing, but nothing ranks and they vanish from the leaderboard.
            $table->timestamp('banned_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('runner_profiles', function (Blueprint $table) {
            $table->dropColumn(['last_device_id', 'banned_at']);
        });
    }
};
