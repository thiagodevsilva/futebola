<?php

namespace App\Console\Commands;

use App\Services\Football\Portals\PortalFootballHtmlJsonDiscovery;
use App\Services\Football\Portals\PortalFootballPersistService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ProbePortalFootballUrlCommand extends Command
{
    protected $signature = 'futebola:probe-portal-url
                            {url : Página do site ou URL que devolve JSON}
                            {--timeout= : Timeout em segundos (padrão: portal-football.timeout)}
                            {--dump : Exibe trecho do JSON decodificado quando nada for reconhecido}
                            {--no-fetch-urls : Não segue URLs de /api/ etc. encontradas no HTML}';

    protected $description = 'Testa URL: se for JSON, analisa; se for HTML, procura JSON em <script> e em links de API no próprio HTML.';

    public function handle(PortalFootballPersistService $persist): int
    {
        $url = (string) $this->argument('url');
        if (! filter_var($url, FILTER_VALIDATE_URL)) {
            $this->error('URL inválida.');

            return self::FAILURE;
        }

        $timeout = (int) ($this->option('timeout') ?: config('portal-football.timeout', 15));
        $userAgent = (string) config('portal-football.user_agent');

        $this->line('GET '.$url);
        $this->line('User-Agent: '.$userAgent);
        $this->newLine();

        try {
            $response = Http::withHeaders([
                'User-Agent' => $userAgent,
                'Accept' => 'text/html,application/xhtml+xml,application/json;q=0.9,*/*;q=0.8',
            ])
                ->timeout($timeout)
                ->get($url);
        } catch (\Throwable $e) {
            $this->error('Falha na requisição: '.$e->getMessage());

            return self::FAILURE;
        }

        $this->line('HTTP '.$response->status());
        $this->line('Content-Type: '.($response->header('Content-Type') ?: '(vazio)'));

        $body = $response->body();
        $data = $response->json();

        if (! is_array($data)) {
            $decoded = json_decode($body, true);
            if (is_array($decoded)) {
                $data = $decoded;
                $this->comment('Corpo interpretado como JSON.');
                $this->newLine();
            }
        }

        if (is_array($data)) {
            if ($this->tryPayload('Resposta direta (JSON)', $url, $data, $persist)) {
                $this->newLine();
                $this->info('Use no .env: PORTAL_BSA_STANDINGS_JSON_URL / FIXTURES_URL = '.$url.' (ou só uma das duas se só uma lista existir).');

                return self::SUCCESS;
            }

            $keys = array_keys($data);
            $this->warn('JSON recebido, mas nenhuma lista de tabela/jogos foi reconhecida (formato diferente ou dados mais fundos no objeto).');
            $this->line('Chaves de topo: '.implode(', ', array_slice($keys, 0, 40)).(count($keys) > 40 ? ' …' : ''));

            $pretty = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            if ($this->option('dump')) {
                $this->newLine();
                $this->line(mb_substr($pretty, 0, 12000).(mb_strlen($pretty) > 12000 ? "\n… (truncado)" : ''));
            } else {
                $this->comment('Use --dump para ver trecho do JSON e comparar chaves com PortalFootballPersistService.');
            }

            return self::FAILURE;
        }

        $looksHtml = str_contains(strtolower($response->header('Content-Type') ?? ''), 'html')
            || preg_match('/^\s*</', $body);

        if (! $looksHtml) {
            $this->warn('Resposta não é JSON nem HTML reconhecível.');
            $sample = mb_substr($body, 0, 400);
            $this->line('<fg=gray>'.$sample.(mb_strlen($body) > 400 ? '…' : '').'</>');

            return self::FAILURE;
        }

        $this->comment('Página HTML — procurando JSON embutido e URLs de API na marcação…');
        $this->newLine();

        $found = false;

        $aggFixturesAll = PortalFootballHtmlJsonDiscovery::extractFixturesFromHtml($body);
        $aggFixtures = array_values(array_filter($aggFixturesAll, function (array $row) {
            $normDate = $row['date'] ?? $row['data'] ?? $row['utcDate'] ?? $row['kickoff'] ?? null;
            if (! is_string($normDate) || $normDate === '') {
                return false;
            }
            try {
                $d = Carbon::parse($normDate);
            } catch (\Throwable) {
                return false;
            }

            return $d->between(
                Carbon::now()->subDays(400)->startOfDay(),
                Carbon::now()->addDays(400)->endOfDay()
            );
        }));
        if ($aggFixtures !== []) {
            $found = true;
            $this->info('Jogos no HTML (matchCards OneFootball / outros formatos): '.count($aggFixtures).' na janela ±400 dias ('.count($aggFixturesAll).' brutos).');
            $this->reportLists([], $aggFixtures, $persist);
            $this->newLine();
        }

        foreach (PortalFootballHtmlJsonDiscovery::scriptBlobsFromHtml($body) as $blob) {
            $label = $blob['label'];
            $payload = PortalFootballHtmlJsonDiscovery::findFirstRecognizedPayload($blob['data']);
            if ($payload['standings'] === [] && $payload['fixtures'] === []) {
                continue;
            }

            $found = true;
            $this->info("Encontrado em <fg=cyan>{$label}</> (JSON dentro da página):");
            $this->reportLists($payload['standings'], $payload['fixtures'], $persist);
            $this->newLine();
            $this->warn('Isso veio do HTML; pode não haver uma URL só de API. Na aba Rede (XHR), confira se existe um GET separado com o mesmo JSON — aí sim use essa URL no .env.');
        }

        if (! $this->option('no-fetch-urls')) {
            $apiUrls = PortalFootballHtmlJsonDiscovery::candidateApiUrls($body, $url);
            if ($apiUrls !== []) {
                $this->line('URLs candidatas no HTML ('.count($apiUrls).'):');
                foreach (array_slice($apiUrls, 0, 15) as $u) {
                    $this->line('  '.$u);
                }
                $this->newLine();

                foreach ($apiUrls as $apiUrl) {
                    try {
                        $r = Http::withHeaders([
                            'User-Agent' => $userAgent,
                            'Accept' => 'application/json, text/plain, */*',
                            'Referer' => $url,
                        ])
                            ->timeout(min($timeout, 20))
                            ->get($apiUrl);
                    } catch (\Throwable) {
                        continue;
                    }

                    if (! $r->successful()) {
                        continue;
                    }

                    $j = $r->json();
                    if (! is_array($j)) {
                        continue;
                    }

                    $payload = PortalFootballHtmlJsonDiscovery::findFirstRecognizedPayload($j);
                    if ($payload['standings'] === [] && $payload['fixtures'] === []) {
                        continue;
                    }

                    $found = true;
                    $this->info('Encontrado ao seguir URL da página:');
                    $this->line('<fg=cyan>'.$apiUrl.'</>');
                    $this->reportLists($payload['standings'], $payload['fixtures'], $persist);
                    $this->newLine();
                    $this->info('Use no .env (standings / fixtures conforme o que apareceu acima):');
                    $this->line('  PORTAL_BSA_STANDINGS_JSON_URL='.$apiUrl);
                    $this->line('  PORTAL_BSA_FIXTURES_JSON_URL='.$apiUrl);
                }
            }
        }

        if ($found) {
            return self::SUCCESS;
        }

        $this->warn('Nenhum JSON com tabela/jogos reconhecível dentro desta página.');
        $this->line('Motivos comuns: dados só no JavaScript em runtime, ou formato React Server Components (stream), ou API protegida.');
        $this->newLine();
        $this->line('Abra o DevTools → <fg=cyan>Rede</> → filtro <fg=cyan>Fetch/XHR</>, recarregue, e probe a URL de cada requisição que pareça lista/tabela.');

        if (str_contains($body, '$Sreact') || preg_match('/^\d+:/', trim($body))) {
            $this->newLine();
            $this->comment('Trecho inicial parece stream Next/React — não é JSON consumível direto.');
        }

        return self::FAILURE;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function tryPayload(string $context, string $recommendedUrl, array $data, PortalFootballPersistService $persist): bool
    {
        $payload = PortalFootballHtmlJsonDiscovery::findFirstRecognizedPayload($data);
        $standings = $payload['standings'];
        $fixturesRaw = $payload['fixtures'];

        $fixtures = array_values(array_filter($fixturesRaw, function (array $row) {
            $normDate = $row['date'] ?? $row['data'] ?? $row['utcDate'] ?? null;
            if (! is_string($normDate) || $normDate === '') {
                return false;
            }
            try {
                $d = Carbon::parse($normDate);
            } catch (\Throwable) {
                return false;
            }

            return $d->between(
                Carbon::now()->subDays(400)->startOfDay(),
                Carbon::now()->addDays(400)->endOfDay()
            );
        }));

        if ($standings === [] && $fixtures === []) {
            return false;
        }

        $this->info($context);
        $this->reportLists($standings, $fixtures, $persist);

        return true;
    }

    /**
     * @param  list<array<string, mixed>>  $standings
     * @param  list<array<string, mixed>>  $fixtures
     */
    private function reportLists(array $standings, array $fixtures, PortalFootballPersistService $persist): void
    {
        $this->line('  Tabela: '.count($standings).' linhas');
        $this->line('  Jogos (janela ±400 dias): '.count($fixtures));

        if ($standings !== []) {
            $norm = $persist->normalizeStandingRow($standings[0], 1);
            $this->newLine();
            $this->line('  Primeira linha (normalizada):');
            if ($norm === null) {
                $this->warn('  null — chaves na 1ª linha: '.implode(', ', array_keys($standings[0])));
                $sample = json_encode($standings[0], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
                $this->line(mb_substr((string) $sample, 0, 2500).(strlen((string) $sample) > 2500 ? "\n…" : ''));
            } else {
                $this->line(json_encode($norm, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }

        if ($fixtures !== []) {
            $norm = $persist->normalizeFixtureRow($fixtures[0]);
            $this->newLine();
            $this->line('  Primeiro jogo (normalizado):');
            if ($norm === null) {
                $this->warn('  null — chaves: '.implode(', ', array_keys($fixtures[0])));
            } else {
                $this->line(json_encode($norm, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        }
    }
}
