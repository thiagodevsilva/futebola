<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Models\Standing;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StandingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        // Temporada: parâmetro ou ano atual (compatível com football-data.org e API-Football)
        $season = (int) $request->get('season', now()->year);
        $leagueId = $request->get('league_id'); // internal id
        $externalId = $request->get('external_league_id'); // API id

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
        $grouped = $standings->groupBy('league_id')->values()->map(function ($rows) {
            $first = $rows->first();
            $league = $first ? $first->league : null;
            return [
                'league' => $league ? [
                    'id' => $league->id,
                    'external_id' => $league->external_id,
                    'name' => $league->name,
                    'logo' => $league->logo,
                ] : null,
                'standings' => $rows->map(fn ($s) => [
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
                ])->values(),
            ];
        });

        return response()->json(['data' => $grouped, 'meta' => ['season' => $season]]);
    }
}
