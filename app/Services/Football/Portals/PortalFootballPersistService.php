<?php

namespace App\Services\Football\Portals;

use App\Models\Fixture;
use App\Models\League;
use App\Models\Standing;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PortalFootballPersistService
{
    /**
     * ID estável para fixtures vindas de portal (evita colisão com IDs numéricos de APIs).
     */
    public function fixtureExternalId(string $providerKey, string $uniqueToken): int
    {
        $hex = substr(hash('sha256', $providerKey.'|'.$uniqueToken), 0, 15);
        $id = hexdec($hex);

        return $id === 0 ? 1 : $id;
    }

    public function replaceStandings(League $league, int $season, array $rows): int
    {
        $count = 0;
        DB::transaction(function () use ($league, $season, $rows, &$count) {
            Standing::where('league_id', $league->id)->where('season', $season)->delete();

            foreach ($rows as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $norm = $this->normalizeStandingRow($row, $index + 1);
                if ($norm === null) {
                    continue;
                }

                Standing::create([
                    'league_id' => $league->id,
                    'season' => $season,
                    'rank' => $norm['rank'],
                    'team_id' => null,
                    'team_name' => $norm['team_name'],
                    'team_logo' => $norm['team_logo'],
                    'points' => $norm['points'],
                    'played' => $norm['played'],
                    'win' => $norm['win'],
                    'draw' => $norm['draw'],
                    'loss' => $norm['loss'],
                    'goals_for' => $norm['goals_for'],
                    'goals_against' => $norm['goals_against'],
                    'goal_diff' => $norm['goal_diff'],
                    'form' => $norm['form'],
                ]);
                $count++;
            }
        });

        return $count;
    }

    public function upsertFixtures(League $league, int $season, string $providerKey, array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            $norm = $this->normalizeFixtureRow($row);
            if ($norm === null) {
                continue;
            }

            $uniqueToken = $norm['unique_token'];
            $externalId = $this->fixtureExternalId($providerKey, $uniqueToken);

            try {
                Fixture::updateOrCreate(
                    ['external_fixture_id' => $externalId],
                    [
                        'league_id' => $league->id,
                        'season' => $season,
                        'date' => $norm['date'],
                        'home_team_id' => null,
                        'home_team_name' => $norm['home_team_name'],
                        'home_team_logo' => $norm['home_team_logo'],
                        'away_team_id' => null,
                        'away_team_name' => $norm['away_team_name'],
                        'away_team_logo' => $norm['away_team_logo'],
                        'home_goals' => $norm['home_goals'],
                        'away_goals' => $norm['away_goals'],
                        'status' => $norm['status'],
                        'venue' => $norm['venue'],
                    ]
                );
                $count++;
            } catch (\Throwable $e) {
                Log::warning('Portal fixture upsert failed', [
                    'external_id' => $externalId,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    /**
     * Aceita chaves flexíveis (football-data, OneFootball, GE, etc.).
     *
     * @param  array<string, mixed>  $row
     * @param  int|null  $fallbackRank  Posição 1-based quando o JSON não traz rank (ordem na lista).
     * @return array<string, mixed>|null
     */
    public function normalizeStandingRow(array $row, ?int $fallbackRank = null): ?array
    {
        $name = $this->resolveStandingTeamName($row);
        if ($name === null || $name === '') {
            return null;
        }

        $rank = (int) ($row['rank'] ?? $row['position'] ?? $row['posicao'] ?? $row['posição']
            ?? $row['place'] ?? $row['standing'] ?? data_get($row, 'ranking.position')
            ?? data_get($row, 'tablePosition') ?? 0);

        if ($rank < 1 && isset($row['index']) && is_numeric($row['index'])) {
            $rank = (int) $row['index'] + 1;
        }
        if ($rank < 1 && isset($row['order']) && is_numeric($row['order'])) {
            $rank = (int) $row['order'];
        }
        if ($rank < 1 && $fallbackRank !== null && $fallbackRank >= 1) {
            $rank = $fallbackRank;
        }

        if ($rank < 1) {
            return null;
        }

        $logo = $row['team_logo'] ?? $row['logo'] ?? $row['escudo'] ?? $row['crest']
            ?? data_get($row, 'squads.logo') ?? data_get($row, 'team.crest')
            ?? data_get($row, 'team.image')
            ?? data_get($row, 'club.image') ?? data_get($row, 'club.logo')
            ?? data_get($row, 'club.emblem') ?? data_get($row, 'emblem')
            ?? data_get($row, 'imageObject.path');
        if (is_array($logo) && isset($logo['url']) && is_string($logo['url'])) {
            $logo = $logo['url'];
        }
        $logo = is_string($logo) ? $logo : null;

        $draw = (int) ($row['draw'] ?? $row['empates'] ?? $row['draws'] ?? $row['drawn'] ?? $row['drawnMatchesCount'] ?? $row['E'] ?? 0);

        return [
            'rank' => $rank,
            'team_name' => $name,
            'team_logo' => $logo,
            'points' => (int) ($row['points'] ?? $row['pontos'] ?? $row['pts'] ?? data_get($row, 'stats.points') ?? 0),
            'played' => (int) ($row['played'] ?? $row['jogos'] ?? $row['playedGames'] ?? $row['PJ']
                ?? $row['matches'] ?? $row['games'] ?? $row['playedMatchesCount']
                ?? data_get($row, 'stats.played') ?? 0),
            'win' => (int) ($row['win'] ?? $row['vitorias'] ?? $row['wins'] ?? $row['won'] ?? $row['V']
                ?? $row['wonMatchesCount'] ?? data_get($row, 'stats.win') ?? 0),
            'draw' => $draw,
            'loss' => (int) ($row['loss'] ?? $row['derrotas'] ?? $row['losses'] ?? $row['lost'] ?? $row['D']
                ?? $row['lostMatchesCount'] ?? data_get($row, 'stats.loss') ?? 0),
            'goals_for' => (int) ($row['goals_for'] ?? $row['goalsFor'] ?? $row['gf'] ?? $row['gp'] ?? $row['GP']
                ?? $row['goalsScored'] ?? $row['pro'] ?? $row['goalsForCount']
                ?? data_get($row, 'stats.goalsFor') ?? data_get($row, 'goals.for') ?? 0),
            'goals_against' => (int) ($row['goals_against'] ?? $row['goalsAgainst'] ?? $row['ga'] ?? $row['gc'] ?? $row['GC']
                ?? $row['goalsConceded'] ?? $row['contra'] ?? $row['goalsAgainstCount']
                ?? data_get($row, 'stats.goalsAgainst') ?? data_get($row, 'goals.against') ?? 0),
            'goal_diff' => (int) ($row['goal_diff'] ?? $row['goalDifference'] ?? $row['sg'] ?? $row['SG']
                ?? $row['goalDiff'] ?? $row['goalsDiff'] ?? data_get($row, 'stats.goalDifference') ?? 0),
            'form' => $this->resolveStandingForm($row),
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveStandingTeamName(array $row): ?string
    {
        $direct = $row['team_name'] ?? null;
        if (is_string($direct) && trim($direct) !== '') {
            return trim($direct);
        }

        foreach (['team', 'club', 'squad', 'participant', 'contestant'] as $key) {
            if (! isset($row[$key])) {
                continue;
            }
            $t = $row[$key];
            if (is_string($t) && trim($t) !== '') {
                return trim($t);
            }
            if (is_array($t)) {
                foreach (['name', 'title', 'shortName', 'short_name', 'fullName', 'displayName'] as $nk) {
                    if (isset($t[$nk]) && is_string($t[$nk]) && trim($t[$nk]) !== '') {
                        return trim($t[$nk]);
                    }
                }
            }
        }

        foreach (['nome', 'time', 'teamName', 'clubName'] as $k) {
            if (isset($row[$k]) && is_string($row[$k]) && trim($row[$k]) !== '') {
                return trim($row[$k]);
            }
        }

        $nested = data_get($row, 'squads.name')
            ?? data_get($row, 'team.name')
            ?? data_get($row, 'club.name')
            ?? data_get($row, 'club.title');

        return is_string($nested) && trim($nested) !== '' ? trim($nested) : null;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function resolveStandingForm(array $row): ?string
    {
        $form = $row['form'] ?? data_get($row, 'stats.form');
        if (is_string($form) && $form !== '') {
            return $form;
        }
        if (is_array($form)) {
            $s = implode('', array_map(fn ($c) => is_string($c) ? $c : '', $form));

            return $s !== '' ? $s : null;
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>|null
     */
    public function normalizeFixtureRow(array $row): ?array
    {
        $home = $row['home_team_name']
            ?? $row['home']
            ?? $row['mandante']
            ?? $row['time_casa']
            ?? $row['homeTeam']
            ?? null;
        $away = $row['away_team_name']
            ?? $row['away']
            ?? $row['visitante']
            ?? $row['time_visitante']
            ?? $row['awayTeam']
            ?? null;

        $home = is_string($home) ? trim($home) : null;
        $away = is_string($away) ? trim($away) : null;
        if ($home === '' || $away === '' || $home === null || $away === null) {
            return null;
        }

        $dateRaw = $row['date'] ?? $row['data'] ?? $row['utcDate'] ?? $row['start'] ?? null;
        $date = null;
        if ($dateRaw instanceof Carbon) {
            $date = $dateRaw;
        } elseif (is_string($dateRaw) && $dateRaw !== '') {
            try {
                $date = Carbon::parse($dateRaw);
            } catch (\Throwable) {
                return null;
            }
        }
        if ($date === null) {
            return null;
        }

        $homeGoals = $row['home_goals'] ?? $row['score_home'] ?? $row['placar_casa'] ?? null;
        $awayGoals = $row['away_goals'] ?? $row['score_away'] ?? $row['placar_visitante'] ?? null;

        $uniqueToken = $date->format('YmdHi').'|'.Str::slug($home).'|'.Str::slug($away);

        return [
            'unique_token' => $uniqueToken,
            'date' => $date,
            'home_team_name' => $home,
            'away_team_name' => $away,
            'home_team_logo' => isset($row['home_team_logo']) && is_string($row['home_team_logo']) ? $row['home_team_logo'] : null,
            'away_team_logo' => isset($row['away_team_logo']) && is_string($row['away_team_logo']) ? $row['away_team_logo'] : null,
            'home_goals' => is_numeric($homeGoals) ? (int) $homeGoals : null,
            'away_goals' => is_numeric($awayGoals) ? (int) $awayGoals : null,
            'status' => isset($row['status']) && is_string($row['status']) ? $row['status'] : null,
            'venue' => isset($row['venue']) && is_string($row['venue']) ? $row['venue'] : null,
        ];
    }
}
