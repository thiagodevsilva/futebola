<template>
  <div class="rounded-[var(--radius-box)] border border-[#6E6E6E]/15 overflow-hidden bg-white shadow-[var(--shadow-card)]">
    <div class="px-4 py-3 border-b border-[#6E6E6E]/15 bg-neutral-50 flex flex-wrap gap-2">
      <button
        v-for="tab in leagues"
        :key="tab.league.id"
        type="button"
        class="btn btn-sm rounded-[var(--radius-btn)]"
        :class="activeLeagueId === tab.league.id ? 'bg-[#BAFF39] text-neutral-900 border-0' : 'btn-ghost'"
        @click="activeLeagueId = tab.league.id"
      >
        {{ tab.league.name }}
      </button>
    </div>
    <div class="p-4">
      <template v-for="tab in leagues" :key="tab.league.id">
        <div v-show="activeLeagueId === tab.league.id" class="space-y-4">
          <div
            v-for="f in tab.fixtures"
            :key="f.id"
            class="flex flex-col items-center gap-2 py-4 border-b border-[#6E6E6E]/10 last:border-0"
          >
            <div class="flex items-center justify-center gap-4 w-full">
              <div class="flex-1 flex justify-end" :title="f.home_team_name">
                <img
                  v-if="f.home_team_logo"
                  :src="f.home_team_logo"
                  :alt="f.home_team_name"
                  class="w-14 h-14 object-contain flex-shrink-0"
                  loading="lazy"
                  @error="($e) => ($e.target.style.display = 'none')"
                />
                <span v-else class="w-14 h-14 rounded-full bg-[#6E6E6E]/20 flex items-center justify-center text-lg font-bold text-[#6E6E6E] flex-shrink-0">{{ (f.home_team_name || '?')[0] }}</span>
              </div>
              <span class="flex-shrink-0 px-3 py-1 rounded bg-neutral-100 text-sm font-mono font-medium">
                {{ f.home_goals != null && f.away_goals != null ? `${f.home_goals} x ${f.away_goals}` : 'x' }}
              </span>
              <div class="flex-1 flex justify-start" :title="f.away_team_name">
                <img
                  v-if="f.away_team_logo"
                  :src="f.away_team_logo"
                  :alt="f.away_team_name"
                  class="w-14 h-14 object-contain flex-shrink-0"
                  loading="lazy"
                  @error="($e) => ($e.target.style.display = 'none')"
                />
                <span v-else class="w-14 h-14 rounded-full bg-[#6E6E6E]/20 flex items-center justify-center text-lg font-bold text-[#6E6E6E] flex-shrink-0">{{ (f.away_team_name || '?')[0] }}</span>
              </div>
            </div>
            <div class="text-center text-sm text-[#6E6E6E]">
              <span>{{ formatDate(f.date) }}</span>
              <template v-if="f.venue">
                <span class="mx-1">·</span>
                <span>{{ f.venue }}</span>
              </template>
            </div>
          </div>
          <p v-if="tab.fixtures.length === 0" class="text-sm text-[#6E6E6E]">
            Nenhum jogo nos próximos dias ou indisponível no momento.
          </p>
        </div>
      </template>
      <p v-if="unavailable" class="text-sm text-[#6E6E6E]">{{ unavailable }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  grouped: { type: Array, default: () => [] }, // [{ league: {}, fixtures: [] }]
  unavailable: { type: String, default: '' },
});

const activeLeagueId = ref(props.grouped[0]?.league?.id ?? null);
watch(() => props.grouped, (g) => {
  const first = g[0]?.league?.id;
  if (first) activeLeagueId.value = first;
}, { immediate: true });

const leagues = computed(() => {
  return props.grouped.map((g) => ({
    league: g.league || { id: null, name: 'Campeonato' },
    fixtures: g.fixtures || [],
  })).filter((t) => t.league.id);
});

function formatDate(value) {
  if (!value) return '';
  const d = new Date(value);
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(d);
}
</script>
