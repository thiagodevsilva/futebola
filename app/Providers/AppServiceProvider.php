<?php

namespace App\Providers;

use App\Services\Football\ApiFootballClient;
use App\Services\Football\ApiFutebolClient;
use App\Services\Football\FootballDataOrgClient;
use App\Services\Football\FootballDataOrgDataService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(ApiFootballClient::class, fn () => ApiFootballClient::fromConfig());
        $this->app->singleton(ApiFutebolClient::class, fn () => ApiFutebolClient::fromConfig());
        $this->app->singleton(FootballDataOrgClient::class, fn () => FootballDataOrgClient::fromConfig());
        $this->app->singleton(FootballDataOrgDataService::class, function ($app) {
            return new FootballDataOrgDataService($app->make(FootballDataOrgClient::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
