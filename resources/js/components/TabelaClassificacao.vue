<template>
  <div class="rounded-[var(--radius-box)] border border-[#6E6E6E]/15 overflow-hidden bg-white shadow-[var(--shadow-card)]">
    <div class="px-4 py-3 border-b border-[#6E6E6E]/15 bg-[#BAFF39]/10">
      <h2 class="font-bold text-lg text-neutral-900">
        {{ leagueName || 'Classificação' }}
      </h2>
      <p v-if="season" class="text-sm text-[#6E6E6E]">Temporada {{ season }}</p>
    </div>
    <div class="overflow-x-auto">
      <table class="table text-sm w-full">
        <thead>
          <tr class="bg-neutral-100 text-[#6E6E6E] text-xs uppercase tracking-wide">
            <th class="w-10 text-center">#</th>
            <th>Time</th>
            <th class="text-center w-12 bg-[#BAFF39]/10">P</th>
            <th class="text-center w-10 hidden sm:table-cell">J</th>
            <th class="text-center w-10 hidden md:table-cell">V</th>
            <th class="text-center w-10 hidden md:table-cell">E</th>
            <th class="text-center w-10 hidden md:table-cell">D</th>
            <th class="text-center w-10 hidden lg:table-cell">GP</th>
            <th class="text-center w-10 hidden lg:table-cell">GC</th>
            <th class="text-center w-12">SG</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, i) in standings"
            :key="i"
            class="hover:bg-neutral-50 border-b border-[#6E6E6E]/5 last:border-0"
            :class="zoneClass(row.rank)"
          >
            <td class="text-center font-medium text-[#6E6E6E]">{{ row.rank }}</td>
            <td>
              <div class="flex items-center gap-2 py-1">
                <img
                  v-if="row.team_logo"
                  :src="row.team_logo"
                  :alt="row.team_name"
                  class="w-7 h-7 object-contain flex-shrink-0"
                  loading="lazy"
                  @error="($e) => ($e.target.style.display = 'none')"
                />
                <span
                  v-else
                  class="w-7 h-7 rounded-full bg-[#6E6E6E]/20 flex items-center justify-center flex-shrink-0"
                >
                  <span class="text-xs font-bold text-[#6E6E6E]">{{ (row.team_name || '?')[0] }}</span>
                </span>
                <span class="font-medium text-neutral-900 truncate max-w-[120px] sm:max-w-none">
                  {{ row.team_name }}
                </span>
              </div>
            </td>
            <td class="text-center font-bold bg-[#BAFF39]/10 text-neutral-900">{{ row.points }}</td>
            <td class="text-center hidden sm:table-cell">{{ row.played }}</td>
            <td class="text-center hidden md:table-cell">{{ row.win }}</td>
            <td class="text-center hidden md:table-cell">{{ row.draw }}</td>
            <td class="text-center hidden md:table-cell">{{ row.loss }}</td>
            <td class="text-center hidden lg:table-cell">{{ row.goals_for }}</td>
            <td class="text-center hidden lg:table-cell">{{ row.goals_against }}</td>
            <td class="text-center font-medium" :class="row.goal_diff > 0 ? 'text-green-700' : row.goal_diff < 0 ? 'text-red-600' : ''">
              {{ row.goal_diff > 0 ? '+' : '' }}{{ row.goal_diff }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>
    <div v-if="standings.length" class="px-4 py-2 border-t border-[#6E6E6E]/10 flex flex-wrap gap-x-4 gap-y-1 text-xs text-[#6E6E6E]">
      <span class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-green-500"></span> Libertadores (1–4)
      </span>
      <span class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-blue-500"></span> Pré-Libertadores (5–6)
      </span>
      <span class="flex items-center gap-1.5">
        <span class="w-2 h-2 rounded-full bg-red-500"></span> Rebaixamento (17–20)
      </span>
    </div>
    <p v-if="unavailable" class="p-4 text-center text-[#6E6E6E] text-sm">
      {{ unavailable }}
    </p>
  </div>
</template>

<script setup>
defineProps({
  leagueName: { type: String, default: '' },
  season: { type: [Number, String], default: null },
  standings: { type: Array, default: () => [] },
  unavailable: { type: String, default: '' },
});

function zoneClass(rank) {
  const r = Number(rank);
  if (r >= 1 && r <= 4) return 'border-l-4 border-l-green-500';
  if (r >= 5 && r <= 6) return 'border-l-4 border-l-blue-500';
  if (r >= 17 && r <= 20) return 'border-l-4 border-l-red-500';
  return 'border-l-4 border-l-transparent';
}
</script>
