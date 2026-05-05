<?php

namespace App\Console\Commands;

use App\Models\News;
use Illuminate\Console\Command;

class ClearNewsCommand extends Command
{
    protected $signature = 'futebola:clear-news';
    protected $description = 'Remove todas as notícias do banco. Use antes de puxar de novo com futebola:fetch-rss.';

    public function handle(): int
    {
        $count = News::query()->count();
        News::query()->delete();
        $this->info("Removidas {$count} notícias.");
        return self::SUCCESS;
    }
}
