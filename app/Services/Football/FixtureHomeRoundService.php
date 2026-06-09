<?php

namespace App\Services\Football;

use App\Models\Fixture;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Agrupa a home em rodadas: última rodada com resultados e próxima rodada de jogos.
 */
class FixtureHomeRoundService
{
    /**
     * @return array{
     *   last_round: array{number: int|null, label: string, fixtures: Collection<int, Fixture>},
     *   next_round: array{number: int|null, label: string, fixtures: Collection<int, Fixture>},
     *   used_round_metadata: bool,
     * }
     */
    public function resolveHomeRounds(int $leagueId): array
    {
        $all = Fixture::query()->where('league_id', $leagueId)->get();

        $withRound = $all->filter(fn (Fixture $f) => $f->match_round !== null);

        if ($withRound->isEmpty()) {
            return $this->fallbackByDates($leagueId);
        }

        $resultsRoundNum = $withRound
            ->filter(fn (Fixture $f) => $f->home_goals !== null && $f->away_goals !== null)
            ->max('match_round');

        if ($resultsRoundNum === null) {
            return $this->fallbackByDates($leagueId);
        }

        $resultsRoundNum = (int) $resultsRoundNum;

        $lastFixtures = $withRound
            ->filter(fn (Fixture $f) => (int) $f->match_round === $resultsRoundNum
                && $f->home_goals !== null && $f->away_goals !== null)
            ->sortBy('date')
            ->values();

        $nextRoundNum = $withRound
            ->filter(fn (Fixture $f) => (int) $f->match_round > $resultsRoundNum)
            ->min('match_round');

        $nextRoundNum = $nextRoundNum !== null ? (int) $nextRoundNum : null;

        $nextFixtures = collect();
        if ($nextRoundNum !== null) {
            $nextFixtures = $withRound
                ->filter(fn (Fixture $f) => (int) $f->match_round === $nextRoundNum)
                ->sortBy('date')
                ->values();
        }

        return [
            'last_round' => [
                'number' => $resultsRoundNum,
                'label' => 'Rodada '.$resultsRoundNum,
                'fixtures' => $lastFixtures,
            ],
            'next_round' => [
                'number' => $nextRoundNum,
                'label' => $nextRoundNum !== null ? 'Rodada '.$nextRoundNum : 'Próximos jogos',
                'fixtures' => $nextFixtures,
            ],
            'used_round_metadata' => true,
        ];
    }

    /**
     * @return array{
     *   last_round: array{number: null, label: string, fixtures: Collection<int, Fixture>},
     *   next_round: array{number: null, label: string, fixtures: Collection<int, Fixture>},
     *   used_round_metadata: bool,
     * }
     */
    /**
     * Últimos N resultados e próximos N jogos por data (sem agrupar por rodada).
     *
     * @return array{
     *   last_round: array{number: null, label: string, fixtures: Collection<int, Fixture>},
     *   next_round: array{number: null, label: string, fixtures: Collection<int, Fixture>},
     *   used_round_metadata: bool,
     * }
     */
    public function resolveHomeRecent(int $leagueId, int $limit = 3): array
    {
        $limit = max(1, min($limit, 20));
        $today = Carbon::now()->toDateString();

        $lastFixtures = Fixture::query()
            ->where('league_id', $leagueId)
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->orderByDesc('date')
            ->limit($limit)
            ->get();

        $nextFixtures = Fixture::query()
            ->where('league_id', $leagueId)
            ->where('date', '>=', $today.' 00:00:00')
            ->orderBy('date')
            ->limit($limit)
            ->get();

        $labelSuffix = $limit === 1 ? 'jogo' : 'jogos';

        return [
            'last_round' => [
                'number' => null,
                'label' => "Últimos {$limit} {$labelSuffix}",
                'fixtures' => $lastFixtures,
            ],
            'next_round' => [
                'number' => null,
                'label' => "Próximos {$limit} {$labelSuffix}",
                'fixtures' => $nextFixtures,
            ],
            'used_round_metadata' => false,
        ];
    }

    private function fallbackByDates(int $leagueId): array
    {
        $today = Carbon::now()->toDateString();
        $pastEnd = Carbon::now()->subDay()->endOfDay();
        $pastStart = Carbon::now()->subDays(7)->startOfDay();

        $lastFixtures = Fixture::query()
            ->where('league_id', $leagueId)
            ->whereBetween('date', [$pastStart, $pastEnd])
            ->whereNotNull('home_goals')
            ->whereNotNull('away_goals')
            ->orderByDesc('date')
            ->get();

        $nextFixtures = Fixture::query()
            ->where('league_id', $leagueId)
            ->where('date', '>=', $today.' 00:00:00')
            ->orderBy('date')
            ->limit(40)
            ->get();

        return [
            'last_round' => [
                'number' => null,
                'label' => 'Últimos 7 dias',
                'fixtures' => $lastFixtures,
            ],
            'next_round' => [
                'number' => null,
                'label' => 'Próximos jogos',
                'fixtures' => $nextFixtures,
            ],
            'used_round_metadata' => false,
        ];
    }
}
