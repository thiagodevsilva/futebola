<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class News extends Model
{
    protected $table = 'news';

    protected $fillable = [
        'feed_id',
        'title',
        'excerpt',
        'published_at',
        'author',
        'link',
        'link_hash',
        'image_url',
        'guid',
    ];

    protected $casts = [
        'published_at' => 'datetime',
    ];

    public function feed(): BelongsTo
    {
        return $this->belongsTo(Feed::class);
    }

    public function scopeLatest($query)
    {
        return $query->orderByDesc('published_at');
    }

    public function scopeFromSource($query, int $feedId)
    {
        return $query->where('feed_id', $feedId);
    }
}
