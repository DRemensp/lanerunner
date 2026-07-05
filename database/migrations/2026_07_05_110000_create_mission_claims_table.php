<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mission_claims', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('claim_date');
            $table->unsignedTinyInteger('mission_index');
            $table->timestamps();
            $table->unique(['user_id', 'claim_date', 'mission_index']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mission_claims');
    }
};
