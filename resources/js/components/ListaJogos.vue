<template>
  <div :class="bare ? '' : 'rounded-[var(--radius-box)] border border-[#6E6E6E]/15 overflow-hidden bg-white shadow-[var(--shadow-card)]'">
    <div
      v-if="!hideTabs && leagues.length > 1"
      class="px-4 py-3 border-b border-[#6E6E6E]/15 bg-neutral-50 flex flex-wrap gap-2"
    >
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
    <div :class="compact ? 'p-2' : 'p-4'">
      <template v-for="tab in leagues" :key="tab.league.id">
        <div v-show="activeLeagueId === tab.league.id" :class="compact ? 'space-y-1' : 'space-y-4'">
          <div
            v-for="f in tab.fixtures"
            :key="f.id"
            class="flex flex-col items-center border-b border-[#6E6E6E]/10 last:border-0"
            :class="compact ? 'gap-1 py-2' : 'gap-2 py-4'"
          >
            <div class="flex items-center justify-center gap-2 w-full" :class="compact ? 'gap-2' : 'gap-4'">
              <div class="flex-1 flex justify-end min-w-0" :title="f.home_team_name">
                <img
                  v-if="f.home_team_logo"
                  :src="f.home_team_logo"
                  :alt="f.home_team_name"
                  :class="logoClass"
                  loading="lazy"
                  @error="($e) => ($e.target.style.display = 'none')"
                />
                <span
                  v-else
                  :class="[logoClass, 'rounded-full bg-[#6E6E6E]/20 flex items-center justify-center font-bold text-[#6E6E6E] flex-shrink-0']"
                >{{ (f.home_team_name || '?')[0] }}</span>
              </div>
              <span
                class="flex-shrink-0 rounded bg-neutral-100 font-mono font-medium"
                :class="compact ? 'px-2 py-0.5 text-xs' : 'px-3 py-1 text-sm'"
              >
                {{ f.home_goals != null && f.away_goals != null ? `${f.home_goals} x ${f.away_goals}` : 'x' }}
              </span>
              <div class="flex-1 flex justify-start min-w-0" :title="f.away_team_name">
                <img
                  v-if="f.away_team_logo"
                  :src="f.away_team_logo"
                  :alt="f.away_team_name"
                  :class="logoClass"
                  loading="lazy"
                  @error="($e) => ($e.target.style.display = 'none')"
                />
                <span
                  v-else
                  :class="[logoClass, 'rounded-full bg-[#6E6E6E]/20 flex items-center justify-center font-bold text-[#6E6E6E] flex-shrink-0']"
                >{{ (f.away_team_name || '?')[0] }}</span>
              </div>
            </div>
            <div class="text-center text-[#6E6E6E]" :class="compact ? 'text-xs' : 'text-sm'">
              <span>{{ formatDate(f.date) }}</span>
              <template v-if="f.venue && !compact">
                <span class="mx-1">·</span>
                <span>{{ f.venue }}</span>
              </template>
            </div>
          </div>
          <p v-if="tab.fixtures.length === 0" class="text-sm text-[#6E6E6E] py-2 text-center">
            Nenhum jogo disponível no momento.
          </p>
        </div>
      </template>
      <p v-if="unavailable" class="text-sm text-[#6E6E6E] py-2 text-center">{{ unavailable }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
  grouped: { type: Array, default: () => [] },
  unavailable: { type: String, default: '' },
  compact: { type: Boolean, default: false },
  hideTabs: { type: Boolean, default: false },
  bare: { type: Boolean, default: false },
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

const logoClass = computed(() =>
  props.compact
    ? 'w-8 h-8 object-contain flex-shrink-0'
    : 'w-14 h-14 object-contain flex-shrink-0'
);

function formatDate(value) {
  if (!value) return '';
  const d = new Date(value);
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(d);
}
</script>
