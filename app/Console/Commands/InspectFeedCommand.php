<?php

namespace App\Console\Commands;

use App\Models\Feed;
use App\Services\Rss\RssNormalizer;
use App\Services\Rss\RssParser;
use Illuminate\Console\Command;

class InspectFeedCommand extends Command
{
    protected $signature = 'futebola:inspect-feed
                            {--feed= : ID ou nome do feed (ex.: 1 ou "Gazeta Esportiva"). Se vazio, usa o primeiro ativo.}
                            {--all : Inspeciona todos os feeds ativos (1 item por feed).}
                            {--items=1 : Quantos itens inspecionar (1 a 5)}';
    protected $description = 'Baixa um feed, mostra a estrutura do primeiro item (campos e trecho de description) e se o normalizer extrai imagem.';

    public function handle(RssParser $parser, RssNormalizer $normalizer): int
    {
        $feedInput = $this->option('feed');
        $all = $this->option('all');
        $limit = max(1, min(5, (int) $this->option('items')));

        if ($all) {
            $feeds = Feed::active()->ordered()->get();
            if ($feeds->isEmpty()) {
                $this->warn('Nenhum feed ativo.');
                return self::SUCCESS;
            }
            foreach ($feeds as $feed) {
                $this->inspectOne($parser, $normalizer, $feed, 1);
            }
            return self::SUCCESS;
        }

        $feed = $this->resolveFeed($feedInput);
        if (!$feed) {
            $this->error('Feed não encontrado. Use --feed=ID ou --feed="Nome do feed", ou --all para todos.');
            return self::FAILURE;
        }

        $this->inspectOne($parser, $normalizer, $feed, $limit);
        return self::SUCCESS;
    }

    private function inspectOne(RssParser $parser, RssNormalizer $normalizer, Feed $feed, int $limit): void
    {
        $this->info("Feed: {$feed->name} (ID {$feed->id})");
        $this->line("URL: {$feed->url}");

        $xml = $parser->fetch($feed->url);
        if ($xml === null) {
            $this->error('  Falha ao baixar.');
            $this->newLine();
            return;
        }

        $rawItems = $parser->parse($xml);
        $this->line('Itens parseados: ' . count($rawItems));
        if (empty($rawItems)) {
            $this->warn('  Nenhum item no feed.');
            $this->newLine();
            return;
        }

        $items = array_slice($rawItems, 0, $limit);
        foreach ($items as $index => $raw) {
            $this->line('--- Item ' . ($index + 1) . ' ---');
            $this->line('Chaves: ' . implode(', ', array_keys($raw)));

            $title = $raw['title'] ?? $raw['link'] ?? '(sem título)';
            $this->line('Título: ' . mb_substr($title, 0, 80) . (mb_strlen($title) > 80 ? '…' : ''));

            foreach (['description', 'encoded', 'content', 'summary'] as $key) {
                if (empty($raw[$key]) || !is_string($raw[$key])) {
                    continue;
                }
                $len = mb_strlen($raw[$key]);
                $hasImg = preg_match('/<img[^>]+src\s*=\s*["\']([^"\']+)["\']/i', $raw[$key], $m);
                $this->line("  [{$key}] {$len} chars" . ($hasImg ? ' → <img>: ' . mb_substr($m[1], 0, 60) . '…' : ' → sem <img>'));
            }

            if (!empty($raw['enclosure']) && is_array($raw['enclosure'])) {
                $this->line('  [enclosure] ' . json_encode($raw['enclosure']));
            }
            if (!empty($raw['image_url'])) {
                $this->line('  [parser image_url] ' . $raw['image_url']);
            }

            $normalized = $normalizer->normalize($raw, $feed->name);
            $this->line('Normalizer image_url: ' . ($normalized['image_url'] ?: '(vazio)'));
        }

        $this->newLine();
    }

    private function resolveFeed(?string $input): ?Feed
    {
        if ($input === null || $input === '') {
            return Feed::active()->ordered()->first();
        }
        if (is_numeric($input)) {
            return Feed::active()->find((int) $input);
        }
        return Feed::active()->where('name', 'like', '%' . $input . '%')->first();
    }
}
