<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Activity log for players who never log in: one row per IP with the
        // total run count and the last five submitted scores (newest in
        // last_run_1), valid or not — guests skip the anti-cheat entirely.
        Schema::create('guests', function (Blueprint $table) {
            $table->id();
            $table->string('ip', 45)->unique();
            $table->string('last_device_id', 64)->nullable();
            $table->unsignedInteger('runs_count')->default(0);
            $table->unsignedInteger('last_run_1')->nullable();
            $table->unsignedInteger('last_run_2')->nullable();
            $table->unsignedInteger('last_run_3')->nullable();
            $table->unsignedInteger('last_run_4')->nullable();
            $table->unsignedInteger('last_run_5')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guests');
    }
};
