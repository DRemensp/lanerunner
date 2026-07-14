<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guest extends Model
{
    protected $fillable = [
        'ip',
        'last_device_id',
        'runs_count',
        'last_run_1',
        'last_run_2',
        'last_run_3',
        'last_run_4',
        'last_run_5',
    ];

    protected $attributes = [
        'runs_count' => 0,
    ];

    /**
     * Log a finished guest run: bump the counter and shift the score into
     * the last-five window (newest in last_run_1). Every submitted run
     * counts — guests never pass through the anti-cheat.
     */
    public static function recordRun(string $ip, ?string $deviceId, int $score): void
    {
        $guest = static::firstOrNew(['ip' => $ip]);
        $guest->runs_count += 1;
        $guest->last_run_5 = $guest->last_run_4;
        $guest->last_run_4 = $guest->last_run_3;
        $guest->last_run_3 = $guest->last_run_2;
        $guest->last_run_2 = $guest->last_run_1;
        $guest->last_run_1 = $score;
        if ($deviceId) {
            $guest->last_device_id = $deviceId;
        }
        $guest->save();
    }
}
