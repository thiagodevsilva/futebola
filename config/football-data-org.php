<?php

return [
    'base_url' => env('FOOTBALL_DATA_ORG_BASE_URL', 'https://api.football-data.org/v4'),
    'token' => env('FOOTBALL_DATA_ORG_TOKEN', ''),
    'timeout' => (int) env('FOOTBALL_DATA_ORG_TIMEOUT', 15),
    'cache' => [
        'standings_ttl' => (int) env('FOOTBALL_DATA_ORG_CACHE_STANDINGS_TTL', 3600),
        'matches_ttl' => (int) env('FOOTBALL_DATA_ORG_CACHE_MATCHES_TTL', 1800),
    ],
];
