<template>
  <span
    class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-[#BAFF39] text-neutral-900"
  >
    {{ name }}
  </span>
</template>

<script setup>
defineProps({
  name: { type: String, default: '' },
});
</script>
