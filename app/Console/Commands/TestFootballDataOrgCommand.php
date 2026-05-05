<?php

namespace App\Console\Commands;

use App\Services\Football\FootballDataOrgClient;
use Illuminate\Console\Command;

class TestFootballDataOrgCommand extends Command
{
    protected $signature = 'futebola:test-football-data-org';
    protected $description = 'Testa a conexão com football-data.org (mostra status e resumo da resposta)';

    public function handle(FootballDataOrgClient $client): int
    {
        $token = config('football-data-org.token');
        $this->line('Token configurado: ' . ($token !== '' ? 'Sim (' . substr($token, 0, 4) . '...)' : 'Não'));
        $this->line('Se acabou de alterar o .env, rode: php artisan config:clear');
        $this->newLine();

        if ($token === '') {
            $this->error('Defina FOOTBALL_DATA_ORG_TOKEN no .env. Obtenha em: https://www.football-data.org/');
            return self::FAILURE;
        }

        $competitions = $client->getCompetitions();
        if (empty($competitions)) {
            $this->warn('Resposta vazia ou erro ao chamar GET /competitions. Verifique o token e os logs.');
            $this->line('Tentando GET /competitions/BSA/standings...');
            $standings = $client->getStandings('BSA');
            if (empty($standings)) {
                $this->error('Nenhum dado retornado.');
                return self::FAILURE;
            }
            $this->line('Standings BSA: ' . count($standings) . ' linhas.');
            $this->info('Token válido (standings BSA retornou dados).');
            return self::SUCCESS;
        }

        $this->line('Competições no plano free: ' . count($competitions));
        foreach (array_slice($competitions, 0, 5) as $c) {
            $this->line('  - ' . ($c['name'] ?? '?') . ' (' . ($c['code'] ?? $c['id'] ?? '') . ')');
        }
        if (count($competitions) > 5) {
            $this->line('  ... e mais ' . (count($competitions) - 5));
        }

        $standings = $client->getStandings('BSA');
        $this->newLine();
        $this->line('Standings Brasileirão Série A (BSA): ' . (empty($standings) ? 'vazio' : count($standings) . ' times'));

        $this->info('Conexão OK.');
        return self::SUCCESS;
    }
}
