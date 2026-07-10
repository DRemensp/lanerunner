<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('runner_profiles', function (Blueprint $table) {
            // Per-stage endless rankings: best verified endless score per
            // stage. Endless never touches best_distance — that stays the
            // classic-only world ranking.
            $table->unsignedInteger('endless1_best')->default(0);
            $table->unsignedInteger('endless2_best')->default(0);
            $table->unsignedInteger('endless3_best')->default(0);
            $table->unsignedInteger('endless4_best')->default(0);
            // Stage of the active endless run session, set on run/start and
            // consumed on run/end alongside run_mode.
            $table->unsignedTinyInteger('run_stage')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('runner_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'endless1_best',
                'endless2_best',
                'endless3_best',
                'endless4_best',
                'run_stage',
            ]);
        });
    }
};
