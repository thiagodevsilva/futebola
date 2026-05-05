<?php

namespace App\Services\Football;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente para football-data.org (plano gratuito permanente).
 * Auth: X-Auth-Token. Inclui Brasileirão Série A/B e Copa do Brasil no free tier.
 */
class FootballDataOrgClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $token,
        protected int $timeout,
        protected int $standingsTtl,
        protected int $matchesTtl
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public static function fromConfig(): self
    {
        return new self(
            config('football-data-org.base_url'),
            config('football-data-org.token'),
            config('football-data-org.timeout'),
            config('football-data-org.cache.standings_ttl'),
            config('football-data-org.cache.matches_ttl')
        );
    }

    /** Lista de competições (free tier retorna só as 12 incluídas). */
    public function getCompetitions(): array
    {
        $data = $this->request('GET', '/competitions');
        if ($data === null || !isset($data['competitions'])) {
            return [];
        }
        return $data['competitions'];
    }

    /** Tabela de classificação. Resposta: standings[] com table[]. */
    public function getStandings(string $competitionId): array
    {
        $cacheKey = 'football_data_org_standings_' . $competitionId;
        $data = $this->request('GET', '/competitions/' . $competitionId . '/standings');
        $result = $this->extractStandingsTable($data);
        if (!empty($result)) {
            Cache::put($cacheKey, $result, $this->standingsTtl);
        }
        return $result;
    }

    /** Partidas entre dateFrom e dateTo (YYYY-MM-DD). */
    public function getMatches(string $competitionId, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $params = array_filter([
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
        ]);
        $path = '/competitions/' . $competitionId . '/matches';
        $url = $params ? $path . '?' . http_build_query($params) : $path;
        $cacheKey = 'football_data_org_matches_' . $competitionId . '_' . ($dateFrom ?? '') . '_' . ($dateTo ?? '');
        $data = $this->request('GET', $url);
        $result = $data !== null && isset($data['matches']) ? $data['matches'] : [];
        if (!empty($result)) {
            Cache::put($cacheKey, $result, $this->matchesTtl);
        }
        return $result;
    }

    /**
     * @param array<string, mixed>|null $data
     * @return array<int, array>
     */
    private function extractStandingsTable(?array $data): array
    {
        if ($data === null || !isset($data['standings']) || !is_array($data['standings'])) {
            return [];
        }
        foreach ($data['standings'] as $standing) {
            $type = $standing['type'] ?? '';
            if (strtoupper($type) === 'TOTAL' && isset($standing['table']) && is_array($standing['table'])) {
                return $standing['table'];
            }
        }
        if (isset($data['standings'][0]['table'])) {
            return $data['standings'][0]['table'];
        }
        return [];
    }

    protected function request(string $method, string $path): ?array
    {
        if ($this->token === '') {
            Log::debug('FootballDataOrg: no token configured');
            return null;
        }

        $url = $this->baseUrl . (str_starts_with($path, '/') ? $path : '/' . $path);

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'X-Auth-Token' => $this->token,
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('FootballDataOrg request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning('FootballDataOrg exception', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
