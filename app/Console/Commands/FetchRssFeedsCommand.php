<?php

namespace App\Console\Commands;

use App\Models\Feed;
use App\Services\Rss\RssAggregatorService;
use Illuminate\Console\Command;

class FetchRssFeedsCommand extends Command
{
    protected $signature = 'futebola:fetch-rss
                            {--feed= : ID do feed (opcional; senão processa todos ativos)}';
    protected $description = 'Busca e armazena notícias dos feeds RSS (execução imediata, sem fila). Rode uma vez para popular; depois use o scheduler + queue:work.';

    public function handle(RssAggregatorService $aggregator): int
    {
        $feedId = $this->option('feed');
        $feeds = $feedId
            ? Feed::active()->where('id', $feedId)->get()
            : Feed::active()->ordered()->get();

        if ($feeds->isEmpty()) {
            $this->warn('Nenhum feed ativo. Rode: php artisan db:seed --force && php artisan futebola:sync-feeds-from-standings');
            return self::FAILURE;
        }

        $total = 0;
        foreach ($feeds as $feed) {
            try {
                $count = $aggregator->processFeed($feed);
                $total += $count;
                $this->line("  {$feed->name}: {$count} novas.");
            } catch (\Throwable $e) {
                $this->error("  {$feed->name}: falha — {$e->getMessage()}");
            }
        }

        $this->info("Concluído. Total de novas notícias: {$total}.");
        return self::SUCCESS;
    }
}
