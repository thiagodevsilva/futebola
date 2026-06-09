<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Models\Standing;
use App\Services\Football\StandingZoneResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StandingController extends Controller
{
    public function index(Request $request, StandingZoneResolver $zoneResolver): JsonResponse
    {
        $season = (int) $request->get('season', now()->year);
        $leagueId = $request->get('league_id');
        $externalId = $request->get('external_league_id');

        $query = Standing::with('league')
            ->where('season', $season)
            ->orderBy('rank');

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

        $standings = $query->get();
        $grouped = $standings->groupBy('league_id')->values()->map(function ($rows) use ($zoneResolver) {
            $first = $rows->first();
            $league = $first ? $first->league : null;
            $leagueZones = $league ? $zoneResolver->zonesForLeague($league) : [];

            return [
                'league' => $league ? [
                    'id' => $league->id,
                    'external_id' => $league->external_id,
                    'name' => $league->name,
                    'logo' => $league->logo,
                ] : null,
                'zones' => $leagueZones,
                'standings' => $rows->map(function ($s) use ($league, $zoneResolver) {
                    return [
                        'rank' => $s->rank,
                        'team_name' => $s->team_name,
                        'team_logo' => $s->team_logo,
                        'points' => $s->points,
                        'played' => $s->played,
                        'win' => $s->win,
                        'draw' => $s->draw,
                        'loss' => $s->loss,
                        'goals_for' => $s->goals_for,
                        'goals_against' => $s->goals_against,
                        'goal_diff' => $s->goal_diff,
                        'form' => $s->form,
                        'zone' => $league ? $zoneResolver->zoneForRank($league, (int) $s->rank) : null,
                    ];
                })->values(),
            ];
        });

        return response()->json(['data' => $grouped, 'meta' => ['season' => $season]]);
    }
}
