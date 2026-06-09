<?php

return [
    /*
    | Faixas de classificação por liga (chave = football_data_org_code).
    | Aliases numéricos apontam para o código principal (ex.: external_id API-Football).
    */
    'aliases' => [
        '71' => 'BSA',
    ],

    'leagues' => [
        'BSA' => [
            ['code' => 'libertadores', 'label' => 'Libertadores', 'from' => 1, 'to' => 4, 'color' => 'green'],
            ['code' => 'pre_libertadores', 'label' => 'Pré-Libertadores', 'from' => 5, 'to' => 6, 'color' => 'blue'],
            ['code' => 'sulamericana', 'label' => 'Sul-Americana', 'from' => 7, 'to' => 12, 'color' => 'amber'],
            ['code' => 'rebaixamento', 'label' => 'Rebaixamento', 'from' => 17, 'to' => 20, 'color' => 'red'],
        ],
    ],
];
