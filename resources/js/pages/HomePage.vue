<template>
  <div class="space-y-8">
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2">
        <TabelaClassificacao
          :league-name="standingsLeagueName"
          :season="standingsSeason"
          :standings="standingsRows"
          :unavailable="standingsUnavailable"
        />
      </div>
      <aside class="space-y-4">
        <div class="rounded-[var(--radius-box)] border border-[#6E6E6E]/15 bg-white shadow-[var(--shadow-card)] overflow-hidden">
          <div class="px-4 py-3 border-b border-[#6E6E6E]/15 bg-neutral-50 flex items-center justify-between">
            <h2 class="font-bold text-neutral-900">Últimas notícias</h2>
            <RouterLink to="/news" class="text-xs font-medium hover:underline" style="color: #0d9488;">
              Ver todas →
            </RouterLink>
          </div>
          <div class="p-2">
            <div v-if="loadingNews" class="flex justify-center py-6">
              <span class="loading loading-spinner loading-md text-[#BAFF39]"></span>
            </div>
            <div v-else-if="homeNews.length" class="divide-y divide-[#6E6E6E]/10">
              <CardNoticiaCompact v-for="item in homeNews" :key="item.id" :news="item" />
            </div>
            <p v-else class="text-sm text-[#6E6E6E] text-center py-4">
              Nenhuma notícia no momento.
            </p>
          </div>
        </div>

        <div class="rounded-[var(--radius-box)] border border-[#6E6E6E]/15 bg-white shadow-[var(--shadow-card)] overflow-hidden">
          <div class="px-4 py-3 border-b border-[#6E6E6E]/15 bg-neutral-50">
            <h2 class="font-bold text-neutral-900">Últimos resultados</h2>
            <p v-if="resultsRoundLabel" class="text-xs text-[#6E6E6E] mt-0.5">{{ resultsRoundLabel }}</p>
          </div>
          <ListaJogos
            :grouped="resultsGrouped"
            :unavailable="resultsUnavailable"
            compact
            hide-tabs
            bare
          />
        </div>

        <div class="rounded-[var(--radius-box)] border border-[#6E6E6E]/15 bg-white shadow-[var(--shadow-card)] overflow-hidden">
          <div class="px-4 py-3 border-b border-[#6E6E6E]/15 bg-neutral-50">
            <h2 class="font-bold text-neutral-900">Próximos jogos</h2>
            <p v-if="fixturesRoundLabel" class="text-xs text-[#6E6E6E] mt-0.5">{{ fixturesRoundLabel }}</p>
          </div>
          <ListaJogos
            :grouped="fixturesGrouped"
            :unavailable="fixturesUnavailable"
            compact
            hide-tabs
            bare
          />
        </div>
      </aside>
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
import CardNoticiaCompact from '@/components/CardNoticiaCompact.vue';
import TabelaClassificacao from '@/components/TabelaClassificacao.vue';
import ListaJogos from '@/components/ListaJogos.vue';
import { getNews, getStandings, getFixturesHomeRounds, getSources } from '@/api';

const homeNews = ref([]);
const loadingNews = ref(true);
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
    const [newsRes, standingsRes, homeRoundsRes, sourcesRes] = await Promise.all([
      getNews({ per_page: 3 }),
      getStandings(),
      getFixturesHomeRounds({ external_league_id: 71, limit: 3 }),
      getSources(),
    ]);
    homeNews.value = newsRes.data?.data ?? [];
    sources.value = sourcesRes.data.data || [];
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
    const leagueBlock = hr?.league;
    if (leagueBlock?.id) {
      resultsGrouped.value = [{ league: leagueBlock, fixtures: hr.last_round?.fixtures ?? [] }];
      fixturesGrouped.value = [{ league: leagueBlock, fixtures: hr.next_round?.fixtures ?? [] }];
      resultsRoundLabel.value = hr.last_round?.label ?? '';
      fixturesRoundLabel.value = hr.next_round?.label ?? '';
      if (!(hr.last_round?.fixtures?.length > 0)) {
        resultsUnavailable.value = 'Nenhum resultado disponível. Rode o sync para atualizar.';
      }
      if (!(hr.next_round?.fixtures?.length > 0)) {
        fixturesUnavailable.value = 'Nenhum jogo agendado no momento.';
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
