<template>
  <RouterLink
    :to="`/news/${news.id}`"
    class="flex gap-3 p-2 rounded-lg hover:bg-neutral-50 transition-colors group"
  >
    <figure
      v-if="news.image_url && showImage"
      class="w-16 h-16 shrink-0 rounded-md overflow-hidden bg-[#6E6E6E]/10"
    >
      <img
        :src="news.image_url"
        :alt="news.title"
        class="w-full h-full object-cover"
        loading="lazy"
        @error="showImage = false"
      />
    </figure>
    <div
      v-else
      class="w-16 h-16 shrink-0 rounded-md bg-[#BAFF39]/20 flex items-center justify-center"
    >
      <span class="text-lg font-bold text-[#6E6E6E]">{{ (news.title || '?')[0] }}</span>
    </div>
    <div class="flex-1 min-w-0">
      <BadgeCampeonato v-if="news.feed" :name="news.feed.name" class="mb-1" />
      <h3 class="text-sm font-semibold text-neutral-900 line-clamp-2 group-hover:text-[#0d9488] transition-colors">
        {{ news.title }}
      </h3>
      <p v-if="news.published_at" class="text-xs text-[#6E6E6E] mt-1">
        {{ formatDate(news.published_at) }}
      </p>
    </div>
  </RouterLink>
</template>

<script setup>
import { ref } from 'vue';
import { RouterLink } from 'vue-router';
import BadgeCampeonato from './BadgeCampeonato.vue';

defineProps({
  news: { type: Object, required: true },
});

const showImage = ref(true);

function formatDate(value) {
  if (!value) return '';
  const d = new Date(value);
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(d);
}
</script>
