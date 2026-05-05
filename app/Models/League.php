<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class League extends Model
{
    protected $fillable = [
        'external_id',
        'api_futebol_id',
        'football_data_org_code',
        'name',
        'country',
        'logo',
        'type',
        'active',
    ];

    protected $casts = [
        'active' => 'boolean',
    ];

    public function standings(): HasMany
    {
        return $this->hasMany(Standing::class);
    }

    public function fixtures(): HasMany
    {
        return $this->hasMany(Fixture::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }
}
