<template>
  <article class="card bg-base-100 border border-[#6E6E6E]/15 rounded-[var(--radius-box)] shadow-[var(--shadow-card)] overflow-hidden hover:shadow-md transition-shadow flex flex-col">
    <figure
      v-if="news && news.image_url"
      class="aspect-video w-full overflow-hidden bg-[#6E6E6E]/10 flex-shrink-0 relative"
    >
      <img
        v-show="showImage"
        :src="news.image_url"
        :alt="(news && news.title) || ''"
        class="w-full h-full object-cover"
        loading="lazy"
        @error="showImage = false"
      />
    </figure>
    <div class="card-body p-4 flex flex-col flex-1">
      <BadgeCampeonato v-if="news && news.feed" :name="news.feed.name" class="self-start" />
      <h2 class="card-title text-base font-semibold line-clamp-2 mt-0">
        <RouterLink :to="`/news/${news.id}`" class="hover:text-[#0d9488] transition-colors">
          {{ news ? news.title : '' }}
        </RouterLink>
      </h2>
      <p v-if="hasExcerpt" class="text-sm text-[#6E6E6E] line-clamp-2 flex-1">{{ news.excerpt }}</p>
      <div class="flex items-center justify-between mt-2 text-xs text-[#6E6E6E]">
        <span>{{ news && news.published_at ? formatDate(news.published_at) : '' }}</span>
        <a
          :href="(news && news.link) || '#'"
          target="_blank"
          rel="noopener noreferrer"
          class="link link-hover font-medium"
          style="color: #0d9488;"
        >
          Ler no site original →
        </a>
      </div>
    </div>
  </article>
</template>

<script setup>
import { ref, computed } from 'vue';
import { RouterLink } from 'vue-router';
import BadgeCampeonato from './BadgeCampeonato.vue';

const props = defineProps({
  news: { type: Object, required: true },
});

const showImage = ref(!!(props.news && props.news.image_url));
const hasExcerpt = computed(() => {
  const e = props.news?.excerpt;
  return e && typeof e === 'string' && e.trim() !== '' && e !== 'null';
});

function formatDate(value) {
  if (!value) return '';
  const d = new Date(value);
  return new Intl.DateTimeFormat('pt-BR', { dateStyle: 'short', timeStyle: 'short' }).format(d);
}
</script>
