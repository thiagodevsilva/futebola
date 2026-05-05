<template>
  <div>
    <h1 class="text-2xl font-bold mb-2 text-neutral-900">Fontes</h1>
    <p class="text-[#6E6E6E] text-sm mb-6">
      Portais de onde agregamos notícias via RSS. Sempre leia a matéria completa no site original.
    </p>
    <div v-if="loading" class="flex justify-center py-12">
      <span class="loading loading-spinner loading-lg text-[#BAFF39]"></span>
    </div>
    <ul v-else class="space-y-4">
      <li
        v-for="s in sources"
        :key="s.id"
        class="flex flex-wrap items-center justify-between gap-4 p-4 rounded-[var(--radius-box)] border border-[#6E6E6E]/15 bg-white shadow-[var(--shadow-card)]"
      >
        <div>
          <span class="font-semibold text-neutral-900">{{ s.name }}</span>
          <span class="badge badge-ghost ml-2 text-xs">{{ s.category }}</span>
        </div>
        <a
          :href="s.url"
          target="_blank"
          rel="noopener noreferrer"
          class="link link-hover text-sm"
          style="color: #0d9488;"
        >
          {{ s.url }}
        </a>
      </li>
    </ul>
    <p v-if="!loading && sources.length === 0" class="text-[#6E6E6E] text-center py-8">
      Nenhuma fonte cadastrada.
    </p>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { getSources } from '@/api';

const sources = ref([]);
const loading = ref(true);

onMounted(async () => {
  try {
    const res = await getSources();
    sources.value = res.data.data || [];
  } finally {
    loading.value = false;
  }
});
</script>
