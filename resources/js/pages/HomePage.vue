<template>
  <div class="space-y-8">
    <section class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <div class="lg:col-span-2">
        <TabelaClassificacao
          :league-name="standingsLeagueName"
          :season="standingsSeason"
          :standings="standingsRows"
          :zones="standingsZones"
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
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { RouterLink } from 'vue-router';
import CardNoticiaCompact from '@/components/CardNoticiaCompact.vue';
import TabelaClassificacao from '@/components/TabelaClassificacao.vue';
import ListaJogos from '@/components/ListaJogos.vue';
import { getNews, getStandings, getFixturesHomeRounds } from '@/api';

const homeNews = ref([]);
const loadingNews = ref(true);
const standingsLeagueName = ref('');
const standingsSeason = ref(null);
const standingsRows = ref([]);
const standingsZones = ref([]);
const standingsUnavailable = ref('');
const fixturesGrouped = ref([]);
const fixturesUnavailable = ref('');
const resultsGrouped = ref([]);
const resultsUnavailable = ref('');
const resultsRoundLabel = ref('');
const fixturesRoundLabel = ref('');

onMounted(async () => {
  try {
    const [newsRes, standingsRes, homeRoundsRes] = await Promise.all([
      getNews({ per_page: 3 }),
      getStandings(),
      getFixturesHomeRounds({ external_league_id: 71, limit: 3 }),
    ]);
    homeNews.value = newsRes.data?.data ?? [];
    const standingGroups = standingsRes.data.data || [];
    if (standingGroups.length && standingGroups[0].standings) {
      const first = standingGroups[0];
      standingsLeagueName.value = first.league?.name || 'Brasileirão Série A';
      standingsSeason.value = standingsRes.data.meta?.season ?? new Date().getFullYear();
      standingsRows.value = first.standings;
      standingsZones.value = first.zones ?? [];
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
