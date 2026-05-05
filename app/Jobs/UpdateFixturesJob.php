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

class UpdateFixturesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(
        public ?int $leagueId = null,
        public int $daysAhead = 14
    ) {
        $this->onQueue('football');
    }

    public function handle(FootballDataService $service): void
    {
        $from = Carbon::now()->toDateString();
        $to = Carbon::now()->addDays($this->daysAhead)->toDateString();
        $leagues = $this->leagueId
            ? League::active()->where('id', $this->leagueId)->get()
            : League::active()->get();

        foreach ($leagues as $league) {
            try {
                $count = $service->updateFixtures($league->external_id, $from, $to);
                Log::info('UpdateFixturesJob done', ['league' => $league->name, 'from' => $from, 'to' => $to, 'rows' => $count]);
            } catch (\Throwable $e) {
                Log::error('UpdateFixturesJob failed', ['league_id' => $league->id, 'message' => $e->getMessage()]);
                if ($this->leagueId) {
                    throw $e;
                }
            }
        }
    }
}
