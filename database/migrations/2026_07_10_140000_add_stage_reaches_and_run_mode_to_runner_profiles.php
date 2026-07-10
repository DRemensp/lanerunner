<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runner_profiles', function (Blueprint $table) {
            // Endless-mode unlocks: how many verified classic runs reached
            // stage 2/3/4 (one increment per run). Stage N endless unlocks
            // at 5. Stage 1 is always unlocked, so it needs no counter.
            $table->unsignedInteger('stage2_reaches')->default(0);
            $table->unsignedInteger('stage3_reaches')->default(0);
            $table->unsignedInteger('stage4_reaches')->default(0);
            // Mode of the active run session ('classic'/'endless'), set on
            // run/start and consumed on run/end alongside active_run_id.
            $table->string('run_mode', 16)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('runner_profiles', function (Blueprint $table) {
            $table->dropColumn(['stage2_reaches', 'stage3_reaches', 'stage4_reaches', 'run_mode']);
        });
    }
};
