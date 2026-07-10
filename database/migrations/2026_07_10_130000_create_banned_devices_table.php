<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Manual device bans (guests included): INSERT a runner_device_id here
        // and the client locks the game behind a suspension screen on the next
        // profile load. Delete the row to unban.
        Schema::create('banned_devices', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 64)->unique();
            $table->string('reason', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banned_devices');
    }
};
