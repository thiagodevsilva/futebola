<template>
  <div class="rounded-[var(--radius-box)] border border-[#6E6E6E]/15 bg-white shadow-[var(--shadow-card)]">
    <div class="px-4 py-3 border-b border-[#6E6E6E]/15 bg-[#BAFF39]/10">
      <h2 class="font-bold text-lg text-neutral-900">
        {{ leagueName || 'Classificação' }}
      </h2>
      <p v-if="season" class="text-sm text-[#6E6E6E]">Temporada {{ season }}</p>
    </div>
    <div
      v-if="zones.length"
      class="px-4 py-2 border-b border-[#6E6E6E]/10 flex flex-wrap gap-x-3 gap-y-1.5 text-xs text-[#6E6E6E] bg-neutral-50"
    >
      <span
        v-for="z in zones"
        :key="z.code"
        class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 bg-white border border-[#6E6E6E]/10"
      >
        <span class="w-2.5 h-2.5 rounded-full shrink-0" :class="zoneDotClass(z.color)"></span>
        {{ z.label }} ({{ z.from }}–{{ z.to }})
      </span>
    </div>
    <div class="overflow-x-auto">
      <table class="table text-sm w-full border-collapse">
        <thead>
          <tr class="bg-neutral-100 text-[#6E6E6E] text-xs uppercase tracking-wide">
            <th class="w-10 text-center pl-3">#</th>
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
            class="border-b border-[#6E6E6E]/5 last:border-0"
            :class="zoneRowClass(row.zone)"
          >
            <td class="text-center font-medium text-[#6E6E6E] relative pl-3">
              <span
                v-if="row.zone"
                class="absolute left-0 top-1 bottom-1 w-1 rounded-full"
                :class="zoneDotClass(row.zone.color)"
              ></span>
              {{ row.rank }}
            </td>
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
  zones: { type: Array, default: () => [] },
  unavailable: { type: String, default: '' },
});

const colorMap = {
  green: { dot: 'bg-green-500', row: 'bg-green-500/5 hover:bg-green-500/10' },
  blue: { dot: 'bg-blue-500', row: 'bg-blue-500/5 hover:bg-blue-500/10' },
  amber: { dot: 'bg-amber-500', row: 'bg-amber-500/5 hover:bg-amber-500/10' },
  red: { dot: 'bg-red-500', row: 'bg-red-500/5 hover:bg-red-500/10' },
};

function zoneRowClass(zone) {
  if (!zone?.color) return 'hover:bg-neutral-50';
  return colorMap[zone.color]?.row ?? 'hover:bg-neutral-50';
}

function zoneDotClass(color) {
  return colorMap[color]?.dot ?? 'bg-neutral-400';
}
</script>
