<template>
  <header class="border-b border-[#6E6E6E]/20 bg-white shadow-sm sticky top-0 z-50">
    <div class="container mx-auto px-4">
      <div class="flex items-center justify-between h-14 md:h-16">
        <RouterLink to="/" class="flex items-center gap-2 text-inherit hover:opacity-90 transition-opacity">
          <LogoFutebola variant="horizontal" />
        </RouterLink>
        <nav class="flex items-center gap-1 md:gap-4">
          <RouterLink
            v-for="item in navItems"
            :key="item.path"
            :to="item.path"
            class="px-3 py-2 rounded-lg text-sm font-medium transition-colors"
            :class="$route.path === item.path
              ? 'bg-[#BAFF39] text-neutral-900'
              : 'text-[#6E6E6E] hover:bg-neutral-100 hover:text-neutral-800'"
          >
            {{ item.label }}
          </RouterLink>
        </nav>
      </div>
    </div>
  </header>
</template>

<script setup>
import { RouterLink } from 'vue-router';
import LogoFutebola from './LogoFutebola.vue';

const navItems = [
  { path: '/', label: 'Início' },
  { path: '/news', label: 'Notícias' },
  { path: '/sources', label: 'Fontes' },
];
</script>
