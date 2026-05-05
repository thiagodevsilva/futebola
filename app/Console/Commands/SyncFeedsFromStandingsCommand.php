<?php

namespace App\Console\Commands;

use App\Models\Feed;
use App\Models\League;
use App\Models\Standing;
use App\Services\SerieATeamsConfig;
use Illuminate\Console\Command;

class SyncFeedsFromStandingsCommand extends Command
{
    protected $signature = 'futebola:sync-feeds-from-standings';
    protected $description = 'Cria/atualiza feeds RSS para os times em config/serie_a_teams.php (vários portais por time). Rode após o sync da API.';

    public function handle(): int
    {
        $league = League::where('football_data_org_code', 'BSA')->first();
        if (!$league) {
            $this->warn('Liga Brasileirão Série A (BSA) não encontrada. Rode o seed de ligas e o sync.');
            return self::FAILURE;
        }

        $season = (int) now()->year;
        $standings = Standing::where('league_id', $league->id)->where('season', $season)->orderBy('rank')->get();
        $configTeams = SerieATeamsConfig::teamsWithFeeds();
        $topics = [];
        $created = 0;
        $updated = 0;
        $geBase = rtrim(config('serie_a_teams.ge_rss_base', 'https://ge.globo.com/rss/futebol/times/'), '/');

        foreach ($configTeams as $t) {
            $topic = $t['slug'];
            $displayName = $t['name'];
            if ($standings->isNotEmpty()) {
                foreach ($standings as $s) {
                    if (SerieATeamsConfig::slugForTeamName($s->team_name) === $topic) {
                        $displayName = $s->team_name;
                        break;
                    }
                }
            }

            $feedsConfig = $t['feeds'] ?? [];
            if (empty($feedsConfig)) {
                $feedsConfig = [['portal' => 'GE', 'url' => $geBase . '/' . $topic . '/']];
            }

            foreach ($feedsConfig as $f) {
                $url = $f['url'] ?? '';
                $portal = $f['portal'] ?? 'RSS';
                if ($url === '') {
                    continue;
                }
                $topics[] = $topic;
                $name = $portal . ' ' . $displayName;
                $feed = Feed::firstOrNew(['url' => $url]);
                $feed->name = $name;
                $feed->url = $url;
                $feed->category = 'futebol';
                $feed->topic = $topic;
                $feed->active = true;
                $feed->priority = 8;
                $feed->language = 'pt';
                if (!$feed->exists) {
                    $feed->save();
                    $created++;
                } else {
                    $feed->save();
                    $updated++;
                }
            }
        }

        Feed::where(function ($q) use ($topics) {
            $q->whereNotIn('topic', $topics)->orWhereNull('topic')->orWhere('topic', '');
        })->update(['active' => false]);

        $this->info("Feeds (vários portais): {$created} criados, {$updated} atualizados. Topics: " . implode(', ', array_unique($topics)));
        return self::SUCCESS;
    }
}
