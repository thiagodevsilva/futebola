<?php

return [
    'base_url' => env('API_FUTEBOL_BASE_URL', 'https://api.api-futebol.com.br/v1'),
    'key' => env('API_FUTEBOL_KEY', ''),
    'timeout' => (int) env('API_FUTEBOL_TIMEOUT', 15),
    'cache' => [
        'tabela_ttl' => (int) env('API_FUTEBOL_CACHE_TABELA_TTL', 3600),
        'partidas_ttl' => (int) env('API_FUTEBOL_CACHE_PARTIDAS_TTL', 1800),
    ],
    'campeonatos' => [
        'serie_a' => (int) env('API_FUTEBOL_CAMPEONATO_SERIE_A', 10),
        'serie_b' => (int) env('API_FUTEBOL_CAMPEONATO_SERIE_B', 11),
        'copa_brasil' => (int) env('API_FUTEBOL_CAMPEONATO_COPA_BRASIL', 4),
    ],
];
