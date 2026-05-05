<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class FeedSeeder extends Seeder
{
    /**
     * Feeds de notícias vêm da API (standings). Rode após o sync:
     *   php artisan futebola:sync-feeds-from-standings
     */
    public function run(): void
    {
        // Nenhum feed fixo; os feeds são criados por futebola:sync-feeds-from-standings
        // a partir dos times do Brasileirão Série A (tabela standings).
    }
}
