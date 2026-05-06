<?php

namespace App\Console\Commands;

use App\Services\Football\Portals\PortalFootballEngine;
use Illuminate\Console\Command;

class SyncPortalFootballCommand extends Command
{
    protected $signature = 'futebola:sync-portal-football
                            {--competition= : Apenas football_data_org_code (ex.: BSA)}
                            {--force : Ignora portal-football.enabled}';

    protected $description = 'Sincroniza tabela e jogos (portal-football). Drivers: json ou next_data_page (página Next.js / OneFootball). Veja config/portal-football.php.';

    public function handle(PortalFootballEngine $engine): int
    {
        if (! config('portal-football.enabled') && ! $this->option('force')) {
            $this->error('Portal football desabilitado. Defina PORTAL_FOOTBALL_ENABLED=true no .env ou use --force.');

            return self::FAILURE;
        }

        $only = $this->option('competition');
        $only = is_string($only) && $only !== '' ? $only : null;

        try {
            $results = $engine->syncAll($only);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($results === []) {
            $this->warn('Nenhuma fonte habilitada ou filtro não encontrou competição. Verifique portal-football.sources no config.');

            return self::SUCCESS;
        }

        foreach ($results as $r) {
            $this->info("{$r['league']}: tabela {$r['standings']} linhas, jogos {$r['fixtures']} gravados/atualizados.");
        }

        return self::SUCCESS;
    }
}
