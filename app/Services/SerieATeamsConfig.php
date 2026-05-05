<?php

namespace App\Services;

class SerieATeamsConfig
{
    /**
     * Retorna o slug configurado para o team_name (da API/standings), ou null se não houver match.
     */
    public static function slugForTeamName(string $teamName): ?string
    {
        $teams = config('serie_a_teams.teams', []);
        $normalized = self::normalize($teamName);
        if ($normalized === '') {
            return null;
        }
        foreach ($teams as $team) {
            foreach ($team['names'] ?? [] as $name) {
                $n = self::normalize($name);
                if ($n === '' || $n === $normalized) {
                    return $team['slug'];
                }
                if (str_contains($normalized, $n) || str_contains($n, $normalized)) {
                    return $team['slug'];
                }
            }
        }
        return null;
    }

    /**
     * Retorna a lista de slugs configurados (para topic).
     */
    public static function slugs(): array
    {
        $teams = config('serie_a_teams.teams', []);
        return array_column($teams, 'slug');
    }

    /**
     * Retorna a lista de times configurados: [['slug' => ..., 'name' => ..., 'feeds' => [...]], ...]
     */
    public static function teamsList(): array
    {
        $teams = config('serie_a_teams.teams', []);
        $out = [];
        foreach ($teams as $t) {
            $names = $t['names'] ?? [];
            $feeds = $t['feeds'] ?? [];
            $out[] = [
                'slug' => $t['slug'],
                'name' => $names[0] ?? $t['slug'],
                'feeds' => $feeds,
            ];
        }
        return $out;
    }

    /**
     * Retorna todos os times com seus feeds (para o sync). Cada item: slug, displayName, feeds = [{portal, url}]
     */
    public static function teamsWithFeeds(): array
    {
        return self::teamsList();
    }

    private static function normalize(string $s): string
    {
        $s = mb_strtolower(trim($s), 'UTF-8');
        $map = ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c'];
        foreach ($map as $from => $to) {
            $s = str_replace($from, $to, $s);
        }
        return $s;
    }
}
