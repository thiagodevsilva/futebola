<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Standing extends Model
{
    protected $fillable = [
        'league_id',
        'season',
        'rank',
        'team_id',
        'team_name',
        'team_logo',
        'points',
        'played',
        'win',
        'draw',
        'loss',
        'goals_for',
        'goals_against',
        'goal_diff',
        'form',
    ];

    protected $casts = [
        'season' => 'integer',
        'rank' => 'integer',
        'points' => 'integer',
        'played' => 'integer',
        'win' => 'integer',
        'draw' => 'integer',
        'loss' => 'integer',
        'goals_for' => 'integer',
        'goals_against' => 'integer',
        'goal_diff' => 'integer',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }
}
