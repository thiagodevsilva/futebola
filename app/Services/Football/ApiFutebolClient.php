<?php

namespace App\Services\Football;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Cliente para a API Futebol (api-futebol.com.br) - gratuita, dados do Brasil atualizados.
 * Auth: Authorization: Bearer {key}
 */
class ApiFutebolClient
{
    public function __construct(
        protected string $baseUrl,
        protected string $apiKey,
        protected int $timeout,
        protected int $tabelaTtl,
        protected int $partidasTtl
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public static function fromConfig(): self
    {
        return new self(
            config('api-futebol.base_url'),
            config('api-futebol.key'),
            config('api-futebol.timeout'),
            config('api-futebol.cache.tabela_ttl'),
            config('api-futebol.cache.partidas_ttl')
        );
    }

    /** Lista de campeonatos disponíveis */
    public function getCampeonatos(): array
    {
        $data = $this->request('GET', '/campeonatos');
        return is_array($data) ? $data : [];
    }

    /** Tabela de classificação por campeonato_id */
    public function getTabela(int $campeonatoId): array
    {
        $cacheKey = "api_futebol_tabela_{$campeonatoId}";
        $data = $this->request('GET', "/campeonatos/{$campeonatoId}/tabela");
        $result = is_array($data) ? $data : [];
        if (!empty($result)) {
            Cache::put($cacheKey, $result, $this->tabelaTtl);
        }
        return $result;
    }

    /** Partidas do campeonato (todas) */
    public function getPartidas(int $campeonatoId): array
    {
        $cacheKey = "api_futebol_partidas_{$campeonatoId}";
        $data = $this->request('GET', "/campeonatos/{$campeonatoId}/partidas");
        $result = is_array($data) ? $data : [];
        if (!empty($result)) {
            Cache::put($cacheKey, $result, $this->partidasTtl);
        }
        return $result;
    }

    protected function request(string $method, string $path): ?array
    {
        if ($this->apiKey === '') {
            Log::debug('API-Futebol: no key configured');
            return null;
        }

        $url = $this->baseUrl . $path;

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey,
                    'Accept' => 'application/json',
                ])
                ->get($url);

            if (!$response->successful()) {
                Log::warning('API-Futebol request failed', [
                    'url' => $url,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            return $response->json();
        } catch (\Throwable $e) {
            Log::warning('API-Futebol exception', ['message' => $e->getMessage()]);
            return null;
        }
    }
}
