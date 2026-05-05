<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Extrai a URL da imagem Open Graph (og:image) de uma página.
 * Fallback quando o RSS não traz media:thumbnail / enclosure / img no conteúdo.
 */
class OgImageService
{
    public function __construct(
        protected int $timeout = 15,
        protected int $maxBytes = 512 * 1024, // 512 KB
        protected string $userAgent = 'Futebola/1.0 (RSS Aggregator; +https://futebola.com)'
    ) {
    }

    public function fetchImageUrl(string $pageUrl): ?string
    {
        if (!filter_var($pageUrl, FILTER_VALIDATE_URL)) {
            return null;
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders(['User-Agent' => $this->userAgent])
                ->get($pageUrl);

            if (!$response->successful()) {
                return null;
            }

            $html = $response->body();
            if ($html === '') {
                return null;
            }

            $url = $this->extractOgImage($html);
            if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
            if ($url && preg_match('#^//#', $url)) {
                return 'https:' . $url;
            }
            return null;
        } catch (\Throwable $e) {
            Log::debug('OgImage fetch failed', ['url' => $pageUrl, 'message' => $e->getMessage()]);
            return null;
        }
    }

    private function extractOgImage(string $html): ?string
    {
        if (preg_match('/<meta[^>]+property\s*=\s*["\']og:image["\'][^>]+content\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            return trim($m[1]);
        }
        if (preg_match('/<meta[^>]+content\s*=\s*["\']([^"\']+)["\'][^>]+property\s*=\s*["\']og:image["\']/i', $html, $m)) {
            return trim($m[1]);
        }
        return null;
    }
}
