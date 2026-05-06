<?php

namespace App\Services\Football\Portals;

use Illuminate\Support\Uri;

/**
 * Tenta achar JSON útil dentro de uma página HTML (página de tabela, etc.).
 */
final class PortalFootballHtmlJsonDiscovery
{
    /**
     * @return list<array{label: string, data: array<string, mixed>}>
     */
    public static function scriptBlobsFromHtml(string $html): array
    {
        $out = [];
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument;
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($dom);

        $queries = [
            '//script[@id="__NEXT_DATA__"]' => 'script#__NEXT_DATA__',
            '//script[@id="ld-schema"]' => 'script#ld-schema',
            '//script[@type="application/json"]' => 'script[type=application/json]',
        ];

        foreach ($queries as $query => $label) {
            foreach ($xpath->query($query) as $script) {
                $text = trim($script->textContent);
                if ($text === '') {
                    continue;
                }
                $j = json_decode($text, true);
                if (is_array($j)) {
                    $out[] = ['label' => $label, 'data' => $j];
                }
            }
        }

        // Alguns sites usam id genérico com JSON grande
        foreach ($xpath->query('//script[starts-with(@id, "__") or contains(@id, "STATE") or contains(@id, "PRELOADED")]') as $script) {
            /** @var \DOMElement $script */
            $id = $script->getAttribute('id');
            if ($id === '__NEXT_DATA__') {
                continue;
            }
            $text = trim($script->textContent);
            if (strlen($text) < 50 || strlen($text) > 5_000_000) {
                continue;
            }
            $j = json_decode($text, true);
            if (is_array($j)) {
                $out[] = ['label' => 'script#'.$id, 'data' => $j];
            }
        }

        libxml_clear_errors();

        return $out;
    }

    /**
     * URLs absolutas candidatas a API (aparecem no HTML/JS).
     *
     * @return list<string>
     */
    public static function candidateApiUrls(string $html, string $baseUrl): array
    {
        $found = [];

        if (preg_match_all('#https?://[^\s"\'\<\>]+#u', $html, $m)) {
            foreach ($m[0] as $u) {
                $u = rtrim($u, '.,;)\]}');
                if (self::looksLikeApiUrl($u)) {
                    $found[] = $u;
                }
            }
        }

        // Aspas: "/api/..." ou '/v1/...'
        if (preg_match_all('#["\'](/[^"\']*(?:api|graphql|standings|classificacao|fixtures|jogos|campeonato)[^"\']*)["\']#iu', $html, $m2)) {
            foreach ($m2[1] as $path) {
                $abs = self::absoluteUrl($baseUrl, $path);
                if ($abs !== null && self::looksLikeApiUrl($abs)) {
                    $found[] = $abs;
                }
            }
        }

        $found = array_values(array_unique($found));

        return array_slice($found, 0, 40);
    }

    public static function looksLikeApiUrl(string $url): bool
    {
        $lower = strtolower($url);
        if (str_contains($lower, 'fonts.googleapis') || str_contains($lower, 'gstatic') || str_contains($lower, 'googletagmanager')) {
            return false;
        }

        return str_contains($lower, '/api/')
            || str_contains($lower, 'graphql')
            || str_contains($lower, '.json')
            || preg_match('#/(?:v\\d+|rest)/#i', $url)
            || str_contains($lower, 'standings')
            || str_contains($lower, 'classificacao')
            || str_contains($lower, 'fixtures')
            || (str_contains($lower, 'brasileirao') && str_contains($lower, 'json'))
            || str_contains($lower, 'web-experience')
            || str_contains($lower, 'onefootball.com/api');
    }

    public static function absoluteUrl(string $baseUrl, string $pathOrUrl): ?string
    {
        $pathOrUrl = trim($pathOrUrl);
        if (filter_var($pathOrUrl, FILTER_VALIDATE_URL)) {
            return $pathOrUrl;
        }
        if ($pathOrUrl === '' || ! str_starts_with($pathOrUrl, '/')) {
            return null;
        }

        try {
            return (string) Uri::of($baseUrl)->join($pathOrUrl);
        } catch (\Throwable) {
            $p = parse_url($baseUrl);
            if (! isset($p['scheme'], $p['host'])) {
                return null;
            }

            return $p['scheme'].'://'.$p['host'].$pathOrUrl;
        }
    }

