<?php

namespace App\Console\Commands;

use App\Models\League;
use App\Services\Football\FootballDataOrgDataService;
use App\Services\Football\FootballDataService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SyncFootballDataCommand extends Command
{
    protected $signature = 'futebola:sync-football
                            {--standings-only : Apenas tabelas de classificação}
                            {--fixtures-only : Apenas próximos jogos}
                            {--league= : ID interno da liga (opcional, senão todas ativas)}';

    protected $description = 'Sincroniza tabela e jogos. Recomendado: FOOTBALL_DATA_ORG_TOKEN (grátis para sempre). Alternativas: API_FOOTBALL_KEY ou API Futebol.';

    public function handle(FootballDataOrgDataService $fdOrgService, FootballDataService $apiFootballService): int
    {
        $leagueId = $this->option('league');
        $season = (int) Carbon::now()->year;
        $from = Carbon::now()->toDateString();
        $to = Carbon::now()->addDays(14)->toDateString();
        $doStandings = !$this->option('fixtures-only');
        $doFixtures = !$this->option('standings-only');

        $useFootballDataOrg = config('football-data-org.token') !== '';

        if ($useFootballDataOrg) {
            $leagues = $leagueId
                ? League::active()->whereNotNull('football_data_org_code')->where('id', $leagueId)->get()
                : League::active()->whereNotNull('football_data_org_code')->get();

            if ($leagues->isEmpty()) {
                $this->warn('Nenhuma liga ativa com football_data_org_code. Rode: php artisan db:seed --class=LeagueSeeder');
                return self::FAILURE;
            }

            foreach ($leagues as $league) {
                $code = $league->football_data_org_code;
                $this->info("Liga: {$league->name} (football-data.org: {$code})");

                if ($doStandings) {
                    $count = $fdOrgService->updateStandings($code, $season);
                    $this->line($count > 0 ? "  → Tabela: {$count} times." : '  → Tabela: sem dados.');
                }
                if ($doFixtures) {
                    $count = $fdOrgService->updateFixtures($code, $season, $from, $to);
                    $this->line($count > 0 ? "  → Próximos jogos: {$count}." : '  → Próximos jogos: nenhum na janela.');
                    $fromPast = Carbon::now()->subDays(7)->toDateString();
                    $toPast = Carbon::now()->subDay()->toDateString();
                    $countPast = $fdOrgService->updateFixtures($code, $season, $fromPast, $toPast);
                    if ($countPast > 0) {
                        $this->line("  → Últimos resultados (7 dias): {$countPast}.");
                    }
                }
            }

            $this->info('Sincronização concluída (football-data.org).');
            return self::SUCCESS;
        }

        if (config('api-football.key') === '') {
            $this->error('Configure FOOTBALL_DATA_ORG_TOKEN (grátis em https://www.football-data.org/) ou API_FOOTBALL_KEY no .env.');
            return self::FAILURE;
        }

        $leagues = $leagueId
            ? League::active()->where('id', $leagueId)->get()
            : League::active()->get();

        if ($leagues->isEmpty()) {
            $this->warn('Nenhuma liga ativa no banco. Rode: php artisan db:seed --class=LeagueSeeder');
            return self::FAILURE;
        }

        $season = config('api-football.default_season', 2024);

        foreach ($leagues as $league) {
            $this->info("Liga: {$league->name} (external_id: {$league->external_id})");

            if ($doStandings) {
                $count = $apiFootballService->updateStandings($league->external_id, $season);
                $this->line($count > 0 ? "  → Tabela: {$count} times." : '  → Tabela: sem dados.');
            }
            if ($doFixtures) {
                $count = $apiFootballService->updateFixtures($league->external_id, $from, $to);
                $this->line($count > 0 ? "  → Jogos: {$count}." : '  → Jogos: nenhum na janela.');
            }
        }

        $this->info('Sincronização concluída (API-Football).');
        return self::SUCCESS;
    }
}
