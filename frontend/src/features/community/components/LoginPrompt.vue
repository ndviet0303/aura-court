<script setup lang="ts">
import { RouterLink, useRoute } from 'vue-router'

defineProps<{ text: string }>()
const route = useRoute()
</script>

<template>
  <p class="login-prompt">
    <span>{{ text }}</span>
    <RouterLink :to="{ name: 'login', query: { redirect: route.fullPath } }">
      {{ $t('nav.login') }}
    </RouterLink>
  </p>
</template>

<style scoped>
.login-prompt {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  margin: 0;
  padding: 12px 14px;
  border: 1px dashed var(--outline);
  border-radius: 12px;
  color: var(--muted);
  font-size: 13px;
}

.login-prompt a {
  color: var(--primary, #6b2d3f);
  font-weight: 700;
}
</style>
