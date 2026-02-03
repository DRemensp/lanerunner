<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('runner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->unsignedInteger('best_distance')->default(0);
            $table->decimal('best_speed', 6, 2)->default(0);
            $table->unsignedInteger('total_runs')->default(0);
            $table->unsignedInteger('coins')->default(0);
            $table->foreignId('active_skin_id')->nullable()->constrained('skins')->nullOnDelete();
            $table->uuid('active_run_id')->nullable();
            $table->timestamp('run_started_at', 3)->nullable();
            $table->string('run_level', 32)->nullable();
            $table->unsignedInteger('integrity_runs')->default(0);
            $table->unsignedInteger('integrity_flags')->default(0);
            $table->boolean('suspicious')->default(false);
            $table->timestamp('suspicious_at')->nullable();
            $table->string('last_suspicious_reason', 120)->nullable();
            $table->timestamp('last_run_at')->nullable();
            $table->timestamps();

            $table->index('best_distance');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('runner_profiles');
    }
};
