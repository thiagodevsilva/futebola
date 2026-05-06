<?php

namespace App\Services\Football\Portals;

use App\Contracts\PortalFootballProviderContract;
use App\Models\League;
use App\Services\Football\Portals\Providers\ConfigurableJsonPortalProvider;
use App\Services\Football\Portals\Providers\NextDataHtmlPortalProvider;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class PortalFootballEngine
{
    public function __construct(
        protected PortalFootballPersistService $persist
    ) {}

    /**
     * @return array{standings: int, fixtures: int, league: string}
     */
    public function syncSource(array $sourceConfig): array
    {
        $code = (string) ($sourceConfig['competition_code'] ?? '');
        if ($code === '') {
            throw new \InvalidArgumentException('competition_code ausente na fonte portal-football.');
        }

        $league = League::query()->where('football_data_org_code', $code)->first();
        if (! $league) {
            throw new \RuntimeException("Liga com football_data_org_code={$code} não encontrada. Rode LeagueSeeder.");
        }

        $seasonRaw = $sourceConfig['season'] ?? null;
        $season = ($seasonRaw !== null && $seasonRaw !== '')
            ? (int) $seasonRaw
            : (int) Carbon::now()->year;
        $provider = $this->makeProvider(array_merge(
            ['competition_code' => $code],
            $sourceConfig
        ));

        $standingsCount = 0;
        $fixturesCount = 0;

        $rows = $provider->fetchStandings();
        if ($rows !== []) {
            $standingsCount = $this->persist->replaceStandings($league, $season, $rows);
        }

        $daysAhead = (int) ($sourceConfig['fixtures_days_ahead'] ?? config('portal-football.fixtures_days_ahead', 14));
        $daysBack = (int) ($sourceConfig['fixtures_days_back'] ?? config('portal-football.fixtures_days_back', 14));
        $from = Carbon::now()->subDays($daysBack)->startOfDay();
        $to = Carbon::now()->addDays($daysAhead)->endOfDay();

        $fixtures = $provider->fetchFixtures($from, $to);
        if ($fixtures !== []) {
            $fixturesCount = $this->persist->upsertFixtures($league, $season, $provider->providerKey(), $fixtures);
        }

        Log::info('PortalFootballEngine syncSource', [
            'league' => $league->name,
            'competition_code' => $code,
            'standings' => $standingsCount,
            'fixtures' => $fixturesCount,
        ]);

        return [
            'league' => $league->name,
            'standings' => $standingsCount,
            'fixtures' => $fixturesCount,
        ];
    }

    /**
     * @return list<array{standings: int, fixtures: int, league: string}>
     */
    public function syncAll(?string $onlyCode = null): array
    {
        $out = [];
        foreach (config('portal-football.sources', []) as $source) {
            if (! ($source['enabled'] ?? false)) {
                continue;
            }
            $code = (string) ($source['competition_code'] ?? '');
            if ($onlyCode !== null && $onlyCode !== '' && $code !== $onlyCode) {
                continue;
            }
            try {
                $out[] = $this->syncSource($source);
            } catch (\Throwable $e) {
                Log::error('PortalFootballEngine sync failed', [
                    'competition_code' => $code,
                    'message' => $e->getMessage(),
                ]);
                throw $e;
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $sourceConfig
     */
    protected function makeProvider(array $sourceConfig): PortalFootballProviderContract
    {
        $merged = array_merge([
            'competition_code' => '',
            'provider_key' => 'portal',
            'user_agent' => config('portal-football.user_agent'),
            'timeout' => config('portal-football.timeout'),
        ], $sourceConfig);

        $driver = (string) ($merged['driver'] ?? 'json');

        return match ($driver) {
            'next_data_page', 'next_data' => NextDataHtmlPortalProvider::fromConfigArray($merged),
            default => ConfigurableJsonPortalProvider::fromConfigArray($merged),
        };
    }
}
