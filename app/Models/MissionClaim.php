<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MissionClaim extends Model
{
    protected $fillable = [
        'user_id',
        'claim_date',
        'mission_index',
    ];

    protected $casts = [
        'claim_date' => 'date',
    ];
}
