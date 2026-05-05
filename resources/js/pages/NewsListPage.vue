<template>
  <div>
    <h1 class="text-2xl font-bold mb-2 text-neutral-900">Notícias</h1>
    <div class="mb-6">
      <span class="text-sm font-medium text-neutral-700 mr-2">Time (Série A):</span>
      <div class="flex flex-wrap items-center gap-2 mt-2">
        <button
          type="button"
          class="rounded-full border-2 transition-colors p-1.5 text-xs font-medium"
          :class="!selectedTopic ? 'border-[#BAFF39] bg-[#BAFF39]/20 text-neutral-900' : 'border-[#6E6E6E]/30 hover:border-[#BAFF39] text-[#6E6E6E]'"
          @click="setTopic('')"
        >
          Todos
        </button>
        <button
          v-for="team in serieATeams"
          :key="team.topic"
          type="button"
          class="rounded-full border-2 transition-colors p-0.5 flex items-center justify-center w-9 h-9 shrink-0"
          :class="selectedTopic === team.topic ? 'border-[#BAFF39] ring-2 ring-[#BAFF39]/50' : 'border-transparent hover:border-[#BAFF39]'"
          :title="team.team_name"
          @click="setTopic(team.topic)"
        >
          <img
            v-if="team.team_logo"
            :src="team.team_logo"
            :alt="team.team_name"
            class="w-full h-full object-contain rounded-full"
          />
          <span v-else class="w-full h-full rounded-full bg-[#6E6E6E]/20 flex items-center justify-center text-xs font-bold text-[#6E6E6E]">{{ (team.team_name || '?')[0] }}</span>
        </button>
      </div>
    </div>
    <div v-if="loading" class="flex justify-center py-12">
      <span class="loading loading-spinner loading-lg text-[#BAFF39]"></span>
    </div>
    <div v-else>
      <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <CardNoticia v-for="item in items" :key="item.id" :news="item" />
      </div>
      <div v-if="items.length === 0" class="rounded-lg border p-8 text-center text-[#6E6E6E]">
        Nenhuma notícia encontrada.
      </div>
      <div v-if="meta.last_page > 1" class="flex justify-center gap-2 mt-6">
        <button
          class="btn btn-sm"
          :class="meta.current_page === 1 ? 'btn-disabled' : 'bg-[#BAFF39] border-0'"
          :disabled="meta.current_page === 1"
          @click="loadPage(meta.current_page - 1)"
        >
          Anterior
        </button>
        <span class="flex items-center px-4 text-sm text-[#6E6E6E]">Página {{ meta.current_page }} de {{ meta.last_page }}</span>
        <button
          class="btn btn-sm"
          :class="meta.current_page === meta.last_page ? 'btn-disabled' : 'bg-[#BAFF39] border-0'"
          :disabled="meta.current_page === meta.last_page"
          @click="loadPage(meta.current_page + 1)"
        >
          Próxima
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import CardNoticia from '@/components/CardNoticia.vue';
import { getNews, getSerieATeams } from '@/api';

const route = useRoute();
const router = useRouter();
const items = ref([]);
const meta = ref({ current_page: 1, last_page: 1, per_page: 15, total: 0 });
const loading = ref(true);
const serieATeams = ref([]);
const selectedTopic = ref('');

async function loadFilters() {
  try {
    const res = await getSerieATeams();
    serieATeams.value = res.data?.data ?? [];
  } catch (_) {
    serieATeams.value = [];
  }
}

function setTopic(topic) {
  selectedTopic.value = topic;
  const query = { ...route.query };
  if (topic) query.topic = topic;
  else delete query.topic;
  router.replace({ query });
}

async function fetchList(page) {
  loading.value = true;
  try {
    const params = { per_page: 15, page: page || 1 };
    if (route.query.source_id) params.source_id = route.query.source_id;
    const topic = route.query.topic;
    if (topic != null && String(topic).trim() !== '') params.topic = String(topic).trim();
    const res = await getNews(params);
    items.value = res.data.data || [];
    meta.value = res.data.meta || meta.value;
  } finally {
    loading.value = false;
  }
}

function loadPage(p) {
  fetchList(p);
}

onMounted(async () => {
  await loadFilters();
  selectedTopic.value = route.query.topic ?? '';
  await fetchList(1);
});
watch(() => route.query, (q) => {
  selectedTopic.value = q.topic ?? '';
  fetchList(1);
}, { deep: true });
</script>