    /**
     * Percorre o JSON (ex.: __NEXT_DATA__.props.pageProps) até achar listas que o Futebola reconhece.
     *
     * @return array{standings: list<array<string, mixed>>, fixtures: list<array<string, mixed>>}
     */
    public static function findFirstRecognizedPayload(array $data, int $maxDepth = 16): array
    {
        $s = PortalFootballJsonInspector::extractStandingsList($data);
        $f = PortalFootballJsonInspector::extractFixturesList($data);
        if ($s !== [] || $f !== []) {
            return ['standings' => $s, 'fixtures' => $f];
        }
        if ($maxDepth <= 0) {
            return ['standings' => [], 'fixtures' => []];
        }
        foreach ($data as $child) {
            if (! is_array($child)) {
                continue;
            }
            $r = self::findFirstRecognizedPayload($child, $maxDepth - 1);
            if ($r['standings'] !== [] || $r['fixtures'] !== []) {
                return $r;
            }
        }

        return ['standings' => [], 'fixtures' => []];
    }

    /**
     * Agrega jogos de todos os scripts JSON da página (matchCards OneFootball + formato genérico).
     *
     * @return list<array<string, mixed>>
     */
    public static function extractFixturesFromHtml(string $html): array
    {
        $merged = [];
        foreach (self::scriptBlobsFromHtml($html) as $blob) {
            foreach (self::collectNestedMatchCards($blob['data']) as $row) {
                $merged[] = $row;
            }
            $payload = self::findFirstRecognizedPayload($blob['data']);
            foreach ($payload['fixtures'] as $row) {
                $merged[] = $row;
            }
        }

        return self::dedupeFixtureRows($merged);
    }

    /**
     * OneFootball / Next.js: cartões com kickoff, homeTeam, awayTeam (tip. matchCardsListsAppender).
     *
     * @return list<array<string, mixed>>
     */
    public static function collectNestedMatchCards(array $node, int $maxDepth = 22): array
    {
        $out = [];
        $budget = 120000;
        self::walkMatchCards($node, $out, 0, $maxDepth, $budget);

        return $out;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    public static function dedupeFixtureRows(array $rows): array
    {
        $byKey = [];
        foreach ($rows as $r) {
            if (isset($r['matchId']) && (is_string($r['matchId']) || is_int($r['matchId']))) {
                $k = 'id:'.(string) $r['matchId'];
            } else {
                $kick = (string) ($r['kickoff'] ?? $r['date'] ?? $r['utcDate'] ?? '');
                $k = $kick.'|'.($r['home_team_name'] ?? data_get($r, 'homeTeam.name') ?? '')
                    .'|'.($r['away_team_name'] ?? data_get($r, 'awayTeam.name') ?? '');
            }
            $byKey[$k] = $r;
        }

        return array_values($byKey);
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $node
     * @param  list<array<string, mixed>>  $out
     */
    private static function walkMatchCards($node, array &$out, int $depth, int $maxDepth, int &$budget): void
    {
        if ($budget-- <= 0 || $depth > $maxDepth) {
            return;
        }
        if (! is_array($node)) {
            return;
        }

        if (isset($node['kickoff'], $node['homeTeam'], $node['awayTeam'])
            && is_array($node['homeTeam']) && is_array($node['awayTeam'])
            && isset($node['homeTeam']['name'], $node['awayTeam']['name'])
            && is_string($node['homeTeam']['name']) && is_string($node['awayTeam']['name'])) {
            $out[] = self::flattenMatchCardLikeOneFootball($node);

            return;
        }

        foreach ($node as $child) {
            if (is_array($child)) {
                self::walkMatchCards($child, $out, $depth + 1, $maxDepth, $budget);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private static function flattenMatchCardLikeOneFootball(array $c): array
    {
        $scoreH = $c['homeTeam']['score'] ?? '';
        $scoreA = $c['awayTeam']['score'] ?? '';
        $kickoff = is_string($c['kickoff'] ?? null) ? $c['kickoff'] : '';

        return [
            'matchId' => $c['matchId'] ?? null,
            'kickoff' => $kickoff,
            'date' => $kickoff,
            'utcDate' => $kickoff,
            'home_team_name' => $c['homeTeam']['name'] ?? '',
            'away_team_name' => $c['awayTeam']['name'] ?? '',
            'home_team_logo' => data_get($c, 'homeTeam.imageObject.path'),
            'away_team_logo' => data_get($c, 'awayTeam.imageObject.path'),
            'home_goals' => ($scoreH !== '' && is_numeric($scoreH)) ? (int) $scoreH : null,
            'away_goals' => ($scoreA !== '' && is_numeric($scoreA)) ? (int) $scoreA : null,
        ];
    }
}
