<?php

namespace App\Services\Rss;

use Carbon\Carbon;

class RssNormalizer
{
    /**
     * Normaliza um item bruto (RSS ou Atom) para o formato interno.
     *
     * @param array<string, mixed> $raw
     * @return array{title: string, link: string, published_at: \Carbon\Carbon, author: string|null, excerpt: string|null, image_url: string|null, guid: string|null}
     */
    public function normalize(array $raw, string $sourceName = ''): array
    {
        $title = $this->extractTitle($raw);
        $link = $this->extractLink($raw);
        $publishedAt = $this->extractPublishedAt($raw);
        $author = $this->extractAuthor($raw);
        $excerpt = $this->extractExcerpt($raw);
        $imageUrl = $this->extractImageUrl($raw);
        $guid = $this->extractGuid($raw, $link);

        return [
            'title' => $title,
            'link' => $link,
            'published_at' => $publishedAt,
            'author' => $author ?: null,
            'excerpt' => $excerpt ?: null,
            'image_url' => $imageUrl ?: null,
            'guid' => $guid ?: null,
        ];
    }

    private function extractTitle(array $raw): string
    {
        $title = $raw['title'] ?? '';
        $title = is_string($title) ? trim($title) : '';
        return $title !== '' ? $title : 'Sem título';
    }

    private function extractLink(array $raw): string
    {
        $link = $raw['link'] ?? $raw['id'] ?? '';
        return is_string($link) ? trim($link) : '';
    }

    private function extractPublishedAt(array $raw): Carbon
    {
        $dateStr = $raw['pubDate'] ?? $raw['published'] ?? $raw['updated'] ?? null;
        if ($dateStr && is_string($dateStr)) {
            try {
                $parsed = Carbon::parse($dateStr);
                $parsed->setTimezone(config('app.timezone', 'America/Sao_Paulo'));
                return $parsed;
            } catch (\Throwable) {
                // fallback
            }
        }
        return now();
    }

    private function extractAuthor(array $raw): ?string
    {
        $author = $raw['author'] ?? $raw['dc:creator'] ?? null;
        if ($author && is_string($author)) {
            $author = trim($author);
            return $author !== '' ? $author : null;
        }
        return null;
    }

    /**
     * Extrai resumo: description/summary ou trecho curto de content (sem copiar artigo inteiro).
     */
    private function extractExcerpt(array $raw): ?string
    {
        $description = $raw['description'] ?? $raw['summary'] ?? null;
        if ($description && is_string($description)) {
            $text = trim(strip_tags($description));
            return $this->truncateExcerpt($text, 500);
        }
        $content = $raw['content'] ?? $raw['encoded'] ?? null;
        if ($content && is_string($content)) {
            $text = trim(strip_tags($content));
            return $this->truncateExcerpt($text, 300);
        }
        return null;
    }

    private function truncateExcerpt(string $text, int $maxLength): string
    {
        $text = preg_replace('/\s+/', ' ', $text);
        if (mb_strlen($text) <= $maxLength) {
            return $text;
        }
        $cut = mb_substr($text, 0, $maxLength);
        $lastSpace = mb_strrpos($cut, ' ');
        if ($lastSpace !== false) {
            $cut = mb_substr($cut, 0, $lastSpace);
        }
        return rtrim($cut, '.,;:') . '…';
    }

    private function extractImageUrl(array $raw): ?string
    {
        $url = $raw['image_url'] ?? null;
        if ($url && is_string($url) && filter_var($url, FILTER_VALIDATE_URL)) {
            return $url;
        }
        $enclosure = $raw['enclosure'] ?? [];
        if (is_array($enclosure) && !empty($enclosure['url'])) {
            $url = $enclosure['url'];
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
        }
        // Muitos feeds colocam a imagem dentro do HTML da descrição ou content
        $html = $raw['description'] ?? $raw['encoded'] ?? $raw['content'] ?? '';
        if ($html && is_string($html) && preg_match('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $html, $m)) {
            $url = trim($m[1]);
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                return $url;
            }
            if (preg_match('#^//#', $url)) {
                return 'https:' . $url;
            }
        }
        return null;
    }

    private function extractGuid(array $raw, string $fallbackLink): ?string
    {
        $guid = $raw['guid'] ?? $raw['id'] ?? $fallbackLink;
        if ($guid && is_string($guid)) {
            $guid = trim($guid);
            return $guid !== '' ? $guid : null;
        }
        return null;
    }
}
