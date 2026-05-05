<?php

namespace App\Services\Football;

use App\Models\Fixture;
use App\Models\League;
use App\Models\Standing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FootballDataService
{
    public function __construct(
        protected ApiFootballClient $client
    ) {
    }

    /**
     * Atualiza standings no banco para uma liga/temporada.
     * Remove dados antigos e insere novo snapshot.
     */
    public function updateStandings(int $leagueId, int $season): int
    {
        $league = League::where('external_id', $leagueId)->first();
        if (!$league) {
            Log::debug("League external_id {$leagueId} not found in DB");
            return 0;
        }

        $response = $this->client->getStandings($leagueId, $season);
        if (empty($response)) {
            return 0;
        }

        $count = 0;
        DB::transaction(function () use ($league, $season, $response, &$count) {
            Standing::where('league_id', $league->id)->where('season', $season)->delete();

            foreach ($response as $standingGroup) {
                $standings = $standingGroup['league']['standings'][0] ?? $standingGroup['league']['standings'] ?? [];
                if (!is_array($standings)) {
                    continue;
                }
                foreach ($standings as $idx => $row) {
                    $rank = (int) ($row['rank'] ?? $idx + 1);
                    $team = $row['team'] ?? [];
                    $all = $row['all'] ?? [];
                    Standing::create([
                        'league_id' => $league->id,
                        'season' => $season,
                        'rank' => $rank,
                        'team_id' => $team['id'] ?? null,
                        'team_name' => $team['name'] ?? 'Desconhecido',
                        'team_logo' => $team['logo'] ?? null,
                        'points' => (int) ($row['points'] ?? 0),
                        'played' => (int) ($all['played'] ?? 0),
                        'win' => (int) ($all['win'] ?? 0),
                        'draw' => (int) ($all['draw'] ?? 0),
                        'loss' => (int) ($all['loss'] ?? 0),
                        'goals_for' => (int) ($row['goalsFor'] ?? $row['goals_for'] ?? 0),
                        'goals_against' => (int) ($row['goalsAgainst'] ?? $row['goals_against'] ?? 0),
                        'goal_diff' => (int) ($row['goalsDiff'] ?? $row['goal_diff'] ?? 0),
                        'form' => $row['form'] ?? null,
                    ]);
                    $count++;
                }
            }
        });

        return $count;
    }

    /**
     * Atualiza fixtures no banco para uma liga, entre from e to.
     */
    public function updateFixtures(int $leagueId, string $from, string $to): int
    {
        $league = League::where('external_id', $leagueId)->first();
        if (!$league) {
            return 0;
        }

        $response = $this->client->getFixtures($leagueId, $from, $to);
        if (empty($response)) {
            return 0;
        }

        $count = 0;
        $season = (int) Carbon::parse($from)->format('Y');

        foreach ($response as $item) {
            $fixture = $item['fixture'] ?? [];
            $leagueData = $item['league'] ?? [];
            $teams = $item['teams'] ?? [];
            $home = $teams['home'] ?? [];
            $away = $teams['away'] ?? [];
            $goals = $item['goals'] ?? [];

            $externalId = (int) ($fixture['id'] ?? 0);
            if ($externalId === 0) {
                continue;
            }

            $date = isset($fixture['date']) ? Carbon::parse($fixture['date']) : null;
            if (!$date) {
                continue;
            }

            try {
                Fixture::updateOrCreate(
                    ['external_fixture_id' => $externalId],
                    [
                        'league_id' => $league->id,
                        'season' => $season,
                        'date' => $date,
                        'home_team_id' => $home['id'] ?? null,
                        'home_team_name' => $home['name'] ?? 'TBD',
                        'away_team_id' => $away['id'] ?? null,
                        'away_team_name' => $away['name'] ?? 'TBD',
                        'home_goals' => $goals['home'] ?? null,
                        'away_goals' => $goals['away'] ?? null,
                        'status' => $fixture['status']['short'] ?? null,
                        'venue' => $fixture['venue']['name'] ?? null,
                    ]
                );
                $count++;
            } catch (\Throwable $e) {
                Log::warning('Fixture save failed', ['external_id' => $externalId, 'message' => $e->getMessage()]);
            }
        }

        return $count;
    }
}
