<?php

namespace App\Services\Rss;

use App\Models\Feed;
use App\Models\News;
use Illuminate\Support\Facades\Log;

class RssAggregatorService
{
    public function __construct(
        protected RssParser $parser,
        protected RssNormalizer $normalizer
    ) {
    }

    /**
     * Processa um feed: baixa, parseia, normaliza, deduplica e persiste.
     * Retorna quantidade de itens novos inseridos.
     */
    public function processFeed(Feed $feed): int
    {
        $xml = $this->parser->fetch($feed->url);
        if ($xml === null) {
            return 0;
        }

        $rawItems = $this->parser->parse($xml);
        $newCount = 0;

        foreach ($rawItems as $raw) {
            $normalized = $this->normalizer->normalize($raw, $feed->name);
            if (empty($normalized['link'])) {
                continue;
            }

            $linkHash = hash('sha256', $normalized['link']);
            if ($this->existsByLinkHash($linkHash)) {
                continue;
            }

            try {
                News::create([
                    'feed_id' => $feed->id,
                    'title' => $normalized['title'],
                    'excerpt' => $normalized['excerpt'],
                    'published_at' => $normalized['published_at'],
                    'author' => $normalized['author'],
                    'link' => $normalized['link'],
                    'link_hash' => $linkHash,
                    'image_url' => $normalized['image_url'],
                    'guid' => $normalized['guid'],
                ]);
                $newCount++;
            } catch (\Throwable $e) {
                if (str_contains($e->getMessage(), 'Duplicate') || str_contains($e->getMessage(), 'unique')) {
                    continue;
                }
                Log::warning('RSS item save failed', ['link' => $normalized['link'], 'message' => $e->getMessage()]);
            }
        }

        if ($newCount > 0) {
            Log::info('RSS feed processed', ['feed_id' => $feed->id, 'feed_name' => $feed->name, 'new_items' => $newCount]);
        }

        return $newCount;
    }

    protected function existsByLinkHash(string $linkHash): bool
    {
        return News::where('link_hash', $linkHash)->exists();
    }
}
