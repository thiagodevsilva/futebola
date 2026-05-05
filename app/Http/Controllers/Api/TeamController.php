<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\League;
use App\Models\Standing;
use App\Services\SerieATeamsConfig;
use Illuminate\Http\JsonResponse;

class TeamController extends Controller
{
    /**
     * Lista os times do filtro (config + standings quando houver).
     * Sempre retorna pelo menos os times da config, para o filtro nunca sumir.
     */
    public function serieA(): JsonResponse
    {
        $season = (int) now()->year;
        $league = League::where('football_data_org_code', 'BSA')->first();

        $teams = [];
        if ($league) {
            $standings = Standing::where('league_id', $league->id)
                ->where('season', $season)
                ->orderBy('rank')
                ->get();
            foreach ($standings as $s) {
                $topic = SerieATeamsConfig::slugForTeamName($s->team_name);
                if ($topic === null) {
                    continue;
                }
                $teams[] = [
                    'team_id' => $s->team_id,
                    'team_name' => $s->team_name,
                    'team_logo' => $s->team_logo,
                    'topic' => $topic,
                ];
            }
        }

        // Sem standings ou sem match: retorna os times da config (filtro sempre visível)
        if (empty($teams)) {
            foreach (SerieATeamsConfig::teamsList() as $t) {
                $teams[] = [
                    'team_id' => null,
                    'team_name' => $t['name'],
                    'team_logo' => null,
                    'topic' => $t['slug'],
                ];
            }
        }

        return response()->json([
            'data' => $teams,
            'meta' => [
                'season' => $season,
                'league' => $league?->name ?? 'Brasileirão Série A',
            ],
        ]);
    }
}
