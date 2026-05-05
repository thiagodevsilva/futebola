<template>
  <div v-if="loading" class="flex justify-center py-12">
    <span class="loading loading-spinner loading-lg text-[#BAFF39]"></span>
  </div>
  <div v-else-if="item" class="max-w-3xl mx-auto">
    <RouterLink to="/news" class="link link-hover text-sm text-[#6E6E6E] mb-4 inline-block">
      ← Voltar às notícias
    </RouterLink>
    <article class="rounded-[var(--radius-box)] border border-[#6E6E6E]/15 bg-white shadow-[var(--shadow-card)] overflow-hidden">
      <div v-if="item.image_url" class="relative w-full aspect-video overflow-hidden">
        <img
          v-show="showImage"
          :src="item.image_url"
          :alt="item.title"
          class="w-full h-full object-cover"
          @error="showImage = false"
        />
      </div>
      <div class="p-6">
        <BadgeCampeonato v-if="item.feed" :name="item.feed.name" class="mb-2" />
        <h1 class="text-2xl font-bold text-neutral-900 mb-2">{{ item.title }}</h1>
        <p v-if="hasExcerpt" class="text-[#6E6E6E] mb-4">{{ item.excerpt }}</p>
        <div class="flex flex-wrap gap-4 text-sm text-[#6E6E6E]">
          <span>{{ formatDate(item.published_at) }}</span>
          <span v-if="item.author">Por {{ item.author }}</span>
          <span v-if="item.feed">Fonte: {{ item.feed.name }}</span>
        </div>
        <div class="mt-6 pt-4 border-t border-[#6E6E6E]/15">
          <a
            :href="item.link"
            target="_blank"
            rel="noopener noreferrer"
            class="btn bg-[#BAFF39] text-neutral-900 border-0 hover:opacity-90"
          >
            Ler no site original →
          </a>
        </div>
      </div>
    </article>
  </div>
  <div v-else class="text-center py-12 text-[#6E6E6E]">
    Notícia não encontrada.
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue';
import { useRoute, RouterLink } from 'vue-router';
import BadgeCampeonato from '@/components/BadgeCampeonato.vue';
import { getNewsItem } from '@/api';

const route = useRoute();
const item = ref(null);
const loading = ref(true);
const showImage = ref(true);
const hasExcerpt = computed(() => {
  const e = item.value?.excerpt;
  return e && typeof e === 'string' && e.trim() !== '' && e !== 'null';
});

function formatDate(value) {
  if (!value) return '';
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(value));
}

async function fetchItem() {
  loading.value = true;
  item.value = null;
  showImage.value = true;
  try {
    const res = await getNewsItem(route.params.id);
    item.value = res.data.data;
    showImage.value = !!item.value?.image_url;
  } catch {
    item.value = null;
  } finally {
    loading.value = false;
  }
}

onMounted(fetchItem);
watch(() => route.params.id, fetchItem);
</script>
