<?php

use App\Jobs\FetchRssFeedsJob;
use App\Jobs\UpdateFixturesJob;
use App\Jobs\UpdateStandingsJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::job(new FetchRssFeedsJob)->everyFifteenMinutes()->name('rss-feeds')->withoutOverlapping(10);
Schedule::job(new UpdateStandingsJob)->hourly()->name('standings')->withoutOverlapping(15);
Schedule::job(new UpdateFixturesJob)->everyThirtyMinutes()->name('fixtures')->withoutOverlapping(15);

if (config('portal-football.enabled')) {
    Schedule::command('futebola:sync-portal-football')->everyThirtyMinutes()->name('portal-football')->withoutOverlapping(15);
}
