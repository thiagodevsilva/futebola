<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class TestApiFootballCommand extends Command
{
    protected $signature = 'futebola:test-api-football';
    protected $description = 'Testa a conexão com a API-Football (mostra status e resposta bruta)';

    public function handle(): int
    {
        $key = config('api-football.key');
        $baseUrl = rtrim(config('api-football.base_url'), '/');
        // Plano grátis só permite temporadas 2022–2024
        $season = config('api-football.default_season', 2024);
        $url = $baseUrl . '/standings?league=71&season=' . $season;

        $this->line('URL: ' . $url);
        $this->line('Chave configurada: ' . ($key !== '' ? 'Sim (' . substr($key, 0, 4) . '...)' : 'Não'));
        $this->line('Se acabou de alterar o .env, rode: php artisan config:clear');
        $this->newLine();

        if ($key === '') {
            $this->error('Defina API_FOOTBALL_KEY no .env');
            return self::FAILURE;
        }

        // Envia os dois headers que a API pode aceitar (My Access / RapidAPI)
        $response = Http::timeout(15)
            ->withHeaders([
                'x-apisports-key' => $key,
                'x-rapidapi-host' => 'v3.football.api-sports.io',
                'x-rapidapi-key' => $key,
                'Accept' => 'application/json',
            ])
            ->get($url);

        $this->line('HTTP Status: ' . $response->status());
        $this->line('Resposta (resumida):');
        $body = $response->json();
        if ($body === null) {
            $this->line($response->body());
        } else {
            $this->line(json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        if (!$response->successful()) {
            $this->newLine();
            $this->warn('Se for 401/403: confira a chave no .env e no painel api-football.com (My Access).');
            $this->warn('Se a API pedir x-rapidapi-host, avise para ajustarmos o cliente.');
            return self::FAILURE;
        }

        $this->info('Conexão OK.');
        return self::SUCCESS;
    }
}
