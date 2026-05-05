<?php

return [
    'base_url' => env('API_FOOTBALL_BASE_URL', 'https://v3.football.api-sports.io'),
    'key' => env('API_FOOTBALL_KEY', ''),
    'timeout' => (int) env('API_FOOTBALL_TIMEOUT', 10),
    // Plano grátis da API só libera temporadas 2022–2024; use 2024 até ter plano pago
    'default_season' => (int) env('API_FOOTBALL_DEFAULT_SEASON', 2024),
    'cache' => [
        'standings_ttl' => (int) env('API_FOOTBALL_CACHE_STANDINGS_TTL', 3600), // 1 hora
        'fixtures_ttl' => (int) env('API_FOOTBALL_CACHE_FIXTURES_TTL', 1800),  // 30 min
    ],
    'leagues' => [
        'serie_a' => (int) env('API_FOOTBALL_LEAGUE_SERIE_A', 71),
        'copa_brasil' => (int) env('API_FOOTBALL_LEAGUE_COPA_BRASIL', 2),
        'libertadores' => (int) env('API_FOOTBALL_LEAGUE_LIBERTADORES', 13),
        'sulamericana' => (int) env('API_FOOTBALL_LEAGUE_SULAMERICANA', 15),
    ],
];
