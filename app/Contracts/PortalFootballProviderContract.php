<?php

namespace App\Contracts;

use Carbon\Carbon;

/**
 * Fonte externa de tabela/jogos alimentada por URLs (JSON) ou parsers específicos.
 * Implementações devem devolver arrays já normalizados para PortalFootballPersistService.
 */
interface PortalFootballProviderContract
{
    /** Código da competição no banco (coluna leagues.football_data_org_code), ex.: BSA */
    public function competitionCode(): string;

    /** Identificador estável para logs e IDs derivados (ex.: globo-bsa-json). */
    public function providerKey(): string;

    /**
     * @return list<array<string, mixed>> linhas normalizadas (ver PortalFootballPersistService::normalizeStandingRow)
     */
    public function fetchStandings(): array;

    /**
     * @return list<array<string, mixed>> jogos normalizados (ver PortalFootballPersistService::normalizeFixtureRow)
     */
    public function fetchFixtures(Carbon $from, Carbon $to): array;
}
