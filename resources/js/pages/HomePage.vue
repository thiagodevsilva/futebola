<template>
  <div class="space-y-8">
    <section>
      <h1 class="text-2xl font-bold mb-4 text-neutral-900">Últimas notícias</h1>
      <div class="mb-4">
        <span class="text-sm font-medium text-neutral-700 mr-2">Filtrar por time (Série A):</span>
        <div class="flex flex-wrap gap-2 mt-2 items-center">
          <RouterLink to="/news" class="rounded-full border-2 border-[#6E6E6E]/30 hover:border-[#BAFF39] p-1.5 transition-colors" title="Todos">
            <span class="text-xs font-medium text-[#6E6E6E]">Todos</span>
          </RouterLink>
          <RouterLink
            v-for="team in serieATeams"
            :key="team.topic"
            :to="`/news?topic=${team.topic}`"
            class="rounded-full border-2 border-transparent hover:border-[#BAFF39] p-0.5 transition-colors flex items-center justify-center w-9 h-9 shrink-0"
            :title="team.team_name"
          >
            <img
              v-if="team.team_logo"
              :src="team.team_logo"
              :alt="team.team_name"
              class="w-full h-full object-contain"
            />
            <span v-else class="w-full h-full rounded-full bg-[#6E6E6E]/20 flex items-center justify-center text-xs font-bold text-[#6E6E6E]">{{ (team.team_name || '?')[0] }}</span>
          </RouterLink>
        </div>
        <p v-if="!serieATeams.length" class="text-xs text-[#6E6E6E] mt-1">Rode o sync da API (standings) e depois futebola:sync-feeds-from-standings.</p>
      </div>
      <div v-if="loadingNews" class="flex justify-center py-8">
        <span class="loading loading-spinner loading-lg text-[#BAFF39]"></span>
      </div>
      <div v-else-if="homeNews.length" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <CardNoticia v-for="item in homeNews" :key="item.id" :news="item" />
      </div>
      <div v-else class="rounded-lg border border-[#6E6E6E]/15 p-8 text-center text-[#6E6E6E]">
        Nenhuma notícia no momento.
      </div>
      <p class="text-xs text-[#6E6E6E] mt-2">
        1ª e 3ª: São Paulo (mais recentes). 2ª: notícia mais atual (qualquer time).
      </p>
      <div class="mt-4 text-center">
        <RouterLink to="/news" class="btn btn-sm bg-[#BAFF39] text-neutral-900 border-0 hover:opacity-90">
          Ver todas as notícias
        </RouterLink>
      </div>
    </section>
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div>
        <h2 class="text-xl font-bold mb-4 text-neutral-900">Tabela Brasileirão Série A</h2>
        <TabelaClassificacao
          :league-name="standingsLeagueName"
          :season="standingsSeason"
          :standings="standingsRows"
          :unavailable="standingsUnavailable"
        />
      </div>
      <div>
        <h2 class="text-xl font-bold mb-1 text-neutral-900">Últimos resultados</h2>
        <p v-if="resultsRoundLabel" class="text-sm font-medium text-neutral-600 mb-3">{{ resultsRoundLabel }}</p>
        <ListaJogos :grouped="resultsGrouped" :unavailable="resultsUnavailable" />
      </div>
      <div>
        <h2 class="text-xl font-bold mb-1 text-neutral-900">Próximos jogos</h2>
        <p v-if="fixturesRoundLabel" class="text-sm font-medium text-neutral-600 mb-3">{{ fixturesRoundLabel }}</p>
        <ListaJogos :grouped="fixturesGrouped" :unavailable="fixturesUnavailable" />
      </div>
    </section>
    <section>
      <h2 class="text-xl font-bold mb-4 text-neutral-900">Fontes</h2>
      <div class="flex flex-wrap gap-2">
        <RouterLink
          v-for="s in sources"
          :key="s.id"
          :to="`/news?source_id=${s.id}`"
          class="badge badge-outline hover:bg-[#BAFF39]"
        >
          {{ s.name }}
        </RouterLink>
      </div>
      <RouterLink to="/sources" class="link text-sm mt-2 inline-block" style="color: #0d9488;">Ver todas →</RouterLink>
    </section>
    <section class="rounded-lg border border-[#6E6E6E]/15 bg-neutral-50 p-4">
      <details class="group">
        <summary class="cursor-pointer list-none font-semibold text-neutral-800 flex items-center gap-2">
          <span class="transition group-open:rotate-90">▶</span>
          O que inclui o plano gratuito (football-data.org)?
        </summary>
        <ul class="mt-3 text-sm text-[#6E6E6E] space-y-1 pl-5">
          <li>10 chamadas/minuto · Tabelas · Jogos e placares (com atraso, não ao vivo)</li>
          <li><strong>12 competições:</strong> Brasileirão Série A e B, Copa do Brasil, Premier League, La Liga, Serie A (Itália), Bundesliga, Ligue 1, Eredivisie, Primeira Liga, Champions League, Copa do Mundo, Euro</li>
          <li>Sem placar ao vivo nem dados extras (escalações, cartões) no free</li>
        </ul>
      </details>
    </section>
    <section class="rounded-lg border border-[#6E6E6E]/15 bg-neutral-50 p-4">
      <details class="group">
        <summary class="cursor-pointer list-none font-semibold text-neutral-800 flex items-center gap-2">
          <span class="transition group-open:rotate-90">▶</span>
          Como buscar imagens das notícias?
        </summary>
        <div class="mt-3 text-sm text-[#6E6E6E] space-y-2 pl-5">
          <p>Os feeds RSS nem sempre trazem imagem. As fotos vêm da <strong>página da notícia</strong> (meta <code>og:image</code>).</p>
          <p>Depois de rodar <code>futebola:fetch-rss</code>, execute no Docker:</p>
          <pre class="bg-neutral-200 rounded p-2 text-xs overflow-x-auto">docker compose -f docker-compose.dev.yml exec app php artisan futebola:backfill-news-images</pre>
          <p>O comando acessa o link de cada notícia sem imagem e preenche com o <code>og:image</code>. Use <code>--limit=50</code> para processar no máximo 50 (evita muitas requisições).</p>
        </div>
      </details>
    </section>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import CardNoticia from '@/components/CardNoticia.vue';
