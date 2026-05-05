<?php

namespace Database\Seeders;

use App\Models\League;
use Illuminate\Database\Seeder;

class LeagueSeeder extends Seeder
{
    public function run(): void
    {
        $leagues = [
            ['external_id' => 71, 'api_futebol_id' => 10, 'football_data_org_code' => 'BSA', 'name' => 'Brasileirão Série A', 'country' => 'Brazil', 'type' => 'league'],
            ['external_id' => 2, 'api_futebol_id' => 4, 'football_data_org_code' => 'CDB', 'name' => 'Copa do Brasil', 'country' => 'Brazil', 'type' => 'cup'],
            ['external_id' => 11, 'api_futebol_id' => 11, 'football_data_org_code' => 'BSB', 'name' => 'Brasileirão Série B', 'country' => 'Brazil', 'type' => 'league'],
            ['external_id' => 13, 'name' => 'Copa Libertadores', 'country' => 'World', 'type' => 'cup'],
            ['external_id' => 15, 'name' => 'Copa Sul-Americana', 'country' => 'World', 'type' => 'cup'],
        ];

        foreach ($leagues as $data) {
            League::updateOrCreate(
                ['external_id' => $data['external_id']],
                array_merge($data, ['active' => true])
            );
        }
    }
}
