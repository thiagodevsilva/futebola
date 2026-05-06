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
 *
 * Para jogos estilo OneFootball (matchCards com kickoff/homeTeam/awayTeam), usa coleta recursiva.
 * Em fixtures_page_url pode haver várias URLs separadas por vírgula (ex.: /jogos e /resultados).
 */
class NextDataHtmlPortalProvider implements PortalFootballProviderContract
{
    /** @var array<string, string> url => html */
    private array $htmlByUrl = [];

    /**
     * @param  list<string>  $fixturesPageUrls
     */
    public function __construct(
        protected string $competitionCode,
        protected string $providerKey,
        protected ?string $standingsPageUrl,
        protected array $fixturesPageUrls,
        protected string $userAgent,
        protected int $timeoutSeconds,
    ) {}

    /**
     * @param  array<string, mixed>  $config
     */
    public static function fromConfigArray(array $config): self
    {
        $standings = $config['standings_page_url'] ?? $config['standings_url'] ?? null;
        $fixturesRaw = $config['fixtures_page_url'] ?? $config['fixtures_url'] ?? '';
        $urls = [];
        if (is_string($fixturesRaw) && $fixturesRaw !== '') {
            foreach (preg_split('/\s*,\s*/', $fixturesRaw) as $u) {
                $u = trim($u);
                if ($u !== '') {
                    $urls[] = $u;
                }
            }
        }

        return new self(
            (string) ($config['competition_code'] ?? ''),
            (string) ($config['provider_key'] ?? 'next-data-html'),
            is_string($standings) && $standings !== '' ? $standings : null,
            $urls,
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
        $urls = $this->fixturesPageUrls;
        if ($urls === [] && $this->standingsPageUrl !== null) {
            $urls = [$this->standingsPageUrl];
        }
        if ($urls === []) {
            return [];
        }

        $merged = [];
        foreach ($urls as $url) {
            $html = $this->htmlFor($url);
            foreach ($this->extractFixturesFromHtml($html) as $row) {
                $merged[] = $row;
            }
        }

        $merged = PortalFootballHtmlJsonDiscovery::dedupeFixtureRows($merged);

        return array_values(array_filter($merged, function (array $row) use ($from, $to) {
            $normDate = $row['date'] ?? $row['data'] ?? $row['utcDate'] ?? $row['kickoff'] ?? null;
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
        if (! isset($this->htmlByUrl[$url])) {
            $this->htmlByUrl[$url] = $this->fetchHtml($url);
        }

        return $this->htmlByUrl[$url];
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
        return PortalFootballHtmlJsonDiscovery::extractFixturesFromHtml($html);
    }
}