import TabelaClassificacao from '@/components/TabelaClassificacao.vue';
import ListaJogos from '@/components/ListaJogos.vue';
import { getNews, getStandings, getFixturesHomeRounds, getSources, getSerieATeams } from '@/api';

const homeNews = ref([]);
const loadingNews = ref(true);
const serieATeams = ref([]);
const standingsLeagueName = ref('');
const standingsSeason = ref(null);
const standingsRows = ref([]);
const standingsUnavailable = ref('');
const fixturesGrouped = ref([]);
const fixturesUnavailable = ref('');
const resultsGrouped = ref([]);
const resultsUnavailable = ref('');
const resultsRoundLabel = ref('');
const fixturesRoundLabel = ref('');
const sources = ref([]);

onMounted(async () => {
  try {
    const [saoPauloRes, latestRes, standingsRes, homeRoundsRes, sourcesRes, teamsRes] = await Promise.all([
      getNews({ topic: 'sao-paulo', per_page: 2 }),
      getNews({ per_page: 5 }),
      getStandings(),
      getFixturesHomeRounds({ external_league_id: 71 }),
      getSources(),
      getSerieATeams(),
    ]);
    const saoPaulo = saoPauloRes.data?.data ?? [];
    const latestList = latestRes.data?.data ?? [];
    const first = saoPaulo[0] ?? latestList[0];
    const third = saoPaulo[1] ?? latestList.find((n) => n.id !== first?.id) ?? latestList[0];
    const middle = latestList.find((n) => n.id !== first?.id && n.id !== third?.id) ?? latestList[0];
    homeNews.value = [first, middle, third].filter(Boolean);
    sources.value = sourcesRes.data.data || [];
    serieATeams.value = teamsRes.data?.data ?? [];
    const standingGroups = standingsRes.data.data || [];
    if (standingGroups.length && standingGroups[0].standings) {
      const first = standingGroups[0];
      standingsLeagueName.value = first.league?.name || 'Brasileirão Série A';
      standingsSeason.value = standingsRes.data.meta?.season ?? new Date().getFullYear();
      standingsRows.value = first.standings;
    } else {
      standingsUnavailable.value = 'Tabela indisponível. Configure FOOTBALL_DATA_ORG_TOKEN ou API_FOOTBALL_KEY e rode o sync.';
    }
    const hr = homeRoundsRes.data?.data;
    const roundsMeta = homeRoundsRes.data?.meta;
    const leagueBlock = hr?.league;
    if (leagueBlock?.id) {
      resultsGrouped.value = [{ league: leagueBlock, fixtures: hr.last_round?.fixtures ?? [] }];
      fixturesGrouped.value = [{ league: leagueBlock, fixtures: hr.next_round?.fixtures ?? [] }];
      resultsRoundLabel.value = hr.last_round?.label ?? '';
      fixturesRoundLabel.value = hr.next_round?.label ?? '';
      if (!(hr.last_round?.fixtures?.length > 0)) {
        resultsUnavailable.value = roundsMeta?.used_round_metadata === false
          ? 'Nenhum resultado nos últimos 7 dias. Rode o sync para atualizar.'
          : 'Nenhum resultado disponível para a rodada. Rode o sync do portal ou da API.';
      }
      if (!(hr.next_round?.fixtures?.length > 0)) {
        fixturesUnavailable.value = roundsMeta?.used_round_metadata === false
          ? 'Jogos indisponíveis no momento.'
          : 'Nenhum jogo agendado na próxima rodada no momento.';
      }
    } else {
      fixturesUnavailable.value = 'Jogos indisponíveis no momento.';
      resultsUnavailable.value = 'Resultados indisponíveis no momento.';
    }
  } catch {
    standingsUnavailable.value = 'Erro ao carregar tabela.';
    fixturesUnavailable.value = 'Erro ao carregar jogos.';
    resultsUnavailable.value = 'Erro ao carregar resultados.';
  } finally {
    loadingNews.value = false;
  }
});
</script>
