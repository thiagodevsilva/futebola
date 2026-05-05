<?php

namespace App\Console\Commands;

use App\Models\Feed;
use App\Models\News;
use Illuminate\Console\Command;

class ValidateNewsCommand extends Command
{
    protected $signature = 'futebola:validate-news
                            {--topic= : Mostrar só este topic (ex.: sao-paulo)}';
    protected $description = 'Valida fora do código: conta notícias por time/topic, amostra de títulos e URLs dos feeds. Use para conferir se os dados batem com o esperado.';

    public function handle(): int
    {
        $topicFilter = $this->option('topic');

        $this->line('=== Feeds ativos (com topic) ===');
        $feeds = Feed::where('active', true)->whereNotNull('topic')->where('topic', '!=', '')->orderBy('topic')->orderBy('name')->get();
        foreach ($feeds as $f) {
            $this->line("  [{$f->topic}] {$f->name}");
            $this->line("      URL: {$f->url}");
        }

        $this->newLine();
        $this->line('=== Notícias por topic ===');
        $topics = $topicFilter ? [$topicFilter] : Feed::where('active', true)->whereNotNull('topic')->where('topic', '!=', '')->distinct()->pluck('topic');
        foreach ($topics as $topic) {
            $query = News::whereHas('feed', fn ($q) => $q->where('topic', $topic));
            $total = $query->count();
            $sample = $query->latest('published_at')->take(3)->get(['id', 'title', 'published_at']);
            $this->line("  <info>{$topic}</info>: {$total} notícias");
            foreach ($sample as $n) {
                $this->line('    - ' . mb_substr($n->title, 0, 70) . (mb_strlen($n->title) > 70 ? '…' : ''));
            }
        }

        $this->newLine();
        $totalNews = News::count();
        $withTopic = News::whereHas('feed', fn ($q) => $q->whereNotNull('topic')->where('topic', '!=', ''))->count();
        $this->line("Total de notícias: {$totalNews} (com topic: {$withTopic})");

        $this->newLine();
        $this->line('Para testar a API no navegador ou curl:');
        $this->line('  GET /api/news?topic=sao-paulo   → deve listar notícias do São Paulo');
        $this->line('  GET /api/news                   → lista todas (paginação)');
        $this->line('  GET /api/teams/serie-a          → lista times para o filtro (escudos)');

        return self::SUCCESS;
    }
}
