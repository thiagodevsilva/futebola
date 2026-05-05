<template>
  <div class="rounded-[var(--radius-box)] border border-[#6E6E6E]/15 overflow-hidden bg-white shadow-[var(--shadow-card)]">
    <div class="px-4 py-3 border-b border-[#6E6E6E]/15 bg-neutral-50">
      <h2 class="font-bold text-lg" style="color: #1a1a1a;">
        {{ leagueName || 'Classificação' }}
      </h2>
      <p v-if="season" class="text-sm text-[#6E6E6E]">Temporada {{ season }}</p>
    </div>
    <div class="overflow-x-auto">
      <table class="table table-zebra table-pin-rows text-sm w-full">
        <thead>
          <tr class="bg-neutral-100 text-[#6E6E6E]">
            <th class="w-10 text-center">#</th>
            <th>Time</th>
            <th class="text-center w-12">P</th>
            <th class="text-center w-12">J</th>
            <th class="text-center w-12">V</th>
            <th class="text-center w-12">E</th>
            <th class="text-center w-12">D</th>
            <th class="text-center w-12">GP</th>
            <th class="text-center w-12">GC</th>
            <th class="text-center w-12">SG</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="(row, i) in standings"
            :key="i"
            class="hover:bg-neutral-50"
          >
            <td class="text-center font-medium">{{ row.rank }}</td>
            <td>
              <div class="flex items-center justify-center">
                <img
                  v-if="row.team_logo"
                  :src="row.team_logo"
                  :alt="row.team_name"
                  :title="row.team_name"
                  class="w-8 h-8 object-contain flex-shrink-0"
                  loading="lazy"
                  @error="($e) => ($e.target.style.display = 'none')"
                />
                <span
                  v-else
                  class="w-8 h-8 rounded-full bg-[#6E6E6E]/20 flex items-center justify-center flex-shrink-0"
                  :title="row.team_name || 'Escudo indisponível'"
                >
                  <span class="text-xs font-bold text-[#6E6E6E]">{{ (row.team_name || '?')[0] }}</span>
                </span>
              </div>
            </td>
            <td class="text-center font-bold" style="color: #0d9488;">{{ row.points }}</td>
            <td class="text-center">{{ row.played }}</td>
            <td class="text-center">{{ row.win }}</td>
            <td class="text-center">{{ row.draw }}</td>
            <td class="text-center">{{ row.loss }}</td>
            <td class="text-center">{{ row.goals_for }}</td>
            <td class="text-center">{{ row.goals_against }}</td>
            <td class="text-center">{{ row.goal_diff > 0 ? '+' : '' }}{{ row.goal_diff }}</td>
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
  unavailable: { type: String, default: '' },
});
</script>
