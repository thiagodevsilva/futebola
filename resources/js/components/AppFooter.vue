<template>
  <footer class="border-t border-[#6E6E6E]/20 bg-white mt-auto py-6">
    <div class="container mx-auto px-4 text-center text-sm text-[#6E6E6E]">
      <LogoFutebola variant="horizontal" class="justify-center mb-2" />
      <p>Portal agregador de futebol brasileiro. Notícias via RSS · Dados via API-Football.</p>
      <p class="mt-1">© {{ new Date().getFullYear() }} Futebola. Todos os direitos reservados.</p>
    </div>
  </footer>
</template>

<script setup>
import LogoFutebola from './LogoFutebola.vue';
</script>
