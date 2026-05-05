<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fixture;
use App\Models\League;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FixtureController extends Controller
{
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
                'fixtures' => $rows->map(fn ($f) => [
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
                ])->values(),
            ];
        });

        return response()->json(['data' => $grouped]);
    }
}
