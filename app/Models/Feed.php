<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Feed extends Model
{
    protected $fillable = [
        'name',
        'url',
        'category',
        'topic',
        'active',
        'priority',
        'language',
    ];

    protected $casts = [
        'active' => 'boolean',
        'priority' => 'integer',
    ];

    public function news(): HasMany
    {
        return $this->hasMany(News::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderByDesc('priority')->orderBy('name');
    }
}
