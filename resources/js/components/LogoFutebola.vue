<template>
  <div class="flex items-center gap-2" :class="className">
    <svg v-if="variant === 'icon'" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-8 h-8 flex-shrink-0" aria-hidden="true">
      <circle cx="16" cy="16" r="14" fill="#BAFF39"/>
      <path d="M10 16c0-1.5.4-2.9 1-4.1l2 1.5a5 5 0 0 0 0 5.2l-2 1.5A8 8 0 0 1 10 16z" fill="#1a1a1a"/>
      <circle cx="16" cy="14" r="3" fill="#1a1a1a"/>
      <path d="M22 16a6 6 0 0 1-1 3.4l-2-1.5a5 5 0 0 0 0-5.2l2-1.5A8 8 0 0 1 22 16z" fill="#1a1a1a"/>
    </svg>
    <svg v-else xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" class="w-9 h-9 flex-shrink-0" aria-hidden="true">
      <circle cx="16" cy="16" r="14" fill="#BAFF39"/>
      <path d="M10 16c0-1.5.4-2.9 1-4.1l2 1.5a5 5 0 0 0 0 5.2l-2 1.5A8 8 0 0 1 10 16z" fill="#1a1a1a"/>
      <circle cx="16" cy="14" r="3" fill="#1a1a1a"/>
      <path d="M22 16a6 6 0 0 1-1 3.4l-2-1.5a5 5 0 0 0 0-5.2l2-1.5A8 8 0 0 1 22 16z" fill="#1a1a1a"/>
    </svg>
    <span v-if="variant !== 'icon'" class="font-bold text-xl tracking-tight" style="color: #1a1a1a;">Futebola</span>
  </div>
</template>

<script setup>
defineProps({
  variant: { type: String, default: 'horizontal' }, // 'horizontal' | 'icon'
  className: { type: String, default: '' },
});
</script>
