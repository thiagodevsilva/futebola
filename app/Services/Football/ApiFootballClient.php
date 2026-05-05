<?php

namespace App\Services\Football;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ApiFootballClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
        protected int $timeout,
        protected int $standingsTtl,
        protected int $fixturesTtl
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public static function fromConfig(): self
    {
        return new self(
            config('api-football.base_url'),
            config('api-football.key'),
            config('api-football.timeout'),
            config('api-football.cache.standings_ttl'),
            config('api-football.cache.fixtures_ttl')
        );
    }

    /**
     * Standings por league e season. Resposta crua da API (array).
     *
     * @return array<string, mixed>
     */
    public function getStandings(int $leagueId, int $season): array
    {
        $cacheKey = "api_football_standings_{$leagueId}_{$season}";
        $data = $this->request('GET', '/standings', [
            'league' => $leagueId,
            'season' => $season,
        ]);
        $result = $data !== null ? ($data['response'] ?? []) : [];

        // Só cacheia quando há dados (evita cachear resposta vazia/erro após corrigir a chave)
        if (!empty($result)) {
            Cache::put($cacheKey, $result, $this->standingsTtl);
        }

        return $result;
    }

    /**
     * Fixtures por league e datas (date from / to). Resposta crua.
     *
     * @return array<int, array>
     */
    public function getFixtures(int $leagueId, string $from, string $to): array
    {
        $cacheKey = "api_football_fixtures_{$leagueId}_{$from}_{$to}";
        $data = $this->request('GET', '/fixtures', [
            'league' => $leagueId,
            'from' => $from,
            'to' => $to,
        ]);
        $result = $data !== null ? ($data['response'] ?? []) : [];

        if (!empty($result)) {
            Cache::put($cacheKey, $result, $this->fixturesTtl);
        }

        return $result;
    }

    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>|null
     */
    protected function request(string $method, string $path, array $query = []): ?array
    {
        if ($this->apiKey === '') {
            Log::debug('API-Football: no key configured');
            return null;
        }

        $url = $this->baseUrl . $path;

        try {
            // v3.football.api-sports.io aceita a chave em qualquer um dos headers;
            // enviamos os dois para compatibilidade (painel "My Access" e RapidAPI).
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'x-apisports-key' => $this->apiKey,
                    'x-rapidapi-host' => 'v3.football.api-sports.io',
                    'x-rapidapi-key' => $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($url, $query);

            if (!$response->successful()) {
                Log::warning('API-Football request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            if (isset($data['errors']) && !empty($data['errors'])) {
                Log::warning('API-Football errors', ['errors' => $data['errors']]);
            }

            return $data;
        } catch (\Throwable $e) {
            Log::warning('API-Football exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Limpa cache de standings e fixtures (útil para forçar atualização).
     */
    public function clearCache(int $leagueId, int $season, string $from, string $to): void
    {
        Cache::forget("api_football_standings_{$leagueId}_{$season}");
        Cache::forget("api_football_fixtures_{$leagueId}_{$from}_{$to}");
    }
}
