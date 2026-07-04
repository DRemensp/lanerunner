<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Skin extends Model
{
    use HasFactory;

    protected $fillable = [
        'slug',
        'name',
        'category',
        'color',
        'price_coins',
        'is_default',
    ];

    protected $casts = [
        'is_default' => 'boolean',
    ];

    public function users()
    {
        return $this->belongsToMany(User::class, 'user_skins')
            ->withPivot('unlocked_at')
            ->withTimestamps();
    }
}
