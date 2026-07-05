<?php

use App\Http\Controllers\ProfileController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\RunnerController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Redirect to game directly
Route::get('/', function () {
    return redirect('/game');
});

Route::get('/dashboard', function () {
    return redirect('/game');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Game routes (no auth required for testing)
Route::get('/game', function () {
    return Inertia::render('Game');
})->name('game');

Route::prefix('api/runner')->group(function () {
    Route::get('/profile', [RunnerController::class, 'profile']);
    Route::post('/run/start', [RunnerController::class, 'startRun']);
    Route::post('/run/end', [RunnerController::class, 'endRun']);
    Route::post('/skin', [RunnerController::class, 'setSkin']);
    Route::post('/skin/buy', [RunnerController::class, 'buySkin']);
    Route::get('/leaderboard', [RunnerController::class, 'leaderboard']);
    Route::post('/mission/claim', [RunnerController::class, 'claimMission']);
});

Route::middleware('auth')->prefix('api/inventory')->group(function () {
    Route::get('/', [InventoryController::class, 'index']);
    Route::post('/grant', [InventoryController::class, 'grant']);
    Route::post('/consume', [InventoryController::class, 'consume']);
});

require __DIR__.'/auth.php';
