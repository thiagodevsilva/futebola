<?php

namespace App\Services\Football\Portals\Providers;

use App\Contracts\PortalFootballProviderContract;
use App\Services\Football\Portals\PortalFootballJsonInspector;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Busca JSON em URLs configuráveis (ex.: endpoint que o portal carrega no navegador).
 * Documente no .env as URLs obtidas via DevTools → Network ao abrir a página da competição.
 */
class ConfigurableJsonPortalProvider implements PortalFootballProviderContract
{
    public function __construct(
        protected string $competitionCode,
        protected string $providerKey,
        protected ?string $standingsUrl,
        protected ?string $fixturesUrl,
        protected string $userAgent,
        protected int $timeoutSeconds,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfigArray(array $config): self
    {
        return new self(
            (string) ($config['competition_code'] ?? ''),
            (string) ($config['provider_key'] ?? 'json'),
            isset($config['standings_url']) ? (string) $config['standings_url'] : null,
            isset($config['fixtures_url']) ? (string) $config['fixtures_url'] : null,
            (string) ($config['user_agent'] ?? 'FutebolaPortal/1.0'),
            (int) ($config['timeout'] ?? 15),
        );
    }

    public function competitionCode(): string
    {
        return $this->competitionCode;
    }

    public function providerKey(): string
    {
        return $this->providerKey;
    }

    public function fetchStandings(): array
    {
        if ($this->standingsUrl === null || $this->standingsUrl === '') {
            return [];
        }

        $data = $this->getJson($this->standingsUrl);
        if ($data === null) {
            return [];
        }

        return PortalFootballJsonInspector::extractStandingsList($data);
    }

    public function fetchFixtures(Carbon $from, Carbon $to): array
    {
        if ($this->fixturesUrl === null || $this->fixturesUrl === '') {
            return [];
        }

        $data = $this->getJson($this->fixturesUrl);
        if ($data === null) {
            return [];
        }

        $fixtures = PortalFootballJsonInspector::extractFixturesList($data);

        return array_values(array_filter($fixtures, function (array $row) use ($from, $to) {
            $normDate = $row['date'] ?? $row['data'] ?? $row['utcDate'] ?? null;
            if (! is_string($normDate) || $normDate === '') {
                return false;
            }
            try {
                $d = Carbon::parse($normDate);
            } catch (\Throwable) {
                return false;
            }

            return $d->between($from->copy()->startOfDay(), $to->copy()->endOfDay());
        }));
    }

    /**
     * @return array<string, mixed>|list<mixed>|null
     */
    protected function getJson(string $url): ?array
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
                'Accept' => 'application/json, text/plain, */*',
            ])
                ->timeout($this->timeoutSeconds)
                ->get($url);

            if (! $response->successful()) {
                Log::warning('PortalFootball JSON request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return null;
            }

            $json = $response->json();
            if (! is_array($json)) {
                return null;
            }

            return $json;
        } catch (\Throwable $e) {
            Log::warning('PortalFootball JSON request exception', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
