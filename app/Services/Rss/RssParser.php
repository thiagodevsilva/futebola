<?php

namespace App\Services\Rss;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class RssParser
{
    public function __construct(
        protected int $timeout = 15,
        protected int $retryTimes = 2,
        protected string $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    ) {
    }

    /**
     * Baixa o conteúdo do feed e retorna XML string ou null em caso de falha.
     */
    public function fetch(string $url): ?string
    {
        try {
            $response = Http::timeout($this->timeout)
                ->retry($this->retryTimes, 1000)
                ->withHeaders(['User-Agent' => $this->userAgent])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('RSS fetch failed', ['url' => $url, 'status' => $response->status()]);
                return null;
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::warning('RSS fetch exception', ['url' => $url, 'message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Parseia XML (RSS 2.0 ou Atom) e retorna array de itens brutos.
     *
     * @return array<int, array{title?: string, link?: string, pubDate?: string, published?: string, updated?: string, author?: string, description?: string, content?: string, encoded?: string, guid?: string, enclosure?: array}>
     */
    public function parse(string $xml): array
    {
        $internalErrors = libxml_use_internal_errors(true);
        try {
            $doc = new \DOMDocument();
            if (!@$doc->loadXML($xml)) {
                Log::warning('RSS parse failed: invalid XML');
                return [];
            }

            $xpath = new \DOMXPath($doc);
            $xpath->registerNamespace('atom', 'http://www.w3.org/2005/Atom');
            $xpath->registerNamespace('content', 'http://purl.org/rss/1.0/modules/content/');
            $xpath->registerNamespace('media', 'http://search.yahoo.com/mrss/');
            $xpath->registerNamespace('dc', 'http://purl.org/dc/elements/1.1/');

            // Atom
            $atomItems = $xpath->query('//atom:entry');
            if ($atomItems->length > 0) {
                return $this->parseAtomEntries($xpath, $atomItems);
            }

            // RSS 2.0
            $rssItems = $xpath->query('//item');
            if ($rssItems->length > 0) {
                return $this->parseRssItems($xpath, $rssItems);
            }

            return [];
        } finally {
            libxml_use_internal_errors($internalErrors);
        }
    }

    /**
     * @param \DOMNodeList<\DOMNode> $entries
     * @return array<int, array>
     */
    private function parseAtomEntries(\DOMXPath $xpath, \DOMNodeList $entries): array
    {
        $items = [];
        foreach ($entries as $entry) {
            $item = [];
            $item['title'] = $this->textContent($xpath->query('.//atom:title', $entry));
            $item['link'] = $this->atomLink($xpath, $entry);
            $item['published'] = $this->textContent($xpath->query('.//atom:published', $entry));
            $item['updated'] = $this->textContent($xpath->query('.//atom:updated', $entry));
            $item['author'] = $this->textContent($xpath->query('.//atom:author/atom:name', $entry));
            $item['content'] = $this->textContent($xpath->query('.//atom:content', $entry));
            $item['summary'] = $this->textContent($xpath->query('.//atom:summary', $entry));
            $item['id'] = $this->textContent($xpath->query('.//atom:id', $entry));
            $item['image_url'] = $this->mediaThumbnail($xpath, $entry);
            $items[] = $item;
        }
        return $items;
    }

    private function atomLink(\DOMXPath $xpath, \DOMNode $entry): string
    {
        $links = $xpath->query('.//atom:link[@href]', $entry);
        foreach ($links as $link) {
            $rel = $link->getAttribute('rel') ?: 'alternate';
            if ($rel === 'alternate') {
                return trim($link->getAttribute('href') ?? '');
            }
        }
        if ($links->length > 0) {
            return trim($links->item(0)->getAttribute('href') ?? '');
        }
        return '';
    }

    private function mediaThumbnail(\DOMXPath $xpath, \DOMNode $entry): string
    {
        $thumb = $xpath->query('.//media:thumbnail[@url]', $entry)->item(0);
        if ($thumb) {
            return trim($thumb->getAttribute('url') ?? '');
        }
        $content = $xpath->query('.//media:content[@url]', $entry)->item(0);
        if ($content) {
            return trim($content->getAttribute('url') ?? '');
        }
        return '';
    }

    /**
     * @param \DOMNodeList<\DOMNode> $nodes
     */
    private function textContent(\DOMNodeList $nodes): string
    {
        if ($nodes->length === 0) {
            return '';
        }
        $node = $nodes->item(0);
        return $node ? trim($node->textContent ?? '') : '';
    }

    /**
     * @param \DOMNodeList<\DOMNode> $items
     * @return array<int, array>
     */
    private function parseRssItems(\DOMXPath $xpath, \DOMNodeList $items): array
    {
        $result = [];
        foreach ($items as $item) {
            $row = [];
            $row['title'] = $this->textContent($xpath->query('./title', $item));
            $row['link'] = $this->textContent($xpath->query('./link', $item));
            $row['pubDate'] = $this->textContent($xpath->query('./pubDate', $item));
            if (empty($row['pubDate'])) {
                $row['pubDate'] = $this->textContent($xpath->query('./dc:date', $item));
            }
            $row['author'] = $this->textContent($xpath->query('./author', $item));
            if (empty($row['author'])) {
                $row['author'] = $this->textContent($xpath->query('./dc:creator', $item));
            }
            $row['description'] = $this->textContent($xpath->query('./description', $item));
            $row['encoded'] = $this->textContent($xpath->query('./content:encoded', $item));
            $row['guid'] = $this->textContent($xpath->query('./guid', $item));
            $row['enclosure'] = $this->enclosure($xpath->query('./enclosure[@url]', $item));
            $row['image_url'] = $this->mediaThumbnailRss($xpath, $item);
            $result[] = $row;
        }
        return $result;
    }

    private function enclosure(\DOMNodeList $nodes): array
    {
        if ($nodes->length === 0) {
            return [];
        }
        $e = $nodes->item(0);
        if (!$e) {
            return [];
        }
        $type = $e->getAttribute('type') ?? '';
        if (stripos($type, 'image') !== false) {
            return ['url' => trim($e->getAttribute('url') ?? '')];
        }
        return [];
    }

    private function mediaThumbnailRss(\DOMXPath $xpath, \DOMNode $item): string
    {
        $thumb = $xpath->query('./media:thumbnail[@url]', $item)->item(0);
        if ($thumb) {
            return trim($thumb->getAttribute('url') ?? '');
        }
        $content = $xpath->query('./media:content[@url]', $item)->item(0);
        if ($content) {
            return trim($content->getAttribute('url') ?? '');
        }
        return '';
    }
}
