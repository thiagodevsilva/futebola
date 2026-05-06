<?php

/**
 * Ingestão alternativa: JSON em URLs que você configurar (ex. endpoints vistos no DevTools dos portais).
 *
 * Formato esperado (flexível — ver PortalFootballPersistService):
 *
 * Tabela: array de objetos com posição/nome do time e números; ou { "standings": [ ... ] }
 *
 * Jogos: array com mandante/visitante e data; ou { "fixtures": [ ... ] }
 *
 * Respeite os termos de uso dos sites. Preferir endpoints públicos que o próprio site já consome.
 *
 * driver `next_data_page`: URL da página HTML (Next.js) — lê #__NEXT_DATA__ e extrai tabela/jogos (ex.: OneFootball).
 * fixtures_page_url: pode listar várias URLs separadas por vírgula (ex.: /jogos e /resultados).
 */

return [

    'enabled' => env('PORTAL_FOOTBALL_ENABLED', false),

    'user_agent' => env('PORTAL_FOOTBALL_USER_AGENT', 'FutebolaBot/1.0 (portal sync; +https://example.com)'),

    'timeout' => (int) env('PORTAL_FOOTBALL_TIMEOUT', 15),

    'fixtures_days_back' => (int) env('PORTAL_FIXTURES_DAYS_BACK', 180),

    'fixtures_days_ahead' => (int) env('PORTAL_FIXTURES_DAYS_AHEAD', 180),

    /*
     * Cada fonte aponta para uma liga existente (football_data_org_code no banco).
     * provider_key: prefixo estável para IDs derivados (evita colisões entre fontes).
     */
    'sources' => [
        [
            'enabled' => env('PORTAL_BSA_ENABLED', false),
            'driver' => env('PORTAL_BSA_DRIVER', 'json'),
            'competition_code' => 'BSA',
            'provider_key' => env('PORTAL_BSA_PROVIDER_KEY', 'portal-bsa-json'),
            'standings_page_url' => env('PORTAL_BSA_STANDINGS_PAGE_URL'),
            'fixtures_page_url' => env('PORTAL_BSA_FIXTURES_PAGE_URL'),
            'standings_url' => env('PORTAL_BSA_STANDINGS_JSON_URL'),
            'fixtures_url' => env('PORTAL_BSA_FIXTURES_JSON_URL'),
            'season' => env('PORTAL_BSA_SEASON'),
        ],
        [
            'enabled' => env('PORTAL_BSB_ENABLED', false),
            'competition_code' => 'BSB',
            'provider_key' => env('PORTAL_BSB_PROVIDER_KEY', 'portal-bsb-json'),
            'standings_url' => env('PORTAL_BSB_STANDINGS_JSON_URL'),
            'fixtures_url' => env('PORTAL_BSB_FIXTURES_JSON_URL'),
            'season' => env('PORTAL_BSB_SEASON'),
        ],
        [
            'enabled' => env('PORTAL_CDB_ENABLED', false),
            'competition_code' => 'CDB',
            'provider_key' => env('PORTAL_CDB_PROVIDER_KEY', 'portal-cdb-json'),
            'standings_url' => env('PORTAL_CDB_STANDINGS_JSON_URL'),
            'fixtures_url' => env('PORTAL_CDB_FIXTURES_JSON_URL'),
            'season' => env('PORTAL_CDB_SEASON'),
        ],
    ],

];
