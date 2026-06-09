<?php

namespace App\Services\Football;

use App\Models\League;

class StandingZoneResolver
{
    /**
     * @return array<int, array{code: string, label: string, from: int, to: int, color: string}>
     */
    public function zonesForLeague(League $league): array
    {
        $key = $this->resolveConfigKey($league);
        if ($key === null) {
            return [];
        }

        return config("league_zones.leagues.{$key}", []);
    }

    /**
     * @return array{code: string, label: string, color: string}|null
     */
    public function zoneForRank(League $league, int $rank): ?array
    {
        foreach ($this->zonesForLeague($league) as $zone) {
            if ($rank >= $zone['from'] && $rank <= $zone['to']) {
                return [
                    'code' => $zone['code'],
                    'label' => $zone['label'],
                    'color' => $zone['color'],
                ];
            }
        }

        return null;
    }

    private function resolveConfigKey(League $league): ?string
    {
        $code = $league->football_data_org_code;
        if ($code && config("league_zones.leagues.{$code}")) {
            return $code;
        }

        $externalId = $league->external_id;
        if ($externalId !== null) {
            $alias = config('league_zones.aliases.'.(string) $externalId);
            if ($alias && config("league_zones.leagues.{$alias}")) {
                return $alias;
            }
        }

        return null;
    }
}
