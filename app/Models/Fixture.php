<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Fixture extends Model
{
    protected $fillable = [
        'league_id',
        'external_fixture_id',
        'season',
        'date',
        'home_team_id',
        'home_team_name',
        'home_team_logo',
        'away_team_id',
        'away_team_name',
        'away_team_logo',
        'home_goals',
        'away_goals',
        'status',
        'venue',
        'match_round',
    ];

    protected $casts = [
        'date' => 'datetime',
        'season' => 'integer',
        'match_round' => 'integer',
        'home_goals' => 'integer',
        'away_goals' => 'integer',
    ];

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }
}
