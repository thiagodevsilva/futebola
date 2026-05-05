<?php

namespace App\Console\Commands;

use App\Models\News;
use App\Services\OgImageService;
use Illuminate\Console\Command;

class BackfillNewsImagesCommand extends Command
{
    protected $signature = 'futebola:backfill-news-images
                            {--limit=100 : Máximo de notícias sem imagem a processar (evita muitas requisições)}';
    protected $description = 'Preenche image_url nas notícias que ainda não têm: primeiro tenta o RSS; se vazio, busca og:image na URL da notícia.';

    public function handle(OgImageService $ogImage): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        $news = News::where(function ($q) {
            $q->whereNull('image_url')->orWhere('image_url', '');
        })->whereNotNull('link')->where('link', '!=', '')->orderByDesc('published_at')->limit($limit)->get();

        if ($news->isEmpty()) {
            $this->info('Nenhuma notícia sem imagem para preencher.');
            return self::SUCCESS;
        }

        $this->info('Processando ' . $news->count() . ' notícias sem imagem (og:image na página)...');
        $updated = 0;
        foreach ($news as $item) {
            $url = $ogImage->fetchImageUrl($item->link);
            if ($url) {
                $item->update(['image_url' => $url]);
                $updated++;
            }
            usleep(800000); // ~0,8 s entre requisições para evitar rate limit (ex.: Gazeta)
        }

        $this->info("Atualizadas {$updated} notícias com imagem (og:image).");
        return self::SUCCESS;
    }
}
