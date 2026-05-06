<?php

namespace App\Services\Football\Portals\Providers;

use App\Contracts\PortalFootballProviderContract;
use App\Services\Football\Portals\PortalFootballHtmlJsonDiscovery;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Página HTML de sites Next.js: extrai JSON do script #__NEXT_DATA__ (e outros scripts JSON)
 * e reutiliza as mesmas heurísticas de PortalFootballJsonInspector.
 */
class NextDataHtmlPortalProvider implements PortalFootballProviderContract
{
    private ?string $htmlCache = null;

    private ?string $htmlCacheUrl = null;

    public function __construct(
        protected string $competitionCode,
        protected string $providerKey,
        protected ?string $standingsPageUrl,
        protected ?string $fixturesPageUrl,
        protected string $userAgent,
        protected int $timeoutSeconds,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfigArray(array $config): self
    {
        $standings = $config['standings_page_url'] ?? $config['standings_url'] ?? null;
        $fixtures = $config['fixtures_page_url'] ?? $config['fixtures_url'] ?? null;

        return new self(
            (string) ($config['competition_code'] ?? ''),
            (string) ($config['provider_key'] ?? 'next-data-html'),
            is_string($standings) && $standings !== '' ? $standings : null,
            is_string($fixtures) && $fixtures !== '' ? $fixtures : null,
            (string) ($config['user_agent'] ?? 'FutebolaPortal/1.0'),
            (int) ($config['timeout'] ?? 25),
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
        if ($this->standingsPageUrl === null) {
            return [];
        }

        $html = $this->htmlFor($this->standingsPageUrl);

        return $this->extractStandingsFromHtml($html);
    }

    public function fetchFixtures(Carbon $from, Carbon $to): array
    {
        $pageUrl = $this->fixturesPageUrl ?? $this->standingsPageUrl;
        if ($pageUrl === null) {
            return [];
        }

        $html = $this->htmlFor($pageUrl);
        $fixtures = $this->extractFixturesFromHtml($html);

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

    private function htmlFor(string $url): string
    {
        if ($this->htmlCache !== null && $this->htmlCacheUrl === $url) {
            return $this->htmlCache;
        }

        $this->htmlCache = $this->fetchHtml($url);
        $this->htmlCacheUrl = $url;

        return $this->htmlCache;
    }

    private function fetchHtml(string $url): string
    {
        try {
            $response = Http::withHeaders([
                'User-Agent' => $this->userAgent,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'pt-BR,pt;q=0.9,en;q=0.8',
            ])
                ->timeout($this->timeoutSeconds)
                ->get($url);

            if (! $response->successful()) {
                Log::warning('NextDataHtmlPortalProvider: HTTP falhou', [
                    'url' => $url,
                    'status' => $response->status(),
                ]);

                return '';
            }

            return $response->body();
        } catch (\Throwable $e) {
            Log::warning('NextDataHtmlPortalProvider: exceção', [
                'url' => $url,
                'message' => $e->getMessage(),
            ]);

            return '';
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractStandingsFromHtml(string $html): array
    {
        foreach (PortalFootballHtmlJsonDiscovery::scriptBlobsFromHtml($html) as $blob) {
            $payload = PortalFootballHtmlJsonDiscovery::findFirstRecognizedPayload($blob['data']);
            if ($payload['standings'] !== []) {
                return $payload['standings'];
            }
        }

        return [];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function extractFixturesFromHtml(string $html): array
    {
        foreach (PortalFootballHtmlJsonDiscovery::scriptBlobsFromHtml($html) as $blob) {
            $payload = PortalFootballHtmlJsonDiscovery::findFirstRecognizedPayload($blob['data']);
            if ($payload['fixtures'] !== []) {
                return $payload['fixtures'];
            }
        }

        return [];
    }
}
