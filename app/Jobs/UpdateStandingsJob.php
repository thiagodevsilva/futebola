<?php

namespace App\Jobs;

use App\Models\League;
use App\Services\Football\FootballDataService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdateStandingsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 60;

    public function __construct(
        public ?int $leagueId = null,
        public ?int $season = null
    ) {
        $this->onQueue('football');
    }

    public function handle(FootballDataService $service): void
    {
        $season = $this->season ?? config('api-football.default_season', 2024);
        $leagues = $this->leagueId
            ? League::active()->where('id', $this->leagueId)->get()
            : League::active()->get();

        foreach ($leagues as $league) {
            try {
                $count = $service->updateStandings($league->external_id, $season);
                Log::info('UpdateStandingsJob done', ['league' => $league->name, 'season' => $season, 'rows' => $count]);
            } catch (\Throwable $e) {
                Log::error('UpdateStandingsJob failed', ['league_id' => $league->id, 'message' => $e->getMessage()]);
                if ($this->leagueId) {
                    throw $e;
                }
            }
        }
    }
}
