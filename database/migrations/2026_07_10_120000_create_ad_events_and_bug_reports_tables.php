<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Raw ad telemetry, one row per event — aggregate freely in SQL.
        // device_id identifies guests too (client-generated UUID).
        Schema::create('ad_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_id', 64)->index();
            // interstitial_shown | interstitial_empty | rewarded_shown | rewarded_failed
            $table->string('type', 32);
            $table->timestamps();
            $table->index(['type', 'created_at']);
        });

        // Player bug reports, read directly from the database.
        Schema::create('bug_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('device_id', 64)->index();
            $table->text('message');
            $table->string('context', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bug_reports');
        Schema::dropIfExists('ad_events');
    }
};
