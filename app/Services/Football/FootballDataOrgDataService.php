<?php

namespace App\Services\Football;

use App\Models\Fixture;
use App\Models\League;
use App\Models\Standing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Atualiza standings e fixtures a partir da API football-data.org (grátis permanente).
 * Liga por football_data_org_code (ex.: BSA para Brasileirão Série A).
 */
class FootballDataOrgDataService
{
    public function __construct(
        protected FootballDataOrgClient $client
    ) {
    }

    /**
     * Atualiza a tabela de classificação para a liga identificada por código e temporada.
     */
    public function updateStandings(string $competitionCode, int $season): int
    {
        $league = League::where('football_data_org_code', $competitionCode)->first();
        if (!$league) {
            Log::debug("League with football_data_org_code {$competitionCode} not found");
            return 0;
        }

        $table = $this->client->getStandings($competitionCode);
        if (empty($table)) {
            return 0;
        }

        $count = 0;
        DB::transaction(function () use ($league, $season, $table, &$count) {
            Standing::where('league_id', $league->id)->where('season', $season)->delete();

            foreach ($table as $row) {
                $team = $row['team'] ?? [];
                Standing::create([
                    'league_id' => $league->id,
                    'season' => $season,
                    'rank' => (int) ($row['position'] ?? 0),
                    'team_id' => isset($team['id']) ? (int) $team['id'] : null,
                    'team_name' => $team['name'] ?? 'Desconhecido',
                    'team_logo' => $team['crest'] ?? null,
                    'points' => (int) ($row['points'] ?? 0),
                    'played' => (int) ($row['playedGames'] ?? 0),
                    'win' => (int) ($row['won'] ?? 0),
                    'draw' => (int) ($row['draw'] ?? 0),
                    'loss' => (int) ($row['lost'] ?? 0),
                    'goals_for' => (int) ($row['goalsFor'] ?? 0),
                    'goals_against' => (int) ($row['goalsAgainst'] ?? 0),
                    'goal_diff' => (int) ($row['goalDifference'] ?? 0),
                    'form' => $row['form'] ?? null,
                ]);
                $count++;
            }
        });

        return $count;
    }

    /**
     * Atualiza jogos (fixtures) para a liga no intervalo de datas.
     */
    public function updateFixtures(string $competitionCode, int $season, string $from, string $to): int
    {
        $league = League::where('football_data_org_code', $competitionCode)->first();
        if (!$league) {
            Log::debug("League with football_data_org_code {$competitionCode} not found");
            return 0;
        }

        $matches = $this->client->getMatches($competitionCode, $from, $to);
        if (empty($matches)) {
            return 0;
        }

        $count = 0;
        foreach ($matches as $m) {
            $id = (int) ($m['id'] ?? 0);
            if ($id === 0) {
                continue;
            }

            $utcDate = $m['utcDate'] ?? null;
            $date = $utcDate ? Carbon::parse($utcDate) : null;
            if (!$date) {
                continue;
            }

            $homeTeam = $m['homeTeam'] ?? [];
            $awayTeam = $m['awayTeam'] ?? [];
            $score = $m['score'] ?? [];
            $fullTime = $score['fullTime'] ?? [];

            try {
                Fixture::updateOrCreate(
                    ['external_fixture_id' => $id],
                    [
                        'league_id' => $league->id,
                        'season' => $season,
                        'date' => $date,
                        'home_team_id' => isset($homeTeam['id']) ? (int) $homeTeam['id'] : null,
                        'home_team_name' => $homeTeam['name'] ?? 'TBD',
                        'home_team_logo' => $homeTeam['crest'] ?? null,
                        'away_team_id' => isset($awayTeam['id']) ? (int) $awayTeam['id'] : null,
                        'away_team_name' => $awayTeam['name'] ?? 'TBD',
                        'away_team_logo' => $awayTeam['crest'] ?? null,
                        'home_goals' => array_key_exists('home', $fullTime) ? (int) $fullTime['home'] : null,
                        'away_goals' => array_key_exists('away', $fullTime) ? (int) $fullTime['away'] : null,
                        'status' => $m['status'] ?? null,
                        'venue' => $m['venue'] ?? null,
                    ]
                );
                $count++;
            } catch (\Throwable $e) {
                Log::warning('FootballDataOrg fixture save failed', ['external_id' => $id, 'message' => $e->getMessage()]);
            }
        }

        return $count;
    }
}
