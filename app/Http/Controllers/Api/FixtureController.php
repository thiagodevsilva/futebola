<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fixture;
use App\Models\League;
use App\Services\Football\FixtureHomeRoundService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixtureController extends Controller
{
    public function homeRounds(Request $request, FixtureHomeRoundService $homeRoundService): JsonResponse
    {
        $externalId = $request->get('external_league_id');
        $leagueId = $request->get('league_id');

        $league = null;
        if ($leagueId) {
            $league = League::find($leagueId);
        }
        if ($league === null && $externalId !== null && $externalId !== '') {
            $league = League::where('external_id', (int) $externalId)->first();
        }
        if ($league === null) {
            $league = League::where('football_data_org_code', 'BSA')->first();
        }

        if ($league === null) {
            return response()->json([
                'data' => null,
                'meta' => ['message' => 'Campeonato não encontrado.'],
            ], 404);
        }

        $resolved = $homeRoundService->resolveHomeRounds($league->id);

        $mapFixtures = fn ($collection) => $collection->map(fn (Fixture $f) => $this->fixtureToApiArray($f))->values();

        return response()->json([
            'data' => [
                'league' => [
                    'id' => $league->id,
                    'external_id' => $league->external_id,
                    'name' => $league->name,
                    'logo' => $league->logo,
                ],
                'last_round' => [
                    'number' => $resolved['last_round']['number'],
                    'label' => $resolved['last_round']['label'],
                    'fixtures' => $mapFixtures($resolved['last_round']['fixtures']),
                ],
                'next_round' => [
                    'number' => $resolved['next_round']['number'],
                    'label' => $resolved['next_round']['label'],
                    'fixtures' => $mapFixtures($resolved['next_round']['fixtures']),
                ],
            ],
            'meta' => [
                'used_round_metadata' => $resolved['used_round_metadata'],
            ],
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $from = $request->get('from', Carbon::now()->toDateString());
        $to = $request->get('to', Carbon::now()->addDays(14)->toDateString());
        $leagueId = $request->get('league_id');
        $externalId = $request->get('external_league_id');

        $today = Carbon::now()->toDateString();
        $isPastRange = $to < $today;

        $query = Fixture::with('league')
            ->whereBetween('date', [$from, $to]);

        if ($isPastRange) {
            $query->whereNotNull('home_goals')->whereNotNull('away_goals')->orderBy('date', 'desc');
        } else {
            $query->orderBy('date');
        }

        if ($leagueId) {
            $query->where('league_id', $leagueId);
        }
        if ($externalId) {
            $league = League::where('external_id', $externalId)->first();
            if ($league) {
                $query->where('league_id', $league->id);
            }
        }
        if (!$leagueId && !$externalId) {
            $query->whereHas('league', fn ($q) => $q->where('active', true));
        }

        $fixtures = $query->get();
        $grouped = $fixtures->groupBy('league_id')->values()->map(function ($rows) {
            $first = $rows->first();
            $league = $first ? $first->league : null;
            return [
                'league' => $league ? [
                    'id' => $league->id,
                    'external_id' => $league->external_id,
                    'name' => $league->name,
                    'logo' => $league->logo,
                ] : null,
                'fixtures' => $rows->map(fn (Fixture $f) => $this->fixtureToApiArray($f))->values(),
            ];
        });

        return response()->json(['data' => $grouped]);
    }

    /**
     * @return array<string, mixed>
     */
    private function fixtureToApiArray(Fixture $f): array
    {
        return [
            'id' => $f->id,
            'date' => $f->date->toIso8601String(),
            'home_team_name' => $f->home_team_name,
            'home_team_logo' => $f->home_team_logo,
            'away_team_name' => $f->away_team_name,
            'away_team_logo' => $f->away_team_logo,
            'home_goals' => $f->home_goals,
            'away_goals' => $f->away_goals,
            'status' => $f->status,
            'venue' => $f->venue,
            'match_round' => $f->match_round,
        ];
    }
}
