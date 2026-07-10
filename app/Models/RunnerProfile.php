<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class RunnerProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'best_distance',
        'best_speed',
        'total_runs',
        'coins',
        'active_skin_id',
        'active_run_id',
        'run_started_at',
        'run_level',
        'integrity_runs',
        'integrity_flags',
        'suspicious',
        'suspicious_at',
        'last_suspicious_reason',
        'last_run_at',
        'last_device_id',
        'banned_at',
        'stage2_reaches',
        'stage3_reaches',
        'stage4_reaches',
        'run_mode',
        'run_stage',
        'endless1_best',
        'endless2_best',
        'endless3_best',
        'endless4_best',
    ];

    protected $casts = [
        'last_run_at' => 'datetime',
        'run_started_at' => 'datetime',
        'suspicious' => 'boolean',
        'suspicious_at' => 'datetime',
        'banned_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function activeSkin()
    {
        return $this->belongsTo(Skin::class, 'active_skin_id');
    }
}
