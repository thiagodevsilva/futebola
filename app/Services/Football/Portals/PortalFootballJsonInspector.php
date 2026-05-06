<?php

namespace App\Services\Football\Portals;

/**
 * Extrai listas de tabela/jogos de um JSON decodificado (mesma heurística do sync portal).
 */
final class PortalFootballJsonInspector
{
    /**
     * @param  array<string, mixed>|list<mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function extractStandingsList(array $data): array
    {
        $candidates = [
            $data['standings'] ?? null,
            $data['tabela'] ?? null,
            $data['classification'] ?? null,
            $data['table'] ?? null,
            $data['rows'] ?? null,
            $data['data'] ?? null,
            $data['lista'] ?? null,
        ];

        foreach ($candidates as $list) {
            if (is_array($list) && $list !== [] && self::looksLikeStandingRows($list)) {
                return array_values(array_filter(array_map(fn ($r) => is_array($r) ? $r : [], $list)));
            }
        }

        if (self::looksLikeStandingRows($data)) {
            return array_values(array_filter(array_map(fn ($r) => is_array($r) ? $r : [], $data)));
        }

        return [];
    }

    /**
     * @param  array<string, mixed>|list<mixed>  $data
     * @return list<array<string, mixed>>
     */
    public static function extractFixturesList(array $data): array
    {
        $candidates = [
            $data['fixtures'] ?? null,
            $data['jogos'] ?? null,
            $data['matches'] ?? null,
            $data['partidas'] ?? null,
            $data['games'] ?? null,
            $data['data'] ?? null,
            $data['rodada'] ?? null,
        ];

        foreach ($candidates as $list) {
            if (is_array($list) && $list !== [] && self::looksLikeFixtureRows($list)) {
                return array_values(array_filter(array_map(fn ($r) => is_array($r) ? $r : [], $list)));
            }
        }

        if (self::looksLikeFixtureRows($data)) {
            return array_values(array_filter(array_map(fn ($r) => is_array($r) ? $r : [], $data)));
        }

        return [];
    }

    /**
     * @param  array<mixed>  $list
     */
    public static function looksLikeStandingRows(array $list): bool
    {
        $first = reset($list);
        if (! is_array($first)) {
            return false;
        }

        if (isset($first['points']) || isset($first['pontos']) || isset($first['pts'])) {
            if (
                isset($first['club']) || isset($first['team']) || isset($first['squad'])
                || isset($first['participant']) || data_get($first, 'team.name')
                || data_get($first, 'club.name')
            ) {
                return true;
            }
        }

        return isset($first['team']) || isset($first['team_name']) || isset($first['nome'])
            || isset($first['teamName']) || isset($first['position']) || isset($first['rank'])
            || isset($first['posicao']) || isset($first['standing']) || isset($first['place'])
            || data_get($first, 'club.name') || data_get($first, 'team.name')
            || data_get($first, 'stats.points');
    }

    /**
     * @param  array<mixed>  $list
     */
    public static function looksLikeFixtureRows(array $list): bool
    {
        $first = reset($list);
        if (! is_array($first)) {
            return false;
        }

        $hasTeams = (isset($first['home']) || isset($first['home_team_name']) || isset($first['mandante']))
            && (isset($first['away']) || isset($first['away_team_name']) || isset($first['visitante']));
        $hasDate = isset($first['date']) || isset($first['data']) || isset($first['utcDate']);

        return $hasTeams && $hasDate;
    }
}
