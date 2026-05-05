<?php

namespace App\Services\Football;

use App\Models\Fixture;
use App\Models\League;
use App\Models\Standing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Preenche standings e fixtures a partir da API Futebol (api-futebol.com.br).
 * Resposta da API pode vir como array de objetos ou objeto com chaves; normalizamos para nosso schema.
 */
class ApiFutebolDataService
{
    public function __construct(
        protected ApiFutebolClient $client
    ) {
    }

    public function updateStandings(int $campeonatoId, int $season): int
    {
        $league = League::where('api_futebol_id', $campeonatoId)->first();
        if (!$league) {
            Log::debug("League api_futebol_id {$campeonatoId} not found");
            return 0;
        }

        $data = $this->client->getTabela($campeonatoId);
        $rows = $this->normalizeTabelaResponse($data);
        if (empty($rows)) {
            return 0;
        }

        $count = 0;
        DB::transaction(function () use ($league, $season, $rows, &$count) {
            Standing::where('league_id', $league->id)->where('season', $season)->delete();
            foreach ($rows as $idx => $row) {
                Standing::create([
                    'league_id' => $league->id,
                    'season' => $season,
                    'rank' => $row['rank'],
                    'team_id' => $row['team_id'] ?? null,
                    'team_name' => $row['team_name'],
                    'team_logo' => $row['team_logo'] ?? null,
                    'points' => $row['points'],
                    'played' => $row['played'],
                    'win' => $row['win'],
                    'draw' => $row['draw'],
                    'loss' => $row['loss'],
                    'goals_for' => $row['goals_for'],
                    'goals_against' => $row['goals_against'],
                    'goal_diff' => $row['goal_diff'],
                    'form' => $row['form'] ?? null,
                ]);
                $count++;
            }
        });

        return $count;
    }

    public function updateFixtures(int $campeonatoId, int $season): int
    {
        $league = League::where('api_futebol_id', $campeonatoId)->first();
        if (!$league) {
            return 0;
        }

        $data = $this->client->getPartidas($campeonatoId);
        $items = $this->normalizePartidasResponse($data);
        $count = 0;

        foreach ($items as $item) {
            $externalId = $item['external_id'];
            if ($externalId <= 0) {
                continue;
            }
            try {
                Fixture::updateOrCreate(
                    ['external_fixture_id' => $externalId],
                    [
                        'league_id' => $league->id,
                        'season' => $season,
                        'date' => $item['date'],
                        'home_team_name' => $item['home_team_name'],
                        'away_team_name' => $item['away_team_name'],
                        'home_goals' => $item['home_goals'],
                        'away_goals' => $item['away_goals'],
                        'status' => $item['status'] ?? null,
                        'venue' => $item['venue'] ?? null,
                    ]
                );
                $count++;
            } catch (\Throwable $e) {
                Log::warning('ApiFutebolDataService fixture failed', ['id' => $externalId, 'msg' => $e->getMessage()]);
            }
        }

        return $count;
    }

    /**
     * API pode retornar: array direto de posições, ou objeto com "tabela"/"posicoes"/etc.
     */
    private function normalizeTabelaResponse(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }
        $rows = $data;
        if (isset($data['tabela']) && is_array($data['tabela'])) {
            $rows = $data['tabela'];
        }
        if (isset($data['posicoes']) && is_array($data['posicoes'])) {
            $rows = $data['posicoes'];
        }
        $result = [];
        $list = array_is_list($rows) ? $rows : [$rows];
        foreach ($list as $idx => $row) {
            if (!is_array($row)) {
                continue;
            }
            $time = $row['time'] ?? $row['equipe'] ?? $row['team'] ?? [];
            $nome = is_array($time) ? ($time['nome'] ?? $time['name'] ?? '') : (string) $time;
            $result[] = [
                'rank' => (int) ($row['posicao'] ?? $row['rank'] ?? $idx + 1),
                'team_id' => $row['time_id'] ?? $row['equipe_id'] ?? $time['id'] ?? null,
                'team_name' => $nome ?: 'Desconhecido',
                'team_logo' => is_array($time) ? ($time['escudo'] ?? $time['logo'] ?? null) : null,
                'points' => (int) ($row['pontos'] ?? $row['points'] ?? 0),
                'played' => (int) ($row['jogos'] ?? $row['played'] ?? $row['partidas'] ?? 0),
                'win' => (int) ($row['vitorias'] ?? $row['win'] ?? 0),
                'draw' => (int) ($row['empates'] ?? $row['draw'] ?? 0),
                'loss' => (int) ($row['derrotas'] ?? $row['loss'] ?? 0),
                'goals_for' => (int) ($row['gols_pro'] ?? $row['gols_marcados'] ?? $row['goals_for'] ?? 0),
                'goals_against' => (int) ($row['gols_contra'] ?? $row['gols_sofridos'] ?? $row['goals_against'] ?? 0),
                'goal_diff' => (int) ($row['saldo_gols'] ?? $row['goal_diff'] ?? 0),
                'form' => $row['form'] ?? $row['ultimos_jogos'] ?? null,
            ];
        }
        return $result;
    }

    private function normalizePartidasResponse(mixed $data): array
    {
        if (!is_array($data)) {
            return [];
        }
        $rows = $data;
        if (isset($data['partidas']) && is_array($data['partidas'])) {
            $rows = $data['partidas'];
        }
        $list = array_is_list($rows) ? $rows : [$rows];
        $result = [];
        $now = Carbon::now();
        $cutoff = $now->copy()->addDays(14);

        foreach ($list as $row) {
            if (!is_array($row)) {
                continue;
            }
            $dateStr = $row['data'] ?? $row['date'] ?? $row['data_realizacao'] ?? null;
            $hora = $row['hora'] ?? $row['time'] ?? null;
            $date = $this->parseDate($dateStr, $hora);
            if (!$date || $date->lt($now) || $date->gt($cutoff)) {
                continue;
            }
            $mandante = $row['time_mandante'] ?? $row['home'] ?? [];
            $visitante = $row['time_visitante'] ?? $row['away'] ?? [];
            $result[] = [
                'external_id' => (int) ($row['partida_id'] ?? $row['id'] ?? $row['jogo_id'] ?? 0),
                'date' => $date,
                'home_team_name' => is_array($mandante) ? ($mandante['nome'] ?? $mandante['name'] ?? 'TBD') : (string) $mandante,
                'away_team_name' => is_array($visitante) ? ($visitante['nome'] ?? $visitante['name'] ?? 'TBD') : (string) $visitante,
                'home_goals' => isset($row['placar_mandante']) ? (int) $row['placar_mandante'] : (isset($row['gols_mandante']) ? (int) $row['gols_mandante'] : null),
                'away_goals' => isset($row['placar_visitante']) ? (int) $row['placar_visitante'] : (isset($row['gols_visitante']) ? (int) $row['gols_visitante'] : null),
                'status' => $row['status'] ?? $row['situacao'] ?? null,
                'venue' => $row['estadio'] ?? $row['venue'] ?? $row['local'] ?? null,
            ];
        }
        return $result;
    }

    private function parseDate(?string $dateStr, $hora = null): ?Carbon
    {
        if (!$dateStr) {
            return null;
        }
        try {
            $str = $dateStr;
            if ($hora !== null && $hora !== '') {
                $str .= ' ' . (is_string($hora) ? $hora : '00:00');
            }
            return Carbon::parse($str);
        } catch (\Throwable $e) {
            return null;
        }
    }
}
